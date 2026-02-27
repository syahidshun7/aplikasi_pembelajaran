<script setup>
import { Head, usePage, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';

// 1. Definisikan Props
const props = defineProps({
    user: Object,
    userQuests: Array,
    averageGrade: Number,   // Nilai rata-rata dari Controller
    totalCompleted: Number, // Total quest selesai dari Controller
    mustVerifyEmail: Boolean,
    status: String,
});

const page = usePage();
const userData = computed(() => props.user || page.props.auth.user);

// 2. State untuk Tab
const activeTab = ref('quests');
const questItems = computed(() => Array.isArray(props.userQuests) ? props.userQuests : (props.userQuests?.data || []));
const questPaginationLinks = computed(() => Array.isArray(props.userQuests) ? [] : (props.userQuests?.links || []));

// 3. Helper Warna Berdasarkan Nilai (Agar tetap tema RPG)
const getGradeColor = (grade) => {
    if (grade >= 90) return 'text-yellow-400';
    if (grade >= 75) return 'text-green-400';
    if (grade >= 60) return 'text-blue-400';
    return 'text-red-500';
};
</script>

<template>
    <AuthenticatedLayout>

        <Head title="HERO_STATUS | P-QUEST" />

        <div class="max-w-7xl mx-auto space-y-8 font-['Press_Start_2P'] text-[#4ed4d4] text-[10px] leading-relaxed">

            <div
                class="rpg-panel flex flex-col md:flex-row items-center gap-6 border-cyan-500/50 bg-[#1a1c2c]/80 backdrop-blur-md">
                <div
                    class="w-20 h-20 border-4 border-cyan-400 bg-slate-800 shadow-[0_0_15px_rgba(78,212,212,0.3)] relative overflow-hidden">
                    <img v-if="userData.profile_photo" :src="'/storage/' + userData.profile_photo"
                        class="w-full h-full object-cover">

                    <img v-else
                        :src="`https://api.dicebear.com/7.x/pixel-art/svg?seed=${userData.username || 'guild-member'}`"
                        class="w-full h-full object-cover">
                </div>

                <div class="flex-1 w-full space-y-4">
                    <div class="flex justify-between items-center">
                        <h1 class="text-white text-lg uppercase italic tracking-tighter">
                            {{ userData.username || userData.name }}
                        </h1>
                        <span class="text-yellow-400 text-sm">
                            {{ userData.gold || 0 }}
                            <span class="text-[8px]">G</span>
                        </span>
                    </div>

                    <div class="w-full h-4 bg-black border-2 border-slate-700 p-[2px] overflow-hidden relative">
                        <div class="h-full bg-cyan-500 shadow-[0_0_10px_#06b6d4] transition-all duration-1000"
                            :style="{ width: (userData.exp ? (userData.exp % 1000) / 10 : 0) + '%' }">
                        </div>
                    </div>

                    <div class="flex justify-between text-[8px] text-slate-400">
                        <span>LVL. {{ userData.lvl || 1 }}</span>
                        <span>EXP: {{ userData.exp % 1000 }} / 1000</span>
                    </div>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="rpg-panel py-4 border-slate-700 bg-black/40 shadow-none">
                    <p class="text-[7px] text-slate-500 uppercase italic mb-2">Overall_Grade_AVG</p>
                    <div class="flex items-baseline gap-2">
                        <span class="text-xl font-bold font-mono" :class="getGradeColor(averageGrade)">
                            {{ averageGrade || 0 }}%
                        </span>
                        <div class="flex-1 h-1 bg-slate-800 ml-2 relative overflow-hidden">
                            <div class="h-full bg-current transition-all duration-1000"
                                :class="getGradeColor(averageGrade)" :style="{ width: (averageGrade || 0) + '%' }">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rpg-panel py-4 border-slate-700 bg-black/40 shadow-none">
                    <p class="text-[7px] text-slate-500 uppercase italic mb-2">Quests_Completed</p>
                    <div class="flex items-center gap-2 text-white">
                        <span class="text-xl font-bold font-mono">{{ totalCompleted || 0 }}</span>
                        <span class="text-[7px] text-slate-600 tracking-widest">SUCCESSFUL_LOGS</span>
                    </div>
                </div>

                <div class="rpg-panel py-4 border-slate-700 bg-black/40 shadow-none">
                    <p class="text-[7px] text-slate-500 uppercase italic mb-2">Battle_Status</p>
                    <span class="text-[8px] text-cyan-400 animate-pulse">
                        >> {{ averageGrade >= 75 ? 'EXCELLENT_FLOW' : 'NEED_MORE_PRACTICE' }}
                    </span>
                </div>
            </div>

            <div class="grid grid-cols-12 gap-6">

                <div class="col-span-12 lg:col-span-3 space-y-4">
                    <div class="rpg-panel bg-slate-900/60">
                        <h2 class="text-white mb-6 border-b-2 border-slate-700 pb-2 uppercase text-center text-[8px]">
                            Menu_Navigation</h2>
                        <nav class="space-y-3">
                            <button @click="activeTab = 'quests'"
                                :class="activeTab === 'quests' ? 'bg-yellow-500 text-black' : 'bg-slate-800 text-yellow-500'"
                                class="w-full p-3 text-left border-r-4 border-black hover:translate-x-1 transition-all uppercase text-[8px]">
                                [1] Quest_Log
                            </button>
                            <button @click="activeTab = 'profile'"
                                :class="activeTab === 'profile' ? 'bg-cyan-400 text-black' : 'bg-slate-800 text-cyan-400'"
                                class="w-full p-3 text-left border-r-4 border-black hover:translate-x-1 transition-all uppercase text-[8px]">
                                [2] Edit_Identity
                            </button>
                            <button @click="activeTab = 'password'"
                                :class="activeTab === 'password' ? 'bg-cyan-400 text-black' : 'bg-slate-800 text-cyan-400'"
                                class="w-full p-3 text-left border-r-4 border-black hover:translate-x-1 transition-all uppercase text-[8px]">
                                [3] Change_Password
                            </button>
                            <button @click="activeTab = 'danger'"
                                :class="activeTab === 'danger' ? 'bg-red-600 text-white' : 'bg-slate-800 text-red-500'"
                                class="w-full p-3 text-left border-r-4 border-black hover:translate-x-1 transition-all uppercase text-[8px]">
                                [4] Danger_Zone
                            </button>
                        </nav>
                    </div>
                </div>

                <div class="col-span-12 lg:col-span-6 min-h-[400px]">
                    <div class="rpg-panel h-full animate-in fade-in slide-in-from-bottom-4 duration-300">

                        <div v-if="activeTab === 'quests'" class="space-y-6">
                            <h3 class="text-green-400 mb-6 uppercase tracking-widest border-l-4 border-green-400 pl-3">
                                Quest_Log</h3>
                            <div class="space-y-4">
                                <template v-if="questItems.length > 0">
                                    <div v-for="q in questItems" :key="q.uuid"
                                        class="p-3 border-2 border-slate-700 bg-black/40 flex justify-between items-center hover:border-cyan-500/50 transition-colors">
                                        <div>
                                            <p class="text-white text-[8px]">{{ q.title }}</p>
                                            <p class="text-[6px] text-slate-500 mt-1 uppercase">
                                                Status:
                                                <span :class="{
                                                    'text-yellow-500': q.status.toLowerCase() === 'pending',
                                                    'text-green-500': q.status.toLowerCase() === 'approved',
                                                    'text-red-500': q.status.toLowerCase() === 'rejected'
                                                }" class="font-bold">
                                                    {{ q.status }}
                                                </span>
                                                <span class="ml-2 text-slate-600">| {{ q.submitted_at }}</span>
                                            </p>
                                        </div>
                                        <Link :href="route('submissions.show', { submission: q.uuid })"
                                            class="text-yellow-500 text-[8px] hover:underline hover:text-white transition-colors">
                                            VIEW >
                                        </Link>
                                    </div>
                                    <div v-if="questPaginationLinks.length > 0" class="flex flex-wrap gap-2 pt-3">
                                        <Link v-for="(link, idx) in questPaginationLinks"
                                            :key="`${idx}-${link.label}`"
                                            :href="link.url || '#'"
                                            class="px-3 py-1 border text-[8px] uppercase transition-all"
                                            :class="[
                                                link.active
                                                    ? 'border-cyan-400 text-cyan-400 bg-cyan-900/20'
                                                    : 'border-slate-700 text-slate-300 hover:bg-slate-700 hover:text-white',
                                                !link.url ? 'opacity-40 pointer-events-none' : ''
                                            ]"
                                            v-html="link.label" />
                                    </div>
                                </template>
                                <div v-else class="text-center py-10">
                                    <p class="text-slate-600 italic">No quests taken yet...</p>
                                    <Link :href="route('lobby')"
                                        class="text-cyan-400 underline mt-4 inline-block hover:text-white">
                                        Browse_Quests
                                    </Link>
                                </div>
                            </div>
                        </div>

                        <div v-if="activeTab === 'profile'" class="space-y-6">
                            <h3 class="text-cyan-400 mb-6 uppercase tracking-widest border-l-4 border-cyan-400 pl-3">
                                Update_Identity</h3>
                            <div class="form-container">
                                <UpdateProfileInformationForm :must-verify-email="mustVerifyEmail" :status="status"
                                    :user="user" class="max-w-xl" />
                            </div>
                        </div>

                        <div v-if="activeTab === 'password'" class="space-y-6">
                            <h3
                                class="text-yellow-500 mb-6 uppercase tracking-widest border-l-4 border-yellow-500 pl-3">
                                Security_Protocol</h3>
                            <div class="form-container">
                                <UpdatePasswordForm />
                            </div>
                        </div>

                        <div v-if="activeTab === 'danger'" class="space-y-6">
                            <h3 class="text-red-600 mb-6 uppercase tracking-widest border-l-4 border-red-600 pl-3">
                                Termination_Process</h3>
                            <div class="bg-red-900/10 p-4 border border-red-900/50 mb-6">
                                <p class="text-red-500 text-[8px] leading-normal">
                                    WARNING: This action is irreversible. All character data, progress, and gold will be
                                    purged from the realm.
                                </p>
                            </div>
                            <DeleteUserForm />
                        </div>

                    </div>
                </div>

                <div class="col-span-12 lg:col-span-3">
                    <div class="rpg-panel border-indigo-500/50 bg-indigo-900/20">
                        <h2
                            class="text-indigo-400 mb-6 border-b-2 border-indigo-900 pb-2 uppercase text-center text-[8px]">
                            Rank_Status</h2>
                        <div class="flex flex-col items-center gap-6 py-4">
                            <div class="text-3xl animate-bounce">
                                {{ averageGrade >= 85 ? '👑' : (averageGrade >= 70 ? '🛡️' : '🗡️') }}
                            </div>
                            <div class="text-center">
                                <p class="text-slate-400 text-[6px] mb-2">CLASS_TITLE</p>
                                <p class="text-white text-sm tracking-widest uppercase">{{ userData.role || 'NOVICE' }}
                                </p>
                                <p class="text-[6px] mt-2" :class="getGradeColor(averageGrade)">
                                    GRADE_RANK: {{ averageGrade >= 90 ? 'S-CLASS' : (averageGrade >= 80 ? 'A-CLASS' :
                                        'B-CLASS') }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
button {
    cursor: pointer;
    font-family: 'Press Start 2P', cursive;
}

.rpg-panel {
    @apply p-6 relative border-4 border-[#3d415f] bg-[#1a1c2c];
    box-shadow: 8px 8px 0px 0px rgba(0, 0, 0, 0.5);
}

.form-container :deep(button) {
    @apply w-full mt-4 p-3 bg-cyan-900/40 border-2 border-cyan-400 text-cyan-400 text-[8px] hover:bg-cyan-400 hover:text-black transition-all font-['Press_Start_2P'];
}

.form-container :deep(input) {
    @apply bg-[#0d1117] border-2 border-slate-700 text-cyan-400 p-2 text-[10px] w-full mt-1 focus:border-cyan-400 outline-none;
}

.form-container :deep(label) {
    @apply text-slate-400 text-[8px] uppercase;
}
</style>
