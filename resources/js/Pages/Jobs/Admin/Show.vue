<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminNavbar from '@/Components/AdminNavbar.vue';

const props = defineProps({
    job: Object,
    groups: {
        type: Array,
        default: () => [],
    },
    studyGroupsUrl: String,
    jobsUrl: String,
});

const statusLabel = (status) => {
    if (status === 'coming_soon') return 'COMING_SOON';
    if (status === 'draft') return 'DRAFT';
    return 'ACTIVE';
};

const statusClass = (status) => {
    if (status === 'coming_soon') return 'border-yellow-500 text-yellow-300 bg-yellow-500/10';
    if (status === 'draft') return 'border-slate-500 text-slate-300 bg-slate-500/10';
    return 'border-emerald-500 text-emerald-300 bg-emerald-500/10';
};

const statusTextClass = (status) => {
    if (status === 'coming_soon') return 'text-yellow-300';
    if (status === 'draft') return 'text-slate-300';
    return 'text-emerald-300';
};
</script>

<template>
    <Head :title="`JOB_COMMAND | ${job?.name || 'JOB'}`" />

    <div class="min-h-screen bg-[#0f101a] p-4 md:p-8 font-['Press_Start_2P'] text-[#4ed4d4] text-[10px] leading-relaxed">
        <div class="mx-auto max-w-7xl space-y-8">
            <AdminNavbar />

            <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <div class="rpg-panel border-slate-700 bg-black/40 py-4 shadow-none">
                    <p class="text-[7px] uppercase italic tracking-widest text-slate-500">Total_Kelas</p>
                    <p class="mt-3 text-xl text-indigo-300">{{ job.study_groups_count || 0 }}</p>
                </div>
                <div class="rpg-panel border-slate-700 bg-black/40 py-4 shadow-none">
                    <p class="text-[7px] uppercase italic tracking-widest text-slate-500">Total_User</p>
                    <p class="mt-3 text-xl text-cyan-300">{{ job.users_count || 0 }}</p>
                </div>
                <div class="rpg-panel border-slate-700 bg-black/40 py-4 shadow-none">
                    <p class="text-[7px] uppercase italic tracking-widest text-slate-500">Task_Banks</p>
                    <p class="mt-3 text-xl text-red-400">{{ job.task_banks_count || 0 }}</p>
                </div>
                <div class="rpg-panel border-slate-700 bg-black/40 py-4 shadow-none">
                    <p class="text-[7px] uppercase italic tracking-widest text-slate-500">Job_Status</p>
                    <p class="mt-3 text-[10px] uppercase" :class="statusTextClass(job.status)">
                        {{ statusLabel(job.status) }}
                    </p>
                </div>
                <div class="rpg-panel border-slate-700 bg-black/40 py-4 shadow-none">
                    <p class="text-[7px] uppercase italic tracking-widest text-slate-500">Entry_Model</p>
                    <p class="mt-3 text-[10px] uppercase text-yellow-300">Job_To_Class</p>
                </div>
            </section>

            <section class="rpg-panel border-emerald-500/40 bg-black/20">
                <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div class="min-w-0">
                        <h1 class="break-words text-[13px] uppercase tracking-widest text-emerald-300 md:text-base">
                            Study_Group_Command
                        </h1>
                        <p class="mt-2 text-[8px] uppercase leading-relaxed text-slate-500">
                            Pilih kelas yang terhubung ke job ini.
                        </p>
                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            <span class="max-w-full break-words text-[9px] uppercase text-white">{{ job.name }}</span>
                            <span class="border px-2 py-1 text-[7px] uppercase" :class="statusClass(job.status)">
                                {{ statusLabel(job.status) }}
                            </span>
                        </div>
                        <p class="mt-2 break-words text-[7px] uppercase text-slate-500">{{ job.slug }}</p>
                        <p v-if="job.description" class="mt-3 max-w-3xl font-sans text-[13px] leading-relaxed text-slate-300">
                            {{ job.description }}
                        </p>
                    </div>

                    <div class="flex shrink-0 flex-wrap gap-2">
                        <Link :href="jobsUrl" class="border border-slate-600 px-3 py-2 text-[8px] uppercase text-slate-300 hover:bg-slate-800 hover:text-white">
                            Back_To_Jobs
                        </Link>
                        <Link :href="studyGroupsUrl" class="border border-emerald-500 px-3 py-2 text-[8px] uppercase text-emerald-300 hover:bg-emerald-400 hover:text-black">
                            Manage_Groups
                        </Link>
                    </div>
                </div>

                <div v-if="groups.length === 0" class="border border-slate-800 bg-black/30 p-5 text-[8px] uppercase text-slate-500">
                    Belum ada study group untuk job ini.
                </div>

                <div v-else class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <article
                        v-for="group in groups"
                        :key="group.uuid"
                        class="border border-slate-700 bg-slate-950/60 p-4 transition-colors hover:border-emerald-400 hover:bg-emerald-950/20"
                    >
                        <div class="mb-3 flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="break-words text-[10px] uppercase text-white">{{ group.name }}</p>
                                <p class="mt-2 break-words text-[7px] uppercase text-cyan-300">{{ job.name }}</p>
                            </div>
                            <Link
                                :href="group.user_preview_url"
                                class="shrink-0 border border-cyan-500 px-2 py-1 text-[7px] uppercase text-cyan-300 hover:bg-cyan-400 hover:text-black"
                            >
                                Preview_Group
                            </Link>
                        </div>

                        <div class="grid grid-cols-2 gap-2 text-[7px] uppercase text-slate-400">
                            <span>Students {{ group.students_count }}</span>
                            <span>Staff {{ group.staff_count }}</span>
                            <span>Quest {{ group.quests_count }}</span>
                            <span>Event {{ group.events_count }}</span>
                        </div>

                        <div class="mt-4 flex border-t border-slate-800 pt-3">
                            <Link :href="group.detail_url" class="inline-flex min-h-9 items-center border border-emerald-700 px-3 text-[7px] uppercase text-emerald-300 hover:bg-emerald-400 hover:text-black">
                                Open
                            </Link>
                        </div>
                    </article>
                </div>
            </section>
        </div>
    </div>
</template>

<style scoped>
.rpg-panel {
    background-color: #1a1c2c;
    border-width: 4px;
    padding: 1.5rem;
    position: relative;
    box-shadow: 8px 8px 0px 0px rgba(0, 0, 0, 0.5);
}
</style>
