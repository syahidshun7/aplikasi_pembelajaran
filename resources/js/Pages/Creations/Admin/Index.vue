<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminNavbar from '@/Components/AdminNavbar.vue';

const props = defineProps({
    creations: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    isAdmin: {
        type: Boolean,
        default: false,
    },
    isMentor: {
        type: Boolean,
        default: false,
    },
});

const filterForm = useForm({
    search: props.filters?.search || '',
    review_status: props.filters?.review_status || 'pending',
    scope: props.filters?.scope || 'all',
});

const rows = computed(() => props.creations?.data || []);
const paginationLinks = computed(() => props.creations?.links || []);

const applyFilters = () => {
    router.get(route('admin.creations.queue'), filterForm.data(), {
        preserveScroll: true,
        preserveState: true,
    });
};

const resetFilters = () => {
    filterForm.search = '';
    filterForm.review_status = 'pending';
    filterForm.scope = 'all';
    applyFilters();
};

const goToPage = (url) => {
    if (!url) {
        return;
    }

    router.get(url, {}, {
        preserveScroll: true,
        preserveState: true,
    });
};

const statusClass = (status) => {
    const normalized = String(status || 'none').toLowerCase();
    if (normalized === 'approved') return 'text-emerald-300 border-emerald-700 bg-emerald-900/20';
    if (normalized === 'needs_revision') return 'text-amber-300 border-amber-700 bg-amber-900/20';
    if (normalized === 'pending') return 'text-cyan-300 border-cyan-700 bg-cyan-900/20';
    return 'text-slate-300 border-slate-700 bg-slate-900/40';
};

const formatDateTime = (value) => {
    const date = new Date(String(value || ''));
    if (Number.isNaN(date.getTime())) {
        return '-';
    }

    return date.toLocaleString('id-ID');
};
</script>

<template>
    <Head title="Creation Review Queue" />

    <div class="min-h-screen bg-[#0d1117] p-4 md:p-8 font-['Press_Start_2P'] text-[#4ed4d4] text-[10px]">
        <div class="max-w-7xl mx-auto space-y-8">
            <AdminNavbar />

            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 border-b-4 border-cyan-900 pb-4">
                <h1 class="text-base sm:text-xl uppercase tracking-widest text-white">Creation_Review_Queue</h1>
                <Link :href="route('dashboard')" class="inline-flex items-center justify-center px-3 py-2 border border-slate-600 bg-slate-900/40 text-slate-300 hover:text-white uppercase text-[9px] sm:text-[10px]">
                    [Back_to_HQ]
                </Link>
            </div>

            <div class="rpg-panel border-slate-700">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-4">
                    <input
                        v-model="filterForm.search"
                        type="text"
                        placeholder="SEARCH TITLE / CREATOR / CATEGORY"
                        class="md:col-span-2 bg-black border-2 border-slate-700 p-2 text-cyan-400 uppercase outline-none"
                    >
                    <select v-model="filterForm.review_status" class="bg-black border-2 border-slate-700 p-2 text-cyan-400 uppercase outline-none">
                        <option value="pending">PENDING</option>
                        <option value="needs_revision">NEEDS_REVISION</option>
                        <option value="approved">APPROVED</option>
                        <option value="none">NONE</option>
                        <option value="all">ALL_STATUS</option>
                    </select>
                    <select v-model="filterForm.scope" class="bg-black border-2 border-slate-700 p-2 text-cyan-400 uppercase outline-none">
                        <option value="all">ALL_SCOPE</option>
                        <option value="assigned">ASSIGNED_ONLY</option>
                        <option v-if="isMentor" value="job">SAME_JOB_ONLY</option>
                    </select>
                </div>

                <div class="flex gap-2 mb-5">
                    <button @click="applyFilters" class="px-4 py-2 border-2 border-cyan-400 text-cyan-400 hover:bg-cyan-400 hover:text-black uppercase">
                        APPLY_FILTER
                    </button>
                    <button @click="resetFilters" class="px-4 py-2 border-2 border-slate-600 text-slate-300 hover:bg-slate-600 hover:text-white uppercase">
                        RESET
                    </button>
                </div>

                <div class="overflow-x-auto custom-scroll">
                    <table class="w-full min-w-[1180px] text-left">
                        <thead class="border-b border-slate-700 text-[8px] uppercase text-slate-500">
                            <tr>
                                <th class="py-3 px-2">Creation</th>
                                <th class="py-3 px-2">Creator</th>
                                <th class="py-3 px-2">Review Status</th>
                                <th class="py-3 px-2">Assigned Reviewer</th>
                                <th class="py-3 px-2">Assigned Rubric</th>
                                <th class="py-3 px-2">Final Result</th>
                                <th class="py-3 px-2">Updated</th>
                                <th class="py-3 px-2 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in rows" :key="row.id" class="border-b border-slate-800 hover:bg-slate-900/40">
                                <td class="py-3 px-2">
                                    <p class="text-white uppercase">{{ row.title }}</p>
                                    <p class="text-[8px] text-slate-500">{{ row.category || '-' }} | {{ row.status }} | {{ row.progress || 0 }}%</p>
                                </td>
                                <td class="py-3 px-2">
                                    <p class="text-slate-200 uppercase">{{ row.creator?.username || row.creator?.name || '-' }}</p>
                                    <p class="text-[8px] text-slate-500">JOB {{ row.creator?.job_id ?? '-' }}</p>
                                </td>
                                <td class="py-3 px-2">
                                    <span class="px-2 py-1 border text-[8px] uppercase" :class="statusClass(row.review_status)">
                                        {{ row.review_status }}
                                    </span>
                                    <p class="text-[8px] text-slate-500 mt-1">OPEN: {{ row.is_open_for_review ? 'YES' : 'NO' }}</p>
                                    <p class="text-[8px] text-slate-500 mt-1">REVIEWS: {{ row.peer_reviews_count || 0 }}</p>
                                </td>
                                <td class="py-3 px-2 text-slate-300">
                                    {{ row.assigned_reviewer?.username || row.assigned_reviewer?.name || '-' }}
                                </td>
                                <td class="py-3 px-2 text-slate-300">
                                    {{ row.assigned_rubric?.title || '-' }}
                                </td>
                                <td class="py-3 px-2">
                                    <template v-if="row.final_review">
                                        <p class="text-cyan-300 text-[8px] uppercase">{{ row.final_review.score_percent }}%</p>
                                        <p class="text-[8px] text-slate-500 uppercase">{{ row.final_review.status }}</p>
                                    </template>
                                    <span v-else class="text-slate-500 text-[8px] uppercase">NO_FINAL_RESULT</span>
                                </td>
                                <td class="py-3 px-2 text-slate-500 text-[8px]">
                                    {{ formatDateTime(row.updated_at) }}
                                </td>
                                <td class="py-3 px-2 text-right">
                                    <Link
                                        :href="route('admin.creations.preview', { creation: row.id })"
                                        class="px-2 py-1 border border-cyan-600 text-cyan-300 hover:bg-cyan-500 hover:text-black uppercase text-[8px]"
                                    >
                                        Mentor Review
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="rows.length === 0">
                                <td colspan="8" class="py-8 px-2 text-center text-slate-500 uppercase">
                                    NO_CREATION_REVIEW_QUEUE
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-5 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                    <p class="text-[8px] text-slate-500 uppercase">
                        PAGE {{ creations.current_page || 1 }} / {{ creations.last_page || 1 }}
                        | TOTAL {{ creations.total || 0 }}
                    </p>

                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="(link, idx) in paginationLinks"
                            :key="`${idx}-${link.label}`"
                            @click="goToPage(link.url)"
                            :disabled="!link.url"
                            class="px-3 py-1 border text-[8px] uppercase transition-all"
                            :class="[
                                link.active
                                    ? 'border-cyan-400 text-cyan-400 bg-cyan-900/20'
                                    : 'border-slate-700 text-slate-300 hover:bg-slate-700 hover:text-white',
                                !link.url ? 'opacity-40 cursor-not-allowed' : ''
                            ]"
                            v-html="link.label"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.rpg-panel {
    background-color: #1a1c2c;
    border-width: 4px;
    padding: 1rem;
    box-shadow: 8px 8px 0 0 rgba(0, 0, 0, 0.5);
}
</style>
