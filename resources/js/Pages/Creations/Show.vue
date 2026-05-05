<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { toast } from '@/Utils/Alert';

const props = defineProps({
    creationId: {
        type: Number,
        required: true,
    },
});

const page = usePage();
const HALL_RETURN_URL_STORAGE_KEY = 'hall.creations.return_to';
const EMPTY_CREATION_PARAGRAPH_PATTERN = '<p>(?:\\s|&nbsp;|<br\\s*\\/?>)*<\\/p>';
const authUser = computed(() => page.props?.auth?.user || null);
const creation = ref(null);
const loadingCreation = ref(false);
const togglingAppreciation = ref(false);
const collaborationSubmitting = ref(false);
const collaborationActionId = ref(0);
const removingCollaboratorId = ref(0);

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
const collaborationForm = reactive({
    requested_role: 'editor',
    message: '',
});
const insightsSection = ref(null);
const activePhotoUrl = ref('');
const lightboxImageUrl = ref('');
const lightboxImageAlt = ref('Preview image');
const relativeRoute = (name, params = {}) => route(name, params, false);
const backToHallHref = computed(() => {
    const fallbackHref = route('hall.creations.index');

    if (typeof window === 'undefined') {
        return fallbackHref;
    }

    const savedHref = String(window.sessionStorage.getItem(HALL_RETURN_URL_STORAGE_KEY) || '').trim();
    const hallIndexPath = relativeRoute('hall.creations.index');

    return savedHref.startsWith(hallIndexPath) ? savedHref : fallbackHref;
});

const redirectToLogin = () => {
    if (typeof window !== 'undefined') {
        const currentPath = `${window.location.pathname}${window.location.search || ''}`;
        window.sessionStorage.setItem(HALL_RETURN_URL_STORAGE_KEY, currentPath);
    }

    router.visit(route('login'));
};

const ensureAuthenticatedOrRedirect = () => {
    if (authUser.value) {
        return true;
    }

    redirectToLogin();
    return false;
};

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

const teamMembers = computed(() => Array.isArray(creation.value?.team) ? creation.value.team : []);
const pendingCollaborationRequests = computed(() => Array.isArray(creation.value?.pending_collaboration_requests) ? creation.value.pending_collaboration_requests : []);
const creationTags = computed(() => Array.isArray(creation.value?.tags) ? creation.value.tags : []);
const renderedCreationContent = computed(() => {
    const rawContent = String(creation.value?.content || '').trim();
    if (rawContent === '') {
        return '';
    }

    const leadingEmptyParagraphs = new RegExp(`^(?:${EMPTY_CREATION_PARAGRAPH_PATTERN}\\s*)+`, 'i');
    const trailingEmptyParagraphs = new RegExp(`(?:\\s*${EMPTY_CREATION_PARAGRAPH_PATTERN})+$`, 'i');
    const emptyParagraphs = new RegExp(EMPTY_CREATION_PARAGRAPH_PATTERN, 'gi');
    const repeatedSpacers = /(?:<p class="creation-doc__spacer"><br><\/p>\s*){2,}/gi;

    return rawContent
        .replace(leadingEmptyParagraphs, '')
        .replace(trailingEmptyParagraphs, '')
        .replace(emptyParagraphs, '<p class="creation-doc__spacer"><br></p>')
        .replace(repeatedSpacers, '<p class="creation-doc__spacer"><br></p>')
        .trim();
});
const canRequestCollaboration = computed(() => {
    if (!creation.value) {
        return false;
    }

    if (!creation.value.is_open_for_collaboration) {
        return false;
    }

    if (creation.value.can_manage_collaboration || creation.value.can_edit) {
        return false;
    }

    return !['pending', 'approved'].includes(String(creation.value.viewer_collaboration_request_status || ''));
});
const hasPendingCollaborationRequest = computed(() => String(creation.value?.viewer_collaboration_request_status || '') === 'pending');
const collaborationRoleLabel = (role) => String(role || 'editor').replaceAll('_', ' ').toUpperCase();

const fetchCreation = async () => {
    loadingCreation.value = true;
    try {
        const response = await window.axios.get(relativeRoute('api.hall.show', { creation: props.creationId }));
        creation.value = response.data?.data || null;
        activePhotoUrl.value = String(
            creation.value?.photos?.[0]?.url
            || creation.value?.featured_image
            || creation.value?.thumbnail_url
            || ''
        );
        return Boolean(creation.value);
    } catch (error) {
        console.error('creation detail load failed', {
            status: error?.response?.status,
            message: error?.response?.data?.message,
            url: error?.config?.url,
            method: error?.config?.method,
        });
        creation.value = null;
        activePhotoUrl.value = '';
        toast.error('LOAD_FAILED', 'Unable to load creation detail.');
        return false;
    } finally {
        loadingCreation.value = false;
    }
};

const refreshCreation = async () => {
    await fetchCreation();
};

const fetchInsights = async (pageNum = 1, append = false) => {
    loadingInsights.value = true;
    try {
        const response = await window.axios.get(relativeRoute('api.creations.insights.index', { creation: props.creationId }), {
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
        console.error('creation insights load failed', {
            status: error?.response?.status,
            message: error?.response?.data?.message,
            url: error?.config?.url,
            method: error?.config?.method,
        });
        toast.error('LOAD_FAILED', 'Unable to load insights.');
    } finally {
        loadingInsights.value = false;
    }
};

const toggleAppreciation = async () => {
    if (!creation.value || togglingAppreciation.value) {
        return;
    }

    if (!ensureAuthenticatedOrRedirect()) {
        return;
    }

    togglingAppreciation.value = true;

    try {
        if (creation.value.is_appreciated) {
            const response = await window.axios.delete(relativeRoute('api.creations.appreciate.destroy', { creation: creation.value.id }));
            creation.value.is_appreciated = false;
            creation.value.appreciations_count = Number(response.data?.appreciations_count || 0);
        } else {
            const response = await window.axios.post(relativeRoute('api.creations.appreciate.store', { creation: creation.value.id }));
            creation.value.is_appreciated = true;
            creation.value.appreciations_count = Number(response.data?.appreciations_count || 0);
        }
    } catch (error) {
        if (Number(error?.response?.status || 0) === 401) {
            redirectToLogin();
            return;
        }

        console.error('creation detail appreciation failed', {
            status: error?.response?.status,
            message: error?.response?.data?.message,
            url: error?.config?.url,
            method: error?.config?.method,
        });
        toast.error('ACTION_FAILED', getAppreciationErrorMessage(error));
    } finally {
        togglingAppreciation.value = false;
    }
};

const submitInsight = async () => {
    if (!ensureAuthenticatedOrRedirect()) {
        return;
    }

    const content = String(insightForm.content || '').trim();
    if (!content) {
        toast.error('EMPTY_INSIGHT', 'Insight content cannot be empty.');
        return;
    }

    postingInsight.value = true;
    try {
        await window.axios.post(relativeRoute('api.creations.insights.store', { creation: props.creationId }), {
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
        if (Number(error?.response?.status || 0) === 401) {
            redirectToLogin();
            return;
        }

        const firstError = Object.values(error?.response?.data?.errors || {})?.[0]?.[0] || 'Failed to send insight.';
        toast.error('POST_FAILED', String(firstError));
    } finally {
        postingInsight.value = false;
    }
};

const setReply = (insightId) => {
    if (!ensureAuthenticatedOrRedirect()) {
        return;
    }

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

const openLightbox = (url, alt = 'Preview image') => {
    const normalizedUrl = String(url || '').trim();
    if (!normalizedUrl) {
        return;
    }

    lightboxImageUrl.value = normalizedUrl;
    lightboxImageAlt.value = String(alt || 'Preview image');
    if (typeof document !== 'undefined') {
        document.body.style.overflow = 'hidden';
    }
};

const closeLightbox = () => {
    lightboxImageUrl.value = '';
    lightboxImageAlt.value = 'Preview image';
    if (typeof document !== 'undefined') {
        document.body.style.overflow = '';
    }
};

const handleDocImageClick = (event) => {
    const target = event?.target;
    if (!(target instanceof HTMLImageElement)) {
        return;
    }

    const imageUrl = String(target.getAttribute('src') || '').trim();
    if (!imageUrl) {
        return;
    }

    event.preventDefault();
    openLightbox(imageUrl, target.getAttribute('alt') || 'Creation image');
};

const handleLightboxEscape = (event) => {
    if (String(event?.key || '').toLowerCase() === 'escape' && lightboxImageUrl.value) {
        closeLightbox();
    }
};

const submitCollaborationRequest = async () => {
    if (!canRequestCollaboration.value || collaborationSubmitting.value) {
        return;
    }

    if (!ensureAuthenticatedOrRedirect()) {
        return;
    }

    collaborationSubmitting.value = true;

    try {
        await window.axios.post(relativeRoute('api.creations.collaboration-requests.store', { creation: props.creationId }), {
            requested_role: collaborationForm.requested_role,
            message: String(collaborationForm.message || '').trim() || null,
        });

        collaborationForm.message = '';
        await refreshCreation();
        toast.success('REQUEST_SENT', 'Collaboration request sent.');
    } catch (error) {
        if (Number(error?.response?.status || 0) === 401) {
            redirectToLogin();
            return;
        }

        toast.error('REQUEST_FAILED', String(error?.response?.data?.message || 'Unable to send collaboration request.'));
    } finally {
        collaborationSubmitting.value = false;
    }
};

const withdrawCollaborationRequest = async () => {
    const requestId = Number(creation.value?.viewer_collaboration_request_id || 0);
    if (!requestId || collaborationSubmitting.value) {
        return;
    }

    if (!ensureAuthenticatedOrRedirect()) {
        return;
    }

    collaborationSubmitting.value = true;

    try {
        await window.axios.delete(relativeRoute('api.creations.collaboration-requests.withdraw', {
            creation: props.creationId,
            collaborationRequest: requestId,
        }));
        await refreshCreation();
        toast.success('REQUEST_UPDATED', 'Collaboration request withdrawn.');
    } catch (error) {
        if (Number(error?.response?.status || 0) === 401) {
            redirectToLogin();
            return;
        }

        toast.error('REQUEST_FAILED', String(error?.response?.data?.message || 'Unable to withdraw collaboration request.'));
    } finally {
        collaborationSubmitting.value = false;
    }
};

const approveRequest = async (requestItem) => {
    const requestId = Number(requestItem?.id || 0);
    if (!requestId || collaborationActionId.value === requestId) {
        return;
    }

    if (!ensureAuthenticatedOrRedirect()) {
        return;
    }

    collaborationActionId.value = requestId;

    try {
        await window.axios.post(relativeRoute('api.creations.collaboration-requests.approve', {
            creation: props.creationId,
            collaborationRequest: requestId,
        }));
        await refreshCreation();
        toast.success('REQUEST_APPROVED', 'Collaboration request approved.');
    } catch (error) {
        if (Number(error?.response?.status || 0) === 401) {
            redirectToLogin();
            return;
        }

        toast.error('REQUEST_FAILED', String(error?.response?.data?.message || 'Unable to approve request.'));
    } finally {
        collaborationActionId.value = 0;
    }
};

const rejectRequest = async (requestItem) => {
    const requestId = Number(requestItem?.id || 0);
    if (!requestId || collaborationActionId.value === requestId) {
        return;
    }

    if (!ensureAuthenticatedOrRedirect()) {
        return;
    }

    collaborationActionId.value = requestId;

    try {
        await window.axios.post(relativeRoute('api.creations.collaboration-requests.reject', {
            creation: props.creationId,
            collaborationRequest: requestId,
        }));
        await refreshCreation();
        toast.success('REQUEST_REJECTED', 'Collaboration request rejected.');
    } catch (error) {
        if (Number(error?.response?.status || 0) === 401) {
            redirectToLogin();
            return;
        }

        toast.error('REQUEST_FAILED', String(error?.response?.data?.message || 'Unable to reject request.'));
    } finally {
        collaborationActionId.value = 0;
    }
};

const removeCollaborator = async (member) => {
    const userId = Number(member?.id || 0);
    if (!userId || removingCollaboratorId.value === userId || member?.is_owner) {
        return;
    }

    if (!ensureAuthenticatedOrRedirect()) {
        return;
    }

    removingCollaboratorId.value = userId;

    try {
        await window.axios.delete(relativeRoute('api.creations.collaborators.destroy', {
            creation: props.creationId,
            user: userId,
        }));
        await refreshCreation();
        toast.success('TEAM_UPDATED', 'Collaborator removed from team.');
    } catch (error) {
        if (Number(error?.response?.status || 0) === 401) {
            redirectToLogin();
            return;
        }

        toast.error('ACTION_FAILED', String(error?.response?.data?.message || 'Unable to remove collaborator.'));
    } finally {
        removingCollaboratorId.value = 0;
    }
};

onMounted(async () => {
    const loaded = await fetchCreation();

    if (loaded) {
        await fetchInsights();
    }

    if (typeof window !== 'undefined') {
        window.addEventListener('keydown', handleLightboxEscape);
    }
});

onBeforeUnmount(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('keydown', handleLightboxEscape);
    }
    closeLightbox();
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
                    <Link :href="backToHallHref" class="text-[8px] uppercase text-amber-300 hover:text-amber-200">
                        Back To Hall
                    </Link>
                </div>

                <div class="creation-stage relative mb-5 overflow-hidden border border-slate-700 bg-gradient-to-br from-[#10202a] via-[#121722] to-[#0d1117]">
                    <div v-if="activePhotoUrl" class="creation-stage__frame">
                        <img
                            :src="activePhotoUrl"
                            alt="Creation photo"
                            class="creation-stage__image"
                            @click="openLightbox(activePhotoUrl, 'Creation photo')"
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
                    <span v-if="creation.team_size > 1" class="rounded border border-emerald-500/50 px-2 py-1 text-emerald-300">TEAM {{ creation.team_size }}</span>
                    <span v-if="creation.is_open_for_collaboration" class="rounded border border-amber-500/50 px-2 py-1 text-amber-300">OPEN COLLAB</span>
                </div>

                <div v-if="creationTags.length > 0" class="mb-5 flex flex-wrap gap-2 text-[7px] uppercase">
                    <span v-for="tag in creationTags" :key="tag" class="rounded border border-slate-600 bg-slate-900/60 px-2 py-1 text-slate-300">
                        {{ tag }}
                    </span>
                </div>

                <div class="creation-reading-surface mb-5" @click="handleDocImageClick">
                    <article v-if="renderedCreationContent" class="creation-doc" v-html="renderedCreationContent" />
                    <p v-else class="creation-doc creation-doc--plain whitespace-pre-line text-[8px] leading-relaxed text-slate-700">{{ creation.description }}</p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <a
                        v-if="creation.link"
                        :href="creation.link"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="creation-link-btn"
                        title="Lihat karya"
                    >
                        <i class="fi fi-rr-link text-[12px]" />
                        <span>Lihat Karya</span>
                    </a>
                    <Link
                        :href="route('hall.creations.review', { creation: creation.id })"
                        class="creation-link-btn"
                        title="Lihat hasil review"
                    >
                        <i class="fi fi-rr-eye text-[12px]" />
                        <span>Hasil Review</span>
                    </Link>
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
                        v-if="creation.can_edit"
                        :href="route('profile.creations.edit', { creation: creation.id })"
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

            <section v-if="creation" class="rpg-panel border-cyan-500/30 bg-[#161b22]/90">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b border-slate-700 pb-3">
                    <div>
                        <h2 class="text-[10px] uppercase text-cyan-200">Team Formation</h2>
                        <p class="mt-2 text-[7px] uppercase text-slate-500">Owner, collaborators, and pending requests live here.</p>
                    </div>
                    <span class="text-[7px] uppercase text-slate-500">Team Size {{ creation.team_size || 1 }}</span>
                </div>

                <div class="grid gap-4 lg:grid-cols-[1.2fr,0.8fr]">
                    <div class="space-y-3">
                        <article
                            v-for="member in teamMembers"
                            :key="`${member.id}-${member.role}`"
                            class="flex items-center justify-between gap-3 border border-slate-700 bg-black/20 px-3 py-3"
                        >
                            <div class="min-w-0">
                                <p class="text-[8px] uppercase text-white">{{ member.username || member.name || 'User' }}</p>
                                <p class="mt-1 text-[7px] uppercase text-slate-500">{{ collaborationRoleLabel(member.role) }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span v-if="member.is_owner" class="rounded border border-cyan-500/40 px-2 py-1 text-[7px] uppercase text-cyan-300">Owner</span>
                                <button
                                    v-else-if="creation.can_manage_collaboration"
                                    type="button"
                                    class="icon-btn text-rose-300 hover:text-rose-100"
                                    :disabled="removingCollaboratorId === Number(member.id)"
                                    @click="removeCollaborator(member)"
                                >
                                    <i class="fi fi-rr-cross-small text-[12px]" />
                                </button>
                            </div>
                        </article>

                        <div v-if="creation.can_manage_collaboration && pendingCollaborationRequests.length > 0" class="space-y-3 border-t border-slate-700 pt-4">
                            <h3 class="text-[8px] uppercase text-amber-300">Pending Requests</h3>
                            <article
                                v-for="requestItem in pendingCollaborationRequests"
                                :key="requestItem.id"
                                class="border border-amber-500/30 bg-amber-500/5 p-3"
                            >
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-[8px] uppercase text-white">{{ requestItem.requester?.username || requestItem.requester?.name || 'User' }}</p>
                                        <p class="mt-1 text-[7px] uppercase text-amber-200">{{ collaborationRoleLabel(requestItem.requested_role) }}</p>
                                        <p v-if="requestItem.message" class="mt-2 text-[8px] leading-relaxed text-slate-300">{{ requestItem.message }}</p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button type="button" class="icon-btn text-emerald-300 hover:text-emerald-100" :disabled="collaborationActionId === Number(requestItem.id)" @click="approveRequest(requestItem)">
                                            <i class="fi fi-rr-check text-[12px]" />
                                        </button>
                                        <button type="button" class="icon-btn text-rose-300 hover:text-rose-100" :disabled="collaborationActionId === Number(requestItem.id)" @click="rejectRequest(requestItem)">
                                            <i class="fi fi-rr-cross text-[12px]" />
                                        </button>
                                    </div>
                                </div>
                            </article>
                        </div>
                    </div>

                    <div class="space-y-3 border border-slate-700 bg-black/20 p-3">
                        <h3 class="text-[8px] uppercase text-cyan-300">Collaboration Access</h3>
                        <p class="text-[8px] leading-relaxed text-slate-300">
                            <span v-if="creation.can_manage_collaboration">Kamu adalah owner. Review request masuk dan susun tim creation dari panel ini.</span>
                            <span v-else-if="creation.can_edit">Kamu adalah bagian dari tim. Gunakan halaman My Creations untuk mengerjakan project bersama.</span>
                            <span v-else-if="hasPendingCollaborationRequest">Permintaan kolaborasimu sedang menunggu review owner.</span>
                            <span v-else-if="creation.is_open_for_collaboration">Creation ini terbuka untuk kolaborasi. Kirim request jika ingin ikut mengerjakan.</span>
                            <span v-else>Owner belum membuka slot kolaborasi untuk creation ini.</span>
                        </p>

                        <form v-if="canRequestCollaboration" class="space-y-3" @submit.prevent="submitCollaborationRequest">
                            <label class="field-label">
                                Preferred Role
                                <select v-model="collaborationForm.requested_role" class="field-input">
                                    <option value="editor">Editor</option>
                                    <option value="contributor">Contributor</option>
                                    <option value="viewer">Viewer</option>
                                </select>
                            </label>
                            <label class="field-label">
                                Message
                                <textarea v-model="collaborationForm.message" class="field-input min-h-[90px]" placeholder="Tell the owner how you want to help..." />
                            </label>
                            <button type="submit" class="icon-btn text-cyan-300 hover:text-cyan-100" :disabled="collaborationSubmitting">
                                <i class="fi fi-rr-paper-plane text-[12px]" />
                            </button>
                        </form>

                        <div v-else-if="hasPendingCollaborationRequest" class="space-y-3">
                            <div class="border border-amber-500/40 bg-amber-500/10 p-3 text-[7px] uppercase text-amber-200">
                                Request status: pending
                            </div>
                            <button type="button" class="icon-btn text-rose-300 hover:text-rose-100" :disabled="collaborationSubmitting" @click="withdrawCollaborationRequest">
                                <i class="fi fi-rr-cross-small text-[12px]" />
                            </button>
                        </div>
                    </div>
                </div>
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

        <div
            v-if="lightboxImageUrl"
            class="creation-lightbox"
            role="dialog"
            aria-modal="true"
            aria-label="Image preview"
            @click.self="closeLightbox"
        >
            <button type="button" class="creation-lightbox__close" @click="closeLightbox">
                <i class="fi fi-rr-cross-small text-[14px]" />
            </button>
            <img :src="lightboxImageUrl" :alt="lightboxImageAlt" class="creation-lightbox__image">
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

.field-label {
    @apply flex flex-col gap-2 text-[8px] uppercase text-slate-400;
}

.icon-btn {
    @apply inline-flex min-h-8 items-center justify-center border border-slate-700 bg-black/25 px-2 transition-colors disabled:cursor-not-allowed disabled:opacity-50;
}

.creation-link-btn {
    @apply inline-flex min-h-8 items-center justify-center gap-2 border border-cyan-500/60 bg-cyan-500/10 px-3 text-[8px] uppercase text-cyan-200 transition-colors;
}

.creation-link-btn:hover {
    @apply border-cyan-300 text-cyan-100 bg-cyan-500/20;
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
    cursor: zoom-in;
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

.creation-doc {
    color: #0f172a;
    font-family: Georgia, "Times New Roman", serif;
    font-size: 16px;
    line-height: 1.78;
}

.creation-doc--plain {
    margin: 0;
}

:deep(.creation-doc > :first-child) {
    margin-top: 0;
}

:deep(.creation-doc > :last-child) {
    margin-bottom: 0;
}

.creation-reading-surface {
    max-height: min(62vh, 760px);
    overflow-y: auto;
    overflow-x: hidden;
    border: 1px solid rgba(203, 213, 225, 0.95);
    background: #ffffff;
    padding: 1.1rem 1.15rem;
    scrollbar-width: thin;
    scrollbar-color: rgba(148, 163, 184, 0.95) rgba(241, 245, 249, 0.9);
}

:deep(.creation-doc h2),
:deep(.creation-doc h3) {
    margin: 1.3rem 0 0.8rem;
    color: #0f172a;
    line-height: 1.35;
}

:deep(.creation-doc h2) {
    font-size: 1.55rem;
}

:deep(.creation-doc h3) {
    font-size: 1.2rem;
}

:deep(.creation-doc p),
:deep(.creation-doc ul),
:deep(.creation-doc ol),
:deep(.creation-doc blockquote),
:deep(.creation-doc pre) {
    margin-bottom: 1rem;
}

:deep(.creation-doc p:empty) {
    display: none;
}

:deep(.creation-doc p.creation-doc__spacer) {
    min-height: 0.95rem;
    margin: 0.45rem 0 0.9rem;
}

:deep(.creation-doc ul),
:deep(.creation-doc ol) {
    padding-left: 1.5rem;
}

:deep(.creation-doc ul) {
    list-style: disc;
}

:deep(.creation-doc ol) {
    list-style: decimal;
}

:deep(.creation-doc blockquote) {
    border-left: 3px solid rgba(37, 99, 235, 0.62);
    padding: 0.7rem 0.95rem;
    color: #334155;
    background: rgba(248, 250, 252, 0.92);
}

:deep(.creation-doc a) {
    color: #1d4ed8;
    text-decoration: underline;
}

:deep(.creation-doc img) {
    display: block;
    max-width: 100%;
    height: auto;
    margin: 1rem 0;
    border: 1px solid rgba(203, 213, 225, 1);
    cursor: zoom-in;
    /* Prevent legacy img align/pasted inline styles from floating text around images */
    float: none !important;
    clear: both;
    position: static !important;
}

:deep(.creation-doc img[align="left"]),
:deep(.creation-doc img[data-align="left"]),
:deep(.creation-doc img.creation-image--left) {
    margin-left: 0;
    margin-right: auto;
}

:deep(.creation-doc img[align="center"]),
:deep(.creation-doc img[data-align="center"]),
:deep(.creation-doc img.creation-image--center) {
    margin-left: auto;
    margin-right: auto;
}

:deep(.creation-doc img[align="right"]),
:deep(.creation-doc img[data-align="right"]),
:deep(.creation-doc img.creation-image--right) {
    margin-left: auto;
    margin-right: 0;
}

:deep(.creation-doc .tableWrapper) {
    margin: 1.2rem 0;
    overflow-x: auto;
}

:deep(.creation-doc table),
:deep(.creation-doc table.doc-table) {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
    border: 1px solid rgba(100, 116, 139, 0.85);
}

:deep(.creation-doc table th),
:deep(.creation-doc table td),
:deep(.creation-doc table.doc-table th),
:deep(.creation-doc table.doc-table td) {
    border: 1px solid rgba(100, 116, 139, 0.85);
    padding: 0.45rem 0.55rem;
    vertical-align: top;
}

:deep(.creation-doc table th),
:deep(.creation-doc table.doc-table th) {
    background: rgba(241, 245, 249, 0.96);
    color: #0f172a;
    font-weight: 700;
}

:deep(.creation-doc pre) {
    margin: 1rem 0;
    padding: 0.8rem;
    border: 1px solid rgba(203, 213, 225, 1);
    background: rgba(248, 250, 252, 0.92);
    overflow-x: auto;
}

.creation-lightbox {
    position: fixed;
    inset: 0;
    z-index: 80;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    background: rgba(2, 6, 23, 0.88);
    backdrop-filter: blur(2px);
}

.creation-lightbox__close {
    position: absolute;
    top: 0.75rem;
    right: 0.75rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.1rem;
    height: 2.1rem;
    border: 1px solid rgba(148, 163, 184, 0.75);
    background: rgba(15, 23, 42, 0.9);
    color: #e2e8f0;
}

.creation-lightbox__close:hover {
    border-color: rgba(56, 189, 248, 0.8);
    color: #67e8f9;
}

.creation-lightbox__image {
    max-height: calc(100vh - 3rem);
    max-width: calc(100vw - 2rem);
    object-fit: contain;
    border: 1px solid rgba(148, 163, 184, 0.65);
    background: #fff;
}

@media (max-width: 768px) {
    .creation-lightbox {
        padding: 0.7rem;
    }

    .creation-lightbox__close {
        top: 0.55rem;
        right: 0.55rem;
        width: 2rem;
        height: 2rem;
    }

    .creation-lightbox__image {
        max-height: calc(100vh - 2.1rem);
        max-width: calc(100vw - 1.1rem);
    }
}

@media (max-width: 768px) {
    .creation-reading-surface {
        max-height: min(56vh, 560px);
        overflow-y: auto;
        padding: 0.9rem;
    }

    .creation-doc {
        font-size: 15px;
        line-height: 1.72;
    }

    :deep(.creation-doc h2) {
        font-size: 1.25rem;
    }

    :deep(.creation-doc h3) {
        font-size: 1.08rem;
    }

    :deep(.creation-doc .tableWrapper) {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    :deep(.creation-doc table),
    :deep(.creation-doc table.doc-table) {
        width: max-content;
        min-width: 500px;
        table-layout: auto;
    }
}

@media (min-width: 1024px) {
    .creation-stage {
        min-height: 420px;
    }

    .creation-stage__frame {
        min-height: 420px;
    }

    .creation-reading-surface {
        max-height: min(68vh, 860px);
        padding: 1.35rem 1.5rem;
    }
}
</style>
