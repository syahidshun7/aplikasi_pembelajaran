<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminNavbar from '@/Components/AdminNavbar.vue';

const props = defineProps({
    user: {
        type: Object,
        required: true,
    },
    ledger: {
        type: Object,
        required: true,
    },
    summary: {
        type: Object,
        required: true,
    },
    sourceBreakdown: {
        type: Array,
        default: () => [],
    },
    sourceOptions: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const filterForm = useForm({
    search: props.filters?.search || '',
    source: props.filters?.source || 'all',
    direction: props.filters?.direction || 'all',
    date_from: props.filters?.date_from || '',
    date_to: props.filters?.date_to || '',
    per_page: Number(props.filters?.per_page || 25),
});

const adjustmentForm = useForm({
    direction: 'add',
    amount: 1,
    reason: '',
});

const rows = computed(() => props.ledger?.data || []);
const paginationLinks = computed(() => props.ledger?.links || []);

const applyFilters = () => {
    router.get(route('admin.users.ledger', props.user.id), filterForm.data(), {
        preserveState: true,
        preserveScroll: true,
    });
};

const resetFilters = () => {
    filterForm.search = '';
    filterForm.source = 'all';
    filterForm.direction = 'all';
    filterForm.date_from = '';
    filterForm.date_to = '';
    filterForm.per_page = 25;
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

    return date.toLocaleString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).toUpperCase();
};

const formatGold = (value) => {
    const number = Number(value || 0);
    return new Intl.NumberFormat('id-ID').format(number);
};

const signedGold = (value) => {
    const number = Number(value || 0);
    if (number > 0) return `+${formatGold(number)}`;
    if (number < 0) return `-${formatGold(Math.abs(number))}`;
    return '0';
};

const directionClass = (direction) => {
    if (direction === 'income') return 'text-emerald-300';
    if (direction === 'expense') return 'text-red-300';
    return 'text-slate-300';
};

const submitGoldAdjustment = () => {
    const amount = Number(adjustmentForm.amount || 0);
    const actionLabel = adjustmentForm.direction === 'add' ? 'menambahkan' : 'mengurangi';

    if (!Number.isFinite(amount) || amount < 1) {
        adjustmentForm.setError('amount', 'Amount minimal 1.');
        return;
    }

    if (!window.confirm(`Yakin ${actionLabel} ${formatGold(amount)} gold untuk @${props.user.username || props.user.id}?`)) {
        return;
    }

    adjustmentForm.post(route('admin.users.gold-adjustment', props.user.id), {
        preserveScroll: true,
        onSuccess: () => {
            adjustmentForm.reset();
            adjustmentForm.direction = 'add';
            adjustmentForm.amount = 1;
        },
    });
};
</script>

<template>
    <Head :title="`USER_LEDGER_${user.username || user.id}`" />

    <div class="min-h-screen bg-[#0d1117] p-4 md:p-8 font-['Press_Start_2P'] text-[#4ed4d4] text-[10px]">
        <div class="max-w-7xl mx-auto space-y-8">
            <AdminNavbar />

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b-4 border-cyan-900 pb-4">
                <div>
                    <h1 class="text-base sm:text-xl uppercase tracking-widest">User_Transaction_Ledger</h1>
                    <p class="text-[8px] text-slate-400 mt-2 uppercase">
                        {{ user.name || '-' }} | @{{ user.username || '-' }} | Role: {{ user.role || '-' }}
                    </p>
                </div>
                <Link
                    :href="route('admin.users.index')"
                    class="inline-flex items-center justify-center px-3 py-2 border border-slate-600 bg-slate-900/40 text-slate-300 hover:text-white uppercase text-[9px] sm:text-[10px]"
                >
                    [Back_to_User_Management]
                </Link>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                <div class="rpg-panel border-emerald-700/60">
                    <p class="text-[8px] uppercase text-slate-400">Income_Total</p>
                    <p class="mt-2 text-emerald-300">+{{ formatGold(summary.income_total || 0) }}</p>
                </div>
                <div class="rpg-panel border-red-700/60">
                    <p class="text-[8px] uppercase text-slate-400">Expense_Total</p>
                    <p class="mt-2 text-red-300">-{{ formatGold(summary.expense_total || 0) }}</p>
                </div>
                <div class="rpg-panel border-cyan-700/60">
                    <p class="text-[8px] uppercase text-slate-400">Net_Total</p>
                    <p class="mt-2" :class="(summary.net_total || 0) >= 0 ? 'text-cyan-300' : 'text-orange-300'">
                        {{ signedGold(summary.net_total || 0) }}
                    </p>
                </div>
                <div class="rpg-panel border-yellow-700/60">
                    <p class="text-[8px] uppercase text-slate-400">Current_Gold</p>
                    <p class="mt-2 text-yellow-300">{{ formatGold(summary.current_gold || 0) }}</p>
                </div>
            </div>

            <div class="rpg-panel border-yellow-700/60">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-[8px] uppercase text-slate-400">Admin_Gold_Adjustment</p>
                        <p class="mt-2 text-[8px] uppercase leading-relaxed text-slate-500">
                            Tambah atau kurangi gold hanya dari ledger agar audit transaksi tercatat rapi.
                        </p>
                    </div>

                    <form class="grid w-full grid-cols-1 gap-3 md:grid-cols-[160px_180px_minmax(0,1fr)_140px] lg:max-w-4xl" @submit.prevent="submitGoldAdjustment">
                        <select
                            v-model="adjustmentForm.direction"
                            class="bg-black border-2 border-slate-700 p-2 text-yellow-300 uppercase outline-none"
                            :disabled="adjustmentForm.processing"
                        >
                            <option value="add">ADD_GOLD</option>
                            <option value="subtract">SUBTRACT_GOLD</option>
                        </select>
                        <input
                            v-model.number="adjustmentForm.amount"
                            type="number"
                            min="1"
                            class="bg-black border-2 border-slate-700 p-2 text-yellow-300 uppercase outline-none"
                            placeholder="AMOUNT"
                            :disabled="adjustmentForm.processing"
                        />
                        <input
                            v-model="adjustmentForm.reason"
                            type="text"
                            maxlength="255"
                            class="bg-black border-2 border-slate-700 p-2 text-cyan-300 uppercase outline-none"
                            placeholder="REASON / NOTE"
                            :disabled="adjustmentForm.processing"
                        />
                        <button
                            type="submit"
                            class="px-3 py-2 border-2 border-yellow-400 text-yellow-300 hover:bg-yellow-400 hover:text-black uppercase disabled:opacity-50"
                            :disabled="adjustmentForm.processing"
                        >
                            {{ adjustmentForm.processing ? 'Saving...' : 'Apply' }}
                        </button>
                    </form>
                </div>
                <div class="mt-3 space-y-1">
                    <p v-if="adjustmentForm.errors.direction" class="text-red-400 text-[8px]">{{ adjustmentForm.errors.direction }}</p>
                    <p v-if="adjustmentForm.errors.amount" class="text-red-400 text-[8px]">{{ adjustmentForm.errors.amount }}</p>
                    <p v-if="adjustmentForm.errors.reason" class="text-red-400 text-[8px]">{{ adjustmentForm.errors.reason }}</p>
                </div>
            </div>

            <div class="rpg-panel border-slate-700">
                <div class="flex flex-col md:flex-row gap-3 mb-4">
                    <input
                        v-model="filterForm.search"
                        type="text"
                        placeholder="SEARCH NOTE / REFERENCE / ITEM"
                        class="flex-1 bg-black border-2 border-slate-700 p-2 text-cyan-400 uppercase outline-none"
                        @keyup.enter="applyFilters"
                    />

                    <select
                        v-model="filterForm.source"
                        class="w-full md:w-52 bg-black border-2 border-slate-700 p-2 text-cyan-400 uppercase outline-none"
                    >
                        <option value="all">SOURCE: ALL</option>
                        <option v-for="source in sourceOptions" :key="source.key" :value="source.key">
                            {{ source.label }}
                        </option>
                    </select>

                    <select
                        v-model="filterForm.direction"
                        class="w-full md:w-44 bg-black border-2 border-slate-700 p-2 text-cyan-400 uppercase outline-none"
                    >
                        <option value="all">DIR: ALL</option>
                        <option value="income">DIR: INCOME</option>
                        <option value="expense">DIR: EXPENSE</option>
                        <option value="neutral">DIR: NEUTRAL</option>
                    </select>

                    <select
                        v-model.number="filterForm.per_page"
                        class="w-full md:w-36 bg-black border-2 border-slate-700 p-2 text-cyan-400 uppercase outline-none"
                    >
                        <option :value="10">10 / PAGE</option>
                        <option :value="25">25 / PAGE</option>
                        <option :value="50">50 / PAGE</option>
                        <option :value="100">100 / PAGE</option>
                    </select>
                </div>

                <div class="flex flex-col md:flex-row gap-3 mb-4">
                    <input
                        v-model="filterForm.date_from"
                        type="date"
                        class="w-full md:w-48 bg-black border-2 border-slate-700 p-2 text-cyan-400 uppercase outline-none"
                    />
                    <input
                        v-model="filterForm.date_to"
                        type="date"
                        class="w-full md:w-48 bg-black border-2 border-slate-700 p-2 text-cyan-400 uppercase outline-none"
                    />

                    <div class="flex gap-2">
                        <button
                            @click="applyFilters"
                            class="px-3 py-2 border-2 border-cyan-400 text-cyan-400 hover:bg-cyan-400 hover:text-black uppercase"
                        >
                            Apply
                        </button>
                        <button
                            @click="resetFilters"
                            class="px-3 py-2 border-2 border-slate-600 text-slate-300 hover:bg-slate-600 hover:text-white uppercase"
                        >
                            Reset
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-2 mb-4">
                    <div v-for="item in sourceBreakdown" :key="item.key" class="border border-slate-700 bg-black/40 p-2">
                        <p class="text-[7px] text-slate-500 uppercase">{{ item.label }}</p>
                        <p class="mt-1 text-[8px] text-slate-300">TX: {{ item.count || 0 }}</p>
                        <p class="mt-1 text-[8px]" :class="(item.net_total || 0) >= 0 ? 'text-emerald-300' : 'text-red-300'">
                            {{ signedGold(item.net_total || 0) }}
                        </p>
                    </div>
                </div>

                <div class="overflow-x-auto custom-scroll">
                    <table class="w-full min-w-[1100px] text-left">
                        <thead class="text-[8px] uppercase border-b border-slate-700 text-slate-500">
                            <tr>
                                <th class="py-3 px-2">Time</th>
                                <th class="py-3 px-2">Source</th>
                                <th class="py-3 px-2">Direction</th>
                                <th class="py-3 px-2">Gold_Change</th>
                                <th class="py-3 px-2">Reference</th>
                                <th class="py-3 px-2">Item</th>
                                <th class="py-3 px-2">Note</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in rows" :key="row.id" class="border-b border-slate-800 hover:bg-slate-900/40">
                                <td class="py-3 px-2 text-slate-300 text-[8px]">{{ formatDate(row.occurred_at) }}</td>
                                <td class="py-3 px-2 text-slate-200 text-[8px]">{{ row.source_label || '-' }}</td>
                                <td class="py-3 px-2 text-[8px]" :class="directionClass(row.direction)">
                                    {{ String(row.direction || '-').toUpperCase() }}
                                </td>
                                <td class="py-3 px-2 text-[8px] font-bold" :class="directionClass(row.direction)">
                                    {{ signedGold(row.gold_change || 0) }}
                                </td>
                                <td class="py-3 px-2 text-slate-400 text-[8px]">{{ row.reference || '-' }}</td>
                                <td class="py-3 px-2 text-slate-300 text-[8px]">
                                    {{ row.item_name || '-' }}
                                    <span v-if="row.item_code" class="text-slate-500">({{ row.item_code }})</span>
                                </td>
                                <td class="py-3 px-2 text-slate-300 text-[8px]">{{ row.note || '-' }}</td>
                            </tr>
                            <tr v-if="rows.length === 0">
                                <td colspan="7" class="py-6 px-2 text-center text-slate-500 uppercase">
                                    NO_TRANSACTION_DATA
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-5 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                    <p class="text-[8px] text-slate-500 uppercase">
                        PAGE {{ ledger.current_page || 1 }} / {{ ledger.last_page || 1 }} | TOTAL {{ ledger.total || 0 }}
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
                                !link.url ? 'opacity-40 cursor-not-allowed' : '',
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
    box-shadow: 8px 8px 0 0 rgba(0, 0, 0, 0.5);
}
</style>
