<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, defineAsyncComponent, ref, watch } from 'vue';
import AppBackgroundLayer from '@/Components/AppBackgroundLayer.vue';
import { useLobby } from '@/Composables/useLobby';
import FloatingChat from '@/Components/FloatingChat.vue';
import UserNavbar from '@/Components/UserNavbar.vue';
import QuestSection from '@/Components/Dashboard/QuestSection.vue';
import LibrarySection from '@/Components/Dashboard/LibrarySection.vue';
import EventSection from '@/Components/Dashboard/EventSection.vue';
import PartySection from '@/Components/Dashboard/PartySection.vue';
import LeaderboardSection from '@/Components/Dashboard/LeaderboardSection.vue';

const CarouselMenu = defineAsyncComponent(() => import('@/Components/Dashboard/CarouselMenu.vue'));
const ACTIVE_MENU_STORAGE_KEY = 'home-active-menu';
const ACTIVE_MENU_DEFAULT_KEY = 'townhall';
const ACTIVE_MENU_INVALID_FALLBACK = 'quest';
const validActiveMenuKeys = ['quest', 'library', 'townhall', 'party', 'leaderboard'];
const LEADERBOARD_MODE_STORAGE_KEY = 'home-leaderboard-mode';
const LEADERBOARD_MODE_DEFAULT = 'global';
const LEADERBOARD_MODE_FALLBACK = 'global';
const validLeaderboardModes = ['global', 'class'];
const leaderboardModeAliases = {
    job: 'global',
    overall: 'global',
    party: 'class',
};
const leaderboardModeLabelMap = {
    global: 'Global',
    class: 'Kelas Saya',
};
const activeMenuAliases = {
    event: 'townhall',
};

const toPositiveInteger = (value) => {
    const parsed = Number.parseInt(value, 10);
    return Number.isFinite(parsed) && parsed > 0 ? parsed : 0;
};

const normalizeActiveMenu = (value, fallback = ACTIVE_MENU_INVALID_FALLBACK) => {
    if (typeof value !== 'string') {
        return fallback;
    }

    const normalizedValue = value.trim().toLowerCase();
    const resolvedValue = activeMenuAliases[normalizedValue] ?? normalizedValue;

    return validActiveMenuKeys.includes(resolvedValue) ? resolvedValue : fallback;
};

const resolveInitialActiveMenu = () => {
    if (typeof window === 'undefined') {
        return ACTIVE_MENU_DEFAULT_KEY;
    }

    try {
        const storedValue = window.localStorage.getItem(ACTIVE_MENU_STORAGE_KEY);

        if (!storedValue) {
            return normalizeActiveMenu('event', ACTIVE_MENU_DEFAULT_KEY);
        }

        return normalizeActiveMenu(storedValue, ACTIVE_MENU_INVALID_FALLBACK);
    } catch {
        return ACTIVE_MENU_DEFAULT_KEY;
    }
};

const normalizeLeaderboardMode = (value, fallback = LEADERBOARD_MODE_FALLBACK) => {
    if (typeof value !== 'string') {
        return fallback;
    }

    const normalizedValue = value.trim().toLowerCase();
    const resolvedValue = leaderboardModeAliases[normalizedValue] ?? normalizedValue;
    return validLeaderboardModes.includes(resolvedValue) ? resolvedValue : fallback;
};

const resolveInitialLeaderboardMode = () => {
    if (typeof window === 'undefined') {
        return LEADERBOARD_MODE_DEFAULT;
    }

    try {
        const storedValue = window.localStorage.getItem(LEADERBOARD_MODE_STORAGE_KEY);

        if (!storedValue) {
            return LEADERBOARD_MODE_DEFAULT;
        }

        return normalizeLeaderboardMode(storedValue, LEADERBOARD_MODE_FALLBACK);
    } catch {
        return LEADERBOARD_MODE_DEFAULT;
    }
};

const props = defineProps({
    players: Array,
    leaderboards: Object,
    leaderboardMeta: Object,
    dailyQuestBoard: Object,
    quests: Array,
    studyGroups: Array,
    materi: Array,
    events: Array,
    auth: Object,
});

const resolveClassGroupIdFromMeta = (meta) => {
    const safeMeta = meta && typeof meta === 'object' ? meta : {};
    const selectedId = toPositiveInteger(safeMeta.selected_class_group_id);
    if (selectedId > 0) {
        return selectedId;
    }

    const defaultId = toPositiveInteger(safeMeta.default_class_group_id);
    return defaultId > 0 ? defaultId : null;
};

const {
    joinForm,
    handleLeave,
    handleJoin,
    auth,
    players,
    quests,
    studyGroups,
    guides,
    events,
} = useLobby(props);

const activeMenu = ref(resolveInitialActiveMenu());
const leaderboardMode = ref(resolveInitialLeaderboardMode());
const selectedClassGroupId = ref(resolveClassGroupIdFromMeta(props.leaderboardMeta));
const isClassLeaderboardLoading = ref(false);
const page = usePage();
const isEmailUnverified = computed(() => !!(auth.value?.user && !auth.value.user.email_verified_at));
const isEmailVerifiedSuccess = computed(() => page.url.includes('verified=1') && !isEmailUnverified.value);
const profileVerificationHref = computed(() => `${route('profile.edit')}#email-verification`);
const isLoggedIn = computed(() => Boolean(auth.value?.user));

const toSafeDate = (dateLike) => {
    if (!dateLike) return null;
    const date = new Date(dateLike);
    if (Number.isNaN(date.getTime())) return null;
    return date;
};

const decorateLeaderboardPlayers = (rawItems) => {
    return (rawItems || []).map((player) => {
        const seed = player?.username || player?.name || 'guild-member';

        return {
            ...player,
            __dicebear_src: `https://api.dicebear.com/7.x/pixel-art/svg?seed=${seed}`,
            __score: Number(player?.exp ?? player?.points ?? ((player?.level || 1) * 100)),
        };
    });
};

const leaderboardSourcePayload = computed(() => {
    const payload = props.leaderboards;
    return payload && typeof payload === 'object' ? payload : {};
});

const leaderboardCollections = computed(() => {
    const payload = leaderboardSourcePayload.value;

    return {
        global: decorateLeaderboardPlayers(payload.global ?? payload.overall ?? []),
        class: decorateLeaderboardPlayers(payload.class ?? payload.party ?? []),
    };
});
const classLeaderboardOptions = computed(() => {
    const groups = props.leaderboardMeta?.class_groups;

    if (!Array.isArray(groups)) {
        return [];
    }

    return groups
        .map((group) => ({
            id: toPositiveInteger(group?.id),
            name: String(group?.name ?? '').trim(),
        }))
        .filter((group) => group.id > 0 && group.name !== '');
});
const loadedClassLeaderboardGroupId = computed(() => {
    return toPositiveInteger(props.leaderboardMeta?.loaded_class_group_id);
});

const leaderboardModeLabel = computed(() => {
    return leaderboardModeLabelMap[leaderboardMode.value] ?? leaderboardModeLabelMap[LEADERBOARD_MODE_FALLBACK];
});

const leaderboardPreview = computed(() => {
    const selectedItems = leaderboardCollections.value[leaderboardMode.value] ?? leaderboardCollections.value[LEADERBOARD_MODE_FALLBACK] ?? [];
    return selectedItems.slice(0, 10);
});
const isLeaderboardEmpty = computed(() => leaderboardPreview.value.length === 0);

const eventItems = computed(() => {
    return (events.value || []).map((event) => {
        const startsAtDate = toSafeDate(event?.starts_at);

        return {
            ...event,
            __starts_at_label: startsAtDate ? startsAtDate.toLocaleString('id-ID') : 'Schedule_Not_Set',
        };
    });
});

const upcomingEventPreview = computed(() => eventItems.value.slice(0, 10));

const questItems = computed(() => {
    const now = Date.now();

    return (quests.value || []).map((quest) => {
        const deadlineDate = toSafeDate(quest?.deadline);
        const deadlineOverdue = Boolean(
            deadlineDate
                && deadlineDate.getTime() < now
                && !quest?.user_has_submitted
                && !quest?.user_has_unlock
        );

        return {
            ...quest,
            __deadline_overdue: deadlineOverdue,
            __deadline_label: deadlineDate
                ? deadlineDate.toLocaleString('id-ID', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }).toUpperCase()
                : 'NO_LIMIT',
        };
    });
});

const questPreview = computed(() => questItems.value.slice(0, 10));
const guidePreview = computed(() => (guides.value || []).slice(0, 10));
const groupPreview = computed(() => (studyGroups.value || []).slice(0, 10));

const carouselItems = computed(() => ([
    {
        key: 'quest',
        title: 'Quest Board',
        subtitle: `${questPreview.value.length} mission node ready`,
        accent: 'from-amber-node',
        icon: 'fi fi-rr-target',
    },
    {
        key: 'library',
        title: 'Library',
        subtitle: `${guidePreview.value.length} material archive`,
        accent: 'from-indigo-node',
        icon: 'fi fi-rr-book-alt',
    },
    {
        key: 'townhall',
        title: 'Events',
        subtitle: `${upcomingEventPreview.value.length} event timeline`,
        accent: 'from-blue-node',
        icon: 'fi fi-rr-calendar-clock',
    },
    {
        key: 'party',
        title: 'Party Guild',
        subtitle: `${groupPreview.value.length} ally slots open`,
        accent: 'from-emerald-node',
        icon: 'fi fi-rr-users',
    },
    {
        key: 'leaderboard',
        title: 'Leaderboard',
        subtitle: isLeaderboardEmpty.value
            ? `No ${leaderboardModeLabel.value} rank data yet`
            : `${leaderboardPreview.value.length} top ${leaderboardModeLabel.value.toLowerCase()} rankers`,
        accent: 'from-cyan-node',
        icon: 'fi fi-rr-trophy',
    },
]));

const activeCarouselItem = computed(() => {
    return carouselItems.value.find((item) => item.key === activeMenu.value) ?? carouselItems.value[0];
});

const latestModuleMeta = computed(() => {
    const moduleMap = {
        quest: {
            helper: `Showing latest ${questPreview.value.length} quests.`,
        },
        library: {
            helper: `Showing latest ${guidePreview.value.length} materials.`,
        },
        townhall: {
            helper: `Showing latest ${upcomingEventPreview.value.length} scheduled events.`,
        },
        party: {
            helper: `Showing latest ${groupPreview.value.length} guild records.`,
        },
        leaderboard: {
            helper: isLeaderboardEmpty.value
                ? `Leaderboard ${leaderboardModeLabel.value} masih kosong. Rank akan tampil setelah progres player tercatat.`
                : `Showing current top ${leaderboardPreview.value.length} ${leaderboardModeLabel.value.toLowerCase()} ranks.`,
        },
    };

    return moduleMap[activeMenu.value] ?? { helper: 'Showing preview data only.' };
});

const syncSelectedClassGroupId = () => {
    const optionIds = classLeaderboardOptions.value.map((group) => group.id);

    if (optionIds.length === 0) {
        selectedClassGroupId.value = null;
        return;
    }

    const currentId = toPositiveInteger(selectedClassGroupId.value);
    if (currentId > 0 && optionIds.includes(currentId)) {
        return;
    }

    const metaGroupId = resolveClassGroupIdFromMeta(props.leaderboardMeta);
    if (metaGroupId && optionIds.includes(metaGroupId)) {
        selectedClassGroupId.value = metaGroupId;
        return;
    }

    selectedClassGroupId.value = optionIds[0];
};

const fetchClassLeaderboard = (groupId) => {
    const targetGroupId = toPositiveInteger(groupId);

    if (targetGroupId <= 0) {
        return;
    }

    if (loadedClassLeaderboardGroupId.value === targetGroupId || isClassLeaderboardLoading.value) {
        return;
    }

    router.reload({
        only: ['leaderboards', 'leaderboardMeta'],
        headers: { 'X-Leaderboard-Class-Group-Id': String(targetGroupId) },
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onStart: () => {
            isClassLeaderboardLoading.value = true;
        },
        onFinish: () => {
            isClassLeaderboardLoading.value = false;
        },
    });
};

watch(() => props.leaderboardMeta, () => {
    syncSelectedClassGroupId();
}, { immediate: true, deep: true });

watch(activeMenu, (nextValue) => {
    const normalizedValue = normalizeActiveMenu(nextValue);

    if (normalizedValue !== nextValue) {
        activeMenu.value = normalizedValue;
        return;
    }

    if (typeof window === 'undefined') {
        return;
    }

    try {
        window.localStorage.setItem(ACTIVE_MENU_STORAGE_KEY, normalizedValue);
    } catch {
        // Ignore storage write failures so the page stays interactive.
    }
}, { immediate: true });

watch(leaderboardMode, (nextValue) => {
    const normalizedValue = normalizeLeaderboardMode(nextValue);

    if (normalizedValue !== nextValue) {
        leaderboardMode.value = normalizedValue;
        return;
    }

    if (typeof window === 'undefined') {
        return;
    }

    try {
        window.localStorage.setItem(LEADERBOARD_MODE_STORAGE_KEY, normalizedValue);
    } catch {
        // Ignore storage write failures so the page stays interactive.
    }

    if (normalizedValue === 'class') {
        fetchClassLeaderboard(selectedClassGroupId.value);
    }
}, { immediate: true });

watch(selectedClassGroupId, (nextValue, previousValue) => {
    const nextGroupId = toPositiveInteger(nextValue);
    const previousGroupId = toPositiveInteger(previousValue);

    if (nextGroupId <= 0 || nextGroupId === previousGroupId) {
        return;
    }

    if (leaderboardMode.value !== 'class') {
        return;
    }

    fetchClassLeaderboard(nextGroupId);
});
</script>

<template>
    <Head title="DOOPTECH" />

    <div
        class="relative isolate min-h-screen overflow-x-hidden bg-[#0a0c10] font-['Press_Start_2P']"
    >
        <AppBackgroundLayer overlay-class="bg-black/60" />

        <div class="relative z-10 flex min-h-screen flex-col">
            <UserNavbar :show-guest-actions="true" />

            <div v-if="isEmailUnverified" class="px-4 pt-4 md:px-8">
                <div class="flex flex-col gap-3 border-2 border-amber-400/60 bg-amber-500/15 p-3 md:flex-row md:items-center md:justify-between">
                    <div class="text-[9px] uppercase leading-relaxed tracking-wide text-amber-100">
                        Email belum terverifikasi. Kamu tetap bisa eksplor Home, tapi beberapa fitur akan dikunci sampai verifikasi selesai.
                    </div>
                    <Link
                        :href="profileVerificationHref"
                        class="btn-pixel border-amber-700 bg-amber-300 px-3 py-2 text-center text-[8px] font-bold uppercase text-black transition-colors hover:bg-amber-200"
                    >
                        Verifikasi di Profile
                    </Link>
                </div>
            </div>

            <div v-else-if="isEmailVerifiedSuccess" class="px-4 pt-4 md:px-8">
                <div class="flex flex-col gap-3 border-2 border-emerald-400/60 bg-emerald-500/15 p-3 md:flex-row md:items-center md:justify-between">
                    <div class="text-[9px] uppercase leading-relaxed tracking-wide text-emerald-100">
                        Verifikasi email berhasil. Semua fitur akun sekarang sudah terbuka.
                    </div>
                    <Link
                        :href="route('profile.dashboard')"
                        class="btn-pixel border-emerald-700 bg-emerald-300 px-3 py-2 text-center text-[8px] font-bold uppercase text-black transition-colors hover:bg-emerald-200"
                    >
                        Buka Profile
                    </Link>
                </div>
            </div>

            <main class="flex-1 px-3 py-3 sm:px-4 sm:py-4 md:px-8 md:py-8">
                <div class="user-page-shell">
                    <section class="academy-hub academy-hub--joined">
                        <div class="academy-scene">
                            <div class="academy-scene__backdrop"></div>
                            <div class="academy-scene__content">
                                <CarouselMenu v-model="activeMenu" :items="carouselItems" />

                                <div class="academy-scene__footer">
                                    <p class="academy-scene__copy">
                                        Swipe, drag, or click a node to focus the dashboard without reloading the page.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <section class="dashboard-focus-shell dashboard-focus-shell--joined">
                            <div class="dashboard-focus-shell__meta">
                                <p class="dashboard-focus-shell__eyebrow">Latest Snapshot</p>
                                <h2 class="dashboard-focus-shell__title">{{ activeCarouselItem?.title }}</h2>
                                <p class="dashboard-focus-shell__helper">{{ latestModuleMeta.helper }}</p>
                            </div>

                            <Transition name="dashboard-section" mode="out-in">
                                <QuestSection
                                    v-if="activeMenu === 'quest'"
                                    :items="questPreview"
                                    :auth-user="isLoggedIn"
                                    :daily-quest-board="isLoggedIn ? props.dailyQuestBoard : null"
                                />

                                <LibrarySection
                                    v-else-if="activeMenu === 'library'"
                                    :items="guidePreview"
                                    :auth-user="isLoggedIn"
                                />

                                <EventSection
                                    v-else-if="activeMenu === 'townhall'"
                                    :items="upcomingEventPreview"
                                    :auth-user="isLoggedIn"
                                />

                                <PartySection
                                    v-else-if="activeMenu === 'party'"
                                    :items="groupPreview"
                                    :join-processing="joinForm.processing"
                                    :on-join="handleJoin"
                                    :on-leave="handleLeave"
                                />

                                <LeaderboardSection
                                    v-else
                                    :items="leaderboardPreview"
                                    :leaderboards="leaderboardCollections"
                                    :metadata="props.leaderboardMeta"
                                    :class-options="classLeaderboardOptions"
                                    :selected-class-group-id="selectedClassGroupId"
                                    :class-loading="isClassLeaderboardLoading"
                                    :mode="leaderboardMode"
                                    @update:selected-class-group-id="selectedClassGroupId = $event"
                                    @update:mode="leaderboardMode = $event"
                                />
                            </Transition>
                        </section>
                    </section>
                </div>
            </main>

            <FloatingChat v-if="auth.user" />

            <footer class="mt-auto border-t-2 border-white/10 bg-[#1a1c2c] p-6 text-center md:bg-[#1a1c2c]/50 md:backdrop-blur-md md:p-8">
                <p class="break-words text-[7px] uppercase tracking-[0.18em] text-white/50 sm:text-[8px] sm:tracking-[0.3em]">
                    Build_Ver_1.1.0 // P-Quest Engine
                </p>
            </footer>
        </div>
    </div>
</template>

<style scoped>
@import "../../css/lobby-style.css";

.academy-hub {
    @apply overflow-hidden border-2 border-[#3d415f] bg-[#1a1c2c] p-2 shadow-[0_14px_38px_rgba(2,8,16,0.42)];
}

.academy-hub--joined {
    @apply space-y-5;
}

.academy-scene {
    @apply relative overflow-hidden border border-slate-700 bg-[#1a1c2c];
    min-height: 268px;
}

.academy-scene__backdrop {
    @apply absolute inset-0 bg-[#1a1c2c];
}

.academy-scene__content {
    @apply relative z-10 flex min-h-[268px] flex-col justify-center pt-1;
}

.academy-scene__copy {
    @apply mx-auto max-w-[760px] px-4 pb-4 text-center text-[9px] uppercase leading-relaxed tracking-[0.16em] text-slate-300;
}

.academy-scene__footer {
    @apply -mt-1;
}

.dashboard-focus-shell {
    @apply space-y-4;
}

.dashboard-focus-shell--joined {
    @apply border-t border-[#33415f] px-2 pb-2 pt-5;
}

.dashboard-focus-shell__meta {
    @apply flex flex-col gap-2 px-1;
}

.dashboard-focus-shell__eyebrow {
    @apply text-[7px] uppercase tracking-[0.26em] text-cyan-300/70;
}

.dashboard-focus-shell__title {
    @apply text-[11px] uppercase tracking-[0.14em] text-white;
}

.dashboard-focus-shell__helper {
    @apply max-w-[560px] text-[7px] uppercase leading-relaxed tracking-[0.12em] text-slate-400;
}

.dashboard-section-enter-active,
.dashboard-section-leave-active {
    transition: opacity 0.35s ease-in-out, transform 0.35s ease-in-out;
}

.dashboard-section-enter-from,
.dashboard-section-leave-to {
    opacity: 0;
    transform: translateY(18px) scale(0.985);
}

@media (max-width: 1279px) {
    .academy-scene {
        min-height: 244px;
    }

    .academy-scene__content {
        min-height: 244px;
        padding-top: 0.25rem;
    }
}

@media (max-width: 767px) {
    .academy-hub {
        @apply p-2;
    }

    .academy-hub--joined {
        @apply space-y-4;
    }

    .academy-scene {
        min-height: 232px;
    }

    .academy-scene__content {
        min-height: 232px;
        padding-top: 0.25rem;
    }

    .academy-scene__copy {
        @apply max-w-[320px] px-3 pb-3 text-[8px];
    }

    .dashboard-focus-shell--joined {
        @apply px-1 pb-1 pt-4;
    }

    .dashboard-focus-shell__helper {
        @apply max-w-[300px] text-[6px];
    }
}
</style>
