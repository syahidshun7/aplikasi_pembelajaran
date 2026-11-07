<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Swal from 'sweetalert2';
import NotificationBell from '@/Components/NotificationBell.vue';

const page = usePage();
const mobileMenuOpen = ref(false);
const desktopMenuOpen = ref(false);
const authUser = computed(() => page.props?.auth?.user || null);
const currentRole = computed(() => String(authUser.value?.role || 'user').toLowerCase());
const isAdminAccess = computed(() => ['super_admin', 'admin'].includes(currentRole.value));
const isTrueSuperAdmin = computed(() => currentRole.value === 'super_admin');
const isMentor = computed(() => currentRole.value === 'mentor');
const roleLabel = computed(() => {
    if (isTrueSuperAdmin.value) return 'SUPER_ADMIN';
    if (currentRole.value === 'admin') return 'ADMIN';
    if (isMentor.value) return 'MENTOR';
    return currentRole.value.toUpperCase();
});

const closeAllMenus = () => {
    mobileMenuOpen.value = false;
    desktopMenuOpen.value = false;
};

const handleLogout = () => {
    Swal.fire({
        title: 'TERMINATE_SESSION?',
        text: "Closing administrative access to the realm...",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'SHUTDOWN',
        cancelButtonText: 'CANCEL',
        background: '#1a1c2c',
        color: '#4ed4d4',
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3d415f',
    }).then((result) => {
        if (result.isConfirmed) {
            closeAllMenus();
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
    <nav class="admin-nav relative z-[120] overflow-visible mb-6 md:mb-8 flex justify-between items-center gap-3">
        
        <div class="flex items-center gap-3 min-w-0">
            <div class="flex flex-col">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 bg-[#00a99d] rounded-full"></span>
                    <h1 class="text-[#173b40] text-base sm:text-lg font-bold tracking-tight">
                        Admin dashboard
                    </h1>
                </div>
                <p class="hidden sm:block text-xs text-[#73848b] mt-1">
                    {{ authUser?.name || '-' }} · {{ roleLabel }}
                </p>
            </div>
        </div>

        <button
            type="button"
            class="md:hidden inline-flex items-center justify-center w-10 h-10 rounded-xl border border-[#c9dada] bg-white text-[#007f78]"
            @click="mobileMenuOpen = !mobileMenuOpen"
            :aria-expanded="mobileMenuOpen ? 'true' : 'false'"
            aria-label="Toggle admin menu"
        >
            <i :class="mobileMenuOpen ? 'fi fi-rr-cross-small' : 'fi fi-rr-menu-burger'" class="text-[14px]"></i>
        </button>

        <div class="hidden md:flex flex-wrap justify-center items-center gap-3 md:gap-4">
            <Link :href="route('dashboard')"
                    class="nav-item hover:text-[#007f78] transition-colors">
                Dashboard
            </Link>

            <Link :href="route('dooplab.dashboard')"
                    class="nav-item hover:text-[#007f78] transition-colors text-[#007f78]">
                DoopLab
            </Link>

            <Link :href="route('hall.creations.index')"
                    class="nav-item hover:text-[#007f78] transition-colors text-[#007f78]">
                Creations
            </Link>

            <Link :href="route('admin.doopnews.index')"
                    class="nav-item hover:text-[#007f78] transition-colors text-[#c67a22]">
                DoopNews
            </Link>

            <NotificationBell variant="admin" />

            <div class="relative z-[130]">
                <button
                    type="button"
                    class="nav-item cursor-pointer list-none rounded-lg border border-[#c9dada] px-3 py-2 hover:border-[#007f78] hover:text-[#007f78]"
                    @click="desktopMenuOpen = !desktopMenuOpen"
                >
                    MENU
                </button>
                <div v-if="desktopMenuOpen" class="absolute right-0 mt-2 min-w-[220px] rounded-xl bg-white border border-[#dce7e8] shadow-xl p-2 z-[140]">
                    <Link :href="route('admin.profile.edit')" class="dropdown-item text-emerald-400" @click="closeAllMenus">PROFILE</Link>
                    <Link v-if="isTrueSuperAdmin" href="/admin/materi" class="dropdown-item" @click="closeAllMenus">GUIDE</Link>
                    <Link v-if="isTrueSuperAdmin" :href="route('quests.index')" class="dropdown-item" @click="closeAllMenus">QUEST</Link>
                    <Link v-if="isTrueSuperAdmin" :href="route('admin.events.index')" class="dropdown-item" @click="closeAllMenus">EVENTS</Link>
                    <Link :href="route('admin.doopnews.index')" class="dropdown-item text-amber-300" @click="closeAllMenus">DOOPNEWS</Link>
                    <Link :href="route('admin.creations.queue')" class="dropdown-item" @click="closeAllMenus">CREATION REVIEW</Link>
                    <Link :href="route('admin.task-banks.index')" class="dropdown-item" @click="closeAllMenus">TASK BANK</Link>
                    <Link v-if="isAdminAccess" :href="route('admin.daily-quest-definitions.index')" class="dropdown-item" @click="closeAllMenus">DAILY QUEST</Link>
                    <Link :href="route('admin.rubrics.index')" class="dropdown-item" @click="closeAllMenus">RUBRICS</Link>
                    <Link v-if="isAdminAccess" :href="route('admin.jobs.index')" class="dropdown-item" @click="closeAllMenus">JOBS</Link>
                    <Link v-if="isAdminAccess" :href="route('admin.shop-items.index')" class="dropdown-item" @click="closeAllMenus">SHOP ITEMS</Link>
                    <Link v-if="isAdminAccess" :href="route('admin.profile-skins.index')" class="dropdown-item" @click="closeAllMenus">PROFILE SKINS</Link>
                    <Link v-if="isAdminAccess || isMentor" :href="route('groups.manage')" class="dropdown-item" @click="closeAllMenus">STUDY_GROUP</Link>
                    <Link v-if="isAdminAccess" :href="route('admin.submissions.manage.index')" class="dropdown-item" @click="closeAllMenus">SUBMISSIONS</Link>
                    <Link v-if="isAdminAccess" :href="route('admin.users.index')" class="dropdown-item" @click="closeAllMenus">USERS</Link>
                    <Link v-if="isAdminAccess" :href="route('admin.error-logs.index')" class="dropdown-item" @click="closeAllMenus">ERROR LOGS</Link>
                    <button type="button" @click="handleLogout" class="dropdown-item w-full text-left text-red-400 hover:text-white">
                        DISCONNECT
                    </button>
                </div>
            </div>
        </div>

        <div
            v-if="mobileMenuOpen"
            class="md:hidden absolute left-0 right-0 top-[calc(100%+8px)] rounded-xl bg-white border border-[#dce7e8] shadow-xl p-2 z-[140] mx-3"
        >
            <div class="grid grid-cols-1 gap-2">
                <Link :href="route('dashboard')" class="dropdown-item" @click="closeAllMenus">ADMIN_DASHBOARD</Link>
                <Link :href="route('dooplab.dashboard')" class="dropdown-item text-cyan-400" @click="closeAllMenus">DOOPLAB</Link>
                <Link :href="route('hall.creations.index')" class="dropdown-item text-emerald-400" @click="closeAllMenus">HALL_OF_CREATIONS</Link>
                <Link :href="route('notifications.index')" class="dropdown-item" @click="closeAllMenus">
                    NOTIFICATIONS
                    <span v-if="Number(page.props?.notificationCenter?.unread_count || 0) > 0" class="ml-2 text-cyan-300">
                        [{{ Number(page.props?.notificationCenter?.unread_count || 0) }}]
                    </span>
                </Link>
                <Link :href="route('admin.profile.edit')" class="dropdown-item text-emerald-400" @click="closeAllMenus">PROFILE</Link>
                <Link v-if="isTrueSuperAdmin" href="/admin/materi" class="dropdown-item" @click="closeAllMenus">GUIDE</Link>
                <Link v-if="isTrueSuperAdmin" :href="route('quests.index')" class="dropdown-item" @click="closeAllMenus">QUEST</Link>
                <Link v-if="isTrueSuperAdmin" :href="route('admin.events.index')" class="dropdown-item" @click="closeAllMenus">EVENTS</Link>
                <Link :href="route('admin.doopnews.index')" class="dropdown-item text-amber-300" @click="closeAllMenus">DOOPNEWS</Link>
                <Link :href="route('admin.creations.queue')" class="dropdown-item" @click="closeAllMenus">CREATION REVIEW</Link>
                <Link :href="route('admin.task-banks.index')" class="dropdown-item" @click="closeAllMenus">TASK BANK</Link>
                <Link v-if="isAdminAccess" :href="route('admin.daily-quest-definitions.index')" class="dropdown-item" @click="closeAllMenus">DAILY QUEST</Link>
                <Link :href="route('admin.rubrics.index')" class="dropdown-item" @click="closeAllMenus">RUBRICS</Link>
                <Link v-if="isAdminAccess" :href="route('admin.jobs.index')" class="dropdown-item" @click="closeAllMenus">JOBS</Link>
                <Link v-if="isAdminAccess" :href="route('admin.shop-items.index')" class="dropdown-item" @click="closeAllMenus">SHOP ITEMS</Link>
                <Link v-if="isAdminAccess" :href="route('admin.profile-skins.index')" class="dropdown-item" @click="closeAllMenus">PROFILE SKINS</Link>
                <Link v-if="isAdminAccess || isMentor" :href="route('groups.manage')" class="dropdown-item" @click="closeAllMenus">STUDY_GROUP</Link>
                <Link v-if="isAdminAccess" :href="route('admin.submissions.manage.index')" class="dropdown-item" @click="closeAllMenus">SUBMISSIONS</Link>
                <Link v-if="isAdminAccess" :href="route('admin.users.index')" class="dropdown-item" @click="closeAllMenus">USERS</Link>
                <Link v-if="isAdminAccess" :href="route('admin.error-logs.index')" class="dropdown-item" @click="closeAllMenus">ERROR LOGS</Link>
                <button type="button" @click="handleLogout" class="dropdown-item w-full text-left text-red-400 hover:text-white">
                    DISCONNECT
                </button>
            </div>
        </div>
    </nav>
</template>

<style scoped>
.admin-nav {
    @apply rounded-2xl border border-[#dce7e8] bg-white px-5 py-4;
    box-shadow: 0 8px 22px rgba(22, 66, 72, .05);
}

.nav-item {
    @apply text-xs tracking-normal font-semibold py-1 text-[#526d72];
}

.dropdown-item {
    @apply block w-full rounded-lg px-3 py-2 text-xs text-[#526d72] hover:bg-[#eef7f6] hover:text-[#007f78] transition-colors;
}

/* Garis pemisah estetik di samping navigasi */
@media (min-width: 768px) {
    .nav-item:not(:last-child) {
        @apply pr-4;
    }
}
</style>
