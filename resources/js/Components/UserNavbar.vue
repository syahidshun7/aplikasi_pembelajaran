<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';
import NotificationBell from '@/Components/NotificationBell.vue';
import { useUserTheme } from '@/Composables/useUserTheme';
import { toast } from '@/Utils/Alert';

const props = defineProps({
    showGuestActions: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();
const auth = computed(() => page.props.auth || {});
const mobileMenuOpen = ref(false);
const { themeMode, setUserTheme } = useUserTheme();
const isThemeApplying = ref(false);
const pendingTheme = ref(null);

const themeActionLabel = computed(() => themeMode.value === 'light' ? 'Dark' : 'Light');
const themeActionIcon = computed(() => themeMode.value === 'light' ? 'fi-rr-moon' : 'fi-rr-sun');

const waitForThemePaint = () => new Promise((resolve) => {
    requestAnimationFrame(() => requestAnimationFrame(resolve));
});

const applyNavbarTheme = async () => {
    if (isThemeApplying.value) return;

    const nextTheme = themeMode.value === 'light' ? 'dark' : 'light';
    pendingTheme.value = nextTheme;
    isThemeApplying.value = true;
    setUserTheme(nextTheme);

    await nextTick();
    await waitForThemePaint();
    await new Promise((resolve) => setTimeout(resolve, 300));

    pendingTheme.value = null;
    isThemeApplying.value = false;
};

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

const handleLogout = () => {
    toast.confirm('QUIT GAME?', 'Are you sure you want to exit?')
        .then((result) => {
            if (result.isConfirmed) {
                closeMobileMenu();
                router.post(route('logout'), {}, {
                    preserveScroll: false,
                    preserveState: false,
                    replace: true,
                });
            }
        });
};

</script>

<template>
    <div data-app-surface="user" :data-theme="themeMode" class="user-navbar-theme-scope relative">
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

                    <Link :href="route('inventory.index')" class="nav-action nav-action--inventory" @click="closeMobileMenu">
                        <i class="fi fi-rr-box-open text-[10px] leading-none"></i>
                        Inventory
                    </Link>

                    <Link :href="route('dooplab.index')" class="nav-action nav-action--dooplab" @click="closeMobileMenu">
                        <i :class="['fi', canAccessDoopLab ? 'fi-rr-apps' : 'fi-rr-lock', 'text-[10px]', 'leading-none']"></i>
                        DoopLab
                    </Link>

                    <Link :href="route('hall.creations.index')" class="nav-action nav-action--hall" @click="closeMobileMenu">
                        <i class="fi fi-rr-lightbulb-on text-[10px] leading-none"></i>
                        Hall of Creations
                    </Link>

                    <button
                        type="button"
                        class="nav-action nav-action--theme nav-action--theme-icon"
                        :class="themeMode === 'dark' ? 'nav-action--theme-light' : 'nav-action--theme-dark'"
                        :aria-label="`Ubah tema ke ${themeActionLabel}`"
                        :title="`Ubah tema ke ${themeActionLabel}`"
                        :disabled="isThemeApplying"
                        @click="applyNavbarTheme"
                    >
                        <i :class="['fi', themeActionIcon, 'text-[10px]', 'leading-none']"></i>
                        <span class="sr-only">{{ themeActionLabel }}</span>
                    </button>

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

    <div v-if="mobileMenuOpen && canOpenMobileMenu" class="absolute left-0 right-0 z-50 px-4 pb-4 lg:hidden">
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
                    :href="route('inventory.index')"
                    class="nav-action nav-action--inventory w-full justify-center"
                    @click="closeMobileMenu"
                >
                    <i class="fi fi-rr-box-open text-[10px] leading-none"></i>
                    Inventory
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

                <button
                    type="button"
                    class="nav-action nav-action--theme nav-action--theme-icon w-full justify-center"
                    :class="themeMode === 'dark' ? 'nav-action--theme-light' : 'nav-action--theme-dark'"
                    :aria-label="`Ubah tema ke ${themeActionLabel}`"
                    :disabled="isThemeApplying"
                    @click="applyNavbarTheme"
                >
                    <i :class="['fi', themeActionIcon, 'text-[10px]', 'leading-none']"></i>
                    <span>{{ themeActionLabel }}</span>
                </button>

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
                    <span>[X]</span>
                    <span>Logout</span>
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

    <Teleport to="body">
        <div
            v-if="isThemeApplying"
            data-app-surface="user"
            :data-theme="themeMode"
            class="fixed inset-0 z-[300] flex items-center justify-center bg-black/55 px-4 font-['Press_Start_2P']"
            role="status"
            aria-live="polite"
            aria-busy="true"
        >
            <div class="w-full max-w-sm border-2 border-[#009999] bg-[#202020] p-5 text-center shadow-[6px_6px_0_rgba(0,0,0,0.35)]">
                <div class="mx-auto h-8 w-8 animate-spin border-4 border-[#f7f7f7]/25 border-t-[#009999]" />
                <p class="mt-4 text-[9px] uppercase leading-relaxed text-[#f7f7f7]">
                    Applying_{{ pendingTheme || themeMode }}_Theme
                </p>
                <p class="mt-2 text-[7px] uppercase text-[#b9d4d4]">Synchronizing_Display...</p>
            </div>
        </div>
    </Teleport>
    </div>
</template>
