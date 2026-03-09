<script setup>
import { io } from 'socket.io-client';
import { usePage } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

const messages = ref([]);
const onlineUsers = ref([]);
const messageInput = ref('');
const chatContainer = ref(null);
const activeRoom = ref('assigning...');
const isConnected = ref(false);
const rateLimitNotice = ref('');
const hasMoreHistory = ref(true);
const isLoadingHistory = ref(false);
const historyCursorId = ref(null);
const typingUsers = ref([]);
const socketClientId = ref(null);

const userName = ref('Anonymous');
const userId = ref(null);
const page = usePage();
const authUser = computed(() => page.props?.auth?.user || null);
let socket = null;
let historyRequestTimer = null;
let typingStopTimer = null;
const MESSAGE_PAGE_SIZE = 10;
const TYPING_STOP_DELAY_MS = 1200;

const getChatSocketPath = () => {
    const configuredPath = String(import.meta.env.VITE_CHAT_SOCKET_PATH || '').trim();
    if (configuredPath === '') {
        return '/socket.io';
    }

    return configuredPath.startsWith('/') ? configuredPath : `/${configuredPath}`;
};

const getChatServerUrl = () => {
    const configuredUrl = String(import.meta.env.VITE_CHAT_SERVER_URL || '').trim();

    if (typeof window !== 'undefined') {
        const host = String(window.location.hostname || '').toLowerCase();
        const isLocalHost = host === 'localhost' || host === '127.0.0.1';

        // In local development/testing, always point to local chat server.
        if (isLocalHost) {
            return `${window.location.protocol}//${window.location.hostname}:3001`;
        }

        if (configuredUrl !== '') {
            return configuredUrl.replace(/\/+$/, '');
        }

        if (window.location.protocol === 'https:') {
            // In production HTTPS, socket is usually exposed behind reverse proxy on same origin.
            return window.location.origin;
        }

        return `${window.location.protocol}//${window.location.hostname}:3001`;
    }

    if (configuredUrl !== '') {
        return configuredUrl.replace(/\/+$/, '');
    }

    return 'http://localhost:3001';
};

const normalizeIdentity = (value) => String(value || '').trim().toLowerCase();

const resolveUserName = () => {
    const pageUser = authUser.value;
    if (pageUser) {
        if (typeof pageUser.username === 'string' && pageUser.username.trim() !== '') {
            return pageUser.username.trim();
        }

        if (typeof pageUser.name === 'string' && pageUser.name.trim() !== '') {
            return pageUser.name.trim();
        }
    }

    const bladeUser = window?.Laravel?.user;

    if (typeof bladeUser === 'string' && bladeUser.trim() !== '') {
        return bladeUser.trim();
    }

    if (bladeUser && typeof bladeUser.username === 'string' && bladeUser.username.trim() !== '') {
        return bladeUser.username.trim();
    }

    if (bladeUser && typeof bladeUser.name === 'string' && bladeUser.name.trim() !== '') {
        return bladeUser.name.trim();
    }

    return 'Anonymous';
};

const resolveUserId = () => {
    const pageUser = authUser.value;
    const parsedId = Number(pageUser?.id);
    if (Number.isInteger(parsedId) && parsedId > 0) {
        return parsedId;
    }

    return null;
};

const buildToken = () => {
    const currentUserId = resolveUserId();
    if (!currentUserId) return '';
    return `user:${currentUserId}`;
};

const fallbackTime = () => {
    return new Date().toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
};

const toDisplayTime = (dateLike) => {
    const date = new Date(dateLike);
    if (Number.isNaN(date.getTime())) {
        return fallbackTime();
    }

    return date.toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
};

const normalizeMessage = (payload = {}) => {
    const sender = String(payload.user || 'Anonymous');
    const senderId = Number(payload.user_id || 0);
    const messageId = Number(payload.id || 0);
    const createdAt = payload.created_at ? String(payload.created_at) : null;
    const mineById = userId.value && senderId ? senderId === userId.value : false;
    return {
        id: Number.isInteger(messageId) && messageId > 0 ? messageId : null,
        room: String(payload.room || activeRoom.value || 'unknown'),
        user: sender,
        message: String(payload.message || ''),
        time: String(payload.time || (createdAt ? toDisplayTime(createdAt) : fallbackTime())),
        created_at: createdAt,
        isMine: mineById || normalizeIdentity(sender) === normalizeIdentity(userName.value),
    };
};

const isPresenceSystemMessage = (payload = {}) => {
    const sender = normalizeIdentity(payload.user || '');
    if (sender !== 'system') return false;

    const message = String(payload.message || '').toLowerCase();
    return /joined\s*\(|left the room/.test(message);
};

const scrollToBottom = async () => {
    await nextTick();
    if (!chatContainer.value) return;
    chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
};

const isMe = (payloadUser = '', payloadUserId = null, payloadSocketId = null) => {
    const senderSocketId = String(payloadSocketId || '').trim();
    if (socketClientId.value && senderSocketId) {
        return senderSocketId === socketClientId.value;
    }

    const senderId = Number(payloadUserId || 0);
    if (userId.value && senderId) return senderId === userId.value;
    return normalizeIdentity(payloadUser) === normalizeIdentity(userName.value);
};

const removeTypingUser = (payloadUser = '', payloadUserId = null) => {
    const senderId = Number(payloadUserId || 0);
    const senderName = normalizeIdentity(payloadUser);

    typingUsers.value = typingUsers.value.filter((item) => {
        const itemId = Number(item.user_id || 0);
        const itemName = normalizeIdentity(item.user || '');

        if (senderId && itemId) return itemId !== senderId;
        return itemName !== senderName;
    });
};

const resetTypingState = () => {
    typingUsers.value = [];
};

const emitTypingState = (isTyping) => {
    if (!socket || !isConnected.value) return;
    socket.emit('typing', { is_typing: Boolean(isTyping) });
};

const stopTyping = () => {
    if (typingStopTimer) {
        clearTimeout(typingStopTimer);
        typingStopTimer = null;
    }
    emitTypingState(false);
};

const handleTypingInput = () => {
    if (!socket || !isConnected.value) return;

    emitTypingState(true);

    if (typingStopTimer) {
        clearTimeout(typingStopTimer);
    }

    typingStopTimer = setTimeout(() => {
        emitTypingState(false);
        typingStopTimer = null;
    }, TYPING_STOP_DELAY_MS);
};

const handleTypingKeydown = () => {
    if (!socket || !isConnected.value) return;
    emitTypingState(true);
};

const typingUsersLabel = computed(() => {
    if (typingUsers.value.length === 0) return '';

    if (typingUsers.value.length === 1) {
        return `${typingUsers.value[0].user} sedang mengetik...`;
    }

    const first = typingUsers.value[0].user;
    const restCount = typingUsers.value.length - 1;
    return `${first} dan ${restCount} lainnya sedang mengetik...`;
});

const handleDocumentVisibilityChange = () => {
    if (typeof document === 'undefined') return;
    if (document.hidden) {
        stopTyping();
    }
};

const handleWindowBlur = () => {
    stopTyping();
};

const handleReceiveMessage = async (payload = {}) => {
    if (isPresenceSystemMessage(payload)) return;

    const parsed = normalizeMessage(payload);
    if (parsed.message.trim() === '') return;
    if (parsed.id && messages.value.some((item) => item.id === parsed.id)) return;

    if (activeRoom.value !== 'assigning...' && parsed.room !== activeRoom.value) {
        return;
    }

    messages.value.push(parsed);
    removeTypingUser(parsed.user, parsed.user_id);
    await scrollToBottom();
};

const requestHistory = (beforeId = null) => {
    if (!socket || !isConnected.value || isLoadingHistory.value) return;

    isLoadingHistory.value = true;

    if (historyRequestTimer) {
        clearTimeout(historyRequestTimer);
    }

    historyRequestTimer = setTimeout(() => {
        isLoadingHistory.value = false;
    }, 4000);

    const payload = { limit: MESSAGE_PAGE_SIZE };
    const cursor = Number(beforeId);
    if (Number.isInteger(cursor) && cursor > 0) {
        payload.before_id = cursor;
    }

    socket.emit('load_messages', payload);
};

const handleHistoryScroll = () => {
    if (!chatContainer.value) return;
    if (!hasMoreHistory.value || isLoadingHistory.value) return;
    if (chatContainer.value.scrollTop > 24) return;

    const cursor = Number(historyCursorId.value || 0);
    if (!Number.isInteger(cursor) || cursor <= 0) {
        hasMoreHistory.value = false;
        return;
    }

    requestHistory(cursor);
};

const handleOnlineUsers = (payload = []) => {
    let users = [];

    if (Array.isArray(payload)) {
        users = payload;
    } else if (payload && Array.isArray(payload.users)) {
        users = payload.users;
    }

    onlineUsers.value = Array.from(
        new Set(
            users
                .map((user) => String(user || '').trim())
                .filter(Boolean)
        )
    );

    if (activeRoom.value === 'assigning...' && onlineUsers.value.length > 0) {
        activeRoom.value = 'global';
    }
};

const handleRoomAssigned = (payload = {}) => {
    const nextRoom = String(payload.room || '').trim();
    if (nextRoom !== '') {
        activeRoom.value = nextRoom;
    }

    messages.value = [];
    resetTypingState();
    hasMoreHistory.value = true;
    isLoadingHistory.value = false;
    historyCursorId.value = null;
    requestHistory();
};

const pushSystemNotice = async (text) => {
    messages.value.push({
        room: activeRoom.value,
        user: 'System',
        message: String(text || ''),
        time: fallbackTime(),
        isMine: false,
    });
    await scrollToBottom();
};

const sendMessage = () => {
    const plainMessage = messageInput.value.trim();
    if (!socket || plainMessage === '') return;

    socket.emit('send_message', {
        message: plainMessage,
    });
    stopTyping();
    messageInput.value = '';
};

const handleTypingStatus = (payload = {}) => {
    const typingRoom = String(payload.room || '').trim();
    if (activeRoom.value !== 'assigning...' && typingRoom && typingRoom !== activeRoom.value) {
        return;
    }

    const typingUser = String(payload.user || '').trim();
    const typingUserId = Number(payload.user_id || 0);
    const typingSocketId = String(payload.socket_id || '').trim();
    const isTyping = Boolean(payload.is_typing);
    if (!typingUser) return;
    if (isMe(typingUser, typingUserId, typingSocketId)) return;

    if (!isTyping) {
        removeTypingUser(typingUser, typingUserId);
        return;
    }

    const existingIndex = typingUsers.value.findIndex((item) => {
        const itemId = Number(item.user_id || 0);
        if (typingUserId && itemId) return itemId === typingUserId;
        return normalizeIdentity(item.user || '') === normalizeIdentity(typingUser);
    });

    if (existingIndex >= 0) {
        typingUsers.value[existingIndex] = {
            ...typingUsers.value[existingIndex],
            user: typingUser,
            user_id: typingUserId || null,
        };
        return;
    }

    typingUsers.value.push({
        user: typingUser,
        user_id: typingUserId || null,
    });
};

const handleMessageHistory = async (payload = {}) => {
    const historyRoom = String(payload.room || '').trim();
    if (activeRoom.value !== 'assigning...' && historyRoom && historyRoom !== activeRoom.value) {
        isLoadingHistory.value = false;
        return;
    }

    const incomingRaw = Array.isArray(payload.messages) ? payload.messages : [];
    const incomingParsed = incomingRaw
        .map((item) => normalizeMessage(item))
        .filter((item) => item.message.trim() !== '')
        .filter((item) => !isPresenceSystemMessage(item));

    const mode = String(payload.mode || 'replace');
    let incoming = incomingParsed;

    if (mode === 'prepend') {
        const existingIds = new Set(
            messages.value
                .map((item) => item.id)
                .filter((id) => Number.isInteger(id) && id > 0)
        );

        incoming = incomingParsed.filter((item) => !item.id || !existingIds.has(item.id));
    }

    if (mode === 'prepend') {
        const prevHeight = chatContainer.value?.scrollHeight || 0;
        const prevTop = chatContainer.value?.scrollTop || 0;

        messages.value = [...incoming, ...messages.value];
        await nextTick();

        if (chatContainer.value) {
            const newHeight = chatContainer.value.scrollHeight;
            chatContainer.value.scrollTop = prevTop + (newHeight - prevHeight);
        }
    } else {
        messages.value = incoming;
        await scrollToBottom();
    }

    hasMoreHistory.value = Boolean(payload.has_more);

    const nextBeforeId = Number(payload.next_before_id || 0);
    if (Number.isInteger(nextBeforeId) && nextBeforeId > 0) {
        historyCursorId.value = nextBeforeId;
    } else {
        const oldest = messages.value[0];
        historyCursorId.value = oldest?.id || null;
    }

    if (historyRequestTimer) {
        clearTimeout(historyRequestTimer);
        historyRequestTimer = null;
    }

    isLoadingHistory.value = false;
};

onMounted(() => {
    userName.value = resolveUserName();
    userId.value = resolveUserId();
    const token = buildToken();

    if (!token) {
        pushSystemNotice('Autentikasi chat tidak valid. Silakan login ulang.');
        return;
    }

    socket = io(getChatServerUrl(), {
        path: getChatSocketPath(),
        transports: ['polling', 'websocket'],
        tryAllTransports: true,
        auth: { token },
    });

    socket.on('connect', () => {
        isConnected.value = true;
        socketClientId.value = String(socket.id || '');
        rateLimitNotice.value = '';
        resetTypingState();
        socket.emit('join_room', {
            token,
            user: userName.value,
            room: 'global',
        });
    });

    socket.on('disconnect', () => {
        isConnected.value = false;
        socketClientId.value = null;
        resetTypingState();
    });

    socket.on('room_assigned', handleRoomAssigned);
    socket.on('message_history', handleMessageHistory);
    socket.on('receive_message', handleReceiveMessage);
    socket.on('online_users', handleOnlineUsers);
    socket.on('typing_status', handleTypingStatus);
    socket.on('rate_limit', async (payload = {}) => {
        rateLimitNotice.value = String(payload.message || 'Rate limit exceeded');
        await pushSystemNotice(rateLimitNotice.value);
    });
    socket.on('auth_error', async (payload = {}) => {
        await pushSystemNotice(String(payload.message || 'Auth error'));
    });
    socket.on('server_error', async (payload = {}) => {
        await pushSystemNotice(String(payload.message || 'Server error'));
    });

    if (typeof document !== 'undefined') {
        document.addEventListener('visibilitychange', handleDocumentVisibilityChange);
    }

    if (typeof window !== 'undefined') {
        window.addEventListener('blur', handleWindowBlur);
    }
});

onBeforeUnmount(() => {
    if (!socket) return;
    socket.off('room_assigned', handleRoomAssigned);
    socket.off('message_history', handleMessageHistory);
    socket.off('receive_message', handleReceiveMessage);
    socket.off('online_users', handleOnlineUsers);
    socket.off('typing_status', handleTypingStatus);
    stopTyping();
    socket.disconnect();
    socket = null;

    if (historyRequestTimer) {
        clearTimeout(historyRequestTimer);
        historyRequestTimer = null;
    }

    if (typingStopTimer) {
        clearTimeout(typingStopTimer);
        typingStopTimer = null;
    }

    if (typeof document !== 'undefined') {
        document.removeEventListener('visibilitychange', handleDocumentVisibilityChange);
    }

    if (typeof window !== 'undefined') {
        window.removeEventListener('blur', handleWindowBlur);
    }
});
</script>

<template>
    <div class="rpg-panel border-cyan-500/40 flex flex-col bg-[#1a1c2c]/90 backdrop-blur-sm">
        <div class="flex justify-between items-center mb-4 border-b border-cyan-900 pb-2 flex-shrink-0">
            <h2 class="text-cyan-300 text-[10px] uppercase tracking-widest flex items-center gap-2">
                <i class="fi fi-rr-comments text-[12px]"></i> Job_Chat
            </h2>
            <span class="text-[8px] text-cyan-200 uppercase">Room: {{ activeRoom }}</span>
        </div>

        <div class="border border-slate-800 bg-[#0d1117]/70 p-3 mb-3">
            <div class="flex items-center justify-between gap-2 mb-2 border-b border-slate-800 pb-2">
                <p class="text-[8px] uppercase text-emerald-300">Online_Users</p>
                <p class="text-[8px] uppercase text-slate-500">You: {{ userName }} | {{ isConnected ? 'ON' : 'OFF' }}</p>
            </div>
            <p v-if="rateLimitNotice" class="text-[8px] uppercase text-amber-300 mb-2">{{ rateLimitNotice }}</p>
            <div class="max-h-[72px] overflow-y-auto custom-scroll">
                <div class="flex flex-wrap gap-2">
                    <span
                        v-for="(user, index) in onlineUsers"
                        :key="`${user}-${index}`"
                        class="relative text-[8px] uppercase text-slate-200 border border-slate-700 bg-black/40 pl-5 pr-2 py-1 rounded-full"
                    >
                        <span class="absolute left-2 top-1/2 -translate-y-1/2 w-1.5 h-1.5 rounded-full bg-emerald-400 shadow-[0_0_6px_rgba(52,211,153,0.85)]"></span>
                        {{ user }}
                    </span>

                    <p v-if="onlineUsers.length === 0" class="text-[8px] uppercase text-slate-600">
                        No_User_Online
                    </p>
                </div>
            </div>
        </div>

        <div class="border border-slate-800 bg-[#0d1117]/70 p-3 flex flex-col">
            <div ref="chatContainer" class="h-[260px] md:h-[300px] overflow-y-auto pr-1 custom-scroll space-y-2" @scroll.passive="handleHistoryScroll">
                <div
                    v-for="(item, index) in messages"
                    :key="item.id ? `msg-${item.id}` : `${item.user}-${item.time}-${index}`"
                    class="flex"
                    :class="item.isMine ? 'justify-end' : 'justify-start'"
                >
                    <div
                        class="max-w-[85%] px-3 py-2 rounded-2xl shadow-[0_2px_0_rgba(0,0,0,0.4)] border"
                        :class="item.isMine
                            ? 'bg-cyan-600/25 border-cyan-500/50 rounded-br-md'
                            : 'bg-slate-800/70 border-slate-600 rounded-bl-md'"
                    >
                        <div class="flex items-center gap-2 mb-1" :class="item.isMine ? 'justify-end' : 'justify-between'">
                            <p class="text-[8px] uppercase" :class="item.isMine ? 'text-cyan-200' : 'text-emerald-300'">
                                {{ item.user }}
                            </p>
                            <p class="text-[7px] uppercase text-slate-500">{{ item.time }}</p>
                        </div>
                        <p class="text-[12px] font-sans text-slate-100 break-words leading-snug">{{ item.message }}</p>
                    </div>
                </div>

                <p v-if="messages.length === 0" class="text-[8px] uppercase text-slate-600">
                    No_Message_Yet
                </p>
            </div>

            <div class="mt-3 pt-3 border-t border-slate-800 flex gap-2">
                <input
                    v-model="messageInput"
                    type="text"
                    maxlength="500"
                    class="flex-1 bg-black border-2 border-slate-700 p-2 text-cyan-300 outline-none text-[10px] font-sans"
                    placeholder="Type message..."
                    @input="handleTypingInput"
                    @keydown="handleTypingKeydown"
                    @blur="stopTyping"
                    @keyup.enter="sendMessage"
                >
                <button
                    type="button"
                    class="px-3 py-2 border-2 border-cyan-500 text-cyan-300 hover:bg-cyan-500 hover:text-black uppercase text-[8px]"
                    @click="sendMessage"
                >
                    Send
                </button>
            </div>
            <p v-if="typingUsersLabel" class="mt-2 text-[8px] uppercase text-emerald-300 animate-pulse">
                {{ typingUsersLabel }}
            </p>
        </div>
    </div>
</template>

<style scoped>
.custom-scroll {
    overflow-y: auto;
    scrollbar-width: thin;
    scrollbar-color: #009999 #0d1117;
}

.custom-scroll::-webkit-scrollbar {
    width: 6px;
}

.custom-scroll::-webkit-scrollbar-track {
    background: #0d1117;
}

.custom-scroll::-webkit-scrollbar-thumb {
    background: #009999;
    border: 1px solid #1a1c2c;
}
</style>
