<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import Swal from 'sweetalert2';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    quest: Object,
    hasSubmitted: Boolean,
    existingSubmission: Object,
    isLate: Boolean,
    hasQuestUnlock: Boolean,
    canSubmit: Boolean,
    timeKeyQty: Number,
    isStaffPlayMode: Boolean,
    initialPlatformingProgress: Object,
});

const existingStatus = computed(() => props.existingSubmission?.status || null);
const canResubmitPending = computed(() => props.hasSubmitted && props.canSubmit && existingStatus.value === 'Pending');
const canResubmitRejected = computed(() => props.hasSubmitted && props.canSubmit && existingStatus.value === 'Rejected');

const taskAnswersFromSubmission = computed(() => {
    const answers = props.existingSubmission?.scores_detail?.answers;
    return answers && typeof answers === 'object' ? answers : {};
});

const form = useForm({
    content: props.existingSubmission?.content || '',
    file: null,
    task_answers: { ...(taskAnswersFromSubmission.value || {}) },
});

const unlockForm = useForm({});
const page = usePage();
const maxSubmissionFileBytes = 10 * 1024 * 1024;

const questDraftStorageKey = computed(() => {
    const questKey = String(props.quest?.uuid || props.quest?.id || 'quest');
    const userKey = String(page.props?.auth?.user?.id || 'guest');
    return `quest-draft:${userKey}:${questKey}`;
});

let draftSaveTimer = null;

const clearDraft = () => {
    if (typeof window === 'undefined') return;
    try {
        window.localStorage.removeItem(questDraftStorageKey.value);
    } catch (_) { }
};

const persistDraft = () => {
    if (typeof window === 'undefined') return;
    if (draftSaveTimer) clearTimeout(draftSaveTimer);
    draftSaveTimer = window.setTimeout(() => {
        try {
            window.localStorage.setItem(questDraftStorageKey.value, JSON.stringify({
                content: form.content || '',
                task_answers: { ...(form.task_answers || {}) },
            }));
        } catch (_) { }
    }, 150);
};

const loadDraftFromStorage = () => {
    if (typeof window === 'undefined') return;
    try {
        const raw = window.localStorage.getItem(questDraftStorageKey.value);
        if (!raw) return;
        const parsed = JSON.parse(raw);
        if (!parsed || typeof parsed !== 'object') return;
        if (typeof parsed.content === 'string') form.content = parsed.content;
        if (parsed.task_answers && typeof parsed.task_answers === 'object' && !Array.isArray(parsed.task_answers)) {
            form.task_answers = { ...(form.task_answers || {}), ...parsed.task_answers };
        }
    } catch (_) { }
};

watch(() => form.content, persistDraft);
watch(() => form.task_answers, persistDraft, { deep: true });

const taskBankType = computed(() => props.quest?.task_bank?.assessment_type || null);
const taskQuestions = computed(() => props.quest?.task_bank?.questions || []);
const isStructuredTaskBankQuest = computed(() => !!props.quest?.task_bank && ['multiple_choice', 'mixed', 'essay', 'platforming', 'word_match'].includes(taskBankType.value));
const isAutoCheckedTaskBankQuest = computed(() => (taskBankType.value === 'multiple_choice' || taskBankType.value === 'platforming' || taskBankType.value === 'word_match'));
const isEditSubmissionMode = computed(() => Boolean(props.hasSubmitted));

const unansweredCount = computed(() => {
    if (!isStructuredTaskBankQuest.value) return 0;
    return taskQuestions.value.filter((question) => {
        const value = form.task_answers?.[question.uuid];
        return !value || String(value).trim() === '';
    }).length;
});

const questToneStyle = computed(() => {
    const toneKey = props.quest?.study_group_id ?? 'global';
    return { '--quest-tone-border': '#2d65cf', '--quest-tone-bg': 'rgba(22, 47, 93, 0.16)', '--quest-tone-accent': '#8cc4ff' };
});

const handleFileChange = (e) => {
    const file = e.target.files[0];
    if (file && file.size > maxSubmissionFileBytes) {
        Swal.fire({ title: 'FILE_TOO_LARGE', text: 'Max 10MB.', icon: 'error', background: '#161b22', color: '#ef4444' });
        e.target.value = '';
        form.file = null;
        return;
    }
    form.file = file;
};

// === SHARED GAME DATA & PERSISTENCE ===
const pfStageIndex = ref(0);
const pfPlayerLevel = ref(0);
const pfAnswers = ref([]);
const pfFinished = ref(false);
const pfFeedback = ref(null);
const pfShuffledOptions = ref([]);
const pfStageRef = ref(null);
const viewportWidth = ref(typeof window !== 'undefined' ? window.innerWidth : 1280);

const wmPlacedWords = ref([]);
const wmAllCards = ref([]);
const wmFinished = ref(false);
const wmFeedback = ref(null);

const gameDuration = computed(() => props.quest?.task_bank?.duration || 60);

const pfTimeLeft = ref(gameDuration.value);
const wmTimeLeft = ref(gameDuration.value);
let gameTimerInterval = null;
let lastSyncTime = 0;
let isResuming = false;

const pfGameStateKey = computed(() => `pf-state:${page.props.auth.user.id}:${props.quest.uuid}`);
const wmGameStateKey = computed(() => `wm-state:${page.props.auth.user.id}:${props.quest.uuid}`);

const saveGameState = async (force = false) => {
    if (typeof window === 'undefined' || pfFinished.value || wmFinished.value) return;
    const now = Date.now();
    const state = taskBankType.value === 'word_match' 
        ? { placed: wmPlacedWords.value, time_left: wmTimeLeft.value, all_cards: wmAllCards.value }
        : { index: pfStageIndex.value, level: pfPlayerLevel.value, answers: pfAnswers.value, time_left: pfTimeLeft.value };
    
    localStorage.setItem(taskBankType.value === 'word_match' ? wmGameStateKey.value : pfGameStateKey.value, JSON.stringify(state));

    if (force || (now - lastSyncTime > 3000)) {
        lastSyncTime = now;
        try {
            const payload = taskBankType.value === 'word_match' 
                ? { index: 0, level: 0, answers: [], time_left: wmTimeLeft.value, wm_state: state }
                : { ...state };
            await axios.post(route('quests.platforming-progress.save', props.quest.uuid), payload);
        } catch (e) { }
    }
};

const loadGameState = () => {
    const serverProgress = props.initialPlatformingProgress;
    const localRaw = localStorage.getItem(taskBankType.value === 'word_match' ? wmGameStateKey.value : pfGameStateKey.value);
    const state = serverProgress || (localRaw ? JSON.parse(localRaw) : null);
    
    if (!state) return;
    isResuming = true;

    if (taskBankType.value === 'word_match') {
        const wmState = state.wm_state || state;
        wmPlacedWords.value = wmState.placed || [];
        wmTimeLeft.value = wmState.time_left ?? gameDuration.value;
        wmAllCards.value = wmState.all_cards || [];
        if (state.complete) wmFinished.value = true;
    } else {
        pfStageIndex.value = state.index || 0;
        pfPlayerLevel.value = state.level || 0;
        pfAnswers.value = state.answers || [];
        pfTimeLeft.value = state.time_left ?? gameDuration.value;
        if (pfStageIndex.value >= (props.quest.task_bank?.questions[0]?.options_json?.stages?.length || 0)) pfFinished.value = true;
    }
};

const clearGameState = () => {
    localStorage.removeItem(pfGameStateKey.value);
    localStorage.removeItem(wmGameStateKey.value);
};

const stopGameTimer = () => { if (gameTimerInterval) { clearInterval(gameTimerInterval); gameTimerInterval = null; } };

const startGameTimer = () => {
    stopGameTimer();
    gameTimerInterval = setInterval(() => {
        if (taskBankType.value === 'word_match') {
            if (wmTimeLeft.value > 0) { wmTimeLeft.value--; saveGameState(); }
            else { stopGameTimer(); wmSubmitGame(true); }
        } else {
            if (pfTimeLeft.value > 0) { pfTimeLeft.value--; saveGameState(); }
            else { stopGameTimer(); pfSelectAnswer(null); }
        }
    }, 1000);
};

// === PLATFORMING LOGIC ===
const pfQuestion = computed(() => taskBankType.value === 'platforming' ? taskQuestions.value[0] : null);
const pfStages = computed(() => {
    let raw = pfQuestion.value?.options_json;
    if (typeof raw === 'string') try { raw = JSON.parse(raw); } catch(e){}
    return raw?.stages || [];
});
const pfTotalStages = computed(() => pfStages.value.length);
const pfCurrentStage = computed(() => pfStages.value[pfStageIndex.value] || null);
const pfCorrectCount = computed(() => pfAnswers.value.filter(a => a.correct).length);
const pfNodeDisplayCurrent = computed(() => {
    if (pfTotalStages.value <= 0) return 0;
    return Math.min(pfStageIndex.value + 1, pfTotalStages.value);
});
const pfCharacterStage = computed(() => Math.min(pfTotalStages.value, Math.max(1, pfPlayerLevel.value + 1)));
const pfPositionPercent = computed(() => pfTotalStages.value === 0 ? 0 : Math.round((pfCharacterStage.value / pfTotalStages.value) * 100));

const pfCameraOffset = computed(() => {
    if (!pfStageRef.value) return 0;
    const vh = pfStageRef.value.offsetHeight || 300;
    const isDesktop = viewportWidth.value >= 1024;
    const isMobile = viewportWidth.value < 640;
    const platformStep = isDesktop ? 66 : (isMobile ? 40 : 46);
    const cameraFocus = isDesktop ? 0.72 : (isMobile ? 0.56 : 0.6);
    const charY = (pfPlayerLevel.value + 0.5) * platformStep;
    return (vh * cameraFocus) - charY;
});

const pfPlatformLevels = computed(() => Array.from({ length: pfTotalStages.value + 5 }, (_, i) => i + 1));
const pfPlatformWidth = (s) => {
    if (s > pfTotalStages.value || s < 1) return '0px';
    const isDesktop = viewportWidth.value >= 1024;
    const isMobile = viewportWidth.value < 640;
    const base = isDesktop ? 170 : (isMobile ? 86 : 104);
    const growth = isDesktop ? 72 : (isMobile ? 32 : 42);
    return `${Math.round(base + ((s / Math.max(1, pfTotalStages.value)) * growth))}px`;
};

const pfShuffleOptions = () => {
    const stage = pfCurrentStage.value;
    if (!stage) return;
    pfShuffledOptions.value = [stage.correct_answer, ...(stage.wrong_answers || [])].sort(() => Math.random() - 0.5);
    if (!isResuming) pfTimeLeft.value = gameDuration.value;
    isResuming = false;
    startGameTimer();
};

const pfSelectAnswer = (ans) => {
    if (pfFinished.value || pfFeedback.value) return;
    stopGameTimer();
    const isCorrect = ans === pfCurrentStage.value?.correct_answer;
    pfPlayerLevel.value = isCorrect ? pfPlayerLevel.value + 1 : Math.max(0, pfPlayerLevel.value - 1);
    pfAnswers.value.push({ stage: pfStageIndex.value, answer: ans || '[TIMEOUT]', correct: isCorrect });
    pfFeedback.value = isCorrect ? 'correct' : 'wrong';
    pfTimeLeft.value = gameDuration.value;
    saveGameState(true);
    setTimeout(() => {
        pfFeedback.value = null;
        pfStageIndex.value++;
        if (pfStageIndex.value >= pfTotalStages.value) {
            pfFinished.value = true;
            clearGameState();
            submitFinalGamePayload('platforming');
        } else { pfShuffleOptions(); }
    }, 1000);
};

// === WORD MATCH LOGIC ===
const wmQuestion = computed(() => taskBankType.value === 'word_match' ? taskQuestions.value[0] : null);
const wmConfig = computed(() => {
    let raw = wmQuestion.value?.options_json;
    if (typeof raw === 'string') try { raw = JSON.parse(raw); } catch(e){}
    return raw || {};
});
const wmSentenceParts = computed(() => (wmConfig.value.sentence || '').split('___'));
const wmBlankCount = computed(() => Math.max(0, wmSentenceParts.value.length - 1));
const wmAvailableCards = computed(() => {
    const used = wmPlacedWords.value.filter(w => w !== null);
    const pool = [...wmAllCards.value];
    for (const w of used) { const i = pool.indexOf(w); if (i !== -1) pool.splice(i, 1); }
    return pool;
});

const wmInitGame = () => {
    if (wmAllCards.value.length === 0) {
        const cards = [...(wmConfig.value.blanks || []), ...(wmConfig.value.distractors || [])];
        wmAllCards.value = cards.sort(() => Math.random() - 0.5);
    }
    if (wmPlacedWords.value.length === 0) wmPlacedWords.value = Array(wmBlankCount.value).fill(null);
    if (!isResuming) wmTimeLeft.value = gameDuration.value;
    isResuming = false;
    startGameTimer();
};

const wmPlaceWord = (w) => {
    const i = wmPlacedWords.value.findIndex(x => x === null);
    if (i !== -1) { wmPlacedWords.value[i] = w; saveGameState(); }
};

const wmRemoveWord = (i) => { wmPlacedWords.value[i] = null; saveGameState(); };

const wmSubmitGame = (isTimeout = false) => {
    if (wmFinished.value) return;
    const isComplete = wmPlacedWords.value.every(w => w !== null);
    if (!isComplete && !isTimeout) {
        Swal.fire({ title: 'INCOMPLETE', text: 'Lengkapi transmisi data.', icon: 'warning', background: '#161b22', color: '#f59e0b' });
        return;
    }
    stopGameTimer();
    let correct = 0;
    wmPlacedWords.value.forEach((w, i) => { if (w === wmConfig.value.blanks[i]) correct++; });
    wmFinished.value = true;
    wmFeedback.value = correct === wmBlankCount.value ? 'perfect' : 'partial';
    clearGameState();
    // FIX: Include 'complete' status for backend validation
    submitFinalGamePayload('word_match', { 
        placed: wmPlacedWords.value, 
        correct_count: correct, 
        total: wmBlankCount.value, 
        timeout: isTimeout,
        complete: isComplete 
    });
};

const submitFinalGamePayload = (type, extra = {}) => {
    const q = type === 'word_match' ? wmQuestion.value : pfQuestion.value;
    const payload = type === 'word_match' ? extra : { answers: pfAnswers.value, level: pfPlayerLevel.value, score: pfCorrectCount.value, total: pfTotalStages.value };
    form.task_answers[q.uuid] = JSON.stringify(payload);
    setTimeout(() => { form.post(route('submissions.store', props.quest.uuid), { preserveScroll: true }); }, 2000);
};

const submitReport = () => {
    if (form.processing) {
        return;
    }

    if (isStructuredTaskBankQuest.value && unansweredCount.value > 0) {
        Swal.fire({
            title: 'INCOMPLETE_REPORT',
            text: `Masih ada ${unansweredCount.value} soal yang belum diisi.`,
            icon: 'warning',
            background: '#161b22',
            color: '#f59e0b',
        });
        return;
    }

    if (!isStructuredTaskBankQuest.value && String(form.content || '').trim() === '' && !form.file) {
        Swal.fire({
            title: 'CONTENT_REQUIRED',
            text: 'Isi laporan atau upload file submission terlebih dahulu.',
            icon: 'warning',
            background: '#161b22',
            color: '#f59e0b',
        });
        return;
    }

    form.post(route('submissions.store', props.quest.uuid), {
        preserveScroll: true,
        onSuccess: () => {
            clearDraft();
        },
        onError: (errors) => {
            const firstError = Object.values(errors || {}).find((value) => Boolean(value));
            Swal.fire({
                title: 'SUBMIT_FAILED',
                text: String(firstError || 'Submission gagal dikirim.'),
                icon: 'error',
                background: '#161b22',
                color: '#ef4444',
            });
        },
    });
};

const syncViewportWidth = () => {
    if (typeof window === 'undefined') return;
    viewportWidth.value = window.innerWidth || 1280;
};

onMounted(() => {
    syncViewportWidth();
    if (typeof window !== 'undefined') window.addEventListener('resize', syncViewportWidth);
    loadDraftFromStorage();
    if (isStructuredTaskBankQuest.value && !props.hasSubmitted) {
        loadGameState();
        if (taskBankType.value === 'platforming' && !pfFinished.value) pfShuffleOptions();
        if (taskBankType.value === 'word_match' && !wmFinished.value) wmInitGame();
    }
});

onBeforeUnmount(() => {
    if (typeof window !== 'undefined') window.removeEventListener('resize', syncViewportWidth);
    if (draftSaveTimer) clearTimeout(draftSaveTimer);
    stopGameTimer();
});

const unlockLateQuest = () => {
    Swal.fire({ title: 'USE_TIME_KEY?', text: '1 Time Key akan digunakan.', icon: 'warning', showCancelButton: true, confirmButtonText: 'YES', background: '#161b22', color: '#4ed4d4' })
    .then((res) => { if (res.isConfirmed) unlockForm.post(route('quests.unlock-late', props.quest.uuid)); });
};
</script>

<template>
    <AuthenticatedLayout>
        <!-- GAME UI (Platforming or Word Match) -->
        <div v-if="taskBankType === 'platforming' || taskBankType === 'word_match'" class="game-scene fixed inset-0 flex items-center justify-center overflow-hidden">
            <Head :title="'MISSION - ' + quest.title" />

            <div class="pf-frame w-full mx-auto overflow-hidden transition-all duration-300"
                 :class="{ 'pf-shake border-red-500 shadow-[0_0_50px_rgba(239,68,68,0.4)]': pfFeedback === 'wrong' }">
                
                <!-- IF ACTIVE PLAYING -->
                <div v-if="canSubmit && !hasSubmitted" class="pf-desktop-grid h-full">
                    <div class="pf-main-col flex flex-col h-full min-h-0">
                        <div class="pf-main-inner flex flex-col h-full border-r border-cyan-300/10">
                            <!-- Header -->
                            <div class="shrink-0 flex items-center justify-between px-3 py-2 border-b border-cyan-300/20 bg-slate-950/90 relative z-30">
                                <div class="flex items-center gap-2 shrink-0">
                                    <Link :href="route('lobby')" class="text-rose-400 hover:text-rose-300 text-[8px] border border-rose-500/30 px-2 py-1 pf-bevel-xs bg-rose-500/5 font-bold uppercase">✕ EXIT</Link>
                                    <div class="flex items-center gap-1 px-1.5 py-0.5 bg-black/40 border border-slate-800 pf-bevel-xs">
                                        <span class="w-1 h-1 rounded-full bg-red-500 animate-pulse shadow-[0_0_5px_rgba(239,68,68,0.8)]"></span>
                                        <span class="text-[7px] text-red-400 font-bold uppercase tracking-tighter">Live</span>
                                    </div>
                                </div>
                                <span class="hidden sm:block text-white text-[9px] font-bold truncate tracking-widest uppercase flex-1 text-center px-4">{{ quest.title }}</span>
                                <div class="bg-amber-500/10 border border-amber-500/30 px-2 py-1 pf-bevel-xs shrink-0">
                                    <span class="text-amber-300 text-[9px] font-black tabular-nums">{{ quest.reward_gold }}G</span>
                                </div>
                            </div>

                            <!-- STAGE AREA (SENSOR HERE) -->
                            <div ref="pfStageRef" class="pf-stage relative overflow-hidden flex-1 min-h-0">
                                <!-- Parallax & Background -->
                                <div class="absolute inset-0 z-0 opacity-20 pointer-events-none">
                                    <div class="pf-city-layer pf-city-2"></div><div class="pf-city-layer pf-city-1"></div>
                                    <div class="pf-parallax-bg-line absolute top-10 left-10 w-32 h-32 border border-cyan-500/30 rotate-12"></div>
                                    <div class="pf-parallax-bg-line absolute bottom-20 right-10 w-48 h-48 border border-blue-500/20 -rotate-12"></div>
                                </div>
                                <div class="pf-scanline"></div><div class="pf-center-beam"></div>
                                <div v-if="taskBankType === 'platforming' && pfFeedback" class="pf-hit-overlay pointer-events-none"
                                     :class="pfFeedback === 'correct' ? 'pf-hit-overlay-correct' : 'pf-hit-overlay-wrong'">
                                    <div class="pf-hit-flash"></div>
                                    <div class="pf-hit-ring"></div>
                                    <div class="pf-hit-ring pf-hit-ring-delay"></div>
                                    <div class="pf-hit-text">
                                        {{ pfFeedback === 'correct' ? 'DIRECT HIT +' : 'SYSTEM BREACH -' }}
                                    </div>
                                    <div v-for="i in 12" :key="`pf-hit-particle-${i}`" class="pf-hit-particle"></div>
                                </div>

                                <!-- GAME CONTENT (PLATFORMING) -->
                                <div v-if="taskBankType === 'platforming'" 
                                    class="absolute left-0 right-0 bottom-0 flex flex-col-reverse items-center px-5 transition-transform duration-700 ease-out z-10"
                                    :style="{ transform: `translateY(${-pfCameraOffset}px)` }">
                                    <div class="w-full flex flex-col-reverse items-center"
                                         :class="{ 'pf-stage-impact-up': pfFeedback === 'correct', 'pf-stage-impact-down': pfFeedback === 'wrong' }">
                                        <div v-for="n in pfPlatformLevels" :key="`plat-${n}`" class="pf-platform-row flex items-end justify-center relative shrink-0">
                                            <template v-if="n <= pfTotalStages">
                                                <div class="h-2.5 rounded-sm transition-all duration-300 pf-bevel-sm pf-platform-circuit relative overflow-hidden"
                                                    :class="n <= pfPlayerLevel ? 'bg-gradient-to-r from-cyan-400 to-blue-600 w-28 shadow-[0_0_20px_rgba(56,189,248,0.6)]' : 'bg-slate-800/80 w-24 border border-slate-600/30'"
                                                    :style="{ width: pfPlatformWidth(n) }">
                                                    <div v-if="n === pfPlayerLevel && pfFeedback === 'correct'" class="absolute inset-0 bg-white/40 animate-pulse"></div>
                                                </div>
                                                <span class="absolute right-[-40px] bottom-1 text-[9px] text-slate-400/75 w-8">{{ n }}</span>
                                            </template>
                                            <div v-if="n === pfCharacterStage" class="absolute bottom-1 left-1/2 -translate-x-1/2 transition-all duration-700"
                                                :class="pfFeedback === 'correct' ? 'pf-jump-up' : (pfFeedback === 'wrong' ? 'pf-jump-down' : 'pf-idle-float')">
                                                <div class="pf-char-aura"></div>
                                                <img src="/images/platforming-character.png" alt="" class="pf-char-sprite relative z-10 pixelated" onerror="this.style.display='none';this.nextElementSibling.style.display='block'">
                                                <div class="pf-char-fallback bg-cyan-300 rounded-full hidden"></div>
                                                <div class="pf-char-shadow"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- GAME CONTENT (WORD MATCH) -->
                                <div v-if="taskBankType === 'word_match'" class="absolute inset-0 flex flex-col items-center justify-center p-4 sm:p-10 z-10 space-y-8">
                                    <div :class="{ 'opacity-20 grayscale pointer-events-none': wmFinished }" class="bg-black/80 backdrop-blur-xl border-2 border-cyan-500/20 p-8 sm:p-12 pf-bevel w-full max-w-4xl shadow-2xl relative overflow-hidden transition-all">
                                        <div class="flex flex-wrap items-center justify-center gap-x-2 gap-y-6 leading-relaxed">
                                            <template v-for="(part, pi) in wmSentenceParts" :key="pi">
                                                <span class="text-slate-100 text-sm sm:text-xl font-bold tracking-tight uppercase">{{ part }}</span>
                                                <div v-if="pi < wmBlankCount" @click="wmRemoveWord(pi)" 
                                                    class="inline-flex items-center justify-center min-w-[110px] sm:min-w-[160px] h-10 sm:h-12 px-3 border-2 transition-all cursor-pointer pf-bevel-xs relative overflow-hidden mx-1"
                                                    :class="wmPlacedWords[pi] ? 'border-cyan-400 bg-cyan-500/10 shadow-[0_0_15px_rgba(34,211,238,0.2)]' : 'border-slate-700 bg-black/60 shadow-inner'">
                                                    <span class="text-[10px] sm:text-sm font-black uppercase text-cyan-300 tracking-wider animate-pf-pop-in">{{ wmPlacedWords[pi] || '' }}</span>
                                                    <div v-if="!wmPlacedWords[pi]" class="absolute inset-0 bg-cyan-500/5 pf-laser-scan" style="animation-duration: 4s"></div>
                                                    <span v-if="!wmPlacedWords[pi]" class="text-[7px] text-slate-600 font-bold uppercase tracking-tighter opacity-40">awaiting_data</span>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                    
                                    <div v-if="!wmFinished" class="w-full max-w-3xl flex flex-wrap justify-center gap-3">
                                        <button v-for="(card, ci) in wmAvailableCards" :key="ci" @click="wmPlaceWord(card)"
                                            class="px-5 py-3 bg-slate-900/60 border border-cyan-500/40 text-cyan-100 text-[9px] sm:text-[11px] pf-bevel-sm hover:bg-cyan-600 hover:text-white transition-all active:scale-95 uppercase font-black tracking-widest relative group overflow-hidden">
                                            <div class="absolute inset-0 bg-white/5 translate-y-full group-hover:translate-y-0 transition-transform"></div>
                                            {{ card }}
                                        </button>
                                    </div>

                                    <button v-if="!wmFinished" @click="wmSubmitGame(false)" class="px-12 py-3 border-2 border-emerald-500 text-emerald-400 text-[10px] font-black uppercase pf-bevel hover:bg-emerald-600 hover:text-white transition-all shadow-xl">
                                        [ TRANSMIT_FINAL_DATA ]
                                    </button>

                                    <!-- WM COMPLETION OVERLAY -->
                                    <div v-if="wmFinished" class="absolute inset-0 z-30 flex flex-col items-center justify-center bg-black/80 backdrop-blur-sm space-y-4">
                                        <div class="p-8 border-4 text-center pf-bevel shadow-2xl transition-all duration-500"
                                            :class="wmFeedback === 'perfect' ? 'border-emerald-500 bg-slate-900/90 shadow-emerald-500/30' : 'border-rose-500 bg-slate-950/95 shadow-rose-500/40'">
                                            <p class="text-3xl font-black italic mb-2 tracking-tighter" :class="wmFeedback === 'perfect' ? 'text-emerald-400' : 'text-rose-500'">
                                                {{ wmFeedback === 'perfect' ? 'MISSION SUCCESS' : 'MISSION FAILURE' }}
                                            </p>
                                            <p class="text-white text-lg font-bold uppercase tracking-widest">{{ wmFeedback === 'perfect' ? 'Data Fully Recovered' : 'Link Connection Lost' }}</p>
                                            <p class="text-[10px] text-slate-500 mt-4 animate-pulse">Finalizing transmission protocols...</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- SHARED HUD (RESPONSIVE BAR) -->
                                <div class="absolute top-2 left-0 right-0 z-20 px-2 flex justify-between items-start pointer-events-none gap-2">
                                    <!-- Left Group: Mission Info -->
                                    <div class="flex flex-col gap-1 items-start">
                                        <div class="bg-black/85 backdrop-blur-md px-2 py-1 text-[8px] text-cyan-300 font-bold border border-cyan-500/30 pf-bevel-xs pf-hud-card whitespace-nowrap shadow-xl">
                                            {{ taskBankType === 'word_match' ? 'MODE: RECON' : `NODE: ${pfNodeDisplayCurrent}/${pfTotalStages}` }}
                                        </div>
                                        <div v-if="taskBankType === 'word_match'" class="bg-black/85 backdrop-blur-md px-2 py-1 text-[8px] text-slate-400 border border-slate-800 pf-bevel-xs pf-hud-card whitespace-nowrap">
                                            RECOVERY: {{ wmPlacedWords.filter(w=>w).length }}/{{ wmBlankCount }}
                                        </div>
                                    </div>

                                    <!-- Right Group: Player Stats -->
                                    <div v-if="taskBankType === 'platforming'" class="flex flex-col gap-1 items-end">
                                        <div class="bg-black/85 backdrop-blur-md px-2 py-1 text-[8px] text-cyan-300 font-bold border border-cyan-500/30 pf-bevel-xs pf-hud-card whitespace-nowrap shadow-xl">
                                            LV.{{ pfPlayerLevel }} | ALT {{ pfPositionPercent }}%
                                        </div>
                                    </div>
                                </div>
                                <div v-if="!pfFinished && !wmFinished && !pfFeedback" class="absolute bottom-4 left-1/2 -translate-x-1/2 z-20 w-48 h-6 bg-black/85 border-2 border-slate-700 pf-bevel-xs flex items-center px-1.5">
                                    <div class="h-2 transition-all duration-1000 linear relative overflow-hidden" 
                                         :class="(taskBankType==='word_match'?wmTimeLeft:pfTimeLeft) > 5 ? 'bg-cyan-400 shadow-[0_0_15px_rgba(34,211,238,0.7)]' : 'bg-rose-500 animate-pulse shadow-[0_0_15px_rgba(244,63,94,0.8)]'"
                                         :style="{ width: `${((taskBankType==='word_match'?wmTimeLeft:pfTimeLeft) / gameDuration) * 100}%` }">
                                         <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/30 to-transparent pf-laser-scan"></div>
                                    </div>
                                    <span class="absolute inset-0 flex items-center justify-center text-[10px] text-white font-black tracking-widest drop-shadow-[0_1px_3px_rgba(0,0,0,1)] uppercase">
                                        {{ taskBankType==='word_match'?wmTimeLeft:pfTimeLeft }}S
                                    </span>
                                </div>
                            </div>

                            <!-- QUESTION FOOTER (Only for Platforming) -->
                            <div v-if="taskBankType === 'platforming'" class="shrink-0 flex flex-col">
                                <div class="px-3 py-2 bg-slate-900/95 border-y border-cyan-300/10 flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <div class="flex gap-0.5"><div v-for="i in 3" :key="i" class="w-0.5 bg-cyan-400 animate-bounce h-2"></div></div>
                                        <span class="text-slate-400 uppercase tracking-widest text-[8px]">Link_Status: Active</span>
                                    </div>
                                    <span class="text-cyan-300 font-black text-[10px] tabular-nums">{{ pfCorrectCount }}/{{ pfTotalStages }}</span>
                                </div>
                                <div class="px-4 py-6 bg-slate-950/40 min-h-[180px]">
                                    <div v-if="!pfFinished && pfCurrentStage && !pfFeedback" class="space-y-6">
                                        <p class="text-[12px] text-white leading-relaxed font-medium pl-3 border-l-2 border-cyan-500 uppercase tracking-tight">{{ pfCurrentStage.prompt }}</p>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                            <button v-for="(opt, oi) in pfShuffledOptions" :key="oi" @click="pfSelectAnswer(opt)" 
                                                class="pf-answer-btn w-full px-4 py-3 bg-slate-800/40 border border-slate-600/40 text-slate-200 text-[10px] pf-bevel-sm transition-all text-left group relative overflow-hidden active:scale-95 uppercase tracking-tighter">
                                                <div class="absolute inset-0 bg-cyan-500/10 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                                                <span class="relative z-10 group-hover:translate-x-2 transition-transform block">{{ opt }}</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <aside class="pf-side-col">
                        <div class="pf-side-card pf-bevel-sm">
                            <p class="pf-side-title"><img class="pf-px-icon" src="https://api.iconify.design/pixelarticons:book-open.svg" alt=""> Mission Brief</p>
                            <p class="pf-side-copy">{{ (taskBankType === 'word_match' ? wmConfig.sentence : pfCurrentStage?.prompt) || 'Selesaikan misi untuk memulihkan data.' }}</p>
                        </div>
                        <div class="pf-side-card pf-bevel-sm">
                            <p class="pf-side-title"><img class="pf-px-icon" src="https://api.iconify.design/pixelarticons:chart-add.svg" alt=""> Progress</p>
                            <div v-if="taskBankType==='platforming'" class="space-y-1">
                                <div class="pf-stat-line"><span>Nodes</span><strong>{{ pfNodeDisplayCurrent }}/{{ pfTotalStages }}</strong></div>
                                <div class="pf-stat-line"><span>Altitude</span><strong>{{ pfPositionPercent }}%</strong></div>
                            </div>
                            <div v-else class="pf-stat-line"><span>Words</span><strong>{{ wmPlacedWords.filter(w=>w).length }}/{{ wmBlankCount }}</strong></div>
                        </div>
                        <div class="pf-side-card pf-bevel-sm border-yellow-600/20">
                            <p class="pf-side-title text-yellow-500"><img class="pf-px-icon" src="https://api.iconify.design/pixelarticons:gift.svg" alt=""> Bounty</p>
                            <p class="text-amber-400 text-[11px] font-black">+{{ quest.reward_gold }} GOLD</p>
                            <p class="text-cyan-400 text-[11px] font-black">+{{ quest.reward_exp || quest.reward_gold }} EXP</p>
                        </div>
                    </aside>
                </div>

                <!-- MISSION DEBRIEF -->
                <div v-else-if="hasSubmitted" class="h-full flex flex-col overflow-y-auto bg-slate-950 relative">
                    <!-- Background Effects -->
                    <div class="absolute inset-0 z-0 opacity-20 pointer-events-none overflow-hidden">
                        <div class="pf-scanline"></div>
                        <div v-for="i in 8" :key="`bg-bit-${i}`" class="absolute w-1 h-1 bg-emerald-500/30 pf-floating-bit"
                             :style="{ left: `${Math.random() * 100}%`, bottom: '-20px', animationDelay: `${Math.random() * 5}s` }"></div>
                    </div>

                    <!-- Header (Responsive Stack) -->
                    <div class="shrink-0 p-4 border-b border-emerald-500/20 bg-slate-950/90 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between relative z-10">
                        <div class="flex items-center justify-between w-full sm:w-auto gap-4">
                            <Link :href="route('lobby')" class="text-emerald-400 text-[8px] border border-emerald-500/30 px-3 py-1.5 pf-bevel-xs font-bold uppercase hover:bg-emerald-500 hover:text-black transition-all">✕ EXIT_TO_LOBBY</Link>
                            <div class="sm:hidden bg-emerald-500/20 border border-emerald-400/50 px-2 py-1 pf-bevel-xs shadow-[0_0_10px_rgba(16,185,129,0.2)]">
                                <span class="text-emerald-300 text-[8px] font-black uppercase">ARCHIVED</span>
                            </div>
                        </div>
                        <div class="flex flex-col items-center sm:items-end flex-1">
                            <span class="text-white text-[10px] font-bold tracking-[0.2em] uppercase">TRANSMISSION_FINALIZED</span>
                            <span class="text-[7px] text-emerald-500/50 font-mono tracking-tighter mt-0.5">REF_ID: {{ existingSubmission.uuid.substring(0,12) }}</span>
                        </div>
                        <div class="hidden sm:block bg-emerald-500/10 border border-emerald-500/30 px-2 py-1 pf-bevel-xs"><span class="text-emerald-400 text-[8px] font-black uppercase">ARCHIVED</span></div>
                    </div>

                    <!-- Content -->
                    <div class="flex-1 p-5 sm:p-10 flex flex-col items-center justify-center space-y-8 sm:space-y-12 relative z-10">
                        <!-- Holographic Success Icon (Fixed Green Color) -->
                        <div class="relative group scale-90 sm:scale-100">
                            <div class="absolute inset-[-25px] border border-emerald-500/20 rounded-full animate-spin-slow"></div>
                            <div class="absolute inset-[-15px] border border-dashed border-emerald-400/40 rounded-full animate-reverse-spin" style="animation-duration: 6s"></div>
                            
                            <!-- Green Glowing Checkmark Circle -->
                            <div class="w-20 h-20 sm:w-24 sm:h-24 rounded-full border-4 border-emerald-400 bg-emerald-500/20 flex items-center justify-center shadow-[0_0_40px_rgba(52,211,153,0.5)] relative z-10">
                                <svg class="w-10 h-10 sm:w-12 sm:h-12 text-emerald-300 drop-shadow-[0_0_8px_rgba(52,211,153,0.8)]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                        </div>

                        <!-- Glitch Title (Responsive Size) -->
                        <div class="text-center space-y-2">
                            <h2 class="pf-glitch-text text-emerald-400 text-xl sm:text-5xl font-black italic tracking-tighter uppercase leading-none" :data-text="'MISSION_ACCOMPLISHED'">
                                Mission_Accomplished
                            </h2>
                            <div class="flex items-center justify-center gap-3">
                                <div class="h-px w-8 sm:w-12 bg-emerald-500/30"></div>
                                <p class="text-slate-400 text-[8px] sm:text-[9px] font-black uppercase tracking-[0.2em] opacity-80">Sync_Complete_100%</p>
                                <div class="h-px w-8 sm:w-12 bg-emerald-500/30"></div>
                            </div>
                        </div>

                        <!-- Stats Grid -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 w-full max-w-xl px-2">
                            <div v-if="taskBankType !== 'word_match'" class="pf-side-card pf-bevel-sm border-emerald-500/30 bg-black/50 p-5 relative overflow-hidden">
                                <div class="absolute inset-0 opacity-5 pointer-events-none" style="background-image: repeating-linear-gradient(45deg, transparent, transparent 5px, #10b981 5px, #10b981 6px);"></div>
                                <p class="text-[7px] sm:text-[8px] text-emerald-500 uppercase font-black mb-3 tracking-widest flex items-center gap-2">
                                    <span class="w-1 h-1 bg-emerald-500 animate-ping"></span> PERFORMANCE_EVAL
                                </p>
                                <div class="flex items-center justify-between">
                                    <span class="text-4xl sm:text-5xl text-white font-black drop-shadow-[0_0_10px_rgba(16,185,129,0.3)]">{{ existingSubmission.grade || 'A' }}</span>
                                    <div class="text-right">
                                        <p class="text-[9px] sm:text-[10px] text-emerald-400 font-bold uppercase tracking-tighter">Status: Optimal</p>
                                        <p class="text-[7px] sm:text-[8px] text-slate-500 font-mono mt-1 opacity-60">> RANK_VERIFIED</p>
                                    </div>
                                </div>
                            </div>

                            <div class="pf-side-card pf-bevel-sm border-amber-500/30 bg-black/50 p-5 relative overflow-hidden"
                                 :class="taskBankType === 'word_match' ? 'sm:col-span-2' : ''">
                                <div class="absolute inset-0 opacity-5 pointer-events-none" style="background-image: repeating-linear-gradient(-45deg, transparent, transparent 5px, #f59e0b 5px, #f59e0b 6px);"></div>
                                <p class="text-[7px] sm:text-[8px] text-amber-500 uppercase font-black mb-3 tracking-widest flex items-center gap-2">
                                    <span class="w-1 h-1 bg-amber-500 rounded-full animate-pulse"></span> BOUNTY_COLLECTED
                                </p>
                                <div class="space-y-1">
                                    <p class="text-lg sm:text-xl text-amber-400 font-black tabular-nums">+{{ existingSubmission.earned_gold ?? quest.reward_gold }} <span class="text-[8px] text-amber-600/80 font-bold uppercase">Gold</span></p>
                                    <p class="text-lg sm:text-xl text-cyan-400 font-black tabular-nums">+{{ existingSubmission.earned_exp ?? (quest.reward_exp || quest.reward_gold) }} <span class="text-[8px] text-cyan-600/80 font-bold uppercase">Exp</span></p>
                                </div>
                            </div>
                        </div>

                        <!-- Action Button -->
                        <div class="w-full max-w-sm pt-4 flex flex-col items-center gap-4 px-2">
                            <Link :href="route('submissions.show', existingSubmission.uuid)" 
                                class="w-full text-center py-4 border-2 border-emerald-500 bg-emerald-500/10 text-emerald-400 text-[10px] font-black uppercase pf-bevel hover:bg-emerald-500 hover:text-black transition-all shadow-xl relative overflow-hidden group">
                                <div class="absolute inset-0 bg-white/10 -translate-x-full group-hover:translate-x-full transition-transform duration-700"></div>
                                [ ACCESS_MISSION_LOGS ]
                            </Link>
                            <p class="text-[6px] sm:text-[8px] text-slate-600 font-mono tracking-widest uppercase text-center">Protocol secure // Archive transmission complete.</p>
                        </div>
                    </div>
                </div>

                <!-- ACCESS DENIED -->
                <div v-else class="h-full flex flex-col bg-slate-950/90">
                    <div class="shrink-0 p-3 border-b border-rose-300/20 bg-slate-950 flex items-center justify-between">
                        <Link :href="route('lobby')" class="text-rose-400 text-[8px] border border-rose-500/30 px-3 py-1 pf-bevel-xs font-bold uppercase">✕ CANCEL</Link>
                        <span class="text-white text-[10px] font-bold uppercase tracking-widest">ACCESS_DENIED: {{ quest.title }}</span>
                        <div class="bg-rose-500/10 border border-rose-500/30 px-2 py-1 pf-bevel-xs"><span class="text-rose-400 text-[8px] font-black uppercase">LOCKED</span></div>
                    </div>
                    <div class="flex-1 p-6 flex flex-col items-center justify-center space-y-6">
                        <div class="w-16 h-16 rounded-full border-4 border-rose-500/30 flex items-center justify-center bg-rose-500/5 pf-shake"><img class="w-8 h-8 invert" src="https://api.iconify.design/pixelarticons:lock.svg" alt=""></div>
                        <div class="text-center space-y-1"><h2 class="text-rose-500 text-xl font-black italic tracking-tighter uppercase">Transmission_Expired</h2><p class="text-slate-500 text-[10px] font-bold uppercase">Penyelidikan melewati batas waktu. Hubungan terputus.</p></div>
                        <div class="bg-black/40 border border-slate-700 p-4 pf-bevel-sm w-full max-w-xs space-y-4">
                            <div class="flex justify-between text-[10px] font-bold uppercase"><span>Required:</span><span class="text-yellow-400">1 TIME_KEY</span></div>
                            <div class="flex justify-between text-[10px] font-bold uppercase"><span>Inventory:</span><span class="text-white">{{ timeKeyQty || 0 }} KEYS</span></div>
                            <button @click="unlockLateQuest" :disabled="unlockForm.processing || (timeKeyQty||0)<1 || isStaffPlayMode" class="w-full py-3 border-2 border-yellow-600 text-yellow-500 text-[9px] font-black uppercase pf-bevel-sm hover:bg-yellow-600 hover:text-white transition-all">
                                {{ isStaffPlayMode ? 'STAFF_PREVIEW' : (unlockForm.processing ? 'PROCESSING...' : '[ USE_TIME_KEY ]') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- NORMAL QUEST PAGE -->
        <div v-else class="min-h-screen p-4 md:p-12 font-['Press_Start_2P'] text-[#4ed4d4] flex justify-center items-start">
            <Head :title="'DETAILS - ' + quest.title" />
            <div class="quest-shell w-full max-w-3xl border-4 border-slate-700 shadow-[20px_20px_0px_0px_rgba(0,0,0,0.5)] relative overflow-hidden" :style="questToneStyle">
                <div class="p-4 md:p-8 border-b-4 border-slate-700 bg-slate-900/50">
                    <div class="flex justify-between items-center gap-3 mb-4">
                        <Link :href="route('lobby')" class="px-3 py-2 border-2 border-slate-700 bg-slate-900/40 text-slate-300 hover:text-white hover:border-cyan-400 text-[10px] uppercase">[ BACK_TO_LOBBY ]</Link>
                        <span class="text-yellow-500 text-[11px]">REF_ID: #{{ quest.uuid.substring(0, 8) }}</span>
                    </div>
                    <h1 class="text-lg md:text-2xl text-white uppercase tracking-tighter leading-tight">{{ quest.title }}</h1>
                </div>
                <div class="p-4 md:p-8 space-y-8">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 border-b-2 border-slate-800 pb-6 text-[12px]">
                        <div><p class="text-slate-500 mb-2 uppercase">Danger_Level:</p><p class="text-red-500 font-bold uppercase">{{ quest.difficulty }}</p></div>
                        <div class="grid grid-cols-2 gap-2"><p class="text-slate-500 mb-2 uppercase">Gold:</p><p class="text-yellow-400 font-bold">{{ quest.reward_gold }}G</p><p class="text-slate-500 mb-2 uppercase">Exp:</p><p class="text-cyan-400 font-bold">{{ quest.reward_exp || quest.reward_gold }}XP</p></div>
                    </div>
                    <div class="space-y-4">
                        <h3 class="text-[12px] text-cyan-400 uppercase tracking-widest underline italic">Quest_Objectives:</h3>
                        <div class="bg-black/30 p-4 border-l-4 border-slate-700 font-sans text-[14px] text-slate-300 whitespace-pre-wrap">{{ quest.description || 'NO DATA.' }}</div>
                    </div>

                    <div v-if="canSubmit" class="mt-8 p-4 border-2 border-dashed border-cyan-900 bg-black/20">
                        <h3 class="text-[12px] mb-6 uppercase tracking-widest text-white">>> {{ props.hasSubmitted ? 'EDIT_REPORT' : 'SUBMIT_REPORT' }}</h3>
                        <form @submit.prevent="submitReport" class="space-y-6">
                            <div v-if="isStructuredTaskBankQuest" class="space-y-4">
                                <div class="bg-slate-900/40 border border-cyan-900/50 p-3"><p class="text-[10px] text-cyan-300 uppercase">BANK: {{ quest.task_bank?.name }}</p></div>
                                <div v-for="(q, idx) in taskQuestions" :key="q.uuid" class="bg-black/30 border border-slate-700 p-4 space-y-3">
                                    <p class="text-[10px] text-yellow-300 uppercase">Q{{ idx + 1 }} // {{ q.question_type }}</p>
                                    <p class="text-[13px] text-slate-200 font-sans">{{ q.question_text }}</p>
                                    <div v-if="q.question_type === 'multiple_choice'" class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                        <label v-for="(opt, oi) in (q.options_json || [])" :key="oi" class="flex items-center gap-2 border border-slate-700 px-3 py-2 cursor-pointer hover:border-cyan-500 transition-colors">
                                            <input v-model="form.task_answers[q.uuid]" type="radio" :value="opt" class="accent-cyan-500">
                                            <span class="text-[12px] text-slate-300 font-sans">{{ opt }}</span>
                                        </label>
                                    </div>
                                    <textarea v-else v-model="form.task_answers[q.uuid]" class="w-full bg-[#0d1117] border-2 border-slate-800 p-3 text-white font-sans text-[13px] outline-none" rows="3" placeholder="Jawaban..."></textarea>
                                </div>
                            </div>
                            <div v-else class="space-y-4">
                                <div>
                                    <label class="block text-[12px] text-slate-500 mb-2 uppercase">Content:</label>
                                    <textarea v-model="form.content" class="w-full bg-[#0d1117] border-2 border-slate-800 p-3 text-white font-sans text-[14px] outline-none" rows="4" placeholder="Tulis jawaban mentah di sini..."></textarea>
                                </div>
                                <div>
                                    <label class="block text-[12px] text-slate-500 mb-2 uppercase">Raw File:</label>
                                    <input type="file" accept=".jpg,.jpeg,.png,.webp,.pdf,.docx,.txt" @change="handleFileChange" class="w-full bg-[#0d1117] border-2 border-slate-800 p-3 text-white font-sans text-[12px] outline-none file:mr-4 file:border-0 file:bg-cyan-900/40 file:px-3 file:py-2 file:text-cyan-300 file:uppercase" />
                                    <p class="mt-2 text-[10px] text-slate-500 font-sans">PDF, DOCX, TXT, JPG, PNG, WEBP. Max 10MB. File hanya disimpan mentah.</p>
                                    <p v-if="form.file" class="mt-2 text-[10px] text-cyan-300 font-sans">Selected: {{ form.file.name }}</p>
                                </div>
                            </div>
                            <button
                                type="submit"
                                :disabled="form.processing"
                                :class="[
                                    'w-full py-4 border-2 font-bold uppercase text-[12px] transition-colors',
                                    isEditSubmissionMode
                                        ? 'bg-yellow-900/40 border-yellow-400 text-yellow-400 hover:bg-yellow-500/20'
                                        : 'bg-cyan-900/40 border-cyan-400 text-cyan-400 hover:bg-cyan-500/20'
                                ]"
                            >
                                {{ form.processing ? (isEditSubmissionMode ? 'UPDATING...' : 'TRANSMITTING...') : (isEditSubmissionMode ? 'UPDATE' : 'SUBMIT') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.game-scene {
    --pf-nav-offset: 96px;
    font-family: 'Press Start 2P', 'VT323', ui-monospace, monospace;
    background: radial-gradient(circle at 15% 10%, rgba(14, 116, 144, 0.2), transparent 38%), linear-gradient(160deg, #020617, #0b132b);
    padding-top: var(--pf-nav-offset);
}
.pf-frame { width: 100%; height: calc(100vh - var(--pf-nav-offset)); display: flex; flex-direction: column; background: rgba(2, 6, 23, 0.95); position: relative; overflow: hidden; }
.pf-desktop-grid { display: flex; flex-direction: column; flex: 1; min-height: 0; width: 100%; }
.pf-main-col { flex: 1; display: flex; flex-direction: column; min-height: 0; width: 100%; }
.pf-main-inner { flex: 1; display: flex; flex-direction: column; min-height: 0; width: 100%; }
.pf-stage { flex: 1; min-height: 0; position: relative; background: radial-gradient(circle at 50% 5%, rgba(56, 189, 248, 0.1), transparent 45%); }
.pf-platform-row { height: 40px; }
.pf-char-sprite { width: 30px; height: 30px; }
.pf-char-fallback { width: 20px; height: 20px; }

@media (max-width: 640px) { .game-scene { --pf-nav-offset: 88px; } .pf-side-col { display: none; } }
@media (min-width: 1024px) { .pf-desktop-grid { display: grid; grid-template-columns: minmax(0, 1fr) 320px; } .pf-main-col { border-right: 1px solid rgba(103, 232, 249, 0.1); } .pf-side-col { display: flex; flex-direction: column; gap: 0.65rem; padding: 0.75rem; background: rgba(15, 23, 42, 0.65); overflow-y: auto; } }
@media (min-width: 640px) { .pf-platform-row { height: 44px; } .pf-char-sprite { width: 32px; height: 32px; } .pf-char-fallback { width: 22px; height: 22px; } }
@media (min-width: 1024px) { .pf-platform-row { height: 66px; } .pf-char-sprite { width: 52px; height: 52px; } .pf-char-fallback { width: 34px; height: 34px; } }

.pf-side-card { border: 1px solid rgba(103, 232, 249, 0.18); background: rgba(15, 23, 42, 0.7); border-radius: 8px; padding: 0.75rem; }
.pf-side-title { font-size: 10px; text-transform: uppercase; color: #67e8f9; margin-bottom: 0.45rem; display: flex; align-items: center; gap: 0.35rem; }
.pf-side-copy { font-size: 12px; color: #cbd5e1; line-height: 1.45; }
.pf-stat-line { display: flex; justify-content: space-between; font-size: 12px; color: #cbd5e1; padding: 0.2rem 0; }
.pf-stat-line strong { color: #e2e8f0; }
.pf-bevel { clip-path: polygon(12px 0, 100% 0, 100% calc(100% - 12px), calc(100% - 12px) 100%, 0 100%, 0 12px); }
.pf-bevel-sm { clip-path: polygon(8px 0, 100% 0, 100% calc(100% - 8px), calc(100% - 8px) 100%, 0 100%, 0 8px); }
.pf-bevel-xs { clip-path: polygon(4px 0, 100% 0, 100% calc(100% - 4px), calc(100% - 4px) 100%, 0 100%, 0 4px); }
.pf-scanline { position: absolute; inset: 0; pointer-events: none; background: repeating-linear-gradient(180deg, rgba(148,163,184,0.05) 0px, transparent 2px); animation: scanline-scroll 8s linear infinite; z-index: 5; }
@keyframes scanline-scroll { from { background-position: 0 0; } to { background-position: 0 100%; } }
.pf-glitch-text { position: relative; animation: glitch-bounce 0.4s ease-out; }
.pf-glitch-text::before, .pf-glitch-text::after { content: attr(data-text); position: absolute; top: 0; left: 0; width: 100%; opacity: 0.8; }
.pf-glitch-text::before { color: #0ff; z-index: -1; animation: glitch-anim 0.2s infinite; }
.pf-glitch-text::after { color: #f0f; z-index: -2; animation: glitch-anim 0.2s infinite reverse; }
@keyframes glitch-anim { 0%, 100% { transform: translate(0); } 20% { transform: translate(-2px, 2px); } 40% { transform: translate(-2px, -2px); } 60% { transform: translate(2px, 2px); } 80% { transform: translate(2px, -2px); } }
@keyframes glitch-bounce { 0% { transform: scale(0.5); } 50% { transform: scale(1.1); } 100% { transform: scale(1); } }
.pf-char-shadow { position: absolute; bottom: -2px; left: 50%; transform: translateX(-50%); width: 20px; height: 4px; background: rgba(0,0,0,0.4); border-radius: 100%; filter: blur(1px); }
.pf-char-aura { position: absolute; width: 50px; height: 50px; background: radial-gradient(circle, rgba(34,211,238,0.4), transparent 70%); border-radius: 50%; left: 50%; top: 50%; transform: translate(-50%,-50%); animation: aura-pulse 2s infinite; }
@keyframes aura-pulse { 0%, 100% { transform: translate(-50%,-50%) scale(1); opacity: 0.4; } 50% { transform: translate(-50%,-50%) scale(1.3); opacity: 0.6; } }
.pf-spark { position: absolute; width: 2px; height: 2px; background: #22d3ee; border-radius: 50%; animation: spark-fly 0.4s forwards; }
@keyframes spark-fly { 0% { transform: translate(0,0) scale(1); opacity: 1; } 100% { transform: translate(var(--tx), var(--ty)) scale(0); opacity: 0; } }
.pf-laser-scan { animation: pf-laser-scan 1.5s linear infinite; }
@keyframes pf-laser-scan { 0% { transform: translateX(-100%); } 100% { transform: translateX(100%); } }
.pixelated { image-rendering: pixelated; }
.animate-spin-slow { animation: spin 8s linear infinite; }
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
.pf-shake { animation: shake 0.4s both; }
@keyframes shake { 10%, 90% { transform: translate3d(-2px,0,0); } 20%, 80% { transform: translate3d(4px,0,0); } 30%, 70% { transform: translate3d(-6px,0,0); } }
.pf-idle-float { animation: idle-float 2s ease-in-out infinite; }
@keyframes idle-float { 0%, 100% { transform: translate(-50%, 0); } 50% { transform: translate(-50%, -4px); } }
.pf-jump-up { animation: jump-up 0.45s ease-out; }
@keyframes jump-up { 0% { transform: translate(-50%, 0); } 50% { transform: translate(-50%, -15px); } 100% { transform: translate(-50%, 0); } }
.pf-stage-impact-up { animation: stage-impact-up 0.5s ease-out; }
.pf-stage-impact-down { animation: stage-impact-down 0.55s ease-out; }
@keyframes stage-impact-up {
    0% { filter: saturate(1); }
    40% { filter: saturate(1.35) brightness(1.15); }
    100% { filter: saturate(1); }
}
@keyframes stage-impact-down {
    0% { filter: saturate(1); }
    30% { filter: saturate(1.4) brightness(1.05); transform: translateX(-6px); }
    60% { transform: translateX(6px); }
    100% { filter: saturate(1); transform: translateX(0); }
}
.pf-hit-overlay { position: absolute; inset: 0; z-index: 25; overflow: hidden; }
.pf-hit-overlay-correct { --pf-hit-color: 16, 185, 129; --pf-hit-text: #86efac; }
.pf-hit-overlay-wrong { --pf-hit-color: 244, 63, 94; --pf-hit-text: #fda4af; }
.pf-hit-flash {
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at 50% 40%, rgba(var(--pf-hit-color), 0.42), rgba(var(--pf-hit-color), 0.06) 40%, transparent 75%);
    animation: hit-flash 0.45s ease-out forwards;
}
.pf-hit-ring {
    position: absolute;
    left: 50%;
    top: 45%;
    width: 80px;
    height: 80px;
    border: 3px solid rgba(var(--pf-hit-color), 0.8);
    border-radius: 999px;
    transform: translate(-50%, -50%);
    animation: hit-ring 0.7s ease-out forwards;
}
.pf-hit-ring-delay { animation-delay: 0.08s; opacity: 0.65; }
.pf-hit-text {
    position: absolute;
    left: 50%;
    top: 32%;
    transform: translate(-50%, -50%);
    color: var(--pf-hit-text);
    font-size: 11px;
    letter-spacing: 0.08em;
    font-weight: 900;
    text-transform: uppercase;
    text-shadow: 0 0 16px rgba(var(--pf-hit-color), 0.7);
    animation: hit-text 0.55s ease-out forwards;
}
.pf-hit-particle {
    position: absolute;
    left: 50%;
    top: 45%;
    width: 4px;
    height: 4px;
    border-radius: 999px;
    background: rgb(var(--pf-hit-color));
    box-shadow: 0 0 8px rgba(var(--pf-hit-color), 0.9);
    animation: hit-particle 0.6s ease-out forwards;
}
.pf-hit-particle:nth-child(6n + 1) { --tx: -140px; --ty: -50px; }
.pf-hit-particle:nth-child(6n + 2) { --tx: -95px; --ty: 75px; }
.pf-hit-particle:nth-child(6n + 3) { --tx: -25px; --ty: -120px; }
.pf-hit-particle:nth-child(6n + 4) { --tx: 95px; --ty: -65px; }
.pf-hit-particle:nth-child(6n + 5) { --tx: 130px; --ty: 50px; }
.pf-hit-particle:nth-child(6n + 6) { --tx: 25px; --ty: 110px; }
@keyframes hit-flash {
    0% { opacity: 0; }
    20% { opacity: 1; }
    100% { opacity: 0; }
}
@keyframes hit-ring {
    0% { transform: translate(-50%, -50%) scale(0.25); opacity: 0.9; }
    100% { transform: translate(-50%, -50%) scale(2.8); opacity: 0; }
}
@keyframes hit-text {
    0% { opacity: 0; transform: translate(-50%, -65%) scale(0.7); }
    30% { opacity: 1; transform: translate(-50%, -50%) scale(1.06); }
    100% { opacity: 0; transform: translate(-50%, -25%) scale(1); }
}
@keyframes hit-particle {
    0% { opacity: 1; transform: translate(-50%, -50%) scale(1); }
    100% { opacity: 0; transform: translate(calc(-50% + var(--tx)), calc(-50% + var(--ty))) scale(0.2); }
}
.pf-city-layer { position: absolute; bottom: 0; left: 0; width: 200%; height: 120px; background-repeat: repeat-x; opacity: 0.15; }
.pf-city-1 { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='200' height='100'%3E%3Cpath fill='%230ea5e9' d='M0 100h20v-40h10v-20h20v60h10v-30h20v30h10v-50h20v50h10v-20h20v20h10v-40h20v40h10v-60h10v60z'/%3E%3C/svg%3E"); animation: city-scroll 60s linear infinite; }
.pf-city-2 { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='300' height='120'%3E%3Cpath fill='%233b82f6' d='M0 120h30v-20h20v-40h40v60h30v-30h40v30h30v-70h40v70h30v-40z'/%3E%3C/svg%3E"); animation: city-scroll 40s linear infinite reverse; opacity: 0.1; }
@keyframes city-scroll { from { transform: translateX(0); } to { transform: translateX(-50%); } }
.pf-platform-circuit { background-image: repeating-linear-gradient(90deg, transparent, transparent 10px, rgba(34,211,238,0.1) 10px, rgba(34,211,238,0.1) 11px); }
.pf-hud-card { position: relative; overflow: hidden; }
.pf-hud-card::after { content: ''; position: absolute; inset: 0; background: linear-gradient(135deg, rgba(255,255,255,0.1), transparent); }
.pf-parallax-bg-line { animation: bg-line-pulse 4s infinite; }
@keyframes bg-line-pulse { 0%, 100% { opacity: 0.1; transform: scale(1); } 50% { opacity: 0.3; transform: scale(1.05); } }
</style>
