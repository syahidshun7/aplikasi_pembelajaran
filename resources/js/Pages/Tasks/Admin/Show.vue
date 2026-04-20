<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminNavbar from '@/Components/AdminNavbar.vue';

const props = defineProps({
    taskBank: Object,
    questions: Object,
    filters: Object,
    importResult: Object,
    importTemplate: Object,
});

const isEditing = ref(false);
const editUuid = ref(null);
const showFormModal = ref(false);

const filterForm = useForm({
    search: props.filters?.search || '',
});

const form = useForm({
    question_text: '',
    question_type: 'essay',
    options: ['', ''],
    correct_option_index: null,
    weight: 1,
    sort_order: 1,
    is_active: true,
});

const importForm = useForm({
    import_file: null,
    skip_invalid: true,
});

const rows = computed(() => props.questions?.data || []);
const paginationLinks = computed(() => props.questions?.links || []);
const importErrors = computed(() => Array.isArray(props.importResult?.errors) ? props.importResult.errors : []);
const importTemplateJson = computed(() => JSON.stringify(props.importTemplate?.sample || [], null, 2));

const isMcq = computed(() => form.question_type === 'multiple_choice');

const startEdit = (row) => {
    isEditing.value = true;
    editUuid.value = row.uuid;
    form.question_text = row.question_text || '';
    form.question_type = row.question_type || 'essay';
    form.options = Array.isArray(row.options_json) && row.options_json.length > 0
        ? row.options_json
        : ['', ''];
    form.correct_option_index = Array.isArray(row.options_json)
        ? row.options_json.findIndex((option) => option === row.answer_key)
        : null;
    if (form.correct_option_index < 0) {
        form.correct_option_index = null;
    }
    form.weight = row.weight || 1;
    form.sort_order = row.sort_order || 1;
    form.is_active = !!row.is_active;
    showFormModal.value = true;
};

const cancelEdit = () => {
    isEditing.value = false;
    editUuid.value = null;
    form.reset();
    form.question_type = 'essay';
    form.options = ['', ''];
    form.correct_option_index = null;
    form.weight = 1;
    form.sort_order = 1;
    form.is_active = true;
    showFormModal.value = false;
};

const openCreateModal = () => {
    cancelEdit();
    showFormModal.value = true;
};

const addOption = () => {
    form.options.push('');
};

const removeOption = (index) => {
    if (form.options.length <= 2) return;
    form.options.splice(index, 1);
    if (form.correct_option_index === index) {
        form.correct_option_index = null;
    } else if (typeof form.correct_option_index === 'number' && form.correct_option_index > index) {
        form.correct_option_index -= 1;
    }
};

const submit = () => {
    const normalizedOptions = (form.options || []).map((v) => String(v || '').trim());
    const cleanedOptions = normalizedOptions.filter((v) => v !== '');

    const answerKey = isMcq.value
        ? (typeof form.correct_option_index === 'number' ? (normalizedOptions[form.correct_option_index] || '') : '')
        : '';

    const payload = {
        question_text: form.question_text,
        question_type: form.question_type,
        options: cleanedOptions,
        answer_key: answerKey,
        weight: form.weight,
        sort_order: form.sort_order,
        is_active: form.is_active,
    };

    if (isEditing.value) {
        router.put(route('admin.task-banks.tasks.update', { taskBank: props.taskBank.uuid, question: editUuid.value }), payload, {
            preserveScroll: true,
            onSuccess: () => cancelEdit(),
        });
        return;
    }

    router.post(route('admin.task-banks.tasks.store', props.taskBank.uuid), payload, {
        preserveScroll: true,
        onSuccess: () => {
            cancelEdit();
        },
    });
};

const destroyTask = (uuid) => {
    router.delete(route('admin.task-banks.tasks.destroy', { taskBank: props.taskBank.uuid, question: uuid }), {
        preserveScroll: true,
    });
};

const applyFilters = () => {
    router.get(route('admin.task-banks.show', props.taskBank.uuid), filterForm.data(), {
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
    return 'text-cyan-400 border-cyan-800 bg-cyan-900/20';
};

const handleImportFileChange = (event) => {
    importForm.import_file = event.target.files?.[0] || null;
};

const submitImport = () => {
    importForm.post(route('admin.task-banks.tasks.import-json', props.taskBank.uuid), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            importForm.reset();
            importForm.skip_invalid = true;
        },
    });
};
</script>

<template>
    <Head :title="`TASKS | ${taskBank.name}`" />

    <div class="min-h-screen bg-[#0d1117] p-4 md:p-8 font-['Press_Start_2P'] text-[#4ed4d4] text-[10px] relative">
        <div class="max-w-7xl mx-auto space-y-8">
            <AdminNavbar />

            <div class="rpg-panel border-teal-500/40">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                    <div>
                        <h1 class="text-base md:text-xl text-white uppercase tracking-widest break-words">{{ taskBank.name }}</h1>
                        <p class="text-[8px] text-slate-400 uppercase mt-2">
                            JOB: <span class="text-teal-300">{{ taskBank.job_role?.name || 'NO_JOB_SCOPE' }}</span>
                            | TYPE: <span class="text-yellow-300">{{ taskBank.assessment_type }}</span>
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            @click="openCreateModal"
                            class="inline-flex items-center justify-center px-3 py-2 border border-teal-500 bg-teal-900/20 text-teal-300 hover:bg-teal-500 hover:text-black uppercase text-[8px]"
                        >
                            [New_Task]
                        </button>
                        <Link :href="route('admin.task-banks.index')" class="inline-flex items-center justify-center px-3 py-2 border border-slate-600 bg-slate-900/40 text-slate-300 hover:text-white uppercase text-[8px]">[Back_to_Task_Banks]</Link>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-12 gap-8">
                <div class="col-span-12 lg:col-span-5">
                    <div class="space-y-6">
                        <div class="rpg-panel border-cyan-500/50">
                            <h2 class="mb-5 uppercase tracking-tighter text-cyan-300">>> IMPORT_JSON</h2>

                            <form @submit.prevent="submitImport" class="space-y-4">
                                <div>
                                    <label class="block mb-2 text-white uppercase">JSON_FILE:</label>
                                    <input
                                        type="file"
                                        accept=".json,application/json,text/plain"
                                        class="w-full bg-black border-2 border-slate-700 p-2 text-[10px] text-slate-300 file:mr-3 file:border-0 file:bg-cyan-900/40 file:px-3 file:py-2 file:text-[8px] file:uppercase file:text-cyan-200"
                                        @change="handleImportFileChange"
                                    >
                                    <p class="mt-2 text-[8px] text-slate-400 uppercase">
                                        Upload file JSON array untuk import banyak soal sekaligus.
                                    </p>
                                    <p v-if="importForm.errors.import_file" class="mt-2 text-red-400 text-[8px]">{{ importForm.errors.import_file }}</p>
                                </div>

                                <label class="inline-flex items-center gap-2 text-[9px] uppercase text-slate-300">
                                    <input v-model="importForm.skip_invalid" type="checkbox" class="accent-cyan-500">
                                    SKIP_INVALID_ROWS
                                </label>

                                <div class="flex flex-wrap gap-2">
                                    <button type="submit" :disabled="importForm.processing" class="flex-1 py-3 border-2 border-cyan-400 text-cyan-300 hover:bg-cyan-400 hover:text-black uppercase font-bold transition-all disabled:opacity-50">
                                        {{ importForm.processing ? 'IMPORTING...' : 'IMPORT_JSON' }}
                                    </button>
                                    <a
                                        :href="importTemplate?.download_url"
                                        target="_blank"
                                        class="inline-flex items-center justify-center px-4 py-3 border-2 border-yellow-500 text-yellow-300 hover:bg-yellow-500 hover:text-black uppercase"
                                    >
                                        TEMPLATE
                                    </a>
                                </div>
                            </form>

                            <div v-if="importResult" class="mt-5 border border-slate-700 bg-black/30 p-3">
                                <p class="text-[8px] uppercase text-white">IMPORT_SUMMARY</p>
                                <div class="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-2 text-[8px] uppercase">
                                    <div class="border border-emerald-900/70 bg-emerald-900/10 px-3 py-2 text-emerald-300">SUCCESS: {{ importResult.success_count || 0 }}</div>
                                    <div class="border border-red-900/70 bg-red-900/10 px-3 py-2 text-red-300">FAILED: {{ importResult.failed_count || 0 }}</div>
                                    <div class="border border-slate-700 px-3 py-2 text-slate-300">MODE: {{ importResult.skipped_invalid ? 'SKIP_INVALID' : 'STRICT' }}</div>
                                </div>

                                <div v-if="importErrors.length > 0" class="mt-4">
                                    <p class="text-[8px] uppercase text-red-300 mb-2">ERROR_DETAILS</p>
                                    <div class="max-h-48 overflow-y-auto pr-2 custom-scroll space-y-2">
                                        <p v-for="(error, index) in importErrors" :key="`import-error-${index}`" class="text-[8px] font-sans text-red-300 break-words">
                                            {{ error }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-5 border border-slate-700 bg-black/20 p-3">
                                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2">
                                    <p class="text-[8px] uppercase text-white">FORMAT_REFERENCE</p>
                                    <a :href="importTemplate?.download_url" target="_blank" class="text-[8px] uppercase text-cyan-300 hover:text-white">[Download_JSON_Template]</a>
                                </div>

                                <div class="mt-3 space-y-2">
                                    <div
                                        v-for="field in (importTemplate?.fields || [])"
                                        :key="field.name"
                                        class="border border-slate-800 px-3 py-2"
                                    >
                                        <p class="text-[8px] uppercase text-yellow-300">
                                            {{ field.name }} <span class="text-slate-500">[{{ field.required ? 'required' : 'optional' }}]</span>
                                        </p>
                                        <p class="mt-1 text-[11px] font-sans text-slate-300">{{ field.description }}</p>
                                    </div>
                                </div>

                                <pre class="mt-4 overflow-x-auto border border-slate-800 bg-[#0b0f14] p-3 text-[10px] font-sans text-slate-300">{{ importTemplateJson }}</pre>
                            </div>
                        </div>

                    </div>
                </div>

                <div
                    v-if="showFormModal"
                    class="fixed inset-0 z-[120] flex items-center justify-center bg-black/85 backdrop-blur-sm p-4"
                >
                    <div class="w-full max-w-4xl max-h-[90vh] overflow-y-auto modal-scroll">
                        <div class="rpg-panel" :class="isEditing ? 'border-emerald-500/50' : 'border-teal-500/50'">
                            <h2 class="mb-5 uppercase tracking-tighter" :class="isEditing ? 'text-emerald-400' : 'text-teal-300'">
                                >> {{ isEditing ? 'UPDATE_TASK' : 'CREATE_TASK' }}
                            </h2>

                            <form @submit.prevent="submit" class="space-y-4">
                                <div>
                                    <label class="block mb-2 text-white uppercase">QUESTION:</label>
                                    <textarea v-model="form.question_text" class="w-full bg-black border-2 border-slate-700 p-2 text-[12px] font-sans text-slate-200 focus:border-teal-400 focus:ring-0" style="resize: vertical; min-height: 110px;" required></textarea>
                                    <p v-if="form.errors.question_text" class="mt-2 text-red-400 text-[8px]">{{ form.errors.question_text }}</p>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <div>
                                        <label class="block mb-2 text-white uppercase">QUESTION_TYPE:</label>
                                        <select v-model="form.question_type" class="w-full bg-black border-2 border-slate-700 p-2 text-yellow-300 uppercase outline-none focus:border-yellow-400">
                                            <option value="essay">ESSAY</option>
                                            <option value="multiple_choice">MULTIPLE_CHOICE</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block mb-2 text-white uppercase">WEIGHT:</label>
                                        <input v-model.number="form.weight" type="number" min="1" max="100" class="w-full bg-black border-2 border-slate-700 p-2 text-teal-300 outline-none focus:border-teal-400">
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <div>
                                        <label class="block mb-2 text-white uppercase">SORT_ORDER:</label>
                                        <input v-model.number="form.sort_order" type="number" min="1" class="w-full bg-black border-2 border-slate-700 p-2 text-teal-300 outline-none focus:border-teal-400">
                                    </div>
                                    <label class="inline-flex items-center gap-2 text-[9px] uppercase text-slate-300 md:mt-7">
                                        <input v-model="form.is_active" type="checkbox" class="accent-teal-500">
                                        TASK_ACTIVE
                                    </label>
                                </div>

                                <div v-if="isMcq">
                                    <div class="flex items-center justify-between gap-2 mb-2">
                                        <label class="block text-white uppercase">OPTIONS:</label>
                                        <button type="button" @click="addOption" class="px-2 py-1 border border-yellow-600 text-yellow-300 hover:bg-yellow-500 hover:text-black uppercase text-[8px]">
                                            + Add_Option
                                        </button>
                                    </div>
                                    <div class="space-y-2">
                                        <div v-for="(option, index) in form.options" :key="`opt-${index}`" class="flex items-center gap-2">
                                            <input
                                                type="radio"
                                                :name="`correct-answer-${taskBank.uuid}`"
                                                :value="index"
                                                v-model="form.correct_option_index"
                                                class="accent-yellow-400"
                                                :title="`Set opsi ${index + 1} sebagai jawaban benar`"
                                            />
                                            <input
                                                v-model="form.options[index]"
                                                type="text"
                                                class="flex-1 bg-black border-2 border-slate-700 p-2 text-[12px] font-sans text-slate-200 focus:border-yellow-400 outline-none"
                                                :placeholder="`Option ${index + 1}`"
                                            />
                                            <button
                                                type="button"
                                                @click="removeOption(index)"
                                                :disabled="form.options.length <= 2"
                                                class="px-2 py-2 border border-red-700 text-red-400 hover:bg-red-600 hover:text-white uppercase text-[8px] disabled:opacity-40 disabled:cursor-not-allowed"
                                            >
                                                X
                                            </button>
                                        </div>
                                    </div>
                                    <p class="mt-2 text-[8px] text-slate-400 uppercase">Pilih jawaban benar dengan radio di kiri opsi.</p>
                                    <p v-if="form.errors.options" class="mt-2 text-red-400 text-[8px]">{{ form.errors.options }}</p>
                                </div>
                                <p v-if="form.errors.answer_key" class="mt-2 text-red-400 text-[8px]">{{ form.errors.answer_key }}</p>

                                <div class="flex gap-2">
                                    <button type="submit" class="flex-1 py-3 border-2 border-teal-400 text-teal-300 hover:bg-teal-400 hover:text-black uppercase font-bold transition-all">{{ isEditing ? 'UPDATE' : 'CREATE' }}</button>
                                    <button type="button" @click="cancelEdit" class="px-4 py-3 border-2 border-slate-500 text-slate-500 hover:bg-slate-500 hover:text-white uppercase">X</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-span-12 lg:col-span-7">
                    <div class="rpg-panel border-slate-700 h-full">
                        <h2 class="text-white mb-6 uppercase tracking-tighter">>> TASK_LIST</h2>

                        <div class="mb-4 flex flex-col md:flex-row gap-2">
                            <input v-model="filterForm.search" type="text" placeholder="SEARCH QUESTION" class="flex-1 bg-black border-2 border-slate-700 p-2 text-teal-300 uppercase outline-none" @keyup.enter="applyFilters" />
                            <button @click="applyFilters" class="px-3 py-2 border-2 border-teal-400 text-teal-300 hover:bg-teal-400 hover:text-black uppercase">APPLY</button>
                            <button @click="resetFilters" class="px-3 py-2 border-2 border-slate-600 text-slate-300 hover:bg-slate-700 hover:text-white uppercase">RESET</button>
                        </div>

                        <div class="space-y-3 max-h-[560px] overflow-y-auto pr-2 custom-scroll">
                            <div v-for="task in rows" :key="task.uuid" class="p-3 bg-slate-900/50 border-l-4 border-teal-500">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0 flex-1">
                                        <p class="text-[8px] text-slate-500 uppercase">ID: {{ task.uuid.substring(0, 8) }}</p>
                                        <p class="text-[12px] font-sans text-slate-200 mt-2 break-words">{{ task.question_text }}</p>
                                        <div class="mt-2 flex flex-wrap gap-2 items-center">
                                            <span class="px-2 py-1 border text-[8px] uppercase" :class="typeClass(task.question_type)">{{ task.question_type }}</span>
                                            <span class="text-[8px] text-yellow-400">W: {{ task.weight }}</span>
                                            <span class="text-[8px] text-teal-300">SORT: {{ task.sort_order }}</span>
                                            <span :class="task.is_active ? 'text-emerald-400' : 'text-red-400'" class="text-[8px]">{{ task.is_active ? 'ACTIVE' : 'INACTIVE' }}</span>
                                        </div>
                                        <div v-if="task.question_type === 'multiple_choice' && Array.isArray(task.options_json)" class="mt-2 text-[8px] text-slate-400 font-sans">
                                            OPTIONS: {{ task.options_json.join(' | ') }}
                                        </div>
                                        <div v-if="task.question_type === 'multiple_choice'" class="text-[8px] text-cyan-400 font-sans mt-1">ANSWER: {{ task.answer_key || '-' }}</div>
                                    </div>
                                    <div class="flex flex-col gap-2 shrink-0">
                                        <button @click="startEdit(task)" class="text-emerald-400 hover:text-white text-[8px] uppercase">[Edit]</button>
                                        <button @click="destroyTask(task.uuid)" class="text-red-400 hover:text-white text-[8px] uppercase">[Delete]</button>
                                    </div>
                                </div>
                            </div>
                            <p v-if="rows.length === 0" class="text-[8px] text-slate-500 uppercase text-center py-8">NO_TASKS_FOUND</p>
                        </div>

                        <div class="mt-5 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                            <p class="text-[8px] text-slate-500 uppercase">PAGE {{ questions.current_page || 1 }} / {{ questions.last_page || 1 }} | TOTAL {{ questions.total || 0 }}</p>
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
