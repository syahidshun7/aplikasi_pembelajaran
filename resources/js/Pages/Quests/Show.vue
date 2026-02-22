<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue'; // Tambahkan ref
import Swal from 'sweetalert2';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
const props = defineProps({
    quest: Object,
    hasSubmitted: Boolean,
    existingSubmission: Object
});

const form = useForm({
    content: props.existingSubmission?.content || '',
    file: null,
    _method: props.hasSubmitted ? 'PUT' : 'POST',
});

// Helper untuk mengambil nama file saja dari path storage
const getFileName = (path) => {
    return path ? path.split('/').pop() : 'No file attached';
};

const handleFileChange = (e) => {
    const file = e.target.files[0];
    if (!file) return;

    // Validasi Ukuran (5MB = 5 * 1024 * 1024 bytes)
    if (file.size > 5 * 1024 * 1024) {
        Swal.fire({
            title: 'FILE_TOO_LARGE',
            text: 'Maximum file size is 5MB.',
            icon: 'error',
            background: '#161b22',
            color: '#ef4444',
            confirmButtonColor: '#7f1d1d',
        });
        e.target.value = ''; // Reset input
        return;
    }

    form.file = file;
};

const submitReport = () => {
    const title = props.hasSubmitted ? 'UPDATE REPORT?' : 'CONFIRM TRANSMISSION?';
    const text = props.hasSubmitted ? 'This will overwrite your previous report.' : 'The Guild will review your report. Continue?';

    Swal.fire({
        title: title,
        text: text,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'YES, EXECUTE',
        cancelButtonText: 'CANCEL',
        background: '#161b22',
        color: '#4ed4d4',
        confirmButtonColor: props.hasSubmitted ? '#854d0e' : '#164e63', 
        cancelButtonColor: '#7f1d1d',
        customClass: { popup: 'border-4 border-slate-700 font-mono' }
    }).then((result) => {
        if (result.isConfirmed) {
            const url = props.hasSubmitted 
                ? route('submissions.update', props.existingSubmission.uuid) 
                : route('submissions.store', props.quest.uuid);

            form.post(url, {
                onSuccess: () => {
                    Swal.fire({
                        title: 'LOGGED!',
                        text: 'YOUR REPORT HAS BEEN UPDATED.',
                        icon: 'success',
                        background: '#161b22',
                        color: '#4ed4d4',
                    });
                },
            });
        }
    });
};
</script>

<template>
    <AuthenticatedLayout>
        <div class="min-h-screen p-2 md:p-12 font-['Press_Start_2P'] text-[#4ed4d4] flex justify-center items-start">
            <Head :title="'DETAILS - ' + quest.title" />

            <div class="max-w-3xl w-full bg-[#161b22] border-4 border-slate-700 shadow-[10px_10px_0px_0px_rgba(0,0,0,0.5)] md:shadow-[20px_20px_0px_0px_rgba(0,0,0,0.5)] relative overflow-hidden">

                <div class="hidden sm:flex absolute top-10 right-10 w-24 h-24 border-4 border-red-900/30 rounded-full items-center justify-center -rotate-12 select-none pointer-events-none text-red-900/30 text-[8px] text-center uppercase">
                    Official<br>Guild<br>Doc
                </div>

                <div class="p-4 md:p-8 border-b-4 border-slate-700 bg-slate-900/50">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-4">
                        <Link :href="route('quests.index')" class="text-slate-500 hover:text-white text-[7px] md:text-[8px] underline uppercase">
                            [ BACK_TO_BOARD ]
                        </Link>
                        <span class="text-yellow-500 text-[7px] md:text-[8px] tracking-tighter">REF_ID: #{{ quest.uuid }}</span>
                    </div>
                    <h1 class="text-sm md:text-2xl text-white uppercase tracking-tighter leading-tight break-words">
                        {{ quest.title }}
                    </h1>
                </div>

                <div class="p-4 md:p-8 space-y-6 md:space-y-8">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 md:gap-8 border-b-2 border-slate-800 pb-6 md:pb-8 text-[8px] md:text-[10px]">
                        <div class="flex justify-between sm:block border-b border-slate-800/50 sm:border-none pb-2 sm:pb-0">
                            <p class="text-slate-500 mb-2 uppercase">Danger_Level:</p>
                            <p class="text-red-500 font-bold uppercase">{{ quest.difficulty }}</p>
                        </div>
                        <div class="flex justify-between sm:block pt-2 sm:pt-0">
                            <p class="text-slate-500 mb-2 uppercase">Reward_Gold:</p>
                            <p class="text-yellow-400 font-bold">{{ quest.reward_gold }} GOLD</p>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <h3 class="text-[8px] md:text-[10px] text-cyan-400 uppercase tracking-widest underline italic">
                            Mission_Objectives:
                        </h3>
                        <div class="bg-black/30 p-4 md:p-6 border-l-4 border-slate-700 leading-relaxed font-sans text-xs md:text-sm text-slate-300 whitespace-pre-wrap uppercase tracking-wide">
                            {{ quest.description || 'NO ADDITIONAL DATA PROVIDED BY THE GUILD.' }}
                        </div>
                    </div>

                    <div v-if="quest.status !== 'Done' && quest.status !== 'Completed'" 
                        class="mt-8 md:mt-12 p-4 md:p-6 border-2 border-dashed border-cyan-900 bg-black/20">
                        
                        <h3 class="text-[7px] md:text-[10px] mb-6 uppercase tracking-widest" :class="props.hasSubmitted ? 'text-yellow-500' : 'text-white'">
                            >> {{ props.hasSubmitted ? 'Edit_Existing_Report' : 'Submit_Mission_Report' }}
                        </h3>
                        
                        <form @submit.prevent="submitReport" class="space-y-6">
                            <div>
                                <label class="block text-[7px] text-slate-500 mb-2 uppercase">Proof_of_Completion:</label>
                                <textarea v-model="form.content" 
                                    class="w-full bg-[#0d1117] border-2 p-3 md:p-4 text-white font-sans text-xs md:text-sm outline-none transition-all" 
                                    :class="props.hasSubmitted ? 'border-yellow-900 focus:border-yellow-400' : 'border-slate-800 focus:border-cyan-400'"
                                    rows="4" required></textarea>
                            </div>

                            <div>
                                <label class="block text-[7px] text-slate-500 mb-2 uppercase">Evidence_Artifact:</label>
                                
                                <div v-if="props.hasSubmitted && props.existingSubmission.file_path" 
                                    class="mb-3 p-3 bg-black/40 border border-yellow-900/50 flex items-center gap-3">
                                    <div class="overflow-hidden">
                                        <p class="text-[6px] text-slate-500 uppercase italic">Previously_Sent:</p>
                                        <p class="text-[7px] text-yellow-500 truncate">{{ getFileName(props.existingSubmission.file_path) }}</p>
                                    </div>
                                </div>

                                <input 
                                    type="file" 
                                    @change="handleFileChange" 
                                    accept="image/*,application/pdf"
                                    class="text-[7px] text-slate-400 file:bg-slate-800 file:border-2 file:border-slate-700 file:text-white file:px-2 file:py-1 file:mr-2 file:uppercase cursor-pointer w-full border-2 border-slate-800 p-2 bg-[#0d1117]" 
                                />
                                
                                <div v-if="form.errors.file" class="text-red-500 text-[6px] mt-2 uppercase">
                                    {{ form.errors.file }}
                                </div>
                            </div>

                            <button type="submit" :disabled="form.processing" 
                                :class="[
                                    'w-full py-4 border-2 transition-all font-bold uppercase text-[8px] md:text-[10px] active:scale-95',
                                    props.hasSubmitted 
                                        ? 'bg-yellow-900/20 border-yellow-500 text-yellow-500' 
                                        : 'bg-cyan-900/40 border-cyan-400 text-cyan-400'
                                ]"
                            >
                                {{ form.processing ? 'TRANSMITTING...' : (props.hasSubmitted ? 'UPDATE_REPORT' : 'EXECUTE_REPORT') }}
                            </button>
                        </form>
                    </div>

                    <div class="pt-6 border-t-2 border-slate-800 text-[6px] md:text-[8px] text-slate-600 flex flex-col sm:flex-row justify-between gap-2 italic uppercase">
                        <span>Issued: {{ new Date(quest.created_at).toLocaleDateString() }}</span>
                        <span :class="quest.status === 'Available' ? 'text-cyan-400' : 'text-yellow-500'">
                            Status: {{ quest.status || 'AVAILABLE' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
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