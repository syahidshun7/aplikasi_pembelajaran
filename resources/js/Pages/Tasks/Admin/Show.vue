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
const importFileInputRef = ref(null);

const filterForm = useForm({
    search: props.filters?.search || '',
});

const form = useForm({
    question_text: '',
    question_type: 'essay',
    options: ['', ''],
    correct_option_index: null,
    // Word match fields
    word_match_blanks: [''],
    word_match_distractors: [''],
    // Platforming fields
    platforming_stages: [{ prompt: '', correct_answer: '', wrong_answers: [''] }],
    weight: 1,
    sort_order: 1,
    is_active: true,
});

const importForm = useForm({
    import_file: null,
    import_json_text: '',
    skip_invalid: true,
});

const rows = computed(() => props.questions?.data || []);
const paginationLinks = computed(() => props.questions?.links || []);
const importErrors = computed(() => Array.isArray(props.importResult?.errors) ? props.importResult.errors : []);
const importTemplateJson = computed(() => JSON.stringify(props.importTemplate?.sample || [], null, 2));
const importJsonInputPlaceholder = computed(() => {
    const type = props.taskBank?.assessment_type || 'mixed';
    if (type === 'platforming') {
        return 'Paste JSON di sini. 1 item = 1 soal.\n[{"pertanyaan":"Ibu kota Indonesia?","tipe_soal":"platforming","stages":[{"prompt":"Ibu kota Indonesia?","correct_answer":"Jakarta","wrong_answers":["Bandung","Surabaya"]}]},{"pertanyaan":"Planet terbesar?","tipe_soal":"platforming","stages":[{"prompt":"Planet terbesar?","correct_answer":"Jupiter","wrong_answers":["Mars","Venus"]}]}]';
    }
    if (type === 'word_match') {
        return 'Paste JSON di sini. Contoh multi soal:\n[{"pertanyaan":"Kalimat dengan ___ kosong","tipe_soal":"word_match","sentence":"Indonesia merdeka tahun ___","blanks":["1945"],"distractors":["2000","1908"]},{"pertanyaan":"Kalimat kedua ___","tipe_soal":"word_match","sentence":"Ibu kota Indonesia adalah ___","blanks":["Jakarta"],"distractors":["Bandung","Surabaya"]}]';
    }
    if (type === 'game_stage') {
        return 'Paste JSON di sini. Contoh: [{"pertanyaan":"...","tipe_soal":"game_stage","prompt":"...","accepted_answers":["..."],"hint":"...","max_attempts":3}]';
    }
    if (type === 'multiple_choice') {
        return 'Paste JSON di sini. Contoh: [{"pertanyaan":"...","tipe_soal":"multiple_choice","opsi":{"A":"...","B":"..."},"jawaban":"A"}]';
    }
    if (type === 'essay') {
        return 'Paste JSON di sini. Contoh: [{"pertanyaan":"...","tipe_soal":"essay","bobot":1}]';
    }
    return 'Paste JSON di sini. Contoh: [{"pertanyaan":"...","tipe_soal":"platforming","stages":[{"prompt":"...","correct_answer":"..."}]},{"pertanyaan":"...","tipe_soal":"word_match","sentence":"... ___ ...","blanks":["..."]}]';
});

const isMcq = computed(() => form.question_type === 'multiple_choice');
const isGameStage = computed(() => form.question_type === 'game_stage');
const isPlatforming = computed(() => form.question_type === 'platforming');
const isWordMatch = computed(() => form.question_type === 'word_match');
const availableQuestionTypes = computed(() => {
    const bankType = props.taskBank?.assessment_type || 'mixed';
    if (bankType === 'essay') return ['essay'];
    if (bankType === 'multiple_choice') return ['multiple_choice'];
    if (bankType === 'platforming') return ['platforming'];
    if (bankType === 'word_match') return ['word_match'];
    return ['essay', 'multiple_choice', 'game_stage', 'platforming', 'word_match'];
});

const questionTypeOptions = [
    { value: 'essay', label: 'ESSAY' },
    { value: 'multiple_choice', label: 'MULTIPLE_CHOICE' },
    { value: 'game_stage', label: 'GAME_STAGE' },
    { value: 'platforming', label: 'PLATFORMING' },
    { value: 'word_match', label: 'WORD_MATCH' },
];

const startEdit = (row) => {
    isEditing.value = true;
    editUuid.value = row.uuid;
    form.question_text = row.question_text || '';
    form.question_type = row.question_type || 'essay';
    if (!availableQuestionTypes.value.includes(form.question_type)) {
        form.question_type = availableQuestionTypes.value[0] || 'essay';
    }
    form.options = Array.isArray(row.options_json) && row.options_json.length > 0
        ? row.options_json
        : ['', ''];
    form.correct_option_index = Array.isArray(row.options_json)
        ? row.options_json.findIndex((option) => option === row.answer_key)
        : null;
    if (form.correct_option_index < 0) {
        form.correct_option_index = null;
    }

    if (form.question_type === 'word_match' && row.options_json) {
        form.word_match_blanks = row.options_json.blanks || [''];
        form.word_match_distractors = row.options_json.distractors || [''];
    }
    if (form.question_type === 'platforming' && row.options_json) {
        // If it's a platforming task, it might be nested inside an object, check structure
        form.platforming_stages = (row.options_json.stages || row.options_json) instanceof Array
            ? (row.options_json.stages || row.options_json)
            : [{ prompt: '', correct_answer: '', wrong_answers: [''] }];
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
    form.word_match_blanks = [''];
    form.word_match_distractors = [''];
    form.platforming_stages = [{ prompt: '', correct_answer: '', wrong_answers: [''] }];
    form.weight = 1;
    form.sort_order = 1;
    form.is_active = true;
    showFormModal.value = false;
};

const openCreateModal = () => {
    cancelEdit();
    if (!availableQuestionTypes.value.includes(form.question_type)) {
        form.question_type = availableQuestionTypes.value[0] || 'essay';
    }
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
        : null;

    let payloadOptions = cleanedOptions;
    if (isWordMatch.value) {
        const blanks = form.word_match_blanks.filter(b => b.trim() !== '');
        const underscoreCount = (form.question_text.match(/___/g) || []).length;
        if (blanks.length === 0) {
            form.errors.question_text = 'Minimal harus ada 1 blank (jawaban benar).';
            return;
        }
        if (underscoreCount !== blanks.length) {
            form.errors.question_text = `Jumlah ___ di kalimat (${underscoreCount}) harus sama dengan jumlah blanks (${blanks.length}).`;
            return;
        }
        payloadOptions = [{
            sentence: form.question_text,
            blanks,
            distractors: form.word_match_distractors.filter(d => d.trim() !== '')
        }];
    } else if (isPlatforming.value) {
        const stage = form.platforming_stages[0];
        if (!stage.prompt.trim() || !stage.correct_answer.trim()) {
            form.errors.question_text = 'Pertanyaan dan jawaban benar wajib diisi.';
            return;
        }
        const wrongAnswers = stage.wrong_answers.filter(wa => wa.trim() !== '');
        if (wrongAnswers.length < 1) {
            form.errors.question_text = 'Minimal 1 pengecoh wajib diisi.';
            return;
        }
        payloadOptions = [{
            stages: [{ prompt: stage.prompt, correct_answer: stage.correct_answer, wrong_answers: wrongAnswers }]
        }];
    }

    const payload = {
        question_text: form.question_text,
        question_type: form.question_type,
        options: payloadOptions,
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
    if (type === 'game_stage') return 'text-emerald-300 border-emerald-800 bg-emerald-900/20';
    if (type === 'platforming') return 'text-purple-300 border-purple-800 bg-purple-900/20';
    if (type === 'word_match') return 'text-orange-300 border-orange-800 bg-orange-900/20';
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
            importForm.import_file = null;
            importForm.import_json_text = '';
            importForm.skip_invalid = true;
            if (importFileInputRef.value) {
                importFileInputRef.value.value = '';
            }
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
                                        ref="importFileInputRef"
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

                                <div class="border border-slate-700 bg-black/20 p-3">
                                    <p class="text-[8px] uppercase text-slate-300 mb-2">ATAU</p>
                                    <label class="block mb-2 text-white uppercase">JSON_INPUT (PASTE):</label>
                                    <textarea
                                        v-model="importForm.import_json_text"
                                        class="w-full bg-black border-2 border-slate-700 p-2 text-[11px] font-sans text-slate-200 focus:border-cyan-400 focus:ring-0"
                                        style="resize: vertical; min-height: 150px;"
                                        :placeholder="importJsonInputPlaceholder"
                                    ></textarea>
                                    <p class="mt-2 text-[8px] text-slate-400 uppercase">
                                        Bisa paste JSON array langsung tanpa upload file.
                                    </p>
                                    <p v-if="importForm.errors.import_json_text" class="mt-2 text-red-400 text-[8px]">{{ importForm.errors.import_json_text }}</p>
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
                                    <div class="flex flex-wrap gap-2">
                                        <a :href="importTemplate?.download_url" target="_blank" class="text-[8px] uppercase text-cyan-300 hover:text-white">[Download_JSON_Template]</a>
                                    </div>
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
                                <div v-if="!isPlatforming">
                                    <div>
                                        <label class="block mb-2 text-white uppercase">{{ isWordMatch ? 'SENTENCE:' : 'QUESTION:' }}</label>
                                        <textarea v-model="form.question_text" class="w-full bg-black border-2 border-slate-700 p-2 text-[12px] font-sans text-slate-200 focus:border-teal-400 focus:ring-0" style="resize: vertical; min-height: 110px;" :placeholder="isWordMatch ? 'Tulis kalimat dengan ___ sebagai tempat kosong. Contoh: Indonesia merdeka pada tahun ___' : ''" required></textarea>
                                        <p v-if="isWordMatch" class="mt-1 text-[8px] text-orange-300 uppercase">Gunakan ___ (3 underscore) untuk menandai setiap blank. Jumlah ___ harus sama dengan jumlah BLANKS.</p>
                                        <p v-if="form.errors.question_text" class="mt-2 text-red-400 text-[8px]">{{ form.errors.question_text }}</p>
                                    </div>

                                    <div v-if="availableQuestionTypes.length > 1" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                        <div>
                                            <label class="block mb-2 text-white uppercase">QUESTION_TYPE:</label>
                                            <select v-model="form.question_type" class="w-full bg-black border-2 border-slate-700 p-2 text-yellow-300 uppercase outline-none focus:border-yellow-400">
                                                <option
                                                    v-for="opt in questionTypeOptions.filter((opt) => availableQuestionTypes.includes(opt.value))"
                                                    :key="opt.value"
                                                    :value="opt.value"
                                                >
                                                    {{ opt.label }}
                                                </option>
                                            </select>
                                        </div>
                                        <div v-if="!isWordMatch && !isPlatforming">
                                            <label class="block mb-2 text-white uppercase">WEIGHT:</label>
                                            <input v-model.number="form.weight" type="number" min="1" max="100" class="w-full bg-black border-2 border-slate-700 p-2 text-teal-300 outline-none focus:border-teal-400">
                                        </div>
                                    </div>
                                </div>
                                <div v-else>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                        <div>
                                            <label class="block mb-2 text-white uppercase">QUESTION_TYPE:</label>
                                            <select v-model="form.question_type" class="w-full bg-black border-2 border-slate-700 p-2 text-yellow-300 uppercase outline-none focus:border-yellow-400">
                                                <option
                                                    v-for="opt in questionTypeOptions.filter((opt) => availableQuestionTypes.includes(opt.value))"
                                                    :key="opt.value"
                                                    :value="opt.value"
                                                >
                                                    {{ opt.label }}
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div v-if="!isWordMatch && !isPlatforming" class="grid grid-cols-1 md:grid-cols-2 gap-3">
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
                                <div v-else-if="isGameStage">
                                    <label class="block mb-2 text-white uppercase">GAME_STAGE_CONFIG_JSON:</label>
                                    <textarea
                                        v-model="form.options[0]"
                                        class="w-full bg-black border-2 border-slate-700 p-2 text-[12px] font-sans text-slate-200 focus:border-emerald-400 focus:ring-0"
                                        style="resize: vertical; min-height: 140px;"
                                        placeholder='{"prompt":"...","accepted_answers":["kode"],"hint":"...","max_attempts":3}'
                                    ></textarea>
                                    <p class="mt-2 text-[8px] text-slate-400 uppercase">
                                        Untuk GAME_STAGE, isi JSON config. accepted_answers minimal 1 item.
                                    </p>
                                </div>
                                <div v-else-if="isWordMatch" class="space-y-4">
                                    <div class="p-4 border-2 border-emerald-800/50 bg-emerald-950/20 rounded">
                                        <label class="block mb-3 text-emerald-300 uppercase tracking-wider text-[10px] font-bold">BLANKS (Jawaban yang Benar):</label>
                                        <p class="text-[8px] text-slate-400 mb-3">Isi jawaban untuk setiap ___ di kalimat. Urutan harus sesuai.</p>
                                        <div v-for="(blank, idx) in form.word_match_blanks" :key="`blank-${idx}`" class="flex items-center gap-2 mb-2">
                                            <span class="text-[9px] text-emerald-400 w-5">{{ idx + 1 }}.</span>
                                            <input v-model="form.word_match_blanks[idx]" type="text" class="flex-1 bg-black border-2 border-slate-700 p-2 text-slate-200 focus:border-emerald-400 outline-none text-[12px]" placeholder="Jawaban..." />
                                            <button type="button" @click="form.word_match_blanks.splice(idx, 1)" :disabled="form.word_match_blanks.length <= 1" class="px-3 py-2 bg-red-900/40 text-red-400 hover:bg-red-600 hover:text-white uppercase text-[10px] disabled:opacity-30 disabled:cursor-not-allowed">X</button>
                                        </div>
                                        <button type="button" @click="form.word_match_blanks.push('')" class="text-[10px] text-emerald-300 border border-emerald-700 px-3 py-2 mt-2 hover:bg-emerald-900/50 rounded">+ Tambah Blank</button>
                                    </div>

                                    <div class="p-4 border-2 border-orange-800/50 bg-orange-950/20 rounded">
                                        <label class="block mb-3 text-orange-300 uppercase tracking-wider text-[10px] font-bold">DISTRACTORS (Pengecoh):</label>
                                        <p class="text-[8px] text-slate-400 mb-3">Kata-kata pengecoh yang akan muncul sebagai pilihan tambahan. Opsional.</p>
                                        <div v-for="(dist, idx) in form.word_match_distractors" :key="`dist-${idx}`" class="flex items-center gap-2 mb-2">
                                            <span class="text-[9px] text-orange-400 w-5">{{ idx + 1 }}.</span>
                                            <input v-model="form.word_match_distractors[idx]" type="text" class="flex-1 bg-black border-2 border-slate-700 p-2 text-slate-200 focus:border-orange-400 outline-none text-[12px]" placeholder="Pengecoh..." />
                                            <button type="button" @click="form.word_match_distractors.splice(idx, 1)" class="px-3 py-2 bg-red-900/40 text-red-400 hover:bg-red-600 hover:text-white uppercase text-[10px]">X</button>
                                        </div>
                                        <button type="button" @click="form.word_match_distractors.push('')" class="text-[10px] text-orange-300 border border-orange-700 px-3 py-2 mt-2 hover:bg-orange-900/50 rounded">+ Tambah Pengecoh</button>
                                    </div>
                                </div>
                                <div v-if="form.question_type === 'platforming'" class="space-y-4">
                                    <div class="p-4 border-2 border-purple-800/50 bg-purple-950/20 rounded">
                                        <label class="block mb-3 text-purple-300 uppercase tracking-wider text-[10px] font-bold">PERTANYAAN (Prompt):</label>
                                        <input v-model="form.platforming_stages[0].prompt" placeholder="Tulis pertanyaan..." class="w-full bg-black p-2 mb-3 border-2 border-slate-700 text-slate-200 focus:border-purple-400 outline-none text-[12px]" required />

                                        <label class="block mb-2 text-emerald-300 uppercase tracking-wider text-[10px] font-bold">JAWABAN BENAR:</label>
                                        <input v-model="form.platforming_stages[0].correct_answer" placeholder="Jawaban benar..." class="w-full bg-black p-2 mb-3 border-2 border-emerald-700 text-emerald-200 focus:border-emerald-400 outline-none text-[12px]" required />

                                        <label class="block mb-2 text-orange-300 uppercase tracking-wider text-[10px] font-bold">PENGECOH:</label>
                                        <div v-for="(wa, waIdx) in form.platforming_stages[0].wrong_answers" :key="`wa-${waIdx}`" class="flex items-center gap-2 mb-2">
                                            <span class="text-[9px] text-orange-400 w-5">{{ waIdx + 1 }}.</span>
                                            <input v-model="form.platforming_stages[0].wrong_answers[waIdx]" placeholder="Pengecoh..." class="flex-1 bg-black p-2 border-2 border-slate-700 text-slate-200 focus:border-orange-400 outline-none text-[12px]" />
                                            <button type="button" @click="form.platforming_stages[0].wrong_answers.splice(waIdx, 1)" :disabled="form.platforming_stages[0].wrong_answers.length <= 1" class="px-3 py-2 bg-red-900/40 text-red-400 hover:bg-red-600 hover:text-white uppercase text-[10px] disabled:opacity-30 disabled:cursor-not-allowed">X</button>
                                        </div>
                                        <button type="button" @click="form.platforming_stages[0].wrong_answers.push('')" class="text-[10px] text-orange-300 border border-orange-700 px-3 py-2 mt-2 hover:bg-orange-900/50 rounded">+ Tambah Pengecoh</button>
                                    </div>
                                </div>                                <p v-if="form.errors.answer_key" class="mt-2 text-red-400 text-[8px]">{{ form.errors.answer_key }}</p>

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
                                            <span v-if="task.question_type !== 'platforming' && task.question_type !== 'word_match'" class="text-[8px] text-yellow-400">W: {{ task.weight }}</span>
                                            <span v-if="task.question_type !== 'platforming' && task.question_type !== 'word_match'" class="text-[8px] text-teal-300">SORT: {{ task.sort_order }}</span>
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
