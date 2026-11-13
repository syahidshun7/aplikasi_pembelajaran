<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { computed, ref } from 'vue';
import { toast } from '@/Utils/Alert';

const props = defineProps({
    event: Object,
    userAttendance: Object,
    contentProgress: {
        type: Object,
        default: () => ({ enabled: false }),
    },
    previewMode: {
        type: Boolean,
        default: false,
    },
    backUrl: {
        type: String,
        default: null,
    },
});

const formatDateTime = (value) => {
    if (!value) return 'Jadwal belum ditentukan';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return 'Jadwal belum ditentukan';
    return date.toLocaleString('id-ID');
};

const normalizedDescription = computed(() => {
    const raw = props.event?.description || '';
    return String(raw).replace(/\\r\\n/g, '\n').replace(/\\n/g, '\n');
});

const eventImages = computed(() => props.event?.images || []);
const activeImageUrl = ref('');
const galleryModalOpen = ref(false);
const attendanceForm = useForm({});
const codeAttendanceForm = useForm({
    code: '',
    token: props.userAttendance?.qr_check_in_token || '',
});

const selectImage = (url) => {
    activeImageUrl.value = String(url || '');
    galleryModalOpen.value = activeImageUrl.value !== '';
};

const closeGalleryModal = () => {
    galleryModalOpen.value = false;
};

const attendanceStatusLabel = computed(() => {
    const status = String(props.userAttendance?.status || 'pending');

    if (status === 'preview') return 'Mode preview';
    if (status === 'present') return 'Sudah hadir';
    if (status === 'absent') return 'Tidak hadir';
    if (status === 'excused') return 'Izin';
    if (status === 'sick') return 'Sakit';
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
    if (props.previewMode) return;

    attendanceForm.post(route('events.attendance.self', props.event.uuid), {
        preserveScroll: true,
        onError: (errors) => {
            const message = errors.event || Object.values(errors || {})[0] || 'Absensi mandiri gagal diproses.';
            toast.error('ATTENDANCE_FAILED', message);
        },
    });
};

const submitCodeAttendance = () => {
    if (props.previewMode) return;

    codeAttendanceForm.post(route('events.attendance.code', props.event.uuid), {
        preserveScroll: true,
        onError: (errors) => {
            const message = errors.code || errors.event || Object.values(errors || {})[0] || 'PIN check-in tidak bisa diproses.';
            toast.error('CHECK_IN_FAILED', message);
        },
    });
};

const progress = computed(() => props.contentProgress || { enabled: false });
const progressEnabled = computed(() => Boolean(progress.value.enabled) && !props.previewMode);
const progressPercent = computed(() => Math.max(0, Math.min(100, Number(progress.value.percent || 0))));
const isPublicEvent = computed(() => !props.event?.study_group_id && !props.event?.study_group);
const publicShareUrl = computed(() => isPublicEvent.value ? route('public.events.show', { uuid: props.event.uuid }) : '');
const backHref = computed(() => props.backUrl || route('events.user.index'));
const backLabel = computed(() => props.previewMode ? 'Kembali ke Event Admin' : 'Daftar Event');
const guideHref = (guide) => props.previewMode
    ? route('guides.user-preview', guide.uuid)
    : route('guides.user.show', guide.uuid);
const questHref = (quest) => props.previewMode
    ? route('quests.user-preview', quest.uuid)
    : route('quests.show', quest.uuid);

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
        <div class="lobby-detail-page max-w-6xl mx-auto space-y-6">
            <div class="rpg-panel border-blue-500/50 relative">
                <Link
                    v-if="!previewMode"
                    :href="route('lobby')"
                    class="absolute right-3 top-3 z-20 text-[8px] uppercase leading-none text-slate-400 hover:text-white md:right-4 md:top-4"
                >
                    [Back_Home]
                </Link>

                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 pr-24 md:pr-28">
                        <h1 class="text-white text-sm md:text-lg">{{ event.title }}</h1>
                        <p class="text-[8px] text-slate-400 uppercase mt-2">
                            Pertemuan {{ event.sequence_order }} · {{ event.study_group?.name || (event.job?.name ? `Public / ${event.job.name}` : 'Public') }}
                        </p>
                        <p class="text-[8px] text-slate-400 uppercase mt-2">
                            {{ formatDateTime(event.starts_at) }} – {{ formatDateTime(event.ends_at) }}
                        </p>
                    </div>
                </div>
                <div class="mt-3">
                    <Link :href="backHref" class="text-[8px] text-blue-300 hover:text-white uppercase">
                        {{ backLabel }}
                    </Link>
                </div>
                <div v-if="previewMode" class="mt-4 border border-cyan-500/50 bg-cyan-950/30 px-4 py-3 text-[8px] uppercase text-cyan-200">
                    User_View_Simulation: attendance, reward, dan activity tidak akan tercatat.
                </div>
                <div v-if="isPublicEvent && !previewMode" class="mt-3 border-t border-slate-800 pt-3">
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
                        Absensi · {{ attendanceStatusLabel }}
                    </p>
                    <p v-if="attendanceCheckedAtLabel" class="mt-2 text-[8px] uppercase text-slate-400">
                        Tercatat: {{ attendanceCheckedAtLabel }}
                    </p>
                </div>
                <div v-if="userAttendance?.can_self_attend && !previewMode" class="mt-4">
                    <button
                        type="button"
                        class="px-3 py-2 border border-emerald-500 text-emerald-300 hover:bg-emerald-500 hover:text-black uppercase text-[8px] disabled:opacity-50"
                        :disabled="attendanceForm.processing"
                        @click="submitSelfAttendance"
                    >
                        {{ attendanceForm.processing ? 'Menyimpan…' : 'Absen Mandiri' }}
                    </button>
                </div>
                <div v-if="userAttendance?.can_code_attend && !previewMode" class="mt-4 border border-emerald-700 bg-emerald-950/20 p-4">
                    <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                        <div class="min-w-0">
                            <p class="text-[8px] uppercase text-emerald-300">Check-in</p>
                            <p v-if="userAttendance.check_in_code_available || codeAttendanceForm.token" class="mt-2 font-sans text-[12px] leading-relaxed text-slate-300">
                                {{ codeAttendanceForm.token ? 'Konfirmasi kehadiran dari QR mentor.' : 'Masukkan PIN dari mentor.' }}
                            </p>
                            <p v-if="userAttendance?.check_in_code_available" class="mt-2 text-[8px] uppercase text-slate-400">
                                Kode aktif sampai: {{ new Date(userAttendance.check_in_code_expires_at).toLocaleString('id-ID') }}
                            </p>
                            <p v-else-if="!codeAttendanceForm.token" class="mt-2 text-[8px] uppercase text-slate-400">
                                Belum ada kode check-in aktif dari mentor.
                            </p>
                        </div>
                        <form v-if="userAttendance.check_in_code_available || codeAttendanceForm.token" class="flex flex-col gap-2 sm:flex-row md:shrink-0" @submit.prevent="submitCodeAttendance">
                            <input
                                v-if="!codeAttendanceForm.token"
                                v-model="codeAttendanceForm.code"
                                aria-label="PIN check-in"
                                type="text"
                                inputmode="numeric"
                                maxlength="6"
                                pattern="[0-9]{6}"
                                placeholder="6 digit"
                                class="border border-slate-700 bg-slate-950 px-3 py-2 font-sans text-[14px] tracking-[0.25em] text-white"
                            >
                            <button
                                type="submit"
                                :disabled="codeAttendanceForm.processing || (!codeAttendanceForm.code && !codeAttendanceForm.token)"
                                class="border border-emerald-500 px-3 py-2 text-[8px] uppercase text-emerald-300 hover:bg-emerald-500 hover:text-black disabled:opacity-40"
                            >
                                {{ codeAttendanceForm.token ? 'Konfirmasi QR' : 'Check-in' }}
                            </button>
                            <p v-if="codeAttendanceForm.errors.code" class="font-sans text-[11px] leading-relaxed text-rose-300 sm:basis-full">
                                {{ codeAttendanceForm.errors.code }}
                            </p>
                        </form>
                    </div>
                </div>
                <div v-if="progressEnabled" class="event-progress-panel">
                    <div class="event-progress">
                        <span class="event-progress__label">Progress</span>
                        <span class="event-progress__track" aria-hidden="true">
                            <span
                                class="event-progress__fill"
                                :class="{ 'is-complete': progress.completed }"
                                :style="{ width: `${progressPercent}%` }"
                            ></span>
                        </span>
                        <span class="event-progress__value" :class="{ 'is-complete': progress.completed }">
                            {{ progressPercent }}%
                        </span>
                    </div>
                </div>
                <div v-if="event.description" class="mt-4 p-4 border border-slate-700 bg-black/30">
                    <p class="text-[8px] text-slate-300 uppercase mb-3 tracking-widest">Topik Event</p>
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
                    <h2 class="text-indigo-300 text-[10px] uppercase mb-4">Materi</h2>
                    <div class="space-y-3 max-h-[520px] overflow-y-auto pr-2 custom-scroll">
                        <div v-for="guide in event.guides" :key="guide.uuid" class="p-3 bg-[#0d1117] border border-slate-700">
                            <div class="mb-3 flex flex-wrap items-start justify-between gap-2">
                                <p class="text-[9px] text-white">{{ guide.title }}</p>
                                <span
                                    v-if="progressEnabled && guide.counts_toward_progress"
                                    class="shrink-0 border px-2 py-1 text-[7px] uppercase"
                                    :class="guide.opened_for_user ? 'border-emerald-500 text-emerald-300' : 'border-slate-600 text-slate-400'"
                                >
                                    {{ guide.opened_for_user ? 'Opened' : 'Not_Opened' }}
                                </span>
                            </div>
                            <p v-if="guide.study_group?.name !== event.study_group?.name" class="text-[7px] text-cyan-400 uppercase mb-2">{{ guide.study_group?.name || 'Public' }}</p>
                            <Link
                                :href="guideHref(guide)"
                                class="text-[8px] px-3 py-1 bg-indigo-900/40 border border-indigo-700 text-indigo-300 hover:bg-indigo-500 hover:text-white uppercase"
                            >
                                Buka Materi
                            </Link>
                        </div>
                        <p v-if="!event.guides.length" class="text-[8px] text-slate-500 uppercase italic">No guides in this event.</p>
                    </div>
                </div>

                <div class="rpg-panel border-yellow-500/50">
                    <h2 class="text-yellow-400 text-[10px] uppercase mb-4">Quest</h2>
                    <div class="space-y-3 max-h-[520px] overflow-y-auto pr-2 custom-scroll">
                        <div v-for="quest in event.quests" :key="quest.uuid" class="p-3 bg-[#0d1117] border border-slate-700">
                            <div class="mb-3 flex flex-wrap items-start justify-between gap-2">
                                <p class="text-[9px] text-white">{{ quest.title }}</p>
                                <span
                                    v-if="progressEnabled && quest.counts_toward_progress"
                                    class="shrink-0 border px-2 py-1 text-[7px] uppercase"
                                    :class="quest.completed_for_user ? 'border-emerald-500 text-emerald-300' : 'border-slate-600 text-slate-400'"
                                >
                                    {{ quest.completed_for_user ? 'Approved' : 'Not_Approved' }}
                                </span>
                            </div>
                            <div class="mb-2 flex flex-wrap items-center gap-2">
                                <p v-if="quest.study_group?.name !== event.study_group?.name" class="text-[7px] text-cyan-400 uppercase">{{ quest.study_group?.name || 'Public' }}</p>
                                <p class="text-[7px] text-orange-400 uppercase">{{ quest.difficulty }}</p>
                            </div>
                            <p v-if="quest.deadline" class="text-[7px] text-slate-400 uppercase mb-3">
                                Tenggat · {{ formatDateTime(quest.deadline) }}
                            </p>
                            <Link
                                v-if="String(quest.status || '') === 'Available'"
                                :href="questHref(quest)"
                                class="text-[8px] px-3 py-1 bg-yellow-900/40 border border-yellow-700 text-yellow-300 hover:bg-yellow-500 hover:text-black uppercase"
                            >
                                Buka Quest
                            </Link>
                            <p v-else class="text-[7px] text-slate-500 uppercase italic">
                                Tidak aktif
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

.event-progress-panel {
    margin-top: 1rem;
}

.event-progress {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.event-progress__label,
.event-progress__value {
    flex: none;
    font-size: 7px;
    line-height: 1;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    text-decoration: none;
    color: #8cc4ff;
}

.event-progress__value.is-complete {
    color: #6ee7b7;
}

.event-progress__track {
    flex: 1;
    height: 12px;
    overflow: hidden;
    border-radius: 999px;
    background: rgba(148, 163, 184, 0.28);
}

.event-progress__fill {
    display: block;
    height: 100%;
    border-radius: inherit;
    background: #38bdf8;
}

.event-progress__fill.is-complete {
    background: #34d399;
}

</style>
