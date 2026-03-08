<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminNavbar from '@/Components/AdminNavbar.vue';
import Swal from 'sweetalert2';

const props = defineProps({
    item: {
        type: Object,
        required: true,
    },
    transactions: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    stats: {
        type: Object,
        default: () => ({}),
    },
});

const filterForm = useForm({
    search: props.filters?.search || '',
    type: props.filters?.type || '',
});

const applyFilters = () => {
    router.get(route('admin.shop-items.detail', props.item.id), filterForm.data(), {
        preserveState: true,
        preserveScroll: true,
    });
};

const resetFilters = () => {
    filterForm.search = '';
    filterForm.type = '';
    applyFilters();
};

const goToPage = (url) => {
    if (!url) return;
    router.get(url, {}, {
        preserveState: true,
        preserveScroll: true,
    });
};

const formatDate = (value) => {
    if (!value) return '-';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '-';
    return date.toLocaleString('id-ID');
};

const typeLabel = (type) => {
    if (type === 'purchase') return 'Purchase';
    if (type === 'consume_unlock') return 'Use Item';
    return type || '-';
};

const compactMeta = (meta) => {
    if (!meta || typeof meta !== 'object') return '-';
    if (meta.quest_title) return `Quest: ${meta.quest_title}`;
    if (meta.item_code) return `Item Code: ${meta.item_code}`;
    const entries = Object.entries(meta);
    if (entries.length === 0) return '-';
    const [key, value] = entries[0];
    return `${key}: ${value}`;
};

const canCancelTransaction = (tx) => {
    return tx?.type === 'purchase' && !tx?.is_cancelled;
};

const cancelTransaction = async (tx) => {
    if (!tx?.id || !canCancelTransaction(tx)) return;

    const result = await Swal.fire({
        title: 'CANCEL_TRANSACTION?',
        text: 'Gold user akan dikembalikan dan item ditarik kembali.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'YES_CANCEL',
        cancelButtonText: 'NO',
        background: '#1a1c2c',
        color: '#4ed4d4',
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#334155',
    });

    if (!result.isConfirmed) return;

    router.post(
        route('admin.shop-items.transactions.cancel', { item: props.item.id, transaction: tx.id }),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                Swal.fire({
                    icon: 'success',
                    title: 'TRANSACTION_CANCELLED',
                    text: 'Transaksi berhasil dibatalkan dan direfund.',
                    background: '#1a1c2c',
                    color: '#4ed4d4',
                });
            },
            onError: (errors) => {
                Swal.fire({
                    icon: 'error',
                    title: 'CANCEL_FAILED',
                    text: errors?.transaction || 'Pembatalan transaksi gagal.',
                    background: '#1a1c2c',
                    color: '#ff4d4d',
                });
            },
        },
    );
};
</script>

<template>
    <Head :title="`SHOP_ITEM_DETAIL_${item.id}`" />

    <div class="min-h-screen bg-[#0d1117] p-4 md:p-8 font-['Press_Start_2P'] text-[#4ed4d4] text-[10px] relative">
        <div class="max-w-7xl mx-auto space-y-8">
            <AdminNavbar />

            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3 border-b-4 border-cyan-900 pb-4">
                <h1 class="text-base sm:text-xl uppercase tracking-widest">Shop_Item_Detail</h1>
                <Link :href="route('admin.shop-items.index')" class="inline-flex items-center justify-center px-3 py-2 border border-slate-600 bg-slate-900/40 text-slate-300 hover:text-white transition-colors uppercase text-[9px] sm:text-[10px]">
                    [Back_to_Item_Registry]
                </Link>
            </div>

            <div class="rpg-panel border-cyan-500/50">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div class="flex items-start gap-4">
                        <img
                            v-if="item.icon_path"
                            :src="`/storage/${item.icon_path}`"
                            alt="Item icon"
                            class="w-16 h-16 object-cover border border-cyan-500/60 shrink-0"
                        >
                        <div
                            v-else
                            class="w-16 h-16 border border-slate-600 bg-slate-900 flex items-center justify-center shrink-0"
                        >
                            <img src="/images/logo.png" alt="Default icon" class="w-8 h-8 object-contain opacity-80">
                        </div>
                        <div class="space-y-2">
                            <p class="text-slate-500 uppercase">ID: {{ item.id }} // {{ item.code }}</p>
                            <h2 class="text-white text-xs uppercase">{{ item.name }}</h2>
                            <p class="text-yellow-300 uppercase">Price: {{ item.price_gold }} Gold</p>
                            <p class="uppercase" :class="item.is_active ? 'text-emerald-400' : 'text-slate-500'">
                                Status: {{ item.is_active ? 'Active' : 'Inactive' }}
                            </p>
                        </div>
                    </div>
                    <div class="text-[8px] uppercase text-slate-400">
                        <p>Total_Transactions: {{ item.transactions_count || 0 }}</p>
                        <p class="mt-1">Owned_Users: {{ item.inventories_count || 0 }}</p>
                    </div>
                </div>
                <p v-if="item.description" class="mt-4 text-[11px] font-sans text-slate-300 leading-relaxed border-t border-slate-700 pt-3">
                    {{ item.description }}
                </p>
            </div>

            <div class="grid grid-cols-2 lg:grid-cols-6 gap-3">
                <div class="rpg-panel border-emerald-700/50">
                    <p class="text-[8px] text-slate-500 uppercase">Purchase_Qty</p>
                    <p class="mt-2 text-emerald-300 text-sm">{{ stats.purchase_qty || 0 }}</p>
                </div>
                <div class="rpg-panel border-yellow-700/50">
                    <p class="text-[8px] text-slate-500 uppercase">Used_Qty</p>
                    <p class="mt-2 text-yellow-300 text-sm">{{ stats.consume_qty || 0 }}</p>
                </div>
                <div class="rpg-panel border-cyan-700/50">
                    <p class="text-[8px] text-slate-500 uppercase">Gold_Spent</p>
                    <p class="mt-2 text-cyan-300 text-sm">{{ stats.purchase_gold || 0 }}</p>
                </div>
                <div class="rpg-panel border-indigo-700/50">
                    <p class="text-[8px] text-slate-500 uppercase">Unique_Buyers</p>
                    <p class="mt-2 text-indigo-300 text-sm">{{ stats.unique_buyer_count || 0 }}</p>
                </div>
                <div class="rpg-panel border-purple-700/50">
                    <p class="text-[8px] text-slate-500 uppercase">Active_Holders</p>
                    <p class="mt-2 text-purple-300 text-sm">{{ stats.active_holder_count || 0 }}</p>
                </div>
                <div class="rpg-panel border-sky-700/50">
                    <p class="text-[8px] text-slate-500 uppercase">Current_Stock</p>
                    <p class="mt-2 text-sky-300 text-sm">{{ stats.current_stock_owned || 0 }}</p>
                </div>
            </div>

            <div class="rpg-panel border-slate-700">
                <h2 class="text-white mb-5 uppercase tracking-tighter">>> ITEM_TRANSACTION_LOG</h2>

                <div class="mb-4 flex flex-col lg:flex-row gap-2">
                    <input
                        v-model="filterForm.search"
                        type="text"
                        placeholder="SEARCH USERNAME / EMAIL / NOTE"
                        class="flex-1 bg-black border-2 border-slate-700 p-2 text-cyan-300 uppercase outline-none"
                        @keyup.enter="applyFilters"
                    />
                    <select
                        v-model="filterForm.type"
                        class="bg-black border-2 border-slate-700 p-2 text-cyan-300 uppercase outline-none min-w-[170px]"
                    >
                        <option value="">ALL_TYPES</option>
                        <option value="purchase">PURCHASE</option>
                        <option value="consume_unlock">USE_ITEM</option>
                    </select>
                    <button
                        @click="applyFilters"
                        class="px-3 py-2 border-2 border-cyan-500 text-cyan-300 hover:bg-cyan-500 hover:text-black uppercase"
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

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[980px] text-left">
                        <thead class="border-b border-slate-700 text-slate-500 text-[8px] uppercase">
                            <tr>
                                <th class="py-3 px-2">Date</th>
                                <th class="py-3 px-2">User</th>
                                <th class="py-3 px-2">Type</th>
                                <th class="py-3 px-2">Qty</th>
                                <th class="py-3 px-2">Gold Change</th>
                                <th class="py-3 px-2">Note</th>
                                <th class="py-3 px-2">Meta</th>
                                <th class="py-3 px-2 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="tx in transactions.data"
                                :key="tx.id"
                                class="border-b border-slate-800 hover:bg-slate-900/40"
                            >
                                <td class="py-3 px-2 text-slate-400">{{ formatDate(tx.created_at) }}</td>
                                <td class="py-3 px-2">
                                    <p class="text-white uppercase">{{ tx.user?.username || tx.user?.name || '-' }}</p>
                                    <p class="text-[8px] text-slate-500 font-sans">{{ tx.user?.email || '-' }}</p>
                                </td>
                                <td class="py-3 px-2">
                                    <span
                                        class="px-2 py-1 border text-[8px] uppercase"
                                        :class="tx.type === 'purchase'
                                            ? 'text-emerald-300 border-emerald-800 bg-emerald-900/20'
                                            : 'text-yellow-300 border-yellow-800 bg-yellow-900/20'"
                                    >
                                        {{ typeLabel(tx.type) }}
                                    </span>
                                </td>
                                <td class="py-3 px-2 text-cyan-300">{{ tx.quantity || 0 }}</td>
                                <td class="py-3 px-2 font-sans" :class="(tx.gold_change || 0) < 0 ? 'text-red-300' : 'text-emerald-300'">
                                    {{ tx.gold_change || 0 }}
                                </td>
                                <td class="py-3 px-2 text-slate-300 font-sans">
                                    <p>{{ tx.note || '-' }}</p>
                                    <p v-if="tx.is_cancelled" class="mt-1 text-[8px] text-rose-300 uppercase">Cancelled_Refunded</p>
                                </td>
                                <td class="py-3 px-2 text-slate-400 font-sans">{{ compactMeta(tx.meta) }}</td>
                                <td class="py-3 px-2 text-right">
                                    <button
                                        v-if="canCancelTransaction(tx)"
                                        type="button"
                                        @click="cancelTransaction(tx)"
                                        class="px-3 py-1 border border-rose-700 text-rose-300 hover:bg-rose-600 hover:text-white uppercase text-[8px]"
                                    >
                                        Cancel
                                    </button>
                                    <span v-else class="text-[8px] uppercase text-slate-600">-</span>
                                </td>
                            </tr>
                            <tr v-if="transactions.data.length === 0">
                                <td colspan="8" class="py-8 text-center text-slate-500 uppercase">No_Transaction_Data</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-5 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                    <p class="text-[8px] text-slate-500 uppercase">
                        PAGE {{ transactions.current_page || 1 }} / {{ transactions.last_page || 1 }}
                        | TOTAL {{ transactions.total || 0 }}
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="(link, idx) in transactions.links"
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
</style>
