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
});

const selectedUser = ref(null);
const roleForm = useForm({
    role: '',
});

const passwordForm = useForm({
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
    applyFilters();
};

const goToPage = (url) => {
    if (!url) return;
    router.get(url, {}, {
        preserveState: true,
        preserveScroll: true,
    });
};

const openRoleModal = (user) => {
    selectedUser.value = user;
    roleForm.role = user.role || 'user';
};

const openPasswordModal = (user) => {
    selectedUser.value = user;
    passwordForm.reset();
};

const closeModal = () => {
    selectedUser.value = null;
    roleForm.reset();
    passwordForm.reset();
};

const submitRole = () => {
    if (!selectedUser.value) return;

    roleForm.patch(route('admin.users.role.update', selectedUser.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            closeModal();
            Swal.fire({
                icon: 'success',
                title: 'ROLE_UPDATED',
                timer: 1600,
                showConfirmButton: false,
                background: '#1a1c2c',
                color: '#4ed4d4',
            });
        },
    });
};

const submitPasswordReset = () => {
    if (!selectedUser.value) return;

    passwordForm.patch(route('admin.users.password.reset', selectedUser.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            closeModal();
            Swal.fire({
                icon: 'success',
                title: 'PASSWORD_RESET_SUCCESS',
                timer: 1600,
                showConfirmButton: false,
                background: '#1a1c2c',
                color: '#4ed4d4',
            });
        },
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

            <div class="rpg-panel border-slate-700 overflow-x-auto">
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
                        class="w-full md:w-56 bg-black border-2 border-slate-700 p-2 text-cyan-400 uppercase outline-none"
                    >
                        <option value="all">ALL_ROLES</option>
                        <option v-for="role in availableRoles" :key="role" :value="role">{{ role }}</option>
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

                <table class="w-full min-w-[980px] text-left">
                    <thead class="text-[8px] uppercase border-b border-slate-700 text-slate-500">
                        <tr>
                            <th class="py-3 px-2">ID</th>
                            <th class="py-3 px-2">Name</th>
                            <th class="py-3 px-2">Email</th>
                            <th class="py-3 px-2">Role</th>
                            <th class="py-3 px-2">Progress</th>
                            <th class="py-3 px-2">Avg_Grade</th>
                            <th class="py-3 px-2">Joined</th>
                            <th class="py-3 px-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="user in rows" :key="user.id" class="border-b border-slate-800 hover:bg-slate-900/40">
                            <td class="py-4 px-2 text-slate-400">{{ user.id }}</td>
                            <td class="py-4 px-2">
                                <p class="text-white uppercase">{{ user.username || user.name }}</p>
                                <p class="text-[8px] text-slate-500">{{ user.name }}</p>
                            </td>
                            <td class="py-4 px-2 text-slate-300">{{ user.email }}</td>
                            <td class="py-4 px-2">
                                <span
                                    class="px-2 py-1 border text-[8px] uppercase"
                                    :class="user.role === 'admin' ? 'text-red-400 border-red-900 bg-red-900/20' : 'text-cyan-400 border-cyan-900 bg-cyan-900/20'"
                                >
                                    {{ user.role || 'user' }}
                                </span>
                            </td>
                            <td class="py-4 px-2 text-[8px] space-y-1">
                                <p>LVL {{ user.level || 1 }} | EXP {{ user.exp || 0 }}</p>
                                <p class="text-yellow-500">GOLD {{ user.gold || 0 }}</p>
                                <p class="text-slate-500">SUBMISSIONS {{ user.submissions_count || 0 }}</p>
                            </td>
                            <td class="py-4 px-2">
                                <span class="px-2 py-1 border text-[8px] uppercase"
                                    :class="(Number(user.avg_grade || 0) >= 75)
                                        ? 'text-emerald-400 border-emerald-800 bg-emerald-900/20'
                                        : 'text-orange-400 border-orange-800 bg-orange-900/20'">
                                    {{ Number(user.avg_grade || 0).toFixed(1) }}%
                                </span>
                            </td>
                            <td class="py-4 px-2 text-slate-500 text-[8px]">{{ formatDate(user.created_at) }}</td>
                            <td class="py-4 px-2 text-right space-x-2">
                                <button
                                    @click="openRoleModal(user)"
                                    class="px-2 py-1 border border-emerald-600 text-emerald-400 hover:bg-emerald-500 hover:text-black uppercase text-[8px]"
                                >
                                    Role
                                </button>
                                <button
                                    @click="openPasswordModal(user)"
                                    class="px-2 py-1 border border-yellow-600 text-yellow-400 hover:bg-yellow-500 hover:text-black uppercase text-[8px]"
                                >
                                    Reset_Password
                                </button>
                            </td>
                        </tr>
                        <tr v-if="rows.length === 0">
                            <td colspan="8" class="py-8 px-2 text-center text-slate-500 uppercase">
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
                <h2 class="text-white uppercase mb-6">Edit_User: {{ selectedUser.username || selectedUser.name }}</h2>

                <div class="space-y-8">
                    <section class="space-y-4">
                        <p class="text-[8px] text-slate-500 uppercase">Update Role</p>
                        <select
                            v-model="roleForm.role"
                            class="w-full bg-black border-2 border-slate-700 p-2 text-cyan-400 uppercase outline-none"
                        >
                            <option v-for="role in availableRoles" :key="role" :value="role">{{ role }}</option>
                        </select>
                        <p v-if="roleForm.errors.role" class="text-red-500 text-[8px]">{{ roleForm.errors.role }}</p>
                        <button
                            @click="submitRole"
                            :disabled="roleForm.processing"
                            class="w-full py-3 border-2 border-cyan-400 text-cyan-400 hover:bg-cyan-400 hover:text-black uppercase"
                        >
                            {{ roleForm.processing ? 'UPDATING...' : 'SAVE_ROLE' }}
                        </button>
                    </section>

                    <section class="space-y-4 border-t border-slate-700 pt-6">
                        <p class="text-[8px] text-slate-500 uppercase">Reset Password</p>
                        <input
                            v-model="passwordForm.password"
                            type="password"
                            placeholder="NEW_PASSWORD"
                            class="w-full bg-black border-2 border-slate-700 p-2 text-yellow-400 uppercase outline-none"
                        />
                        <input
                            v-model="passwordForm.password_confirmation"
                            type="password"
                            placeholder="CONFIRM_PASSWORD"
                            class="w-full bg-black border-2 border-slate-700 p-2 text-yellow-400 uppercase outline-none"
                        />
                        <p v-if="passwordForm.errors.password" class="text-red-500 text-[8px]">{{ passwordForm.errors.password }}</p>
                        <button
                            @click="submitPasswordReset"
                            :disabled="passwordForm.processing"
                            class="w-full py-3 border-2 border-yellow-500 text-yellow-500 hover:bg-yellow-500 hover:text-black uppercase"
                        >
                            {{ passwordForm.processing ? 'PROCESSING...' : 'RESET_PASSWORD' }}
                        </button>
                    </section>
                </div>

                <button
                    @click="closeModal"
                    class="w-full mt-6 py-3 border-2 border-slate-600 text-slate-400 hover:bg-slate-700 hover:text-white uppercase"
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
