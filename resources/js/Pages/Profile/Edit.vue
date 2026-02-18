<script setup>
import { Head, usePage, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue'; // Tambahkan ref
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';

const page = usePage();
const user = computed(() => page.props.auth.user);

// Ambil data quest yang diambil user (pastikan dikirim dari controller)
const userQuests = computed(() => page.props.userQuests || []);

// State untuk mengatur tab mana yang aktif
const activeTab = ref('quests'); // default tab
</script>

<template>
    <AuthenticatedLayout>
        <Head title="HERO_STATUS | P-QUEST" />

        <div class="max-w-7xl mx-auto space-y-8 font-['Press_Start_2P'] text-[#4ed4d4] text-[10px] leading-relaxed">
            
            <div class="rpg-panel flex flex-col md:flex-row items-center gap-6 border-cyan-500/50 bg-[#1a1c2c]/80 backdrop-blur-md">
                <div class="w-20 h-20 border-4 border-cyan-400 bg-slate-800 shadow-[0_0_15px_rgba(78,212,212,0.3)] relative">
                    <img :src="`https://api.dicebear.com/7.x/pixel-art/svg?seed=${user.name}`" class="w-full h-full object-cover">
                </div>
                <div class="flex-1 w-full space-y-4">
                    <div class="flex justify-between items-center">
                        <h1 class="text-white text-lg uppercase italic tracking-tighter">{{ user.name }}</h1>
                        <span class="text-yellow-400 text-sm">{{ user.gold || 0 }} <span class="text-[8px]">G</span></span>
                    </div>
                    <div class="w-full h-4 bg-black border-2 border-slate-700 p-[2px] overflow-hidden">
                        <div class="h-full bg-cyan-500 shadow-[0_0_10px_#06b6d4] transition-all duration-1000"
                             :style="{ width: (user.exp || 10) + '%' }"></div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-12 gap-6">
                
                <div class="col-span-12 lg:col-span-3 space-y-4">
                    <div class="rpg-panel bg-slate-900/60">
                        <h2 class="text-white mb-6 border-b-2 border-slate-700 pb-2 uppercase text-center text-[8px]">Menu_Navigation</h2>
                        <nav class="space-y-3">
                            <button @click="activeTab = 'quests'" 
                                :class="activeTab === 'quests' ? 'bg-yellow-500 text-black' : 'bg-slate-800 text-yellow-500'"
                                class="w-full p-3 text-left border-r-4 border-black hover:translate-x-1 transition-all uppercase text-[8px]">
                                [1] Active_Quests
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
                            <h3 class="text-green-400 mb-6 uppercase tracking-widest border-l-4 border-green-400 pl-3">Mission_Log</h3>
                            <div class="space-y-4">
                                <div v-if="userQuests.length > 0" v-for="q in userQuests" :key="q.id" class="p-3 border-2 border-slate-700 bg-black/40 flex justify-between items-center">
                                    <div>
                                        <p class="text-white text-[8px]">{{ q.title }}</p>
                                        <p class="text-[6px] text-slate-500 mt-1">Status: <span class="text-cyan-400">{{ q.status }}</span></p>
                                    </div>
                                    <Link :href="route('quests.show', q.id)" class="text-yellow-500 text-[8px] hover:underline">VIEW ></Link>
                                </div>
                                <div v-else class="text-center py-10">
                                    <p class="text-slate-600 italic">No missions taken yet...</p>
                                    <Link :href="route('lobby')" class="text-cyan-400 underline mt-4 inline-block">Browse_Quests</Link>
                                </div>
                            </div>
                        </div>
                       
                        <div v-if="activeTab === 'profile'" class="space-y-6">
                            <h3 class="text-cyan-400 mb-6 uppercase tracking-widest border-l-4 border-cyan-400 pl-3">Update_Identity</h3>
                            <div class="form-container">
                                <UpdateProfileInformationForm :must-verify-email="false" :status="''" />
                            </div>
                        </div>

                        <div v-if="activeTab === 'password'" class="space-y-6">
                            <h3 class="text-yellow-500 mb-6 uppercase tracking-widest border-l-4 border-yellow-500 pl-3">Security_Protocol</h3>
                            <div class="form-container">
                                <UpdatePasswordForm />
                            </div>
                        </div>


                        <div v-if="activeTab === 'danger'" class="space-y-6">
                            <h3 class="text-red-600 mb-6 uppercase tracking-widest border-l-4 border-red-600 pl-3">Termination_Process</h3>
                            <div class="bg-red-900/10 p-4 border border-red-900/50 mb-6">
                                <p class="text-red-500 text-[8px] leading-normal">
                                    WARNING: This action is irreversible. All character data, progress, and gold will be purged from the realm.
                                </p>
                            </div>
                            <DeleteUserForm />
                        </div>

                    </div>
                </div>

                <div class="col-span-12 lg:col-span-3">
                    <div class="rpg-panel border-indigo-500/50 bg-indigo-900/20">
                        <h2 class="text-indigo-400 mb-6 border-b-2 border-indigo-900 pb-2 uppercase text-center text-[8px]">Rank_Status</h2>
                        <div class="flex flex-col items-center gap-6 py-4">
                            <div class="text-3xl animate-bounce">🛡️</div>
                            <div class="text-center">
                                <p class="text-slate-400 text-[6px] mb-2">TITLE</p>
                                <p class="text-white text-sm tracking-widest">{{ user.rank || 'NOVICE' }}</p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
/* Styling Tab Button agar terasa seperti menu game */
button {
    cursor: pointer;
    font-family: 'Press Start 2P', cursive;
}

.rpg-panel {
    @apply p-6 relative border-4 border-[#3d415f] bg-[#1a1c2c];
    box-shadow: 8px 8px 0px 0px rgba(0, 0, 0, 0.5);
}

/* Memastikan form tidak berantakan di panel tengah */
.form-container :deep(button) {
    @apply w-full mt-4 p-3 bg-cyan-900/40 border-2 border-cyan-400 text-cyan-400 text-[8px] hover:bg-cyan-400 hover:text-black transition-all;
}

.form-container :deep(input) {
    @apply bg-[#0d1117] border-2 border-slate-700 text-cyan-400 p-2 text-[10px] w-full mt-1;
}
</style>