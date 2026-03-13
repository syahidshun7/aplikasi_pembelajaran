<script setup>
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminNavbar from '@/Components/AdminNavbar.vue';

const props = defineProps({
    stats: Object,
    students: Object,
    helpUsers: Array,
    pendingSubmissions: Array,
    scope: Object,
    recentLogs: Array,
    errorLogs: {
        type: Array,
        default: () => []
    }
});

const page = usePage();
const isSuperAdmin = computed(() => String(page.props?.auth?.user?.role || '').toLowerCase() === 'admin');
const isMentor = computed(() => String(page.props?.auth?.user?.role || '').toLowerCase() === 'mentor');
const performanceTab = ref('all'); // all | help

const studentItems = computed(() => props.students?.data || []);
const studentLinks = computed(() => props.students?.links || []);

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







const formatTime = (iso) => {
    if (!iso) return '-';
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) return '-';
    return date.toLocaleString('id-ID', { hour: '2-digit', minute: '2-digit' });
};
</script>

<template>

    <Head title="ADMIN_PANEL | P-QUEST" />

    <div
        class="min-h-screen bg-[#0f101a] p-4 md:p-8 font-['Press_Start_2P'] text-[#4ed4d4] text-[10px] leading-relaxed">
        <div class="max-w-7xl mx-auto space-y-8">

        <AdminNavbar />

        <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-8">
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

        <div class="grid grid-cols-12 gap-6">

            <div class="col-span-12 lg:col-span-3 space-y-4">
                <div class="rpg-panel bg-slate-900/60 border-indigo-500/30">
                    <h2 class="text-white mb-6 border-b-2 border-slate-700 pb-2 uppercase text-center">Menu</h2>
                    <nav class="space-y-3">
                        <Link :href="route('dashboard')"
                            class="menu-btn border-slate-500 text-slate-300 bg-slate-900/10 hover:bg-slate-500 hover:text-black">
                            [#] ADMIN_DASHBOARD
                        </Link>
                        <Link :href="route('lobby')"
                            class="menu-btn border-slate-500 text-slate-300 bg-slate-900/10 hover:bg-slate-500 hover:text-black">
                            [@] USER_DASHBOARD
                        </Link>
                        <Link :href="route('quests.index')"
                            class="menu-btn border-yellow-500 text-yellow-500 bg-yellow-900/10 hover:bg-yellow-500 hover:text-black">
                            [+] QUEST
                        </Link>
                        <Link v-if="isSuperAdmin" :href="route('admin.jobs.index')"
                            class="menu-btn border-cyan-500 text-cyan-300 bg-cyan-900/10 hover:bg-cyan-300 hover:text-black">
                            [J] JOBS
                        </Link>
                        <Link :href="route('admin.task-banks.index')"
                            class="menu-btn border-teal-500 text-teal-300 bg-teal-900/10 hover:bg-teal-300 hover:text-black">
                            [T] TASK_BANK
                        </Link>
                        <Link :href="route('admin.events.index')"
                            class="menu-btn border-blue-500 text-blue-300 bg-blue-900/10 hover:bg-blue-300 hover:text-black">
                            [E] EVENTS
                        </Link>
                        <Link v-if="isSuperAdmin" :href="route('admin.shop-items.index')"
                            class="menu-btn border-amber-500 text-amber-300 bg-amber-900/10 hover:bg-amber-300 hover:text-black">
                            [$] SHOP_ITEMS
                        </Link>
                        <Link v-if="isSuperAdmin" :href="route('admin.submissions.manage.index')"
                            class="menu-btn border-cyan-500 text-cyan-400 bg-cyan-900/10 hover:bg-cyan-400 hover:text-black">
                            [S] SUBMISSIONS
                        </Link>
                        <Link :href="route('materi.index')"
                            class="menu-btn border-indigo-500 text-indigo-400 bg-indigo-900/10 hover:bg-grey-500 hover:text-black">
                            [O] GUIDE
                        </Link>
                        
                         <Link v-if="isSuperAdmin" :href="route('groups.manage')"
                            class="menu-btn border-emerald-500 text-emerald-400 bg-emerald-900/10 hover:bg-emerald-500 hover:text-black">
                            [U] STUDY_GROUP
                        </Link>
                        <Link v-if="isSuperAdmin" :href="route('admin.users.index')"
                            class="menu-btn border-orange-500 text-orange-400 bg-orange-900/10 hover:bg-orange-500 hover:text-black">
                            [!] USERS
                        </Link>
                       
                    </nav>
                </div>
            </div>

            <div class="col-span-12 lg:col-span-9 space-y-6">
                <div class="grid grid-cols-12 gap-6">
                    <div class="col-span-12 xl:col-span-7 space-y-6">
                        <div class="rpg-panel min-h-[450px]">
                            <div class="flex flex-col md:flex-row md:justify-between md:items-start gap-3 mb-6 border-l-4 border-green-500 pl-3">
                                <div class="min-w-0">
                                    <h3 class="text-green-400 uppercase tracking-widest text-[11px] md:text-[12px] break-words">
                                        {{ isMentor ? 'Mentor_Performance_Console' : 'Student_Performance_Console' }}
                                    </h3>
                                    <p class="text-[7px] text-slate-500 mt-2 italic break-words">
                                        {{ isMentor ? 'Scope: job-based. Review pending, help struggling students.' : 'Scope: global. Sorted by avg grade.' }}
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

                            <div class="w-full overflow-x-auto" v-if="performanceTab === 'all'">
                                <table class="w-full text-left border-collapse">
                                    <thead>
                                        <tr class="border-b-2 border-slate-800 text-slate-500 text-[8px]">
                                            <th class="py-3 px-2">STUDENT</th>
                                            <th class="py-3 px-2">LVL</th>
                                            <th class="py-3 px-2">QUESTS_DONE</th>
                                            <th class="py-3 px-2 text-right">AVG_GRADE</th>
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
                                        </tr>
                                        <tr v-if="!studentItems || studentItems.length === 0">
                                            <td colspan="4" class="py-10 text-center text-slate-600 italic">
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

                            <div class="w-full overflow-x-auto" v-else>
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

                    <div class="col-span-12 xl:col-span-5 space-y-6">
                        <div class="rpg-panel border-red-500/30 bg-black/20">
                            <div class="flex items-center justify-between mb-4 border-b border-slate-700 pb-3">
                                <h2 class="text-[10px] text-red-400 uppercase tracking-widest">Pending_Inbox</h2>
                                <span class="text-[8px] text-slate-400 uppercase">
                                    {{ isMentor ? 'Job_Scoped' : 'Global' }}
                                </span>
                            </div>

                            <div class="space-y-3 max-h-[420px] overflow-y-auto pr-2 custom-scroll">
                                <div
                                    v-for="item in pendingSubmissions"
                                    :key="item.uuid"
                                    class="p-3 border border-slate-700 bg-black/30"
                                >
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="text-[8px] text-slate-400 uppercase">
                                                {{ formatTime(item.created_at) }} | {{ item.user?.name || 'Unknown' }}
                                            </p>
                                            <p class="text-[9px] text-white uppercase mt-1 break-words">
                                                {{ item.quest?.title || 'Unknown Quest' }}
                                            </p>
                                            <p class="text-[7px] text-slate-500 uppercase mt-1">
                                                SUB_ID: {{ String(item.uuid || '').substring(0, 8) }}
                                            </p>
                                        </div>
                                        <Link
                                            :href="route('admin.submissions.inspect', item.uuid)"
                                            class="shrink-0 px-2 py-1 border border-red-500 text-red-400 hover:bg-red-500 hover:text-black uppercase text-[8px]"
                                        >
                                            [Review]
                                        </Link>
                                    </div>
                                </div>

                                <p v-if="!pendingSubmissions || pendingSubmissions.length === 0" class="text-[8px] text-slate-500 uppercase italic text-center py-6">
                                    Inbox clear. No pending submissions.
                                </p>
                            </div>
                        </div>

                        <div class="rpg-panel h-48 overflow-y-auto border-slate-700 bg-black/20">
                            <div class="flex items-center justify-between mb-2">
                                <h2 class="text-[8px] text-slate-300 uppercase tracking-widest">Server_Error_Log</h2>
                                <div class="flex items-center gap-2 text-[8px] text-slate-500 uppercase">
                                    <span>Last 12</span>
                                    <Link :href="route('admin.error-logs.index')" class="px-2 py-1 border border-slate-600 bg-slate-900/60 text-slate-200 hover:text-white">[View_All]</Link>
                                </div>
                            </div>

                            <div v-if="errorLogs && errorLogs.length" class="space-y-2">
                                <div
                                    v-for="log in errorLogs"
                                    :key="log.id"
                                    class="p-2 border border-slate-700 bg-slate-900/30"
                                >
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="text-[8px] text-slate-400 uppercase">
                                                {{ log.created_at || '-' }} | {{ log.method }} | {{ log.status_code }}
                                            </p>
                                            <p class="text-[9px] text-red-300 uppercase mt-1 break-words line-clamp-2">
                                                {{ log.message || '(no message)' }}
                                            </p>
                                            <p class="text-[7px] text-slate-500 uppercase mt-1 break-words">
                                                {{ log.url || '-' }}
                                            </p>
                                            <p class="text-[7px] text-slate-500 uppercase mt-1">
                                                Trace: {{ (log.trace_id || '').substring(0, 8) }} | IP: {{ log.ip || '-' }} | UID: {{ log.user_id || '-' }}
                                            </p>
                                        </div>
                                        <span class="px-2 py-1 text-[8px] uppercase border border-red-500 text-red-300">
                                            {{ log.exception_class ? log.exception_class.split('\\\\').pop() : 'Exception' }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div v-else class="space-y-1 text-[8px] text-green-500/70 font-mono">
                                <p v-for="(log, i) in recentLogs" :key="i">> {{ log }}</p>
                                <p v-if="!recentLogs || recentLogs.length === 0" class="text-slate-500 uppercase">No logs.</p>
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

.menu-btn {
    @apply block w-full p-3 text-left border-r-4 transition-all uppercase text-[10px] hover:translate-x-2;
}
</style>
