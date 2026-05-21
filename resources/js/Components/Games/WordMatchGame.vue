<script setup>
import { ref, computed, watch } from 'vue';

const props = defineProps({
    question: Object,
    modelValue: { type: String, default: '' },
});
const emit = defineEmits(['update:modelValue']);

const config = computed(() => props.question?.options_json || {});
const sentence = computed(() => config.value.sentence || '');
const blanks = computed(() => config.value.blanks || []);
const distractors = computed(() => config.value.distractors || []);

// All available word cards (blanks + distractors shuffled once)
const allCards = ref([]);
const initCards = () => {
    const cards = [...blanks.value, ...distractors.value];
    allCards.value = cards.sort(() => Math.random() - 0.5);
};
initCards();

// Split sentence by ___ to get parts
const sentenceParts = computed(() => sentence.value.split('___'));
const blankCount = computed(() => Math.max(0, sentenceParts.value.length - 1));

// User's placed answers (index -> word)
const placedWords = ref(Array(blankCount.value).fill(null));

// Available cards (not yet placed)
const availableCards = computed(() => {
    const used = placedWords.value.filter(w => w !== null);
    const pool = [...allCards.value];
    for (const word of used) {
        const idx = pool.indexOf(word);
        if (idx !== -1) pool.splice(idx, 1);
    }
    return pool;
});

// Restore from modelValue
const restoreState = () => {
    if (!props.modelValue) return;
    try {
        const parsed = JSON.parse(props.modelValue);
        if (Array.isArray(parsed.placed)) {
            placedWords.value = parsed.placed.map((w, i) => i < blankCount.value ? w : null);
        }
    } catch (_) {}
};
restoreState();

const draggedWord = ref(null);

const onDragStart = (word) => {
    draggedWord.value = word;
};

const onDropOnBlank = (blankIndex) => {
    if (draggedWord.value === null) return;
    // If blank already has a word, put it back
    placedWords.value[blankIndex] = draggedWord.value;
    draggedWord.value = null;
    emitValue();
};

const removeFromBlank = (blankIndex) => {
    placedWords.value[blankIndex] = null;
    emitValue();
};

const onCardClick = (word) => {
    // Find first empty blank
    const emptyIdx = placedWords.value.findIndex(w => w === null);
    if (emptyIdx !== -1) {
        placedWords.value[emptyIdx] = word;
        emitValue();
    }
};

const onBlankClick = (blankIndex) => {
    removeFromBlank(blankIndex);
};

const emitValue = () => {
    const payload = JSON.stringify({
        placed: [...placedWords.value],
        complete: placedWords.value.every(w => w !== null),
    });
    emit('update:modelValue', payload);
};

const resetWords = () => {
    placedWords.value = Array(blankCount.value).fill(null);
    initCards();
    emit('update:modelValue', '');
};
</script>

<template>
    <div class="border-2 border-orange-800 bg-orange-950/20 p-4 space-y-4">
        <div class="flex items-center justify-between">
            <span class="text-[9px] text-orange-300 uppercase">WORD MATCH — Drag kata ke tempat yang kosong</span>
            <button
                type="button"
                @click="resetWords"
                class="px-2 py-1 border border-orange-700 text-orange-300 text-[8px] uppercase hover:bg-orange-700 hover:text-white"
            >
                RESET
            </button>
        </div>

        <!-- Sentence with blanks -->
        <div class="bg-black/30 border border-orange-900/50 p-3 font-sans text-[13px] text-slate-200 leading-relaxed flex flex-wrap items-center gap-1">
            <template v-for="(part, pi) in sentenceParts" :key="`part-${pi}`">
                <span>{{ part }}</span>
                <span
                    v-if="pi < blankCount"
                    class="inline-flex items-center justify-center min-w-[60px] h-8 px-2 border-2 border-dashed rounded cursor-pointer transition-colors"
                    :class="placedWords[pi] ? 'border-orange-400 bg-orange-900/30 text-orange-200' : 'border-slate-600 bg-slate-900/40 text-slate-500'"
                    @click="onBlankClick(pi)"
                    @dragover.prevent
                    @drop.prevent="onDropOnBlank(pi)"
                >
                    {{ placedWords[pi] || '___' }}
                </span>
            </template>
        </div>

        <!-- Draggable word cards -->
        <div class="flex flex-wrap gap-2">
            <div
                v-for="(card, ci) in availableCards"
                :key="`card-${ci}-${card}`"
                draggable="true"
                @dragstart="onDragStart(card)"
                @click="onCardClick(card)"
                class="px-3 py-2 border border-orange-600 bg-orange-900/40 text-orange-200 text-[11px] font-sans cursor-grab hover:bg-orange-700 hover:text-white transition-colors select-none"
            >
                {{ card }}
            </div>
            <p v-if="availableCards.length === 0 && blankCount > 0" class="text-[9px] text-slate-500 uppercase">
                Semua kata sudah ditempatkan. Klik blank untuk mengembalikan.
            </p>
        </div>
    </div>
</template>
