<script setup>
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { toast } from '@/Utils/Alert';

const props = defineProps({
    players: Array,
    quests: Array,
    materi: Array, // Data dari GuideController
    auth: Object
});

const page = usePage();
const auth = computed(() => page.props.auth);

const players = computed(() => props.players || []);
const quests = computed(() => props.quests || []);
const guides = computed(() => props.materi || []);

const handleLogout = () => {
    toast.confirm('QUIT GAME?', 'Are you sure you want to exit?')
        .then((result) => {
            if (result.isConfirmed) {
                router.post(route('logout'));
            }
        });
};
</script>

<template>

    <Head title="P-QUEST | Game Lobby" />

    <div class="min-h-screen bg-[#0a0c10] bg-cover bg-center bg-no-repeat bg-fixed relative font-['Press_Start_2P']"
        style="background-image: url('/images/bg-loby.png');">

        <div class="absolute inset-0 bg-black/60 backdrop-blur-[1px]"></div>

        <div class="relative z-10 flex flex-col min-h-screen">

            <nav
                class="bg-[#1a1c2c]/90 backdrop-blur-sm border-b-4 border-[#3d415f] p-4 md:px-8 flex justify-between items-center shadow-2xl sticky top-0 z-50">
                <div class="flex items-center gap-4">
                    <Link :href="route('lobby')" class="flex items-center gap-4 group">
                        <div
                            class="w-10 h-10 bg-[#0a0c10] flex items-center justify-center border-b-4 border-r-4 border-[#4ed4d4] overflow-hidden group-hover:scale-110 transition-transform">
                            <img src="/images/logo.png" alt="Logo" class="w-7 h-7 object-contain pixelated">
                        </div>
                        <h1
                            class="text-[#009999] text-[8px] md:text-sm tracking-tighter uppercase group-hover:text-[#4ed4d4]">
                            Lobby_Room_01
                        </h1>
                    </Link>
                </div>

                <div class="flex gap-2 md:gap-4 items-center">
                    <template v-if="auth.user">
                        <Link v-if="auth.user.role === 'admin'" :href="route('admin.dashboard')"
                            class="text-[8px] bg-purple-600/80 text-white px-3 py-2 btn-pixel border-purple-900 uppercase font-bold hover:bg-purple-500 transition-colors">
                            Admin
                        </Link>

                        <Link :href="route('profile.edit')"
                            class="text-[8px] bg-[#3d415f]/80 text-white px-3 py-2 btn-pixel border-[#1a1c2c] uppercase font-bold hover:bg-slate-600 transition-colors">
                            Profile
                        </Link>

                        <button @click="handleLogout"
                            class="text-[8px] bg-red-900/80 text-white px-3 py-2 btn-pixel border-red-950 uppercase font-bold hover:bg-red-700 transition-colors">
                            [X]
                        </button>
                    </template>

                    <template v-else>
                        <Link :href="route('login')"
                            class="text-[8px] bg-[#009999] text-black px-4 py-2 btn-pixel border-[#006666] uppercase font-bold hover:bg-[#4ed4d4] transition-all">
                            Login
                        </Link>
                        <Link :href="route('register')"
                            class="text-[8px] bg-[#facc15] text-black px-4 py-2 btn-pixel border-[#854d0e] uppercase font-bold hover:bg-yellow-400 transition-all">
                            Register
                        </Link>
                    </template>
                </div>
            </nav>

            <main class="p-4 md:p-8 grid grid-cols-12 gap-8 flex-1">

                <div class="col-span-12 lg:col-span-4 space-y-6">



                    <div
                        class="rpg-panel border-indigo-500/50 h-[380px] flex flex-col bg-[#1a1c2c]/90 backdrop-blur-sm relative">
                        <div
                            class="flex justify-between items-start mb-4 border-b border-indigo-900 pb-2 flex-shrink-0">
                            <h2
                                class="text-indigo-400 text-[10px] uppercase tracking-widest flex items-center gap-2 font-['Press_Start_2P']">
                                Materi Ajar
                            </h2>

                            <div
                                class="bg-indigo-900/40 p-2 border-b-4 border-r-4 border-indigo-500 shadow-[3px_3px_0px_0px_rgba(0,0,0,0.5)] flex items-center justify-center">
                                <span class="text-2xl leading-none">📚</span>
                            </div>
                        </div>

                        <div class="space-y-4 overflow-y-auto pr-2 custom-scroll-indigo flex-1">
                            <div v-for="item in guides" :key="item.uuid"
                                class="p-0 bg-[#0d1117] border-2 border-slate-800 hover:border-indigo-500 transition-all group relative overflow-hidden">

                                <div class="absolute top-0 left-0 w-1 h-full bg-indigo-600"></div>

                                <div class="p-3 pl-5 relative">
                                    <div class="flex justify-between items-center mb-1">
                                        <span
                                            class="text-[5px] text-indigo-400 font-['Press_Start_2P'] uppercase tracking-tighter">[
                                            STUDY_MATERIAL ]</span>
                                        <span class="text-[5px] text-slate-600 font-mono italic uppercase">Ref.{{
                                            item.uuid.substring(0,5) }}</span>
                                    </div>

                                    <h3
                                        class="text-[14px] font-sans font-extrabold text-white uppercase tracking-tight group-hover:text-indigo-300 leading-tight mb-1">
                                        {{ item.title }}
                                    </h3>

                                    <p
                                        class="text-[10px] font-sans text-slate-500 italic mb-4 line-clamp-2 leading-snug">
                                        {{ item.description || 'Accessing knowledge database...' }}
                                    </p>

                                    <div class="flex justify-between items-center border-t border-slate-800/50 pt-2.5">
                                        <div class="flex items-center gap-1">
                                            <span class="w-1 h-1 bg-green-500 rounded-full animate-pulse"></span>
                                            <span
                                                class="text-[5px] text-slate-500 font-['Press_Start_2P'] uppercase">Verified</span>
                                        </div>

                                        <a v-if="item.file_path" :href="'/storage/' + item.file_path" target="_blank"
                                            class="text-[7px] bg-[#1a1c2c] text-indigo-300 px-3 py-1.5 border-b-2 border-r-2 border-indigo-900 hover:bg-indigo-500 hover:text-white transition-all uppercase font-['Press_Start_2P']">
                                            LEARN
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <div v-if="guides.length === 0" class="text-center py-8">
                                <p
                                    class="text-slate-700 text-[8px] uppercase font-['Press_Start_2P'] italic tracking-tighter">
                                    Database_Empty</p>
                            </div>
                        </div>


                    </div>

                    <div class="rpg-panel border-[#3d415f] h-[350px] flex flex-col bg-[#1a1c2c]/90 backdrop-blur-sm">
                        <h2
                            class="text-[#4ed4d4] text-[10px] mb-4 flex items-center gap-2 border-b border-slate-700 pb-2 flex-shrink-0 uppercase">
                            <span>●</span> Players[{{ players.length }}]
                        </h2>
                        <div class="space-y-4 overflow-y-auto pr-2 custom-scroll flex-1">
                            <div v-for="player in players" :key="player.id"
                                class="flex items-center gap-4 p-2 hover:bg-[#009999]/10 border-l-4 border-transparent hover:border-[#009999] transition-all">
                                <div class="w-10 h-10 bg-slate-800 border-2 border-slate-600 flex-shrink-0">
                                    <img :src="`https://api.dicebear.com/7.x/pixel-art/svg?seed=${player.name}`"
                                        class="w-full h-full">
                                </div>
                                <div class="flex-1">
                                    <div class="flex justify-between text-[8px] font-sans font-bold">
                                        <span class="text-[14px] text-white uppercase">{{ player.name }}</span>
                                        <span class="text-[#009999]">LVL.{{ player.lvl || 1 }}</span>
                                    </div>
                                    <p class="text-[6px] text-slate-400 mt-1 uppercase">{{ player.job || 'Adventurer' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-span-12 lg:col-span-8">
                    <div class="rpg-panel h-[745px] flex flex-col bg-[#1a1c2c]/90 backdrop-blur-sm border-[#3d415f]">
                        <div
                            class="flex justify-between items-center mb-6 border-b border-slate-700 pb-4 flex-shrink-0">
                            <h2 class="text-yellow-400 text-xs uppercase tracking-widest animate-pulse">Available_Quests
                            </h2>
                            <span class="text-[8px] text-slate-500 uppercase">Sort: Difficulty ▲</span>
                        </div>

                        <div class="overflow-y-auto pr-2 custom-scroll flex-1">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pb-4">
                                <div v-for="quest in quests" :key="quest.uuid"
                                    class="rpg-panel bg-[#161b22] transition-all group cursor-pointer shadow-none flex flex-col h-[200px]"
                                    :class="[
                                        (quest.status === 'Done' && !quest.user_has_submitted) ? 'border-red-600 shadow-[0_0_10px_rgba(220,38,38,0.2)]' :
                                            (quest.status === 'In-Progress') ? 'border-slate-500 bg-slate-900/50' :
                                                quest.user_has_submitted ? 'border-yellow-600 shadow-[0_0_10px_rgba(202,138,4,0.2)]' : 'border-slate-700 hover:border-[#009999]'
                                    ]">

                                    <div class="flex flex-col h-full">
                                        <div class="flex justify-between items-start mb-3">
                                            <span
                                                class="text-[7px] px-2 py-1 bg-slate-800 text-slate-400 border border-slate-600 uppercase">ID:{{
                                                quest.id }}</span>
                                            <span :class="{
                                                'text-red-500': quest.difficulty === 'S-Rank',
                                                'text-orange-500': quest.difficulty === 'A-Rank',
                                                'text-cyan-500': quest.difficulty === 'B-Rank',
                                                'text-green-500': quest.difficulty === 'C-Rank'
                                            }" class="text-[8px] font-bold tracking-widest">{{ quest.difficulty
                                                }}</span>
                                        </div>

                                        <h3
                                            class="text-[10px] text-white group-hover:text-[#4ed4d4] leading-relaxed transition-colors uppercase line-clamp-3 mb-2">
                                            {{ quest.title }}
                                        </h3>

                                        <div class="flex-grow">
                                            <p v-if="quest.status === 'Done' && !quest.user_has_submitted"
                                                class="text-[6px] text-red-500 uppercase">Mission_Expired</p>
                                            <p v-if="quest.status === 'In-Progress'"
                                                class="text-[6px] text-slate-500 uppercase italic tracking-widest">
                                                Active_In_Journal...</p>
                                        </div>

                                        <div class="pt-4 flex justify-between items-center border-t border-slate-800">
                                            <div class="flex flex-col">
                                                <span class="text-[6px] text-slate-500 uppercase mb-1">Reward</span>
                                                <span class="text-yellow-500 text-[8px] tracking-tighter font-bold">{{
                                                    quest.reward_gold }}G</span>
                                            </div>

                                            <template v-if="quest.status !== 'In-Progress'">
                                                <Link :href="route('quests.show', quest.uuid)" :class="[
                                                    'text-[8px] px-3 py-2 btn-pixel uppercase font-bold transition-colors whitespace-nowrap',
                                                    (quest.status === 'Done' && !quest.user_has_submitted) ? 'bg-red-700 text-white border-red-950 hover:bg-red-600' :
                                                        quest.user_has_submitted ? 'bg-yellow-600 text-black border-yellow-800 hover:bg-yellow-400' :
                                                            'bg-[#009999] text-black border-[#006666] hover:bg-[#4ed4d4]'
                                                ]">
                                                    <template
                                                        v-if="quest.status === 'Done' && !quest.user_has_submitted">Late</template>
                                                    <template v-else>{{ quest.user_has_submitted ? 'Re-Take' :
                                                        'Take_Quest' }}</template>
                                                </Link>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </main>

            <footer class="p-8 text-center bg-[#1a1c2c]/50 backdrop-blur-md border-t-2 border-white/10 mt-auto">
                <p class="text-[8px] text-white/50 uppercase tracking-[0.3em]">Build_Ver_1.1.0 // P-Quest Engine</p>
            </footer>

        </div>
    </div>
</template>

<style scoped>
.rpg-panel {
    @apply border-4 p-4;
    box-shadow: 8px 8px 0px 0px rgba(0, 0, 0, 0.5);
}

.btn-pixel {
    @apply border-b-4 border-r-4 transition-all active:translate-y-1 active:translate-x-1 active:border-0;
}

.pixelated {
    image-rendering: pixelated;
}

/* Scrollbars */
.custom-scroll {
    scrollbar-width: thin;
    scrollbar-color: #009999 #0d1117;
}

.custom-scroll::-webkit-scrollbar {
    width: 4px;
}

.custom-scroll::-webkit-scrollbar-thumb {
    background: #009999;
}

.custom-scroll-indigo {
    scrollbar-width: thin;
    scrollbar-color: #6366f1 #0d1117;
}

.custom-scroll-indigo::-webkit-scrollbar {
    width: 4px;
}

.custom-scroll-indigo::-webkit-scrollbar-thumb {
    background: #4338ca;
}
</style>