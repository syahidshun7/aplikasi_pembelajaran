<script setup>
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AdminNavbar from '@/Components/AdminNavbar.vue';
import Swal from 'sweetalert2';

const props = defineProps({
    events: Object,
    studyGroups: Array,
    filters: Object,
});
const page = usePage();
const isMentor = computed(() => String(page.props?.auth?.user?.role || '').toLowerCase() === 'mentor');
const firstStudyGroupId = computed(() => props.studyGroups?.[0]?.id ?? '');

const isEditing = ref(false);
const editUuid = ref(null);
const showDeleteModal = ref(false);
const deleteUuid = ref(null);

const form = useForm({
    title: '',
    description: '',
    sequence_order: 1,
    study_group_id: '',
    starts_at: '',
    ends_at: '',
});

const filterForm = useForm({
    search: props.filters?.search || '',
});

const eventItems = computed(() => props.events?.data || []);
const paginationLinks = computed(() => props.events?.links || []);

const applyMentorDefaultStudyGroup = () => {
    if (isMentor.value && !form.study_group_id) {
        form.study_group_id = firstStudyGroupId.value || '';
    }
};

const startEdit = (event) => {
    isEditing.value = true;
    editUuid.value = event.uuid;
    form.title = event.title || '';
    form.description = event.description || '';
    form.sequence_order = event.sequence_order || 1;
    form.study_group_id = event.study_group_id || '';
    form.starts_at = event.starts_at ? formatDateTimeLocal(event.starts_at) : '';
    form.ends_at = event.ends_at ? formatDateTimeLocal(event.ends_at) : '';
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

const cancelEdit = () => {
    isEditing.value = false;
    editUuid.value = null;
    form.reset();
    form.sequence_order = 1;
    applyMentorDefaultStudyGroup();
};

const formatDateTimeLocal = (value) => {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';
    const pad = (n) => String(n).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
};

const formatScheduleText = (startAt, endAt) => {
    if (!startAt || !endAt) return 'SCHEDULE_NOT_SET';
    const start = new Date(startAt);
    const end = new Date(endAt);
    if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) return 'SCHEDULE_NOT_SET';
    const minutes = Math.max(0, Math.round((end - start) / 60000));
    return `${start.toLocaleString('id-ID')} | ${minutes} MIN`;
};

const submit = () => {
    if (isEditing.value) {
        form.put(route('admin.events.update', editUuid.value), {
            onSuccess: () => cancelEdit(),
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

    form.post(route('admin.events.store'), {
        onSuccess: () => {
            form.reset();
            form.sequence_order = 1;
            applyMentorDefaultStudyGroup();
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

const confirmDelete = (uuid) => {
    deleteUuid.value = uuid;
    showDeleteModal.value = true;
};

const executeDelete = () => {
    if (!deleteUuid.value) return;
    form.delete(route('admin.events.destroy', deleteUuid.value), {
        onSuccess: () => {
            showDeleteModal.value = false;
            deleteUuid.value = null;
        },
    });
};

const applyFilters = () => {
    router.get(route('admin.events.index'), filterForm.data(), {
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

watch([isMentor, firstStudyGroupId], () => {
    applyMentorDefaultStudyGroup();
}, { immediate: true });
</script>

<template>
    <Head title="EVENTS_REGISTRY" />

    <div class="min-h-screen bg-[#0d1117] p-4 md:p-8 font-['Press_Start_2P'] text-[#4ed4d4] text-[10px] relative">
        <div class="max-w-7xl mx-auto space-y-8">
            <AdminNavbar />

            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3 border-b-4 border-blue-900 pb-4">
                <h1 class="text-base sm:text-xl uppercase tracking-widest animate-pulse">Events_Registry_System</h1>
                <Link href="/dashboard" class="inline-flex items-center justify-center px-3 py-2 border border-slate-600 bg-slate-900/40 text-slate-300 hover:text-white transition-colors uppercase text-[9px] sm:text-[10px]">[Back_to_HQ]</Link>
            </div>

            <div class="grid grid-cols-12 gap-8">
                <div class="col-span-12 lg:col-span-5">
                    <div class="rpg-panel border-blue-500/50">
                        <h2 class="mb-6 uppercase tracking-tighter text-blue-400">
                            >> {{ isEditing ? 'UPDATE_EVENT_' + editUuid?.substring(0, 8) : 'ISSUE_NEW_EVENT' }}
                        </h2>

                        <form @submit.prevent="submit" class="space-y-6">
                            <div>
                                <label class="block mb-2 text-white uppercase">EVENT_TITLE:</label>
                                <input
                                    v-model="form.title"
                                    type="text"
                                    class="w-full bg-black border-2 border-slate-700 p-2 focus:border-blue-400 outline-none text-blue-300 uppercase"
                                    required
                                >
                            </div>

                            <div>
                                <label class="block mb-2 text-white uppercase">DESCRIPTION:</label>
                                <textarea
                                    v-model="form.description"
                                    class="w-full bg-black border-2 border-slate-700 p-2 text-[12px] font-sans text-slate-200 focus:border-blue-400 focus:ring-0"
                                    style="resize: vertical; min-height: 120px;"
                                ></textarea>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block mb-2 text-white uppercase">SEQUENCE:</label>
                                    <input
                                        v-model="form.sequence_order"
                                        type="number"
                                        min="1"
                                        class="w-full bg-black border-2 border-slate-700 p-2 focus:border-blue-400 outline-none text-yellow-400"
                                        required
                                    >
                                </div>
                                <div>
                                    <label class="block mb-2 text-white uppercase">STUDY_GROUP:</label>
                                    <select
                                        v-model="form.study_group_id"
                                        class="w-full bg-black border-2 border-slate-700 p-2 focus:border-blue-400 outline-none text-blue-300 uppercase"
                                    >
                                        <option v-if="!isMentor" value="">NO_GROUP</option>
                                        <option v-if="isMentor && !studyGroups.length" value="" disabled>NO_STUDY_GROUP_AVAILABLE</option>
                                        <option v-for="group in studyGroups" :key="group.id" :value="group.id">
                                            {{ group.name }}
                                        </option>
                                    </select>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div>
                                    <label class="block mb-2 text-white uppercase">STARTS_AT:</label>
                                    <input
                                        v-model="form.starts_at"
                                        type="datetime-local"
                                        class="w-full bg-black border-2 border-slate-700 p-2 focus:border-blue-400 outline-none text-cyan-300"
                                    >
                                </div>
                                <div>
                                    <label class="block mb-2 text-white uppercase">ENDS_AT:</label>
                                    <input
                                        v-model="form.ends_at"
                                        type="datetime-local"
                                        class="w-full bg-black border-2 border-slate-700 p-2 focus:border-blue-400 outline-none text-cyan-300"
                                    >
                                </div>
                            </div>

                            <div class="flex gap-2">
                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="flex-1 py-3 border-2 border-blue-400 text-blue-300 hover:bg-blue-400 hover:text-black uppercase font-bold transition-all"
                                >
                                    {{ form.processing ? 'PROCESSING...' : (isEditing ? 'UPDATE_EVENT' : 'CREATE_EVENT') }}
                                </button>
                                <button
                                    v-if="isEditing"
                                    @click="cancelEdit"
                                    type="button"
                                    class="px-4 py-3 border-2 border-slate-500 text-slate-500 hover:bg-slate-500 hover:text-white uppercase"
                                >
                                    X
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="col-span-12 lg:col-span-7">
                    <div class="rpg-panel border-slate-700 h-full">
                        <h2 class="text-white mb-6 uppercase tracking-tighter">>> ACTIVE_EVENTS_BOARD</h2>

                        <div class="mb-4 flex flex-col md:flex-row gap-2">
                            <input
                                v-model="filterForm.search"
                                type="text"
                                placeholder="SEARCH EVENT / GROUP"
                                class="flex-1 bg-black border-2 border-slate-700 p-2 text-blue-300 uppercase outline-none"
                                @keyup.enter="applyFilters"
                            />
                            <button
                                @click="applyFilters"
                                class="px-3 py-2 border-2 border-blue-400 text-blue-300 hover:bg-blue-400 hover:text-black uppercase"
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
                                v-for="event in eventItems"
                                :key="event.uuid"
                                class="flex flex-col p-4 bg-slate-900/50 border-l-4 border-blue-500 hover:bg-slate-800 transition-all"
                            >
                                <div class="flex justify-between items-start mb-2">
                                    <div class="flex-1">
                                        <div class="text-[8px] text-slate-500 mb-1 uppercase tracking-tighter">
                                            ID: {{ event.uuid.substring(0, 8) }}
                                        </div>
                                        <div class="text-white uppercase">{{ event.title }}</div>
                                        <div class="text-[7px] text-cyan-400 uppercase mt-1">
                                            GROUP: {{ event.study_group?.name || 'NO_GROUP' }}
                                        </div>
                                        <div class="text-[7px] text-slate-300 uppercase mt-1 break-words">
                                            {{ formatScheduleText(event.starts_at, event.ends_at) }}
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-yellow-500 text-[8px]">SEQ: {{ event.sequence_order }}</div>
                                        <div class="text-[8px] text-emerald-400 mt-1">GUIDES: {{ event.guides_count || 0 }}</div>
                                        <div class="text-[8px] text-blue-300 mt-1">QUESTS: {{ event.quests_count || 0 }}</div>
                                    </div>
                                </div>

                                <div
                                    v-if="event.description"
                                    class="text-[7px] text-slate-500 italic mb-4 border-t border-slate-800 pt-2 leading-loose"
                                >
                                    > {{ event.description }}
                                </div>

                                <div class="flex gap-4 self-end mt-2">
                                    <Link
                                        :href="route('admin.events.detail', event.uuid)"
                                        class="text-cyan-400 hover:text-white text-[8px] uppercase font-bold"
                                    >
                                        [Detail]
                                    </Link>
                                    <button
                                        @click="startEdit(event)"
                                        class="text-emerald-500 hover:text-white text-[8px] uppercase font-bold"
                                    >
                                        [Edit]
                                    </button>
                                    <button
                                        @click="confirmDelete(event.uuid)"
                                        class="text-red-500 hover:text-white text-[8px] uppercase font-bold"
                                    >
                                        [Delete]
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="mt-5 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                            <p class="text-[8px] text-slate-500 uppercase">
                                PAGE {{ events.current_page || 1 }} / {{ events.last_page || 1 }}
                                | TOTAL {{ events.total || 0 }}
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
                                            ? 'border-blue-400 text-blue-300 bg-blue-900/20'
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
                    <h2 class="text-red-500 font-bold uppercase tracking-tighter text-[10px]">WARNING: DELETE_EVENT</h2>
                </div>
                <div class="p-8 space-y-6">
                    <div class="border-l-2 border-red-900 pl-4">
                        <p class="text-slate-200 text-[10px] leading-relaxed uppercase">
                            Are you sure you want to remove this event?
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
    background: #60a5fa;
}
</style>
