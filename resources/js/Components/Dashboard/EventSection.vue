<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    items: {
        type: Array,
        default: () => [],
    },
    authUser: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <section class="dashboard-section-shell">
        <div class="dashboard-section-header">
            <div>
                <p class="dashboard-section-header__eyebrow text-blue-300/80">Upcoming</p>
                <h2 class="dashboard-section-header__title text-blue-300">Town Hall Timeline</h2>
            </div>
            <Link
                :href="authUser ? route('events.user.index') : route('login')"
                class="dashboard-section-header__action border-blue-700 bg-blue-900/30 text-blue-200 hover:bg-blue-500/40"
            >
                <i class="fi fi-rr-calendar text-[18px] leading-none"></i>
                <span>View All Events</span>
            </Link>
        </div>

        <div v-if="items.length > 0" class="space-y-4">
            <article
                v-for="event in items"
                :key="event.uuid"
                class="border border-slate-800 bg-[#0d1117] p-4 shadow-[0_12px_22px_rgba(3,8,16,0.34)]"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="text-[7px] uppercase text-blue-300">Meeting_{{ event.sequence_order }}</span>
                    <span class="rounded-full border border-blue-500/30 bg-blue-500/10 px-2 py-1 text-[7px] uppercase text-blue-100">
                        {{ event.study_group?.name || 'Public' }}
                    </span>
                </div>
                <h3 class="mt-3 break-words text-[10px] uppercase text-white">{{ event.title }}</h3>
                <p class="mt-2 text-[8px] uppercase leading-relaxed text-slate-400">{{ event.__starts_at_label }}</p>
                <div class="mt-4 flex items-center justify-between gap-2 border-t border-slate-800 pt-3">
                    <span class="text-[7px] uppercase text-cyan-300">Event Node</span>
                    <Link :href="route('events.show', event.uuid)" class="text-[7px] uppercase text-blue-300 hover:text-white">
                        View >
                    </Link>
                </div>
            </article>
        </div>

        <div v-else class="dashboard-empty-state">
            <div class="dashboard-empty-state__icon">+</div>
            <h3 class="dashboard-empty-state__title text-blue-300">No Events Available</h3>
            <p class="dashboard-empty-state__copy">No schedules are queued in the town hall right now.</p>
        </div>
    </section>
</template>

<style scoped>
.dashboard-section-shell {
    @apply border-2 border-[#3d415f] bg-[#1a1c2c] p-5 shadow-[0_18px_50px_rgba(2,8,16,0.46)];
}

.dashboard-section-header {
    @apply mb-6 flex flex-wrap items-center justify-between gap-3 border-b border-slate-700 pb-4;
}

.dashboard-section-header__eyebrow {
    @apply text-[7px] uppercase tracking-[0.24em];
}

.dashboard-section-header__title {
    @apply text-[10px] uppercase tracking-widest sm:text-xs;
}

.dashboard-section-header__action {
    @apply inline-flex items-center gap-2 border-b-4 border-r-4 p-2 text-[8px] uppercase shadow-[3px_3px_0px_0px_rgba(0,0,0,0.5)] transition-colors;
}

.dashboard-empty-state {
    @apply flex min-h-[260px] flex-col items-center justify-center border-2 border-dashed border-slate-800 p-6 text-center;
}

.dashboard-empty-state__icon {
    @apply mb-4 text-4xl italic text-slate-600;
}

.dashboard-empty-state__title {
    @apply mb-2 text-[12px] font-bold uppercase tracking-[0.2em];
}

.dashboard-empty-state__copy {
    @apply max-w-[280px] text-[9px] uppercase leading-relaxed text-slate-500;
}
</style>
