<script setup>
import { Head, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CreationCard from '@/Components/Creations/CreationCard.vue';
import { toast } from '@/Utils/Alert';

const loading = ref(false);
const initialLoading = ref(true);
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
const requestVersion = ref(0);
const fetchError = ref('');
let searchDebounceTimeout = null;

const hasCreations = computed(() => creations.value.length > 0);
const isRefreshing = computed(() => loading.value && hasCreations.value);
const showLoadingState = computed(() => initialLoading.value || (loading.value && !hasCreations.value));

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

const replaceCreationCard = (creationId, nextCreation) => {
    const targetId = Number(creationId || 0);
    if (!targetId || !nextCreation) {
        return;
    }

    creations.value = creations.value.map((item) => {
        if (Number(item?.id || 0) !== targetId) {
            return item;
        }

        return {
            ...item,
            ...nextCreation,
        };
    });
};

const refreshCreationCard = async (creationId) => {
    const response = await window.axios.get(relativeRoute('api.hall.show', { creation: creationId }));
    const nextCreation = response.data?.data || null;

    if (nextCreation) {
        replaceCreationCard(creationId, nextCreation);
    }
};

const fetchCreations = async (page = 1) => {
    const nextRequestVersion = requestVersion.value + 1;
    requestVersion.value = nextRequestVersion;
    loading.value = true;
    fetchError.value = '';

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

        if (nextRequestVersion !== requestVersion.value) {
            return;
        }

        const payload = response.data || {};
        creations.value = Array.isArray(payload.data) ? payload.data : [];

        meta.value = {
            current_page: Number(payload.meta?.current_page || 1),
            last_page: Number(payload.meta?.last_page || 1),
            total: Number(payload.meta?.total || 0),
        };
    } catch (error) {
        if (nextRequestVersion !== requestVersion.value) {
            return;
        }

        fetchError.value = 'Failed to load hall data.';
        toast.error('LOAD_FAILED', 'Failed to load hall data.');
    } finally {
        if (nextRequestVersion === requestVersion.value) {
            loading.value = false;
            initialLoading.value = false;
        }
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

        await refreshCreationCard(creation.id);
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
    if (searchDebounceTimeout) {
        window.clearTimeout(searchDebounceTimeout);
        searchDebounceTimeout = null;
    }

    filters.search = '';
    filters.category = '';
    filters.status = '';
    filters.sort = 'popular';
    fetchCreations(1);
};

watch(
    () => [filters.search, filters.category],
    () => {
        if (searchDebounceTimeout) {
            window.clearTimeout(searchDebounceTimeout);
        }

        searchDebounceTimeout = window.setTimeout(() => {
            fetchCreations(1);
        }, 260);
    },
);

onBeforeUnmount(() => {
    if (searchDebounceTimeout) {
        window.clearTimeout(searchDebounceTimeout);
        searchDebounceTimeout = null;
    }
});

onMounted(() => {
    fetchCreations();
});
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Hall of Creations" />

        <div class="hall-shell mx-auto max-w-7xl space-y-5 px-1 font-['Press_Start_2P'] text-[#4ed4d4] sm:space-y-6 sm:px-0">
            <section class="hall-hero">
                <div class="hall-hero__overlay" />
                <div class="hall-hero__grid">
                    <div class="space-y-3">
                        <p class="text-[8px] uppercase tracking-[0.26em] text-cyan-300/90">Artifact Showcase</p>
                        <h1 class="text-[11px] uppercase tracking-[0.18em] text-white sm:text-[13px] lg:text-[14px]">Hall of Creations</h1>
                    </div>

                    <div class="filter-panel">
                        <div class="filter-grid">
                            <label class="icon-input">
                                <i class="fi fi-rr-search text-[11px]" />
                                <input
                                    v-model="filters.search"
                                    type="text"
                                    class="icon-input__control"
                                    placeholder="Search"
                                >
                            </label>

                            <label class="icon-input">
                                <i class="fi fi-rr-apps-sort text-[11px]" />
                                <input
                                    v-model="filters.category"
                                    type="text"
                                    class="icon-input__control"
                                    placeholder="Category"
                                >
                            </label>

                            <label class="icon-input">
                                <i class="fi fi-rr-filter text-[11px]" />
                                <select
                                    v-model="filters.status"
                                    class="icon-input__control"
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
                                    class="icon-input__control"
                                    @change="fetchCreations(1)"
                                >
                                    <option value="popular">Popular</option>
                                    <option value="latest">Latest</option>
                                </select>
                            </label>

                            <button
                                type="button"
                                class="filter-action-btn filter-action-btn--reset"
                                @click="resetFilters"
                            >
                                Reset
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <section class="rpg-panel relative border-cyan-500/40 bg-[#161b22]/85">
                <div v-if="showLoadingState" class="loading-state">
                    <div class="loading-chip">
                        <span class="loading-chip__dot" />
                        Loading creations...
                    </div>
                </div>

                <div v-else class="space-y-4">
                    <div v-if="isRefreshing" class="refresh-chip">
                        Refreshing hall...
                    </div>

                    <div class="hall-grid">
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
                </div>

                <div v-if="!loading && creations.length === 0" class="empty-state">
                    <i class="fi fi-rr-lightbulb-on text-[22px] text-cyan-300/70" />
                    <p>No creations found.</p>
                    <p class="empty-state__hint">Try another keyword, category, or status.</p>
                </div>

                <div v-if="fetchError && !hasCreations" class="mt-4 text-center text-[8px] uppercase text-rose-300">
                    {{ fetchError }}
                </div>

                <div class="pagination-bar">
                    <span class="text-center text-[8px] uppercase text-slate-500 sm:text-left">Total {{ meta.total }}</span>

                    <div class="flex items-center justify-center gap-2 sm:justify-end">
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
.hall-shell {
    width: 100%;
}

.hall-hero {
    @apply relative overflow-hidden border-4 border-cyan-500/40 bg-[#121722] p-4 sm:p-5 lg:p-6;
    box-shadow: 8px 8px 0 0 rgba(0, 0, 0, 0.4);
}

.hall-hero__overlay {
    @apply absolute inset-0;
    background:
        radial-gradient(circle at 15% 25%, rgba(34, 211, 238, 0.2), transparent 35%),
        radial-gradient(circle at 80% 15%, rgba(20, 184, 166, 0.14), transparent 40%),
        linear-gradient(120deg, rgba(3, 7, 18, 0.92), rgba(8, 47, 73, 0.55));
}

.hall-hero__grid {
    position: relative;
    z-index: 10;
    display: grid;
    gap: 1rem;
}

.rpg-panel {
    @apply relative border-4 p-4;
    box-shadow: 8px 8px 0 0 rgba(0, 0, 0, 0.45);
}

.filter-panel {
    width: 100%;
}

.filter-grid {
    display: grid;
    width: 100%;
    gap: 0.45rem;
    grid-template-columns: 1fr;
}

.hall-grid {
    display: grid;
    gap: 1.25rem;
    justify-items: start;
}

.icon-input {
    @apply inline-flex min-h-9 w-full items-center gap-2 border border-slate-700 bg-[#0b1520]/70 px-3 py-2 text-cyan-300 transition-colors;
}

.icon-input:focus-within {
    border-color: rgba(34, 211, 238, 0.5);
    background: rgba(2, 8, 23, 0.72);
}

.icon-input__control {
    min-width: 0;
    width: 100%;
    border: 1px solid rgba(100, 116, 139, 0.55);
    background: rgba(3, 10, 18, 0.7);
    padding: 0.68rem 0.85rem;
    font-size: 7px;
    text-transform: uppercase;
    color: #a5f3fc;
    outline: none;
}

.icon-input__control::placeholder {
    color: #64748b;
}

.icon-input select.icon-input__control {
    padding-right: 2.5rem;
}

.pager-btn {
    @apply inline-flex h-8 w-8 items-center justify-center border border-slate-700 bg-slate-900/90 text-cyan-300 transition-colors disabled:cursor-not-allowed disabled:opacity-40 hover:border-cyan-500 hover:text-cyan-200;
}

.filter-action-btn {
    @apply inline-flex min-h-9 w-full items-center justify-center border border-cyan-500/70 bg-cyan-500/20 px-4 text-[7px] uppercase tracking-[0.18em] text-cyan-100 transition-colors hover:border-cyan-300 hover:bg-cyan-500/30 hover:text-white;
}

.filter-action-btn--reset {
    min-width: 120px;
}

.refresh-chip {
    @apply inline-flex items-center border border-cyan-500/40 bg-cyan-500/10 px-3 py-2 text-[8px] uppercase text-cyan-200;
}

.loading-state {
    display: flex;
    min-height: 280px;
    align-items: center;
    justify-content: center;
    padding: 1.5rem 0;
}

.loading-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.55rem;
    border: 1px solid rgba(34, 211, 238, 0.32);
    background: rgba(6, 22, 34, 0.68);
    padding: 0.7rem 0.95rem;
    font-size: 8px;
    text-transform: uppercase;
    color: #a5f3fc;
}

.loading-chip__dot {
    width: 8px;
    height: 8px;
    background: #67e8f9;
    box-shadow: 0 0 12px rgba(103, 232, 249, 0.8);
    animation: hallPulse 1s ease-in-out infinite;
}

.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.75rem;
    padding-block: 3.5rem;
    text-align: center;
    font-size: 8px;
    text-transform: uppercase;
    color: #94a3b8;
}

.empty-state__hint {
    max-width: 320px;
    line-height: 1.6;
    color: #64748b;
}

.pagination-bar {
    margin-top: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    border-top: 1px solid rgb(51 65 85);
    padding-top: 1rem;
}

@keyframes hallPulse {
    0%,
    100% {
        opacity: 0.45;
        transform: scale(0.92);
    }
    50% {
        opacity: 1;
        transform: scale(1);
    }
}

@media (min-width: 1280px) {
    .filter-grid {
        grid-template-columns: minmax(200px, 1.3fr) minmax(200px, 1.3fr) minmax(150px, 0.95fr) minmax(150px, 0.95fr) auto;
    }
}

@media (min-width: 768px) {
    .hall-hero__grid {
        gap: 1.25rem;
    }

    .hall-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .pagination-bar {
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
    }
}

@media (min-width: 1280px) {
    .hall-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 639px) {
    .rpg-panel {
        padding: 0.875rem;
    }

    .hall-hero {
        padding: 0.875rem;
    }

    .empty-state__hint {
        line-height: 1.7;
    }
}
</style>
