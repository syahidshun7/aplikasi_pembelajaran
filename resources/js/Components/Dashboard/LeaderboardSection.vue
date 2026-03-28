<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    items: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <section class="dashboard-section-shell">
        <div class="dashboard-section-header">
            <div>
                <p class="dashboard-section-header__eyebrow text-cyan-300/80">Leaderboard</p>
                <h2 class="dashboard-section-header__title text-cyan-300">Top Adventurers</h2>
            </div>
            <span class="rounded-full border border-cyan-400/20 bg-cyan-400/10 px-3 py-2 text-[8px] uppercase text-cyan-100">
                Ranking Live
            </span>
        </div>

        <div v-if="items.length > 0" class="grid gap-4 xl:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)]">
            <article class="border border-cyan-400/20 bg-[#0d1117] p-5 shadow-[0_0_30px_rgba(34,211,238,0.1)]">
                <p class="text-[7px] uppercase tracking-[0.24em] text-cyan-300/70">Rank #1</p>
                <div class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-center">
                    <div class="relative">
                        <div class="absolute -left-2 -top-2 rounded-full border border-yellow-500/40 bg-yellow-500/10 px-2 py-1 text-[7px] uppercase text-yellow-300">
                            Champion
                        </div>
                        <div class="h-20 w-20 overflow-hidden rounded-[20px] border-2 border-cyan-300/40 bg-slate-800">
                            <img v-if="items[0]?.profile_photo" :src="'/storage/' + items[0].profile_photo" loading="lazy" decoding="async" class="h-full w-full object-cover">
                            <img v-else :src="items[0].__dicebear_src" loading="lazy" decoding="async" class="h-full w-full">
                        </div>
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 class="truncate text-[12px] uppercase text-white">{{ items[0].username || items[0].name }}</h3>
                        <p class="mt-2 text-[8px] uppercase text-slate-400">{{ items[0].role || 'Adventurer' }}</p>
                        <div class="mt-4 flex flex-wrap items-center gap-2 text-[8px] uppercase">
                            <span class="rounded-full border border-cyan-400/20 bg-cyan-400/10 px-2 py-1 text-cyan-100">Level {{ items[0].level || 1 }}</span>
                            <span class="rounded-full border border-emerald-400/20 bg-emerald-400/10 px-2 py-1 text-emerald-100">{{ items[0].__score }} PTS</span>
                        </div>
                    </div>
                </div>
            </article>

            <div class="space-y-3">
                <Link
                    v-for="(player, index) in items"
                    :key="player.id"
                    :href="route('profiles.show', { user: player.username })"
                    class="flex items-center gap-3 border border-slate-800 bg-[#0d1117] p-3 transition-all hover:border-cyan-500/60"
                >
                    <div class="flex h-10 w-10 items-center justify-center rounded-full border border-cyan-400/20 bg-cyan-400/10 text-[9px] uppercase text-cyan-100">
                        #{{ index + 1 }}
                    </div>

                    <div class="h-11 w-11 overflow-hidden rounded-[14px] border border-slate-700 bg-slate-800">
                        <img v-if="player.profile_photo" :src="'/storage/' + player.profile_photo" loading="lazy" decoding="async" class="h-full w-full object-cover">
                        <img v-else :src="player.__dicebear_src" loading="lazy" decoding="async" class="h-full w-full">
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="flex items-center justify-between gap-2">
                            <span class="truncate text-[9px] uppercase text-white">{{ player.username || player.name }}</span>
                            <span class="text-[8px] uppercase text-cyan-300">LVL {{ player.level || 1 }}</span>
                        </div>
                        <div class="mt-2 flex items-center justify-between gap-2 text-[7px] uppercase text-slate-500">
                            <span>{{ player.__score }} PTS</span>
                            <span>Visit ></span>
                        </div>
                    </div>
                </Link>
            </div>
        </div>

        <div v-else class="dashboard-empty-state">
            <div class="dashboard-empty-state__icon">#</div>
            <h3 class="dashboard-empty-state__title text-cyan-300">No Players Found</h3>
            <p class="dashboard-empty-state__copy">Leaderboard data will appear once players start progressing.</p>
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
