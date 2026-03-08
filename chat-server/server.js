const express = require('express');
const http = require('http');
const cors = require('cors');
const { Server } = require('socket.io');

const app = express();
const server = http.createServer(app);
const roomMembers = new Map();
const socketPresence = new Map();

const CHAT_PORT = Number(process.env.PORT || 3001);
const CHAT_HOST = process.env.HOST || '0.0.0.0';
const corsOriginEnv = process.env.CORS_ORIGIN;
const corsOrigin = corsOriginEnv
    ? corsOriginEnv.split(',').map((origin) => origin.trim()).filter(Boolean)
    : '*';

app.use(cors({
    origin: corsOrigin,
    methods: ['GET', 'POST'],
}));
app.use(express.json());

const io = new Server(server, {
    cors: {
        origin: corsOrigin,
        methods: ['GET', 'POST'],
    }
});

app.get('/', (req,res)=>{
    res.send("Chat server running");
});

app.get('/health', (req,res)=>{
    res.json({status:"ok"});
});

const normalizeText = (value, fallback = '') => {
    const text = String(value ?? '').trim();
    return text !== '' ? text : fallback;
};

const getTimeStamp = () => {
    return new Date().toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
};

const emitOnlineUsers = (room) => {
    const members = roomMembers.get(room);
    if (!members) {
        io.to(room).emit('online_users', []);
        return;
    }

    const users = Array.from(new Set(Array.from(members.values()).filter(Boolean)));
    io.to(room).emit('online_users', users);
};

const removeSocketFromRoom = (socketId) => {
    const presence = socketPresence.get(socketId);
    if (!presence) return null;

    const members = roomMembers.get(presence.room);
    if (members) {
        members.delete(socketId);
        if (members.size === 0) {
            roomMembers.delete(presence.room);
        }
    }

    socketPresence.delete(socketId);
    return presence;
};

io.on("connection",(socket)=>{
    console.log("User connected");

    socket.on("join_room",(data = {})=>{
        const room = normalizeText(data.room, 'global');
        const user = normalizeText(data.user, 'Anonymous');

        const previousPresence = removeSocketFromRoom(socket.id);
        if (previousPresence?.room) {
            emitOnlineUsers(previousPresence.room);
        }

        socket.join(room);

        if (!roomMembers.has(room)) {
            roomMembers.set(room, new Map());
        }

        roomMembers.get(room).set(socket.id, user);
        socketPresence.set(socket.id, { room, user });

        emitOnlineUsers(room);

        socket.to(room).emit("receive_message",{
            room,
            user:"System",
            message: `${user} joined the room`,
            time: getTimeStamp(),
        });
    });

    socket.on("send_message",(data = {})=>{
        const room = normalizeText(data.room, 'global');
        const user = normalizeText(data.user, 'Anonymous');
        const message = normalizeText(data.message);

        if (message === '') return;

        io.to(room).emit("receive_message",{
            room,
            user,
            message,
            time: normalizeText(data.time, getTimeStamp()),
        });
    });

    socket.on("disconnect",()=>{
        const presence = removeSocketFromRoom(socket.id);
        if (presence?.room) {
            emitOnlineUsers(presence.room);
        }
        console.log("User disconnected");
    });
});

server.listen(CHAT_PORT, CHAT_HOST, () => {
    console.log(`Chat server running on ${CHAT_HOST}:${CHAT_PORT}`);
});
