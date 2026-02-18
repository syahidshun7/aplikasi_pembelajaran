<template>
    <div class="min-h-screen bg-[#0d1117] p-4 md:p-8 font-['Press_Start_2P'] text-[#4ed4d4]">

        <div class="mb-8 flex justify-between items-center border-b-4 border-slate-800 pb-6">
            <div>
                <h1 class="text-xl text-white uppercase mb-2">Manual_Inspection_Console</h1>
                <div class="flex items-center gap-4">
                    <span class="text-[8px] text-slate-500 italic">LOG_ID: #{{ submission.id }}</span>
                    <span class="text-[8px] bg-yellow-900/30 text-yellow-500 px-2 py-1 border border-yellow-700">
                        DIFFICULTY: {{ submission.quest.difficulty }}
                    </span>
                </div>
            </div>
            <Link :href="route('admin.quests.submissions', submission.quest_id)"
                class="text-[8px] bg-red-900/20 text-red-500 px-6 py-3 border-2 border-red-900 hover:bg-red-900 hover:text-white transition-all">
                [ CLOSE_TERMINAL ]
            </Link>
        </div>

        <div class="max-w-7xl mx-auto space-y-10">

            <section class="bg-[#161b22] border-4 border-slate-700 shadow-2xl">
                <div class="bg-slate-900 p-4 border-b-4 border-slate-700 flex justify-between items-center">
                    <h3 class="text-[10px] text-yellow-500 uppercase tracking-widest">>> MISSION_OBJECTIVE_&_LOG</h3>
                    <span class="text-[8px] text-slate-500 font-sans italic font-bold uppercase">{{ submission.user.name
                        }}'s Submission</span>
                </div>

                <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-8">
                    <div class="md:col-span-1 space-y-4 border-r-2 border-slate-800 pr-6">
                        <p class="text-[8px] text-slate-500 uppercase">Quest_Title:</p>
                        <p class="text-white text-sm leading-relaxed mb-6">{{ submission.quest.title }}</p>

                        <p class="text-[8px] text-slate-500 uppercase">Objective_Brief:</p>
                        <p class="text-slate-400 font-sans text-xs leading-relaxed italic">
                            "{{ submission.quest.description }}"
                        </p>
                    </div>

                    <div class="md:col-span-2 space-y-4">
                        <p class="text-[8px] text-cyan-500 uppercase mb-2 italic">Artifact_Viewer_Active:</p>

                        <div v-if="submission.content"
                            class="bg-black border-2 border-slate-800 p-6 font-sans text-sm text-slate-300 whitespace-pre-wrap mb-4 shadow-inner max-h-96 overflow-y-auto custom-scrollbar">
                            {{ submission.content }}
                        </div>

                        <div v-if="submission.file_path" class="border-4 border-black bg-black shadow-2xl">
                            <div v-if="isImage(submission.file_path)" class="p-2">
                                <img :src="`/storage/${submission.file_path}`" class="w-full h-auto">
                            </div>
                            <div v-else class="h-[600px] w-full bg-white">
                                <iframe :src="`/storage/${submission.file_path}`" class="w-full h-full"
                                    frameborder="0"></iframe>
                            </div>
                        </div>

                        <div v-if="submission.link"
                            class="p-4 bg-cyan-900/10 border-2 border-cyan-500/30 flex justify-between items-center">
                            <span class="text-[8px] text-cyan-400 font-mono">LINK: {{ submission.link }}</span>
                            <a :href="submission.link" target="_blank"
                                class="bg-cyan-500 text-black px-4 py-2 text-[8px] font-bold">OPEN_EXTERNAL_SOURCE</a>
                        </div>
                    </div>
                </div>
            </section>

            <section class="bg-[#161b22] border-4 border-slate-700 shadow-2xl overflow-hidden">
                <div class="bg-slate-900 p-4 border-b-4 border-slate-700">
                    <h3 class="text-[10px] text-green-500 uppercase tracking-widest">>> ACADEMIC_EVALUATION_&_REWARDS
                    </h3>
                </div>

                <div class="p-8">
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-10">

                      

                        <div class="lg:col-span-7 space-y-3">
                            <p class="text-[8px] text-slate-500 uppercase mb-4 tracking-widest">Assessment_Rubric:</p>
                            <div v-for="item in criteria" :key="item.id" @click="toggleCriteria(item)"
                                class="flex items-center justify-between p-4 border-2 transition-all cursor-pointer hover:border-cyan-400"
                                :class="item.checked ? 'border-cyan-500 bg-cyan-500/10' : 'border-slate-800 bg-black/40'">
                                <div class="flex items-center gap-4">
                                    <div class="w-6 h-6 border-2 flex items-center justify-center transition-all"
                                        :class="item.checked ? 'border-cyan-400 bg-cyan-400' : 'border-slate-700'">
                                        <span v-if="item.checked" class="text-black font-bold text-xs">X</span>
                                    </div>
                                    <span class="text-[10px] uppercase font-bold"
                                        :class="item.checked ? 'text-white' : 'text-slate-600'">{{ item.label }}</span>
                                </div>
                                <span class="text-xs font-mono text-cyan-500 font-bold">+{{ item.weight }}</span>
                            </div>
                              <div class="mb-6">
                            <button @click="scanWithAI" :disabled="isScanning"
                                class="w-full py-3 bg-indigo-900/40 border-2 border-indigo-500 text-indigo-400 text-[8px] hover:bg-indigo-500 hover:text-white transition-all flex items-center justify-center gap-3">
                                <span v-if="isScanning" class="animate-spin">⚙️</span>
                                <span>{{ isScanning ? 'COMMUNING_WITH_AI...' : '[ RUN_AI_ANALYSIS ]' }}</span>
                            </button>
                        </div>
                        </div>
                        

                        <div class="lg:col-span-5 flex flex-col justify-between space-y-6">
                            <div class="grid grid-cols-2 gap-4">
                                <div class="bg-black p-4 border-2 border-slate-800 text-center">
                                    <p class="text-[7px] text-slate-600 uppercase mb-2 italic">Final_Score:</p>
                                    <p class="text-2xl font-bold"
                                        :class="totalScore >= 50 ? 'text-green-400' : 'text-red-500'">{{ totalScore }}%
                                    </p>
                                </div>
                                <div class="bg-black p-4 border-2 border-slate-800 text-center text-[10px] flex items-center justify-center font-bold"
                                    :class="getStatusClass(localStatus)">
                                    {{ localStatus }}
                                </div>
                            </div>

                            <div class="bg-slate-800/50 p-4 border-2 border-slate-700 space-y-2">
                                <div class="flex justify-between text-[8px] uppercase">
                                    <span class="text-slate-400">Total Gold Reward:</span>
                                    <span class="text-yellow-400 font-bold">{{ calculatedGold }} G</span>
                                </div>
                                <div class="flex justify-between text-[8px] uppercase">
                                    <span class="text-slate-400">Total EXP Reward:</span>
                                    <span class="text-cyan-400 font-bold">{{ calculatedExp }} XP</span>
                                </div>
                            </div>

                            <textarea v-model="feedbackText"
                                class="w-full bg-black border-2 border-slate-800 p-4 text-slate-200 font-sans text-xs focus:border-green-500 outline-none h-32"
                                placeholder="TYPE_MASTER_FEEDBACK_LOG_HERE..."></textarea>

                            <div class="flex gap-4">
                                <button @click="submitEvaluation('Approved')"
                                    class="flex-1 py-5 bg-green-600 hover:bg-green-500 text-black font-bold text-[10px] uppercase tracking-widest shadow-[6px_6px_0_0_#14532d]">
                                    [ ACCEPT_SUBMISSION ]
                                </button>
                                <button @click="submitEvaluation('Rejected')"
                                    class="flex-1 py-5 bg-red-700 hover:bg-red-600 text-white font-bold text-[10px] uppercase tracking-widest shadow-[6px_6px_0_0_#450a0a]">
                                    [ REJECT_SUBMISSION ]
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    submission: Object
});

// 1. STATE
const feedbackText = ref(props.submission.feedback || '');
const localStatus = ref(props.submission.status || 'Pending');

const criteria = ref([
    { id: 'func', label: 'Functionalitas', weight: 50, checked: false },
    { id: 'logic', label: 'Logic & Structure', weight: 30, checked: false },
    { id: 'neat', label: 'Clean Code', weight: 10, checked: false },
    { id: 'extra', label: 'Extra Effort', weight: 5, checked: false },
    { id: 'att', label: 'Attitude', weight: 5, checked: false },
]);

// 2. LOGIC REWARD (Match with Controller)
const difficultyMult = computed(() => {
    const diff = props.submission.quest.difficulty;
    const multipliers = { 'S-Rank': 3.0, 'A-Rank': 2.0, 'B-Rank': 1.5, 'C-Rank': 1.0, 'D-Rank': 0.8 };
    return multipliers[diff] || 1.0;
});

const totalScore = computed(() => {
    return criteria.value.reduce((acc, item) => acc + (item.checked ? item.weight : 0), 0);
});

const calculatedGold = computed(() => {
    return Math.floor(props.submission.quest.reward_gold * (totalScore.value / 100) * difficultyMult.value);
});

const calculatedExp = computed(() => {
    return Math.floor(100 * (totalScore.value / 100) * difficultyMult.value);
});

// 3. METHODS
const toggleCriteria = (item) => {
    item.checked = !item.checked;
    // Auto-status change: Jika skor >= 50, arahkan ke Approved
    localStatus.value = totalScore.value >= 50 ? 'Approved' : 'Rejected';
};

const getStatusClass = (status) => {
    if (status === 'Approved') return 'text-green-400 border-green-500 bg-green-500/10';
    if (status === 'Rejected') return 'text-red-500 border-red-500 bg-red-500/10';
    return 'text-yellow-500 border-yellow-500 bg-yellow-500/10';
};

onMounted(() => {
    if (props.submission.grade > 0) {
        let tempGrade = props.submission.grade;
        criteria.value.forEach(item => {
            if (tempGrade >= item.weight) {
                item.checked = true;
                tempGrade -= item.weight;
            }
        });
    }
});

const submitEvaluation = (status) => {
    localStatus.value = status; // Bisa di-override manual lewat klik tombol

    Swal.fire({
        title: `CONFIRM ${status}?`,
        html: `<div class="text-[10px] font-mono text-left bg-black p-4 border border-slate-700">
                SCORE: ${totalScore.value}%<br>
                GOLD: +${calculatedGold.value}<br>
                EXP: +${calculatedExp.value}
               </div>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'CONFIRM_VERDICT',
        cancelButtonText: 'CANCEL',
        background: '#0d1117',
        color: '#fff',
        confirmButtonColor: status === 'Approved' ? '#16a34a' : '#b91c1c',
    }).then((result) => {
        if (result.isConfirmed) {
            router.post(route('admin.submissions.verdict', props.submission.id), {
                final_score: totalScore.value,
                feedback: feedbackText.value,
                status: status, // Kita kirim status yang dipilih tombol
                scores_detail: criteria.value
            }, {
                preserveScroll: true,
                onSuccess: () => Swal.fire('SYNCED', 'Data has been updated', 'success')
            });
        }
    });
};


const isImage = (path) => {
    if (!path) return false;
    return /\.(jpg|jpeg|png|webp|avif|gif)$/i.test(path);
};

const isPDF = (path) => {
    if (!path) return false;
    return /\.pdf$/i.test(path);
};


const isScanning = ref(false);

const scanWithAI = async () => {
    isScanning.value = true;
    try {
        const response = await axios.post(route('admin.submissions.checkAI', props.submission.id));
        const data = response.data;

        // Otomatis update kriteria berdasarkan skor AI
        // Kita anggap jika skor AI > 70, maka item tersebut dicentang
        criteria.value.find(c => c.id === 'func').checked = data.func >= 70;
        criteria.value.find(c => c.id === 'logic').checked = data.logic >= 70;
        criteria.value.find(c => c.id === 'neat').checked = data.clean >= 70;

        feedbackText.value = `[AI ADVISOR]: ${data.feedback}`;

        Swal.fire('AI_SCAN_COMPLETE', 'Rubric has been adjusted by AI Advisor', 'success');
    } catch (error) {
        Swal.fire('ERROR', 'AI uplink failed', 'error');
    } finally {
        isScanning.value = false;
    }
};
</script>