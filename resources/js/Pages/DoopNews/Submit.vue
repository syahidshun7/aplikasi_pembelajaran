<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useUserTheme } from '@/Composables/useUserTheme';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({ categories: Array });

const form = useForm({
    title: '',
    category: 'community',
    excerpt: '',
    body: '',
    cover_image: null,
    action_label: '',
    action_url: '',
});

const submit = () => form.post(route('doopnews.store'), { preserveScroll: true, forceFormData: true });
const setCoverImage = (event) => {
    form.cover_image = event.target.files?.[0] || null;
};
const categoryLabel = (value) => String(value || '').replaceAll('_', ' ').toUpperCase();
const { themeMode } = useUserTheme();
const isLightTheme = computed(() => themeMode.value === 'light');
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Kirim DoopNews" />

        <main class="doopnews-submit-page lobby-detail-page mx-auto max-w-4xl space-y-5 font-['Press_Start_2P'] text-[10px] text-cyan-200">
            <header class="doopnews-submit-page__header flex items-center justify-between gap-3 border-b-4 border-cyan-900 pb-4">
                <h1 class="doopnews-submit-page__title text-lg uppercase text-white">Kirim Kabar</h1>
                <Link :href="route('doopnews.index')" class="doopnews-submit-page__back border-2 border-slate-700 px-3 py-2 text-[8px] uppercase text-slate-300 hover:text-white">Back</Link>
            </header>

            <form
                class="doopnews-submit-page__form grid gap-4 border-2 p-5"
                :class="isLightTheme ? 'doopnews-submit-page__form--light' : 'border-slate-700 bg-[#1a1c2c]/90'"
                enctype="multipart/form-data"
                @submit.prevent="submit"
            >
                <input v-model="form.title" class="doopnews-submit-page__input border-2 border-slate-700 bg-black p-3 text-cyan-300 outline-none" placeholder="TITLE" />
                <select v-model="form.category" class="doopnews-submit-page__input border-2 border-slate-700 bg-black p-3 text-cyan-300 outline-none">
                    <option v-for="category in categories" :key="category" :value="category">{{ categoryLabel(category) }}</option>
                </select>
                <input type="file" accept="image/*" class="doopnews-submit-page__input doopnews-submit-page__file border-2 border-slate-700 bg-black p-3 text-cyan-300 outline-none" @change="setCoverImage" />
                <p v-if="form.errors.cover_image" class="text-[8px] uppercase text-red-300">{{ form.errors.cover_image }}</p>
                <textarea v-model="form.excerpt" rows="3" class="doopnews-submit-page__input border-2 border-slate-700 bg-black p-3 font-sans text-[14px] text-cyan-100 outline-none" placeholder="RINGKASAN OPSIONAL" />
                <textarea v-model="form.body" rows="10" class="doopnews-submit-page__input border-2 border-slate-700 bg-black p-3 font-sans text-[14px] text-cyan-100 outline-none" placeholder="TULIS KABAR" />
                <input v-model="form.action_label" class="doopnews-submit-page__input border-2 border-slate-700 bg-black p-3 text-cyan-300 outline-none" placeholder="ACTION LABEL OPSIONAL" />
                <input v-model="form.action_url" class="doopnews-submit-page__input border-2 border-slate-700 bg-black p-3 text-cyan-300 outline-none" placeholder="ACTION URL OPSIONAL" />
                <button :disabled="form.processing" class="doopnews-submit-page__submit border-2 border-[#006f6f] bg-[#009999] px-4 py-3 text-[9px] uppercase text-white hover:bg-[#007f7f] disabled:opacity-50">
                    Submit_To_DoopNews
                </button>
            </form>
        </main>
    </AuthenticatedLayout>
</template>

<style scoped>
.doopnews-submit-page__form {
    box-shadow: 8px 8px 0 rgba(0, 0, 0, 0.35);
}

.doopnews-submit-page__form--light {
    border-color: #087f7f !important;
    background: #f7f7f7 !important;
    color: #202020 !important;
    box-shadow: 8px 8px 0 rgba(32, 32, 32, 0.16) !important;
}

:global([data-app-surface='user'][data-theme='light']) .doopnews-submit-page__header {
    border-color: #087f7f !important;
}

:global([data-app-surface='user'][data-theme='light']) .doopnews-submit-page__title {
    color: #202020 !important;
}

:global([data-app-surface='user'][data-theme='light']) .doopnews-submit-page__back {
    border-color: #9eb8b8 !important;
    background: #f1f4f4 !important;
    color: #202020 !important;
}

:global([data-app-surface='user'][data-theme='light']) .doopnews-submit-page__input {
    border-color: #9eb8b8 !important;
    background: #ffffff !important;
    color: #202020 !important;
    box-shadow: none !important;
}

:global([data-app-surface='user'][data-theme='light']) .doopnews-submit-page__input::placeholder {
    color: #777777 !important;
}

:global([data-app-surface='user'][data-theme='light']) .doopnews-submit-page__submit {
    border-color: #006f6f !important;
    background: #009999 !important;
    color: #ffffff !important;
}

:global([data-app-surface='user'][data-theme='light']) .doopnews-submit-page__submit:hover {
    background: #007f7f !important;
    color: #ffffff !important;
}
</style>
