<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminNavbar from '@/Components/AdminNavbar.vue';

const props = defineProps({
    rubrics: Object,
    filters: Object,
});

const items = computed(() => props.rubrics?.data || []);
const links = computed(() => props.rubrics?.links || []);

const filterForm = useForm({
    search: props.filters?.search || '',
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

