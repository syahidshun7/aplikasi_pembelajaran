<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useUserTheme } from '@/Composables/useUserTheme';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

const props = defineProps({
    posts: Object,
    categories: Array,
});

const editingUuid = ref(null);
const editForms = reactive({});
const { themeMode } = useUserTheme();
const isLightTheme = computed(() => themeMode.value === 'light');

const categoryLabel = (value) => String(value || '').replaceAll('_', ' ').toUpperCase();
const statusLabel = (value) => String(value || '').replaceAll('_', ' ').toUpperCase();
const formatDate = (value) => value ? new Date(value).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' }) : '-';
const storageUrl = (path) => path ? `/storage/${String(path).replace(/^storage\//, '')}` : '';

const formFor = (post) => {
    if (!editForms[post.uuid]) {
        editForms[post.uuid] = useForm({
            title: post.title || '',
            category: post.category || 'community',
            excerpt: post.excerpt || '',
            body: post.body || '',
            cover_image: null,
            action_label: post.action_label || '',
            action_url: post.action_url || '',
        });
    }

    return editForms[post.uuid];
};

const startEdit = (post) => {
    formFor(post);
    editingUuid.value = post.uuid;
};

const cancelEdit = () => {
    editingUuid.value = null;
};

const setCoverImage = (post, event) => {
    formFor(post).cover_image = event.target.files?.[0] || null;
};

const submitEdit = (post) => {
    formFor(post).post(route('doopnews.update', post.slug), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            editingUuid.value = null;
        },
    });
};

const deletePost = (post) => {
    if (window.confirm('Hapus DoopNews ini?')) {
        router.delete(route('doopnews.destroy', post.slug), { preserveScroll: true });
    }
};

const goToPage = (url) => {
    if (url) {
        router.get(url, {}, { preserveScroll: true, preserveState: true });
    }
};
</script>

<template>
    <AuthenticatedLayout>
        <Head title="DoopNews Saya" />

        <main
            class="doopnews-mine-page lobby-detail-page mx-auto max-w-5xl space-y-4 font-['Press_Start_2P'] text-[10px] text-cyan-200"
            :class="{ 'doopnews-mine-page--light': isLightTheme }"
        >
            <header class="doopnews-mine-page__header flex flex-col gap-3 border-b-4 border-cyan-900 pb-4 md:flex-row md:items-end md:justify-between">
                <div>
                    <p class="doopnews-mine-page__eyebrow text-[8px] uppercase tracking-[0.24em] text-cyan-300">Submission Desk</p>
                    <h1 class="doopnews-mine-page__title mt-2 text-lg uppercase text-white">DoopNews Saya</h1>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link :href="route('doopnews.create')" class="doopnews-mine-page__primary-action border-2 border-[#006f6f] bg-[#009999] px-3 py-2 text-[8px] uppercase text-white hover:bg-[#007f7f]">Kirim Baru</Link>
                    <Link :href="route('doopnews.index')" class="doopnews-mine-page__secondary-action border-2 border-slate-700 px-3 py-2 text-[8px] uppercase text-slate-300 hover:text-white">DoopNews</Link>
                </div>
            </header>

            <section class="space-y-3">
                <article v-for="post in posts.data" :key="post.uuid" class="doopnews-mine-card border-2 border-slate-700 bg-[#1a1c2c]/90 p-3 shadow-[4px_4px_0_rgba(0,0,0,0.28)]">
                    <form v-if="editingUuid === post.uuid" class="doopnews-mine-edit grid gap-2" enctype="multipart/form-data" @submit.prevent="submitEdit(post)">
                        <input v-model="formFor(post).title" class="doopnews-mine-input border-2 border-slate-700 bg-black p-2 text-cyan-300 outline-none" placeholder="TITLE" />
                        <select v-model="formFor(post).category" class="doopnews-mine-input border-2 border-slate-700 bg-black p-2 text-cyan-300 outline-none">
                            <option v-for="category in categories" :key="category" :value="category">{{ categoryLabel(category) }}</option>
                        </select>
                        <textarea v-model="formFor(post).excerpt" rows="2" class="doopnews-mine-input border-2 border-slate-700 bg-black p-2 font-sans text-[13px] text-cyan-100 outline-none" placeholder="RINGKASAN OPSIONAL" />
                        <textarea v-model="formFor(post).body" rows="5" class="doopnews-mine-input border-2 border-slate-700 bg-black p-2 font-sans text-[13px] text-cyan-100 outline-none" placeholder="ISI KABAR" />
                        <input type="file" accept="image/*" class="doopnews-mine-input border-2 border-slate-700 bg-black p-2 text-cyan-300 outline-none" @change="setCoverImage(post, $event)" />
                        <input v-model="formFor(post).action_label" class="doopnews-mine-input border-2 border-slate-700 bg-black p-2 text-cyan-300 outline-none" placeholder="ACTION LABEL OPSIONAL" />
                        <input v-model="formFor(post).action_url" class="doopnews-mine-input border-2 border-slate-700 bg-black p-2 text-cyan-300 outline-none" placeholder="ACTION URL OPSIONAL" />
                        <div class="flex flex-wrap gap-2">
                            <button :disabled="formFor(post).processing" class="doopnews-mine-page__primary-action border-2 border-cyan-500 bg-cyan-500 px-3 py-2 text-[8px] uppercase text-black disabled:opacity-50">Simpan</button>
                            <button type="button" class="doopnews-mine-page__secondary-action border-2 border-slate-700 px-3 py-2 text-[8px] uppercase text-slate-300" @click="cancelEdit">Batal</button>
                        </div>
                    </form>

                    <div v-else class="grid gap-3 md:grid-cols-[96px_1fr_auto] md:items-start">
                        <img v-if="post.cover_image_path" :src="storageUrl(post.cover_image_path)" alt="" class="doopnews-mine-card__thumb aspect-square w-24 border-2 border-slate-700 object-cover">
                        <div v-else class="doopnews-mine-card__thumb flex aspect-square w-24 items-center justify-center border-2 border-dashed border-slate-700 text-[7px] uppercase text-slate-500">No Cover</div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="doopnews-mine-card__category border border-cyan-700 px-2 py-1 text-[7px] uppercase text-cyan-300">{{ categoryLabel(post.category) }}</span>
                                <span class="doopnews-mine-card__status border border-slate-700 px-2 py-1 text-[7px] uppercase text-slate-300">{{ statusLabel(post.status) }}</span>
                                <span class="doopnews-mine-card__date text-[7px] uppercase text-slate-500">{{ formatDate(post.created_at) }}</span>
                            </div>
                            <h2 class="doopnews-mine-card__title mt-2 break-words text-[11px] uppercase leading-relaxed text-white">{{ post.title }}</h2>
                            <p class="doopnews-mine-card__copy mt-1 line-clamp-2 font-sans text-[12px] leading-relaxed text-slate-300">{{ post.excerpt || post.body }}</p>
                            <p v-if="post.rejection_reason" class="doopnews-mine-card__rejection mt-2 border border-red-700 bg-red-950/40 p-2 font-sans text-[12px] text-red-200">Alasan ditolak: {{ post.rejection_reason }}</p>
                        </div>
                        <div class="flex shrink-0 flex-wrap gap-2 md:w-28 md:justify-end">
                            <Link v-if="post.status === 'published'" :href="route('doopnews.show', post.slug)" class="doopnews-mine-card__view border border-cyan-700 px-3 py-2 text-[8px] uppercase text-cyan-300 hover:bg-cyan-500 hover:text-black">View</Link>
                            <button type="button" class="doopnews-mine-card__edit border border-amber-600 px-3 py-2 text-[8px] uppercase text-amber-300 hover:bg-amber-500 hover:text-black" @click="startEdit(post)">Edit</button>
                            <button type="button" class="doopnews-mine-card__delete border border-red-800 px-3 py-2 text-[8px] uppercase text-red-300 hover:bg-red-600 hover:text-white" @click="deletePost(post)">Delete</button>
                        </div>
                    </div>
                </article>

                <div v-if="posts.data.length === 0" class="doopnews-mine-empty border-2 border-dashed border-slate-700 p-10 text-center text-slate-500">BELUM_ADA_DOOPNEWS</div>
            </section>

            <div class="doopnews-mine-pagination flex flex-wrap gap-2 border-t border-slate-800 pt-4">
                <button
                    v-for="(link, index) in posts.links"
                    :key="index"
                    :disabled="!link.url"
                    class="doopnews-mine-page-button border px-3 py-2 text-[8px] uppercase"
                    :class="link.active ? 'border-cyan-400 bg-cyan-900/20 text-cyan-300' : 'border-slate-700 text-slate-300 disabled:opacity-40'"
                    @click="goToPage(link.url)"
                    v-html="link.label"
                />
            </div>
        </main>
    </AuthenticatedLayout>
</template>

<style scoped>
.doopnews-mine-page--light {
    color: #202020 !important;
}

.doopnews-mine-page--light .doopnews-mine-page__header,
.doopnews-mine-page--light .doopnews-mine-pagination {
    border-color: #087f7f !important;
}

.doopnews-mine-page--light .doopnews-mine-page__eyebrow {
    color: #007777 !important;
}

.doopnews-mine-page--light .doopnews-mine-page__title,
.doopnews-mine-page--light .doopnews-mine-card__title {
    color: #202020 !important;
}

.doopnews-mine-page--light .doopnews-mine-card {
    border-color: #8fb7b7 !important;
    background: rgba(255, 255, 255, 0.86) !important;
    color: #202020 !important;
    box-shadow: 4px 4px 0 rgba(0, 111, 111, 0.18) !important;
}

.doopnews-mine-page--light .doopnews-mine-card__thumb {
    border-color: #8fb7b7 !important;
    background: #f6fbfb !important;
    color: #5f7777 !important;
}

.doopnews-mine-page--light .doopnews-mine-card__copy,
.doopnews-mine-page--light .doopnews-mine-card__date {
    color: #5c6666 !important;
}

.doopnews-mine-page--light .doopnews-mine-card__category {
    border-color: rgba(0, 153, 153, 0.38) !important;
    background: #e3f5f5 !important;
    color: #006f6f !important;
}

.doopnews-mine-page--light .doopnews-mine-card__status {
    border-color: #9eb8b8 !important;
    background: #f1f4f4 !important;
    color: #334444 !important;
}

.doopnews-mine-page--light .doopnews-mine-page__primary-action,
.doopnews-mine-page--light .doopnews-mine-card__view {
    border-color: #006f6f !important;
    background: #009999 !important;
    color: #ffffff !important;
    box-shadow: 3px 3px 0 rgba(0, 111, 111, 0.22) !important;
}

.doopnews-mine-page--light .doopnews-mine-page__primary-action:hover,
.doopnews-mine-page--light .doopnews-mine-card__view:hover {
    background: #007777 !important;
    color: #ffffff !important;
}

.doopnews-mine-page--light .doopnews-mine-page__secondary-action,
.doopnews-mine-page--light .doopnews-mine-card__edit,
.doopnews-mine-page--light .doopnews-mine-page-button {
    border-color: #006f6f !important;
    background: #e6fbfb !important;
    color: #004f4f !important;
}

.doopnews-mine-page--light .doopnews-mine-page__secondary-action:hover,
.doopnews-mine-page--light .doopnews-mine-card__edit:hover,
.doopnews-mine-page--light .doopnews-mine-page-button:hover:not(:disabled) {
    background: #009999 !important;
    color: #ffffff !important;
}

.doopnews-mine-page--light .doopnews-mine-card__delete {
    border-color: #c92d3f !important;
    background: #fff1f2 !important;
    color: #9f1239 !important;
}

.doopnews-mine-page--light .doopnews-mine-card__delete:hover {
    background: #e11d48 !important;
    color: #ffffff !important;
}

.doopnews-mine-page--light .doopnews-mine-input {
    border-color: #9eb8b8 !important;
    background: #ffffff !important;
    color: #202020 !important;
}

.doopnews-mine-page--light .doopnews-mine-input::placeholder {
    color: #777777 !important;
}

.doopnews-mine-page--light .doopnews-mine-empty {
    border-color: #9eb8b8 !important;
    color: #5c6666 !important;
}
</style>
