<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { toast } from '@/Utils/Alert';

const props = defineProps({
    creationId: {
        type: Number,
        required: true,
    },
});

const creation = ref(null);
const loading = ref(true);

const officialReview = computed(() => creation.value?.official_review || null);
const officialStatusClass = computed(() => {
    const normalized = String(officialReview.value?.status || '').toLowerCase();
    if (normalized === 'approved') {
        return 'border-emerald-500/60 bg-emerald-500/10 text-emerald-300';
    }
    if (normalized === 'needs_revision') {
        return 'border-amber-500/60 bg-amber-500/10 text-amber-300';
    }
    return 'border-slate-600 text-slate-300';
});

const toDisplayDate = (value) => {
    if (!value) {
        return '-';
    }

    const date = new Date(String(value));
    if (Number.isNaN(date.getTime())) {
        return '-';
    }

    return date.toLocaleString('id-ID');
};

const fetchCreation = async () => {
    loading.value = true;
    try {
        const response = await window.axios.get(route('api.hall.show', { creation: props.creationId }, false));
        creation.value = response.data?.data || null;
    } catch (_error) {
        toast.error('LOAD_FAILED', 'Unable to load review result.');
        creation.value = null;
    } finally {
        loading.value = false;
    }
};

onMounted(async () => {
    await fetchCreation();
});
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Hasil Review Creation" />

        <div class="mx-auto max-w-4xl space-y-6 px-2 sm:px-0 font-['Press_Start_2P'] text-[#4ed4d4]">
            <section class="rpg-panel border-slate-700 bg-[#1a1c2c]/90">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-slate-700 pb-3 mb-4">
                    <h1 class="text-[10px] sm:text-[11px] uppercase text-white leading-relaxed">Hasil Review Creation</h1>
                    <div class="grid w-full grid-cols-2 gap-2 sm:w-auto sm:flex sm:items-center">
                        <Link :href="route('hall.creations.show', { creation: creationId })" class="creation-link-btn">
                            <i class="fi fi-rr-angle-left text-[11px]" />
                            <span>Kembali ke Detail</span>
                        </Link>
                        <Link :href="route('hall.creations.index')" class="creation-link-btn">
                            <i class="fi fi-rr-apps text-[11px]" />
                            <span>Ke Hall</span>
                        </Link>
                    </div>
                </div>

                <div v-if="loading" class="text-[8px] uppercase text-slate-400">Loading review result...</div>

                <div v-else-if="creation && officialReview" class="space-y-4">
                    <h2 class="text-[9px] sm:text-[10px] uppercase leading-relaxed text-cyan-200 break-words [overflow-wrap:anywhere]">{{ creation.title }}</h2>

                    <div class="flex flex-wrap items-center gap-2 text-[8px] uppercase">
                        <span class="rounded border border-cyan-500/60 px-2 py-1 text-cyan-300">
                            Score {{ officialReview.score_percent }}%
                        </span>
                        <span class="rounded border px-2 py-1" :class="officialStatusClass">
                            {{ officialReview.status }}
                        </span>
                        <span v-if="officialReview.rubric?.title" class="rounded border border-slate-600 px-2 py-1 text-slate-300 break-words [overflow-wrap:anywhere]">
                            Rubric {{ officialReview.rubric.title }}
                        </span>
                    </div>

                    <p class="text-[8px] uppercase text-slate-400">
                        Reviewer: {{ officialReview.reviewer_label || officialReview.reviewer?.username || officialReview.reviewer?.name || '-' }}
                    </p>
                    <p class="text-[8px] uppercase text-slate-500">
                        Reviewed At: {{ toDisplayDate(officialReview.reviewed_at) }}
                    </p>

                    <div class="border border-slate-700 bg-black/25 p-3">
                        <p class="text-[8px] uppercase text-slate-400 mb-2">Feedback</p>
                        <p class="text-[8px] leading-relaxed text-slate-200 whitespace-pre-line break-words [overflow-wrap:anywhere]">
                            {{ officialReview.feedback || '-' }}
                        </p>
                    </div>
                </div>

                <div v-else-if="creation" class="text-[8px] uppercase text-slate-500">
                    Belum ada hasil review resmi untuk creation ini.
                </div>

                <div v-else class="text-[8px] uppercase text-slate-500">
                    Data review tidak ditemukan.
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

.creation-link-btn {
    @apply inline-flex w-full min-h-9 items-center justify-center gap-2 border border-cyan-500/60 bg-cyan-500/10 px-2 sm:px-3 text-[8px] uppercase text-cyan-200 transition-colors;
}

.creation-link-btn span {
    @apply leading-tight text-center;
}

.creation-link-btn:hover {
    @apply border-cyan-300 text-cyan-100 bg-cyan-500/20;
}

@media (max-width: 640px) {
    .rpg-panel {
        padding: 0.85rem;
        box-shadow: 4px 4px 0 0 rgba(0, 0, 0, 0.45);
    }
}
</style>
