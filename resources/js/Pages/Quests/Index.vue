<script setup>
import { Head, useForm, Link,usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    quests: Array
});

const isEditing = ref(false);
const editId = ref(null);

// State untuk Modal Konfirmasi
const showDeleteModal = ref(false);
const questIdToDelete = ref(null);

const form = useForm({
    title: '',
    difficulty: 'C-Rank',
    reward_gold: 0,
    description: '',
    status: 'Available', // Tambahkan default value di sini
});

const startEdit = (quest) => {
    isEditing.value = true;
    editId.value = quest.id;
    form.title = quest.title;
    form.difficulty = quest.difficulty;
    form.reward_gold = quest.reward_gold;
    form.description = quest.description || '';
    form.status = quest.status || 'Available';
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

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

// Fungsi memicu modal
const confirmAbort = (id) => {
    questIdToDelete.value = id;
    showDeleteModal.value = true;
};

// Fungsi eksekusi hapus permanen
const executeAbort = () => {
    if (questIdToDelete.value) {
        form.delete(route('quests.destroy', questIdToDelete.value), {
            onSuccess: () => {
                showDeleteModal.value = false;
                questIdToDelete.value = null;
            }
        });
    }
};
</script>

<template>

    <Head title="GUILD_BOARD" />

    <div class="min-h-screen bg-[#0d1117] p-4 md:p-8 font-['Press_Start_2P'] text-[#4ed4d4] text-[10px] relative">
        <div class="max-w-6xl mx-auto space-y-8">

            <div class="flex justify-between items-center border-b-4 border-cyan-900 pb-4">
                <h1 class="text-xl uppercase tracking-widest animate-pulse">Quest_Management_System</h1>
                <Link href="/dashboard" class="text-slate-500 hover:text-white transition-colors uppercase">[Back_to_HQ]
                </Link>
            </div>

            <div class="grid grid-cols-12 gap-8">

                <div class="col-span-12 lg:col-span-5">
                    <div class="rpg-panel" :class="isEditing ? 'border-green-500/50' : 'border-yellow-500/50'">
                        <h2 class="mb-6 uppercase tracking-tighter"
                            :class="isEditing ? 'text-green-500' : 'text-yellow-500'">
                            >> {{ isEditing ? 'UPDATE_CONTRACT_ID_' + editId : 'ISSUE_NEW_QUEST' }}
                        </h2>

                        <form @submit.prevent="submit" class="space-y-6">
                            <div>
                                <label class="block mb-2 text-white">QUEST_TITLE:</label>
                                <input v-model="form.title" type="text"
                                    class="w-full bg-black border-2 border-slate-700 p-2 focus:border-cyan-400 outline-none text-cyan-400 uppercase"
                                    placeholder="Enter quest name..." required>
                            </div>

                            <div>
                                <label class="block mb-2 text-white">MISSION_DETAILS:</label>
                                <textarea v-model="form.description" rows="5"
                                    class="w-full bg-black border-2 border-slate-700 p-2 focus:border-cyan-400 outline-none text-slate-400 text-[8px] leading-relaxed uppercase"
                                    placeholder="Describe the objective..."></textarea>
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
                                        class="w-full bg-black border-2 border-slate-700 p-2 focus:border-cyan-400 outline-none text-yellow-400"
                                        required>
                                </div>
                            </div>

                            <label class="block mb-2 text-white">MISSION_STATUS:</label>
                            <select v-model="form.status"
                                class="w-full bg-black border-2 border-slate-700 p-2 focus:border-cyan-400 outline-none text-orange-400 uppercase">
                                <option value="Available">Available</option>
                                <option value="In-Progress">Ongoing</option>
                                <option value="Done">Completed</option>
                            </select>

                            <div class="flex gap-2">
                                <button type="submit" :disabled="form.processing"
                                    class="flex-1 py-3 border-2 uppercase font-bold transition-all"
                                    :class="isEditing ? 'border-green-500 text-green-500 hover:bg-green-500 hover:text-black' : 'border-cyan-400 text-cyan-400 hover:bg-cyan-400 hover:text-black'">
                                    {{ form.processing ? 'PROCESSING...' : (isEditing ? 'UPDATE_CONTRACT' :
                                        'CONFIRM_MISSION') }}
                                </button>
                                <button v-if="isEditing" @click="cancelEdit" type="button"
                                    class="px-4 py-3 border-2 border-slate-500 text-slate-500 hover:bg-slate-500 hover:text-white uppercase">
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
                                class="flex flex-col p-4 bg-slate-900/50 border-l-4 border-cyan-500 hover:bg-slate-800 transition-all">

                                <div class="flex justify-between items-start mb-2">
                                    <div>
                                        <div class="text-[8px] text-slate-500 mb-1 uppercase tracking-tighter">ID: {{
                                            q.id }} // RANK: {{ q.difficulty }}</div>
                                        <div class="text-white uppercase">{{ q.title }}</div>
                                    </div>
                                    <div class="text-yellow-500 text-[8px] tracking-widest">+{{ q.reward_gold }} GOLD
                                    </div>
                                </div>

                                <div v-if="q.description"
                                    class="text-[7px] text-slate-500 italic mb-4 border-t border-slate-800 pt-2 leading-loose">
                                    > {{ q.description }}
                                </div>

                                <div class="flex gap-4 self-end mt-2">
                                    <Link :href="route('quests.show', q.id)"
                                        class="text-cyan-400 hover:text-white text-[8px] uppercase">
                                        [Detail]
                                    </Link>
                                    <button @click="startEdit(q)"
                                        class="text-green-500 hover:text-white text-[8px] uppercase">[Edit]</button>
                                    <button @click="confirmAbort(q.id)"
                                        class="text-red-500 hover:text-white text-[8px] uppercase">[Abort]</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <div v-if="showDeleteModal"
            class="fixed inset-0 z-[100] flex items-center justify-center bg-black/90 backdrop-blur-md p-4">
            <div
                class="w-full max-w-lg bg-[#121212] border-2 border-red-600 shadow-[0_0_30px_rgba(220,38,38,0.3)] overflow-hidden rounded-lg">
                <div class="bg-red-600/10 border-b-2 border-red-600 p-4 flex items-center gap-3">
                    <span class="text-red-500 text-lg">⚠</span>
                    <h2 class="text-red-500 font-bold uppercase tracking-tighter text-[10px]">» WARNING:
                        TERMINATION_PROTOCOL</h2>
                </div>
                <div class="p-8 space-y-6">
                    <div class="border-l-2 border-red-900 pl-4">
                        <p class="text-slate-200 text-[10px] leading-relaxed uppercase font-sans">Are you sure you want
                            to abort this mission?</p>
                    </div>
                    <div class="bg-black/40 p-4 border border-slate-800">
                        <p class="text-slate-500 text-[8px] leading-relaxed uppercase italic">"All progress and rewards
                            associated with this contract will be purged from the system."</p>
                    </div>
                </div>
                <div class="p-6 pt-0 flex gap-4">
                    <button @click="executeAbort" :disabled="form.processing"
                        class="flex-1 py-3 bg-red-600/20 border-2 border-red-600 text-red-500 hover:bg-red-600 hover:text-white transition-all uppercase font-bold text-[9px] rounded active:scale-95">
                        {{ form.processing ? 'PURGING...' : 'PROCEED' }}
                    </button>
                    <button @click="showDeleteModal = false"
                        class="flex-1 py-3 bg-slate-800 border-2 border-slate-600 text-slate-400 hover:bg-slate-700 hover:text-white transition-all uppercase font-bold text-[9px] rounded active:scale-95">
                        CANCEL
                    </button>
                </div>
            </div>
        </div>

    </div>
</template>

<style scoped>
.rpg-panel {
    background-color: #1a1c2c;
    border-width: 4px;
    padding: 1rem;
    position: relative;
    /* Gunakan properti standar agar tidak bentrok dengan parser Tailwind */
    box-shadow: 8px 8px 0px 0px rgba(0, 0, 0, 0.5);
}

textarea {
    resize: none;
}

.fixed {
    animation: fadeIn 0.2s ease-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }

    to {
        opacity: 1;
    }
}
</style>