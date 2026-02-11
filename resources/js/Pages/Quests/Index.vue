<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';

const props = defineProps({
    quests: Array
});

// Form helper dari Inertia untuk menangani input
const form = useForm({
    title: '',
    difficulty: 'C-Rank',
    reward_gold: 0,
});

const submit = () => {
    form.post(route('quests.store'), {
        onSuccess: () => form.reset(),
    });
};
</script>

<template>
    <Head title="GUILD_BOARD" />

    <div class="min-h-screen bg-[#0d1117] p-4 md:p-8 font-['Press_Start_2P'] text-[#4ed4d4] text-[10px]">
        <div class="max-w-6xl mx-auto space-y-8">
            
            <div class="flex justify-between items-center border-b-4 border-cyan-900 pb-4">
                <h1 class="text-xl uppercase tracking-widest animate-pulse">Quest_Management_System</h1>
                <Link href="/dashboard" class="text-slate-500 hover:text-white transition-colors uppercase">[Back_to_HQ]</Link>
            </div>

            <div class="grid grid-cols-12 gap-8">
                
                <div class="col-span-12 lg:col-span-5">
                    <div class="rpg-panel border-yellow-500/50">
                        <h2 class="text-yellow-500 mb-6 uppercase tracking-tighter">>> ISSUE_NEW_QUEST</h2>
                        
                        <form @submit.prevent="submit" class="space-y-6">
                            <div>
                                <label class="block mb-2 text-white">QUEST_TITLE:</label>
                                <input v-model="form.title" type="text" 
                                    class="w-full bg-black border-2 border-slate-700 p-2 focus:border-cyan-400 outline-none text-cyan-400 font-vt uppercase"
                                    placeholder="Enter quest name..." required>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block mb-2 text-white">DIFFICULTY:</label>
                                    <select v-model="form.difficulty" 
                                        class="w-full bg-black border-2 border-slate-700 p-2 focus:border-cyan-400 outline-none text-yellow-500">
                                        <option>C-Rank</option>
                                        <option>B-Rank</option>
                                        <option>A-Rank</option>
                                        <option>S-Rank</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block mb-2 text-white">GOLD_REWARD:</label>
                                    <input v-model="form.reward_gold" type="number" 
                                        class="w-full bg-black border-2 border-slate-700 p-2 focus:border-cyan-400 outline-none text-yellow-400 font-vt"
                                        required>
                                </div>
                            </div>

                            <button type="submit" :disabled="form.processing"
                                class="w-full py-3 bg-cyan-900/30 border-2 border-cyan-400 text-cyan-400 hover:bg-cyan-400 hover:text-black transition-all uppercase font-bold">
                                {{ form.processing ? 'UPLOADING...' : 'CONFIRM_MISSION' }}
                            </button>
                        </form>
                    </div>
                </div>

                <div class="col-span-12 lg:col-span-7">
                    <div class="rpg-panel border-slate-700 h-full">
                        <h2 class="text-white mb-6 uppercase tracking-tighter">>> ACTIVE_MISSIONS_BOARD</h2>
                        
                        <div v-if="quests.length === 0" class="text-center py-20 text-slate-600 italic">
                            No missions available on the board...
                        </div>

                        <div class="space-y-4">
                            <div v-for="q in quests" :key="q.id" 
                                class="flex justify-between items-center p-4 bg-slate-900/50 border-l-4 border-cyan-500 hover:bg-slate-800 transition-all">
                                <div>
                                    <div class="text-[8px] text-slate-500 mb-1 uppercase">ID: {{ q.id }} // RANK: {{ q.difficulty }}</div>
                                    <div class="text-white uppercase">{{ q.title }}</div>
                                    <div class="text-yellow-500 text-[8px] mt-1">+{{ q.reward_gold }} GOLD</div>
                                </div>
                                <div class="flex gap-2">
                                    <button class="text-red-500 hover:text-white text-[8px] border border-red-900 p-1">ABORT</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</template>

<style scoped>
.rpg-panel {
    @apply bg-[#1a1c2c] border-4 p-6 relative shadow-[8px_8px_0px_0px_rgba(0,0,0,0.5)];
}

input::placeholder {
    @apply text-slate-700;
}

/* Chrome, Safari, Edge, Opera */
input::-webkit-outer-spin-button,
input::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}
</style>