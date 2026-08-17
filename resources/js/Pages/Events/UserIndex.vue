<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    events: Object,
    filters: Object,
    classGroups: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    search: props.filters?.search || '',
    class_group_id: props.filters?.class_group_id ? String(props.filters.class_group_id) : '',
});

const applySearch = () => {
    router.get(route('events.user.index'), form.data(), { preserveState: true, preserveScroll: true });
};

const resetSearch = () => {
    form.search = '';
    form.class_group_id = '';
    applySearch();
};

const goToPage = (url) => {
    if (!url) return;
    router.get(url, {}, { preserveState: true, preserveScroll: true });
};

const formatDate = (value) => {
    if (!value) return 'Schedule_Not_Set';
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) return 'Schedule_Not_Set';
    return parsed.toLocaleString('id-ID');
};

const shortText = (text, max = 130) => {
    const value = String(text || '').replace(/\s+/g, ' ').trim();
    if (value === '') return '-';
    if (value.length <= max) return value;
    return `${value.slice(0, max)}...`;
};

const hashGroupKey = (value) => {
    const normalized = String(value || 'public');
    let hash = 0;

    for (let index = 0; index < normalized.length; index += 1) {
        hash = ((hash << 5) - hash) + normalized.charCodeAt(index);
        hash |= 0;
    }

    return Math.abs(hash);
};

const toneForEvent = (item) => {
    const groupId = item?.study_group_id ?? item?.study_group?.id ?? null;

    if (!groupId) {
        return {
            border: '#2d65cf',
            bg: 'rgba(22, 47, 93, 0.20)',
            accent: '#8cc4ff',
        };
    }

    const hash = hashGroupKey(String(groupId));
    let hue = Math.floor((hash * 137.508) % 360);

    if (hue >= 185 && hue <= 225) {
        hue = (hue + 92) % 360;
    }

    const saturation = 66 + (hash % 8);
    const borderLightness = 56 + ((hash >> 3) % 7);
    const accentLightness = 74 + ((hash >> 5) % 8);

    return {
        border: `hsl(${hue} ${saturation}% ${borderLightness}%)`,
        bg: `hsl(${hue} ${Math.max(60, saturation - 6)}% 20% / 0.22)`,
        accent: `hsl(${hue} ${Math.min(90, saturation + 10)}% ${accentLightness}%)`,
    };
};

const toneStyleForEvent = (item) => {
    const tone = toneForEvent(item);

    return {
        '--event-tone-border': tone.border,
        '--event-tone-bg': tone.bg,
        '--event-tone-accent': tone.accent,
    };
};
</script>

<template>
    <AuthenticatedLayout>
        <Head title="EVENT_TIMELINE_LIST" />

        <div class="lobby-detail-page p-0 md:p-4 font-['Press_Start_2P'] text-[#4ed4d4] text-[10px]">
            <div class="max-w-7xl mx-auto space-y-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b-4 border-blue-900 pb-4">
                    <h1 class="text-base sm:text-lg md:text-xl uppercase tracking-widest">Event_Timeline_List</h1>
                    <Link
                        :href="route('lobby')"
                        class="inline-flex items-center justify-center px-3 py-2 border-2 border-slate-700 bg-slate-900/40 text-slate-300 hover:text-white hover:border-cyan-400 uppercase text-[9px] sm:text-[10px] whitespace-nowrap"
                    >
                        [Back_to_Home]
                    </Link>
                </div>

                <div class="rpg-panel border-slate-700 flex flex-col min-h-[540px]">
                    <form @submit.prevent="applySearch" class="mb-4 flex flex-col md:flex-row gap-2">
                        <input
                            v-model="form.search"
                            type="text"
                            placeholder="SEARCH EVENT / PARTY / DESCRIPTION"
                            class="flex-1 bg-black border-2 border-slate-700 p-2 text-cyan-400 uppercase outline-none"
                        />
                        <select
                            v-model="form.class_group_id"
                            class="w-full md:w-56 bg-black border-2 border-slate-700 p-2 text-cyan-400 uppercase outline-none"
                        >
                            <option value="">ALL_CLASSES</option>
                            <option
                                v-for="group in classGroups"
                                :key="group.id"
                                :value="String(group.id)"
                            >
                                {{ group.name }}
                            </option>
                        </select>
                        <div class="flex gap-2">
                            <button
                                type="submit"
                                class="px-4 py-2 border-2 border-cyan-400 text-cyan-400 hover:bg-cyan-400 hover:text-black uppercase"
                            >
                                Search
                            </button>
                            <button
                                type="button"
                                @click="resetSearch"
                                class="px-4 py-2 border-2 border-slate-600 text-slate-300 hover:bg-slate-700 hover:text-white uppercase"
                            >
                                Reset
                            </button>
                        </div>
                    </form>

                    <div class="md:hidden space-y-3 flex-1">
                        <div
                            v-for="item in events.data"
                            :key="`m-${item.uuid}`"
                            class="event-item-card p-3 border"
                            :style="toneStyleForEvent(item)"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <p class="text-white uppercase text-[11px]">{{ item.title }}</p>
                                <span v-if="item.is_new_for_user" class="event-new-badge">NEW</span>
                            </div>
                            <p class="event-item-type mt-1 text-[8px] uppercase">
                                {{ item.study_group_id ? `Party: ${item.study_group?.name || 'Unknown'}` : `Public${item.job?.name ? ` / ${item.job.name}` : ''}` }}
                            </p>
                            <p class="mt-2 text-slate-400 font-sans text-[11px]">{{ shortText(item.description, 120) }}</p>
                            <p class="mt-2 text-slate-500 text-[8px]">{{ formatDate(item.starts_at) }}</p>
                            <p class="mt-1 text-[8px] uppercase text-slate-400">Guide: {{ item.guides_count || 0 }} | Quest: {{ item.quests_count || 0 }}</p>
                            <div class="mt-3 flex items-center gap-2">
                                <Link
                                    :href="route('events.show', item.uuid)"
                                    class="inline-block px-3 py-1 border border-blue-700 text-blue-300 hover:bg-blue-500 hover:text-black uppercase text-[8px]"
                                >
                                    Detail
                                </Link>
                            </div>
                        </div>
                    </div>

                    <div class="hidden md:block overflow-x-auto flex-1">
                        <table class="w-full min-w-[980px] text-left">
                            <thead class="border-b border-slate-700 text-slate-500 text-[8px] uppercase">
                                <tr>
                                    <th class="py-3 px-2">Title</th>
                                    <th class="py-3 px-2">Type</th>
                                    <th class="py-3 px-2">Description</th>
                                    <th class="py-3 px-2">Schedule</th>
                                    <th class="py-3 px-2">Stats</th>
                                    <th class="py-3 px-2 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="item in events.data"
                                    :key="item.uuid"
                                    class="event-row border-b border-slate-800 hover:bg-slate-900/40"
                                    :style="toneStyleForEvent(item)"
                                >
                                    <td class="py-3 px-2 text-white uppercase">
                                        <div class="flex items-center gap-2">
                                            <span>{{ item.title }}</span>
                                            <span v-if="item.is_new_for_user" class="event-new-badge">NEW</span>
                                        </div>
                                    </td>
                                    <td class="py-3 px-2">
                                        <span
                                            class="event-item-badge px-2 py-1 border text-[8px] uppercase"
                                        >
                                            {{ item.study_group_id ? `Party: ${item.study_group?.name || 'Unknown'}` : `Public${item.job?.name ? ` / ${item.job.name}` : ''}` }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-2 text-slate-400 font-sans" :title="item.description || ''">
                                        {{ shortText(item.description, 120) }}
                                    </td>
                                    <td class="py-3 px-2 text-slate-500">{{ formatDate(item.starts_at) }}</td>
                                    <td class="py-3 px-2 text-slate-400 uppercase text-[8px]">Guide: {{ item.guides_count || 0 }} | Quest: {{ item.quests_count || 0 }}</td>
                                    <td class="py-3 px-2 text-right">
                                        <Link
                                            :href="route('events.show', item.uuid)"
                                            class="inline-block px-3 py-1 border border-blue-700 text-blue-300 hover:bg-blue-500 hover:text-black uppercase text-[8px]"
                                        >
                                            Detail
                                        </Link>
                                    </td>
                                </tr>
                                <tr v-if="events.data.length === 0">
                                    <td colspan="6" class="py-8 text-center text-slate-500 uppercase">No_Event_Available</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-5 flex flex-wrap gap-2 pt-4 border-t border-slate-800">
                        <button
                            v-for="(link, idx) in events.links"
                            :key="`${idx}-${link.label}`"
                            @click="goToPage(link.url)"
                            :disabled="!link.url"
                            class="px-3 py-1 border text-[8px] uppercase transition-all"
                            :class="[
                                link.active
                                    ? 'border-indigo-400 text-indigo-300 bg-indigo-900/20'
                                    : 'border-slate-700 text-slate-300 hover:bg-slate-700 hover:text-white',
                                !link.url ? 'opacity-40 cursor-not-allowed' : ''
                            ]"
                            v-html="link.label"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.rpg-panel {
    background-color: #1a1c2c;
    border-width: 4px;
    padding: 1rem;
    box-shadow: 8px 8px 0 0 rgba(0, 0, 0, 0.5);
}

.event-item-card {
    border-color: color-mix(in srgb, var(--event-tone-border) 52%, #1f2937 48%);
    background:
        linear-gradient(
            180deg,
            var(--event-tone-bg) 0%,
            rgba(13, 17, 23, 0.90) 100%
        );
}

.event-item-type {
    color: color-mix(in srgb, var(--event-tone-accent) 88%, #f8fafc 12%);
}

.event-new-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #fecaca;
    background: #ef4444;
    color: #fff;
    box-shadow: 0 0 12px rgba(239, 68, 68, 0.62);
    padding: 0.25rem 0.45rem;
    font-size: 7px;
    font-weight: 700;
    line-height: 1;
    letter-spacing: 0.14em;
}

.event-row {
    background:
        linear-gradient(
            90deg,
            color-mix(in srgb, var(--event-tone-bg) 70%, transparent 30%) 0%,
            transparent 42%
        );
}

.event-item-badge {
    border-color: color-mix(in srgb, var(--event-tone-border) 58%, transparent 42%);
    background: color-mix(in srgb, var(--event-tone-bg) 74%, transparent 26%);
    color: color-mix(in srgb, var(--event-tone-accent) 90%, #f8fafc 10%);
}
</style>
