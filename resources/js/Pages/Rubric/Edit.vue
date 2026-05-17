<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
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

const metaForm = useForm({
    title: props.rubric?.title || '',
    description: props.rubric?.description || '',
});

const criteriaState = ref((props.criteria || []).map((c) => ({ ...c })));
const levelsState = ref((props.levels || []).map((l) => ({ ...l })));
const matrixState = ref(props.matrix || {});

const nextLevelNumber = computed(() => {
    const max = Math.max(0, ...(levelsState.value || []).map((l) => Number(l.level || 0)));
    return max + 1;
});

const newCriterionForm = useForm({
    name: '',
    weight: 25,
    order: (criteriaState.value?.length || 0) + 1,
});

const newLevelForm = useForm({
    level: nextLevelNumber.value,
    label: '',
    score_value: (levelsState.value?.length || 0) + 1,
});

const normalizedDescriptionsPayload = computed(() => {
    const payload = [];
    const criteriaIds = (criteriaState.value || []).map((c) => c.id);
    const levelIds = (levelsState.value || []).map((l) => l.id);
    const criteriaLookup = new Set(criteriaIds);
    const levelLookup = new Set(levelIds);

    for (const [criteriaId, row] of Object.entries(matrixState.value || {})) {
        const cId = Number(criteriaId);
        if (!criteriaLookup.has(cId)) continue;
        for (const [levelId, description] of Object.entries(row || {})) {
            const lId = Number(levelId);
            if (!levelLookup.has(lId)) continue;
            payload.push({
                criteria_id: cId,
                level_id: lId,
                description: String(description ?? ''),
            });
        }
    }

    return payload;
});

const saveRubric = () => {
    metaForm
        .transform((data) => ({
            ...data,
            descriptions: normalizedDescriptionsPayload.value,
        }))
        .put(route('admin.rubrics.update', rubricId.value), {
            preserveScroll: true,
        });
};

const refresh = () => {
    router.get(route('admin.rubrics.edit', rubricId.value), {}, { preserveScroll: true });
};

const addCriterion = () => {
    newCriterionForm.post(route('admin.rubrics.criteria.store', rubricId.value), {
        preserveScroll: true,
        onSuccess: () => {
            newCriterionForm.reset();
            refresh();
        },
    });
};

const addLevel = () => {
    newLevelForm.level = nextLevelNumber.value;
    newLevelForm.post(route('admin.rubrics.levels.store', rubricId.value), {
        preserveScroll: true,
        onSuccess: () => {
            newLevelForm.reset();
            refresh();
        },
    });
};

const deleteCriterion = (criterion) => {
    if (!confirm('DELETE_CRITERION?')) return;
    router.delete(route('admin.rubrics.criteria.destroy', { rubric: rubricId.value, criterion: criterion.id }), {
        preserveScroll: true,
        onSuccess: refresh,
    });
};

const deleteLevel = (level) => {
    if (!confirm('DELETE_LEVEL?')) return;
    router.delete(route('admin.rubrics.levels.destroy', { rubric: rubricId.value, level: level.id }), {
        preserveScroll: true,
        onSuccess: refresh,
    });
};

const updateCriterion = (criterion) => {
    router.patch(
        route('admin.rubrics.criteria.update', { rubric: rubricId.value, criterion: criterion.id }),
        { name: criterion.name, weight: criterion.weight, order: criterion.order },
        { preserveScroll: true, onSuccess: refresh }
    );
};

const updateLevel = (level) => {
    router.patch(
        route('admin.rubrics.levels.update', { rubric: rubricId.value, level: level.id }),
        { level: level.level, label: level.label, score_value: level.score_value },
        { preserveScroll: true, onSuccess: refresh }
    );
};

const maxWeightScore = computed(() => {
    return (criteriaState.value || []).reduce((sum, c) => sum + Number(c.weight || 0), 0);
});
</script>

<template>
    <Head :title="`EDIT_RUBRIC | ${rubric?.title || ''}`" />

    <div class="min-h-screen bg-[#0f101a] p-4 md:p-8 font-['Press_Start_2P'] text-[#4ed4d4] text-[10px] leading-relaxed">
        <div class="max-w-7xl mx-auto space-y-8">
            <AdminNavbar />

            <div class="rpg-panel bg-black/40 border-slate-700 shadow-none">
                <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                    <div class="min-w-0">
                            <h1 class="text-white text-sm uppercase tracking-widest">Rubric Editor</h1>
                            <p class="text-[8px] text-slate-500 uppercase mt-2">
                                Max_Score: <span class="text-emerald-300">{{ maxWeightScore.toFixed(2) }}</span>
                                <span class="text-slate-600">|</span>
                                Levels: <span class="text-cyan-300">{{ levelsState?.length || 0 }}</span>
                                <span class="text-slate-600">|</span>
                                Criteria: <span class="text-indigo-300">{{ criteriaState?.length || 0 }}</span>
                            </p>
                        </div>

                    <div class="flex gap-2">
                        <Link
                            :href="route('admin.rubrics.show', rubricId)"
                            class="btn-pixel bg-indigo-300 text-black px-4 py-2 border-indigo-700 uppercase font-bold hover:bg-indigo-200 transition-colors text-[8px]"
                        >
                            Preview
                        </Link>
                        <button
                            type="button"
                            class="btn-pixel bg-emerald-300 text-black px-4 py-2 border-emerald-700 uppercase font-bold hover:bg-emerald-200 transition-colors text-[8px]"
                            :disabled="metaForm.processing"
                            @click="saveRubric"
                        >
                            Save
                        </button>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-12 gap-6">
                <div class="col-span-12 lg:col-span-4 space-y-6">
                    <div class="rpg-panel bg-[#1a1c2c]/80 border-slate-700">
                        <h2 class="text-white text-[11px] uppercase tracking-widest mb-4">Rubric Meta</h2>

                        <div class="space-y-4">
                            <div>
                                <label class="text-[8px] text-slate-500 uppercase">Title</label>
                                <input
                                    v-model="metaForm.title"
                                    type="text"
                                    class="mt-2 w-full bg-black/30 border-2 border-slate-700 px-3 py-2 text-slate-200 text-[9px] focus:outline-none focus:border-cyan-400"
                                />
                                <div v-if="metaForm.errors.title" class="text-red-400 text-[8px] mt-2">
                                    {{ metaForm.errors.title }}
                                </div>
                            </div>
                            <div>
                                <label class="text-[8px] text-slate-500 uppercase">Description</label>
                                <textarea
                                    v-model="metaForm.description"
                                    class="mt-2 w-full min-h-[120px] bg-black/30 border-2 border-slate-700 px-3 py-2 text-slate-200 text-[9px] focus:outline-none focus:border-cyan-400"
                                />
                                <div v-if="metaForm.errors.description" class="text-red-400 text-[8px] mt-2">
                                    {{ metaForm.errors.description }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rpg-panel bg-[#1a1c2c]/80 border-slate-700">
                        <h2 class="text-white text-[11px] uppercase tracking-widest mb-4">Criteria</h2>

                        <div class="space-y-3">
                            <div
                                v-for="c in criteriaState"
                                :key="c.id"
                                class="border-2 border-slate-700 bg-black/20 p-3"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <div class="flex-1 space-y-2">
                                        <input
                                            v-model="c.name"
                                            type="text"
                                            class="w-full bg-black/30 border border-slate-700 px-2 py-1 text-[9px] text-slate-200 focus:outline-none focus:border-cyan-400"
                                            placeholder="Criterion name"
                                        />
                                        <div class="grid grid-cols-2 gap-2">
                                            <input
                                                v-model="c.weight"
                                                type="number"
                                                step="0.01"
                                                class="w-full bg-black/30 border border-slate-700 px-2 py-1 text-[9px] text-slate-200 focus:outline-none focus:border-cyan-400"
                                                placeholder="Weight"
                                            />
                                            <input
                                                v-model="c.order"
                                                type="number"
                                                class="w-full bg-black/30 border border-slate-700 px-2 py-1 text-[9px] text-slate-200 focus:outline-none focus:border-cyan-400"
                                                placeholder="Order"
                                            />
                                        </div>
                                    </div>

                                    <div class="flex flex-col gap-2">
                                        <button
                                            type="button"
                                            class="btn-pixel bg-emerald-300 text-black px-3 py-2 border-emerald-700 uppercase font-bold hover:bg-emerald-200 transition-colors text-[8px]"
                                            @click="updateCriterion(c)"
                                        >
                                            Save
                                        </button>
                                        <button
                                            type="button"
                                            class="btn-pixel bg-red-900/80 text-white px-3 py-2 border-red-950 uppercase font-bold hover:bg-red-700 transition-colors text-[8px]"
                                            @click="deleteCriterion(c)"
                                        >
                                            Del
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="border-2 border-dashed border-slate-700 p-3">
                                <div class="text-[8px] text-slate-500 uppercase mb-2">Add Criterion</div>
                                <div class="space-y-2">
                                    <input
                                        v-model="newCriterionForm.name"
                                        type="text"
                                        class="w-full bg-black/30 border border-slate-700 px-2 py-1 text-[9px] text-slate-200 focus:outline-none focus:border-cyan-400"
                                        placeholder="Name"
                                    />
                                    <div class="grid grid-cols-2 gap-2">
                                        <input
                                            v-model="newCriterionForm.weight"
                                            type="number"
                                            step="0.01"
                                            class="w-full bg-black/30 border border-slate-700 px-2 py-1 text-[9px] text-slate-200 focus:outline-none focus:border-cyan-400"
                                            placeholder="Weight"
                                        />
                                        <input
                                            v-model="newCriterionForm.order"
                                            type="number"
                                            class="w-full bg-black/30 border border-slate-700 px-2 py-1 text-[9px] text-slate-200 focus:outline-none focus:border-cyan-400"
                                            placeholder="Order"
                                        />
                                    </div>
                                    <button
                                        type="button"
                                        class="btn-pixel bg-cyan-300 text-black px-4 py-2 border-cyan-700 uppercase font-bold hover:bg-cyan-200 transition-colors text-[8px] w-full"
                                        :disabled="newCriterionForm.processing"
                                        @click="addCriterion"
                                    >
                                        Add
                                    </button>
                                    <div v-if="newCriterionForm.errors.name" class="text-red-400 text-[8px]">
                                        {{ newCriterionForm.errors.name }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rpg-panel bg-[#1a1c2c]/80 border-slate-700">
                        <h2 class="text-white text-[11px] uppercase tracking-widest mb-4">Levels</h2>

                        <div class="space-y-3">
                            <div
                                v-for="l in levelsState"
                                :key="l.id"
                                class="border-2 border-slate-700 bg-black/20 p-3"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <div class="flex-1 space-y-2">
                                        <div class="grid grid-cols-2 gap-2">
                                            <input
                                                v-model="l.level"
                                                type="number"
                                                class="w-full bg-black/30 border border-slate-700 px-2 py-1 text-[9px] text-slate-200 focus:outline-none focus:border-cyan-400"
                                                placeholder="Level"
                                            />
                                            <input
                                                v-model="l.score_value"
                                                type="number"
                                                step="0.01"
                                                class="w-full bg-black/30 border border-slate-700 px-2 py-1 text-[9px] text-slate-200 focus:outline-none focus:border-cyan-400"
                                                placeholder="Score"
                                            />
                                        </div>
                                        <input
                                            v-model="l.label"
                                            type="text"
                                            class="w-full bg-black/30 border border-slate-700 px-2 py-1 text-[9px] text-slate-200 focus:outline-none focus:border-cyan-400"
                                            placeholder="Label"
                                        />
                                    </div>

                                    <div class="flex flex-col gap-2">
                                        <button
                                            type="button"
                                            class="btn-pixel bg-emerald-300 text-black px-3 py-2 border-emerald-700 uppercase font-bold hover:bg-emerald-200 transition-colors text-[8px]"
                                            @click="updateLevel(l)"
                                        >
                                            Save
                                        </button>
                                        <button
                                            type="button"
                                            class="btn-pixel bg-red-900/80 text-white px-3 py-2 border-red-950 uppercase font-bold hover:bg-red-700 transition-colors text-[8px]"
                                            @click="deleteLevel(l)"
                                        >
                                            Del
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="border-2 border-dashed border-slate-700 p-3">
                                <div class="text-[8px] text-slate-500 uppercase mb-2">Add Level</div>
                                <div class="space-y-2">
                                    <input
                                        v-model="newLevelForm.label"
                                        type="text"
                                        class="w-full bg-black/30 border border-slate-700 px-2 py-1 text-[9px] text-slate-200 focus:outline-none focus:border-cyan-400"
                                        placeholder="Label (e.g. Excellent)"
                                    />
                                    <div class="grid grid-cols-2 gap-2">
                                        <input
                                            v-model="newLevelForm.score_value"
                                            type="number"
                                            step="0.01"
                                            class="w-full bg-black/30 border border-slate-700 px-2 py-1 text-[9px] text-slate-200 focus:outline-none focus:border-cyan-400"
                                            placeholder="Score value"
                                        />
                                        <input
                                            :value="nextLevelNumber"
                                            type="number"
                                            disabled
                                            class="w-full bg-black/20 border border-slate-800 px-2 py-1 text-[9px] text-slate-500"
                                            title="Auto next level"
                                        />
                                    </div>
                                    <button
                                        type="button"
                                        class="btn-pixel bg-cyan-300 text-black px-4 py-2 border-cyan-700 uppercase font-bold hover:bg-cyan-200 transition-colors text-[8px] w-full"
                                        :disabled="newLevelForm.processing"
                                        @click="addLevel"
                                    >
                                        Add
                                    </button>
                                    <div v-if="newLevelForm.errors.label" class="text-red-400 text-[8px]">
                                        {{ newLevelForm.errors.label }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-span-12 lg:col-span-8 space-y-6">
                    <div class="rpg-panel bg-[#1a1c2c]/80 border-slate-700">
                        <div class="flex items-center justify-between gap-4 mb-4">
                            <h2 class="text-white text-[11px] uppercase tracking-widest">Rubric Matrix</h2>
                            <div class="text-[8px] text-slate-500 uppercase">
                                Editable cells. Save to persist.
                            </div>
                        </div>

                        <RubricMatrixEditor
                            v-model="matrixState"
                            :criteria="criteriaState"
                            :levels="levelsState"
                        />

                        <div v-if="metaForm.errors.descriptions" class="text-red-400 text-[8px] mt-3">
                            {{ metaForm.errors.descriptions }}
                        </div>
                    </div>

                    <div class="rpg-panel bg-black/40 border-slate-700 shadow-none">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                            <div class="text-[8px] text-slate-500 uppercase">
                                Tip: keep levels score_value increasing so max_level_score is correct.
                            </div>
                            <div class="flex gap-2">
                                <Link
                                    :href="route('admin.rubrics.index')"
                                    class="btn-pixel bg-slate-700 text-white px-4 py-2 border-slate-900 uppercase font-bold hover:bg-slate-600 transition-colors text-[8px]"
                                >
                                    Back
                                </Link>
                                <button
                                    type="button"
                                    class="btn-pixel bg-emerald-300 text-black px-4 py-2 border-emerald-700 uppercase font-bold hover:bg-emerald-200 transition-colors text-[8px]"
                                    :disabled="metaForm.processing"
                                    @click="saveRubric"
                                >
                                    Save All
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
