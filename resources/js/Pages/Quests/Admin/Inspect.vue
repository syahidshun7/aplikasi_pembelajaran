<template>
    <div class="min-h-screen bg-[#0d1117] p-4 md:p-8 font-['Press_Start_2P'] text-[#4ed4d4] text-[10px]">
        <div class="max-w-7xl mx-auto space-y-8">
            <AdminNavbar />

            <div class="mb-8 flex flex-col md:flex-row justify-between items-start md:items-center border-b-4 border-slate-800 pb-6 gap-4">
                <div>
                    <h1 class="text-xl text-white uppercase mb-2 tracking-tighter">Manual_Inspection_Console</h1>
                    <div class="flex flex-wrap items-center gap-4">
                        <span class="text-[8px] text-slate-500 italic">SUBMISSION_UUID: {{ submission.uuid.substring(0, 8) }}...</span>
                        <span class="text-[8px] bg-yellow-900/30 text-yellow-500 px-2 py-1 border border-yellow-700">
                            DIFFICULTY: {{ submission.quest.difficulty }}
                        </span>
                        <span class="text-[8px] bg-blue-900/30 text-blue-400 px-2 py-1 border border-blue-700 uppercase">
                            MAX_REWARD: {{ submission.quest.reward_gold }} G
                        </span>
                        <span class="text-[8px] bg-cyan-900/30 text-cyan-400 px-2 py-1 border border-cyan-700 uppercase">
                            MAX_EXP: {{ maxExpReward }} XP
                        </span>
                    </div>
                </div>
                <Link :href="route('admin.quests.submissions', { quest: submission.quest.uuid })"
                    class="text-[8px] bg-red-900/20 text-red-500 px-6 py-3 border-2 border-red-900 hover:bg-red-900 hover:text-white transition-all">
                    [ CLOSE_TERMINAL ]
                </Link>
            </div>

            <section class="bg-[#161b22] border-4 border-slate-700 shadow-2xl overflow-hidden">
                <div class="bg-slate-900 p-4 border-b-4 border-slate-700 flex justify-between items-center">
                    <h3 class="text-[10px] text-yellow-500 uppercase tracking-widest">>> MISSION_OBJECTIVE_&_LOG</h3>
                    <span class="text-[8px] text-slate-500 italic font-bold uppercase">
                        Adventurer: {{ submission.user.name }}
                    </span>
                </div>

                <div class="p-6 grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <div class="lg:col-span-1 space-y-4 border-b-2 lg:border-b-0 lg:border-r-2 border-slate-800 pb-6 lg:pb-0 lg:pr-6">
                        <p class="text-[8px] text-slate-500 uppercase italic">Quest_Title:</p>
                        <p class="text-white text-sm leading-relaxed mb-6">{{ submission.quest.title }}</p>

                        <p class="text-[8px] text-slate-500 uppercase italic">Objective_Brief:</p>
                        <p class="text-slate-400 font-sans text-xs leading-relaxed italic border-l-2 border-slate-700 pl-4">
                            "{{ submission.quest.description }}"
                        </p>
                    </div>

                    <div class="lg:col-span-2 space-y-4">
                        <p class="text-[8px] text-cyan-500 uppercase mb-2 italic animate-pulse">Artifact_Viewer_Active:</p>

                        <div v-if="submission.content"
                            class="bg-black border-2 border-slate-800 p-6 font-sans text-sm text-slate-300 whitespace-pre-wrap mb-4 shadow-inner max-h-96 overflow-y-auto custom-scrollbar">
                            {{ submission.content }}
                        </div>

                        <div v-if="taskQuestions.length"
                            class="bg-black border-2 border-slate-800 p-6 shadow-inner space-y-4">
                            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2">
                                <p class="text-[8px] text-yellow-400 uppercase tracking-widest">
                                    >> TASK_BANK_QA_LOG
                                </p>
                                <p class="text-[7px] text-slate-500 uppercase italic">
                                    BANK: <span class="text-slate-300 font-bold">{{ taskBank?.name || '-' }}</span>
                                    <span v-if="taskBank?.assessment_type" class="text-slate-500">({{ taskBank.assessment_type }})</span>
                                </p>
                            </div>

                            <div class="space-y-3 max-h-96 overflow-y-auto pr-2 custom-scrollbar">
                                <div v-for="(q, idx) in taskQuestions" :key="q.uuid"
                                    class="p-4 border border-slate-800 bg-slate-950/30">
                                    <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-3">
                                        <div class="min-w-0 flex-1">
                                            <p class="text-[7px] text-slate-500 uppercase mb-2">
                                                Q{{ idx + 1 }} // {{ q.question_type || 'essay' }} // W: {{ q.weight || 1 }}
                                            </p>
                                            <p class="text-[13px] font-sans text-slate-200 break-words">
                                                {{ q.question_text }}
                                            </p>
                                        </div>
                                        <div class="shrink-0 text-right text-[7px] text-slate-500 uppercase">
                                            ID: {{ String(q.uuid || '').substring(0, 8) }}...
                                        </div>
                                    </div>

                                    <div class="mt-4">
                                        <template v-if="q.question_type === 'multiple_choice'">
                                            <p class="text-[8px] text-slate-500 uppercase italic mb-2">SELECTED_ANSWER:</p>
                                            <p class="text-[12px] font-sans"
                                                :class="selectedAnswerFor(q) ? 'text-cyan-300' : 'text-red-400'">
                                                {{ selectedAnswerFor(q) || 'NOT_ANSWERED' }}
                                            </p>

                                            <div v-if="Array.isArray(q.options_json) && q.options_json.length"
                                                class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-2">
                                                <div v-for="(opt, oidx) in q.options_json" :key="`${q.uuid}-opt-${oidx}`"
                                                    class="px-3 py-2 border text-[11px] font-sans break-words"
                                                    :class="String(opt) === String(selectedAnswerFor(q)) ? 'border-cyan-500 bg-cyan-500/10 text-cyan-200' : 'border-slate-800 bg-black/20 text-slate-400'">
                                                    {{ opt }}
                                                </div>
                                            </div>

                                            <p v-if="q.answer_key" class="mt-3 text-[8px] text-slate-500 uppercase italic">
                                                ANSWER_KEY: <span class="text-emerald-400 font-bold">{{ q.answer_key }}</span>
                                            </p>
                                        </template>

                                        <template v-else>
                                            <p class="text-[8px] text-slate-500 uppercase italic mb-2">STUDENT_ANSWER:</p>
                                            <div class="bg-black/40 border border-slate-800 p-4 font-sans text-[12px] text-slate-200 whitespace-pre-wrap">
                                                {{ selectedAnswerFor(q) || 'NOT_ANSWERED' }}
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="submission.file_path" class="border-4 border-black bg-black shadow-2xl overflow-hidden">
                            <div v-if="isImage(submission.file_path)" class="p-2">
                                <img :src="attachmentPreviewUrl" class="w-full h-auto" alt="Submission attachment">
                            </div>
                            <div v-else-if="isPdf(submission.file_path)" class="h-[500px] w-full bg-slate-800">
                                <div v-if="isMobilePdfPreview" class="h-full w-full p-4 text-[8px] text-slate-300 uppercase border border-slate-700 bg-black">
                                    Preview PDF di mobile kadang dibatasi browser. Gunakan OPEN_ATTACHMENT untuk membuka viewer bawaan browser.
                                </div>
                                <iframe
                                    v-else
                                    :src="`${attachmentPreviewUrl}#toolbar=1&view=FitH`"
                                    class="w-full h-full"
                                    title="Submission PDF preview"
                                />
                            </div>
                            <div v-else class="p-4 bg-slate-900 text-[8px] text-slate-400 uppercase">
                                Preview not supported for this file type.
                            </div>
                            <div class="p-3 border-t border-slate-800 bg-slate-950 text-right">
                                <a :href="attachmentPreviewUrl" target="_blank"
                                    class="inline-block bg-cyan-900/40 border border-cyan-400 px-3 py-1 text-cyan-300 hover:bg-cyan-400 hover:text-black transition-all text-[8px] uppercase">
                                    OPEN_ATTACHMENT
                                </a>
                            </div>
                        </div>

                        <div v-if="submission.link"
                            class="p-4 bg-cyan-900/10 border-2 border-cyan-500/30 flex flex-col md:flex-row justify-between items-center gap-4">
                            <span class="text-[8px] text-cyan-400 font-mono break-all">{{ submission.link }}</span>
                            <a :href="submission.link" target="_blank"
                                class="bg-cyan-500 text-black px-4 py-2 text-[8px] font-bold hover:bg-white transition-colors whitespace-nowrap">
                                OPEN_EXTERNAL_SOURCE
                            </a>
                        </div>
                    </div>
                </div>
            </section>

            <section class="bg-[#161b22] border-4 border-slate-700 shadow-2xl overflow-hidden">
                <div class="bg-slate-900 p-4 border-b-4 border-slate-700 flex justify-between items-center">
                    <h3 class="text-[10px] text-green-500 uppercase tracking-widest">>> ACADEMIC_EVALUATION_&_REWARDS</h3>
                    <div class="flex items-center gap-3">
                        <span v-if="hasRubric" class="text-[8px] text-slate-400 uppercase italic">
                            RUBRIC: <span class="text-cyan-300 font-bold">{{ rubricTitle }}</span>
                            <span v-if="rubricSourceLabel" class="text-slate-500">({{ rubricSourceLabel }})</span>
                        </span>
                        <span v-else-if="isTaskBankSubmission" class="text-[8px] text-slate-400 uppercase italic">
                            QUESTION_BANK: <span class="text-yellow-400 font-bold">{{ taskBank?.name || '-' }}</span>
                            <span v-if="taskBankType" class="text-slate-500">({{ taskBankType }})</span>
                        </span>
                        <span v-else class="text-[8px] text-slate-400 uppercase italic">
                            MANUAL_SCORE: <span class="text-cyan-300 font-bold">1–100</span>
                        </span>
                        <button v-if="!hasRubric && !isTaskBankSubmission" @click="scanWithAI" :disabled="isScanning" 
                            class="text-[8px] bg-indigo-900/30 text-indigo-400 px-3 py-1 border border-indigo-700 hover:bg-indigo-500 hover:text-white transition-all disabled:opacity-50">
                            {{ isScanning ? 'ANALYZING...' : '[ AI_ADVISOR_SCAN ]' }}
                        </button>
                    </div>
                </div>

                <div class="p-6 md:p-8">
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-10">
                        
                        <div class="lg:col-span-7 space-y-3">
                            <template v-if="isTaskBankSubmission">
                                <div class="p-4 border-2 border-slate-800 bg-black/40">
                                    <div class="flex justify-between text-[8px] uppercase">
                                        <span class="text-slate-400 font-sans">AUTO_MCQ:</span>
                                        <span class="text-slate-200 font-bold">{{ taskBankMcqEarnedPoints }} / {{ taskBankMcqMaxPoints }} pts</span>
                                    </div>
                                    <div v-if="essayQuestions.length" class="flex justify-between text-[8px] uppercase border-t border-slate-800 pt-2 mt-2">
                                        <span class="text-slate-400 font-sans">MANUAL_ESSAY:</span>
                                        <span class="text-slate-200 font-bold">{{ taskBankEssayEarnedPoints }} / {{ taskBankEssayMaxPoints }} pts</span>
                                    </div>
                                    <div class="flex justify-between text-[8px] uppercase border-t border-slate-800 pt-2 mt-2">
                                        <span class="text-slate-400 font-sans">TOTAL_POINTS:</span>
                                        <span class="text-cyan-300 font-bold underline">{{ taskBankEarnedPoints }} / {{ taskBankMaxPoints }} pts</span>
                                    </div>
                                </div>

                                <div v-for="(q, idx) in taskQuestions" :key="q.uuid"
                                    class="p-4 border-2 border-slate-800 bg-black/40 hover:border-cyan-500 transition-all">
                                    <div class="flex justify-between items-start gap-4">
                                        <div class="min-w-0">
                                            <p class="text-[7px] text-slate-500 uppercase italic mb-2">
                                                Q{{ idx + 1 }} // {{ q.question_type || 'essay' }} // W: {{ q.weight || 0 }}
                                            </p>
                                            <p class="text-[12px] font-sans text-slate-200 break-words">{{ q.question_text }}</p>
                                        </div>
                                        <div class="text-right text-[8px] uppercase">
                                            <span v-if="q.question_type === 'multiple_choice'"
                                                :class="taskBankMcqByQuestion?.[q.uuid]?.is_correct ? 'text-emerald-400' : 'text-red-400'">
                                                {{ taskBankMcqByQuestion?.[q.uuid]?.is_correct ? 'CORRECT' : 'WRONG' }}
                                            </span>
                                            <span v-else class="text-yellow-400">ESSAY</span>
                                        </div>
                                    </div>

                                    <div v-if="q.question_type === 'multiple_choice'" class="mt-3 text-[11px] font-sans">
                                        <p class="text-slate-400 uppercase text-[8px] italic">AUTO_POINTS:</p>
                                        <p class="text-cyan-300">
                                            +{{ taskBankMcqByQuestion?.[q.uuid]?.earned_points || 0 }} / {{ q.weight || 0 }}
                                        </p>
                                    </div>

                                    <div v-else class="mt-4">
                                        <label class="block text-[7px] text-slate-500 uppercase italic mb-2">
                                            ESSAY_SCORE (0–{{ q.weight || 0 }}):
                                        </label>
                                        <input
                                            type="number"
                                            v-model.number="essayPoints[q.uuid]"
                                            :min="0"
                                            :max="q.weight || 0"
                                            step="1"
                                            @input="validateEssayPoints(q)"
                                            class="w-full bg-slate-900 border-2 border-slate-700 p-2 text-[10px] text-cyan-300 uppercase outline-none focus:border-cyan-400"
                                        />
                                    </div>
                                </div>
                            </template>

                            <template v-else-if="hasRubric">
                                <div v-for="criterion in rubricCriteria" :key="criterion.id"
                                    class="p-4 border-2 border-slate-800 bg-black/40 hover:border-cyan-500 transition-all">
                                    <div class="flex flex-col md:flex-row md:items-start justify-between gap-4">
                                        <div class="min-w-0">
                                            <p class="text-[10px] uppercase font-bold text-white break-words">
                                                {{ criterion.name }}
                                            </p>
                                            <p class="text-[7px] text-slate-500 uppercase italic mt-1">
                                                WEIGHT: <span class="text-slate-300 font-bold">{{ Number(criterion.weight || 0).toFixed(2) }}</span>
                                            </p>
                                            <p v-if="rubricCellDescription(criterion.id, selectedLevels[criterion.id])"
                                                class="text-[11px] font-sans text-slate-300 mt-3 leading-relaxed border-l-2 border-slate-700 pl-3 italic">
                                                "{{ rubricCellDescription(criterion.id, selectedLevels[criterion.id]) }}"
                                            </p>
                                        </div>

                                        <div class="shrink-0 w-full md:w-56">
                                            <label class="block text-[7px] text-slate-500 uppercase italic mb-2">SELECT_LEVEL:</label>
                                            <select
                                                v-model.number="selectedLevels[criterion.id]"
                                                class="w-full bg-slate-900 border-2 border-slate-700 p-2 text-[10px] text-cyan-300 uppercase outline-none focus:border-cyan-400"
                                            >
                                                <option :value="0">-- SELECT --</option>
                                                <option v-for="lvl in rubricLevels" :key="lvl.id" :value="lvl.id">
                                                    {{ lvl.label }} ({{ Number(lvl.score_value || 0).toFixed(0) }})
                                                </option>
                                            </select>
                                            <p v-if="missingRubricCriteriaIds.includes(criterion.id)"
                                                class="text-[8px] text-red-400 uppercase mt-2">
                                                LEVEL_REQUIRED
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div class="p-4 border-2 border-slate-800 bg-slate-950/30">
                                    <div class="flex justify-between text-[8px] uppercase">
                                        <span class="text-slate-400 font-sans">Max Level Score:</span>
                                        <span class="text-slate-200 font-bold">{{ Number(rubricMaxLevelScore || 0).toFixed(2) }}</span>
                                    </div>
                                    <div class="flex justify-between text-[8px] uppercase border-t border-slate-800 pt-2 mt-2">
                                        <span class="text-slate-400 font-sans">Total Weight:</span>
                                        <span class="text-slate-200 font-bold">{{ Number(rubricMaxWeight || 0).toFixed(2) }}</span>
                                    </div>
                                </div>
                            </template>

                            <template v-else>
                                <div class="p-4 border-2 border-slate-800 bg-black/40">
                                    <div class="flex items-center justify-between gap-4">
                                        <div>
                                            <p class="text-[8px] text-yellow-400 uppercase tracking-widest">>> MANUAL_SCORE_MODE</p>
                                            <p class="text-[7px] text-slate-500 uppercase italic mt-1">
                                                Range: 1–100 (fallback tanpa rubric)
                                            </p>
                                        </div>
                                        <div class="shrink-0 w-32">
                                            <input
                                                type="number"
                                                v-model.number="manualFinalScore"
                                                min="1"
                                                max="100"
                                                step="1"
                                                @input="validateManualScore"
                                                class="w-full bg-slate-900 border-2 border-slate-700 p-2 text-[10px] text-cyan-300 uppercase outline-none focus:border-cyan-400 text-right font-mono"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div class="lg:col-span-5 flex flex-col justify-between space-y-6">
                            <div class="grid grid-cols-2 gap-4">
                                <div class="bg-black p-4 border-2 border-slate-800 text-center">
                                    <p class="text-[7px] text-slate-600 uppercase mb-2 italic">Final_Score:</p>
                                    <p class="text-2xl font-bold" :class="totalScore >= 70 ? 'text-green-400' : (totalScore >= 40 ? 'text-yellow-400' : 'text-red-500')">
                                        {{ totalScore }}%
                                    </p>
                                </div>
                                <div class="bg-black p-4 border-2 border-slate-800 text-center text-[10px] flex items-center justify-center font-bold uppercase tracking-tighter"
                                    :class="getStatusClass(localStatus)">
                                    {{ localStatus }}
                                </div>
                            </div>

                            <div class="bg-slate-800/30 p-4 border-2 border-slate-700 space-y-2">
                                <div class="flex justify-between text-[8px] uppercase">
                                    <span class="text-slate-400 font-sans">Quest Max Reward:</span>
                                    <span class="text-slate-300 font-bold">{{ submission.quest.reward_gold }} G</span>
                                </div>
                                <div class="flex justify-between text-[8px] uppercase border-t border-slate-700 pt-2">
                                    <span class="text-slate-400 font-sans italic text-[7px]">Calculated Gold ({{ totalScore }}%):</span>
                                    <span class="text-yellow-400 font-bold underline">+{{ calculatedGold }} G</span>
                                </div>
                                <div class="flex justify-between text-[8px] uppercase">
                                    <span class="text-slate-400 font-sans italic text-[7px]">Calculated EXP ({{ totalScore }}%):</span>
                                    <span class="text-cyan-400 font-bold">+{{ calculatedExp }} XP</span>
                                </div>
                            </div>

                            <div class="bg-black p-4 border-2 border-slate-800">
                                <label class="block text-[7px] text-slate-500 uppercase italic mb-2">Verdict_Status:</label>
                                <select
                                    v-model="localStatus"
                                    class="w-full bg-slate-900 border-2 border-slate-700 p-2 text-[10px] text-cyan-300 uppercase outline-none focus:border-cyan-400"
                                >
                                    <option value="Approved">Approved</option>
                                    <option value="Rejected">Rejected</option>
                                </select>
                            </div>

                            <textarea v-model="feedbackText"
                                class="w-full bg-black border-2 border-slate-800 p-4 text-slate-200 font-sans text-xs focus:border-green-500 outline-none h-32 custom-scrollbar"
                                placeholder="TYPE_COMMANDER_FEEDBACK_LOG_HERE..."></textarea>

                            <div class="flex flex-col gap-2">
                                <p class="text-[7px] text-slate-500 uppercase italic">Execution_Command:</p>
                                <button
                                    @click="submitEvaluation"
                                    class="w-full py-5 font-bold text-[10px] uppercase tracking-widest transition-all active:translate-y-1 active:shadow-none bg-cyan-700 hover:bg-cyan-600 text-black shadow-[4px_4px_0_0_#0e7490]"
                                >
                                    [ CONFIRM_VERDICT_AND_REWARD ]
                                </button>
                                <p class="text-[6px] text-center text-slate-600 mt-2 italic">
                                    *Caution: This action will modify user balance and experience.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import Swal from 'sweetalert2';
import axios from 'axios';
import AdminNavbar from '@/Components/AdminNavbar.vue';

const props = defineProps({
    submission: Object,
    rubric: Object,
    rubricSource: String,
});

// 1. STATE & DATA
const feedbackText = ref(props.submission.feedback || '');
const localStatus = ref(['Approved', 'Rejected'].includes(props.submission.status) ? props.submission.status : 'Approved');
const isScanning = ref(false);
const manualFinalScore = ref(Math.max(1, Math.min(100, Number(props.submission.grade || 1))));
const selectedLevels = ref({});
const essayPoints = ref({});
const taskAnswers = computed(() => {
    const answers = props.submission?.scores_detail?.answers;
    return answers && typeof answers === 'object' ? answers : {};
});
const taskBank = computed(() => props.submission?.quest?.task_bank || null);
const taskQuestions = computed(() => {
    const questions = taskBank.value?.questions;
    return Array.isArray(questions) ? questions : [];
});
const isTaskBankSubmission = computed(() => taskQuestions.value.length > 0);
const taskBankType = computed(() => taskBank.value?.assessment_type || null);
const mcqQuestions = computed(() => taskQuestions.value.filter((q) => q.question_type === 'multiple_choice'));
const essayQuestions = computed(() => taskQuestions.value.filter((q) => q.question_type !== 'multiple_choice'));

const attachmentPreviewUrl = computed(() => {
    if (!props.submission?.file_path) return null;
    try {
        return route('admin.submissions.file', { submission: props.submission.uuid });
    } catch (error) {
        // Fallback untuk deploy yang cache Ziggy-nya belum update.
        return `/submissions/${props.submission.uuid}/file`;
    }
});

// 2. COMPUTED PROPERTIES
const hasRubric = computed(() => !!props.rubric?.rubric?.id);
const rubricTitle = computed(() => props.rubric?.rubric?.title || 'N/A');
const rubricSourceLabel = computed(() => {
    const src = String(props.rubricSource || '').toLowerCase();
    if (src === 'quest') return 'quest';
    if (src === 'task_bank') return 'task_bank';
    return '';
});
const rubricCriteria = computed(() => Array.isArray(props.rubric?.criteria) ? props.rubric.criteria : []);
const rubricLevels = computed(() => Array.isArray(props.rubric?.levels) ? props.rubric.levels : []);
const rubricMaxLevelScore = computed(() => {
    const scores = rubricLevels.value.map((l) => Number(l.score_value || 0));
    return scores.length ? Math.max(...scores) : 0;
});
const rubricMaxWeight = computed(() => rubricCriteria.value.reduce((acc, c) => acc + Number(c.weight || 0), 0));
const missingRubricCriteriaIds = computed(() => {
    if (!hasRubric.value) return [];
    return rubricCriteria.value
        .filter((c) => Number(selectedLevels.value?.[c.id] || 0) <= 0)
        .map((c) => c.id);
});

const rubricTotalScore = computed(() => {
    if (!hasRubric.value) return 0;
    const maxLevel = Number(rubricMaxLevelScore.value || 0);
    const maxWeight = Number(rubricMaxWeight.value || 0);
    if (maxLevel <= 0 || maxWeight <= 0) return 0;

    let total = 0;
    rubricCriteria.value.forEach((c) => {
        const levelId = Number(selectedLevels.value?.[c.id] || 0);
        const level = rubricLevels.value.find((l) => Number(l.id) === levelId);
        const selectedScore = level ? Number(level.score_value || 0) : 0;
        const weight = Number(c.weight || 0);
        total += (selectedScore / maxLevel) * weight;
    });

    const percent = Math.round((total / maxWeight) * 100);
    return Math.max(0, Math.min(100, percent));
});

const taskBankMcqByQuestion = computed(() => {
    const by = {};
    mcqQuestions.value.forEach((q) => {
        const uuid = String(q.uuid || '');
        const weight = Math.max(0, Number(q.weight || 0));
        const selected = String(selectedAnswerFor(q) || '');
        const answerKey = String(q.answer_key || '');
        const isCorrect = selected !== '' && answerKey !== '' && selected === answerKey;
        by[uuid] = {
            weight,
            selected,
            answer_key: answerKey,
            is_correct: isCorrect,
            earned_points: isCorrect ? weight : 0,
        };
    });
    return by;
});

const taskBankMcqMaxPoints = computed(() => Object.values(taskBankMcqByQuestion.value).reduce((acc, v) => acc + Number(v.weight || 0), 0));
const taskBankMcqEarnedPoints = computed(() => Object.values(taskBankMcqByQuestion.value).reduce((acc, v) => acc + Number(v.earned_points || 0), 0));
const taskBankEssayMaxPoints = computed(() => essayQuestions.value.reduce((acc, q) => acc + Math.max(0, Number(q.weight || 0)), 0));
const taskBankEssayEarnedPoints = computed(() => {
    return essayQuestions.value.reduce((acc, q) => {
        const uuid = String(q.uuid || '');
        const max = Math.max(0, Number(q.weight || 0));
        const raw = Number(essayPoints.value?.[uuid] ?? 0);
        const clamped = Math.max(0, Math.min(max, isNaN(raw) ? 0 : raw));
        return acc + clamped;
    }, 0);
});
const taskBankMaxPoints = computed(() => taskQuestions.value.reduce((acc, q) => acc + Math.max(0, Number(q.weight || 0)), 0));
const taskBankEarnedPoints = computed(() => Number(taskBankMcqEarnedPoints.value || 0) + Number(taskBankEssayEarnedPoints.value || 0));
const taskBankTotalScore = computed(() => {
    const max = Number(taskBankMaxPoints.value || 0);
    if (max <= 0) return 0;
    const percent = Math.round((Number(taskBankEarnedPoints.value || 0) / max) * 100);
    return Math.max(0, Math.min(100, percent));
});

const totalScore = computed(() => {
    if (isTaskBankSubmission.value) return taskBankTotalScore.value;
    if (hasRubric.value) return rubricTotalScore.value;
    return Math.max(1, Math.min(100, Number(manualFinalScore.value || 1)));
});

const calculatedGold = computed(() => {
    const baseGold = Number(props.submission.quest.reward_gold) || 0;
    return Math.floor(baseGold * (totalScore.value / 100));
});

const calculatedExp = computed(() => {
    const baseExp = maxExpReward.value;
    return Math.floor(baseExp * (totalScore.value / 100));
});

const maxExpReward = computed(() => {
    const exp = Number(props.submission.quest.reward_exp) || 0;
    if (exp > 0) return exp;
    const gold = Number(props.submission.quest.reward_gold) || 0;
    return gold > 0 ? gold : 1000;
});

// 3. METHODS
const validateManualScore = () => {
    const raw = Number(manualFinalScore.value || 1);
    if (isNaN(raw)) {
        manualFinalScore.value = 1;
        return;
    }
    manualFinalScore.value = Math.max(1, Math.min(100, Math.round(raw)));
};

const validateEssayPoints = (question) => {
    const uuid = String(question?.uuid || '');
    if (!uuid) return;
    const max = Math.max(0, Number(question?.weight || 0));
    const raw = Number(essayPoints.value?.[uuid] ?? 0);
    const clamped = Math.max(0, Math.min(max, isNaN(raw) ? 0 : raw));
    essayPoints.value[uuid] = clamped;
};

const getStatusClass = (status) => {
    if (status === 'Approved') return 'text-green-400 border-green-500 bg-green-500/10';
    if (status === 'Rejected') return 'text-red-500 border-red-500 bg-red-500/10';
    return 'text-yellow-500 border-yellow-500 bg-yellow-500/10';
};

const isImage = (path) => /\.(jpg|jpeg|png|webp|avif|gif)$/i.test(path);
const isPdf = (path) => /\.pdf$/i.test(path);
const isMobilePdfPreview = computed(() => {
    if (typeof window === 'undefined') return false;
    const ua = window.navigator?.userAgent || '';
    const iPadOs = /Macintosh/i.test(ua) && (window.navigator?.maxTouchPoints || 0) > 1;
    return /Android|iPhone|iPad|iPod|Mobile|Opera Mini|IEMobile/i.test(ua) || iPadOs;
});

const rubricCellDescription = (criteriaId, levelId) => {
    if (!hasRubric.value) return '';
    const cid = Number(criteriaId || 0);
    const lid = Number(levelId || 0);
    if (!cid || !lid) return '';
    return props.rubric?.matrix?.[cid]?.[lid] || '';
};

const selectedAnswerFor = (question) => {
    const uuid = String(question?.uuid || '');
    if (!uuid) return '';
    const value = taskAnswers.value?.[uuid];
    return typeof value === 'string' ? value : String(value || '');
};

onMounted(() => {
    const savedScores = props.submission.scores_detail;

    if (hasRubric.value) {
        const verdict = savedScores && typeof savedScores === 'object'
            ? (savedScores.verdict && typeof savedScores.verdict === 'object' ? savedScores.verdict : null)
            : null;

        if (verdict && verdict.source === 'rubric' && Number(verdict.rubric_id) === Number(props.rubric.rubric.id)) {
            selectedLevels.value = { ...(verdict.selected_level_by_criteria_id || {}) };
        } else {
            const initial = {};
            rubricCriteria.value.forEach((c) => { initial[c.id] = 0; });
            selectedLevels.value = initial;
        }
        return;
    }

    if (isTaskBankSubmission.value) {
        const verdictEssay = savedScores?.verdict?.task_bank?.essay?.by_question || {};
        const initial = {};
        essayQuestions.value.forEach((q) => {
            const uuid = String(q.uuid || '');
            const saved = verdictEssay?.[uuid]?.earned_points;
            initial[uuid] = typeof saved === 'number' ? saved : Number(saved || 0);
        });
        essayPoints.value = initial;
        return;
    }
});

const scanWithAI = async () => {
    if (hasRubric.value || isTaskBankSubmission.value) return;
    isScanning.value = true;
    try {
        const response = await axios.post(route('admin.submissions.checkAI', { submission: props.submission.uuid }));
        const data = response.data;

        const func = Number(data.func ?? 0);
        const logic = Number(data.logic ?? 0);
        const clean = Number(data.clean ?? 0);
        const suggested = Math.round((func + logic + clean) / 3);
        if (!isNaN(suggested) && suggested > 0) {
            manualFinalScore.value = Math.max(1, Math.min(100, suggested));
        }

        feedbackText.value = `[AI_ADVISOR]: ${data.feedback}\n\n${feedbackText.value}`;

        Swal.fire({
            title: 'AI_ANALYSIS_COMPLETE',
            text: 'System has been calibrated with AI suggestions.',
            icon: 'success',
            background: '#0d1117',
            color: '#4ed4d4',
            confirmButtonColor: '#1e293b'
        });
    } catch (error) {
        Swal.fire('UPLINK_ERROR', 'AI Advisor is currently offline.', 'error');
    } finally {
        isScanning.value = false;
    }
};

const submitEvaluation = () => {
    const status = localStatus.value;

    if (hasRubric.value && missingRubricCriteriaIds.value.length > 0) {
        Swal.fire({
            title: 'RUBRIC_INCOMPLETE',
            text: 'Pilih level untuk semua kriteria sebelum menyimpan verdict.',
            icon: 'warning',
            background: '#0d1117',
            color: '#4ed4d4',
            confirmButtonColor: '#a16207',
        });
        return;
    }

    if (!hasRubric.value && !isTaskBankSubmission.value) {
        validateManualScore();
    }

    Swal.fire({
        title: `CONFIRM ${status.toUpperCase()}?`,
        html: `
            <div class="text-left font-mono text-[10px] space-y-2 bg-black p-4 border border-slate-700 mt-4">
                <p class="text-cyan-400">FINAL_GRADE: ${totalScore.value}%</p>
                <p class="text-yellow-400">GOLD_EARNED: +${calculatedGold.value} G</p>
                <p class="text-blue-400">EXP_GAINED: +${calculatedExp.value} XP</p>
                <p class="text-white mt-2 border-t border-slate-800 pt-2">STATUS: ${status}</p>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '[ EXECUTE_VERDICT ]',
        cancelButtonText: '[ ABORT ]',
        background: '#0d1117',
        color: '#4ed4d4',
        confirmButtonColor: '#0891b2',
        cancelButtonColor: '#1e293b',
    }).then((result) => {
        if (result.isConfirmed) {
            const payload = isTaskBankSubmission.value
                ? {
                    status,
                    feedback: feedbackText.value,
                    question_points: { ...essayPoints.value },
                }
                : (hasRubric.value
                    ? {
                        status,
                        feedback: feedbackText.value,
                        selected_levels: { ...selectedLevels.value },
                    }
                    : {
                        final_score: manualFinalScore.value,
                        feedback: feedbackText.value,
                        status: status,
                    });

            router.post(route('admin.submissions.verdict', { submission: props.submission.uuid }), payload, {
                onSuccess: () => {
                    Swal.fire({
                        title: 'SYSTEM_UPDATED',
                        text: 'Verdict has been permanently logged.',
                        icon: 'success',
                        background: '#0d1117',
                        color: '#4ed4d4'
                    });
                },
            });
        }
    });
};
</script>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: #0d1117;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #1e293b;
    border-radius: 10px;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #4ed4d4;
}

/* Chrome, Safari, Edge, Opera - Remove arrows on number input */
input::-webkit-outer-spin-button,
input::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}

/* Firefox */
input[type=number] {
  -moz-appearance: textfield;
}
</style>
