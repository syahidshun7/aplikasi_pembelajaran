<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    items: {
        type: Array,
        default: () => [],
    },
    authUser: {
        type: Boolean,
        default: false,
    },
    dailyQuestBoard: {
        type: Object,
        default: null,
    },
});

const claimProcessingUuid = ref(null);

const dailyQuestSummary = computed(() => props.dailyQuestBoard?.summary ?? {});
const claimableDailyQuests = computed(() => {
    const items = Array.isArray(props.dailyQuestBoard?.items) ? props.dailyQuestBoard.items : [];

    return items.filter((quest) => Boolean(quest?.is_claimable));
});

const claimDailyQuest = (quest) => {
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
</script>

<template>
    <section class="dashboard-section-shell">
        <div class="dashboard-section-header">
            <div>
                <p class="dashboard-section-header__eyebrow text-yellow-300/80">Quest Feed</p>
                <h2 class="dashboard-section-header__title text-yellow-300">Available Quests</h2>
            </div>
            <Link
                :href="authUser ? route('quests.user.index') : route('login')"
                class="dashboard-section-header__action border-yellow-700 bg-yellow-900/30 text-yellow-200 hover:bg-yellow-500/40"
            >
                <i class="fi fi-rr-target text-[18px] leading-none"></i>
                <span>View All Quests</span>
            </Link>
        </div>

        <article
            v-if="dailyQuestBoard"
            class="daily-claim-card"
        >
            <div class="daily-claim-card__header">
                <div>
                    <p class="daily-claim-card__eyebrow">Daily Quest</p>
                    <h3 class="daily-claim-card__title">Reward Claim Board</h3>
                    <p class="daily-claim-card__copy">
                        {{ claimableDailyQuests.length > 0
                            ? `${claimableDailyQuests.length} reward siap di-claim hari ini.`
                            : 'Belum ada reward yang siap di-claim. Selesaikan aktivitas harian dulu.' }}
                    </p>
                </div>

                <div class="daily-claim-card__count-shell">
                    <span class="daily-claim-card__count-label">Claimable</span>
                    <strong class="daily-claim-card__count">{{ claimableDailyQuests.length }}</strong>
                </div>
            </div>

            <div class="daily-claim-card__meta-grid">
                <div class="daily-claim-card__meta">
                    <span class="daily-claim-card__meta-label">Completed</span>
                    <strong class="daily-claim-card__meta-value text-cyan-200">
                        {{ dailyQuestSummary.completed || 0 }}/{{ dailyQuestSummary.total || 0 }}
                    </strong>
                </div>
                <div class="daily-claim-card__meta">
                    <span class="daily-claim-card__meta-label">Claimed Today</span>
                    <strong class="daily-claim-card__meta-value text-emerald-200">
                        +{{ dailyQuestSummary.today_claimed_exp || 0 }} EXP / +{{ dailyQuestSummary.today_claimed_gold || 0 }} GOLD
                    </strong>
                </div>
            </div>

            <div v-if="claimableDailyQuests.length > 0" class="daily-claim-card__list">
                <article
                    v-for="quest in claimableDailyQuests"
                    :key="quest.uuid"
                    class="daily-claim-card__item"
                >
                    <div class="daily-claim-card__item-copy">
                        <h4 class="daily-claim-card__item-title">{{ quest.title }}</h4>
                        <p class="daily-claim-card__item-description">{{ quest.description }}</p>
                        <p class="daily-claim-card__item-reward">
                            Reward +{{ quest.reward_exp }} EXP / +{{ quest.reward_gold }} GOLD
                        </p>
                    </div>

                    <button
                        type="button"
                        class="daily-claim-card__action"
                        :disabled="claimProcessingUuid === quest.uuid"
                        @click="claimDailyQuest(quest)"
                    >
                        {{ claimProcessingUuid === quest.uuid ? 'Claiming...' : 'Claim' }}
                    </button>
                </article>
            </div>

            <div v-else class="daily-claim-card__empty">
                <span class="daily-claim-card__empty-title">No reward ready</span>
                <span class="daily-claim-card__empty-copy">
                    Kalau quest sudah complete, reward akan muncul di card ini.
                </span>
            </div>
        </article>

        <div v-if="items.length > 0" class="grid gap-4 xl:grid-cols-2">
            <article
                v-for="quest in items"
                :key="quest.uuid"
                class="group flex min-h-[220px] flex-col border-2 bg-[#161b22] p-4 transition-all"
                :class="[
                    (quest.user_submission_status === 'Approved') ? 'border-emerald-500 shadow-[0_0_12px_rgba(16,185,129,0.35)] bg-emerald-950/20' :
                    (quest.user_submission_status === 'Pending') ? 'border-yellow-500 shadow-[0_0_12px_rgba(234,179,8,0.35)] bg-yellow-950/20' :
                    (quest.user_has_unlock && !quest.user_has_submitted) ? 'border-cyan-500 shadow-[0_0_12px_rgba(34,211,238,0.3)] bg-cyan-950/20' :
                    (quest.status === 'Done' && !quest.user_has_submitted && !quest.user_has_unlock) ? 'border-red-600 shadow-[0_0_10px_rgba(220,38,38,0.2)]' :
                    (quest.status === 'In-Progress') ? 'border-slate-500 bg-slate-900/50' :
                    quest.user_has_submitted ? 'border-yellow-600 shadow-[0_0_10px_rgba(202,138,4,0.2)]' : 'border-slate-700 hover:border-[#009999]'
                ]"
            >
                <div class="mb-3 flex items-start justify-between gap-2">
                    <span class="border border-slate-600 bg-slate-800 px-2 py-1 text-[7px] uppercase text-slate-400">
                        ID:{{ quest.id }}
                    </span>
                    <span
                        :class="{
                            'text-red-500': quest.difficulty === 'S-Rank',
                            'text-orange-500': quest.difficulty === 'A-Rank',
                            'text-cyan-500': quest.difficulty === 'B-Rank',
                            'text-green-500': quest.difficulty === 'C-Rank'
                        }"
                        class="text-[8px] font-bold tracking-widest"
                    >
                        {{ quest.difficulty }}
                    </span>
                </div>

                <h3 class="mb-2 line-clamp-3 break-words text-[9px] uppercase leading-relaxed text-white group-hover:text-[#4ed4d4] sm:text-[10px]">
                    {{ quest.title }}
                </h3>

                <div class="mb-2 flex items-center gap-1">
                    <span class="text-[6px] uppercase tracking-tighter text-orange-500">Deadline:</span>
                    <span
                        :class="[
                            'text-[7px] uppercase font-bold tracking-tighter',
                            quest.__deadline_overdue ? 'animate-pulse text-red-500' : 'text-orange-300'
                        ]"
                    >
                        {{ quest.__deadline_label }}
                    </span>
                </div>

                <div class="flex-grow">
                    <p v-if="quest.status === 'Done' && !quest.user_has_submitted && !quest.user_has_unlock" class="text-[6px] uppercase text-red-500">
                        Mission_Expired
                    </p>
                    <p v-if="quest.user_has_unlock && !quest.user_has_submitted" class="text-[6px] uppercase italic tracking-widest text-cyan-300">
                        Quest_Reopened_With_Time_Key
                    </p>
                    <p v-if="quest.user_submission_status === 'Pending'" class="text-[6px] uppercase italic tracking-widest text-yellow-400">
                        Waiting_For_Review...
                    </p>
                    <p v-if="quest.user_submission_status === 'Approved'" class="text-[6px] uppercase italic tracking-widest text-emerald-400">
                        Approved!
                    </p>
                    <p v-if="quest.status === 'In-Progress'" class="text-[6px] uppercase italic tracking-widest text-slate-500">
                        Active_In_Journal...
                    </p>
                </div>

                <div class="mt-4 flex flex-col gap-3 border-t border-slate-800 pt-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex flex-col">
                        <span class="mb-1 text-[6px] uppercase text-slate-500">Reward</span>
                        <span class="text-[8px] font-bold tracking-tighter text-yellow-500">{{ quest.reward_gold }}G</span>
                    </div>

                    <template v-if="quest.status !== 'In-Progress'">
                        <Link
                            :href="route('quests.show', quest.uuid)"
                            :class="[
                                'btn-pixel self-start whitespace-nowrap px-3 py-2 text-[8px] font-bold uppercase transition-colors sm:self-auto',
                                (quest.user_submission_status === 'Approved') ? 'border-emerald-800 bg-emerald-600 text-black hover:bg-emerald-400' :
                                (quest.user_submission_status === 'Pending') ? 'border-yellow-800 bg-yellow-600 text-black hover:bg-yellow-400' :
                                (quest.user_has_unlock && !quest.user_has_submitted) ? 'border-cyan-800 bg-cyan-600 text-black hover:bg-cyan-400' :
                                (quest.status === 'Done' && !quest.user_has_submitted && !quest.user_has_unlock) ? 'border-red-950 bg-red-700 text-white hover:bg-red-600' :
                                quest.user_has_submitted ? 'border-slate-900 bg-slate-700 text-white hover:bg-slate-600' :
                                'border-[#006666] bg-[#009999] text-black hover:bg-[#4ed4d4]'
                            ]"
                        >
                            <template v-if="quest.user_submission_status === 'Approved'">View</template>
                            <template v-else-if="quest.user_submission_status === 'Pending'">Preview</template>
                            <template v-else-if="quest.user_has_unlock && !quest.user_has_submitted">Continue</template>
                            <template v-else-if="quest.status === 'Done' && !quest.user_has_submitted && !quest.user_has_unlock">Late</template>
                            <template v-else>{{ quest.user_has_submitted ? 'View' : 'Take_Quest' }}</template>
                        </Link>
                    </template>
                </div>
            </article>
        </div>

        <div v-else class="dashboard-empty-state">
            <div class="dashboard-empty-state__icon">!</div>
            <h3 class="dashboard-empty-state__title text-[#4ed4d4]">No Quests Available</h3>
            <p class="dashboard-empty-state__copy">
                Your quest journal is empty. Join a <span class="text-white underline">Party / Study Group</span> to unlock exclusive missions.
            </p>
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
    @apply flex min-h-[320px] flex-col items-center justify-center border-2 border-dashed border-slate-800 p-6 text-center;
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

.daily-claim-card {
    @apply mb-6 border border-cyan-900/70 bg-[linear-gradient(135deg,rgba(8,18,28,0.92),rgba(12,24,40,0.92))] p-4 shadow-[0_12px_32px_rgba(2,8,16,0.36)];
}

.daily-claim-card__header {
    @apply flex flex-col gap-4 border-b border-cyan-950/80 pb-4 md:flex-row md:items-start md:justify-between;
}

.daily-claim-card__eyebrow {
    @apply text-[7px] uppercase tracking-[0.24em] text-cyan-300/80;
}

.daily-claim-card__title {
    @apply mt-2 text-[10px] uppercase tracking-[0.18em] text-white;
}

.daily-claim-card__copy {
    @apply mt-2 max-w-[520px] text-[7px] uppercase leading-relaxed tracking-[0.12em] text-slate-300;
}

.daily-claim-card__count-shell {
    @apply flex min-w-[112px] flex-col border border-emerald-500/40 bg-emerald-500/10 px-3 py-3 text-left md:items-end md:text-right;
}

.daily-claim-card__count-label {
    @apply text-[7px] uppercase tracking-[0.2em] text-emerald-200/70;
}

.daily-claim-card__count {
    @apply mt-2 text-[18px] uppercase tracking-[0.14em] text-emerald-100;
}

.daily-claim-card__meta-grid {
    @apply mt-4 grid gap-3 md:grid-cols-2;
}

.daily-claim-card__meta {
    @apply border border-slate-700/80 bg-slate-950/40 px-3 py-3;
}

.daily-claim-card__meta-label {
    @apply block text-[7px] uppercase tracking-[0.2em] text-slate-400;
}

.daily-claim-card__meta-value {
    @apply mt-2 block text-[9px] uppercase tracking-[0.14em];
}

.daily-claim-card__list {
    @apply mt-4 space-y-3;
}

.daily-claim-card__item {
    @apply flex flex-col gap-3 border border-slate-700/80 bg-slate-950/40 px-3 py-3 md:flex-row md:items-center md:justify-between;
}

.daily-claim-card__item-copy {
    @apply min-w-0;
}

.daily-claim-card__item-title {
    @apply text-[8px] uppercase tracking-[0.16em] text-white;
}

.daily-claim-card__item-description {
    @apply mt-2 text-[7px] uppercase leading-relaxed tracking-[0.1em] text-slate-400;
}

.daily-claim-card__item-reward {
    @apply mt-3 text-[7px] uppercase tracking-[0.16em] text-amber-200;
}

.daily-claim-card__action {
    @apply min-w-[108px] self-start border-b-4 border-r-4 border-emerald-800 bg-emerald-400 px-3 py-2 text-[8px] font-bold uppercase text-black shadow-[3px_3px_0px_0px_rgba(0,0,0,0.45)] transition-colors hover:bg-emerald-300 disabled:cursor-not-allowed disabled:border-slate-800 disabled:bg-slate-700 disabled:text-slate-300 md:self-auto;
}

.daily-claim-card__empty {
    @apply mt-4 flex flex-col items-center justify-center gap-2 border border-dashed border-slate-700 px-3 py-5 text-center;
}

.daily-claim-card__empty-title {
    @apply text-[8px] uppercase tracking-[0.18em] text-slate-200;
}

.daily-claim-card__empty-copy {
    @apply max-w-[360px] text-[7px] uppercase leading-relaxed tracking-[0.12em] text-slate-500;
}
</style>
