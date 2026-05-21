<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminNavbar from '@/Components/AdminNavbar.vue';
import Swal from 'sweetalert2';

const props = defineProps({
    jobs: Object,
    filters: Object,
});

const isEditing = ref(false);
const editId = ref(null);
const showFormModal = ref(false);
const showDeleteModal = ref(false);
const deleteId = ref(null);

const form = useForm({
    name: '',
    status: 'active',
    description: '',
    emblem: null,
    _method: 'POST',
});

const filterForm = useForm({
    search: props.filters?.search || '',
});

const jobItems = computed(() => props.jobs?.data || []);
const paginationLinks = computed(() => props.jobs?.links || []);

const startEdit = (job) => {
    isEditing.value = true;
    editId.value = job.id;
    form.name = job.name;
    form.status = job.status || 'active';
    form.description = job.description || '';
    form.emblem = null;
    form._method = 'PUT';
    showFormModal.value = true;
};

const cancelEdit = () => {
    isEditing.value = false;
    editId.value = null;
    form.reset();
    form.status = 'active';
    form._method = 'POST';
    showFormModal.value = false;
};

const openCreateModal = () => {
    isEditing.value = false;
    editId.value = null;
    form.reset();
    form.status = 'active';
    form._method = 'POST';
    showFormModal.value = true;
};

const submit = () => {
    if (isEditing.value) {
        form.post(route('admin.jobs.update', editId.value), {
            onSuccess: () => {
                cancelEdit();
            },
            onError: (errors) => {
                Swal.fire({
                    icon: 'error',
                    title: 'UPDATE_FAILED',
                    text: Object.values(errors)[0] || 'UNKNOWN_ERROR',
                    background: '#1a1c2c',
                    color: '#ff4d4d',
                });
            },
        });
        return;
    }

    form.post(route('admin.jobs.store'), {
        onSuccess: () => {
            cancelEdit();
        },
        onError: (errors) => {
            Swal.fire({
                icon: 'warning',
                title: 'CREATE_FAILED',
                text: Object.values(errors)[0] || 'UNKNOWN_ERROR',
                background: '#1a1c2c',
                color: '#facc15',
            });
        },
    });
};

const onEmblemChange = (event) => {
    form.emblem = event.target.files[0] || null;
};

const confirmDelete = (id) => {
    deleteId.value = id;
    showDeleteModal.value = true;
};

const executeDelete = () => {
    if (!deleteId.value) return;
    form.delete(route('admin.jobs.destroy', deleteId.value), {
        onSuccess: () => {
            showDeleteModal.value = false;
            deleteId.value = null;
        },
    });
};

const applyFilters = () => {
    router.get(route('admin.jobs.index'), filterForm.data(), {
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
    router.get(url, {}, {
        preserveState: true,
        preserveScroll: true,
    });
};

const statusLabel = (status) => {
    if (status === 'coming_soon') return 'COMING_SOON';
    if (status === 'draft') return 'DRAFT';
    return 'ACTIVE';
};

const statusClass = (status) => {
    if (status === 'coming_soon') return 'border-yellow-500 text-yellow-300 bg-yellow-500/10';
    if (status === 'draft') return 'border-slate-500 text-slate-300 bg-slate-500/10';
    return 'border-emerald-500 text-emerald-300 bg-emerald-500/10';
};
</script>

<template>
    <Head title="JOBS_REGISTRY" />

    <div class="min-h-screen bg-[#0d1117] p-4 md:p-8 font-['Press_Start_2P'] text-[#4ed4d4] text-[10px] relative">
        <div class="max-w-7xl mx-auto space-y-8">
            <AdminNavbar />

            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3 border-b-4 border-cyan-900 pb-4">
                <h1 class="text-base sm:text-xl uppercase tracking-widest animate-pulse">Jobs_Registry_System</h1>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="openCreateModal"
                        class="inline-flex items-center justify-center px-3 py-2 border border-cyan-500 bg-cyan-900/20 text-cyan-300 hover:bg-cyan-500 hover:text-black transition-colors uppercase text-[9px] sm:text-[10px]"
                    >
                        [New_Job]
                    </button>
                    <Link href="/dashboard" class="inline-flex items-center justify-center px-3 py-2 border border-slate-600 bg-slate-900/40 text-slate-300 hover:text-white transition-colors uppercase text-[9px] sm:text-[10px]">[Back_to_HQ]</Link>
                </div>
            </div>

            <div class="grid grid-cols-12 gap-8">
                <div
                    v-if="showFormModal"
                    class="fixed inset-0 z-[120] flex items-center justify-center bg-black/85 backdrop-blur-sm p-4"
                >
                    <div class="w-full max-w-4xl max-h-[90vh] overflow-y-auto modal-scroll">
                        <div class="rpg-panel" :class="isEditing ? 'border-green-500/50' : 'border-cyan-500/50'">
                        <h2 class="mb-6 uppercase tracking-tighter" :class="isEditing ? 'text-green-500' : 'text-cyan-400'">
                            >> {{ isEditing ? 'UPDATE_JOB_ID_' + editId : 'ISSUE_NEW_JOB' }}
                        </h2>

                        <form @submit.prevent="submit" class="space-y-6">
                            <div>
                                <label class="block mb-2 text-white uppercase">JOB_NAME:</label>
                                <input
                                    v-model="form.name"
                                    type="text"
                                    class="w-full bg-black border-2 border-slate-700 p-2 focus:border-cyan-400 outline-none text-cyan-400 uppercase"
                                    placeholder="Enter job name..."
                                    required
                                >
                            </div>

                            <div>
                                <label class="block mb-2 text-white uppercase">DESCRIPTION:</label>
                                <textarea
                                    v-model="form.description"
                                    class="w-full bg-black border-2 border-slate-700 p-2 text-[12px] font-sans text-slate-200 focus:border-cyan-400 focus:ring-0"
                                    style="resize: vertical; min-height: 120px;"
                                ></textarea>
                            </div>

                            <div>
                                <label class="block mb-2 text-white uppercase">JOB_STATUS:</label>
                                <select
                                    v-model="form.status"
                                    class="w-full bg-black border-2 border-slate-700 p-2 focus:border-cyan-400 outline-none text-cyan-400 uppercase"
                                    required
                                >
                                    <option value="active">ACTIVE</option>
                                    <option value="coming_soon">COMING_SOON</option>
                                    <option value="draft">DRAFT</option>
                                </select>
                                <p class="mt-2 text-[7px] uppercase leading-relaxed text-slate-500">
                                    Active muncul normal di landing. Coming soon tetap muncul dengan lock. Draft hanya tersimpan di admin.
                                </p>
                            </div>

                            <div>
                                <label class="block mb-2 text-white uppercase">JOB_EMBLEM:</label>
                                <input
                                    type="file"
                                    accept="image/*"
                                    @change="onEmblemChange"
                                    class="w-full bg-black border-2 border-slate-700 p-2 text-[8px] text-cyan-400 uppercase"
                                >
                            </div>

                            <div class="flex gap-2">
                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="flex-1 py-3 border-2 uppercase font-bold transition-all"
                                    :class="isEditing ? 'border-green-500 text-green-500 hover:bg-green-600 hover:text-black' : 'border-cyan-400 text-cyan-400 hover:bg-cyan-400 hover:text-black'"
                                >
                                    {{ form.processing ? 'PROCESSING...' : (isEditing ? 'UPDATE_JOB' : 'CREATE_JOB') }}
                                </button>
                                <button type="button" @click="cancelEdit" class="px-4 py-3 border-2 border-slate-500 text-slate-500 hover:bg-slate-500 hover:text-white uppercase">
                                    X
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                </div>

                <div class="col-span-12 lg:col-span-12">
                    <div class="rpg-panel border-slate-700 h-full">
                        <h2 class="text-white mb-6 uppercase tracking-tighter">>> ACTIVE_JOBS_BOARD</h2>

                        <div class="mb-4 flex flex-col md:flex-row gap-2">
                            <input
                                v-model="filterForm.search"
                                type="text"
                                placeholder="SEARCH JOB / SLUG"
                                class="flex-1 bg-black border-2 border-slate-700 p-2 text-cyan-400 uppercase outline-none"
                                @keyup.enter="applyFilters"
                            />
                            <button
                                @click="applyFilters"
                                class="px-3 py-2 border-2 border-cyan-400 text-cyan-400 hover:bg-cyan-400 hover:text-black uppercase"
                            >
                                APPLY
                            </button>
                            <button
                                @click="resetFilters"
                                class="px-3 py-2 border-2 border-slate-600 text-slate-300 hover:bg-slate-700 hover:text-white uppercase"
                            >
                                RESET
                            </button>
                        </div>

                        <div class="space-y-4 max-h-[560px] overflow-y-auto pr-2 custom-scroll">
                            <div
                                v-for="job in jobItems"
                                :key="job.id"
                                class="flex flex-col p-4 bg-slate-900/50 border-l-4 border-cyan-500 hover:bg-slate-800 transition-all"
                            >
                                <div class="flex justify-between items-start mb-2">
                                    <div class="flex-1">
                                        <div class="text-[8px] text-slate-500 mb-1 uppercase tracking-tighter">
                                            ID: {{ job.id }} // {{ job.slug }}
                                        </div>
                                        <div class="text-white uppercase">{{ job.name }}</div>
                                        <div class="mt-2 inline-flex border px-2 py-1 text-[7px] uppercase" :class="statusClass(job.status || 'active')">
                                            {{ statusLabel(job.status || 'active') }}
                                        </div>
                                    </div>
                                    <img
                                        v-if="job.emblem_path"
                                        :src="`/storage/${job.emblem_path}`"
                                        alt="Job emblem"
                                        class="w-10 h-10 object-cover border border-cyan-500/60"
                                    >
                                    <div class="text-[8px] text-emerald-400">GROUPS: {{ job.study_groups_count || 0 }}</div>
                                </div>

                                <div class="text-[8px] text-cyan-400 mb-2 uppercase tracking-tighter">
                                    USERS: {{ job.users_count || 0 }}
                                </div>

                                <div
                                    v-if="job.description"
                                    class="text-[7px] text-slate-500 italic mb-4 border-t border-slate-800 pt-2 leading-loose"
                                >
                                    > {{ job.description }}
                                </div>

                                <div class="flex gap-4 self-end mt-2">
                                    <button
                                        @click="startEdit(job)"
                                        class="text-green-500 hover:text-white text-[8px] uppercase font-bold"
                                    >
                                        [Edit]
                                    </button>
                                    <button
                                        @click="confirmDelete(job.id)"
                                        class="text-red-500 hover:text-white text-[8px] uppercase font-bold"
                                    >
                                        [Delete]
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="mt-5 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                            <p class="text-[8px] text-slate-500 uppercase">
                                PAGE {{ jobs.current_page || 1 }} / {{ jobs.last_page || 1 }}
                                | TOTAL {{ jobs.total || 0 }}
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
        </div>

        <div
            v-if="showDeleteModal"
            class="fixed inset-0 z-[100] flex items-center justify-center bg-black/90 backdrop-blur-md p-4"
        >
            <div class="w-full max-w-lg bg-[#121212] border-2 border-red-600 shadow-[0_0_30px_rgba(220,38,38,0.3)] overflow-hidden rounded-lg">
                <div class="bg-red-600/10 border-b-2 border-red-600 p-4 flex items-center gap-3">
                    <span class="text-red-500 text-lg">!</span>
                    <h2 class="text-red-500 font-bold uppercase tracking-tighter text-[10px]">WARNING: DELETE_JOB</h2>
                </div>
                <div class="p-8 space-y-6">
                    <div class="border-l-2 border-red-900 pl-4">
                        <p class="text-slate-200 text-[10px] leading-relaxed uppercase">
                            Are you sure you want to remove this job path?
                        </p>
                    </div>
                </div>
                <div class="p-6 pt-0 flex gap-4">
                    <button
                        @click="executeDelete"
                        :disabled="form.processing"
                        class="flex-1 py-3 bg-red-600/20 border-2 border-red-600 text-red-500 hover:bg-red-600 hover:text-white transition-all uppercase font-bold text-[9px]"
                    >
                        {{ form.processing ? 'DELETING...' : 'PROCEED' }}
                    </button>
                    <button
                        @click="showDeleteModal = false"
                        class="flex-1 py-3 bg-slate-800 border-2 border-slate-600 text-slate-400 hover:bg-slate-700 hover:text-white transition-all uppercase font-bold text-[9px]"
                    >
                        CANCEL
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
    position: relative;
    box-shadow: 8px 8px 0px 0px rgba(0, 0, 0, 0.5);
}

textarea {
    resize: none;
}

.custom-scroll::-webkit-scrollbar {
    width: 6px;
}

.custom-scroll::-webkit-scrollbar-track {
    background: #0d1117;
}

.custom-scroll::-webkit-scrollbar-thumb {
    background: #334155;
    border-radius: 999px;
}

.custom-scroll::-webkit-scrollbar-thumb:hover {
    background: #4ed4d4;
}
</style>
