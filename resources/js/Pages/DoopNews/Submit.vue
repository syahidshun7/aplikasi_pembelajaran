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

        <main class="doopnews-submit-page lobby-detail-page mx-auto max-w-3xl space-y-4 px-1 font-['Press_Start_2P'] text-[10px] text-cyan-200 sm:px-0">
            <header class="doopnews-submit-page__header flex items-center justify-between gap-3 border-b-4 border-cyan-900 pb-3">
                <h1 class="doopnews-submit-page__title text-[16px] uppercase leading-tight text-white sm:text-lg">Kirim Kabar</h1>
                <Link :href="route('doopnews.index')" class="doopnews-submit-page__back shrink-0 border-2 border-slate-700 px-3 py-2 text-[8px] uppercase text-slate-300 hover:text-white">Back</Link>
            </header>

            <form
                class="doopnews-submit-page__form grid gap-3 border-2 p-3 sm:gap-4 sm:p-5"
                :class="isLightTheme ? 'doopnews-submit-page__form--light' : 'border-slate-700 bg-[#1a1c2c]/90'"
                enctype="multipart/form-data"
                @submit.prevent="submit"
            >
                <div class="grid gap-3 sm:grid-cols-[1fr_220px]">
                    <input v-model="form.title" class="doopnews-submit-page__input border-2 border-slate-700 bg-black px-3 py-2 text-cyan-300 outline-none sm:py-3" placeholder="TITLE" />
                    <select v-model="form.category" class="doopnews-submit-page__input border-2 border-slate-700 bg-black px-3 py-2 text-cyan-300 outline-none sm:py-3">
                        <option v-for="category in categories" :key="category" :value="category">{{ categoryLabel(category) }}</option>
                    </select>
                </div>
                <label class="doopnews-submit-page__file-wrap border-2 border-slate-700 bg-black px-3 py-2 text-[8px] uppercase text-slate-300">
                    <span class="doopnews-submit-page__file-label">Cover Image</span>
                    <input type="file" accept="image/*" class="doopnews-submit-page__file mt-2 block w-full text-[8px] text-cyan-300 outline-none" @change="setCoverImage" />
                </label>
                <p v-if="form.errors.cover_image" class="text-[8px] uppercase text-red-300">{{ form.errors.cover_image }}</p>
                <textarea v-model="form.excerpt" rows="2" class="doopnews-submit-page__input border-2 border-slate-700 bg-black p-3 font-sans text-[13px] text-cyan-100 outline-none" placeholder="RINGKASAN OPSIONAL" />
                <textarea v-model="form.body" rows="7" class="doopnews-submit-page__input doopnews-submit-page__body border-2 border-slate-700 bg-black p-3 font-sans text-[13px] text-cyan-100 outline-none sm:text-[14px]" placeholder="TULIS KABAR" />

                <details class="doopnews-submit-page__optional border-2 border-slate-700 bg-black/40">
                    <summary class="cursor-pointer px-3 py-2 text-[8px] uppercase text-cyan-300">Action Opsional</summary>
                    <div class="grid gap-3 border-t border-slate-700 p-3 sm:grid-cols-2">
                        <input v-model="form.action_label" class="doopnews-submit-page__input border-2 border-slate-700 bg-black px-3 py-2 text-cyan-300 outline-none" placeholder="LABEL" />
                        <input v-model="form.action_url" class="doopnews-submit-page__input border-2 border-slate-700 bg-black px-3 py-2 text-cyan-300 outline-none" placeholder="URL" />
                    </div>
                </details>

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

.doopnews-submit-page__input,
.doopnews-submit-page__file-wrap,
.doopnews-submit-page__optional,
.doopnews-submit-page__submit,
.doopnews-submit-page__back {
    min-height: 42px;
}

.doopnews-submit-page__file::file-selector-button {
    margin-right: 0.5rem;
    border: 0;
    background: #4b5563;
    padding: 0.35rem 0.55rem;
    color: #ffffff;
    font: inherit;
    font-size: 8px;
    text-transform: uppercase;
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

:global([data-app-surface='user'][data-theme='light']) .doopnews-submit-page__file-wrap,
:global([data-app-surface='user'][data-theme='light']) .doopnews-submit-page__optional {
    border-color: #9eb8b8 !important;
    background: #ffffff !important;
    color: #202020 !important;
}

:global([data-app-surface='user'][data-theme='light']) .doopnews-submit-page__optional summary,
:global([data-app-surface='user'][data-theme='light']) .doopnews-submit-page__file-label {
    color: #006f6f !important;
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

@media (max-width: 640px) {
    .doopnews-submit-page__form {
        box-shadow: 4px 4px 0 rgba(0, 0, 0, 0.3);
    }

    .doopnews-submit-page__body {
        min-height: 180px;
    }
}
</style>
