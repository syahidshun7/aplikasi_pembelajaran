<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AdminNavbar from '@/Components/AdminNavbar.vue';
import { computed } from 'vue';

const props = defineProps({
    event: Object,
    attendanceUsers: Array,
    activeCheckInCode: {
        type: Object,
        default: null,
    },
});

const page = usePage();

const attendanceForm = useForm({
    attendance: (props.attendanceUsers || []).map((user) => ({
        user_id: user.id,
        status: user.attendance_status || 'pending',
    })),
});

const checkInCodeForm = useForm({
    duration_minutes: 10,
});

const generatedCheckInCode = computed(() => page.props.flash?.checkInCode || null);

const qrImageUrl = (url) => {
    if (!url) return '';
    return `https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=${encodeURIComponent(url)}`;
};

const formatDateTime = (value) => {
    if (!value) return '-';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '-';
    return date.toLocaleString('id-ID');
};

const generateCheckInCode = () => {
    checkInCodeForm.post(route('admin.events.attendance.check-in-code.generate', props.event.uuid), {
        preserveScroll: true,
    });
};

const setAttendanceStatus = (userId, status) => {
    const row = attendanceForm.attendance.find((item) => item.user_id === userId);
    if (row) row.status = status;
};

const getAttendanceStatus = (userId) => {
    return attendanceForm.attendance.find((item) => item.user_id === userId)?.status || 'pending';
};

const applyStatusToAll = (status) => {
    attendanceForm.attendance = attendanceForm.attendance.map((item) => ({
        ...item,
        status,
    }));
};

const saveAttendance = () => {
    attendanceForm.patch(route('admin.events.attendance.update', props.event.uuid), {
        preserveScroll: true,
        preserveState: false,
    });
};

const photoUrl = (user) => {
    if (!user?.profile_photo) return '/images/logo.png';
    if (String(user.profile_photo).startsWith('http')) return user.profile_photo;
    return `/storage/${user.profile_photo}`;
};

</script>

<template>
    <Head :title="`EVENT_ATTENDANCE | ${event.title}`" />

    <div class="min-h-screen bg-[#0d1117] p-4 md:p-8 font-['Press_Start_2P'] text-[#4ed4d4] text-[10px]">
        <div class="max-w-7xl mx-auto space-y-8">
            <AdminNavbar />

            <div class="rpg-panel border-yellow-500/40">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                    <div>
                        <h1 class="text-base md:text-xl text-white uppercase tracking-widest">{{ event.title }}</h1>
                        <p class="text-[8px] text-slate-400 uppercase mt-2">
                            ABSENSI_EVENT | GROUP: <span class="text-cyan-400">{{ event.study_group?.name || 'GLOBAL_EVENT' }}</span>
                        </p>
                    </div>
                    <Link :href="route('admin.events.detail', event.uuid)" class="inline-flex items-center justify-center px-3 py-2 border border-slate-600 bg-slate-900/40 text-slate-300 hover:text-white uppercase text-[8px]">
                        [Back_to_Detail]
                    </Link>
                </div>
            </div>

            <div class="rpg-panel border-yellow-500/40">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
                    <h2 class="text-yellow-400 uppercase">Attendance_List</h2>
                    <button
                        @click="saveAttendance"
                        :disabled="attendanceForm.processing || !attendanceForm.attendance.length"
                        class="px-3 py-2 border border-yellow-500 text-yellow-400 hover:bg-yellow-500 hover:text-black uppercase disabled:opacity-40"
                    >
                        Save_Attendance
                    </button>
                </div>

                <div class="mb-4 border border-emerald-700 bg-emerald-950/20 p-4">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0">
                            <p class="text-[8px] uppercase text-emerald-300">Check_In_Code</p>
                            <p class="mt-2 font-sans text-[12px] leading-relaxed text-slate-300">
                                Generate kode sementara untuk validasi attendance. Kode lama otomatis mati saat kode baru dibuat.
                            </p>
                            <div v-if="generatedCheckInCode" class="mt-3 border border-emerald-500/60 bg-black/40 p-3">
                                <p class="text-[8px] uppercase text-slate-400">Kode baru</p>
                                <p class="mt-2 text-2xl tracking-[0.35em] text-emerald-200">{{ generatedCheckInCode.code }}</p>
                                <p class="mt-2 text-[8px] uppercase text-slate-400">Expired: {{ formatDateTime(generatedCheckInCode.expires_at) }}</p>
                            </div>
                            <div v-else-if="activeCheckInCode" class="mt-3 border border-slate-700 bg-black/30 p-3">
                                <p class="text-[8px] uppercase text-slate-400">
                                    Kode aktif tersedia, berakhir {{ formatDateTime(activeCheckInCode.expires_at) }}.
                                    Last four: ****{{ activeCheckInCode.last_four }}
                                </p>
                            </div>
                            <div v-else class="mt-3 text-[8px] uppercase text-slate-500">
                                Tidak ada kode check-in aktif.
                            </div>
                        </div>

                        <form class="flex shrink-0 flex-col gap-2 sm:flex-row lg:flex-col" @submit.prevent="generateCheckInCode">
                            <label class="text-[8px] uppercase text-slate-400">
                                Durasi Menit
                                <input
                                    v-model.number="checkInCodeForm.duration_minutes"
                                    type="number"
                                    min="1"
                                    max="120"
                                    class="mt-2 w-32 border border-slate-700 bg-slate-950 px-3 py-2 font-sans text-[12px] text-white"
                                >
                            </label>
                            <button
                                type="submit"
                                :disabled="checkInCodeForm.processing"
                                class="border border-emerald-500 px-3 py-2 text-[8px] uppercase text-emerald-300 hover:bg-emerald-500 hover:text-black disabled:opacity-40"
                            >
                                Generate_Code
                            </button>
                        </form>
                    </div>

                    <div v-if="generatedCheckInCode?.qr_url || activeCheckInCode?.qr_url" class="mt-4 grid gap-4 md:grid-cols-[auto_1fr] md:items-center">
                        <img
                            :src="qrImageUrl(generatedCheckInCode?.qr_url || activeCheckInCode?.qr_url)"
                            alt="QR check-in event"
                            class="h-[220px] w-[220px] border border-emerald-700 bg-white p-2"
                        >
                        <div class="min-w-0">
                            <p class="text-[8px] uppercase text-slate-400">QR_Link</p>
                            <p class="mt-2 break-all font-sans text-[12px] leading-relaxed text-slate-300">
                                {{ generatedCheckInCode?.qr_url || activeCheckInCode?.qr_url }}
                            </p>
                        </div>
                    </div>
                </div>

                <p v-if="!event.study_group_id" class="text-[8px] text-cyan-300 uppercase italic mb-4">
                    Event global: absensi berlaku lintas kelas sesuai target job event.
                </p>

                <div>
                    <div class="p-3 border border-slate-700 bg-black/30 mb-4">
                        <p class="text-[8px] text-slate-300 uppercase mb-2">Global_Action</p>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" @click="applyStatusToAll('present')" class="px-3 py-2 border border-emerald-500 text-emerald-400 hover:bg-emerald-500 hover:text-black text-[10px] font-sans uppercase">Select_All_Present</button>
                            <button type="button" @click="applyStatusToAll('absent')" class="px-3 py-2 border border-red-500 text-red-400 hover:bg-red-500 hover:text-black text-[10px] font-sans uppercase">Select_All_Absent</button>
                            <button type="button" @click="applyStatusToAll('excused')" class="px-3 py-2 border border-cyan-500 text-cyan-400 hover:bg-cyan-500 hover:text-black text-[10px] font-sans uppercase">Select_All_Excused</button>
                            <button type="button" @click="applyStatusToAll('pending')" class="px-3 py-2 border border-slate-500 text-slate-300 hover:bg-slate-500 hover:text-black text-[10px] font-sans uppercase">Reset_All_Pending</button>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div
                            v-for="(member, index) in attendanceUsers"
                            :key="member.id"
                            class="p-3 border border-slate-700 bg-black/30"
                        >
                            <div class="border border-slate-700 bg-black/40 p-2">
                                <div class="flex items-center gap-3">
                                    <img :src="photoUrl(member)" alt="User avatar" class="avatar-thumb border border-slate-600 rounded" />
                                    <p class="text-[10px] font-sans text-white uppercase leading-relaxed break-words">
                                        {{ index + 1 }}. {{ member.name }}
                                    </p>
                                </div>
                                <div class="flex flex-wrap items-center gap-1 w-full mt-2">
                                    <label class="flex items-center gap-1 text-[8px] font-sans uppercase text-emerald-400 border border-emerald-700/50 px-1.5 py-1 whitespace-nowrap">
                                        <input type="radio" :name="`attendance-${member.id}`" :checked="getAttendanceStatus(member.id) === 'present'" @change="setAttendanceStatus(member.id, 'present')">
                                        Present
                                    </label>
                                    <label class="flex items-center gap-1 text-[8px] font-sans uppercase text-red-400 border border-red-700/50 px-1.5 py-1 whitespace-nowrap">
                                        <input type="radio" :name="`attendance-${member.id}`" :checked="getAttendanceStatus(member.id) === 'absent'" @change="setAttendanceStatus(member.id, 'absent')">
                                        Absent
                                    </label>
                                    <label class="flex items-center gap-1 text-[8px] font-sans uppercase text-cyan-400 border border-cyan-700/50 px-1.5 py-1 whitespace-nowrap">
                                        <input type="radio" :name="`attendance-${member.id}`" :checked="getAttendanceStatus(member.id) === 'excused'" @change="setAttendanceStatus(member.id, 'excused')">
                                        Excused
                                    </label>
                                    <label class="flex items-center gap-1 text-[8px] font-sans uppercase text-slate-300 border border-slate-700 px-1.5 py-1 whitespace-nowrap">
                                        <input type="radio" :name="`attendance-${member.id}`" :checked="getAttendanceStatus(member.id) === 'pending'" @change="setAttendanceStatus(member.id, 'pending')">
                                        Pending
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <p v-if="!attendanceUsers.length" class="text-[8px] text-slate-500 uppercase italic mt-3">
                        Belum ada peserta yang bisa diabsen untuk event ini.
                    </p>
                </div>
            </div>
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

.avatar-thumb {
    width: 38px;
    height: 38px;
    min-width: 38px;
    min-height: 38px;
    object-fit: cover;
    display: block;
}
</style>
