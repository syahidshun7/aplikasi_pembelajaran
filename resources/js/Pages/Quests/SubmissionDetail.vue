<script setup>
import { Head, router } from '@inertiajs/vue3'; // FIXED: Ditambahkan router
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useUserTheme } from '@/Composables/useUserTheme';
import { computed, ref } from 'vue';

const props = defineProps({
    submission: Object
});
const { themeMode } = useUserTheme();

const attachmentItems = computed(() => {
    const attachments = props.submission?.attachments || [];
    if (attachments.length) {
        return attachments.map((file) => ({
            id: file.id,
            path: file.file_path,
            name: file.original_name || String(file.file_path || '').split('/').pop(),
            size: Number(file.size_bytes || 0),
        }));
    }

    if (!props.submission?.file_path) return [];
    return [{
        id: 'legacy',
        path: props.submission.file_path,
        name: String(props.submission.file_path).split('/').pop(),
        size: 0,
    }];
});
const selectedAttachmentIndex = ref(0);
const selectedAttachment = computed(() => attachmentItems.value[selectedAttachmentIndex.value] || null);
const attachmentUrl = computed(() => selectedAttachment.value ? `/storage/${selectedAttachment.value.path}` : null);

const isPdfAttachment = computed(() => {
    const path = selectedAttachment.value?.path || '';
    return /\.pdf$/i.test(path);
});
const isImageAttachment = computed(() => /\.(jpe?g|png|webp)$/i.test(selectedAttachment.value?.path || ''));
const attachmentExtension = (file) => String(file?.name || '').split('.').pop()?.toUpperCase() || 'FILE';
const formatFileSize = (bytes) => {
    const value = Number(bytes || 0);
    if (!value) return '';
    return value >= 1024 * 1024 ? `${(value / (1024 * 1024)).toFixed(1)} MB` : `${Math.ceil(value / 1024)} KB`;
};
const selectAttachment = (index) => {
    selectedAttachmentIndex.value = index;
};
const showPreviousAttachment = () => {
    selectedAttachmentIndex.value = Math.max(0, selectedAttachmentIndex.value - 1);
};
const showNextAttachment = () => {
    selectedAttachmentIndex.value = Math.min(attachmentItems.value.length - 1, selectedAttachmentIndex.value + 1);
};

const isMobilePdfPreview = computed(() => {
    if (typeof window === 'undefined') return false;
    const ua = window.navigator?.userAgent || '';
    const iPadOs = /Macintosh/i.test(ua) && (window.navigator?.maxTouchPoints || 0) > 1;
    return /Android|iPhone|iPad|iPod|Mobile|Opera Mini|IEMobile/i.test(ua) || iPadOs;
});

const canEditSubmission = computed(() => {
    return ['Pending', 'Rejected'].includes(props.submission?.status);
});

const actionLabel = computed(() => {
    return props.submission?.status === 'Rejected' ? 'RE-SUBMIT_QUEST' : 'EDIT_SUBMISSION';
});

const actionButtonClass = computed(() => {
    return props.submission?.status === 'Pending' ? 'btn-pixel-yellow' : 'btn-pixel-red';
});


</script>

<template>
    <AuthenticatedLayout>
        <Head :title="'QUEST_REPORT - ' + submission.quest.title" />

        <div
            class="submission-detail-page lobby-detail-page max-w-5xl mx-auto font-['Press_Start_2P'] text-[10px] leading-relaxed p-2 sm:p-4"
            :class="{ 'submission-detail-page--light': themeMode === 'light' }"
        >

            <div class="grid grid-cols-12 gap-6">
                <div class="col-span-12 lg:col-span-4 space-y-6">
                    <div class="rpg-panel submission-info-panel border-cyan-500/30">
                        <h2 class="text-white text-[10px] mb-4 border-b border-slate-700 pb-2">QUEST_INFO</h2>
                        <div class="space-y-4">
                            <div>
                                <p class="text-slate-500 text-[7px] mb-1">IDENTIFIER:</p>
                                <p class="text-cyan-400 break-words">{{ submission.quest.title }}</p>
                            </div>
                            <div>
                                <p class="text-slate-500 text-[7px] mb-1">DIFFICULTY:</p>
                                <p class="text-yellow-500">{{ submission.quest.difficulty }}</p>
                            </div>
                            <div>
                                <p class="text-slate-500 text-[7px] mb-1">FINAL_GRADE:</p>
                                <p class="submission-grade text-purple-400">{{ submission.grade ? submission.grade + '%' : 'NOT_GRADED' }}</p>
                            </div>
                            <div>
                                <p class="text-slate-500 text-[7px] mb-1">EXP_GAINED:</p>
                                <p class="text-cyan-300">+{{ submission.earned_exp ?? 0 }} EXP</p>
                            </div>
                            <div>
                                <p class="text-slate-500 text-[7px] mb-1">GOLD_GAINED:</p>
                                <p class="text-yellow-400">+{{ submission.earned_gold ?? 0 }} G</p>
                            </div>
                        </div>
                    </div>

                    <div class="rpg-panel submission-status-panel" :class="{
                        'border-yellow-500 bg-yellow-900/10': submission.status === 'Pending',
                        'border-green-500 bg-green-900/10': submission.status === 'Approved',
                        'border-red-500 bg-red-900/10': submission.status === 'Rejected',
                    }">
                        <h2 class="text-white text-[10px] mb-4 border-b border-slate-700 pb-2">QUEST_STATUS</h2>
                        <p class="text-center py-2 text-sm font-bold uppercase tracking-widest"
                           :class="{
                                'text-yellow-500 animate-pulse': submission.status === 'Pending',
                                'text-green-500': submission.status === 'Approved',
                                'text-red-500': submission.status === 'Rejected',
                           }">
                            {{ submission.status }}
                        </p>
                    </div>


                </div>

                <div class="col-span-12 lg:col-span-8 space-y-6">
                    <div class="rpg-panel submission-content-panel bg-black/40">
                        <div class="flex justify-between items-center mb-6 border-b border-slate-800 pb-2">
                            <h3 class="text-cyan-400 uppercase tracking-widest">>> PLAYER_SUBMISSION</h3>
                            <span class="text-slate-600 text-[7px]">{{ submission.submitted_at }}</span>
                        </div>
                        
                        <div class="submission-content-box p-4 bg-slate-900/50 border border-slate-800 font-mono text-slate-300 min-h-[100px] break-words">
                            <template v-if="submission.content && submission.content.startsWith('http')">
                                <p class="mb-2 text-slate-500 uppercase text-[6px]">External_Link_Detected:</p>
                                <a :href="submission.content" target="_blank" class="text-cyan-400 underline hover:text-white break-all">
                                    {{ submission.content }}
                                </a>
                            </template>
                            <template v-else>
                                {{ submission.content || 'NO_CONTENT_PROVIDED' }}
                            </template>
                        </div>

                        <div v-if="attachmentItems.length" class="submission-attachment-viewer mt-4 border border-slate-700 bg-slate-950/50">
                            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-700 px-3 py-3">
                                <div>
                                    <p class="text-[7px] text-cyan-300 uppercase">ATTACHMENTS_{{ attachmentItems.length }}</p>
                                    <p class="mt-1 font-sans text-[12px] text-slate-200">{{ selectedAttachment?.name }}</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button type="button" class="attachment-nav-button" :disabled="selectedAttachmentIndex === 0" @click="showPreviousAttachment">‹</button>
                                    <span class="text-[8px] text-slate-500">{{ selectedAttachmentIndex + 1 }} / {{ attachmentItems.length }}</span>
                                    <button type="button" class="attachment-nav-button" :disabled="selectedAttachmentIndex === attachmentItems.length - 1" @click="showNextAttachment">›</button>
                                </div>
                            </div>

                            <div class="flex gap-2 overflow-x-auto border-b border-slate-800 p-3">
                                <button
                                    v-for="(file, index) in attachmentItems"
                                    :key="file.id || file.path"
                                    type="button"
                                    class="attachment-file-tab shrink-0"
                                    :class="selectedAttachmentIndex === index ? 'attachment-file-tab--active' : ''"
                                    @click="selectAttachment(index)"
                                >
                                    <span class="attachment-file-tab__type">{{ attachmentExtension(file) }}</span>
                                    <span class="max-w-32 truncate">{{ file.name }}</span>
                                    <span v-if="file.size" class="text-[7px] opacity-70">{{ formatFileSize(file.size) }}</span>
                                </button>
                            </div>

                            <div class="p-3">
                                <div v-if="isPdfAttachment" class="border border-slate-700 bg-black p-2">
                                    <div v-if="isMobilePdfPreview" class="p-4 text-[8px] text-slate-300 uppercase">
                                        Preview PDF di mobile kadang diblok browser. Gunakan tombol OPEN_FILE untuk membukanya.
                                    </div>
                                    <iframe
                                        v-else
                                        :src="`${attachmentUrl}#toolbar=1&view=FitH`"
                                        class="w-full h-[65vh] min-h-[420px] bg-black"
                                        title="PDF attachment preview"
                                    />
                                </div>
                                <div v-else-if="isImageAttachment" class="flex min-h-72 items-center justify-center border border-slate-700 bg-black p-3">
                                    <img :src="attachmentUrl" :alt="selectedAttachment?.name" class="max-h-[65vh] max-w-full object-contain" />
                                </div>
                                <div v-else class="flex min-h-48 flex-col items-center justify-center border border-slate-700 bg-black/30 p-6 text-center">
                                    <span class="text-2xl text-cyan-400">{{ attachmentExtension(selectedAttachment) }}</span>
                                    <p class="mt-3 text-[8px] text-slate-400 uppercase">Preview tidak tersedia untuk tipe file ini.</p>
                                </div>
                            </div>

                            <div class="flex justify-end border-t border-slate-800 p-3">
                                <a :href="attachmentUrl" target="_blank" class="btn-pixel-blue px-4 py-2">
                                    OPEN_FILE ↗
                                </a>
                            </div>
                        </div>
                    </div>

                    <div v-if="submission.feedback" class="rpg-panel submission-feedback-panel border-indigo-500/50 bg-indigo-900/10">
                        <h3 class="submission-feedback-title text-[10px] text-indigo-400 mb-4 uppercase tracking-widest flex items-center gap-2">
                            <span class="animate-pulse">●</span> COMMAND_CENTER_FEEDBACK
                        </h3>
                        <div class="submission-feedback-box p-4 border-l-4 border-indigo-500 bg-black/20 text-[10px] italic text-indigo-200 font-sans">
                            "{{ submission.feedback }}"
                        </div>
                    </div>

                    <div class="flex justify-end items-center pt-4">
                        <button v-if="canEditSubmission" 
                                @click="router.visit(route('quests.show', submission.quest.uuid))"
                                :class="[actionButtonClass, 'px-6 py-3']">
                            {{ actionLabel }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.rpg-panel {
    @apply p-6 relative border-4 border-[#3d415f] bg-[#1a1c2c];
    box-shadow: 8px 8px 0px 0px rgba(0, 0, 0, 0.5);
}

.btn-pixel-red {
    @apply bg-red-900/20 border-2 border-red-600 text-red-500 hover:bg-red-600 hover:text-white transition-all font-bold uppercase text-[10px];
    box-shadow: 4px 4px 0px 0px #450a0a;
}

.btn-pixel-red:active {
    box-shadow: none;
    transform: translate(2px, 2px);
}

.btn-pixel-yellow {
    @apply bg-yellow-900/20 border-2 border-yellow-500 text-yellow-300 hover:bg-yellow-500 hover:text-black transition-all font-bold uppercase text-[10px];
    box-shadow: 4px 4px 0px 0px #713f12;
}

.btn-pixel-yellow:active {
    box-shadow: none;
    transform: translate(2px, 2px);
}

.btn-pixel-blue {
    @apply inline-block border-2 border-cyan-500 bg-cyan-900/30 text-[8px] text-cyan-200 transition-all hover:bg-cyan-500 hover:text-black;
    box-shadow: 3px 3px 0 #164e63;
}

.btn-pixel-blue:active {
    box-shadow: none;
    transform: translate(2px, 2px);
}

.attachment-nav-button {
    @apply h-7 w-7 border border-cyan-700 bg-cyan-950/40 text-sm text-cyan-200 transition-colors hover:bg-cyan-800 disabled:cursor-not-allowed disabled:opacity-30;
}

.attachment-file-tab {
    @apply flex items-center gap-2 border border-slate-700 bg-slate-900/70 px-2 py-2 text-left text-[8px] text-slate-400 transition-colors hover:border-cyan-700 hover:text-cyan-200;
    max-width: 11rem;
}

.attachment-file-tab--active {
    @apply border-cyan-400 bg-cyan-950/60 text-cyan-100;
    box-shadow: inset 3px 0 0 #22d3ee;
}

.attachment-file-tab__type {
    @apply border border-cyan-800 bg-cyan-950/50 px-1.5 py-1 text-[7px] font-bold text-cyan-300;
}

:global([data-app-surface='user'][data-theme='light']) .submission-detail-page .rpg-panel {
    border-color: #087f7f !important;
    background: #f7f7f7 !important;
    color: #202020 !important;
    box-shadow: 6px 6px 0 rgba(32, 32, 32, 0.16) !important;
}

:global([data-app-surface='user'][data-theme='light']) .submission-detail-page .rpg-panel h2,
:global([data-app-surface='user'][data-theme='light']) .submission-detail-page .submission-content-panel h3 {
    border-color: #aac2c2 !important;
    color: #007f7f !important;
    text-shadow: none !important;
}

:global([data-app-surface='user'][data-theme='light']) .submission-detail-page .submission-content-box {
    border-color: #b8cccc !important;
    background: #fff !important;
    color: #202020 !important;
}

:global([data-app-surface='user'][data-theme='light']) .submission-detail-page .submission-attachment-viewer {
    border-color: #9ababa !important;
    background: #ffffff !important;
}

:global([data-app-surface='user'][data-theme='light']) .submission-detail-page .submission-attachment-viewer > div {
    border-color: #c7dada !important;
}

:global([data-app-surface='user'][data-theme='light']) .attachment-file-tab {
    border-color: #b8cccc !important;
    background: #f9fdfd !important;
    color: #486161 !important;
}

:global([data-app-surface='user'][data-theme='light']) .attachment-file-tab--active {
    border-color: #008f8f !important;
    background: #dff4f4 !important;
    color: #005f5f !important;
}

:global([data-app-surface='user'][data-theme='light']) .submission-detail-page .submission-grade {
    color: #6b21a8 !important;
    font-weight: 700;
    opacity: 1 !important;
    text-shadow: none !important;
}

:global([data-app-surface='user'][data-theme='light']) .submission-detail-page .submission-feedback-panel h3 {
    color: #5b21b6 !important;
    text-shadow: none !important;
}

:global([data-app-surface='user'][data-theme='light']) .submission-detail-page .submission-feedback-box {
    border-left-color: #009999 !important;
    background: #e8f5f5 !important;
    color: #202020 !important;
    font-weight: 600;
    line-height: 1.7;
    opacity: 1 !important;
    text-shadow: none !important;
}

.submission-detail-page--light .submission-feedback-title {
    color: #5b21b6 !important;
    opacity: 1 !important;
    text-shadow: none !important;
}

.submission-detail-page--light .submission-feedback-box {
    border-left-color: #007f7f !important;
    background: #e6f3f3 !important;
    color: #202020 !important;
    font-weight: 700;
    opacity: 1 !important;
    text-shadow: none !important;
}

:global([data-app-surface='user'][data-theme='light']) .submission-detail-page .submission-status-panel {
    background: #fff !important;
}

@media (max-width: 640px) {
    .rpg-panel {
        padding: 18px;
        border-width: 2px;
        box-shadow: 4px 4px 0 rgba(0, 0, 0, 0.28);
    }
}
</style>
