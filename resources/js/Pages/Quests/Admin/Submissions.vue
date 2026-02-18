<template>
    <div class="min-h-screen bg-[#0d1117] p-8 font-['Press_Start_2P'] text-[#4ed4d4]">
        <div class="mb-8 flex justify-between items-end border-b-4 border-slate-800 pb-4">
            <div>
                <h1 class="text-xl text-white uppercase mb-2">Quest_Submissions</h1>
                <p class="text-[8px] text-slate-500 italic flex items-center gap-2">
                    MISSION: <span class="text-yellow-500">{{ quest.title }}</span> 
                    <span class="bg-slate-800 px-2 py-0.5 text-cyan-400 border border-slate-600">[{{ quest.difficulty }}]</span>
                </p>
            </div>
            <Link :href="route('quests.index')" class="text-[8px] bg-slate-800 px-4 py-2 border-2 border-slate-600 hover:bg-slate-700 transition-all shadow-[4px_4px_0_0_rgba(0,0,0,0.5)] active:translate-y-1 active:shadow-none text-white">
                [ BACK_TO_LIST ]
            </Link>
        </div>

        <div class="bg-[#161b22] border-4 border-slate-700 overflow-hidden shadow-2xl">
            <table class="w-full text-left border-collapse">
                <thead class="bg-slate-900 text-[8px] uppercase text-slate-400">
                    <tr>
                        <th class="p-4 border-b-2 border-slate-700">Adventurer</th>
                        <th class="p-4 border-b-2 border-slate-700 text-center">Grade_Point</th>
                        <th class="p-4 border-b-2 border-slate-700">Status</th>
                        <th class="p-4 border-b-2 border-slate-700">Date_Logged</th>
                        <th class="p-4 border-b-2 border-slate-700">Action</th>
                    </tr>
                </thead>
                <tbody class="text-[12px] font-sans">
                    <tr v-for="sub in submissions" :key="sub.id" class="hover:bg-cyan-900/10 border-b border-slate-800 transition-colors group">
                        <td class="p-4 flex items-center gap-3">
                            <img :src="`https://api.dicebear.com/7.x/pixel-art/svg?seed=${sub.user.name}`" class="w-10 h-10 border-2 border-slate-600 bg-slate-800 group-hover:border-cyan-400 transition-colors">
                            <div>
                                <p class="text-[#4ed4d4] font-bold group-hover:text-white transition-colors">{{ sub.user.name }}</p>
                                <p class="text-[7px] text-slate-500 italic font-mono uppercase tracking-tighter">LVL: {{ sub.user.level }} | ID: #{{ sub.user.id }}</p>
                            </div>
                        </td>

                        <td class="p-4 text-center font-bold">
                            <div v-if="sub.grade !== null" class="flex flex-col items-center">
                                <span :class="getGradeColor(sub.grade)" class="text-lg tracking-tighter">
                                    {{ sub.grade }}
                                </span>
                                <span class="text-[6px] text-slate-500 uppercase">Points</span>
                            </div>
                            <span v-else class="text-slate-700 italic text-[10px]">UNRANKED</span>
                        </td>

                        <td class="p-4">
                            <span :class="getStatusClass(sub.status)" class="px-2 py-1 text-[7px] border font-bold uppercase tracking-widest">
                                {{ sub.status || 'Pending' }}
                            </span>
                        </td>

                        <td class="p-4 text-slate-400 text-[10px] font-mono">
                            {{ formatTime(sub.created_at) }}
                        </td>

                        <td class="p-4 text-right">
                            <Link :href="route('admin.submissions.inspect', sub.id)" 
                                  class="inline-block bg-cyan-900/20 border-2 border-cyan-400 px-4 py-2 text-cyan-400 hover:bg-cyan-400 hover:text-black transition-all text-[9px] font-bold tracking-widest active:translate-y-0.5">
                                [ INSPECT_LOG ]
                            </Link>
                        </td>
                    </tr>

                    <tr v-if="submissions.length === 0">
                        <td colspan="5" class="p-16 text-center">
                            <div class="flex flex-col items-center opacity-30">
                                <p class="text-slate-500 text-[8px] italic uppercase tracking-[0.4em] mt-4">No data streams found in this mission...</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    quest: Object,
    submissions: Array
});

// Warna berdasarkan angka Grade (0-100)
const getGradeColor = (grade) => {
    if (grade >= 90) return 'text-yellow-400';
    if (grade >= 75) return 'text-green-400';
    if (grade >= 50) return 'text-cyan-400';
    return 'text-red-500';
};

const getStatusClass = (status) => {
    switch(status) {
        case 'Approved': return 'text-green-500 border-green-500/50 bg-green-500/10';
        case 'Rejected': return 'text-red-500 border-red-500/50 bg-red-500/10';
        default: return 'text-yellow-500 border-yellow-500/50 bg-yellow-500/10 animate-pulse';
    }
};

const formatTime = (dateString) => {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    }).toUpperCase();
};
</script>