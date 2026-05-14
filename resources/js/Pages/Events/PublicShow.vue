<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import UserNavbar from '@/Components/UserNavbar.vue';
import { toast } from '@/Utils/Alert';

const props = defineProps({
    event: {
        type: Object,
        required: true,
    },
    share: {
        type: Object,
        required: true,
    },
    isAuthenticated: {
        type: Boolean,
        default: false,
    },
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
    return String(raw).replace(/\r\n/g, '\n').replace(/\n/g, '\n');
});

const eventImages = computed(() => Array.isArray(props.event?.images) ? props.event.images : []);
const activeImageUrl = ref('');
const galleryModalOpen = ref(false);

const selectImage = (url) => {
    activeImageUrl.value = String(url || '');
    galleryModalOpen.value = activeImageUrl.value !== '';
};

const closeGalleryModal = () => {
    galleryModalOpen.value = false;
};

const copyShareLink = async () => {
    const url = String(props.share?.url || '').trim();
    if (!url) return;
    try {
        await navigator.clipboard.writeText(url);
        toast.success('LINK_COPIED', 'Link event berhasil disalin.');
    } catch {
        toast.error('COPY_FAILED', 'Gagal menyalin link. Copy manual ya.');
    }
};
</script>

<template>
    <Head :title="`PUBLIC EVENT | ${event.title}`" />

    <div class="min-h-screen bg-[#0d1117] text-[#4ed4d4] p-4 md:p-8">
        <UserNavbar :show-guest-actions="true" />

        <div class="max-w-6xl mx-auto space-y-6 mt-6">
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
                            Meeting_{{ event.sequence_order || 0 }}
                            | Audience: {{ event.job?.name ? `Public / ${event.job.name}` : 'Public' }}
                        </p>
                        <p class="text-[8px] text-slate-400 uppercase mt-2">
                            Start: {{ formatDateTime(event.starts_at) }} | End: {{ formatDateTime(event.ends_at) }}
                        </p>
                        <p class="text-[8px] text-yellow-400 uppercase mt-2">
                            Durasi: {{ durationText(event.starts_at, event.ends_at) }}
                        </p>
                    </div>
                </div>

                <div class="mt-4 border-t border-slate-700 pt-4">
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 border border-cyan-600 bg-cyan-900/30 px-3 py-2 text-[8px] uppercase text-cyan-200 hover:bg-cyan-500 hover:text-black"
                        @click="copyShareLink"
                    >
                        Copy_Link
                    </button>
                </div>

                <div v-if="normalizedDescription" class="mt-4 p-4 border border-slate-700 bg-black/30">
                    <p class="text-[8px] text-slate-300 uppercase mb-3 tracking-widest">Event_Overview</p>
                    <p class="text-[13px] md:text-[14px] font-sans text-slate-200 leading-7 whitespace-pre-line break-words">
                        {{ normalizedDescription }}
                    </p>
                </div>
                <div v-else class="mt-4 p-4 border border-slate-700 bg-black/30">
                    <p class="text-[8px] text-slate-500 uppercase italic">No description provided.</p>
                </div>

                <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div class="p-3 border border-slate-700 bg-black/30">
                        <p class="text-[8px] uppercase text-slate-300">Learning_Materials: <span class="text-cyan-300">{{ event.guides_count || 0 }}</span></p>
                    </div>
                    <div class="p-3 border border-slate-700 bg-black/30">
                        <p class="text-[8px] uppercase text-slate-300">Quests_Linked: <span class="text-cyan-300">{{ event.quests_count || 0 }}</span></p>
                    </div>
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

            <div class="rpg-panel border-emerald-500/50">
                <h2 class="text-emerald-300 text-[10px] uppercase mb-4">Join_Platform</h2>
                <p class="text-[8px] uppercase leading-relaxed text-slate-400">
                    Mau ikut event ini dan akses fitur lengkap (attendance, quest, progress)? Login atau register dulu.
                </p>
                <div class="mt-4 flex flex-wrap gap-2">
                    <Link
                        :href="isAuthenticated ? route('events.show', event.uuid) : route('login')"
                        class="text-[8px] px-3 py-2 border border-cyan-700 bg-cyan-900/30 text-cyan-300 hover:bg-cyan-500 hover:text-black uppercase"
                    >
                        {{ isAuthenticated ? 'Open_Member_View' : 'Login' }}
                    </Link>
                    <Link
                        v-if="!isAuthenticated"
                        :href="route('register')"
                        class="text-[8px] px-3 py-2 border border-emerald-700 bg-emerald-900/30 text-emerald-300 hover:bg-emerald-500 hover:text-black uppercase"
                    >
                        Register
                    </Link>
                </div>
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
    </div>
</template>

<style scoped>
.rpg-panel {
    @apply p-4 relative border-4 border-[#3d415f] bg-[#1a1c2c];
    box-shadow: 8px 8px 0px 0px rgba(0, 0, 0, 0.5);
}
</style>
