<script setup>
import { Head, useForm, Link, usePage, router } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue'; 
import AdminNavbar from '@/Components/AdminNavbar.vue';
import Swal from 'sweetalert2';
import axios from 'axios';

const props = defineProps({
    quests: Object,
    studyGroups: Array,
    taskBanks: Array,
    jobRoles: Array,
    rubrics: Array,
    filters: Object,
});
const page = usePage();
const isMentor = computed(() => String(page.props?.auth?.user?.role || '').toLowerCase() === 'mentor');
const isAdminScope = computed(() => !isMentor.value);
const firstStudyGroupId = computed(() => props.studyGroups?.[0]?.id ?? null);
const firstTaskBankId = computed(() => props.taskBanks?.[0]?.id ?? null);

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
const showFormModal = ref(false);
const showDeleteModal = ref(false);
const questIdToDelete = ref(null);
const filterForm = useForm({
    search: props.filters?.search || '',
    view: props.filters?.view || 'active',
});
const isTrashView = computed(() => filterForm.view === 'trash');

const questItems = computed(() => props.quests?.data || []);
const paginationLinks = computed(() => props.quests?.links || []);
const selectedJobScope = ref('');
const showAiGeneratorModal = ref(false);
const aiScopeForm = useForm({
    job_id: '',
    study_group_id: '',
    theme: '',
    ai_note: '',
    question_type: 'mixed',
    question_count: 10,
    difficulty: 'C-Rank',
    publish_mode: 'draft',
    available_from: '',
    available_until: '',
    deadline: '',
});
const isGeneratingThemePreview = ref(false);
const isCommittingThemeBundle = ref(false);
const themePreview = ref(null);
const currentRole = computed(() => String(page.props?.auth?.user?.role || '').toLowerCase());
const isSuperAdmin = computed(() => currentRole.value === 'super_admin');
const canUseOptionalQuestAi = computed(() => ['super_admin', 'admin', 'mentor'].includes(currentRole.value));
const mentorJobId = computed(() => {
    const value = Number(page.props?.auth?.user?.job_id || 0);
    return Number.isFinite(value) ? value : 0;
});

const filteredStudyGroups = computed(() => {
    if (isMentor.value || selectedJobScope.value === '') {
        return props.studyGroups || [];
    }

    return (props.studyGroups || []).filter((group) => String(group.job_id || '') === selectedJobScope.value);
});

const filteredTaskBanks = computed(() => {
    if (isMentor.value || selectedJobScope.value === '') {
        return props.taskBanks || [];
    }

    return (props.taskBanks || []).filter((bank) => String(bank.job_role_id || '') === selectedJobScope.value);
});

const taskBankSearch = ref('');
const searchableTaskBanks = computed(() => {
    const q = taskBankSearch.value.trim().toLowerCase();
    if (!q) return filteredTaskBanks.value;
    return filteredTaskBanks.value.filter((bank) =>
        bank.name.toLowerCase().includes(q) || (bank.assessment_type || '').toLowerCase().includes(q)
    );
});

const aiFilteredStudyGroups = computed(() => {
    if (isMentor.value || aiScopeForm.job_id === '') {
        return props.studyGroups || [];
    }

    return (props.studyGroups || []).filter((group) => String(group.job_id || '') === String(aiScopeForm.job_id));
});

const form = useForm({
    title: '',
    difficulty: 'C-Rank', 
    reward_gold: 500,
    reward_exp: 500,
    description: '',
    quest_type: 'main',
    is_active: true,
    study_group_id: null,
    task_bank_id: null,
    rubric_id: null,
    deadline: '',
    schedule_type: 'manual',
    available_from: '',
    available_until: '',
});
const mentorCannotSubmitQuest = computed(() => isMentor.value && !form.study_group_id && !form.task_bank_id);

const applyMentorDefaults = () => {
    if (!isMentor.value) return;
    if (!form.study_group_id && !form.task_bank_id) {
        form.study_group_id = firstStudyGroupId.value;
        if (!form.study_group_id) {
            form.task_bank_id = firstTaskBankId.value;
        }
    }
};

watch(() => form.difficulty, (newDifficulty) => {
    if (newDifficulty) {
        form.reward_gold = rankGoldMap[newDifficulty] || 0;
        form.reward_exp = rankGoldMap[newDifficulty] || 0;
    }
});

watch(() => form.task_bank_id, (newTaskBankId) => {
    if (newTaskBankId) {
        form.rubric_id = null;
    }
});

watch(() => form.schedule_type, (scheduleType) => {
    if (scheduleType === 'once') {
        form.is_active = true;
        return;
    }

    form.available_from = '';
    form.available_until = '';
});

watch(() => usePage().props.flash, (flash) => {
    if (flash?.message) {
        Toast.fire({
            icon: 'success',
            title: flash.message
        });
    }
}, { deep: true });

watch([isMentor, firstStudyGroupId, firstTaskBankId], () => {
    applyMentorDefaults();
}, { immediate: true });

watch(selectedJobScope, (jobId) => {
    if (isMentor.value || jobId === '') {
        return;
    }

    if (form.study_group_id && !filteredStudyGroups.value.some((group) => String(group.id) === String(form.study_group_id))) {
        form.study_group_id = null;
    }

    if (form.task_bank_id && !filteredTaskBanks.value.some((bank) => String(bank.id) === String(form.task_bank_id))) {
        form.task_bank_id = null;
    }
});

watch([isMentor, mentorJobId], () => {
    if (!isMentor.value) return;
    if (mentorJobId.value > 0) {
        aiScopeForm.job_id = String(mentorJobId.value);
    }
    if (!aiScopeForm.study_group_id && firstStudyGroupId.value) {
        aiScopeForm.study_group_id = String(firstStudyGroupId.value);
    }
}, { immediate: true });

watch(() => aiScopeForm.job_id, () => {
    if (!aiScopeForm.study_group_id) {
        return;
    }

    const exists = aiFilteredStudyGroups.value.some((group) => String(group.id) === String(aiScopeForm.study_group_id));
    if (!exists) {
        aiScopeForm.study_group_id = '';
    }
});

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

const formatSchedule = (date) => {
    if (!date) return 'NO_LIMIT';
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

const formatDateTimeLocal = (value) => {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';
    const pad = (number) => String(number).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
};

const startEdit = (quest) => {
    showAiGeneratorModal.value = false;
    isEditing.value = true;
    editId.value = quest.uuid;
    form.title = quest.title;
    form.difficulty = quest.difficulty;
    form.reward_gold = quest.reward_gold;
    form.reward_exp = quest.reward_exp ?? quest.reward_gold ?? 0;
    form.description = quest.description || '';
    form.quest_type = quest.quest_type || 'main';
    const isScheduledOnceQuest = String(quest.schedule_type || 'manual') === 'once';
    // Manual quest legacy bisa pernah berstatus Done; anggap tetap aktif selama bukan In-Progress.
    form.is_active = isScheduledOnceQuest
        ? true
        : String(quest.status || 'Available') !== 'In-Progress';
    form.study_group_id = quest.study_group_id;
    form.task_bank_id = quest.task_bank_id;
    form.rubric_id = quest.rubric_id ?? null;
    selectedJobScope.value = String(quest.study_group?.job_id ?? quest.task_bank?.job_role_id ?? '');
    
    form.deadline = quest.deadline ? formatDateTimeLocal(quest.deadline) : '';
    form.schedule_type = quest.schedule_type || 'manual';
    form.available_from = quest.available_from ? formatDateTimeLocal(quest.available_from) : '';
    form.available_until = quest.available_until ? formatDateTimeLocal(quest.available_until) : '';

    showFormModal.value = true;
    Toast.fire({ icon: 'info', title: 'MODIFYING_CONTRACT' });
};

const cancelEdit = () => {
    isEditing.value = false;
    editId.value = null;
    showAiGeneratorModal.value = false;
    themePreview.value = null;
    form.reset();
    form.rubric_id = null;
    selectedJobScope.value = '';
    taskBankSearch.value = '';
    applyMentorDefaults();
    showFormModal.value = false;
};

const openCreateModal = () => {
    cancelEdit();
    showAiGeneratorModal.value = false;
    showFormModal.value = true;
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
                cancelEdit();
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

const restoreQuest = (uuid) => {
    router.patch(route('quests.restore', uuid), {}, {
        preserveScroll: true,
    });
};

const hardDeleteQuest = (uuid) => {
    Swal.fire({
        title: 'HARD_DELETE_MISSION?',
        text: 'Quest akan dihapus permanen dan tidak bisa dipulihkan.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'DELETE_PERMANENTLY',
        cancelButtonText: 'CANCEL',
        background: '#1a1c2c',
        color: '#4ed4d4',
        confirmButtonColor: '#dc2626',
    }).then((result) => {
        if (!result.isConfirmed) return;
        router.delete(route('quests.force-destroy', uuid), {
            preserveScroll: true,
        });
    });
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

const setView = (view) => {
    if (filterForm.view === view) return;
    filterForm.view = view;
    cancelEdit();
    applyFilters();
};

const goToPage = (url) => {
    if (!url) return;
    router.get(url, {}, {
        preserveState: true,
        preserveScroll: true,
    });
};

const generateThemePreview = async () => {
    if (!canUseOptionalQuestAi.value) return;
    if (!aiScopeForm.theme.trim()) {
        Swal.fire('INPUT_REQUIRED', 'Tema wajib diisi sebelum generate.', 'warning');
        return;
    }
    isGeneratingThemePreview.value = true;
    try {
        const payload = {
            theme: aiScopeForm.theme.trim(),
            ai_note: aiScopeForm.ai_note.trim() || null,
            question_type: aiScopeForm.question_type,
            question_count: Number(aiScopeForm.question_count || 10),
            difficulty: aiScopeForm.difficulty,
        };
        if (aiScopeForm.job_id) payload.job_id = Number(aiScopeForm.job_id);
        if (aiScopeForm.study_group_id) payload.study_group_id = Number(aiScopeForm.study_group_id);

        const response = await axios.post(route('admin.quests.optional.theme-preview'), payload);
        themePreview.value = response?.data || null;
        Toast.fire({ icon: 'success', title: 'THEME_QUEST_PREVIEW_READY' });
    } catch (error) {
        const message = String(error?.response?.data?.message || 'FAILED_TO_GENERATE_THEME_PREVIEW');
        Swal.fire('UPLINK_ERROR', message, 'error');
    } finally {
        isGeneratingThemePreview.value = false;
    }
};

const commitThemeBundle = async () => {
    const bundle = themePreview.value?.bundle;
    if (!bundle) {
        Swal.fire('NO_BUNDLE', 'Generate theme preview dulu.', 'warning');
        return;
    }
    if (aiScopeForm.publish_mode === 'schedule' && !aiScopeForm.available_from) {
        Swal.fire('INPUT_REQUIRED', 'Publish At wajib diisi jika mode Schedule.', 'warning');
        return;
    }
    if (!Array.isArray(bundle.questions) || bundle.questions.length < 1) {
        Swal.fire('INVALID_BUNDLE', 'Bundle soal kosong. Generate ulang preview.', 'warning');
        return;
    }

    isCommittingThemeBundle.value = true;
    try {
        const payload = {
            bundle: JSON.parse(JSON.stringify(bundle)),
            publish_mode: aiScopeForm.publish_mode,
            available_from: aiScopeForm.available_from || null,
            available_until: aiScopeForm.available_until || null,
            deadline: aiScopeForm.deadline || null,
        };
        if (aiScopeForm.job_id) payload.job_id = Number(aiScopeForm.job_id);
        if (aiScopeForm.study_group_id) payload.study_group_id = Number(aiScopeForm.study_group_id);

        const response = await axios.post(route('admin.quests.optional.commit-theme'), payload);
        const message = String(response?.data?.message || 'OPTIONAL_QUEST_AI_BUNDLE_COMMITTED');
        Toast.fire({ icon: 'success', title: message });
        themePreview.value = null;
        showAiGeneratorModal.value = false;
        showFormModal.value = false;
        router.reload({ only: ['quests'], preserveState: true, preserveScroll: true });
    } catch (error) {
        const backendMessage = String(error?.response?.data?.message || '').trim();
        const validationErrors = error?.response?.data?.errors;
        const firstValidation = validationErrors && typeof validationErrors === 'object'
            ? Object.values(validationErrors)?.[0]?.[0]
            : '';
        const message = String(firstValidation || backendMessage || 'FAILED_TO_COMMIT_THEME_BUNDLE');
        Swal.fire('COMMIT_FAILED', message, 'error');
    } finally {
        isCommittingThemeBundle.value = false;
    }
};
</script>

<template>
    <Head title="GUILD_BOARD" />

    <div class="min-h-screen bg-[#0d1117] p-4 md:p-8 font-['Press_Start_2P'] text-[#4ed4d4] text-[10px] relative">
        <div class="max-w-7xl mx-auto space-y-8">

            <AdminNavbar />

            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3 border-b-4 border-cyan-900 pb-4">
                <h1 class="text-base sm:text-xl uppercase tracking-widest animate-pulse">Quest_Management_System</h1>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="openCreateModal"
                        class="inline-flex items-center justify-center px-3 py-2 border border-cyan-500 bg-cyan-900/20 text-cyan-300 hover:bg-cyan-500 hover:text-black transition-colors uppercase text-[9px] sm:text-[10px]"
                    >
                        [New_Quest]
                    </button>
                    <Link href="/dashboard" class="inline-flex items-center justify-center px-3 py-2 border border-slate-600 bg-slate-900/40 text-slate-300 hover:text-white transition-colors uppercase text-[9px] sm:text-[10px]">[Back_to_HQ]</Link>
                </div>
            </div>

            <div class="grid grid-cols-12 gap-8">
                <div
                    v-if="showFormModal"
                    class="fixed inset-0 z-[120] flex items-center justify-center bg-black/85 backdrop-blur-sm p-4"
                >
                    <div class="w-full max-w-5xl max-h-[90vh] overflow-y-auto modal-scroll">
                    <div class="rpg-panel" :class="isEditing ? 'border-green-500/50' : 'border-yellow-500/50'">
                        <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <h2 class="uppercase tracking-tighter" :class="isEditing ? 'text-green-500' : 'text-yellow-500'">
                                >> {{ isEditing ? 'UPDATE_CONTRACT_ID_' + editId.substring(0,8) : 'ISSUE_NEW_QUEST' }}
                            </h2>
                            <button
                                v-if="!isEditing && canUseOptionalQuestAi"
                                type="button"
                                @click="showAiGeneratorModal = !showAiGeneratorModal"
                                class="px-3 py-2 border-2 border-amber-500 text-amber-300 hover:bg-amber-500 hover:text-black uppercase text-[8px]"
                            >
                                {{ showAiGeneratorModal ? 'BACK_TO_MANUAL_FORM' : 'CREATE_WITH_AI' }}
                            </button>
                        </div>

                        <form v-if="!showAiGeneratorModal" @submit.prevent="submit" class="space-y-6">
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
                                    <label class="block mb-2 text-white">QUEST_TYPE:</label>
                                    <select v-model="form.quest_type"
                                        class="w-full bg-black border-2 border-slate-700 p-2 focus:border-lime-400 outline-none text-lime-300 uppercase">
                                        <option value="main">MAIN_QUEST</option>
                                        <option value="optional">OPTIONAL_BONUS</option>
                                    </select>
                                    <p class="mt-2 text-[7px] text-slate-500 uppercase italic">
                                        *Optional quest hanya memberi exp dan gold, tidak masuk average akademik.
                                    </p>
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
                                    <label class="block mb-2 text-white">QUEST_ACTIVE:</label>
                                    <label class="inline-flex items-center gap-3 select-none">
                                        <input v-model="form.is_active" type="checkbox"
                                            class="h-4 w-4 accent-cyan-400" />
                                        <span class="text-[8px] uppercase" :class="form.is_active ? 'text-emerald-400' : 'text-orange-400'">
                                            {{ form.is_active ? 'ACTIVE (AVAILABLE)' : 'INACTIVE (IN-PROGRESS)' }}
                                        </span>
                                    </label>
                                    <p class="mt-2 text-[7px] text-slate-500 uppercase italic">
                                        *Mode manual memakai toggle ini. Mode scheduled akan aktif/nonaktif otomatis.
                                    </p>
                                </div>
                                <div>
                                    <label class="block mb-2 text-white text-orange-500">SET_DEADLINE:</label>
                                    <input v-model="form.deadline" type="datetime-local"
                                        class="w-full bg-black border-2 border-slate-700 p-2 focus:border-orange-500 outline-none text-orange-400 uppercase text-[8px]">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block mb-2 text-white">SCHEDULE_MODE:</label>
                                    <select v-model="form.schedule_type"
                                        class="w-full bg-black border-2 border-slate-700 p-2 focus:border-fuchsia-400 outline-none text-fuchsia-300 uppercase">
                                        <option value="manual">MANUAL</option>
                                        <option value="once">SCHEDULED_ONCE</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block mb-2 text-white text-fuchsia-400">SHOW_FROM:</label>
                                    <input v-model="form.available_from" type="datetime-local"
                                        class="w-full bg-black border-2 border-slate-700 p-2 focus:border-fuchsia-500 outline-none text-fuchsia-300 uppercase text-[8px]"
                                        :disabled="form.schedule_type !== 'once'">
                                </div>
                                <div>
                                    <label class="block mb-2 text-white text-fuchsia-400">HIDE_AT:</label>
                                    <input v-model="form.available_until" type="datetime-local"
                                        class="w-full bg-black border-2 border-slate-700 p-2 focus:border-fuchsia-500 outline-none text-fuchsia-300 uppercase text-[8px]"
                                        :disabled="form.schedule_type !== 'once'">
                                </div>
                            </div>
                            <p class="text-[7px] text-slate-500 uppercase italic">
                                *Scheduled quest cocok untuk event/limited window. Daily reward quest tetap memakai sistem daily quest terpisah agar tidak mengganggu average.
                            </p>

                            <div v-if="isAdminScope">
                                <label class="block mb-2 text-white">JOB_SCOPE_FILTER:</label>
                                <select v-model="selectedJobScope"
                                    class="w-full bg-black border-2 border-slate-700 p-2 focus:border-sky-400 outline-none text-sky-300 uppercase">
                                    <option value="">-- ALL_JOBS_FOR_ADMIN_TESTING --</option>
                                    <option v-for="job in (jobRoles || [])" :key="job.id" :value="String(job.id)">
                                        {{ job.name }}
                                    </option>
                                </select>
                                <p class="mt-2 text-[8px] text-slate-500 uppercase italic">
                                    *Filter ini hanya membantu admin memilih party dan task bank lintas job dengan cepat.
                                </p>
                            </div>

                            <div>
                                <label class="block mb-2 text-white">ASSIGN_TO_PARTY:</label>
                                <select v-model="form.study_group_id"
                                    class="w-full bg-black border-2 border-slate-700 p-2 focus:border-emerald-400 outline-none text-emerald-400 uppercase">
                                    <option v-if="!isMentor" :value="null">-- GLOBAL_QUEST (PUBLIC) --</option>
                                    <option v-if="isMentor && !filteredStudyGroups.length" :value="null" disabled>-- NO_STUDY_GROUP_AVAILABLE --</option>
                                    <option v-for="group in filteredStudyGroups" :key="group.id" :value="group.id">
                                        >> PARTY: {{ group.name }}{{ group.job?.name ? ` [${group.job.name}]` : '' }}
                                    </option>
                                </select>
                            </div>

                            <div>
                                <label class="block mb-2 text-white">TASK_BANK_SOURCE:</label>
                                <input
                                    v-model="taskBankSearch"
                                    type="text"
                                    placeholder="Search task bank..."
                                    class="w-full bg-black border-2 border-slate-700 p-2 mb-1 text-teal-300 outline-none focus:border-teal-400 text-[10px] uppercase placeholder:text-slate-600"
                                />
                                <select v-model="form.task_bank_id"
                                    class="w-full bg-black border-2 border-slate-700 p-2 focus:border-teal-400 outline-none text-teal-300 uppercase">
                                    <option :value="null">-- NO_TASK_BANK (MANUAL_QUEST) --</option>
                                    <option v-for="bank in searchableTaskBanks" :key="bank.id" :value="bank.id">
                                        {{ bank.name }} [{{ bank.assessment_type }}]{{ bank.job_role?.name ? ` [${bank.job_role.name}]` : '' }}
                                    </option>
                                </select>
                            </div>

                            <div v-if="!form.task_bank_id">
                                <label class="block mb-2 text-white">RUBRIC_OVERRIDE:</label>
                                <select v-model="form.rubric_id"
                                    class="w-full bg-black border-2 border-slate-700 p-2 focus:border-cyan-400 outline-none text-cyan-300 uppercase">
                                    <option :value="null">
                                        NO_RUBRIC (MANUAL_SCORE_1-100)
                                    </option>
                                    <option v-for="rb in (rubrics || [])" :key="rb.id" :value="rb.id">
                                        {{ rb.title }}
                                    </option>
                                </select>
                                <p v-if="form.errors.rubric_id" class="mt-2 text-red-400 text-[8px]">{{ form.errors.rubric_id }}</p>
                                <p class="mt-2 text-[8px] text-slate-500 uppercase italic">
                                    *Rubric hanya untuk quest manual (tanpa task bank).
                                </p>
                            </div>

                            <div class="flex gap-2">
                                <button type="submit" :disabled="form.processing || mentorCannotSubmitQuest"
                                    class="flex-1 py-3 border-2 uppercase font-bold transition-all"
                                    :class="isEditing ? 'border-green-500 text-green-500 hover:bg-green-600 hover:text-black' : 'border-cyan-400 text-cyan-400 hover:bg-cyan-400 hover:text-black'">
                                    {{ form.processing ? 'PROCESSING...' : (isEditing ? 'UPDATE_CONTRACT' : 'CONFIRM_MISSION') }}
                                </button>
                                <button @click="cancelEdit" type="button"
                                    class="px-4 py-3 border-2 border-slate-500 text-slate-500 hover:bg-slate-500 hover:text-white uppercase">
                                    X
                                </button>
                            </div>
                        </form>

                        <div v-else class="space-y-3">
                            <div class="text-[7px] uppercase text-slate-400">ONE_MODAL_FLOW // THEME_TO_QUEST_+_TASK_BANK</div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                <div v-if="isSuperAdmin || isAdminScope">
                                    <label class="block mb-1 text-[7px] text-slate-300 uppercase">Job Scope</label>
                                    <select v-model="aiScopeForm.job_id" class="w-full bg-black border-2 border-slate-700 p-2 text-cyan-400 uppercase outline-none text-[9px]">
                                        <option value="">ALL_JOB</option>
                                        <option v-for="job in jobRoles" :key="`job-inline-${job.id}`" :value="String(job.id)">{{ job.name }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block mb-1 text-[7px] text-slate-300 uppercase">Study Group</label>
                                    <select v-model="aiScopeForm.study_group_id" class="w-full bg-black border-2 border-slate-700 p-2 text-cyan-400 uppercase outline-none text-[9px]">
                                        <option value="">ALL_GROUP</option>
                                        <option v-for="group in aiFilteredStudyGroups" :key="`group-inline-${group.id}`" :value="String(group.id)">{{ group.name }}</option>
                                    </select>
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block mb-1 text-[7px] text-slate-300 uppercase">Tema / Topik</label>
                                    <input v-model="aiScopeForm.theme" type="text" maxlength="500" placeholder="Contoh: HTTP Protocol, Laravel Routing, OOP Dasar..." class="w-full bg-black border-2 border-slate-700 p-2 text-cyan-400 uppercase outline-none text-[9px]" />
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block mb-1 text-[7px] text-amber-300 uppercase">Catatan Penting ke AI (Input Tambahan)</label>
                                    <textarea
                                        v-model="aiScopeForm.ai_note"
                                        rows="3"
                                        maxlength="1000"
                                        placeholder="Contoh: Buat lebih sulit untuk level advance, fokus ke analisis, hindari soal terlalu basic."
                                        class="w-full bg-black border-2 border-amber-700 p-2 text-amber-200 outline-none text-[9px] font-sans"
                                    ></textarea>
                                    <div class="mt-1 flex items-center justify-between gap-2">
                                        <p class="text-[7px] text-amber-400 uppercase">PRIORITAS_TINGGI: catatan ini dipakai sebagai arahan penting saat AI generate.</p>
                                        <span class="text-[7px] text-slate-500">{{ aiScopeForm.ai_note.length }}/1000</span>
                                    </div>
                                </div>
                                <div>
                                    <label class="block mb-1 text-[7px] text-slate-300 uppercase">Tipe Soal</label>
                                    <select v-model="aiScopeForm.question_type" class="w-full bg-black border-2 border-slate-700 p-2 text-cyan-400 uppercase outline-none text-[9px]">
                                        <option value="multiple_choice">MULTIPLE_CHOICE</option>
                                        <option value="essay">ESSAY</option>
                                        <option value="mixed">MIXED</option>
                                        <option value="platforming">PLATFORMING</option>
                                        <option value="word_match">WORD_MATCH</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block mb-1 text-[7px] text-slate-300 uppercase">Jumlah Soal</label>
                                    <input v-model.number="aiScopeForm.question_count" type="number" min="3" max="30" class="w-full bg-black border-2 border-slate-700 p-2 text-cyan-400 uppercase outline-none text-[9px]" />
                                </div>
                                <div>
                                    <label class="block mb-1 text-[7px] text-slate-300 uppercase">Difficulty</label>
                                    <select v-model="aiScopeForm.difficulty" class="w-full bg-black border-2 border-slate-700 p-2 text-cyan-400 uppercase outline-none text-[9px]">
                                        <option value="C-Rank">C-Rank</option>
                                        <option value="B-Rank">B-Rank</option>
                                        <option value="A-Rank">A-Rank</option>
                                        <option value="S-Rank">S-Rank</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block mb-1 text-[7px] text-slate-300 uppercase">Publish Mode</label>
                                    <select v-model="aiScopeForm.publish_mode" class="w-full bg-black border-2 border-slate-700 p-2 text-cyan-400 uppercase outline-none text-[9px]">
                                        <option value="draft">DRAFT (Review Dulu)</option>
                                        <option value="publish_now">PUBLISH_NOW</option>
                                        <option value="schedule">SCHEDULE</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block mb-1 text-[7px] text-slate-300 uppercase">Publish At (schedule)</label>
                                    <input v-model="aiScopeForm.available_from" type="datetime-local" :disabled="aiScopeForm.publish_mode !== 'schedule'" class="w-full bg-black border-2 border-slate-700 p-2 text-cyan-400 outline-none text-[9px] disabled:opacity-50" />
                                </div>
                                <div>
                                    <label class="block mb-1 text-[7px] text-slate-300 uppercase">Hide At (opsional)</label>
                                    <input v-model="aiScopeForm.available_until" type="datetime-local" :disabled="aiScopeForm.publish_mode !== 'schedule'" class="w-full bg-black border-2 border-slate-700 p-2 text-cyan-400 outline-none text-[9px] disabled:opacity-50" />
                                </div>
                                <div>
                                    <label class="block mb-1 text-[7px] text-slate-300 uppercase">Deadline (opsional)</label>
                                    <input v-model="aiScopeForm.deadline" type="datetime-local" class="w-full bg-black border-2 border-slate-700 p-2 text-cyan-400 outline-none text-[9px]" />
                                </div>
                            </div>

                            <div class="mt-3 flex gap-2">
                                <button type="button" @click="generateThemePreview" :disabled="isGeneratingThemePreview" class="px-3 py-2 border-2 border-amber-500 text-amber-300 hover:bg-amber-500 hover:text-black uppercase text-[8px]">
                                    {{ isGeneratingThemePreview ? 'GENERATING...' : 'GENERATE_PREVIEW' }}
                                </button>
                                <button type="button" @click="themePreview = null" class="px-3 py-2 border-2 border-slate-600 text-slate-300 hover:bg-slate-700 hover:text-white uppercase text-[8px]">
                                    RESET
                                </button>
                                <button @click="cancelEdit" type="button" class="px-3 py-2 border-2 border-slate-500 text-slate-500 hover:bg-slate-500 hover:text-white uppercase text-[8px]">
                                    CLOSE
                                </button>
                            </div>

                            <div v-if="themePreview?.bundle" class="mt-3 p-3 border border-amber-800 bg-black/40 space-y-3">
                                <div class="text-[7px] uppercase text-slate-400">
                                    PROVIDER: {{ themePreview.provider_used || '-' }}
                                    <span class="text-amber-300 ml-2" v-if="themePreview.is_fallback">[FALLBACK]</span>
                                    <span class="ml-2">LATENCY: {{ themePreview.latency_ms || 0 }}ms</span>
                                </div>
                                <p class="text-amber-200 text-[9px] uppercase">QUEST: {{ themePreview.bundle.quest.title }}</p>
                                <p class="text-slate-300 text-[8px] font-sans leading-relaxed">{{ themePreview.bundle.quest.description }}</p>
                                <p class="text-[7px] text-cyan-300 uppercase">DIFFICULTY: {{ themePreview.bundle.quest.difficulty }} | TASK_BANK: {{ themePreview.bundle.task_bank.name }} [{{ themePreview.bundle.task_bank.assessment_type }}]</p>
                                <p class="text-[7px] text-slate-400 uppercase">QUESTIONS: {{ themePreview.bundle.question_count }} soal</p>
                                <div class="max-h-48 overflow-y-auto custom-scroll space-y-1">
                                    <div v-for="(q, idx) in themePreview.bundle.questions" :key="`inline-q-${idx}`" class="border border-slate-800 bg-slate-900/40 px-2 py-1">
                                        <p class="text-[8px] text-slate-200"><span class="text-cyan-400">{{ idx + 1 }}.</span> {{ q.question_text }}</p>
                                        <p class="text-[7px] text-slate-400 uppercase">TYPE: {{ q.question_type }} | WEIGHT: {{ q.weight }}</p>
                                        <p v-if="q.question_type === 'multiple_choice'" class="text-[7px] text-emerald-300">OPTIONS: {{ (q.options || []).join(' | ') }} | KEY: {{ q.answer_key }}</p>
                                    </div>
                                </div>
                                <button type="button" @click="commitThemeBundle" :disabled="isCommittingThemeBundle" class="mt-2 px-4 py-2 border-2 border-cyan-400 text-cyan-300 hover:bg-cyan-400 hover:text-black uppercase text-[8px]">
                                    {{ isCommittingThemeBundle ? 'COMMITTING...' : 'COMMIT_QUEST_+_TASK_BANK' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                </div>

                <div class="col-span-12 lg:col-span-12">
                    <div class="rpg-panel border-slate-700 h-full">
                        <h2 class="text-white mb-6 uppercase tracking-tighter">
                            >> {{ isTrashView ? 'TRASH_MISSIONS_BOARD' : 'ACTIVE_MISSIONS_BOARD' }}
                        </h2>
                        <div class="mb-4 flex gap-2">
                            <button
                                @click="setView('active')"
                                class="px-3 py-2 border-2 uppercase"
                                :class="isTrashView ? 'border-slate-600 text-slate-300 hover:bg-slate-700 hover:text-white' : 'border-cyan-400 text-cyan-400 bg-cyan-900/20'"
                            >
                                ACTIVE
                            </button>
                            <button
                                @click="setView('trash')"
                                class="px-3 py-2 border-2 uppercase"
                                :class="isTrashView ? 'border-amber-500 text-amber-300 bg-amber-900/20' : 'border-slate-600 text-slate-300 hover:bg-slate-700 hover:text-white'"
                            >
                                TRASH
                            </button>
                        </div>
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
                                        <div class="text-[7px] uppercase mt-1" :class="(q.quest_type || 'main') === 'optional' ? 'text-lime-300' : 'text-sky-300'">
                                            TYPE: {{ (q.quest_type || 'main') === 'optional' ? 'OPTIONAL_BONUS' : 'MAIN_QUEST' }}
                                        </div>
                                        <div class="text-[7px] text-cyan-300 uppercase mt-1">
                                            SCORING: {{ q.task_bank ? 'QUESTION_BANK (AUTO/MANUAL_BY_WEIGHT)' : (q.rubric?.title ? `RUBRIC: ${q.rubric.title}` : 'MANUAL_SCORE_1-100') }}
                                        </div>
                                        <div class="text-[7px] text-fuchsia-300 uppercase mt-1">
                                            SCHEDULE: {{ (q.schedule_type || 'manual') === 'once' ? 'SCHEDULED_ONCE' : 'MANUAL' }}
                                            <span v-if="q.available_from"> // FROM {{ formatSchedule(q.available_from) }}</span>
                                            <span v-if="q.available_until"> // UNTIL {{ formatSchedule(q.available_until) }}</span>
                                        </div>
                                    </div>
                                    <div class="text-yellow-500 text-[8px] tracking-widest">+{{ q.reward_gold }} GOLD</div>
                                </div>

                                <div v-if="!isTrashView" class="text-[7px] mb-3 flex items-center gap-1">
                                    <span class="text-orange-500">>> DEADLINE:</span>
                                    <span :class="isExpired(q.deadline) ? 'text-red-500 animate-pulse font-bold' : 'text-orange-300'">
                                        {{ q.deadline ? formatDeadline(q.deadline) : 'NO_TIME_LIMIT' }}
                                    </span>
                                    <span v-if="isExpired(q.deadline)" class="text-red-600 ml-1">[EXPIRED]</span>
                                </div>
                                <div v-else class="text-[7px] mb-3 flex items-center gap-1">
                                    <span class="text-amber-500">>> DELETED_AT:</span>
                                    <span class="text-amber-300">
                                        {{ q.deleted_at ? new Date(q.deleted_at).toLocaleString('id-ID') : '-' }}
                                    </span>
                                </div>

                                <div v-if="q.description"
                                    class="text-[7px] text-slate-500 italic mb-4 border-t border-slate-800 pt-2 leading-loose line-clamp-2">
                                    > {{ q.description }}
                                </div>

                                <div class="flex gap-4 self-end mt-auto">
                                    <Link v-if="!isTrashView" :href="route('admin.quests.submissions', q.uuid)"
                                        class="text-cyan-400 hover:text-white text-[8px] uppercase font-bold">[Detail]</Link>
                                    <button v-if="!isTrashView" @click="startEdit(q)"
                                        class="text-green-500 hover:text-white text-[8px] uppercase font-bold">[Edit]</button>
                                    <button v-if="!isTrashView" @click="confirmAbort(q.uuid)"
                                        class="text-red-500 hover:text-white text-[8px] uppercase font-bold">[Abort]</button>
                                    <button v-if="isTrashView" @click="restoreQuest(q.uuid)"
                                        class="text-emerald-400 hover:text-white text-[8px] uppercase font-bold">[Restore]</button>
                                    <button v-if="isTrashView" @click="hardDeleteQuest(q.uuid)"
                                        class="text-red-500 hover:text-white text-[8px] uppercase font-bold">[Hard_Delete]</button>
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
                        <p class="text-slate-200 text-[10px] leading-relaxed uppercase">Move this mission contract to trash?</p>
                    </div>
                </div>
                <div class="p-6 pt-0 flex gap-4">
                    <button @click="executeAbort" :disabled="form.processing"
                        class="flex-1 py-3 bg-red-600/20 border-2 border-red-600 text-red-500 hover:bg-red-600 hover:text-white transition-all uppercase font-bold text-[9px]">
                        {{ form.processing ? 'ARCHIVING...' : 'ARCHIVE' }}
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
