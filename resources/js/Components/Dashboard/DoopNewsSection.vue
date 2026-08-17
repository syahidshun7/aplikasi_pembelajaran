<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useUserTheme } from '@/Composables/useUserTheme';

const props = defineProps({
    items: {
        type: Array,
        default: () => [],
    },
    authUser: {
        type: Boolean,
        default: false,
    },
    newCount: {
        type: Number,
        default: 0,
    },
});

const categoryOrder = ['all', 'announcement', 'event', 'shop_item', 'class', 'quest', 'app_update', 'community'];
const categoryNames = {
    all: 'All News',
    announcement: 'Announcement',
    event: 'Event',
    shop_item: 'Shop Item',
    class: 'Class',
    quest: 'Quest',
    app_update: 'App Update',
    community: 'Community',
};
const activeCategory = ref('all');
const { themeMode } = useUserTheme();
const isLightTheme = computed(() => themeMode.value === 'light');

const visibleNewItemCount = computed(() => {
    return (props.items || []).filter((post) => Boolean(post?.is_new_for_user)).length;
});

const hiddenNewItemCount = computed(() => {
    return Math.max(0, Number(props.newCount || 0) - visibleNewItemCount.value);
});

const visibleCategories = computed(() => {
    const usedCategories = new Set((props.items || []).map((post) => post?.category).filter(Boolean));

    return categoryOrder.filter((category) => category === 'all' || usedCategories.has(category));
});

const visiblePosts = computed(() => {
    const posts = props.items || [];

    if (activeCategory.value === 'all') {
        return posts.slice(0, 6);
    }

    return posts.filter((post) => post?.category === activeCategory.value).slice(0, 6);
});

const formatDate = (value) => {
    if (!value) {
        return 'Tanggal belum diatur';
    }

    return new Date(value).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
};

const categoryLabel = (value) => categoryNames[value] || String(value || 'news').replaceAll('_', ' ');
const storageUrl = (path) => {
    if (!path) return '';
    if (String(path).startsWith('/storage/') || String(path).startsWith('http://') || String(path).startsWith('https://')) {
        return path;
    }

    return `/storage/${String(path).replace(/^storage\//, '')}`;
};
const postCoverStyle = (post) => ({
    backgroundImage: `url("${storageUrl(post?.cover_image_path)}")`,
});
const authorName = (post) => post?.author?.username || post?.author?.name || 'DoopNews';
</script>

<template>
    <section class="doopnews-blog-section" :class="{ 'doopnews-blog-section--light': isLightTheme }">
        <div class="doopnews-blog-heading">
            <p class="doopnews-blog-heading__kicker">DoopTech Signal</p>
            <h2>DoopNews Feed</h2>
            <span></span>
            <p>Latest broadcasts from the network.</p>
        </div>

        <div class="doopnews-blog-tabs" aria-label="DoopNews categories">
            <button
                v-for="category in visibleCategories"
                :key="category"
                type="button"
                class="doopnews-blog-tab"
                :class="{ 'doopnews-blog-tab--active': category === activeCategory }"
                @click="activeCategory = category"
            >
                {{ categoryLabel(category) }}
            </button>
        </div>

        <div v-if="visiblePosts.length > 0" class="doopnews-blog-grid">
            <article
                v-for="post in visiblePosts"
                :key="post.slug"
                class="doopnews-blog-card"
            >
                <Link :href="route('doopnews.show', post.slug)" class="doopnews-blog-card__link">
                    <div class="doopnews-blog-card__body">
                        <div class="doopnews-blog-card__author">
                            <img
                                v-if="post.author?.profile_photo"
                                :src="storageUrl(post.author.profile_photo)"
                                alt=""
                                class="doopnews-blog-card__avatar"
                                loading="lazy"
                                decoding="async"
                            >
                            <span>{{ authorName(post) }}</span>
                            <time>{{ formatDate(post.published_at) }}</time>
                        </div>
                        <div class="doopnews-blog-card__topline">
                            <span class="doopnews-blog-card__category">{{ categoryLabel(post.category) }}</span>
                            <span v-if="post.version_label">{{ post.version_label }}</span>
                            <span v-if="post.is_new_for_user" class="doopnews-blog-card__new">NEW</span>
                        </div>
                        <h3>{{ post.title }}</h3>
                        <p>{{ post.excerpt || post.body || 'Incoming broadcast from DoopNews command.' }}</p>
                        <span class="doopnews-blog-card__read">Read More</span>
                    </div>

                    <div v-if="post.cover_image_path" class="doopnews-blog-card__cover" :style="postCoverStyle(post)">
                        <span>POST_IMAGE</span>
                    </div>
                </Link>
            </article>
        </div>

        <div v-else class="doopnews-blog-empty">
            <h3>No Broadcasts</h3>
            <p>DoopNews board is quiet right now.</p>
        </div>

        <div class="doopnews-blog-footer">
            <Link
                :href="authUser ? route('doopnews.index') : route('login')"
                class="doopnews-blog-all"
            >
                See All News
                <span v-if="hiddenNewItemCount > 0">{{ hiddenNewItemCount }} NEW</span>
            </Link>
        </div>
    </section>
</template>

<style scoped>
.doopnews-blog-section {
    @apply border-2 border-[#3d415f] bg-[#1a1c2c] px-4 py-6 shadow-[8px_8px_0_rgba(0,0,0,0.34)] sm:px-6 lg:px-8;
}

.doopnews-blog-heading {
    @apply mb-6 border-b-2 border-cyan-900/70 pb-4 text-left;
}

.doopnews-blog-heading__kicker {
    @apply mb-2 text-[7px] uppercase tracking-[0.24em] text-amber-300;
}

.doopnews-blog-heading h2 {
    @apply text-[15px] uppercase leading-tight text-white sm:text-[18px];
}

.doopnews-blog-heading span {
    @apply mt-3 block h-[3px] w-24 bg-[#009999];
}

.doopnews-blog-heading p {
    @apply mt-4 text-[7px] uppercase leading-relaxed tracking-[0.12em] text-slate-300;
}

.doopnews-blog-tabs {
    @apply mb-8 flex flex-wrap items-center justify-center gap-3;
}

.doopnews-blog-tab {
    @apply cursor-pointer border border-transparent px-3 py-1.5 text-[7px] uppercase text-slate-300 transition-colors hover:border-[#009999] hover:text-[#22c7c7] focus:outline-none focus:ring-2 focus:ring-[#009999]/40;
}

.doopnews-blog-tab--active {
    @apply border-[#009999] bg-[#009999] text-white;
}

.doopnews-blog-grid {
    @apply columns-1 gap-4 md:columns-2 xl:columns-3;
}

.doopnews-blog-card {
    @apply mb-4 break-inside-avoid overflow-hidden border-2 border-slate-700 bg-[#0d1117] shadow-[6px_6px_0_rgba(0,0,0,0.3)] transition-transform duration-200 hover:-translate-y-1 hover:border-[#009999];
}

.doopnews-blog-card__link {
    @apply flex h-full flex-col;
}

.doopnews-blog-card__cover {
    @apply relative mt-auto aspect-[16/10] border-t-2 border-slate-700 bg-cover bg-center;
    image-rendering: auto;
}

.doopnews-blog-card__cover::after {
    content: '';
    position: absolute;
    inset: 0;
    border: 1px solid rgba(255, 255, 255, 0.18);
    pointer-events: none;
}

.doopnews-blog-card__cover span {
    @apply absolute bottom-3 left-3 border border-cyan-400/70 bg-black/65 px-2 py-1 text-[6px] uppercase tracking-[0.12em] text-cyan-200;
}

.doopnews-blog-card__category {
    @apply border border-[#009999] bg-[#009999] px-2 py-1 text-[7px] uppercase text-white;
}

.doopnews-blog-card__new {
    @apply border border-red-200 bg-red-500 px-2 py-1 text-[7px] font-bold text-white shadow-[0_0_10px_rgba(239,68,68,0.45)];
}

.doopnews-blog-card__body {
    @apply flex min-h-[140px] flex-col px-4 pb-3 pt-4;
}

.doopnews-blog-card__author {
    @apply mb-3 flex min-h-[24px] items-center gap-2 text-[7px] uppercase text-slate-400;
}

.doopnews-blog-card__author span {
    @apply min-w-0 flex-1 truncate text-[#22c7c7];
}

.doopnews-blog-card__author time {
    @apply shrink-0 text-[6px] text-slate-500;
}

.doopnews-blog-card__avatar {
    @apply h-6 w-6 shrink-0 border border-cyan-500 object-cover;
}

.doopnews-blog-card__topline {
    @apply flex min-h-[22px] flex-wrap items-start justify-between gap-2 text-[7px] uppercase text-[#22c7c7];
}

.doopnews-blog-card__body h3 {
    @apply mt-2 line-clamp-1 break-words text-[11px] uppercase leading-relaxed text-white;
}

.doopnews-blog-card__body p {
    @apply mt-2 line-clamp-2 font-sans text-[12px] leading-relaxed text-slate-300;
}

.doopnews-blog-card__read {
    @apply mt-auto inline-flex pt-3 text-[8px] uppercase text-[#22c7c7];
}

.doopnews-blog-empty {
    @apply border-2 border-dashed border-slate-700 p-10 text-center;
}

.doopnews-blog-empty h3 {
    @apply text-[12px] uppercase text-white;
}

.doopnews-blog-empty p {
    @apply mt-3 font-sans text-[13px] text-slate-400;
}

.doopnews-blog-footer {
    @apply mt-10 flex justify-center;
}

.doopnews-blog-all {
    @apply inline-flex min-h-[42px] items-center justify-center gap-2 border-2 border-[#006f6f] bg-[#009999] px-8 text-[8px] uppercase text-white shadow-[6px_6px_0_rgba(0,0,0,0.22)] transition-colors hover:bg-[#007f7f];
}

.doopnews-blog-all span {
    @apply rounded-full bg-white/20 px-2 py-1 text-[10px];
}

.doopnews-blog-section--light {
    border-color: #087f7f !important;
    background:
        linear-gradient(180deg, rgba(0, 153, 153, 0.08), transparent 26%),
        #f7f7f7 !important;
    color: #202020 !important;
    box-shadow:
        0 18px 34px rgba(32, 32, 32, 0.12),
        0 0 0 1px rgba(32, 32, 32, 0.08) !important;
}

.doopnews-blog-section--light .doopnews-blog-heading {
    border-color: rgba(0, 153, 153, 0.36) !important;
}

.doopnews-blog-section--light .doopnews-blog-heading__kicker {
    color: #b77900 !important;
}

.doopnews-blog-section--light .doopnews-blog-heading h2,
.doopnews-blog-section--light .doopnews-blog-card__body h3,
.doopnews-blog-section--light .doopnews-blog-empty h3 {
    color: #202020 !important;
}

.doopnews-blog-section--light .doopnews-blog-heading p,
.doopnews-blog-section--light .doopnews-blog-card__body p,
.doopnews-blog-section--light .doopnews-blog-card__author,
.doopnews-blog-section--light .doopnews-blog-card__author time,
.doopnews-blog-section--light .doopnews-blog-empty p {
    color: #626262 !important;
}

.doopnews-blog-section--light .doopnews-blog-tab {
    color: #202020 !important;
}

.doopnews-blog-section--light .doopnews-blog-tab:hover {
    color: #007777 !important;
}

.doopnews-blog-section--light .doopnews-blog-tab--active {
    color: #ffffff !important;
}

.doopnews-blog-section--light .doopnews-blog-card {
    border-color: #d5e2e2 !important;
    background: #fbfefe !important;
    box-shadow:
        inset 0 0 0 1px rgba(32, 32, 32, 0.04),
        0 10px 22px rgba(32, 32, 32, 0.08) !important;
}

.doopnews-blog-section--light .doopnews-blog-card:hover {
    border-color: #009999 !important;
    background: #f4fbfb !important;
    box-shadow: 0 12px 24px rgba(0, 153, 153, 0.14) !important;
}

.doopnews-blog-section--light .doopnews-blog-card__topline,
.doopnews-blog-section--light .doopnews-blog-card__read,
.doopnews-blog-section--light .doopnews-blog-card__author span {
    color: #007777 !important;
}

.doopnews-blog-section--light .doopnews-blog-card__avatar {
    border-color: #009999 !important;
}

.doopnews-blog-section--light .doopnews-blog-card__cover {
    border-color: #d5e2e2 !important;
}

.doopnews-blog-section--light .doopnews-blog-card__cover span {
    border-color: rgba(0, 153, 153, 0.42) !important;
    background: rgba(255, 255, 255, 0.78) !important;
    color: #007777 !important;
}

:global([data-theme='light'] .doopnews-blog-section) {
    border-color: #087f7f !important;
    background:
        linear-gradient(180deg, rgba(0, 153, 153, 0.08), transparent 26%),
        #f7f7f7 !important;
    color: #202020 !important;
    box-shadow:
        0 18px 34px rgba(32, 32, 32, 0.12),
        0 0 0 1px rgba(32, 32, 32, 0.08) !important;
}

:global([data-theme='light'] .doopnews-blog-heading) {
    border-color: rgba(0, 153, 153, 0.36) !important;
}

:global([data-theme='light'] .doopnews-blog-heading__kicker) {
    color: #b77900 !important;
}

:global([data-theme='light'] .doopnews-blog-heading h2),
:global([data-theme='light'] .doopnews-blog-card__body h3),
:global([data-theme='light'] .doopnews-blog-empty h3) {
    color: #202020 !important;
}

:global([data-theme='light'] .doopnews-blog-heading p),
:global([data-theme='light'] .doopnews-blog-card__body p),
:global([data-theme='light'] .doopnews-blog-card__author),
:global([data-theme='light'] .doopnews-blog-card__author time),
:global([data-theme='light'] .doopnews-blog-empty p) {
    color: #626262 !important;
}

:global([data-theme='light'] .doopnews-blog-tab) {
    color: #202020 !important;
}

:global([data-theme='light'] .doopnews-blog-tab--active) {
    color: #ffffff !important;
}

:global([data-theme='light'] .doopnews-blog-card) {
    border-color: #d5e2e2 !important;
    background: #fbfefe !important;
    box-shadow:
        inset 0 0 0 1px rgba(32, 32, 32, 0.04),
        0 10px 22px rgba(32, 32, 32, 0.08) !important;
}

:global([data-theme='light'] .doopnews-blog-card:hover) {
    border-color: #009999 !important;
    background: #f4fbfb !important;
    box-shadow: 0 12px 24px rgba(0, 153, 153, 0.14) !important;
}

:global([data-theme='light'] .doopnews-blog-card__topline),
:global([data-theme='light'] .doopnews-blog-card__read),
:global([data-theme='light'] .doopnews-blog-card__author span) {
    color: #007777 !important;
}

:global([data-theme='light'] .doopnews-blog-card__avatar) {
    border-color: #009999 !important;
}

:global([data-theme='light'] .doopnews-blog-card__cover) {
    border-color: #d5e2e2 !important;
}

:global([data-theme='light'] .doopnews-blog-card__cover span) {
    border-color: rgba(0, 153, 153, 0.42) !important;
    background: rgba(255, 255, 255, 0.78) !important;
    color: #007777 !important;
}
</style>
