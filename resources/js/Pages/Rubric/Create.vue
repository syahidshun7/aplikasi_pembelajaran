<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminNavbar from '@/Components/AdminNavbar.vue';

const form = useForm({
    title: '',
    description: '',
});

const submit = () => {
    form.post(route('admin.rubrics.store'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="CREATE_RUBRIC | ADMIN_CONSOLE" />

    <div class="min-h-screen bg-[#0f101a] p-4 md:p-8 font-['Press_Start_2P'] text-[#4ed4d4] text-[10px] leading-relaxed">
        <div class="max-w-5xl mx-auto space-y-8">
            <AdminNavbar />

            <div class="rpg-panel bg-black/40 border-slate-700 shadow-none">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h1 class="text-white text-sm uppercase tracking-widest">Create Rubric</h1>
                        <p class="text-[8px] text-slate-500 uppercase mt-2">Define rubric title and description.</p>
                    </div>
                    <Link
                        :href="route('admin.rubrics.index')"
                        class="btn-pixel bg-slate-700 text-white px-4 py-2 border-slate-900 uppercase font-bold hover:bg-slate-600 transition-colors text-[8px]"
                    >
                        Back
                    </Link>
                </div>
            </div>

            <div class="rpg-panel bg-[#1a1c2c]/80 border-slate-700">
                <form class="space-y-5" @submit.prevent="submit">
                    <div>
                        <label class="text-[8px] text-slate-500 uppercase">Title</label>
                        <input
                            v-model="form.title"
                            type="text"
                            class="mt-2 w-full bg-black/30 border-2 border-slate-700 px-3 py-2 text-slate-200 text-[9px] focus:outline-none focus:border-cyan-400"
                            placeholder="e.g. Essay Rubric"
                        />
                        <div v-if="form.errors.title" class="text-red-400 text-[8px] mt-2">
                            {{ form.errors.title }}
                        </div>
                    </div>

                    <div>
                        <label class="text-[8px] text-slate-500 uppercase">Description</label>
                        <textarea
                            v-model="form.description"
                            class="mt-2 w-full min-h-[120px] bg-black/30 border-2 border-slate-700 px-3 py-2 text-slate-200 text-[9px] focus:outline-none focus:border-cyan-400"
                            placeholder="Optional description..."
                        />
                        <div v-if="form.errors.description" class="text-red-400 text-[8px] mt-2">
                            {{ form.errors.description }}
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button
                            type="submit"
                            class="btn-pixel bg-emerald-300 text-black px-4 py-2 border-emerald-700 uppercase font-bold hover:bg-emerald-200 transition-colors text-[8px]"
                            :disabled="form.processing"
                        >
                            Create
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

