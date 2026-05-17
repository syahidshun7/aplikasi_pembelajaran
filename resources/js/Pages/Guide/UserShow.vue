<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    guide: Object,
});

const normalizedDescription = computed(() => {
    const raw = props.guide?.description || '';
    return String(raw).replace(/\\r\\n/g, '\n').replace(/\\n/g, '\n');
});

const hasGoogleDocsEmbed = computed(() => !!props.guide?.google_docs_embed_url);
const hasAttachment = computed(() => !!props.guide?.file_path);
const hasAnyResource = computed(() => hasGoogleDocsEmbed.value || hasAttachment.value);

const googleDocsEmbedUrl = computed(() => {
    if (!hasGoogleDocsEmbed.value) return null;
    return props.guide.google_docs_embed_url;
});

const attachmentUrl = computed(() => {
    if (!hasAttachment.value) return null;
    return `/storage/${props.guide.file_path}`;
});

const primaryResourceUrl = computed(() => {
    if (hasGoogleDocsEmbed.value) return googleDocsEmbedUrl.value;
    return attachmentUrl.value;
});

const isPdfAttachment = computed(() => {
    const path = props.guide?.file_path || '';
    return /\.pdf$/i.test(path);
});

const isMobilePdfPreview = computed(() => {
    if (typeof window === 'undefined') return false;
    const ua = window.navigator?.userAgent || '';
    const iPadOs = /Macintosh/i.test(ua) && (window.navigator?.maxTouchPoints || 0) > 1;
    return /Android|iPhone|iPad|iPod|Mobile|Opera Mini|IEMobile/i.test(ua) || iPadOs;
});

const isImageAttachment = computed(() => {
    const path = props.guide?.file_path || '';
    return /\.(jpg|jpeg|png|webp|gif|avif)$/i.test(path);
});

const createdAtLabel = computed(() => {
    const raw = props.guide?.created_at;
    if (!raw) return '-';
    const date = new Date(raw);
    if (Number.isNaN(date.getTime())) return '-';
    return date.toLocaleString('id-ID');
});

const hashGroupKey = (value) => {
    const normalized = String(value || 'global');
    let hash = 0;
    for (let index = 0; index < normalized.length; index += 1) {
        hash = ((hash << 5) - hash) + normalized.charCodeAt(index);
        hash |= 0;
    }
    return Math.abs(hash);
};

const toneForGroup = (groupKey) => {
    if (!groupKey || groupKey === 'global') {
        return {
            border: '#4f46e5',
            bg: 'rgba(49, 46, 129, 0.18)',
            accent: '#a5b4fc',
        };
    }
    const hash = hashGroupKey(groupKey);
    let hue = Math.floor((hash * 137.508) % 360);
    if (hue >= 230 && hue <= 255) {
        hue = (hue + 92) % 360;
    }
    const saturation = 64 + (hash % 9);
    const borderLightness = 56 + ((hash >> 3) % 7);
    const accentLightness = 74 + ((hash >> 5) % 8);
    return {
        border: `hsl(${hue} ${saturation}% ${borderLightness}%)`,
        bg: `hsl(${hue} ${Math.max(58, saturation - 6)}% 20% / 0.18)`,
        accent: `hsl(${hue} ${Math.min(90, saturation + 10)}% ${accentLightness}%)`,
    };
};

const guideToneStyle = computed(() => {
    const toneKey = props.guide?.study_group_id ?? props.guide?.study_group?.id ?? props.guide?.study_group?.name ?? 'global';
    const tone = toneForGroup(String(toneKey));
    return {
        '--guide-tone-border': tone.border,
        '--guide-tone-bg': tone.bg,
        '--guide-tone-accent': tone.accent,
    };
});

const guideClassLabel = computed(() => {
    if (!props.guide?.study_group_id) {
        return 'Global';
    }
    const name = String(props.guide?.study_group?.name || '').trim();
    return name !== '' ? name : `#${props.guide.study_group_id}`;
});
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="`GUIDE | ${guide.title}`" />

        <div class="p-4 md:p-8 font-['Press_Start_2P'] text-[#4ed4d4] text-[10px]">
            <div class="max-w-5xl mx-auto space-y-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b-4 border-indigo-900 pb-4">
                    <h1 class="text-base sm:text-lg md:text-xl uppercase tracking-widest">Guide_Detail</h1>
                    <div class="flex items-center gap-2">
                        <Link
                            :href="route('guides.user.index')"
                            class="inline-flex items-center justify-center px-3 py-2 border-2 border-slate-700 bg-slate-900/40 text-slate-300 hover:text-white hover:border-cyan-400 uppercase text-[9px] sm:text-[10px] whitespace-nowrap"
                        >
                            [Back_to_Guides]
                        </Link>
                        <Link
                            :href="route('lobby')"
                            class="inline-flex items-center justify-center px-3 py-2 border-2 border-slate-700 bg-slate-900/40 text-slate-300 hover:text-white hover:border-cyan-400 uppercase text-[9px] sm:text-[10px] whitespace-nowrap"
                        >
                            [Back_to_Home]
                        </Link>
                    </div>
                </div>

                <div class="rpg-panel border-indigo-700/70 space-y-6" :style="guideToneStyle">
                    <div class="space-y-3">
                        <p class="text-[8px] uppercase text-slate-400">
                            Ref: {{ guide.uuid?.substring(0, 8) }} | Type:
                            <span class="guide-class-badge">
                                {{ guideClassLabel }}
                            </span>
                        </p>
                        <h2 class="text-white text-sm md:text-lg uppercase leading-relaxed">{{ guide.title }}</h2>
                        <p class="text-[8px] uppercase text-slate-500">Created_At: {{ createdAtLabel }}</p>
                    </div>

                    <div class="border-2 border-slate-800 bg-black/30 p-4 md:p-6">
                        <h3 class="text-[9px] uppercase text-indigo-300 mb-3">Description</h3>
                        <p class="font-sans text-[14px] text-slate-200 leading-7 whitespace-pre-line break-words">
                            {{ normalizedDescription || 'No description available.' }}
                        </p>
                    </div>

                    <div class="border-2 border-slate-800 bg-black/30 p-4 md:p-6 space-y-4">
                        <h3 class="text-[9px] uppercase text-indigo-300">Attachment</h3>

                        <div v-if="hasAnyResource" class="space-y-4">
                            <div v-if="hasGoogleDocsEmbed" class="border border-slate-700 p-2 bg-[#0d1117]">
                                <iframe
                                    :src="googleDocsEmbedUrl"
                                    class="w-full h-[70vh] min-h-[420px]"
                                    title="Google Docs preview"
                                    allowfullscreen
                                />
                            </div>

                            <div v-if="isImageAttachment" class="border border-slate-700 p-2 bg-[#0d1117]">
                                <img :src="attachmentUrl" :alt="guide.title" class="max-h-[420px] w-full object-contain">
                            </div>

                            <div v-else-if="isPdfAttachment" class="border border-slate-700 p-2 bg-[#0d1117]">
                                <div v-if="isMobilePdfPreview" class="border border-slate-700 bg-black p-4 text-[8px] text-slate-300 uppercase">
                                    Preview PDF di mobile kadang diblok browser. Tap Open_File untuk membuka viewer bawaan browser.
                                </div>
                                <iframe
                                    v-else
                                    :src="`${attachmentUrl}#toolbar=1&view=FitH`"
                                    class="w-full h-[70vh] min-h-[420px]"
                                    title="Guide PDF preview"
                                />
                            </div>

                            <div class="flex justify-end">
                                <a
                                    :href="primaryResourceUrl"
                                    target="_blank"
                                    class="inline-flex items-center justify-center px-3 py-2 border border-indigo-700 text-indigo-300 hover:bg-indigo-500 hover:text-white uppercase text-[8px]"
                                >
                                    {{ hasGoogleDocsEmbed ? 'Open_Google_Docs' : 'Open_File' }}
                                </a>
                            </div>
                        </div>

                        <p v-else class="text-slate-600 uppercase text-[8px]">No_File_Attached</p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.rpg-panel {
    background-color: #1a1c2c;
    border-width: 4px;
    padding: 1rem;
    box-shadow: 8px 8px 0 0 rgba(0, 0, 0, 0.5);
    border-color: color-mix(in srgb, var(--guide-tone-border) 56%, #312e81 44%);
    background-image: linear-gradient(180deg, var(--guide-tone-bg) 0%, rgba(26, 28, 44, 0.94) 100%);
}

.guide-class-badge {
    border: 1px solid color-mix(in srgb, var(--guide-tone-border) 58%, transparent 42%);
    background: color-mix(in srgb, var(--guide-tone-bg) 72%, transparent 28%);
    color: color-mix(in srgb, var(--guide-tone-accent) 90%, #f8fafc 10%);
    padding: 0.15rem 0.45rem;
}
</style>
