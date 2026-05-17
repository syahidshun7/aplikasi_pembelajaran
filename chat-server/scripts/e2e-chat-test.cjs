const { io } = require('socket.io-client');

const CHAT_URL = process.env.CHAT_URL || 'http://127.0.0.1:3001';
const SOCKET_PATH = process.env.SOCKET_PATH || '/socket.io';
const TEST_USER_ID = process.env.TEST_USER_ID || '48';
const TEST_MESSAGE = process.env.TEST_MESSAGE || `E2E test message ${Date.now()}`;
const TIMEOUT_MS = Number(process.env.TIMEOUT_MS || 8000);

const socket = io(CHAT_URL, {
    path: SOCKET_PATH,
    transports: ['websocket'],
    auth: {
        token: `user:${TEST_USER_ID}`,
    },
});

const fail = (message, detail) => {
    if (detail) {
        console.error(message, detail);
    } else {
        console.error(message);
    }
    socket.disconnect();
    process.exit(1);
};

const timeout = setTimeout(() => {
    fail(`Timeout after ${TIMEOUT_MS}ms`);
}, TIMEOUT_MS);

socket.on('connect', () => {
    socket.emit('join_room', {
        token: `user:${TEST_USER_ID}`,
    });
});

socket.on('auth_error', (payload) => {
    fail('auth_error', payload);
});

socket.on('server_error', (payload) => {
    fail('server_error', payload);
});

socket.on('room_assigned', (payload) => {
    console.log('room_assigned:', payload);
    socket.emit('send_message', {
        message: TEST_MESSAGE,
    });
});

socket.on('receive_message', (payload) => {
    if (payload?.message === TEST_MESSAGE) {
        clearTimeout(timeout);
        console.log('receive_message:', payload);
        socket.disconnect();
        process.exit(0);
    }
});
