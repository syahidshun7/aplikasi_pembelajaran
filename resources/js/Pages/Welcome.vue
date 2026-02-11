<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';


// Tambahkan baris ini jika kamu menggunakan Ziggy Laravel
// Jika di setup Laravel kamu sudah ada Ziggy, ini akan memanggilnya
const route = window.route;

// ... data players dan quests kamu tetap di sini ..

// DATA PLAYERS ONLINE (Daftar User)
const players = ref([
    { id: 1, name: "Zoro_Dev", lvl: 42, job: "Swordsman", status: "In-Game" },
    { id: 2, name: "Luffy_Code", lvl: 56, job: "Captain", status: "Lobby" },
    { id: 3, name: "Nami_UI", lvl: 30, job: "Navigator", status: "Lobby" },
    { id: 4, name: "Sanji_Back", lvl: 38, job: "Chef", status: "Away" },
    { id: 1, name: "Zoro_Dev", lvl: 42, job: "Swordsman", status: "In-Game" },
    { id: 2, name: "Luffy_Code", lvl: 56, job: "Captain", status: "Lobby" },
    { id: 3, name: "Nami_UI", lvl: 30, job: "Navigator", status: "Lobby" },
    { id: 4, name: "Sanji_Back", lvl: 38, job: "Chef", status: "Away" },
]);

// DATA MISSION BOARD (List Quest)
const quests = ref([
    { id: 101, title: "Refactor Ancient Monolith", difficulty: "S-Rank", reward: "5000G" },
    { id: 102, title: "Debug CSS Slimes", difficulty: "C-Rank", reward: "200G" },
    { id: 103, title: "Deploy to Production Gates", difficulty: "A-Rank", reward: "1500G" },
    { id: 104, title: "Optimize Query Dungeon", difficulty: "B-Rank", reward: "800G" },
    { id: 101, title: "Refactor Ancient Monolith", difficulty: "S-Rank", reward: "5000G" },
    { id: 102, title: "Debug CSS Slimes", difficulty: "C-Rank", reward: "200G" },
    { id: 103, title: "Deploy to Production Gates", difficulty: "A-Rank", reward: "1500G" },
    { id: 104, title: "Optimize Query Dungeon", difficulty: "B-Rank", reward: "800G" },
    { id: 101, title: "Refactor Ancient Monolith", difficulty: "S-Rank", reward: "5000G" },
    { id: 102, title: "Debug CSS Slimes", difficulty: "C-Rank", reward: "200G" },
    { id: 103, title: "Deploy to Production Gates", difficulty: "A-Rank", reward: "1500G" },
    { id: 104, title: "Optimize Query Dungeon", difficulty: "B-Rank", reward: "800G" },
]);

</script>

<template>

    <Head title="P-QUEST | Game Lobby" />

    <div class="min-h-screen bg-[#0d1117] p-4 md:p-8 font-['Press_Start_2P'] text-[#F7F7F7] selection:bg-[#009999]">

      <nav class="border-b-4 border-[#333333] mb-8 pb-4 flex justify-between items-center">
    <div class="flex items-center gap-4">
        <div class="w-10 h-10 bg-[#0a0c10] flex items-center justify-center border-b-4 border-r-4 border-[#1a1c2c] overflow-hidden">
    <img src="images/logo.png" alt="Logo" class="w-7 h-7 object-contain pixelated">
</div>
        <h1 class="text-[#009999] text-sm md:text-xl tracking-tighter uppercase">Lobby_Room_01</h1>
    </div>

    <div class="flex gap-4">
        <Link :href="route('register')"
            class="text-[8px] bg-[#facc15] text-black px-4 py-2 btn-pixel border-[#854d0e] uppercase font-bold hover:bg-yellow-400 transition-colors">
            Register
        </Link>

        <Link :href="route('login')"
            class="text-[8px] bg-[#009999] text-black px-4 py-2 btn-pixel border-[#006666] uppercase font-bold hover:bg-[#4ed4d4] transition-colors">
            Login
        </Link>
    </div>
</nav>

        <div class="grid grid-cols-12 gap-8">

            <div class="col-span-12 lg:col-span-4 flex flex-col gap-4">
                <div class="rpg-panel border-[#3d415f] h-[550px] flex flex-col">
                    <h2
                        class="text-[#4ed4d4] text-[10px] mb-6 flex items-center gap-2 border-b border-slate-700 pb-2 flex-shrink-0">
                        <span>●</span> PLAYERS_ONLINE [{{ players.length }}]
                    </h2>

                    <div class="space-y-4 overflow-y-auto pr-2 custom-scroll">
                        <div v-for="player in players" :key="player.id"
                            class="flex items-center gap-4 p-2 hover:bg-[#009999]/10 border-l-4 border-transparent hover:border-[#009999]">
                            <div class="w-10 h-10 bg-slate-800 border-2 border-slate-600 flex-shrink-0">
                                <img :src="`https://api.dicebear.com/7.x/pixel-art/svg?seed=${player.name}`"
                                    class="w-full h-full">
                            </div>
                            <div class="flex-1">
                                <div class="flex justify-between text-[8px]">
                                    <span class="text-white">{{ player.name }}</span>
                                    <span class="text-[#009999]">LVL.{{ player.lvl }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-span-12 lg:col-span-8">
    <div class="rpg-panel h-[600px] flex flex-col">
        
        <div class="flex justify-between items-center mb-6 border-b border-slate-700 pb-4 flex-shrink-0">
            <h2 class="text-yellow-400 text-xs uppercase tracking-widest animate-pulse">
                Available_Quests
            </h2>
            <span class="text-[8px] text-slate-500 uppercase">Sort: Difficulty ▲</span>
        </div>

        <div class="overflow-y-auto pr-2 custom-scroll flex-1">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div v-for="quest in quests" :key="quest.id"
                    class="rpg-panel bg-[#161b22] border-slate-700 hover:border-[#009999] transition-all group cursor-pointer h-fit">
                    <div class="flex flex-col h-full gap-4">
                        <div class="flex justify-between items-start">
                            <span class="text-[7px] px-2 py-1 bg-slate-800 text-slate-400 border border-slate-600">
                                ID:{{ quest.id }}
                            </span>
                            <span :class="{
                                'text-red-500': quest.difficulty === 'S-Rank',
                                'text-orange-500': quest.difficulty === 'A-Rank',
                                'text-cyan-500': quest.difficulty === 'B-Rank',
                                'text-green-500': quest.difficulty === 'C-Rank'
                            }" class="text-[8px] font-bold tracking-widest">{{ quest.difficulty }}</span>
                        </div>

                        <h3 class="text-[10px] text-white group-hover:text-[#4ed4d4] leading-relaxed transition-colors uppercase">
                            {{ quest.title }}
                        </h3>

                        <div class="mt-auto pt-4 flex justify-between items-center border-t border-slate-800">
                            <span class="text-yellow-500 text-[8px] tracking-tighter">
                                REWARD: {{ quest.reward }}
                            </span>
                            <button class="text-[7px] text-black bg-[#4ed4d4] px-3 py-1 btn-pixel border-[#2da1a1] uppercase opacity-80 group-hover:opacity-100">
                                Take
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div> </div>
</div>

        </div>

        <div class="mt-8 text-center border-t-2 border-[#333333] pt-6 opacity-30">
            <p class="text-[8px] uppercase tracking-[0.3em]">Build_Ver_1.0.9 // P-Quest Engine</p>
        </div>
    </div>
</template>

<style scoped>
.rpg-panel {
    /* Gunakan @apply untuk utility standar */
    @apply bg-[#1a1c2c] border-4 border-[#3d415f] p-4;
    
    /* Gunakan CSS murni untuk shadow agar tidak error di Vite/PostCSS */
    box-shadow: 8px 8px 0px 0px rgba(0,0,0,0.5);
}

.btn-pixel {
    @apply border-b-4 border-r-4 transition-all active:translate-y-1 active:translate-x-1 active:border-0;
}

/* Custom Scrollbar Styles */
.custom-scroll {
    scrollbar-width: thin;
    scrollbar-color: #009999 #0d1117;
}

.custom-scroll::-webkit-scrollbar {
    width: 6px;
}

.custom-scroll::-webkit-scrollbar-track {
    background: #0d1117;
    border-left: 1px solid #333333;
}

.custom-scroll::-webkit-scrollbar-thumb {
    background: #009999;
    border: 1px solid #006666;
}

.custom-scroll::-webkit-scrollbar-thumb:hover {
    background: #4ed4d4;
}
</style>
