<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, defineAsyncComponent, ref } from 'vue';
import { useLobby } from '@/Composables/useLobby';
import FloatingChat from '@/Components/FloatingChat.vue';
import NotificationBell from '@/Components/NotificationBell.vue';
import QuestSection from '@/Components/Dashboard/QuestSection.vue';
import LibrarySection from '@/Components/Dashboard/LibrarySection.vue';
import EventSection from '@/Components/Dashboard/EventSection.vue';
import PartySection from '@/Components/Dashboard/PartySection.vue';
import LeaderboardSection from '@/Components/Dashboard/LeaderboardSection.vue';

const CarouselMenu = defineAsyncComponent(() => import('@/Components/Dashboard/CarouselMenu.vue'));

const props = defineProps({
    players: Array,
    quests: Array,
    studyGroups: Array,
    materi: Array,
    events: Array,
    auth: Object,
});

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
    handleLogout,
} = useLobby(props);

const mobileMenuOpen = ref(false);
const activeMenu = ref('townhall');
const page = usePage();
const isStaff = computed(() => ['super_admin', 'admin', 'mentor'].includes(String(auth.value?.user?.role || '').toLowerCase()));
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

const playerItems = computed(() => {
    return (players.value || []).map((player) => {
        const seed = player?.username || player?.name || 'guild-member';

        return {
            ...player,
            __dicebear_src: `https://api.dicebear.com/7.x/pixel-art/svg?seed=${seed}`,
            __score: Number(player?.exp ?? player?.points ?? ((player?.level || 1) * 100)),
        };
    });
});

const leaderboardPreview = computed(() => playerItems.value.slice(0, 10));

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
        subtitle: `${leaderboardPreview.value.length} top rankers`,
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
            helper: `Showing current top ${leaderboardPreview.value.length} ranks.`,
        },
    };

    return moduleMap[activeMenu.value] ?? { helper: 'Showing preview data only.' };
});

const closeMobileMenu = () => {
    mobileMenuOpen.value = false;
};
</script>

<template>
    <Head title="DOOPTECH" />

    <div
        class="relative min-h-screen overflow-x-hidden bg-[#0a0c10] font-['Press_Start_2P']"
    >
        <div
            aria-hidden="true"
            class="absolute inset-0 z-0 bg-[#0a0c10] bg-cover bg-center bg-no-repeat"
            style="background-image: url('/images/bg-loby.png');"
        ></div>
        <div class="absolute inset-0 z-0 bg-black/60"></div>

        <div class="relative z-10 flex min-h-screen flex-col">
            <nav class="sticky top-0 z-50 flex items-center justify-between border-b-4 border-[#3d415f] bg-[#1a1c2c] p-4 shadow-2xl md:bg-[#1a1c2c]/90 md:backdrop-blur-sm md:px-8">
                <div class="flex items-center gap-4">
                    <Link :href="route('lobby')" class="group flex items-center gap-4" @click="closeMobileMenu">
                        <div class="flex h-10 w-10 items-center justify-center overflow-hidden border-b-4 border-r-4 border-[#4ed4d4] bg-[#0a0c10] transition-transform group-hover:scale-110">
                            <img src="/images/logo.png" alt="Logo" class="pixelated h-7 w-7 object-contain">
                        </div>
                        <h1 class="text-[8px] uppercase tracking-tighter text-[#009999] group-hover:text-[#4ed4d4] md:text-sm">
                            DOOPTECH
                        </h1>
                    </Link>
                </div>

                <div class="hidden items-center lg:flex">
                    <template v-if="auth.user">
                        <div class="nav-dock">
                            <Link v-if="isStaff" :href="route('admin.dashboard')" class="nav-action nav-action--admin">
                                Admin
                            </Link>

                            <Link :href="route('profile.dashboard')" class="nav-action nav-action--profile">
                                <i class="fi fi-rr-user text-[10px] leading-none"></i>
                                Profile
                            </Link>

                            <Link :href="route('shop.index')" class="nav-action nav-action--shop">
                                <i class="fi fi-rr-shopping-cart text-[10px] leading-none"></i>
                                Shop
                            </Link>

                            <Link :href="route('hall.creations.index')" class="nav-action nav-action--hall">
                                <i class="fi fi-rr-lightbulb-on text-[10px] leading-none"></i>
                                <span class="hidden xl:inline">Hall of Creations</span>
                                <span class="xl:hidden">Hall</span>
                            </Link>

                            <NotificationBell />

                            <button @click="handleLogout" class="nav-action nav-action--logout" type="button">
                                <span class="sr-only">Logout</span>
                                [X]
                            </button>
                        </div>
                    </template>

                    <template v-else>
                        <div class="nav-dock">
                            <Link :href="route('login')" class="nav-action nav-action--profile" @click="closeMobileMenu">
                                Login
                            </Link>
                            <Link :href="route('register')" class="nav-action nav-action--shop" @click="closeMobileMenu">
                                Register
                            </Link>
                        </div>
                    </template>
                </div>

                <button
                    type="button"
                    class="inline-flex h-10 w-10 items-center justify-center border-2 border-slate-600 bg-slate-900/70 text-cyan-300 lg:hidden"
                    @click="mobileMenuOpen = !mobileMenuOpen"
                    :aria-expanded="mobileMenuOpen ? 'true' : 'false'"
                    aria-label="Toggle menu"
                >
                    <i :class="mobileMenuOpen ? 'fi fi-rr-cross-small' : 'fi fi-rr-menu-burger'" class="text-[14px]"></i>
                </button>
            </nav>

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

            <div v-if="mobileMenuOpen" class="relative z-50 px-4 pb-4 lg:hidden">
                <div class="space-y-2 border-2 border-[#3d415f] bg-[#1a1c2c] p-3 shadow-2xl md:bg-[#1a1c2c]/95 md:backdrop-blur-sm">
                    <template v-if="auth.user">
                        <Link
                            v-if="isStaff"
                            :href="route('admin.dashboard')"
                            class="nav-action nav-action--admin w-full justify-center"
                            @click="closeMobileMenu"
                        >
                            Admin
                        </Link>

                        <Link
                            :href="route('profile.dashboard')"
                            class="nav-action nav-action--profile w-full justify-center"
                            @click="closeMobileMenu"
                        >
                            <i class="fi fi-rr-user text-[10px] leading-none"></i>
                            Profile
                        </Link>

                        <Link
                            :href="route('shop.index')"
                            class="nav-action nav-action--shop w-full justify-center"
                            @click="closeMobileMenu"
                        >
                            <i class="fi fi-rr-shopping-cart text-[10px] leading-none"></i>
                            Shop
                        </Link>

                        <Link
                            :href="route('hall.creations.index')"
                            class="nav-action nav-action--hall w-full justify-center"
                            @click="closeMobileMenu"
                        >
                            <i class="fi fi-rr-lightbulb-on text-[10px] leading-none"></i>
                            Hall of Creations
                        </Link>

                        <Link
                            :href="route('notifications.index')"
                            class="nav-action nav-action--notifications w-full justify-center"
                            @click="closeMobileMenu"
                        >
                            <i class="fi fi-rr-bell text-[10px] leading-none"></i>
                            Notifications
                            <span
                                v-if="Number(page.props?.notificationCenter?.unread_count || 0) > 0"
                                class="rounded-full bg-cyan-300 px-2 py-[2px] text-[8px] font-bold text-black"
                            >
                                {{ Number(page.props?.notificationCenter?.unread_count || 0) }}
                            </span>
                        </Link>

                        <button
                            @click="handleLogout(); closeMobileMenu()"
                            class="nav-action nav-action--logout w-full justify-center"
                            type="button"
                        >
                            [X]
                        </button>
                    </template>

                    <template v-else>
                        <Link
                            :href="route('login')"
                            class="nav-action nav-action--profile w-full justify-center"
                            @click="closeMobileMenu"
                        >
                            Login
                        </Link>
                        <Link
                            :href="route('register')"
                            class="nav-action nav-action--shop w-full justify-center"
                            @click="closeMobileMenu"
                        >
                            Register
                        </Link>
                    </template>
                </div>
            </div>

            <main class="flex-1 p-3 sm:p-4 md:p-8">
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
                            />
                        </Transition>
                    </section>
                </section>
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
