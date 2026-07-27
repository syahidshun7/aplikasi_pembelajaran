<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminNavbar from '@/Components/AdminNavbar.vue';
import { swal } from '@/Utils/Alert';
import { computed, ref } from 'vue';

const props = defineProps({
    group: Object,
    studentDashboard: {
        type: Array,
        default: () => [],
    },
    staffMembers: {
        type: Array,
        default: () => [],
    },
    availableStaff: {
        type: Array,
        default: () => [],
    },
    canManageStaffAccess: {
        type: Boolean,
        default: false,
    },
});

const staffForm = useForm({
    user_id: '',
    role_in_group: 'mentor',
});
const assignedStaffIds = computed(() => new Set((props.staffMembers || []).map((staff) => Number(staff.id))));
const assignableStaff = computed(() => (props.availableStaff || []).filter((staff) => !assignedStaffIds.value.has(Number(staff.id))));
const safeStudentCount = computed(() => props.studentDashboard.filter((student) => student.status === 'safe').length);
const attentionStudentCount = computed(() => props.studentDashboard.filter((student) => student.status === 'needs_attention').length);
const performanceStatusFilter = ref('all');
const performanceSortKey = ref('performance_average');
const performanceSortDirection = ref('desc');

const displayedStudents = computed(() => {
    const filtered = performanceStatusFilter.value === 'all'
        ? [...props.studentDashboard]
        : props.studentDashboard.filter((student) => student.status === performanceStatusFilter.value);
    const key = performanceSortKey.value;
    const direction = performanceSortDirection.value === 'asc' ? 1 : -1;

    return filtered.sort((left, right) => {
        const leftValue = left?.[key] ?? '';
        const rightValue = right?.[key] ?? '';

        if (['attendance_percentage', 'main_quest_average', 'performance_average'].includes(key)) {
            return (Number(leftValue) - Number(rightValue)) * direction;
        }

        return String(leftValue).localeCompare(String(rightValue), 'id', {
            sensitivity: 'base',
            numeric: true,
        }) * direction;
    });
});

const sortPerformanceBy = (key) => {
    if (performanceSortKey.value === key) {
        performanceSortDirection.value = performanceSortDirection.value === 'asc' ? 'desc' : 'asc';
        return;
    }

    performanceSortKey.value = key;
    performanceSortDirection.value = ['attendance_percentage', 'main_quest_average', 'performance_average'].includes(key)
        ? 'desc'
        : 'asc';
};

const sortIndicator = (key) => {
    if (performanceSortKey.value !== key) return 'SORT';
    return performanceSortDirection.value === 'asc' ? 'ASC' : 'DESC';
};

const percentageClass = (value) => {
    const percentage = Number(value || 0);
    if (percentage >= 75) return 'text-emerald-300';
    if (percentage >= 60) return 'text-yellow-300';
    return 'text-red-300';
};

const assignStaff = () => {
    if (!staffForm.user_id) return;
    staffForm.post(route('groups.staff.assign', { uuid: props.group.uuid }), {
        preserveScroll: true,
        onSuccess: () => staffForm.reset(),
    });
};

const removeStaff = (staff) => {
    swal.fire({
        title: 'REVOKE_ACCESS?',
        text: `Cabut akses ${staff.username || staff.name} dari group ini?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'REVOKE',
        cancelButtonText: 'CANCEL',
    }).then((result) => {
        if (!result.isConfirmed) return;
        router.delete(route('groups.staff.remove', { uuid: props.group.uuid, userId: staff.id }), {
            preserveScroll: true,
        });
    });
};

</script>

<template>
    <Head title="GROUP_DETAIL" />

    <div class="min-h-screen bg-[#0d1117] p-4 md:p-8 font-['Press_Start_2P'] text-[#4ed4d4] text-[10px]">
        <div class="max-w-7xl mx-auto space-y-8">
            <AdminNavbar />

            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3 border-b-4 border-emerald-900 pb-4">
                <div>
                    <h1 class="text-base sm:text-xl uppercase tracking-widest">Group_Detail</h1>
                    <p class="text-[8px] text-slate-500 mt-2 uppercase">
                        {{ group.name }} | ID: {{ group.uuid?.substring(0, 8) }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <a :href="route('groups.export-recap', { uuid: group.uuid })" class="inline-flex items-center justify-center px-3 py-2 border border-emerald-600 bg-emerald-900/40 text-emerald-300 hover:text-white uppercase text-[9px] sm:text-[10px]">[↓ Download Rekap CSV]</a>
                    <Link :href="route('groups.manage')" class="inline-flex items-center justify-center px-3 py-2 border border-slate-600 bg-slate-900/40 text-slate-300 hover:text-white uppercase text-[9px] sm:text-[10px]">[Back]</Link>
                </div>
            </div>

            <section class="rpg-panel border-emerald-500/50">
                <h2 class="mb-4 text-emerald-300 uppercase">Class_Operations</h2>
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <Link
                        :href="route('groups.quests.index', group.uuid)"
                        class="border border-yellow-600 bg-yellow-950/20 p-4 text-yellow-300 hover:bg-yellow-500 hover:text-black uppercase"
                    >
                        <p class="text-[10px]">Quest</p>
                        <p class="mt-2 text-[7px]">Manage class missions</p>
                    </Link>
                    <Link
                        :href="route('groups.guides.index', group.uuid)"
                        class="border border-indigo-600 bg-indigo-950/20 p-4 text-indigo-300 hover:bg-indigo-500 hover:text-black uppercase"
                    >
                        <p class="text-[10px]">Guide</p>
                        <p class="mt-2 text-[7px]">Manage class materials</p>
                    </Link>
                    <Link
                        :href="route('groups.events.index', group.uuid)"
                        class="border border-blue-600 bg-blue-950/20 p-4 text-blue-300 hover:bg-blue-500 hover:text-black uppercase"
                    >
                        <p class="text-[10px]">Event</p>
                        <p class="mt-2 text-[7px]">Manage class schedule</p>
                    </Link>
                    <Link
                        :href="route('groups.attendance', group.uuid)"
                        class="border border-emerald-600 bg-emerald-950/20 p-4 text-emerald-300 hover:bg-emerald-500 hover:text-black uppercase"
                    >
                        <p class="text-[10px]">Attendance</p>
                        <p class="mt-2 text-[7px]">Monitor class presence</p>
                    </Link>
                    <Link
                        :href="route('groups.join-requests', group.uuid)"
                        class="border border-orange-600 bg-orange-950/20 p-4 text-orange-300 hover:bg-orange-500 hover:text-black uppercase"
                    >
                        <p class="text-[10px]">Membership</p>
                        <p class="mt-2 text-[7px]">Requests and active members</p>
                    </Link>
                    <Link
                        :href="route('groups.roadmaps', group.uuid)"
                        class="border border-purple-600 bg-purple-950/20 p-4 text-purple-300 hover:bg-purple-500 hover:text-black uppercase"
                    >
                        <p class="text-[10px]">Roadmap</p>
                        <p class="mt-2 text-[7px]">Manage class curriculum</p>
                    </Link>
                </div>
            </section>

            <section class="rpg-panel border-cyan-500/50">
                <div class="mb-5 flex flex-col gap-4 border-b border-slate-700 pb-4 md:flex-row md:items-start md:justify-between">
                    <div>
                        <p class="text-[8px] uppercase text-cyan-300">Class_Health</p>
                        <h2 class="mt-3 text-[12px] uppercase text-white">Student_Performance_Dashboard</h2>
                        <p class="mt-3 font-sans text-[12px] leading-relaxed text-slate-400">
                            Status dihitung dari rata-rata persentase attendance dan nilai main quest. Nilai di bawah 75% perlu diperhatikan.
                        </p>
                    </div>
                    <div class="flex shrink-0 flex-wrap gap-2">
                        <span class="border border-emerald-600 bg-emerald-950/30 px-3 py-2 text-[8px] uppercase text-emerald-300">
                            Aman {{ safeStudentCount }}
                        </span>
                        <span class="border border-red-600 bg-red-950/30 px-3 py-2 text-[8px] uppercase text-red-300">
                            Perlu_Diperhatikan {{ attentionStudentCount }}
                        </span>
                    </div>
                </div>

                <div v-if="studentDashboard.length === 0" class="border border-slate-800 bg-black/30 p-6 text-center text-[8px] uppercase text-slate-500">
                    Belum ada user aktif di Study Group ini.
                </div>

                <div v-else class="mb-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-[7px] uppercase text-slate-500">
                        Showing {{ displayedStudents.length }} / {{ studentDashboard.length }} student
                    </p>
                    <select
                        v-model="performanceStatusFilter"
                        class="border-2 border-slate-700 bg-black p-2 text-[8px] uppercase text-cyan-300 outline-none focus:border-cyan-400 sm:min-w-[230px]"
                    >
                        <option value="all">ALL_STATUS</option>
                        <option value="safe">AMAN</option>
                        <option value="needs_attention">PERLU_DIPERHATIKAN</option>
                    </select>
                </div>

                <div v-if="studentDashboard.length > 0 && displayedStudents.length === 0" class="border border-slate-800 bg-black/30 p-6 text-center text-[8px] uppercase text-slate-500">
                    Tidak ada student untuk status ini.
                </div>

                <div v-else-if="studentDashboard.length > 0" class="overflow-x-auto border border-slate-800 bg-black/20">
                    <table class="min-w-full border-collapse">
                        <thead>
                            <tr class="border-b-2 border-slate-700 bg-slate-950/70 text-left text-[8px] uppercase text-slate-400">
                                <th class="w-[64px] p-3 text-center">No</th>
                                <th class="min-w-[160px] p-0">
                                    <button type="button" class="sort-header" @click="sortPerformanceBy('username')">
                                        <span>Username</span><span>{{ sortIndicator('username') }}</span>
                                    </button>
                                </th>
                                <th class="min-w-[220px] p-0">
                                    <button type="button" class="sort-header" @click="sortPerformanceBy('name')">
                                        <span>Nama_Lengkap</span><span>{{ sortIndicator('name') }}</span>
                                    </button>
                                </th>
                                <th class="min-w-[130px] p-0">
                                    <button type="button" class="sort-header justify-center" @click="sortPerformanceBy('attendance_percentage')">
                                        <span>Attendance</span><span>{{ sortIndicator('attendance_percentage') }}</span>
                                    </button>
                                </th>
                                <th class="min-w-[160px] p-0">
                                    <button type="button" class="sort-header justify-center" @click="sortPerformanceBy('main_quest_average')">
                                        <span>Main_Quest_Avg</span><span>{{ sortIndicator('main_quest_average') }}</span>
                                    </button>
                                </th>
                                <th class="min-w-[150px] p-0">
                                    <button type="button" class="sort-header justify-center" @click="sortPerformanceBy('performance_average')">
                                        <span>Combined_Avg</span><span>{{ sortIndicator('performance_average') }}</span>
                                    </button>
                                </th>
                                <th class="min-w-[260px] p-0">
                                    <button type="button" class="sort-header" @click="sortPerformanceBy('status')">
                                        <span>Status</span><span>{{ sortIndicator('status') }}</span>
                                    </button>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(student, index) in displayedStudents"
                                :key="student.id"
                                class="cursor-pointer border-b border-slate-800/80 hover:bg-cyan-500/10"
                                tabindex="0"
                                @click="router.visit(route('groups.students.detail', { uuid: group.uuid, userId: student.id }))"
                                @keydown.enter="router.visit(route('groups.students.detail', { uuid: group.uuid, userId: student.id }))"
                            >
                                <td class="p-3 text-center text-[9px] text-slate-500">{{ index + 1 }}</td>
                                <td class="p-3 text-[9px] text-cyan-300">@{{ student.username || 'user' }}</td>
                                <td class="p-3 font-sans text-[13px] font-semibold text-white">{{ student.name }}</td>
                                <td class="p-3 text-center text-[10px] font-bold" :class="percentageClass(student.attendance_percentage)">
                                    {{ student.attendance_percentage }}%
                                </td>
                                <td class="p-3 text-center text-[10px] font-bold" :class="percentageClass(student.main_quest_average)">
                                    {{ student.main_quest_average }}%
                                </td>
                                <td class="p-3 text-center text-[10px] font-bold" :class="percentageClass(student.performance_average)">
                                    {{ student.performance_average }}%
                                </td>
                                <td class="p-3">
                                    <span
                                        class="inline-flex border px-3 py-2 text-[7px] uppercase"
                                        :class="student.status === 'safe'
                                            ? 'border-emerald-600 bg-emerald-950/30 text-emerald-300'
                                            : 'border-red-600 bg-red-950/30 text-red-300'"
                                    >
                                        {{ student.status === 'safe' ? 'Aman' : 'Perlu_Diperhatikan' }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rpg-panel staff-access-panel min-w-0 overflow-hidden border-cyan-500/50">
                <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div class="min-w-0">
                        <h2 class="break-words text-[9px] uppercase leading-relaxed text-cyan-300 sm:text-[10px]">Staff_Access_Control</h2>
                        <p class="mt-2 font-sans text-[12px] leading-relaxed text-slate-300">
                            Admin dan mentor yang ada di panel ini menjadi pemilik akses operasional untuk kelas ini.
                        </p>
                    </div>
                </div>

                <form v-if="canManageStaffAccess" @submit.prevent="assignStaff" class="mb-4 grid min-w-0 grid-cols-1 gap-2 md:grid-cols-[minmax(0,1fr)_180px_auto]">
                    <select
                        v-model="staffForm.user_id"
                        class="min-w-0 w-full bg-black border-2 border-slate-700 p-2 text-[8px] text-cyan-300 uppercase focus:border-cyan-400 focus:ring-0"
                        required
                    >
                        <option value="" disabled>-- SELECT STAFF --</option>
                        <option v-for="staff in assignableStaff" :key="staff.id" :value="staff.id">
                            {{ staff.name }} / {{ staff.role }}
                        </option>
                    </select>
                    <select
                        v-model="staffForm.role_in_group"
                        class="min-w-0 w-full bg-black border-2 border-slate-700 p-2 text-[8px] text-cyan-300 uppercase focus:border-cyan-400 focus:ring-0"
                    >
                        <option value="mentor">Mentor</option>
                        <option value="admin">Admin</option>
                        <option value="viewer">Viewer</option>
                    </select>
                    <button
                        type="submit"
                        :disabled="staffForm.processing || !staffForm.user_id"
                        class="w-full border-2 border-cyan-500 px-4 py-3 text-[8px] uppercase text-cyan-300 hover:bg-cyan-400 hover:text-black disabled:opacity-40 md:w-auto md:py-2"
                    >
                        Grant
                    </button>
                </form>

                <div v-if="staffMembers.length === 0" class="border border-slate-800 bg-black/30 p-4 text-[8px] uppercase text-slate-500">
                    Belum ada staff yang di-assign khusus ke group ini.
                </div>

                <div v-else class="grid gap-3 md:grid-cols-2">
                    <article v-for="staff in staffMembers" :key="staff.id" class="flex min-w-0 flex-col gap-4 border border-slate-800 bg-black/40 p-3 sm:flex-row sm:items-start sm:justify-between sm:gap-3">
                        <div class="min-w-0 w-full">
                            <p class="break-words text-[10px] uppercase text-white">{{ staff.name }}</p>
                            <p class="mt-1 break-all font-sans text-[11px] leading-relaxed text-slate-500">{{ staff.email }}</p>
                            <p class="mt-2 break-words text-[7px] uppercase leading-relaxed text-cyan-300">
                                System {{ staff.role }} | Group {{ staff.role_in_group }}
                            </p>
                        </div>
                        <button
                            v-if="canManageStaffAccess"
                            type="button"
                            class="w-full shrink-0 border border-red-600 px-3 py-3 text-[8px] uppercase text-red-400 hover:bg-red-600 hover:text-white sm:w-auto sm:py-2"
                            @click="removeStaff(staff)"
                        >
                            Revoke
                        </button>
                    </article>
                </div>
            </section>

            <section v-if="false" class="rpg-panel border-indigo-500/50">
                <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div>
                        <h2 class="text-indigo-300 uppercase">Class_Roadmap_View_Only</h2>
                        <p class="mt-2 font-sans text-[12px] leading-relaxed text-slate-300">
                            Roadmap di sini tampil di detail kelas sebagai kurikulum view-only. Tidak membuat enrollment atau progress siswa.
                        </p>
                    </div>
                </div>

                <div class="mb-4 border border-slate-800 bg-black/20 p-3">
                    <p class="mb-3 text-[8px] uppercase text-slate-400">Roadmap yang bisa dipasang</p>

                    <div v-if="attachableRoadmaps.length === 0" class="text-[8px] uppercase text-slate-500">
                        Semua roadmap published sudah terpasang, atau belum ada roadmap published.
                    </div>

                    <div v-else class="grid gap-2 md:grid-cols-2">
                        <article v-for="roadmap in attachableRoadmaps" :key="roadmap.uuid" class="flex items-start justify-between gap-3 border border-slate-800 bg-black/30 p-3">
                            <div class="min-w-0">
                                <p class="break-words text-[9px] uppercase text-white">{{ roadmap.title }}</p>
                                <p class="mt-2 line-clamp-2 font-sans text-[12px] leading-relaxed text-slate-400">
                                    {{ roadmap.description || 'Tidak ada deskripsi.' }}
                                </p>
                            </div>
                            <button
                                type="button"
                                :disabled="roadmapForm.processing"
                                class="shrink-0 border border-indigo-500 px-3 py-2 text-[8px] uppercase text-indigo-300 hover:bg-indigo-500 hover:text-black disabled:opacity-40"
                                @click="attachRoadmap(roadmap)"
                            >
                                Attach
                            </button>
                        </article>
                    </div>
                </div>

                <div v-if="attachedRoadmaps.length === 0" class="border border-slate-800 bg-black/30 p-4 text-[8px] uppercase text-slate-500">
                    Belum ada roadmap view-only untuk group ini.
                </div>

                <div v-else class="grid gap-3 md:grid-cols-2">
                    <article v-for="roadmap in attachedRoadmaps" :key="roadmap.uuid" class="border border-slate-800 bg-black/40 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="break-words text-[10px] uppercase text-white">{{ roadmap.title }}</p>
                                <p class="mt-2 font-sans text-[12px] leading-relaxed text-slate-400 line-clamp-2">
                                    {{ roadmap.description || 'Tidak ada deskripsi.' }}
                                </p>
                                <p class="mt-3 text-[7px] uppercase text-indigo-300">
                                    Order {{ roadmap.sort_order || 0 }} | {{ roadmap.is_active ? 'Active' : 'Inactive' }}
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

            <section v-if="false" class="rpg-panel border-emerald-500/50">
                <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div>
                        <h2 class="text-emerald-300 uppercase">Attendance_Dashboard</h2>
                        <p class="mt-2 font-sans text-[12px] leading-relaxed text-slate-300">
                            Monitoring kehadiran siswa pada event kelas. P = Present, A = Absent, I = Izin, - = Pending.
                        </p>
                    </div>
                </div>

                <div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    <div class="border border-slate-800 bg-black/30 p-3">
                        <p class="text-[7px] uppercase text-slate-500">Events</p>
                        <p class="mt-2 text-lg text-white">{{ attendanceSummary.total_events || 0 }}</p>
                    </div>
                    <div class="border border-slate-800 bg-black/30 p-3">
                        <p class="text-[7px] uppercase text-slate-500">Students</p>
                        <p class="mt-2 text-lg text-white">{{ attendanceSummary.total_students || 0 }}</p>
                    </div>
                    <div class="border border-emerald-800 bg-emerald-950/20 p-3">
                        <p class="text-[7px] uppercase text-slate-500">Class Rate</p>
                        <p class="mt-2 text-lg" :class="rateClass(attendanceSummary.class_attendance_rate)">
                            {{ attendanceSummary.class_attendance_rate || 0 }}%
                        </p>
                    </div>
                    <div class="border border-red-800 bg-red-950/20 p-3">
                        <p class="text-[7px] uppercase text-slate-500">Low Rate</p>
                        <p class="mt-2 text-lg text-red-300">{{ attendanceSummary.low_attendance_students || 0 }}</p>
                    </div>
                    <div class="border border-yellow-800 bg-yellow-950/20 p-3">
                        <p class="text-[7px] uppercase text-slate-500">Lowest Event</p>
                        <p class="mt-2 break-words font-sans text-[12px] leading-relaxed text-yellow-200">
                            {{ attendanceSummary.worst_event?.title || '-' }}
                            <span v-if="attendanceSummary.worst_event">({{ attendanceSummary.worst_event.attendance_rate }}%)</span>
                        </p>
                    </div>
                </div>

                <div v-if="attendanceEvents.length === 0" class="border border-slate-800 bg-black/30 p-4 text-[8px] uppercase text-slate-500">
                    Belum ada event kelas untuk dashboard attendance.
                </div>

                <div v-else class="overflow-x-auto border border-slate-800 bg-black/20">
                    <table class="min-w-full border-collapse font-sans text-[12px]">
                        <thead>
                            <tr class="bg-slate-950/80 text-left text-[10px] uppercase text-slate-300">
                                <th class="sticky left-0 z-20 min-w-[220px] border-b border-r border-slate-800 bg-slate-950/95 p-3">Student</th>
                                <th class="min-w-[92px] border-b border-r border-slate-800 p-3 text-center">Rate</th>
                                <th
                                    v-for="eventItem in attendanceEvents"
                                    :key="eventItem.uuid"
                                    class="min-w-[120px] border-b border-r border-slate-800 p-2 text-center align-top"
                                >
                                    <Link :href="eventItem.attendance_url" class="block text-cyan-300 hover:text-white">
                                        <span class="line-clamp-2 break-words">{{ eventItem.title }}</span>
                                    </Link>
                                    <span class="mt-1 block text-[9px] text-slate-500">{{ formatShortDate(eventItem.starts_at) }}</span>
                                    <span class="mt-1 block text-[9px]" :class="rateClass(eventItem.attendance_rate)">
                                        {{ eventItem.attendance_rate }}%
                                    </span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="student in attendanceStudents" :key="student.id" class="border-b border-slate-900/80 hover:bg-slate-900/40">
                                <td class="sticky left-0 z-10 border-r border-slate-800 bg-[#111827] p-3">
                                    <p class="break-words text-[12px] font-bold uppercase text-white">{{ student.name }}</p>
                                    <p class="mt-1 break-words text-[10px] text-slate-500">@{{ student.username || 'user' }}</p>
                                    <p class="mt-2 text-[10px] text-slate-400">
                                        P {{ student.counts?.present || 0 }} / A {{ student.counts?.absent || 0 }} / I {{ student.counts?.excused || 0 }} / - {{ student.counts?.pending || 0 }}
                                    </p>
                                </td>
                                <td class="border-r border-slate-800 p-3 text-center font-bold" :class="rateClass(student.attendance_rate)">
                                    {{ student.attendance_rate }}%
                                </td>
                                <td
                                    v-for="eventStatus in student.events"
                                    :key="`${student.id}-${eventStatus.event_uuid}`"
                                    class="border-r border-slate-800 p-2 text-center"
                                >
                                    <span
                                        class="inline-flex h-8 min-w-8 items-center justify-center border px-2 text-[10px] font-bold uppercase"
                                        :class="attendanceStatusClass(eventStatus.status)"
                                        :title="eventStatus.status"
                                    >
                                        {{ attendanceStatusLabel(eventStatus.status) }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <div v-if="false" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <section v-if="false" class="rpg-panel border-orange-500/50">
                    <h2 class="text-orange-400 mb-4 uppercase">Pending_Join_Requests [{{ requests.length }}]</h2>

                    <div v-if="requests.length === 0" class="text-slate-500 uppercase text-[8px] py-4">
                        NO_PENDING_REQUEST
                    </div>

                    <div v-else class="space-y-3">
                        <div v-for="r in requests" :key="r.id" class="p-3 bg-black/40 border border-slate-800">
                            <p class="text-white uppercase">{{ r.user?.username || r.user?.name }}</p>
                            <p class="text-[8px] text-slate-500 mb-3">{{ r.user?.email }}</p>
                            <div class="mb-3 flex flex-wrap gap-2">
                                <span class="px-2 py-1 border border-cyan-800 bg-cyan-900/20 text-cyan-200 text-[8px] uppercase">User LVL {{ r.user_level || 1 }}</span>
                                <span class="px-2 py-1 border border-yellow-800 bg-yellow-900/20 text-yellow-200 text-[8px] uppercase">Need LVL {{ group.min_level || 1 }}</span>
                            </div>
                            <div class="mb-3 border border-cyan-900/60 bg-cyan-950/20 p-2">
                                <p class="text-[8px] uppercase text-cyan-300">Reason</p>
                                <p class="mt-1 font-sans text-[12px] leading-relaxed text-slate-200 break-words">
                                    {{ r.reason || '-' }}
                                </p>
                            </div>
                            <div class="flex gap-2">
                                <button @click="approveRequest(r.id)"
                                    class="px-3 py-2 border border-emerald-500 text-emerald-400 hover:bg-emerald-500 hover:text-black uppercase text-[8px]">
                                    Approve
                                </button>
                                <button @click="rejectRequest(r)"
                                    class="px-3 py-2 border border-red-500 text-red-400 hover:bg-red-600 hover:text-white uppercase text-[8px]">
                                    Reject
                                </button>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="rpg-panel border-cyan-500/50 lg:col-span-2">
                <h2 class="text-cyan-400 mb-4 uppercase">
                    Members [{{ members.length }} / {{ group.max_members }}]
                </h2>
                <p class="text-[8px] text-slate-400 uppercase mb-4">Min Join Level: {{ group.min_level || 1 }}</p>

                    <div v-if="members.length === 0" class="text-slate-500 uppercase text-[8px] py-4">
                        NO_MEMBER
                    </div>

                    <div v-else class="space-y-3">
                        <div v-for="m in members" :key="m.id" class="p-3 bg-black/40 border border-slate-800 flex items-center justify-between gap-3">
                            <div>
                                <p class="text-white uppercase">{{ m.username || m.name }}</p>
                                <p class="text-[8px] text-slate-500">{{ m.email }}</p>
                            </div>
                            <button @click="removeMember(m)"
                                class="px-3 py-2 border border-red-600 text-red-500 hover:bg-red-600 hover:text-white uppercase text-[8px]">
                                Remove
                            </button>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
</template>

<style scoped>
.rpg-panel {
    background-color: #1a1c2c;
    border-width: 4px;
    padding: 1rem;
    box-shadow: 8px 8px 0 0 rgba(0, 0, 0, 0.5);
}

.sort-header {
    display: flex;
    width: 100%;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.75rem;
    color: #94a3b8;
    text-align: left;
    text-transform: uppercase;
    transition: color 150ms ease, background-color 150ms ease;
}

.sort-header:hover {
    background: rgba(34, 211, 238, 0.08);
    color: #67e8f9;
}

.sort-header span:last-child {
    color: #22d3ee;
    font-size: 6px;
}

@media (max-width: 639px) {
    .staff-access-panel {
        padding: 0.75rem;
        box-shadow: 4px 4px 0 0 rgba(0, 0, 0, 0.5);
    }
}
</style>
