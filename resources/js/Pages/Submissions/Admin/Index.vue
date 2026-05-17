<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Swal from 'sweetalert2';
import AdminNavbar from '@/Components/AdminNavbar.vue';

const props = defineProps({
    submissions: Object,
    filters: Object,
});

const filterForm = useForm({
    search: props.filters?.search || '',
    status: props.filters?.status || 'all',
    duplicates: props.filters?.duplicates || '0',
    view: props.filters?.view || 'active',
});

const editTarget = ref(null);
const editForm = useForm({
    content: '',
    status: 'Pending',
    grade: 0,
    feedback: '',
    earned_exp: 0,
    earned_gold: 0,
});

const rows = computed(() => props.submissions?.data || []);
const paginationLinks = computed(() => props.submissions?.links || []);
const isTrashView = computed(() => filterForm.view === 'trash');

const applyFilters = () => {
    router.get(route('admin.submissions.manage.index'), filterForm.data(), {
        preserveState: true,
        preserveScroll: true,
    });
};

const resetFilters = () => {
    filterForm.search = '';
    filterForm.status = 'all';
    filterForm.duplicates = '0';
    applyFilters();
};

const setView = (view) => {
    if (filterForm.view === view) return;
    filterForm.view = view;
    applyFilters();
};

const openEdit = (row) => {
    editTarget.value = row;
    editForm.content = row.content || '';
    editForm.status = row.status || 'Pending';
    editForm.grade = row.grade ?? 0;
    editForm.feedback = row.feedback || '';
    editForm.earned_exp = row.earned_exp ?? 0;
    editForm.earned_gold = row.earned_gold ?? 0;
};

const closeEdit = () => {
    editTarget.value = null;
    editForm.reset();
};

const saveEdit = () => {
    if (!editTarget.value) return;

    editForm.patch(route('admin.submissions.manage.update', editTarget.value.uuid), {
        preserveScroll: true,
        onSuccess: () => {
            closeEdit();
            Swal.fire({
                icon: 'success',
                title: 'SUBMISSION_UPDATED',
                timer: 1400,
                showConfirmButton: false,
                background: '#1a1c2c',
                color: '#4ed4d4',
            });
        },
    });
};

const removeSubmission = (row) => {
    Swal.fire({
        title: 'ARCHIVE_SUBMISSION?',
        text: 'Data submission akan dipindah ke trash.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'YES_ARCHIVE',
        cancelButtonText: 'CANCEL',
        background: '#1a1c2c',
        color: '#4ed4d4',
        confirmButtonColor: '#b91c1c',
    }).then((result) => {
        if (!result.isConfirmed) return;

        router.delete(route('admin.submissions.manage.destroy', row.uuid), {
            preserveScroll: true,
        });
    });
};

const restoreSubmission = (row) => {
    router.patch(route('admin.submissions.manage.restore', row.uuid), {}, {
        preserveScroll: true,
    });
};

const hardDeleteSubmission = (row) => {
    Swal.fire({
        title: 'HARD_DELETE_SUBMISSION?',
        text: 'Submission akan dihapus permanen dan tidak bisa dipulihkan.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'DELETE_PERMANENTLY',
        cancelButtonText: 'CANCEL',
        background: '#1a1c2c',
        color: '#4ed4d4',
        confirmButtonColor: '#b91c1c',
    }).then((result) => {
        if (!result.isConfirmed) return;

        router.delete(route('admin.submissions.manage.force-destroy', row.uuid), {
            preserveScroll: true,
        });
    });
};

const statusClass = (status) => {
    if (status === 'Approved') return 'text-green-400 border-green-900 bg-green-900/20';
    if (status === 'Rejected') return 'text-red-400 border-red-900 bg-red-900/20';
    return 'text-yellow-400 border-yellow-900 bg-yellow-900/20';
};

const goToPage = (url) => {
    if (!url) return;

    router.get(url, {}, {
        preserveState: true,
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="SUBMISSION_MANAGEMENT" />

    <div class="min-h-screen bg-[#0d1117] p-4 md:p-8 font-['Press_Start_2P'] text-[#4ed4d4] text-[10px]">
        <div class="max-w-7xl mx-auto space-y-8">
            <AdminNavbar />

            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3 border-b-4 border-cyan-900 pb-4">
                <h1 class="text-base sm:text-xl uppercase tracking-widest">Submission_Management</h1>
                <Link href="/dashboard" class="inline-flex items-center justify-center px-3 py-2 border border-slate-600 bg-slate-900/40 text-slate-300 hover:text-white uppercase text-[9px] sm:text-[10px]">[Back_to_HQ]</Link>
            </div>

            <div class="rpg-panel border-slate-700">
                <div class="flex gap-2 mb-4">
                    <button
                        @click="setView('active')"
                        class="px-3 py-2 border-2 uppercase"
                        :class="isTrashView ? 'border-slate-600 text-slate-300 hover:bg-slate-600 hover:text-white' : 'border-cyan-400 text-cyan-400 bg-cyan-900/20'"
                    >
                        ACTIVE
                    </button>
                    <button
                        @click="setView('trash')"
                        class="px-3 py-2 border-2 uppercase"
                        :class="isTrashView ? 'border-amber-500 text-amber-300 bg-amber-900/20' : 'border-slate-600 text-slate-300 hover:bg-slate-600 hover:text-white'"
                    >
                        TRASH
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-5">
                    <input
                        v-model="filterForm.search"
                        type="text"
                        placeholder="SEARCH USER / QUEST / UUID"
                        class="md:col-span-2 bg-black border-2 border-slate-700 p-2 text-cyan-400 uppercase outline-none"
                    />
                    <select
                        v-model="filterForm.status"
                        class="bg-black border-2 border-slate-700 p-2 text-cyan-400 uppercase outline-none"
                    >
                        <option value="all">ALL_STATUS</option>
                        <option value="Pending">Pending</option>
                        <option value="Approved">Approved</option>
                        <option value="Rejected">Rejected</option>
                    </select>
                    <select
                        v-model="filterForm.duplicates"
                        class="bg-black border-2 border-slate-700 p-2 text-cyan-400 uppercase outline-none"
                    >
                        <option value="0">ALL_ROWS</option>
                        <option value="1">ONLY_DUPLICATES</option>
                    </select>
                </div>

                <div class="flex gap-2 mb-5">
                    <button
                        @click="applyFilters"
                        class="px-4 py-2 border-2 border-cyan-400 text-cyan-400 hover:bg-cyan-400 hover:text-black uppercase"
                    >
                        APPLY_FILTER
                    </button>
                    <button
                        @click="resetFilters"
                        class="px-4 py-2 border-2 border-slate-600 text-slate-300 hover:bg-slate-600 hover:text-white uppercase"
                    >
                        RESET
                    </button>
                </div>

                <div class="overflow-x-auto custom-scroll">
                    <table class="w-full min-w-[1200px] text-left">
                        <thead class="border-b border-slate-700 text-[8px] uppercase text-slate-500">
                            <tr>
                                <th class="py-3 px-2">UUID</th>
                                <th class="py-3 px-2">User</th>
                                <th class="py-3 px-2">Quest</th>
                                <th class="py-3 px-2">Status</th>
                                <th class="py-3 px-2">Grade</th>
                                <th class="py-3 px-2">Reward</th>
                                <th class="py-3 px-2">Dup</th>
                                <th class="py-3 px-2">{{ isTrashView ? 'Deleted' : 'Date' }}</th>
                                <th class="py-3 px-2 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in rows" :key="row.uuid" class="border-b border-slate-800 hover:bg-slate-900/40">
                                <td class="py-3 px-2 text-slate-400">{{ row.uuid.slice(0, 8) }}...</td>
                                <td class="py-3 px-2">
                                    <p class="text-white uppercase">{{ row.user?.username || row.user?.name }}</p>
                                    <p class="text-[8px] text-slate-500">{{ row.user?.email }}</p>
                                </td>
                                <td class="py-3 px-2">
                                    <p class="text-slate-200 uppercase">{{ row.quest?.title }}</p>
                                    <p class="text-[8px] text-slate-500">{{ row.quest?.difficulty }}</p>
                                </td>
                                <td class="py-3 px-2">
                                    <span class="px-2 py-1 border text-[8px]" :class="statusClass(row.status)">
                                        {{ row.status }}
                                    </span>
                                </td>
                                <td class="py-3 px-2 text-slate-200">{{ row.grade || 0 }}%</td>
                                <td class="py-3 px-2 text-[8px]">
                                    <p class="text-cyan-400">EXP {{ row.earned_exp || 0 }}</p>
                                    <p class="text-yellow-500">GOLD {{ row.earned_gold || 0 }}</p>
                                </td>
                                <td class="py-3 px-2">
                                    <span
                                        class="px-2 py-1 border text-[8px]"
                                        :class="(row.duplicate_count || 0) > 1 ? 'text-red-400 border-red-800 bg-red-900/20' : 'text-slate-400 border-slate-700'"
                                    >
                                        {{ row.duplicate_count || 1 }}
                                    </span>
                                </td>
                                <td class="py-3 px-2 text-slate-500 text-[8px]">
                                    {{ new Date(isTrashView ? row.deleted_at : row.created_at).toLocaleString('id-ID') }}
                                </td>
                                <td class="py-3 px-2 text-right space-x-2">
                                    <Link
                                        :href="route('admin.submissions.inspect', { submission: row.uuid })"
                                        class="px-2 py-1 border border-blue-700 text-blue-300 hover:bg-blue-600 hover:text-white uppercase text-[8px]"
                                    >
                                        Inspect
                                    </Link>
                                    <button
                                        v-if="!isTrashView"
                                        @click="openEdit(row)"
                                        class="px-2 py-1 border border-emerald-700 text-emerald-400 hover:bg-emerald-500 hover:text-black uppercase text-[8px]"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        v-if="!isTrashView"
                                        @click="removeSubmission(row)"
                                        class="px-2 py-1 border border-red-700 text-red-400 hover:bg-red-600 hover:text-white uppercase text-[8px]"
                                    >
                                        Delete
                                    </button>
                                    <button
                                        v-if="isTrashView"
                                        @click="restoreSubmission(row)"
                                        class="px-2 py-1 border border-emerald-700 text-emerald-400 hover:bg-emerald-500 hover:text-black uppercase text-[8px]"
                                    >
                                        Restore
                                    </button>
                                    <button
                                        v-if="isTrashView"
                                        @click="hardDeleteSubmission(row)"
                                        class="px-2 py-1 border border-red-700 text-red-400 hover:bg-red-600 hover:text-white uppercase text-[8px]"
                                    >
                                        Hard_Delete
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="rows.length === 0">
                                <td colspan="9" class="py-8 px-2 text-center text-slate-500 uppercase">
                                    NO_SUBMISSION_FOUND
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-5 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                    <p class="text-[8px] text-slate-500 uppercase">
                        PAGE {{ submissions.current_page || 1 }} / {{ submissions.last_page || 1 }}
                        | TOTAL {{ submissions.total || 0 }}
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

        <div v-if="editTarget" class="fixed inset-0 z-[95] bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="w-full max-w-3xl rpg-panel border-emerald-500/40">
                <h2 class="text-white uppercase mb-5">
                    Edit_Submission {{ editTarget.uuid.slice(0, 8) }}...
                </h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-slate-500 mb-2 uppercase">Status</label>
                        <select v-model="editForm.status" class="w-full bg-black border-2 border-slate-700 p-2 text-cyan-400 uppercase outline-none">
                            <option value="Pending">Pending</option>
                            <option value="Approved">Approved</option>
                            <option value="Rejected">Rejected</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-slate-500 mb-2 uppercase">Grade (0-100)</label>
                        <input v-model.number="editForm.grade" type="number" min="0" max="100"
                            class="w-full bg-black border-2 border-slate-700 p-2 text-cyan-400 uppercase outline-none">
                    </div>
                    <div>
                        <label class="block text-slate-500 mb-2 uppercase">Earned Exp</label>
                        <input v-model.number="editForm.earned_exp" type="number" min="0"
                            class="w-full bg-black border-2 border-slate-700 p-2 text-cyan-400 uppercase outline-none">
                    </div>
                    <div>
                        <label class="block text-slate-500 mb-2 uppercase">Earned Gold</label>
                        <input v-model.number="editForm.earned_gold" type="number" min="0"
                            class="w-full bg-black border-2 border-slate-700 p-2 text-cyan-400 uppercase outline-none">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-slate-500 mb-2 uppercase">Content</label>
                    <textarea v-model="editForm.content" rows="5"
                        class="w-full bg-black border-2 border-slate-700 p-2 text-slate-200 font-sans outline-none"></textarea>
                </div>

                <div class="mb-4">
                    <label class="block text-slate-500 mb-2 uppercase">Feedback</label>
                    <textarea v-model="editForm.feedback" rows="3"
                        class="w-full bg-black border-2 border-slate-700 p-2 text-slate-200 font-sans outline-none"></textarea>
                </div>

                <p v-if="editForm.hasErrors" class="text-red-400 text-[8px] mb-4 uppercase">VALIDATION_ERROR_CHECK_INPUT</p>

                <div class="flex gap-2">
                    <button @click="saveEdit" :disabled="editForm.processing"
                        class="px-4 py-2 border-2 border-emerald-500 text-emerald-400 hover:bg-emerald-500 hover:text-black uppercase">
                        {{ editForm.processing ? 'SAVING...' : 'SAVE' }}
                    </button>
                    <button @click="closeEdit"
                        class="px-4 py-2 border-2 border-slate-600 text-slate-300 hover:bg-slate-600 hover:text-white uppercase">
                        CLOSE
                    </button>
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
