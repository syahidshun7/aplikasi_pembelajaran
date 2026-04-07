<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CreationDocumentEditor from '@/Components/Creations/CreationDocumentEditor.vue';
import { toast } from '@/Utils/Alert';

const MAX_CREATION_PHOTOS = 8;
const MAX_PHOTO_SIZE_BYTES = 4 * 1024 * 1024;
const ALLOWED_PHOTO_MIME_TYPES = ['image/jpeg', 'image/png', 'image/x-png', 'image/webp'];
const ALLOWED_PHOTO_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];
const PROJECT_STATUS_OPTIONS = ['crafting', 'refining', 'finished'];

const props = defineProps({
    mode: {
        type: String,
        required: true,
    },
    creationId: {
        type: Number,
        default: null,
    },
    categories: {
        type: Array,
        default: () => [],
    },
});

const titleInputRef = ref(null);
const featuredImageInputRef = ref(null);
const photoInputRef = ref(null);
const mainWorkspaceRef = ref(null);
const pageMode = ref(String(props.mode || 'create'));
const activeCreationId = ref(props.creationId ? Number(props.creationId) : null);
const sidebarCollapsed = ref(false);
const loading = ref(Boolean(activeCreationId.value));
const saving = ref(false);
const autosaving = ref(false);
const isResizingWorkspace = ref(false);
const workspaceHeight = ref(0);
const uploadingFeaturedImage = ref(false);
const editorUploading = ref(false);
const lastSavedAt = ref('');
const photoErrorMessage = ref('');
const selectedPhotoFiles = ref([]);
const newPhotoPreviews = ref([]);
const existingPhotos = ref([]);
const removedPhotoIds = ref([]);
const canManageCollaboration = ref(true);
const MIN_WORKSPACE_HEIGHT = 420;
const MAX_WORKSPACE_HEIGHT = 8000;

const form = reactive({
    title: '',
    content: '<p></p>',
    link: '',
    category_id: '',
    tags_text: '',
    featured_image: '',
    status: 'finished',
    progress: 100,
    publication_status: 'draft',
    is_open_for_collaboration: true,
});

const persistKey = computed(() => `creation-editor-${activeCreationId.value || 'new'}`);
const formStorageKey = computed(() => `creation.form.state.${activeCreationId.value || 'new'}`);
const uploadUrl = computed(() => route('api.upload.store', {}, false));
const editorUploadBusy = computed(() => editorUploading.value || uploadingFeaturedImage.value);
const titleCount = computed(() => String(form.title || '').length);
const activeExistingPhotoCount = computed(() => existingPhotos.value.filter((photo) => !isExistingPhotoRemoved(photo.id)).length);
const availablePhotoSlots = computed(() => Math.max(0, MAX_CREATION_PHOTOS - activeExistingPhotoCount.value));
const workspaceMainStyle = computed(() => {
    if (!workspaceHeight.value) {
        return {};
    }

    if (typeof window !== 'undefined' && !window.matchMedia('(min-width: 1100px)').matches) {
        return {};
    }

    return {
        height: `${workspaceHeight.value}px`,
        maxHeight: `${workspaceHeight.value}px`,
    };
});

let autosaveTimer = null;
let scrollTimer = null;
let lastSavedFingerprint = '';
let resizeStartY = 0;
let resizeStartHeight = 0;

const normalizeTags = (rawValue) => {
    return String(rawValue || '')
        .split(',')
        .map((tag) => tag.trim())
        .filter(Boolean)
        .filter((tag, index, list) => list.indexOf(tag) === index);
};

const normalizeProjectStatus = (value) => {
    const normalized = String(value || 'finished').trim().toLowerCase();
    return PROJECT_STATUS_OPTIONS.includes(normalized) ? normalized : 'finished';
};

const clampProgress = (value) => {
    const normalized = Number(value);
    if (!Number.isFinite(normalized)) {
        return 0;
    }

    return Math.min(100, Math.max(0, Math.round(normalized)));
};

const updateProgress = (value) => {
    form.progress = clampProgress(value);
};

const setProjectStatus = (value) => {
    form.status = normalizeProjectStatus(value);
    if (form.status === 'finished' && Number(form.progress || 0) < 100) {
        form.progress = 100;
    }
};

const toBooleanFlag = (value, fallback = false) => {
    if (typeof value === 'boolean') {
        return value;
    }

    if (typeof value === 'number') {
        return value === 1;
    }

    const normalized = String(value ?? '').trim().toLowerCase();
    if (['1', 'true', 'yes', 'on', 'open'].includes(normalized)) {
        return true;
    }
    if (['0', 'false', 'no', 'off', 'closed'].includes(normalized)) {
        return false;
    }

    return Boolean(fallback);
};

const formSnapshot = () => ({
    title: String(form.title || ''),
    content: String(form.content || '<p></p>'),
    link: String(form.link || ''),
    category_id: form.category_id ? Number(form.category_id) : null,
    tags: normalizeTags(form.tags_text),
    featured_image: String(form.featured_image || ''),
    status: normalizeProjectStatus(form.status),
    progress: clampProgress(form.progress),
    publication_status: String(form.publication_status || 'draft'),
    is_open_for_collaboration: Boolean(form.is_open_for_collaboration),
    photos: selectedPhotoFiles.value.map((file) => `${file.name}:${file.size}:${file.lastModified}`),
    removed_photo_ids: [...removedPhotoIds.value].map((id) => Number(id)).sort((left, right) => left - right),
});

const hasMeaningfulContent = () => {
    const plainText = String(form.content || '').replace(/<[^>]*>/g, '').trim();
    return String(form.title || '').trim() !== '' || plainText !== '';
};

const canResizeWorkspace = () => (
    typeof window !== 'undefined' && window.matchMedia('(min-width: 1100px)').matches
);

const clampWorkspaceHeight = (height) => (
    Math.min(MAX_WORKSPACE_HEIGHT, Math.max(MIN_WORKSPACE_HEIGHT, Number(height || 0)))
);

const getPointerClientY = (event) => {
    if ('touches' in event && event.touches?.length) {
        return Number(event.touches[0].clientY || 0);
    }

    if ('changedTouches' in event && event.changedTouches?.length) {
        return Number(event.changedTouches[0].clientY || 0);
    }

    return Number(event.clientY || 0);
};

const stopWorkspaceResize = () => {
    if (!isResizingWorkspace.value || typeof window === 'undefined') {
        return;
    }

    isResizingWorkspace.value = false;
    window.removeEventListener('mousemove', onWorkspaceResizeMove);
    window.removeEventListener('mouseup', stopWorkspaceResize);
    window.removeEventListener('touchmove', onWorkspaceResizeMove);
    window.removeEventListener('touchend', stopWorkspaceResize);

    document.body.style.userSelect = '';
    document.body.style.cursor = '';
    saveFormStateLocally();
};

const onWorkspaceResizeMove = (event) => {
    if (!isResizingWorkspace.value) {
        return;
    }

    if (event.cancelable) {
        event.preventDefault();
    }

    const pointerY = getPointerClientY(event);
    const delta = pointerY - resizeStartY;
    workspaceHeight.value = clampWorkspaceHeight(resizeStartHeight + delta);
};

const startWorkspaceResize = (event) => {
    if (!mainWorkspaceRef.value || !canResizeWorkspace() || typeof window === 'undefined') {
        return;
    }

    if (event.cancelable) {
        event.preventDefault();
    }

    isResizingWorkspace.value = true;
    resizeStartY = getPointerClientY(event);
    resizeStartHeight = clampWorkspaceHeight(
        Math.round(mainWorkspaceRef.value.getBoundingClientRect().height || workspaceHeight.value || MIN_WORKSPACE_HEIGHT),
    );
    workspaceHeight.value = resizeStartHeight;

    window.addEventListener('mousemove', onWorkspaceResizeMove);
    window.addEventListener('mouseup', stopWorkspaceResize);
    window.addEventListener('touchmove', onWorkspaceResizeMove, { passive: false });
    window.addEventListener('touchend', stopWorkspaceResize);

    document.body.style.userSelect = 'none';
    document.body.style.cursor = 'nwse-resize';
};

const saveFormStateLocally = () => {
    if (typeof window === 'undefined') {
        return;
    }

    const payload = {
        title: String(form.title || ''),
        content: String(form.content || '<p></p>'),
        link: String(form.link || ''),
        category_id: form.category_id ? Number(form.category_id) : null,
        tags: normalizeTags(form.tags_text),
        featured_image: String(form.featured_image || ''),
        status: normalizeProjectStatus(form.status),
        progress: clampProgress(form.progress),
        publication_status: String(form.publication_status || 'draft'),
        is_open_for_collaboration: Boolean(form.is_open_for_collaboration),
        removed_photo_ids: [...removedPhotoIds.value].map((id) => Number(id)),
        workspace_height: Number(workspaceHeight.value || 0),
        workspaceScrollTop: Number(mainWorkspaceRef.value?.scrollTop || 0),
        scrollY: window.scrollY || 0,
        savedAt: Date.now(),
    };

    window.localStorage.setItem(formStorageKey.value, JSON.stringify(payload));
};

const loadLocalFormState = () => {
    if (typeof window === 'undefined') {
        return null;
    }

    try {
        const raw = window.localStorage.getItem(formStorageKey.value);
        if (!raw) {
            return null;
        }

        const parsed = JSON.parse(raw);
        if (!parsed || typeof parsed !== 'object') {
            return null;
        }

        return parsed;
    } catch {
        return null;
    }
};

const migrateLocalStateKey = (nextId) => {
    if (typeof window === 'undefined' || !nextId) {
        return;
    }

    const previousKey = formStorageKey.value;
    const nextKey = `creation.form.state.${nextId}`;

    if (previousKey === nextKey) {
        return;
    }

    const existing = window.localStorage.getItem(previousKey);
    if (existing) {
        window.localStorage.setItem(nextKey, existing);
        window.localStorage.removeItem(previousKey);
    }
};

const applyStateToForm = (payload) => {
    if (!payload) {
        return;
    }

    form.title = String(payload.title || '');
    form.content = String(payload.content || '<p></p>');
    form.link = String(payload.link || '');
    form.category_id = payload.category_id ? String(payload.category_id) : '';
    form.tags_text = Array.isArray(payload.tags)
        ? payload.tags.join(', ')
        : String(payload.tags_text || '');
    form.featured_image = String(payload.featured_image || '');
    form.status = normalizeProjectStatus(payload.status);
    form.progress = clampProgress(payload.progress);
    form.publication_status = String(payload.publication_status || 'draft');
    form.is_open_for_collaboration = toBooleanFlag(payload.is_open_for_collaboration, true);
    removedPhotoIds.value = Array.isArray(payload.removed_photo_ids)
        ? payload.removed_photo_ids.map((id) => Number(id)).filter((id) => id > 0)
        : [];
};

const applyEditorPreferences = (payload) => {
    sidebarCollapsed.value = false;

    const preferredWorkspaceHeight = Number(payload?.workspace_height || 0);
    workspaceHeight.value = Number.isFinite(preferredWorkspaceHeight) && preferredWorkspaceHeight >= MIN_WORKSPACE_HEIGHT
        ? clampWorkspaceHeight(preferredWorkspaceHeight)
        : 0;
};

const clearNewPhotoPreviews = () => {
    newPhotoPreviews.value.forEach((preview) => {
        if (preview?.url) {
            URL.revokeObjectURL(preview.url);
        }
    });

    newPhotoPreviews.value = [];
};

const setSelectedPhotoPreviews = () => {
    clearNewPhotoPreviews();
    newPhotoPreviews.value = selectedPhotoFiles.value.map((file) => ({
        key: `${file.name}-${file.size}-${file.lastModified}`,
        name: file.name,
        url: URL.createObjectURL(file),
    }));
};

const isAllowedPhotoFile = (file) => {
    const fileName = String(file?.name || '');
    const extension = fileName.includes('.') ? fileName.split('.').pop().toLowerCase() : '';
    const mimeType = String(file?.type || '').toLowerCase();

    return ALLOWED_PHOTO_MIME_TYPES.includes(mimeType) || ALLOWED_PHOTO_EXTENSIONS.includes(extension);
};

const isExistingPhotoRemoved = (photoId) => removedPhotoIds.value.map((id) => Number(id)).includes(Number(photoId));

const validateSelectedPhotoFiles = (files) => {
    const normalizedFiles = Array.isArray(files) ? files : [];
    const validFiles = [];
    const slots = availablePhotoSlots.value;
    let errorMessage = '';

    for (const file of normalizedFiles) {
        if (validFiles.length >= slots) {
            errorMessage = slots > 0
                ? `Maksimal ${slots} thumbnail baru bisa ditambahkan sekarang.`
                : 'Batas 8 thumbnail sudah penuh. Hapus salah satu dulu sebelum menambah lagi.';
            break;
        }

        if (!isAllowedPhotoFile(file)) {
            errorMessage = `File "${file.name}" harus berformat JPG, JPEG, PNG, atau WEBP.`;
            break;
        }

        if (Number(file.size || 0) > MAX_PHOTO_SIZE_BYTES) {
            errorMessage = `File "${file.name}" melebihi batas 4MB.`;
            break;
        }

        validFiles.push(file);
    }

    selectedPhotoFiles.value = validFiles;
    setSelectedPhotoPreviews();
    photoErrorMessage.value = errorMessage;
};

const onPhotoInputChange = (event) => {
    validateSelectedPhotoFiles(Array.from(event?.target?.files || []));
};

const toggleRemoveExistingPhoto = (photoId) => {
    const current = removedPhotoIds.value.map((id) => Number(id));
    const target = Number(photoId);
    removedPhotoIds.value = current.includes(target)
        ? current.filter((id) => id !== target)
        : [...current, target];

    validateSelectedPhotoFiles(selectedPhotoFiles.value);
};

const fetchCreation = async () => {
    if (!activeCreationId.value) {
        canManageCollaboration.value = true;
        const localState = loadLocalFormState();
        if (localState) {
            applyStateToForm(localState);
            applyEditorPreferences(localState);
        }
        loading.value = false;
        return;
    }

    loading.value = true;

    try {
        const response = await window.axios.get(route('api.creations.show', { creation: activeCreationId.value }, false));
        const creation = response.data?.data || null;

        if (!creation) {
            throw new Error('CREATION_NOT_FOUND');
        }

        applyStateToForm({
            title: creation.title,
            content: creation.content || creation.description || '<p></p>',
            link: creation.link,
            category_id: creation.category_id,
            tags: creation.tags || [],
            featured_image: creation.featured_image,
            status: creation.status,
            progress: creation.progress,
            publication_status: creation.publication_status || (creation.is_public ? 'publish' : 'draft'),
            is_open_for_collaboration: creation.is_open_for_collaboration,
        });
        canManageCollaboration.value = Boolean(creation.can_manage_collaboration ?? creation.can_delete ?? false);
        existingPhotos.value = Array.isArray(creation.photos) ? creation.photos : [];
        removedPhotoIds.value = [];

        const serverFingerprint = JSON.stringify(formSnapshot());
        const localState = loadLocalFormState();
        if (localState) {
            applyStateToForm(localState);
            applyEditorPreferences(localState);
            lastSavedFingerprint = serverFingerprint;
        } else {
            lastSavedFingerprint = JSON.stringify(formSnapshot());
        }
    } catch (error) {
        toast.error('LOAD_FAILED', 'Unable to load creation editor.');
    } finally {
        loading.value = false;
    }
};

const persistCreation = async ({ publicationStatus = form.publication_status, notify = false, autosave = false } = {}) => {
    if (!hasMeaningfulContent()) {
        return null;
    }

    if (photoErrorMessage.value) {
        if (!autosave) {
            toast.error('SAVE_FAILED', photoErrorMessage.value);
        }
        return null;
    }

    const payload = {
        title: String(form.title || '').trim() || 'Untitled creation',
        content: String(form.content || '<p></p>'),
        link: String(form.link || '').trim() || null,
        category_id: form.category_id ? Number(form.category_id) : null,
        tags: normalizeTags(form.tags_text),
        featured_image: String(form.featured_image || '').trim() || null,
        publication_status: publicationStatus,
        is_public: publicationStatus === 'publish',
        status: normalizeProjectStatus(form.status),
        progress: clampProgress(form.progress),
        is_open_for_collaboration: Boolean(form.is_open_for_collaboration),
    };

    const fingerprint = JSON.stringify(payload);
    if (autosave && fingerprint === lastSavedFingerprint) {
        return null;
    }

    if (autosave) {
        autosaving.value = true;
    } else {
        saving.value = true;
    }

    try {
        const requestPayload = new FormData();
        requestPayload.append('title', payload.title);
        requestPayload.append('content', payload.content);
        requestPayload.append('publication_status', payload.publication_status);
        requestPayload.append('is_public', payload.is_public ? '1' : '0');
        requestPayload.append('status', String(payload.status));
        requestPayload.append('progress', String(payload.progress));
        requestPayload.append('is_open_for_collaboration', payload.is_open_for_collaboration ? '1' : '0');
        requestPayload.append('link', String(payload.link || ''));

        if (payload.category_id) {
            requestPayload.append('category_id', String(payload.category_id));
        }

        if (payload.featured_image) {
            requestPayload.append('featured_image', payload.featured_image);
        }

        payload.tags.forEach((tag) => {
            requestPayload.append('tags[]', tag);
        });

        removedPhotoIds.value.forEach((id) => {
            requestPayload.append('remove_photo_ids[]', String(id));
        });

        selectedPhotoFiles.value.forEach((file) => {
            requestPayload.append('photos[]', file);
        });

        let response;

        if (activeCreationId.value) {
            requestPayload.append('_method', 'PUT');
            response = await window.axios.post(route('api.creations.update', { creation: activeCreationId.value }, false), requestPayload, {
                headers: {
                    'Content-Type': 'multipart/form-data',
                },
            });
        } else {
            response = await window.axios.post(route('api.creations.store', {}, false), requestPayload, {
                headers: {
                    'Content-Type': 'multipart/form-data',
                },
            });
        }

        const saved = response.data?.data || null;
        if (saved?.id && !activeCreationId.value) {
            const nextId = Number(saved.id);
            migrateLocalStateKey(nextId);
            activeCreationId.value = nextId;
            pageMode.value = 'edit';
            if (typeof window !== 'undefined') {
                window.history.replaceState(window.history.state, '', route('profile.creations.edit', { creation: nextId }));
            }
        }

        form.publication_status = String(saved?.publication_status || payload.publication_status);
        form.link = String(saved?.link || payload.link || '');
        form.status = normalizeProjectStatus(saved?.status || payload.status);
        form.progress = clampProgress(saved?.progress ?? payload.progress);
        form.is_open_for_collaboration = toBooleanFlag(
            saved?.is_open_for_collaboration,
            payload.is_open_for_collaboration,
        );
        existingPhotos.value = Array.isArray(saved?.photos) ? saved.photos : [];
        removedPhotoIds.value = [];
        selectedPhotoFiles.value = [];
        clearNewPhotoPreviews();
        photoErrorMessage.value = '';
        if (photoInputRef.value) {
            photoInputRef.value.value = '';
        }
        lastSavedFingerprint = JSON.stringify(formSnapshot());
        lastSavedAt.value = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
        saveFormStateLocally();

        if (notify) {
            toast.success(publicationStatus === 'publish' ? 'PUBLISHED' : 'SAVED', publicationStatus === 'publish' ? 'Creation published.' : 'Draft saved.');
        }

        return saved;
    } catch (error) {
        if (!autosave) {
            const validationMessage = error?.response?.data?.errors?.photos?.[0]
                || error?.response?.data?.errors?.['photos.0']?.[0]
                || error?.response?.data?.message
                || 'Unable to save creation.';
            toast.error('SAVE_FAILED', String(validationMessage));
        }
        return null;
    } finally {
        autosaving.value = false;
        saving.value = false;
    }
};

const handleManualSave = async () => {
    await persistCreation({ publicationStatus: 'draft', notify: true });
};

const handlePublish = async () => {
    form.publication_status = 'publish';
    await persistCreation({ publicationStatus: 'publish', notify: true });
};

const openFeaturedImagePicker = () => {
    featuredImageInputRef.value?.click();
};

const openPhotoPicker = () => {
    photoInputRef.value?.click();
};

const toggleSidebar = () => {
    sidebarCollapsed.value = !sidebarCollapsed.value;
    saveFormStateLocally();
};

const uploadFeaturedImage = async (event) => {
    const file = event?.target?.files?.[0];
    if (!file) {
        return;
    }

    const formData = new FormData();
    formData.append('image', file);
    uploadingFeaturedImage.value = true;

    try {
        const response = await window.axios.post(uploadUrl.value, formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        });
        form.featured_image = String(response.data?.url || '');
        saveFormStateLocally();
        toast.success('IMAGE_READY', 'Featured image updated.');
    } catch (error) {
        toast.error('UPLOAD_FAILED', 'Unable to upload featured image.');
    } finally {
        uploadingFeaturedImage.value = false;
        if (featuredImageInputRef.value) {
            featuredImageInputRef.value.value = '';
        }
    }
};

const restoreScrollPosition = async () => {
    const localState = loadLocalFormState();
    await nextTick();

    const workspaceScrollTop = Number(localState?.workspaceScrollTop || 0);
    if (mainWorkspaceRef.value && workspaceScrollTop > 0) {
        mainWorkspaceRef.value.scrollTo({ top: workspaceScrollTop, behavior: 'auto' });
        return;
    }

    const scrollY = Number(localState?.scrollY || 0);
    if (scrollY > 0 && typeof window !== 'undefined') {
        window.scrollTo({ top: scrollY, behavior: 'auto' });
    }
};

watch(
    () => formSnapshot(),
    () => {
        saveFormStateLocally();
    },
    { deep: true },
);

onMounted(async () => {
    await fetchCreation();
    await restoreScrollPosition();
    await nextTick();
    titleInputRef.value?.focus();

    autosaveTimer = window.setInterval(() => {
        if (saving.value || autosaving.value || editorUploadBusy.value) {
            return;
        }
        persistCreation({ publicationStatus: form.publication_status || 'draft', autosave: true });
    }, 600000);

    scrollTimer = window.setInterval(() => {
        saveFormStateLocally();
    }, 1500);
});

onBeforeUnmount(() => {
    stopWorkspaceResize();

    if (autosaveTimer) {
        window.clearInterval(autosaveTimer);
        autosaveTimer = null;
    }

    if (scrollTimer) {
        window.clearInterval(scrollTimer);
        scrollTimer = null;
    }

    clearNewPhotoPreviews();
    saveFormStateLocally();
});
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="pageMode === 'edit' ? 'Edit Creation' : 'Create Creation'" />

        <div class="creation-editor-page font-['Press_Start_2P'] text-[#4ed4d4]">
            <div class="creation-editor-page__header">
                <div>
                    <p class="creation-editor-page__eyebrow">Creation Studio</p>
                    <h1 class="creation-editor-page__title">{{ pageMode === 'edit' ? 'Edit Documentation' : 'Write Documentation' }}</h1>
                </div>

                <div class="creation-editor-page__actions">
                    <span v-if="autosaving" class="creation-editor-page__meta">Autosaving...</span>
                    <span v-else-if="lastSavedAt" class="creation-editor-page__meta">Saved {{ lastSavedAt }}</span>
                    <button
                        type="button"
                        class="creation-editor-page__toggle"
                        :class="{
                            'creation-editor-page__toggle--show': sidebarCollapsed,
                            'creation-editor-page__toggle--hide': !sidebarCollapsed,
                        }"
                        @click="toggleSidebar"
                    >
                        <i class="fi text-[10px]" :class="sidebarCollapsed ? 'fi-rr-layout-fluid' : 'fi-rr-apps'" />
                        <span>{{ sidebarCollapsed ? 'SHOW PANEL' : 'HIDE PANEL' }}</span>
                    </button>
                    <Link :href="route('profile.creations')" class="creation-editor-page__link">
                        <i class="fi fi-rr-arrow-left text-[12px]" />
                    </Link>
                </div>
            </div>

            <div v-if="loading" class="creation-editor-page__loading">
                Loading editor...
            </div>

            <div
                v-else
                class="creation-editor-page__grid"
                :class="{
                    'creation-editor-page__grid--create': pageMode === 'create',
                    'creation-editor-page__grid--sidebar-collapsed': sidebarCollapsed,
                }"
            >
                <section
                    ref="mainWorkspaceRef"
                    class="creation-editor-page__main"
                    :class="{ 'creation-editor-page__main--resizing': isResizingWorkspace }"
                    :style="workspaceMainStyle"
                >
                    <div class="creation-editor-page__main-header">
                        <input
                            ref="titleInputRef"
                            v-model="form.title"
                            type="text"
                            maxlength="255"
                            class="creation-title-input"
                            placeholder="Untitled creation"
                        >

                        <div class="creation-title-meta">
                            <span>{{ titleCount }}/255</span>
                            <span>{{ form.publication_status === 'publish' ? 'Published' : 'Draft' }}</span>
                        </div>
                    </div>

                    <div class="creation-editor-page__editor-shell">
                        <CreationDocumentEditor
                            v-model="form.content"
                            :upload-url="uploadUrl"
                            :persist-key="persistKey"
                            placeholder="Write your documentation, paste screenshots, or drop images directly here..."
                            @uploading="editorUploading = $event"
                        />
                    </div>

                    <button
                        type="button"
                        class="creation-editor-page__resize-handle"
                        :class="{ 'creation-editor-page__resize-handle--active': isResizingWorkspace }"
                        title="Drag to resize editor height"
                        aria-label="Resize editor workspace"
                        @mousedown="startWorkspaceResize"
                        @touchstart="startWorkspaceResize"
                    >
                        <span class="creation-editor-page__resize-arrow">&#8600;</span>
                    </button>
                </section>

                <aside v-show="!sidebarCollapsed" class="creation-sidebar">
                    <section class="creation-sidebar__section">
                        <p class="creation-sidebar__label">Category</p>
                        <select v-model="form.category_id" class="creation-sidebar__input">
                            <option value="">No category</option>
                            <option v-for="category in categories" :key="category.id" :value="String(category.id)">
                                {{ category.name }}
                            </option>
                        </select>
                    </section>

                    <section class="creation-sidebar__section">
                        <p class="creation-sidebar__label">Tags</p>
                        <input
                            v-model="form.tags_text"
                            type="text"
                            class="creation-sidebar__input"
                            placeholder="vue, laravel, ui"
                        >
                    </section>

                    <section class="creation-sidebar__section">
                        <p class="creation-sidebar__label">Project Link</p>
                        <input
                            v-model="form.link"
                            type="url"
                            class="creation-sidebar__input"
                            placeholder="https://example.com/project"
                        >
                        <p class="creation-sidebar__hint">Link referensi / demo project (opsional).</p>
                    </section>

                    <section class="creation-sidebar__section">
                        <div class="creation-sidebar__section-head">
                            <p class="creation-sidebar__label">Featured Image</p>
                            <button type="button" class="creation-sidebar__icon" :disabled="uploadingFeaturedImage" @click="openFeaturedImagePicker">
                                <i class="fi fi-rr-picture text-[12px]" />
                            </button>
                        </div>

                        <input
                            ref="featuredImageInputRef"
                            type="file"
                            class="hidden"
                            accept="image/jpeg,image/png,image/webp,image/jpg"
                            @change="uploadFeaturedImage"
                        >

                        <div class="creation-sidebar__image-shell">
                            <img
                                v-if="form.featured_image"
                                :src="form.featured_image"
                                alt="Featured"
                                class="creation-sidebar__image"
                            >
                            <div v-else class="creation-sidebar__image-empty">
                                <i class="fi fi-rr-picture text-[18px]" />
                            </div>
                        </div>
                    </section>

                    <section class="creation-sidebar__section">
                        <div class="creation-sidebar__section-head">
                            <div>
                                <p class="creation-sidebar__label">Gallery / Thumbnail</p>
                                <p class="creation-sidebar__hint">{{ activeExistingPhotoCount }}/{{ MAX_CREATION_PHOTOS }} aktif</p>
                            </div>
                            <button type="button" class="creation-sidebar__icon" @click="openPhotoPicker">
                                <i class="fi fi-rr-images text-[12px]" />
                            </button>
                        </div>

                        <input
                            ref="photoInputRef"
                            type="file"
                            class="hidden"
                            accept="image/jpeg,image/png,image/webp,image/jpg"
                            multiple
                            @change="onPhotoInputChange"
                        >

                        <p v-if="photoErrorMessage" class="creation-sidebar__error">{{ photoErrorMessage }}</p>

                        <div v-if="existingPhotos.length > 0" class="creation-sidebar__gallery">
                            <button
                                v-for="photo in existingPhotos"
                                :key="photo.id"
                                type="button"
                                class="creation-sidebar__thumb"
                                :class="{ 'creation-sidebar__thumb--removed': isExistingPhotoRemoved(photo.id) }"
                                @click="toggleRemoveExistingPhoto(photo.id)"
                            >
                                <img :src="photo.url" alt="Creation thumbnail" class="creation-sidebar__thumb-image">
                                <span class="creation-sidebar__thumb-badge">
                                    <i class="fi" :class="isExistingPhotoRemoved(photo.id) ? 'fi-rr-undo' : 'fi-rr-trash'" />
                                </span>
                            </button>
                        </div>

                        <div v-if="newPhotoPreviews.length > 0" class="creation-sidebar__gallery">
                            <div v-for="preview in newPhotoPreviews" :key="preview.key" class="creation-sidebar__thumb creation-sidebar__thumb--new">
                                <img :src="preview.url" :alt="preview.name" class="creation-sidebar__thumb-image">
                                <span class="creation-sidebar__thumb-badge creation-sidebar__thumb-badge--new">NEW</span>
                            </div>
                        </div>

                        <p class="creation-sidebar__hint">Klik thumbnail lama untuk tandai hapus. Thumbnail baru ikut tersimpan saat save atau autosave.</p>
                    </section>

                    <section class="creation-sidebar__section">
                        <p class="creation-sidebar__label">Project</p>
                        <div class="creation-sidebar__toggle creation-sidebar__toggle--triple">
                            <button
                                type="button"
                                class="creation-sidebar__toggle-btn"
                                :class="{ 'creation-sidebar__toggle-btn--active': form.status === 'crafting' }"
                                @click="setProjectStatus('crafting')"
                            >
                                Crafting
                            </button>
                            <button
                                type="button"
                                class="creation-sidebar__toggle-btn"
                                :class="{ 'creation-sidebar__toggle-btn--active': form.status === 'refining' }"
                                @click="setProjectStatus('refining')"
                            >
                                Refining
                            </button>
                            <button
                                type="button"
                                class="creation-sidebar__toggle-btn"
                                :class="{ 'creation-sidebar__toggle-btn--active': form.status === 'finished' }"
                                @click="setProjectStatus('finished')"
                            >
                                Finished
                            </button>
                        </div>

                        <div class="creation-sidebar__progress-row">
                            <input
                                :value="form.progress"
                                type="range"
                                min="0"
                                max="100"
                                step="1"
                                class="creation-sidebar__range"
                                @input="updateProgress($event.target.value)"
                            >
                            <input
                                :value="form.progress"
                                type="number"
                                min="0"
                                max="100"
                                class="creation-sidebar__progress-input"
                                @input="updateProgress($event.target.value)"
                            >
                            <span class="creation-sidebar__progress-badge">{{ form.progress }}%</span>
                        </div>
                    </section>

                    <section class="creation-sidebar__section">
                        <p class="creation-sidebar__label">Publication</p>
                        <div class="creation-sidebar__toggle">
                            <button
                                type="button"
                                class="creation-sidebar__toggle-btn"
                                :class="{ 'creation-sidebar__toggle-btn--active': form.publication_status === 'draft' }"
                                @click="form.publication_status = 'draft'"
                            >
                                Draft
                            </button>
                            <button
                                type="button"
                                class="creation-sidebar__toggle-btn"
                                :class="{ 'creation-sidebar__toggle-btn--active': form.publication_status === 'publish' }"
                                @click="form.publication_status = 'publish'"
                            >
                                Publish
                            </button>
                        </div>
                    </section>

                    <section class="creation-sidebar__section">
                        <p class="creation-sidebar__label">Collaboration</p>
                        <div class="creation-sidebar__toggle">
                            <button
                                type="button"
                                class="creation-sidebar__toggle-btn"
                                :class="{ 'creation-sidebar__toggle-btn--active': form.is_open_for_collaboration }"
                                :disabled="!canManageCollaboration"
                                @click="form.is_open_for_collaboration = true"
                            >
                                Open Collab
                            </button>
                            <button
                                type="button"
                                class="creation-sidebar__toggle-btn"
                                :class="{ 'creation-sidebar__toggle-btn--active': !form.is_open_for_collaboration }"
                                :disabled="!canManageCollaboration"
                                @click="form.is_open_for_collaboration = false"
                            >
                                Private
                            </button>
                        </div>
                        <p class="creation-sidebar__hint">
                            {{ canManageCollaboration
                                ? (form.is_open_for_collaboration ? 'Creator lain bisa kirim request kolaborasi.' : 'Kolaborasi ditutup untuk sementara.')
                                : 'Hanya owner yang bisa mengubah akses kolaborasi.' }}
                        </p>
                    </section>

                    <section class="creation-sidebar__section creation-sidebar__section--actions">
                        <button type="button" class="creation-sidebar__primary" :disabled="saving || editorUploadBusy" title="Save draft" @click="handleManualSave">
                            <i class="fi fi-rr-disk text-[12px]" />
                            <span>Save</span>
                        </button>
                        <button type="button" class="creation-sidebar__accent" :disabled="saving || editorUploadBusy" title="Publish creation" @click="handlePublish">
                            <i class="fi fi-rr-paper-plane text-[12px]" />
                            <span>Publish</span>
                        </button>
                    </section>
                </aside>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.creation-editor-page {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.creation-editor-page__header {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    gap: 1rem;
    align-items: center;
}

.creation-editor-page__eyebrow {
    font-size: 8px;
    text-transform: uppercase;
    letter-spacing: 0.22em;
    color: rgba(103, 232, 249, 0.8);
}

.creation-editor-page__title {
    margin-top: 0.65rem;
    font-size: 13px;
    text-transform: uppercase;
    color: white;
}

.creation-editor-page__actions {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.creation-editor-page__toggle {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;
    min-height: 2rem;
    padding: 0 0.85rem;
    border: 1px solid rgba(71, 85, 105, 0.8);
    background: rgba(3, 10, 28, 0.9);
    color: #cbd5e1;
    font-size: 8px;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    transition: 160ms ease;
}

.creation-editor-page__toggle:hover {
    border-color: rgba(34, 211, 238, 0.65);
    color: #ecfeff;
    background: rgba(8, 47, 73, 0.9);
}

.creation-editor-page__toggle--show {
    border-color: rgba(16, 185, 129, 0.72);
    color: #6ee7b7;
    box-shadow: 0 0 0 1px rgba(16, 185, 129, 0.22), 0 0 12px rgba(16, 185, 129, 0.28);
    text-shadow: 0 0 6px rgba(16, 185, 129, 0.5);
}

.creation-editor-page__toggle--hide {
    border-color: rgba(34, 211, 238, 0.72);
    color: #67e8f9;
    box-shadow: 0 0 0 1px rgba(34, 211, 238, 0.2), 0 0 12px rgba(34, 211, 238, 0.25);
    text-shadow: 0 0 6px rgba(34, 211, 238, 0.45);
}

.creation-editor-page__meta {
    font-size: 8px;
    text-transform: uppercase;
    color: #94a3b8;
}

.creation-editor-page__link {
    display: inline-flex;
    height: 2rem;
    width: 2rem;
    align-items: center;
    justify-content: center;
    border: 1px solid rgba(71, 85, 105, 0.8);
    background: rgba(15, 23, 42, 0.88);
    color: #cbd5e1;
}

.creation-editor-page__loading {
    border: 1px solid rgba(71, 85, 105, 0.8);
    padding: 2rem;
    text-align: center;
    text-transform: uppercase;
    font-size: 8px;
    color: #94a3b8;
}

.creation-editor-page__grid {
    display: grid;
    gap: 1.25rem;
    grid-template-columns: minmax(0, 1fr);
}

.creation-editor-page__main,
.creation-sidebar {
    border: 1px solid rgba(71, 85, 105, 0.8);
    background: rgba(15, 23, 42, 0.72);
}

.creation-editor-page__main {
    padding: 0 0.75rem 0.75rem;
    display: flex;
    flex-direction: column;
    gap: 0;
    min-height: 0;
    position: relative;
}

.creation-editor-page__main--resizing {
    user-select: none;
}

.creation-editor-page__main-header {
    padding-top: 0.65rem;
    margin-bottom: 0.55rem;
}

.creation-editor-page__editor-shell {
    flex: 1;
    min-height: 0;
    display: flex;
}

.creation-editor-page__resize-handle {
    display: none;
}

.creation-title-input {
    width: 100%;
    border: none;
    background: transparent;
    color: #f8fafc;
    font-family: Georgia, "Times New Roman", serif;
    font-size: clamp(20px, 2.6vw, 32px);
    line-height: 1.05;
    outline: none;
    margin-bottom: 0.35rem;
}

.creation-title-input::placeholder {
    color: #64748b;
}

.creation-title-meta {
    display: flex;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 0;
    font-size: 7px;
    text-transform: uppercase;
    color: #64748b;
}

.creation-sidebar {
    padding: 1rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.creation-sidebar__section {
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
}

.creation-sidebar__section--actions {
    margin-top: auto;
    flex-direction: row;
    align-items: center;
    justify-content: flex-end;
}

.creation-sidebar__label {
    font-size: 8px;
    text-transform: uppercase;
    color: #94a3b8;
}

.creation-sidebar__hint {
    font-size: 7px;
    text-transform: uppercase;
    color: #64748b;
    line-height: 1.5;
}

.creation-sidebar__error {
    font-size: 7px;
    text-transform: uppercase;
    color: #fda4af;
    line-height: 1.5;
}

.creation-sidebar__input {
    width: 100%;
    border: 1px solid rgba(71, 85, 105, 0.8);
    background: rgba(2, 6, 23, 0.7);
    padding: 0.8rem 0.9rem;
    color: #e2e8f0;
    font-size: 11px;
    outline: none;
}

.creation-sidebar__section-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
}

.creation-sidebar__icon,
.creation-sidebar__primary,
.creation-sidebar__accent,
.creation-sidebar__toggle-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid rgba(71, 85, 105, 0.8);
    background: rgba(15, 23, 42, 0.88);
    color: #cbd5e1;
    transition: 160ms ease;
}

.creation-sidebar__icon {
    width: 2rem;
    height: 2rem;
}

.creation-sidebar__icon:hover,
.creation-sidebar__primary:hover,
.creation-sidebar__accent:hover,
.creation-sidebar__toggle-btn:hover,
.creation-sidebar__toggle-btn--active {
    border-color: rgba(34, 211, 238, 0.65);
    color: #ecfeff;
    background: rgba(8, 47, 73, 0.85);
}

.creation-sidebar__image-shell {
    min-height: 170px;
    border: 1px dashed rgba(71, 85, 105, 0.8);
    background: rgba(2, 6, 23, 0.7);
    overflow: hidden;
}

.creation-sidebar__image {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.creation-sidebar__image-empty {
    min-height: 170px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #64748b;
}

.creation-sidebar__gallery {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.5rem;
}

.creation-sidebar__thumb {
    position: relative;
    aspect-ratio: 1 / 1;
    overflow: hidden;
    border: 1px solid rgba(71, 85, 105, 0.8);
    background: rgba(2, 6, 23, 0.7);
}

.creation-sidebar__thumb--removed {
    opacity: 0.4;
    border-color: rgba(251, 113, 133, 0.85);
}

.creation-sidebar__thumb--new {
    border-color: rgba(34, 211, 238, 0.65);
}

.creation-sidebar__thumb-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.creation-sidebar__thumb-badge {
    position: absolute;
    right: 0.35rem;
    top: 0.35rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1.35rem;
    height: 1.35rem;
    border: 1px solid rgba(15, 23, 42, 0.92);
    background: rgba(2, 6, 23, 0.82);
    color: #f8fafc;
    font-size: 8px;
}

.creation-sidebar__thumb-badge--new {
    width: auto;
    padding: 0 0.35rem;
    color: #67e8f9;
}

.creation-sidebar__toggle {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.5rem;
}

.creation-sidebar__toggle--triple {
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.creation-sidebar__progress-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 58px auto;
    gap: 0.5rem;
    align-items: center;
}

.creation-sidebar__range {
    width: 100%;
    accent-color: #22d3ee;
}

.creation-sidebar__progress-input {
    width: 100%;
    height: 2rem;
    border: 1px solid rgba(71, 85, 105, 0.8);
    background: rgba(2, 6, 23, 0.7);
    color: #e2e8f0;
    font-size: 10px;
    text-align: center;
    outline: none;
}

.creation-sidebar__progress-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 3.2rem;
    height: 2rem;
    padding: 0 0.45rem;
    border: 1px solid rgba(71, 85, 105, 0.8);
    background: rgba(15, 23, 42, 0.88);
    color: #cbd5e1;
    font-size: 8px;
    text-transform: uppercase;
}

.creation-sidebar__toggle-btn,
.creation-sidebar__primary,
.creation-sidebar__accent {
    min-height: 2.75rem;
}

.creation-sidebar__toggle-btn {
    font-size: 8px;
    text-transform: uppercase;
    letter-spacing: 0.12em;
}

.creation-sidebar__toggle-btn:disabled {
    opacity: 0.45;
    cursor: not-allowed;
    border-color: rgba(71, 85, 105, 0.55);
    background: rgba(15, 23, 42, 0.5);
    color: #94a3b8;
}

.creation-sidebar__primary {
    gap: 0.45rem;
    padding: 0 0.9rem;
    font-size: 8px;
    text-transform: uppercase;
}

.creation-sidebar__accent {
    gap: 0.45rem;
    padding: 0 0.9rem;
    font-size: 8px;
    text-transform: uppercase;
}

@media (min-width: 1100px) {
    .creation-editor-page {
        min-height: calc(100vh - 7rem);
    }

    .creation-editor-page__grid {
        grid-template-columns: minmax(0, 1fr) 340px;
        align-items: stretch;
        min-height: calc(100vh - 10.35rem);
    }

    .creation-editor-page__grid--create {
        grid-template-columns: minmax(0, 1fr) 292px;
        gap: 1rem;
    }

    .creation-editor-page__grid--sidebar-collapsed {
        grid-template-columns: minmax(0, 1fr);
    }

    .creation-editor-page__main {
        height: calc(100vh - 10.35rem);
        max-height: none;
        overflow: visible;
        scrollbar-width: thin;
        scrollbar-color: rgba(34, 211, 238, 0.45) rgba(15, 23, 42, 0.4);
    }

    .creation-sidebar {
        max-height: calc(100vh - 11rem);
        scrollbar-width: thin;
        scrollbar-color: rgba(34, 211, 238, 0.45) rgba(15, 23, 42, 0.4);
    }

    .creation-editor-page__resize-handle {
        position: absolute;
        right: 0.35rem;
        bottom: 0.35rem;
        z-index: 9;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1.55rem;
        height: 1.55rem;
        border: 1px solid rgba(71, 85, 105, 0.85);
        background: rgba(2, 6, 23, 0.88);
        color: rgba(148, 163, 184, 0.95);
        cursor: nwse-resize;
        transition: 160ms ease;
    }

    .creation-editor-page__resize-handle:hover,
    .creation-editor-page__resize-handle--active {
        border-color: rgba(34, 211, 238, 0.7);
        color: #67e8f9;
        background: rgba(8, 47, 73, 0.9);
    }

    .creation-editor-page__resize-arrow {
        font-size: 12px;
        line-height: 1;
    }

    .creation-sidebar {
        position: sticky;
        top: 1.25rem;
        overflow-y: auto;
    }
}

</style>
