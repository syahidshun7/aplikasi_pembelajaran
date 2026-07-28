<script setup>
import { Head, useForm, Link, usePage, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import AdminNavbar from '@/Components/AdminNavbar.vue';
import Swal from 'sweetalert2';

const props = defineProps({
    materi: [Array, Object], // Data dari tabel 'guides'
    studyGroups: Array,
    filters: Object,
    selectedStudyGroup: Object,
});
const page = usePage();
const isMentor = computed(() => String(page.props?.auth?.user?.role || '').toLowerCase() === 'mentor');
const firstStudyGroupId = computed(() => props.studyGroups?.[0]?.id ?? null);
const isScopedGroup = computed(() => Boolean(props.selectedStudyGroup?.uuid));
const indexRouteUrl = computed(() => props.selectedStudyGroup?.guides_url || route('materi.index'));

// State untuk UI
const isEditing = ref(false);
const editId = ref(null);
const showFormModal = ref(false);
const showDeleteModal = ref(false);
const materiIdToDelete = ref(null);

// 1. INITIALIZE SWEETALERT TOAST
const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
    background: '#1a1c2c',
    color: '#4ed4d4',
    didOpen: (toast) => {
        toast.addEventListener('mouseenter', Swal.stopTimer)
        toast.addEventListener('mouseleave', Swal.resumeTimer)
    }
});

// 2. FORM INITIALIZATION
const form = useForm({
    title: '',
    description: '',
    study_group_id: null,
    content_source: 'file',
    google_docs_url: '',
    video_url: '',
    file: null,
});
const mentorCannotSubmitGuide = computed(() => isMentor.value && !form.study_group_id);
const searchForm = useForm({
    search: props.filters?.search || '',
    view: props.filters?.view || 'active',
});
const isTrashView = computed(() => searchForm.view === 'trash');

// 3. COMPUTED
const getOldFileName = computed(() => {
    if (isEditing.value && editId.value) {
        const currentItem = guideItems.value.find(item => item.uuid === editId.value);
        if (currentItem && currentItem.file_path) {
            return currentItem.file_path.split('/').pop();
        }
    }
    return null;
});

const getOldGoogleDocsUrl = computed(() => {
    if (isEditing.value && editId.value) {
        const currentItem = guideItems.value.find(item => item.uuid === editId.value);
        if (currentItem && currentItem.google_docs_embed_url) {
            return currentItem.google_docs_embed_url;
        }
    }
    return null;
});

const getOldVideoUrl = computed(() => {
    if (isEditing.value && editId.value) {
        const currentItem = guideItems.value.find(item => item.uuid === editId.value);
        return currentItem?.video_embed_url || null;
    }
    return null;
});

const isGoogleDocsSource = computed(() => form.content_source === 'google_docs');
const isVideoSource = computed(() => form.content_source === 'video');

const guideItems = computed(() => {
    if (Array.isArray(props.materi)) return props.materi;
    return props.materi?.data || [];
});

const canPreviewAsUser = computed(() => {
    const role = String(page.props?.auth?.user?.role || '').toLowerCase();
    return ['admin', 'super_admin', 'mentor'].includes(role);
});

const paginationLinks = computed(() => {
    if (Array.isArray(props.materi)) return [];
    return props.materi?.links || [];
});

const goToPage = (url) => {
    if (!url) return;
    router.get(url, {}, {
        preserveState: true,
        preserveScroll: true,
    });
};

const applySearch = () => {
    router.get(indexRouteUrl.value, searchForm.data(), {
        preserveState: true,
        preserveScroll: true,
    });
};

const resetSearch = () => {
    searchForm.search = '';
    applySearch();
};

const setView = (view) => {
    if (searchForm.view === view) return;
    searchForm.view = view;
    cancelEdit();
    applySearch();
};

// 4. METHODS
const handleFileUpload = (e) => {
    form.file = e.target.files[0];
};

const handleContentSourceChange = () => {
    if (isGoogleDocsSource.value || isVideoSource.value) {
        form.file = null;
    }

    if (!isGoogleDocsSource.value) form.google_docs_url = '';
    if (!isVideoSource.value) form.video_url = '';
};

const startEdit = (item) => {
    isEditing.value = true;
    editId.value = item.uuid;
    form.title = item.title;
    form.description = item.description || '';
    form.study_group_id = item.study_group_id ?? null;
    form.content_source = item.video_embed_url
        ? 'video'
        : (item.google_docs_embed_url ? 'google_docs' : 'file');
    form.google_docs_url = item.google_docs_embed_url || '';
    form.video_url = item.video_embed_url || '';
    form.file = null;
    showFormModal.value = true;
    
    Toast.fire({
        icon: 'info',
        title: 'EDIT_MODE_ACTIVATED'
    });
};

const cancelEdit = () => {
    isEditing.value = false;
    editId.value = null;
    form.reset();
    form.study_group_id = isScopedGroup.value ? props.selectedStudyGroup.id : (isMentor.value ? firstStudyGroupId.value : null);
    form.content_source = 'file';
    form.google_docs_url = '';
    form.video_url = '';
    form.file = null;
    showFormModal.value = false;
};

const openCreateModal = () => {
    cancelEdit();
    showFormModal.value = true;
};

const submit = () => {
    if (isEditing.value) {
        // UPDATE LOGIC (POST with PATCH Method Trick)
        form.post(route('materi.update', editId.value), {
            forceFormData: true,
            onBefore: (request) => {
                request.data._method = 'patch';
            },
            onSuccess: () => {
                cancelEdit();
                Toast.fire({
                    icon: 'success',
                    title: 'SCROLL_UPDATED'
                });
            },
            onError: (errors) => {
                Swal.fire({
                    icon: 'error',
                    title: 'MODIFICATION_FAILED',
                    text: Object.values(errors)[0],
                    background: '#1a1c2c',
                    color: '#ff4d4d',
                    confirmButtonColor: '#4f46e5'
                });
            }
        });
    } else {
        // STORE LOGIC
        form.post(route('materi.store'), {
            forceFormData: true,
            onSuccess: () => {
                cancelEdit();
                Toast.fire({
                    icon: 'success',
                    title: 'KNOWLEDGE_INSCRIBED'
                });
            },
            onError: (errors) => {
                Swal.fire({
                    icon: 'warning',
                    title: 'INSCRIPTION_ERROR',
                    text: Object.values(errors)[0],
                    background: '#1a1c2c',
                    color: '#facc15',
                    confirmButtonColor: '#4f46e5'
                });
            }
        });
    }
};

const confirmDelete = (uuid) => {
    materiIdToDelete.value = uuid;
    showDeleteModal.value = true;
};

const executeDelete = () => {
    if (materiIdToDelete.value) {
        form.delete(route('materi.destroy', materiIdToDelete.value), {
            onSuccess: () => {
                showDeleteModal.value = false;
                materiIdToDelete.value = null;
                Toast.fire({
                    icon: 'success',
                    title: 'SCROLL_ARCHIVED'
                });
            },
            onError: () => {
                Toast.fire({
                    icon: 'error',
                    title: 'PURGE_FAILED'
                });
            }
        });
    }
};

const restoreGuide = (uuid) => {
    router.patch(route('materi.restore', uuid), {}, {
        preserveScroll: true,
        onSuccess: () => {
            Toast.fire({
                icon: 'success',
                title: 'SCROLL_RESTORED',
            });
        },
    });
};

const hardDeleteGuide = (uuid) => {
    Swal.fire({
        title: 'HARD_DELETE_SCROLL?',
        text: 'Guide akan dihapus permanen dan tidak bisa dipulihkan.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'DELETE_PERMANENTLY',
        cancelButtonText: 'CANCEL',
        background: '#1a1c2c',
        color: '#4ed4d4',
        confirmButtonColor: '#dc2626',
    }).then((result) => {
        if (!result.isConfirmed) return;

        router.delete(route('materi.force-destroy', uuid), {
            preserveScroll: true,
            onSuccess: () => {
                Toast.fire({
                    icon: 'success',
                    title: 'SCROLL_PERMANENTLY_DELETED',
                });
            },
        });
    });
};

// Pantau Flash Message dari Controller (with('message', ...))
watch(() => usePage().props.flash, (flash) => {
    if (flash?.message) {
        Toast.fire({
            icon: 'success',
            title: flash.message
        });
    }
}, { deep: true });

watch([isMentor, firstStudyGroupId], ([mentor, firstGroup]) => {
    if (isScopedGroup.value && props.selectedStudyGroup?.id && !form.study_group_id) {
        form.study_group_id = props.selectedStudyGroup.id;
        return;
    }
    if (mentor && !form.study_group_id) {
        form.study_group_id = firstGroup;
    }
}, { immediate: true });

</script>

<template>
    <Head title="GUIDE_ARCHIVE" />

    <div class="min-h-screen bg-[#0d1117] p-4 md:p-8 font-['Press_Start_2P'] text-[#4ed4d4] text-[10px] relative">
        <div class="max-w-7xl mx-auto space-y-8">

            <AdminNavbar />

            <div v-if="isScopedGroup" class="border-2 border-indigo-500/50 bg-indigo-950/20 p-4">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <p class="text-[8px] uppercase text-indigo-300">Study_Group_Context</p>
                        <h1 class="mt-2 text-[13px] uppercase text-white">{{ selectedStudyGroup.name }}</h1>
                    </div>
                    <Link :href="selectedStudyGroup.back_url" class="border border-slate-600 px-3 py-2 text-[8px] uppercase text-slate-300 hover:text-white">
                        Back_To_Group
                    </Link>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3 border-b-4 border-indigo-900 pb-4">
                <h1 class="text-base sm:text-xl uppercase tracking-widest animate-pulse">Guide_Library_System</h1>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="openCreateModal"
                        class="inline-flex items-center justify-center px-3 py-2 border border-indigo-500 bg-indigo-900/20 text-indigo-300 hover:bg-indigo-500 hover:text-black transition-colors uppercase text-[9px] sm:text-[10px]"
                    >
                        [New_Guide]
                    </button>
                    <Link href="/dashboard" class="inline-flex items-center justify-center px-3 py-2 border border-slate-600 bg-slate-900/40 text-slate-300 hover:text-white transition-colors uppercase text-[9px] sm:text-[10px]">[Back_to_HQ]
                    </Link>
                </div>
            </div>

            <div class="grid grid-cols-12 gap-8">
                <div
                    v-if="showFormModal"
                    class="fixed inset-0 z-[120] flex items-center justify-center bg-black/85 backdrop-blur-sm p-4"
                >
                    <div class="w-full max-w-4xl max-h-[90vh] overflow-y-auto modal-scroll">
                    <div class="rpg-panel" :class="isEditing ? 'border-green-500/50' : 'border-indigo-500/50'">
                        <h2 class="mb-6 uppercase tracking-tighter"
                            :class="isEditing ? 'text-green-500' : 'text-indigo-400'">
                            >> {{ isEditing ? 'MODIFY_SCROLL_ID_' + editId.substring(0, 8) : 'INSCRIBE_NEW_KNOWLEDGE' }}
                        </h2>

                        <form @submit.prevent="submit" class="space-y-6">
                            <div>
                                <label class="block mb-2 text-white">TITLE_OF_SCROLL:</label>
                                <input v-model="form.title" type="text"
                                    class="w-full bg-black border-2 border-slate-700 p-2 focus:border-indigo-400 outline-none text-indigo-400 uppercase"
                                    placeholder="Enter scroll title..." required>
                            </div>

                            <div>
                                <label class="block mb-2 text-white">KNOWLEDGE_SUMMARY:</label>
                                <textarea v-model="form.description"
                                    class="w-full bg-black border-2 border-slate-700 p-2 text-[12px] font-sans text-slate-200 focus:border-indigo-400 focus:ring-0"
                                    placeholder="Describe the content..."
                                    style="resize: vertical; min-height: 140px;"></textarea>
                            </div>

                            <div>
                                <label class="block mb-2 text-white">ASSIGN_TO_PARTY:</label>
                                <select v-model="form.study_group_id"
                                    class="w-full bg-black border-2 border-slate-700 p-2 focus:border-emerald-400 outline-none text-emerald-400 uppercase">
                                    <option v-if="!isMentor && !isScopedGroup" :value="null">-- GLOBAL_GUIDE (PUBLIC) --</option>
                                    <option v-if="isMentor && !studyGroups.length" :value="null" disabled>-- NO_STUDY_GROUP_AVAILABLE --</option>
                                    <option v-for="group in studyGroups" :key="group.id" :value="group.id">
                                        >> PARTY: {{ group.name }}
                                    </option>
                                </select>
                            </div>

                            <div>
                                <label class="block mb-2 text-white">CONTENT_SOURCE:</label>
                                <select
                                    v-model="form.content_source"
                                    class="w-full bg-black border-2 border-slate-700 p-2 focus:border-cyan-400 outline-none text-cyan-300 uppercase"
                                    @change="handleContentSourceChange"
                                >
                                    <option value="file">-- FILE_UPLOAD --</option>
                                    <option value="google_docs">-- GOOGLE_DOCS_EMBED --</option>
                                    <option value="video">-- VIDEO_LINK --</option>
                                </select>
                                <p class="mt-2 text-[7px] text-slate-500 uppercase">
                                    Upload file, embed Google Docs, atau putar video dari YouTube/Google Drive.
                                </p>
                            </div>

                            <div v-if="isGoogleDocsSource">
                                <label class="block mb-2 text-indigo-400">GOOGLE_DOCS_URL:</label>
                                <input
                                    v-model="form.google_docs_url"
                                    type="url"
                                    class="w-full bg-black border-2 border-slate-700 p-2 focus:border-indigo-400 outline-none text-indigo-300"
                                    placeholder="https://docs.google.com/document/d/..."
                                >
                                <div v-if="isEditing && getOldGoogleDocsUrl" class="mt-3 pt-3 border-t border-slate-800">
                                    <p class="text-[7px] text-slate-500 uppercase tracking-tighter">
                                        Current_Embed_URL:
                                        <span class="text-yellow-500 italic break-all">{{ getOldGoogleDocsUrl }}</span>
                                    </p>
                                </div>
                            </div>

                            <div v-else-if="isVideoSource">
                                <label class="block mb-2 text-red-300">VIDEO_URL:</label>
                                <input
                                    v-model="form.video_url"
                                    type="url"
                                    class="w-full bg-black border-2 border-slate-700 p-2 focus:border-red-400 outline-none text-red-200"
                                    placeholder="https://www.youtube.com/watch?v=... atau https://drive.google.com/file/d/..."
                                >
                                <p class="mt-2 text-[7px] text-slate-500 uppercase leading-relaxed">
                                    Video Google Drive harus dapat diakses oleh pengguna yang memiliki link.
                                </p>
                                <div v-if="isEditing && getOldVideoUrl" class="mt-3 border-t border-slate-800 pt-3">
                                    <p class="break-all text-[7px] uppercase text-slate-500">
                                        Current_Video: <span class="text-yellow-500">{{ getOldVideoUrl }}</span>
                                    </p>
                                </div>
                            </div>

                            <div v-else>
                                <label class="block mb-2 text-indigo-400">ATTACHMENT_PROTOCOL:</label>
                                <div
                                    class="bg-black/40 border-2 border-dashed border-slate-700 p-4 text-center relative">
                                    <input type="file" @change="handleFileUpload"
                                        class="text-[7px] text-slate-500 file:mr-4 file:py-1 file:px-2 file:border-2 file:border-indigo-900 file:bg-indigo-950 file:text-indigo-400 file:uppercase cursor-pointer w-full">

                                    <div v-if="isEditing && getOldFileName" class="mt-3 pt-3 border-t border-slate-800">
                                        <p class="text-[7px] text-slate-500 uppercase tracking-tighter">
                                            Current_Vault_File:
                                            <span class="text-yellow-500 italic">{{ getOldFileName }}</span>
                                        </p>
                                        <p class="text-[6px] text-indigo-900 mt-1 uppercase italic underline">
                                            *Upload new to overwrite current scroll
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="flex gap-2">
                                <button type="submit" :disabled="form.processing || mentorCannotSubmitGuide"
                                    class="flex-1 py-3 border-2 uppercase font-bold transition-all"
                                    :class="isEditing ? 'border-green-600 text-green-500 hover:bg-green-600 hover:text-black' : 'border-indigo-500 text-indigo-400 hover:bg-indigo-500 hover:text-black'">
                                    {{ form.processing ? 'SYNCING...' : (isEditing ? 'UPDATE_SCROLL' : 'ISSUE_GUIDE') }}
                                </button>
                                <button @click="cancelEdit" type="button"
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
                            >> {{ isTrashView ? 'TRASH_ARCHIVE_BOARD' : 'ARCHIVE_REGISTRY_BOARD' }}
                        </h2>
                        <div class="mb-4 flex gap-2">
                            <button
                                @click="setView('active')"
                                class="px-3 py-2 border-2 uppercase"
                                :class="isTrashView ? 'border-slate-600 text-slate-300 hover:bg-slate-700 hover:text-white' : 'border-indigo-400 text-indigo-300 bg-indigo-900/20'"
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
                                v-model="searchForm.search"
                                type="text"
                                placeholder="SEARCH GUIDE / PARTY"
                                class="flex-1 bg-black border-2 border-slate-700 p-2 text-cyan-400 uppercase outline-none"
                                @keyup.enter="applySearch"
                            />
                            <button @click="applySearch"
                                class="px-3 py-2 border-2 border-cyan-400 text-cyan-400 hover:bg-cyan-400 hover:text-black uppercase">
                                APPLY
                            </button>
                            <button @click="resetSearch"
                                class="px-3 py-2 border-2 border-slate-600 text-slate-300 hover:bg-slate-700 hover:text-white uppercase">
                                RESET
                            </button>
                        </div>

                        <div class="space-y-4 max-h-[560px] overflow-y-auto pr-2 custom-scroll">
                            <div v-for="item in guideItems" :key="item.uuid"
                                class="flex flex-col p-4 bg-slate-900/50 border-l-4 border-indigo-500 hover:bg-slate-800 transition-all">

                                <div class="flex justify-between items-start mb-2">
                                    <div class="flex-1">
                                        <div class="text-[8px] text-slate-500 mb-1 uppercase tracking-tighter">REF_ID:
                                            {{ item.uuid }}</div>
                                        <div class="text-white uppercase tracking-tight">{{ item.title }}</div>
                                        <div class="text-[8px] mt-1 uppercase"
                                            :class="item.study_group_id ? 'text-emerald-400' : 'text-cyan-400'">
                                            {{ item.study_group_id ? `PARTY: ${item.study_group?.name || 'UNKNOWN'}` : 'GLOBAL_GUIDE' }}
                                        </div>
                                    </div>
                                    <div
                                        v-if="item.file_path || item.google_docs_embed_url || item.video_embed_url"
                                        class="text-indigo-400 text-[7px] animate-pulse"
                                    >
                                        {{ item.video_embed_url ? '[VIDEO_EMBED]' : (item.google_docs_embed_url ? '[GOOGLE_DOCS_EMBED]' : '[DOC_ATTACHED]') }}
                                    </div>
                                </div>

                                <div v-if="isTrashView" class="text-[8px] text-amber-400 mb-2 uppercase tracking-tighter">
                                    DELETED_AT: {{ new Date(item.deleted_at).toLocaleString('id-ID') }}
                                </div>

                                <div v-if="item.description"
                                    class="text-[7px] text-slate-500 italic mb-4 border-t border-slate-800 pt-2 leading-loose">
                                    > {{ item.description }}
                                </div>

                                <div class="flex gap-4 self-end mt-2">
                                    <Link v-if="canPreviewAsUser && !isTrashView" :href="route('guides.user-preview', item.uuid)"
                                        class="text-cyan-400 hover:text-white text-[8px] uppercase font-bold">[User_View]</Link>
                                    <button v-if="!isTrashView" @click="startEdit(item)"
                                        class="text-green-500 hover:text-white text-[8px] uppercase font-bold">[Edit]</button>
                                    <button v-if="!isTrashView" @click="confirmDelete(item.uuid)"
                                        class="text-red-500 hover:text-white text-[8px] uppercase font-bold">[Purge]</button>
                                    <button v-if="isTrashView" @click="restoreGuide(item.uuid)"
                                        class="text-emerald-400 hover:text-white text-[8px] uppercase font-bold">[Restore]</button>
                                    <button v-if="isTrashView" @click="hardDeleteGuide(item.uuid)"
                                        class="text-red-500 hover:text-white text-[8px] uppercase font-bold">[Hard_Delete]</button>
                                </div>
                            </div>

                            <div v-if="guideItems.length === 0"
                                class="py-12 text-center text-slate-700 italic uppercase text-[8px]">
                                The archive vaults are currently empty...
                            </div>
                        </div>

                        <div v-if="paginationLinks.length > 0" class="mt-6 flex flex-wrap gap-2">
                            <button
                                v-for="(link, index) in paginationLinks"
                                :key="`${index}-${link.label}`"
                                @click="goToPage(link.url)"
                                :disabled="!link.url"
                                class="px-3 py-1 border text-[8px] uppercase transition-all"
                                :class="[
                                    link.active
                                        ? 'border-indigo-400 text-indigo-300 bg-indigo-900/20'
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

        <div v-if="showDeleteModal"
            class="fixed inset-0 z-[100] flex items-center justify-center bg-black/90 backdrop-blur-md p-4">
            <div
                class="w-full max-w-lg bg-[#121212] border-2 border-red-600 shadow-[0_0_30px_rgba(220,38,38,0.3)] overflow-hidden rounded-lg">
                <div class="bg-red-600/10 border-b-2 border-red-600 p-4 flex items-center gap-3">
                    <span class="text-red-500 text-lg">⚠</span>
                    <h2 class="text-red-500 font-bold uppercase tracking-tighter text-[10px]">» WARNING: PURGE_PROTOCOL
                    </h2>
                </div>
                <div class="p-8 space-y-6">
                    <div class="border-l-2 border-red-900 pl-4">
                        <p class="text-slate-200 text-[10px] leading-relaxed uppercase">Are you sure you want to
                            move this knowledge scroll to trash?</p>
                    </div>
                </div>
                <div class="p-6 pt-0 flex gap-4">
                    <button @click="executeDelete" :disabled="form.processing"
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
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

/* Custom Scrollbar for RPG feel */
.custom-scroll::-webkit-scrollbar {
    width: 4px;
}
.custom-scroll::-webkit-scrollbar-track {
    background: #0d1117;
}
.custom-scroll::-webkit-scrollbar-thumb {
    background: #4f46e5;
}
</style>
