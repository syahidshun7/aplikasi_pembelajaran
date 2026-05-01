<script setup>
import { usePage } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    isOpen: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits([
    'unread-change',
]);

const messages = ref([]);
const onlineUsers = ref([]);
const messageInput = ref('');
const chatContainer = ref(null);
const activeRoom = ref('assigning...');
const activeRoomType = ref('job');
const activeRoomLabel = ref('Assigning...');
const isConnected = ref(false);
const rateLimitNotice = ref('');
const hasMoreHistory = ref(true);
const isLoadingHistory = ref(false);
const historyCursorId = ref(null);
const typingUsers = ref([]);
const socketClientId = ref(null);
const localUnreadCount = ref(0);
const unreadAnchorKey = ref('');
let ephemeralMessageCounter = 0;
const roomCatalog = ref({
    job_room: null,
    class_rooms: [],
    dm_contacts: [],
});
const roomOptions = ref([]);
const selectedRoomKey = ref('');
const selectedClassGroupUuid = ref('');
const selectedDmUserId = ref('');
const roomSwitching = ref(false);
const roomUnreadCounts = ref({});
const processedRoomActivityIds = new Set();

const userName = ref('Anonymous');
const userId = ref(null);
const page = usePage();
const authUser = computed(() => page.props?.auth?.user || null);
let socket = null;
let historyRequestTimer = null;
let typingStopTimer = null;
let markReadTimer = null;
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

const sanitizeUnreadCounts = (value) => {
    if (!value || typeof value !== 'object' || Array.isArray(value)) return {};

    const nextMap = {};
    for (const [rawKey, rawCount] of Object.entries(value)) {
        const key = normalizeRoomOptionKey(rawKey);
        if (key === '') continue;

        const parsedCount = Number(rawCount || 0);
        if (!Number.isFinite(parsedCount) || parsedCount <= 0) continue;
        nextMap[key] = Math.floor(parsedCount);
    }

    return nextMap;
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
    const hasMessageId = Number.isInteger(messageId) && messageId > 0;
    const key = hasMessageId ? `id-${messageId}` : `tmp-${Date.now()}-${ephemeralMessageCounter++}`;
    const mineById = userId.value && senderId ? senderId === userId.value : false;
    return {
        id: hasMessageId ? messageId : null,
        room: String(payload.room || activeRoom.value || 'unknown'),
        user: sender,
        message: String(payload.message || ''),
        time: String(payload.time || (createdAt ? toDisplayTime(createdAt) : fallbackTime())),
        created_at: createdAt,
        isMine: mineById || normalizeIdentity(sender) === normalizeIdentity(userName.value),
        __key: key,
        __unread_while_closed: false,
    };
};

const isPresenceSystemMessage = (payload = {}) => {
    const sender = normalizeIdentity(payload.user || '');
    if (sender !== 'system') return false;

    const message = String(payload.message || '').toLowerCase();
    return /joined\s*\(|left the room/.test(message);
};

const normalizeRoomOptionKey = (value) => String(value || '').trim();

const getCurrentRoomOptionKey = () => normalizeRoomOptionKey(selectedRoomKey.value);

const getRoomOptionKeyFromAssignedPayload = (payload = {}) => {
    const roomType = String(payload.room_type || '').trim().toLowerCase();
    const roomKey = String(payload.room_key || '').trim();

    if (roomType === 'class' && roomKey !== '') {
        return `class:${roomKey}`;
    }

    if (roomType === 'dm' && roomKey !== '') {
        return `dm:${roomKey}`;
    }

    return 'job';
};

const getRoomOptionKeyFromActivityPayload = (payload = {}) => {
    const directKey = normalizeRoomOptionKey(payload.room_option_key);
    if (directKey !== '') {
        return directKey;
    }

    const roomType = String(payload.room_type || '').trim().toLowerCase();
    const roomKey = String(payload.room_key || '').trim();

    if (roomType === 'class' && roomKey !== '') {
        return `class:${roomKey}`;
    }

    if (roomType === 'dm') {
        const senderId = Number(payload.sender_user_id || 0);
        if (Number.isInteger(senderId) && senderId > 0) {
            return `dm:${senderId}`;
        }
    }

    return 'job';
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

    if (markReadTimer) {
        clearTimeout(markReadTimer);
        markReadTimer = null;
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

const applyRoomCatalog = (payload = {}) => {
    const safeCatalog = payload && typeof payload === 'object' ? payload : {};

    roomCatalog.value = {
        job_room: safeCatalog.job_room && typeof safeCatalog.job_room === 'object'
            ? safeCatalog.job_room
            : null,
        class_rooms: Array.isArray(safeCatalog.class_rooms) ? safeCatalog.class_rooms : [],
        dm_contacts: Array.isArray(safeCatalog.dm_contacts) ? safeCatalog.dm_contacts : [],
    };

    const options = [];

    if (roomCatalog.value.job_room) {
        options.push({
            key: 'job',
            type: 'job',
            label: roomCatalog.value.job_room.label || 'Job Room',
            payload: { type: 'job' },
        });
    }

    for (const room of roomCatalog.value.class_rooms) {
        const roomKey = String(room?.key || '').trim();
        if (roomKey === '') continue;

        options.push({
            key: `class:${roomKey}`,
            type: 'class',
            label: room?.label || `Class: ${roomKey}`,
            payload: { type: 'class', group_uuid: roomKey },
        });
    }

    for (const contact of roomCatalog.value.dm_contacts) {
        const targetUserId = Number(contact?.user_id || 0);
        if (!Number.isInteger(targetUserId) || targetUserId <= 0) continue;

        options.push({
            key: `dm:${targetUserId}`,
            type: 'dm',
            label: `DM: ${contact?.label || contact?.username || contact?.name || targetUserId}`,
            payload: { type: 'dm', user_id: targetUserId },
        });
    }

    roomOptions.value = options;

    const validOptionKeys = new Set(options.map((option) => option.key));
    const nextUnreadMap = {};
    for (const [key, count] of Object.entries(roomUnreadCounts.value)) {
        if (!validOptionKeys.has(key)) continue;
        const parsedCount = Number(count || 0);
        if (!Number.isFinite(parsedCount) || parsedCount <= 0) continue;
        nextUnreadMap[key] = Math.floor(parsedCount);
    }
    roomUnreadCounts.value = nextUnreadMap;
    syncTotalUnreadCount();

    const selectedClassExists = roomCatalog.value.class_rooms.some(
        (room) => String(room?.key || '') === String(selectedClassGroupUuid.value || '')
    );
    if (!selectedClassExists) {
        selectedClassGroupUuid.value = '';
    }

    const selectedDmExists = roomCatalog.value.dm_contacts.some(
        (contact) => String(contact?.user_id || '') === String(selectedDmUserId.value || '')
    );
    if (!selectedDmExists) {
        selectedDmUserId.value = '';
    }
};

const requestRoomSwitch = (payload = {}) => {
    if (!socket || !isConnected.value) return;

    roomSwitching.value = true;
    socket.emit('switch_room', payload);
};

const switchToRoomByOptionKey = (optionKey) => {
    const normalizedKey = String(optionKey || '').trim();
    if (normalizedKey === '') return;

    const selectedOption = roomOptions.value.find((option) => option.key === normalizedKey);
    if (!selectedOption?.payload) return;

    requestRoomSwitch(selectedOption.payload);
};

const handleReceiveMessage = async (payload = {}) => {
    if (isPresenceSystemMessage(payload)) return;

    const parsed = normalizeMessage(payload);
    if (parsed.message.trim() === '') return;
    if (parsed.id && messages.value.some((item) => item.id === parsed.id)) return;

    if (activeRoom.value !== 'assigning...' && parsed.room !== activeRoom.value) {
        return;
    }

    if (!parsed.isMine && !props.isOpen) {
        parsed.__unread_while_closed = true;
        if (!unreadAnchorKey.value) {
            unreadAnchorKey.value = parsed.__key;
        }
        incrementUnreadForRoom(getCurrentRoomOptionKey(), 1);
    }

    messages.value.push(parsed);
    removeTypingUser(parsed.user, parsed.user_id);

    if (!parsed.isMine && props.isOpen) {
        scheduleMarkActiveRoomAsRead();
    }

    await scrollToBottom();
};

const handleRoomActivity = (payload = {}) => {
    const senderId = Number(payload.sender_user_id || 0);
    if (userId.value && senderId && senderId === userId.value) {
        return;
    }

    const messageId = Number(payload.message_id || 0);
    if (Number.isInteger(messageId) && messageId > 0) {
        if (processedRoomActivityIds.has(messageId)) {
            return;
        }
        processedRoomActivityIds.add(messageId);
        if (processedRoomActivityIds.size > 2000) {
            const oldestKey = processedRoomActivityIds.values().next().value;
            if (oldestKey) {
                processedRoomActivityIds.delete(oldestKey);
            }
        }
    }

    const targetOptionKey = getRoomOptionKeyFromActivityPayload(payload);
    if (targetOptionKey === '') return;

    if (targetOptionKey === getCurrentRoomOptionKey()) {
        return;
    }

    incrementUnreadForRoom(targetOptionKey, 1);
};

const handleRoomReadAck = (payload = {}) => {
    const optionKey = normalizeRoomOptionKey(payload.option_key || getCurrentRoomOptionKey());
    if (!optionKey) return;
    clearUnreadForRoom(optionKey);
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

};

const handleRoomAssigned = (payload = {}) => {
    const nextRoom = String(payload.room || '').trim();
    if (nextRoom !== '') {
        activeRoom.value = nextRoom;
    }

    const nextRoomType = String(payload.room_type || 'job').trim().toLowerCase();
    if (nextRoomType !== '') {
        activeRoomType.value = nextRoomType;
    }

    const nextRoomLabel = String(payload.room_label || '').trim();
    activeRoomLabel.value = nextRoomLabel || (nextRoom !== '' ? nextRoom.toUpperCase() : 'UNKNOWN');

    if (activeRoomType.value === 'class') {
        selectedClassGroupUuid.value = String(payload.room_key || selectedClassGroupUuid.value || '');
    }

    if (activeRoomType.value === 'dm') {
        selectedDmUserId.value = String(payload.room_key || selectedDmUserId.value || '');
    }

    if (activeRoomType.value === 'job') {
        selectedRoomKey.value = 'job';
    } else if (activeRoomType.value === 'class') {
        selectedRoomKey.value = `class:${String(payload.room_key || '').trim()}`;
    } else if (activeRoomType.value === 'dm') {
        selectedRoomKey.value = `dm:${String(payload.room_key || '').trim()}`;
    }

    messages.value = [];
    unreadAnchorKey.value = '';
    clearUnreadForRoom(getRoomOptionKeyFromAssignedPayload(payload));
    resetTypingState();
    hasMoreHistory.value = true;
    isLoadingHistory.value = false;
    historyCursorId.value = null;
    roomSwitching.value = false;
    requestHistory();

    if (props.isOpen) {
        scheduleMarkActiveRoomAsRead(120);
    }
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

const setUnreadCount = (nextValue) => {
    const parsed = Number(nextValue);
    localUnreadCount.value = Number.isFinite(parsed) && parsed > 0 ? Math.floor(parsed) : 0;
    emit('unread-change', localUnreadCount.value);
};

const syncTotalUnreadCount = () => {
    const total = Object.values(roomUnreadCounts.value).reduce((sum, value) => {
        const parsed = Number(value || 0);
        return sum + (Number.isFinite(parsed) && parsed > 0 ? Math.floor(parsed) : 0);
    }, 0);

    setUnreadCount(total);
};

const incrementUnreadForRoom = (optionKey, amount = 1) => {
    const safeKey = normalizeRoomOptionKey(optionKey);
    if (safeKey === '') return;

    const nextMap = { ...roomUnreadCounts.value };
    const current = Number(nextMap[safeKey] || 0);
    const delta = Number(amount);
    const safeDelta = Number.isFinite(delta) && delta > 0 ? Math.floor(delta) : 0;
    nextMap[safeKey] = Math.max(0, current + safeDelta);
    roomUnreadCounts.value = nextMap;
    syncTotalUnreadCount();
};

const clearUnreadForRoom = (optionKey) => {
    const safeKey = normalizeRoomOptionKey(optionKey);
    if (safeKey === '') return;
    if (!(safeKey in roomUnreadCounts.value)) return;

    const nextMap = { ...roomUnreadCounts.value };
    delete nextMap[safeKey];
    roomUnreadCounts.value = nextMap;
    syncTotalUnreadCount();
};

const getLatestRoomMessageId = () => {
    const lastItem = [...messages.value]
        .reverse()
        .find((item) => Number.isInteger(item?.id) && item.id > 0);

    return lastItem?.id || null;
};

const markActiveRoomAsRead = () => {
    if (!socket || !isConnected.value) return;
    if (activeRoom.value === 'assigning...') return;

    const payload = {
        room: activeRoom.value,
    };

    const latestMessageId = getLatestRoomMessageId();
    if (Number.isInteger(latestMessageId) && latestMessageId > 0) {
        payload.last_message_id = latestMessageId;
    }

    socket.emit('mark_room_read', payload);
};

const scheduleMarkActiveRoomAsRead = (delayMs = 80) => {
    if (markReadTimer) {
        clearTimeout(markReadTimer);
        markReadTimer = null;
    }

    const schedule = typeof window !== 'undefined' ? window.setTimeout : setTimeout;
    markReadTimer = schedule(() => {
        markReadTimer = null;
        markActiveRoomAsRead();
    }, delayMs);
};

const applyUnreadSnapshot = (payload = {}) => {
    const source = payload && typeof payload === 'object' && payload.counts
        ? payload.counts
        : payload;

    roomUnreadCounts.value = sanitizeUnreadCounts(source);
    syncTotalUnreadCount();
};

const roomOptionsForDisplay = computed(() => {
    return roomOptions.value.map((option) => {
        const count = Number(roomUnreadCounts.value[option.key] || 0);
        const unread = Number.isFinite(count) && count > 0 ? Math.floor(count) : 0;
        return {
            ...option,
            unread,
            displayLabel: unread > 0 ? `${option.label} [${unread}]` : option.label,
        };
    });
});

const inactiveRoomUnreadCount = computed(() => {
    const activeKey = getCurrentRoomOptionKey();
    return Object.entries(roomUnreadCounts.value).reduce((sum, [key, count]) => {
        if (key === activeKey) return sum;
        const parsed = Number(count || 0);
        if (!Number.isFinite(parsed) || parsed <= 0) return sum;
        return sum + Math.floor(parsed);
    }, 0);
});

const clearUnreadMarkers = () => {
    if (!messages.value.length) {
        unreadAnchorKey.value = '';
        return;
    }

    messages.value = messages.value.map((item) => ({
        ...item,
        __unread_while_closed: false,
    }));

    unreadAnchorKey.value = '';
};

const scrollToUnreadAnchor = async () => {
    if (!chatContainer.value || !unreadAnchorKey.value) {
        return false;
    }

    await nextTick();

    const selector = `[data-chat-key="${unreadAnchorKey.value}"]`;
    const target = chatContainer.value.querySelector(selector);
    if (!target) {
        return false;
    }

    target.scrollIntoView({
        block: 'start',
        behavior: 'auto',
    });

    return true;
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

        if (props.isOpen) {
            scheduleMarkActiveRoomAsRead();
        }
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

watch(() => props.isOpen, (isOpen) => {
    const activeOptionKey = getCurrentRoomOptionKey();

    if (isOpen) {
        if (localUnreadCount.value > 0 && unreadAnchorKey.value) {
            const schedule = typeof window !== 'undefined' ? window.setTimeout : setTimeout;
            schedule(async () => {
                await scrollToUnreadAnchor();
                clearUnreadMarkers();
                clearUnreadForRoom(activeOptionKey);
                scheduleMarkActiveRoomAsRead();
            }, 60);
            return;
        }

        clearUnreadMarkers();
        clearUnreadForRoom(activeOptionKey);
        scheduleMarkActiveRoomAsRead();
    }
}, { immediate: true });

watch(selectedRoomKey, (nextValue, previousValue) => {
    const nextKey = String(nextValue || '').trim();
    const prevKey = String(previousValue || '').trim();

    if (nextKey === '' || nextKey === prevKey) {
        return;
    }

    if (roomSwitching.value) {
        return;
    }

    switchToRoomByOptionKey(nextKey);
});

onMounted(async () => {
    setUnreadCount(0);
    userName.value = resolveUserName();
    userId.value = resolveUserId();
    const token = buildToken();

    if (!token) {
        pushSystemNotice('Autentikasi chat tidak valid. Silakan login ulang.');
        return;
    }

    const { io } = await import('socket.io-client');
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
        roomSwitching.value = false;
        resetTypingState();
    });

    socket.on('room_catalog', applyRoomCatalog);
    socket.on('unread_snapshot', applyUnreadSnapshot);
    socket.on('room_assigned', handleRoomAssigned);
    socket.on('message_history', handleMessageHistory);
    socket.on('receive_message', handleReceiveMessage);
    socket.on('room_activity', handleRoomActivity);
    socket.on('room_read_ack', handleRoomReadAck);
    socket.on('online_users', handleOnlineUsers);
    socket.on('typing_status', handleTypingStatus);
    socket.on('rate_limit', async (payload = {}) => {
        rateLimitNotice.value = String(payload.message || 'Rate limit exceeded');
        await pushSystemNotice(rateLimitNotice.value);
    });
    socket.on('auth_error', async (payload = {}) => {
        roomSwitching.value = false;
        await pushSystemNotice(String(payload.message || 'Auth error'));
    });
    socket.on('room_switch_failed', async (payload = {}) => {
        roomSwitching.value = false;
        await pushSystemNotice(String(payload.message || 'Room switch failed'));
    });
    socket.on('server_error', async (payload = {}) => {
        roomSwitching.value = false;
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
    socket.off('room_catalog', applyRoomCatalog);
    socket.off('unread_snapshot', applyUnreadSnapshot);
    socket.off('room_assigned', handleRoomAssigned);
    socket.off('message_history', handleMessageHistory);
    socket.off('receive_message', handleReceiveMessage);
    socket.off('room_activity', handleRoomActivity);
    socket.off('room_read_ack', handleRoomReadAck);
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
    <div class="rpg-panel border-cyan-500/40 flex h-full min-h-0 flex-col overflow-hidden bg-[#1a1c2c]/90 backdrop-blur-sm">
        <div class="flex justify-between items-center mb-4 border-b border-cyan-900 pb-2 flex-shrink-0">
            <h2 class="text-cyan-300 text-[10px] uppercase tracking-widest flex items-center gap-2">
                <i class="fi fi-rr-comments text-[12px]"></i> Job_Chat
            </h2>
            <div class="flex items-center gap-2">
                <span class="text-[8px] text-cyan-200 uppercase">Room:</span>
                <div class="relative">
                    <select
                        v-model="selectedRoomKey"
                        class="bg-black border border-slate-700 px-2 py-1 text-[8px] uppercase text-cyan-300 outline-none min-w-[220px]"
                        :disabled="roomSwitching || roomOptionsForDisplay.length === 0"
                    >
                        <option v-if="roomOptionsForDisplay.length === 0" value="">No_Room</option>
                        <option v-for="option in roomOptionsForDisplay" :key="option.key" :value="option.key">
                            {{ option.displayLabel }}
                        </option>
                    </select>
                    <span
                        v-if="inactiveRoomUnreadCount > 0"
                        class="absolute -top-1 -right-1 min-w-[14px] h-[14px] px-1 rounded-full bg-cyan-400 text-[7px] leading-[14px] text-black text-center border border-cyan-100 shadow-[0_0_8px_rgba(34,211,238,0.85)]"
                    >
                        {{ inactiveRoomUnreadCount > 99 ? '99+' : inactiveRoomUnreadCount }}
                    </span>
                </div>
            </div>
        </div>

        <div class="border border-slate-800 bg-[#0d1117]/70 p-3 mb-3 flex-shrink-0">
            <div class="flex items-center justify-between gap-2 mb-2 border-b border-slate-800 pb-2">
                <p class="text-[8px] uppercase text-emerald-300">Online_Users</p>
                <p class="text-[8px] uppercase text-slate-500">You: {{ userName }} | {{ isConnected ? 'ON' : 'OFF' }}</p>
            </div>
            <p v-if="rateLimitNotice" class="text-[8px] uppercase text-amber-300 mb-2">{{ rateLimitNotice }}</p>
            <div class="max-h-[72px] overflow-y-auto custom-scroll">
                <div class="flex flex-wrap gap-2">
                    <span
                        v-for="user in onlineUsers"
                        :key="user"
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

        <div class="border border-slate-800 bg-[#0d1117]/70 p-3 flex min-h-0 flex-1 flex-col overflow-hidden">
            <div ref="chatContainer" class="flex-1 min-h-0 overflow-y-auto pr-1 custom-scroll space-y-2" @scroll.passive="handleHistoryScroll">
                <div
                    v-for="(item, index) in messages"
                    :key="item.id ? `msg-${item.id}` : `${item.user}-${item.time}-${index}`"
                    :data-chat-key="item.__key || ''"
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

            <div class="mt-3 pt-3 border-t border-slate-800 flex flex-shrink-0 items-end gap-2">
                <div class="relative flex-1">
                    <input
                        v-model="messageInput"
                        type="text"
                        maxlength="500"
                        class="w-full bg-black border-2 border-slate-700 pt-2 pb-4 px-2 text-cyan-300 outline-none text-[10px] font-sans"
                        placeholder="Type message..."
                        @input="handleTypingInput"
                        @keydown="handleTypingKeydown"
                        @blur="stopTyping"
                        @keyup.enter="sendMessage"
                    >
                    <p v-if="typingUsersLabel" class="pointer-events-none absolute left-2 bottom-1 text-[7px] uppercase text-emerald-300/95">
                        {{ typingUsersLabel }}
                    </p>
                </div>
                <button
                    type="button"
                    class="px-3 py-2 border-2 border-cyan-500 text-cyan-300 hover:bg-cyan-500 hover:text-black uppercase text-[8px]"
                    @click="sendMessage"
                >
                    Send
                </button>
            </div>
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
