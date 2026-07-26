<script setup>
import AdminNavbar from '@/Components/AdminNavbar.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    group: Object,
    student: Object,
    summary: Object,
    questHistory: {
        type: Array,
        default: () => [],
    },
    attendanceHistory: {
        type: Array,
        default: () => [],
    },
});

const questSearch = ref('');
const questTypeFilter = ref('all');
const questProgressFilter = ref('all');
const attendanceSearch = ref('');
const attendanceStatusFilter = ref('all');

const filteredQuestHistory = computed(() => {
    const keyword = questSearch.value.trim().toLowerCase();

    return props.questHistory.filter((quest) => {
        const matchesSearch = keyword === ''
            || String(quest.title || '').toLowerCase().includes(keyword)
            || String(quest.difficulty || '').toLowerCase().includes(keyword);
        const matchesType = questTypeFilter.value === 'all'
            || quest.quest_type === questTypeFilter.value;
        const isCompleted = Number(quest.attempts || 0) > 0;
        const matchesProgress = questProgressFilter.value === 'all'
            || (questProgressFilter.value === 'completed' && isCompleted)
            || (questProgressFilter.value === 'not_started' && !isCompleted);

        return matchesSearch && matchesType && matchesProgress;
    });
});

const filteredAttendanceHistory = computed(() => {
    const keyword = attendanceSearch.value.trim().toLowerCase();

    return props.attendanceHistory.filter((attendance) => {
        const matchesSearch = keyword === ''
            || String(attendance.title || '').toLowerCase().includes(keyword);
        const matchesStatus = attendanceStatusFilter.value === 'all'
            || attendance.status === attendanceStatusFilter.value;

        return matchesSearch && matchesStatus;
    });
});

const percentageClass = (value) => {
    const percentage = Number(value || 0);
    if (percentage >= 75) return 'text-emerald-300';
    if (percentage >= 60) return 'text-yellow-300';
    return 'text-red-300';
};

const statusClass = (status) => ({
    present: 'border-emerald-600 bg-emerald-950/30 text-emerald-300',
    absent: 'border-red-600 bg-red-950/30 text-red-300',
    excused: 'border-yellow-600 bg-yellow-950/30 text-yellow-300',
    pending: 'border-slate-600 bg-slate-900/50 text-slate-400',
}[status] || 'border-slate-600 bg-slate-900/50 text-slate-400');

const formatDate = (value) => {
    if (!value) return '-';
    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
};
</script>

<template>
    <Head :title="`STUDENT_DETAIL_${student.username || student.id}`" />

    <div class="min-h-screen bg-[#0d1117] p-4 font-['Press_Start_2P'] text-[10px] text-[#4ed4d4] md:p-8">
        <div class="mx-auto max-w-7xl space-y-8">
            <AdminNavbar />

            <header class="flex flex-col gap-4 border-b-4 border-cyan-900 pb-5 md:flex-row md:items-end md:justify-between">
                <div>
                    <p class="text-[8px] uppercase text-cyan-400/70">Student_Performance_Profile</p>
                    <h1 class="mt-3 text-base uppercase text-cyan-200 sm:text-xl">{{ student.name }}</h1>
                    <p class="mt-3 text-[8px] uppercase text-slate-500">
                        @{{ student.username || 'user' }} | {{ group.name }}
                    </p>
                </div>
                <Link
                    :href="route('groups.detail', group.uuid)"
                    class="inline-flex items-center justify-center border border-slate-600 bg-slate-900/40 px-4 py-3 text-[8px] uppercase text-slate-300 hover:text-white"
                >
                    Back_To_Group
                </Link>
            </header>

            <section class="rpg-panel border-cyan-500/50">
                <div class="grid gap-5 lg:grid-cols-[1fr_2fr]">
                    <div class="border border-slate-700 bg-black/30 p-5">
                        <p class="text-[8px] uppercase text-cyan-300">Account_Information</p>
                        <h2 class="mt-4 font-sans text-xl font-bold text-white">{{ student.name }}</h2>
                        <div class="mt-5 space-y-3 font-sans text-[13px] text-slate-300">
                            <p><span class="text-slate-500">Username:</span> @{{ student.username || '-' }}</p>
                            <p><span class="text-slate-500">Email:</span> {{ student.email }}</p>
                            <p><span class="text-slate-500">Level:</span> {{ student.level }}</p>
                            <p><span class="text-slate-500">EXP:</span> {{ student.exp }}</p>
                            <p><span class="text-slate-500">Gold:</span> {{ student.gold }}</p>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <div class="metric-card">
                            <p>Attendance_Avg</p>
                            <strong :class="percentageClass(summary.attendance_percentage)">{{ summary.attendance_percentage }}%</strong>
                        </div>
                        <div class="metric-card">
                            <p>Main_Quest_Avg</p>
                            <strong :class="percentageClass(summary.main_quest_average)">{{ summary.main_quest_average }}%</strong>
                        </div>
                        <div class="metric-card">
                            <p>Combined_Avg</p>
                            <strong :class="percentageClass(summary.performance_average)">{{ summary.performance_average }}%</strong>
                        </div>
                        <div class="metric-card">
                            <p>Status</p>
                            <span
                                class="mt-5 inline-flex border px-3 py-2 text-[7px] uppercase"
                                :class="summary.status === 'safe'
                                    ? 'border-emerald-600 bg-emerald-950/30 text-emerald-300'
                                    : 'border-red-600 bg-red-950/30 text-red-300'"
                            >
                                {{ summary.status === 'safe' ? 'Aman' : 'Perlu_Diperhatikan' }}
                            </span>
                        </div>
                    </div>
                </div>
            </section>

            <section class="rpg-panel border-yellow-500/50">
                <div class="mb-5 border-b border-slate-700 pb-4">
                    <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                        <div>
                            <p class="text-[8px] uppercase text-yellow-300">Quest_History</p>
                            <h2 class="mt-3 text-[12px] uppercase text-white">Seluruh Quest Study Group</h2>
                        </div>
                        <div class="grid gap-2 sm:grid-cols-3 xl:w-[680px]">
                            <input
                                v-model="questSearch"
                                type="search"
                                placeholder="SEARCH_QUEST..."
                                class="filter-control sm:col-span-1"
                            >
                            <select v-model="questTypeFilter" class="filter-control">
                                <option value="all">ALL_TYPES</option>
                                <option value="main">MAIN</option>
                                <option value="optional">OPTIONAL</option>
                            </select>
                            <select v-model="questProgressFilter" class="filter-control">
                                <option value="all">ALL_PROGRESS</option>
                                <option value="completed">SUDAH_DIKERJAKAN</option>
                                <option value="not_started">BELUM_DIKERJAKAN</option>
                            </select>
                        </div>
                    </div>
                    <p class="mt-3 text-[7px] uppercase text-slate-500">
                        Showing {{ filteredQuestHistory.length }} / {{ questHistory.length }} quest
                    </p>
                </div>

                <div v-if="questHistory.length === 0" class="empty-state">Belum ada quest di Study Group ini.</div>
                <div v-else-if="filteredQuestHistory.length === 0" class="empty-state">Quest tidak ditemukan untuk filter ini.</div>
                <div v-else class="overflow-x-auto">
                    <table class="min-w-full border-collapse">
                        <thead>
                            <tr class="table-head">
                                <th class="p-3">Quest</th>
                                <th class="p-3">Type</th>
                                <th class="p-3">Difficulty</th>
                                <th class="p-3 text-center">Attempts</th>
                                <th class="p-3 text-center">Nilai_Terakhir</th>
                                <th class="p-3">Status</th>
                                <th class="p-3">Submitted_At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="quest in filteredQuestHistory" :key="quest.uuid" class="table-row">
                                <td class="p-3 font-sans text-[13px] font-semibold text-white">{{ quest.title }}</td>
                                <td class="p-3 uppercase" :class="quest.quest_type === 'main' ? 'text-yellow-300' : 'text-purple-300'">{{ quest.quest_type }}</td>
                                <td class="p-3 uppercase text-slate-300">{{ quest.difficulty || '-' }}</td>
                                <td class="p-3 text-center text-cyan-300">{{ quest.attempts }}</td>
                                <td class="p-3 text-center text-[11px] font-bold" :class="percentageClass(quest.grade)">
                                    {{ quest.attempts === 0 ? '-' : (quest.grade === null ? 'Pending' : `${quest.grade}%`) }}
                                </td>
                                <td
                                    class="p-3 uppercase"
                                    :class="quest.attempts === 0 ? 'text-red-300' : 'text-slate-300'"
                                >
                                    {{ quest.attempts === 0 ? 'Belum_Dikerjakan' : (quest.status || '-') }}
                                </td>
                                <td class="p-3 font-sans text-[12px] text-slate-400">{{ formatDate(quest.submitted_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rpg-panel border-emerald-500/50">
                <div class="mb-5 border-b border-slate-700 pb-4">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <p class="text-[8px] uppercase text-emerald-300">Attendance_History</p>
                            <h2 class="mt-3 text-[12px] uppercase text-white">Riwayat Kehadiran</h2>
                        </div>
                        <div class="grid gap-2 sm:grid-cols-2 lg:w-[460px]">
                            <input
                                v-model="attendanceSearch"
                                type="search"
                                placeholder="SEARCH_EVENT..."
                                class="filter-control"
                            >
                            <select v-model="attendanceStatusFilter" class="filter-control">
                                <option value="all">ALL_STATUS</option>
                                <option value="present">PRESENT</option>
                                <option value="absent">ABSENT</option>
                                <option value="excused">EXCUSED</option>
                                <option value="pending">PENDING</option>
                            </select>
                        </div>
                    </div>
                    <p class="mt-3 text-[7px] uppercase text-slate-500">
                        Showing {{ filteredAttendanceHistory.length }} / {{ attendanceHistory.length }} event
                    </p>
                </div>

                <div v-if="attendanceHistory.length === 0" class="empty-state">Belum ada event attendance di kelas ini.</div>
                <div v-else-if="filteredAttendanceHistory.length === 0" class="empty-state">Attendance tidak ditemukan untuk filter ini.</div>
                <div v-else class="overflow-x-auto">
                    <table class="min-w-full border-collapse">
                        <thead>
                            <tr class="table-head">
                                <th class="p-3">Event</th>
                                <th class="p-3">Jadwal</th>
                                <th class="p-3">Checked_At</th>
                                <th class="p-3">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="attendance in filteredAttendanceHistory" :key="attendance.uuid" class="table-row">
                                <td class="p-3 font-sans text-[13px] font-semibold text-white">{{ attendance.title }}</td>
                                <td class="p-3 font-sans text-[12px] text-slate-300">{{ formatDate(attendance.starts_at) }}</td>
                                <td class="p-3 font-sans text-[12px] text-slate-400">{{ formatDate(attendance.checked_at) }}</td>
                                <td class="p-3">
                                    <span class="inline-flex border px-3 py-2 text-[7px] uppercase" :class="statusClass(attendance.status)">
                                        {{ attendance.status }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</template>

<style scoped>
.rpg-panel {
    position: relative;
    border-width: 4px;
    background: #1a1c2c;
    padding: 1rem;
    box-shadow: 8px 8px 0 rgba(0, 0, 0, 0.5);
}

.metric-card {
    min-height: 132px;
    border: 1px solid #334155;
    background: rgba(2, 6, 23, 0.45);
    padding: 1rem;
}

.metric-card p {
    color: #94a3b8;
    font-size: 7px;
    text-transform: uppercase;
}

.metric-card strong {
    display: block;
    margin-top: 1.25rem;
    font-family: sans-serif;
    font-size: 28px;
    font-weight: 900;
}

.table-head {
    border-bottom: 2px solid #334155;
    background: rgba(2, 6, 23, 0.7);
    color: #94a3b8;
    font-size: 8px;
    text-align: left;
    text-transform: uppercase;
}

.table-row {
    border-bottom: 1px solid rgba(51, 65, 85, 0.8);
}

.table-row:hover {
    background: rgba(255, 255, 255, 0.04);
}

.empty-state {
    border: 1px solid #1e293b;
    background: rgba(0, 0, 0, 0.3);
    padding: 1.5rem;
    color: #64748b;
    font-size: 8px;
    text-align: center;
    text-transform: uppercase;
}

.filter-control {
    width: 100%;
    border: 2px solid #475569;
    border-radius: 0;
    background: #0d1117;
    padding: 0.7rem;
    color: #cbd5e1;
    font-size: 8px;
    text-transform: uppercase;
}

.filter-control:focus {
    border-color: #22d3ee;
    outline: none;
    box-shadow: none;
}

.filter-control::placeholder {
    color: #64748b;
}
</style>
