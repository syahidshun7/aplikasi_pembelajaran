<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PixelModal from '@/Components/PixelModal.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { toast } from '@/Utils/Alert';
import { computed, ref } from 'vue';

const props = defineProps({
    items: {
        type: Array,
        default: () => [],
    },
    gold: {
        type: Number,
        default: 0,
    },
});

const purchaseForm = useForm({
    quantity: 1,
});
const page = usePage();
const selectedItem = ref(null);
const isStaffPlayMode = computed(() => Boolean(page.props?.auth?.user?.staff_play_mode));
const isProfileSkin = (item) => String(item?.item_kind || '') === 'profile_skin';
const isUnlockedCosmetic = (item) => isProfileSkin(item) && Number(item?.owned_qty || 0) > 0;
const openItemDetail = (item) => {
    selectedItem.value = item;
};
const closeItemDetail = () => {
    selectedItem.value = null;
};
const buySelectedItem = () => {
    const item = selectedItem.value;
    closeItemDetail();
    buyItem(item);
};

const buyItem = async (item) => {
    if (!item?.id) {
        toast.error('INVALID_ITEM', 'Item tidak valid.');
        return;
    }

    if (isStaffPlayMode.value) {
        toast.error('STAFF_PLAY_MODE', 'Pembelian shop dimatikan untuk mentor/admin pada mode preview.');
        return;
    }

    if ((props.gold || 0) < (item.price_gold || 0)) {
        toast.error('GOLD_NOT_ENOUGH', 'Gold kamu tidak cukup.');
        return;
    }

    if (isUnlockedCosmetic(item)) {
        toast.success('SKIN_UNLOCKED', 'Skin ini sudah kamu miliki. Equip dari Hero Status/Profile.');
        return;
    }

    const result = await toast.confirm(
        isProfileSkin(item) ? 'UNLOCK_SKIN?' : 'BUY_ITEM?',
        `${isProfileSkin(item) ? 'Unlock skin' : 'Beli'} ${item.name} seharga ${item.price_gold} gold?`,
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

        <div class="lobby-detail-page shop-light-page user-page-shell text-[#4ed4d4]">
            <div class="shop-header rpg-panel mb-6 bg-[#1a1c2c]/90 border-yellow-500/50 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h1 class="text-yellow-400 text-xs uppercase tracking-widest">Item_Shop</h1>
                    <p class="text-[9px] text-slate-400 mt-2 uppercase">Beli item progres dan cosmetic skin. Skin bisa dipasang ke profil publik dari Hero Status.</p>
                </div>
                <div class="shop-wallet text-left sm:text-right">
                    <p class="text-[8px] text-slate-500 uppercase mb-1">Your_Gold</p>
                    <p class="text-yellow-300 text-sm uppercase">{{ gold }} G</p>
                </div>
            </div>
            <div class="shop-items-panel rpg-panel bg-[#1a1c2c]/90 border-cyan-500/40">
                <h2 class="text-cyan-300 text-[10px] uppercase tracking-widest mb-4">Available_Items</h2>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5 gap-4">
                    <article
                        v-for="item in items"
                        :key="item.id"
                        class="shop-item-card group relative min-w-0 overflow-hidden p-3 flex flex-col items-center justify-between gap-3 min-h-[170px] transition-colors shadow-[4px_4px_0_rgba(0,0,0,0.45)]"
                        role="button"
                        tabindex="0"
                        :aria-label="`Lihat detail ${item.name}`"
                        @click="openItemDetail(item)"
                        @keydown.enter.prevent="openItemDetail(item)"
                        @keydown.space.prevent="openItemDetail(item)"
                    >
                        <div class="shop-item-icon w-16 h-16 border border-slate-600 bg-slate-900 flex items-center justify-center overflow-hidden">
                            <img
                                v-if="item.icon_path"
                                :src="`/storage/${item.icon_path}`"
                                :alt="item.name"
                                loading="lazy"
                                decoding="async"
                                class="w-full h-full object-cover"
                            >
                            <img
                                v-else
                                src="/images/logo.png"
                                :alt="item.name"
                                loading="lazy"
                                decoding="async"
                                class="w-8 h-8 object-contain opacity-80"
                            >
                        </div>

                        <div class="text-center">
                            <p
                                v-if="isProfileSkin(item)"
                                class="shop-item-type mb-2 inline-flex border border-purple-700 bg-purple-500/10 px-2 py-1 text-[6px] uppercase text-purple-300"
                            >
                                Profile_Skin
                            </p>
                            <h3 class="shop-item-name text-[9px] text-white uppercase leading-snug line-clamp-2 min-h-[28px]">{{ item.name }}</h3>
                            <p class="shop-item-price text-[8px] text-yellow-300 mt-1">{{ item.price_gold }} G</p>
                            <Link
                                v-if="Number(item.owned_qty || 0) > 0"
                                :href="isProfileSkin(item) ? route('profile.dashboard') : route('inventory.index')"
                                class="shop-owned-link mt-1 inline-flex text-[7px] uppercase text-emerald-300 hover:text-emerald-100"
                                @click.stop
                            >
                                {{ isProfileSkin(item) ? 'Unlocked - Equip' : `Owned: ${item.owned_qty || 0} - Inventory` }}
                            </Link>
                            <p v-else class="shop-not-owned text-[7px] text-slate-500 mt-1 uppercase">Not Owned</p>
                        </div>

                        <div class="shop-item-action flex w-full flex-col gap-2 border-t border-slate-800 pt-1">
                            <Link
                                v-if="isProfileSkin(item)"
                                :href="route('profile.skins.preview', item.profile_skin.id)"
                                class="shop-preview-button w-full border border-purple-600 bg-purple-500/10 px-2 py-2 text-[8px] font-bold uppercase text-purple-200 transition-colors hover:bg-purple-400 hover:text-black"
                                @click.stop
                            >
                                Preview
                            </Link>
                            <button
                                type="button"
                                class="shop-buy-button w-full text-[8px] px-2 py-2 btn-pixel uppercase font-bold bg-[#009999] text-black border-[#006666] hover:bg-[#4ed4d4] transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                :disabled="purchaseForm.processing || gold < item.price_gold || isStaffPlayMode || isUnlockedCosmetic(item)"
                                @click.stop="buyItem(item)"
                            >
                                {{ isStaffPlayMode ? 'Preview Only' : (purchaseForm.processing ? 'Processing...' : (isUnlockedCosmetic(item) ? 'Unlocked' : (isProfileSkin(item) ? 'Unlock' : 'Buy'))) }}
                            </button>
                        </div>
                    </article>
                </div>
                <p v-if="items.length === 0" class="text-[8px] text-slate-500 uppercase mt-4">
                    No_Items_Available
                </p>
            </div>

            <PixelModal
                :show="Boolean(selectedItem)"
                title="Item_Detail"
                panel-class="shop-item-modal"
                @close="closeItemDetail"
            >
                <template #content>
                    <div v-if="selectedItem" class="shop-item-modal-content">
                        <div class="shop-item-modal-icon">
                            <img
                                v-if="selectedItem.icon_path"
                                :src="`/storage/${selectedItem.icon_path}`"
                                :alt="selectedItem.name"
                                class="h-full w-full object-cover"
                            >
                            <img v-else src="/images/logo.png" :alt="selectedItem.name" class="h-10 w-10 object-contain opacity-80">
                        </div>
                        <p v-if="isProfileSkin(selectedItem)" class="shop-item-modal-type">Profile_Skin</p>
                        <h3 class="shop-item-modal-name">{{ selectedItem.name }}</h3>
                        <p class="shop-item-modal-description">
                            {{ selectedItem.description || 'Tidak ada deskripsi item.' }}
                        </p>
                        <div class="shop-item-modal-meta">
                            <span>Price</span>
                            <strong>{{ selectedItem.price_gold }} G</strong>
                        </div>
                        <div class="shop-item-modal-meta">
                            <span>Status</span>
                            <strong>{{ Number(selectedItem.owned_qty || 0) > 0 ? `Owned ${selectedItem.owned_qty}` : 'Not Owned' }}</strong>
                        </div>
                    </div>
                </template>
                <template #footer>
                    <button type="button" class="shop-modal-close-button" @click="closeItemDetail">Close</button>
                    <button
                        v-if="selectedItem && !isUnlockedCosmetic(selectedItem)"
                        type="button"
                        class="shop-modal-buy-button"
                        :disabled="purchaseForm.processing || gold < selectedItem.price_gold || isStaffPlayMode"
                        @click="buySelectedItem"
                    >
                        {{ isProfileSkin(selectedItem) ? 'Unlock' : 'Buy' }}
                    </button>
                </template>
            </PixelModal>

        </div>
    </AuthenticatedLayout>
</template>
