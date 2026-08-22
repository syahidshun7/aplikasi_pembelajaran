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
    newCount: {
        type: Number,
        default: 0,
    },
});

const claimProcessingUuid = ref(null);
const dailyQuestStatusLabelMap = {
    pending: 'On Progress',
    completed: 'Ready To Claim',
    claimed: 'Reward Claimed',
    expired: 'Expired',
};
const dailyQuestStatusClassMap = {
    pending: 'daily-claim-card__activity-status--pending',
    completed: 'daily-claim-card__activity-status--completed',
    claimed: 'daily-claim-card__activity-status--claimed',
    expired: 'daily-claim-card__activity-status--expired',
};

const dailyQuestItems = computed(() => {
    return Array.isArray(props.dailyQuestBoard?.items) ? props.dailyQuestBoard.items : [];
});
const claimableDailyQuestCount = computed(() => {
    return dailyQuestItems.value.filter((quest) => Boolean(quest?.is_claimable)).length;
});
const visibleNewItemCount = computed(() => {
    return (props.items || []).filter((quest) => Boolean(quest?.is_new_for_user)).length;
});
const hiddenNewItemCount = computed(() => {
    return Math.max(0, Number(props.newCount || 0) - visibleNewItemCount.value);
});

const claimDailyQuest = (quest) => {
    if (!quest?.uuid || claimProcessingUuid.value) {
        return;
    }

    claimProcessingUuid.value = quest.uuid;

    router.post(route('daily-quests.claim', quest.uuid), {}, {
        preserveScroll: true,
        preserveState: false,
        onFinish: () => {
            claimProcessingUuid.value = null;
        },
    });
};

const dailyQuestStatusLabel = (status) => {
    return dailyQuestStatusLabelMap[String(status || 'pending')] || 'On Progress';
};

const dailyQuestStatusClass = (status) => {
    return dailyQuestStatusClassMap[String(status || 'pending')] || dailyQuestStatusClassMap.pending;
};

const progressText = (quest) => {
    const progress = Number(quest?.progress || 0);
    const target = Math.max(1, Number(quest?.target || 1));

    return `${progress}/${target}`;
};

const isLateUnsubmitted = (quest) => {
    return Boolean(
        quest?.__deadline_overdue
        && !quest?.user_has_submitted
        && !quest?.user_has_unlock
    );
};

const hashGroupKey = (value) => {
    const normalized = String(value || 'global');
    let hash = 0;

    for (let index = 0; index < normalized.length; index += 1) {
        hash = ((hash << 5) - hash) + normalized.charCodeAt(index);
        hash |= 0;
    }

    return Math.abs(hash);
};

const toneForGroup = (groupKey) => {
    if (!groupKey || groupKey === 'global') {
        return {
            border: '#2d65cf',
            bg: 'rgba(22, 47, 93, 0.18)',
            accent: '#8cc4ff',
        };
    }

    const hash = hashGroupKey(groupKey);
    let hue = Math.floor((hash * 137.508) % 360);
    if (hue >= 185 && hue <= 225) {
        hue = (hue + 92) % 360;
    }
    const saturation = 66 + (hash % 8);
    const borderLightness = 56 + ((hash >> 3) % 7);
    const accentLightness = 74 + ((hash >> 5) % 8);
    const border = `hsl(${hue} ${saturation}% ${borderLightness}%)`;
    const accent = `hsl(${hue} ${Math.min(90, saturation + 10)}% ${accentLightness}%)`;
    const bg = `hsl(${hue} ${Math.max(60, saturation - 6)}% 20% / 0.22)`;

    return { border, bg, accent };
};

const toneStyleForQuest = (quest) => {
    const toneKey = quest?.study_group_id ?? quest?.study_group?.id ?? quest?.study_group?.name ?? 'global';
    const tone = toneForGroup(String(toneKey));

    return {
        '--quest-tone-border': tone.border,
        '--quest-tone-bg': tone.bg,
        '--quest-tone-accent': tone.accent,
    };
};

const partyLabelForQuest = (quest) => {
    if (!quest?.study_group_id) {
        return 'Global';
    }

    const groupName = String(quest?.study_group?.name || '').trim();
    if (groupName !== '') {
        return groupName;
    }

    return `#${quest.study_group_id}`;
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
                <span v-if="hiddenNewItemCount > 0" class="view-all-new-badge">{{ hiddenNewItemCount }} NEW</span>
            </Link>
        </div>

        <article
            v-if="dailyQuestBoard"
            class="daily-claim-card"
        >
            <div class="daily-claim-card__header">
                <h3 class="daily-claim-card__title">Daily Quest</h3>
                <span v-if="claimableDailyQuestCount > 0" class="daily-claim-card__activities-count">
                    {{ claimableDailyQuestCount }} Ready_To_Claim
                </span>
            </div>

            <div v-if="dailyQuestItems.length > 0" class="daily-claim-card__activities">
                <div class="daily-claim-card__activities-list">
                    <article
                        v-for="quest in dailyQuestItems"
                        :key="`activity-${quest.uuid}`"
                        class="daily-claim-card__activity"
                    >
                        <div class="daily-claim-card__activity-top">
                            <div class="daily-claim-card__activity-main">
                                <div>
                                    <h5 class="daily-claim-card__activity-title">{{ quest.title }}</h5>
                                    <span class="daily-claim-card__activity-progress-inline">
                                        {{ progressText(quest) }}
                                    </span>
                                </div>
                            </div>

                            <div class="daily-claim-card__activity-side">
                                <span class="daily-claim-card__activity-reward">
                                    +{{ quest.reward_exp }} EXP / +{{ quest.reward_gold }} GOLD
                                </span>
                                <button
                                    v-if="quest.is_claimable"
                                    type="button"
                                    class="daily-claim-card__action daily-claim-card__activity-action"
                                    :disabled="claimProcessingUuid === quest.uuid"
                                    @click="claimDailyQuest(quest)"
                                >
                                    {{ claimProcessingUuid === quest.uuid ? 'Claiming...' : 'Claim' }}
                                </button>
                                <span
                                    v-else
                                    class="daily-claim-card__activity-status"
                                    :class="dailyQuestStatusClass(quest.status)"
                                >
                                    {{ dailyQuestStatusLabel(quest.status) }}
                                </span>
                            </div>
                        </div>
                    </article>
                </div>
            </div>

            <div v-else class="daily-claim-card__empty">
                <span class="daily-claim-card__empty-title">No activity yet</span>
                <span class="daily-claim-card__empty-copy">
                    Daily quest akan muncul di sini saat objective hari ini sudah tersedia.
                </span>
            </div>
        </article>

        <div v-if="items.length > 0" class="grid gap-4 lg:grid-cols-2">
            <article
                v-for="quest in items"
                :key="quest.uuid"
                class="quest-item-card group flex min-h-[220px] flex-col border-2 p-4 transition-all"
                :style="toneStyleForQuest(quest)"
                :class="[
                    (quest.user_submission_status === 'Approved') ? 'border-emerald-500 shadow-[0_0_12px_rgba(16,185,129,0.35)] bg-emerald-950/20' :
                    (quest.user_submission_status === 'Pending') ? 'border-yellow-500 shadow-[0_0_12px_rgba(234,179,8,0.35)] bg-yellow-950/20' :
                    (quest.user_submission_status === 'Rejected') ? 'border-red-600 shadow-[0_0_10px_rgba(220,38,38,0.24)] bg-red-950/15' :
                    (quest.user_has_unlock && !quest.user_has_submitted) ? 'border-cyan-500 shadow-[0_0_12px_rgba(34,211,238,0.3)] bg-cyan-950/20' :
                    (isLateUnsubmitted(quest)) ? 'border-red-600 shadow-[0_0_10px_rgba(220,38,38,0.2)]' :
                    (quest.status === 'In-Progress') ? 'border-slate-500 bg-slate-900/50' :
                    quest.user_has_submitted ? 'border-yellow-600 shadow-[0_0_10px_rgba(202,138,4,0.2)]' : 'border-slate-700 hover:border-[#009999]'
                ]"
            >
                <div class="mb-3 flex items-start justify-between gap-2">
                    <div class="flex flex-wrap items-center gap-1.5">
                        <span class="border border-slate-600 bg-slate-800 px-2 py-1 text-[7px] uppercase text-slate-400">
                            ID:{{ quest.id }}
                        </span>
                        <span
                            v-if="quest.is_new_for_user"
                            class="quest-new-badge"
                        >
                            NEW
                        </span>
                    </div>
                    <div class="flex flex-wrap items-center justify-end gap-1.5">
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
                </div>

                <h3 class="mb-2 line-clamp-3 break-words text-[9px] uppercase leading-relaxed text-white group-hover:text-[#4ed4d4] sm:text-[10px]">
                    {{ quest.title }}
                </h3>

                <div class="mb-2">
                    <div class="flex flex-wrap items-center gap-1.5">
                        <span
                            class="inline-flex border px-2 py-1 text-[6px] uppercase tracking-[0.16em]"
                            :class="String(quest.quest_type || 'main') === 'optional'
                                ? 'border-lime-500/30 bg-lime-500/10 text-lime-200'
                                : 'border-sky-500/30 bg-sky-500/10 text-sky-200'"
                        >
                            {{ String(quest.quest_type || 'main') === 'optional' ? 'Optional Bonus Quest' : 'Main Quest' }}
                        </span>
                        <span class="quest-group-badge px-2 py-1 border text-[6px] uppercase tracking-[0.14em]">
                            {{ partyLabelForQuest(quest) }}
                        </span>
                    </div>
                </div>

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

                <div
                    v-if="quest.__schedule_once && quest.__schedule_until_label"
                    class="mb-2 flex flex-wrap items-center gap-1.5"
                >
                    <span class="text-[6px] uppercase tracking-tighter text-cyan-500">Ends:</span>
                    <span class="text-[7px] font-bold uppercase tracking-tighter text-cyan-200">
                        {{ quest.__schedule_until_label }}
                    </span>
                    <span class="rounded border border-cyan-500/30 bg-cyan-500/10 px-1.5 py-0.5 text-[6px] font-bold uppercase tracking-[0.18em] text-cyan-100">
                        {{ quest.__schedule_countdown_label }}
                    </span>
                </div>

                <div class="flex-grow">
                    <p v-if="isLateUnsubmitted(quest)" class="text-[6px] uppercase text-red-500">
                        Mission_Expired
                    </p>
                    <p v-if="quest.user_has_unlock && !quest.user_has_submitted" class="text-[6px] uppercase italic tracking-widest text-cyan-300">
                        Quest_Reopened_With_Time_Key
                    </p>
                    <p v-if="quest.user_submission_status === 'Pending'" class="text-[6px] uppercase italic tracking-widest text-yellow-400">
                        Waiting_For_Review...
                    </p>
                    <p v-if="quest.user_submission_status === 'Rejected'" class="text-[6px] uppercase italic tracking-widest text-red-400">
                        Rejected
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
                                (quest.user_submission_status === 'Rejected') ? 'border-red-950 bg-red-700 text-white hover:bg-red-600' :
                                (quest.user_has_unlock && !quest.user_has_submitted) ? 'border-cyan-800 bg-cyan-600 text-black hover:bg-cyan-400' :
                                (isLateUnsubmitted(quest)) ? 'border-red-950 bg-red-700 text-white hover:bg-red-600' :
                                quest.user_has_submitted ? 'border-slate-900 bg-slate-700 text-white hover:bg-slate-600' :
                                'border-[#006666] bg-[#009999] text-black hover:bg-[#4ed4d4]'
                            ]"
                        >
                            <template v-if="quest.user_submission_status === 'Approved'">View</template>
                            <template v-else-if="quest.user_submission_status === 'Pending'">Preview</template>
                            <template v-else-if="quest.user_submission_status === 'Rejected'">Rejected</template>
                            <template v-else-if="quest.user_has_unlock && !quest.user_has_submitted">Continue</template>
                            <template v-else-if="isLateUnsubmitted(quest)">Late</template>
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

.view-all-new-badge {
    @apply inline-flex min-w-[30px] items-center justify-center rounded-full border border-red-200 bg-red-500 px-1.5 py-0.5 text-[7px] font-bold leading-none text-white shadow-[0_0_10px_rgba(239,68,68,0.45)];
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
    @apply flex flex-wrap items-center justify-between gap-3 border-b border-cyan-950/80 pb-3;
}

.daily-claim-card__title {
    @apply text-[10px] uppercase tracking-[0.18em] text-white;
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

.quest-item-card {
    background:
        linear-gradient(
            180deg,
            var(--quest-tone-bg) 0%,
            rgba(22, 27, 34, 0.94) 100%
        );
}

.quest-group-badge {
    border-color: color-mix(in srgb, var(--quest-tone-border) 58%, transparent 42%);
    background: color-mix(in srgb, var(--quest-tone-bg) 76%, transparent 24%);
    color: color-mix(in srgb, var(--quest-tone-accent) 90%, #f8fafc 10%);
}

.quest-new-badge {
    @apply inline-flex items-center justify-center border border-red-300 bg-red-500 px-2 py-1 text-[7px] font-bold uppercase tracking-[0.16em] text-white shadow-[0_0_12px_rgba(239,68,68,0.62)];
}

.daily-claim-card__empty-copy {
    @apply max-w-[360px] text-[7px] uppercase leading-relaxed tracking-[0.12em] text-slate-500;
}

.daily-claim-card__activities {
    @apply mt-3;
}

.daily-claim-card__activities-count {
    @apply self-start border border-cyan-500/30 bg-cyan-500/10 px-2 py-1 text-[7px] uppercase tracking-[0.18em] text-cyan-100;
}

.daily-claim-card__activities-list {
    @apply space-y-2;
}

.daily-claim-card__activity {
    @apply border border-slate-700/80 bg-slate-950/40 px-3 py-3;
}

.daily-claim-card__activity-top {
    @apply flex flex-col gap-3 md:flex-row md:items-center md:justify-between;
}

.daily-claim-card__activity-main {
    @apply flex min-w-0 items-center gap-2;
}

.daily-claim-card__activity-side {
    @apply flex flex-wrap items-center gap-3 md:justify-end;
}

.daily-claim-card__activity-title {
    @apply text-[8px] uppercase tracking-[0.16em] text-white;
}

.daily-claim-card__activity-reward {
    @apply text-[7px] uppercase tracking-[0.16em] text-amber-200;
}

.daily-claim-card__activity-progress-inline {
    @apply mt-1 block text-[7px] uppercase tracking-[0.14em] text-cyan-200;
}

.daily-claim-card__activity-status {
    @apply border px-2 py-1 text-[6px] uppercase tracking-[0.18em];
}

.daily-claim-card__activity-status--pending {
    @apply border-amber-500/40 bg-amber-500/10 text-amber-200;
}

.daily-claim-card__activity-status--completed {
    @apply border-cyan-500/40 bg-cyan-500/10 text-cyan-100;
}

.daily-claim-card__activity-status--claimed {
    @apply border-emerald-500/40 bg-emerald-500/10 text-emerald-100;
}

.daily-claim-card__activity-status--expired {
    @apply border-rose-500/40 bg-rose-500/10 text-rose-100;
}

.daily-claim-card__activity-action {
    @apply min-w-[90px] px-3 py-2;
}

</style>
