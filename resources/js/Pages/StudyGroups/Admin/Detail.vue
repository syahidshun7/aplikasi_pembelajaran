<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminNavbar from '@/Components/AdminNavbar.vue';
import { swal } from '@/Utils/Alert';
import { computed } from 'vue';

const props = defineProps({
    group: Object,
    members: Array,
    requests: Array,
    questCounts: Object,
    attachedRoadmaps: {
        type: Array,
        default: () => [],
    },
    availableRoadmaps: {
        type: Array,
        default: () => [],
    },
    attendanceDashboard: {
        type: Object,
        default: () => ({
            summary: {},
            events: [],
            students: [],
        }),
    },
});

const roadmapForm = useForm({
    roadmap_uuid: '',
    sort_order: 0,
});

const attachedRoadmapUuidSet = computed(() => new Set((props.attachedRoadmaps || []).map((roadmap) => String(roadmap.uuid))));

const attachableRoadmaps = computed(() => {
    return (props.availableRoadmaps || []).filter((roadmap) => !attachedRoadmapUuidSet.value.has(String(roadmap.uuid)));
});

const nextRoadmapSortOrder = computed(() => {
    const maxOrder = Math.max(0, ...(props.attachedRoadmaps || []).map((roadmap) => Number(roadmap.sort_order || 0)));
    return maxOrder + 1;
});

const attendanceEvents = computed(() => props.attendanceDashboard?.events || []);
const attendanceStudents = computed(() => props.attendanceDashboard?.students || []);
const attendanceSummary = computed(() => props.attendanceDashboard?.summary || {});

const attendanceStatusClass = (status) => {
    const value = String(status || 'pending');
    if (value === 'present') return 'border-emerald-600 bg-emerald-500/15 text-emerald-300';
    if (value === 'absent') return 'border-red-600 bg-red-500/15 text-red-300';
    if (value === 'excused') return 'border-cyan-600 bg-cyan-500/15 text-cyan-300';
    return 'border-slate-700 bg-slate-800/40 text-slate-400';
};

const attendanceStatusLabel = (status) => {
    const value = String(status || 'pending');
    if (value === 'present') return 'P';
    if (value === 'absent') return 'A';
    if (value === 'excused') return 'I';
    return '-';
};

const rateClass = (rate) => {
    const value = Number(rate || 0);
    if (value >= 85) return 'text-emerald-300';
    if (value >= 75) return 'text-yellow-300';
    return 'text-red-300';
};

const formatShortDate = (value) => {
    if (!value) return 'No_Date';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return 'No_Date';
    return date.toLocaleDateString('id-ID', { day: '2-digit', month: 'short' });
};

const attachRoadmap = (roadmap) => {
    if (!roadmap?.uuid) return;
    roadmapForm.roadmap_uuid = String(roadmap.uuid);
    roadmapForm.sort_order = nextRoadmapSortOrder.value;
    roadmapForm.post(route('groups.roadmaps.attach', { uuid: props.group.uuid }), {
        preserveScroll: true,
        onSuccess: () => roadmapForm.reset(),
    });
};

const detachRoadmap = (roadmap) => {
    swal.fire({
        title: 'DETACH_ROADMAP?',
        text: `Lepas ${roadmap.title || 'roadmap'} dari group ini?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'DETACH',
        cancelButtonText: 'CANCEL',
    }).then((result) => {
        if (!result.isConfirmed) return;
        router.delete(route('groups.roadmaps.detach', {
            uuid: props.group.uuid,
            roadmapUuid: roadmap.uuid,
        }), {
            preserveScroll: true,
        });
    });
};

const approveRequest = (requestId) => {
    router.post(route('groups.requests.approve', { uuid: props.group.uuid, requestId }), {}, {
        preserveScroll: true,
    });
};

const rejectRequest = async (requestItem) => {
    const result = await swal.fire({
        title: 'REJECT_REQUEST',
        text: `Kamu bisa isi alasan reject untuk ${requestItem.user?.username || requestItem.user?.name || 'user'} (opsional).`,
        input: 'textarea',
        inputPlaceholder: 'Alasan reject (opsional)',
        inputClass: 'rpg-alert-textarea',
        inputAttributes: {
            maxlength: 500,
        },
        showCancelButton: true,
        confirmButtonText: 'REJECT',
        cancelButtonText: 'BATAL',
    });

    if (!result.isConfirmed) {
        return;
    }

    router.post(route('groups.requests.reject', { uuid: props.group.uuid, requestId: requestItem.id }), {
        reason: String(result.value || '').trim() || null,
    }, {
        preserveScroll: true,
    });
};

const removeMember = (member) => {
    swal.fire({
        title: 'REMOVE_MEMBER?',
        text: `Keluarkan ${member.username || member.name} dari group ini?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'YES_REMOVE',
        cancelButtonText: 'CANCEL',
    }).then((result) => {
        if (!result.isConfirmed) return;
        router.delete(route('groups.members.remove', { uuid: props.group.uuid, userId: member.id }), {
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

            <!-- Quest Stats -->
            <div class="flex flex-wrap gap-4">
                <div class="border border-slate-700 bg-slate-900/50 px-4 py-3 text-center min-w-[100px]">
                    <p class="text-[7px] text-slate-400 uppercase mb-1">Total Quest</p>
                    <p class="text-lg text-white">{{ questCounts?.total ?? 0 }}</p>
                </div>
                <div class="border border-cyan-700 bg-cyan-900/20 px-4 py-3 text-center min-w-[100px]">
                    <p class="text-[7px] text-cyan-400 uppercase mb-1">Main Quest</p>
                    <p class="text-lg text-cyan-300">{{ questCounts?.main ?? 0 }}</p>
                </div>
                <div class="border border-purple-700 bg-purple-900/20 px-4 py-3 text-center min-w-[100px]">
                    <p class="text-[7px] text-purple-400 uppercase mb-1">Side Quest</p>
                    <p class="text-lg text-purple-300">{{ questCounts?.optional ?? 0 }}</p>
                </div>
            </div>

            <section class="rpg-panel border-indigo-500/50">
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

            <section class="rpg-panel border-emerald-500/50">
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

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <section class="rpg-panel border-orange-500/50">
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

                <section class="rpg-panel border-cyan-500/50">
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
</style>
