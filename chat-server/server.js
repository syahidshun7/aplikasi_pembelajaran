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
const DM_CONTACT_LIMIT = 200;

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

const buildDmRoomByUserIds = (userIdA, userIdB) => {
    const first = Math.min(Number(userIdA || 0), Number(userIdB || 0));
    const second = Math.max(Number(userIdA || 0), Number(userIdB || 0));

    if (!Number.isInteger(first) || !Number.isInteger(second) || first <= 0 || second <= 0 || first === second) {
        return '';
    }

    return `dm:${first}-${second}`;
};

const buildUserInboxRoom = (userId) => `user:${Number(userId || 0)}`;

const userDisplayName = (user) => {
    const username = normalizeText(user?.username);
    if (username) return username;
    return normalizeText(user?.name, 'Anonymous');
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

    return loadUserById(userId);
};

const loadUserById = async (userId) => {
    const [rows] = await dbPool.execute(
        `SELECT u.id,
                u.name,
                u.username,
                u.role,
                u.job_id,
                COALESCE(jr.name, u.role, 'General') AS job
         FROM users u
         LEFT JOIN job_roles jr ON jr.id = u.job_id
         WHERE u.deleted_at IS NULL
           AND u.id = ?
         LIMIT 1`,
        [userId]
    );

    if (!rows.length) return null;
    return rows[0];
};

const buildUserRoomCatalog = async (user) => {
    const { room: jobRoomName, job } = buildJobRoom(user?.job);
    const jobRoom = {
        type: 'job',
        key: jobRoomName,
        room: jobRoomName,
        label: `Job: ${job}`,
        meta: {
            job,
        },
    };

    const [classRows] = await dbPool.execute(
        `SELECT sg.uuid,
                sg.name
         FROM group_user gu
         INNER JOIN study_groups sg ON sg.id = gu.study_group_id
         WHERE gu.user_id = ?
           AND gu.deleted_at IS NULL
           AND sg.deleted_at IS NULL
         ORDER BY sg.name ASC`,
        [user.id]
    );

    const classRooms = classRows.map((row) => {
        const groupUuid = normalizeText(row.uuid);
        return {
            type: 'class',
            key: groupUuid,
            room: `class:${groupUuid}`,
            label: `Class: ${normalizeText(row.name, groupUuid)}`,
            meta: {
                study_group_uuid: groupUuid,
                study_group_name: normalizeText(row.name, groupUuid),
            },
        };
    }).filter((room) => room.key !== '');

    const [dmRows] = await dbPool.execute(
        `SELECT DISTINCT u.id,
                u.name,
                u.username
         FROM group_user gu_self
         INNER JOIN group_user gu_peer ON gu_peer.study_group_id = gu_self.study_group_id
         INNER JOIN users u ON u.id = gu_peer.user_id
         INNER JOIN study_groups sg ON sg.id = gu_self.study_group_id
         WHERE gu_self.user_id = ?
           AND gu_self.deleted_at IS NULL
           AND gu_peer.deleted_at IS NULL
           AND gu_peer.user_id != ?
           AND u.deleted_at IS NULL
           AND sg.deleted_at IS NULL
         ORDER BY COALESCE(NULLIF(u.username, ''), u.name) ASC
         LIMIT ${DM_CONTACT_LIMIT}`,
        [user.id, user.id]
    );

    const dmContacts = dmRows.map((row) => {
        const peerId = Number(row.id || 0);
        const peerName = normalizeText(row.name, 'User');
        const peerUsername = normalizeText(row.username);
        return {
            user_id: peerId,
            name: peerName,
            username: peerUsername,
            label: peerUsername || peerName,
        };
    }).filter((contact) => Number.isInteger(contact.user_id) && contact.user_id > 0);

    return {
        job_room: jobRoom,
        class_rooms: classRooms,
        dm_contacts: dmContacts,
    };
};

const resolveRoomAssignment = (catalog, requestData = {}) => {
    const requestedType = normalizeText(requestData.type || requestData.room_type || 'job').toLowerCase();

    if (requestedType === 'class') {
        const requestedUuid = normalizeText(
            requestData.group_uuid || requestData.room_key || requestData.key || requestData.target
        );

        const matchedClassRoom = catalog.class_rooms.find((room) => room.key === requestedUuid);
        if (matchedClassRoom) {
            return matchedClassRoom;
        }

        return null;
    }

    if (requestedType === 'dm') {
        const targetUserId = normalizePositiveInt(requestData.user_id || requestData.target_user_id, null);
        if (!targetUserId) {
            return null;
        }

        const matchedContact = catalog.dm_contacts.find((contact) => contact.user_id === targetUserId);
        if (!matchedContact) {
            return null;
        }

        const roomName = buildDmRoomByUserIds(requestData.current_user_id, targetUserId);
        if (!roomName) {
            return null;
        }

        return {
            type: 'dm',
            key: String(targetUserId),
            room: roomName,
            label: `DM: ${matchedContact.label}`,
            meta: {
                peer_user_id: targetUserId,
                peer_name: matchedContact.name,
                peer_username: matchedContact.username,
            },
        };
    }

    return catalog.job_room;
};

const emitRoomAssignedWithHistory = async (socket, presence) => {
    socket.emit('room_assigned', {
        room: presence.room,
        room_type: presence.roomType,
        room_key: presence.roomKey,
        room_label: presence.roomLabel,
        job: presence.job,
        user: {
            id: presence.userId,
            name: presence.userRealName,
            username: presence.userName,
        },
    });

    const history = await loadRoomMessages({
        room: presence.room,
        limit: MESSAGE_PAGE_SIZE,
    });

    socket.emit('message_history', {
        room: presence.room,
        mode: 'replace',
        ...history,
    });
};

const assignSocketToRoom = async ({ socket, user, roomAssignment }) => {
    const previousPresence = socketPresence.get(socket.id);
    if (previousPresence?.room === roomAssignment.room) {
        await emitRoomAssignedWithHistory(socket, previousPresence);
        return previousPresence;
    }

    const removedPresence = await removeSocketFromRoom(socket, { leaveSocketRoom: true });
    if (removedPresence?.room) {
        await emitOnlineUsers(removedPresence.room);
    }

    await socket.join(roomAssignment.room);

    const nextPresence = {
        room: roomAssignment.room,
        roomType: roomAssignment.type,
        roomKey: roomAssignment.key,
        roomLabel: roomAssignment.label,
        userId: Number(user.id),
        userName: userDisplayName(user),
        userRealName: normalizeText(user.name, userDisplayName(user)),
        job: normalizeText(user.job, 'General'),
    };

    socketPresence.set(socket.id, nextPresence);

    await addOnlineUser(nextPresence.room, nextPresence.userId, nextPresence.userName);
    await emitOnlineUsers(nextPresence.room);
    await emitRoomAssignedWithHistory(socket, nextPresence);

    return nextPresence;
};

const buildCatalogPayload = (catalog) => ({
    job_room: catalog.job_room,
    class_rooms: catalog.class_rooms,
    dm_contacts: catalog.dm_contacts,
});

const buildRoomOptionKey = ({ roomType, roomKey, senderUserId = null, recipientUserId = null }) => {
    if (roomType === 'class') {
        const classKey = normalizeText(roomKey);
        return classKey ? `class:${classKey}` : '';
    }

    if (roomType === 'dm') {
        const senderId = Number(senderUserId || 0);
        const recipientId = Number(recipientUserId || 0);
        if (!senderId || !recipientId) return '';
        const peerId = senderId === recipientId ? null : senderId;
        return peerId ? `dm:${peerId}` : '';
    }

    return 'job';
};

const buildCurrentOptionKeyByPresence = (presence) => {
    if (presence?.roomType === 'class') {
        const roomKey = normalizeText(presence.roomKey);
        return roomKey ? `class:${roomKey}` : 'job';
    }

    if (presence?.roomType === 'dm') {
        const roomKey = normalizeText(presence.roomKey);
        return roomKey ? `dm:${roomKey}` : 'job';
    }

    return 'job';
};

const buildCatalogRoomTargets = (catalog, currentUserId) => {
    const targets = [];

    if (catalog?.job_room?.room) {
        targets.push({
            option_key: 'job',
            room: String(catalog.job_room.room),
        });
    }

    for (const classRoom of (catalog?.class_rooms || [])) {
        const key = normalizeText(classRoom?.key);
        const room = normalizeText(classRoom?.room);
        if (!key || !room) continue;

        targets.push({
            option_key: `class:${key}`,
            room,
        });
    }

    for (const contact of (catalog?.dm_contacts || [])) {
        const peerUserId = Number(contact?.user_id || 0);
        if (!Number.isInteger(peerUserId) || peerUserId <= 0) continue;

        const room = buildDmRoomByUserIds(currentUserId, peerUserId);
        if (!room) continue;

        targets.push({
            option_key: `dm:${peerUserId}`,
            room,
        });
    }

    return targets;
};

const loadUnreadSnapshotForCatalog = async ({ userId, catalog }) => {
    const parsedUserId = Number(userId || 0);
    if (!Number.isInteger(parsedUserId) || parsedUserId <= 0) {
        return {};
    }

    const targets = buildCatalogRoomTargets(catalog, parsedUserId);
    if (!targets.length) return {};

    const uniqueRooms = Array.from(new Set(targets.map((target) => target.room).filter(Boolean)));
    if (!uniqueRooms.length) return {};

    const placeholders = uniqueRooms.map(() => '?').join(',');

    try {
        const [rows] = await dbPool.execute(
            `SELECT m.room,
                    COUNT(*) AS unread_count
             FROM messages m
             LEFT JOIN chat_room_reads rr
               ON rr.user_id = ?
              AND rr.room = m.room
             WHERE m.room IN (${placeholders})
               AND m.user_id <> ?
               AND m.id > COALESCE(rr.last_read_message_id, 0)
             GROUP BY m.room`,
            [parsedUserId, ...uniqueRooms, parsedUserId]
        );

        const unreadByRoom = new Map();
        for (const row of rows) {
            const room = normalizeText(row?.room);
            const unreadCount = Number(row?.unread_count || 0);
            if (!room) continue;
            if (!Number.isFinite(unreadCount) || unreadCount <= 0) continue;
            unreadByRoom.set(room, Math.floor(unreadCount));
        }

        const counts = {};
        for (const target of targets) {
            const roomUnread = unreadByRoom.get(target.room) || 0;
            if (roomUnread <= 0) continue;
            counts[target.option_key] = roomUnread;
        }

        return counts;
    } catch (error) {
        const sqlState = String(error?.code || '');
        if (sqlState === 'ER_NO_SUCH_TABLE') {
            return {};
        }
        throw error;
    }
};

const emitUnreadSnapshotToSocket = async ({ socket, userId, catalog }) => {
    const counts = await loadUnreadSnapshotForCatalog({ userId, catalog });
    socket.emit('unread_snapshot', { counts });
};

const getAudienceUserIdsByRoom = async (presence) => {
    if (!presence?.roomType || !presence?.room) return [];

    if (presence.roomType === 'dm') {
        const selfId = Number(presence.userId || 0);
        const peerId = Number(presence.roomKey || 0);
        const audience = [selfId, peerId].filter((id) => Number.isInteger(id) && id > 0);
        return Array.from(new Set(audience));
    }

    if (presence.roomType === 'class') {
        const classUuid = normalizeText(presence.roomKey);
        if (!classUuid) return [];

        const [rows] = await dbPool.execute(
            `SELECT gu.user_id
             FROM group_user gu
             INNER JOIN study_groups sg ON sg.id = gu.study_group_id
             WHERE sg.uuid = ?
               AND sg.deleted_at IS NULL
               AND gu.deleted_at IS NULL`,
            [classUuid]
        );

        return Array.from(
            new Set(
                rows
                    .map((row) => Number(row.user_id || 0))
                    .filter((id) => Number.isInteger(id) && id > 0)
            )
        );
    }

    const jobName = normalizeText(presence.job, 'General');
    const [rows] = await dbPool.execute(
        `SELECT u.id
         FROM users u
         LEFT JOIN job_roles jr ON jr.id = u.job_id
         WHERE u.deleted_at IS NULL
           AND COALESCE(jr.name, u.role, 'General') = ?`,
        [jobName]
    );

    return Array.from(
        new Set(
            rows
                .map((row) => Number(row.id || 0))
                .filter((id) => Number.isInteger(id) && id > 0)
        )
    );
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

const markRoomAsRead = async ({ userId, room, requestedMessageId = null }) => {
    const parsedUserId = Number(userId || 0);
    const parsedRoom = normalizeText(room);
    if (!Number.isInteger(parsedUserId) || parsedUserId <= 0 || parsedRoom === '') {
        return 0;
    }

    const [rows] = await dbPool.execute(
        'SELECT COALESCE(MAX(id), 0) AS max_id FROM messages WHERE room = ?',
        [parsedRoom]
    );

    const roomMaxId = Number(rows?.[0]?.max_id || 0);
    const requestedId = normalizePositiveInt(requestedMessageId, null);
    const safeLastReadId = requestedId ? Math.min(requestedId, roomMaxId) : roomMaxId;

    try {
        await dbPool.execute(
            `INSERT INTO chat_room_reads (user_id, room, last_read_message_id, last_read_at, created_at, updated_at)
             VALUES (?, ?, ?, NOW(), NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                 last_read_message_id = GREATEST(last_read_message_id, VALUES(last_read_message_id)),
                 last_read_at = VALUES(last_read_at),
                 updated_at = VALUES(updated_at)`,
            [parsedUserId, parsedRoom, safeLastReadId]
        );
    } catch (error) {
        const sqlState = String(error?.code || '');
        if (sqlState !== 'ER_NO_SUCH_TABLE') {
            throw error;
        }
    }

    return safeLastReadId;
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
               COALESCE(NULLIF(u.username, ''), u.name) AS user_name
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

            const inboxRoom = buildUserInboxRoom(user.id);
            if (inboxRoom !== 'user:0') {
                await socket.join(inboxRoom);
            }

            const catalog = await buildUserRoomCatalog(user);

            const roomAssignment = resolveRoomAssignment(catalog, {
                ...data,
                current_user_id: Number(user.id),
            }) || catalog.job_room;

            await assignSocketToRoom({
                socket,
                user,
                roomAssignment,
            });

            socket.emit('room_catalog', buildCatalogPayload(catalog));
            await emitUnreadSnapshotToSocket({
                socket,
                userId: Number(user.id),
                catalog,
            });
        } catch (error) {
            console.error('join_room error:', error);
            socket.emit('server_error', { message: 'Failed to join room' });
        }
    });

    socket.on('switch_room', async (data = {}) => {
        try {
            const presence = socketPresence.get(socket.id);
            if (!presence?.userId) {
                socket.emit('auth_error', { message: 'Join a room first' });
                return;
            }

            const user = await loadUserById(presence.userId);
            if (!user) {
                socket.emit('auth_error', { message: 'User session invalid' });
                return;
            }

            const catalog = await buildUserRoomCatalog(user);
            socket.emit('room_catalog', buildCatalogPayload(catalog));

            const roomAssignment = resolveRoomAssignment(catalog, {
                ...data,
                current_user_id: Number(user.id),
            });

            if (!roomAssignment) {
                socket.emit('room_switch_failed', { message: 'Room tidak ditemukan atau tidak diizinkan.' });
                return;
            }

            await assignSocketToRoom({
                socket,
                user,
                roomAssignment,
            });

            await emitUnreadSnapshotToSocket({
                socket,
                userId: Number(user.id),
                catalog,
            });
        } catch (error) {
            console.error('switch_room error:', error);
            socket.emit('server_error', { message: 'Failed to switch room' });
        }
    });

    socket.on('mark_room_read', async (data = {}) => {
        try {
            const presence = socketPresence.get(socket.id);
            if (!presence?.room || !presence?.userId) return;

            const requestRoom = normalizeText(data.room);
            if (requestRoom && requestRoom !== presence.room) {
                return;
            }

            await markRoomAsRead({
                userId: presence.userId,
                room: presence.room,
                requestedMessageId: data.last_message_id,
            });

            const user = await loadUserById(presence.userId);
            if (!user) return;

            const catalog = await buildUserRoomCatalog(user);
            await emitUnreadSnapshotToSocket({
                socket,
                userId: Number(user.id),
                catalog,
            });

            socket.emit('room_read_ack', {
                room: presence.room,
                option_key: buildCurrentOptionKeyByPresence(presence),
            });
        } catch (error) {
            console.error('mark_room_read error:', error);
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

            const audienceUserIds = await getAudienceUserIdsByRoom(presence);
            for (const recipientUserId of audienceUserIds) {
                if (Number(recipientUserId) === Number(presence.userId)) {
                    continue;
                }

                const roomOptionKey = buildRoomOptionKey({
                    roomType: presence.roomType,
                    roomKey: presence.roomKey,
                    senderUserId: presence.userId,
                    recipientUserId,
                });

                io.to(buildUserInboxRoom(recipientUserId)).emit('room_activity', {
                    message_id: insertedId,
                    room: presence.room,
                    room_type: presence.roomType,
                    room_key: presence.roomKey,
                    room_label: presence.roomLabel,
                    room_option_key: roomOptionKey,
                    sender_user_id: presence.userId,
                    sender_user_name: presence.userName,
                    message_preview: message.slice(0, 80),
                    created_at: now.toISOString(),
                });
            }

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
