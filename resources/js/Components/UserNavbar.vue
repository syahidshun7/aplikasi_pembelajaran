<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import NotificationBell from '@/Components/NotificationBell.vue';
import { toast } from '@/Utils/Alert';

const props = defineProps({
    showGuestActions: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();
const USER_THEME_STORAGE_KEY = 'dooptech-user-theme';
const USER_THEME_EVENT = 'dooptech:user-theme-change';
const auth = computed(() => page.props.auth || {});
const mobileMenuOpen = ref(false);
const userTheme = ref('dark');

const normalizedUserRole = computed(() => String(auth.value?.user?.role || '').trim().toLowerCase());
const isStaff = computed(() => ['super_admin', 'admin', 'mentor'].includes(normalizedUserRole.value));
const canAccessDoopLab = computed(() => Boolean(auth.value?.user?.can_access_dooplab));
const staffNavLabel = computed(() => {
    if (normalizedUserRole.value === 'super_admin') return 'Super Admin';
    if (normalizedUserRole.value === 'mentor') return 'Mentor';
    return 'Admin';
});
const shouldShowGuestActions = computed(() => !auth.value?.user && props.showGuestActions);
const canOpenMobileMenu = computed(() => Boolean(auth.value?.user) || shouldShowGuestActions.value);

const closeMobileMenu = () => {
    mobileMenuOpen.value = false;
};

const normalizeTheme = (value) => (String(value || '').toLowerCase() === 'light' ? 'light' : 'dark');

const setUserTheme = (nextTheme, options = {}) => {
    const { persist = true, broadcast = true } = options;
    const normalizedTheme = normalizeTheme(nextTheme);
    userTheme.value = normalizedTheme;

    if (typeof window === 'undefined') {
        return;
    }

    if (persist) {
        window.localStorage.setItem(USER_THEME_STORAGE_KEY, normalizedTheme);
    }

    if (broadcast) {
        window.dispatchEvent(new CustomEvent(USER_THEME_EVENT, { detail: { theme: normalizedTheme } }));
    }
};

const syncThemeFromStorage = (event) => {
    if (event.key !== USER_THEME_STORAGE_KEY) {
        return;
    }

    setUserTheme(event.newValue, { persist: false, broadcast: false });
};

const syncThemeFromBroadcast = (event) => {
    setUserTheme(event?.detail?.theme, { persist: false, broadcast: false });
};

const handleLogout = () => {
    toast.confirm('QUIT GAME?', 'Are you sure you want to exit?')
        .then((result) => {
            if (result.isConfirmed) {
                closeMobileMenu();
                router.post(route('logout'));
            }
        });
};

onMounted(() => {
    if (typeof window === 'undefined') {
        return;
    }

    setUserTheme(window.localStorage.getItem(USER_THEME_STORAGE_KEY), { persist: false, broadcast: false });
    window.addEventListener('storage', syncThemeFromStorage);
    window.addEventListener(USER_THEME_EVENT, syncThemeFromBroadcast);
});

onBeforeUnmount(() => {
    if (typeof window === 'undefined') {
        return;
    }

    window.removeEventListener('storage', syncThemeFromStorage);
    window.removeEventListener(USER_THEME_EVENT, syncThemeFromBroadcast);
});
</script>

<template>
    <div data-app-surface="user" :data-theme="userTheme" class="user-navbar-theme-scope">
    <nav class="user-navbar-shell sticky top-0 z-50 flex items-center justify-between border-b-4 border-[var(--panel-border)] bg-[var(--panel)] p-4 text-[var(--text)] shadow-2xl md:bg-[var(--panel-soft)] md:backdrop-blur-sm md:px-8">
        <div class="flex items-center gap-4">
            <Link :href="route('lobby')" class="group flex items-center gap-4" @click="closeMobileMenu">
                <div class="user-navbar-brand-logo flex h-10 w-10 items-center justify-center overflow-hidden border-b-4 border-r-4 border-[var(--accent)] bg-[var(--bg)] transition-transform group-hover:scale-110">
                    <img src="/images/logo.png" alt="Logo" class="pixelated h-7 w-7 object-contain">
                </div>
                <h1 class="user-navbar-brand-title text-[8px] uppercase tracking-tighter text-[var(--accent)] opacity-85 group-hover:opacity-100 md:text-sm">
                    DOOPTECH
                </h1>
            </Link>
        </div>

        <div v-if="auth.user || shouldShowGuestActions" class="hidden items-center lg:flex">
            <template v-if="auth.user">
                <div class="nav-dock">
                    <Link v-if="isStaff" :href="route('admin.dashboard')" class="nav-action nav-action--admin" @click="closeMobileMenu">
                        {{ staffNavLabel }}
                    </Link>

                    <Link :href="route('profile.dashboard')" class="nav-action nav-action--profile" @click="closeMobileMenu">
                        <i class="fi fi-rr-user text-[10px] leading-none"></i>
                        Profile
                    </Link>

                    <Link :href="route('shop.index')" class="nav-action nav-action--shop" @click="closeMobileMenu">
                        <i class="fi fi-rr-shopping-cart text-[10px] leading-none"></i>
                        Shop
                    </Link>

                    <Link :href="route('dooplab.index')" class="nav-action nav-action--dooplab" @click="closeMobileMenu">
                        <i :class="['fi', canAccessDoopLab ? 'fi-rr-apps' : 'fi-rr-lock', 'text-[10px]', 'leading-none']"></i>
                        DoopLab
                    </Link>

                    <Link :href="route('hall.creations.index')" class="nav-action nav-action--hall" @click="closeMobileMenu">
                        <i class="fi fi-rr-lightbulb-on text-[10px] leading-none"></i>
                        Hall of Creations
                    </Link>

                    <NotificationBell />

                    <button @click="handleLogout" class="nav-action nav-action--logout" type="button">
                        <span class="sr-only">Logout</span>
                        [X]
                    </button>
                </div>
            </template>

            <template v-else-if="shouldShowGuestActions">
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
            v-if="canOpenMobileMenu"
            type="button"
            class="user-navbar-mobile-toggle inline-flex h-10 w-10 items-center justify-center border-2 border-[var(--panel-border)] bg-[var(--panel-soft)] text-[var(--accent)] lg:hidden"
            :aria-expanded="mobileMenuOpen ? 'true' : 'false'"
            aria-label="Toggle menu"
            @click="mobileMenuOpen = !mobileMenuOpen"
        >
            <i :class="mobileMenuOpen ? 'fi fi-rr-cross-small' : 'fi fi-rr-menu-burger'" class="text-[14px]"></i>
        </button>
    </nav>

    <div v-if="mobileMenuOpen && canOpenMobileMenu" class="relative z-50 px-4 pb-4 lg:hidden">
        <div class="user-navbar-mobile-shell space-y-2 border-2 border-[var(--panel-border)] bg-[var(--panel)] p-3 shadow-2xl md:bg-[var(--panel-soft)] md:backdrop-blur-sm">
            <template v-if="auth.user">
                <Link
                    v-if="isStaff"
                    :href="route('admin.dashboard')"
                    class="nav-action nav-action--admin w-full justify-center"
                    @click="closeMobileMenu"
                >
                    {{ staffNavLabel }}
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
                    :href="route('dooplab.index')"
                    class="nav-action nav-action--dooplab w-full justify-center"
                    @click="closeMobileMenu"
                >
                    <i :class="['fi', canAccessDoopLab ? 'fi-rr-apps' : 'fi-rr-lock', 'text-[10px]', 'leading-none']"></i>
                    DoopLab
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
                    class="nav-action nav-action--logout w-full justify-center"
                    type="button"
                    @click="handleLogout"
                >
                    [X]
                </button>
            </template>

            <template v-else-if="shouldShowGuestActions">
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
    </div>
</template>
