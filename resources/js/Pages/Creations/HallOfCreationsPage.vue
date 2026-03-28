<script setup>
import { Head, router } from '@inertiajs/vue3';
import { onMounted, reactive, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CreationCard from '@/Components/Creations/CreationCard.vue';
import { toast } from '@/Utils/Alert';

const loading = ref(false);
const togglingId = ref(0);
const creations = ref([]);
const meta = ref({
    current_page: 1,
    last_page: 1,
    total: 0,
});

const filters = reactive({
    search: '',
    category: '',
    status: '',
    sort: 'popular',
});

const relativeRoute = (name, params = {}) => route(name, params, false);

const getAppreciationErrorMessage = (error) => {
    const status = Number(error?.response?.status || 0);
    const serverMessage = String(error?.response?.data?.message || '').trim();

    if (status === 401) {
        return 'Session expired. Please log in again.';
    }

    if (status === 419) {
        return 'Session mismatch detected. Reload the page and try again.';
    }

    if (serverMessage !== '') {
        return serverMessage;
    }

    return 'Unable to update appreciation.';
};

const fetchCreations = async (page = 1) => {
    loading.value = true;

    try {
        const response = await window.axios.get(relativeRoute('api.hall.index'), {
            params: {
                page,
                per_page: 12,
                search: filters.search || undefined,
                category: filters.category || undefined,
                status: filters.status || undefined,
                sort: filters.sort || undefined,
            },
        });

        const payload = response.data || {};
        creations.value = Array.isArray(payload.data) ? payload.data : [];

        meta.value = {
            current_page: Number(payload.meta?.current_page || 1),
            last_page: Number(payload.meta?.last_page || 1),
            total: Number(payload.meta?.total || 0),
        };
    } catch (error) {
        toast.error('LOAD_FAILED', 'Failed to load hall data.');
    } finally {
        loading.value = false;
    }
};

const openDetail = (creation) => {
    router.visit(relativeRoute('hall.creations.show', { creation: creation.id }));
};

const toggleAppreciation = async (creation) => {
    if (!creation?.id || togglingId.value === Number(creation.id)) {
        return;
    }

    togglingId.value = Number(creation.id);

    try {
        if (creation.is_appreciated) {
            const response = await window.axios.delete(relativeRoute('api.creations.appreciate.destroy', { creation: creation.id }));
            creation.is_appreciated = false;
            creation.appreciations_count = Number(response.data?.appreciations_count || creation.appreciations_count || 0);
        } else {
            const response = await window.axios.post(relativeRoute('api.creations.appreciate.store', { creation: creation.id }));
            creation.is_appreciated = true;
            creation.appreciations_count = Number(response.data?.appreciations_count || creation.appreciations_count || 0);
        }
    } catch (error) {
        console.error('hall appreciation failed', {
            status: error?.response?.status,
            message: error?.response?.data?.message,
            url: error?.config?.url,
            method: error?.config?.method,
        });
        toast.error('ACTION_FAILED', getAppreciationErrorMessage(error));
    } finally {
        togglingId.value = 0;
    }
};

const openInsight = (creation) => {
    openDetail(creation);
};

const resetFilters = () => {
    filters.search = '';
    filters.category = '';
    filters.status = '';
    filters.sort = 'popular';
    fetchCreations(1);
};

onMounted(() => {
    fetchCreations();
});
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Hall of Creations" />

        <div class="mx-auto max-w-7xl space-y-6 font-['Press_Start_2P'] text-[#4ed4d4]">
            <section class="hall-hero">
                <div class="hall-hero__overlay" />
                <div class="relative z-10 flex flex-wrap items-center justify-between gap-4">
                    <div class="space-y-2">
                        <p class="text-[8px] uppercase tracking-[0.26em] text-cyan-300/90">Artifact Showcase</p>
                        <h1 class="text-[12px] uppercase tracking-[0.18em] text-white">Hall of Creations</h1>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <label class="icon-input">
                            <i class="fi fi-rr-search text-[11px]" />
                            <input
                                v-model="filters.search"
                                type="text"
                                class="bg-transparent text-[8px] uppercase text-cyan-200 outline-none placeholder:text-slate-500"
                                placeholder="Search"
                                @keyup.enter="fetchCreations(1)"
                            >
                        </label>

                        <label class="icon-input">
                            <i class="fi fi-rr-apps-sort text-[11px]" />
                            <input
                                v-model="filters.category"
                                type="text"
                                class="bg-transparent text-[8px] uppercase text-cyan-200 outline-none placeholder:text-slate-500"
                                placeholder="Category"
                                @keyup.enter="fetchCreations(1)"
                            >
                        </label>

                        <label class="icon-input">
                            <i class="fi fi-rr-filter text-[11px]" />
                            <select
                                v-model="filters.status"
                                class="bg-transparent text-[8px] uppercase text-cyan-200 outline-none"
                                @change="fetchCreations(1)"
                            >
                                <option value="">All</option>
                                <option value="crafting">Crafting</option>
                                <option value="refining">Refining</option>
                                <option value="finished">Finished</option>
                            </select>
                        </label>

                        <label class="icon-input">
                            <i class="fi fi-rr-sort-amount-down text-[11px]" />
                            <select
                                v-model="filters.sort"
                                class="bg-transparent text-[8px] uppercase text-cyan-200 outline-none"
                                @change="fetchCreations(1)"
                            >
                                <option value="popular">Popular</option>
                                <option value="latest">Latest</option>
                            </select>
                        </label>

                        <button
                            type="button"
                            class="filter-action-btn"
                            @click="resetFilters"
                        >
                            Reset
                        </button>
                    </div>
                </div>
            </section>

            <section class="rpg-panel border-cyan-500/40 bg-[#161b22]/85">
                <div v-if="loading" class="py-14 text-center text-[8px] uppercase text-slate-500">
                    Loading...
                </div>

                <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
                    <CreationCard
                        v-for="creation in creations"
                        :key="creation.id"
                        :creation="creation"
                        :busy="togglingId === Number(creation.id)"
                        @open="openDetail"
                        @appreciate="toggleAppreciation"
                        @insight="openInsight"
                    />
                </div>

                <div v-if="!loading && creations.length === 0" class="py-14 text-center text-[8px] uppercase text-slate-500">
                    No creations found.
                </div>

                <div class="mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-slate-700 pt-4">
                    <span class="text-[8px] uppercase text-slate-500">Total {{ meta.total }}</span>

                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="pager-btn"
                            :disabled="meta.current_page <= 1"
                            @click="fetchCreations(meta.current_page - 1)"
                        >
                            <i class="fi fi-rr-angle-small-left text-[12px]" />
                        </button>
                        <span class="text-[8px] uppercase text-slate-400">{{ meta.current_page }} / {{ meta.last_page }}</span>
                        <button
                            type="button"
                            class="pager-btn"
                            :disabled="meta.current_page >= meta.last_page"
                            @click="fetchCreations(meta.current_page + 1)"
                        >
                            <i class="fi fi-rr-angle-small-right text-[12px]" />
                        </button>
                    </div>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.hall-hero {
    @apply relative overflow-hidden border-4 border-cyan-500/40 bg-[#121722] p-6;
    box-shadow: 8px 8px 0 0 rgba(0, 0, 0, 0.4);
}

.hall-hero__overlay {
    @apply absolute inset-0;
    background:
        radial-gradient(circle at 15% 25%, rgba(34, 211, 238, 0.2), transparent 35%),
        radial-gradient(circle at 80% 15%, rgba(20, 184, 166, 0.14), transparent 40%),
        linear-gradient(120deg, rgba(3, 7, 18, 0.92), rgba(8, 47, 73, 0.55));
}

.rpg-panel {
    @apply relative border-4 p-4;
    box-shadow: 8px 8px 0 0 rgba(0, 0, 0, 0.45);
}

.icon-input {
    @apply inline-flex items-center gap-2 border border-slate-700 bg-black/35 px-3 py-2 text-cyan-300;
}

.pager-btn {
    @apply inline-flex h-8 w-8 items-center justify-center border border-slate-700 bg-slate-900/90 text-cyan-300 transition-colors disabled:cursor-not-allowed disabled:opacity-40 hover:border-cyan-500 hover:text-cyan-200;
}

.filter-action-btn {
    @apply inline-flex min-h-8 items-center justify-center border border-cyan-500/50 bg-cyan-500/10 px-4 text-[8px] uppercase tracking-[0.18em] text-cyan-200 transition-colors hover:border-cyan-400 hover:bg-cyan-500/20 hover:text-white;
}
</style>
