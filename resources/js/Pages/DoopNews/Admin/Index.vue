<script setup>
import AdminNavbar from '@/Components/AdminNavbar.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';

const props = defineProps({
    posts: Object,
    filters: Object,
    categories: Array,
    statuses: Array,
});

const filterForm = useForm({
    status: props.filters?.status || 'pending',
    category: props.filters?.category || 'all',
    search: props.filters?.search || '',
});

const createForm = useForm({
    title: '',
    category: 'announcement',
    excerpt: '',
    body: '',
    cover_image: null,
    version_label: '',
    action_label: '',
    action_url: '',
});

const rejectForms = {};
const editingUuid = ref(null);
const editForms = reactive({});

const categoryLabel = (value) => String(value || '').replaceAll('_', ' ').toUpperCase();
const statusLabel = (value) => String(value || '').replaceAll('_', ' ').toUpperCase();
const applyFilters = () => router.get(route('admin.doopnews.index'), filterForm.data(), { preserveScroll: true, preserveState: true });
const publishPost = (post) => router.patch(route('admin.doopnews.publish', post.slug), {}, { preserveScroll: true });
const deletePost = (post) => {
    if (window.confirm('Hapus DoopNews ini?')) {
        router.delete(route('admin.doopnews.destroy', post.slug), { preserveScroll: true });
    }
};
const rejectPost = (post) => {
    const reason = rejectForms[post.uuid] || '';
    router.patch(route('admin.doopnews.reject', post.slug), { rejection_reason: reason }, { preserveScroll: true });
};
const submitCreate = () => createForm.post(route('admin.doopnews.store'), {
    preserveScroll: true,
    forceFormData: true,
    onSuccess: () => createForm.reset(),
});
const formFor = (post) => {
    if (!editForms[post.uuid]) {
        editForms[post.uuid] = useForm({
            _method: 'put',
            title: post.title || '',
            category: post.category || 'announcement',
            excerpt: post.excerpt || '',
            body: post.body || '',
            cover_image: null,
            version_label: post.version_label || '',
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
const submitEdit = (post) => {
    formFor(post).post(route('admin.doopnews.update', post.slug), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            editingUuid.value = null;
        },
    });
};
const setCreateCoverImage = (event) => {
    createForm.cover_image = event.target.files?.[0] || null;
};
const setEditCoverImage = (post, event) => {
    formFor(post).cover_image = event.target.files?.[0] || null;
};
const formatDate = (value) => value ? new Date(value).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' }) : '-';
</script>

<template>
    <Head title="Admin DoopNews" />

    <div class="min-h-screen bg-[#0d1117] p-4 font-['Press_Start_2P'] text-[10px] text-cyan-200 md:p-8">
        <div class="mx-auto max-w-7xl space-y-6">
            <AdminNavbar />

            <header class="flex flex-col gap-3 border-b-4 border-cyan-900 pb-4 md:flex-row md:items-end md:justify-between">
                <div>
                    <p class="text-[8px] uppercase tracking-[0.24em] text-amber-300">Broadcast Console</p>
                    <h1 class="mt-2 text-xl uppercase text-white">DoopNews_Command</h1>
                </div>
                <Link :href="route('doopnews.index')" class="border-2 border-cyan-700 px-3 py-2 text-[8px] uppercase text-cyan-300 hover:bg-cyan-400 hover:text-black">
                    View_Public
                </Link>
            </header>

            <section class="grid gap-6 lg:grid-cols-[380px_1fr]">
                <form class="border-4 border-amber-700 bg-[#1a1c2c] p-4 shadow-[8px_8px_0_rgba(0,0,0,0.45)]" enctype="multipart/form-data" @submit.prevent="submitCreate">
                    <h2 class="mb-4 text-[12px] uppercase text-amber-300">Publish_New</h2>
                    <div class="grid gap-3">
                        <input v-model="createForm.title" class="border-2 border-slate-700 bg-black p-2 text-cyan-300 outline-none" placeholder="TITLE" />
                        <select v-model="createForm.category" class="border-2 border-slate-700 bg-black p-2 text-cyan-300 outline-none">
                            <option v-for="category in categories" :key="category" :value="category">{{ categoryLabel(category) }}</option>
                        </select>
                        <input v-model="createForm.version_label" class="border-2 border-slate-700 bg-black p-2 text-cyan-300 outline-none" placeholder="VERSION OPTIONAL" />
                        <input type="file" accept="image/*" class="border-2 border-slate-700 bg-black p-2 text-cyan-300 outline-none" @change="setCreateCoverImage" />
                        <p v-if="createForm.errors.cover_image" class="text-[8px] uppercase text-red-300">{{ createForm.errors.cover_image }}</p>
                        <textarea v-model="createForm.excerpt" rows="3" class="border-2 border-slate-700 bg-black p-2 font-sans text-[13px] text-cyan-100 outline-none" placeholder="EXCERPT" />
                        <textarea v-model="createForm.body" rows="9" class="border-2 border-slate-700 bg-black p-2 font-sans text-[13px] text-cyan-100 outline-none" placeholder="BODY" />
                        <input v-model="createForm.action_label" class="border-2 border-slate-700 bg-black p-2 text-cyan-300 outline-none" placeholder="ACTION LABEL OPTIONAL" />
                        <input v-model="createForm.action_url" class="border-2 border-slate-700 bg-black p-2 text-cyan-300 outline-none" placeholder="ACTION URL OPTIONAL" />
                        <button :disabled="createForm.processing" class="border-2 border-amber-500 bg-amber-500 px-3 py-3 text-black hover:bg-amber-300 disabled:opacity-50">
                            PUBLISH
                        </button>
                    </div>
                </form>

                <section class="space-y-4">
                    <form class="grid gap-2 border-2 border-slate-700 bg-slate-900/60 p-3 md:grid-cols-[160px_180px_1fr_auto]" @submit.prevent="applyFilters">
                        <select v-model="filterForm.status" class="border-2 border-slate-700 bg-black p-2 text-cyan-300 outline-none">
                            <option value="all">ALL_STATUS</option>
                            <option v-for="status in statuses" :key="status" :value="status">{{ statusLabel(status) }}</option>
                        </select>
                        <select v-model="filterForm.category" class="border-2 border-slate-700 bg-black p-2 text-cyan-300 outline-none">
                            <option value="all">ALL_CATEGORY</option>
                            <option v-for="category in categories" :key="category" :value="category">{{ categoryLabel(category) }}</option>
                        </select>
                        <input v-model="filterForm.search" class="border-2 border-slate-700 bg-black p-2 text-cyan-300 outline-none" placeholder="SEARCH" />
                        <button class="border-2 border-cyan-500 px-3 py-2 text-cyan-300 hover:bg-cyan-500 hover:text-black">FILTER</button>
                    </form>

                    <article v-for="post in posts.data" :key="post.uuid" class="border-l-4 border-amber-500 bg-[#1a1c2c] p-4">
                        <form v-if="editingUuid === post.uuid" class="grid gap-3" enctype="multipart/form-data" @submit.prevent="submitEdit(post)">
                            <input v-model="formFor(post).title" class="border-2 border-slate-700 bg-black p-2 text-cyan-300 outline-none" placeholder="TITLE" />
                            <select v-model="formFor(post).category" class="border-2 border-slate-700 bg-black p-2 text-cyan-300 outline-none">
                                <option v-for="category in categories" :key="category" :value="category">{{ categoryLabel(category) }}</option>
                            </select>
                            <input v-model="formFor(post).version_label" class="border-2 border-slate-700 bg-black p-2 text-cyan-300 outline-none" placeholder="VERSION OPTIONAL" />
                            <input type="file" accept="image/*" class="border-2 border-slate-700 bg-black p-2 text-cyan-300 outline-none" @change="setEditCoverImage(post, $event)" />
                            <textarea v-model="formFor(post).excerpt" rows="3" class="border-2 border-slate-700 bg-black p-2 font-sans text-[13px] text-cyan-100 outline-none" placeholder="EXCERPT" />
                            <textarea v-model="formFor(post).body" rows="9" class="border-2 border-slate-700 bg-black p-2 font-sans text-[13px] text-cyan-100 outline-none" placeholder="BODY" />
                            <input v-model="formFor(post).action_label" class="border-2 border-slate-700 bg-black p-2 text-cyan-300 outline-none" placeholder="ACTION LABEL OPTIONAL" />
                            <input v-model="formFor(post).action_url" class="border-2 border-slate-700 bg-black p-2 text-cyan-300 outline-none" placeholder="ACTION URL OPTIONAL" />
                            <div class="flex flex-wrap gap-2">
                                <button :disabled="formFor(post).processing" class="border border-amber-500 bg-amber-500 px-3 py-2 text-[8px] uppercase text-black disabled:opacity-50">Save</button>
                                <button type="button" class="border border-slate-700 px-3 py-2 text-[8px] uppercase text-slate-300 hover:bg-slate-700 hover:text-white" @click="cancelEdit">Cancel</button>
                            </div>
                        </form>

                        <div v-else class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap gap-2">
                                    <span class="border border-cyan-700 px-2 py-1 text-[7px] uppercase text-cyan-300">{{ categoryLabel(post.category) }}</span>
                                    <span class="border border-slate-700 px-2 py-1 text-[7px] uppercase text-slate-300">{{ statusLabel(post.status) }}</span>
                                    <span class="text-[7px] uppercase text-slate-500">{{ formatDate(post.created_at) }}</span>
                                </div>
                                <h3 class="mt-3 text-[12px] uppercase text-white">{{ post.title }}</h3>
                                <img v-if="post.cover_image_path" :src="`/storage/${post.cover_image_path}`" alt="" class="mt-3 aspect-[4/3] w-full max-w-sm border-2 border-slate-700 object-cover" loading="lazy" decoding="async">
                                <p class="mt-2 font-sans text-[13px] leading-relaxed text-slate-300">{{ post.excerpt || post.body?.slice(0, 180) }}</p>
                                <p class="mt-2 text-[7px] uppercase text-slate-500">Author: {{ post.author?.username || post.author?.name || '-' }}</p>
                                <textarea v-if="post.status === 'pending'" v-model="rejectForms[post.uuid]" rows="2" class="mt-3 w-full border border-slate-700 bg-black p-2 font-sans text-[12px] text-cyan-100 outline-none" placeholder="REJECTION_REASON_OPTIONAL" />
                            </div>
                            <div class="flex shrink-0 flex-wrap gap-2 md:w-[220px] md:justify-end">
                                <Link v-if="post.status === 'published'" :href="route('doopnews.show', post.slug)" class="border border-cyan-700 px-3 py-2 text-[8px] uppercase text-cyan-300 hover:bg-cyan-400 hover:text-black">View</Link>
                                <button class="border border-amber-600 px-3 py-2 text-[8px] uppercase text-amber-300 hover:bg-amber-500 hover:text-black" @click="startEdit(post)" type="button">Edit</button>
                                <button v-if="post.status !== 'published'" class="border border-emerald-700 px-3 py-2 text-[8px] uppercase text-emerald-300 hover:bg-emerald-500 hover:text-black" @click="publishPost(post)" type="button">Publish</button>
                                <button v-if="post.status === 'pending'" class="border border-red-800 px-3 py-2 text-[8px] uppercase text-red-300 hover:bg-red-600 hover:text-white" @click="rejectPost(post)" type="button">Reject</button>
                                <button class="border border-slate-700 px-3 py-2 text-[8px] uppercase text-slate-300 hover:bg-slate-700 hover:text-white" @click="deletePost(post)" type="button">Delete</button>
                            </div>
                        </div>
                    </article>

                    <div v-if="posts.data.length === 0" class="border-2 border-dashed border-slate-700 p-10 text-center text-slate-500">NO_DOOPNEWS_RECORD</div>
                </section>
            </section>
        </div>
    </div>
</template>
