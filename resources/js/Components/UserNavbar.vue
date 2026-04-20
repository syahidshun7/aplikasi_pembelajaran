<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import NotificationBell from '@/Components/NotificationBell.vue';
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

const normalizedUserRole = computed(() => String(auth.value?.user?.role || '').trim().toLowerCase());
const isStaff = computed(() => ['super_admin', 'admin', 'mentor'].includes(normalizedUserRole.value));
const isStaffPlayMode = computed(() => Boolean(auth.value?.user?.staff_play_mode));
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
                router.post(route('logout'));
            }
        });
};
</script>

<template>
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

                    <Link :href="route('hall.creations.index')" class="nav-action nav-action--hall" @click="closeMobileMenu">
                        <i class="fi fi-rr-lightbulb-on text-[10px] leading-none"></i>
                        <span class="hidden xl:inline">Hall of Creations</span>
                        <span class="xl:hidden">Hall</span>
                    </Link>

                    <NotificationBell />

                    <span
                        v-if="isStaffPlayMode"
                        class="nav-action border-cyan-500/60 bg-cyan-500/10 text-cyan-200"
                    >
                        Staff Play Mode
                    </span>

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
            class="inline-flex h-10 w-10 items-center justify-center border-2 border-slate-600 bg-slate-900/70 text-cyan-300 lg:hidden"
            :aria-expanded="mobileMenuOpen ? 'true' : 'false'"
            aria-label="Toggle menu"
            @click="mobileMenuOpen = !mobileMenuOpen"
        >
            <i :class="mobileMenuOpen ? 'fi fi-rr-cross-small' : 'fi fi-rr-menu-burger'" class="text-[14px]"></i>
        </button>
    </nav>

    <div v-if="mobileMenuOpen && canOpenMobileMenu" class="relative z-50 px-4 pb-4 lg:hidden">
        <div class="space-y-2 border-2 border-[#3d415f] bg-[#1a1c2c] p-3 shadow-2xl md:bg-[#1a1c2c]/95 md:backdrop-blur-sm">
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

                <span
                    v-if="isStaffPlayMode"
                    class="nav-action w-full justify-center border-cyan-500/60 bg-cyan-500/10 text-cyan-200"
                >
                    Staff Play Mode
                </span>

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
</template>
