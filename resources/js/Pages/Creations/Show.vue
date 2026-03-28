<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, reactive, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { toast } from '@/Utils/Alert';

const props = defineProps({
    creationId: {
        type: Number,
        required: true,
    },
});

const page = usePage();
const authUser = computed(() => page.props?.auth?.user || null);
const creation = ref(null);
const loadingCreation = ref(false);
const togglingAppreciation = ref(false);

const insights = ref([]);
const insightMeta = ref({
    current_page: 1,
    last_page: 1,
});
const loadingInsights = ref(false);
const postingInsight = ref(false);

const insightForm = reactive({
    content: '',
    parent_id: null,
});
const insightsSection = ref(null);
const activePhotoUrl = ref('');

const toDisplayDate = (value) => {
    if (!value) {
        return '';
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return date.toLocaleString('id-ID');
};

const fetchCreation = async () => {
    loadingCreation.value = true;
    try {
        const response = await window.axios.get(route('api.hall.show', { creation: props.creationId }));
        creation.value = response.data?.data || null;
        activePhotoUrl.value = String(
            creation.value?.photos?.[0]?.url
            || creation.value?.thumbnail_url
            || ''
        );
        return Boolean(creation.value);
    } catch (error) {
        creation.value = null;
        activePhotoUrl.value = '';
        toast.error('LOAD_FAILED', 'Unable to load creation detail.');
        return false;
    } finally {
        loadingCreation.value = false;
    }
};

const fetchInsights = async (pageNum = 1, append = false) => {
    loadingInsights.value = true;
    try {
        const response = await window.axios.get(route('api.creations.insights.index', { creation: props.creationId }), {
            params: {
                page: pageNum,
                per_page: 10,
            },
        });

        const payload = response.data || {};
        const incoming = Array.isArray(payload.data) ? payload.data : [];
        insights.value = append ? [...insights.value, ...incoming] : incoming;
        insightMeta.value = {
            current_page: Number(payload.meta?.current_page || 1),
            last_page: Number(payload.meta?.last_page || 1),
        };
    } catch (error) {
        toast.error('LOAD_FAILED', 'Unable to load insights.');
    } finally {
        loadingInsights.value = false;
    }
};

const toggleAppreciation = async () => {
    if (!creation.value || togglingAppreciation.value) {
        return;
    }

    togglingAppreciation.value = true;

    try {
        if (creation.value.is_appreciated) {
            const response = await window.axios.delete(route('api.creations.appreciate.destroy', { creation: creation.value.id }));
            creation.value.is_appreciated = false;
            creation.value.appreciations_count = Number(response.data?.appreciations_count || 0);
        } else {
            const response = await window.axios.post(route('api.creations.appreciate.store', { creation: creation.value.id }));
            creation.value.is_appreciated = true;
            creation.value.appreciations_count = Number(response.data?.appreciations_count || 0);
        }
    } catch (error) {
        toast.error('ACTION_FAILED', 'Unable to update appreciation.');
    } finally {
        togglingAppreciation.value = false;
    }
};

const submitInsight = async () => {
    const content = String(insightForm.content || '').trim();
    if (!content) {
        toast.error('EMPTY_INSIGHT', 'Insight content cannot be empty.');
        return;
    }

    postingInsight.value = true;
    try {
        await window.axios.post(route('api.creations.insights.store', { creation: props.creationId }), {
            content,
            parent_id: insightForm.parent_id || null,
        });

        insightForm.content = '';
        insightForm.parent_id = null;
        await fetchInsights(1, false);
        if (creation.value) {
            creation.value.insights_count = Number(creation.value.insights_count || 0) + 1;
        }
    } catch (error) {
        const firstError = Object.values(error?.response?.data?.errors || {})?.[0]?.[0] || 'Failed to send insight.';
        toast.error('POST_FAILED', String(firstError));
    } finally {
        postingInsight.value = false;
    }
};

const setReply = (insightId) => {
    insightForm.parent_id = Number(insightId);
};

const cancelReply = () => {
    insightForm.parent_id = null;
};

const loadMoreInsights = () => {
    const nextPage = insightMeta.value.current_page + 1;
    if (nextPage <= insightMeta.value.last_page) {
        fetchInsights(nextPage, true);
    }
};

const focusInsights = () => {
    if (!insightsSection.value) {
        return;
    }

    insightsSection.value.scrollIntoView({
        behavior: 'smooth',
        block: 'start',
    });
};

const selectPhoto = (url) => {
    activePhotoUrl.value = String(url || '');
};

onMounted(async () => {
    const loaded = await fetchCreation();

    if (loaded) {
        await fetchInsights();
    }
});
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Creation Detail" />

        <div class="mx-auto max-w-6xl space-y-6 font-['Press_Start_2P'] text-[#4ed4d4]">
            <section v-if="loadingCreation" class="rpg-panel border-slate-700 bg-[#1a1c2c]/80 text-center text-[8px] uppercase text-slate-400">
                Loading creation detail...
            </section>

            <section v-else-if="creation" class="rpg-panel border-cyan-500/40 bg-[#1a1c2c]/80">
                <div class="mb-4 flex flex-wrap items-start justify-between gap-3 border-b border-slate-700 pb-3">
                    <div class="space-y-2">
                        <p class="text-[8px] uppercase text-slate-400">
                            Hall of Creations / {{ creation.category || 'General' }}
                        </p>
                        <h1 class="text-[12px] uppercase text-white">{{ creation.title }}</h1>
                        <p class="text-[8px] uppercase text-cyan-300">
                            by {{ creation.creator?.username || creation.creator?.name || 'Unknown Creator' }}
                        </p>
                    </div>
                    <Link :href="route('hall.creations.index')" class="text-[8px] uppercase text-amber-300 hover:text-amber-200">
                        Back To Hall
                    </Link>
                </div>

                <div class="creation-stage relative mb-5 overflow-hidden border border-slate-700 bg-gradient-to-br from-[#10202a] via-[#121722] to-[#0d1117]">
                    <div v-if="activePhotoUrl" class="creation-stage__frame">
                        <img
                            :src="activePhotoUrl"
                            alt="Creation photo"
                            class="creation-stage__image"
                        >
                    </div>
                    <div v-else class="creation-stage__frame flex items-center justify-center p-4">
                        <div class="absolute -right-6 -top-6 h-20 w-20 rounded-full border border-cyan-300/20 bg-cyan-500/10 blur-sm" />
                        <i class="fi fi-rr-lightbulb-on relative z-10 text-[34px] text-cyan-200/85" />
                    </div>
                </div>

                <div
                    v-if="Array.isArray(creation.photos) && creation.photos.length > 0"
                    class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5"
                >
                    <button
                        v-for="photo in creation.photos"
                        :key="photo.id"
                        type="button"
                        class="creation-thumb overflow-hidden border bg-[#101622] transition-all"
                        :class="activePhotoUrl === photo.url ? 'border-cyan-400' : 'border-slate-700 hover:border-cyan-500/70'"
                        @click="selectPhoto(photo.url)"
                    >
                        <img :src="photo.url" alt="Creation thumbnail" class="creation-thumb__image">
                    </button>
                </div>

                <div v-if="creation.status !== 'finished'" class="mb-4 h-2 overflow-hidden border border-slate-700 bg-slate-950">
                    <div class="h-full bg-cyan-500 transition-all" :style="{ width: `${creation.progress || 0}%` }" />
                </div>

                <div class="mb-4 flex flex-wrap items-center gap-2 text-[8px] uppercase">
                    <span class="rounded border border-cyan-500 px-2 py-1 text-cyan-300">{{ creation.status }}</span>
                    <span class="rounded border border-slate-600 px-2 py-1 text-slate-300">{{ creation.progress }}%</span>
                </div>

                <p class="mb-5 whitespace-pre-line text-[8px] leading-relaxed text-slate-200">{{ creation.description }}</p>

                <div class="flex flex-wrap items-center gap-2">
                    <a
                        v-if="creation.link"
                        :href="creation.link"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="icon-btn text-cyan-300 hover:text-cyan-100"
                        title="Open project"
                    >
                        <i class="fi fi-rr-link text-[12px]" />
                    </a>
                    <button
                        type="button"
                        class="icon-btn inline-flex items-center gap-1"
                        :class="creation.is_appreciated ? 'text-rose-300 hover:text-rose-200' : 'text-slate-300 hover:text-rose-300'"
                        :disabled="togglingAppreciation"
                        title="Appreciate"
                        @click="toggleAppreciation"
                    >
                        <i class="fi fi-rr-heart text-[12px]" />
                        <span class="text-[8px]">{{ creation.appreciations_count || 0 }}</span>
                    </button>
                    <button
                        type="button"
                        class="icon-btn inline-flex items-center gap-1 text-slate-300 hover:text-cyan-200"
                        title="Insights"
                        @click="focusInsights"
                    >
                        <i class="fi fi-rr-comment-alt text-[12px]" />
                        <span class="text-[8px]">{{ creation.insights_count || 0 }}</span>
                    </button>
                    <Link
                        v-if="Number(authUser?.id || 0) === Number(creation.user_id)"
                        :href="route('profile.creations')"
                        class="icon-btn text-amber-300 hover:text-amber-100"
                        title="Edit"
                    >
                        <i class="fi fi-rr-pencil text-[12px]" />
                    </Link>
                </div>
            </section>

            <section v-else class="rpg-panel border-rose-500/30 bg-[#161b22]/90 text-center text-[8px] uppercase text-slate-300">
                Creation detail is unavailable.
            </section>

            <section v-if="creation" ref="insightsSection" class="rpg-panel border-amber-500/30 bg-[#161b22]/90">
                <h2 class="mb-4 flex items-center gap-2 border-b border-slate-700 pb-3 text-[10px] uppercase text-amber-300">
                    <i class="fi fi-rr-comment-alt text-[12px]" />
                    Insights
                </h2>

                <form class="space-y-3 border border-slate-700 bg-black/20 p-3" @submit.prevent="submitInsight">
                    <p v-if="insightForm.parent_id" class="text-[7px] uppercase text-cyan-300">
                        Replying to insight #{{ insightForm.parent_id }}
                        <button type="button" class="ml-2 text-amber-300 underline" @click="cancelReply">cancel</button>
                    </p>
                    <textarea
                        v-model="insightForm.content"
                        class="field-input min-h-[90px] w-full"
                        placeholder="Write insight..."
                        required
                    />
                    <button type="submit" class="icon-btn text-cyan-300 hover:text-cyan-100" :disabled="postingInsight" title="Send insight">
                        <i class="fi fi-rr-paper-plane text-[12px]" />
                    </button>
                </form>

                <div v-if="loadingInsights" class="py-6 text-center text-[8px] uppercase text-slate-500">Loading insights...</div>

                <div v-else class="mt-4 space-y-3">
                    <article v-for="insight in insights" :key="insight.id" class="border border-slate-700 bg-black/25 p-3">
                        <div class="mb-2 flex items-center justify-between gap-3">
                            <p class="text-[8px] uppercase text-cyan-300">{{ insight.user?.username || insight.user?.name || 'User' }}</p>
                            <p class="text-[7px] uppercase text-slate-500">{{ toDisplayDate(insight.created_at) }}</p>
                        </div>
                        <p class="text-[8px] leading-relaxed text-slate-200">{{ insight.content }}</p>
                        <div class="mt-3">
                            <button type="button" class="icon-btn text-amber-300 hover:text-amber-100" title="Reply" @click="setReply(insight.id)">
                                <i class="fi fi-rr-share text-[10px]" />
                            </button>
                        </div>

                        <div v-if="Array.isArray(insight.replies) && insight.replies.length > 0" class="mt-3 space-y-2 border-l border-slate-600 pl-3">
                            <article v-for="reply in insight.replies" :key="reply.id" class="border border-slate-700 bg-slate-900/40 p-2">
                                <div class="mb-1 flex items-center justify-between gap-2">
                                    <p class="text-[7px] uppercase text-emerald-300">{{ reply.user?.username || reply.user?.name || 'User' }}</p>
                                    <p class="text-[7px] uppercase text-slate-500">{{ toDisplayDate(reply.created_at) }}</p>
                                </div>
                                <p class="text-[8px] leading-relaxed text-slate-200">{{ reply.content }}</p>
                            </article>
                        </div>
                    </article>

                    <div v-if="insights.length === 0" class="py-8 text-center text-[8px] uppercase text-slate-500">
                        No insights yet.
                    </div>

                    <div v-if="insightMeta.current_page < insightMeta.last_page" class="pt-2 text-center">
                        <button type="button" class="icon-btn text-cyan-300 hover:text-cyan-100" title="Load more" @click="loadMoreInsights">
                            <i class="fi fi-rr-angle-double-small-down text-[12px]" />
                        </button>
                    </div>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.rpg-panel {
    @apply relative border-4 p-6;
    box-shadow: 8px 8px 0 0 rgba(0, 0, 0, 0.45);
}

.field-input {
    @apply border-2 border-slate-700 bg-[#0d1117] p-2 text-[8px] text-cyan-300 outline-none transition-colors focus:border-cyan-500;
}

.icon-btn {
    @apply inline-flex min-h-8 items-center justify-center border border-slate-700 bg-black/25 px-2 transition-colors disabled:cursor-not-allowed disabled:opacity-50;
}

.creation-stage {
    min-height: 320px;
}

.creation-stage__frame {
    display: flex;
    min-height: 320px;
    max-height: 520px;
    width: 100%;
    align-items: center;
    justify-content: center;
    background:
        linear-gradient(180deg, rgba(6, 10, 18, 0.42), rgba(6, 10, 18, 0.76)),
        radial-gradient(circle at top, rgba(34, 211, 238, 0.08), transparent 42%);
}

.creation-stage__image {
    max-height: 520px;
    width: 100%;
    object-fit: contain;
    object-position: center;
}

.creation-thumb {
    aspect-ratio: 16 / 10;
}

.creation-thumb__image {
    height: 100%;
    width: 100%;
    object-fit: cover;
    object-position: center;
}

@media (min-width: 1024px) {
    .creation-stage {
        min-height: 420px;
    }

    .creation-stage__frame {
        min-height: 420px;
    }
}
</style>
