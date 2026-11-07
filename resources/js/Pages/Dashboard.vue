<script setup>
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminNavbar from '@/Components/AdminNavbar.vue';

const props = defineProps({
    stats: Object,
    students: Object,
    helpUsers: Array,
    accessibleGroups: {
        type: Array,
        default: () => [],
    },
    jobCommandItems: {
        type: Array,
        default: () => [],
    },
    scope: Object,
});

const page = usePage();
const currentRole = computed(() => String(page.props?.auth?.user?.role || '').toLowerCase());
const isSuperAdmin = computed(() => currentRole.value === 'super_admin');
const isAdminAccess = computed(() => ['super_admin', 'admin'].includes(currentRole.value));
const isMentor = computed(() => currentRole.value === 'mentor');
const performanceTab = ref('all'); // all | help

const studentItems = computed(() => props.students?.data || []);
const studentLinks = computed(() => props.students?.links || []);
const groupItems = computed(() => props.accessibleGroups || []);
const jobItems = computed(() => props.jobCommandItems || []);
const systemHealthLabel = computed(() => ({
    healthy: 'Healthy',
    warning: 'Warning',
    critical: 'Critical',
}[String(props.stats?.system_health || '')] || 'Unknown'));
const systemHealthClass = computed(() => ({
    healthy: 'text-emerald-400',
    warning: 'text-yellow-400',
    critical: 'text-red-500',
}[String(props.stats?.system_health || '')] || 'text-slate-400'));

// Helper untuk warna grade
const getGradeColor = (grade) => {
    if (grade >= 90) return 'text-yellow-400';
    return 'text-green-400';
};

const getRiskColor = (grade) => {
    if (grade < 60) return 'text-red-400';
    if (grade < 75) return 'text-orange-400';
    return 'text-slate-300';
};
</script>

<template>

    <Head title="ADMIN_PANEL | P-QUEST" />

    <div
        class="min-h-screen bg-[#0f101a] p-4 md:p-8 font-['Press_Start_2P'] text-[#4ed4d4] text-[10px] leading-relaxed">
        <div class="max-w-7xl mx-auto space-y-8">

        <AdminNavbar />

        <div v-if="isSuperAdmin" class="grid grid-cols-1 gap-4 mb-8 sm:grid-cols-2 lg:grid-cols-5">
            <div class="rpg-panel py-4 border-slate-700 bg-black/40 shadow-none">
                <p class="text-[7px] text-slate-500 uppercase italic mb-2">Jobs</p>
                <div class="text-xl font-bold text-cyan-400">{{ stats?.total_jobs || 0 }}</div>
            </div>
            <div class="rpg-panel py-4 border-slate-700 bg-black/40 shadow-none">
                <p class="text-[7px] text-slate-500 uppercase italic mb-2">Study_Groups</p>
                <div class="text-xl font-bold text-emerald-400">{{ stats?.total_study_groups || 0 }}</div>
            </div>
            <div class="rpg-panel py-4 border-slate-700 bg-black/40 shadow-none">
                <p class="text-[7px] text-slate-500 uppercase italic mb-2">Students</p>
                <div class="text-xl font-bold text-cyan-400">{{ stats?.total_students || 0 }}</div>
            </div>
            <div class="rpg-panel py-4 border-slate-700 bg-black/40 shadow-none">
                <p class="text-[7px] text-slate-500 uppercase italic mb-2">Pending_Reviews</p>
                <div class="text-xl font-bold text-red-500">{{ stats?.pending_verdicts || 0 }}</div>
            </div>
            <Link
                :href="route('admin.error-logs.index')"
                class="rpg-panel block py-4 border-slate-700 bg-black/40 shadow-none transition-colors hover:border-cyan-500"
            >
                <p class="text-[7px] text-slate-500 uppercase italic mb-2">System_Health</p>
                <div class="text-[13px] font-bold uppercase" :class="systemHealthClass">{{ systemHealthLabel }}</div>
                <p class="mt-2 text-[7px] uppercase text-slate-500">{{ stats?.system_errors_24h || 0 }} errors / 24h</p>
            </Link>
        </div>

        <div v-else class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-8">
            <div class="rpg-panel py-4 border-slate-700 bg-black/40 shadow-none">
                <p class="text-[8px] text-slate-500 uppercase italic mb-2">Total_Materi</p>
                <div class="text-xl font-bold text-indigo-400">{{ stats?.total_materi || 0 }}</div>
            </div>
            <div class="rpg-panel py-4 border-slate-700 bg-black/40 shadow-none">
                <p class="text-[8px] text-slate-500 uppercase italic mb-2">Total_Siswa</p>
                <div class="text-xl font-bold text-cyan-400">{{ stats?.total_students || 0 }}</div>
            </div>
            <div class="rpg-panel py-4 border-slate-700 bg-black/40 shadow-none">
                <p class="text-[8px] text-slate-500 uppercase italic mb-2">Pending_Reviews</p>
                <div class="text-xl font-bold text-red-500 animate-pulse">{{ stats?.pending_verdicts || 0 }}</div>
            </div>
            <div class="rpg-panel py-4 border-slate-700 bg-black/40 shadow-none">
                <p class="text-[8px] text-slate-500 uppercase italic mb-2">Avg_Grade_30D</p>
                <div class="text-xl font-bold text-emerald-400">{{ stats?.avg_grade_30d ?? 0 }}%</div>
            </div>
            <div class="rpg-panel py-4 border-slate-700 bg-black/40 shadow-none">
                <p class="text-[8px] text-slate-500 uppercase italic mb-2">Graded_7D</p>
                <div class="text-xl font-bold text-yellow-400">{{ stats?.graded_7d || 0 }}</div>
            </div>
        </div>

        <section v-if="isSuperAdmin" class="rpg-panel border-cyan-500/40 bg-black/20">
            <div class="mb-5 flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div>
                    <h2 class="text-[12px] uppercase tracking-widest text-cyan-300">
                        Job_Command
                    </h2>
                    <p class="mt-2 text-[8px] uppercase leading-relaxed text-slate-500">
                        Super admin mulai dari job/program, lalu masuk ke study group dan operasional kelas.
                    </p>
                </div>
                <Link
                    :href="route('admin.jobs.index')"
                    class="shrink-0 border border-cyan-500 px-3 py-2 text-[8px] uppercase text-cyan-300 hover:bg-cyan-400 hover:text-black"
                >
                    Manage_Jobs
                </Link>
            </div>

            <div v-if="jobItems.length === 0" class="border border-slate-800 bg-slate-950/60 p-5 text-[8px] uppercase text-slate-500">
                Belum ada job/program.
            </div>

            <div v-else class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                <Link
                    v-for="job in jobItems"
                    :key="job.id"
                    :href="job.detail_url"
                    class="block border border-slate-700 bg-slate-950/60 p-4 transition-colors hover:border-cyan-400 hover:bg-cyan-950/20"
                >
                    <div class="mb-3 grid grid-cols-[minmax(0,1fr)_auto] items-start gap-2 sm:gap-3">
                        <div class="min-w-0">
                            <p class="break-words text-[10px] uppercase text-white">{{ job.name }}</p>
                            <p class="mt-2 text-[7px] uppercase text-cyan-300">{{ job.slug }}</p>
                        </div>
                        <span class="shrink-0 border border-cyan-700 px-2 py-1 text-[7px] uppercase text-cyan-300">
                            Open
                        </span>
                    </div>
                    <div class="grid grid-cols-3 gap-2 text-[7px] uppercase text-slate-400">
                        <span>Groups {{ job.study_groups_count }}</span>
                        <span>Users {{ job.users_count }}</span>
                        <span>Banks {{ job.task_banks_count }}</span>
                    </div>
                </Link>
            </div>
        </section>

        <section v-if="!isSuperAdmin" class="rpg-panel border-emerald-500/40 bg-black/20">
            <div class="mb-5 flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div>
                    <h2 class="text-[12px] uppercase tracking-widest text-emerald-300">
                        Study_Group_Command
                    </h2>
                    <p class="mt-2 text-[8px] uppercase leading-relaxed text-slate-500">
                        {{ isMentor ? 'Pilih kelas yang diberikan ke akun mentor ini.' : 'Mulai operasional dari kelas, lalu quest/event/guide mengikuti konteks kelas.' }}
                    </p>
                </div>
                <Link
                    v-if="isAdminAccess"
                    :href="route('groups.manage')"
                    class="shrink-0 border border-emerald-500 px-3 py-2 text-[8px] uppercase text-emerald-300 hover:bg-emerald-400 hover:text-black"
                >
                    Manage_Access
                </Link>
            </div>

            <div v-if="groupItems.length === 0" class="border border-slate-800 bg-slate-950/60 p-5 text-[8px] uppercase text-slate-500">
                Belum ada study group yang bisa diakses akun ini.
            </div>

            <div v-else class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                <article
                    v-for="group in groupItems"
                    :key="group.uuid"
                    class="border border-slate-700 bg-slate-950/60 p-4 transition-colors hover:border-emerald-400 hover:bg-emerald-950/20"
                >
                    <div class="mb-3 flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="break-words text-[10px] uppercase text-white">{{ group.name }}</p>
                            <p class="mt-2 text-[7px] uppercase text-cyan-300">{{ group.job?.name || 'No Job' }}</p>
                        </div>
                        <Link
                            :href="group.preview_url"
                            class="shrink-0 whitespace-nowrap border border-cyan-600 px-1.5 py-1 text-[6px] uppercase text-cyan-300 hover:bg-cyan-400 hover:text-black sm:px-2 sm:text-[7px]"
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
                        <Link
                            :href="group.detail_url"
                            class="inline-flex min-h-9 items-center justify-center border border-emerald-700 bg-emerald-950/20 px-3 py-2 text-[7px] uppercase text-emerald-300 hover:bg-emerald-400 hover:text-black"
                        >
                            Open
                        </Link>
                    </div>
                </article>
            </div>
        </section>

        <div v-if="!isSuperAdmin" class="grid grid-cols-12 gap-6">
            <div class="col-span-12 space-y-6">
                <div class="grid grid-cols-12 gap-6">
                    <div class="col-span-12 space-y-6">
                        <div v-if="!isMentor" class="rpg-panel min-h-[450px]">
                            <div class="flex flex-col md:flex-row md:justify-between md:items-start gap-3 mb-6 border-l-4 border-green-500 pl-3">
                                <div class="min-w-0">
                                    <h3 class="text-green-400 uppercase tracking-widest text-[11px] md:text-[12px] break-words">
                                        Student_Performance_Console
                                    </h3>
                                    <p class="text-[7px] text-slate-500 mt-2 italic break-words">
                                        Scope: global. Sorted by avg grade.
                                    </p>
                                </div>
                                <div class="flex flex-wrap gap-2 self-start md:self-auto shrink-0 md:justify-end md:pt-1">
                                    <button
                                        @click="performanceTab = 'all'"
                                        class="px-2 py-1 border text-[8px] uppercase transition-all"
                                        :class="performanceTab === 'all' ? 'border-emerald-500 text-emerald-400 bg-emerald-900/20' : 'border-slate-700 text-slate-300 hover:bg-slate-700 hover:text-white'"
                                    >
                                        All_Students
                                    </button>
                                    <button
                                        @click="performanceTab = 'help'"
                                        class="px-2 py-1 border text-[8px] uppercase transition-all"
                                        :class="performanceTab === 'help' ? 'border-orange-500 text-orange-400 bg-orange-900/20' : 'border-slate-700 text-slate-300 hover:bg-slate-700 hover:text-white'"
                                    >
                                        Need_Help
                                    </button>
                                </div>
                            </div>

                            <div class="w-full overflow-x-auto custom-scroll" v-if="performanceTab === 'all'">
                                <table class="w-full text-left border-collapse">
                                    <thead>
                                        <tr class="border-b-2 border-slate-800 text-slate-500 text-[8px]">
                                            <th class="py-3 px-2">STUDENT</th>
                                            <th class="py-3 px-2">LVL</th>
                                            <th class="py-3 px-2">QUESTS_DONE</th>
                                            <th class="py-3 px-2 text-right">AVG_GRADE</th>
                                            <th class="py-3 px-2">AVG_PER_CLASS</th>
                                        </tr>
                                    </thead>
                                    <tbody class="text-[10px]">
                                        <tr
                                            v-for="user in studentItems"
                                            :key="user.id"
                                            class="border-b border-slate-800/50 hover:bg-white/5 transition-colors">
                                            <td class="py-4 px-2 text-white uppercase">{{ user.name }}</td>
                                            <td class="py-4 px-2 text-cyan-500">{{ user.lvl }}</td>
                                            <td class="py-4 px-2 text-slate-400">{{ user.total_completed }} Missions</td>
                                            <td class="py-4 px-2 text-right font-bold" :class="getGradeColor(user.avg_grade)">
                                                {{ user.avg_grade }}%
                                            </td>
                                            <td class="py-4 px-2 align-top">
                                                <div v-if="Array.isArray(user.class_averages) && user.class_averages.length > 0" class="space-y-1">
                                                    <p
                                                        v-for="classItem in user.class_averages"
                                                        :key="`${user.id}-${classItem.study_group_id ?? 'general'}-${classItem.class_name}`"
                                                        class="text-[7px] uppercase text-slate-300"
                                                    >
                                                        <span class="text-cyan-300">{{ classItem.class_name }}</span>:
                                                        {{ classItem.average_grade ?? 0 }}%
                                                        <span class="text-slate-500">/ {{ classItem.total_quests ?? 0 }} Quest</span>
                                                    </p>
                                                </div>
                                                <span v-else class="text-[7px] uppercase text-slate-600">No class data</span>
                                            </td>
                                        </tr>
                                        <tr v-if="!studentItems || studentItems.length === 0">
                                            <td colspan="5" class="py-10 text-center text-slate-600 italic">
                                                No students found...
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>

                                <div v-if="studentLinks.length" class="mt-5 flex flex-wrap gap-2 justify-end">
                                    <button
                                        v-for="(link, idx) in studentLinks"
                                        :key="`${idx}-${link.label}`"
                                        @click="link.url && router.get(link.url, {}, { preserveScroll: true, preserveState: true })"
                                        :disabled="!link.url"
                                        class="px-3 py-1 border text-[8px] uppercase transition-all"
                                        :class="[
                                            link.active
                                                ? 'border-emerald-500 text-emerald-400 bg-emerald-900/20'
                                                : 'border-slate-700 text-slate-300 hover:bg-slate-700 hover:text-white',
                                            !link.url ? 'opacity-40 cursor-not-allowed' : ''
                                        ]"
                                        v-html="link.label"
                                    />
                                </div>
                            </div>

                            <div class="w-full overflow-x-auto custom-scroll" v-else>
                                <table class="w-full text-left border-collapse">
                                    <thead>
                                        <tr class="border-b-2 border-slate-800 text-slate-500 text-[8px]">
                                            <th class="py-3 px-2">STUDENT</th>
                                            <th class="py-3 px-2">LVL</th>
                                            <th class="py-3 px-2">GRADED_30D</th>
                                            <th class="py-3 px-2 text-right">AVG_30D</th>
                                        </tr>
                                    </thead>
                                    <tbody class="text-[10px]">
                                        <tr
                                            v-for="user in helpUsers"
                                            :key="user.id"
                                            class="border-b border-slate-800/50 hover:bg-white/5 transition-colors">
                                            <td class="py-4 px-2 text-white uppercase">{{ user.name }}</td>
                                            <td class="py-4 px-2 text-cyan-500">{{ user.lvl }}</td>
                                            <td class="py-4 px-2 text-slate-400">{{ user.graded_count_30d }} Reviews</td>
                                            <td class="py-4 px-2 text-right font-bold" :class="getRiskColor(user.avg_grade_30d)">
                                                {{ user.avg_grade_30d }}%
                                            </td>
                                        </tr>
                                        <tr v-if="!helpUsers || helpUsers.length === 0">
                                            <td colspan="4" class="py-10 text-center text-slate-600 italic">
                                                No students flagged in the last 30 days...
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>

            </div>

        </div>
        </div>
    </div>
</template>

<style scoped>
.rpg-panel {
    @apply p-6 relative border-4 border-[#3d415f] bg-[#1a1c2c];
    box-shadow: 8px 8px 0px 0px rgba(0, 0, 0, 0.5);
}

</style>
