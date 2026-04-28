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

const tonePalette = [
    { border: '#2d65cf', bg: 'rgba(22, 47, 93, 0.22)', accent: '#8cc4ff' },
    { border: '#1b9a6a', bg: 'rgba(20, 82, 62, 0.20)', accent: '#8ff0c8' },
    { border: '#8b5cf6', bg: 'rgba(67, 46, 112, 0.22)', accent: '#ccb5ff' },
    { border: '#c97f1c', bg: 'rgba(92, 62, 20, 0.22)', accent: '#ffd28d' },
    { border: '#c24b79', bg: 'rgba(96, 35, 61, 0.22)', accent: '#ffb8d2' },
];

const hashGroupKey = (value) => {
    const normalized = String(value || 'global');
    let hash = 0;

    for (let index = 0; index < normalized.length; index += 1) {
        hash = ((hash << 5) - hash) + normalized.charCodeAt(index);
        hash |= 0;
    }

    return Math.abs(hash);
};

const toneStyleForGuide = (item) => {
    const toneKey = item?.study_group_id ?? item?.study_group?.id ?? item?.study_group?.name ?? 'global';
    const tone = tonePalette[hashGroupKey(toneKey) % tonePalette.length];

    return {
        '--guide-tone-border': tone.border,
        '--guide-tone-bg': tone.bg,
        '--guide-tone-accent': tone.accent,
    };
};
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
                class="library-item-card border p-4 shadow-[0_12px_22px_rgba(3,8,16,0.34)]"
                :style="toneStyleForGuide(item)"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="library-item-label text-[7px] uppercase">Study_Material</span>
                    <span class="text-[7px] uppercase text-slate-600">Ref.{{ item.uuid.substring(0, 5) }}</span>
                </div>
                <h3 class="mt-3 break-words text-[10px] uppercase text-white">{{ item.title }}</h3>
                <p class="mt-3 line-clamp-3 text-[8px] uppercase leading-relaxed text-slate-500">
                    {{ item.description || 'Accessing knowledge database...' }}
                </p>
                <div class="mt-4 flex items-center justify-between gap-2 border-t border-slate-800 pt-3">
                    <span class="library-item-group text-[7px] uppercase">
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

.library-item-card {
    border-color: color-mix(in srgb, var(--guide-tone-border) 52%, #1f2937 48%);
    background:
        linear-gradient(
            180deg,
            var(--guide-tone-bg) 0%,
            rgba(13, 17, 23, 0.92) 100%
        );
}

.library-item-label {
    color: color-mix(in srgb, var(--guide-tone-accent) 86%, #cbd5e1 14%);
}

.library-item-group {
    color: color-mix(in srgb, var(--guide-tone-accent) 90%, #f8fafc 10%);
}
</style>
