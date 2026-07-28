<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AdminNavbar from '@/Components/AdminNavbar.vue';

const props = defineProps({
    taskBanks: Object,
    jobs: Array,
    filters: Object,
});
const page = usePage();
const isMentor = computed(() => String(page.props?.auth?.user?.role || '').toLowerCase() === 'mentor');
const firstJobId = computed(() => props.jobs?.[0]?.id ?? '');

const isEditing = ref(false);
const editUuid = ref(null);
const showFormModal = ref(false);
const showDeleteModal = ref(false);
const deleteUuid = ref(null);

const filterForm = useForm({
    search: props.filters?.search || '',
});

const form = useForm({
    name: '',
    description: '',
    job_role_id: '',
    assessment_type: 'essay',
    duration: 60,
    has_time_limit: false,
    is_active: true,
});
const mentorCannotSubmitTaskBank = computed(() => isMentor.value && !form.job_role_id);

const bankItems = computed(() => props.taskBanks?.data || []);
const paginationLinks = computed(() => props.taskBanks?.links || []);

const applyMentorDefaultJob = () => {
    if (isMentor.value && !form.job_role_id) {
        form.job_role_id = firstJobId.value || '';
    }
};

const startEdit = (bank) => {
    isEditing.value = true;
    editUuid.value = bank.uuid;
    form.name = bank.name || '';
    form.description = bank.description || '';
    form.job_role_id = bank.job_role_id || '';
    form.assessment_type = bank.assessment_type || 'essay';
    form.duration = bank.duration ?? 60;
    form.has_time_limit = bank.has_time_limit !== false;
    form.is_active = !!bank.is_active;
    applyMentorDefaultJob();
    showFormModal.value = true;
};

const cancelEdit = () => {
    isEditing.value = false;
    editUuid.value = null;
    form.reset();
    form.assessment_type = 'essay';
    form.duration = 60;
    form.has_time_limit = false;
    form.is_active = true;
    applyMentorDefaultJob();
    showFormModal.value = false;
};

const openCreateModal = () => {
    cancelEdit();
    showFormModal.value = true;
};

const submit = () => {
    if (isEditing.value) {
        form.put(route('admin.task-banks.update', editUuid.value), {
            preserveScroll: true,
            onSuccess: () => {
                cancelEdit();
            },
        });
        return;
    }

    form.post(route('admin.task-banks.store'), {
        preserveScroll: true,
        onSuccess: () => {
            cancelEdit();
        },
    });
};

const confirmDelete = (uuid) => {
    deleteUuid.value = uuid;
    showDeleteModal.value = true;
};

const executeDelete = () => {
    if (!deleteUuid.value) return;

    router.delete(route('admin.task-banks.destroy', deleteUuid.value), {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteModal.value = false;
            deleteUuid.value = null;
        },
    });
};

const applyFilters = () => {
    router.get(route('admin.task-banks.index'), filterForm.data(), {
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

const typeClass = (type) => {
    if (type === 'multiple_choice') return 'text-yellow-400 border-yellow-800 bg-yellow-900/20';
    if (type === 'mixed') return 'text-purple-300 border-purple-800 bg-purple-900/20';
    if (type === 'platforming') return 'text-purple-400 border-purple-700 bg-purple-900/20';
    if (type === 'word_match') return 'text-orange-300 border-orange-800 bg-orange-900/20';
    return 'text-cyan-400 border-cyan-800 bg-cyan-900/20';
};

watch([isMentor, firstJobId], () => {
    applyMentorDefaultJob();
}, { immediate: true });
</script>

<template>
    <Head title="TASK_BANK_MANAGEMENT" />

    <div class="min-h-screen bg-[#0d1117] p-4 md:p-8 font-['Press_Start_2P'] text-[#4ed4d4] text-[10px] relative">
        <div class="max-w-7xl mx-auto space-y-8">
            <AdminNavbar />

            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3 border-b-4 border-teal-900 pb-4">
                <h1 class="text-base sm:text-xl uppercase tracking-widest">Task_Bank_Management</h1>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="openCreateModal"
                        class="inline-flex items-center justify-center px-3 py-2 border border-teal-500 bg-teal-900/20 text-teal-300 hover:bg-teal-500 hover:text-black uppercase text-[9px] sm:text-[10px]"
                    >
                        [New_Bank]
                    </button>
                    <Link :href="route('dashboard')" class="inline-flex items-center justify-center px-3 py-2 border border-slate-600 bg-slate-900/40 text-slate-300 hover:text-white uppercase text-[9px] sm:text-[10px]">[Back_to_HQ]</Link>
                </div>
            </div>

            <div class="grid grid-cols-12 gap-8">
                <div
                    v-if="showFormModal"
                    class="fixed inset-0 z-[120] flex items-center justify-center bg-black/85 backdrop-blur-sm p-4"
                >
                    <div class="w-full max-w-4xl max-h-[90vh] overflow-y-auto modal-scroll">
                    <div class="rpg-panel" :class="isEditing ? 'border-emerald-500/50' : 'border-teal-500/50'">
                        <h2 class="mb-6 uppercase tracking-tighter" :class="isEditing ? 'text-emerald-400' : 'text-teal-300'">
                            >> {{ isEditing ? 'UPDATE_TASK_BANK' : 'CREATE_TASK_BANK' }}
                        </h2>

                        <form @submit.prevent="submit" class="space-y-5">
                            <div>
                                <label class="block mb-2 text-white uppercase">BANK_NAME:</label>
                                <input v-model="form.name" type="text" class="w-full bg-black border-2 border-slate-700 p-2 text-teal-300 uppercase outline-none focus:border-teal-400" required>
                                <p v-if="form.errors.name" class="mt-2 text-red-400 text-[8px]">{{ form.errors.name }}</p>
                            </div>

                            <div>
                                <label class="block mb-2 text-white uppercase">DESCRIPTION:</label>
                                <textarea v-model="form.description" class="w-full bg-black border-2 border-slate-700 p-2 text-[12px] font-sans text-slate-200 focus:border-teal-400 focus:ring-0" style="resize: vertical; min-height: 100px;"></textarea>
                                <p v-if="form.errors.description" class="mt-2 text-red-400 text-[8px]">{{ form.errors.description }}</p>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div>
                                    <label class="block mb-2 text-white uppercase">JOB_SCOPE:</label>
                                    <select v-model="form.job_role_id" class="w-full bg-black border-2 border-slate-700 p-2 text-teal-300 uppercase outline-none focus:border-teal-400">
                                        <option v-if="!isMentor" value="">ALL_JOBS</option>
                                        <option v-if="isMentor && !jobs.length" value="" disabled>NO_JOB_AVAILABLE</option>
                                        <option v-for="job in jobs" :key="job.id" :value="job.id">{{ job.name }}</option>
                                    </select>
                                    <p v-if="form.errors.job_role_id" class="mt-2 text-red-400 text-[8px]">{{ form.errors.job_role_id }}</p>
                                </div>
                                <div>
                                    <label class="block mb-2 text-white uppercase">ASSESSMENT_TYPE:</label>
                                    <select v-model="form.assessment_type" class="w-full bg-black border-2 border-slate-700 p-2 text-yellow-300 uppercase outline-none focus:border-yellow-400">
                                        <option value="essay">ESSAY</option>
                                        <option value="multiple_choice">MULTIPLE_CHOICE</option>
                                        <option value="mixed">MIXED</option>
                                        <option value="platforming">PLATFORMING</option>
                                        <option value="word_match">WORD_MATCH</option>
                                    </select>
                                    <p v-if="form.errors.assessment_type" class="mt-2 text-red-400 text-[8px]">{{ form.errors.assessment_type }}</p>
                                </div>
                            </div>

                            <div v-if="form.assessment_type === 'platforming' || form.assessment_type === 'word_match'">
                                <label class="block mb-2 text-white uppercase">GAME_DURATION (SECONDS):</label>
                                <input v-model.number="form.duration" type="number" min="5" max="3600" class="w-full bg-black border-2 border-slate-700 p-2 text-cyan-300 uppercase outline-none focus:border-cyan-400" required>
                                <p class="mt-2 text-[8px] text-slate-500 uppercase">Waktu menjawab per soal (Platforming) atau total sesi (Word Match).</p>
                                <p v-if="form.errors.duration" class="mt-2 text-red-400 text-[8px]">{{ form.errors.duration }}</p>
                            </div>

                            <div v-else>
                                <label class="mb-3 inline-flex items-center gap-2 text-[9px] uppercase text-slate-300">
                                    <input v-model="form.has_time_limit" type="checkbox" class="accent-cyan-500">
                                    USE_EXAM_TIMER
                                </label>
                                <div v-if="form.has_time_limit">
                                    <label class="block mb-2 text-white uppercase">EXAM_DURATION (MINUTES):</label>
                                    <input v-model.number="form.duration" type="number" min="1" max="1440" class="w-full bg-black border-2 border-slate-700 p-2 text-cyan-300 uppercase outline-none focus:border-cyan-400" required>
                                    <p class="mt-2 text-[8px] text-slate-500 uppercase">Timer dimulai ketika user membuka attempt. Gunakan 60 untuk ujian satu jam.</p>
                                </div>
                                <p v-else class="border border-slate-700 bg-black/30 px-3 py-2 text-[8px] uppercase text-emerald-300">
                                    NO_TIME: quest dapat dikerjakan tanpa batas waktu ujian.
                                </p>
                                <p v-if="form.errors.duration" class="mt-2 text-red-400 text-[8px]">{{ form.errors.duration }}</p>
                                <p v-if="form.errors.has_time_limit" class="mt-2 text-red-400 text-[8px]">{{ form.errors.has_time_limit }}</p>
                            </div>

                            <label class="inline-flex items-center gap-2 text-[9px] uppercase text-slate-300">
                                <input v-model="form.is_active" type="checkbox" class="accent-teal-500">
                                BANK_ACTIVE
                            </label>

                            <div class="flex gap-2">
                                <button type="submit" :disabled="form.processing || mentorCannotSubmitTaskBank" class="flex-1 py-3 border-2 border-teal-400 text-teal-300 hover:bg-teal-400 hover:text-black uppercase font-bold transition-all">
                                    {{ form.processing ? 'PROCESSING...' : (isEditing ? 'UPDATE' : 'CREATE') }}
                                </button>
                                <button @click="cancelEdit" type="button" class="px-4 py-3 border-2 border-slate-500 text-slate-500 hover:bg-slate-500 hover:text-white uppercase">
                                    X
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                </div>

                <div class="col-span-12 lg:col-span-12">
                    <div class="rpg-panel border-slate-700 h-full">
                        <h2 class="text-white mb-6 uppercase tracking-tighter">>> TASK_BANKS_BOARD</h2>

                        <div class="mb-4 flex flex-col md:flex-row gap-2">
                            <input v-model="filterForm.search" type="text" placeholder="SEARCH BANK / JOB / TYPE" class="flex-1 bg-black border-2 border-slate-700 p-2 text-teal-300 uppercase outline-none" @keyup.enter="applyFilters" />
                            <button @click="applyFilters" class="px-3 py-2 border-2 border-teal-400 text-teal-300 hover:bg-teal-400 hover:text-black uppercase">APPLY</button>
                            <button @click="resetFilters" class="px-3 py-2 border-2 border-slate-600 text-slate-300 hover:bg-slate-700 hover:text-white uppercase">RESET</button>
                        </div>

                        <div class="space-y-4 max-h-[560px] overflow-y-auto pr-2 custom-scroll">
                            <div v-for="bank in bankItems" :key="bank.uuid" class="flex flex-col p-4 bg-slate-900/50 border-l-4 border-teal-500 hover:bg-slate-800 transition-all">
                                <div class="flex justify-between items-start gap-3">
                                    <div class="flex-1 min-w-0">
                                        <div class="text-[8px] text-slate-500 mb-1 uppercase tracking-tighter">ID: {{ bank.uuid.substring(0, 8) }}</div>
                                        <div class="text-white uppercase text-[9px]">{{ bank.name }}</div>
                                        <div class="text-[7px] mt-2 uppercase text-teal-300">JOB: {{ bank.job_role?.name || 'NO_JOB_SCOPE' }}</div>
                                        <div class="mt-2 inline-flex px-2 py-1 border text-[8px] uppercase" :class="typeClass(bank.assessment_type)">{{ bank.assessment_type }}</div>
                                        <div v-if="bank.description" class="text-[7px] text-slate-500 italic mt-3 leading-loose">> {{ bank.description }}</div>
                                    </div>
                                    <div class="text-right text-[8px] shrink-0">
                                        <div class="text-yellow-400">TASKS: {{ bank.questions_count || 0 }}</div>
                                        <div :class="bank.is_active ? 'text-emerald-400' : 'text-red-400'">{{ bank.is_active ? 'ACTIVE' : 'INACTIVE' }}</div>
                                    </div>
                                </div>

                                <div class="flex gap-4 self-end mt-3">
                                    <Link :href="route('admin.task-banks.show', bank.uuid)" class="text-cyan-400 hover:text-white text-[8px] uppercase font-bold">[Tasks]</Link>
                                    <button @click="startEdit(bank)" class="text-emerald-500 hover:text-white text-[8px] uppercase font-bold">[Edit]</button>
                                    <button @click="confirmDelete(bank.uuid)" class="text-red-500 hover:text-white text-[8px] uppercase font-bold">[Delete]</button>
                                </div>
                            </div>

                            <p v-if="bankItems.length === 0" class="text-[8px] text-slate-500 uppercase text-center py-8">NO_TASK_BANKS_FOUND</p>
                        </div>

                        <div class="mt-5 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                            <p class="text-[8px] text-slate-500 uppercase">PAGE {{ taskBanks.current_page || 1 }} / {{ taskBanks.last_page || 1 }} | TOTAL {{ taskBanks.total || 0 }}</p>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="(link, idx) in paginationLinks"
                                    :key="`${idx}-${link.label}`"
                                    @click="goToPage(link.url)"
                                    :disabled="!link.url"
                                    class="px-3 py-1 border text-[8px] uppercase transition-all"
                                    :class="[
                                        link.active ? 'border-teal-400 text-teal-300 bg-teal-900/20' : 'border-slate-700 text-slate-300 hover:bg-slate-700 hover:text-white',
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

        <div v-if="showDeleteModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/90 backdrop-blur-md p-4">
            <div class="w-full max-w-md bg-[#121212] border-2 border-red-600 shadow-[0_0_30px_rgba(220,38,38,0.3)] rounded-lg overflow-hidden">
                <div class="p-5 border-b border-red-600 text-red-400 uppercase">Delete Task Bank?</div>
                <div class="p-5 text-slate-300 text-[10px] uppercase">Semua task di dalam bank ini akan ikut terhapus.</div>
                <div class="p-5 pt-0 flex gap-3">
                    <button @click="executeDelete" class="flex-1 py-2 border-2 border-red-600 text-red-400 hover:bg-red-600 hover:text-white uppercase">Delete</button>
                    <button @click="showDeleteModal = false" class="flex-1 py-2 border-2 border-slate-600 text-slate-300 hover:bg-slate-700 hover:text-white uppercase">Cancel</button>
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
    box-shadow: 8px 8px 0 0 rgba(0, 0, 0, 0.5);
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
    background: #2dd4bf;
}
</style>
