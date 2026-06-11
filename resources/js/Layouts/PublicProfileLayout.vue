<script setup>
import { Head } from '@inertiajs/vue3';
import AppBackgroundLayer from '@/Components/AppBackgroundLayer.vue';
import UserNavbar from '@/Components/UserNavbar.vue';

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
</script>

<template>
    <Head>
        <meta head-key="robots" name="robots" content="index,follow" />
    </Head>

    <div
        data-app-surface="user"
        class="user-theme-root font-['Press_Start_2P'] selection:bg-[var(--accent)] relative isolate flex flex-col"
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
            <p class="user-theme-muted break-words text-[7px] uppercase tracking-[0.18em] sm:text-[8px] sm:tracking-[0.3em]">Build_Ver_1.1.0 // P-Quest Engine</p>
        </footer>
    </div>
</template>

<style scoped>
.animate-in {
    animation-fill-mode: forwards;
}
</style>
