<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useUserTheme } from '@/Composables/useUserTheme';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    posts: Object,
    filters: Object,
    categories: Array,
});

const form = useForm({
    search: props.filters?.search || '',
    category: props.filters?.category || 'all',
});
const { themeMode } = useUserTheme();
const isLightTheme = computed(() => themeMode.value === 'light');

const applyFilters = () => {
    router.get(route('doopnews.index'), form.data(), { preserveScroll: true, preserveState: true });
};

const resetFilters = () => {
    form.search = '';
    form.category = 'all';
    applyFilters();
};

const goToPage = (url) => {
    if (!url) return;
    router.get(url, {}, { preserveState: true, preserveScroll: true });
};

const storageUrl = (path) => {
    if (!path) return '';
    if (String(path).startsWith('/storage/') || String(path).startsWith('http://') || String(path).startsWith('https://')) {
        return path;
    }

    return `/storage/${String(path).replace(/^storage\//, '')}`;
};
const authorName = (post) => post?.author?.username || post?.author?.name || 'DoopNews';
const categoryLabel = (value) => String(value || 'announcement').replaceAll('_', ' ').toUpperCase();
const formatDate = (value) => value ? new Date(value).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' }) : '-';
</script>

<template>
    <AuthenticatedLayout>
        <Head title="DoopNews" />

        <main
            class="doopnews-page lobby-detail-page p-0 font-['Press_Start_2P'] text-[10px] text-[#4ed4d4] md:p-4"
            :class="{ 'doopnews-page--light': isLightTheme }"
        >
            <div class="mx-auto max-w-7xl space-y-6">
                <header class="doopnews-page__header flex flex-col gap-3 border-b-4 border-cyan-900 pb-4 md:flex-row md:items-end md:justify-between">
                    <div>
                        <p class="doopnews-page__eyebrow text-[8px] uppercase tracking-[0.24em] text-cyan-300">Broadcast Board</p>
                        <h1 class="doopnews-page__title mt-2 text-lg uppercase text-white md:text-2xl">DoopNews</h1>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Link :href="route('doopnews.create')" class="doopnews-page__primary-action border-2 border-[#006f6f] bg-[#009999] px-3 py-2 text-[8px] uppercase text-white hover:bg-[#007f7f]">
                            Kirim Kabar
                        </Link>
                        <Link :href="route('doopnews.mine')" class="doopnews-page__secondary-action border-2 border-slate-700 bg-slate-900/60 px-3 py-2 text-[8px] uppercase text-slate-300 hover:text-white">
                            Post Saya
                        </Link>
                        <Link :href="route('lobby')" class="doopnews-page__secondary-action border-2 border-slate-700 bg-slate-900/60 px-3 py-2 text-[8px] uppercase text-slate-300 hover:text-white">
                            Back Home
                        </Link>
                    </div>
                </header>

                <div
                    class="rpg-panel doopnews-page__panel flex min-h-[540px] flex-col border-slate-700"
                    :class="{ 'doopnews-page__panel--light': isLightTheme }"
                >
                    <form class="doopnews-page__filters mb-4 flex flex-col gap-2 md:flex-row" @submit.prevent="applyFilters">
                        <input
                            v-model="form.search"
                            class="doopnews-page__input flex-1 border-2 border-slate-700 bg-black p-2 text-cyan-400 uppercase outline-none"
                            placeholder="SEARCH DOOPNEWS"
                        />
                        <select
                            v-model="form.category"
                            class="doopnews-page__input w-full border-2 border-slate-700 bg-black p-2 text-cyan-400 uppercase outline-none md:w-56"
                        >
                            <option value="all">ALL_CATEGORY</option>
                            <option v-for="category in categories" :key="category" :value="category">{{ categoryLabel(category) }}</option>
                        </select>
                        <div class="flex gap-2">
                            <button class="doopnews-page__filter-button border-2 border-cyan-400 px-4 py-2 uppercase text-cyan-400 hover:bg-cyan-400 hover:text-black" type="submit">
                                Filter
                            </button>
                            <button class="doopnews-page__reset-button border-2 border-slate-600 px-4 py-2 uppercase text-slate-300 hover:bg-slate-700 hover:text-white" type="button" @click="resetFilters">
                                Reset
                            </button>
                        </div>
                    </form>

                    <section class="doopnews-page__grid flex-1">
                        <article
                            v-for="post in posts.data"
                            :key="post.uuid"
                            class="doopnews-page__card overflow-hidden border-2 transition-colors"
                            :class="isLightTheme ? 'doopnews-page__card--light' : 'border-slate-700 bg-[#101827] hover:border-cyan-400'"
                        >
                            <Link :href="route('doopnews.show', post.slug)" class="doopnews-page__card-link flex h-full flex-col">
                                <div class="doopnews-page__card-body flex min-h-[150px] flex-col p-4">
                                    <div class="doopnews-page__author mb-3 flex min-h-[24px] items-center gap-2 text-[7px] uppercase text-slate-400">
                                        <img
                                            v-if="post.author?.profile_photo"
                                            :src="storageUrl(post.author.profile_photo)"
                                            alt=""
                                            class="doopnews-page__avatar h-6 w-6 shrink-0 border border-cyan-500 object-cover"
                                            loading="lazy"
                                            decoding="async"
                                        >
                                        <span class="min-w-0 flex-1 truncate">{{ authorName(post) }}</span>
                                        <time class="shrink-0 text-[6px]">{{ formatDate(post.published_at) }}</time>
                                    </div>

                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="doopnews-page__category border border-[#009999]/60 bg-[#009999]/10 px-2 py-1 text-[7px] uppercase text-cyan-200">
                                            {{ categoryLabel(post.category) }}
                                        </span>
                                        <span v-if="post.is_new_for_user" class="doopnews-new-badge">NEW</span>
                                        <span v-if="post.version_label" class="doopnews-page__version border border-cyan-500/60 bg-cyan-500/10 px-2 py-1 text-[7px] uppercase text-cyan-200">
                                            {{ post.version_label }}
                                        </span>
                                    </div>

                                    <h2 class="doopnews-page__card-title mt-3 line-clamp-1 break-words text-[12px] uppercase leading-relaxed text-white">{{ post.title }}</h2>
                                    <p class="doopnews-page__card-copy mt-2 line-clamp-2 font-sans text-[13px] leading-relaxed text-slate-300">
                                        {{ post.excerpt || post.body }}
                                    </p>
                                    <span class="doopnews-page__read mt-auto inline-flex pt-3 text-[8px] uppercase text-cyan-300">
                                        Baca
                                    </span>
                                </div>

                                <img
                                    v-if="post.cover_image_path"
                                    :src="storageUrl(post.cover_image_path)"
                                    alt=""
                                    class="doopnews-page__cover aspect-[16/10] w-full border-t-2 border-slate-700 object-cover"
                                    loading="lazy"
                                    decoding="async"
                                >
                            </Link>
                        </article>

                        <div v-if="posts.data.length === 0" class="doopnews-page__empty col-span-full border-2 border-dashed border-slate-700 p-10 text-center text-slate-500">
                            NO_DOOPNEWS_SIGNAL
                        </div>
                    </section>

                    <div class="doopnews-page__pagination mt-5 flex flex-wrap gap-2 border-t border-slate-800 pt-4">
                        <button
                            v-for="(link, index) in posts.links"
                            :key="index"
                            :disabled="!link.url"
                            class="doopnews-page__page-button border px-3 py-2 text-[8px] uppercase transition-all"
                            :class="[
                                link.active ? 'border-cyan-400 bg-cyan-900/20 text-cyan-300' : 'border-slate-700 text-slate-300 hover:bg-slate-700 hover:text-white',
                                !link.url ? 'cursor-not-allowed opacity-40' : '',
                            ]"
                            @click="goToPage(link.url)"
                            v-html="link.label"
                        />
                    </div>
                </div>
            </div>
        </main>
    </AuthenticatedLayout>
</template>

<style scoped>
.rpg-panel {
    background-color: #1a1c2c;
    border-width: 4px;
    padding: 1rem;
    box-shadow: 8px 8px 0 0 rgba(0, 0, 0, 0.5);
}

.doopnews-page__card {
    box-shadow: 6px 6px 0 rgba(0, 0, 0, 0.3);
}

.doopnews-page__grid {
    column-count: 1;
    column-gap: 1rem;
}

.doopnews-page__card {
    break-inside: avoid;
    margin-bottom: 1rem;
}

.doopnews-page__card-link {
    color: inherit;
}

.doopnews-page--light .doopnews-page__primary-action {
    border-color: #005f5f !important;
    background: #009999 !important;
    color: #ffffff !important;
    box-shadow: 3px 3px 0 rgba(0, 95, 95, 0.28) !important;
}

.doopnews-page--light .doopnews-page__primary-action:hover {
    background: #007777 !important;
    color: #ffffff !important;
}

.doopnews-page--light .doopnews-page__secondary-action {
    border-color: #006f6f !important;
    background: #e6fbfb !important;
    color: #004f4f !important;
    box-shadow: 3px 3px 0 rgba(0, 111, 111, 0.22) !important;
}

.doopnews-page--light .doopnews-page__secondary-action:hover {
    background: #009999 !important;
    color: #ffffff !important;
}

.doopnews-page__author span {
    color: #22c7c7;
}

.doopnews-page__author time {
    color: #64748b;
}

.doopnews-page__panel--light {
    border-color: #087f7f !important;
    background:
        linear-gradient(180deg, rgba(0, 153, 153, 0.08), transparent 26%),
        #f7f7f7 !important;
    color: #202020 !important;
    box-shadow:
        0 18px 34px rgba(32, 32, 32, 0.12),
        0 0 0 1px rgba(32, 32, 32, 0.08) !important;
}

.doopnews-page__card--light {
    border-color: #d5e2e2 !important;
    background: #ffffff !important;
    color: #202020 !important;
    box-shadow:
        inset 0 0 0 1px rgba(32, 32, 32, 0.04),
        5px 5px 0 rgba(32, 32, 32, 0.1) !important;
}

.doopnews-page__card--light:hover {
    border-color: #009999 !important;
    background: #f4fbfb !important;
    box-shadow: 5px 5px 0 rgba(0, 153, 153, 0.22) !important;
}

.doopnews-new-badge {
    @apply inline-flex min-w-[30px] items-center justify-center rounded-full border border-red-200 bg-red-500 px-1.5 py-0.5 text-[7px] font-bold leading-none text-white shadow-[0_0_10px_rgba(239,68,68,0.45)];
}

:global([data-app-surface='user'][data-theme='light']) .doopnews-page,
:global([data-theme='light']) .doopnews-page {
    color: #202020 !important;
}

:global([data-app-surface='user'][data-theme='light']) .doopnews-page__header,
:global([data-theme='light']) .doopnews-page__header {
    border-color: #087f7f !important;
}

:global([data-app-surface='user'][data-theme='light']) .doopnews-page__eyebrow,
:global([data-theme='light']) .doopnews-page__eyebrow {
    color: #009999 !important;
}

:global([data-app-surface='user'][data-theme='light']) .doopnews-page__title,
:global([data-app-surface='user'][data-theme='light']) .doopnews-page__card-title,
:global([data-theme='light']) .doopnews-page__title,
:global([data-theme='light']) .doopnews-page__card-title {
    color: #202020 !important;
}

:global([data-app-surface='user'][data-theme='light']) .doopnews-page__panel,
:global([data-theme='light']) .doopnews-page__panel {
    border-color: #087f7f !important;
    background:
        linear-gradient(180deg, rgba(0, 153, 153, 0.08), transparent 26%),
        #f7f7f7 !important;
    color: #202020 !important;
    box-shadow:
        0 18px 34px rgba(32, 32, 32, 0.12),
        0 0 0 1px rgba(32, 32, 32, 0.08) !important;
}

:global([data-app-surface='user'][data-theme='light']) .doopnews-page__card,
:global([data-theme='light']) .doopnews-page__card {
    border-color: #d5e2e2 !important;
    background: #ffffff !important;
    color: #202020 !important;
    box-shadow:
        inset 0 0 0 1px rgba(32, 32, 32, 0.04),
        5px 5px 0 rgba(32, 32, 32, 0.1) !important;
}

:global([data-app-surface='user'][data-theme='light']) .doopnews-page__card:hover,
:global([data-theme='light']) .doopnews-page__card:hover {
    border-color: #009999 !important;
    background: #f4fbfb !important;
    box-shadow: 5px 5px 0 rgba(0, 153, 153, 0.22) !important;
}

:global([data-app-surface='user'][data-theme='light']) .doopnews-page__input,
:global([data-theme='light']) .doopnews-page__input {
    border-color: #9eb8b8 !important;
    background: #ffffff !important;
    color: #202020 !important;
}

:global([data-app-surface='user'][data-theme='light']) .doopnews-page__input::placeholder,
:global([data-theme='light']) .doopnews-page__input::placeholder {
    color: #777777 !important;
}

:global([data-app-surface='user'][data-theme='light']) .doopnews-page__card-copy,
:global([data-app-surface='user'][data-theme='light']) .doopnews-page__author,
:global([data-app-surface='user'][data-theme='light']) .doopnews-page__author time,
:global([data-theme='light']) .doopnews-page__card-copy,
:global([data-theme='light']) .doopnews-page__author,
:global([data-theme='light']) .doopnews-page__author time {
    color: #626262 !important;
}

:global([data-app-surface='user'][data-theme='light']) .doopnews-page__pagination,
:global([data-theme='light']) .doopnews-page__pagination {
    border-color: rgba(32, 32, 32, 0.16) !important;
}

:global([data-app-surface='user'][data-theme='light']) .doopnews-page__author span,
:global([data-app-surface='user'][data-theme='light']) .doopnews-page__read,
:global([data-theme='light']) .doopnews-page__author span,
:global([data-theme='light']) .doopnews-page__read {
    color: #007777 !important;
}

:global([data-app-surface='user'][data-theme='light']) .doopnews-page__avatar,
:global([data-theme='light']) .doopnews-page__avatar {
    border-color: #009999 !important;
}

:global([data-app-surface='user'][data-theme='light']) .doopnews-page__cover,
:global([data-theme='light']) .doopnews-page__cover {
    border-color: #d5e2e2 !important;
}

:global([data-app-surface='user'][data-theme='light']) .doopnews-page__category,
:global([data-theme='light']) .doopnews-page__category {
    border-color: rgba(0, 153, 153, 0.34) !important;
    background: #e3f5f5 !important;
    color: #006f6f !important;
}

:global([data-app-surface='user'][data-theme='light']) .doopnews-page__version,
:global([data-theme='light']) .doopnews-page__version {
    border-color: rgba(0, 153, 153, 0.34) !important;
    background: #f4fbfb !important;
    color: #007777 !important;
}

:global([data-app-surface='user'][data-theme='light']) .doopnews-page__primary-action,
:global([data-theme='light']) .doopnews-page__primary-action {
    border-color: #006f6f !important;
    background: #009999 !important;
    color: #ffffff !important;
}

:global([data-app-surface='user'][data-theme='light']) .doopnews-page__primary-action:hover,
:global([data-theme='light']) .doopnews-page__primary-action:hover {
    background: #007f7f !important;
    color: #ffffff !important;
}

:global([data-app-surface='user'][data-theme='light']) .doopnews-page__secondary-action,
:global([data-app-surface='user'][data-theme='light']) .doopnews-page__reset-button,
:global([data-app-surface='user'][data-theme='light']) .doopnews-page__page-button,
:global([data-theme='light']) .doopnews-page__secondary-action,
:global([data-theme='light']) .doopnews-page__reset-button,
:global([data-theme='light']) .doopnews-page__page-button {
    border-color: #9eb8b8 !important;
    background: #f1f4f4 !important;
    color: #202020 !important;
}

:global([data-app-surface='user'][data-theme='light']) .doopnews-page__secondary-action,
:global([data-theme='light']) .doopnews-page__secondary-action {
    border-color: #006f6f !important;
    background: #e3f5f5 !important;
    color: #006f6f !important;
    box-shadow: 3px 3px 0 rgba(32, 32, 32, 0.18) !important;
}

:global([data-app-surface='user'][data-theme='light']) .doopnews-page__secondary-action:hover,
:global([data-theme='light']) .doopnews-page__secondary-action:hover {
    background: #009999 !important;
    color: #ffffff !important;
}

:global([data-app-surface='user'][data-theme='light']) .doopnews-page__filter-button,
:global([data-theme='light']) .doopnews-page__filter-button {
    border-color: #006f6f !important;
    background: #009999 !important;
    color: #ffffff !important;
}

:global([data-app-surface='user'][data-theme='light']) .doopnews-page__filter-button:hover,
:global([data-theme='light']) .doopnews-page__filter-button:hover {
    background: #007f7f !important;
    color: #ffffff !important;
}

:global([data-app-surface='user'][data-theme='light']) .doopnews-page__empty,
:global([data-theme='light']) .doopnews-page__empty {
    border-color: #cbd8d8 !important;
    color: #626262 !important;
}

@media (min-width: 768px) {
    .doopnews-page__grid {
        column-count: 2;
    }
}
</style>
