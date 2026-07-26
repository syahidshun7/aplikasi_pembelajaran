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
</script>

<template>
    <Head :title="`JOB_COMMAND | ${job?.name || 'JOB'}`" />

    <div class="min-h-screen bg-[#0d1117] p-4 md:p-8 font-['Press_Start_2P'] text-[#4ed4d4] text-[10px]">
        <div class="mx-auto max-w-7xl space-y-8">
            <AdminNavbar />

            <section class="rpg-panel border-cyan-500/50">
                <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div class="min-w-0">
                        <p class="text-[8px] uppercase text-cyan-300">Job_Command_Center</p>
                        <h1 class="mt-3 break-words text-base uppercase tracking-widest text-white md:text-xl">
                            {{ job.name }}
                        </h1>
                        <p class="mt-2 text-[8px] uppercase text-slate-500">{{ job.slug }}</p>
                        <div class="mt-3 inline-flex border px-2 py-1 text-[7px] uppercase" :class="statusClass(job.status)">
                            {{ statusLabel(job.status) }}
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <Link :href="jobsUrl" class="border border-slate-600 px-3 py-2 text-[8px] uppercase text-slate-300 hover:text-white">
                            Back_To_Jobs
                        </Link>
                        <Link :href="studyGroupsUrl" class="border border-emerald-500 px-3 py-2 text-[8px] uppercase text-emerald-300 hover:bg-emerald-400 hover:text-black">
                            Manage_Groups
                        </Link>
                    </div>
                </div>

                <p v-if="job.description" class="mt-5 border-t border-slate-800 pt-4 font-sans text-[13px] leading-relaxed text-slate-300">
                    {{ job.description }}
                </p>
            </section>

            <section class="grid gap-4 md:grid-cols-4">
                <div class="rpg-panel border-slate-700 bg-black/30">
                    <p class="text-[7px] uppercase text-slate-500">Study_Groups</p>
                    <p class="mt-3 text-xl text-cyan-300">{{ job.study_groups_count || 0 }}</p>
                </div>
                <div class="rpg-panel border-slate-700 bg-black/30">
                    <p class="text-[7px] uppercase text-slate-500">Users</p>
                    <p class="mt-3 text-xl text-emerald-300">{{ job.users_count || 0 }}</p>
                </div>
                <div class="rpg-panel border-slate-700 bg-black/30">
                    <p class="text-[7px] uppercase text-slate-500">Task_Banks</p>
                    <p class="mt-3 text-xl text-yellow-300">{{ job.task_banks_count || 0 }}</p>
                </div>
                <div class="rpg-panel border-slate-700 bg-black/30">
                    <p class="text-[7px] uppercase text-slate-500">Entry_Model</p>
                    <p class="mt-3 text-[10px] uppercase text-white">Job_To_Class</p>
                </div>
            </section>

            <section class="rpg-panel border-emerald-500/40">
                <div class="mb-5 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h2 class="text-[12px] uppercase tracking-widest text-emerald-300">Study_Groups</h2>
                        <p class="mt-2 text-[8px] uppercase leading-relaxed text-slate-500">
                            Pilih kelas di bawah job ini, lalu kelola quest, guide, event, staff, dan student dari konteks kelas.
                        </p>
                    </div>
                </div>

                <div v-if="groups.length === 0" class="border border-slate-800 bg-black/30 p-5 text-[8px] uppercase text-slate-500">
                    Belum ada study group untuk job ini.
                </div>

                <div v-else class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <article
                        v-for="group in groups"
                        :key="group.uuid"
                        class="border border-slate-700 bg-slate-950/50 p-4"
                    >
                        <div class="mb-3 flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="break-words text-[10px] uppercase text-white">{{ group.name }}</p>
                                <p class="mt-2 text-[7px] uppercase text-slate-500">Code {{ group.invite_code || '-' }}</p>
                            </div>
                            <Link :href="group.detail_url" class="shrink-0 border border-cyan-600 px-2 py-1 text-[7px] uppercase text-cyan-300 hover:bg-cyan-400 hover:text-black">
                                Open
                            </Link>
                        </div>

                        <div class="grid grid-cols-2 gap-2 text-[7px] uppercase text-slate-400">
                            <span>Students {{ group.students_count }} / {{ group.max_members }}</span>
                            <span>Staff {{ group.staff_count }}</span>
                            <span>Quest {{ group.quests_count }}</span>
                            <span>Event {{ group.events_count }}</span>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <Link :href="group.user_preview_url" class="border border-emerald-700 px-2 py-1 text-[7px] uppercase text-emerald-300 hover:bg-emerald-400 hover:text-black">User_View</Link>
                            <Link :href="group.quests_url" class="border border-yellow-700 px-2 py-1 text-[7px] uppercase text-yellow-300 hover:bg-yellow-400 hover:text-black">Quest</Link>
                            <Link :href="group.guides_url" class="border border-indigo-700 px-2 py-1 text-[7px] uppercase text-indigo-300 hover:bg-indigo-400 hover:text-black">Guide</Link>
                            <Link :href="group.events_url" class="border border-blue-700 px-2 py-1 text-[7px] uppercase text-blue-300 hover:bg-blue-400 hover:text-black">Event</Link>
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
    padding: 1rem;
    position: relative;
    box-shadow: 8px 8px 0px 0px rgba(0, 0, 0, 0.5);
}
</style>
