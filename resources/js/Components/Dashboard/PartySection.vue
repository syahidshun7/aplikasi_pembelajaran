<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    items: {
        type: Array,
        default: () => [],
    },
    joinProcessing: {
        type: Boolean,
        default: false,
    },
    onJoin: {
        type: Function,
        default: null,
    },
    onLeave: {
        type: Function,
        default: null,
    },
});
</script>

<template>
    <section class="dashboard-section-shell">
        <div class="dashboard-section-header">
            <div>
                <p class="dashboard-section-header__eyebrow text-emerald-300/80">Collaborate</p>
                <h2 class="dashboard-section-header__title text-emerald-300">Party Guild</h2>
            </div>
            <Link
                :href="route('groups.index')"
                class="dashboard-section-header__action border-emerald-700 bg-emerald-900/30 text-emerald-200 hover:bg-emerald-500/40"
            >
                <i class="fi fi-rr-users text-[18px] leading-none"></i>
                <span>View All Parties</span>
            </Link>
        </div>

        <div v-if="items.length > 0" class="grid gap-4 lg:grid-cols-2">
            <article
                v-for="group in items"
                :key="group.uuid"
                class="border border-slate-800 bg-[#0d1117] p-4 shadow-[0_12px_22px_rgba(3,8,16,0.34)]"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="break-words text-[9px] uppercase text-white">{{ group.name }}</h3>
                        <p class="mt-2 line-clamp-2 text-[7px] uppercase leading-relaxed text-slate-500">
                            {{ group.description || 'In pursuit of higher knowledge...' }}
                        </p>
                    </div>
                    <span class="shrink-0 rounded-full border border-emerald-500/20 bg-emerald-500/10 px-2 py-1 text-[8px] text-yellow-300">
                        {{ group.users_count || 0 }}/{{ group.max_members }}
                    </span>
                </div>

                <div class="mt-4 flex flex-col gap-2 border-t border-slate-800 pt-3 sm:flex-row sm:items-center sm:justify-between">
                    <span class="text-[7px] uppercase text-slate-600">Party_ID: {{ group.uuid?.substring(0, 8) }}</span>

                    <button
                        v-if="group.is_member"
                        type="button"
                        class="border border-red-700 bg-red-900/50 px-3 py-1 text-[8px] uppercase text-red-400 transition-all hover:bg-red-600 hover:text-white"
                        @click="onLeave?.(group.uuid)"
                    >
                        Leave
                    </button>

                    <button
                        v-else-if="group.join_request_status === 'pending'"
                        type="button"
                        disabled
                        class="cursor-not-allowed border border-slate-700 bg-slate-900/60 px-3 py-1 text-[8px] uppercase text-slate-400"
                    >
                        Pending
                    </button>

                    <button
                        v-else
                        type="button"
                        :disabled="joinProcessing"
                        class="border border-emerald-700 bg-emerald-900/50 px-3 py-1 text-[8px] uppercase text-emerald-400 transition-all hover:bg-emerald-500 hover:text-black disabled:cursor-not-allowed disabled:opacity-60"
                        @click="onJoin?.(group.uuid)"
                    >
                        {{ joinProcessing ? 'Sending...' : 'Join' }}
                    </button>
                </div>
            </article>
        </div>

        <div v-else class="dashboard-empty-state">
            <div class="dashboard-empty-state__icon">*</div>
            <h3 class="dashboard-empty-state__title text-emerald-300">No Parties Found</h3>
            <p class="dashboard-empty-state__copy">The guild hall is quiet. Form a new alliance to begin.</p>
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
