<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { computed } from 'vue';

const props = defineProps({
    event: Object,
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
</script>

<template>
    <Head :title="`EVENT | ${event.title}`" />

    <AuthenticatedLayout>
        <div class="max-w-6xl mx-auto space-y-6">
            <div class="rpg-panel border-blue-500/50">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <h1 class="text-white text-sm md:text-lg uppercase">{{ event.title }}</h1>
                        <p class="text-[8px] text-slate-400 uppercase mt-2">
                            Meeting_{{ event.sequence_order }}
                            | Group: {{ event.study_group?.name || 'Public' }}
                        </p>
                        <p class="text-[8px] text-slate-400 uppercase mt-2">
                            Start: {{ formatDateTime(event.starts_at) }} | End: {{ formatDateTime(event.ends_at) }}
                        </p>
                        <p class="text-[8px] text-yellow-400 uppercase mt-2">
                            Durasi: {{ durationText(event.starts_at, event.ends_at) }}
                        </p>
                    </div>
                    <Link :href="route('lobby')" class="text-[8px] text-slate-400 hover:text-white uppercase">
                        [Back_Home]
                    </Link>
                </div>
                <div class="mt-3">
                    <Link :href="route('events.user.index')" class="text-[8px] text-blue-300 hover:text-white uppercase">
                        [Back_Event_List]
                    </Link>
                </div>
                <div v-if="event.description" class="mt-4 p-4 border border-slate-700 bg-black/30">
                    <p class="text-[8px] text-slate-300 uppercase mb-3 tracking-widest">Event_Description</p>
                    <p class="text-[13px] md:text-[14px] font-sans text-slate-200 leading-7 whitespace-pre-line break-words">
                        {{ normalizedDescription }}
                    </p>
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
                                :href="route('quests.show', quest.uuid)"
                                class="text-[8px] px-3 py-1 bg-yellow-900/40 border border-yellow-700 text-yellow-300 hover:bg-yellow-500 hover:text-black uppercase"
                            >
                                Open_Quest
                            </Link>
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
