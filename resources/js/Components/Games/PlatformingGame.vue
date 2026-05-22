<script setup>
import { ref, computed, watch } from 'vue';

const props = defineProps({
    question: Object,
    modelValue: { type: String, default: '' },
});
const emit = defineEmits(['update:modelValue']);

const config = computed(() => props.question?.options_json || {});
const stages = computed(() => config.value.stages || []);
const totalStages = computed(() => stages.value.length);

const currentStageIndex = ref(0);
const playerLevel = ref(0);
const answers = ref([]);
const gameFinished = ref(false);
const lastFeedback = ref(null);

// Restore state from modelValue
const restoreState = () => {
    if (!props.modelValue) return;
    try {
        const parsed = JSON.parse(props.modelValue);
        if (parsed && Array.isArray(parsed.answers)) {
            answers.value = parsed.answers;
            playerLevel.value = parsed.level ?? 0;
            currentStageIndex.value = parsed.answers.length;
            if (currentStageIndex.value >= totalStages.value) {
                gameFinished.value = true;
            }
        }
    } catch (_) {}
};
restoreState();

const currentStage = computed(() => stages.value[currentStageIndex.value] || null);

const shuffledOptions = ref([]);

const shuffleCurrentOptions = () => {
    const stage = stages.value[currentStageIndex.value];
    if (!stage) { shuffledOptions.value = []; return; }
    const correct = stage.correct_answer;
    const wrong = stage.wrong_answers || [];
    const opts = [correct, ...wrong];
    shuffledOptions.value = opts.sort(() => Math.random() - 0.5);
};

// Shuffle on mount and when stage changes
shuffleCurrentOptions();

const selectAnswer = (answer) => {
    if (gameFinished.value) return;
    const stage = currentStage.value;
    if (!stage) return;

    const isCorrect = answer === stage.correct_answer;
    const newLevel = isCorrect ? playerLevel.value + 1 : Math.max(0, playerLevel.value - 1);
    playerLevel.value = newLevel;

    answers.value.push({ stage: currentStageIndex.value, answer, correct: isCorrect });
    lastFeedback.value = isCorrect ? 'correct' : 'wrong';

    setTimeout(() => {
        lastFeedback.value = null;
        currentStageIndex.value++;
        if (currentStageIndex.value >= totalStages.value) {
            gameFinished.value = true;
        } else {
            shuffleCurrentOptions();
        }
        emitValue();
    }, 800);
};

const emitValue = () => {
    const payload = JSON.stringify({
        answers: answers.value,
        level: playerLevel.value,
        score: answers.value.filter(a => a.correct).length,
        total: totalStages.value,
    });
    emit('update:modelValue', payload);
};

const resetGame = () => {
    currentStageIndex.value = 0;
    playerLevel.value = 0;
    answers.value = [];
    gameFinished.value = false;
    lastFeedback.value = null;
    shuffleCurrentOptions();
    emit('update:modelValue', '');
};

const platformPositionPercent = computed(() => {
    if (totalStages.value === 0) return 0;
    return Math.round((playerLevel.value / totalStages.value) * 100);
});

const correctCount = computed(() => answers.value.filter(a => a.correct).length);
</script>

<template>
    <div class="border-2 border-purple-800 bg-purple-950/20 p-4 space-y-4">
        <div class="flex items-center justify-between">
            <span class="text-[9px] text-purple-300 uppercase">PLATFORMING GAME</span>
            <span class="text-[9px] text-slate-400">STAGE {{ Math.min(currentStageIndex + 1, totalStages) }} / {{ totalStages }}</span>
        </div>

        <!-- Platform visual -->
        <div class="relative h-32 bg-black/40 border border-purple-900 overflow-hidden">
            <div class="absolute inset-0 flex flex-col-reverse">
                <div
                    v-for="n in totalStages"
                    :key="`platform-${n}`"
                    class="flex-1 border-t border-purple-900/30 flex items-center px-2"
                >
                    <div class="w-full h-1 bg-purple-900/40 rounded"></div>
                </div>
            </div>
            <div
                class="absolute left-4 w-6 h-6 bg-purple-400 border-2 border-purple-200 rounded-sm transition-all duration-500 flex items-center justify-center text-[8px] text-black font-bold"
                :style="{ bottom: platformPositionPercent + '%' }"
            >
                ▲
            </div>
        </div>

        <!-- Feedback flash -->
        <div v-if="lastFeedback" class="text-center py-2">
            <span v-if="lastFeedback === 'correct'" class="text-emerald-400 text-[11px] uppercase font-bold">✓ NAIK! BENAR!</span>
            <span v-else class="text-red-400 text-[11px] uppercase font-bold">✗ TURUN! SALAH!</span>
        </div>

        <!-- Current stage question -->
        <div v-if="!gameFinished && currentStage && !lastFeedback" class="space-y-3">
            <p class="text-[12px] text-slate-200 font-sans">{{ currentStage.prompt }}</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                <button
                    v-for="(opt, oi) in shuffledOptions"
                    :key="`opt-${oi}`"
                    type="button"
                    @click="selectAnswer(opt)"
                    class="px-3 py-2 border border-purple-700 bg-purple-900/30 text-purple-200 text-[11px] font-sans hover:bg-purple-700 hover:text-white transition-colors text-left"
                >
                    {{ opt }}
                </button>
            </div>
        </div>

        <!-- Game finished -->
        <div v-if="gameFinished" class="text-center space-y-3">
            <p class="text-[11px] text-purple-200 uppercase">GAME SELESAI!</p>
            <p class="text-[10px] text-slate-300 font-sans">
                Skor: <span class="text-emerald-400 font-bold">{{ correctCount }}</span> / {{ totalStages }} benar
                · Level akhir: <span class="text-purple-300 font-bold">{{ playerLevel }}</span>
            </p>
            <button
                type="button"
                @click="resetGame"
                class="px-3 py-1 border border-purple-600 text-purple-300 text-[9px] uppercase hover:bg-purple-700 hover:text-white"
            >
                ULANG
            </button>
        </div>
    </div>
</template>
