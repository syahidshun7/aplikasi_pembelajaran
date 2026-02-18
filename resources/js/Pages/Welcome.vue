<script setup>
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import Swal from 'sweetalert2';

const page = usePage();
const auth = computed(() => page.props.auth);
const route = window.route;
const players = computed(() => page.props.players || []);
const quests = computed(() => page.props.quests || []);


// Fitur Take Quest (Koneksi ke Backend)
const takeQuest = (questId) => {
    router.post(route('quests.take', questId), {}, {
        onSuccess: () => {
            Swal.fire({
                title: 'SUCCESS!',
                text: 'Quest has been added to your log.',
                icon: 'success',
                background: '#1a1c2c',
                color: '#4ed4d4',
            });
        }
    });
};

const handleLogout = () => {
    Swal.fire({
        title: 'QUIT GAME?',
        text: "Are you sure you want to return to the real world?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'YES, LOGOUT',
        cancelButtonText: 'CANCEL',
        background: '#1a1c2c',
        color: '#4ed4d4',
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        customClass: {
            popup: 'border-4 border-[#3d415f] font-mono',
            title: 'text-red-500 uppercase',
        }
    }).then((result) => {
        if (result.isConfirmed) {
            router.post(route('logout'));
        }
    });
};
</script>

<template>

    <Head title="P-QUEST | Game Lobby" />

    <div class="min-h-screen bg-cover bg-center bg-no-repeat bg-fixed relative font-['Press_Start_2P']"
        style="background-image: url('/images/bg-loby.png');">

        <div class="absolute inset-0 bg-black/50 backdrop-blur-[1px]"></div>

        <div class="relative z-10 flex flex-col min-h-screen">

            <nav
                class="bg-[#1a1c2c] border-b-4 border-[#3d415f] p-4 md:px-8 flex justify-between items-center shadow-2xl">
                <div class="flex items-center gap-4">
                    <div
                        class="w-10 h-10 bg-[#0a0c10] flex items-center justify-center border-b-4 border-r-4 border-[#4ed4d4] overflow-hidden">
                        <img src="images/logo.png" alt="Logo" class="w-7 h-7 object-contain pixelated">
                    </div>
                    <h1 class="text-[#009999] text-sm md:text-xl tracking-tighter uppercase">Lobby_Room_01</h1>
                </div>

                <div class="flex gap-4">
                    <template v-if="!auth.user">
                        <Link :href="route('register')"
                            class="text-[8px] bg-[#facc15] text-black px-4 py-2 btn-pixel border-[#854d0e] uppercase font-bold hover:bg-yellow-400 transition-colors">
                            Register
                        </Link>
                        <Link :href="route('login')"
                            class="text-[8px] bg-[#009999] text-black px-4 py-2 btn-pixel border-[#006666] uppercase font-bold hover:bg-[#4ed4d4] transition-colors">
                            Login
                        </Link>
                    </template>

                    <template v-else>
                        <Link v-if="auth.user.role === 'admin'" :href="route('dashboard')"
                            class="text-[8px] bg-purple-600 text-white px-4 py-2 btn-pixel border-purple-900 uppercase font-bold hover:bg-purple-500 transition-colors">
                            Admin_Panel
                        </Link>
                        <Link :href="route('profile.edit')"
                            class="text-[8px] bg-[#3d415f] text-white px-4 py-2 btn-pixel border-[#1a1c2c] uppercase font-bold hover:bg-slate-600 transition-colors">
                            Profile
                        </Link>
                        <button @click="handleLogout"
                            class="text-[8px] bg-red-900 text-white px-4 py-2 btn-pixel border-red-950 uppercase font-bold hover:bg-red-700 transition-colors">
                            Logout [X]
                        </button>
                    </template>
                </div>
            </nav>

            <main class="p-4 md:p-8 grid grid-cols-12 gap-8 flex-1">

                <div class="col-span-12 lg:col-span-4">
                    <div class="rpg-panel border-[#3d415f] h-[550px] flex flex-col bg-[#1a1c2c]/90 backdrop-blur-sm">
                        <h2
                            class="text-[#4ed4d4] text-[10px] mb-6 flex items-center gap-2 border-b border-slate-700 pb-2 flex-shrink-0 uppercase">
                            <span>●</span> Players_Online [{{ players.length }}]
                        </h2>

                        <div class="space-y-4 overflow-y-auto pr-2 custom-scroll">
                            <div v-for="player in players" :key="player.id"
                                class="flex items-center gap-4 p-2 hover:bg-[#009999]/10 border-l-4 border-transparent hover:border-[#009999] transition-all">
                                <div class="w-10 h-10 bg-slate-800 border-2 border-slate-600 flex-shrink-0">
                                    <img :src="`https://api.dicebear.com/7.x/pixel-art/svg?seed=${player.name}`"
                                        class="w-full h-full">
                                </div>
                                <div class="flex-1">
                                    <div class="flex justify-between text-[8px]">
                                        <span class="text-white uppercase">{{ player.name }}</span>
                                        <span class="text-[#009999]">LVL.{{ player.lvl }}</span>
                                    </div>
                                    <p class="text-[6px] text-slate-400 mt-1 uppercase">{{ player.job }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-span-12 lg:col-span-8">
                    <div class="rpg-panel h-[600px] flex flex-col bg-[#1a1c2c]/90 backdrop-blur-sm">
                        <div
                            class="flex justify-between items-center mb-6 border-b border-slate-700 pb-4 flex-shrink-0">
                            <h2 class="text-yellow-400 text-xs uppercase tracking-widest animate-pulse">
                                Available_Quests
                            </h2>
                            <span class="text-[8px] text-slate-500 uppercase">Sort: Difficulty ▲</span>
                        </div>

                        <div class="overflow-y-auto pr-2 custom-scroll flex-1">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pb-4">
                                <div v-for="quest in quests" :key="quest.id"
                                    class="rpg-panel bg-[#161b22] transition-all group cursor-pointer h-fit shadow-none"
                                    :class="[
                                        quest.user_has_submitted
                                            ? 'border-yellow-600 shadow-[0_0_10px_rgba(202,138,4,0.2)]'
                                            : 'border-slate-700 hover:border-[#009999]'
                                    ]">
                                    <div class="flex flex-col h-full gap-4">
                                        <div class="flex justify-between items-start">
                                            <span
                                                class="text-[7px] px-2 py-1 bg-slate-800 text-slate-400 border border-slate-600">
                                                ID:{{ quest.id }}
                                            </span>

                                            

                                            <span :class="{
                                                'text-red-500': quest.difficulty === 'S-Rank',
                                                'text-orange-500': quest.difficulty === 'A-Rank',
                                                'text-cyan-500': quest.difficulty === 'B-Rank',
                                                'text-green-500': quest.difficulty === 'C-Rank'
                                            }" class="text-[8px] font-bold tracking-widest">{{ quest.difficulty }}</span>
                                        </div>

                                        <h3
                                            class="text-[10px] text-white group-hover:text-[#4ed4d4] leading-relaxed transition-colors uppercase">
                                            {{ quest.title }}
                                        </h3>

                                        <div
                                            class="mt-auto pt-4 flex justify-between items-center border-t border-slate-800">
                                            <span class="text-yellow-500 text-[8px] tracking-tighter">
                                                REWARD: {{ quest.reward_gold }}
                                            </span>

                                            <Link :href="route('quests.show', quest.id)" :class="[
                                                'text-[8px] px-4 py-2 btn-pixel uppercase font-bold transition-colors',
                                                quest.user_has_submitted
                                                    ? 'bg-yellow-600 text-black border-yellow-800 hover:bg-yellow-400'
                                                    : 'bg-[#009999] text-black border-[#006666] hover:bg-[#4ed4d4]'
                                            ]">
                                                {{ quest.user_has_submitted ? 'Re-Take' : 'Take' }}
                                            </Link>
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

.pixelated {
    image-rendering: pixelated;
}
</style>