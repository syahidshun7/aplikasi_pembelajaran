const express = require('express');
const http = require('http');
const path = require('path');
const cors = require('cors');
const dotenv = require('dotenv');
const mysql = require('mysql2/promise');
const { createClient } = require('redis');
const { Server } = require('socket.io');
const { createAdapter } = require('@socket.io/redis-adapter');

// Load chat-server/.env first, then fallback to project root .env (Laravel style).
dotenv.config({ path: path.resolve(__dirname, '.env') });
dotenv.config({ path: path.resolve(__dirname, '..', '.env') });

/**
 * Environment configuration
 */
const CHAT_PORT = Number(process.env.PORT || 3001);
const CHAT_HOST = process.env.HOST || '0.0.0.0';
const REDIS_HOST = process.env.REDIS_HOST || '127.0.0.1';
const REDIS_PORT = Number(process.env.REDIS_PORT || 6379);
const REDIS_URL = process.env.REDIS_URL || `redis://${REDIS_HOST}:${REDIS_PORT}`;
const REDIS_SOCKET = String(process.env.REDIS_SOCKET || '').trim() || null;
const REDIS_USERNAME = String(process.env.REDIS_USERNAME || '').trim() || null;
const REDIS_PASSWORD = String(process.env.REDIS_PASSWORD || '').trim() || null;
const REDIS_DB = Number(process.env.REDIS_DB || 0);
const DB_HOST = process.env.DB_HOST || '127.0.0.1';
const DB_PORT = Number(process.env.DB_PORT || 3306);
const DB_USER = process.env.DB_USER || process.env.DB_USERNAME || 'root';
const DB_PASS = process.env.DB_PASS ?? process.env.DB_PASSWORD ?? '';
const DB_NAME = process.env.DB_NAME || process.env.DB_DATABASE || 'chat';
const SOCKET_PATH = process.env.SOCKET_PATH || '/socket.io';
const corsOriginEnv = process.env.CORS_ORIGIN;
const corsOrigin = corsOriginEnv
    ? corsOriginEnv.split(',').map((origin) => origin.trim()).filter(Boolean)
    : '*';

const RATE_LIMIT_MAX = 5;
const RATE_LIMIT_WINDOW_SECONDS = 1;
const MESSAGE_PAGE_SIZE = 10;
const MESSAGE_PAGE_MAX = 50;

/**
 * App + server bootstrap
 */
const app = express();
const server = http.createServer(app);
const io = new Server(server, {
    path: SOCKET_PATH,
    cors: {
        origin: corsOrigin,
        methods: ['GET', 'POST'],
    },
});

app.use(cors({
    origin: corsOrigin,
    methods: ['GET', 'POST'],
}));
app.use(express.json());

/**
 * In-memory socket presence is local to one Node process.
 * Shared states (online users, rate limits) are stored in Redis.
 */





const socketPresence = new Map();

let dbPool;
let redisPub;
let redisSub;

const normalizeText = (value, fallback = '') => {
    const text = String(value ?? '').trim();
    return text !== '' ? text : fallback;
};

const normalizePositiveInt = (value, fallback, max = null) => {
    const num = Number(value);
    if (!Number.isInteger(num) || num <= 0) return fallback;
    if (Number.isInteger(max) && num > max) return max;
    return num;
};

const buildRedisClientOptions = () => {
    const options = {};

    if (REDIS_SOCKET) {
        options.socket = { path: REDIS_SOCKET };
    } else {
        options.url = REDIS_URL;
    }

    if (REDIS_USERNAME) {
        options.username = REDIS_USERNAME;
    }

    if (REDIS_PASSWORD) {
        options.password = REDIS_PASSWORD;
    }

    if (Number.isInteger(REDIS_DB) && REDIS_DB >= 0) {
        options.database = REDIS_DB;
    }

    return options;
};

const getTimeStamp = (date = new Date()) => {
    return date.toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
};

const slugify = (value, fallback = 'general') => {
    const base = normalizeText(value, fallback).toLowerCase();
    const slug = base
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
    return slug || fallback;
};

const buildJobRoom = (jobValue) => {
    const job = normalizeText(jobValue, 'General');
    return {
        job,
        room: `job:${slugify(job, 'general')}`,
    };
};

const onlineUsersKey = (room) => `online:room:${room}:users`;
const onlineUserCounterKey = (room, userId) => `online:room:${room}:user:${userId}:count`;
const rateLimitKey = (userId, epochSecond) => `rate_limit:user:${userId}:${epochSecond}`;

/**
 * Token strategy for this server:
 * - Accepts "Bearer <token>" or raw "<token>"
 * - Current token format: "user:<id>" or "<id>"
 * Replace this parser with real JWT/session validation if needed.
 */
const extractToken = (socket, payloadToken) => {
    const fromPayload = normalizeText(payloadToken);
    if (fromPayload) return fromPayload;

    const fromAuth = normalizeText(socket.handshake?.auth?.token);
    if (fromAuth) return fromAuth;

    return normalizeText(socket.handshake?.headers?.authorization);
};

const parseUserIdFromToken = (rawToken) => {
    const token = normalizeText(rawToken);
    if (!token) return null;

    const normalized = token.toLowerCase().startsWith('bearer ')
        ? token.slice(7).trim()
        : token;

    const prefixedMatch = normalized.match(/^user:(\d+)$/i);
    const numericMatch = normalized.match(/^(\d+)$/);
    const value = prefixedMatch?.[1] || numericMatch?.[1];
    if (!value) return null;

    const userId = Number(value);
    if (!Number.isInteger(userId) || userId <= 0) return null;
    return userId;
};

const validateTokenAndLoadUser = async (token) => {
    const userId = parseUserIdFromToken(token);
    if (!userId) return null;

    const [rows] = await dbPool.execute(
        `SELECT u.id,
                u.name,
                COALESCE(jr.name, u.role, 'General') AS job
         FROM users u
         LEFT JOIN job_roles jr ON jr.id = u.job_id
         WHERE u.id = ?
         LIMIT 1`,
        [userId]
    );

    if (!rows.length) return null;
    return rows[0];
};

const addOnlineUser = async (room, userId, userName) => {
    const multi = redisPub.multi();
    multi.hSet(onlineUsersKey(room), String(userId), userName);
    multi.incr(onlineUserCounterKey(room, userId));
    await multi.exec();
};

const removeOnlineUser = async (room, userId) => {
    const counterKey = onlineUserCounterKey(room, userId);
    const remaining = await redisPub.decr(counterKey);

    if (remaining <= 0) {
        const multi = redisPub.multi();
        multi.del(counterKey);
        multi.hDel(onlineUsersKey(room), String(userId));
        await multi.exec();
    }
};

const emitOnlineUsers = async (room) => {
    const userMap = await redisPub.hGetAll(onlineUsersKey(room));
    const users = Object.values(userMap).filter(Boolean);
    io.to(room).emit('online_users', users);
};

const removeSocketFromRoom = async (socket, options = {}) => {
    const { leaveSocketRoom = false } = options;
    const presence = socketPresence.get(socket.id);
    if (!presence) return null;

    socketPresence.delete(socket.id);

    if (leaveSocketRoom && presence.room) {
        await socket.leave(presence.room);
    }

    if (presence.room && presence.userId) {
        await removeOnlineUser(presence.room, presence.userId);
    }

    return presence;
};

const isRateLimited = async (userId) => {
    const nowSecond = Math.floor(Date.now() / 1000);
    const key = rateLimitKey(userId, nowSecond);
    const currentCount = await redisPub.incr(key);
    if (currentCount === 1) {
        await redisPub.expire(key, RATE_LIMIT_WINDOW_SECONDS);
    }

    return currentCount > RATE_LIMIT_MAX;
};

const saveMessage = async (userId, room, message) => {
    const [result] = await dbPool.execute(
        'INSERT INTO messages (user_id, room, message, created_at) VALUES (?, ?, ?, NOW())',
        [userId, room, message]
    );

    return result.insertId;
};

const loadRoomMessages = async ({ room, beforeId = null, limit = MESSAGE_PAGE_SIZE }) => {
    const pageLimit = normalizePositiveInt(limit, MESSAGE_PAGE_SIZE, MESSAGE_PAGE_MAX);
    const cursor = normalizePositiveInt(beforeId, null);
    const sqlLimit = pageLimit + 1;

    const baseSql = `
        SELECT m.id,
               m.user_id,
               m.room,
               m.message,
               m.created_at,
               u.name AS user_name
        FROM messages m
        LEFT JOIN users u ON u.id = m.user_id
        WHERE m.room = ?
    `;

    const withCursorSql = `
        ${baseSql}
          AND m.id < ?
        ORDER BY m.id DESC
        LIMIT ${sqlLimit}
    `;

    const noCursorSql = `
        ${baseSql}
        ORDER BY m.id DESC
        LIMIT ${sqlLimit}
    `;

    const [rows] = cursor
        ? await dbPool.execute(withCursorSql, [room, cursor])
        : await dbPool.execute(noCursorSql, [room]);

    const hasMore = rows.length > pageLimit;
    const pageRows = hasMore ? rows.slice(0, pageLimit) : rows;

    const messages = pageRows
        .reverse()
        .map((row) => {
            const createdAt = row.created_at ? new Date(row.created_at) : new Date();
            return {
                id: row.id,
                user_id: row.user_id,
                user: normalizeText(row.user_name, 'Unknown'),
                room: row.room,
                message: normalizeText(row.message),
                time: getTimeStamp(createdAt),
                created_at: createdAt.toISOString(),
            };
        });

    const nextBeforeId = messages.length ? messages[0].id : null;

    return {
        messages,
        has_more: hasMore,
        next_before_id: nextBeforeId,
    };
};

app.get('/', (req, res) => {
    res.send('Chat server running');
});

app.get('/health', async (req, res) => {
    const checks = {
        database: false,
        redis: false,
    };

    try {
        await dbPool.query('SELECT 1');
        checks.database = true;
    } catch (error) {
        checks.database = false;
    }

    try {
        await redisPub.ping();
        checks.redis = true;
    } catch (error) {
        checks.redis = false;
    }

    const isHealthy = checks.database && checks.redis;
    res.status(isHealthy ? 200 : 503).json({
        status: isHealthy ? 'ok' : 'degraded',
        checks,
    });
});

/**
 * Socket architecture:
 * 1) join_room validates token against DB user.
 * 2) room is assigned from user.job (server-side authority).
 * 3) send_message is rate-limited via Redis.
 * 4) message is persisted in MySQL before broadcast.
 * 5) online users list is shared through Redis keys.
 */
io.on('connection', (socket) => {
    console.log(`User connected: ${socket.id}`);

    socket.on('join_room', async (data = {}) => {
        try {
            const token = extractToken(socket, data.token);
            const user = await validateTokenAndLoadUser(token);
            if (!user) {
                socket.emit('auth_error', { message: 'Invalid token or user not found' });
                return;
            }

            const { room, job } = buildJobRoom(user.job);

            const previousPresence = await removeSocketFromRoom(socket, { leaveSocketRoom: true });
            if (previousPresence?.room) {
                await emitOnlineUsers(previousPresence.room);
            }

            await socket.join(room);

            socketPresence.set(socket.id, {
                room,
                userId: user.id,
                userName: user.name,
                job,
            });

            await addOnlineUser(room, user.id, user.name);
            await emitOnlineUsers(room);

            socket.emit('room_assigned', {
                room,
                job,
                user: {
                    id: user.id,
                    name: user.name,
                },
            });

            const history = await loadRoomMessages({
                room,
                limit: MESSAGE_PAGE_SIZE,
            });

            socket.emit('message_history', {
                room,
                mode: 'replace',
                ...history,
            });
        } catch (error) {
            console.error('join_room error:', error);
            socket.emit('server_error', { message: 'Failed to join room' });
        }
    });

    socket.on('send_message', async (data = {}) => {
        try {
            const presence = socketPresence.get(socket.id);
            if (!presence?.room || !presence?.userId) {
                socket.emit('auth_error', { message: 'Join a room first' });
                return;
            }

            const message = normalizeText(data.message);
            if (!message) return;

            const limited = await isRateLimited(presence.userId);
            if (limited) {
                socket.emit('rate_limit', {
                    message: 'Too many messages. Max 5 per second.',
                    max: RATE_LIMIT_MAX,
                    window_seconds: RATE_LIMIT_WINDOW_SECONDS,
                });
                return;
            }

            const insertedId = await saveMessage(presence.userId, presence.room, message);
            const now = new Date();

            io.to(presence.room).emit('receive_message', {
                id: insertedId,
                room: presence.room,
                user: presence.userName,
                user_id: presence.userId,
                message,
                time: getTimeStamp(now),
                created_at: now.toISOString(),
            });

            socket.to(presence.room).emit('typing_status', {
                room: presence.room,
                user: presence.userName,
                user_id: presence.userId,
                socket_id: socket.id,
                is_typing: false,
            });
        } catch (error) {
            console.error('send_message error:', error);
            socket.emit('server_error', { message: 'Failed to send message' });
        }
    });

    socket.on('typing', (data = {}) => {
        try {
            const presence = socketPresence.get(socket.id);
            if (!presence?.room || !presence?.userId) return;

            const isTyping = Boolean(data.is_typing);

            socket.to(presence.room).emit('typing_status', {
                room: presence.room,
                user: presence.userName,
                user_id: presence.userId,
                socket_id: socket.id,
                is_typing: isTyping,
            });
        } catch (error) {
            console.error('typing error:', error);
        }
    });

    socket.on('load_messages', async (data = {}) => {
        try {
            const presence = socketPresence.get(socket.id);
            if (!presence?.room) {
                socket.emit('auth_error', { message: 'Join a room first' });
                return;
            }

            const beforeId = normalizePositiveInt(data.before_id, null);
            const limit = normalizePositiveInt(data.limit, MESSAGE_PAGE_SIZE, MESSAGE_PAGE_MAX);

            const history = await loadRoomMessages({
                room: presence.room,
                beforeId,
                limit,
            });

            socket.emit('message_history', {
                room: presence.room,
                mode: beforeId ? 'prepend' : 'replace',
                ...history,
            });
        } catch (error) {
            console.error('load_messages error:', error);
            socket.emit('server_error', { message: 'Failed to load message history' });
        }
    });

    socket.on('disconnect', async () => {
        try {
            const presence = await removeSocketFromRoom(socket);
            if (presence?.room) {
                io.to(presence.room).emit('typing_status', {
                    room: presence.room,
                    user: presence.userName,
                    user_id: presence.userId,
                    socket_id: socket.id,
                    is_typing: false,
                });
                await emitOnlineUsers(presence.room);
            }
        } catch (error) {
            console.error('disconnect error:', error);
        }

        console.log(`User disconnected: ${socket.id}`);
    });
});

const bootstrap = async () => {
    dbPool = mysql.createPool({
        host: DB_HOST,
        port: DB_PORT,
        user: DB_USER,
        password: DB_PASS,
        database: DB_NAME,
        waitForConnections: true,
        connectionLimit: 10,
        queueLimit: 0,
    });

    await dbPool.query('SELECT 1');

    const redisClientOptions = buildRedisClientOptions();
    redisPub = createClient(redisClientOptions);
    redisSub = redisPub.duplicate();

    redisPub.on('error', (error) => {
        console.error('Redis pub error:', error);
    });

    redisSub.on('error', (error) => {
        console.error('Redis sub error:', error);
    });

    await Promise.all([
        redisPub.connect(),
        redisSub.connect(),
    ]);

    const redisMode = REDIS_SOCKET ? `socket:${REDIS_SOCKET}` : `url:${REDIS_URL}`;
    console.log(`Redis connected via ${redisMode}`);

    io.adapter(createAdapter(redisPub, redisSub));

    server.listen(CHAT_PORT, CHAT_HOST, () => {
        console.log(`Chat server running on ${CHAT_HOST}:${CHAT_PORT} with path ${SOCKET_PATH}`);
    });
};

const shutdown = async () => {
    try {
        await Promise.allSettled([
            redisPub?.quit(),
            redisSub?.quit(),
            dbPool?.end(),
        ]);
    } finally {
        process.exit(0);
    }
};

process.on('SIGINT', shutdown);
process.on('SIGTERM', shutdown);

bootstrap().catch((error) => {
    console.error('Failed to bootstrap chat server:', error);
    process.exit(1);
});
