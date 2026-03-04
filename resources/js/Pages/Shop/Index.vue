<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { toast } from '@/Utils/Alert';

const props = defineProps({
    items: {
        type: Array,
        default: () => [],
    },
    gold: {
        type: Number,
        default: 0,
    },
    transactions: {
        type: Array,
        default: () => [],
    },
});

const purchaseForm = useForm({
    quantity: 1,
});

const buyItem = async (item) => {
    if (!item?.id) {
        toast.error('INVALID_ITEM', 'Item tidak valid.');
        return;
    }

    if ((props.gold || 0) < (item.price_gold || 0)) {
        toast.error('GOLD_NOT_ENOUGH', 'Gold kamu tidak cukup.');
        return;
    }

    const result = await toast.confirm(
        'BUY_ITEM?',
        `Beli ${item.name} seharga ${item.price_gold} gold?`,
        'YES_BUY'
    );

    if (!result.isConfirmed) return;

    purchaseForm.post(route('shop.purchase', item.id), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('PURCHASE_SUCCESS', `${item.name} berhasil dibeli.`);
        },
        onError: (errors) => {
            const message = Object.values(errors || {})[0] || 'Pembelian gagal.';
            toast.error('PURCHASE_FAILED', message);
        },
        onFinish: () => {
            purchaseForm.reset('quantity');
            purchaseForm.quantity = 1;
        },
    });
};
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Shop" />

        <div class="max-w-7xl mx-auto text-[#4ed4d4]">
            <div class="rpg-panel mb-6 bg-[#1a1c2c]/90 border-yellow-500/50 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h1 class="text-yellow-400 text-xs uppercase tracking-widest">Item_Shop</h1>
                    <p class="text-[9px] text-slate-400 mt-2 uppercase">Beli item untuk kebutuhan quest dan progres.</p>
                </div>
                <div class="text-right">
                    <p class="text-[8px] text-slate-500 uppercase mb-1">Your_Gold</p>
                    <p class="text-yellow-300 text-sm uppercase">{{ gold }} G</p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2">
                    <div class="rpg-panel bg-[#1a1c2c]/90 border-cyan-500/40">
                        <h2 class="text-cyan-300 text-[10px] uppercase tracking-widest mb-4">Available_Items</h2>
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                            <article
                                v-for="item in items"
                                :key="item.id"
                                class="group relative p-3 flex flex-col items-center justify-between gap-3 min-h-[170px] transition-colors shadow-[4px_4px_0_rgba(0,0,0,0.45)]"
                            >
                                <div
                                    class="pointer-events-none absolute left-1/2 -translate-x-1/2 -top-2 z-20 w-[220px] opacity-0 translate-y-1 group-hover:opacity-100 group-hover:-translate-y-1 transition-all duration-200"
                                >
                                    <div class="relative bg-[#070b11] border-2 border-cyan-700 px-2 py-2 text-[8px] text-slate-200 font-sans leading-relaxed shadow-[3px_3px_0_rgba(0,0,0,0.55)]">
                                        {{ item.description || 'Tidak ada deskripsi item.' }}
                                        <span class="absolute left-1/2 -translate-x-1/2 -bottom-[8px] w-0 h-0 border-l-[8px] border-r-[8px] border-t-[8px] border-l-transparent border-r-transparent border-t-cyan-700"></span>
                                        <span class="absolute left-1/2 -translate-x-1/2 -bottom-[6px] w-0 h-0 border-l-[6px] border-r-[6px] border-t-[6px] border-l-transparent border-r-transparent border-t-[#070b11]"></span>
                                    </div>
                                </div>

                                <div class="w-16 h-16 border border-slate-600 bg-slate-900 flex items-center justify-center overflow-hidden">
                                    <img
                                        v-if="item.icon_path"
                                        :src="`/storage/${item.icon_path}`"
                                        :alt="item.name"
                                        class="w-full h-full object-cover"
                                    >
                                    <img
                                        v-else
                                        src="/images/logo.png"
                                        :alt="item.name"
                                        class="w-8 h-8 object-contain opacity-80"
                                    >
                                </div>

                                <div class="text-center">
                                    <h3 class="text-[9px] text-white uppercase leading-snug line-clamp-2 min-h-[28px]">{{ item.name }}</h3>
                                    <p class="text-[8px] text-yellow-300 mt-1">{{ item.price_gold }} G</p>
                                    <p class="text-[7px] text-emerald-300 mt-1 uppercase">Owned: {{ item.owned_qty || 0 }}</p>
                                </div>

                                <div class="w-full pt-1 border-t border-slate-800">
                                    <button
                                        type="button"
                                        class="w-full text-[8px] px-2 py-2 btn-pixel uppercase font-bold bg-[#009999] text-black border-[#006666] hover:bg-[#4ed4d4] transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                        :disabled="purchaseForm.processing || gold < item.price_gold"
                                        @click="buyItem(item)"
                                    >
                                        {{ purchaseForm.processing ? 'Processing...' : 'Buy' }}
                                    </button>
                                </div>
                            </article>
                        </div>
                        <p v-if="items.length === 0" class="text-[8px] text-slate-500 uppercase mt-4">
                            No_Items_Available
                        </p>
                    </div>
                </div>

                <div>
                    <div class="rpg-panel bg-[#1a1c2c]/90 border-purple-500/40 h-full">
                        <h2 class="text-purple-300 text-[10px] uppercase tracking-widest mb-4">Recent_Transactions</h2>
                        <div class="space-y-3 max-h-[560px] overflow-y-auto pr-2 custom-scroll">
                            <div
                                v-for="tx in transactions"
                                :key="tx.id"
                                class="border border-slate-700 bg-[#0d1117] p-3"
                            >
                                <p class="text-[8px] uppercase mb-1"
                                    :class="tx.type === 'purchase' ? 'text-emerald-300' : 'text-yellow-300'">
                                    {{ tx.type === 'purchase' ? 'Purchase' : 'Use Item' }}
                                </p>
                                <p class="text-[9px] text-white font-sans">{{ tx.item?.name || 'Unknown Item' }} x{{ tx.quantity }}</p>
                                <p class="text-[8px] text-slate-400 font-sans mt-1">{{ new Date(tx.created_at).toLocaleString('id-ID') }}</p>
                            </div>
                            <p v-if="transactions.length === 0" class="text-[8px] text-slate-500 uppercase">
                                No_Transaction_Yet
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
