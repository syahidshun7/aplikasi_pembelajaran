<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminNavbar from '@/Components/AdminNavbar.vue';
import Swal from 'sweetalert2';

const props = defineProps({
    items: Object,
    filters: Object,
});

const isEditing = ref(false);
const editId = ref(null);
const showDeleteModal = ref(false);
const deleteId = ref(null);

const form = useForm({
    code: '',
    name: '',
    description: '',
    price_gold: 0,
    is_active: true,
    icon: null,
    _method: 'POST',
});

const filterForm = useForm({
    search: props.filters?.search || '',
});

const shopItems = computed(() => props.items?.data || []);
const paginationLinks = computed(() => props.items?.links || []);

const startEdit = (item) => {
    isEditing.value = true;
    editId.value = item.id;
    form.code = item.code;
    form.name = item.name;
    form.description = item.description || '';
    form.price_gold = item.price_gold ?? 0;
    form.is_active = !!item.is_active;
    form.icon = null;
    form._method = 'PUT';
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

const cancelEdit = () => {
    isEditing.value = false;
    editId.value = null;
    form.reset();
    form.price_gold = 0;
    form.is_active = true;
    form._method = 'POST';
};

const submit = () => {
    const endpoint = isEditing.value
        ? route('admin.shop-items.update', editId.value)
        : route('admin.shop-items.store');

    form.post(endpoint, {
        onSuccess: () => {
            if (isEditing.value) {
                cancelEdit();
            } else {
                form.reset();
                form.price_gold = 0;
                form.is_active = true;
                form._method = 'POST';
            }
        },
        onError: (errors) => {
            Swal.fire({
                icon: 'error',
                title: isEditing.value ? 'UPDATE_FAILED' : 'CREATE_FAILED',
                text: Object.values(errors)[0] || 'UNKNOWN_ERROR',
                background: '#1a1c2c',
                color: '#ff4d4d',
            });
        },
    });
};

const onIconChange = (event) => {
    form.icon = event.target.files[0] || null;
};

const confirmDelete = (id) => {
    deleteId.value = id;
    showDeleteModal.value = true;
};

const executeDelete = () => {
    if (!deleteId.value) return;
    form.delete(route('admin.shop-items.destroy', deleteId.value), {
        onSuccess: () => {
            showDeleteModal.value = false;
            deleteId.value = null;
            if (isEditing.value && editId.value === deleteId.value) {
                cancelEdit();
            }
        },
    });
};

const applyFilters = () => {
    router.get(route('admin.shop-items.index'), filterForm.data(), {
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
</script>

<template>
    <Head title="SHOP_ITEM_MANAGEMENT" />

    <div class="min-h-screen bg-[#0d1117] p-4 md:p-8 font-['Press_Start_2P'] text-[#4ed4d4] text-[10px] relative">
        <div class="max-w-7xl mx-auto space-y-8">
            <AdminNavbar />

            <div class="flex justify-between items-center border-b-4 border-amber-900 pb-4">
                <h1 class="text-xl uppercase tracking-widest">Shop_Item_Registry</h1>
                <Link :href="route('dashboard')" class="text-slate-500 hover:text-white transition-colors uppercase">[Back_to_HQ]</Link>
            </div>

            <div class="grid grid-cols-12 gap-8">
                <div class="col-span-12 lg:col-span-5">
                    <div class="rpg-panel" :class="isEditing ? 'border-green-500/50' : 'border-amber-500/50'">
                        <h2 class="mb-6 uppercase tracking-tighter" :class="isEditing ? 'text-green-500' : 'text-amber-400'">
                            >> {{ isEditing ? 'UPDATE_ITEM_ID_' + editId : 'CREATE_NEW_ITEM' }}
                        </h2>

                        <form @submit.prevent="submit" class="space-y-5">
                            <div>
                                <label class="block mb-2 text-white uppercase">ITEM_CODE:</label>
                                <input
                                    v-model="form.code"
                                    type="text"
                                    class="w-full bg-black border-2 border-slate-700 p-2 focus:border-amber-400 outline-none text-amber-300 uppercase"
                                    placeholder="TIME_KEY"
                                    required
                                >
                            </div>

                            <div>
                                <label class="block mb-2 text-white uppercase">ITEM_NAME:</label>
                                <input
                                    v-model="form.name"
                                    type="text"
                                    class="w-full bg-black border-2 border-slate-700 p-2 focus:border-amber-400 outline-none text-amber-300"
                                    placeholder="Time Key"
                                    required
                                >
                            </div>

                            <div>
                                <label class="block mb-2 text-white uppercase">PRICE_GOLD:</label>
                                <input
                                    v-model.number="form.price_gold"
                                    type="number"
                                    min="0"
                                    class="w-full bg-black border-2 border-slate-700 p-2 focus:border-amber-400 outline-none text-yellow-300"
                                    required
                                >
                            </div>

                            <div>
                                <label class="block mb-2 text-white uppercase">DESCRIPTION:</label>
                                <textarea
                                    v-model="form.description"
                                    class="w-full bg-black border-2 border-slate-700 p-2 text-[12px] font-sans text-slate-200 focus:border-amber-400 focus:ring-0"
                                    style="resize: vertical; min-height: 100px;"
                                ></textarea>
                            </div>

                            <div>
                                <label class="block mb-2 text-white uppercase">ITEM_ICON:</label>
                                <input
                                    type="file"
                                    accept="image/*"
                                    @change="onIconChange"
                                    class="w-full bg-black border-2 border-slate-700 p-2 text-[8px] text-amber-300 uppercase"
                                >
                            </div>

                            <div class="flex items-center gap-2">
                                <input id="is_active" v-model="form.is_active" type="checkbox" class="accent-amber-400">
                                <label for="is_active" class="text-[9px] text-amber-300 uppercase">Item_Active</label>
                            </div>

                            <div class="flex gap-2">
                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="flex-1 py-3 border-2 uppercase font-bold transition-all"
                                    :class="isEditing ? 'border-green-500 text-green-500 hover:bg-green-600 hover:text-black' : 'border-amber-400 text-amber-300 hover:bg-amber-400 hover:text-black'"
                                >
                                    {{ form.processing ? 'PROCESSING...' : (isEditing ? 'UPDATE_ITEM' : 'CREATE_ITEM') }}
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
                        <h2 class="text-white mb-6 uppercase tracking-tighter">>> SHOP_ITEMS_BOARD</h2>

                        <div class="mb-4 flex flex-col md:flex-row gap-2">
                            <input
                                v-model="filterForm.search"
                                type="text"
                                placeholder="SEARCH CODE / NAME"
                                class="flex-1 bg-black border-2 border-slate-700 p-2 text-amber-300 uppercase outline-none"
                                @keyup.enter="applyFilters"
                            />
                            <button
                                @click="applyFilters"
                                class="px-3 py-2 border-2 border-amber-400 text-amber-300 hover:bg-amber-400 hover:text-black uppercase"
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
                                v-for="item in shopItems"
                                :key="item.id"
                                class="flex flex-col p-4 bg-slate-900/50 border-l-4 border-amber-500 hover:bg-slate-800 transition-all"
                            >
                                <div class="flex justify-between items-start mb-2 gap-3">
                                    <div class="flex-1">
                                        <div class="text-[8px] text-slate-500 mb-1 uppercase tracking-tighter">
                                            ID: {{ item.id }} // {{ item.code }}
                                        </div>
                                        <div class="text-white uppercase">{{ item.name }}</div>
                                    </div>
                                    <img
                                        v-if="item.icon_path"
                                        :src="`/storage/${item.icon_path}`"
                                        alt="Item icon"
                                        class="w-10 h-10 object-cover border border-amber-500/60"
                                    >
                                </div>

                                <div class="text-[8px] text-yellow-300 mb-2 uppercase tracking-tighter">
                                    PRICE: {{ item.price_gold }} GOLD
                                </div>

                                <div class="text-[8px] mb-2 uppercase"
                                    :class="item.is_active ? 'text-emerald-400' : 'text-slate-500'">
                                    STATUS: {{ item.is_active ? 'ACTIVE' : 'INACTIVE' }}
                                </div>

                                <div class="text-[8px] text-cyan-400 mb-2 uppercase tracking-tighter">
                                    OWNED_USERS: {{ item.inventories_count || 0 }}
                                </div>

                                <div
                                    v-if="item.description"
                                    class="text-[7px] text-slate-500 italic mb-4 border-t border-slate-800 pt-2 leading-loose font-sans"
                                >
                                    {{ item.description }}
                                </div>

                                <div class="flex gap-4 self-end mt-2">
                                    <button
                                        @click="startEdit(item)"
                                        class="text-green-500 hover:text-white text-[8px] uppercase font-bold"
                                    >
                                        [Edit]
                                    </button>
                                    <button
                                        @click="confirmDelete(item.id)"
                                        class="text-red-500 hover:text-white text-[8px] uppercase font-bold"
                                    >
                                        [Delete]
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="mt-5 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                            <p class="text-[8px] text-slate-500 uppercase">
                                PAGE {{ items.current_page || 1 }} / {{ items.last_page || 1 }}
                                | TOTAL {{ items.total || 0 }}
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
                                            ? 'border-amber-400 text-amber-300 bg-amber-900/20'
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
                    <h2 class="text-red-500 font-bold uppercase tracking-tighter text-[10px]">WARNING: DELETE_ITEM</h2>
                </div>
                <div class="p-8 space-y-6">
                    <div class="border-l-2 border-red-900 pl-4">
                        <p class="text-slate-200 text-[10px] leading-relaxed uppercase">
                            Are you sure you want to remove this shop item?
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
    background: #f59e0b;
}
</style>
