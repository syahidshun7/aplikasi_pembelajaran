<script setup>
import { Head } from '@inertiajs/vue3';
import AppBackgroundLayer from '@/Components/AppBackgroundLayer.vue';
import UserNavbar from '@/Components/UserNavbar.vue';
import { useUserTheme } from '@/Composables/useUserTheme';

defineProps({
    fullBleed: {
        type: Boolean,
        default: false,
    },
    hideFooter: {
        type: Boolean,
        default: false,
    },
});

const { themeMode } = useUserTheme();
</script>

<template>
    <Head>
        <meta head-key="robots" name="robots" content="index,follow" />
    </Head>

    <div
        data-app-surface="user"
        :data-theme="themeMode"
        class="user-theme-root user-theme-root--public-profile font-['Press_Start_2P'] selection:bg-[var(--accent)] relative isolate flex flex-col"
        :class="fullBleed ? 'h-screen overflow-hidden' : 'min-h-screen overflow-x-hidden'"
    >
        <AppBackgroundLayer />

        <UserNavbar :show-guest-actions="true" />

        <main
            class="relative z-10 animate-in fade-in zoom-in-95 duration-500 flex-1"
            :class="fullBleed ? 'flex min-h-0 p-0' : 'p-4 md:p-8'"
        >
            <slot />
        </main>

        <footer v-if="!hideFooter" class="user-theme-footer mt-auto border-t-2 p-6 text-center backdrop-blur-md md:p-8">
            <p class="user-theme-muted break-words text-[7px] uppercase tracking-[0.18em] sm:text-[8px] sm:tracking-[0.3em]">Build_Ver_1.2.1 // P-Quest Engine</p>
        </footer>
    </div>
</template>

<style scoped>
.animate-in {
    animation-fill-mode: forwards;
}

[data-theme='light'].user-theme-root--public-profile :deep(.user-navbar-shell) {
    border-bottom-color: #009999 !important;
    background: #202020 !important;
    color: #f7f7f7 !important;
    box-shadow: 0 10px 24px rgba(32, 32, 32, 0.2) !important;
}

[data-theme='light'].user-theme-root--public-profile :deep(.user-navbar-brand-title) {
    color: #f7f7f7 !important;
    text-shadow: none !important;
}

[data-theme='light'].user-theme-root--public-profile :deep(.nav-dock),
[data-theme='light'].user-theme-root--public-profile :deep(.user-navbar-mobile-shell) {
    border-color: rgba(247, 247, 247, 0.18) !important;
    background: #181818 !important;
    box-shadow: none !important;
}

[data-theme='light'].user-theme-root--public-profile :deep(.user-navbar-mobile-toggle) {
    border-color: rgba(0, 153, 153, 0.58) !important;
    background: #202020 !important;
    color: #f7f7f7 !important;
    box-shadow: 3px 3px 0 rgba(0, 153, 153, 0.28) !important;
    text-shadow: none !important;
}

[data-theme='light'].user-theme-root--public-profile :deep(.user-navbar-mobile-toggle:hover) {
    background: #009999 !important;
    color: #fff !important;
}

[data-theme='light'].user-theme-root--public-profile .user-theme-footer {
    border-top-color: #009999 !important;
    background: #202020 !important;
    color: #f7f7f7 !important;
}

[data-theme='light'].user-theme-root--public-profile .user-theme-footer .user-theme-muted {
    color: rgba(247, 247, 247, 0.72) !important;
}
</style>
