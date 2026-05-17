<script setup>
import { ref, onMounted, onBeforeUnmount, computed } from 'vue';
import { onLoadingChange } from '@/Utils/globalLoader';

const active = ref(false);
const queue = ref(0);
let unsubscribe = null;

onMounted(() => {
    unsubscribe = onLoadingChange((isActive, pending) => {
        active.value = isActive;
        queue.value = pending;
    });
});

onBeforeUnmount(() => {
    if (unsubscribe) unsubscribe();
});

const barStyle = computed(() => {
    if (!active.value) return { width: '100%' };
    const clamped = Math.min(queue.value, 7);
    const percent = 20 + clamped * 10; // 20%..90%
    return { width: `${percent}%` };
});
</script>

<template>
    <transition name="fade">
        <div
            v-if="active"
            class="fixed top-0 left-0 right-0 z-50 h-[3px] bg-transparent pointer-events-none"
        >
            <div
                class="h-full bg-gradient-to-r from-[#00f2fe] via-[#4ed4d4] to-[#7cf9ff] shadow-[0_0_12px_rgba(126,249,255,0.65)] animate-loading-stripes"
                :style="barStyle"
            ></div>
        </div>
    </transition>
</template>

<style scoped>
@keyframes loading-stripes {
  from { background-position: 0 0; }
  to { background-position: 40px 0; }
}

@keyframes fadePulse {
  0%, 100% { opacity: 0.95; }
  50% { opacity: 0.6; }
}

.animate-loading-stripes {
  background-size: 40px 3px;
  animation: loading-stripes 0.8s linear infinite, fadePulse 1.6s ease-in-out infinite;
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 150ms ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
