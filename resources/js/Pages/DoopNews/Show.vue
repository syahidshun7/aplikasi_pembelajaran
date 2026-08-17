<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useUserTheme } from '@/Composables/useUserTheme';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({ post: Object });

const storageUrl = (path) => {
    if (!path) return '';
    if (String(path).startsWith('/storage/') || String(path).startsWith('http://') || String(path).startsWith('https://')) {
        return path;
    }

    return `/storage/${String(path).replace(/^storage\//, '')}`;
};
const categoryLabel = (value) => String(value || 'announcement').replaceAll('_', ' ').toUpperCase();
const formatDate = (value) => value ? new Date(value).toLocaleString('id-ID', { dateStyle: 'full', timeStyle: 'short' }) : '-';
const { themeMode } = useUserTheme();
const isLightTheme = computed(() => themeMode.value === 'light');
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="post.title" />

        <article class="doopnews-show-page lobby-detail-page mx-auto max-w-4xl space-y-5 font-['Press_Start_2P'] text-[10px] text-cyan-200">
            <div class="doopnews-show-page__header border-b-4 border-cyan-900 pb-4">
                <Link :href="route('doopnews.index')" class="doopnews-show-page__back text-[8px] uppercase text-cyan-300 hover:text-white">Back_To_DoopNews</Link>
                <div class="mt-4 flex flex-wrap gap-2">
                    <span class="doopnews-show-page__category border border-[#009999]/60 bg-[#009999]/10 px-2 py-1 text-[7px] uppercase text-cyan-200">{{ categoryLabel(post.category) }}</span>
                    <span v-if="post.version_label" class="doopnews-show-page__version border border-cyan-500 bg-cyan-500/10 px-2 py-1 text-[7px] uppercase text-cyan-200">{{ post.version_label }}</span>
                </div>
                <h1 class="doopnews-show-page__title mt-4 text-lg uppercase leading-relaxed text-white md:text-2xl">{{ post.title }}</h1>
                <p class="doopnews-show-page__meta mt-3 text-[8px] uppercase text-slate-500">{{ formatDate(post.published_at) }} | {{ post.author?.username || post.author?.name || 'DoopNews' }}</p>
            </div>

            <div
                class="doopnews-show-page__panel border-2 p-5"
                :class="isLightTheme ? 'doopnews-show-page__panel--light' : 'border-slate-700 bg-[#1a1c2c]/90'"
            >
                <img
                    v-if="post.cover_image_path"
                    :src="storageUrl(post.cover_image_path)"
                    alt=""
                    class="doopnews-show-page__cover mb-5 aspect-[16/9] w-full border-2 border-slate-700 object-cover"
                    loading="lazy"
                    decoding="async"
                >
                <p v-if="post.excerpt" class="doopnews-show-page__excerpt mb-5 border-l-4 border-[#009999] bg-[#009999]/10 p-3 font-sans text-[14px] leading-relaxed text-cyan-100">{{ post.excerpt }}</p>
                <div class="doopnews-show-page__body whitespace-pre-wrap font-sans text-[15px] leading-8 text-slate-200">{{ post.body }}</div>
                <a v-if="post.action_url" :href="post.action_url" class="doopnews-show-page__action mt-6 inline-flex border-2 border-cyan-500 bg-cyan-500 px-4 py-3 text-[8px] uppercase text-black hover:bg-cyan-300">
                    {{ post.action_label || 'Buka Link' }}
                </a>
            </div>
        </article>
    </AuthenticatedLayout>
</template>

<style scoped>
.doopnews-show-page__panel {
    box-shadow: 8px 8px 0 rgba(0, 0, 0, 0.35);
}

.doopnews-show-page__panel--light {
    border-color: #087f7f !important;
    background: #f7f7f7 !important;
    color: #202020 !important;
    box-shadow: 8px 8px 0 rgba(32, 32, 32, 0.16) !important;
}

:global([data-app-surface='user'][data-theme='light']) .doopnews-show-page__header {
    border-color: #087f7f !important;
}

:global([data-app-surface='user'][data-theme='light']) .doopnews-show-page__title,
:global([data-app-surface='user'][data-theme='light']) .doopnews-show-page__body {
    color: #202020 !important;
}

:global([data-app-surface='user'][data-theme='light']) .doopnews-show-page__back {
    color: #007777 !important;
}

:global([data-app-surface='user'][data-theme='light']) .doopnews-show-page__meta {
    color: #626262 !important;
}

:global([data-app-surface='user'][data-theme='light']) .doopnews-show-page__cover {
    border-color: #d5e2e2 !important;
}

:global([data-app-surface='user'][data-theme='light']) .doopnews-show-page__category,
:global([data-app-surface='user'][data-theme='light']) .doopnews-show-page__version {
    border-color: rgba(0, 153, 153, 0.34) !important;
    background: #e3f5f5 !important;
    color: #006f6f !important;
}

:global([data-app-surface='user'][data-theme='light']) .doopnews-show-page__excerpt {
    border-left-color: #009999 !important;
    background: #e3f5f5 !important;
    color: #202020 !important;
}

:global([data-app-surface='user'][data-theme='light']) .doopnews-show-page__action {
    border-color: #006f6f !important;
    background: #009999 !important;
    color: #ffffff !important;
}
</style>
