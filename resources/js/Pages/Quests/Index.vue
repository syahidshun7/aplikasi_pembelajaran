<script setup>
import { Head, useForm, Link, usePage, router } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue'; 
import AdminNavbar from '@/Components/AdminNavbar.vue';
import Swal from 'sweetalert2';

const props = defineProps({
    quests: Object,
    studyGroups: Array,
    taskBanks: Array,
    filters: Object,
});

const rankGoldMap = {
    'S-Rank': 5000,
    'A-Rank': 2500,
    'B-Rank': 1000,
    'C-Rank': 500,
};

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
const showDeleteModal = ref(false);
const questIdToDelete = ref(null);
const filterForm = useForm({
    search: props.filters?.search || '',
});

const questItems = computed(() => props.quests?.data || []);
const paginationLinks = computed(() => props.quests?.links || []);

const form = useForm({
    title: '',
    difficulty: 'C-Rank', 
    reward_gold: 500,
    reward_exp: 500,
    description: '',
    status: 'Available',
    study_group_id: null,
    task_bank_id: null,
    deadline: '', // NEW_FIELD
});

watch(() => form.difficulty, (newDifficulty) => {
    if (newDifficulty) {
        form.reward_gold = rankGoldMap[newDifficulty] || 0;
        form.reward_exp = rankGoldMap[newDifficulty] || 0;
    }
});

const syncStatusFromDeadline = (deadlineValue) => {
    if (!deadlineValue) {
        form.status = 'Available';
        return;
    }

    const selectedDate = new Date(deadlineValue);
    form.status = selectedDate > new Date() ? 'Available' : 'Done';
};

watch(() => form.deadline, (newDeadline) => {
    syncStatusFromDeadline(newDeadline);
});

watch(() => usePage().props.flash, (flash) => {
    if (flash?.message) {
        Toast.fire({
            icon: 'success',
            title: flash.message
        });
    }
}, { deep: true });

// HELPER: Format date for display
const formatDeadline = (date) => {
    if (!date) return 'PERMANENT_CONTRACT';
    return new Date(date).toLocaleString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    }).toUpperCase();
};

// HELPER: Check if expired
const isExpired = (date) => {
    if (!date) return false;
    return new Date(date) < new Date();
};

const startEdit = (quest) => {
    isEditing.value = true;
    editId.value = quest.uuid;
    form.title = quest.title;
    form.difficulty = quest.difficulty;
    form.reward_gold = quest.reward_gold;
    form.reward_exp = quest.reward_exp ?? quest.reward_gold ?? 0;
    form.description = quest.description || '';
    form.status = quest.status || 'Available';
    form.study_group_id = quest.study_group_id;
    form.task_bank_id = quest.task_bank_id;
    
    // Format deadline for datetime-local input (YYYY-MM-DDTHH:mm)
    if (quest.deadline) {
        const d = new Date(quest.deadline);
        d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
        form.deadline = d.toISOString().slice(0, 16);
    } else {
        form.deadline = '';
    }
    syncStatusFromDeadline(form.deadline);

    window.scrollTo({ top: 0, behavior: 'smooth' });
    Toast.fire({ icon: 'info', title: 'MODIFYING_CONTRACT' });
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
                // Alert dihilangkan sesuai permintaan, hanya Toast
            },
        });
    } else {
        form.post(route('quests.store'), {
            onSuccess: () => {
                form.reset();
                form.difficulty = 'C-Rank';
                form.reward_gold = 500;
                form.reward_exp = 500;
                form.task_bank_id = null;
            },
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
            }
        });
    }
};

const applyFilters = () => {
    router.get(route('quests.index'), filterForm.data(), {
        preserveState: true,
        preserveScroll: true,
    });
};

const resetFilters = () => {
    filterForm.search = '';
    applyFilters();
};

const goToPage = (url) => {
    if (!url) return;
    router.get(url, {}, {
        preserveState: true,
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="GUILD_BOARD" />

    <div class="min-h-screen bg-[#0d1117] p-4 md:p-8 font-['Press_Start_2P'] text-[#4ed4d4] text-[10px] relative">
        <div class="max-w-7xl mx-auto space-y-8">

            <AdminNavbar />

            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3 border-b-4 border-cyan-900 pb-4">
                <h1 class="text-base sm:text-xl uppercase tracking-widest animate-pulse">Quest_Management_System</h1>
                <Link href="/dashboard" class="inline-flex items-center justify-center px-3 py-2 border border-slate-600 bg-slate-900/40 text-slate-300 hover:text-white transition-colors uppercase text-[9px] sm:text-[10px]">[Back_to_HQ]</Link>
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
                                    class="w-full bg-black border-2 border-slate-700 p-2 text-[12px] font-sans text-slate-200 focus:border-cyan-400 focus:ring-0"
                                    style="resize: vertical; min-height: 100px;"></textarea>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block mb-2 text-white">DIFFICULTY:</label>
                                    <select v-model="form.difficulty"
                                        class="w-full bg-black border-2 border-slate-700 p-2 focus:border-cyan-400 outline-none text-yellow-500 uppercase">
                                        <option>C-Rank</option>
                                        <option>B-Rank</option>
                                        <option>A-Rank</option>
                                        <option>S-Rank</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block mb-2 text-white">REWARDS:</label>
                                    <div class="grid grid-cols-2 gap-2">
                                        <input v-model="form.reward_gold" type="number" readonly
                                            class="w-full bg-slate-900 border-2 border-slate-800 p-2 text-yellow-400 cursor-not-allowed opacity-80 outline-none"
                                            aria-label="GOLD_REWARD">
                                        <input v-model="form.reward_exp" type="number" readonly
                                            class="w-full bg-slate-900 border-2 border-slate-800 p-2 text-cyan-400 cursor-not-allowed opacity-80 outline-none"
                                            aria-label="EXP_REWARD">
                                    </div>
                                    <div class="grid grid-cols-2 gap-2 mt-1 text-[7px] uppercase">
                                        <span class="text-yellow-500">Gold</span>
                                        <span class="text-cyan-400">Exp</span>
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block mb-2 text-white">MISSION_STATUS:</label>
                                    <select v-model="form.status"
                                        class="w-full bg-black border-2 border-slate-700 p-2 focus:border-cyan-400 outline-none text-orange-400 uppercase">
                                        <option value="Available">Available</option>
                                        <option value="In-Progress">In-Progress</option>
                                        <option value="Done">Done</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block mb-2 text-white text-orange-500">SET_DEADLINE:</label>
                                    <input v-model="form.deadline" type="datetime-local"
                                        class="w-full bg-black border-2 border-slate-700 p-2 focus:border-orange-500 outline-none text-orange-400 uppercase text-[8px]">
                                </div>
                            </div>

                            <div>
                                <label class="block mb-2 text-white">ASSIGN_TO_PARTY:</label>
                                <select v-model="form.study_group_id"
                                    class="w-full bg-black border-2 border-slate-700 p-2 focus:border-emerald-400 outline-none text-emerald-400 uppercase">
                                    <option :value="null">-- GLOBAL_QUEST (PUBLIC) --</option>
                                    <option v-for="group in studyGroups" :key="group.id" :value="group.id">
                                        >> PARTY: {{ group.name }}
                                    </option>
                                </select>
                            </div>

                            <div>
                                <label class="block mb-2 text-white">TASK_BANK_SOURCE:</label>
                                <select v-model="form.task_bank_id"
                                    class="w-full bg-black border-2 border-slate-700 p-2 focus:border-teal-400 outline-none text-teal-300 uppercase">
                                    <option :value="null">-- NO_TASK_BANK (MANUAL_QUEST) --</option>
                                    <option v-for="bank in taskBanks" :key="bank.id" :value="bank.id">
                                        {{ bank.name }} [{{ bank.assessment_type }}]
                                    </option>
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
                        <div class="mb-4 flex flex-col md:flex-row gap-2">
                            <input
                                v-model="filterForm.search"
                                type="text"
                                placeholder="SEARCH QUEST / PARTY / STATUS"
                                class="flex-1 bg-black border-2 border-slate-700 p-2 text-cyan-400 uppercase outline-none"
                                @keyup.enter="applyFilters"
                            />
                            <button @click="applyFilters"
                                class="px-3 py-2 border-2 border-cyan-400 text-cyan-400 hover:bg-cyan-400 hover:text-black uppercase">
                                APPLY
                            </button>
                            <button @click="resetFilters"
                                class="px-3 py-2 border-2 border-slate-600 text-slate-300 hover:bg-slate-700 hover:text-white uppercase">
                                RESET
                            </button>
                        </div>

                        <div class="space-y-4 max-h-[560px] overflow-y-auto pr-2 custom-scroll">
                            <div v-for="q in questItems" :key="q.uuid"
                                class="flex flex-col p-4 bg-slate-900/50 border-l-4 border-cyan-500 hover:bg-slate-800 transition-all relative overflow-hidden">
                                
                                <div class="flex justify-between items-start mb-1">
                                    <div class="flex-1">
                                        <div class="text-[7px] text-slate-500 mb-1 uppercase tracking-tighter">
                                            ID: {{ q.uuid.substring(0,8) }} // RANK: {{ q.difficulty }}
                                            <span v-if="q.study_group" class="text-emerald-500 ml-2">// [PARTY: {{ q.study_group.name }}]</span>
                                        </div>
                                        <div class="text-white uppercase text-[9px]">{{ q.title }}</div>
                                        <div class="text-[7px] text-teal-300 uppercase mt-1">
                                            TASK_BANK: {{ q.task_bank?.name || 'MANUAL' }}
                                            <span v-if="q.task_bank?.assessment_type" class="text-yellow-300 ml-1">[{{ q.task_bank.assessment_type }}]</span>
                                        </div>
                                    </div>
                                    <div class="text-yellow-500 text-[8px] tracking-widest">+{{ q.reward_gold }} GOLD</div>
                                </div>

                                <div class="text-[7px] mb-3 flex items-center gap-1">
                                    <span class="text-orange-500">>> DEADLINE:</span>
                                    <span :class="isExpired(q.deadline) ? 'text-red-500 animate-pulse font-bold' : 'text-orange-300'">
                                        {{ q.deadline ? formatDeadline(q.deadline) : 'NO_TIME_LIMIT' }}
                                    </span>
                                    <span v-if="isExpired(q.deadline)" class="text-red-600 ml-1">[EXPIRED]</span>
                                </div>

                                <div v-if="q.description"
                                    class="text-[7px] text-slate-500 italic mb-4 border-t border-slate-800 pt-2 leading-loose line-clamp-2">
                                    > {{ q.description }}
                                </div>

                                <div class="flex gap-4 self-end mt-auto">
                                    <Link :href="route('admin.quests.submissions', q.uuid)"
                                        class="text-cyan-400 hover:text-white text-[8px] uppercase font-bold">[Detail]</Link>
                                    <button @click="startEdit(q)"
                                        class="text-green-500 hover:text-white text-[8px] uppercase font-bold">[Edit]</button>
                                    <button @click="confirmAbort(q.uuid)"
                                        class="text-red-500 hover:text-white text-[8px] uppercase font-bold">[Abort]</button>
                                </div>
                            </div>
                        </div>
                        <div class="mt-5 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                            <p class="text-[8px] text-slate-500 uppercase">
                                PAGE {{ quests.current_page || 1 }} / {{ quests.last_page || 1 }}
                                | TOTAL {{ quests.total || 0 }}
                            </p>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="(link, idx) in paginationLinks"
                                    :key="`${idx}-${link.label}`"
                                    @click="goToPage(link.url)"
                                    :disabled="!link.url"
                                    class="px-3 py-1 border text-[8px] uppercase transition-all"
                                    :class="[
                                        link.active
                                            ? 'border-cyan-400 text-cyan-400 bg-cyan-900/20'
                                            : 'border-slate-700 text-slate-300 hover:bg-slate-700 hover:text-white',
                                        !link.url ? 'opacity-40 cursor-not-allowed' : ''
                                    ]"
                                    v-html="link.label"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showDeleteModal"
            class="fixed inset-0 z-[100] flex items-center justify-center bg-black/90 backdrop-blur-md p-4">
            <div class="w-full max-w-lg bg-[#121212] border-2 border-red-600 shadow-[0_0_30px_rgba(220,38,38,0.3)] overflow-hidden rounded-lg font-['Press_Start_2P']">
                <div class="bg-red-600/10 border-b-2 border-red-600 p-4 flex items-center gap-3">
                    <span class="text-red-500 text-lg">⚠</span>
                    <h2 class="text-red-500 font-bold uppercase tracking-tighter text-[10px]">» WARNING: TERMINATION_PROTOCOL</h2>
                </div>
                <div class="p-8 space-y-6">
                    <div class="border-l-2 border-red-900 pl-4">
                        <p class="text-slate-200 text-[10px] leading-relaxed uppercase">Abort this mission contract?</p>
                    </div>
                </div>
                <div class="p-6 pt-0 flex gap-4">
                    <button @click="executeAbort" :disabled="form.processing"
                        class="flex-1 py-3 bg-red-600/20 border-2 border-red-600 text-red-500 hover:bg-red-600 hover:text-white transition-all uppercase font-bold text-[9px]">
                        {{ form.processing ? 'PURGING...' : 'PROCEED' }}
                    </button>
                    <button @click="showDeleteModal = false"
                        class="flex-1 py-3 bg-slate-800 border-2 border-slate-600 text-slate-400 hover:bg-slate-700 hover:text-white transition-all uppercase font-bold text-[9px]">
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

textarea { resize: none; }
.fixed { animation: fadeIn 0.2s ease-out; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

/* Chrome, Safari, Edge, Opera */
input::-webkit-calendar-picker-indicator {
    filter: invert(0.8) sepia(100%) saturate(500%) hue-rotate(10deg);
    cursor: pointer;
}

.custom-scroll::-webkit-scrollbar {
    width: 6px;
}

.custom-scroll::-webkit-scrollbar-track {
    background: #0d1117;
}

.custom-scroll::-webkit-scrollbar-thumb {
    background: #334155;
    border-radius: 999px;
}

.custom-scroll::-webkit-scrollbar-thumb:hover {
    background: #4ed4d4;
}
</style>
