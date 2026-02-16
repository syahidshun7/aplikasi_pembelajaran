<script setup>
import { Head, usePage, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import Swal from 'sweetalert2';

const auth = computed(() => page.props.auth);

// Definisikan fungsi logout
const handleLogout = () => {
    Swal.fire({
        title: 'LEAVING THE GUILD?',
        text: "Your session will be closed.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'YES, LOGOUT',
        cancelButtonText: 'STAY HERE',
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

const page = usePage();

// DATA DUMMY (Tetap sebagai fallback)
const defaultHero = {
    name: "Adventurer",
    gold: 0,
    level: 1,
    exp: 10,
    str: 5,
    int: 5
};

const defaultQuests = [
    { id: 1, title: "Connecting to Realm", xp: "Low", status: "Wait" },
    { id: 2, title: "Loading Assets", xp: "Low", status: "Wait" }
];

const defaultLogs = [
    "System initializing...",
    "Waiting for server response..."
];

// PROPS: Mengambil data asli dari Laravel jika ada
const hero = computed(() => {
    return {
        ...defaultHero,
        name: page.props.auth?.user?.name || defaultHero.name,
        // Jika Anda mengirim data 'hero' dari DashboardController, akan masuk ke sini
        ...(page.props.hero || {})
    };
});

// Mengambil data quest asli dari database yang dikirim lewat controller
const quests = computed(() => page.props.quests || defaultQuests);
const logs = computed(() => page.props.logs || defaultLogs);

</script>

<template>

    <Head title="DASHBOARD | P-QUEST" />

    <div
        class="min-h-screen bg-[#0f101a] p-4 md:p-8 font-['Press_Start_2P'] text-[#4ed4d4] text-[10px] leading-relaxed">

        <div class="rpg-panel mb-6 flex flex-col md:flex-row items-center gap-6 border-cyan-500/50 relative">
            <div class="w-20 h-20 border-4 border-cyan-400 bg-slate-800 shadow-[0_0_15px_rgba(78,212,212,0.3)]">
                <img src="https://api.dicebear.com/7.x/pixel-art/svg?seed=Adventurer"
                    class="w-full h-full object-cover">
            </div>

            <div class="flex-1 w-full space-y-3">
                <div class="flex justify-between items-center">
                    <span class="text-white text-xs">Hero Name: {{ hero.name }}</span>
                    <span class="text-yellow-400">GOLD: {{ hero.gold }} G</span>
                </div>

                <div class="flex items-center gap-4">
                    <span class="whitespace-nowrap">LVL: {{ hero.level }}</span>
                    <div class="flex-1 h-5 bg-black border-2 border-slate-700 overflow-hidden">
                        <div class="xp-bar-fill h-full transition-all duration-1000 bg-cyan-400"
                            :style="{ width: hero.exp + '%' }"></div>
                    </div>
                </div>

                <div class="flex justify-between items-end">
                    <div class="flex gap-10 text-slate-400 text-[8px]">
                        <span>STR: {{ hero.str }}</span>
                        <span>INT: {{ hero.int }}</span>
                        <span class="text-cyan-500 uppercase">Status: Online</span>
                    </div>

                    <button @click="handleLogout" type="button"
                        class="text-[10px] bg-red-900/20 border-2 border-red-600 p-2 text-red-500 hover:bg-red-600 hover:text-white transition-all btn-pixel">
                        [X] DISCONNECT_SESSION
                    </button>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-12 gap-6">
            <div class="col-span-12 lg:col-span-3 rpg-panel flex flex-col gap-6">
                <h2 class="text-center text-white border-b-2 border-slate-700 pb-2 uppercase">Menu</h2>
                <nav class="space-y-2">
                    <Link :href="route('quests.index')"
                        class="p-2 bg-cyan-900/30 border-r-4 border-cyan-400 flex justify-between cursor-pointer hover:bg-cyan-400 hover:text-black transition-all">
                        <span>Quests</span> <span>▶</span>
                    </Link>
                    <div class="p-2 opacity-30 cursor-not-allowed uppercase">Battle Arena</div>
                    <div class="p-2 opacity-30 cursor-not-allowed uppercase">Inventory</div>
                    <div class="p-2 opacity-30 cursor-not-allowed uppercase">Options</div>
                </nav>
            </div>

            <div class="col-span-12 lg:col-span-9 flex flex-col gap-6">
                <div class="rpg-panel flex-1 min-h-[300px]">
                    <h2 class="text-cyan-400 mb-6 flex items-center gap-2 uppercase tracking-tighter">
                        <span class="animate-pulse">●</span> Active Quests (Tasks)
                    </h2>
                    <div class="space-y-4">
                        <div v-for="q in quests" :key="q.id"
                            class="flex justify-between items-center p-3 border-b border-slate-800 hover:bg-white/5 transition-colors">
                            <span
                                :class="q.difficulty === 'A-Rank' || q.difficulty === 'S-Rank' ? 'text-red-400' : 'text-cyan-400'">
                                {{ q.difficulty === 'S-Rank' ? '⚔' : '⚓' }} {{ q.title }}
                                <span class="text-[8px] opacity-50 ml-2">[{{ q.difficulty }}]</span>
                            </span>

                            <Link :href="route('quests.show', q.id)" class="btn-rpg text-[8px]"
                                :class="q.status === 'Completed' ? 'bg-green-600 border-green-800 text-white' : ''">
                                {{ q.status || 'VIEW' }}
                            </Link>
                        </div>
                        <p v-if="quests.length === 0" class="text-slate-600 italic">No quests available in the board...
                        </p>
                    </div>
                </div>

                <div class="rpg-panel h-32 overflow-y-auto border-slate-600">
                    <h2 class="text-white text-[8px] mb-2 uppercase opacity-50">Battle Log</h2>
                    <div class="space-y-1 text-[8px] text-slate-300">
                        <p v-for="(log, i) in logs" :key="i" class="animate-in fade-in slide-in-from-left duration-500">
                            > {{ log }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.xp-bar-fill {
    background: repeating-linear-gradient(90deg,
            #4ed4d4,
            #4ed4d4 10px,
            #2a7a7a 10px,
            #2a7a7a 12px);
}

/* PERBAIKAN: Hapus spasi di dalam rgba agar Tailwind JIT tidak error */
.rpg-panel {
    /* Gunakan @apply untuk warna dan border saja */
    @apply bg-[#1a1c2c] border-4 border-[#3d415f] p-4 relative;
    
    /* Gunakan CSS murni untuk shadow agar tidak bentrok dengan compiler Tailwind */
    box-shadow: 8px 8px 0px 0px rgba(0, 0, 0, 0.5);
}

.btn-rpg {
    @apply px-3 py-1 bg-[#facc15] text-black border-b-4 border-r-4 border-[#854d0e] 
           font-bold active:border-0 active:translate-y-1 active:translate-x-1 
           transition-all uppercase text-[10px] inline-block;
}

/* Tambahan: Efek Hover untuk tombol agar lebih "gamey" */
.btn-rpg:hover {
    @apply bg-yellow-400;
}
/* Animasi tambahan */
.btn-pixel:active {
    box-shadow: none;
    transform: translate(2px, 2px);
}
</style>