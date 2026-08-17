<script setup>
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import AdminNavbar from '@/Components/AdminNavbar.vue';
import Swal from 'sweetalert2';

const props = defineProps({
    events: Object,
    studyGroups: Array,
    jobRoles: Array,
    filters: Object,
    selectedStudyGroup: Object,
});
const page = usePage();
const isMentor = computed(() => String(page.props?.auth?.user?.role || '').toLowerCase() === 'mentor');
const firstStudyGroupId = computed(() => props.studyGroups?.[0]?.id ?? '');
const isScopedGroup = computed(() => Boolean(props.selectedStudyGroup?.uuid));
const indexRouteUrl = computed(() => props.selectedStudyGroup?.events_url || route('admin.events.index'));
const studyGroupJobMap = computed(() => Object.fromEntries((props.studyGroups || []).map((group) => [String(group.id), group.job_id || ''])));

const isEditing = ref(false);
const editUuid = ref(null);
const showFormModal = ref(false);
const showDeleteModal = ref(false);
const deleteUuid = ref(null);
const imageInputRef = ref(null);
const selectedImageFiles = ref([]);
const newImagePreviews = ref([]);
const existingImages = ref([]);

const form = useForm({
    title: '',
    description: '',
    sequence_order: 1,
    study_group_id: '',
    job_id: '',
    starts_at: '',
    ends_at: '',
    self_attendance_enabled: false,
    images: [],
    remove_image_ids: [],
});

const filterForm = useForm({
    search: props.filters?.search || '',
    view: props.filters?.view || 'active',
});

const eventItems = computed(() => props.events?.data || []);
const paginationLinks = computed(() => props.events?.links || []);
const isTrashView = computed(() => filterForm.view === 'trash');

const clearNewImagePreviews = () => {
    newImagePreviews.value.forEach((preview) => {
        if (preview?.url) {
            URL.revokeObjectURL(preview.url);
        }
    });
    newImagePreviews.value = [];
};

const resetImageState = () => {
    selectedImageFiles.value = [];
    existingImages.value = [];
    form.images = [];
    form.remove_image_ids = [];
    clearNewImagePreviews();
    if (imageInputRef.value) {
        imageInputRef.value.value = '';
    }
};

const applyMentorDefaultStudyGroup = () => {
    if (isScopedGroup.value && props.selectedStudyGroup?.id && !form.study_group_id) {
        form.study_group_id = props.selectedStudyGroup.id;
        return;
    }
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
    form.job_id = event.job_id || '';
    form.starts_at = event.starts_at ? formatDateTimeLocal(event.starts_at) : '';
    form.ends_at = event.ends_at ? formatDateTimeLocal(event.ends_at) : '';
    form.self_attendance_enabled = Boolean(event.self_attendance_enabled);
    existingImages.value = Array.isArray(event.images) ? event.images : [];
    form.remove_image_ids = [];
    selectedImageFiles.value = [];
    form.images = [];
    clearNewImagePreviews();
    if (imageInputRef.value) {
        imageInputRef.value.value = '';
    }
    showFormModal.value = true;
};

const cancelEdit = () => {
    isEditing.value = false;
    editUuid.value = null;
    form.reset();
    form.sequence_order = 1;
    form.self_attendance_enabled = false;
    resetImageState();
    if (isScopedGroup.value && props.selectedStudyGroup?.id) {
        form.study_group_id = props.selectedStudyGroup.id;
    }
    applyMentorDefaultStudyGroup();
    showFormModal.value = false;
};

const openCreateModal = () => {
    cancelEdit();
    showFormModal.value = true;
};

const syncAudienceTarget = () => {
    const groupId = String(form.study_group_id || '');

    if (groupId !== '') {
        form.job_id = studyGroupJobMap.value[groupId] || '';
        return;
    }

    if (isMentor.value) {
        form.job_id = '';
    }
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
    form.images = selectedImageFiles.value;

    form.transform((data) => ({
        ...data,
        sequence_order: Number(data.sequence_order || 1),
        self_attendance_enabled: data.self_attendance_enabled ? '1' : '0',
        _method: isEditing.value ? 'PUT' : undefined,
    }));

    form.post(isEditing.value ? route('admin.events.update', editUuid.value) : route('admin.events.store'), {
        forceFormData: true,
        onSuccess: () => {
            cancelEdit();
        },
        onError: (errors) => {
            Swal.fire({
                icon: isEditing.value ? 'error' : 'warning',
                title: isEditing.value ? 'UPDATE_FAILED' : 'CREATE_FAILED',
                text: Object.values(errors)[0] || 'UNKNOWN_ERROR',
                background: '#1a1c2c',
                color: isEditing.value ? '#ff4d4d' : '#facc15',
            });
        },
    });
};

const onImageInputChange = (event) => {
    const fileList = Array.from(event?.target?.files || []);
    clearNewImagePreviews();
    selectedImageFiles.value = fileList.slice(0, 8);
    form.images = selectedImageFiles.value;
    newImagePreviews.value = selectedImageFiles.value.map((file) => ({
        key: `${file.name}-${file.size}-${file.lastModified}`,
        url: URL.createObjectURL(file),
    }));
};

const toggleRemoveExistingImage = (imageId) => {
    const current = form.remove_image_ids.map((id) => Number(id));
    const target = Number(imageId);

    if (current.includes(target)) {
        form.remove_image_ids = current.filter((id) => id !== target);
        return;
    }

    form.remove_image_ids = [...current, target];
};

const isExistingImageRemoved = (imageId) => {
    return form.remove_image_ids.map((id) => Number(id)).includes(Number(imageId));
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

const restoreEvent = (uuid) => {
    router.patch(route('admin.events.restore', uuid), {}, {
        preserveScroll: true,
    });
};

const hardDeleteEvent = (uuid) => {
    Swal.fire({
        title: 'HARD_DELETE_EVENT?',
        text: 'Event akan dihapus permanen dan tidak bisa dipulihkan.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'DELETE_PERMANENTLY',
        cancelButtonText: 'CANCEL',
        background: '#1a1c2c',
        color: '#4ed4d4',
        confirmButtonColor: '#dc2626',
    }).then((result) => {
        if (!result.isConfirmed) return;
        router.delete(route('admin.events.force-destroy', uuid), {
            preserveScroll: true,
        });
    });
};

const applyFilters = () => {
    router.get(indexRouteUrl.value, filterForm.data(), {
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

watch([isMentor, firstStudyGroupId], () => {
    applyMentorDefaultStudyGroup();
}, { immediate: true });

watch(() => form.study_group_id, () => {
    syncAudienceTarget();
}, { immediate: true });

onBeforeUnmount(() => {
    clearNewImagePreviews();
});
</script>

<template>
    <Head title="EVENTS_REGISTRY" />

    <div class="min-h-screen bg-[#0d1117] p-4 md:p-8 font-['Press_Start_2P'] text-[#4ed4d4] text-[10px] relative">
        <div class="max-w-7xl mx-auto space-y-8">
            <AdminNavbar />

            <div v-if="isScopedGroup" class="border-2 border-blue-500/50 bg-blue-950/20 p-4">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <p class="text-[8px] uppercase text-blue-300">Study_Group_Context</p>
                        <h1 class="mt-2 text-[13px] uppercase text-white">{{ selectedStudyGroup.name }}</h1>
                    </div>
                    <Link :href="selectedStudyGroup.back_url" class="border border-slate-600 px-3 py-2 text-[8px] uppercase text-slate-300 hover:text-white">
                        Back_To_Group
                    </Link>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3 border-b-4 border-blue-900 pb-4">
                <h1 class="text-base sm:text-xl uppercase tracking-widest animate-pulse">Events_Registry_System</h1>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="openCreateModal"
                        class="inline-flex items-center justify-center px-3 py-2 border border-blue-500 bg-blue-900/20 text-blue-300 hover:bg-blue-500 hover:text-black transition-colors uppercase text-[9px] sm:text-[10px]"
                    >
                        [New_Event]
                    </button>
                    <Link href="/dashboard" class="inline-flex items-center justify-center px-3 py-2 border border-slate-600 bg-slate-900/40 text-slate-300 hover:text-white transition-colors uppercase text-[9px] sm:text-[10px]">[Back_to_HQ]</Link>
                </div>
            </div>

            <div class="grid grid-cols-12 gap-8">
                <div
                    v-if="showFormModal"
                    class="fixed inset-0 z-[120] flex items-center justify-center bg-black/85 backdrop-blur-sm p-4"
                >
                    <div class="w-full max-w-5xl max-h-[90vh] overflow-y-auto modal-scroll">
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
                                        <option v-if="!isMentor && !isScopedGroup" value="">NO_GROUP</option>
                                        <option v-if="isMentor && !studyGroups.length" value="" disabled>NO_STUDY_GROUP_AVAILABLE</option>
                                        <option v-for="group in studyGroups" :key="group.id" :value="group.id">
                                            {{ group.name }}
                                        </option>
                                    </select>
                                </div>
                            </div>

                            <div v-if="!isMentor">
                                <label class="block mb-2 text-white uppercase">TARGET_JOB:</label>
                                <select
                                    v-model="form.job_id"
                                    :disabled="Boolean(form.study_group_id)"
                                    class="w-full bg-black border-2 border-slate-700 p-2 focus:border-blue-400 outline-none text-blue-300 uppercase disabled:cursor-not-allowed disabled:opacity-60"
                                >
                                    <option value="">SELECT_JOB_FOR_PUBLIC_EVENT</option>
                                    <option v-for="job in jobRoles" :key="job.id" :value="job.id">
                                        {{ job.name }}
                                    </option>
                                </select>
                                <p class="mt-2 text-[7px] uppercase text-slate-500">
                                    {{ form.study_group_id ? 'Auto mengikuti job dari study group.' : 'Kosongkan untuk event global semua user, atau pilih job untuk public per jurusan.' }}
                                </p>
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

                            <label class="flex items-center gap-2 text-white uppercase">
                                <input v-model="form.self_attendance_enabled" type="checkbox">
                                <span>User can self-attend this event</span>
                            </label>

                            <div>
                                <label class="block mb-2 text-white uppercase">EVENT_IMAGES:</label>
                                <input
                                    ref="imageInputRef"
                                    type="file"
                                    multiple
                                    accept="image/jpeg,image/png,image/webp,image/jpg"
                                    class="w-full bg-black border-2 border-slate-700 p-2 focus:border-blue-400 outline-none text-blue-300"
                                    @change="onImageInputChange"
                                >
                                <p class="mt-2 text-[7px] uppercase text-slate-500">
                                    Max 8 gambar. Format: JPG, JPEG, PNG, WEBP.
                                </p>
                            </div>

                            <div v-if="isEditing && existingImages.length > 0">
                                <p class="mb-2 text-[8px] uppercase text-slate-400">Existing Images</p>
                                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                    <button
                                        v-for="image in existingImages"
                                        :key="image.id"
                                        type="button"
                                        class="relative overflow-hidden border"
                                        :class="isExistingImageRemoved(image.id) ? 'border-red-500/70 opacity-50' : 'border-slate-700'"
                                        @click="toggleRemoveExistingImage(image.id)"
                                    >
                                        <img :src="image.url" alt="Event image" class="h-20 w-full object-cover">
                                        <span class="absolute right-1 top-1 rounded bg-black/65 px-1 py-[1px] text-[7px] uppercase text-white">
                                            <i class="fi" :class="isExistingImageRemoved(image.id) ? 'fi-rr-undo' : 'fi-rr-trash'" />
                                        </span>
                                    </button>
                                </div>
                            </div>

                            <div v-if="newImagePreviews.length > 0">
                                <p class="mb-2 text-[8px] uppercase text-slate-400">New Images</p>
                                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                    <div
                                        v-for="preview in newImagePreviews"
                                        :key="preview.key"
                                        class="overflow-hidden border border-blue-500/60"
                                    >
                                        <img :src="preview.url" alt="Event image preview" class="h-20 w-full object-cover">
                                    </div>
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
                </div>

                <div class="col-span-12 lg:col-span-12">
                    <div class="rpg-panel border-slate-700 h-full">
                        <h2 class="text-white mb-6 uppercase tracking-tighter">
                            >> {{ isTrashView ? 'TRASH_EVENTS_BOARD' : 'ACTIVE_EVENTS_BOARD' }}
                        </h2>
                        <div class="mb-4 flex gap-2">
                            <button
                                @click="setView('active')"
                                class="px-3 py-2 border-2 uppercase"
                                :class="isTrashView ? 'border-slate-600 text-slate-300 hover:bg-slate-700 hover:text-white' : 'border-blue-400 text-blue-300 bg-blue-900/20'"
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
                                        <div class="text-[7px] text-emerald-400 uppercase mt-1">
                                            SELF_ATTENDANCE: {{ event.self_attendance_enabled ? 'ENABLED' : 'DISABLED' }}
                                        </div>
                                        <div class="text-[7px] text-cyan-400 uppercase mt-1">
                                            TARGET:
                                            {{ event.study_group?.name || (event.job?.name ? `PUBLIC_${event.job.name}` : 'PUBLIC_ALL') }}
                                        </div>
                                        <div v-if="!isTrashView" class="text-[7px] text-slate-300 uppercase mt-1 break-words">
                                            {{ formatScheduleText(event.starts_at, event.ends_at) }}
                                        </div>
                                        <div v-else class="text-[7px] text-amber-300 uppercase mt-1 break-words">
                                            DELETED_AT: {{ new Date(event.deleted_at).toLocaleString('id-ID') }}
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
                                        v-if="!isTrashView"
                                        :href="route('admin.events.detail', event.uuid)"
                                        class="text-cyan-400 hover:text-white text-[8px] uppercase font-bold"
                                    >
                                        [Detail]
                                    </Link>
                                    <Link
                                        v-if="!isTrashView"
                                        :href="route('events.user-preview', event.uuid)"
                                        class="text-blue-300 hover:text-white text-[8px] uppercase font-bold"
                                    >
                                        [User_View]
                                    </Link>
                                    <button
                                        v-if="!isTrashView"
                                        @click="startEdit(event)"
                                        class="text-emerald-500 hover:text-white text-[8px] uppercase font-bold"
                                    >
                                        [Edit]
                                    </button>
                                    <button
                                        v-if="!isTrashView"
                                        @click="confirmDelete(event.uuid)"
                                        class="text-red-500 hover:text-white text-[8px] uppercase font-bold"
                                    >
                                        [Delete]
                                    </button>
                                    <button
                                        v-if="isTrashView"
                                        @click="restoreEvent(event.uuid)"
                                        class="text-emerald-400 hover:text-white text-[8px] uppercase font-bold"
                                    >
                                        [Restore]
                                    </button>
                                    <button
                                        v-if="isTrashView"
                                        @click="hardDeleteEvent(event.uuid)"
                                        class="text-red-500 hover:text-white text-[8px] uppercase font-bold"
                                    >
                                        [Hard_Delete]
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
                            Move this event to trash?
                        </p>
                    </div>
                </div>
                <div class="p-6 pt-0 flex gap-4">
                    <button
                        @click="executeDelete"
                        :disabled="form.processing"
                        class="flex-1 py-3 bg-red-600/20 border-2 border-red-600 text-red-500 hover:bg-red-600 hover:text-white transition-all uppercase font-bold text-[9px]"
                    >
                        {{ form.processing ? 'ARCHIVING...' : 'ARCHIVE' }}
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
