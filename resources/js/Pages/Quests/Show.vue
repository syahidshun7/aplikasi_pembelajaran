<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import Swal from 'sweetalert2';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EscapeGameEngine from '@/Components/EscapeGameEngine.vue';

const props = defineProps({
    quest: Object,
    hasSubmitted: Boolean,
    existingSubmission: Object,
    isLate: Boolean,
    hasQuestUnlock: Boolean,
    canSubmit: Boolean,
    timeKeyQty: Number,
    isStaffPlayMode: Boolean,
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
    game_payload: {},
});

const unlockForm = useForm({});
const page = usePage();

const questDraftStorageKey = computed(() => {
    const questKey = String(props.quest?.uuid || props.quest?.id || 'quest');
    const userKey = String(page.props?.auth?.user?.id || 'guest');
    return `quest-draft:${userKey}:${questKey}`;
});

let draftSaveTimer = null;
let gameTimerInterval = null;
let prelaunchInterval = null;
let escBlockHandler = null;

const clearDraft = () => {
    if (typeof window === 'undefined') return;
    try {
        window.localStorage.removeItem(questDraftStorageKey.value);
    } catch (_) {
        // ignore
    }
};

const persistDraft = () => {
    if (typeof window === 'undefined') return;

    if (draftSaveTimer) {
        clearTimeout(draftSaveTimer);
    }

    draftSaveTimer = window.setTimeout(() => {
        try {
            window.localStorage.setItem(questDraftStorageKey.value, JSON.stringify({
                content: form.content || '',
                task_answers: { ...(form.task_answers || {}) },
                game_payload: { ...(form.game_payload || {}) },
            }));
        } catch (_) {
            // ignore quota / privacy mode
        }
    }, 150);
};

const loadDraftFromStorage = () => {
    if (typeof window === 'undefined') return;

    try {
        const raw = window.localStorage.getItem(questDraftStorageKey.value);
        if (!raw) return;

        const parsed = JSON.parse(raw);
        if (!parsed || typeof parsed !== 'object') return;

        if (typeof parsed.content === 'string') {
            form.content = parsed.content;
        }

        if (parsed.task_answers && typeof parsed.task_answers === 'object' && !Array.isArray(parsed.task_answers)) {
            form.task_answers = {
                ...(form.task_answers || {}),
                ...parsed.task_answers,
            };
        }

        if (parsed.game_payload && typeof parsed.game_payload === 'object' && !Array.isArray(parsed.game_payload)) {
            form.game_payload = {
                ...(form.game_payload || {}),
                ...parsed.game_payload,
            };
        }
    } catch (_) {
        // ignore corrupted draft
    }
};

watch(() => form.content, persistDraft);
watch(() => form.task_answers, persistDraft, { deep: true });
watch(() => form.game_payload, persistDraft, { deep: true });

onMounted(() => {
    loadDraftFromStorage();
});

onBeforeUnmount(() => {
    if (draftSaveTimer) {
        clearTimeout(draftSaveTimer);
        draftSaveTimer = null;
    }
    if (gameTimerInterval) {
        clearInterval(gameTimerInterval);
        gameTimerInterval = null;
    }
    if (prelaunchInterval) {
        clearInterval(prelaunchInterval);
        prelaunchInterval = null;
    }
    if (typeof document !== 'undefined') {
        document.body.style.overflow = '';
    }
    if (typeof window !== 'undefined' && escBlockHandler) {
        window.removeEventListener('keydown', escBlockHandler, true);
        escBlockHandler = null;
    }
});

const taskBankType = computed(() => props.quest?.task_bank?.assessment_type || null);
const isAllMcq = computed(() => {
    if (!isStructuredTaskBankQuest.value) return false;
    return taskQuestions.value.length > 0 && taskQuestions.value.every((q) => q.question_type === 'multiple_choice');
});
const isStructuredTaskBankQuest = computed(() => {
    return !!props.quest?.task_bank && ['multiple_choice', 'mixed', 'essay', 'game_escape'].includes(taskBankType.value);
});
const isGameEscapeQuest = computed(() => taskBankType.value === 'game_escape');
const isAutoCheckedTaskBankQuest = computed(() => taskBankType.value === 'multiple_choice' && isAllMcq.value);
const taskQuestions = computed(() => props.quest?.task_bank?.questions || []);

const showQuestModal = ref(false);
const activeModalStageUuid = ref(null);
const clearedStageUuids = computed(() => gameStages.value.filter(s => isStageCleared(s)).map(s => s.uuid));
const modalStage = computed(() => gameStages.value.find(s => s.uuid === activeModalStageUuid.value) || activeStage.value);

const handleInteractTerminal = (stageUuid) => {
    activeModalStageUuid.value = stageUuid;
    showQuestModal.value = true;
};
const handleReachExit = () => {
    if (allStagesCleared.value) {
        Swal.fire({
            title: 'ESCAPE_SUCCESS',
            text: 'Semua stage berhasil dilewati! Sistem akan mensubmit otomatis.',
            icon: 'success',
            background: '#161b22',
            color: '#4ed4d4',
        });
        submitReport();
    }
};
const handleUpdateIntegrity = (newHealth) => {
    integrity.value = newHealth;
    if (integrity.value <= 0) {
        gameMessage.value = 'Integrity habis. Mission di-reset ke stage awal.';
        integrity.value = 3;
        activeStageIndex.value = 0;
    }
};
const closeQuestModal = () => {
    showQuestModal.value = false;
    activeModalStageUuid.value = null;
};

const gameStages = computed(() => {
    if (!isGameEscapeQuest.value) return [];
    return taskQuestions.value.map((question, index) => {
        const config = question?.options_json && typeof question.options_json === 'object' && !Array.isArray(question.options_json)
            ? question.options_json
            : {};
        const acceptedAnswers = Array.isArray(config.accepted_answers) ? config.accepted_answers : [];
        return {
            ...question,
            stageNumber: index + 1,
            prompt: String(config.prompt || question.question_text || ''),
            hint: String(config.hint || ''),
            maxAttempts: Math.max(1, Number(config.max_attempts || 3)),
            timeLimitSeconds: Math.max(0, Number(config.time_limit_seconds || 0)),
            acceptedAnswers,
        };
    });
});
const gameStartedAt = ref(Number(form.game_payload?.started_ts || Date.now()));
const gameElapsedSeconds = ref(Math.max(0, Number(form.game_payload?.elapsed_seconds || 0)));
const activeStageIndex = ref(0);
const localAttempts = ref({});
const missionStarted = ref(false);
const gameMessage = ref('Tekan START MISSION untuk memulai.');
const integrity = ref(3);
const prelaunchActive = ref(false);
const prelaunchCount = ref(3);
const missionLockedByTimeout = ref(false);
const maxUnlockedStage = ref(0);
const inventoryItems = ref([]);
const discoveredHotspots = ref({});
const showResultScreen = ref(false);

const gameProgressPercent = computed(() => {
    if (!gameStages.value.length) return 0;
    const cleared = gameStages.value.filter((stage) => {
        const answer = normalizeEscapeAnswer(form.task_answers?.[stage.uuid]);
        if (!answer) return false;
        return stage.acceptedAnswers.map((v) => normalizeEscapeAnswer(v)).includes(answer);
    }).length;
    return Math.round((cleared / gameStages.value.length) * 100);
});
const activeStage = computed(() => gameStages.value[activeStageIndex.value] || null);
const allStagesCleared = computed(() => gameStages.value.length > 0 && gameStages.value.every((stage) => isStageCleared(stage)));
const gameTimeLimitSeconds = computed(() => {
    const candidate = Number(gameStages.value?.[0]?.timeLimitSeconds || 0);
    if (Number.isInteger(candidate) && candidate > 0) return candidate;
    return 600;
});
const gameRemainingSeconds = computed(() => {
    return Math.max(0, gameTimeLimitSeconds.value - gameElapsedSeconds.value);
});
const isTimeExpired = computed(() => gameRemainingSeconds.value <= 0 && missionStarted.value);
const gameElapsedLabel = computed(() => {
    const total = Math.max(0, gameElapsedSeconds.value);
    const mm = String(Math.floor(total / 60)).padStart(2, '0');
    const ss = String(total % 60).padStart(2, '0');
    return `${mm}:${ss}`;
});
const gameRemainingLabel = computed(() => {
    const total = Math.max(0, gameRemainingSeconds.value);
    const mm = String(Math.floor(total / 60)).padStart(2, '0');
    const ss = String(total % 60).padStart(2, '0');
    return `${mm}:${ss}`;
});
const doorStatusLabel = computed(() => {
    if (allStagesCleared.value) return 'Door Unlocked';
    if (isTimeExpired.value) return 'Lockdown';
    return 'Sealed';
});
const stageScenes = computed(() => {
    const stages = gameStages.value;
    return stages.map((stage, idx) => {
        const n = idx + 1;
        if (n === 1) {
            return {
                scene: 'Control Room',
                hotspots: [
                    { id: `${stage.uuid}-pad`, label: 'Keypad', reward: 'code-fragment-a', clue: 'Fragmen A ditemukan: 12', answerFill: '1206' },
                    { id: `${stage.uuid}-board`, label: 'Math Board', reward: null, clue: 'Petunjuk: 7+5 dan 9-3.' },
                ],
            };
        }
        if (n === 2) {
            return {
                scene: 'Cipher Terminal',
                hotspots: [
                    { id: `${stage.uuid}-terminal`, label: 'Terminal', reward: 'code-fragment-b', clue: 'Fragmen B ditemukan: PINTU', answerFill: 'PINTU' },
                    { id: `${stage.uuid}-manual`, label: 'Manual', reward: null, clue: 'Shift huruf mundur 1 langkah.' },
                ],
            };
        }
        return {
            scene: 'Vault Chamber',
            hotspots: [
                { id: `${stage.uuid}-vault`, label: 'Vault Console', reward: 'vault-keycard', clue: 'Gabungkan format angka-kata.', answerFill: '1206-pintu' },
                { id: `${stage.uuid}-panel`, label: 'Panel', reward: null, clue: 'Gunakan "-" di tengah kode final.' },
            ],
        };
    });
});
const activeScene = computed(() => stageScenes.value[activeStageIndex.value] || { scene: 'Unknown', hotspots: [] });
const rankLabel = computed(() => {
    const score = Number(gameProgressPercent.value);
    const timeBonus = gameRemainingSeconds.value >= 300 ? 6 : gameRemainingSeconds.value >= 120 ? 3 : 0;
    const integrityBonus = Math.max(0, Number(integrity.value) - 1) * 2;
    const total = Math.min(100, score + timeBonus + integrityBonus);
    if (total >= 95) return 'SS';
    if (total >= 90) return 'S';
    if (total >= 80) return 'A';
    if (total >= 70) return 'B';
    if (total >= 60) return 'C';
    if (total >= 50) return 'D';
    return 'F';
});

const normalizeEscapeAnswer = (value) => {
    return String(value || '')
        .toLowerCase()
        .replace(/\s*-\s*/g, '-')
        .replace(/\s+/g, ' ')
        .trim();
};

const isStageCleared = (stage) => {
    if (!stage) return false;
    const answer = normalizeEscapeAnswer(form.task_answers?.[stage.uuid]);
    if (!answer) return false;
    return stage.acceptedAnswers.map((v) => normalizeEscapeAnswer(v)).includes(answer);
};

const bumpAttempt = (stage) => {
    if (!stage?.uuid) return;
    const key = String(stage.uuid);
    const current = Number(localAttempts.value[key] || 0);
    localAttempts.value = {
        ...localAttempts.value,
        [key]: current + 1,
    };
};

const goStage = (index) => {
    if (index < 0 || index >= gameStages.value.length) return;
    if (index > maxUnlockedStage.value) return;
    activeStageIndex.value = index;
};

const checkCurrentStage = () => {
    const stage = activeStage.value;
    if (!stage || !missionStarted.value) return;
    bumpAttempt(stage);

    if (isStageCleared(stage)) {
        gameMessage.value = `Stage ${stage.stageNumber} clear.`;
        if (activeStageIndex.value + 1 > maxUnlockedStage.value) {
            maxUnlockedStage.value = Math.min(activeStageIndex.value + 1, Math.max(0, gameStages.value.length - 1));
        }
        Swal.fire({
            title: `STAGE_${stage.stageNumber}_CLEAR`,
            text: 'Stage berhasil dibuka. Lanjut ke stage berikutnya.',
            icon: 'success',
            background: '#161b22',
            color: '#4ed4d4',
        });
        if (activeStageIndex.value < gameStages.value.length - 1) {
            activeStageIndex.value += 1;
        }
        return;
    }

    integrity.value = Math.max(0, integrity.value - 1);
    gameMessage.value = stage.hint || 'Kode salah. Coba lagi.';

    if (integrity.value <= 0) {
        gameMessage.value = 'Integrity habis. Mission di-reset ke stage awal.';
        activeStageIndex.value = 0;
        integrity.value = 3;
    }

    Swal.fire({
        title: 'CODE_INVALID',
        text: stage.hint || 'Jawaban belum tepat. Cek lagi clue di stage ini.',
        icon: 'warning',
        background: '#161b22',
        color: '#fbbf24',
    });
};

const handleStageAnswerInput = (stage) => {
    if (!stage || !missionStarted.value || isTimeExpired.value || missionLockedByTimeout.value) return;

    const alreadyCleared = isStageCleared(stage);
    if (!alreadyCleared) return;

    gameMessage.value = `Stage ${stage.stageNumber} clear.`;
    if (activeStageIndex.value + 1 > maxUnlockedStage.value) {
        maxUnlockedStage.value = Math.min(activeStageIndex.value + 1, Math.max(0, gameStages.value.length - 1));
    }
    if (activeStageIndex.value < gameStages.value.length - 1) {
        activeStageIndex.value += 1;
    }
};

const collectInventory = (item) => {
    if (!item) return;
    if (inventoryItems.value.includes(item)) return;
    inventoryItems.value = [...inventoryItems.value, item];
};

const activateHotspot = (spot, stage) => {
    if (!spot || !stage) return;
    const key = String(spot.id);
    if (discoveredHotspots.value[key]) return;
    discoveredHotspots.value = { ...discoveredHotspots.value, [key]: true };
    if (spot.reward) {
        collectInventory(spot.reward);
    }
    if (spot.answerFill && !String(form.task_answers?.[stage.uuid] || '').trim()) {
        form.task_answers[stage.uuid] = String(spot.answerFill);
        handleStageAnswerInput(stage);
    }
    gameMessage.value = spot.clue || 'Hotspot ditemukan.';
};

const startMission = () => {
    missionStarted.value = true;
    gameStartedAt.value = Date.now();
    gameElapsedSeconds.value = 0;
    activeStageIndex.value = 0;
    maxUnlockedStage.value = 0;
    localAttempts.value = {};
    integrity.value = 3;
    missionLockedByTimeout.value = false;
    inventoryItems.value = [];
    discoveredHotspots.value = {};
    showResultScreen.value = false;
    gameMessage.value = 'Mission aktif. Pecahkan semua stage.';
    gameStages.value.forEach((stage) => {
        form.task_answers[stage.uuid] = '';
    });
};

const startPrelaunch = () => {
    prelaunchActive.value = true;
    prelaunchCount.value = 3;
    missionStarted.value = false;

    if (prelaunchInterval) {
        clearInterval(prelaunchInterval);
        prelaunchInterval = null;
    }

    prelaunchInterval = window.setInterval(() => {
        prelaunchCount.value -= 1;
        if (prelaunchCount.value <= 0) {
            clearInterval(prelaunchInterval);
            prelaunchInterval = null;
            prelaunchActive.value = false;
            startMission();
        }
    }, 1000);
};

watch(isGameEscapeQuest, (isGame) => {
    if (!isGame || typeof window === 'undefined') {
        if (gameTimerInterval) {
            clearInterval(gameTimerInterval);
            gameTimerInterval = null;
        }
        if (prelaunchInterval) {
            clearInterval(prelaunchInterval);
            prelaunchInterval = null;
        }
        return;
    }

    if (!form.game_payload || typeof form.game_payload !== 'object') {
        form.game_payload = {};
    }

    if (!form.game_payload.started_ts) {
        form.game_payload.started_ts = Date.now();
    }

    gameStartedAt.value = Number(form.game_payload.started_ts || Date.now());
    gameElapsedSeconds.value = Math.max(0, Number(form.game_payload.elapsed_seconds || 0));

    if (gameTimerInterval) {
        clearInterval(gameTimerInterval);
        gameTimerInterval = null;
    }

    gameTimerInterval = window.setInterval(() => {
        const elapsed = Math.floor((Date.now() - gameStartedAt.value) / 1000);
        gameElapsedSeconds.value = Math.max(0, elapsed);
    }, 1000);

    startPrelaunch();
}, { immediate: true });

watch(isTimeExpired, (expired) => {
    if (!expired) return;
    missionLockedByTimeout.value = true;
    missionStarted.value = false;
    gameMessage.value = 'Waktu habis. Mission gagal.';
});

watch(
    () => isGameEscapeQuest.value && props.canSubmit,
    (active) => {
        if (typeof document === 'undefined') return;
        document.body.style.overflow = active ? 'hidden' : '';

        if (typeof window !== 'undefined') {
            if (active && !escBlockHandler) {
                escBlockHandler = (event) => {
                    if (event.key === 'Escape' || event.key === 'Esc') {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                };
                window.addEventListener('keydown', escBlockHandler, true);
            } else if (!active && escBlockHandler) {
                window.removeEventListener('keydown', escBlockHandler, true);
                escBlockHandler = null;
            }
        }
    },
    { immediate: true }
);

if (typeof document !== 'undefined') {
    document.addEventListener('fullscreenchange', () => {
        // Do not force fullscreen re-entry.
        // Browsers (especially Firefox) reject non-user-gesture fullscreen requests.
    });
}
const unansweredCount = computed(() => {
    if (!isStructuredTaskBankQuest.value) return 0;
    return taskQuestions.value.filter((question) => {
        const value = form.task_answers?.[question.uuid];
        return !value || String(value).trim() === '';
    }).length;
});

const hashGroupKey = (value) => {
    const normalized = String(value || 'global');
    let hash = 0;
    for (let index = 0; index < normalized.length; index += 1) {
        hash = ((hash << 5) - hash) + normalized.charCodeAt(index);
        hash |= 0;
    }
    return Math.abs(hash);
};

const toneForGroup = (groupKey) => {
    if (!groupKey || groupKey === 'global') {
        return {
            border: '#2d65cf',
            bg: 'rgba(22, 47, 93, 0.16)',
            accent: '#8cc4ff',
        };
    }
    const hash = hashGroupKey(groupKey);
    let hue = Math.floor((hash * 137.508) % 360);
    if (hue >= 185 && hue <= 225) {
        hue = (hue + 92) % 360;
    }
    const saturation = 66 + (hash % 8);
    const borderLightness = 56 + ((hash >> 3) % 7);
    const accentLightness = 74 + ((hash >> 5) % 8);
    return {
        border: `hsl(${hue} ${saturation}% ${borderLightness}%)`,
        bg: `hsl(${hue} ${Math.max(60, saturation - 6)}% 20% / 0.16)`,
        accent: `hsl(${hue} ${Math.min(90, saturation + 10)}% ${accentLightness}%)`,
    };
};

const questToneStyle = computed(() => {
    const toneKey = props.quest?.study_group_id ?? props.quest?.study_group?.id ?? props.quest?.study_group?.name ?? 'global';
    const tone = toneForGroup(String(toneKey));
    return {
        '--quest-tone-border': tone.border,
        '--quest-tone-bg': tone.bg,
        '--quest-tone-accent': tone.accent,
    };
});

const questClassLabel = computed(() => {
    if (!props.quest?.study_group_id) {
        return 'Global';
    }
    const name = String(props.quest?.study_group?.name || '').trim();
    return name !== '' ? name : `#${props.quest.study_group_id}`;
});

const answerFor = (question) => {
    const uuid = String(question?.uuid || '');
    if (!uuid) return '';
    const raw = taskAnswersFromSubmission.value?.[uuid];
    return raw === undefined || raw === null ? '' : String(raw);
};

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
    if (!props.canSubmit) {
        Swal.fire({
            title: 'SUBMISSION_LOCKED',
            text: 'Kamu sudah submit. Submission tidak bisa diulang.',
            icon: 'info',
            background: '#161b22',
            color: '#4ed4d4',
            confirmButtonColor: '#1e293b',
        });
        return;
    }

    if (isStructuredTaskBankQuest.value && unansweredCount.value > 0) {
        if (!isGameEscapeQuest.value) {
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
    }

    if (isGameEscapeQuest.value && !allStagesCleared.value) {
        Swal.fire({
            title: 'MISSION_NOT_CLEARED',
            text: 'Selesaikan semua stage dulu sebelum finalize.',
            icon: 'warning',
            background: '#161b22',
            color: '#4ed4d4',
            confirmButtonColor: '#a16207',
        });
        return;
    }

    if (isGameEscapeQuest.value) {
        if (!showResultScreen.value) {
            showResultScreen.value = true;
            return;
        }
        const url = route('submissions.store', props.quest.uuid);
        form.game_payload = {
            ...(form.game_payload || {}),
            elapsed_seconds: gameElapsedSeconds.value,
            started_at: new Date(gameStartedAt.value).toISOString(),
            finished_at: new Date().toISOString(),
            attempts: { ...(localAttempts.value || {}) },
            rank: rankLabel.value,
            inventory: [...inventoryItems.value],
        };

        form.post(url, {
            preserveScroll: true,
            onSuccess: () => {
                clearDraft();
                showResultScreen.value = false;
                Swal.fire({
                    title: 'ESCAPE_LOGGED',
                    text: 'Escape mission berhasil dikirim.',
                    icon: 'success',
                    background: '#161b22',
                    color: '#4ed4d4',
                });
            },
            onError: () => {
                persistDraft();
                if (Object.keys(form.errors).length > 0) {
                    Swal.fire({
                        title: 'TRANSMISSION_FAILED',
                        text: Object.values(form.errors).join('\n'),
                        icon: 'error',
                        background: '#161b22',
                        color: '#ff4757',
                        confirmButtonColor: '#1e293b',
                    });
                }
            },
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
            const url = route('submissions.store', props.quest.uuid);

            form.post(url, {
                preserveScroll: true,
                onSuccess: () => {
                    clearDraft();
                    Swal.fire({
                        title: 'LOGGED!',
                        text: 'Submission berhasil dikirim.',
                        icon: 'success',
                        background: '#161b22',
                        color: '#4ed4d4',
                    });
                },
                onError: () => {
                    persistDraft();
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
            <Teleport to="body">
                <div
                    v-if="isGameEscapeQuest && canSubmit"
                    class="escape-overlay fixed inset-0 z-[2147483647] bg-[#05080f] text-[#9ae6ff] overflow-auto font-['Press_Start_2P'] tracking-wide"
                >
                <div class="escape-bg-grid"></div>
                <div class="escape-bg-scanline"></div>
                <div class="escape-bg-glow"></div>
                <div v-if="prelaunchActive" class="min-h-screen flex items-center justify-center">
                    <div class="text-center space-y-5">
                        <p class="text-[16px] tracking-widest text-cyan-300 uppercase">Loading Escape Mission</p>
                        <p class="text-[88px] leading-none text-white">{{ prelaunchCount }}</p>
                        <p class="text-[12px] text-slate-400 uppercase">Mode terkunci: fokus misi</p>
                    </div>
                </div>
                <div v-else class="min-h-screen w-full h-full relative overflow-hidden bg-[#000]">
                    <EscapeGameEngine 
                        v-if="missionStarted && !isTimeExpired && !missionLockedByTimeout"
                        :stages="gameStages"
                        :clearedStageUuids="clearedStageUuids"
                        :isActive="!showQuestModal"
                        @interact-terminal="handleInteractTerminal"
                        @reach-exit="handleReachExit"
                        @update-integrity="handleUpdateIntegrity"
                    />
                    
                    <div v-if="showQuestModal" class="absolute inset-0 z-[2147483648] flex items-center justify-center bg-black/80 p-4">
                        <div class="bg-[#0d1117] border-4 border-cyan-900 p-6 max-w-lg w-full font-sans text-white shadow-2xl relative">
                            <button @click="closeQuestModal" class="absolute top-2 right-2 p-2 text-slate-400 hover:text-white font-['Press_Start_2P'] text-[12px]">X</button>
                            <h3 class="text-cyan-400 font-['Press_Start_2P'] text-[12px] uppercase mb-4 text-center">Quest Terminal</h3>
                            <div v-if="modalStage">
                                <p class="text-[14px] leading-relaxed mb-4 whitespace-pre-wrap">{{ modalStage.prompt }}</p>
                                <p v-if="modalStage.hint" class="text-[11px] text-yellow-400 mb-4 font-bold">Hint: {{ modalStage.hint }}</p>
                                
                                <input
                                    v-model="form.task_answers[modalStage.uuid]"
                                    type="text"
                                    @input="handleStageAnswerInput(modalStage)"
                                    class="w-full bg-[#161b22] border-2 border-slate-700 p-3 text-cyan-300 font-['Press_Start_2P'] text-[10px] outline-none transition-all focus:border-cyan-400 mb-2"
                                    placeholder="Enter code..."
                                >
                                <p v-if="form.errors[`task_answers.${modalStage.uuid}`]" class="text-[10px] text-red-400 font-sans mb-2">
                                    {{ form.errors[`task_answers.${modalStage.uuid}`] }}
                                </p>
                                <div class="mt-4 flex gap-2">
                                    <button type="button" class="flex-1 py-3 bg-cyan-900/40 border border-cyan-700 text-cyan-300 font-['Press_Start_2P'] text-[9px] uppercase hover:bg-cyan-700/50" @click="checkCurrentStage">
                                        Check
                                    </button>
                                    <span v-if="isStageCleared(modalStage)" class="py-3 px-4 bg-emerald-900/40 border border-emerald-700 text-emerald-300 font-['Press_Start_2P'] text-[9px] uppercase">
                                        Cleared
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="absolute top-4 left-4 z-50 pointer-events-none">
                        <div class="flex flex-col gap-2">
                            <div class="border-2 border-cyan-700/70 bg-cyan-900/60 p-3 backdrop-blur-sm">
                                <p class="text-[8px] uppercase text-cyan-300">Timer</p>
                                <p class="text-[14px] text-white mt-1">{{ gameElapsedLabel }}</p>
                            </div>
                            <div class="border-2 border-rose-700/70 bg-rose-900/60 p-3 backdrop-blur-sm">
                                <p class="text-[8px] uppercase text-rose-300">Integrity</p>
                                <p class="text-[14px] text-white mt-1">{{ integrity }}/3</p>
                            </div>
                        </div>
                    </div>
                    <div class="absolute top-4 right-4 z-50">
                         <button
                             type="button"
                             class="px-4 py-3 border-2 border-emerald-700 bg-emerald-900/60 text-emerald-300 hover:bg-emerald-700/80 uppercase text-[10px] font-['Press_Start_2P'] backdrop-blur-sm shadow-lg transition-colors"
                             @click="startMission"
                         >
                             {{ missionStarted ? 'Restart Mission' : 'Start Mission' }}
                         </button>
                    </div>
                    <div class="absolute bottom-4 right-4 z-50">
                        <button
                            type="button"
                            @click="submitReport"
                            :disabled="form.processing || !allStagesCleared"
                            class="px-6 py-4 border-4 transition-all font-bold uppercase text-[12px] font-['Press_Start_2P'] shadow-xl disabled:opacity-60 disabled:cursor-not-allowed"
                            :class="allStagesCleared ? 'bg-emerald-800/90 border-emerald-400 text-emerald-100 hover:bg-emerald-700' : 'bg-slate-800/80 border-slate-600 text-slate-400'"
                        >
                            {{ form.processing ? 'TRANSMITTING...' : (allStagesCleared ? 'FINALIZE_ESCAPE' : 'LOCKED') }}
                        </button>
                    </div>
                    
                    <div class="max-w-[1800px] hidden mx-auto min-h-[calc(100vh-2.5rem)] flex-col gap-4">
                        <div class="flex flex-wrap items-center justify-between gap-3 border border-cyan-900/50 bg-black/40 px-4 py-3">
                            <p class="text-[10px] uppercase text-cyan-300">Containment Sector // Escape Protocol</p>
                            <div class="flex items-center gap-2 text-[10px]">
                                <span class="escape-dot"></span>
                                <span class="text-emerald-300 uppercase">System Armed</span>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 md:grid-cols-6 gap-3">
                            <div class="border border-cyan-700/70 bg-cyan-900/20 p-3">
                                <p class="text-[9px] uppercase text-cyan-300">Quest</p>
                                <p class="text-[12px] text-white mt-2 truncate">{{ quest.title }}</p>
                            </div>
                            <div class="border border-emerald-700/70 bg-emerald-900/20 p-3">
                                <p class="text-[9px] uppercase text-emerald-300">Timer</p>
                                <p class="text-[18px] text-white mt-2">{{ gameElapsedLabel }}</p>
                            </div>
                            <div class="border border-rose-700/70 bg-rose-900/20 p-3">
                                <p class="text-[9px] uppercase text-rose-300">Countdown</p>
                                <p class="text-[18px] mt-2" :class="gameRemainingSeconds <= 60 ? 'text-red-300' : 'text-white'">{{ gameRemainingLabel }}</p>
                            </div>
                            <div class="border border-yellow-700/70 bg-yellow-900/20 p-3">
                                <p class="text-[9px] uppercase text-yellow-300">Stage</p>
                                <p class="text-[18px] text-white mt-2">{{ activeStage ? `${activeStage.stageNumber}/${gameStages.length}` : '0/0' }}</p>
                            </div>
                            <div class="border border-purple-700/70 bg-purple-900/20 p-3">
                                <p class="text-[9px] uppercase text-purple-300">Progress</p>
                                <p class="text-[18px] text-white mt-2">{{ gameProgressPercent }}%</p>
                            </div>
                            <div class="border border-rose-700/70 bg-rose-900/20 p-3">
                                <p class="text-[9px] uppercase text-rose-300">Integrity</p>
                                <p class="text-[18px] text-white mt-2">{{ integrity }}/3</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 flex-1">
                            <div class="lg:col-span-9 border border-cyan-900/60 bg-black/50 p-4 space-y-4">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-[12px] text-cyan-300 uppercase">Escape Mission Console</p>
                                    <button
                                        type="button"
                                        class="px-3 py-2 border border-emerald-500 text-emerald-300 hover:bg-emerald-500/20 uppercase text-[9px]"
                                        @click="startMission"
                                    >
                                        Restart Mission
                                    </button>
                                </div>
                                <p class="text-[11px] text-slate-300 font-sans">{{ gameMessage }}</p>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <div class="border border-cyan-900/60 bg-cyan-950/20 p-3">
                                        <p class="text-[9px] uppercase text-cyan-300">Scene: {{ activeScene.scene }}</p>
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            <button
                                                v-for="spot in activeScene.hotspots"
                                                :key="spot.id"
                                                type="button"
                                                class="px-2 py-1 border text-[8px] uppercase"
                                                :class="discoveredHotspots[spot.id] ? 'border-emerald-500 text-emerald-300 bg-emerald-900/20' : 'border-cyan-700 text-cyan-300 hover:bg-cyan-900/20'"
                                                @click="activateHotspot(spot, activeStage)"
                                            >
                                                {{ discoveredHotspots[spot.id] ? `${spot.label} ✓` : spot.label }}
                                            </button>
                                        </div>
                                    </div>
                                    <div class="border border-yellow-900/60 bg-yellow-950/20 p-3">
                                        <p class="text-[9px] uppercase text-yellow-300">Inventory</p>
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            <span
                                                v-for="item in inventoryItems"
                                                :key="item"
                                                class="px-2 py-1 border border-yellow-700 text-yellow-200 text-[8px] uppercase"
                                            >
                                                {{ item }}
                                            </span>
                                            <span v-if="inventoryItems.length === 0" class="text-[8px] uppercase text-slate-500">Empty</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <button
                                        v-for="(stage, idx) in gameStages"
                                        :key="`fs-stage-nav-${stage.uuid}`"
                                        type="button"
                                        class="px-3 py-2 border text-[9px] uppercase disabled:opacity-40"
                                        :disabled="idx > maxUnlockedStage"
                                        :class="isStageCleared(stage) ? 'border-emerald-500 text-emerald-300 bg-emerald-900/20' : (idx === activeStageIndex ? 'border-cyan-500 text-cyan-300 bg-cyan-900/20' : 'border-slate-700 text-slate-400')"
                                        @click="goStage(idx)"
                                    >
                                        {{ idx > maxUnlockedStage ? '???' : `Stage ${stage.stageNumber}` }}
                                    </button>
                                </div>

                                    <div v-if="activeStage" class="bg-black/30 border border-slate-700 p-4 space-y-4">
                                    <p class="text-[10px] text-yellow-300 uppercase">STAGE {{ activeStage.stageNumber }}</p>
                                    <p class="text-[14px] text-slate-200 font-sans whitespace-pre-wrap">{{ activeStage.prompt }}</p>
                                    <p v-if="activeStage.hint" class="text-[11px] text-slate-400 font-sans">Hint: {{ activeStage.hint }}</p>
                                        <input
                                            v-model="form.task_answers[activeStage.uuid]"
                                            type="text"
                                            :disabled="!missionStarted || isTimeExpired || missionLockedByTimeout"
                                            @input="handleStageAnswerInput(activeStage)"
                                            class="w-full bg-[#0d1117] border-2 border-slate-800 p-3 text-white font-sans text-[13px] outline-none transition-all focus:border-cyan-400 disabled:opacity-60"
                                            placeholder="Masukkan kode/jawaban stage..."
                                        >
                                        <div class="flex flex-wrap gap-2 items-center">
                                            <span class="text-[9px] text-slate-400 uppercase">Jawaban dicek otomatis saat kamu mengetik.</span>
                                            <span
                                                class="text-[9px] uppercase"
                                                :class="!String(form.task_answers?.[activeStage.uuid] || '').trim()
                                                    ? 'text-slate-500'
                                                    : (isStageCleared(activeStage) ? 'text-emerald-300' : 'text-red-300')"
                                            >
                                                {{
                                                    !String(form.task_answers?.[activeStage.uuid] || '').trim()
                                                        ? 'Belum diisi'
                                                        : (isStageCleared(activeStage) ? 'Jawaban cocok' : 'Jawaban belum cocok')
                                                }}
                                            </span>
                                        </div>
                                    </div>

                                <div class="grid grid-cols-3 gap-2 text-[9px]">
                                    <div class="border border-cyan-900/60 bg-cyan-950/20 px-2 py-2 uppercase text-cyan-300">Node A</div>
                                    <div class="border border-yellow-900/60 bg-yellow-950/20 px-2 py-2 uppercase text-yellow-300">Node B</div>
                                    <div class="border border-rose-900/60 bg-rose-950/20 px-2 py-2 uppercase text-rose-300">Node C</div>
                                </div>
                            </div>

                            <div class="lg:col-span-3 border border-cyan-900/60 bg-black/40 p-4 space-y-4 flex flex-col">
                                <p class="text-[12px] text-cyan-300 uppercase">Vault Door</p>
                                <div class="door-art-wrap">
                                    <div class="door-art" :class="{ 'door-art-open': allStagesCleared }">
                                        <div class="door-left"></div>
                                        <div class="door-right"></div>
                                        <div class="door-center-glow"></div>
                                        <div class="door-warning"></div>
                                        <div class="door-status">{{ doorStatusLabel }}</div>
                                    </div>
                                </div>

                                <div class="keycard-panel">
                                    <p class="text-[9px] uppercase text-cyan-300">Keycard Reader</p>
                                    <div class="keycard-slot"></div>
                                    <div class="keycard-line">
                                        <span class="key-led" :class="{ 'key-led-green': allStagesCleared }"></span>
                                        <span class="text-[9px] uppercase text-slate-300">{{ allStagesCleared ? 'Authorization Accepted' : 'Awaiting Authorization' }}</span>
                                    </div>
                                </div>

                                <button
                                    type="button"
                                    :disabled="form.processing || !allStagesCleared"
                                    class="w-full py-4 border-2 transition-all font-bold uppercase text-[12px] disabled:opacity-60 disabled:cursor-not-allowed"
                                    :class="allStagesCleared ? 'bg-emerald-800/40 border-emerald-300 text-emerald-200' : 'bg-slate-800/40 border-slate-600 text-slate-400'"
                                    @click="submitReport"
                                >
                                    {{ form.processing ? 'Transmitting...' : (allStagesCleared ? 'Finalize Escape' : 'Complete All Stages') }}
                                </button>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
                            <div class="lg:col-span-8 border border-cyan-900/60 bg-black/40 p-3">
                                <p class="text-[10px] uppercase text-cyan-300 mb-2">Mission Log</p>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-2 text-[9px]">
                                    <div class="border border-cyan-900/60 bg-cyan-950/20 p-2 uppercase">Stage Cleared: {{ Math.round((gameProgressPercent / 100) * gameStages.length) }}/{{ gameStages.length }}</div>
                                    <div class="border border-yellow-900/60 bg-yellow-950/20 p-2 uppercase">Remaining Time: {{ gameRemainingLabel }}</div>
                                    <div class="border border-rose-900/60 bg-rose-950/20 p-2 uppercase">Integrity: {{ integrity }}/3</div>
                                </div>
                            </div>
                            <div class="lg:col-span-4 border border-cyan-900/60 bg-black/40 p-3">
                                <p class="text-[10px] uppercase text-cyan-300 mb-2">Telemetry</p>
                                <div class="telemetry-bar">
                                    <div class="telemetry-fill" :style="{ width: `${Math.max(8, gameProgressPercent)}%` }"></div>
                                </div>
                                <div class="mt-2 text-[9px] uppercase text-slate-400">Door Core Charge {{ gameProgressPercent }}%</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="showResultScreen" class="fixed inset-0 z-[2147483648] bg-black/85 flex items-center justify-center p-4">
                    <div class="w-full max-w-xl border-2 border-cyan-700 bg-[#060d18] p-6 space-y-4">
                        <p class="text-[12px] uppercase text-cyan-300">Escape Result</p>
                        <div class="grid grid-cols-3 gap-3">
                            <div class="border border-cyan-800 p-3">
                                <p class="text-[8px] uppercase text-slate-400">Rank</p>
                                <p class="text-[22px] text-emerald-300 mt-2">{{ rankLabel }}</p>
                            </div>
                            <div class="border border-cyan-800 p-3">
                                <p class="text-[8px] uppercase text-slate-400">Time</p>
                                <p class="text-[16px] text-cyan-200 mt-2">{{ gameElapsedLabel }}</p>
                            </div>
                            <div class="border border-cyan-800 p-3">
                                <p class="text-[8px] uppercase text-slate-400">Progress</p>
                                <p class="text-[16px] text-cyan-200 mt-2">{{ gameProgressPercent }}%</p>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <button
                                type="button"
                                class="flex-1 py-3 border border-slate-600 text-slate-300 uppercase text-[10px]"
                                @click="showResultScreen = false"
                            >
                                Back
                            </button>
                            <button
                                type="button"
                                class="flex-1 py-3 border border-emerald-500 text-emerald-300 uppercase text-[10px] hover:bg-emerald-900/20"
                                @click="submitReport"
                            >
                                Confirm Finalize
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            </Teleport>

            <div
                class="quest-shell w-full border-4 border-slate-700 shadow-[10px_10px_0px_0px_rgba(0,0,0,0.5)] md:shadow-[20px_20px_0px_0px_rgba(0,0,0,0.5)] relative overflow-hidden"
                :class="isGameEscapeQuest ? 'max-w-[1500px]' : 'max-w-3xl'"
                :style="questToneStyle"
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

                    <div class="pb-2 text-[11px] uppercase">
                        <span class="text-slate-500">Class_Node:</span>
                        <span class="quest-class-badge ml-2">{{ questClassLabel }}</span>
                    </div>

                    <div
                        v-if="isStaffPlayMode"
                        class="border border-cyan-500/50 bg-cyan-500/10 p-4 text-[10px] uppercase leading-relaxed text-cyan-100"
                    >
                        Staff play mode aktif. Kamu tetap bisa mengirim submission untuk preview flow, tetapi reward, leaderboard, dan penggunaan Time Key tidak dihitung ke mode pemain utama.
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
                        <h3 class="mb-4 uppercase text-yellow-400 text-[10px] leading-relaxed tracking-normal break-all sm:text-[12px] sm:tracking-widest sm:break-normal">
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
                                    :disabled="unlockForm.processing || (timeKeyQty || 0) < 1 || isStaffPlayMode"
                                    @click="unlockLateQuest"
                                >
                                    {{ isStaffPlayMode ? 'Preview Only' : (unlockForm.processing ? 'PROCESSING...' : 'Use_Time_Key') }}
                                </button>
                            </div>
                        </div>
                        <p v-if="page.props.errors?.unlock" class="mt-3 text-[10px] text-red-400 font-sans">
                            {{ page.props.errors.unlock }}
                        </p>
                    </div>

                    <div v-if="canSubmit" class="mt-8 p-4 md:p-6 border-2 border-dashed border-cyan-900 bg-black/20">
                        <h3 class="text-[12px] mb-6 uppercase tracking-widest" :class="props.hasSubmitted ? 'text-yellow-500' : 'text-white'">
                            >> {{ isStructuredTaskBankQuest
                                ? (canResubmitRejected ? 'Re-Take_Task_Bank' : 'Task_Bank_Submission')
                                : (canResubmitRejected
                                    ? 'Re-Take_Rejected_Report'
                                    : (canResubmitPending ? 'Re-Submit_Pending_Report' : (props.hasSubmitted ? 'Edit_Existing_Report' : 'Submit_Quest_Report'))) }}
                        </h3>

                        <form @submit.prevent="submitReport" class="space-y-6">
                            <div v-if="isStructuredTaskBankQuest" class="space-y-4">
                                <div class="bg-slate-900/40 border border-cyan-900/50 p-3">
                                    <p class="text-[10px] text-cyan-300 uppercase">
                                        BANK: {{ quest.task_bank?.name || 'TASK_BANK' }} [{{ taskBankType || 'essay' }}]
                                    </p>
                                    <p class="text-[10px] text-slate-400 font-sans mt-1">
                                        <span v-if="isGameEscapeQuest">Selesaikan semua stage Escape Room lalu submit untuk auto-check.</span>
                                        <span v-else>Jawab semua soal lalu submit. Submission akan diproses oleh sistem/admin.</span>
                                    </p>
                                    <p v-if="taskBankType === 'multiple_choice' && !isAllMcq"
                                        class="text-[10px] text-yellow-300 font-sans mt-2">
                                        Detected essay question(s). Auto-check dinonaktifkan, submission akan masuk review manual.
                                    </p>
                                </div>

                                <p v-if="form.errors.task_answers" class="text-[10px] text-red-400 font-sans">
                                    {{ form.errors.task_answers }}
                                </p>
                                <template v-if="isGameEscapeQuest">
                                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
                                        <div class="lg:col-span-8 border border-emerald-700/60 bg-black/50 p-4 space-y-4">
                                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                                <p class="text-[10px] text-emerald-300 uppercase">Escape Mission Console</p>
                                                <button
                                                    type="button"
                                                    class="px-3 py-2 border border-emerald-500 text-emerald-300 hover:bg-emerald-500/20 uppercase text-[9px]"
                                                    @click="startMission"
                                                >
                                                    {{ missionStarted ? 'Restart Mission' : 'Start Mission' }}
                                                </button>
                                            </div>
                                            <p class="text-[11px] text-slate-300 font-sans">{{ gameMessage }}</p>

                                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                                <div class="border border-emerald-700/60 bg-emerald-900/20 p-3">
                                                    <p class="text-[9px] text-emerald-300 uppercase">Timer</p>
                                                    <p class="text-[16px] text-white mt-2">{{ gameElapsedLabel }}</p>
                                                </div>
                                                <div class="border border-cyan-700/60 bg-cyan-900/20 p-3">
                                                    <p class="text-[9px] text-cyan-300 uppercase">Progress</p>
                                                    <p class="text-[16px] text-white mt-2">{{ gameProgressPercent }}%</p>
                                                </div>
                                                <div class="border border-yellow-700/60 bg-yellow-900/20 p-3">
                                                    <p class="text-[9px] text-yellow-300 uppercase">Stage</p>
                                                    <p class="text-[16px] text-white mt-2">{{ activeStage ? `${activeStage.stageNumber}/${gameStages.length}` : '0/0' }}</p>
                                                </div>
                                                <div class="border border-rose-700/60 bg-rose-900/20 p-3">
                                                    <p class="text-[9px] text-rose-300 uppercase">Integrity</p>
                                                    <p class="text-[16px] text-white mt-2">{{ integrity }}/3</p>
                                                </div>
                                            </div>

                                            <div class="w-full h-3 border border-slate-700 bg-black/30 overflow-hidden">
                                                <div class="h-full bg-cyan-500/70 transition-all duration-300" :style="{ width: `${gameProgressPercent}%` }"></div>
                                            </div>

                                            <div class="flex flex-wrap gap-2">
                                                <button
                                                    v-for="(stage, idx) in gameStages"
                                                    :key="`stage-nav-${stage.uuid}`"
                                                    type="button"
                                                    class="px-3 py-2 border text-[9px] uppercase"
                                                    :class="isStageCleared(stage) ? 'border-emerald-500 text-emerald-300 bg-emerald-900/20' : (idx === activeStageIndex ? 'border-cyan-500 text-cyan-300 bg-cyan-900/20' : 'border-slate-700 text-slate-300')"
                                                    @click="goStage(idx)"
                                                >
                                                    Stage {{ stage.stageNumber }}
                                                </button>
                                            </div>

                                            <div v-if="activeStage" class="bg-black/30 border border-slate-700 p-4 space-y-4">
                                                <p class="text-[10px] text-yellow-300 uppercase">
                                                    STAGE {{ activeStage.stageNumber }} · WEIGHT {{ activeStage.weight || 1 }}
                                                </p>
                                                <p class="text-[13px] text-slate-200 font-sans whitespace-pre-wrap">{{ activeStage.prompt }}</p>
                                                <p v-if="activeStage.hint" class="text-[11px] text-slate-400 font-sans">
                                                    Hint: {{ activeStage.hint }}
                                                </p>
                                                <input
                                                    v-model="form.task_answers[activeStage.uuid]"
                                                    type="text"
                                                    :disabled="!missionStarted"
                                                    class="w-full bg-[#0d1117] border-2 border-slate-800 p-3 text-white font-sans text-[13px] outline-none transition-all focus:border-cyan-400 disabled:opacity-60"
                                                    placeholder="Masukkan kode/jawaban stage..."
                                                >
                                                <div class="flex flex-wrap gap-2">
                                                    <button type="button" class="px-3 py-2 border border-cyan-500 text-cyan-300 hover:bg-cyan-500/20 uppercase text-[9px] disabled:opacity-60" :disabled="!missionStarted" @click="checkCurrentStage">
                                                        Check_Stage
                                                    </button>
                                                    <span class="text-[9px] text-slate-400 uppercase py-2">Attempts: {{ localAttempts[activeStage.uuid] || 0 }}/{{ activeStage.maxAttempts }}</span>
                                                    <span v-if="isStageCleared(activeStage)" class="text-[9px] text-emerald-300 uppercase py-2">Cleared</span>
                                                </div>
                                                <p v-if="form.errors[`task_answers.${activeStage.uuid}`]" class="text-[10px] text-red-400 font-sans">
                                                    {{ form.errors[`task_answers.${activeStage.uuid}`] }}
                                                </p>
                                            </div>
                                        </div>

                                        <div class="lg:col-span-4 border border-cyan-900/60 bg-black/40 p-4 space-y-4">
                                            <div class="space-y-2">
                                                <label class="block text-[12px] text-slate-500 uppercase">
                                                    Mission_Notes: <span class="font-sans normal-case">Optional</span>
                                                </label>
                                                <textarea
                                                    v-model="form.content"
                                                    class="w-full bg-[#0d1117] border-2 border-slate-800 p-3 text-white font-sans text-[13px] outline-none transition-all focus:border-cyan-400"
                                                    rows="8"
                                                    placeholder="Catatan tambahan (opsional)."
                                                ></textarea>
                                                <p v-if="form.errors.content" class="text-[10px] text-red-400 font-sans">
                                                    {{ form.errors.content }}
                                                </p>
                                            </div>

                                            <button
                                                type="submit"
                                                :disabled="form.processing || !allStagesCleared"
                                                class="w-full py-4 border-2 transition-all font-bold uppercase text-[12px] active:scale-95 disabled:opacity-60 disabled:cursor-not-allowed"
                                                :class="allStagesCleared ? 'bg-emerald-800/40 border-emerald-300 text-emerald-200' : 'bg-slate-800/40 border-slate-600 text-slate-400'"
                                            >
                                                {{ form.processing ? 'TRANSMITTING...' : (allStagesCleared ? 'FINALIZE_ESCAPE' : 'COMPLETE_ALL_STAGES_FIRST') }}
                                            </button>

                                            <p class="text-[10px] text-slate-400 font-sans">
                                                Finalize akan aktif setelah semua stage clear.
                                            </p>
                                        </div>
                                    </div>
                                </template>

                                <template v-else>
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
                                </template>
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

                            <div v-if="isStructuredTaskBankQuest && !isGameEscapeQuest" class="space-y-2">
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

                            <div v-if="!isStructuredTaskBankQuest">
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
                                v-if="!isGameEscapeQuest"
                                type="submit"
                                :disabled="form.processing"
                                class="w-full py-4 border-2 transition-all font-bold uppercase text-[12px] active:scale-95"
                                :class="isAutoCheckedTaskBankQuest
                                    ? 'bg-cyan-800/40 border-cyan-300 text-cyan-200'
                                    : (isGameEscapeQuest
                                        ? (allStagesCleared ? 'bg-emerald-800/40 border-emerald-300 text-emerald-200' : 'bg-slate-800/40 border-slate-600 text-slate-400')
                                        : (props.hasSubmitted ? 'bg-yellow-900/20 border-yellow-500 text-yellow-500' : 'bg-cyan-900/40 border-cyan-400 text-cyan-400'))"
                            >
                                {{ form.processing ? 'TRANSMITTING...' : (isGameEscapeQuest ? (allStagesCleared ? 'FINALIZE_ESCAPE' : 'COMPLETE_ALL_STAGES_FIRST') : 'SUBMIT') }}
                            </button>
                        </form>
                    </div>

                    <div v-else class="mt-8 p-4 md:p-6 border-2 border-dashed border-slate-800 bg-black/20">
                        <h3 class="text-[12px] mb-3 uppercase tracking-widest text-white">
                            >> SUBMISSION_LOCKED
                        </h3>
                        <p class="text-[12px] text-slate-300 font-sans">
                            Kamu sudah submit quest ini. Submission tidak bisa diulang.
                        </p>
                        <div v-if="existingSubmission?.uuid" class="mt-4 flex flex-col sm:flex-row gap-3">
                            <Link
                                :href="route('submissions.show', existingSubmission.uuid)"
                                class="text-center text-[12px] bg-cyan-900/50 text-cyan-300 px-4 py-3 border border-cyan-700 hover:bg-cyan-500 hover:text-black transition-all uppercase font-bold"
                            >
                                [ VIEW_SUBMISSION ]
                            </Link>
                        </div>

                        <div v-if="isStructuredTaskBankQuest && taskQuestions.length" class="mt-6 space-y-4">
                            <div class="bg-slate-900/40 border border-cyan-900/50 p-3">
                                <p class="text-[10px] text-cyan-300 uppercase">
                                    BANK: {{ quest.task_bank?.name || 'TASK_BANK' }} [{{ taskBankType || 'essay' }}]
                                </p>
                                <p class="text-[10px] text-slate-400 font-sans mt-1">
                                    Berikut jawaban yang kamu kirim (read-only).
                                </p>
                            </div>

                            <div
                                v-for="(question, index) in taskQuestions"
                                :key="`locked-${question.uuid}`"
                                class="bg-black/30 border border-slate-700 p-4 space-y-3"
                            >
                                <p class="text-[10px] text-yellow-300 uppercase">
                                    Q{{ index + 1 }} · WEIGHT {{ question.weight || 1 }}
                                </p>
                                <p class="text-[13px] text-slate-200 font-sans whitespace-pre-wrap">
                                    {{ question.question_text }}
                                </p>

                                <template v-if="question.question_type === 'multiple_choice'">
                                    <p class="text-[10px] text-slate-400 font-sans uppercase">Selected_Answer:</p>
                                    <p class="text-[13px] font-sans" :class="answerFor(question) ? 'text-cyan-300' : 'text-red-400'">
                                        {{ answerFor(question) || 'NOT_ANSWERED' }}
                                    </p>

                                    <div v-if="Array.isArray(question.options_json) && question.options_json.length" class="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-2">
                                        <div
                                            v-for="(option, optionIndex) in question.options_json"
                                            :key="`locked-${question.uuid}-${optionIndex}`"
                                            class="border px-3 py-2 text-[12px] font-sans break-words"
                                            :class="String(option) === String(answerFor(question)) ? 'border-cyan-500 bg-cyan-500/10 text-cyan-200' : 'border-slate-700 text-slate-400 bg-black/10'"
                                        >
                                            {{ option }}
                                        </div>
                                    </div>
                                </template>

                                <template v-else>
                                    <p class="text-[10px] text-slate-400 font-sans uppercase">Essay_Answer:</p>
                                    <div class="bg-black/40 border border-slate-700 p-3 text-[13px] text-slate-200 font-sans whitespace-pre-wrap">
                                        {{ answerFor(question) || 'NOT_ANSWERED' }}
                                    </div>
                                </template>
                            </div>
                        </div>
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

<style scoped>
.quest-shell {
    background-color: #161b22;
    border-color: color-mix(in srgb, var(--quest-tone-border) 54%, #334155 46%);
    background-image: linear-gradient(180deg, var(--quest-tone-bg) 0%, rgba(22, 27, 34, 0.95) 100%);
}

.quest-class-badge {
    border: 1px solid color-mix(in srgb, var(--quest-tone-border) 58%, transparent 42%);
    background: color-mix(in srgb, var(--quest-tone-bg) 72%, transparent 28%);
    color: color-mix(in srgb, var(--quest-tone-accent) 90%, #f8fafc 10%);
    padding: 0.18rem 0.5rem;
}

.escape-overlay {
    position: fixed;
    isolation: isolate;
}

.escape-bg-grid,
.escape-bg-scanline,
.escape-bg-glow {
    position: absolute;
    inset: 0;
    pointer-events: none;
}

.escape-bg-grid {
    background-image:
        linear-gradient(rgba(0, 180, 255, 0.07) 1px, transparent 1px),
        linear-gradient(90deg, rgba(0, 180, 255, 0.07) 1px, transparent 1px);
    background-size: 38px 38px, 38px 38px;
    animation: grid-drift 14s linear infinite;
    opacity: 0.35;
    z-index: -3;
}

.escape-bg-scanline {
    background: repeating-linear-gradient(
        to bottom,
        rgba(255, 255, 255, 0.02) 0px,
        rgba(255, 255, 255, 0.02) 1px,
        transparent 2px,
        transparent 4px
    );
    mix-blend-mode: screen;
    opacity: 0.28;
    z-index: -2;
}

.escape-bg-glow {
    background:
        radial-gradient(700px 280px at 15% 12%, rgba(0, 187, 255, 0.2), transparent 60%),
        radial-gradient(600px 260px at 82% 18%, rgba(0, 255, 170, 0.12), transparent 65%),
        radial-gradient(500px 200px at 50% 100%, rgba(150, 0, 255, 0.12), transparent 70%);
    animation: glow-breathe 5.2s ease-in-out infinite;
    z-index: -1;
}

.escape-dot {
    width: 8px;
    height: 8px;
    border-radius: 999px;
    background: #34d399;
    box-shadow: 0 0 10px #34d399;
    animation: dot-pulse 1.3s ease-in-out infinite;
}

@keyframes grid-drift {
    from { transform: translateY(0); }
    to { transform: translateY(38px); }
}

@keyframes glow-breathe {
    0%, 100% { opacity: 0.75; }
    50% { opacity: 1; }
}

@keyframes dot-pulse {
    0%, 100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(0.8); opacity: 0.55; }
}

.door-art-wrap {
    border: 1px solid rgba(34, 211, 238, 0.35);
    background: linear-gradient(180deg, rgba(3, 12, 30, 0.95), rgba(7, 10, 18, 0.9));
    padding: 0.9rem;
}

.door-art {
    position: relative;
    height: 210px;
    border: 2px solid rgba(34, 211, 238, 0.45);
    overflow: hidden;
    background:
        linear-gradient(180deg, rgba(4, 16, 30, 0.95), rgba(2, 8, 18, 0.95));
    box-shadow: inset 0 0 40px rgba(6, 182, 212, 0.18);
}

.door-left,
.door-right {
    position: absolute;
    top: 0;
    bottom: 0;
    width: 50%;
    background: repeating-linear-gradient(
        90deg,
        rgba(15, 23, 42, 0.98) 0px,
        rgba(15, 23, 42, 0.98) 8px,
        rgba(30, 41, 59, 0.98) 9px,
        rgba(30, 41, 59, 0.98) 16px
    );
    border-right: 1px solid rgba(34, 211, 238, 0.35);
    transition: transform 0.7s ease;
}

.door-left { left: 0; }
.door-right {
    right: 0;
    border-right: 0;
    border-left: 1px solid rgba(34, 211, 238, 0.35);
}

.door-art-open .door-left { transform: translateX(-82%); }
.door-art-open .door-right { transform: translateX(82%); }

.door-center-glow {
    position: absolute;
    left: 50%;
    top: 50%;
    width: 110px;
    height: 110px;
    transform: translate(-50%, -50%);
    border-radius: 999px;
    background: radial-gradient(circle, rgba(34, 211, 238, 0.45), rgba(2, 8, 18, 0));
    opacity: 0.45;
    animation: glow-breathe 3.5s ease-in-out infinite;
}

.door-warning {
    position: absolute;
    left: 0;
    right: 0;
    bottom: 42px;
    height: 18px;
    background: repeating-linear-gradient(
        45deg,
        rgba(245, 158, 11, 0.75) 0 10px,
        rgba(15, 23, 42, 0.82) 10px 20px
    );
    border-top: 1px solid rgba(245, 158, 11, 0.4);
    border-bottom: 1px solid rgba(245, 158, 11, 0.4);
}

.door-left::after,
.door-right::after {
    content: '';
    position: absolute;
    width: 14px;
    height: 52px;
    border: 1px solid rgba(34, 211, 238, 0.45);
    background: rgba(15, 23, 42, 0.95);
    top: 36%;
}

.door-left::after { right: 10px; }
.door-right::after { left: 10px; }

.door-status {
    position: absolute;
    bottom: 12px;
    left: 0;
    right: 0;
    text-align: center;
    font-size: 10px;
    text-transform: uppercase;
    color: #67e8f9;
}

.keycard-panel {
    border: 1px solid rgba(34, 211, 238, 0.35);
    background: linear-gradient(180deg, rgba(8, 15, 30, 0.9), rgba(6, 10, 20, 0.95));
    padding: 0.85rem;
    space-y: 0.5rem;
}

.keycard-slot {
    margin-top: 0.55rem;
    width: 100%;
    height: 26px;
    border: 1px solid rgba(100, 116, 139, 0.7);
    background: linear-gradient(90deg, rgba(15, 23, 42, 0.95), rgba(30, 41, 59, 0.65), rgba(15, 23, 42, 0.95));
    position: relative;
}

.keycard-slot::after {
    content: '';
    position: absolute;
    top: 50%;
    right: 14px;
    width: 42px;
    height: 8px;
    transform: translateY(-50%);
    background: rgba(34, 211, 238, 0.25);
}

.keycard-line {
    margin-top: 0.7rem;
    display: flex;
    align-items: center;
    gap: 0.45rem;
}

.key-led {
    width: 8px;
    height: 8px;
    border-radius: 999px;
    background: #f59e0b;
    box-shadow: 0 0 8px rgba(245, 158, 11, 0.7);
    animation: dot-pulse 1.1s ease-in-out infinite;
}

.key-led-green {
    background: #34d399;
    box-shadow: 0 0 8px rgba(52, 211, 153, 0.8);
}

.telemetry-bar {
    width: 100%;
    height: 12px;
    border: 1px solid rgba(34, 211, 238, 0.35);
    background: rgba(15, 23, 42, 0.6);
    overflow: hidden;
}

.telemetry-fill {
    height: 100%;
    background: linear-gradient(90deg, rgba(34, 211, 238, 0.75), rgba(16, 185, 129, 0.85));
    transition: width 0.4s ease;
}

</style>
