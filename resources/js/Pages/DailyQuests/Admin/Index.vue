<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminNavbar from '@/Components/AdminNavbar.vue';

const props = defineProps({
    definitions: Object,
    stats: Object,
    activityTypes: Array,
    filters: Object,
});

const showFormModal = ref(false);
const showDeleteModal = ref(false);
const isEditing = ref(false);
const editId = ref(null);
const deleteId = ref(null);

const filterForm = useForm({
    search: props.filters?.search || '',
    activity_type: props.filters?.activity_type || 'all',
});

const form = useForm({
    code: '',
    title: '',
    description: '',
    activity_type: 'login',
    target_value: 1,
    reward_exp: 0,
    reward_gold: 0,
    sort_order: 1,
    is_active: true,
    activity_steps_text: '',
    meta_category: '',
    meta_icon: '',
    _method: 'POST',
});

const items = computed(() => props.definitions?.data || []);
const paginationLinks = computed(() => props.definitions?.links || []);

const openCreateModal = () => {
    isEditing.value = false;
    editId.value = null;
    form.reset();
    form.code = '';
    form.title = '';
    form.description = '';
    form.activity_type = props.activityTypes?.[0] || 'login';
    form.target_value = 1;
    form.reward_exp = 0;
    form.reward_gold = 0;
    form.sort_order = 1;
    form.is_active = true;
    form.activity_steps_text = '';
    form.meta_category = '';
    form.meta_icon = '';
    form._method = 'POST';
    showFormModal.value = true;
};

const openEditModal = (item) => {
    isEditing.value = true;
    editId.value = item.id;
    form.code = item.code || '';
    form.title = item.title || '';
    form.description = item.description || '';
    form.activity_type = item.activity_type || (props.activityTypes?.[0] || 'login');
    form.target_value = Number(item.target_value || 1);
    form.reward_exp = Number(item.reward_exp || 0);
    form.reward_gold = Number(item.reward_gold || 0);
    form.sort_order = Number(item.sort_order || 1);
    form.is_active = !!item.is_active;
    form.activity_steps_text = item.activity_steps_text || '';
    form.meta_category = '';
    form.meta_icon = '';
    form._method = 'PUT';
    showFormModal.value = true;
};

const closeFormModal = () => {
    showFormModal.value = false;
    isEditing.value = false;
    editId.value = null;
    form.clearErrors();
};

const submitForm = () => {
    const endpoint = isEditing.value
        ? route('admin.daily-quest-definitions.update', editId.value)
        : route('admin.daily-quest-definitions.store');

    form.post(endpoint, {
        onSuccess: () => {
            closeFormModal();
        },
    });
};

const openDeleteModal = (id) => {
    deleteId.value = id;
    showDeleteModal.value = true;
};

const executeDelete = () => {
    if (!deleteId.value) return;

    router.delete(route('admin.daily-quest-definitions.destroy', deleteId.value), {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteModal.value = false;
            deleteId.value = null;
        },
    });
};

const applyFilters = () => {
    router.get(route('admin.daily-quest-definitions.index'), filterForm.data(), {
        preserveState: true,
        preserveScroll: true,
    });
};

const resetFilters = () => {
    filterForm.search = '';
    filterForm.activity_type = 'all';
    applyFilters();
};

const goToPage = (url) => {
    if (!url) return;

    router.get(url, {}, {
        preserveState: true,
        preserveScroll: true,
    });
};

const formatActivityType = (value) => {
    const map = {
        login: 'LOGIN',
        quest_submission: 'QUEST_SUBMISSION',
        event_attendance: 'EVENT_ATTENDANCE',
    };

    return map[value] || String(value || '').toUpperCase();
};
</script>

<template>
    <Head title="DAILY_QUEST_DEFINITIONS" />

    <div class="min-h-screen bg-[#0d1117] p-4 md:p-8 font-['Press_Start_2P'] text-[#4ed4d4] text-[10px] relative">
        <div class="max-w-7xl mx-auto space-y-8">
            <AdminNavbar />

            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3 border-b-4 border-cyan-900 pb-4">
                <h1 class="text-base sm:text-xl uppercase tracking-widest">Daily_Quest_Definitions</h1>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="inline-flex items-center justify-center px-3 py-2 border border-cyan-500 bg-cyan-900/20 text-cyan-300 hover:bg-cyan-500 hover:text-black uppercase text-[9px] sm:text-[10px]"
                        @click="openCreateModal"
                    >
                        [New_Definition]
                    </button>
                    <Link
                        :href="route('dashboard')"
                        class="inline-flex items-center justify-center px-3 py-2 border border-slate-600 bg-slate-900/40 text-slate-300 hover:text-white uppercase text-[9px] sm:text-[10px]"
                    >
                        [Back_to_HQ]
                    </Link>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="rpg-panel border-cyan-700">
                    <p class="text-[8px] text-slate-400 uppercase mb-2">TOTAL</p>
                    <strong class="text-cyan-300 text-[12px]">{{ Number(stats?.total || 0) }}</strong>
                </div>
                <div class="rpg-panel border-emerald-700">
                    <p class="text-[8px] text-slate-400 uppercase mb-2">ACTIVE</p>
                    <strong class="text-emerald-300 text-[12px]">{{ Number(stats?.active || 0) }}</strong>
                </div>
                <div class="rpg-panel border-slate-700">
                    <p class="text-[8px] text-slate-400 uppercase mb-2">INACTIVE</p>
                    <strong class="text-slate-300 text-[12px]">{{ Number(stats?.inactive || 0) }}</strong>
                </div>
            </div>

            <div class="rpg-panel border-slate-700">
                <div class="mb-4 flex flex-col md:flex-row gap-2">
                    <input
                        v-model="filterForm.search"
                        type="text"
                        placeholder="SEARCH: CODE / TITLE / ACTIVITY"
                        class="flex-1 bg-black border-2 border-slate-700 p-2 text-cyan-300 uppercase outline-none"
                        @keyup.enter="applyFilters"
                    >
                    <select
                        v-model="filterForm.activity_type"
                        class="w-full md:w-56 bg-black border-2 border-slate-700 p-2 text-cyan-300 uppercase outline-none"
                    >
                        <option value="all">ALL_ACTIVITY_TYPES</option>
                        <option v-for="activityType in activityTypes || []" :key="activityType" :value="activityType">
                            {{ formatActivityType(activityType) }}
                        </option>
                    </select>
                    <button
                        @click="applyFilters"
                        class="px-3 py-2 border-2 border-cyan-400 text-cyan-300 hover:bg-cyan-400 hover:text-black uppercase"
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

                <div class="space-y-3 max-h-[620px] overflow-y-auto pr-2 custom-scroll">
                    <article
                        v-for="item in items"
                        :key="item.id"
                        class="p-4 bg-slate-900/50 border-l-4 transition-all"
                        :class="item.is_active ? 'border-cyan-500' : 'border-slate-700'"
                    >
                        <div class="flex items-start justify-between gap-4 mb-2">
                            <div class="space-y-2">
                                <p class="text-[8px] text-slate-500 uppercase tracking-tighter">ID: {{ item.id }} // {{ item.code }}</p>
                                <h3 class="text-white uppercase">{{ item.title }}</h3>
                            </div>
                            <span
                                class="text-[8px] px-2 py-1 border uppercase"
                                :class="item.is_active ? 'border-emerald-700 text-emerald-300 bg-emerald-900/20' : 'border-slate-600 text-slate-400 bg-slate-900/20'"
                            >
                                {{ item.is_active ? 'ACTIVE' : 'INACTIVE' }}
                            </span>
                        </div>

                        <p class="text-[8px] text-slate-400 uppercase mb-2">
                            ACTIVITY: <span class="text-cyan-300">{{ formatActivityType(item.activity_type) }}</span>
                        </p>
                        <p class="text-[8px] text-slate-400 uppercase mb-2">
                            TARGET: <span class="text-yellow-300">{{ item.target_value }}</span>
                            | REWARD_EXP: <span class="text-emerald-300">{{ item.reward_exp }}</span>
                            | REWARD_GOLD: <span class="text-amber-300">{{ item.reward_gold }}</span>
                            | SORT: <span class="text-violet-300">{{ item.sort_order }}</span>
                        </p>
                        <p v-if="item.description" class="text-[8px] text-slate-500 mb-2 font-sans italic">{{ item.description }}</p>

                        <div v-if="item.activity_steps?.length" class="mb-2">
                            <p class="text-[8px] text-slate-400 uppercase mb-1">ACTIVITY_STEPS:</p>
                            <p class="text-[8px] text-slate-500 font-sans">
                                {{ item.activity_steps.join(' | ') }}
                            </p>
                        </div>

                        <div class="flex gap-4 justify-end mt-3">
                            <button
                                type="button"
                                class="text-green-500 hover:text-white text-[8px] uppercase font-bold"
                                @click="openEditModal(item)"
                            >
                                [Edit]
                            </button>
                            <button
                                type="button"
                                class="text-red-500 hover:text-white text-[8px] uppercase font-bold"
                                @click="openDeleteModal(item.id)"
                            >
                                [Delete]
                            </button>
                        </div>
                    </article>

                    <div v-if="items.length === 0" class="text-center text-slate-500 uppercase py-8">
                        NO_DAILY_QUEST_DEFINITION_FOUND
                    </div>
                </div>

                <div class="mt-5 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                    <p class="text-[8px] text-slate-500 uppercase">
                        PAGE {{ definitions.current_page || 1 }} / {{ definitions.last_page || 1 }}
                        | TOTAL {{ definitions.total || 0 }}
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
                                    ? 'border-cyan-400 text-cyan-300 bg-cyan-900/20'
                                    : 'border-slate-700 text-slate-300 hover:bg-slate-700 hover:text-white',
                                !link.url ? 'opacity-40 cursor-not-allowed' : ''
                            ]"
                            v-html="link.label"
                        />
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showFormModal" class="fixed inset-0 z-[120] flex items-center justify-center bg-black/85 backdrop-blur-sm p-4">
            <div class="w-full max-w-3xl max-h-[92vh] overflow-y-auto modal-scroll rpg-panel border-cyan-500/50">
                <h2 class="mb-6 uppercase tracking-tighter text-cyan-300">
                    >> {{ isEditing ? `UPDATE_DEFINITION_${editId}` : 'CREATE_NEW_DEFINITION' }}
                </h2>

                <form @submit.prevent="submitForm" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block mb-2 text-white uppercase">CODE</label>
                            <input v-model="form.code" type="text" class="admin-input" placeholder="daily_login" required>
                            <p v-if="form.errors.code" class="error-text">{{ form.errors.code }}</p>
                        </div>
                        <div>
                            <label class="block mb-2 text-white uppercase">ACTIVITY_TYPE</label>
                            <select v-model="form.activity_type" class="admin-input uppercase">
                                <option v-for="activityType in activityTypes || []" :key="activityType" :value="activityType">
                                    {{ formatActivityType(activityType) }}
                                </option>
                            </select>
                            <p v-if="form.errors.activity_type" class="error-text">{{ form.errors.activity_type }}</p>
                        </div>
                    </div>

                    <div>
                        <label class="block mb-2 text-white uppercase">TITLE</label>
                        <input v-model="form.title" type="text" class="admin-input" required>
                        <p v-if="form.errors.title" class="error-text">{{ form.errors.title }}</p>
                    </div>

                    <div>
                        <label class="block mb-2 text-white uppercase">DESCRIPTION</label>
                        <textarea v-model="form.description" class="admin-textarea"></textarea>
                        <p v-if="form.errors.description" class="error-text">{{ form.errors.description }}</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                        <div>
                            <label class="block mb-2 text-white uppercase">TARGET</label>
                            <input v-model.number="form.target_value" type="number" min="1" class="admin-input" required>
                            <p v-if="form.errors.target_value" class="error-text">{{ form.errors.target_value }}</p>
                        </div>
                        <div>
                            <label class="block mb-2 text-white uppercase">REWARD_EXP</label>
                            <input v-model.number="form.reward_exp" type="number" min="0" class="admin-input" required>
                            <p v-if="form.errors.reward_exp" class="error-text">{{ form.errors.reward_exp }}</p>
                        </div>
                        <div>
                            <label class="block mb-2 text-white uppercase">REWARD_GOLD</label>
                            <input v-model.number="form.reward_gold" type="number" min="0" class="admin-input" required>
                            <p v-if="form.errors.reward_gold" class="error-text">{{ form.errors.reward_gold }}</p>
                        </div>
                        <div>
                            <label class="block mb-2 text-white uppercase">SORT_ORDER</label>
                            <input v-model.number="form.sort_order" type="number" min="1" class="admin-input" required>
                            <p v-if="form.errors.sort_order" class="error-text">{{ form.errors.sort_order }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block mb-2 text-white uppercase">META_CATEGORY</label>
                            <input v-model="form.meta_category" type="text" class="admin-input" placeholder="engagement">
                        </div>
                        <div>
                            <label class="block mb-2 text-white uppercase">META_ICON</label>
                            <input v-model="form.meta_icon" type="text" class="admin-input" placeholder="fi fi-rr-enter">
                        </div>
                    </div>

                    <div>
                        <label class="block mb-2 text-white uppercase">ACTIVITY_STEPS (1 BARIS = 1 STEP)</label>
                        <textarea
                            v-model="form.activity_steps_text"
                            class="admin-textarea"
                            placeholder="Login ke akunmu hari ini.&#10;Buka dashboard sampai progress daily quest tercatat."
                        ></textarea>
                        <p v-if="form.errors.activity_steps_text" class="error-text">{{ form.errors.activity_steps_text }}</p>
                    </div>

                    <label class="inline-flex items-center gap-2 text-[9px] text-cyan-300 uppercase">
                        <input v-model="form.is_active" type="checkbox" class="accent-cyan-400">
                        Is_Active
                    </label>

                    <div class="flex gap-2">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="flex-1 py-3 border-2 border-cyan-400 text-cyan-300 hover:bg-cyan-400 hover:text-black uppercase"
                        >
                            {{ form.processing ? 'PROCESSING...' : (isEditing ? 'UPDATE_DEFINITION' : 'CREATE_DEFINITION') }}
                        </button>
                        <button
                            type="button"
                            class="px-4 py-3 border-2 border-slate-500 text-slate-400 hover:bg-slate-700 hover:text-white uppercase"
                            @click="closeFormModal"
                        >
                            X
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div v-if="showDeleteModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/90 backdrop-blur-md p-4">
            <div class="w-full max-w-lg bg-[#121212] border-2 border-red-600 shadow-[0_0_30px_rgba(220,38,38,0.3)] overflow-hidden rounded-lg">
                <div class="bg-red-600/10 border-b-2 border-red-600 p-4 flex items-center gap-3">
                    <span class="text-red-500 text-lg">!</span>
                    <h2 class="text-red-500 font-bold uppercase tracking-tighter text-[10px]">WARNING: DELETE_DEFINITION</h2>
                </div>
                <div class="p-8 space-y-6">
                    <p class="text-slate-200 text-[10px] leading-relaxed uppercase">
                        Hapus definition ini? Jika definition sudah punya histori quest, sistem akan menolak penghapusan.
                    </p>
                </div>
                <div class="p-6 pt-0 flex gap-4">
                    <button
                        @click="executeDelete"
                        class="flex-1 py-3 bg-red-600/20 border-2 border-red-600 text-red-500 hover:bg-red-600 hover:text-white uppercase font-bold text-[9px]"
                    >
                        PROCEED
                    </button>
                    <button
                        @click="showDeleteModal = false"
                        class="flex-1 py-3 bg-slate-800 border-2 border-slate-600 text-slate-400 hover:bg-slate-700 hover:text-white uppercase font-bold text-[9px]"
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
    box-shadow: 8px 8px 0px 0px rgba(0, 0, 0, 0.5);
}

.admin-input {
    width: 100%;
    background: #000;
    border: 2px solid #334155;
    padding: 8px;
    font-size: 9px;
    color: #67e8f9;
    outline: none;
}

.admin-textarea {
    width: 100%;
    background: #000;
    border: 2px solid #334155;
    padding: 8px;
    min-height: 120px;
    font-size: 12px;
    color: #cbd5e1;
    outline: none;
    resize: vertical;
    font-family: Inter, sans-serif;
}

.admin-input:focus,
.admin-textarea:focus {
    border-color: rgba(103, 232, 249, 0.7);
}

.error-text {
    margin-top: 4px;
    color: #f87171;
    font-size: 8px;
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
</style>
