<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    quest: Object
});

// Inisialisasi form pengumpulan
const form = useForm({
    content: '',
    file: null,
});

const submitReport = () => {
    // Mengirim data ke route submissions.store
    form.post(route('submissions.store', props.quest.id), {
        onSuccess: () => {
            form.reset();
            alert('MISSION REPORT LOGGED SUCCESSFULLY.');
        },
    });
};
</script>

<template>
    <Head :title="'DETAILS - ' + quest.title" />

    <div class="min-h-screen bg-[#0d1117] p-4 md:p-12 font-['Press_Start_2P'] text-[#4ed4d4] flex justify-center items-start">
        
        <div class="max-w-3xl w-full bg-[#161b22] border-4 border-slate-700 shadow-[20px_20px_0px_0px_rgba(0,0,0,0.5)] relative overflow-hidden">
            
            <div class="absolute top-10 right-10 w-24 h-24 border-4 border-red-900/30 rounded-full flex items-center justify-center -rotate-12 select-none pointer-events-none text-red-900/30 text-[8px] text-center">
                OFFICIAL<br>GUILD<br>DOCUMENT
            </div>

            <div class="p-8 border-b-4 border-slate-700 bg-slate-900/50">
                <div class="flex justify-between items-center mb-4">
                    <Link :href="route('quests.index')" class="text-slate-500 hover:text-white text-[8px] underline">[ BACK_TO_BOARD ]</Link>
                    <span class="text-yellow-500 text-[8px]">REF_ID: #{{ quest.id }}</span>
                </div>
                <h1 class="text-xl md:text-2xl text-white uppercase tracking-tighter leading-tight">{{ quest.title }}</h1>
            </div>

            <div class="p-8 space-y-8">
                <div class="grid grid-cols-2 gap-8 border-b-2 border-slate-800 pb-8 text-[10px]">
                    <div>
                        <p class="text-slate-500 mb-2 uppercase">Danger_Level:</p>
                        <p class="text-red-500 font-bold uppercase">{{ quest.difficulty }}</p>
                    </div>
                    <div>
                        <p class="text-slate-500 mb-2 uppercase">Reward_Gold:</p>
                        <p class="text-yellow-400 font-bold">{{ quest.reward_gold }} GOLD</p>
                    </div>
                </div>

                <div class="space-y-4">
                    <h3 class="text-[10px] text-cyan-400 uppercase tracking-widest underline italic">Mission_Objectives:</h3>
                    <div class="bg-black/30 p-6 border-l-4 border-slate-700 leading-relaxed font-sans text-sm text-slate-300 whitespace-pre-wrap uppercase tracking-wide">
                        {{ quest.description || 'NO ADDITIONAL DATA PROVIDED BY THE GUILD.' }}
                    </div>
                </div>

                <div v-if="quest.status !== 'Done' && quest.status !== 'Completed'" class="mt-12 p-6 border-2 border-dashed border-cyan-900 bg-black/20">
                    <h3 class="text-[10px] text-white mb-6 uppercase tracking-widest">>> Submit_Mission_Report</h3>
                    
                    <form @submit.prevent="submitReport" class="space-y-6">
                        <div>
                            <label class="block text-[8px] text-slate-500 mb-2 uppercase">Proof_of_Completion (Text/Link):</label>
                            <textarea v-model="form.content" 
                                class="w-full bg-[#0d1117] border-2 border-slate-800 p-4 text-white font-sans text-sm focus:border-cyan-400 outline-none" 
                                rows="3" 
                                placeholder="Describe your result or paste links here..." required></textarea>
                        </div>

                        <div>
                            <label class="block text-[8px] text-slate-500 mb-2 uppercase">Upload_Evidence (Max 5MB):</label>
                            <input type="file" @input="form.file = $event.target.files[0]" 
                                class="text-[8px] text-slate-400 file:bg-slate-800 file:border-2 file:border-slate-700 file:text-white file:px-4 file:py-2 file:mr-4 file:uppercase file:font-['Press_Start_2P'] file:text-[8px] cursor-pointer w-full" />
                            
                            <div v-if="form.progress" class="mt-4 w-full bg-slate-800 h-2">
                                <div class="bg-cyan-400 h-full transition-all" :style="{ width: form.progress.percentage + '%' }"></div>
                            </div>
                        </div>

                        <button type="submit" :disabled="form.processing" 
                            class="w-full py-4 bg-cyan-900/40 border-2 border-cyan-400 text-cyan-400 hover:bg-cyan-400 hover:text-black transition-all font-bold uppercase text-[10px]">
                            {{ form.processing ? 'TRANSMITTING_DATA...' : 'EXECUTE_SUBMISSION' }}
                        </button>
                    </form>
                </div>

                <div class="pt-8 border-t-2 border-slate-800 text-[8px] text-slate-600 flex justify-between italic uppercase">
                    <span>Issued_on: {{ new Date(quest.created_at).toLocaleDateString() }}</span>
                    <span :class="quest.status === 'Available' ? 'text-cyan-400' : 'text-yellow-500'">
                        Status: {{ quest.status || 'AVAILABLE' }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.font-sans {
    font-family: ui-sans-serif, system-ui, -apple-system, sans-serif;
}

/* Custom styling untuk scrollbar textarea agar senada */
textarea::-webkit-scrollbar {
    width: 8px;
}
textarea::-webkit-scrollbar-track {
    background: #0d1117;
}
textarea::-webkit-scrollbar-thumb {
    background: #1e293b;
}
</style>