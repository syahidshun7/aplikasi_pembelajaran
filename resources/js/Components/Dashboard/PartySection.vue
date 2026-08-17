<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

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
    themeMode: {
        type: String,
        default: 'dark',
    },
});

const page = usePage();
const isStaffPlayMode = computed(() => Boolean(page.props?.auth?.user?.staff_play_mode));
const normalizedUserRole = computed(() => String(page.props?.auth?.user?.role || '').trim().toLowerCase());
const isStaff = computed(() => ['super_admin', 'admin', 'mentor'].includes(normalizedUserRole.value));
const canManagePartyMembership = computed(() => !isStaff.value);

const viewerLevel = computed(() => {
    const lvl = Number(page.props?.auth?.user?.lvl ?? page.props?.auth?.user?.level_progress?.level ?? 1);
    return Number.isFinite(lvl) && lvl > 0 ? lvl : 1;
});

const groupMinLevel = (group) => {
    const parsed = Number(group?.min_level ?? 1);
    return Number.isFinite(parsed) && parsed > 0 ? parsed : 1;
};

const canJoinByLevel = (group) => viewerLevel.value >= groupMinLevel(group);
</script>

<template>
    <section class="dashboard-section-shell" :class="{ 'party-section--light': themeMode === 'light' }">
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
            <div
                v-if="isStaffPlayMode || isStaff"
                class="lg:col-span-2 border border-cyan-500/40 bg-cyan-500/10 p-3 text-[8px] uppercase leading-relaxed text-cyan-100"
            >
                Staff hanya dapat melihat daftar kelas. Akses admin dan mentor dikelola melalui Staff Access.
            </div>
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
                    <div class="shrink-0 flex flex-col items-end gap-1">
                        <span class="rounded-full border border-emerald-500/20 bg-emerald-500/10 px-2 py-1 text-[8px] text-yellow-300">
                            {{ group.users_count || 0 }} Members
                        </span>
                        <span
                            class="rounded-full border px-2 py-1 text-[8px] uppercase"
                            :class="canJoinByLevel(group)
                                ? 'border-cyan-500/30 bg-cyan-500/10 text-cyan-200'
                                : 'border-rose-500/30 bg-rose-500/10 text-rose-200'"
                        >
                            Min LVL {{ groupMinLevel(group) }}
                        </span>
                    </div>
                </div>

                <div class="mt-4 flex flex-col gap-2 border-t border-slate-800 pt-3 sm:flex-row sm:items-center sm:justify-between">
                    <span class="text-[7px] uppercase text-slate-600">Party_ID: {{ group.uuid?.substring(0, 8) }}</span>

                    <div v-if="group.is_member" class="flex flex-wrap items-center gap-2 sm:justify-end">
                        <Link
                            :href="route('groups.show', group.uuid)"
                            class="party-action-btn party-action-btn--detail px-3 py-1 text-[8px] uppercase transition-all"
                        >
                            Detail
                        </Link>

                        <button
                            type="button"
                            class="party-action-btn party-action-btn--leave px-3 py-1 text-[8px] uppercase transition-all disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="!canManagePartyMembership"
                            @click="onLeave?.(group.uuid)"
                        >
                            Leave
                        </button>
                    </div>

                    <button
                        v-if="!group.is_member && group.join_request_status === 'pending'"
                        type="button"
                        disabled
                        class="cursor-not-allowed border border-slate-700 bg-slate-900/60 px-3 py-1 text-[8px] uppercase text-slate-400"
                    >
                        Pending
                    </button>

                    <button
                        v-if="!group.is_member && group.join_request_status !== 'pending'"
                        type="button"
                        :disabled="joinProcessing || !canManagePartyMembership || !canJoinByLevel(group)"
                        class="party-action-btn party-action-btn--join px-3 py-1 text-[8px] uppercase transition-all disabled:cursor-not-allowed disabled:opacity-60"
                        @click="onJoin?.(group)"
                    >
                        {{
                            !canManagePartyMembership
                                ? 'Preview Only'
                                : (!canJoinByLevel(group)
                                    ? `Need LVL ${groupMinLevel(group)}`
                                    : (joinProcessing ? 'Sending...' : 'Join'))
                        }}
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

.party-action-btn {
    display: inline-flex;
    min-height: 28px;
    align-items: center;
    justify-content: center;
    border-width: 1px;
    border-style: solid;
    text-shadow: none;
}

.party-action-btn--detail {
    border-color: #0e7490;
    background: rgba(22, 78, 99, 0.4);
    color: #67e8f9;
}

.party-action-btn--detail:hover {
    background: #06b6d4;
    color: #020617;
}

.party-action-btn--leave {
    border-color: #b91c1c;
    background: rgba(127, 29, 29, 0.5);
    color: #f87171;
}

.party-action-btn--leave:hover:not(:disabled) {
    background: #dc2626;
    color: #fff;
}

.party-action-btn--join {
    border-color: #047857;
    background: rgba(6, 78, 59, 0.5);
    color: #34d399;
}

.party-action-btn--join:hover:not(:disabled) {
    background: #10b981;
    color: #020617;
}

.party-section--light .party-action-btn--detail {
    border-color: #006f6f;
    background: #009999;
    color: #fff;
    box-shadow: none;
}

.party-section--light .party-action-btn--detail:hover {
    border-color: #005f5f;
    background: #007f7f;
    color: #fff;
}

.party-section--light .party-action-btn--leave {
    border-color: #dc2626;
    background: #fff;
    color: #dc2626;
    box-shadow: none;
}

.party-section--light .party-action-btn--leave:hover:not(:disabled) {
    border-color: #b91c1c;
    background: #dc2626;
    color: #fff;
}

.party-section--light .party-action-btn--join {
    border-color: #047857;
    background: #10b981;
    color: #052e1b;
    box-shadow: none;
}

.party-section--light .party-action-btn--join:hover:not(:disabled) {
    border-color: #065f46;
    background: #059669;
    color: #fff;
}

</style>
