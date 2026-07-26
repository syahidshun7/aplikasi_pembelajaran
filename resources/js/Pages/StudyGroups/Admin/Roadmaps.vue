<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminNavbar from '@/Components/AdminNavbar.vue';
import { swal } from '@/Utils/Alert';

const props = defineProps({
    group: Object,
    attachedRoadmaps: {
        type: Array,
        default: () => [],
    },
    availableRoadmaps: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    roadmap_uuid: '',
    sort_order: 0,
});

const attachedUuids = computed(() => new Set(
    props.attachedRoadmaps.map((roadmap) => String(roadmap.uuid))
));

const attachableRoadmaps = computed(() => props.availableRoadmaps.filter(
    (roadmap) => !attachedUuids.value.has(String(roadmap.uuid))
));

const nextSortOrder = computed(() => (
    Math.max(0, ...props.attachedRoadmaps.map((roadmap) => Number(roadmap.sort_order || 0))) + 1
));

const attachRoadmap = (roadmap) => {
    if (!roadmap?.uuid) return;

    form.roadmap_uuid = String(roadmap.uuid);
    form.sort_order = nextSortOrder.value;
    form.post(route('groups.roadmaps.attach', { uuid: props.group.uuid }), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};

const detachRoadmap = async (roadmap) => {
    const result = await swal.fire({
        title: 'DETACH_ROADMAP?',
        text: `Lepas ${roadmap.title || 'roadmap'} dari kelas ini?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'DETACH',
        cancelButtonText: 'CANCEL',
    });

    if (!result.isConfirmed) return;

    router.delete(route('groups.roadmaps.detach', {
        uuid: props.group.uuid,
        roadmapUuid: roadmap.uuid,
    }), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="`ROADMAP | ${group.name}`" />

    <div class="min-h-screen bg-[#0d1117] p-4 font-['Press_Start_2P'] text-[10px] text-[#4ed4d4] md:p-8">
        <div class="mx-auto max-w-6xl space-y-6">
            <AdminNavbar />

            <header class="rpg-panel border-indigo-500/50">
                <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div>
                        <p class="text-[8px] uppercase text-indigo-300">Class_Operations</p>
                        <h1 class="mt-3 text-base uppercase text-white md:text-xl">Class_Roadmap</h1>
                        <p class="mt-3 font-sans text-[13px] text-slate-400">{{ group.name }}</p>
                    </div>
                    <Link
                        :href="route('groups.detail', group.uuid)"
                        class="border border-slate-600 px-3 py-2 text-[8px] uppercase text-slate-300 hover:border-cyan-400 hover:text-white"
                    >
                        Back_To_Class
                    </Link>
                </div>
            </header>

            <section class="rpg-panel border-indigo-500/50">
                <div class="mb-5 border-b border-slate-700 pb-4">
                    <h2 class="text-[11px] uppercase text-indigo-300">Available_Roadmaps</h2>
                    <p class="mt-2 font-sans text-[12px] leading-relaxed text-slate-400">
                        Pasang roadmap published sebagai kurikulum view-only. Tidak membuat enrollment atau progress siswa.
                    </p>
                </div>

                <div v-if="attachableRoadmaps.length === 0" class="border border-slate-800 bg-black/30 p-5 text-[8px] uppercase text-slate-500">
                    Semua roadmap published sudah terpasang, atau belum ada roadmap published.
                </div>
                <div v-else class="grid gap-3 md:grid-cols-2">
                    <article v-for="roadmap in attachableRoadmaps" :key="roadmap.uuid" class="flex items-start justify-between gap-4 border border-slate-700 bg-black/35 p-4">
                        <div class="min-w-0">
                            <p class="break-words text-[10px] uppercase text-white">{{ roadmap.title }}</p>
                            <p class="mt-3 line-clamp-3 font-sans text-[12px] leading-relaxed text-slate-400">{{ roadmap.description || 'Tidak ada deskripsi.' }}</p>
                        </div>
                        <button
                            type="button"
                            :disabled="form.processing"
                            class="shrink-0 border border-indigo-500 px-3 py-2 text-[8px] uppercase text-indigo-300 hover:bg-indigo-500 hover:text-black disabled:opacity-40"
                            @click="attachRoadmap(roadmap)"
                        >
                            Attach
                        </button>
                    </article>
                </div>
            </section>

            <section class="rpg-panel border-cyan-500/50">
                <div class="mb-5 border-b border-slate-700 pb-4">
                    <h2 class="text-[11px] uppercase text-cyan-300">Attached_Roadmaps [{{ attachedRoadmaps.length }}]</h2>
                </div>

                <div v-if="attachedRoadmaps.length === 0" class="border border-slate-800 bg-black/30 p-5 text-[8px] uppercase text-slate-500">
                    Belum ada roadmap view-only untuk kelas ini.
                </div>
                <div v-else class="grid gap-3 md:grid-cols-2">
                    <article v-for="roadmap in attachedRoadmaps" :key="roadmap.uuid" class="border border-slate-700 bg-black/35 p-4">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <p class="break-words text-[10px] uppercase text-white">{{ roadmap.title }}</p>
                                <p class="mt-3 line-clamp-3 font-sans text-[12px] leading-relaxed text-slate-400">{{ roadmap.description || 'Tidak ada deskripsi.' }}</p>
                                <p class="mt-3 text-[7px] uppercase text-indigo-300">
                                    Order {{ roadmap.sort_order || 0 }} / {{ roadmap.is_active ? 'Active' : 'Inactive' }}
                                </p>
                            </div>
                            <button
                                type="button"
                                class="shrink-0 border border-red-600 px-3 py-2 text-[8px] uppercase text-red-400 hover:bg-red-600 hover:text-white"
                                @click="detachRoadmap(roadmap)"
                            >
                                Detach
                            </button>
                        </div>
                    </article>
                </div>
            </section>
        </div>
    </div>
</template>

<style scoped>
.rpg-panel { @apply border-4 bg-[#1a1c2c] p-5; box-shadow: 8px 8px 0 rgba(0, 0, 0, .5); }
</style>
