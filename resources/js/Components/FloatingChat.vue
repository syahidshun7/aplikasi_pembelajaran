<script setup>
import { defineAsyncComponent, onMounted, ref } from 'vue';

const isOpen = ref(false);
const chatReady = ref(false);
const unreadCount = ref(0);

let globalChatPreloadPromise = null;
const preloadGlobalChat = () => {
    if (!globalChatPreloadPromise) {
        globalChatPreloadPromise = import('./GlobalChat.vue').then((module) => {
            chatReady.value = true;
            return module;
        });
    }
    return globalChatPreloadPromise;
};

const GlobalChat = defineAsyncComponent(() => preloadGlobalChat());

onMounted(() => {
    if (typeof window === 'undefined') return;

    const warm = () => preloadGlobalChat();
    if (typeof window.requestIdleCallback === 'function') {
        window.requestIdleCallback(warm, { timeout: 1500 });
        return;
    }

    window.setTimeout(warm, 800);
});

const toggleChat = () => {
    if (!isOpen.value) {
        preloadGlobalChat();
        unreadCount.value = 0;
    }
    isOpen.value = !isOpen.value;
};

const closeChat = () => {
    isOpen.value = false;
};

const handleUnreadChange = (nextValue) => {
    const parsed = Number(nextValue);
    unreadCount.value = Number.isFinite(parsed) && parsed > 0 ? Math.floor(parsed) : 0;
};
</script>

<template>
    <div class="fixed bottom-4 right-4 z-[9999] flex flex-col items-end gap-3 md:bottom-6 md:right-6">
        <Transition name="chat-pop">
            <div
                v-if="chatReady"
                v-show="isOpen"
                class="w-[min(94vw,420px)]"
            >
                <div class="mb-1 flex justify-end">
                    <button
                        type="button"
                        class="border border-cyan-400 bg-[#0d1117]/95 px-2.5 py-1 text-[10px] font-bold uppercase leading-none tracking-[0.08em] text-cyan-300 shadow-[0_0_10px_rgba(45,212,191,0.32)] transition-colors hover:bg-cyan-500 hover:text-black"
                        @click="closeChat"
                    >
                        x Tutup
                    </button>
                </div>
                <div class="h-[min(82vh,760px)] md:h-[86vh] md:max-h-[760px] min-h-0">
                    <div class="min-h-0 h-full">
                        <GlobalChat :is-open="isOpen" @unread-change="handleUnreadChange" />
                    </div>
                </div>
            </div>
        </Transition>

        <button
            v-if="!isOpen"
            type="button"
            class="relative h-14 w-14 border-2 border-cyan-400 bg-[#0d1117]/95 text-cyan-300 shadow-[0_0_16px_rgba(45,212,191,0.35)] transition-all hover:scale-105 hover:bg-cyan-500 hover:text-black"
            aria-label="Open chat"
            @click="toggleChat"
        >
            <i
                :class="isOpen ? 'fi fi-rr-minus-small' : 'fi fi-rr-comments'"
                class="text-[20px]"
            ></i>
            <span
                v-if="!isOpen && unreadCount > 0"
                class="absolute -right-2 -top-2 min-w-[20px] rounded-full border border-cyan-100 bg-cyan-400 px-1 py-[2px] text-center text-[9px] font-bold text-black"
            >
                {{ unreadCount > 99 ? '99+' : unreadCount }}
            </span>
        </button>
    </div>
</template>

<style scoped>
.chat-pop-enter-active,
.chat-pop-leave-active {
    transition: opacity 0.2s ease, transform 0.2s ease;
    transform-origin: bottom right;
}

.chat-pop-enter-from,
.chat-pop-leave-to {
    opacity: 0;
    transform: translateY(12px) scale(0.94);
}
</style>
