<script setup>
import { io } from 'socket.io-client';
import { usePage } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

const messages = ref([]);
const onlineUsers = ref([]);
const messageInput = ref('');
const chatContainer = ref(null);

const room = 'global';
const userName = ref('Anonymous');
const page = usePage();
const authUser = computed(() => page.props?.auth?.user || null);
let socket = null;

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

const fallbackTime = () => {
    return new Date().toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
};

const normalizeMessage = (payload = {}) => {
    const sender = String(payload.user || 'Anonymous');
    return {
        room: String(payload.room || room),
        user: sender,
        message: String(payload.message || ''),
        time: String(payload.time || fallbackTime()),
        isMine: normalizeIdentity(sender) === normalizeIdentity(userName.value),
    };
};

const scrollToBottom = async () => {
    await nextTick();
    if (!chatContainer.value) return;
    chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
};

const handleReceiveMessage = async (payload = {}) => {
    const parsed = normalizeMessage(payload);
    if (parsed.room !== room || parsed.message.trim() === '') return;
    messages.value.push(parsed);
    await scrollToBottom();
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

const sendMessage = () => {
    const plainMessage = messageInput.value.trim();
    if (!socket || plainMessage === '') return;

    const payload = {
        room,
        user: userName.value,
        message: plainMessage,
        time: fallbackTime(),
    };

    socket.emit('send_message', payload);
    messageInput.value = '';
};

onMounted(() => {
    userName.value = resolveUserName();

    socket = io('http://localhost:3001', {
        transports: ['websocket', 'polling'],
        withCredentials: true,
    });

    socket.on('connect', () => {
        onlineUsers.value = [userName.value];
        socket.emit('join_room', {
            room,
            user: userName.value,
        });
    });

    socket.on('receive_message', handleReceiveMessage);
    socket.on('online_users', handleOnlineUsers);
});

onBeforeUnmount(() => {
    if (!socket) return;
    socket.off('receive_message', handleReceiveMessage);
    socket.off('online_users', handleOnlineUsers);
    socket.disconnect();
    socket = null;
});
</script>

<template>
    <div class="rpg-panel border-cyan-500/40 flex flex-col bg-[#1a1c2c]/90 backdrop-blur-sm">
        <div class="flex justify-between items-center mb-4 border-b border-cyan-900 pb-2 flex-shrink-0">
            <h2 class="text-cyan-300 text-[10px] uppercase tracking-widest flex items-center gap-2">
                <i class="fi fi-rr-comments text-[12px]"></i> Global_Chat
            </h2>
            <span class="text-[8px] text-cyan-200 uppercase">Room: {{ room }}</span>
        </div>

        <div class="border border-slate-800 bg-[#0d1117]/70 p-3 mb-3">
            <div class="flex items-center justify-between gap-2 mb-2 border-b border-slate-800 pb-2">
                <p class="text-[8px] uppercase text-emerald-300">Online_Users</p>
                <p class="text-[8px] uppercase text-slate-500">You: {{ userName }}</p>
            </div>
            <div class="max-h-[72px] overflow-y-auto custom-scroll">
                <div class="flex flex-wrap gap-2">
                    <span
                        v-for="(user, index) in onlineUsers"
                        :key="`${user}-${index}`"
                        class="text-[8px] uppercase text-slate-200 border border-slate-700 bg-black/40 px-2 py-1 rounded-full"
                    >
                        {{ user }}
                    </span>

                    <p v-if="onlineUsers.length === 0" class="text-[8px] uppercase text-slate-600">
                        No_User_Online
                    </p>
                </div>
            </div>
        </div>

        <div class="border border-slate-800 bg-[#0d1117]/70 p-3 flex flex-col">
            <div ref="chatContainer" class="h-[260px] md:h-[300px] overflow-y-auto pr-1 custom-scroll space-y-2">
                <div
                    v-for="(item, index) in messages"
                    :key="`${item.user}-${item.time}-${index}`"
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
