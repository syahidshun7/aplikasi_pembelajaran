<template>
    <div class="min-h-screen bg-[#0d1117] p-4 md:p-8 font-['Press_Start_2P'] text-[#4ed4d4] text-[10px]">
        <div class="max-w-7xl mx-auto space-y-8">
            <AdminNavbar />

            <div class="mb-8 flex flex-col md:flex-row justify-between items-start md:items-center border-b-4 border-slate-800 pb-6 gap-4">
                <div>
                    <h1 class="text-xl text-white uppercase mb-2 tracking-tighter">Manual_Inspection_Console</h1>
                    <div class="flex flex-wrap items-center gap-4">
                        <span class="text-[8px] text-slate-500 italic">SUBMISSION_UUID: {{ submission.uuid.substring(0, 8) }}...</span>
                        <span class="text-[8px] bg-yellow-900/30 text-yellow-500 px-2 py-1 border border-yellow-700">
                            DIFFICULTY: {{ submission.quest.difficulty }}
                        </span>
                        <span class="text-[8px] bg-blue-900/30 text-blue-400 px-2 py-1 border border-blue-700 uppercase">
                            MAX_REWARD: {{ submission.quest.reward_gold }} G
                        </span>
                    </div>
                </div>
                <Link :href="route('admin.quests.submissions', { quest: submission.quest.uuid })"
                    class="text-[8px] bg-red-900/20 text-red-500 px-6 py-3 border-2 border-red-900 hover:bg-red-900 hover:text-white transition-all">
                    [ CLOSE_TERMINAL ]
                </Link>
            </div>

            <section class="bg-[#161b22] border-4 border-slate-700 shadow-2xl overflow-hidden">
                <div class="bg-slate-900 p-4 border-b-4 border-slate-700 flex justify-between items-center">
                    <h3 class="text-[10px] text-yellow-500 uppercase tracking-widest">>> MISSION_OBJECTIVE_&_LOG</h3>
                    <span class="text-[8px] text-slate-500 italic font-bold uppercase">
                        Adventurer: {{ submission.user.name }}
                    </span>
                </div>

                <div class="p-6 grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <div class="lg:col-span-1 space-y-4 border-b-2 lg:border-b-0 lg:border-r-2 border-slate-800 pb-6 lg:pb-0 lg:pr-6">
                        <p class="text-[8px] text-slate-500 uppercase italic">Quest_Title:</p>
                        <p class="text-white text-sm leading-relaxed mb-6">{{ submission.quest.title }}</p>

                        <p class="text-[8px] text-slate-500 uppercase italic">Objective_Brief:</p>
                        <p class="text-slate-400 font-sans text-xs leading-relaxed italic border-l-2 border-slate-700 pl-4">
                            "{{ submission.quest.description }}"
                        </p>
                    </div>

                    <div class="lg:col-span-2 space-y-4">
                        <p class="text-[8px] text-cyan-500 uppercase mb-2 italic animate-pulse">Artifact_Viewer_Active:</p>

                        <div v-if="submission.content"
                            class="bg-black border-2 border-slate-800 p-6 font-sans text-sm text-slate-300 whitespace-pre-wrap mb-4 shadow-inner max-h-96 overflow-y-auto custom-scrollbar">
                            {{ submission.content }}
                        </div>

                        <div v-if="submission.file_path" class="border-4 border-black bg-black shadow-2xl overflow-hidden">
                            <div v-if="isImage(submission.file_path)" class="p-2">
                                <img :src="`/storage/${submission.file_path}`" class="w-full h-auto">
                            </div>
                            <div v-else class="h-[500px] w-full bg-slate-800">
                                <iframe :src="`/storage/${submission.file_path}`" class="w-full h-full" frameborder="0"></iframe>
                            </div>
                        </div>

                        <div v-if="submission.link"
                            class="p-4 bg-cyan-900/10 border-2 border-cyan-500/30 flex flex-col md:flex-row justify-between items-center gap-4">
                            <span class="text-[8px] text-cyan-400 font-mono break-all">{{ submission.link }}</span>
                            <a :href="submission.link" target="_blank"
                                class="bg-cyan-500 text-black px-4 py-2 text-[8px] font-bold hover:bg-white transition-colors whitespace-nowrap">
                                OPEN_EXTERNAL_SOURCE
                            </a>
                        </div>
                    </div>
                </div>
            </section>

            <section class="bg-[#161b22] border-4 border-slate-700 shadow-2xl overflow-hidden">
                <div class="bg-slate-900 p-4 border-b-4 border-slate-700 flex justify-between items-center">
                    <h3 class="text-[10px] text-green-500 uppercase tracking-widest">>> ACADEMIC_EVALUATION_&_REWARDS</h3>
                    <button @click="scanWithAI" :disabled="isScanning" 
                            class="text-[8px] bg-indigo-900/30 text-indigo-400 px-3 py-1 border border-indigo-700 hover:bg-indigo-500 hover:text-white transition-all disabled:opacity-50">
                        {{ isScanning ? 'ANALYZING...' : '[ AI_ADVISOR_SCAN ]' }}
                    </button>
                </div>

                <div class="p-6 md:p-8">
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-10">
                        
                        <div class="lg:col-span-7 space-y-3">
                            <div v-for="item in criteria" :key="item.id" 
                                @click="toggleCriteria(item)"
                                class="flex items-center justify-between p-4 border-2 transition-all cursor-pointer hover:border-cyan-400 group"
                                :class="item.checked ? 'border-cyan-500 bg-cyan-500/10' : 'border-slate-800 bg-black/40 text-slate-600'">

                                <div class="flex items-center gap-4">
                                    <div class="w-6 h-6 border-2 flex items-center justify-center transition-all"
                                        :class="item.checked ? 'border-cyan-400 bg-cyan-400' : 'border-slate-700 group-hover:border-cyan-400'">
                                        <span v-if="item.checked" class="text-black font-bold text-xs">X</span>
                                    </div>

                                    <span class="text-[10px] uppercase font-bold"
                                        :class="item.checked ? 'text-white' : 'text-slate-600'">
                                        {{ item.label }}
                                    </span>
                                </div>

                                <div class="flex items-center gap-2" @click.stop> 
                                    <input type="number"
                                        v-model.number="item.weight" 
                                        @input="validateWeight(item)"
                                        :max="item.maxWeight"
                                        class="w-12 bg-transparent border-b-2 border-slate-700 text-right font-mono text-xs text-cyan-500 focus:border-cyan-400 outline-none p-0"
                                        :class="!item.checked ? 'opacity-30' : 'opacity-100'" />
                                    <span class="text-[8px] font-mono text-cyan-700">%</span>
                                </div>
                            </div>
                        </div>

                        <div class="lg:col-span-5 flex flex-col justify-between space-y-6">
                            <div class="grid grid-cols-2 gap-4">
                                <div class="bg-black p-4 border-2 border-slate-800 text-center">
                                    <p class="text-[7px] text-slate-600 uppercase mb-2 italic">Final_Score:</p>
                                    <p class="text-2xl font-bold" :class="totalScore >= 50 ? 'text-green-400' : 'text-red-500'">
                                        {{ totalScore }}%
                                    </p>
                                </div>
                                <div class="bg-black p-4 border-2 border-slate-800 text-center text-[10px] flex items-center justify-center font-bold uppercase tracking-tighter"
                                    :class="getStatusClass(localStatus)">
                                    {{ localStatus }}
                                </div>
                            </div>

                            <div class="bg-slate-800/30 p-4 border-2 border-slate-700 space-y-2">
                                <div class="flex justify-between text-[8px] uppercase">
                                    <span class="text-slate-400 font-sans">Quest Max Reward:</span>
                                    <span class="text-slate-300 font-bold">{{ submission.quest.reward_gold }} G</span>
                                </div>
                                <div class="flex justify-between text-[8px] uppercase border-t border-slate-700 pt-2">
                                    <span class="text-slate-400 font-sans italic text-[7px]">Calculated Gold ({{ totalScore }}%):</span>
                                    <span class="text-yellow-400 font-bold underline">+{{ calculatedGold }} G</span>
                                </div>
                                <div class="flex justify-between text-[8px] uppercase">
                                    <span class="text-slate-400 font-sans italic text-[7px]">Calculated EXP ({{ totalScore }}%):</span>
                                    <span class="text-cyan-400 font-bold">+{{ calculatedExp }} XP</span>
                                </div>
                            </div>

                            <textarea v-model="feedbackText"
                                class="w-full bg-black border-2 border-slate-800 p-4 text-slate-200 font-sans text-xs focus:border-green-500 outline-none h-32 custom-scrollbar"
                                placeholder="TYPE_COMMANDER_FEEDBACK_LOG_HERE..."></textarea>

                            <div class="flex flex-col gap-2">
                                <p class="text-[7px] text-slate-500 uppercase italic">Execution_Command:</p>
                                <button @click="submitEvaluation" :class="[
                                    'w-full py-5 font-bold text-[10px] uppercase tracking-widest transition-all active:translate-y-1 active:shadow-none',
                                    localStatus === 'Approved'
                                        ? 'bg-green-600 hover:bg-green-500 text-black shadow-[4px_4px_0_0_#14532d]'
                                        : 'bg-red-700 hover:bg-red-600 text-white shadow-[4px_4px_0_0_#450a0a]'
                                ]">
                                    [ {{ localStatus === 'Approved' ? 'CONFIRM_PASSING_GRADE' : 'CONFIRM_REJECTION' }} ]
                                </button>
                                <p class="text-[6px] text-center text-slate-600 mt-2 italic">
                                    *Caution: This action will modify user balance and experience.
                                </p>
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
import { ref, computed, onMounted, watch } from 'vue';
import Swal from 'sweetalert2';
import axios from 'axios';
import AdminNavbar from '@/Components/AdminNavbar.vue';

const props = defineProps({
    submission: Object
});

// 1. STATE & DATA
const feedbackText = ref(props.submission.feedback || '');
const localStatus = ref(props.submission.status || 'Pending');
const isScanning = ref(false);

const criteria = ref([
    { id: 'func', label: 'Functionalitas', weight: 50, maxWeight: 50, checked: false },
    { id: 'logic', label: 'Logic & Structure', weight: 30, maxWeight: 30, checked: false },
    { id: 'neat', label: 'Clean Code', weight: 10, maxWeight: 10, checked: false },
    { id: 'extra', label: 'Extra Effort', weight: 5, maxWeight: 5, checked: false },
    { id: 'att', label: 'Attitude', weight: 5, maxWeight: 5, checked: false },
]);

// 2. COMPUTED PROPERTIES
const totalScore = computed(() => {
    return criteria.value.reduce((acc, item) => acc + (item.checked ? Number(item.weight) : 0), 0);
});

const calculatedGold = computed(() => {
    const baseGold = Number(props.submission.quest.reward_gold) || 0;
    return Math.floor(baseGold * (totalScore.value / 100));
});

const calculatedExp = computed(() => {
    const baseExp = 1000; // Standar 1000 EXP per quest sempurna
    return Math.floor(baseExp * (totalScore.value / 100));
});

// 3. WATCHERS
// Otomatis update status berdasarkan skor
watch(totalScore, (newScore) => {
    localStatus.value = newScore >= 50 ? 'Approved' : 'Rejected';
});

// 4. METHODS
const toggleCriteria = (item) => {
    item.checked = !item.checked;
};

const validateWeight = (item) => {
    if (item.weight > item.maxWeight) item.weight = item.maxWeight;
    if (item.weight < 0) item.weight = 0;
};

const getStatusClass = (status) => {
    if (status === 'Approved') return 'text-green-400 border-green-500 bg-green-500/10';
    if (status === 'Rejected') return 'text-red-500 border-red-500 bg-red-500/10';
    return 'text-yellow-500 border-yellow-500 bg-yellow-500/10';
};

const isImage = (path) => /\.(jpg|jpeg|png|webp|avif|gif)$/i.test(path);

onMounted(() => {
    const savedScores = props.submission.scores_detail;
    if (savedScores && typeof savedScores === 'object') {
        criteria.value.forEach((item) => {
            const value = Number(savedScores[item.id] ?? 0);
            if (value > 0) {
                item.checked = true;
                item.weight = Math.min(value, item.maxWeight);
            }
        });
        return;
    }

    // Fallback untuk data lama yang belum punya scores_detail
    if (props.submission.grade > 0) {
        let remainingGrade = props.submission.grade;
        // Logic ini mencoba mencocokkan checklist dengan grade yang ada
        // (Bisa disesuaikan jika kamu ingin menyimpan state checkbox di DB di masa depan)
        criteria.value.forEach(item => {
            if (remainingGrade >= item.maxWeight) {
                item.checked = true;
                item.weight = item.maxWeight;
                remainingGrade -= item.maxWeight;
            } else if (remainingGrade > 0) {
                item.checked = true;
                item.weight = remainingGrade;
                remainingGrade = 0;
            }
        });
    }
});

const scanWithAI = async () => {
    isScanning.value = true;
    try {
        const response = await axios.post(route('admin.submissions.checkAI', { submission: props.submission.uuid }));
        const data = response.data;

        if (data.scores) {
            Object.keys(data.scores).forEach(key => {
                const item = criteria.value.find(c => c.id === key);
                if (item) {
                    item.checked = data.scores[key] > 0;
                    item.weight = data.scores[key];
                }
            });
        }

        feedbackText.value = `[AI_ADVISOR]: ${data.feedback}\n\n${feedbackText.value}`;

        Swal.fire({
            title: 'AI_ANALYSIS_COMPLETE',
            text: 'System has been calibrated with AI suggestions.',
            icon: 'success',
            background: '#0d1117',
            color: '#4ed4d4',
            confirmButtonColor: '#1e293b'
        });
    } catch (error) {
        Swal.fire('UPLINK_ERROR', 'AI Advisor is currently offline.', 'error');
    } finally {
        isScanning.value = false;
    }
};

const submitEvaluation = () => {
    const status = localStatus.value;

    Swal.fire({
        title: `CONFIRM ${status.toUpperCase()}?`,
        html: `
            <div class="text-left font-mono text-[10px] space-y-2 bg-black p-4 border border-slate-700 mt-4">
                <p class="text-cyan-400">FINAL_GRADE: ${totalScore.value}%</p>
                <p class="text-yellow-400">GOLD_EARNED: +${calculatedGold.value} G</p>
                <p class="text-blue-400">EXP_GAINED: +${calculatedExp.value} XP</p>
                <p class="text-white mt-2 border-t border-slate-800 pt-2">STATUS: ${status}</p>
            </div>
        `,
        icon: status === 'Approved' ? 'success' : 'warning',
        showCancelButton: true,
        confirmButtonText: '[ EXECUTE_VERDICT ]',
        cancelButtonText: '[ ABORT ]',
        background: '#0d1117',
        color: '#4ed4d4',
        confirmButtonColor: status === 'Approved' ? '#166534' : '#991b1b',
        cancelButtonColor: '#1e293b',
    }).then((result) => {
        if (result.isConfirmed) {
            const scoresDetail = criteria.value.reduce((acc, item) => {
                acc[item.id] = item.checked ? Number(item.weight) : 0;
                return acc;
            }, {});

            router.post(route('admin.submissions.verdict', { submission: props.submission.uuid }), {
                final_score: totalScore.value,
                feedback: feedbackText.value,
                status: status,
                scores_detail: scoresDetail,
            }, {
                onSuccess: () => {
                    Swal.fire({
                        title: 'SYSTEM_UPDATED',
                        text: 'Verdict has been permanently logged.',
                        icon: 'success',
                        background: '#0d1117',
                        color: '#4ed4d4'
                    });
                },
            });
        }
    });
};
</script>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: #0d1117;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #1e293b;
    border-radius: 10px;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #4ed4d4;
}

/* Chrome, Safari, Edge, Opera - Remove arrows on number input */
input::-webkit-outer-spin-button,
input::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}

/* Firefox */
input[type=number] {
  -moz-appearance: textfield;
}
</style>
