<script setup>
import { computed } from 'vue';

const props = defineProps({
    criteria: { type: Array, default: () => [] },
    levels: { type: Array, default: () => [] },
    modelValue: { type: Object, default: () => ({}) }, // { [criteriaId]: { [levelId]: string } }
    readonly: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const normalizedMatrix = computed(() => props.modelValue || {});

const getCellValue = (criteriaId, levelId) => {
    return normalizedMatrix.value?.[criteriaId]?.[levelId] ?? '';
};

const setCellValue = (criteriaId, levelId, value) => {
    const next = { ...(normalizedMatrix.value || {}) };
    const row = { ...(next[criteriaId] || {}) };
    row[levelId] = value;
    next[criteriaId] = row;
    emit('update:modelValue', next);
};
</script>

<template>
    <div class="border-2 border-slate-700 bg-[#0f101a]/70">
        <div class="max-h-[65vh] overflow-auto">
            <table class="min-w-full table-fixed border-collapse">
                <thead class="sticky top-0 z-20 bg-[#0f101a]">
                    <tr>
                        <th
                            class="w-[240px] text-left p-3 border-b border-slate-700 text-[10px] uppercase tracking-widest text-slate-300 sticky left-0 z-30 bg-[#0f101a]"
                        >
                            Criteria
                        </th>
                        <th
                            v-for="level in levels"
                            :key="level.id"
                            class="min-w-[220px] p-3 border-b border-slate-700 text-[10px] uppercase tracking-widest text-slate-300"
                        >
                            <div class="flex flex-col gap-1">
                                <span class="text-white text-[10px] uppercase">{{ level.label }}</span>
                                <span class="text-[8px] text-slate-500">Score: {{ level.score_value }}</span>
                            </div>
                        </th>
                    </tr>
                </thead>

                <tbody>
                    <tr v-if="criteria.length === 0">
                        <td
                            class="p-6 text-center text-[10px] uppercase text-slate-500"
                            :colspan="levels.length + 1"
                        >
                            No criteria yet. Add one to start building the rubric.
                        </td>
                    </tr>

                    <tr v-for="c in criteria" :key="c.id" class="align-top">
                        <td
                            class="p-3 border-b border-slate-800 sticky left-0 z-10 bg-[#0f101a] border-r border-slate-800"
                        >
                            <div class="flex flex-col gap-1">
                                <div class="text-white text-[10px] uppercase leading-snug">
                                    {{ c.name }}
                                </div>
                                <div class="text-[8px] text-slate-500">
                                    Weight: {{ c.weight }}
                                </div>
                            </div>
                        </td>

                        <td
                            v-for="level in levels"
                            :key="level.id"
                            class="p-2 border-b border-slate-800"
                        >
                            <textarea
                                v-if="!readonly"
                                class="w-full min-h-[92px] resize-y bg-black/30 border border-slate-700 text-slate-200 text-[9px] leading-relaxed p-2 focus:outline-none focus:border-cyan-400 focus:ring-2 focus:ring-cyan-400/20"
                                :value="getCellValue(c.id, level.id)"
                                @input="setCellValue(c.id, level.id, $event.target.value)"
                                placeholder="Write description..."
                            />
                            <div v-else class="min-h-[92px] p-2 text-[9px] leading-relaxed text-slate-200 whitespace-pre-wrap">
                                {{ getCellValue(c.id, level.id) || '-' }}
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

