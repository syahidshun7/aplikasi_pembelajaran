<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminNavbar from '@/Components/AdminNavbar.vue';

const props = defineProps({
    rubrics: Object,
    filters: Object,
    importResult: Object,
    importTemplate: Object,
});

const items = computed(() => props.rubrics?.data || []);
const links = computed(() => props.rubrics?.links || []);
const importTemplateJson = computed(() => JSON.stringify(props.importTemplate?.sample || {}, null, 2));
const importFileInputRef = ref(null);

const filterForm = useForm({
    search: props.filters?.search || '',
});
const importForm = useForm({
    import_file: null,
    import_json_text: '',
});

const applyFilters = () => {
    router.get(route('admin.rubrics.index'), filterForm.data(), {
        preserveState: true,
        preserveScroll: true,
    });
};

const resetFilters = () => {
    filterForm.search = '';
    applyFilters();
};

const goToPage = (url) => {
    if (!url) return;
    router.get(url, {}, { preserveState: true, preserveScroll: true });
};

const destroyRubric = (rubric) => {
    if (!rubric?.id) return;
    if (!confirm('DELETE_RUBRIC?')) return;

    router.delete(route('admin.rubrics.destroy', rubric.id), {
        preserveScroll: true,
    });
};

const handleImportFileChange = (event) => {
    importForm.import_file = event.target.files?.[0] || null;
};

const submitImport = () => {
    importForm.post(route('admin.rubrics.import-json'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            importForm.reset();
            importForm.import_file = null;
            importForm.import_json_text = '';
            if (importFileInputRef.value) {
                importFileInputRef.value.value = '';
            }
        },
    });
};
</script>

<template>
    <Head title="RUBRICS | ADMIN_CONSOLE" />

    <div class="min-h-screen bg-[#0f101a] p-4 md:p-8 font-['Press_Start_2P'] text-[#4ed4d4] text-[10px] leading-relaxed">
        <div class="max-w-7xl mx-auto space-y-8">
            <AdminNavbar />

            <div class="rpg-panel bg-black/40 border-slate-700 shadow-none">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div class="min-w-0">
                        <h1 class="text-white text-sm uppercase tracking-widest">Rubric Management</h1>
                        <p class="text-[8px] text-slate-500 uppercase mt-2">
                            Build grading matrices for future assignment scoring and AI grading context.
                        </p>
                    </div>

                    <Link
                        :href="route('admin.rubrics.create')"
                        class="btn-pixel bg-emerald-300 text-black px-4 py-2 border-emerald-700 uppercase font-bold hover:bg-emerald-200 transition-colors text-[8px] inline-flex items-center justify-center"
                    >
                        Create Rubric
                    </Link>
                </div>
            </div>

            <div class="rpg-panel bg-black/40 border-slate-700 shadow-none">
                <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
                    <div class="flex-1">
                        <label class="text-[8px] text-slate-500 uppercase">Search</label>
                        <input
                            v-model="filterForm.search"
                            type="text"
                            class="mt-2 w-full bg-black/30 border-2 border-slate-700 px-3 py-2 text-slate-200 text-[9px] focus:outline-none focus:border-cyan-400"
                            placeholder="Title or description..."
                            @keyup.enter="applyFilters"
                        />
                    </div>

                    <div class="flex gap-2">
                        <button
                            type="button"
                            class="btn-pixel bg-cyan-300 text-black px-4 py-2 border-cyan-700 uppercase font-bold hover:bg-cyan-200 transition-colors text-[8px]"
                            @click="applyFilters"
                        >
                            Apply
                        </button>
                        <button
                            type="button"
                            class="btn-pixel bg-slate-700 text-white px-4 py-2 border-slate-900 uppercase font-bold hover:bg-slate-600 transition-colors text-[8px]"
                            @click="resetFilters"
                        >
                            Reset
                        </button>
                    </div>
                </div>
            </div>

            <div class="rpg-panel bg-black/40 border-cyan-700/60 shadow-none">
                <h2 class="text-white text-[11px] uppercase tracking-widest mb-4">Import Rubric JSON</h2>
                <form class="space-y-4" @submit.prevent="submitImport">
                    <div>
                        <label class="text-[8px] text-slate-500 uppercase">JSON File</label>
                        <input
                            ref="importFileInputRef"
                            type="file"
                            accept=".json,application/json,text/plain"
                            class="mt-2 w-full bg-black/30 border-2 border-slate-700 p-2 text-[10px] text-slate-300 file:mr-3 file:border-0 file:bg-cyan-900/40 file:px-3 file:py-2 file:text-[8px] file:uppercase file:text-cyan-200"
                            @change="handleImportFileChange"
                        />
                        <div v-if="importForm.errors.import_file" class="text-red-400 text-[8px] mt-2">{{ importForm.errors.import_file }}</div>
                    </div>

                    <div class="border border-slate-700 bg-black/20 p-3">
                        <label class="text-[8px] text-slate-500 uppercase">JSON Input (Paste)</label>
                        <textarea
                            v-model="importForm.import_json_text"
                            class="mt-2 w-full min-h-[150px] bg-black/30 border-2 border-slate-700 px-3 py-2 text-slate-200 text-[10px] font-sans focus:outline-none focus:border-cyan-400"
                            placeholder='Paste JSON rubric di sini.'
                        />
                        <div v-if="importForm.errors.import_json_text" class="text-red-400 text-[8px] mt-2">{{ importForm.errors.import_json_text }}</div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button
                            type="submit"
                            class="btn-pixel bg-cyan-300 text-black px-4 py-2 border-cyan-700 uppercase font-bold hover:bg-cyan-200 transition-colors text-[8px]"
                            :disabled="importForm.processing"
                        >
                            {{ importForm.processing ? 'Importing...' : 'Import JSON' }}
                        </button>
                        <a
                            :href="importTemplate?.download_url"
                            target="_blank"
                            class="btn-pixel bg-yellow-300 text-black px-4 py-2 border-yellow-700 uppercase font-bold hover:bg-yellow-200 transition-colors text-[8px]"
                        >
                            Template
                        </a>
                    </div>
                </form>

                <div v-if="importResult" class="mt-4 border border-emerald-700/40 bg-emerald-900/10 p-3">
                    <div class="text-[8px] text-emerald-300 uppercase">Import Summary</div>
                    <div class="mt-2 text-[8px] text-slate-300 uppercase">
                        Title: <span class="text-white">{{ importResult.title || '-' }}</span>
                    </div>
                    <div class="mt-1 text-[8px] text-slate-300 uppercase">
                        Criteria: <span class="text-cyan-300">{{ importResult.criteria_count || 0 }}</span>
                        <span class="text-slate-600">|</span>
                        Levels: <span class="text-cyan-300">{{ importResult.levels_count || 0 }}</span>
                        <span class="text-slate-600">|</span>
                        Matrix: <span class="text-cyan-300">{{ importResult.matrix_count || 0 }}</span>
                    </div>
                </div>

                <div class="mt-4 border border-slate-700 bg-black/20 p-3">
                    <div class="text-[8px] text-white uppercase">Format Reference</div>
                    <div class="mt-3 space-y-2">
                        <div
                            v-for="field in (importTemplate?.fields || [])"
                            :key="field.name"
                            class="border border-slate-800 px-3 py-2"
                        >
                            <div class="text-[8px] uppercase text-yellow-300">
                                {{ field.name }} <span class="text-slate-500">[{{ field.required ? 'required' : 'optional' }}]</span>
                            </div>
                            <div class="mt-1 text-[10px] font-sans text-slate-300">{{ field.description }}</div>
                        </div>
                    </div>
                    <pre class="mt-4 overflow-x-auto border border-slate-800 bg-[#0b0f14] p-3 text-[10px] font-sans text-slate-300">{{ importTemplateJson }}</pre>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div
                    v-for="rubric in items"
                    :key="rubric.id"
                    class="rpg-panel bg-[#1a1c2c]/80 border-slate-700 hover:border-cyan-400/60 transition-colors"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <div class="text-white uppercase text-[11px] leading-snug truncate">
                                {{ rubric.title }}
                            </div>
                            <div class="text-[8px] text-slate-500 uppercase mt-2">
                                Mentor: <span class="text-cyan-300">{{ rubric.mentor?.name || '-' }}</span>
                            </div>
                            <div class="text-[8px] text-slate-500 uppercase mt-1">
                                Max_Score: <span class="text-emerald-300">{{ rubric.max_score ?? 0 }}</span>
                            </div>
                        </div>

                        <div class="flex gap-2 flex-shrink-0">
                            <Link
                                :href="route('admin.rubrics.show', rubric.id)"
                                class="btn-pixel bg-indigo-300 text-black px-3 py-2 border-indigo-700 uppercase font-bold hover:bg-indigo-200 transition-colors text-[8px]"
                            >
                                View
                            </Link>
                            <Link
                                :href="route('admin.rubrics.edit', rubric.id)"
                                class="btn-pixel bg-yellow-300 text-black px-3 py-2 border-yellow-700 uppercase font-bold hover:bg-yellow-200 transition-colors text-[8px]"
                            >
                                Edit
                            </Link>
                            <button
                                type="button"
                                class="btn-pixel bg-red-900/80 text-white px-3 py-2 border-red-950 uppercase font-bold hover:bg-red-700 transition-colors text-[8px]"
                                @click="destroyRubric(rubric)"
                            >
                                Del
                            </button>
                        </div>
                    </div>

                    <div class="mt-4 text-[9px] text-slate-300 leading-relaxed line-clamp-3">
                        {{ rubric.description || '...' }}
                    </div>
                </div>
            </div>

            <div v-if="links.length" class="rpg-panel bg-black/40 border-slate-700 shadow-none">
                <div class="flex flex-wrap gap-2 justify-center">
                    <button
                        v-for="l in links"
                        :key="l.label"
                        type="button"
                        class="px-3 py-2 border-2 border-slate-700 text-[8px] uppercase"
                        :class="[
                            l.active ? 'bg-cyan-300 text-black border-cyan-700' : 'bg-black/30 text-slate-300 hover:border-cyan-400',
                            !l.url ? 'opacity-40 cursor-not-allowed' : ''
                        ]"
                        :disabled="!l.url"
                        v-html="l.label"
                        @click="goToPage(l.url)"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
