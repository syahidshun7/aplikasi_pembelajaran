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
                <p class="dashboard-section-header__eyebrow text-indigo-300/80">Knowledge</p>
                <h2 class="dashboard-section-header__title text-indigo-300">Library Archive</h2>
            </div>
            <Link
                :href="authUser ? route('guides.user.index') : route('login')"
                class="dashboard-section-header__action border-indigo-500 bg-indigo-900/30 text-indigo-200 hover:bg-indigo-500/40"
            >
                <i class="fi fi-rr-book-alt text-[18px] leading-none"></i>
                <span>View All Materials</span>
            </Link>
        </div>

        <div v-if="items.length > 0" class="grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
            <article
                v-for="item in items"
                :key="item.uuid"
                class="border border-slate-800 bg-[#0d1117] p-4 shadow-[0_12px_22px_rgba(3,8,16,0.34)]"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="text-[7px] uppercase text-indigo-400">Study_Material</span>
                    <span class="text-[7px] uppercase text-slate-600">Ref.{{ item.uuid.substring(0, 5) }}</span>
                </div>
                <h3 class="mt-3 break-words text-[10px] uppercase text-white">{{ item.title }}</h3>
                <p class="mt-3 line-clamp-3 text-[8px] uppercase leading-relaxed text-slate-500">
                    {{ item.description || 'Accessing knowledge database...' }}
                </p>
                <div class="mt-4 flex items-center justify-between gap-2 border-t border-slate-800 pt-3">
                    <span class="text-[7px] uppercase" :class="item.study_group_id ? 'text-emerald-400' : 'text-cyan-400'">
                        {{ item.study_group_id ? `Party: ${item.study_group?.name || 'Unknown'}` : 'Global' }}
                    </span>
                    <Link :href="route('guides.user.show', item.uuid)" class="text-[7px] uppercase text-indigo-300 hover:text-white">
                        Detail >
                    </Link>
                </div>
            </article>
        </div>

        <div v-else class="dashboard-empty-state">
            <div class="dashboard-empty-state__icon">?</div>
            <h3 class="dashboard-empty-state__title text-indigo-300">Database Empty</h3>
            <p class="dashboard-empty-state__copy">The library is waiting for the next set of materials.</p>
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
