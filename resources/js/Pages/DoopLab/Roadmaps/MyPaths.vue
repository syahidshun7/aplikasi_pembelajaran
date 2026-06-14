<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    enrollments: { type: Array, default: () => [] },
});
</script>

<template>
    <AuthenticatedLayout>
        <Head title="My Roadmaps" />
        <div class="relative min-h-screen">
            <Teleport to="body">
                <div class="fixed inset-0 -z-10 pointer-events-none">
                    <img src="/images/Gerbang_lab_pixel_art_website (3).jpeg" class="hidden md:block w-full h-full object-cover opacity-[0.15]" style="image-rendering: pixelated; transform: translateZ(0); will-change: auto;" alt="" />
                </div>
            </Teleport>
            <div class="relative z-10 p-4 md:p-8 text-[10px] font-['Press_Start_2P'] text-[#4ed4d4] space-y-4">
            <div class="border-b-2 border-cyan-900 pb-3">
                <h1 class="text-sm md:text-lg uppercase tracking-wider break-words">My_Roadmaps</h1>
                <p class="text-[8px] text-slate-400 uppercase mt-2">Roadmap mentoring yang ditugaskan untukmu</p>
            </div>

            <div v-if="enrollments.length" class="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="item in enrollments"
                    :key="item.uuid"
                    :href="route('dooplab.roadmaps.enrollments.show', item.uuid)"
                    class="panel p-3 border border-slate-700 hover:border-cyan-500 transition"
                >
                    <p class="text-[12px] font-bold text-slate-100">{{ item.roadmap.title }}</p>
                    <p class="text-[9px] text-slate-400 mt-1">Mentor: {{ item.mentor_name }}</p>
                    <p class="text-[9px] text-cyan-400 mt-1 uppercase">Status: {{ item.status }}</p>
                </Link>
            </div>
            <p v-else class="text-[8px] text-slate-400 uppercase">Belum ada roadmap. Mentor akan assign ke kamu.</p>
        </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.panel {
    background: rgba(10, 16, 30, 0.72);
    border-radius: 0;
    border: 2px solid rgba(87, 214, 255, 0.24);
    box-shadow: 4px 4px 0 rgba(1, 6, 14, 0.9);
    display: block;
}
</style>

<style scoped>
.p-4 {
    font-family: "Press Start 2P", Inter, sans-serif !important;
    font-size: 8px !important;
}

h1 { font-size: 11px !important; }
p, a, span { font-size: 8px; }

.panel { padding: 10px !important; }
.panel p { font-size: 8px !important; }
.panel p.text-\[12px\] { font-size: 9px !important; font-weight: 700 !important; }

@media (max-width: 640px) {
    .grid {
        grid-template-columns: 1fr !important;
    }
}

@media (max-width: 480px) {
    h1 {
        font-size: 10px !important;
        line-height: 1.6 !important;
    }

    .panel {
        padding: 8px !important;
    }
}
</style>
