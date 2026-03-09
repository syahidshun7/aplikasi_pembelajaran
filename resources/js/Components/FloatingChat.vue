<script setup>
import { ref } from 'vue';
import GlobalChat from './GlobalChat.vue';

const isOpen = ref(false);

const toggleChat = () => {
    isOpen.value = !isOpen.value;
};

const closeChat = () => {
    isOpen.value = false;
};
</script>

<template>
    <div class="fixed bottom-4 right-4 z-[80] flex flex-col items-end gap-3 md:bottom-6 md:right-6">
        <Transition name="chat-pop">
            <div
                v-if="isOpen"
                class="w-[min(94vw,420px)] max-h-[82vh]"
            >
                <div class="relative">
                    <button
                        type="button"
                        class="absolute right-2 top-2 z-[5] h-7 w-7 border border-cyan-500/60 bg-black/70 text-cyan-300 transition-colors hover:bg-cyan-500 hover:text-black"
                        aria-label="Close chat"
                        @click="closeChat"
                    >
                        <i class="fi fi-rr-cross-small text-[12px]"></i>
                    </button>

                    <GlobalChat />
                </div>
            </div>
        </Transition>

        <button
            type="button"
            class="h-14 w-14 border-2 border-cyan-400 bg-[#0d1117]/95 text-cyan-300 shadow-[0_0_16px_rgba(45,212,191,0.35)] transition-all hover:scale-105 hover:bg-cyan-500 hover:text-black"
            aria-label="Open chat"
            @click="toggleChat"
        >
            <i
                :class="isOpen ? 'fi fi-rr-minus-small' : 'fi fi-rr-comments'"
                class="text-[20px]"
            ></i>
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
