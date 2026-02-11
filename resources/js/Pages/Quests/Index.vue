<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    quests: Array
});

// State untuk tahu sama ada kita sedang edit atau tambah baru
const isEditing = ref(false);
const editId = ref(null);

const form = useForm({
    title: '',
    difficulty: 'C-Rank',
    reward_gold: 0,
});

// Fungsi untuk isi data ke dalam form apabila klik EDIT
const startEdit = (quest) => {
    isEditing.value = true;
    editId.value = quest.id;
    form.title = quest.title;
    form.difficulty = quest.difficulty;
    form.reward_gold = quest.reward_gold;
};

// Fungsi untuk cancel edit
const cancelEdit = () => {
    isEditing.value = false;
    editId.value = null;
    form.reset();
};

const submit = () => {
    if (isEditing.value) {
        form.patch(route('quests.update', editId.value), {
            onSuccess: () => cancelEdit(),
        });
    } else {
        form.post(route('quests.store'), {
            onSuccess: () => form.reset(),
        });
    }
};

const deleteQuest = (id) => {
    if (confirm('TERMINATE MISSION: Are you sure you want to abort this quest?')) {
        form.delete(route('quests.destroy', id));
    }
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
                    <div class="rpg-panel" :class="isEditing ? 'border-green-500/50' : 'border-yellow-500/50'">
                        <h2 class="mb-6 uppercase tracking-tighter" :class="isEditing ? 'text-green-500' : 'text-yellow-500'">
                            >> {{ isEditing ? 'UPDATE_CONTRACT_ID_' + editId : 'ISSUE_NEW_QUEST' }}
                        </h2>
                        
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

                            <div class="flex gap-2">
                                <button type="submit" :disabled="form.processing"
                                    class="flex-1 py-3 border-2 uppercase font-bold transition-all"
                                    :class="isEditing ? 'border-green-500 text-green-500 hover:bg-green-500 hover:text-black' : 'border-cyan-400 text-cyan-400 hover:bg-cyan-400 hover:text-black'">
                                    {{ form.processing ? 'PROCESSING...' : (isEditing ? 'UPDATE_CONTRACT' : 'CONFIRM_MISSION') }}
                                </button>
                                
                                <button v-if="isEditing" @click="cancelEdit" type="button"
                                    class="px-4 py-3 border-2 border-slate-500 text-slate-500 hover:bg-slate-500 hover:text-black uppercase">
                                    X
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="col-span-12 lg:col-span-7">
                    <div class="rpg-panel border-slate-700 h-full">
                        <h2 class="text-white mb-6 uppercase tracking-tighter">>> ACTIVE_MISSIONS_BOARD</h2>
                        
                        <div class="space-y-4">
                            <div v-for="q in quests" :key="q.id" 
                                class="flex justify-between items-center p-4 bg-slate-900/50 border-l-4 border-cyan-500 hover:bg-slate-800 transition-all">
                                <div>
                                    <div class="text-[8px] text-slate-500 mb-1 uppercase">ID: {{ q.id }} // RANK: {{ q.difficulty }}</div>
                                    <div class="text-white uppercase">{{ q.title }}</div>
                                    <div class="text-yellow-500 text-[8px] mt-1">+{{ q.reward_gold }} GOLD</div>
                                </div>
                                <div class="flex gap-2">
                                    <button @click="startEdit(q)" 
                                        class="text-green-500 hover:text-white text-[8px] border border-green-900 p-2 uppercase">
                                        Edit
                                    </button>
                                    <button @click="deleteQuest(q.id)" 
                                        class="text-red-500 hover:text-white text-[8px] border border-red-900 p-2 uppercase">
                                        Abort
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</template>