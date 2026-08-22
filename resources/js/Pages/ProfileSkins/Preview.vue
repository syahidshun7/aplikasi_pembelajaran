<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';

const props = defineProps({
    skin: {
        type: Object,
        default: () => ({}),
    },
    previewPayload: {
        type: Object,
        default: () => ({}),
    },
    backUrl: {
        type: String,
        default: '',
    },
});

const previewFrame = ref(null);

const assetUrl = (path) => {
    const value = String(path || '').trim();
    if (!value) return '';
    if (value.startsWith('http://') || value.startsWith('https://') || value.startsWith('/')) return value;
    return `/storage/${value.replace(/^\/+/, '')}`;
};

const previewUrl = computed(() => assetUrl(props.skin?.project_entry_path));
const previewImageUrl = computed(() => assetUrl(props.skin?.preview_image_path || props.skin?.background_image_path));
const usesProjectPreview = computed(() => String(props.skin?.renderer_type || '') === 'project_static' && Boolean(previewUrl.value));
const fallbackBackUrl = computed(() => props.backUrl || route('shop.index'));

const previewMessage = computed(() => ({
    ...props.previewPayload,
    type: 'dooptech:profile-skin-data',
    activeSkin: props.skin,
}));

const sendPreviewPayload = () => {
    const frameWindow = previewFrame.value?.contentWindow;
    if (!frameWindow || !props.skin?.id) return;
    frameWindow.postMessage(JSON.parse(JSON.stringify(previewMessage.value)), '*');
};

const queuePreviewPayload = () => {
    nextTick(() => {
        sendPreviewPayload();
        window.setTimeout(sendPreviewPayload, 120);
        window.setTimeout(sendPreviewPayload, 420);
    });
};

watch(() => props.skin, queuePreviewPayload, { immediate: true });
</script>

<template>
    <Head :title="`${skin?.name || 'Skin'} Preview`" />

    <main class="profile-skin-preview-page min-h-screen bg-[#080a12] text-white">
        <header class="fixed left-0 right-0 top-0 z-20 border-b border-purple-500/30 bg-[#0b0d18]/92 px-4 py-3 shadow-[0_12px_30px_rgba(0,0,0,0.35)] backdrop-blur-md">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[10px] uppercase tracking-[0.25em] text-purple-300">Preview Skin</p>
                    <h1 class="mt-1 truncate text-sm font-bold uppercase tracking-widest text-white md:text-lg">{{ skin?.name || 'Profile Skin' }}</h1>
                </div>
                <Link
                    :href="fallbackBackUrl"
                    class="shrink-0 border border-cyan-500/60 bg-cyan-400/10 px-3 py-2 text-[10px] font-bold uppercase text-cyan-100 hover:bg-cyan-300 hover:text-black md:px-4"
                >
                    Kembali
                </Link>
            </div>
        </header>

        <section class="pt-[76px]">
            <iframe
                v-if="usesProjectPreview"
                ref="previewFrame"
                :src="previewUrl"
                :title="`${skin?.name || 'Profile skin'} preview`"
                class="block h-[calc(100vh-76px)] w-full border-0 bg-white"
                @load="queuePreviewPayload"
            />
            <div v-else-if="previewImageUrl" class="flex min-h-[calc(100vh-76px)] items-center justify-center bg-slate-950 p-4">
                <img
                    :src="previewImageUrl"
                    :alt="`${skin?.name || 'Profile skin'} preview`"
                    class="max-h-[calc(100vh-108px)] w-auto max-w-full object-contain"
                    loading="eager"
                    decoding="async"
                >
            </div>
            <div v-else class="flex min-h-[calc(100vh-76px)] flex-col items-center justify-center gap-4 px-6 text-center">
                <img src="/images/logo.png" :alt="skin?.name || 'Profile skin'" class="h-16 w-16 object-contain opacity-70">
                <p class="text-xs uppercase tracking-widest text-slate-400">Preview visual belum tersedia untuk skin ini.</p>
            </div>
        </section>
    </main>
</template>
