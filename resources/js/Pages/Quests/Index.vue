<script setup>
import { Head, useForm, Link, usePage } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue'; 
import AdminNavbar from '@/Components/AdminNavbar.vue'; // Integrasi Navbar
import Swal from 'sweetalert2';

const props = defineProps({
    quests: Array
});

// 1. DATA MAPS (Standar Gold Fix per Rank) - TETAP 100% ORISINIL
const rankGoldMap = {
    'S-Rank': 5000,
    'A-Rank': 2500,
    'B-Rank': 1000,
    'C-Rank': 500,
    'D-Rank': 100
};

// INITIALIZE SWEETALERT TOAST
const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
    background: '#1a1c2c',
    color: '#4ed4d4',
    didOpen: (toast) => {
        toast.addEventListener('mouseenter', Swal.stopTimer)
        toast.addEventListener('mouseleave', Swal.resumeTimer)
    }
});

const isEditing = ref(false);
const editId = ref(null);

// State untuk Modal Konfirmasi
const showDeleteModal = ref(false);
const questIdToDelete = ref(null);

// 2. FORM INITIALIZATION
const form = useForm({
    title: '',
    difficulty: 'C-Rank', 
    reward_gold: 500,     // Default awal mengikuti C-Rank
    description: '',
    status: 'Available',
});

// 3. LOGIC OTOMATISASI (Watch Difficulty) - TETAP DIPERTAHANKAN
watch(() => form.difficulty, (newDifficulty) => {
    if (newDifficulty) {
        form.reward_gold = rankGoldMap[newDifficulty] || 0;
    }
});

// Pantau Flash Message dari Server
watch(() => usePage().props.flash, (flash) => {
    if (flash?.message) {
        Toast.fire({
            icon: 'success',
            title: flash.message
        });
    }
}, { deep: true });

// 4. METHODS
const startEdit = (quest) => {
    isEditing.value = true;
    editId.value = quest.uuid;
    form.title = quest.title;
    form.difficulty = quest.difficulty;
    form.reward_gold = quest.reward_gold;
    form.description = quest.description || '';
    form.status = quest.status || 'Available';
    window.scrollTo({ top: 0, behavior: 'smooth' });

    Toast.fire({
        icon: 'info',
        title: 'MODIFYING_CONTRACT'
    });
};

const cancelEdit = () => {
    isEditing.value = false;
    editId.value = null;
    form.reset();
};

const submit = () => {
    if (isEditing.value) {
        form.patch(route('quests.update', editId.value), {
            onSuccess: () => {
                cancelEdit();
                Toast.fire({ icon: 'success', title: 'CONTRACT_UPDATED' });
            },
            onError: (err) => {
                Swal.fire({
                    icon: 'error',
                    title: 'UPDATE_FAILED',
                    text: Object.values(err)[0],
                    background: '#1a1c2c',
                    color: '#ff4d4d',
                    confirmButtonColor: '#4f46e5'
                });
            }
        });
    } else {
        form.post(route('quests.store'), {
            onSuccess: () => {
                form.reset();
                form.difficulty = 'C-Rank';
                form.reward_gold = 500;
                Toast.fire({ icon: 'success', title: 'QUEST_PUBLISHED' });
            },
            onError: (err) => {
                Swal.fire({
                    icon: 'warning',
                    title: 'PUBLISH_FAILED',
                    text: Object.values(err)[0],
                    background: '#1a1c2c',
                    color: '#facc15',
                    confirmButtonColor: '#4f46e5'
                });
            }
        });
    }
};

const confirmAbort = (id) => {
    questIdToDelete.value = id;
    showDeleteModal.value = true;
};

const executeAbort = () => {
    if (questIdToDelete.value) {
        form.delete(route('quests.destroy', questIdToDelete.value), {
            onSuccess: () => {
                showDeleteModal.value = false;
                questIdToDelete.value = null;
                Toast.fire({ icon: 'success', title: 'QUEST_TERMINATED' });
            },
            onError: () => {
                Toast.fire({ icon: 'error', title: 'ABORT_FAILED' });
            }
        });
    }
};
</script>

<template>
    <Head title="GUILD_BOARD" />

    <div class="min-h-screen bg-[#0d1117] p-4 md:p-8 font-['Press_Start_2P'] text-[#4ed4d4] text-[10px] relative">
        <div class="max-w-6xl mx-auto space-y-8">

            <AdminNavbar />

            <div class="flex justify-between items-center border-b-4 border-cyan-900 pb-4">
                <h1 class="text-xl uppercase tracking-widest animate-pulse">Quest_Management_System</h1>
                <Link href="/dashboard" class="text-slate-500 hover:text-white transition-colors uppercase">[Back_to_HQ]</Link>
            </div>

            <div class="grid grid-cols-12 gap-8">
                <div class="col-span-12 lg:col-span-5">
                    <div class="rpg-panel" :class="isEditing ? 'border-green-500/50' : 'border-yellow-500/50'">
                        <h2 class="mb-6 uppercase tracking-tighter" :class="isEditing ? 'text-green-500' : 'text-yellow-500'">
                            >> {{ isEditing ? 'UPDATE_CONTRACT_ID_' + editId.substring(0,8) : 'ISSUE_NEW_QUEST' }}
                        </h2>

                        <form @submit.prevent="submit" class="space-y-6">
                            <div>
                                <label class="block mb-2 text-white">QUEST_TITLE:</label>
                                <input v-model="form.title" type="text"
                                    class="w-full bg-black border-2 border-slate-700 p-2 focus:border-cyan-400 outline-none text-cyan-400 uppercase"
                                    placeholder="Enter quest name..." required>
                            </div>

                            <div>
                                <label class="block mb-2 text-white">DESCRIPTION:</label>
                                <textarea v-model="form.description"
                                    class="w-full bg-black border-2 border-slate-700 p-2 text-[8px] uppercase focus:border-cyan-400 focus:ring-0"
                                    style="resize: vertical; min-height: 120px;"></textarea>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block mb-2 text-white">DIFFICULTY:</label>
                                    <select v-model="form.difficulty"
                                        class="w-full bg-black border-2 border-slate-700 p-2 focus:border-cyan-400 outline-none text-yellow-500 uppercase">
                                        <option>D-Rank</option>
                                        <option>C-Rank</option>
                                        <option>B-Rank</option>
                                        <option>A-Rank</option>
                                        <option>S-Rank</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block mb-2 text-white">GOLD_REWARD (FIXED):</label>
                                    <div class="relative">
                                        <input v-model="form.reward_gold" type="number" readonly
                                            class="w-full bg-slate-900 border-2 border-slate-800 p-2 text-yellow-400 cursor-not-allowed opacity-80 outline-none"
                                            placeholder="Auto-calculated...">
                                        <span class="absolute right-3 top-2 text-[10px] text-slate-500 italic">AUTO</span>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label class="block mb-2 text-white">MISSION_STATUS:</label>
                                <select v-model="form.status"
                                    class="w-full bg-black border-2 border-slate-700 p-2 focus:border-cyan-400 outline-none text-orange-400 uppercase">
                                    <option value="Available">Available</option>
                                    <option value="In-Progress">Ongoing</option>
                                    <option value="Done">Completed</option>
                                </select>
                            </div>

                            <div class="flex gap-2">
                                <button type="submit" :disabled="form.processing"
                                    class="flex-1 py-3 border-2 uppercase font-bold transition-all"
                                    :class="isEditing ? 'border-green-500 text-green-500 hover:bg-green-600 hover:text-black' : 'border-cyan-400 text-cyan-400 hover:bg-cyan-400 hover:text-black'">
                                    {{ form.processing ? 'PROCESSING...' : (isEditing ? 'UPDATE_CONTRACT' : 'CONFIRM_MISSION') }}
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
                            <div v-for="q in quests" :key="q.uuid"
                                class="flex flex-col p-4 bg-slate-900/50 border-l-4 border-cyan-500 hover:bg-slate-800 transition-all">

                                <div class="flex justify-between items-start mb-2">
                                    <div class="flex-1">
                                        <div class="text-[8px] text-slate-500 mb-1 uppercase tracking-tighter">ID: {{ q.uuid.substring(0,8) }} // RANK: {{ q.difficulty }}</div>
                                        <div class="text-white uppercase">{{ q.title }}</div>
                                    </div>
                                    <div class="text-yellow-500 text-[8px] tracking-widest">+{{ q.reward_gold }} GOLD</div>
                                </div>

                                <div v-if="q.description"
                                    class="text-[7px] text-slate-500 italic mb-4 border-t border-slate-800 pt-2 leading-loose">
                                    > {{ q.description }}
                                </div>

                                <div class="flex gap-4 self-end mt-2">
                                    <Link :href="route('admin.quests.submissions', q.uuid)"
                                        class="text-cyan-400 hover:text-white text-[8px] uppercase font-bold">[Detail]</Link>
                                    <button @click="startEdit(q)"
                                        class="text-green-500 hover:text-white text-[8px] uppercase font-bold">[Edit]</button>
                                    <button @click="confirmAbort(q.uuid)"
                                        class="text-red-500 hover:text-white text-[8px] uppercase font-bold">[Abort]</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showDeleteModal"
            class="fixed inset-0 z-[100] flex items-center justify-center bg-black/90 backdrop-blur-md p-4">
            <div class="w-full max-w-lg bg-[#121212] border-2 border-red-600 shadow-[0_0_30px_rgba(220,38,38,0.3)] overflow-hidden rounded-lg">
                <div class="bg-red-600/10 border-b-2 border-red-600 p-4 flex items-center gap-3">
                    <span class="text-red-500 text-lg">⚠</span>
                    <h2 class="text-red-500 font-bold uppercase tracking-tighter text-[10px]">» WARNING: TERMINATION_PROTOCOL</h2>
                </div>
                <div class="p-8 space-y-6">
                    <div class="border-l-2 border-red-900 pl-4">
                        <p class="text-slate-200 text-[10px] leading-relaxed uppercase">Are you sure you want to abort this mission contract?</p>
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
    box-shadow: 8px 8px 0px 0px rgba(0, 0, 0, 0.5);
}

textarea {
    resize: none;
}

.fixed {
    animation: fadeIn 0.2s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}
</style>