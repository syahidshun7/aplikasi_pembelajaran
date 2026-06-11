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
            router.post(route('logout'));
        }
    });
};
</script>

<template>
    <nav class="rpg-panel relative z-[120] overflow-visible mb-6 md:mb-8 flex justify-between items-center gap-3 border-indigo-500/50 bg-[#1a1c2c]/90 backdrop-blur-md">
        
        <div class="flex items-center gap-3 min-w-0">
            <div class="flex flex-col">
                <div class="flex items-center gap-2">
                    <span class="w-3 h-3 bg-green-500 animate-pulse rounded-full shadow-[0_0_8px_#22c55e]"></span>
                    <h1 class="text-white text-[11px] sm:text-sm uppercase tracking-widest">
                        Admin_Console <span class="text-indigo-400">v2.0</span>
                    </h1>
                </div>
                <p class="hidden sm:block text-[8px] text-slate-500 mt-1 uppercase">
                    Operator: <span class="text-cyan-400">{{ authUser?.name || '-' }}</span> | Role: <span class="text-indigo-400">{{ roleLabel }}</span>
                </p>
            </div>
        </div>

        <button
            type="button"
            class="md:hidden inline-flex items-center justify-center w-10 h-10 border-2 border-slate-600 bg-slate-900/70 text-cyan-300"
            @click="mobileMenuOpen = !mobileMenuOpen"
            :aria-expanded="mobileMenuOpen ? 'true' : 'false'"
            aria-label="Toggle admin menu"
        >
            <i :class="mobileMenuOpen ? 'fi fi-rr-cross-small' : 'fi fi-rr-menu-burger'" class="text-[14px]"></i>
        </button>

        <div class="hidden md:flex flex-wrap justify-center items-center gap-3 md:gap-4">
            <Link :href="route('lobby')" 
                  class="nav-item hover:text-white transition-colors">
                USER_DASHBOARD
            </Link>
             <Link :href="route('dashboard')"
                  class="nav-item hover:text-indigo-300 transition-colors">
                ADMIN_DASHBOARD
            </Link>

            <NotificationBell variant="admin" />

            <div class="relative z-[130]">
                <button
                    type="button"
                    class="nav-item cursor-pointer list-none border border-slate-600 px-3 py-1 hover:border-cyan-400 hover:text-cyan-300"
                    @click="desktopMenuOpen = !desktopMenuOpen"
                >
                    MENU
                </button>
                <div v-if="desktopMenuOpen" class="absolute right-0 mt-2 min-w-[220px] bg-[#0f101a] border-2 border-slate-700 shadow-xl p-2 z-[140]">
                    <Link href="/admin/materi" class="dropdown-item" @click="closeAllMenus">GUIDE</Link>
                    <Link :href="route('quests.index')" class="dropdown-item" @click="closeAllMenus">QUEST</Link>
                    <Link :href="route('admin.events.index')" class="dropdown-item" @click="closeAllMenus">EVENTS</Link>
                    <Link :href="route('admin.creations.queue')" class="dropdown-item" @click="closeAllMenus">CREATION REVIEW</Link>
                    <Link :href="route('admin.task-banks.index')" class="dropdown-item" @click="closeAllMenus">TASK BANK</Link>
                    <Link v-if="isAdminAccess" :href="route('admin.daily-quest-definitions.index')" class="dropdown-item" @click="closeAllMenus">DAILY QUEST</Link>
                    <Link :href="route('admin.rubrics.index')" class="dropdown-item" @click="closeAllMenus">RUBRICS</Link>
                    <Link v-if="isAdminAccess" :href="route('admin.jobs.index')" class="dropdown-item" @click="closeAllMenus">JOBS</Link>
                    <Link v-if="isAdminAccess" :href="route('admin.shop-items.index')" class="dropdown-item" @click="closeAllMenus">SHOP ITEMS</Link>
                    <Link v-if="isAdminAccess" :href="route('admin.profile-skins.index')" class="dropdown-item" @click="closeAllMenus">PROFILE SKINS</Link>
                    <Link v-if="isAdminAccess" :href="route('groups.manage')" class="dropdown-item" @click="closeAllMenus">STUDY_GROUP</Link>
                    <Link v-if="isAdminAccess" :href="route('admin.submissions.manage.index')" class="dropdown-item" @click="closeAllMenus">SUBMISSIONS</Link>
                    <Link v-if="isAdminAccess" :href="route('admin.users.index')" class="dropdown-item" @click="closeAllMenus">USERS</Link>
                    <Link v-if="isAdminAccess" :href="route('admin.error-logs.index')" class="dropdown-item" @click="closeAllMenus">ERROR LOGS</Link>
                    <button @click="handleLogout" class="dropdown-item w-full text-left text-red-400 hover:text-white">
                        DISCONNECT
                    </button>
                </div>
            </div>
        </div>

        <div
            v-if="mobileMenuOpen"
            class="md:hidden absolute left-0 right-0 top-[calc(100%+8px)] bg-[#0f101a] border-2 border-slate-700 shadow-xl p-2 z-[140] mx-3"
        >
            <div class="grid grid-cols-1 gap-2">
                <Link :href="route('lobby')" class="dropdown-item" @click="closeAllMenus">USER_DASHBOARD</Link>
                <Link :href="route('dashboard')" class="dropdown-item" @click="closeAllMenus">ADMIN_DASHBOARD</Link>
                <Link :href="route('notifications.index')" class="dropdown-item" @click="closeAllMenus">
                    NOTIFICATIONS
                    <span v-if="Number(page.props?.notificationCenter?.unread_count || 0) > 0" class="ml-2 text-cyan-300">
                        [{{ Number(page.props?.notificationCenter?.unread_count || 0) }}]
                    </span>
                </Link>
                <Link href="/admin/materi" class="dropdown-item" @click="closeAllMenus">GUIDE</Link>
                <Link :href="route('quests.index')" class="dropdown-item" @click="closeAllMenus">QUEST</Link>
                <Link :href="route('admin.events.index')" class="dropdown-item" @click="closeAllMenus">EVENTS</Link>
                <Link :href="route('admin.creations.queue')" class="dropdown-item" @click="closeAllMenus">CREATION REVIEW</Link>
                <Link :href="route('admin.task-banks.index')" class="dropdown-item" @click="closeAllMenus">TASK BANK</Link>
                <Link v-if="isAdminAccess" :href="route('admin.daily-quest-definitions.index')" class="dropdown-item" @click="closeAllMenus">DAILY QUEST</Link>
                <Link :href="route('admin.rubrics.index')" class="dropdown-item" @click="closeAllMenus">RUBRICS</Link>
                <Link v-if="isAdminAccess" :href="route('admin.jobs.index')" class="dropdown-item" @click="closeAllMenus">JOBS</Link>
                <Link v-if="isAdminAccess" :href="route('admin.shop-items.index')" class="dropdown-item" @click="closeAllMenus">SHOP ITEMS</Link>
                <Link v-if="isAdminAccess" :href="route('admin.profile-skins.index')" class="dropdown-item" @click="closeAllMenus">PROFILE SKINS</Link>
                <Link v-if="isAdminAccess" :href="route('groups.manage')" class="dropdown-item" @click="closeAllMenus">STUDY_GROUP</Link>
                <Link v-if="isAdminAccess" :href="route('admin.submissions.manage.index')" class="dropdown-item" @click="closeAllMenus">SUBMISSIONS</Link>
                <Link v-if="isAdminAccess" :href="route('admin.users.index')" class="dropdown-item" @click="closeAllMenus">USERS</Link>
                <Link v-if="isAdminAccess" :href="route('admin.error-logs.index')" class="dropdown-item" @click="closeAllMenus">ERROR LOGS</Link>
                <button @click="handleLogout" class="dropdown-item w-full text-left text-red-400 hover:text-white">
                    DISCONNECT
                </button>
            </div>
        </div>
    </nav>
</template>

<style scoped>
.rpg-panel {
    @apply p-5 relative border-4 border-[#3d415f] bg-[#1a1c2c];
    box-shadow: 8px 8px 0px 0px rgba(0, 0, 0, 0.5);
}

.nav-item {
    @apply text-[10px] tracking-tighter uppercase font-bold py-1;
}

.dropdown-item {
    @apply block w-full px-3 py-2 text-[10px] uppercase text-slate-300 hover:bg-slate-800 hover:text-cyan-300 transition-colors;
}

/* Garis pemisah estetik di samping navigasi */
@media (min-width: 768px) {
    .nav-item:not(:last-child) {
        @apply pr-4;
    }
}
</style>
