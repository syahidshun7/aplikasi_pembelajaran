<script setup>
import AdminNavbar from '@/Components/AdminNavbar.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

const props = defineProps({
    logs: Object,
    filters: Object,
    stats: Object,
});

const form = reactive({
    status: props.filters?.status || '',
    from: props.filters?.from || '',
    to: props.filters?.to || '',
    search: props.filters?.search || '',
});

const applyFilter = () => {
    router.get(route('admin.error-logs.index'), { ...form }, { preserveState: true, replace: true });
};

const clearFilter = () => {
    form.status = '';
    form.from = '';
    form.to = '';
    form.search = '';
    applyFilter();
};

const pagination = computed(() => props.logs || {});
</script>

<template>
    <Head title="Server Error Logs" />

    <div class="min-h-screen bg-[#0a0c10] text-white font-['Press_Start_2P']">
        <AdminNavbar />

        <div class="max-w-7xl mx-auto px-4 py-8 space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-lg uppercase text-[#4ed4d4] tracking-widest">Server Error Logs</h1>
                    <p class="text-[9px] text-slate-400 uppercase">Riwayat 5xx + analitik</p>
                </div>
                <Link
                    :href="route('dashboard')"
                    class="nav-action nav-action--admin px-3 py-2 text-[9px]"
                >[Back_to_HQ]</Link>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="rpg-panel bg-red-900/20 border-red-500/50">
                    <p class="text-[8px] text-red-200 uppercase">Total</p>
                    <p class="text-2xl font-black">{{ stats?.total ?? 0 }}</p>
                </div>
                <div class="rpg-panel bg-amber-900/20 border-amber-500/50">
                    <p class="text-[8px] text-amber-200 uppercase">24h</p>
                    <p class="text-2xl font-black">{{ stats?.last_24h ?? 0 }}</p>
                </div>
                <div class="rpg-panel bg-cyan-900/20 border-cyan-500/50">
                    <p class="text-[8px] text-cyan-200 uppercase">7d</p>
                    <p class="text-2xl font-black">{{ stats?.last_7d ?? 0 }}</p>
                </div>
                <div class="rpg-panel bg-slate-900/40 border-slate-600">
                    <p class="text-[8px] text-slate-300 uppercase mb-2">By Status</p>
                    <div class="flex flex-wrap gap-2 text-[9px]">
                        <span
                            v-for="item in stats?.by_status || []"
                            :key="item.status_code"
                            class="px-2 py-1 border border-slate-700 bg-black/40"
                        >
                            {{ item.status_code }}: {{ item.count }}
                        </span>
                        <span v-if="!stats?.by_status || stats.by_status.length === 0" class="text-slate-500">-</span>
                    </div>
                </div>
            </div>

            <div class="rpg-panel bg-[#111827]/80 border-[#3d415f] space-y-3">
                <div class="grid grid-cols-1 md:grid-cols-5 gap-3 text-[9px] uppercase">
                    <div>
                        <label class="block text-slate-400 mb-1">Status</label>
                        <input v-model="form.status" type="number" min="400" max="599" class="w-full bg-black border border-slate-700 p-2 text-white text-[9px]" placeholder="500" />
                    </div>
                    <div>
                        <label class="block text-slate-400 mb-1">From</label>
                        <input v-model="form.from" type="date" class="w-full bg-black border border-slate-700 p-2 text-white text-[9px]" />
                    </div>
                    <div>
                        <label class="block text-slate-400 mb-1">To</label>
                        <input v-model="form.to" type="date" class="w-full bg-black border border-slate-700 p-2 text-white text-[9px]" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-slate-400 mb-1">Search</label>
                        <input v-model="form.search" type="text" class="w-full bg-black border border-slate-700 p-2 text-white text-[9px]" placeholder="message/url/trace" />
                    </div>
                </div>
                <div class="flex items-center gap-2 text-[9px] uppercase">
                    <button @click="applyFilter" class="px-3 py-2 btn-pixel bg-emerald-600 text-black border-emerald-800 hover:bg-emerald-400">Apply</button>
                    <button @click="clearFilter" class="px-3 py-2 btn-pixel bg-slate-800 text-white border-slate-600 hover:bg-slate-600">Reset</button>
                </div>
            </div>

            <div class="rpg-panel bg-[#0d1117]/80 border-[#3d415f] overflow-x-auto">
                <table class="min-w-full text-left text-[9px]">
                    <thead class="text-slate-400 uppercase">
                        <tr class="border-b border-slate-800">
                            <th class="px-3 py-2">Time</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2">Exception</th>
                            <th class="px-3 py-2">Message</th>
                            <th class="px-3 py-2">File:Line</th>
                            <th class="px-3 py-2">URL</th>
                            <th class="px-3 py-2">User/IP</th>
                            <th class="px-3 py-2">Trace</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="log in logs.data" :key="log.id" class="border-b border-slate-900 hover:bg-slate-800/40">
                            <td class="px-3 py-2 whitespace-nowrap">{{ log.created_at }}</td>
                            <td class="px-3 py-2 text-red-300">{{ log.status_code }}</td>
                            <td class="px-3 py-2">{{ log.exception_class }}</td>
                            <td class="px-3 py-2 max-w-xs truncate" :title="log.message">{{ log.message }}</td>
                            <td class="px-3 py-2 max-w-xs truncate" :title="`${log.file || ''}:${log.line || ''}`">{{ log.file || '-' }}:{{ log.line || '-' }}</td>
                            <td class="px-3 py-2 max-w-xs truncate" :title="log.url">{{ log.url || '-' }}</td>
                            <td class="px-3 py-2">{{ log.user_id || '-' }} / {{ log.ip || '-' }}</td>
                            <td class="px-3 py-2">{{ (log.trace_id || '').substring(0, 12) }}</td>
                        </tr>
                        <tr v-if="!logs.data || logs.data.length === 0">
                            <td colspan="8" class="px-3 py-6 text-center text-slate-500 uppercase">No error logs found</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="pagination.links" class="flex flex-wrap gap-2 text-[10px]">
                <button
                    v-for="link in pagination.links"
                    :key="link.label"
                    :disabled="!link.url"
                    class="px-3 py-2 border border-slate-700"
                    :class="link.active ? 'bg-emerald-700 text-white' : 'text-slate-300 hover:bg-slate-800'"
                    v-html="link.label"
                    @click="link.url && router.get(link.url, {}, { preserveState: true, replace: true })"
                />
            </div>
        </div>
    </div>
</template>
