<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Swal from 'sweetalert2';
import AdminNavbar from '@/Components/AdminNavbar.vue';

const props = defineProps({
    creation: {
        type: Object,
        required: true,
    },
    finalReview: {
        type: Object,
        default: null,
    },
    peerReviews: {
        type: Array,
        default: () => [],
    },
    myReview: {
        type: Object,
        default: null,
    },
    publicationLogs: {
        type: Array,
        default: () => [],
    },
    rubric: {
        type: Object,
        default: null,
    },
    permissions: {
        type: Object,
        default: () => ({}),
    },
    assignmentOptions: {
        type: Object,
        default: () => ({ reviewers: [], rubrics: [] }),
    },
});

const hasRubric = computed(() => Boolean(props.rubric?.rubric?.id));
const rubricCriteria = computed(() => Array.isArray(props.rubric?.criteria) ? props.rubric.criteria : []);
const rubricLevels = computed(() => Array.isArray(props.rubric?.levels) ? props.rubric.levels : []);
const rubricMaxLevelScore = computed(() => {
    const scores = rubricLevels.value.map((level) => Number(level.score_value || 0));
    return scores.length ? Math.max(...scores) : 0;
});
const rubricMaxWeight = computed(() => rubricCriteria.value.reduce((sum, criterion) => sum + Number(criterion.weight || 0), 0));

const assignmentForm = useForm({
    is_open_for_review: Boolean(props.creation?.is_open_for_review),
    assigned_reviewer_id: props.creation?.assigned_reviewer_id || '',
    assigned_rubric_id: props.creation?.assigned_rubric_id || '',
});

const reviewForm = useForm({
    status: String(props.myReview?.status || 'approved'),
    feedback: String(props.myReview?.feedback || ''),
    selected_levels: {},
});

const selectedLevels = ref({});
const resolveInitialAggregateSelection = () => {
    const peerIds = Array.isArray(props.finalReview?.aggregate?.peer_review_ids)
        ? props.finalReview.aggregate.peer_review_ids
        : [];

    if (!peerIds.length) {
        return [];
    }

    const validPeerIds = new Set(
        (props.peerReviews || [])
            .map((item) => Number(item.id || 0))
            .filter((id) => id > 0),
    );

    return peerIds
        .map((id) => Number(id || 0))
        .filter((id) => id > 0 && validPeerIds.has(id));
};

const aggregateSelection = ref(resolveInitialAggregateSelection());

const initializeSelectedLevels = () => {
    const existing = props.myReview?.selected_levels && typeof props.myReview.selected_levels === 'object'
        ? props.myReview.selected_levels
        : {};

    const next = {};
    rubricCriteria.value.forEach((criterion) => {
        const saved = Number(existing[String(criterion.id)] || existing[criterion.id] || 0);
        next[criterion.id] = saved > 0 ? saved : 0;
    });

    selectedLevels.value = next;
    reviewForm.selected_levels = { ...next };
};

initializeSelectedLevels();

const selectedSummary = computed(() => {
    if (!hasRubric.value) {
        return { score: 0, breakdown: [] };
    }

    const maxLevel = Number(rubricMaxLevelScore.value || 0);
    const maxWeight = Number(rubricMaxWeight.value || 0);
    if (maxLevel <= 0 || maxWeight <= 0) {
        return { score: 0, breakdown: [] };
    }

    let totalRaw = 0;
    const breakdown = rubricCriteria.value.map((criterion) => {
        const levelId = Number(selectedLevels.value?.[criterion.id] || 0);
        const level = rubricLevels.value.find((item) => Number(item.id) === levelId);
        const selectedScore = Number(level?.score_value || 0);
        const weight = Number(criterion.weight || 0);
        const raw = (selectedScore / maxLevel) * weight;
        totalRaw += raw;

        return {
            criterionId: criterion.id,
            criterionName: criterion.name,
            weight,
            selectedLevelLabel: level?.label || '-',
            raw,
        };
    });

    const score = Math.max(0, Math.min(100, Math.round((totalRaw / maxWeight) * 100)));
    return { score, breakdown };
});

const missingCriteriaIds = computed(() => (
    rubricCriteria.value
        .filter((criterion) => Number(selectedLevels.value?.[criterion.id] || 0) <= 0)
        .map((criterion) => criterion.id)
));
const scoreDisplayClass = computed(() => {
    const score = Number(selectedSummary.value?.score || 0);
    if (score >= 70) return 'text-green-400';
    if (score >= 40) return 'text-yellow-400';
    return 'text-red-500';
});
const verdictStatusClass = computed(() => (
    reviewForm.status === 'approved'
        ? 'text-green-400 border-green-700 bg-green-900/20'
        : 'text-yellow-400 border-yellow-700 bg-yellow-900/20'
));

const cellDescription = (criteriaId, levelId) => {
    if (!criteriaId || !levelId) {
        return '';
    }

    return props.rubric?.matrix?.[Number(criteriaId)]?.[Number(levelId)] || '';
};

const saveAssignment = () => {
    assignmentForm.patch(route('admin.creations.assignment.update', { creation: props.creation.id }), {
        preserveScroll: true,
        onSuccess: () => {
            Swal.fire({
                icon: 'success',
                title: 'ASSIGNMENT_UPDATED',
                timer: 1300,
                showConfirmButton: false,
                background: '#1a1c2c',
                color: '#4ed4d4',
            });
        },
    });
};

const submitFinalReview = () => {
    reviewForm.selected_levels = { ...selectedLevels.value };

    if (!hasRubric.value) {
        Swal.fire('RUBRIC_REQUIRED', 'Admin harus assign rubric dulu sebelum review.', 'warning');
        return;
    }

    if (missingCriteriaIds.value.length > 0) {
        Swal.fire('RUBRIC_INCOMPLETE', 'Semua kriteria wajib dipilih levelnya.', 'warning');
        return;
    }

    Swal.fire({
        title: 'SAVE_MY_REVIEW?',
        html: `<p style="font-size:12px;">Score: <b>${selectedSummary.value.score}%</b></p>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'SUBMIT',
        cancelButtonText: 'CANCEL',
        background: '#1a1c2c',
        color: '#4ed4d4',
        confirmButtonColor: '#0891b2',
    }).then((result) => {
        if (!result.isConfirmed) {
            return;
        }

        reviewForm.post(route('admin.creations.review.submit', { creation: props.creation.id }), {
            preserveScroll: true,
            onSuccess: () => {
                Swal.fire({
                    icon: 'success',
                    title: 'FINAL_REVIEW_SAVED',
                    timer: 1300,
                    showConfirmButton: false,
                    background: '#1a1c2c',
                    color: '#4ed4d4',
                });
            },
        });
    });
};

const publishAsOfficial = (peerReview) => {
    if (!props.permissions?.can_publish_official || !peerReview?.id) {
        return;
    }

    Swal.fire({
        title: 'PUBLISH_AS_OFFICIAL?',
        html: `<p style="font-size:12px;">Reviewer: <b>${peerReview.reviewer?.username || peerReview.reviewer?.name || '-'}</b><br>Score: <b>${peerReview.score_percent}%</b></p>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'PUBLISH',
        cancelButtonText: 'CANCEL',
        background: '#1a1c2c',
        color: '#4ed4d4',
        confirmButtonColor: '#0891b2',
    }).then((result) => {
        if (!result.isConfirmed) {
            return;
        }

        router.post(route('admin.creations.review.publish', {
            creation: props.creation.id,
            peerReview: peerReview.id,
        }), {}, {
            preserveScroll: true,
            onSuccess: () => {
                Swal.fire({
                    icon: 'success',
                    title: 'OFFICIAL_REVIEW_UPDATED',
                    timer: 1300,
                    showConfirmButton: false,
                    background: '#1a1c2c',
                    color: '#4ed4d4',
                });
            },
            onError: (errors) => {
                const message = errors?.peer_review_ids || errors?.message || 'Gagal publish official review.';
                Swal.fire('PUBLISH_FAILED', message, 'error');
            },
        });
    });
};

const canPublishAggregate = computed(() => (
    Boolean(props.permissions?.can_publish_aggregate) && Array.isArray(props.peerReviews) && props.peerReviews.length >= 2
));

const selectedAggregateReviews = computed(() => (
    (props.peerReviews || []).filter((item) => aggregateSelection.value.includes(Number(item.id)))
));

const toggleAggregateSelection = (reviewId) => {
    const id = Number(reviewId || 0);
    if (id <= 0) {
        return;
    }

    if (aggregateSelection.value.includes(id)) {
        aggregateSelection.value = aggregateSelection.value.filter((item) => Number(item) !== id);
        return;
    }

    aggregateSelection.value = [...aggregateSelection.value, id];
};

const selectAllAggregateReviews = () => {
    aggregateSelection.value = (props.peerReviews || [])
        .map((item) => Number(item.id || 0))
        .filter((id) => id > 0);
};

const clearAggregateSelection = () => {
    aggregateSelection.value = [];
};

const allAggregateChecked = computed({
    get() {
        const allIds = (props.peerReviews || [])
            .map((item) => Number(item.id || 0))
            .filter((id) => id > 0);

        if (allIds.length === 0) {
            return false;
        }

        return allIds.every((id) => aggregateSelection.value.includes(id));
    },
    set(checked) {
        if (checked) {
            selectAllAggregateReviews();
            return;
        }

        clearAggregateSelection();
    },
});

const publishAggregateOfficial = () => {
    if (!canPublishAggregate.value) {
        return;
    }

    const hasManualSelection = aggregateSelection.value.length > 0;
    if (hasManualSelection && aggregateSelection.value.length < 2) {
        Swal.fire('MIN_2_REVIEW', 'Jika pakai centang manual, minimal pilih 2 review.', 'warning');
        return;
    }

    const selectedReviews = hasManualSelection
        ? selectedAggregateReviews.value
        : (props.peerReviews || []);

    const detailHtml = selectedReviews
        .map((item) => {
            const reviewer = item.reviewer?.username || item.reviewer?.name || '-';
            return `${reviewer}: <b>${item.score_percent}%</b> (${item.status})`;
        })
        .join('<br>');

    Swal.fire({
        title: hasManualSelection ? 'PUBLISH_KALKULASI_SELECTED_REVIEW?' : 'PUBLISH_KALKULASI_ALL_REVIEW?',
        html: `<p style="font-size:12px;">${detailHtml}</p>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'PUBLISH',
        cancelButtonText: 'CANCEL',
        background: '#1a1c2c',
        color: '#4ed4d4',
        confirmButtonColor: '#0891b2',
    }).then((result) => {
        if (!result.isConfirmed) {
            return;
        }

        router.post(route('admin.creations.review.publish-aggregate', {
            creation: props.creation.id,
        }), hasManualSelection ? {
            peer_review_ids: aggregateSelection.value,
        } : {}, {
            preserveScroll: true,
            onSuccess: () => {
                selectAllAggregateReviews();
                Swal.fire({
                    icon: 'success',
                    title: 'OFFICIAL_AGGREGATE_PUBLISHED',
                    timer: 1300,
                    showConfirmButton: false,
                    background: '#1a1c2c',
                    color: '#4ed4d4',
                });
            },
            onError: (errors) => {
                const message = errors?.peer_review_ids || errors?.message || 'Gagal publish kalkulasi review.';
                Swal.fire('PUBLISH_FAILED', message, 'error');
            },
        });
    });
};

const displayDate = (value) => {
    const date = new Date(String(value || ''));
    if (Number.isNaN(date.getTime())) {
        return '-';
    }

    return date.toLocaleString('id-ID');
};
</script>

<template>
    <Head title="Creation Review Preview" />

    <div class="min-h-screen bg-[#0d1117] p-4 md:p-8 font-['Press_Start_2P'] text-[#4ed4d4] text-[10px]">
        <div class="mx-auto max-w-7xl space-y-6">
            <AdminNavbar />

            <div class="flex flex-wrap items-center justify-between gap-3 border-b-4 border-slate-800 pb-4">
                <h1 class="text-white text-sm uppercase">Creation_Review_Preview</h1>
                <div class="flex items-center gap-2">
                    <Link :href="route('admin.creations.queue')" class="text-[8px] border border-slate-600 px-3 py-2 text-slate-300 hover:text-white">
                        BACK_TO_QUEUE
                    </Link>
                    <Link :href="route('hall.creations.show', { creation: creation.id })" class="text-[8px] border border-slate-600 px-3 py-2 text-slate-300 hover:text-white">
                        OPEN_PUBLIC_VIEW
                    </Link>
                </div>
            </div>

            <div class="space-y-6">
                <section class="rpg-panel border-slate-700">
                    <div class="space-y-2 border-b border-slate-700 pb-4 mb-4">
                        <p class="text-[8px] text-slate-500 uppercase">Creation</p>
                        <h2 class="text-white text-[12px] uppercase">{{ creation.title }}</h2>
                        <p class="text-[8px] text-slate-400 uppercase">
                            Creator: {{ creation.creator?.username || creation.creator?.name || '-' }}
                            <span class="ml-2 text-cyan-300">Job: {{ creation.creator?.job_id ?? '-' }}</span>
                        </p>
                        <p class="text-[8px] text-slate-500 uppercase">
                            Review status: <span class="text-cyan-300">{{ creation.review_status }}</span>
                        </p>
                    </div>

                    <div v-if="Array.isArray(creation.photos) && creation.photos.length" class="grid grid-cols-2 md:grid-cols-3 gap-2 mb-4">
                        <img
                            v-for="photo in creation.photos"
                            :key="photo.id"
                            :src="photo.url"
                            alt="Creation photo"
                            class="w-full aspect-video object-cover border border-slate-700"
                        >
                    </div>

                    <div class="bg-white text-slate-900 p-4 max-h-[60vh] overflow-auto font-serif text-sm leading-relaxed">
                        <article v-if="creation.content" v-html="creation.content" />
                        <p v-else>{{ creation.description }}</p>
                    </div>
                </section>

                <section class="space-y-6">
                    <div class="rpg-panel border-slate-700">
                        <h3 class="text-[9px] uppercase text-emerald-400 mb-4">Mentor Review Workspace</h3>

                        <div v-if="!hasRubric" class="text-[8px] text-amber-300 border border-amber-700 bg-amber-900/20 p-3 uppercase">
                            Rubric belum tersedia. Minta admin assign rubric dulu.
                        </div>

                        <div v-else-if="permissions?.can_review" class="space-y-4">
                            <div class="flex items-center justify-between gap-3 border-b border-slate-700 pb-3">
                                <h4 class="text-[9px] uppercase text-green-400 tracking-widest">>> ACADEMIC_EVALUATION</h4>
                                <p class="text-[8px] uppercase text-slate-400 italic">
                                    Rubric: <span class="text-cyan-300 font-bold">{{ rubric.rubric.title }}</span>
                                </p>
                            </div>

                            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                                <div class="lg:col-span-7 space-y-3 max-h-[560px] overflow-auto pr-1">
                                    <article
                                        v-for="(criterion, index) in rubricCriteria"
                                        :key="criterion.id"
                                        class="p-4 border-2 border-slate-800 bg-black/40 hover:border-cyan-500 transition-all"
                                    >
                                        <div class="flex flex-col md:flex-row md:items-start justify-between gap-4">
                                            <div class="min-w-0">
                                                <p class="text-[10px] uppercase font-bold text-white break-words">
                                                    {{ index + 1 }}. {{ criterion.name }}
                                                </p>
                                                <p class="text-[7px] text-slate-500 uppercase italic mt-1">
                                                    WEIGHT: <span class="text-slate-300 font-bold">{{ Number(criterion.weight || 0).toFixed(2) }}</span>
                                                </p>
                                                <p
                                                    v-if="cellDescription(criterion.id, selectedLevels[criterion.id])"
                                                    class="text-[10px] font-sans text-slate-300 mt-3 leading-6 border-l-2 border-slate-700 pl-3"
                                                >
                                                    {{ cellDescription(criterion.id, selectedLevels[criterion.id]) }}
                                                </p>
                                            </div>

                                            <div class="shrink-0 w-full md:w-56">
                                                <label class="block text-[7px] text-slate-500 uppercase italic mb-2">SELECT_LEVEL:</label>
                                                <select
                                                    v-model.number="selectedLevels[criterion.id]"
                                                    class="w-full field-input"
                                                >
                                                    <option :value="0">-- SELECT --</option>
                                                    <option v-for="level in rubricLevels" :key="level.id" :value="level.id">
                                                        {{ level.label }} ({{ Number(level.score_value || 0).toFixed(0) }})
                                                    </option>
                                                </select>
                                                <p
                                                    v-if="missingCriteriaIds.includes(criterion.id)"
                                                    class="mt-2 text-[8px] uppercase text-red-400"
                                                >
                                                    LEVEL_REQUIRED
                                                </p>
                                            </div>
                                        </div>
                                    </article>
                                </div>

                                <div class="lg:col-span-5 flex flex-col space-y-4">
                                    <div class="grid grid-cols-2 gap-3">
                                        <div class="bg-black p-4 border-2 border-slate-800 text-center">
                                            <p class="text-[7px] text-slate-600 uppercase mb-2 italic">Final_Score:</p>
                                            <p class="text-2xl font-bold" :class="scoreDisplayClass">
                                                {{ selectedSummary.score }}%
                                            </p>
                                        </div>
                                        <div
                                            class="bg-black p-4 border-2 text-center text-[10px] flex items-center justify-center font-bold uppercase tracking-tighter"
                                            :class="verdictStatusClass"
                                        >
                                            {{ reviewForm.status }}
                                        </div>
                                    </div>

                                    <div class="bg-black p-4 border-2 border-slate-800">
                                        <label class="block text-[7px] text-slate-500 uppercase italic mb-2">Verdict_Status:</label>
                                        <select v-model="reviewForm.status" class="w-full field-input">
                                            <option value="approved">APPROVED</option>
                                            <option value="needs_revision">NEEDS_REVISION</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block text-[7px] text-slate-500 uppercase italic mb-2">Reviewer_Feedback:</label>
                                        <textarea v-model="reviewForm.feedback" rows="6" class="w-full field-input font-sans text-[12px]" />
                                    </div>

                                    <button
                                        type="button"
                                        class="w-full py-4 border-2 border-emerald-500 text-emerald-300 hover:bg-emerald-500 hover:text-black uppercase font-bold tracking-widest"
                                        :disabled="reviewForm.processing || !hasRubric"
                                        @click="submitFinalReview"
                                    >
                                        {{ reviewForm.processing ? '[ SAVING... ]' : '[ SAVE_MY_REVIEW ]' }}
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-[8px] text-slate-400 border border-slate-700 bg-black/20 p-3 uppercase">
                            Mode monitoring admin. Penilaian hanya bisa dikirim oleh akun mentor.
                        </div>
                    </div>

                    <div v-if="Array.isArray(peerReviews) && peerReviews.length > 0" class="rpg-panel border-slate-700">
                        <h3 class="text-[9px] uppercase text-cyan-300 mb-3">Reviewer Notes</h3>
                        <div v-if="canPublishAggregate" class="mb-3 flex flex-wrap items-center gap-2">
                            <label class="inline-flex items-center gap-2 border border-slate-600 px-2 py-1 text-[8px] uppercase text-slate-300">
                                <input
                                    v-model="allAggregateChecked"
                                    type="checkbox"
                                    class="accent-emerald-500"
                                >
                                All
                            </label>
                            <button
                                type="button"
                                class="border border-emerald-500 px-2 py-1 text-[8px] uppercase text-emerald-300 hover:bg-emerald-500 hover:text-black"
                                @click="publishAggregateOfficial"
                            >
                                Publish Kalkulasi All Review
                            </button>
                            <p class="text-[8px] uppercase text-slate-400">
                                Selected: {{ aggregateSelection.length }} (0 = all review)
                            </p>
                        </div>
                        <div class="space-y-3">
                            <article v-for="reviewItem in peerReviews" :key="reviewItem.id" class="border border-slate-700 bg-black/30 p-3">
                                <label
                                    v-if="canPublishAggregate"
                                    class="mb-2 inline-flex items-center gap-2 text-[8px] uppercase text-slate-400"
                                >
                                    <input
                                        type="checkbox"
                                        :checked="aggregateSelection.includes(Number(reviewItem.id))"
                                        class="accent-emerald-500"
                                        @change="toggleAggregateSelection(reviewItem.id)"
                                    >
                                    Select for aggregate
                                </label>
                                <p class="text-[8px] uppercase text-white">
                                    {{ reviewItem.reviewer?.username || reviewItem.reviewer?.name || '-' }}
                                </p>
                                <p class="text-[8px] uppercase text-slate-400">
                                    {{ reviewItem.status }} | {{ reviewItem.score_percent }}% | {{ displayDate(reviewItem.reviewed_at) }}
                                </p>
                                <p class="mt-2 text-[8px] text-slate-300 font-sans leading-relaxed">{{ reviewItem.feedback || '-' }}</p>

                                <button
                                    v-if="permissions?.can_publish_official"
                                    type="button"
                                    class="mt-3 border border-cyan-600 px-2 py-1 text-[8px] uppercase text-cyan-300 hover:bg-cyan-500 hover:text-black"
                                    @click="publishAsOfficial(reviewItem)"
                                >
                                    Publish Official
                                </button>
                            </article>
                        </div>
                    </div>

                    <div v-if="finalReview" class="rpg-panel border-cyan-800/60 bg-cyan-950/20">
                        <h3 class="text-[9px] uppercase text-cyan-300 mb-3">Latest Official Result</h3>
                        <p class="text-[8px] uppercase text-slate-300">
                            Reviewer: {{ finalReview.reviewer_label || finalReview.reviewer?.username || finalReview.reviewer?.name || '-' }}
                        </p>
                        <p v-if="finalReview.is_aggregate && finalReview.aggregate?.reviewer_names?.length" class="text-[8px] uppercase text-slate-300">
                            Source reviewers: {{ finalReview.aggregate.reviewer_names.join(', ') }}
                        </p>
                        <p class="text-[8px] uppercase text-slate-300">Rubric: {{ finalReview.rubric?.title || '-' }}</p>
                        <p class="text-[8px] uppercase text-slate-300">Score: {{ finalReview.score_percent }}%</p>
                        <p class="text-[8px] uppercase text-slate-300">Status: {{ finalReview.status }}</p>
                        <p class="mt-2 text-[8px] text-slate-400 font-sans leading-relaxed">{{ finalReview.feedback || '-' }}</p>
                    </div>
                    <div v-if="permissions?.can_assign" class="rpg-panel border-slate-700">
                        <h3 class="text-[9px] uppercase text-yellow-400 mb-4">Assignment Panel</h3>

                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-3">
                            <label class="field-label">
                                Open For Mentor Review
                                <select v-model="assignmentForm.is_open_for_review" class="field-input">
                                    <option :value="true">OPEN</option>
                                    <option :value="false">CLOSE</option>
                                </select>
                            </label>

                            <label class="field-label">
                                Assigned Reviewer
                                <select v-model="assignmentForm.assigned_reviewer_id" class="field-input">
                                    <option value="">UNASSIGNED</option>
                                    <option v-for="reviewer in assignmentOptions.reviewers || []" :key="reviewer.id" :value="reviewer.id">
                                        {{ reviewer.username || reviewer.name }} (job {{ reviewer.job_id ?? '-' }})
                                    </option>
                                </select>
                            </label>

                            <label class="field-label">
                                Assigned Rubric
                                <select v-model="assignmentForm.assigned_rubric_id" class="field-input">
                                    <option value="">UNASSIGNED</option>
                                    <option v-for="rubricOption in assignmentOptions.rubrics || []" :key="rubricOption.id" :value="rubricOption.id">
                                        {{ rubricOption.title }} {{ rubricOption.is_archived ? '[ARCHIVED]' : '' }}
                                    </option>
                                </select>
                            </label>
                        </div>

                        <button
                            type="button"
                            class="mt-4 w-full border border-cyan-500 px-3 py-2 text-cyan-300 hover:bg-cyan-500 hover:text-black uppercase"
                            :disabled="assignmentForm.processing"
                            @click="saveAssignment"
                        >
                            {{ assignmentForm.processing ? 'SAVING...' : 'SAVE_ASSIGNMENT' }}
                        </button>
                    </div>

                    <div v-if="permissions?.can_assign && Array.isArray(publicationLogs) && publicationLogs.length > 0" class="rpg-panel border-slate-700">
                        <h3 class="text-[9px] uppercase text-cyan-300 mb-3">Publication History</h3>
                        <div class="space-y-3">
                            <article v-for="log in publicationLogs" :key="log.id" class="border border-slate-700 bg-black/25 p-3">
                                <p class="text-[8px] uppercase text-slate-200">
                                    Published: {{ displayDate(log.published_at) }}
                                </p>
                                <p class="text-[8px] uppercase text-slate-400">
                                    By: {{ log.publisher?.username || log.publisher?.name || '-' }}
                                </p>
                                <p class="text-[8px] uppercase text-slate-400">
                                    Reviewer: {{ log.reviewer?.username || log.reviewer?.name || (String(log.payload?.mode || '').startsWith('aggregate_') ? 'AGGREGATED_REVIEWERS' : '-') }}
                                </p>
                                <p class="text-[8px] uppercase text-slate-400">
                                    Status: {{ log.payload?.current?.status || '-' }}
                                    | Score: {{ log.payload?.current?.score_percent ?? '-' }}%
                                </p>
                            </article>
                        </div>
                    </div>
                </section>
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

.field-label {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    font-size: 8px;
    text-transform: uppercase;
    color: #94a3b8;
}

.field-input {
    width: 100%;
    background: #020617;
    border: 2px solid #334155;
    padding: 0.5rem;
    color: #67e8f9;
    outline: none;
    text-transform: uppercase;
    font-size: 10px;
}
</style>
