<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    quests: Object,
    filters: Object,
});

const form = useForm({
    search: props.filters?.search || '',
});

const applySearch = () => {
    router.get(route('quests.user.index'), form.data(), { preserveState: true, preserveScroll: true });
};

const resetSearch = () => {
    form.search = '';
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
                        <div v-for="item in quests.data" :key="`m-${item.uuid}`" class="p-3 bg-black/40 border border-slate-800">
                            <p class="text-white uppercase text-[11px]">{{ item.title }}</p>
                            <div class="mt-2 flex flex-wrap gap-1">
                                <span class="px-2 py-1 border text-[8px] uppercase"
                                    :class="item.study_group_id ? 'text-emerald-400 border-emerald-900 bg-emerald-900/20' : 'text-cyan-400 border-cyan-900 bg-cyan-900/20'">
                                    {{ item.study_group_id ? `Party: ${item.study_group?.name || 'Unknown'}` : 'Global' }}
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
                                <tr v-for="item in quests.data" :key="item.uuid" class="border-b border-slate-800 hover:bg-slate-900/40">
                                    <td class="py-3 px-2 text-white uppercase">{{ item.title }}</td>
                                    <td class="py-3 px-2">
                                        <span class="px-2 py-1 border text-[8px] uppercase"
                                            :class="item.study_group_id ? 'text-emerald-400 border-emerald-900 bg-emerald-900/20' : 'text-cyan-400 border-cyan-900 bg-cyan-900/20'">
                                            {{ item.study_group_id ? `Party: ${item.study_group?.name || 'Unknown'}` : 'Global' }}
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
</style>
