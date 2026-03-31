<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { toast } from '@/Utils/Alert';

const MAX_CREATION_PHOTOS = 8;
const MAX_PHOTO_SIZE_BYTES = 4 * 1024 * 1024;
const ALLOWED_PHOTO_MIME_TYPES = ['image/jpeg', 'image/png', 'image/x-png', 'image/webp'];
const ALLOWED_PHOTO_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

const loading = ref(false);
const saving = ref(false);
const editingId = ref(0);
const photoInputRef = ref(null);

const creations = ref([]);
const meta = ref({
    current_page: 1,
    last_page: 1,
    total: 0,
});

const filters = reactive({
    search: '',
    status: '',
});

const form = reactive({
    title: '',
    description: '',
    link: '',
    category: '',
    status: 'crafting',
    progress: 0,
    is_public: true,
});
const formErrors = ref({});

const selectedPhotoFiles = ref([]);
const newPhotoPreviews = ref([]);
const existingPhotos = ref([]);
const removedPhotoIds = ref([]);
const creationFieldLabels = {
    title: 'Judul',
    description: 'Deskripsi',
    link: 'Link',
    category: 'Kategori',
    status: 'Status',
    progress: 'Progress',
    photos: 'Foto',
    remove_photo_ids: 'Foto',
};
const creationErrorEntries = computed(() => {
    return Object.entries(formErrors.value || {})
        .map(([field, message]) => {
            const normalizedField = String(field || '').replace(/\.\d+$/, '').replace(/\.\*$/, '');

            return {
                field,
                label: creationFieldLabels[normalizedField] || 'Form',
                message: String(message || '').trim(),
            };
        })
        .filter((entry) => entry.message !== '');
});
const photoErrorMessage = computed(() => {
    const photoEntry = Object.entries(formErrors.value || {}).find(([field]) => {
        return field === 'photos'
            || field === 'remove_photo_ids'
            || field.startsWith('photos.')
            || field.startsWith('remove_photo_ids.');
    });

    return String(photoEntry?.[1] || '').trim();
});
const titleCharacterCount = computed(() => String(form.title || '').length);
const activeExistingPhotoCount = computed(() => {
    return existingPhotos.value.filter((photo) => !isExistingPhotoRemoved(photo.id)).length;
});
const availablePhotoSlots = computed(() => {
    return Math.max(0, MAX_CREATION_PHOTOS - activeExistingPhotoCount.value);
});

const clearFormErrors = () => {
    formErrors.value = {};
};

const setFormErrors = (errors) => {
    const nextErrors = {};

    Object.entries(errors || {}).forEach(([field, value]) => {
        const firstMessage = Array.isArray(value) ? value[0] : value;
        const normalizedMessage = String(firstMessage || '').trim();

        if (normalizedMessage !== '') {
            nextErrors[field] = normalizedMessage;
        }
    });

    formErrors.value = nextErrors;
};

const setFieldError = (field, message) => {
    formErrors.value = {
        ...formErrors.value,
        [field]: String(message || '').trim(),
    };
};

const clearPhotoErrors = () => {
    const nextErrors = { ...formErrors.value };

    Object.keys(nextErrors).forEach((field) => {
        if (
            field === 'photos'
            || field === 'remove_photo_ids'
            || field.startsWith('photos.')
            || field.startsWith('remove_photo_ids.')
        ) {
            delete nextErrors[field];
        }
    });

    formErrors.value = nextErrors;
};

const clearNewPhotoPreviews = () => {
    newPhotoPreviews.value.forEach((preview) => {
        if (preview?.url) {
            URL.revokeObjectURL(preview.url);
        }
    });
    newPhotoPreviews.value = [];
};

const isEditing = computed(() => editingId.value > 0);

const setSelectedPhotoPreviews = () => {
    clearNewPhotoPreviews();
    newPhotoPreviews.value = selectedPhotoFiles.value.map((file) => ({
        key: `${file.name}-${file.size}-${file.lastModified}`,
        url: URL.createObjectURL(file),
    }));
};

const isAllowedPhotoFile = (file) => {
    const fileName = String(file?.name || '');
    const extension = fileName.includes('.') ? fileName.split('.').pop().toLowerCase() : '';
    const mimeType = String(file?.type || '').toLowerCase();

    return ALLOWED_PHOTO_MIME_TYPES.includes(mimeType) || ALLOWED_PHOTO_EXTENSIONS.includes(extension);
};

const validateSelectedPhotoFiles = (files) => {
    const normalizedFiles = Array.isArray(files) ? files : [];
    const validFiles = [];
    const slots = availablePhotoSlots.value;
    let errorMessage = '';

    for (const file of normalizedFiles) {
        if (validFiles.length >= slots) {
            errorMessage = slots > 0
                ? `Maksimal ${slots} foto baru bisa ditambahkan sekarang. Hapus foto lama dulu jika ingin menambah lebih banyak.`
                : 'Batas 8 foto sudah penuh. Hapus salah satu foto lama dulu sebelum menambah foto baru.';
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

    if (errorMessage) {
        setFieldError('photos', errorMessage);
        return;
    }

    clearPhotoErrors();
};

const fetchCreations = async (page = 1) => {
    loading.value = true;

    try {
        const response = await window.axios.get(route('api.profile.creations.index'), {
            params: {
                page,
                per_page: 10,
                search: filters.search || undefined,
                status: filters.status || undefined,
            },
        });

        const payload = response.data || {};
        creations.value = Array.isArray(payload.data) ? payload.data : [];

        meta.value = {
            current_page: Number(payload.current_page || 1),
            last_page: Number(payload.last_page || 1),
            total: Number(payload.total || 0),
        };
    } catch (error) {
        toast.error('LOAD_FAILED', 'Failed to load creations.');
    } finally {
        loading.value = false;
    }
};

const resetForm = () => {
    editingId.value = 0;
    clearFormErrors();
    form.title = '';
    form.description = '';
    form.link = '';
    form.category = '';
    form.status = 'crafting';
    form.progress = 0;
    form.is_public = true;
    selectedPhotoFiles.value = [];
    clearNewPhotoPreviews();
    existingPhotos.value = [];
    removedPhotoIds.value = [];
    if (photoInputRef.value) {
        photoInputRef.value.value = '';
    }
};

const editCreation = (creation) => {
    editingId.value = Number(creation.id);
    clearFormErrors();
    form.title = String(creation.title || '');
    form.description = String(creation.description || '');
    form.link = String(creation.link || '');
    form.category = String(creation.category || '');
    form.status = String(creation.status || 'crafting');
    form.progress = Number(creation.progress || 0);
    form.is_public = Boolean(creation.is_public);
    existingPhotos.value = Array.isArray(creation.photos) ? creation.photos : [];
    removedPhotoIds.value = [];
    selectedPhotoFiles.value = [];
    clearNewPhotoPreviews();
    if (photoInputRef.value) {
        photoInputRef.value.value = '';
    }
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

const onPhotoInputChange = (event) => {
    const fileList = Array.from(event?.target?.files || []);
    validateSelectedPhotoFiles(fileList);
};

const toggleRemoveExistingPhoto = (photoId) => {
    const current = removedPhotoIds.value.map((id) => Number(id));
    const target = Number(photoId);

    if (current.includes(target)) {
        removedPhotoIds.value = current.filter((id) => id !== target);
    } else {
        removedPhotoIds.value = [...current, target];
    }

    validateSelectedPhotoFiles(selectedPhotoFiles.value);
};

const isExistingPhotoRemoved = (photoId) => {
    return removedPhotoIds.value.map((id) => Number(id)).includes(Number(photoId));
};

const submit = async () => {
    saving.value = true;
    clearFormErrors();
    validateSelectedPhotoFiles(selectedPhotoFiles.value);

    if (photoErrorMessage.value) {
        saving.value = false;
        toast.error('SAVE_FAILED', `Foto: ${photoErrorMessage.value}`);
        return;
    }

    const formData = new FormData();
    formData.append('title', form.title);
    formData.append('description', form.description);
    formData.append('link', form.link || '');
    formData.append('category', form.category || '');
    formData.append('status', form.status);
    formData.append('progress', String(Number(form.progress || 0)));
    formData.append('is_public', form.is_public ? '1' : '0');

    selectedPhotoFiles.value.forEach((file) => {
        formData.append('photos[]', file);
    });

    removedPhotoIds.value.forEach((id) => {
        formData.append('remove_photo_ids[]', String(id));
    });

    try {
        if (isEditing.value) {
            formData.append('_method', 'PUT');
            await window.axios.post(route('api.creations.update', { creation: editingId.value }), formData, {
                headers: {
                    'Content-Type': 'multipart/form-data',
                },
            });
            toast.success('UPDATED', 'Creation updated.');
        } else {
            await window.axios.post(route('api.creations.store'), formData, {
                headers: {
                    'Content-Type': 'multipart/form-data',
                },
            });
            toast.success('CREATED', 'Creation saved.');
        }

        resetForm();
        fetchCreations(meta.value.current_page);
    } catch (error) {
        const validationErrors = error?.response?.data?.errors || {};

        if (Object.keys(validationErrors).length > 0) {
            setFormErrors(validationErrors);

            const firstError = creationErrorEntries.value[0];
            const firstMessage = firstError
                ? `${firstError.label}: ${firstError.message}`
                : 'Periksa kembali form creation kamu.';
            toast.error('SAVE_FAILED', firstMessage);
        } else {
            toast.error('SAVE_FAILED', 'Creation gagal disimpan. Coba cek lagi input yang dimasukkan.');
        }
    } finally {
        saving.value = false;
    }
};

const removeCreation = async (creation) => {
    const result = await toast.confirm('DELETE?', creation.title, 'DELETE');

    if (!result.isConfirmed) {
        return;
    }

    try {
        await window.axios.delete(route('api.creations.destroy', { creation: creation.id }));
        toast.success('DELETED', 'Creation removed.');

        if (isEditing.value && Number(editingId.value) === Number(creation.id)) {
            resetForm();
        }

        fetchCreations(meta.value.current_page);
    } catch (error) {
        toast.error('DELETE_FAILED', 'Unable to delete creation.');
    }
};

const statusBadge = (status) => {
    const value = String(status || '');

    if (value === 'finished') {
        return 'border-emerald-500/70 text-emerald-300';
    }

    if (value === 'refining') {
        return 'border-amber-500/70 text-amber-300';
    }

    return 'border-cyan-500/70 text-cyan-300';
};

onMounted(() => {
    fetchCreations();
});

onBeforeUnmount(() => {
    clearNewPhotoPreviews();
});
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Profile Creations" />

        <div class="user-page-shell space-y-6 font-['Press_Start_2P'] text-[#4ed4d4]">
            <section class="rpg-panel border-cyan-500/40 bg-[#1a1c2c]/85">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b border-slate-700 pb-3">
                    <div>
                        <p class="text-[8px] uppercase tracking-[0.22em] text-cyan-300/90">Profile</p>
                        <h1 class="text-[11px] uppercase tracking-[0.18em] text-white">My Creations</h1>
                    </div>

                    <div class="flex items-center gap-2">
                        <Link :href="route('profile.dashboard')" class="icon-link" title="Profile dashboard">
                            <i class="fi fi-rr-user text-[12px]" />
                        </Link>
                        <Link :href="route('hall.creations.index')" class="icon-link" title="Hall of Creations">
                            <i class="fi fi-rr-lightbulb-on text-[12px]" />
                        </Link>
                    </div>
                </div>

                <form class="grid grid-cols-1 gap-3 md:grid-cols-2" @submit.prevent="submit">
                    <div v-if="creationErrorEntries.length > 0" class="validation-panel md:col-span-2">
                        <p class="validation-panel__title">Periksa input berikut sebelum menyimpan:</p>
                        <ul class="space-y-1">
                            <li
                                v-for="entry in creationErrorEntries"
                                :key="`${entry.field}-${entry.message}`"
                                class="validation-panel__item"
                            >
                                <span class="text-rose-200">{{ entry.label }}:</span> {{ entry.message }}
                            </li>
                        </ul>
                    </div>

                    <label class="field-label md:col-span-2">
                        Title
                        <input v-model="form.title" class="field-input" :class="{ 'field-input--error': formErrors.title }" required type="text" maxlength="255">
                        <p class="text-[7px] uppercase text-slate-500">{{ titleCharacterCount }}/255 karakter</p>
                        <p v-if="formErrors.title" class="field-error">{{ formErrors.title }}</p>
                    </label>

                    <label class="field-label md:col-span-2">
                        Description
                        <textarea v-model="form.description" class="field-input min-h-[100px]" :class="{ 'field-input--error': formErrors.description }" required />
                        <p v-if="formErrors.description" class="field-error">{{ formErrors.description }}</p>
                    </label>

                    <label class="field-label">
                        Link
                        <input v-model="form.link" class="field-input" :class="{ 'field-input--error': formErrors.link }" type="url" placeholder="https://...">
                        <p v-if="formErrors.link" class="field-error">{{ formErrors.link }}</p>
                    </label>

                    <label class="field-label">
                        Category
                        <input v-model="form.category" class="field-input" :class="{ 'field-input--error': formErrors.category }" type="text" maxlength="120">
                        <p v-if="formErrors.category" class="field-error">{{ formErrors.category }}</p>
                    </label>

                    <label class="field-label">
                        Status
                        <select v-model="form.status" class="field-input" :class="{ 'field-input--error': formErrors.status }">
                            <option value="crafting">Crafting</option>
                            <option value="refining">Refining</option>
                            <option value="finished">Finished</option>
                        </select>
                        <p v-if="formErrors.status" class="field-error">{{ formErrors.status }}</p>
                    </label>

                    <label class="field-label">
                        Progress
                        <input v-model.number="form.progress" class="field-input" :class="{ 'field-input--error': formErrors.progress }" type="number" min="0" max="100">
                        <p v-if="formErrors.progress" class="field-error">{{ formErrors.progress }}</p>
                    </label>

                    <label class="field-label inline-flex items-center gap-2">
                        <input v-model="form.is_public" type="checkbox">
                        <span>Public</span>
                    </label>

                    <label class="field-label md:col-span-2">
                        Photos (max 8)
                        <input
                            ref="photoInputRef"
                            class="field-input"
                            :class="{ 'field-input--error': photoErrorMessage }"
                            type="file"
                            accept="image/jpeg,image/png,image/webp,image/jpg"
                            multiple
                            @change="onPhotoInputChange"
                        >
                        <p class="text-[7px] uppercase text-slate-500">Gunakan JPG, PNG, atau WEBP dengan ukuran maksimal 4MB per file. Slot tersisa: {{ availablePhotoSlots }}.</p>
                        <p v-if="photoErrorMessage" class="field-error">{{ photoErrorMessage }}</p>
                    </label>

                    <div v-if="isEditing && existingPhotos.length > 0" class="md:col-span-2">
                        <p class="mb-2 text-[7px] uppercase text-slate-500">Existing Photos</p>
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                            <button
                                v-for="photo in existingPhotos"
                                :key="photo.id"
                                type="button"
                                class="relative overflow-hidden border"
                                :class="isExistingPhotoRemoved(photo.id) ? 'border-rose-500/70 opacity-50' : 'border-slate-700'"
                                @click="toggleRemoveExistingPhoto(photo.id)"
                            >
                                <img :src="photo.url" alt="Creation photo" class="h-20 w-full object-cover">
                                <span class="absolute right-1 top-1 rounded bg-black/65 px-1 py-[1px] text-[7px] uppercase text-white">
                                    <i class="fi" :class="isExistingPhotoRemoved(photo.id) ? 'fi-rr-undo' : 'fi-rr-trash'" />
                                </span>
                            </button>
                        </div>
                    </div>

                    <div v-if="newPhotoPreviews.length > 0" class="md:col-span-2">
                        <p class="mb-2 text-[7px] uppercase text-slate-500">New Photos</p>
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                            <div
                                v-for="preview in newPhotoPreviews"
                                :key="preview.key"
                                class="overflow-hidden border border-cyan-500/60"
                            >
                                <img :src="preview.url" alt="New photo preview" class="h-20 w-full object-cover">
                            </div>
                        </div>
                    </div>

                    <div class="md:col-span-2 flex items-center gap-2 pt-1">
                        <button type="submit" class="action-btn action-btn--save" :disabled="saving">
                            <i class="fi fi-rr-disk text-[10px]" />
                        </button>
                        <button v-if="isEditing" type="button" class="action-btn action-btn--cancel" @click="resetForm">
                            <i class="fi fi-rr-cross-small text-[10px]" />
                        </button>
                    </div>
                </form>
            </section>

            <section class="rpg-panel border-emerald-500/30 bg-[#161b22]/90">
                <div class="mb-4 flex flex-wrap items-center gap-2 border-b border-slate-700 pb-3">
                    <label class="icon-input">
                        <i class="fi fi-rr-search text-[11px]" />
                        <input
                            v-model="filters.search"
                            class="bg-transparent text-[8px] uppercase text-cyan-200 outline-none placeholder:text-slate-500"
                            placeholder="Search"
                            @keyup.enter="fetchCreations(1)"
                        >
                    </label>
                    <label class="icon-input">
                        <i class="fi fi-rr-filter text-[11px]" />
                        <select v-model="filters.status" class="bg-transparent text-[8px] uppercase text-cyan-200 outline-none" @change="fetchCreations(1)">
                            <option value="">All</option>
                            <option value="crafting">Crafting</option>
                            <option value="refining">Refining</option>
                            <option value="finished">Finished</option>
                        </select>
                    </label>
                </div>

                <div v-if="loading" class="py-12 text-center text-[8px] uppercase text-slate-500">Loading...</div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[700px] border-collapse text-[8px] uppercase">
                        <thead>
                            <tr class="border-b border-slate-700 text-slate-500">
                                <th class="px-2 py-2 text-left">Preview</th>
                                <th class="px-2 py-2 text-left">Title</th>
                                <th class="px-2 py-2 text-left">Status</th>
                                <th class="px-2 py-2 text-left">Progress</th>
                                <th class="px-2 py-2 text-left">Stats</th>
                                <th class="px-2 py-2 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="creation in creations" :key="creation.id" class="border-b border-slate-800/80">
                                <td class="px-2 py-3">
                                    <div class="relative h-10 w-14 overflow-hidden border border-slate-700 bg-slate-900">
                                        <img
                                            v-if="creation.thumbnail_url"
                                            :src="creation.thumbnail_url"
                                            alt="Thumbnail"
                                            class="h-full w-full object-cover"
                                        >
                                        <i v-else class="fi fi-rr-lightbulb-on absolute inset-0 flex items-center justify-center text-[12px] text-cyan-300/80" />
                                    </div>
                                </td>
                                <td class="px-2 py-3 text-white">{{ creation.title }}</td>
                                <td class="px-2 py-3">
                                    <span class="rounded border px-2 py-[2px]" :class="statusBadge(creation.status)">
                                        {{ creation.status }}
                                    </span>
                                </td>
                                <td class="px-2 py-3">
                                    <div class="w-28">
                                        <div class="h-2 overflow-hidden border border-slate-700 bg-slate-950">
                                            <div class="h-full bg-cyan-500" :style="{ width: `${creation.progress || 0}%` }" />
                                        </div>
                                    </div>
                                </td>
                                <td class="px-2 py-3 text-slate-400">
                                    <span class="inline-flex items-center gap-1"><i class="fi fi-rr-heart text-[10px]" />{{ creation.appreciations_count || 0 }}</span>
                                    <span class="ml-2 inline-flex items-center gap-1"><i class="fi fi-rr-comment-alt text-[10px]" />{{ creation.insights_count || 0 }}</span>
                                    <span class="ml-2 inline-flex items-center gap-1"><i class="fi fi-rr-picture text-[10px]" />{{ creation.photos_count || 0 }}</span>
                                </td>
                                <td class="px-2 py-3">
                                    <div class="flex justify-end gap-1">
                                        <button type="button" class="icon-action text-cyan-300 hover:text-cyan-100" title="Edit" @click="editCreation(creation)">
                                            <i class="fi fi-rr-pencil text-[11px]" />
                                        </button>
                                        <button type="button" class="icon-action text-rose-300 hover:text-rose-100" title="Delete" @click="removeCreation(creation)">
                                            <i class="fi fi-rr-trash text-[11px]" />
                                        </button>
                                        <Link :href="route('hall.creations.show', { creation: creation.id })" class="icon-action text-amber-300 hover:text-amber-100" title="Detail">
                                            <i class="fi fi-rr-eye text-[11px]" />
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="!loading && creations.length === 0" class="py-12 text-center text-[8px] uppercase text-slate-500">
                    No creations.
                </div>

                <div class="mt-5 flex items-center justify-between border-t border-slate-700 pt-4">
                    <span class="text-[8px] uppercase text-slate-500">Total {{ meta.total }}</span>

                    <div class="flex items-center gap-2">
                        <button type="button" class="pager-btn" :disabled="meta.current_page <= 1" @click="fetchCreations(meta.current_page - 1)">
                            <i class="fi fi-rr-angle-small-left text-[12px]" />
                        </button>
                        <span class="text-[8px] uppercase text-slate-400">{{ meta.current_page }} / {{ meta.last_page }}</span>
                        <button type="button" class="pager-btn" :disabled="meta.current_page >= meta.last_page" @click="fetchCreations(meta.current_page + 1)">
                            <i class="fi fi-rr-angle-small-right text-[12px]" />
                        </button>
                    </div>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.rpg-panel {
    @apply relative border-4 p-5;
    box-shadow: 8px 8px 0 0 rgba(0, 0, 0, 0.45);
}

.field-label {
    @apply flex flex-col gap-2 text-[8px] uppercase text-slate-400;
}

.field-input {
    @apply border-2 border-slate-700 bg-[#0d1117] p-2 text-[8px] text-cyan-300 outline-none transition-colors focus:border-cyan-500;
}

.field-input--error {
    @apply border-rose-500 text-rose-100 focus:border-rose-400;
}

.field-error {
    @apply text-[7px] uppercase text-rose-300;
}

.validation-panel {
    @apply border border-rose-500/60 bg-rose-950/40 px-3 py-3 text-[7px] uppercase text-rose-100;
}

.validation-panel__title {
    @apply mb-2 text-[8px] text-rose-200;
}

.validation-panel__item {
    @apply leading-relaxed text-rose-100;
}

.action-btn {
    @apply inline-flex h-8 w-8 items-center justify-center border-b-4 border-r-4;
}

.action-btn--save {
    @apply border-cyan-900 bg-cyan-600 text-black hover:bg-cyan-500;
}

.action-btn--cancel {
    @apply border-slate-800 bg-slate-700 text-white hover:bg-slate-600;
}

.icon-link {
    @apply inline-flex h-8 w-8 items-center justify-center border border-slate-700 bg-slate-900/90 text-cyan-300 transition-colors hover:border-cyan-500 hover:text-cyan-100;
}

.icon-input {
    @apply inline-flex items-center gap-2 border border-slate-700 bg-black/35 px-3 py-2 text-cyan-300;
}

.icon-action {
    @apply inline-flex h-7 w-7 items-center justify-center border border-slate-700 bg-black/25 transition-colors;
}

.pager-btn {
    @apply inline-flex h-8 w-8 items-center justify-center border border-slate-700 bg-slate-900/90 text-cyan-300 transition-colors disabled:cursor-not-allowed disabled:opacity-40 hover:border-cyan-500 hover:text-cyan-200;
}
</style>
