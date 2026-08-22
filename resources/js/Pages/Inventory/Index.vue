<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { toast } from '@/Utils/Alert';

const props = defineProps({
    inventories: {
        type: Object,
        default: () => ({ data: [] }),
    },
    logs: {
        type: Object,
        default: () => ({ data: [] }),
    },
    summary: {
        type: Object,
        default: () => ({
            unique_items: 0,
            total_quantity: 0,
        }),
    },
});

const formatDate = (iso) => {
    if (!iso) return '-';
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) return '-';
    return date.toLocaleString('id-ID');
};

const inventoryItems = computed(() => props.inventories?.data || []);
const activityLogs = computed(() => props.logs?.data || []);
const isProfileSkin = (inventory) => String(inventory?.item?.item_kind || '') === 'profile_skin';
const processingSkinId = ref(null);

const toggleProfileSkin = (inventory) => {
    const skin = inventory?.item?.profile_skin;
    const skinId = Number(skin?.id || 0);
    if (!skinId || processingSkinId.value) return;

    processingSkinId.value = skinId;
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            toast.success(
                skin.is_equipped ? 'SKIN_REMOVED' : 'SKIN_EQUIPPED',
                skin.is_equipped ? 'Profil publik kembali ke skin default.' : `${skin.name} dipasang ke profil publikmu.`,
            );
        },
        onError: (errors) => {
            toast.error('SKIN_FAILED', Object.values(errors || {})[0] || 'Skin gagal diperbarui.');
        },
        onFinish: () => {
            processingSkinId.value = null;
        },
    };

    if (skin.is_equipped) {
        router.delete(route('profile.skins.deactivate'), options);
        return;
    }

    router.post(route('profile.skins.activate', skinId), {}, options);
};

const goToPage = (url) => {
    if (!url) return;
    router.get(url, {}, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const signedQuantity = (value) => {
    const number = Number(value || 0);
    if (number > 0) return `+${number}`;
    return String(number);
};

const logTypeLabel = (type) => {
    const normalized = String(type || '').toLowerCase();
    if (normalized === 'purchase') return 'Purchased';
    if (normalized === 'use') return 'Used';
    if (normalized === 'refund_remove') return 'Refund Removed';
    return normalized.replaceAll('_', ' ') || 'Activity';
};
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Inventory" />

        <div class="lobby-detail-page inventory-light-page user-page-shell text-[#4ed4d4]">
            <div class="inventory-header rpg-panel mb-6 border-cyan-500/50 bg-[#1a1c2c]/90">
                <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <p class="text-[8px] uppercase tracking-[0.35em] text-cyan-300">Player_Storage</p>
                        <h1 class="mt-2 text-xl uppercase tracking-widest text-white md:text-3xl">Inventory</h1>
                    </div>
                    <div class="grid grid-cols-2 gap-3 sm:min-w-[280px]">
                        <div class="inventory-stat inventory-stat--unique border-2 border-cyan-700 bg-[#070b11] p-3">
                            <p class="text-[8px] uppercase text-slate-500">Unique_Items</p>
                            <p class="mt-2 text-lg text-cyan-200">{{ summary.unique_items || 0 }}</p>
                        </div>
                        <div class="inventory-stat inventory-stat--quantity border-2 border-yellow-700 bg-[#070b11] p-3">
                            <p class="text-[8px] uppercase text-slate-500">Total_Qty</p>
                            <p class="mt-2 text-lg text-yellow-300">{{ summary.total_quantity || 0 }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
                <section class="inventory-items-panel rpg-panel border-cyan-500/40 bg-[#1a1c2c]/90">
                    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-[10px] uppercase tracking-widest text-cyan-300">Owned_Items</h2>
                            <p class="mt-1 text-[8px] uppercase text-slate-500">Item dengan quantity 0 otomatis disembunyikan.</p>
                        </div>
                        <Link :href="route('shop.index')" class="inline-flex items-center justify-center border border-yellow-600 bg-yellow-400 px-3 py-2 text-[8px] font-bold uppercase text-black hover:bg-yellow-300">
                            Open_Shop
                        </Link>
                    </div>

                    <div v-if="inventoryItems.length > 0" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
                        <article
                            v-for="inventory in inventoryItems"
                            :key="inventory.id"
                            class="inventory-item-card relative min-w-0 overflow-hidden flex min-h-[148px] flex-col border-2 border-slate-700 bg-[#0d1117] p-3 shadow-[4px_4px_0_rgba(0,0,0,0.45)]"
                        >
                            <div class="flex items-start gap-3">
                                <div class="inventory-item-icon flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden border-2 border-slate-600 bg-slate-950">
                                    <img
                                        v-if="inventory.item?.icon_path"
                                        :src="`/storage/${inventory.item.icon_path}`"
                                        :alt="inventory.item.name"
                                        loading="lazy"
                                        decoding="async"
                                        class="h-full w-full object-cover"
                                    >
                                    <img
                                        v-else
                                        src="/images/logo.png"
                                        :alt="inventory.item?.name || 'Item'"
                                        loading="lazy"
                                        decoding="async"
                                        class="h-8 w-8 object-contain opacity-70"
                                    >
                                </div>
                                <div class="min-w-0 flex-1">
                                    <h3 class="inventory-item-name line-clamp-2 text-[10px] uppercase leading-snug text-white">{{ inventory.item?.name }}</h3>
                                    <p class="mt-2 inline-flex border border-yellow-700 bg-yellow-400/10 px-2 py-1 text-[9px] font-bold uppercase text-yellow-300">
                                        Qty: {{ inventory.quantity }}
                                    </p>
                                </div>
                            </div>

                            <div class="mt-auto flex min-w-0 flex-col gap-2 border-t border-slate-800 pt-3">
                                <p class="text-[8px] uppercase" :class="inventory.item?.is_usable ? 'text-emerald-300' : 'text-slate-500'">
                                    {{ isProfileSkin(inventory) ? 'Profile_Cosmetic' : (inventory.item?.is_usable ? 'Usable_Item' : 'Storage_Item') }}
                                </p>
                                <Link
                                    v-if="isProfileSkin(inventory)"
                                    :href="route('profile.skins.preview', inventory.item.profile_skin.id)"
                                    class="inventory-preview-button inline-flex max-w-full items-center justify-center border border-cyan-700 bg-cyan-500/10 px-2 py-2 text-[7px] uppercase text-cyan-300 hover:bg-cyan-400 hover:text-black"
                                >
                                    Preview_Profile_Skin
                                </Link>
                                <button
                                    v-if="isProfileSkin(inventory)"
                                    type="button"
                                    class="inventory-equip-button inline-flex max-w-full items-center justify-center border border-purple-700 bg-purple-500/10 px-2 py-2 text-[7px] uppercase text-purple-300 hover:bg-purple-400 hover:text-black"
                                    :class="inventory.item.profile_skin?.is_equipped ? 'inventory-equip-button--active' : ''"
                                    :disabled="Boolean(processingSkinId)"
                                    @click="toggleProfileSkin(inventory)"
                                >
                                    {{ processingSkinId === Number(inventory.item.profile_skin?.id) ? 'Processing...' : (inventory.item.profile_skin?.is_equipped ? 'Unequip_Profile_Skin' : 'Equip_Profile_Skin') }}
                                </button>
                            </div>
                        </article>
                    </div>

                    <div v-else class="inventory-empty-state flex min-h-[280px] flex-col items-center justify-center border-2 border-dashed border-slate-700 bg-[#070b11] p-6 text-center">
                        <p class="text-sm uppercase tracking-widest text-slate-300">Inventory_Empty</p>
                        <p class="mt-3 max-w-md text-[9px] uppercase leading-relaxed text-slate-500">
                            Kamu belum punya item. Beli item di Shop, lalu item akan muncul di inventory ini.
                        </p>
                        <Link :href="route('shop.index')" class="mt-5 inline-flex border border-cyan-700 bg-cyan-400 px-4 py-2 text-[8px] font-bold uppercase text-black hover:bg-cyan-300">
                            Go_To_Shop
                        </Link>
                    </div>

                    <nav
                        v-if="Number(inventories.last_page || 1) > 1"
                        class="inventory-pagination mt-4 flex items-center justify-between gap-3 border-t border-slate-700 pt-4"
                        aria-label="Pagination item inventory"
                    >
                        <button
                            type="button"
                            class="inventory-pagination-button"
                            :disabled="!inventories.prev_page_url"
                            @click="goToPage(inventories.prev_page_url)"
                        >
                            Previous
                        </button>
                        <p class="inventory-pagination-status">
                            Page {{ inventories.current_page || 1 }} / {{ inventories.last_page || 1 }}
                        </p>
                        <button
                            type="button"
                            class="inventory-pagination-button"
                            :disabled="!inventories.next_page_url"
                            @click="goToPage(inventories.next_page_url)"
                        >
                            Next
                        </button>
                    </nav>
                </section>

                <aside class="inventory-log-panel rpg-panel border-purple-500/40 bg-[#1a1c2c]/90">
                    <h2 class="text-[10px] uppercase tracking-widest text-purple-300">Inventory_Log</h2>
                    <p class="mt-1 text-[8px] uppercase leading-relaxed text-slate-500">Riwayat perubahan item terbaru.</p>

                    <div class="mt-4 max-h-[680px] space-y-3 overflow-y-auto pr-1 custom-scroll">
                        <div
                            v-for="log in activityLogs"
                            :key="log.id"
                            class="inventory-log-entry border border-slate-700 bg-[#0d1117] p-3"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-[8px] uppercase text-white">{{ log.item?.name || 'Unknown Item' }}</p>
                                    <p class="mt-1 text-[7px] uppercase text-slate-500">{{ logTypeLabel(log.type) }}</p>
                                </div>
                                <p class="shrink-0 text-[9px] font-bold" :class="Number(log.quantity_change || 0) >= 0 ? 'text-emerald-300' : 'text-red-300'">
                                    {{ signedQuantity(log.quantity_change) }}
                                </p>
                            </div>
                            <p v-if="log.note" class="mt-2 text-[8px] leading-relaxed text-slate-300">{{ log.note }}</p>
                            <p class="mt-2 text-[8px] text-slate-500">{{ formatDate(log.created_at) }}</p>
                        </div>

                        <p v-if="activityLogs.length === 0" class="border border-dashed border-slate-700 p-4 text-center text-[8px] uppercase text-slate-500">
                            No_Inventory_Log
                        </p>
                    </div>

                    <nav
                        v-if="Number(logs.last_page || 1) > 1"
                        class="inventory-pagination inventory-log-pagination mt-4 flex items-center justify-between gap-2 border-t border-slate-700 pt-4"
                        aria-label="Pagination log inventory"
                    >
                        <button
                            type="button"
                            class="inventory-pagination-button"
                            :disabled="!logs.prev_page_url"
                            @click="goToPage(logs.prev_page_url)"
                        >
                            Prev
                        </button>
                        <p class="inventory-pagination-status">
                            {{ logs.current_page || 1 }} / {{ logs.last_page || 1 }}
                        </p>
                        <button
                            type="button"
                            class="inventory-pagination-button"
                            :disabled="!logs.next_page_url"
                            @click="goToPage(logs.next_page_url)"
                        >
                            Next
                        </button>
                    </nav>
                </aside>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
