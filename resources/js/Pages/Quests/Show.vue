<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Swal from 'sweetalert2';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    quest: Object,
    hasSubmitted: Boolean,
    existingSubmission: Object,
    isLate: Boolean,
    hasQuestUnlock: Boolean,
    canSubmit: Boolean,
    timeKeyQty: Number,
});

const taskAnswersFromSubmission = props.existingSubmission?.scores_detail?.answers &&
    typeof props.existingSubmission.scores_detail.answers === 'object'
    ? props.existingSubmission.scores_detail.answers
    : {};

const form = useForm({
    content: props.existingSubmission?.content || '',
    file: null,
    task_answers: { ...taskAnswersFromSubmission },
    _method: props.hasSubmitted ? 'PUT' : 'POST',
});

const unlockForm = useForm({});
const page = usePage();
const taskBankType = computed(() => props.quest?.task_bank?.assessment_type || null);
const isStructuredTaskBankQuest = computed(() => {
    return !!props.quest?.task_bank && ['multiple_choice', 'mixed', 'essay'].includes(taskBankType.value);
});
const isAutoCheckedTaskBankQuest = computed(() => taskBankType.value === 'multiple_choice');
const taskQuestions = computed(() => props.quest?.task_bank?.questions || []);
const unansweredCount = computed(() => {
    if (!isStructuredTaskBankQuest.value) return 0;
    return taskQuestions.value.filter((question) => {
        const value = form.task_answers?.[question.uuid];
        return !value || String(value).trim() === '';
    }).length;
});

const handleFileChange = (e) => {
    const file = e.target.files[0];
    if (!file) return;

    if (file.size > 5 * 1024 * 1024) {
        Swal.fire({
            title: 'FILE_TOO_LARGE',
            text: 'Maximum file size is 5MB.',
            icon: 'error',
            background: '#161b22',
            color: '#ef4444',
            confirmButtonColor: '#7f1d1d',
        });
        e.target.value = '';
        form.file = null;
        return;
    }
    form.file = file;
};

const submitReport = () => {
    if (isStructuredTaskBankQuest.value && unansweredCount.value > 0) {
        Swal.fire({
            title: 'ANSWER_INCOMPLETE',
            text: `Masih ada ${unansweredCount.value} soal yang belum dijawab.`,
            icon: 'warning',
            background: '#161b22',
            color: '#4ed4d4',
            confirmButtonColor: '#a16207',
        });
        return;
    }

    const title = props.hasSubmitted ? 'UPDATE REPORT?' : 'CONFIRM TRANSMISSION?';
    const text = isAutoCheckedTaskBankQuest.value
        ? 'Jawaban akan dinilai otomatis. Reward mengikuti skor akhir.'
        : (isStructuredTaskBankQuest.value
            ? 'Jawaban task bank akan dikirim untuk review admin.'
            : (props.hasSubmitted
                ? 'This will overwrite your previous report.'
                : 'The Guild will review your report. Continue?'));

    Swal.fire({
        title: title,
        text: text,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'YES, EXECUTE',
        cancelButtonText: 'CANCEL',
        background: '#161b22',
        color: '#4ed4d4',
        confirmButtonColor: props.hasSubmitted ? '#854d0e' : '#164e63',
        cancelButtonColor: '#7f1d1d',
        customClass: { popup: 'border-4 border-slate-700 font-mono' }
    }).then((result) => {
        if (result.isConfirmed) {
            const url = props.hasSubmitted
                ? route('submissions.update', props.existingSubmission.uuid)
                : route('submissions.store', props.quest.uuid);

            form.post(url, {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire({
                        title: isAutoCheckedTaskBankQuest.value ? 'AUTO_CHECK_DONE!' : 'LOGGED!',
                        text: isAutoCheckedTaskBankQuest.value
                            ? 'Skor dan reward sudah dihitung otomatis.'
                            : (isStructuredTaskBankQuest.value
                                ? (props.hasSubmitted ? 'TASK BANK SUBMISSION UPDATED.' : 'TASK BANK SUBMISSION SENT.')
                                : (props.hasSubmitted ? 'YOUR REPORT HAS BEEN UPDATED.' : 'REPORT SENT SUCCESSFULLY.')),
                        icon: 'success',
                        background: '#161b22',
                        color: '#4ed4d4',
                    });
                },
            });
        }
    });
};

const unlockLateQuest = () => {
    Swal.fire({
        title: 'USE_TIME_KEY?',
        text: '1 Time Key akan dikonsumsi untuk membuka kembali quest ini.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'YES_USE_KEY',
        cancelButtonText: 'CANCEL',
        background: '#161b22',
        color: '#4ed4d4',
        confirmButtonColor: '#a16207',
        cancelButtonColor: '#7f1d1d',
        customClass: { popup: 'border-4 border-slate-700 font-mono' }
    }).then((result) => {
        if (result.isConfirmed) {
            unlockForm.post(route('quests.unlock-late', props.quest.uuid), {
                preserveScroll: true,
            });
        }
    });
};
</script>

<template>
    <AuthenticatedLayout>
        <div class="min-h-screen p-2 md:p-12 font-['Press_Start_2P'] text-[#4ed4d4] flex justify-center items-start">
            <Head :title="'DETAILS - ' + quest.title" />

            <div
                class="max-w-3xl w-full bg-[#161b22] border-4 border-slate-700 shadow-[10px_10px_0px_0px_rgba(0,0,0,0.5)] md:shadow-[20px_20px_0px_0px_rgba(0,0,0,0.5)] relative overflow-hidden"
            >
                <div
                    class="hidden sm:flex absolute top-10 right-10 w-24 h-24 border-4 border-red-900/30 rounded-full items-center justify-center -rotate-12 select-none pointer-events-none text-red-900/30 text-[10px] text-center uppercase"
                >
                    Official<br>Guild<br>Doc
                </div>

                <div class="p-4 md:p-8 border-b-4 border-slate-700 bg-slate-900/50">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-4">
                        <Link
                            :href="route('lobby')"
                            class="inline-flex items-center justify-center px-3 py-2 border-2 border-slate-700 bg-slate-900/40 text-slate-300 hover:text-white hover:border-cyan-400 text-[10px] sm:text-[11px] uppercase whitespace-nowrap"
                        >
                            [ BACK_TO_LOBBY ]
                        </Link>
                        <span class="text-yellow-500 text-[11px] sm:text-[12px]">REF_ID: #{{ quest.uuid.substring(0, 8) }}</span>
                    </div>
                    <h1 class="text-lg md:text-2xl text-white uppercase tracking-tighter leading-tight break-words">
                        {{ quest.title }}
                    </h1>
                </div>

                <div class="p-4 md:p-8 space-y-6 md:space-y-8">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 border-b-2 border-slate-800 pb-6 text-[12px]">
                        <div>
                            <p class="text-slate-500 mb-2 uppercase">Danger_Level:</p>
                            <p class="text-red-500 font-bold uppercase">{{ quest.difficulty }}</p>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <p class="text-slate-500 mb-2 uppercase">Reward_Gold:</p>
                            <p class="text-yellow-400 font-bold">{{ quest.reward_gold }} GOLD</p>
                            <p class="text-slate-500 mb-2 uppercase">Reward_EXP:</p>
                            <p class="text-cyan-400 font-bold">{{ quest.reward_exp || quest.reward_gold }} EXP</p>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <h3 class="text-[12px] text-cyan-400 uppercase tracking-widest underline italic">Quest_Objectives:</h3>
                        <div
                            class="bg-black/30 p-4 md:p-6 border-l-4 border-slate-700 leading-relaxed font-sans text-[14px] text-slate-300 whitespace-pre-wrap"
                        >
                            {{ quest.description || 'NO ADDITIONAL DATA PROVIDED BY THE GUILD.' }}
                        </div>
                    </div>

                    <div
                        v-if="isLate && !canSubmit && !hasSubmitted"
                        class="mt-8 p-4 md:p-6 border-2 border-dashed border-yellow-700 bg-yellow-950/20"
                    >
                        <h3 class="text-[12px] mb-4 uppercase tracking-widest text-yellow-400">
                            Quest_Locked_By_Deadline
                        </h3>
                        <p class="text-[12px] text-slate-300 font-sans mb-4">
                            Quest ini sudah lewat deadline. Gunakan item <span class="text-yellow-300 font-bold">Time Key</span>
                            untuk membuka ulang quest ini.
                        </p>
                        <div class="flex flex-col sm:flex-row gap-3 sm:items-center sm:justify-between">
                            <p class="text-[11px] text-slate-400 uppercase">Your_Time_Key: {{ timeKeyQty || 0 }}</p>
                            <div class="flex gap-2">
                                <Link
                                    :href="route('shop.index')"
                                    class="px-3 py-2 text-[10px] uppercase border-2 border-cyan-800 bg-cyan-900/30 text-cyan-300 hover:bg-cyan-700/40 transition-colors"
                                >
                                    Open_Shop
                                </Link>
                                <button
                                    type="button"
                                    class="px-3 py-2 text-[10px] uppercase border-2 border-yellow-700 bg-yellow-700/20 text-yellow-300 hover:bg-yellow-600/40 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                    :disabled="unlockForm.processing || (timeKeyQty || 0) < 1"
                                    @click="unlockLateQuest"
                                >
                                    {{ unlockForm.processing ? 'PROCESSING...' : 'Use_Time_Key' }}
                                </button>
                            </div>
                        </div>
                        <p v-if="page.props.errors?.unlock" class="mt-3 text-[10px] text-red-400 font-sans">
                            {{ page.props.errors.unlock }}
                        </p>
                    </div>

                    <div v-if="canSubmit" class="mt-8 p-4 md:p-6 border-2 border-dashed border-cyan-900 bg-black/20">
                        <h3 class="text-[12px] mb-6 uppercase tracking-widest" :class="props.hasSubmitted ? 'text-yellow-500' : 'text-white'">
                            >> {{ isStructuredTaskBankQuest ? 'Task_Bank_Submission' : (props.hasSubmitted ? 'Edit_Existing_Report' : 'Submit_Quest_Report') }}
                        </h3>

                        <form @submit.prevent="submitReport" class="space-y-6">
                            <div v-if="isStructuredTaskBankQuest" class="space-y-4">
                                <div class="bg-slate-900/40 border border-cyan-900/50 p-3">
                                    <p class="text-[10px] text-cyan-300 uppercase">
                                        BANK: {{ quest.task_bank?.name || 'TASK_BANK' }} [{{ taskBankType || 'essay' }}]
                                    </p>
                                    <p class="text-[10px] text-slate-400 font-sans mt-1">
                                        <template v-if="isAutoCheckedTaskBankQuest">
                                            Jawab semua soal. Reward dihitung proporsional dari nilai akhir.
                                        </template>
                                        <template v-else>
                                            Jawab semua soal. Submission akan direview oleh admin.
                                        </template>
                                    </p>
                                </div>

                                <p v-if="form.errors.task_answers" class="text-[10px] text-red-400 font-sans">
                                    {{ form.errors.task_answers }}
                                </p>

                                <div
                                    v-for="(question, index) in taskQuestions"
                                    :key="question.uuid"
                                    class="bg-black/30 border border-slate-700 p-4 space-y-3"
                                >
                                    <p class="text-[10px] text-yellow-300 uppercase">
                                        Q{{ index + 1 }} · WEIGHT {{ question.weight || 1 }}
                                    </p>
                                    <p class="text-[13px] text-slate-200 font-sans whitespace-pre-wrap">
                                        {{ question.question_text }}
                                    </p>

                                    <div v-if="question.question_type === 'multiple_choice'" class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                        <label
                                            v-for="(option, optionIndex) in (question.options_json || [])"
                                            :key="`${question.uuid}-${optionIndex}`"
                                            class="flex items-center gap-2 border border-slate-700 px-3 py-2 cursor-pointer hover:border-cyan-500 transition-colors"
                                        >
                                            <input
                                                v-model="form.task_answers[question.uuid]"
                                                type="radio"
                                                :name="`answer-${question.uuid}`"
                                                :value="option"
                                                class="accent-cyan-500"
                                            >
                                            <span class="text-[12px] text-slate-300 font-sans">{{ option }}</span>
                                        </label>
                                    </div>

                                    <textarea
                                        v-else
                                        v-model="form.task_answers[question.uuid]"
                                        rows="3"
                                        class="w-full bg-[#0d1117] border-2 border-slate-800 p-3 text-white font-sans text-[13px] outline-none transition-all focus:border-cyan-400"
                                        placeholder="Tulis jawabanmu di sini..."
                                    />

                                    <p v-if="form.errors[`task_answers.${question.uuid}`]" class="text-[10px] text-red-400 font-sans">
                                        {{ form.errors[`task_answers.${question.uuid}`] }}
                                    </p>
                                </div>
                            </div>

                            <div v-else>
                                <label class="block text-[12px] text-slate-500 mb-2 uppercase">Proof_of_Completion:</label>
                                <textarea
                                    v-model="form.content"
                                    class="w-full bg-[#0d1117] border-2 p-3 text-white font-sans text-[14px] outline-none transition-all"
                                    :class="props.hasSubmitted ? 'border-yellow-900 focus:border-yellow-400' : 'border-slate-800 focus:border-cyan-400'"
                                    rows="4"
                                    required
                                ></textarea>
                                <p v-if="form.errors.content" class="mt-2 text-[10px] text-red-400 font-sans">
                                    {{ form.errors.content }}
                                </p>
                            </div>

                            <div v-if="isStructuredTaskBankQuest" class="space-y-2">
                                <label class="block text-[12px] text-slate-500 uppercase">
                                    Additional_Notes: <span class="font-sans normal-case">Optional</span>
                                </label>
                                <textarea
                                    v-model="form.content"
                                    class="w-full bg-[#0d1117] border-2 border-slate-800 p-3 text-white font-sans text-[13px] outline-none transition-all focus:border-cyan-400"
                                    rows="3"
                                    placeholder="Catatan tambahan untuk reviewer (opsional)."
                                ></textarea>
                                <p v-if="form.errors.content" class="text-[10px] text-red-400 font-sans">
                                    {{ form.errors.content }}
                                </p>
                            </div>

                            <div v-if="!isAutoCheckedTaskBankQuest">
                                <label class="block text-[12px] text-slate-500 mb-2 uppercase">Evidence_Artifact:</label>

                                <div
                                    v-if="props.hasSubmitted && props.existingSubmission?.file_path"
                                    class="mb-4 p-3 bg-black/40 border border-yellow-900/50"
                                >
                                    <p class="text-[12px] text-slate-500 uppercase italic mb-2">Previously_Sent:</p>
                                    <div class="flex flex-col gap-2">
                                        <a
                                            :href="'/storage/' + props.existingSubmission.file_path"
                                            target="_blank"
                                            class="text-center text-[12px] bg-blue-900/50 text-blue-300 px-3 py-2 border border-blue-700 hover:bg-blue-600 hover:text-white transition-all uppercase font-bold"
                                        >
                                            [ VIEW_CURRENT_FILE ]
                                        </a>
                                    </div>
                                </div>

                                <input
                                    type="file"
                                    @change="handleFileChange"
                                    accept="image/*,application/pdf"
                                    class="text-[12px] text-slate-400 file:bg-slate-800 file:border-2 file:border-slate-700 file:text-white file:px-2 file:py-1 file:mr-2 file:uppercase cursor-pointer w-full border-2 border-slate-800 p-2 bg-[#0d1117]"
                                />

                                <div v-if="form.errors.file" class="text-red-500 text-[12px] mt-2 uppercase">{{ form.errors.file }}</div>
                            </div>

                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="w-full py-4 border-2 transition-all font-bold uppercase text-[12px] active:scale-95"
                                :class="isAutoCheckedTaskBankQuest
                                    ? 'bg-cyan-800/40 border-cyan-300 text-cyan-200'
                                    : (props.hasSubmitted ? 'bg-yellow-900/20 border-yellow-500 text-yellow-500' : 'bg-cyan-900/40 border-cyan-400 text-cyan-400')"
                            >
                                {{ form.processing
                                    ? (isAutoCheckedTaskBankQuest ? 'AUTO_CHECKING...' : 'TRANSMITTING...')
                                    : (isAutoCheckedTaskBankQuest
                                        ? (props.hasSubmitted ? 'UPDATE_AND_RECHECK' : 'SUBMIT_AND_AUTO_CHECK')
                                        : (isStructuredTaskBankQuest
                                            ? (props.hasSubmitted ? 'UPDATE_TASK_SUBMISSION' : 'SUBMIT_TASK_SUBMISSION')
                                            : (props.hasSubmitted ? 'UPDATE_REPORT' : 'EXECUTE_REPORT'))) }}
                            </button>
                        </form>
                    </div>

                    <div
                        class="pt-6 border-t-2 border-slate-800 text-[12px] text-slate-600 flex flex-col sm:flex-row justify-between gap-2 italic uppercase"
                    >
                        <span>Issued: {{ new Date(quest.created_at).toLocaleDateString() }}</span>
                        <span :class="quest.status === 'Available' ? 'text-cyan-400' : 'text-yellow-500'">
                            Status: {{ quest.status || 'AVAILABLE' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
