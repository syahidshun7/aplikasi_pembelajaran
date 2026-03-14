<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Swal from 'sweetalert2';
import AdminNavbar from '@/Components/AdminNavbar.vue';

const props = defineProps({
    users: Object,
    availableRoles: Array,
    filters: Object,
});

const filterForm = useForm({
    search: props.filters?.search || '',
    role: props.filters?.role || 'all',
    rank_by: props.filters?.rank_by || 'newest',
    grade_order: props.filters?.grade_order || 'none',
});

const selectedUser = ref(null);
const editForm = useForm({
    name: '',
    username: '',
    email: '',
    role: 'user',
    gold: 0,
    exp: 0,
    level: 1,
    password: '',
    password_confirmation: '',
});

const rows = computed(() => props.users?.data || []);
const paginationLinks = computed(() => props.users?.links || []);

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
    editForm.name = user.name || '';
    editForm.username = user.username || '';
    editForm.email = user.email || '';
    editForm.role = user.role || 'user';
    editForm.gold = Number(user.gold || 0);
    editForm.exp = Number(user.exp || 0);
    editForm.level = Number(user.level_display || user.level || 1);
    editForm.password = '';
    editForm.password_confirmation = '';
    editForm.clearErrors();
};

const closeModal = () => {
    selectedUser.value = null;
    editForm.reset();
    editForm.clearErrors();
};

const submitEdit = () => {
    if (!selectedUser.value) return;

    editForm.patch(route('admin.users.update', selectedUser.value.id), {
        preserveScroll: true,
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
        title: 'HAPUS_AKUN_USER?',
        text: `Akun ${selectedUser.value.name || selectedUser.value.username} akan dihapus permanen.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'YA_HAPUS',
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
                    title: 'USER_DELETED',
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
                            <th class="py-3 px-2">Joined</th>
                            <th class="py-3 px-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="user in rows" :key="user.id" class="border-b border-slate-800 hover:bg-slate-900/40">
                            <td class="py-4 px-2 text-slate-400">{{ user.id }}</td>
                            <td class="py-4 px-2 text-white">{{ user.name || '-' }}</td>
                            <td class="py-4 px-2 text-slate-300">{{ user.username || '-' }}</td>
                            <td class="py-4 px-2 text-slate-300">{{ user.email }}</td>
                            <td class="py-4 px-2">
                                <span
                                    class="px-2 py-1 border text-[8px] uppercase"
                                    :class="user.role === 'admin'
                                        ? 'text-red-400 border-red-900 bg-red-900/20'
                                        : (user.role === 'mentor'
                                            ? 'text-violet-300 border-violet-800 bg-violet-900/20'
                                            : 'text-cyan-400 border-cyan-900 bg-cyan-900/20')"
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
                            <td class="py-4 px-2 text-slate-500 text-[8px]">{{ formatDate(user.created_at) }}</td>
                            <td class="py-4 px-2 text-right space-x-2">
                                <button
                                    @click="openEditModal(user)"
                                    class="px-2 py-1 border border-emerald-600 text-emerald-400 hover:bg-emerald-500 hover:text-black uppercase text-[8px]"
                                >
                                    Edit
                                </button>
                            </td>
                        </tr>
                        <tr v-if="rows.length === 0">
                            <td colspan="9" class="py-8 px-2 text-center text-slate-500 uppercase">
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

        <div v-if="selectedUser" class="fixed inset-0 z-[90] bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="w-full max-w-xl rpg-panel border-cyan-500/40">
                <h2 class="text-white uppercase mb-6">Edit_User: {{ selectedUser.name || selectedUser.username }}</h2>

                <div class="space-y-3">
                    <input
                        v-model="editForm.name"
                        type="text"
                        placeholder="REAL_NAME"
                        class="w-full bg-black border-2 border-slate-700 p-2 text-cyan-400 outline-none"
                    />
                    <p v-if="editForm.errors.name" class="text-red-500 text-[8px]">{{ editForm.errors.name }}</p>

                    <input
                        v-model="editForm.username"
                        type="text"
                        placeholder="USERNAME"
                        class="w-full bg-black border-2 border-slate-700 p-2 text-cyan-400 outline-none"
                    />
                    <p v-if="editForm.errors.username" class="text-red-500 text-[8px]">{{ editForm.errors.username }}</p>

                    <input
                        v-model="editForm.email"
                        type="email"
                        placeholder="EMAIL"
                        class="w-full bg-black border-2 border-slate-700 p-2 text-cyan-400 outline-none"
                    />
                    <p v-if="editForm.errors.email" class="text-red-500 text-[8px]">{{ editForm.errors.email }}</p>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <select
                            v-model="editForm.role"
                            class="w-full bg-black border-2 border-slate-700 p-2 text-cyan-400 uppercase outline-none"
                        >
                            <option v-for="role in availableRoles" :key="role" :value="role">{{ role }}</option>
                        </select>
                        <input
                            v-model.number="editForm.level"
                            type="number"
                            min="1"
                            placeholder="LEVEL"
                            class="w-full bg-black border-2 border-slate-700 p-2 text-cyan-400 outline-none"
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
                            class="w-full bg-black border-2 border-slate-700 p-2 text-cyan-400 outline-none"
                        />
                        <input
                            v-model.number="editForm.gold"
                            type="number"
                            min="0"
                            placeholder="GOLD"
                            class="w-full bg-black border-2 border-slate-700 p-2 text-cyan-400 outline-none"
                        />
                    </div>
                    <p v-if="editForm.errors.exp" class="text-red-500 text-[8px]">{{ editForm.errors.exp }}</p>
                    <p v-if="editForm.errors.gold" class="text-red-500 text-[8px]">{{ editForm.errors.gold }}</p>

                    <div class="border-t border-slate-700 pt-4 space-y-3">
                        <p class="text-[8px] text-slate-500 uppercase">Optional_Reset_Password</p>
                        <input
                            v-model="editForm.password"
                            type="password"
                            placeholder="NEW_PASSWORD"
                            class="w-full bg-black border-2 border-slate-700 p-2 text-yellow-400 outline-none"
                        />
                        <input
                            v-model="editForm.password_confirmation"
                            type="password"
                            placeholder="CONFIRM_PASSWORD"
                            class="w-full bg-black border-2 border-slate-700 p-2 text-yellow-400 outline-none"
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
                        DELETE_ACCOUNT
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
