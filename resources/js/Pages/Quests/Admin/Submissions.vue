<template>
    <div class="min-h-screen bg-[#0d1117] p-4 md:p-8 font-['Press_Start_2P'] text-[#4ed4d4] text-[10px]">
        <div class="max-w-7xl mx-auto space-y-8">
            <AdminNavbar />

            <div class="mb-8 flex justify-between items-end border-b-4 border-slate-800 pb-4">
                <div>
                    <h1 class="text-xl text-white uppercase mb-2">Quest_Submissions</h1>
                    <p class="text-[8px] text-slate-500 italic">MISSION: {{ quest.title }}</p>
                </div>
                <Link :href="route('quests.index')"
                    class="text-[8px] bg-slate-800 px-4 py-2 border-2 border-slate-600 hover:bg-slate-700">
                    [ BACK_TO_DASHBOARD ]
                </Link>
            </div>

            <div class="bg-[#161b22] border-4 border-slate-700 overflow-hidden">
                <div class="p-4 border-b border-slate-800 flex flex-col md:flex-row gap-2">
                    <input
                        v-model="filterForm.search"
                        type="text"
                        placeholder="SEARCH ADVENTURER / CONTENT"
                        class="flex-1 bg-black border-2 border-slate-700 p-2 text-cyan-400 uppercase outline-none"
                        @keyup.enter="applyFilters"
                    />
                    <select
                        v-model="filterForm.status"
                        class="w-full md:w-48 bg-black border-2 border-slate-700 p-2 text-cyan-400 uppercase outline-none"
                    >
                        <option value="all">ALL_STATUS</option>
                        <option value="Pending">Pending</option>
                        <option value="Approved">Approved</option>
                        <option value="Rejected">Rejected</option>
                    </select>
                    <button @click="applyFilters"
                        class="px-3 py-2 border-2 border-cyan-400 text-cyan-400 hover:bg-cyan-400 hover:text-black uppercase">
                        APPLY
                    </button>
                    <button @click="resetFilters"
                        class="px-3 py-2 border-2 border-slate-600 text-slate-300 hover:bg-slate-700 hover:text-white uppercase">
                        RESET
                    </button>
                </div>
                <table class="w-full text-left border-collapse">
                    <thead class="bg-slate-900 text-[8px] uppercase">
                        <tr>
                            <th class="p-4 border-b-2 border-slate-700">Adventurer</th>
                            <th class="p-4 border-b-2 border-slate-700">Status</th>
                            <th class="p-4 border-b-2 border-slate-700">Date_Logged</th>
                            <th class="p-4 border-b-2 border-slate-700">Action</th>
                        </tr>
                    </thead>
                    <tbody class="text-[12px] font-sans">
                        <tr v-for="sub in rows" :key="sub.id"
                            class="hover:bg-cyan-900/10 border-b border-slate-800 transition-colors">
                            <td class="p-4 flex items-center gap-3">
                                <img :src="`https://api.dicebear.com/7.x/pixel-art/svg?seed=${sub.user.name}`"
                                    class="w-8 h-8 border border-slate-600 bg-slate-800">
                                <div>
                                    <p class="text-[#4ed4d4] font-bold">{{ sub.user.name }}</p>
                                    <p class="text-[6px] text-slate-500 italic">ID: #{{ sub.user.id }}</p>
                                </div>
                            </td>
                            <td class="p-4">
                                <span :class="getStatusClass(sub.status)" class="px-2 py-1 text-[7px] border uppercase">
                                    {{ sub.status || 'Pending' }}
                                </span>
                            </td>
                            <td class="p-4 text-slate-400">
                                {{ new Date(sub.created_at).toLocaleString() }}
                            </td>
                            <td class="p-4">
                                <Link :href="route('admin.submissions.inspect', { submission: sub.uuid })"
                                    class="inline-block bg-cyan-900/40 border border-cyan-400 px-3 py-1 text-cyan-400 hover:bg-cyan-400 hover:text-black transition-all text-[10px] uppercase font-bold tracking-tighter">
                                    INSPECT
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="rows.length === 0">
                            <td colspan="4" class="p-12 text-center text-slate-600 italic">
                                NO ADVENTURERS HAVE SUBMITTED THIS MISSION YET...
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div class="p-4 border-t border-slate-800 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                    <p class="text-[8px] text-slate-500 uppercase">
                        PAGE {{ submissions.current_page || 1 }} / {{ submissions.last_page || 1 }}
                        | TOTAL {{ submissions.total || 0 }}
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="(link, idx) in paginationLinks"
                            :key="`${idx}-${link.label}`"
                            @click="goToPage(link.url)"
                            :disabled="!link.url"
                            class="px-3 py-1 border text-[8px] uppercase transition-all"
                            :class="[
                                link.active
                                    ? 'border-cyan-400 text-cyan-400 bg-cyan-900/20'
                                    : 'border-slate-700 text-slate-300 hover:bg-slate-700 hover:text-white',
                                !link.url ? 'opacity-40 cursor-not-allowed' : ''
                            ]"
                            v-html="link.label"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminNavbar from '@/Components/AdminNavbar.vue';

const props = defineProps({
    quest: Object,
    submissions: Object,
    filters: Object,
});

const filterForm = useForm({
    search: props.filters?.search || '',
    status: props.filters?.status || 'all',
});

const rows = computed(() => props.submissions?.data || []);
const paginationLinks = computed(() => props.submissions?.links || []);

const applyFilters = () => {
    router.get(route('admin.quests.submissions', props.quest.uuid), filterForm.data(), {
        preserveState: true,
        preserveScroll: true,
    });
};

const resetFilters = () => {
    filterForm.search = '';
    filterForm.status = 'all';
    applyFilters();
};

const goToPage = (url) => {
    if (!url) return;
    router.get(url, {}, {
        preserveState: true,
        preserveScroll: true,
    });
};

const getStatusClass = (status) => {
    switch (status) {
        case 'Approved': return 'text-green-500 border-green-900 bg-green-900/10';
        case 'Rejected': return 'text-red-500 border-red-900 bg-red-900/10';
        default: return 'text-yellow-500 border-yellow-900 bg-yellow-900/10';
    }
};
</script>
