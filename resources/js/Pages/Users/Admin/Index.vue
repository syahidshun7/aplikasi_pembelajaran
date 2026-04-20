<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, ref } from 'vue';
import Swal from 'sweetalert2';
import AdminNavbar from '@/Components/AdminNavbar.vue';
import Cropper from 'cropperjs';
import 'cropperjs/dist/cropper.css';

const props = defineProps({
    users: Object,
    availableRoles: Array,
    jobRoles: Array,
    filters: Object,
});

const filterForm = useForm({
    search: props.filters?.search || '',
    role: props.filters?.role || 'all',
    rank_by: props.filters?.rank_by || 'newest',
    grade_order: props.filters?.grade_order || 'none',
    view: props.filters?.view || 'active',
});

const selectedUser = ref(null);
const currentAvatarPath = ref('');
const avatarPreview = ref('');
const avatarObjectUrl = ref(null);
const cropModalOpen = ref(false);
const cropSourceUrl = ref('');
const cropImageRef = ref(null);
let cropperInstance = null;
let cropSourceObjectUrl = null;
const editForm = useForm({
    name: '',
    username: '',
    email: '',
    role: 'user',
    job_id: '',
    gold: 0,
    exp: 0,
    level: 1,
    bio: '',
    experience: '',
    location: '',
    skills_text: '',
    profile_photo: null,
    remove_avatar: false,
    password: '',
    password_confirmation: '',
    _method: 'patch',
});

const rows = computed(() => props.users?.data || []);
const paginationLinks = computed(() => props.users?.links || []);
const isTrashView = computed(() => filterForm.view === 'trash');

const applyFilters = () => {
    router.get(route('admin.users.index'), filterForm.data(), {
        preserveState: true,
        preserveScroll: true,
    });
};

const resetFilters = () => {
    filterForm.search = '';
    filterForm.role = 'all';
    filterForm.rank_by = 'newest';
    filterForm.grade_order = 'none';
    applyFilters();
};

const setView = (view) => {
    if (filterForm.view === view) return;
    filterForm.view = view;
    applyFilters();
};

const setGradeOrder = (order) => {
    filterForm.grade_order = filterForm.grade_order === order ? 'none' : order;
    applyFilters();
};

const goToPage = (url) => {
    if (!url) return;
    router.get(url, {}, {
        preserveState: true,
        preserveScroll: true,
    });
};

const openEditModal = (user) => {
    selectedUser.value = user;
    const detail = user.detail_user || {};
    currentAvatarPath.value = user.profile_photo || '';
    avatarPreview.value = currentAvatarPath.value ? `/storage/${currentAvatarPath.value}` : '';
    editForm.name = user.name || '';
    editForm.username = user.username || '';
    editForm.email = user.email || '';
    editForm.role = user.role || 'user';
    editForm.job_id = user.job_id ? String(user.job_id) : '';
    editForm.gold = Number(user.gold || 0);
    editForm.exp = Number(user.exp || 0);
    editForm.level = Number(user.level_display || user.level || 1);
    editForm.bio = detail.bio || '';
    editForm.experience = detail.experience || '';
    editForm.location = detail.location || '';
    editForm.skills_text = Array.isArray(detail.skills) ? detail.skills.join(', ') : (detail.skills || '');
    editForm.profile_photo = null;
    editForm.remove_avatar = false;
    editForm.password = '';
    editForm.password_confirmation = '';
    editForm.clearErrors();
};

const closeModal = () => {
    selectedUser.value = null;
    currentAvatarPath.value = '';
    avatarPreview.value = '';
    closeCropper();
    if (avatarObjectUrl.value) {
        URL.revokeObjectURL(avatarObjectUrl.value);
        avatarObjectUrl.value = null;
    }
    editForm.reset();
    editForm.clearErrors();
};

const handleAvatarChange = (event) => {
    const file = event.target.files && event.target.files[0] ? event.target.files[0] : null;
    event.target.value = '';
    if (!file) {
        avatarPreview.value = currentAvatarPath.value ? `/storage/${currentAvatarPath.value}` : '';
        return;
    }

    openCropper(file);
};

const handleRemoveAvatar = () => {
    if (editForm.remove_avatar) {
        editForm.profile_photo = null;
        avatarPreview.value = '';
        if (avatarObjectUrl.value) {
            URL.revokeObjectURL(avatarObjectUrl.value);
            avatarObjectUrl.value = null;
        }
        return;
    }

    avatarPreview.value = currentAvatarPath.value ? `/storage/${currentAvatarPath.value}` : '';
};

const openCropper = async (file) => {
    if (cropSourceObjectUrl) {
        URL.revokeObjectURL(cropSourceObjectUrl);
    }
    cropSourceObjectUrl = URL.createObjectURL(file);
    cropSourceUrl.value = cropSourceObjectUrl;
    cropModalOpen.value = true;

    await nextTick();

    if (!cropImageRef.value) return;
    if (cropperInstance) {
        cropperInstance.destroy();
        cropperInstance = null;
    }

    cropperInstance = new Cropper(cropImageRef.value, {
        aspectRatio: 1,
        viewMode: 1,
        autoCropArea: 1,
        background: false,
        responsive: true,
    });
};

const closeCropper = () => {
    cropModalOpen.value = false;
    if (cropperInstance) {
        cropperInstance.destroy();
        cropperInstance = null;
    }
    if (cropSourceObjectUrl) {
        URL.revokeObjectURL(cropSourceObjectUrl);
        cropSourceObjectUrl = null;
    }
    cropSourceUrl.value = '';
};

const applyCrop = () => {
    if (!cropperInstance) return;
    const canvas = cropperInstance.getCroppedCanvas({
        width: 512,
        height: 512,
        imageSmoothingEnabled: true,
        imageSmoothingQuality: 'high',
    });

    canvas.toBlob((blob) => {
        if (!blob) return;
        const croppedFile = new File([blob], 'avatar.png', { type: 'image/png' });
        editForm.profile_photo = croppedFile;

        if (avatarObjectUrl.value) {
            URL.revokeObjectURL(avatarObjectUrl.value);
            avatarObjectUrl.value = null;
        }
        avatarObjectUrl.value = URL.createObjectURL(blob);
        avatarPreview.value = avatarObjectUrl.value;
        editForm.remove_avatar = false;

        closeCropper();
    }, 'image/png', 0.92);
};

onBeforeUnmount(() => {
    closeCropper();
    if (avatarObjectUrl.value) {
        URL.revokeObjectURL(avatarObjectUrl.value);
        avatarObjectUrl.value = null;
    }
});

const submitEdit = () => {
    if (!selectedUser.value) return;

    editForm.post(route('admin.users.update', selectedUser.value.id), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            closeModal();
            Swal.fire({
                icon: 'success',
                title: 'USER_UPDATED',
                timer: 1600,
                showConfirmButton: false,
                background: '#1a1c2c',
                color: '#4ed4d4',
            });
        },
    });
};

const deleteUser = () => {
    if (!selectedUser.value) return;

    Swal.fire({
        title: 'ARSIPKAN_AKUN_USER?',
        text: `Akun ${selectedUser.value.name || selectedUser.value.username} akan dipindah ke trash.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'YA_ARSIPKAN',
        cancelButtonText: 'BATAL',
        background: '#1a1c2c',
        color: '#4ed4d4',
        confirmButtonColor: '#dc2626',
    }).then((result) => {
        if (!result.isConfirmed) return;

        router.delete(route('admin.users.destroy', selectedUser.value.id), {
            preserveScroll: true,
            onSuccess: () => {
                closeModal();
                Swal.fire({
                    icon: 'success',
                    title: 'USER_ARCHIVED',
                    timer: 1600,
                    showConfirmButton: false,
                    background: '#1a1c2c',
                    color: '#4ed4d4',
                });
            },
        });
    });
};

const restoreUser = (user) => {
    Swal.fire({
        title: 'RESTORE_ACCOUNT?',
        text: `Pulihkan akun ${user.name || user.username}?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'RESTORE',
        cancelButtonText: 'CANCEL',
        background: '#1a1c2c',
        color: '#4ed4d4',
        confirmButtonColor: '#059669',
    }).then((result) => {
        if (!result.isConfirmed) return;

        router.patch(route('admin.users.restore', user.id), {}, {
            preserveScroll: true,
            onSuccess: () => {
                Swal.fire({
                    icon: 'success',
                    title: 'USER_RESTORED',
                    timer: 1600,
                    showConfirmButton: false,
                    background: '#1a1c2c',
                    color: '#4ed4d4',
                });
            },
        });
    });
};

const hardDeleteUser = (user) => {
    Swal.fire({
        title: 'HARD_DELETE_ACCOUNT?',
        text: `Akun ${user.name || user.username} akan dihapus permanen dan tidak bisa dipulihkan.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'DELETE_PERMANENTLY',
        cancelButtonText: 'CANCEL',
        background: '#1a1c2c',
        color: '#4ed4d4',
        confirmButtonColor: '#dc2626',
    }).then((result) => {
        if (!result.isConfirmed) return;

        router.delete(route('admin.users.force-destroy', user.id), {
            preserveScroll: true,
            onSuccess: () => {
                Swal.fire({
                    icon: 'success',
                    title: 'USER_PERMANENTLY_DELETED',
                    timer: 1600,
                    showConfirmButton: false,
                    background: '#1a1c2c',
                    color: '#4ed4d4',
                });
            },
        });
    });
};

const formatDate = (date) => {
    return new Date(date).toLocaleString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).toUpperCase();
};

</script>

<template>
    <Head title="USER_MANAGEMENT" />

    <div class="min-h-screen bg-[#0d1117] p-4 md:p-8 font-['Press_Start_2P'] text-[#4ed4d4] text-[10px]">
        <div class="max-w-7xl mx-auto space-y-8">
            <AdminNavbar />

            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3 border-b-4 border-cyan-900 pb-4">
                <h1 class="text-base sm:text-xl uppercase tracking-widest">User_Management</h1>
                <Link href="/dashboard" class="inline-flex items-center justify-center px-3 py-2 border border-slate-600 bg-slate-900/40 text-slate-300 hover:text-white uppercase text-[9px] sm:text-[10px]">[Back_to_HQ]</Link>
            </div>

            <div class="rpg-panel border-slate-700 overflow-x-auto custom-scroll">
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

                <div class="flex flex-col md:flex-row gap-3 mb-4">
                    <input
                        v-model="filterForm.search"
                        type="text"
                        placeholder="SEARCH: NAME / USERNAME / EMAIL"
                        class="flex-1 bg-black border-2 border-slate-700 p-2 text-cyan-400 uppercase outline-none"
                        @keyup.enter="applyFilters"
                    />
                    <select
                        v-model="filterForm.role"
                        class="w-full md:w-44 bg-black border-2 border-slate-700 p-2 text-cyan-400 uppercase outline-none"
                    >
                        <option value="all">ALL_ROLES</option>
                        <option v-for="role in availableRoles" :key="role" :value="role">{{ role }}</option>
                    </select>
                    <select
                        v-model="filterForm.rank_by"
                        class="w-full md:w-56 bg-black border-2 border-slate-700 p-2 text-cyan-400 uppercase outline-none"
                    >
                        <option value="newest">SORT: NEWEST</option>
                        <option value="highest_gold">SORT: HIGHEST_GOLD</option>
                        <option value="highest_exp">SORT: HIGHEST_EXP</option>
                    </select>
                </div>
                <div class="flex gap-2 mb-4">
                    <button @click="applyFilters"
                        class="px-3 py-2 border-2 border-cyan-400 text-cyan-400 hover:bg-cyan-400 hover:text-black uppercase">
                        APPLY_FILTER
                    </button>
                    <button @click="resetFilters"
                        class="px-3 py-2 border-2 border-slate-600 text-slate-300 hover:bg-slate-600 hover:text-white uppercase">
                        RESET
                    </button>
                </div>

                <table class="w-full min-w-[1080px] text-left">
                    <thead class="text-[8px] uppercase border-b border-slate-700 text-slate-500">
                        <tr>
                            <th class="py-3 px-2">ID</th>
                            <th class="py-3 px-2">Real_Name</th>
                            <th class="py-3 px-2">Username</th>
                            <th class="py-3 px-2">Email</th>
                            <th class="py-3 px-2">Job</th>
                            <th class="py-3 px-2">Role</th>
                            <th class="py-3 px-2">Progress</th>
                            <th class="py-3 px-2">
                                <div class="inline-flex items-center gap-2">
                                    <span>Grade</span>
                                    <button
                                        type="button"
                                        class="px-1 border border-slate-600 hover:border-cyan-400"
                                        :class="filterForm.grade_order === 'asc' ? 'text-cyan-300' : 'text-slate-500'"
                                        @click="setGradeOrder('asc')"
                                    >
                                        ▲
                                    </button>
                                    <button
                                        type="button"
                                        class="px-1 border border-slate-600 hover:border-cyan-400"
                                        :class="filterForm.grade_order === 'desc' ? 'text-cyan-300' : 'text-slate-500'"
                                        @click="setGradeOrder('desc')"
                                    >
                                        ▼
                                    </button>
                                </div>
                            </th>
                            <th class="py-3 px-2">{{ isTrashView ? 'Deleted' : 'Joined' }}</th>
                            <th class="py-3 px-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="user in rows" :key="user.id" class="border-b border-slate-800 hover:bg-slate-900/40">
                            <td class="py-4 px-2 text-slate-400">{{ user.id }}</td>
                            <td class="py-4 px-2 text-white">{{ user.name || '-' }}</td>
                            <td class="py-4 px-2 text-slate-300">{{ user.username || '-' }}</td>
                            <td class="py-4 px-2 text-slate-300">{{ user.email }}</td>
                            <td class="py-4 px-2 text-slate-300">{{ user.job?.name || 'UNASSIGNED' }}</td>
                            <td class="py-4 px-2">
                                <span
                                    class="px-2 py-1 border text-[8px] uppercase"
                                    :class="user.role === 'super_admin'
                                        ? 'text-amber-300 border-amber-700 bg-amber-900/20'
                                        : (user.role === 'admin'
                                        ? 'text-red-400 border-red-900 bg-red-900/20'
                                        : (user.role === 'mentor'
                                            ? 'text-violet-300 border-violet-800 bg-violet-900/20'
                                            : 'text-cyan-400 border-cyan-900 bg-cyan-900/20'))"
                                >
                                    {{ user.role || 'user' }}
                                </span>
                            </td>
                            <td class="py-4 px-2 text-[8px] space-y-1">
                                <p>LVL {{ user.level_display || user.level || 1 }} | EXP {{ user.exp || 0 }}</p>
                                <p class="text-yellow-500">GOLD {{ user.gold || 0 }}</p>
                                <p class="text-slate-500">SUBMISSIONS {{ user.submissions_count || 0 }}</p>
                            </td>
                            <td class="py-4 px-2 text-[8px] space-y-1">
                                <p>
                                    <span class="text-slate-500">Highest:</span>
                                    <span class="text-emerald-400">{{ Number(user.highest_grade || 0).toFixed(0) }}%</span>
                                </p>
                                <p>
                                    <span class="text-slate-500">Avg:</span>
                                    <span :class="(Number(user.avg_grade || 0) >= 75) ? 'text-emerald-400' : 'text-orange-400'">
                                        {{ Number(user.avg_grade || 0).toFixed(1) }}%
                                    </span>
                                </p>
                            </td>
                            <td class="py-4 px-2 text-slate-500 text-[8px]">
                                {{ isTrashView ? formatDate(user.deleted_at) : formatDate(user.created_at) }}
                            </td>
                            <td class="py-4 px-2 text-right space-x-2">
                                <Link
                                    v-if="!isTrashView"
                                    :href="route('admin.users.ledger', user.id)"
                                    class="inline-block px-2 py-1 border border-cyan-700 text-cyan-300 hover:bg-cyan-700 hover:text-black uppercase text-[8px]"
                                >
                                    Ledger
                                </Link>
                                <button
                                    v-if="!isTrashView"
                                    @click="openEditModal(user)"
                                    class="px-2 py-1 border border-emerald-600 text-emerald-400 hover:bg-emerald-500 hover:text-black uppercase text-[8px]"
                                >
                                    Edit
                                </button>
                                <button
                                    v-if="isTrashView"
                                    @click="restoreUser(user)"
                                    class="px-2 py-1 border border-emerald-600 text-emerald-300 hover:bg-emerald-500 hover:text-black uppercase text-[8px]"
                                >
                                    Restore
                                </button>
                                <button
                                    v-if="isTrashView"
                                    @click="hardDeleteUser(user)"
                                    class="px-2 py-1 border border-red-700 text-red-400 hover:bg-red-700 hover:text-white uppercase text-[8px]"
                                >
                                    Hard_Delete
                                </button>
                            </td>
                        </tr>
                        <tr v-if="rows.length === 0">
                            <td :colspan="10" class="py-8 px-2 text-center text-slate-500 uppercase">
                                NO_USERS_MATCH_FILTER
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div class="mt-5 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                    <p class="text-[8px] text-slate-500 uppercase">
                        PAGE {{ users.current_page || 1 }} / {{ users.last_page || 1 }} | TOTAL {{ users.total || 0 }}
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

        <div v-if="selectedUser" class="fixed inset-0 z-[220] bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="w-full max-w-2xl rpg-panel border-cyan-500/40 max-h-[90vh] overflow-y-auto modal-scroll">
                <h2 class="text-white uppercase mb-4">Edit_User: {{ selectedUser.name || selectedUser.username }}</h2>

                <div class="space-y-3">
                    <div class="space-y-2">
                        <p class="section-label">Basic_Profile</p>
                        <input
                            v-model="editForm.name"
                            type="text"
                            placeholder="REAL_NAME"
                            class="admin-input"
                        />
                        <p v-if="editForm.errors.name" class="text-red-500 text-[8px]">{{ editForm.errors.name }}</p>

                        <input
                            v-model="editForm.username"
                            type="text"
                            placeholder="USERNAME"
                            class="admin-input"
                        />
                        <p v-if="editForm.errors.username" class="text-red-500 text-[8px]">{{ editForm.errors.username }}</p>

                        <input
                            v-model="editForm.email"
                            type="email"
                            placeholder="EMAIL"
                            class="admin-input"
                        />
                        <p v-if="editForm.errors.email" class="text-red-500 text-[8px]">{{ editForm.errors.email }}</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <select
                            v-model="editForm.role"
                            class="admin-input uppercase"
                        >
                            <option v-for="role in availableRoles" :key="role" :value="role">{{ role }}</option>
                        </select>
                        <select
                            v-model="editForm.job_id"
                            class="admin-input uppercase"
                        >
                            <option value="">JOB_ROLE: NONE</option>
                            <option v-for="job in jobRoles || []" :key="job.id" :value="String(job.id)">
                                {{ job.name }}
                            </option>
                        </select>
                        <input
                            v-model.number="editForm.level"
                            type="number"
                            min="1"
                            placeholder="LEVEL"
                            class="admin-input"
                        />
                    </div>
                    <p v-if="editForm.errors.role" class="text-red-500 text-[8px]">{{ editForm.errors.role }}</p>
                    <p v-if="editForm.errors.level" class="text-red-500 text-[8px]">{{ editForm.errors.level }}</p>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <input
                            v-model.number="editForm.exp"
                            type="number"
                            min="0"
                            placeholder="EXP"
                            class="admin-input"
                        />
                        <input
                            v-model.number="editForm.gold"
                            type="number"
                            min="0"
                            placeholder="GOLD"
                            class="admin-input"
                        />
                    </div>
                    <p v-if="editForm.errors.exp" class="text-red-500 text-[8px]">{{ editForm.errors.exp }}</p>
                    <p v-if="editForm.errors.gold" class="text-red-500 text-[8px]">{{ editForm.errors.gold }}</p>

                    <div class="border-t border-slate-700 pt-4 space-y-3">
                        <p class="section-label">Avatar</p>
                        <div class="flex items-center gap-3">
                            <div class="avatar-preview" :class="{ 'is-empty': !avatarPreview }">
                                <img v-if="avatarPreview" :src="avatarPreview" alt="Avatar preview" />
                                <span v-else>NO_AVATAR</span>
                            </div>
                            <div class="flex-1 space-y-2">
                                <input
                                    type="file"
                                    accept="image/png, image/jpeg"
                                    class="admin-input file-input"
                                    @change="handleAvatarChange"
                                />
                                <label class="inline-flex items-center gap-2 text-[8px] text-slate-400 uppercase">
                                    <input
                                        v-model="editForm.remove_avatar"
                                        type="checkbox"
                                        class="accent-cyan-400"
                                        @change="handleRemoveAvatar"
                                    />
                                    Remove_Avatar
                                </label>
                                <p v-if="editForm.errors.profile_photo" class="text-red-500 text-[8px]">{{ editForm.errors.profile_photo }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-slate-700 pt-4 space-y-3">
                        <p class="section-label">Mentor_Detail</p>
                        <textarea
                            v-model="editForm.bio"
                            rows="3"
                            placeholder="BIO_MENTOR"
                            class="admin-textarea"
                        ></textarea>
                        <p v-if="editForm.errors.bio" class="text-red-500 text-[8px]">{{ editForm.errors.bio }}</p>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <input
                                v-model="editForm.experience"
                                type="text"
                                placeholder="PENGALAMAN (contoh: 5+ tahun)"
                                class="admin-input"
                            />
                            <input
                                v-model="editForm.location"
                                type="text"
                                placeholder="LOKASI (contoh: Jakarta / Remote)"
                                class="admin-input"
                            />
                        </div>
                        <p v-if="editForm.errors.experience" class="text-red-500 text-[8px]">{{ editForm.errors.experience }}</p>
                        <p v-if="editForm.errors.location" class="text-red-500 text-[8px]">{{ editForm.errors.location }}</p>

                        <input
                            v-model="editForm.skills_text"
                            type="text"
                            placeholder="SKILLS (pisahkan dengan koma)"
                            class="admin-input"
                        />
                        <p v-if="editForm.errors.skills_text" class="text-red-500 text-[8px]">{{ editForm.errors.skills_text }}</p>
                    </div>

                    <div class="border-t border-slate-700 pt-4 space-y-3">
                        <p class="section-label">Optional_Reset_Password</p>
                        <input
                            v-model="editForm.password"
                            type="password"
                            placeholder="NEW_PASSWORD"
                            class="admin-input text-yellow-400"
                        />
                        <input
                            v-model="editForm.password_confirmation"
                            type="password"
                            placeholder="CONFIRM_PASSWORD"
                            class="admin-input text-yellow-400"
                        />
                        <p v-if="editForm.errors.password" class="text-red-500 text-[8px]">{{ editForm.errors.password }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-2 mt-6">
                    <button
                        @click="submitEdit"
                        :disabled="editForm.processing"
                        class="md:col-span-2 py-3 border-2 border-cyan-400 text-cyan-400 hover:bg-cyan-400 hover:text-black uppercase"
                    >
                        {{ editForm.processing ? 'SAVING...' : 'SAVE_ALL_DATA' }}
                    </button>
                    <button
                        @click="deleteUser"
                        class="py-3 border-2 border-red-600 text-red-400 hover:bg-red-600 hover:text-white uppercase"
                    >
                        ARCHIVE_ACCOUNT
                    </button>
                </div>

                <button
                    @click="closeModal"
                    class="w-full mt-2 py-3 border-2 border-slate-600 text-slate-400 hover:bg-slate-700 hover:text-white uppercase"
                >
                    Close
                </button>
            </div>
        </div>

        <div v-if="cropModalOpen" class="fixed inset-0 z-[240] bg-black/85 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="cropper-card">
                <div class="cropper-frame">
                    <img ref="cropImageRef" :src="cropSourceUrl" alt="Crop avatar" />
                </div>
                <div class="mt-4 flex items-center justify-between">
                    <p class="text-[8px] text-slate-400 uppercase">Crop_Avatar_1_1</p>
                    <div class="flex gap-2">
                        <button
                            type="button"
                            class="px-3 py-2 border-2 border-slate-600 text-slate-300 hover:bg-slate-700 uppercase text-[8px]"
                            @click="closeCropper"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            class="px-3 py-2 border-2 border-cyan-400 text-cyan-300 hover:bg-cyan-400 hover:text-black uppercase text-[8px]"
                            @click="applyCrop"
                        >
                            Use_Crop
                        </button>
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

.section-label {
    font-size: 8px;
    text-transform: uppercase;
    color: #64748b;
    letter-spacing: 0.18em;
}

.admin-input {
    width: 100%;
    background: #000000;
    border: 2px solid #334155;
    padding: 6px 8px;
    font-size: 9px;
    color: #4ed4d4;
    outline: none;
}

.admin-textarea {
    width: 100%;
    background: #000000;
    border: 2px solid #334155;
    padding: 6px 8px;
    font-size: 9px;
    color: #4ed4d4;
    outline: none;
    resize: vertical;
}

.admin-input:focus,
.admin-textarea:focus {
    border-color: rgba(78, 212, 212, 0.6);
    box-shadow: 0 0 0 1px rgba(78, 212, 212, 0.25);
}

.file-input {
    padding: 4px 6px;
}

.avatar-preview {
    width: 64px;
    height: 64px;
    border: 2px solid #334155;
    background: #0f172a;
    display: grid;
    place-items: center;
    color: #64748b;
    font-size: 7px;
    text-transform: uppercase;
}

.avatar-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.modal-scroll::-webkit-scrollbar {
    width: 6px;
}

.modal-scroll::-webkit-scrollbar-thumb {
    background: rgba(78, 212, 212, 0.35);
    border-radius: 999px;
}

.modal-scroll::-webkit-scrollbar-track {
    background: rgba(15, 23, 42, 0.6);
}

.cropper-card {
    width: min(520px, 92vw);
    background: #0b0f1a;
    border: 2px solid rgba(78, 212, 212, 0.5);
    padding: 16px;
    box-shadow: 10px 10px 0 rgba(0, 0, 0, 0.4);
}

.cropper-frame {
    width: 100%;
    height: 360px;
    background: #0f172a;
    border: 2px solid rgba(148, 163, 184, 0.4);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.cropper-frame img {
    max-width: 100%;
    display: block;
}
</style>
