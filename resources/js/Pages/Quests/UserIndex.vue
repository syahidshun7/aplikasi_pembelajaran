<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    quests: Object,
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
    router.get(route('quests.user.index'), form.data(), { preserveState: true, preserveScroll: true });
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

const statusClass = (status) => {
    if (status === 'Done') return 'text-red-400 border-red-900 bg-red-900/20';
    if (status === 'In-Progress') return 'text-yellow-400 border-yellow-900 bg-yellow-900/20';
    return 'text-cyan-400 border-cyan-900 bg-cyan-900/20';
};

const questTypeClass = (questType) => {
    return String(questType || 'main') === 'optional'
        ? 'text-lime-300 border-lime-900 bg-lime-900/20'
        : 'text-sky-300 border-sky-900 bg-sky-900/20';
};

const hashGroupKey = (value) => {
    const normalized = String(value || 'global');
    let hash = 0;

    for (let index = 0; index < normalized.length; index += 1) {
        hash = ((hash << 5) - hash) + normalized.charCodeAt(index);
        hash |= 0;
    }

    return Math.abs(hash);
};

const toneForGroup = (groupKey) => {
    if (!groupKey || groupKey === 'global') {
        return {
            border: '#2d65cf',
            bg: 'rgba(22, 47, 93, 0.20)',
            accent: '#8cc4ff',
        };
    }

    const hash = hashGroupKey(groupKey);
    let hue = Math.floor((hash * 137.508) % 360);
    if (hue >= 185 && hue <= 225) {
        hue = (hue + 92) % 360;
    }
    const saturation = 66 + (hash % 8);
    const borderLightness = 56 + ((hash >> 3) % 7);
    const accentLightness = 74 + ((hash >> 5) % 8);
    const border = `hsl(${hue} ${saturation}% ${borderLightness}%)`;
    const accent = `hsl(${hue} ${Math.min(90, saturation + 10)}% ${accentLightness}%)`;
    const bg = `hsl(${hue} ${Math.max(60, saturation - 6)}% 20% / 0.24)`;

    return { border, bg, accent };
};

const toneStyleForQuest = (item) => {
    const toneKey = item?.study_group_id ?? item?.study_group?.id ?? item?.study_group?.name ?? 'global';
    const tone = toneForGroup(String(toneKey));

    return {
        '--quest-tone-border': tone.border,
        '--quest-tone-bg': tone.bg,
        '--quest-tone-accent': tone.accent,
    };
};

const classLabelForQuest = (item) => {
    if (!item?.study_group_id) {
        return 'Global';
    }

    const groupName = String(item?.study_group?.name || '').trim();
    if (groupName !== '') {
        return groupName;
    }

    return `#${item.study_group_id}`;
};
</script>

<template>
    <AuthenticatedLayout>
        <Head title="DETAIL_QUEST_USER" />

        <div class="p-4 md:p-8 font-['Press_Start_2P'] text-[#4ed4d4] text-[10px]">
            <div class="max-w-7xl mx-auto space-y-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b-4 border-yellow-900 pb-4">
                    <h1 class="text-base sm:text-lg md:text-xl uppercase tracking-widest">Detail_Quest_User</h1>
                    <Link
                        :href="route('lobby')"
                        class="inline-flex items-center justify-center px-3 py-2 border-2 border-slate-700 bg-slate-900/40 text-slate-300 hover:text-white hover:border-yellow-400 uppercase text-[9px] sm:text-[10px] whitespace-nowrap"
                    >
                        [Back_to_Home]
                    </Link>
                </div>

                <div class="rpg-panel border-slate-700 flex flex-col min-h-[540px]">
                    <form @submit.prevent="applySearch" class="mb-4 flex flex-col md:flex-row gap-2">
                        <input
                            v-model="form.search"
                            type="text"
                            placeholder="SEARCH QUEST / PARTY / STATUS / RANK"
                            class="flex-1 bg-black border-2 border-slate-700 p-2 text-cyan-400 uppercase outline-none"
                        />
                        <select
                            v-model="form.class_group_id"
                            class="w-full md:w-56 bg-black border-2 border-slate-700 p-2 text-cyan-400 uppercase outline-none"
                        >
                            <option value="">ALL_CLASSES</option>
                            <option v-for="group in classGroups" :key="group.id" :value="String(group.id)">
                                {{ group.name }}
                            </option>
                        </select>
                        <div class="flex gap-2">
                            <button type="submit"
                                class="px-4 py-2 border-2 border-cyan-400 text-cyan-400 hover:bg-cyan-400 hover:text-black uppercase">
                                Search
                            </button>
                            <button type="button" @click="resetSearch"
                                class="px-4 py-2 border-2 border-slate-600 text-slate-300 hover:bg-slate-700 hover:text-white uppercase">
                                Reset
                            </button>
                        </div>
                    </form>

                    <div class="md:hidden space-y-3 flex-1">
                        <div
                            v-for="item in quests.data"
                            :key="`m-${item.uuid}`"
                            class="quest-item-card p-3 border"
                            :style="toneStyleForQuest(item)"
                        >
                            <p class="text-white uppercase text-[11px]">{{ item.title }}</p>
                            <div class="mt-2 flex flex-wrap gap-1">
                                <span class="quest-item-badge px-2 py-1 border text-[8px] uppercase">
                                    {{ classLabelForQuest(item) }}
                                </span>
                                <span class="px-2 py-1 border text-[8px] uppercase" :class="questTypeClass(item.quest_type)">
                                    {{ String(item.quest_type || 'main') === 'optional' ? 'Optional Bonus' : 'Main Quest' }}
                                </span>
                                <span class="px-2 py-1 border text-[8px] uppercase border-slate-700 text-slate-300">{{ item.difficulty }}</span>
                                <span class="px-2 py-1 border text-[8px] uppercase" :class="statusClass(item.status)">{{ item.status }}</span>
                            </div>
                            <p class="mt-2 text-slate-400 font-sans text-[11px]">{{ item.description || '-' }}</p>
                            <p class="mt-2 text-yellow-500 text-[8px]">Reward: {{ item.reward_gold || 0 }} G</p>
                            <div class="mt-3">
                                <Link
                                    v-if="item.user_has_submitted || String(item.status || '') !== 'In-Progress'"
                                    :href="route('quests.show', item.uuid)"
                                    class="inline-block px-3 py-1 border border-indigo-700 text-indigo-300 hover:bg-indigo-500 hover:text-white uppercase text-[8px]">
                                    {{ item.user_has_submitted ? 'View / Edit Report' : 'Open Quest' }}
                                </Link>
                                <p v-else class="text-[7px] text-slate-500 uppercase italic">
                                    Quest_Inactive
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="hidden md:block overflow-x-auto flex-1">
                        <table class="w-full min-w-[980px] text-left">
                            <thead class="border-b border-slate-700 text-slate-500 text-[8px] uppercase">
                                <tr>
                                    <th class="py-3 px-2">Title</th>
                                    <th class="py-3 px-2">Type</th>
                                    <th class="py-3 px-2">Rank</th>
                                    <th class="py-3 px-2">Status</th>
                                    <th class="py-3 px-2">Reward</th>
                                    <th class="py-3 px-2 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="item in quests.data"
                                    :key="item.uuid"
                                    class="quest-row border-b border-slate-800 hover:bg-slate-900/40"
                                    :style="toneStyleForQuest(item)"
                                >
                                    <td class="py-3 px-2 text-white uppercase">{{ item.title }}</td>
                                    <td class="py-3 px-2">
                                        <span class="quest-item-badge px-2 py-1 border text-[8px] uppercase">
                                            {{ classLabelForQuest(item) }}
                                        </span>
                                        <span class="ml-2 px-2 py-1 border text-[8px] uppercase" :class="questTypeClass(item.quest_type)">
                                            {{ String(item.quest_type || 'main') === 'optional' ? 'Optional Bonus' : 'Main Quest' }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-2 text-slate-200 uppercase">{{ item.difficulty }}</td>
                                    <td class="py-3 px-2">
                                        <span class="px-2 py-1 border text-[8px] uppercase" :class="statusClass(item.status)">
                                            {{ item.status }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-2 text-yellow-500">{{ item.reward_gold || 0 }} G</td>
                                    <td class="py-3 px-2 text-right">
                                        <Link
                                            v-if="item.user_has_submitted || String(item.status || '') !== 'In-Progress'"
                                            :href="route('quests.show', item.uuid)"
                                            class="inline-block px-3 py-1 border border-indigo-700 text-indigo-300 hover:bg-indigo-500 hover:text-white uppercase text-[8px]">
                                            {{ item.user_has_submitted ? 'View / Edit Report' : 'Open Quest' }}
                                        </Link>
                                        <span v-else class="text-[7px] text-slate-500 uppercase italic">
                                            Quest_Inactive
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="quests.data.length === 0">
                                    <td colspan="6" class="py-8 text-center text-slate-500 uppercase">No_Quest_Available</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-5 flex flex-wrap gap-2 pt-4 border-t border-slate-800">
                        <button
                            v-for="(link, idx) in quests.links"
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

.quest-item-card {
    border-color: color-mix(in srgb, var(--quest-tone-border) 52%, #1f2937 48%);
    background:
        linear-gradient(
            180deg,
            var(--quest-tone-bg) 0%,
            rgba(13, 17, 23, 0.90) 100%
        );
}

.quest-row {
    background:
        linear-gradient(
            90deg,
            color-mix(in srgb, var(--quest-tone-bg) 70%, transparent 30%) 0%,
            transparent 42%
        );
}

.quest-item-badge {
    border-color: color-mix(in srgb, var(--quest-tone-border) 58%, transparent 42%);
    background: color-mix(in srgb, var(--quest-tone-bg) 74%, transparent 26%);
    color: color-mix(in srgb, var(--quest-tone-accent) 90%, #f8fafc 10%);
}
</style>
