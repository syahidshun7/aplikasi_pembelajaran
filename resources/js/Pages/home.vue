<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, defineAsyncComponent, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AppBackgroundLayer from '@/Components/AppBackgroundLayer.vue';
import { useUserTheme } from '@/Composables/useUserTheme';
import { useLobby } from '@/Composables/useLobby';
import FloatingChat from '@/Components/FloatingChat.vue';
import UserNavbar from '@/Components/UserNavbar.vue';
import QuestSection from '@/Components/Dashboard/QuestSection.vue';
import LibrarySection from '@/Components/Dashboard/LibrarySection.vue';
import EventSection from '@/Components/Dashboard/EventSection.vue';
import PartySection from '@/Components/Dashboard/PartySection.vue';
import LeaderboardSection from '@/Components/Dashboard/LeaderboardSection.vue';
import DoopNewsSection from '@/Components/Dashboard/DoopNewsSection.vue';

const CarouselMenu = defineAsyncComponent(() => import('@/Components/Dashboard/CarouselMenu.vue'));
const ACTIVE_MENU_STORAGE_KEY = 'home-active-menu';
const ACTIVE_MENU_DEFAULT_KEY = 'doopnews';
const ACTIVE_MENU_INVALID_FALLBACK = 'quest';
const validActiveMenuKeys = ['doopnews', 'quest', 'library', 'townhall', 'party', 'leaderboard'];
const LEADERBOARD_MODE_STORAGE_KEY = 'home-leaderboard-mode';
const LOCAL_SEEN_CONTENT_STORAGE_KEY = 'home-local-seen-content';
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
const emptySeenContentMap = () => ({
    quest: [],
    guide: [],
    event: [],
    study_group: [],
    doop_news: [],
});

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
        return ACTIVE_MENU_DEFAULT_KEY;
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

const readLocalSeenContentMap = () => {
    if (typeof window === 'undefined') {
        return emptySeenContentMap();
    }

    try {
        const parsed = JSON.parse(window.sessionStorage.getItem(LOCAL_SEEN_CONTENT_STORAGE_KEY) || '{}');
        const fallback = emptySeenContentMap();

        return Object.fromEntries(
            Object.keys(fallback).map((type) => [
                type,
                Array.isArray(parsed?.[type])
                    ? parsed[type].map((id) => String(id)).filter(Boolean)
                    : [],
            ]),
        );
    } catch {
        return emptySeenContentMap();
    }
};

const props = defineProps({
    players: {
        type: Array,
        default: () => [],
    },
    leaderboards: {
        type: Object,
        default: () => ({}),
    },
    leaderboardMeta: {
        type: Object,
        default: () => ({}),
    },
    dailyQuestBoard: {
        type: Object,
        default: null,
    },
    quests: {
        type: Array,
        default: () => [],
    },
    studyGroups: {
        type: Array,
        default: () => [],
    },
    materi: {
        type: Array,
        default: () => [],
    },
    events: {
        type: Array,
        default: () => [],
    },
    doopNewsPosts: {
        type: Array,
        default: () => [],
    },
    newContentCounts: {
        type: Object,
        default: () => ({}),
    },
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
const localSeenContentIds = ref(readLocalSeenContentMap());
const page = usePage();
const { themeMode } = useUserTheme();
const lobbyBackgroundOverlayClass = computed(() => (
    themeMode.value === 'light' ? 'bg-[#f7f7f7]/20' : 'bg-black/60'
));
const lobbyBackgroundGlowClass = computed(() => (
    themeMode.value === 'light'
        ? ''
        : 'bg-[radial-gradient(circle_at_18%_20%,rgba(34,211,238,0.18),transparent_34%),radial-gradient(circle_at_82%_14%,rgba(45,212,191,0.14),transparent_30%),linear-gradient(180deg,rgba(2,6,23,0.16),rgba(2,6,23,0.4))]'
));
const lobbyBackgroundImage = computed(() => (
    themeMode.value === 'light' ? '/images/bg-loby5.webp' : '/images/bg-loby.webp'
));
const lobbyBackgroundPosition = computed(() => (
    'center'
));
const lobbyInlineBackgroundStyle = computed(() => (
    themeMode.value === 'light'
        ? {
            backgroundImage: "linear-gradient(rgba(247,247,247,0.18), rgba(247,247,247,0.18)), url('/images/bg-loby5.webp')",
            backgroundColor: '#f7f7f7',
            backgroundPosition: 'center',
            backgroundRepeat: 'no-repeat',
            backgroundSize: 'cover',
            backgroundAttachment: 'fixed',
        }
        : null
));
const isEmailUnverified = computed(() => !!(auth.value?.user && !auth.value.user.email_verified_at));
const isEmailVerifiedSuccess = computed(() => page.url.includes('verified=1') && !isEmailUnverified.value);
const isLoggedIn = computed(() => Boolean(auth.value?.user));
const currentTimestamp = ref(Date.now());
let questClockInterval = null;

const toSafeDate = (dateLike) => {
    if (!dateLike) return null;
    const date = new Date(dateLike);
    if (Number.isNaN(date.getTime())) return null;
    return date;
};

const formatCountdown = (remainingMs) => {
    if (!Number.isFinite(remainingMs) || remainingMs <= 0) {
        return '00:00:00';
    }

    const totalSeconds = Math.floor(remainingMs / 1000);
    const days = Math.floor(totalSeconds / 86400);
    const hours = Math.floor((totalSeconds % 86400) / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;

    if (days > 0) {
        return `${String(days).padStart(2, '0')}:${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    }

    return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
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
    return applyLocalSeenState((events.value || []), 'event')
        .map((event) => {
            const startsAtDate = toSafeDate(event?.starts_at);
            const createdAtDate = toSafeDate(event?.created_at);

            return {
                ...event,
                __starts_at_ts: startsAtDate ? startsAtDate.getTime() : -1,
                __created_at_ts: createdAtDate ? createdAtDate.getTime() : -1,
                __starts_at_label: startsAtDate ? startsAtDate.toLocaleString('id-ID') : 'Schedule_Not_Set',
            };
        })
        .sort((left, right) => {
            if (right.__created_at_ts !== left.__created_at_ts) {
                return right.__created_at_ts - left.__created_at_ts;
            }

            return right.__starts_at_ts - left.__starts_at_ts;
        });
});

const upcomingEventPreview = computed(() => eventItems.value.slice(0, 10));
const contentTypeByPayloadKey = {
    quest: 'quest',
    guide: 'guide',
    event: 'event',
    study_group: 'study_group',
    doop_news: 'doop_news',
};
const newCountKeyByType = {
    quest: 'quest',
    guide: 'guide',
    event: 'event',
    study_group: 'study_group',
    doop_news: 'doop_news',
};

const getItemContentId = (item) => {
    const rawId = item?.id ?? item?.uuid ?? item?.slug ?? '';
    return String(rawId || '').trim();
};

const isContentLocallySeen = (type, item) => {
    const contentId = getItemContentId(item);

    if (!contentId) {
        return false;
    }

    return (localSeenContentIds.value?.[type] || []).includes(contentId);
};

const applyLocalSeenState = (items, type) => {
    return (items || []).map((item) => ({
        ...item,
        is_new_for_user: Boolean(item?.is_new_for_user) && !isContentLocallySeen(type, item),
    }));
};

const rawItemsForContentType = (type) => {
    switch (type) {
        case 'quest':
            return quests.value || [];
        case 'guide':
            return guides.value || [];
        case 'event':
            return events.value || [];
        case 'study_group':
            return studyGroups.value || [];
        case 'doop_news':
            return props.doopNewsPosts || [];
        default:
            return [];
    }
};

const getLocalSeenServerNewCount = (type) => {
    const locallySeenIds = new Set(localSeenContentIds.value?.[type] || []);

    if (locallySeenIds.size === 0) {
        return 0;
    }

    return rawItemsForContentType(type).filter((item) => (
        Boolean(item?.is_new_for_user)
        && locallySeenIds.has(getItemContentId(item))
    )).length;
};

const getLocalNewCount = (type) => {
    const key = newCountKeyByType[type];
    const serverCount = Number(props.newContentCounts?.[key] || 0);

    return Math.max(0, serverCount - getLocalSeenServerNewCount(type));
};

const questItems = computed(() => {
    const now = currentTimestamp.value;

    return applyLocalSeenState((quests.value || []), 'quest')
        .filter((quest) => {
            const isScheduledOnce = String(quest?.schedule_type || '') === 'once';
            const availableUntilDate = toSafeDate(quest?.available_until);

            if (!isScheduledOnce || !availableUntilDate) {
                return true;
            }

            return availableUntilDate.getTime() > now;
        })
        .map((quest) => {
        const deadlineDate = toSafeDate(quest?.deadline);
        const availableUntilDate = toSafeDate(quest?.available_until);
        const isScheduledOnce = String(quest?.schedule_type || '') === 'once';
        const deadlineOverdue = Boolean(
            deadlineDate
                && deadlineDate.getTime() < now
                && !quest?.user_has_submitted
                && !quest?.user_has_unlock
        );
        const remainingWindowMs = availableUntilDate ? (availableUntilDate.getTime() - now) : null;

        return {
            ...quest,
            __deadline_overdue: deadlineOverdue,
            __deadline_label: deadlineDate
                ? deadlineDate.toLocaleString('id-ID', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }).toUpperCase()
                : 'NO_LIMIT',
            __schedule_once: isScheduledOnce,
            __schedule_until_label: availableUntilDate
                ? availableUntilDate.toLocaleString('id-ID', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }).toUpperCase()
                : null,
            __schedule_countdown_label: isScheduledOnce && availableUntilDate
                ? formatCountdown(remainingWindowMs)
                : null,
        };
    });
});

const questPreview = computed(() => questItems.value.slice(0, 10));
const guidePreview = computed(() => applyLocalSeenState((guides.value || []), 'guide').slice(0, 10));
const groupPreview = computed(() => applyLocalSeenState((studyGroups.value || []), 'study_group').slice(0, 10));
const doopNewsPreview = computed(() => applyLocalSeenState((props.doopNewsPosts || []), 'doop_news').slice(0, 5));

const persistLocalSeenContent = () => {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        window.sessionStorage.setItem(LOCAL_SEEN_CONTENT_STORAGE_KEY, JSON.stringify(localSeenContentIds.value));
    } catch {
        // Ignore storage write failures so browser back still works through Vue state.
    }
};

const acknowledgeContentItem = ({ type, item } = {}) => {
    const contentType = contentTypeByPayloadKey[String(type || '')];
    const contentId = getItemContentId(item);

    if (!contentType || !contentId || !item?.is_new_for_user || isContentLocallySeen(contentType, item)) {
        return;
    }

    localSeenContentIds.value = {
        ...localSeenContentIds.value,
        [contentType]: [
            ...(localSeenContentIds.value?.[contentType] || []),
            contentId,
        ],
    };
    persistLocalSeenContent();
};

const carouselItems = computed(() => ([
    {
        key: 'doopnews',
        title: 'DoopNews',
        subtitle: `${doopNewsPreview.value.length} broadcasts`,
        accent: 'from-rose-node',
        icon: 'fi fi-rr-megaphone',
        badge_count: getLocalNewCount('doop_news'),
    },
    {
        key: 'quest',
        title: 'Quest Board',
        subtitle: `${questPreview.value.length} mission node ready`,
        accent: 'from-amber-node',
        icon: 'fi fi-rr-target',
        badge_count: getLocalNewCount('quest'),
    },
    {
        key: 'library',
        title: 'Library',
        subtitle: `${guidePreview.value.length} material archive`,
        accent: 'from-indigo-node',
        icon: 'fi fi-rr-book-alt',
        badge_count: getLocalNewCount('guide'),
    },
    {
        key: 'townhall',
        title: 'Events',
        subtitle: `${upcomingEventPreview.value.length} event timeline`,
        accent: 'from-blue-node',
        icon: 'fi fi-rr-calendar-clock',
        badge_count: getLocalNewCount('event'),
    },
    {
        key: 'party',
        title: 'Party Guild',
        subtitle: `${groupPreview.value.length} ally slots open`,
        accent: 'from-emerald-node',
        icon: 'fi fi-rr-users',
        badge_count: getLocalNewCount('study_group'),
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
            helper: `Showing prioritized ${questPreview.value.length} quests.`,
        },
        library: {
            helper: `Showing latest ${guidePreview.value.length} materials.`,
        },
        townhall: {
            helper: `Showing latest ${upcomingEventPreview.value.length} scheduled events.`,
        },
        doopnews: {
            helper: `Showing latest ${doopNewsPreview.value.length} broadcasts.`,
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

onMounted(() => {
    questClockInterval = window.setInterval(() => {
        currentTimestamp.value = Date.now();
    }, 1000);
});

onBeforeUnmount(() => {
    if (questClockInterval) {
        window.clearInterval(questClockInterval);
        questClockInterval = null;
    }
});
</script>

<template>
    <Head title="DOOPTECH" />

    <div
        data-app-surface="user"
        :data-theme="themeMode"
        class="user-theme-root relative isolate min-h-screen overflow-x-hidden font-['Press_Start_2P']"
        :style="lobbyInlineBackgroundStyle"
    >
        <div
            v-if="themeMode === 'light'"
            aria-hidden="true"
            class="pointer-events-none absolute inset-0 z-0 bg-cover bg-center bg-no-repeat"
            :style="lobbyInlineBackgroundStyle"
        />

        <AppBackgroundLayer
            v-if="themeMode !== 'light'"
            :image="lobbyBackgroundImage"
            :overlay-class="lobbyBackgroundOverlayClass"
            :glow-class="lobbyBackgroundGlowClass"
            :image-position="lobbyBackgroundPosition"
        />

        <div class="relative z-10 flex min-h-screen flex-col">
            <UserNavbar :show-guest-actions="true" />

            <div v-if="isEmailUnverified" class="px-4 pt-4 md:px-8">
                <div class="email-verification-banner flex flex-col gap-3 border-2 border-amber-400/60 bg-amber-500/15 p-3 md:flex-row md:items-center md:justify-between">
                    <div class="email-verification-banner__text text-[9px] uppercase leading-relaxed tracking-wide text-amber-100">
                        Email belum terverifikasi. Kamu tetap bisa eksplor game, tapi submit quest, shop, transfer gold, dan klaim reward terkunci sampai verifikasi selesai.
                    </div>
                    <a
                        href="/verify-email"
                        class="email-verification-banner__button btn-pixel relative z-30 border-amber-700 bg-amber-300 px-3 py-2 text-center text-[8px] font-bold uppercase text-black transition-colors hover:bg-amber-200"
                    >
                        Verifikasi Email
                    </a>
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
                    <section class="academy-hub academy-hub--joined lobby-color-system">
                        <div class="academy-scene">
                            <div class="academy-scene__backdrop"></div>
                            <div class="academy-scene__content">
                                <CarouselMenu
                                    v-model="activeMenu"
                                    :items="carouselItems"
                                />

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
                                    :new-count="getLocalNewCount('quest')"
                                    @item-open="acknowledgeContentItem"
                                />

                                <LibrarySection
                                    v-else-if="activeMenu === 'library'"
                                    :items="guidePreview"
                                    :auth-user="isLoggedIn"
                                    :new-count="getLocalNewCount('guide')"
                                    @item-open="acknowledgeContentItem"
                                />

                                <EventSection
                                    v-else-if="activeMenu === 'townhall'"
                                    :items="upcomingEventPreview"
                                    :auth-user="isLoggedIn"
                                    :new-count="getLocalNewCount('event')"
                                    @item-open="acknowledgeContentItem"
                                />

                                <DoopNewsSection
                                    v-else-if="activeMenu === 'doopnews'"
                                    :items="doopNewsPreview"
                                    :auth-user="isLoggedIn"
                                    :new-count="getLocalNewCount('doop_news')"
                                    @item-open="acknowledgeContentItem"
                                />

                                <PartySection
                                    v-else-if="activeMenu === 'party'"
                                    :items="groupPreview"
                                    :theme-mode="themeMode"
                                    :join-processing="joinForm.processing"
                                    :on-join="handleJoin"
                                    :on-leave="handleLeave"
                                    :new-count="getLocalNewCount('study_group')"
                                    @item-open="acknowledgeContentItem"
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

            <footer class="user-theme-footer mt-auto border-t-2 p-6 text-center md:backdrop-blur-md md:p-8">
                <p class="user-theme-muted break-words text-[7px] uppercase tracking-[0.18em] sm:text-[8px] sm:tracking-[0.3em]">
                    Build_Ver_1.2.1 // P-Quest Engine
                </p>
            </footer>
        </div>
    </div>
</template>

<style scoped>
@import "../../css/lobby-style.css";

.academy-hub {
    @apply overflow-hidden border-2 p-2 shadow-[0_14px_38px_rgba(2,8,16,0.42)];
    --lobby-primary: #202020;
    --lobby-primary-soft: #303030;
    --lobby-primary-deep: #161616;
    --lobby-secondary: #f7f7f7;
    --lobby-secondary-muted: #c4c2c2;
    --lobby-accent: #009999;
    --lobby-accent-soft: #38b2b2;
    --lobby-accent-deep: #087f7f;
    border-color: var(--panel-border);
    background-color: var(--panel);
}

[data-theme='light'] .academy-hub {
    background:
        linear-gradient(180deg, rgba(0, 153, 153, 0.07), transparent 22%),
        var(--lobby-secondary);
    color: var(--lobby-primary);
    box-shadow: 0 16px 34px rgba(32, 32, 32, 0.12);
}

[data-theme='light'].user-theme-root :deep(.user-navbar-shell) {
    border-bottom-color: #009999 !important;
    background: #202020 !important;
    color: #f7f7f7 !important;
    box-shadow: 0 10px 24px rgba(32, 32, 32, 0.20) !important;
}

[data-theme='light'].user-theme-root :deep(.user-navbar-brand-title) {
    color: #f7f7f7 !important;
    text-shadow: none !important;
}

[data-theme='light'].user-theme-root :deep(.nav-dock),
[data-theme='light'].user-theme-root :deep(.user-navbar-mobile-shell) {
    border-color: rgba(247, 247, 247, 0.18) !important;
    background: #181818 !important;
    box-shadow: none !important;
}

[data-theme='light'].user-theme-root :deep(.user-navbar-mobile-toggle) {
    border-color: rgba(0, 153, 153, 0.58) !important;
    background: #202020 !important;
    color: #f7f7f7 !important;
    box-shadow: none !important;
    text-shadow: none !important;
}

[data-theme='light'].user-theme-root :deep(.user-navbar-mobile-toggle:hover) {
    background: #009999 !important;
    color: #ffffff !important;
}

[data-theme='light'].user-theme-root .user-theme-footer {
    border-top-color: #009999 !important;
    background: #202020 !important;
    color: #f7f7f7 !important;
}

[data-theme='light'].user-theme-root .user-theme-footer .user-theme-muted {
    color: rgba(247, 247, 247, 0.72) !important;
}

.academy-hub--joined {
    @apply space-y-5;
}

.academy-scene {
    @apply relative overflow-hidden border;
    border-color: var(--panel-border);
    background-color: var(--panel);
    min-height: 268px;
}

.academy-scene__backdrop {
    @apply absolute inset-0;
    background-color: var(--panel);
}

[data-theme='light'] .academy-scene {
    background:
        linear-gradient(135deg, rgba(0, 153, 153, 0.10), transparent 34%),
        #ffffff;
}

[data-theme='light'] .academy-scene__backdrop {
    background: transparent;
    opacity: 1;
}

.academy-scene__content {
    @apply relative z-10 flex min-h-[268px] flex-col justify-center pt-1;
}

.academy-scene__copy {
    @apply mx-auto max-w-[760px] px-4 pb-4 text-center text-[9px] uppercase leading-relaxed tracking-[0.16em];
    color: var(--text-muted);
}

[data-theme='light'] .academy-scene__copy {
    color: color-mix(in srgb, var(--lobby-primary) 68%, var(--lobby-secondary) 32%);
}

.academy-scene__footer {
    @apply -mt-1;
}

.dashboard-focus-shell {
    @apply space-y-4;
}

.dashboard-focus-shell--joined {
    @apply border-t px-2 pb-2 pt-5;
    border-top-color: var(--panel-border);
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

[data-theme='light'] .dashboard-focus-shell__title {
    color: var(--lobby-primary);
}

.dashboard-focus-shell__helper {
    @apply max-w-[560px] text-[7px] uppercase leading-relaxed tracking-[0.12em] text-slate-400;
}

[data-theme='light'] .dashboard-focus-shell__helper {
    color: color-mix(in srgb, var(--lobby-primary) 58%, var(--lobby-secondary) 42%);
}

[data-theme='light'] .lobby-color-system :deep(.dashboard-section-shell) {
    background:
        linear-gradient(180deg, rgba(0, 153, 153, 0.08), transparent 26%),
        #f7f7f7 !important;
    border-color: #087f7f !important;
    color: #202020 !important;
    box-shadow:
        0 18px 34px rgba(32, 32, 32, 0.12),
        0 0 0 1px rgba(32, 32, 32, 0.08) !important;
}

[data-theme='light'] .lobby-color-system :deep(.dashboard-section-header) {
    border-bottom-color: rgba(32, 32, 32, 0.34) !important;
}

[data-theme='light'] .lobby-color-system :deep(.dashboard-section-header__eyebrow),
[data-theme='light'] .lobby-color-system :deep(.dashboard-section-header__title),
[data-theme='light'] .lobby-color-system :deep(.dashboard-empty-state__title) {
    color: #009999 !important;
}

[data-theme='light'] .lobby-color-system :deep(.dashboard-section-header__action) {
    border-color: #006f6f !important;
    background: #009999 !important;
    color: #ffffff !important;
    box-shadow: 4px 4px 0 #202020 !important;
}

[data-theme='light'] .lobby-color-system :deep(.dashboard-section-header__action:hover) {
    background: var(--lobby-accent) !important;
    color: #ffffff !important;
}

[data-theme='light'] .lobby-color-system :deep(.quest-item-card .text-white),
[data-theme='light'] .lobby-color-system :deep(.library-item-card .text-white),
[data-theme='light'] .lobby-color-system :deep(.event-card .text-white),
[data-theme='light'] .lobby-color-system :deep(.dashboard-section-shell article .text-white) {
    color: #202020 !important;
}

[data-theme='light'] .lobby-color-system :deep(.text-slate-300),
[data-theme='light'] .lobby-color-system :deep(.text-slate-400),
[data-theme='light'] .lobby-color-system :deep(.text-slate-500),
[data-theme='light'] .lobby-color-system :deep(.text-slate-600) {
    color: #626262 !important;
}

[data-theme='light'] .lobby-color-system :deep(.text-cyan-100),
[data-theme='light'] .lobby-color-system :deep(.text-cyan-200),
[data-theme='light'] .lobby-color-system :deep(.text-cyan-300),
[data-theme='light'] .lobby-color-system :deep(.text-cyan-300\/70),
[data-theme='light'] .lobby-color-system :deep(.text-cyan-300\/80),
[data-theme='light'] .lobby-color-system :deep(.text-sky-200) {
    color: #006f6f !important;
}

[data-theme='light'] .lobby-color-system :deep(.text-lime-200),
[data-theme='light'] .lobby-color-system :deep(.text-emerald-100),
[data-theme='light'] .lobby-color-system :deep(.text-emerald-300),
[data-theme='light'] .lobby-color-system :deep(.text-emerald-400) {
    color: #166534 !important;
}

[data-theme='light'] .lobby-color-system :deep(.text-yellow-300),
[data-theme='light'] .lobby-color-system :deep(.text-yellow-400),
[data-theme='light'] .lobby-color-system :deep(.text-yellow-500),
[data-theme='light'] .lobby-color-system :deep(.text-orange-300),
[data-theme='light'] .lobby-color-system :deep(.text-orange-500) {
    color: #9a5b00 !important;
}

[data-theme='light'] .lobby-color-system :deep(.border-slate-700),
[data-theme='light'] .lobby-color-system :deep(.border-slate-800),
[data-theme='light'] .lobby-color-system :deep(.border-cyan-400\/20),
[data-theme='light'] .lobby-color-system :deep(.border-cyan-500\/30),
[data-theme='light'] .lobby-color-system :deep(.border-cyan-700) {
    border-color: color-mix(in srgb, var(--lobby-accent) 36%, var(--lobby-primary) 64%) !important;
}

[data-theme='light'] .lobby-color-system :deep(.bg-\[\#0d1117\]),
[data-theme='light'] .lobby-color-system :deep(.bg-slate-900\/60),
[data-theme='light'] .lobby-color-system :deep(.bg-slate-800),
[data-theme='light'] .lobby-color-system :deep(.bg-black\/20) {
    background-color: #f1f4f4 !important;
}

[data-theme='light'] .lobby-color-system :deep(.quest-item-card),
[data-theme='light'] .lobby-color-system :deep(.library-item-card),
[data-theme='light'] .lobby-color-system :deep(.event-card),
[data-theme='light'] .lobby-color-system :deep(.doopnews-blog-card),
[data-theme='light'] .lobby-color-system :deep(.doopnews-featured),
[data-theme='light'] .lobby-color-system :deep(.doopnews-list-item),
[data-theme='light'] .lobby-color-system :deep(.party-card),
[data-theme='light'] .lobby-color-system :deep(.leaderboard-row),
[data-theme='light'] .lobby-color-system :deep(.dashboard-section-shell .space-y-3 > a),
[data-theme='light'] .lobby-color-system :deep(.dashboard-section-shell .space-y-3 > div),
[data-theme='light'] .lobby-color-system :deep(.daily-claim-card) {
    border-color: #d5e2e2 !important;
    background: #ffffff !important;
    color: #202020 !important;
    box-shadow:
        inset 0 0 0 1px rgba(32, 32, 32, 0.04),
        0 10px 22px rgba(32, 32, 32, 0.08) !important;
}

[data-theme='light'] .lobby-color-system :deep(.quest-item-card:hover),
[data-theme='light'] .lobby-color-system :deep(.library-item-card:hover),
[data-theme='light'] .lobby-color-system :deep(.event-card:hover),
[data-theme='light'] .lobby-color-system :deep(.doopnews-blog-card:hover),
[data-theme='light'] .lobby-color-system :deep(.doopnews-featured:hover),
[data-theme='light'] .lobby-color-system :deep(.doopnews-list-item:hover),
[data-theme='light'] .lobby-color-system :deep(.party-card:hover),
[data-theme='light'] .lobby-color-system :deep(.dashboard-section-shell .space-y-3 > a:hover) {
    border-color: #009999 !important;
    background: #f4fbfb !important;
    box-shadow: 0 12px 24px rgba(0, 153, 153, 0.14) !important;
}

[data-theme='light'] .lobby-color-system :deep(.quest-item-card > div:first-child > span:first-child) {
    border-color: rgba(0, 153, 153, 0.32) !important;
    background: #e3f5f5 !important;
    color: #006f6f !important;
}

[data-theme='light'] .lobby-color-system :deep(.quest-item-card .border-t) {
    border-color: rgba(32, 32, 32, 0.16) !important;
}

[data-theme='light'] .lobby-color-system :deep(.quest-item-card .text-slate-400),
[data-theme='light'] .lobby-color-system :deep(.quest-item-card .text-slate-500),
[data-theme='light'] .lobby-color-system :deep(.quest-item-card .text-slate-600) {
    color: #696969 !important;
}

[data-theme='light'] .lobby-color-system :deep(.quest-item-card .text-green-500) {
    color: #15803d !important;
}

[data-theme='light'] .lobby-color-system :deep(.quest-item-card .text-cyan-500),
[data-theme='light'] .lobby-color-system :deep(.quest-item-card .text-cyan-300),
[data-theme='light'] .lobby-color-system :deep(.quest-item-card .text-cyan-200) {
    color: #087f7f !important;
}

[data-theme='light'] .lobby-color-system :deep(.quest-item-card .text-orange-500),
[data-theme='light'] .lobby-color-system :deep(.quest-item-card .text-orange-300),
[data-theme='light'] .lobby-color-system :deep(.quest-item-card .text-yellow-500),
[data-theme='light'] .lobby-color-system :deep(.quest-item-card .text-yellow-400) {
    color: #a16207 !important;
}

[data-theme='light'] .lobby-color-system :deep(.quest-item-card .text-red-500) {
    color: #dc2626 !important;
}

[data-theme='light'] .lobby-color-system :deep(.quest-item-card .btn-pixel),
[data-theme='light'] .lobby-color-system :deep(.dashboard-section-header__action),
[data-theme='light'] .lobby-color-system :deep(.daily-claim-card__action) {
    border-color: #006f6f !important;
    background: #009999 !important;
    color: #ffffff !important;
}

[data-theme='light'] .lobby-color-system :deep(.quest-group-badge),
[data-theme='light'] .lobby-color-system :deep(.library-item-label),
[data-theme='light'] .lobby-color-system :deep(.event-card__meeting),
[data-theme='light'] .lobby-color-system :deep(.event-card__group),
[data-theme='light'] .lobby-color-system :deep(.rounded-full) {
    border-color: rgba(0, 153, 153, 0.34) !important;
    background: #e3f5f5 !important;
    color: #006f6f !important;
}

[data-theme='light'] .lobby-color-system :deep(.library-item-group) {
    color: #006f6f !important;
}

[data-theme='light'] .lobby-color-system :deep(.library-item-action) {
    color: #007777 !important;
    background: transparent !important;
    box-shadow: none !important;
}

[data-theme='light'] .lobby-color-system :deep(.library-item-action:hover) {
    background: transparent !important;
    color: #00b3b3 !important;
}

[data-theme='light'] .lobby-color-system :deep(.event-card__footer) {
    margin-top: auto !important;
}

[data-theme='light'] .lobby-color-system :deep(.event-card__action) {
    display: inline-flex !important;
    min-height: 30px !important;
    align-items: center !important;
    justify-content: center !important;
    border: 1px solid #006f6f !important;
    background: #009999 !important;
    padding: 0.45rem 0.65rem !important;
    color: #ffffff !important;
    box-shadow: none !important;
    text-shadow: none !important;
}

[data-theme='light'] .lobby-color-system :deep(.event-card__action:hover) {
    border-color: #005f5f !important;
    background: #007f7f !important;
    color: #ffffff !important;
}

[data-theme='light'] .lobby-color-system :deep(.doopnews-category),
[data-theme='light'] .lobby-color-system :deep(.doopnews-version) {
    border-color: rgba(190, 18, 60, 0.24) !important;
    background: #fff1f4 !important;
    color: #9f1239 !important;
}

[data-theme='light'] .lobby-color-system :deep(.doopnews-action) {
    border-color: #be123c !important;
    background: #fff1f4 !important;
    color: #9f1239 !important;
    box-shadow: none !important;
    text-shadow: none !important;
}

[data-theme='light'] .lobby-color-system :deep(.doopnews-action:hover) {
    background: #be123c !important;
    color: #ffffff !important;
}

[data-theme='light'] .lobby-color-system :deep(.daily-claim-card) {
    border-color: #087f7f !important;
    background: #f7f7f7 !important;
    color: #202020 !important;
}

[data-theme='light'] .lobby-color-system :deep(.daily-claim-card__header),
[data-theme='light'] .lobby-color-system :deep(.daily-claim-card__activities) {
    border-color: rgba(32, 32, 32, 0.24) !important;
}

[data-theme='light'] .lobby-color-system :deep(.daily-claim-card__eyebrow),
[data-theme='light'] .lobby-color-system :deep(.daily-claim-card__activities-eyebrow) {
    color: #009999 !important;
}

[data-theme='light'] .lobby-color-system :deep(.daily-claim-card__title),
[data-theme='light'] .lobby-color-system :deep(.daily-claim-card__activities-title),
[data-theme='light'] .lobby-color-system :deep(.daily-claim-card__activity-title),
[data-theme='light'] .lobby-color-system :deep(.daily-claim-card__empty-title) {
    color: #202020 !important;
}

[data-theme='light'] .lobby-color-system :deep(.daily-claim-card__copy),
[data-theme='light'] .lobby-color-system :deep(.daily-claim-card__empty-copy),
[data-theme='light'] .lobby-color-system :deep(.daily-claim-card__meta-label) {
    color: #626262 !important;
}

[data-theme='light'] .lobby-color-system :deep(.daily-claim-card__count-shell) {
    border-color: rgba(0, 153, 153, 0.38) !important;
    background: #e3f5f5 !important;
}

[data-theme='light'] .lobby-color-system :deep(.daily-claim-card__count-label),
[data-theme='light'] .lobby-color-system :deep(.daily-claim-card__count),
[data-theme='light'] .lobby-color-system :deep(.daily-claim-card__activities-count),
[data-theme='light'] .lobby-color-system :deep(.daily-claim-card__objective-box),
[data-theme='light'] .lobby-color-system :deep(.daily-claim-card__activity-progress-inline),
[data-theme='light'] .lobby-color-system :deep(.daily-claim-card__meta-value.text-cyan-200) {
    color: #006f6f !important;
}

[data-theme='light'] .lobby-color-system :deep(.daily-claim-card__activities-count) {
    border-color: rgba(0, 153, 153, 0.34) !important;
    background: #e3f5f5 !important;
}

[data-theme='light'] .lobby-color-system :deep(.daily-claim-card__meta),
[data-theme='light'] .lobby-color-system :deep(.daily-claim-card__activity) {
    border-color: #cbd8d8 !important;
    background: #ffffff !important;
    box-shadow: 0 6px 14px rgba(32, 32, 32, 0.06) !important;
}

[data-theme='light'] .lobby-color-system :deep(.daily-claim-card__meta-value.text-emerald-200) {
    color: #166534 !important;
}

[data-theme='light'] .lobby-color-system :deep(.daily-claim-card__activity-reward) {
    color: #9a5b00 !important;
}

[data-theme='light'] .lobby-color-system :deep(.daily-claim-card__activity-status--pending) {
    border-color: #d6a22d !important;
    background: #fffbeb !important;
    color: #92400e !important;
}

[data-theme='light'] .lobby-color-system :deep(.daily-claim-card__activity-status--completed) {
    border-color: #009999 !important;
    background: #e3f5f5 !important;
    color: #006f6f !important;
}

[data-theme='light'] .lobby-color-system :deep(.daily-claim-card__activity-status--claimed) {
    border-color: #22a35a !important;
    background: #f0fdf4 !important;
    color: #166534 !important;
}

[data-theme='light'] .lobby-color-system :deep(.daily-claim-card__activity-status--expired) {
    border-color: #dc2626 !important;
    background: #fef2f2 !important;
    color: #b91c1c !important;
}

[data-theme='light'] .lobby-color-system :deep(.daily-claim-card__action:disabled) {
    border-color: #9ca3af !important;
    background: #e5e7eb !important;
    color: #555555 !important;
    box-shadow: none !important;
}

[data-theme='light'] .lobby-color-system :deep(.quest-item-card .text-sky-200) {
    color: #075985 !important;
}

[data-theme='light'] .lobby-color-system :deep(.quest-item-card .text-lime-200) {
    color: #3f6212 !important;
}

[data-theme='light'] .lobby-color-system :deep(.leaderboard-row-top .text-cyan-300),
[data-theme='light'] .lobby-color-system :deep(.dashboard-section-shell article .text-cyan-300\/70) {
    color: #007777 !important;
}

[data-theme='light'] .lobby-color-system :deep(.leaderboard-row-top .text-white) {
    color: #202020 !important;
}

[data-theme='light'] .lobby-color-system :deep(.leaderboard-class-select) {
    border-color: #087f7f !important;
    background: #ffffff !important;
    color: #202020 !important;
    box-shadow: 2px 2px 0 rgba(32, 32, 32, 0.14);
}

[data-theme='light'] .lobby-color-system :deep(.leaderboard-class-select:focus) {
    border-color: #009999 !important;
    box-shadow: 0 0 0 2px rgba(0, 153, 153, 0.16);
}

[data-theme='light'] .lobby-color-system :deep(.leaderboard-class-select:disabled) {
    border-color: #9eb8b8 !important;
    background: #edf4f4 !important;
    color: #555555 !important;
    cursor: not-allowed;
    opacity: 1;
}

[data-theme='light'] .lobby-color-system :deep(.leaderboard-class-select option) {
    background: #ffffff;
    color: #202020;
}

[data-theme='light'] .lobby-color-system :deep(.quest-item-card.border-red-600) {
    border-color: #dc2626 !important;
    background: #fff5f5 !important;
    box-shadow:
        inset 0 0 0 1px rgba(255, 255, 255, 0.10),
        0 12px 24px rgba(185, 28, 28, 0.22) !important;
}

[data-theme='light'] .lobby-color-system :deep(.quest-item-card.border-yellow-500),
[data-theme='light'] .lobby-color-system :deep(.quest-item-card.border-yellow-600) {
    border-color: #ca8a04 !important;
    background: #fffbeb !important;
}

[data-theme='light'] .lobby-color-system :deep(.quest-item-card.border-emerald-500) {
    border-color: #16a34a !important;
    background: #f0fdf4 !important;
}

[data-theme='light'] .lobby-color-system :deep(.quest-item-card.border-cyan-500) {
    border-color: #0891b2 !important;
}

[data-theme='light'] .lobby-color-system :deep(.quest-item-card .bg-red-700) {
    border-color: #7f1d1d !important;
    background: #dc2626 !important;
    color: #ffffff !important;
}

[data-theme='light'] .lobby-color-system :deep(.quest-item-card .bg-yellow-600) {
    border-color: #713f12 !important;
    background: #eab308 !important;
    color: #202020 !important;
}

[data-theme='light'] .lobby-color-system :deep(.quest-item-card .bg-emerald-600),
[data-theme='light'] .lobby-color-system :deep(.quest-item-card .bg-cyan-600) {
    border-color: #064e3b !important;
    background: #10b981 !important;
    color: #202020 !important;
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
