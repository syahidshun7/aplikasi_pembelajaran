<script setup>
import { Head, useForm, Link, usePage, router } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue'; 
import AdminNavbar from '@/Components/AdminNavbar.vue';
import Swal from 'sweetalert2';

const props = defineProps({
    groups: Object,
    filters: Object,
    jobs: Array,
});

// INITIALIZE SWEETALERT TOAST
const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
    background: '#1a1c2c',
    color: '#4ed4d4',
});

const isEditing = ref(false);
const editId = ref(null);
const showFormModal = ref(false);

// State untuk Modal Konfirmasi
const showDeleteModal = ref(false);
const groupIdToDelete = ref(null);

// FORM INITIALIZATION
const form = useForm({
    name: '',
    description: '',
    max_members: 5,
    min_level: 1,
    job_id: '',
});
const filterForm = useForm({
    search: props.filters?.search || '',
    view: props.filters?.view || 'active',
});
const groupItems = computed(() => props.groups?.data || []);
const paginationLinks = computed(() => props.groups?.links || []);
const isTrashView = computed(() => filterForm.view === 'trash');

// Pantau Flash Message dari Server
watch(() => usePage().props.flash, (flash) => {
    if (flash?.message) {
        Toast.fire({
            icon: 'success',
            title: flash.message
        });
    }
}, { deep: true });

// METHODS
const startEdit = (group) => {
    isEditing.value = true;
    editId.value = group.uuid;
    form.name = group.name;
    form.description = group.description || '';
    form.max_members = group.max_members;
    form.min_level = group.min_level ?? 1;
    form.job_id = group.job_id || '';
    showFormModal.value = true;

    Toast.fire({
        icon: 'info',
        title: 'MODIFYING_PARTY_DATA'
    });
};

const cancelEdit = () => {
    isEditing.value = false;
    editId.value = null;
    form.reset();
    showFormModal.value = false;
};

const openCreateModal = () => {
    isEditing.value = false;
    editId.value = null;
    form.reset();
    showFormModal.value = true;
};

const submit = () => {
    if (isEditing.value) {
        form.put(route('groups.update', editId.value), {
            onSuccess: () => {
                cancelEdit();
                Toast.fire({ icon: 'success', title: 'REGISTRY_UPDATED' });
            },
            onError: (err) => {
                Swal.fire({
                    icon: 'error',
                    title: 'UPDATE_FAILED',
                    text: Object.values(err)[0],
                    background: '#1a1c2c',
                    color: '#ff4d4d'
                });
            }
        });
    } else {
        form.post(route('groups.store'), {
            onSuccess: () => {
                cancelEdit();
                Toast.fire({ icon: 'success', title: 'PARTY_ESTABLISHED' });
            },
            onError: (err) => {
                Swal.fire({
                    icon: 'warning',
                    title: 'REGISTRATION_FAILED',
                    text: Object.values(err)[0],
                    background: '#1a1c2c',
                    color: '#facc15'
                });
            }
        });
    }
};

const confirmAbort = (id) => {
    groupIdToDelete.value = id;
    showDeleteModal.value = true;
};

const executeAbort = () => {
    if (groupIdToDelete.value) {
        form.delete(route('groups.destroy', groupIdToDelete.value), {
            onSuccess: () => {
                showDeleteModal.value = false;
                groupIdToDelete.value = null;
                Toast.fire({ icon: 'success', title: 'PARTY_ARCHIVED' });
            }
        });
    }
};

const restoreGroup = (uuid) => {
    router.patch(route('groups.restore', uuid), {}, {
        preserveScroll: true,
        onSuccess: () => {
            Toast.fire({ icon: 'success', title: 'PARTY_RESTORED' });
        },
    });
};

const hardDeleteGroup = (uuid) => {
    Swal.fire({
        title: 'HARD_DELETE_PARTY?',
        text: 'Group akan dihapus permanen dan tidak bisa dipulihkan.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'DELETE_PERMANENTLY',
        cancelButtonText: 'CANCEL',
        background: '#1a1c2c',
        color: '#4ed4d4',
        confirmButtonColor: '#dc2626',
    }).then((result) => {
        if (!result.isConfirmed) return;

        router.delete(route('groups.force-destroy', uuid), {
            preserveScroll: true,
            onSuccess: () => {
                Toast.fire({ icon: 'success', title: 'PARTY_PERMANENTLY_DELETED' });
            },
        });
    });
};

const applyFilters = () => {
    router.get(route('groups.manage'), filterForm.data(), {
        preserveState: true,
        preserveScroll: true,
    });
};

const resetFilters = () => {
    filterForm.search = '';
    applyFilters();
};

const setView = (view) => {
    if (filterForm.view === view) return;
    filterForm.view = view;
    cancelEdit();
    applyFilters();
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
    <Head title="PARTY_REGISTRY" />

    <div class="min-h-screen bg-[#0d1117] p-4 md:p-8 font-['Press_Start_2P'] text-[#4ed4d4] text-[10px] relative">
        <div class="max-w-7xl mx-auto space-y-8">

            <AdminNavbar />

            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3 border-b-4 border-emerald-900 pb-4">
                <h1 class="text-base sm:text-xl uppercase tracking-widest animate-pulse">Party_Registry_System</h1>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="openCreateModal"
                        class="inline-flex items-center justify-center px-3 py-2 border border-emerald-500 bg-emerald-900/20 text-emerald-300 hover:bg-emerald-500 hover:text-black transition-colors uppercase text-[9px] sm:text-[10px]"
                    >
                        [New_Party]
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
                    <div class="rpg-panel border-emerald-500/50">
                        <h2 class="mb-6 uppercase tracking-tighter text-emerald-500">
                            >> {{ isEditing ? 'UPDATE_PARTY_ID_' + editId.substring(0,8) : 'ISSUE_NEW_PARTY' }}
                        </h2>

                        <form @submit.prevent="submit" class="space-y-6">
                            <div>
                                <label class="block mb-2 text-white uppercase">PARTY_NAME:</label>
                                <input v-model="form.name" type="text"
                                    class="w-full bg-black border-2 border-slate-700 p-2 focus:border-emerald-400 outline-none text-emerald-400 uppercase"
                                    placeholder="Enter party name..." required>
                            </div>

                            <div>
                                <label class="block mb-2 text-white uppercase">MISSION_OBJECTIVE:</label>
                                <textarea v-model="form.description"
                                    class="w-full bg-black border-2 border-slate-700 p-2 text-[12px] font-sans text-slate-200 focus:border-emerald-400 focus:ring-0"
                                    style="resize: vertical; min-height: 120px;"></textarea>
                            </div>

                            <div>
                                <label class="block mb-2 text-white uppercase">MEMBER_CAPACITY:</label>
                                <input v-model="form.max_members" type="number"
                                    class="w-full bg-black border-2 border-slate-700 p-2 focus:border-emerald-400 outline-none text-yellow-500">
                            </div>

                            <div>
                                <label class="block mb-2 text-white uppercase">MINIMUM_JOIN_LEVEL:</label>
                                <input v-model.number="form.min_level" type="number" min="1" max="100"
                                    class="w-full bg-black border-2 border-slate-700 p-2 focus:border-emerald-400 outline-none text-cyan-400">
                                <p class="mt-2 text-[8px] uppercase text-slate-500">Player dengan level di bawah ini tidak bisa kirim join request.</p>
                            </div>

                            <div>
                                <label class="block mb-2 text-white uppercase">JOB_PATH:</label>
                                <select v-model="form.job_id"
                                    class="w-full bg-black border-2 border-slate-700 p-2 focus:border-emerald-400 outline-none text-emerald-400 uppercase"
                                    required>
                                    <option value="" disabled>-- SELECT JOB --</option>
                                    <option v-for="job in jobs" :key="job.id" :value="job.id">
                                        {{ job.name }}
                                    </option>
                                </select>
                            </div>

                            <div class="flex gap-2">
                                <button type="submit" :disabled="form.processing"
                                    class="flex-1 py-3 border-2 border-emerald-400 text-emerald-400 hover:bg-emerald-400 hover:text-black uppercase font-bold transition-all">
                                    {{ form.processing ? 'PROCESSING...' : (isEditing ? 'UPDATE_PARTY' : 'CONFIRM_PARTY') }}
                                </button>
                                <button type="button" @click="cancelEdit"
                                    class="px-4 py-3 border-2 border-slate-500 text-slate-500 hover:bg-slate-500 hover:text-white uppercase">
                                    X
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                </div>

                <div class="col-span-12 lg:col-span-12">
                    <div class="rpg-panel border-slate-700 h-full">
                        <h2 class="text-white mb-6 uppercase tracking-tighter">
                            >> {{ isTrashView ? 'TRASH_PARTY_BOARD' : 'ACTIVE_PARTY_BOARD' }}
                        </h2>
                        <div class="mb-4 flex gap-2">
                            <button
                                @click="setView('active')"
                                class="px-3 py-2 border-2 uppercase"
                                :class="isTrashView ? 'border-slate-600 text-slate-300 hover:bg-slate-700 hover:text-white' : 'border-emerald-400 text-emerald-300 bg-emerald-900/20'"
                            >
                                ACTIVE
                            </button>
                            <button
                                @click="setView('trash')"
                                class="px-3 py-2 border-2 uppercase"
                                :class="isTrashView ? 'border-amber-500 text-amber-300 bg-amber-900/20' : 'border-slate-600 text-slate-300 hover:bg-slate-700 hover:text-white'"
                            >
                                TRASH
                            </button>
                        </div>
                        <div class="mb-4 flex flex-col md:flex-row gap-2">
                            <input
                                v-model="filterForm.search"
                                type="text"
                                placeholder="SEARCH PARTY / CODE"
                                class="flex-1 bg-black border-2 border-slate-700 p-2 focus:border-emerald-400 outline-none text-emerald-400 uppercase"
                                @keyup.enter="applyFilters"
                            />
                            <button @click="applyFilters"
                                class="px-3 py-2 border-2 border-emerald-400 text-emerald-400 hover:bg-emerald-400 hover:text-black uppercase">
                                APPLY
                            </button>
                            <button @click="resetFilters"
                                class="px-3 py-2 border-2 border-slate-600 text-slate-300 hover:bg-slate-700 hover:text-white uppercase">
                                RESET
                            </button>
                        </div>

                        <div class="space-y-4 max-h-[560px] overflow-y-auto pr-2 custom-scroll">
                            <div v-for="g in groupItems" :key="g.uuid"
                                class="flex flex-col p-4 bg-slate-900/50 border-l-4 border-emerald-500 hover:bg-slate-800 transition-all">

                                <div class="flex justify-between items-start gap-3 mb-2">
                                    <div class="flex-1">
                                        <div class="text-[8px] text-slate-500 mb-1 uppercase tracking-tighter">ID: {{ g.uuid.substring(0,8) }}</div>
                                        <div class="text-[7px] text-cyan-400 uppercase mb-1">
                                            JOB: {{ g.job?.name || 'UNASSIGNED' }}
                                        </div>
                                        <div class="text-white uppercase">{{ g.name }}</div>
                                    </div>
                                    <div class="shrink-0 text-right leading-tight space-y-1">
                                        <div class="text-yellow-500 text-[8px] tracking-widest">{{ g.users_count || 0 }} / {{ g.max_members }} MEMBERS</div>
                                        <div class="text-cyan-300 text-[8px] tracking-widest">MIN JOIN LVL {{ g.min_level || 1 }}</div>
                                    </div>
                                </div>
                                <div v-if="!isTrashView" class="text-[8px] text-orange-400 mb-2 uppercase tracking-tighter">
                                    PENDING_REQUESTS: {{ g.pending_requests_count || 0 }}
                                </div>
                                <div v-else class="text-[8px] text-amber-400 mb-2 uppercase tracking-tighter">
                                    DELETED_AT: {{ new Date(g.deleted_at).toLocaleString('id-ID') }}
                                </div>

                                <div v-if="g.description"
                                    class="text-[7px] text-slate-500 italic mb-4 border-t border-slate-800 pt-2 leading-loose">
                                    > {{ g.description }}
                                </div>

                                <div class="flex gap-4 self-end mt-2">
                                    <Link v-if="!isTrashView" :href="route('groups.detail', g.uuid)"
                                        class="text-cyan-400 hover:text-white text-[8px] uppercase font-bold">[Detail]</Link>
                                    <button v-if="!isTrashView" @click="startEdit(g)"
                                        class="text-emerald-500 hover:text-white text-[8px] uppercase font-bold">[Edit]</button>
                                    <button v-if="!isTrashView" @click="confirmAbort(g.uuid)"
                                        class="text-red-500 hover:text-white text-[8px] uppercase font-bold">[Purge]</button>
                                    <button v-if="isTrashView" @click="restoreGroup(g.uuid)"
                                        class="text-emerald-400 hover:text-white text-[8px] uppercase font-bold">[Restore]</button>
                                    <button v-if="isTrashView" @click="hardDeleteGroup(g.uuid)"
                                        class="text-red-500 hover:text-white text-[8px] uppercase font-bold">[Hard_Delete]</button>
                                </div>
                            </div>
                        </div>
                        <div class="mt-5 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                            <p class="text-[8px] text-slate-500 uppercase">
                                PAGE {{ groups.current_page || 1 }} / {{ groups.last_page || 1 }}
                                | TOTAL {{ groups.total || 0 }}
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
                                            ? 'border-emerald-400 text-emerald-400 bg-emerald-900/20'
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

        <div v-if="showDeleteModal"
            class="fixed inset-0 z-[100] flex items-center justify-center bg-black/90 backdrop-blur-md p-4">
            <div class="w-full max-w-lg bg-[#121212] border-2 border-red-600 shadow-[0_0_30px_rgba(220,38,38,0.3)] overflow-hidden rounded-lg">
                <div class="bg-red-600/10 border-b-2 border-red-600 p-4 flex items-center gap-3">
                    <span class="text-red-500 text-lg">[!]</span>
                    <h2 class="text-red-500 font-bold uppercase tracking-tighter text-[10px]">WARNING: ARCHIVE_PROTOCOL</h2>
                </div>
                <div class="p-8 space-y-6">
                    <div class="border-l-2 border-red-900 pl-4">
                        <p class="text-slate-200 text-[10px] leading-relaxed uppercase">Pindahkan group ini ke trash (masih bisa direstore)?</p>
                    </div>
                </div>
                <div class="p-6 pt-0 flex gap-4">
                    <button @click="executeAbort" :disabled="form.processing"
                        class="flex-1 py-3 bg-red-600/20 border-2 border-red-600 text-red-500 hover:bg-red-600 hover:text-white transition-all uppercase font-bold text-[9px] rounded active:scale-95">
                        {{ form.processing ? 'ARCHIVING...' : 'ARCHIVE' }}
                    </button>
                    <button @click="showDeleteModal = false"
                        class="flex-1 py-3 bg-slate-800 border-2 border-slate-600 text-slate-400 hover:bg-slate-700 hover:text-white transition-all uppercase font-bold text-[9px] rounded active:scale-95">
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

.fixed {
    animation: fadeIn 0.2s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
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
    background: #34d399;
}
</style>
