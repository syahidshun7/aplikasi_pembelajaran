<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminNavbar from '@/Components/AdminNavbar.vue';

const props = defineProps({
    event: Object,
    availableGuides: Array,
    availableQuests: Array,
});

const guideAttachForm = useForm({
    guide_ids: [],
});

const questAttachForm = useForm({
    quest_ids: [],
});

const guideOrders = ref((props.event?.guides || []).map((guide) => ({
    id: guide.id,
    sort_order: guide.pivot?.sort_order || 1,
})));

const questOrders = ref((props.event?.quests || []).map((quest) => ({
    id: quest.id,
    sort_order: quest.pivot?.sort_order || 1,
})));

const guideOrderForm = useForm({
    orders: guideOrders.value,
});

const questOrderForm = useForm({
    orders: questOrders.value,
});

const attachedGuideIds = computed(() => new Set((props.event?.guides || []).map((item) => item.id)));
const attachedQuestIds = computed(() => new Set((props.event?.quests || []).map((item) => item.id)));

const attachGuides = () => {
    guideAttachForm.post(route('admin.events.guides.attach', props.event.uuid), {
        preserveScroll: true,
        preserveState: false,
        onSuccess: () => {
            guideAttachForm.reset();
        },
    });
};

const attachQuests = () => {
    questAttachForm.post(route('admin.events.quests.attach', props.event.uuid), {
        preserveScroll: true,
        preserveState: false,
        onSuccess: () => {
            questAttachForm.reset();
        },
    });
};

const detachGuide = (guideUuid) => {
    router.delete(route('admin.events.guides.detach', { event: props.event.uuid, guide: guideUuid }), {
        preserveScroll: true,
        preserveState: false,
    });
};

const detachQuest = (questUuid) => {
    router.delete(route('admin.events.quests.detach', { event: props.event.uuid, quest: questUuid }), {
        preserveScroll: true,
        preserveState: false,
    });
};

const saveGuideOrder = () => {
    guideOrderForm.orders = guideOrders.value;
    guideOrderForm.patch(route('admin.events.guides.reorder', props.event.uuid), {
        preserveScroll: true,
        preserveState: false,
    });
};

const saveQuestOrder = () => {
    questOrderForm.orders = questOrders.value;
    questOrderForm.patch(route('admin.events.quests.reorder', props.event.uuid), {
        preserveScroll: true,
        preserveState: false,
    });
};

const formatSchedule = (value) => {
    if (!value) return '-';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '-';
    return date.toLocaleString('id-ID');
};

const durationText = computed(() => {
    if (!props.event?.starts_at || !props.event?.ends_at) return 'NOT_SET';
    const start = new Date(props.event.starts_at);
    const end = new Date(props.event.ends_at);
    if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) return 'NOT_SET';
    const totalMinutes = Math.max(0, Math.round((end - start) / 60000));
    const hours = Math.floor(totalMinutes / 60);
    const minutes = totalMinutes % 60;
    if (hours <= 0) return `${minutes} MENIT`;
    return `${hours} JAM ${minutes} MENIT`;
});
</script>

<template>
    <Head :title="`EVENT_DETAIL | ${event.title}`" />

    <div class="min-h-screen bg-[#0d1117] p-4 md:p-8 font-['Press_Start_2P'] text-[#4ed4d4] text-[10px]">
        <div class="max-w-7xl mx-auto space-y-8">
            <AdminNavbar />

            <div class="rpg-panel border-blue-500/50">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                    <div class="min-w-0">
                        <h1 class="text-base md:text-xl text-white uppercase tracking-widest break-words">{{ event.title }}</h1>
                        <p class="text-[8px] text-slate-400 uppercase mt-2 leading-relaxed break-words">
                            EVENT_ID: {{ event.uuid.substring(0, 8) }}
                            | SEQ: <span class="text-yellow-400">{{ event.sequence_order }}</span>
                            | GROUP: <span class="text-cyan-400">{{ event.study_group?.name || 'PUBLIC' }}</span>
                        </p>
                        <p class="text-[8px] text-slate-300 uppercase mt-2 leading-relaxed break-words">
                            START: <span class="text-emerald-400">{{ formatSchedule(event.starts_at) }}</span>
                            | END: <span class="text-emerald-400">{{ formatSchedule(event.ends_at) }}</span>
                            | DURATION: <span class="text-yellow-400">{{ durationText }}</span>
                        </p>
                        <p v-if="event.description" class="text-[8px] text-slate-500 mt-3 leading-loose uppercase">
                            > {{ event.description }}
                        </p>
                    </div>
                    <Link :href="route('admin.events.index')" class="text-[8px] text-slate-400 hover:text-white uppercase">
                        [Back_to_Events]
                    </Link>
                </div>
                <div class="mt-4">
                    <Link
                        :href="route('admin.events.attendance', event.uuid)"
                        class="inline-block px-3 py-2 border border-yellow-500 text-yellow-400 hover:bg-yellow-500 hover:text-black text-[8px] uppercase"
                    >
                        [Manage_Attendance]
                    </Link>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                <div class="rpg-panel border-emerald-500/40">
                    <h2 class="text-emerald-400 uppercase mb-4">Guides_Attachment</h2>

                    <div class="space-y-2 max-h-[220px] overflow-y-auto pr-1 md:pr-2 custom-scroll mb-4">
                        <label
                            v-for="guide in availableGuides"
                            :key="guide.id"
                            class="flex items-start md:items-center gap-2 p-2 border border-slate-700 bg-black/30"
                        >
                            <input
                                type="checkbox"
                                :value="guide.id"
                                v-model="guideAttachForm.guide_ids"
                                :disabled="attachedGuideIds.has(guide.id)"
                            >
                            <span class="text-[8px] text-slate-200 uppercase flex-1 min-w-0 break-words">{{ guide.title }}</span>
                            <span class="text-[7px] text-cyan-400 uppercase ml-auto shrink-0">
                                {{ guide.study_group?.name || 'PUBLIC' }}
                            </span>
                        </label>
                    </div>

                    <button
                        @click="attachGuides"
                        :disabled="guideAttachForm.processing || !guideAttachForm.guide_ids.length"
                        class="w-full py-2 border-2 border-emerald-500 text-emerald-400 hover:bg-emerald-500 hover:text-black uppercase transition-all disabled:opacity-40"
                    >
                        Attach_Selected_Guides
                    </button>

                    <div class="mt-6 border-t border-slate-700 pt-4">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-[9px] uppercase text-white">Attached_Guides</h3>
                            <button
                                @click="saveGuideOrder"
                                :disabled="guideOrderForm.processing || !guideOrders.length"
                                class="px-3 py-1 border border-emerald-500 text-emerald-400 hover:bg-emerald-500 hover:text-black uppercase disabled:opacity-40"
                            >
                                Save_Order
                            </button>
                        </div>
                        <div class="space-y-2 max-h-[280px] overflow-y-auto pr-1 md:pr-2 custom-scroll">
                            <div
                                v-for="(guide, idx) in event.guides"
                                :key="guide.id"
                                class="p-2 border border-slate-700 bg-black/30"
                            >
                                <div class="flex items-start md:items-center gap-2">
                                    <input
                                        v-model.number="guideOrders[idx].sort_order"
                                        type="number"
                                        min="1"
                                        class="w-16 bg-black border border-slate-600 p-1 text-yellow-400"
                                    >
                                    <span class="text-[8px] text-slate-200 uppercase flex-1 min-w-0 break-words">{{ guide.title }}</span>
                                    <button
                                        @click="detachGuide(guide.uuid)"
                                        class="text-[8px] text-red-400 hover:text-red-300 uppercase shrink-0"
                                    >
                                        [Detach]
                                    </button>
                                </div>
                            </div>
                            <p v-if="!event.guides.length" class="text-[8px] text-slate-500 uppercase italic">
                                No guides attached yet.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="rpg-panel border-blue-500/40">
                    <h2 class="text-blue-300 uppercase mb-4">Quests_Attachment</h2>

                    <div class="space-y-2 max-h-[220px] overflow-y-auto pr-1 md:pr-2 custom-scroll mb-4">
                        <label
                            v-for="quest in availableQuests"
                            :key="quest.id"
                            class="flex items-start md:items-center gap-2 p-2 border border-slate-700 bg-black/30"
                        >
                            <input
                                type="checkbox"
                                :value="quest.id"
                                v-model="questAttachForm.quest_ids"
                                :disabled="attachedQuestIds.has(quest.id)"
                            >
                            <span class="text-[8px] text-slate-200 uppercase flex-1 min-w-0 break-words">{{ quest.title }}</span>
                            <span class="text-[7px] text-blue-300 uppercase ml-auto shrink-0">
                                {{ quest.study_group?.name || 'PUBLIC' }}
                            </span>
                        </label>
                    </div>

                    <button
                        @click="attachQuests"
                        :disabled="questAttachForm.processing || !questAttachForm.quest_ids.length"
                        class="w-full py-2 border-2 border-blue-400 text-blue-300 hover:bg-blue-400 hover:text-black uppercase transition-all disabled:opacity-40"
                    >
                        Attach_Selected_Quests
                    </button>

                    <div class="mt-6 border-t border-slate-700 pt-4">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-[9px] uppercase text-white">Attached_Quests</h3>
                            <button
                                @click="saveQuestOrder"
                                :disabled="questOrderForm.processing || !questOrders.length"
                                class="px-3 py-1 border border-blue-400 text-blue-300 hover:bg-blue-400 hover:text-black uppercase disabled:opacity-40"
                            >
                                Save_Order
                            </button>
                        </div>
                        <div class="space-y-2 max-h-[280px] overflow-y-auto pr-1 md:pr-2 custom-scroll">
                            <div
                                v-for="(quest, idx) in event.quests"
                                :key="quest.id"
                                class="p-2 border border-slate-700 bg-black/30"
                            >
                                <div class="flex items-start md:items-center gap-2">
                                    <input
                                        v-model.number="questOrders[idx].sort_order"
                                        type="number"
                                        min="1"
                                        class="w-16 bg-black border border-slate-600 p-1 text-yellow-400"
                                    >
                                    <span class="text-[8px] text-slate-200 uppercase flex-1 min-w-0 break-words">{{ quest.title }}</span>
                                    <button
                                        @click="detachQuest(quest.uuid)"
                                        class="text-[8px] text-red-400 hover:text-red-300 uppercase shrink-0"
                                    >
                                        [Detach]
                                    </button>
                                </div>
                            </div>
                            <p v-if="!event.quests.length" class="text-[8px] text-slate-500 uppercase italic">
                                No quests attached yet.
                            </p>
                        </div>
                    </div>
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

.custom-scroll::-webkit-scrollbar-thumb:hover {
    background: #38bdf8;
}
</style>
