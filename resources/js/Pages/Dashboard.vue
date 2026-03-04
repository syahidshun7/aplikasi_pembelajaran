<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminNavbar from '@/Components/AdminNavbar.vue';

const props = defineProps({
    stats: Object,
    topUsers: Array, // Data user dengan grade > 75
    recentLogs: Array
});

const page = usePage();

// Helper untuk warna grade
const getGradeColor = (grade) => {
    if (grade >= 90) return 'text-yellow-400';
    return 'text-green-400';
};
</script>

<template>

    <Head title="ADMIN_PANEL | P-QUEST" />

    <div
        class="min-h-screen bg-[#0f101a] p-4 md:p-8 font-['Press_Start_2P'] text-[#4ed4d4] text-[10px] leading-relaxed">
        <div class="max-w-7xl mx-auto space-y-8">

        <AdminNavbar />

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
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
                        <Link :href="route('admin.jobs.index')"
                            class="menu-btn border-cyan-500 text-cyan-300 bg-cyan-900/10 hover:bg-cyan-300 hover:text-black">
                            [J] JOBS
                        </Link>
                        <Link :href="route('admin.events.index')"
                            class="menu-btn border-blue-500 text-blue-300 bg-blue-900/10 hover:bg-blue-300 hover:text-black">
                            [E] EVENTS
                        </Link>
                        <Link :href="route('admin.submissions.manage.index')"
                            class="menu-btn border-cyan-500 text-cyan-400 bg-cyan-900/10 hover:bg-cyan-400 hover:text-black">
                            [⚔] SUBMISSIONS
                        </Link>
                        <Link :href="route('materi.index')"
                            class="menu-btn border-indigo-500 text-indigo-400 bg-indigo-900/10 hover:bg-grey-500 hover:text-black">
                            [O] GUIDE
                        </Link>
                        
                         <Link :href="route('groups.manage')"
                            class="menu-btn border-emerald-500 text-emerald-400 bg-emerald-900/10 hover:bg-emerald-500 hover:text-black">
                            [U] STUDY_GROUP
                        </Link>
                        <Link :href="route('admin.users.index')"
                            class="menu-btn border-orange-500 text-orange-400 bg-orange-900/10 hover:bg-orange-500 hover:text-black">
                            [!] USERS
                        </Link>
                       
                    </nav>
                </div>
            </div>

            <div class="col-span-12 lg:col-span-9 space-y-6">
                <div class="rpg-panel min-h-[450px]">
                    <div class="flex justify-between items-center mb-6 border-l-4 border-green-500 pl-3">
                        <div>
                            <h3 class="text-green-400 uppercase tracking-widest text-[12px]">Elite_Performers_Monitor
                            </h3>
                            <p class="text-[7px] text-slate-500 mt-1 italic">Target: Users with Avg. Grade > 75%</p>
                        </div>
                        <span
                            class="text-[8px] bg-green-900/30 text-green-400 p-1 border border-green-500">QUALIFIED</span>
                    </div>

                    <div class="w-full overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b-2 border-slate-800 text-slate-500 text-[8px]">
                                    <th class="py-3 px-2">HERO_NAME</th>
                                    <th class="py-3 px-2">LVL</th>
                                    <th class="py-3 px-2">QUESTS_DONE</th>
                                    <th class="py-3 px-2 text-right">AVG_GRADE</th>
                                </tr>
                            </thead>
                            <tbody class="text-[10px]">
                                <tr v-for="user in topUsers" :key="user.id"
                                    class="border-b border-slate-800/50 hover:bg-white/5 transition-colors">
                                    <td class="py-4 px-2 text-white uppercase">{{ user.name }}</td>
                                    <td class="py-4 px-2 text-cyan-500">{{ user.lvl }}</td>
                                    <td class="py-4 px-2 text-slate-400">{{ user.total_completed }} Missions</td>
                                    <td class="py-4 px-2 text-right font-bold" :class="getGradeColor(user.avg_grade)">
                                        {{ user.avg_grade }}%
                                    </td>
                                </tr>
                                <tr v-if="!topUsers || topUsers.length === 0">
                                    <td colspan="4" class="py-10 text-center text-slate-600 italic">
                                        No elite performers detected in this realm yet...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="rpg-panel h-32 overflow-y-auto border-slate-700 bg-black/20">
                    <h2 class="text-[8px] text-slate-500 mb-2 uppercase italic">Admin_Action_Log</h2>
                    <div class="space-y-1 text-[8px] text-green-500/70 font-mono">
                        <p v-for="(log, i) in recentLogs" :key="i">> {{ log }}</p>
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
