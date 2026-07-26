<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminNavbar from '@/Components/AdminNavbar.vue';

const props = defineProps({
    group: Object,
    attendanceDashboard: {
        type: Object,
        default: () => ({ summary: {}, events: [], students: [] }),
    },
});

const attendanceEvents = computed(() => props.attendanceDashboard?.events || []);
const attendanceStudents = computed(() => props.attendanceDashboard?.students || []);
const summary = computed(() => props.attendanceDashboard?.summary || {});

const statusClass = (status) => {
    if (status === 'present') return 'border-emerald-600 bg-emerald-500/15 text-emerald-300';
    if (status === 'absent') return 'border-red-600 bg-red-500/15 text-red-300';
    if (status === 'excused') return 'border-cyan-600 bg-cyan-500/15 text-cyan-300';
    return 'border-slate-700 bg-slate-800/40 text-slate-400';
};

const statusLabel = (status) => {
    if (status === 'present') return 'P';
    if (status === 'absent') return 'A';
    if (status === 'excused') return 'I';
    return '-';
};

const rateClass = (rate) => {
    const value = Number(rate || 0);
    if (value >= 85) return 'text-emerald-300';
    if (value >= 75) return 'text-yellow-300';
    return 'text-red-300';
};

const shortDate = (value) => {
    if (!value) return 'No_Date';
    const date = new Date(value);
    return Number.isNaN(date.getTime())
        ? 'No_Date'
        : date.toLocaleDateString('id-ID', { day: '2-digit', month: 'short' });
};
</script>

<template>
    <Head :title="`ATTENDANCE | ${group.name}`" />

    <div class="min-h-screen bg-[#0d1117] p-4 font-['Press_Start_2P'] text-[10px] text-[#4ed4d4] md:p-8">
        <div class="mx-auto max-w-7xl space-y-6">
            <AdminNavbar />

            <header class="rpg-panel border-emerald-500/50">
                <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div>
                        <p class="text-[8px] uppercase text-emerald-300">Class_Operations</p>
                        <h1 class="mt-3 text-base uppercase text-white md:text-xl">Attendance_Dashboard</h1>
                        <p class="mt-3 font-sans text-[13px] text-slate-400">{{ group.name }}</p>
                    </div>
                    <Link :href="route('groups.detail', group.uuid)" class="border border-slate-600 px-3 py-2 text-[8px] uppercase text-slate-300 hover:border-cyan-400 hover:text-white">
                        Back_To_Class
                    </Link>
                </div>
            </header>

            <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <div class="metric"><p>Events</p><strong>{{ summary.total_events || 0 }}</strong></div>
                <div class="metric"><p>Students</p><strong>{{ summary.total_students || 0 }}</strong></div>
                <div class="metric"><p>Class_Rate</p><strong :class="rateClass(summary.class_attendance_rate)">{{ summary.class_attendance_rate || 0 }}%</strong></div>
                <div class="metric"><p>Low_Rate</p><strong class="text-red-300">{{ summary.low_attendance_students || 0 }}</strong></div>
                <div class="metric">
                    <p>Lowest_Event</p>
                    <span class="mt-3 block break-words font-sans text-[12px] text-yellow-200">
                        {{ summary.worst_event?.title || '-' }}
                        <template v-if="summary.worst_event"> ({{ summary.worst_event.attendance_rate }}%)</template>
                    </span>
                </div>
            </section>

            <section class="rpg-panel border-emerald-500/50">
                <p class="mb-4 font-sans text-[12px] text-slate-400">P = Present, A = Absent, I = Izin, - = Pending.</p>

                <div v-if="attendanceEvents.length === 0" class="border border-slate-800 bg-black/30 p-5 text-[8px] uppercase text-slate-500">
                    Belum ada event kelas untuk dashboard attendance.
                </div>
                <div v-else class="overflow-x-auto border border-slate-800 bg-black/20">
                    <table class="min-w-full border-collapse font-sans text-[12px]">
                        <thead>
                            <tr class="bg-slate-950/80 text-left text-[10px] uppercase text-slate-300">
                                <th class="sticky left-0 z-20 min-w-[220px] border-b border-r border-slate-800 bg-slate-950/95 p-3">Student</th>
                                <th class="min-w-[92px] border-b border-r border-slate-800 p-3 text-center">Rate</th>
                                <th v-for="event in attendanceEvents" :key="event.uuid" class="min-w-[120px] border-b border-r border-slate-800 p-2 text-center align-top">
                                    <Link :href="event.attendance_url" class="block text-cyan-300 hover:text-white">{{ event.title }}</Link>
                                    <span class="mt-1 block text-[9px] text-slate-500">{{ shortDate(event.starts_at) }}</span>
                                    <span class="mt-1 block text-[9px]" :class="rateClass(event.attendance_rate)">{{ event.attendance_rate }}%</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="student in attendanceStudents" :key="student.id" class="border-b border-slate-900/80 hover:bg-slate-900/40">
                                <td class="sticky left-0 z-10 border-r border-slate-800 bg-[#111827] p-3">
                                    <p class="font-bold uppercase text-white">{{ student.name }}</p>
                                    <p class="mt-1 text-[10px] text-slate-500">@{{ student.username || 'user' }}</p>
                                    <p class="mt-2 text-[10px] text-slate-400">P {{ student.counts?.present || 0 }} / A {{ student.counts?.absent || 0 }} / I {{ student.counts?.excused || 0 }} / - {{ student.counts?.pending || 0 }}</p>
                                </td>
                                <td class="border-r border-slate-800 p-3 text-center font-bold" :class="rateClass(student.attendance_rate)">{{ student.attendance_rate }}%</td>
                                <td v-for="eventStatus in student.events" :key="`${student.id}-${eventStatus.event_uuid}`" class="border-r border-slate-800 p-2 text-center">
                                    <span class="inline-flex h-8 min-w-8 items-center justify-center border px-2 text-[10px] font-bold" :class="statusClass(eventStatus.status)" :title="eventStatus.status">
                                        {{ statusLabel(eventStatus.status) }}
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
.rpg-panel { @apply border-4 bg-[#1a1c2c] p-5; box-shadow: 8px 8px 0 rgba(0, 0, 0, .5); }
.metric { @apply border-4 border-slate-700 bg-[#1a1c2c] p-4; box-shadow: 6px 6px 0 rgba(0, 0, 0, .45); }
.metric p { @apply text-[7px] uppercase text-slate-500; }
.metric strong { @apply mt-3 block text-lg; }
</style>
