<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminNavbar from '@/Components/AdminNavbar.vue';
import RubricMatrixEditor from '@/Components/RubricMatrixEditor.vue';

const props = defineProps({
    rubric: Object,
    criteria: Array,
    levels: Array,
    matrix: Object,
});

const rubricId = computed(() => props.rubric?.id);
const exportUrl = computed(() => route('admin.rubrics.export', rubricId.value));

const maxLevelScore = computed(() => {
    return Math.max(0, ...(props.levels || []).map((l) => Number(l.score_value || 0)));
});

const selections = ref({});

const totalScore = computed(() => {
    const max = maxLevelScore.value;
    if (!max) return 0;

    let total = 0;
    for (const c of props.criteria || []) {
        const levelId = Number(selections.value?.[c.id] || 0);
        const level = (props.levels || []).find((l) => Number(l.id) === levelId);
        const selectedScore = level ? Number(level.score_value || 0) : 0;
        const weight = Number(c.weight || 0);
        total += (selectedScore / max) * weight;
    }
    return total;
});
</script>

<template>
    <Head :title="`RUBRIC | ${rubric?.title || ''}`" />

    <div class="min-h-screen bg-[#0f101a] p-4 md:p-8 font-['Press_Start_2P'] text-[#4ed4d4] text-[10px] leading-relaxed">
        <div class="max-w-7xl mx-auto space-y-8">
            <AdminNavbar />

            <div class="rpg-panel bg-black/40 border-slate-700 shadow-none">
                <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                    <div class="min-w-0">
                        <h1 class="text-white text-sm uppercase tracking-widest truncate">{{ rubric?.title }}</h1>
                        <p class="text-[8px] text-slate-500 uppercase mt-2">
                            Mentor: <span class="text-cyan-300">{{ rubric?.mentor_name || '-' }}</span>
                            <span class="text-slate-600">|</span>
                            Max_Score: <span class="text-emerald-300">{{ rubric?.max_score ?? 0 }}</span>
                        </p>
                        <p class="mt-4 text-[9px] text-slate-300 leading-relaxed whitespace-pre-wrap">
                            {{ rubric?.description || '' }}
                        </p>
                    </div>

                    <div class="flex gap-2 flex-shrink-0">
                        <a
                            :href="exportUrl"
                            class="btn-pixel bg-cyan-300 text-black px-4 py-2 border-cyan-700 uppercase font-bold hover:bg-cyan-200 transition-colors text-[8px]"
                            target="_blank"
                            rel="noreferrer"
                        >
                            Export JSON
                        </a>
                        <Link
                            :href="route('admin.rubrics.edit', rubricId)"
                            class="btn-pixel bg-yellow-300 text-black px-4 py-2 border-yellow-700 uppercase font-bold hover:bg-yellow-200 transition-colors text-[8px]"
                        >
                            Edit
                        </Link>
                        <Link
                            :href="route('admin.rubrics.index')"
                            class="btn-pixel bg-slate-700 text-white px-4 py-2 border-slate-900 uppercase font-bold hover:bg-slate-600 transition-colors text-[8px]"
                        >
                            Back
                        </Link>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-12 gap-6">
                <div class="col-span-12 lg:col-span-8 space-y-6">
                    <div class="rpg-panel bg-[#1a1c2c]/80 border-slate-700">
                        <div class="flex items-center justify-between gap-4 mb-4">
                            <h2 class="text-white text-[11px] uppercase tracking-widest">Rubric Matrix</h2>
                            <div class="text-[8px] text-slate-500 uppercase">
                                Read-only preview
                            </div>
                        </div>

                        <RubricMatrixEditor
                            :readonly="true"
                            :criteria="criteria"
                            :levels="levels"
                            :model-value="matrix"
                        />
                    </div>
                </div>

                <div class="col-span-12 lg:col-span-4 space-y-6">
                    <div class="rpg-panel bg-[#1a1c2c]/80 border-slate-700">
                        <h2 class="text-white text-[11px] uppercase tracking-widest mb-4">Scoring Preview</h2>
                        <p class="text-[8px] text-slate-500 uppercase mb-4">
                            Select a level per criteria to see automatic score calculation.
                        </p>

                        <div class="space-y-3">
                            <div v-for="c in criteria" :key="c.id" class="border-2 border-slate-700 bg-black/20 p-3">
                                <div class="text-white text-[10px] uppercase leading-snug">
                                    {{ c.name }}
                                </div>
                                <div class="text-[8px] text-slate-500 uppercase mt-1">
                                    Weight: <span class="text-emerald-300">{{ c.weight }}</span>
                                </div>
                                <select
                                    v-model="selections[c.id]"
                                    class="mt-3 w-full bg-black/30 border-2 border-slate-700 px-3 py-2 text-slate-200 text-[9px] focus:outline-none focus:border-cyan-400"
                                >
                                    <option :value="''">Select level</option>
                                    <option v-for="l in levels" :key="l.id" :value="l.id">
                                        {{ l.label }} ({{ l.score_value }})
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="mt-4 border-t border-slate-700 pt-4">
                            <div class="text-[8px] text-slate-500 uppercase">Total</div>
                            <div class="text-white text-xl font-bold">
                                {{ totalScore.toFixed(2) }}
                                <span class="text-[10px] text-slate-500">/ {{ rubric?.max_score ?? 0 }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

