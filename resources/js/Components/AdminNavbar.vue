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
    <nav class="rpg-panel mb-8 flex flex-col md:flex-row justify-between items-center gap-4 border-indigo-500/50 bg-[#1a1c2c]/90 backdrop-blur-md">
        
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

        <div class="flex flex-wrap justify-center gap-4 md:gap-8">
            <Link :href="route('lobby')" 
                  class="nav-item hover:text-white transition-colors"
                  :class="{ 'text-indigo-400 border-b-2 border-indigo-400': $page.component === 'Dashboard' }">
                DASHBOARD USER
            </Link>
            
            <Link href="/admin/materi" 
                  class="nav-item hover:text-yellow-400 transition-colors">
                GUIDE
            </Link>

            <Link :href="route('quests.index')" 
                  class="nav-item hover:text-cyan-400 transition-colors">
                QUEST
            </Link>
             <Link :href="route('groups.manage')" 
                  class="nav-item hover:text-cyan-400 transition-colors">
                STUDY_GROUP
            </Link>

            <button @click="handleLogout" 
                    class="nav-item text-red-500 hover:bg-red-500/10 px-2 transition-all border border-transparent hover:border-red-500/50">
                DISCONNECT
            </button>
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

/* Garis pemisah estetik di samping navigasi */
@media (min-width: 768px) {
    .nav-item:not(:last-child) {
        @apply pr-4;
    }
}
</style>