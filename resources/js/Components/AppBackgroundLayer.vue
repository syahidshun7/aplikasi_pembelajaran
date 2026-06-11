<script setup>
import { computed } from 'vue';

const props = defineProps({
    image: {
        type: String,
        default: '/images/bg-loby.png',
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
});

const backgroundStyle = computed(() => ({
    backgroundImage: `url('${props.image}')`,
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
.app-bg-layer {
    background: var(--bg, #0a0c10);
    /* Gunakan 100dvh di mobile agar tidak terpengaruh dynamic viewport (address bar muncul/hilang) */
    height: 100dvh;
}

.app-bg-layer__image {
    position: absolute;
    inset: 0;
    background-position: center;
    background-repeat: no-repeat;
    background-size: cover;
    /* Paksa layer ke GPU composite, cegah repaint saat scroll */
    transform: translate3d(0, 0, 0);
    backface-visibility: hidden;
    /* Jangan pakai will-change: transform di mobile — bisa menyebabkan layer baru yang
       di-scroll terpisah dari halaman, efeknya bg bergerak saat scroll */
    will-change: auto;
}

/* Mobile: iOS Safari & Android Chrome punya masalah dengan fixed + background saat scroll.
   Tambahkan contain untuk mencegah repaint yang tidak perlu. */
@media (max-width: 1024px) {
    .app-bg-layer {
        contain: strict;
    }

    .app-bg-layer__image {
        /* Pada mobile, pastikan image tidak ter-repaint saat momentum scroll */
        transform: translateZ(0);
        will-change: auto;
    }
}
</style>
