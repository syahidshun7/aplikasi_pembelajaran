<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, reactive, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { toast } from '@/Utils/Alert';

const loading = ref(false);
const creations = ref([]);
const meta = ref({ current_page: 1, last_page: 1, total: 0 });
const filters = reactive({ search: '', status: '', scope: 'owned' });

const fetchCreations = async (page = 1) => {
    loading.value = true;

    try {
        const response = await window.axios.get(route('api.profile.creations.index'), {
            params: {
                page,
                per_page: 12,
                search: filters.search || undefined,
                status: filters.status || undefined,
                scope: filters.scope || undefined,
            },
        });

        const payload = response.data || {};
        creations.value = Array.isArray(payload.data) ? payload.data : [];
        meta.value = {
            current_page: Number(payload.current_page || 1),
            last_page: Number(payload.last_page || 1),
            total: Number(payload.total || 0),
        };
    } catch (error) {
        toast.error('LOAD_FAILED', 'Failed to load creations.');
    } finally {
        loading.value = false;
    }
};

const removeCreation = async (creation) => {
    if (!creation?.can_delete) {
        toast.error('ACCESS_DENIED', 'Only the owner can delete this creation.');
        return;
    }

    const result = await toast.confirm('DELETE?', creation.title, 'DELETE');
    if (!result.isConfirmed) {
        return;
    }

    try {
        await window.axios.delete(route('api.creations.destroy', { creation: creation.id }));
        toast.success('DELETED', 'Creation removed.');

        if (creations.value.length === 1 && meta.value.current_page > 1) {
            await fetchCreations(meta.value.current_page - 1);
            return;
        }

        await fetchCreations(meta.value.current_page);
    } catch (error) {
        toast.error('DELETE_FAILED', 'Unable to delete creation.');
    }
};

const setScope = (scope) => {
    filters.scope = scope;
    fetchCreations(1);
};

const scopeButtonClass = (scope) => (
    filters.scope === scope
        ? 'creation-scope--active border-cyan-400 bg-cyan-500/20 text-cyan-100'
        : 'creation-scope--idle border-slate-700 bg-black/25 text-slate-400 hover:border-cyan-500/60 hover:text-cyan-200'
);

const statusBadgeClass = (status) => {
    const value = String(status || '');

    if (value === 'finished') {
        return 'border-emerald-500/60 bg-emerald-500/10 text-emerald-200';
    }

    if (value === 'refining') {
        return 'border-amber-500/60 bg-amber-500/10 text-amber-200';
    }

    return 'border-cyan-500/60 bg-cyan-500/10 text-cyan-200';
};

const publicationBadgeClass = (status) => (
    String(status || 'draft') === 'publish'
        ? 'creation-publication--published border-emerald-500/60 bg-emerald-500/10 text-emerald-200'
        : 'creation-publication--draft border-slate-600 bg-slate-800/60 text-slate-300'
);

const statsLabel = computed(() => {
    if (filters.scope === 'collaborating') {
        return 'Active team docs';
    }

    if (filters.scope === 'all') {
        return 'Accessible docs';
    }

    return 'Owned docs';
});

onMounted(() => {
    fetchCreations();
});
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Profile Creations" />

        <div class="lobby-detail-page profile-light-page profile-creations-page user-page-shell space-y-6 font-['Press_Start_2P'] text-[#4ed4d4]">
            <section class="creation-workspace-panel rpg-panel border-cyan-500/35 bg-[#111827]/82">
                <div class="flex flex-wrap items-start justify-between gap-4 border-b border-slate-700 pb-4">
                    <div class="space-y-2">
                        <p class="text-[8px] uppercase tracking-[0.22em] text-cyan-300/80">Creation Workspace</p>
                        <h1 class="text-[12px] uppercase tracking-[0.14em] text-white">Write, revise, and publish documentation</h1>
                        <p class="max-w-2xl text-[8px] leading-relaxed text-slate-400">
                            Semua creation sekarang dikerjakan lewat editor documentation yang lebih fokus. Halaman ini dipakai untuk memilih draft, melihat project tim, dan masuk kembali ke editor.
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <Link :href="route('profile.creations.create')" class="icon-link" title="New creation">
                            <i class="fi fi-rr-plus text-[12px]" />
                        </Link>
                        <Link :href="route('hall.creations.index')" class="icon-link" title="Hall of Creations">
                            <i class="fi fi-rr-lightbulb-on text-[12px]" />
                        </Link>
                        <Link :href="route('profile.dashboard')" class="icon-link" title="Profile dashboard">
                            <i class="fi fi-rr-user text-[12px]" />
                        </Link>
                    </div>
                </div>

                <div class="mt-4 grid gap-3 lg:grid-cols-[1fr,auto] lg:items-center">
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" class="scope-chip" :class="scopeButtonClass('owned')" @click="setScope('owned')">Owned</button>
                        <button type="button" class="scope-chip" :class="scopeButtonClass('collaborating')" @click="setScope('collaborating')">Collaborating</button>
                        <button type="button" class="scope-chip" :class="scopeButtonClass('all')" @click="setScope('all')">All Access</button>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <label class="icon-input">
                            <i class="fi fi-rr-search text-[11px]" />
                            <input
                                v-model="filters.search"
                                class="bg-transparent text-[8px] uppercase text-cyan-200 outline-none placeholder:text-slate-500"
                                placeholder="Search"
                                @keyup.enter="fetchCreations(1)"
                            >
                        </label>
                        <label class="icon-input">
                            <i class="fi fi-rr-filter text-[11px]" />
                            <select v-model="filters.status" class="bg-transparent text-[8px] uppercase text-cyan-200 outline-none" @change="fetchCreations(1)">
                                <option value="">All</option>
                                <option value="crafting">Crafting</option>
                                <option value="refining">Refining</option>
                                <option value="finished">Finished</option>
                            </select>
                        </label>
                    </div>
                </div>
            </section>

            <section class="creation-list-panel rpg-panel border-slate-700/80 bg-[#0f172a]/82">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b border-slate-700 pb-4">
                    <div>
                        <p class="text-[8px] uppercase text-slate-500">{{ statsLabel }}</p>
                        <p class="mt-2 text-[8px] uppercase text-slate-400">Total {{ meta.total }}</p>
                    </div>

                    <Link :href="route('profile.creations.create')" class="editor-cta">
                        <i class="fi fi-rr-pen-fancy text-[12px]" />
                        <span>Open Editor</span>
                    </Link>
                </div>

                <div v-if="loading" class="py-14 text-center text-[8px] uppercase text-slate-500">
                    Loading...
                </div>

                <div v-else-if="creations.length === 0" class="empty-state">
                    <i class="fi fi-rr-document text-[20px]" />
                    <p class="mt-4 text-[8px] uppercase text-slate-300">No creation found in this scope.</p>
                    <Link :href="route('profile.creations.create')" class="mt-4 inline-flex h-8 w-8 items-center justify-center border border-cyan-500/50 bg-cyan-500/10 text-cyan-200">
                        <i class="fi fi-rr-plus text-[12px]" />
                    </Link>
                </div>

                <div v-else class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <article v-for="creation in creations" :key="creation.id" class="creation-card">
                        <div class="creation-card__preview">
                            <img v-if="creation.thumbnail_url" :src="creation.thumbnail_url" alt="Creation thumbnail" class="creation-card__image">
                            <div v-else class="creation-card__empty">
                                <i class="fi fi-rr-lightbulb-on text-[22px]" />
                            </div>

                            <div class="creation-card__badges">
                                <span class="creation-card__badge" :class="publicationBadgeClass(creation.publication_status)">
                                    {{ creation.publication_status === 'publish' ? 'Published' : 'Draft' }}
                                </span>
                                <span v-if="Number(creation.team_size || 1) > 1" class="creation-card__badge border-emerald-500/60 bg-emerald-500/10 text-emerald-200">
                                    Team {{ creation.team_size }}
                                </span>
                            </div>
                        </div>

                        <div class="space-y-3 p-4">
                            <div class="space-y-2">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <h2 class="line-clamp-2 text-[9px] uppercase leading-relaxed text-white">{{ creation.title }}</h2>
                                    <span class="rounded border px-2 py-[3px] text-[7px] uppercase" :class="statusBadgeClass(creation.status)">
                                        {{ creation.status }}
                                    </span>
                                </div>

                                <p class="line-clamp-3 text-[8px] leading-relaxed text-slate-400">
                                    {{ creation.description || 'No summary yet.' }}
                                </p>
                            </div>

                            <div class="flex flex-wrap items-center gap-2 text-[7px] uppercase text-slate-500">
                                <span v-if="creation.category" class="inline-flex items-center gap-1">
                                    <i class="fi fi-rr-apps text-[9px]" />
                                    {{ creation.category }}
                                </span>
                                <span class="inline-flex items-center gap-1">
                                    <i class="fi fi-rr-user text-[9px]" />
                                    {{ creation.ownership_type === 'collaborator' ? 'Collab' : 'Owner' }}
                                </span>
                            </div>

                            <div v-if="creation.status !== 'finished'" class="space-y-1">
                                <div class="h-1.5 overflow-hidden border border-slate-700 bg-slate-950">
                                    <div class="h-full bg-cyan-500 transition-all" :style="{ width: `${creation.progress || 0}%` }" />
                                </div>
                                <p class="text-[7px] uppercase text-slate-500">{{ creation.progress || 0 }}%</p>
                            </div>

                            <div class="flex items-center justify-between border-t border-slate-800 pt-3">
                                <div class="flex items-center gap-3 text-[7px] uppercase text-slate-500">
                                    <span class="inline-flex items-center gap-1"><i class="fi fi-rr-heart text-[9px]" />{{ creation.appreciations_count || 0 }}</span>
                                    <span class="inline-flex items-center gap-1"><i class="fi fi-rr-comment-alt text-[9px]" />{{ creation.insights_count || 0 }}</span>
                                </div>

                                <div class="flex items-center gap-1">
                                   <Link :href="route('profile.creations.edit', { creation: creation.slug || creation.id })" class="icon-action text-cyan-300 hover:text-cyan-100" title="Edit">
                                       <i class="fi fi-rr-pencil text-[11px]" />
                                   </Link>
                                    <Link :href="route('hall.creations.show', { creation: creation.slug || creation.id })" class="icon-action text-amber-300 hover:text-amber-100" title="Detail">
                                       <i class="fi fi-rr-eye text-[11px]" />
                                   </Link>
                                    <button v-if="creation.can_delete" type="button" class="icon-action text-rose-300 hover:text-rose-100" title="Delete" @click="removeCreation(creation)">
                                        <i class="fi fi-rr-trash text-[11px]" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </article>
                </div>

                <div class="mt-5 flex items-center justify-between border-t border-slate-700 pt-4">
                    <span class="text-[8px] uppercase text-slate-500">Page {{ meta.current_page }} / {{ meta.last_page }}</span>
                    <div class="flex items-center gap-2">
                        <button type="button" class="pager-btn" :disabled="meta.current_page <= 1" @click="fetchCreations(meta.current_page - 1)">
                            <i class="fi fi-rr-angle-small-left text-[12px]" />
                        </button>
                        <button type="button" class="pager-btn" :disabled="meta.current_page >= meta.last_page" @click="fetchCreations(meta.current_page + 1)">
                            <i class="fi fi-rr-angle-small-right text-[12px]" />
                        </button>
                    </div>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.rpg-panel { @apply relative border-4 p-5; box-shadow: 8px 8px 0 0 rgba(0, 0, 0, 0.45); }
.icon-link { @apply inline-flex h-8 w-8 items-center justify-center border border-slate-700 bg-slate-900/90 text-cyan-300 transition-colors hover:border-cyan-500 hover:text-cyan-100; }
.icon-input { @apply inline-flex items-center gap-2 border border-slate-700 bg-black/35 px-3 py-2 text-cyan-300; }
.scope-chip { @apply inline-flex items-center justify-center border px-3 py-2 text-[7px] uppercase transition-colors; }
.icon-action { @apply inline-flex h-8 w-8 items-center justify-center border border-slate-700 bg-black/25 transition-colors; }
.pager-btn { @apply inline-flex h-8 w-8 items-center justify-center border border-slate-700 bg-slate-900/90 text-cyan-300 transition-colors disabled:cursor-not-allowed disabled:opacity-40 hover:border-cyan-500 hover:text-cyan-200; }
.editor-cta { @apply inline-flex items-center gap-2 border border-cyan-500/40 bg-cyan-500/10 px-3 py-2 text-[8px] uppercase text-cyan-200 transition-colors hover:border-cyan-400 hover:bg-cyan-500/20; }
.empty-state { @apply flex flex-col items-center justify-center border border-dashed border-slate-700 bg-black/20 px-6 py-16 text-center text-slate-500; }
.creation-card { @apply overflow-hidden border border-slate-700 bg-slate-900/90 shadow-[6px_6px_0_rgba(0,0,0,0.25)]; }
.creation-card__preview { @apply relative aspect-[16/10] overflow-hidden border-b border-slate-700 bg-gradient-to-br from-[#10202a] via-[#111827] to-[#0b1120]; }
.creation-card__image { @apply h-full w-full object-cover; }
.creation-card__empty { @apply flex h-full items-center justify-center text-cyan-200/70; }
.creation-card__badges { @apply absolute left-3 top-3 flex flex-wrap gap-1; }
.creation-card__badge { @apply rounded border px-2 py-[3px] text-[6px] uppercase; }
</style>
