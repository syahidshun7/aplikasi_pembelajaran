<script setup>
import { router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    board: {
        type: Object,
        default: null,
    },
});

const claimProcessingUuid = ref(null);
const nowMs = ref(0);
let timerId = null;

const statusLabelMap = {
    pending: 'Pending',
    completed: 'Completed',
    claimed: 'Claimed',
    expired: 'Expired',
};

const statusClassMap = {
    pending: 'text-amber-300 border-amber-600/60 bg-amber-500/10',
    completed: 'text-cyan-200 border-cyan-500/60 bg-cyan-500/10',
    claimed: 'text-emerald-200 border-emerald-500/60 bg-emerald-500/10',
    expired: 'text-rose-200 border-rose-500/60 bg-rose-500/10',
};

const nextResetTimestamp = computed(() => {
    const value = props.board?.next_reset_at;
    const timestamp = value ? Date.parse(value) : Number.NaN;

    return Number.isFinite(timestamp) ? timestamp : null;
});

const resetCountdown = computed(() => {
    if (!nextResetTimestamp.value) {
        return 'SYNCING';
    }

    const remainingMs = Math.max(0, nextResetTimestamp.value - nowMs.value);
    const totalSeconds = Math.floor(remainingMs / 1000);
    const hours = String(Math.floor(totalSeconds / 3600)).padStart(2, '0');
    const minutes = String(Math.floor((totalSeconds % 3600) / 60)).padStart(2, '0');
    const seconds = String(totalSeconds % 60).padStart(2, '0');

    return `${hours}:${minutes}:${seconds}`;
});

const items = computed(() => {
    return Array.isArray(props.board?.items) ? props.board.items : [];
});

const summary = computed(() => {
    return props.board?.summary ?? {};
});

const claimReward = (quest) => {
    if (!quest?.uuid || claimProcessingUuid.value) {
        return;
    }

    claimProcessingUuid.value = quest.uuid;

    router.post(route('daily-quests.claim', quest.uuid), {}, {
        preserveScroll: true,
        onFinish: () => {
            claimProcessingUuid.value = null;
        },
    });
};

onMounted(() => {
    const initialServerNow = props.board?.server_now ? Date.parse(props.board.server_now) : Date.now();
    nowMs.value = Number.isFinite(initialServerNow) ? initialServerNow : Date.now();

    timerId = window.setInterval(() => {
        nowMs.value += 1000;
    }, 1000);
});

onBeforeUnmount(() => {
    if (timerId) {
        window.clearInterval(timerId);
    }
});
</script>

<template>
    <section id="daily-quests" class="daily-quest-shell">
        <div class="daily-quest-header">
            <div>
                <p class="daily-quest-header__eyebrow">Daily Quest</p>
                <h2 class="daily-quest-header__title">Bonus Progress Board</h2>
                <p class="daily-quest-header__copy">
                    Progress dibaca dari aktivitas harian tanpa menyentuh sistem nilai utama.
                </p>
            </div>

            <div class="daily-quest-reset">
                <span class="daily-quest-reset__label">Reset</span>
                <strong class="daily-quest-reset__timer">{{ resetCountdown }}</strong>
                <span class="daily-quest-reset__timezone">{{ board?.timezone || 'UTC' }}</span>
            </div>
        </div>

        <div
            v-if="Number(summary.claimable || 0) > 0"
            class="daily-quest-claimable-banner"
        >
            <div>
                <p class="daily-quest-claimable-banner__title">Reward Ready To Claim</p>
                <p class="daily-quest-claimable-banner__copy">
                    {{ summary.claimable }} daily quest selesai. Klik tombol claim pada quest yang sudah complete.
                </p>
            </div>
            <strong class="daily-quest-claimable-banner__count">
                {{ summary.claimable }}
            </strong>
        </div>

        <div class="daily-quest-summary-grid">
            <article class="daily-quest-summary-card">
                <span class="daily-quest-summary-card__label">Completed Today</span>
                <strong class="daily-quest-summary-card__value text-cyan-200">
                    {{ summary.completed || 0 }}/{{ summary.total || 0 }}
                </strong>
            </article>
            <article class="daily-quest-summary-card">
                <span class="daily-quest-summary-card__label">Claimed Bonus</span>
                <strong class="daily-quest-summary-card__value text-emerald-200">
                    +{{ summary.today_claimed_exp || 0 }} EXP / +{{ summary.today_claimed_gold || 0 }} GOLD
                </strong>
            </article>
            <article class="daily-quest-summary-card">
                <span class="daily-quest-summary-card__label">Lifetime Bonus</span>
                <strong class="daily-quest-summary-card__value text-amber-200">
                    {{ summary.bonus_exp_total || 0 }} EXP / {{ summary.bonus_gold_total || 0 }} GOLD
                </strong>
            </article>
        </div>

        <div v-if="items.length" class="daily-quest-grid">
            <article
                v-for="quest in items"
                :key="quest.uuid"
                class="daily-quest-item"
            >
                <div class="daily-quest-item__top">
                    <div class="space-y-2">
                        <h3 class="daily-quest-item__title">{{ quest.title }}</h3>
                        <p class="daily-quest-item__description">{{ quest.description }}</p>
                    </div>

                    <span
                        class="daily-quest-item__status"
                        :class="statusClassMap[quest.status] || statusClassMap.pending"
                    >
                        {{ statusLabelMap[quest.status] || 'Pending' }}
                    </span>
                </div>

                <div class="daily-quest-item__progress-meta">
                    <span>Progress {{ quest.progress }}/{{ quest.target }}</span>
                    <span>{{ quest.progress_percent || 0 }}%</span>
                </div>

                <div class="daily-quest-item__progress-track">
                    <div
                        class="daily-quest-item__progress-fill"
                        :style="{ width: `${Math.max(0, Math.min(100, Number(quest.progress_percent || 0)))}%` }"
                    ></div>
                </div>

                <div class="daily-quest-item__footer">
                    <div class="daily-quest-item__reward">
                        <span>Reward</span>
                        <strong>+{{ quest.reward_exp }} EXP / +{{ quest.reward_gold }} GOLD</strong>
                    </div>

                    <button
                        type="button"
                        class="daily-quest-item__claim-btn"
                        :disabled="!quest.is_claimable || claimProcessingUuid === quest.uuid"
                        :class="quest.is_claimable ? 'daily-quest-item__claim-btn--active' : 'daily-quest-item__claim-btn--idle'"
                        @click="claimReward(quest)"
                    >
                        {{ claimProcessingUuid === quest.uuid ? 'Claiming...' : (quest.is_claimable ? 'Claim Reward' : (quest.status === 'claimed' ? 'Claimed' : 'Locked')) }}
                    </button>
                </div>
            </article>
        </div>

        <div v-else class="daily-quest-empty">
            <p class="daily-quest-empty__title">No Daily Quest Available</p>
            <p class="daily-quest-empty__copy">
                Saat ini belum ada quest bonus yang cocok untuk akunmu. Sistem akan generate otomatis saat aktivitas relevan tersedia.
            </p>
        </div>
    </section>
</template>

<style scoped>
.daily-quest-shell {
    @apply mb-5 border-2 border-[#3d415f] bg-[linear-gradient(135deg,rgba(10,18,28,0.96),rgba(17,25,39,0.96))] p-4 shadow-[0_18px_50px_rgba(2,8,16,0.46)] md:p-5;
}

.daily-quest-header {
    @apply mb-4 flex flex-col gap-4 border-b border-cyan-900/60 pb-4 md:flex-row md:items-start md:justify-between;
}

.daily-quest-header__eyebrow {
    @apply text-[7px] uppercase tracking-[0.28em] text-cyan-300/80;
}

.daily-quest-header__title {
    @apply mt-2 text-[11px] uppercase tracking-[0.18em] text-white;
}

.daily-quest-header__copy {
    @apply mt-3 max-w-[560px] text-[7px] uppercase leading-relaxed tracking-[0.12em] text-slate-400;
}

.daily-quest-reset {
    @apply flex min-w-[150px] flex-col border border-cyan-500/40 bg-cyan-500/10 px-3 py-3 text-left md:items-end md:text-right;
}

.daily-quest-reset__label {
    @apply text-[7px] uppercase tracking-[0.2em] text-cyan-200/70;
}

.daily-quest-reset__timer {
    @apply mt-2 text-[13px] uppercase tracking-[0.14em] text-cyan-100;
}

.daily-quest-reset__timezone {
    @apply mt-2 text-[7px] uppercase tracking-[0.18em] text-slate-400;
}

.daily-quest-summary-grid {
    @apply mb-4 grid gap-3 md:grid-cols-3;
}

.daily-quest-claimable-banner {
    @apply mb-4 flex items-center justify-between gap-4 border border-emerald-500/60 bg-emerald-500/10 px-4 py-3;
}

.daily-quest-claimable-banner__title {
    @apply text-[8px] uppercase tracking-[0.18em] text-emerald-100;
}

.daily-quest-claimable-banner__copy {
    @apply mt-2 text-[7px] uppercase leading-relaxed tracking-[0.12em] text-emerald-200/80;
}

.daily-quest-claimable-banner__count {
    @apply text-[18px] uppercase text-emerald-200;
}

.daily-quest-summary-card {
    @apply border border-slate-700/80 bg-black/20 px-3 py-3;
}

.daily-quest-summary-card__label {
    @apply block text-[7px] uppercase tracking-[0.18em] text-slate-500;
}

.daily-quest-summary-card__value {
    @apply mt-2 block text-[9px] uppercase leading-relaxed tracking-[0.12em];
}

.daily-quest-grid {
    @apply grid gap-4 xl:grid-cols-3;
}

.daily-quest-item {
    @apply flex min-h-[220px] flex-col border border-slate-700 bg-[#111827]/80 p-4;
}

.daily-quest-item__top {
    @apply flex items-start justify-between gap-3;
}

.daily-quest-item__title {
    @apply text-[9px] uppercase leading-relaxed tracking-[0.12em] text-white;
}

.daily-quest-item__description {
    @apply text-[7px] uppercase leading-relaxed tracking-[0.1em] text-slate-400;
}

.daily-quest-item__status {
    @apply shrink-0 border px-2 py-1 text-[6px] uppercase tracking-[0.16em];
}

.daily-quest-item__progress-meta {
    @apply mt-5 flex items-center justify-between text-[7px] uppercase tracking-[0.16em] text-slate-400;
}

.daily-quest-item__progress-track {
    @apply mt-2 h-3 overflow-hidden border border-slate-700 bg-slate-900;
}

.daily-quest-item__progress-fill {
    @apply h-full bg-[linear-gradient(90deg,#06b6d4,#22c55e)];
    transition: width 0.3s ease;
}

.daily-quest-item__footer {
    @apply mt-auto flex flex-col gap-4 border-t border-slate-800 pt-4;
}

.daily-quest-item__reward {
    @apply flex flex-col gap-1 text-[7px] uppercase tracking-[0.14em] text-slate-400;
}

.daily-quest-item__reward strong {
    @apply text-[8px] text-amber-200;
}

.daily-quest-item__claim-btn {
    @apply border-b-4 border-r-4 px-3 py-2 text-[8px] uppercase transition-colors;
}

.daily-quest-item__claim-btn--active {
    @apply border-emerald-800 bg-emerald-500 text-black hover:bg-emerald-300;
}

.daily-quest-item__claim-btn--idle {
    @apply cursor-not-allowed border-slate-900 bg-slate-700 text-slate-300;
}

.daily-quest-empty {
    @apply border border-dashed border-slate-700 px-4 py-8 text-center;
}

.daily-quest-empty__title {
    @apply text-[10px] uppercase tracking-[0.16em] text-slate-200;
}

.daily-quest-empty__copy {
    @apply mx-auto mt-3 max-w-[460px] text-[7px] uppercase leading-relaxed tracking-[0.12em] text-slate-500;
}
</style>
