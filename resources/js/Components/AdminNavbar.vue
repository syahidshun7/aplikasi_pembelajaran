<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

const page = usePage();

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
            router.post(route('logout'));
        }
    });
};
</script>

<template>
    <nav class="rpg-panel relative z-[120] overflow-visible mb-8 flex flex-col md:flex-row justify-between items-center gap-4 border-indigo-500/50 bg-[#1a1c2c]/90 backdrop-blur-md">
        
        <div class="flex items-center gap-4">
            <div class="flex flex-col">
                <div class="flex items-center gap-2">
                    <span class="w-3 h-3 bg-green-500 animate-pulse rounded-full shadow-[0_0_8px_#22c55e]"></span>
                    <h1 class="text-white text-sm uppercase tracking-widest">
                        Admin_Console <span class="text-indigo-400">v2.0</span>
                    </h1>
                </div>
                <p class="text-[8px] text-slate-500 mt-1 uppercase">
                    Operator: <span class="text-cyan-400">{{ page.props.auth.user.name }}</span> | Role: <span class="text-indigo-400">Realm_Master</span>
                </p>
            </div>
        </div>

        <div class="flex flex-wrap justify-center items-center gap-3 md:gap-4">
           

            <Link :href="route('lobby')" 
                  class="nav-item hover:text-white transition-colors">
                USER_DASHBOARD
            </Link>
             <Link :href="route('dashboard')"
                  class="nav-item hover:text-indigo-300 transition-colors">
                ADMIN_DASHBOARD
            </Link>

            <details class="relative z-[130]">
                <summary class="nav-item cursor-pointer list-none border border-slate-600 px-3 py-1 hover:border-cyan-400 hover:text-cyan-300">
                    MENU
                </summary>
                <div class="absolute right-0 mt-2 min-w-[220px] bg-[#0f101a] border-2 border-slate-700 shadow-xl p-2 z-[140]">
                    <Link href="/admin/materi" class="dropdown-item">GUIDE</Link>
                    <Link :href="route('quests.index')" class="dropdown-item">QUEST</Link>
                    <Link :href="route('admin.events.index')" class="dropdown-item">EVENTS</Link>
                    <Link :href="route('admin.jobs.index')" class="dropdown-item">JOBS</Link>
                    <Link :href="route('groups.manage')" class="dropdown-item">STUDY_GROUP</Link>
                    <Link :href="route('admin.submissions.manage.index')" class="dropdown-item">SUBMISSIONS</Link>
                    <Link :href="route('admin.users.index')" class="dropdown-item">USERS</Link>
                    <button @click="handleLogout" class="dropdown-item w-full text-left text-red-400 hover:text-white">
                        DISCONNECT
                    </button>
                </div>
            </details>
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
