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
</style>
