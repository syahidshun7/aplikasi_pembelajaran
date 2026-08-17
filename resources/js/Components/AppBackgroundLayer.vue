<script setup>
import { computed } from 'vue';

const props = defineProps({
    image: {
        type: String,
        default: '/images/bg-loby.webp',
    },
    overlayClass: {
        type: [String, Array, Object],
        default: 'bg-black/65',
    },
    glowClass: {
        type: [String, Array, Object],
        default: 'bg-[radial-gradient(circle_at_18%_20%,rgba(34,211,238,0.18),transparent_34%),radial-gradient(circle_at_82%_14%,rgba(45,212,191,0.14),transparent_30%),linear-gradient(180deg,rgba(2,6,23,0.16),rgba(2,6,23,0.4))]',
    },
    showGlow: {
        type: Boolean,
        default: true,
    },
    imagePosition: {
        type: String,
        default: 'center',
    },
});

const backgroundStyle = computed(() => ({
    backgroundImage: `url('${props.image}')`,
    backgroundPosition: props.imagePosition,
}));
</script>

<template>
    <!-- Teleport ke body agar fixed positioning tidak terjebak di dalam ancestor overflow-x-hidden/isolate -->
    <Teleport to="body">
        <div aria-hidden="true" class="app-bg-layer fixed inset-0 -z-10 overflow-hidden pointer-events-none">
            <div class="app-bg-layer__image" :style="backgroundStyle"></div>
            <div class="absolute inset-0" :class="overlayClass"></div>
            <div v-if="showGlow" class="absolute inset-0" :class="glowClass"></div>
        </div>
    </Teleport>
</template>

<style scoped>
.app-bg-layer__image {
    position: absolute;
    inset: 0;
    background-position: center;
    background-repeat: no-repeat;
    background-size: cover;
    transform: translateZ(0);
    backface-visibility: hidden;
    will-change: auto;
}
</style>
