<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { computed, ref } from 'vue';
import { toast } from '@/Utils/Alert';

const props = defineProps({
    event: Object,
    userAttendance: Object,
});

const formatDateTime = (value) => {
    if (!value) return 'Schedule_Not_Set';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return 'Schedule_Not_Set';
    return date.toLocaleString('id-ID');
};

const durationText = (start, end) => {
    if (!start || !end) return 'Durasi belum ditentukan';
    const s = new Date(start);
    const e = new Date(end);
    if (Number.isNaN(s.getTime()) || Number.isNaN(e.getTime())) return 'Durasi belum ditentukan';
    const totalMinutes = Math.max(0, Math.round((e - s) / 60000));
    const hours = Math.floor(totalMinutes / 60);
    const minutes = totalMinutes % 60;
    if (hours === 0) return `${minutes} menit`;
    return `${hours} jam ${minutes} menit`;
};

const normalizedDescription = computed(() => {
    const raw = props.event?.description || '';
    return String(raw).replace(/\\r\\n/g, '\n').replace(/\\n/g, '\n');
});

const eventImages = computed(() => props.event?.images || []);
const activeImageUrl = ref('');
const galleryModalOpen = ref(false);
const attendanceForm = useForm({});

const selectImage = (url) => {
    activeImageUrl.value = String(url || '');
    galleryModalOpen.value = activeImageUrl.value !== '';
};

const closeGalleryModal = () => {
    galleryModalOpen.value = false;
};

const attendanceBadgeClass = computed(() => {
    const status = String(props.userAttendance?.status || 'pending');

    if (status === 'present') return 'border-emerald-500/70 text-emerald-300';
    if (status === 'absent') return 'border-rose-500/70 text-rose-300';
    if (status === 'excused') return 'border-amber-500/70 text-amber-300';
    return 'border-slate-600 text-slate-300';
});

const attendanceStatusLabel = computed(() => {
    const status = String(props.userAttendance?.status || 'pending');

    if (status === 'present') return 'Sudah hadir';
    if (status === 'absent') return 'Tidak hadir';
    if (status === 'excused') return 'Izin';
    return 'Belum absensi';
});

const attendanceCheckedAtLabel = computed(() => {
    const value = props.userAttendance?.checked_at;
    if (!value) return '';

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';

    return date.toLocaleString('id-ID');
});

const submitSelfAttendance = () => {
    attendanceForm.post(route('events.attendance.self', props.event.uuid), {
        preserveScroll: true,
    });
};

const isPublicEvent = computed(() => !props.event?.study_group_id && !props.event?.study_group);
const publicShareUrl = computed(() => isPublicEvent.value ? route('public.events.show', { uuid: props.event.uuid }) : '');

const copyPublicLink = async () => {
    if (!publicShareUrl.value) return;
    try {
        await navigator.clipboard.writeText(publicShareUrl.value);
        toast.success('LINK_COPIED', 'Link publik event berhasil disalin.');
    } catch {
        toast.error('COPY_FAILED', 'Gagal menyalin link.');
    }
};
</script>

<template>
    <Head :title="`EVENT | ${event.title}`" />

    <AuthenticatedLayout>
        <div class="max-w-6xl mx-auto space-y-6">
            <div class="rpg-panel border-blue-500/50 relative">
                <Link
                    :href="route('lobby')"
                    class="absolute right-3 top-3 z-20 text-[8px] uppercase leading-none text-slate-400 hover:text-white md:right-4 md:top-4"
                >
                    [Back_Home]
                </Link>

                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 pr-24 md:pr-28">
                        <h1 class="text-white text-sm md:text-lg uppercase">{{ event.title }}</h1>
                        <p class="text-[8px] text-slate-400 uppercase mt-2">
                            Meeting_{{ event.sequence_order }}
                            | Target: {{ event.study_group?.name || (event.job?.name ? `Public / ${event.job.name}` : 'Public') }}
                        </p>
                        <p class="text-[8px] text-slate-400 uppercase mt-2">
                            Start: {{ formatDateTime(event.starts_at) }} | End: {{ formatDateTime(event.ends_at) }}
                        </p>
                        <p class="text-[8px] text-yellow-400 uppercase mt-2">
                            Durasi: {{ durationText(event.starts_at, event.ends_at) }}
                        </p>
                        <div class="mt-3 flex flex-wrap items-center gap-2 text-[8px] uppercase">
                            <span class="rounded border px-2 py-1" :class="attendanceBadgeClass">
                                Attendance: {{ userAttendance?.status || 'pending' }}
                            </span>
                            <span class="rounded border border-slate-700 px-2 py-1 text-slate-300">
                                Self Check-In: {{ event.self_attendance_enabled ? 'enabled' : 'disabled' }}
                            </span>
                        </div>
                    </div>
                </div>
                <div class="mt-3">
                    <Link :href="route('events.user.index')" class="text-[8px] text-blue-300 hover:text-white uppercase">
                        [Back_Event_List]
                    </Link>
                </div>
                <div v-if="isPublicEvent" class="mt-3 border-t border-slate-800 pt-3">
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 border border-cyan-600 bg-cyan-900/30 px-3 py-2 text-[8px] uppercase text-cyan-200 hover:bg-cyan-500/40"
                        @click="copyPublicLink"
                    >
                        Copy Public Link
                    </button>
                </div>
                <div class="mt-4 border px-4 py-3" :class="String(userAttendance?.status || 'pending') === 'present' ? 'border-emerald-500/50 bg-emerald-500/10' : 'border-slate-700 bg-black/20'">
                    <p class="text-[8px] uppercase" :class="String(userAttendance?.status || 'pending') === 'present' ? 'text-emerald-300' : 'text-slate-300'">
                        Status_Absensi: {{ attendanceStatusLabel }}
                    </p>
                    <p v-if="attendanceCheckedAtLabel" class="mt-2 text-[8px] uppercase text-slate-400">
                        Tercatat: {{ attendanceCheckedAtLabel }}
                    </p>
                </div>
                <div v-if="userAttendance?.can_self_attend" class="mt-4">
                    <button
                        type="button"
                        class="px-3 py-2 border border-emerald-500 text-emerald-300 hover:bg-emerald-500 hover:text-black uppercase text-[8px] disabled:opacity-50"
                        :disabled="attendanceForm.processing"
                        @click="submitSelfAttendance"
                    >
                        {{ attendanceForm.processing ? 'Saving_Attendance...' : '[Check_In_Myself]' }}
                    </button>
                </div>
                <div v-if="event.description" class="mt-4 p-4 border border-slate-700 bg-black/30">
                    <p class="text-[8px] text-slate-300 uppercase mb-3 tracking-widest">Event_Description</p>
                    <p class="text-[13px] md:text-[14px] font-sans text-slate-200 leading-7 whitespace-pre-line break-words">
                        {{ normalizedDescription }}
                    </p>
                </div>
            </div>

            <div v-if="eventImages.length > 0" class="rpg-panel border-fuchsia-500/50">
                <h2 class="text-fuchsia-300 text-[10px] uppercase mb-4">Event_Gallery</h2>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-6">
                    <button
                        v-for="image in eventImages"
                        :key="image.id"
                        type="button"
                        class="group overflow-hidden border border-slate-700 bg-black/30 transition-colors hover:border-fuchsia-500/70"
                        @click="selectImage(image.url)"
                    >
                        <img :src="image.url" alt="Event thumbnail" class="h-20 w-full object-cover transition-transform group-hover:scale-[1.03]">
                    </button>
                </div>
            </div>

            <div
                v-if="galleryModalOpen && activeImageUrl"
                class="fixed inset-0 z-[120] flex items-center justify-center bg-black/85 p-4 backdrop-blur-sm"
                @click.self="closeGalleryModal"
            >
                <div class="w-full max-w-5xl border-2 border-fuchsia-500/60 bg-[#111827] p-3 shadow-[0_0_30px_rgba(217,70,239,0.2)]">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <p class="text-[8px] uppercase text-fuchsia-300">Event Image Preview</p>
                        <button
                            type="button"
                            class="border border-slate-600 px-2 py-1 text-[8px] uppercase text-slate-300 hover:border-fuchsia-400 hover:text-white"
                            @click="closeGalleryModal"
                        >
                            Close
                        </button>
                    </div>
                    <img :src="activeImageUrl" alt="Event preview" class="max-h-[75vh] w-full object-contain">
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="rpg-panel border-indigo-500/50">
                    <h2 class="text-indigo-300 text-[10px] uppercase mb-4">Event_Guides</h2>
                    <div class="space-y-3 max-h-[520px] overflow-y-auto pr-2 custom-scroll">
                        <div v-for="guide in event.guides" :key="guide.uuid" class="p-3 bg-[#0d1117] border border-slate-700">
                            <p class="text-[9px] text-white uppercase mb-1">{{ guide.title }}</p>
                            <p class="text-[7px] text-cyan-400 uppercase mb-2">{{ guide.study_group?.name || 'Public' }}</p>
                            <p class="text-[8px] text-slate-500 line-clamp-2 font-sans mb-3">{{ guide.description || 'No description.' }}</p>
                            <Link
                                :href="route('guides.user.show', guide.uuid)"
                                class="text-[8px] px-3 py-1 bg-indigo-900/40 border border-indigo-700 text-indigo-300 hover:bg-indigo-500 hover:text-white uppercase"
                            >
                                Open_Guide
                            </Link>
                        </div>
                        <p v-if="!event.guides.length" class="text-[8px] text-slate-500 uppercase italic">No guides in this event.</p>
                    </div>
                </div>

                <div class="rpg-panel border-yellow-500/50">
                    <h2 class="text-yellow-400 text-[10px] uppercase mb-4">Event_Quests</h2>
                    <div class="space-y-3 max-h-[520px] overflow-y-auto pr-2 custom-scroll">
                        <div v-for="quest in event.quests" :key="quest.uuid" class="p-3 bg-[#0d1117] border border-slate-700">
                            <p class="text-[9px] text-white uppercase mb-1">{{ quest.title }}</p>
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-[7px] text-cyan-400 uppercase">{{ quest.study_group?.name || 'Public' }}</p>
                                <p class="text-[7px] text-orange-400 uppercase">{{ quest.difficulty }}</p>
                            </div>
                            <p class="text-[8px] text-slate-500 line-clamp-2 font-sans mb-2">{{ quest.description || 'No description.' }}</p>
                            <p class="text-[7px] text-slate-400 uppercase mb-3">
                                Deadline: {{ quest.deadline ? new Date(quest.deadline).toLocaleString('id-ID') : 'No_Limit' }}
                            </p>
                            <Link
                                v-if="String(quest.status || '') === 'Available'"
                                :href="route('quests.show', quest.uuid)"
                                class="text-[8px] px-3 py-1 bg-yellow-900/40 border border-yellow-700 text-yellow-300 hover:bg-yellow-500 hover:text-black uppercase"
                            >
                                Open_Quest
                            </Link>
                            <p v-else class="text-[7px] text-slate-500 uppercase italic">
                                Quest_Inactive
                            </p>
                        </div>
                        <p v-if="!event.quests.length" class="text-[8px] text-slate-500 uppercase italic">No quests in this event.</p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.rpg-panel {
    @apply p-4 relative border-4 border-[#3d415f] bg-[#1a1c2c];
    box-shadow: 8px 8px 0px 0px rgba(0, 0, 0, 0.5);
}

.custom-scroll::-webkit-scrollbar {
    width: 6px;
}

.custom-scroll::-webkit-scrollbar-track {
    background: #0d1117;
}

.custom-scroll::-webkit-scrollbar-thumb {
    background: #334155;
    border-radius: 999px;
}
</style>
