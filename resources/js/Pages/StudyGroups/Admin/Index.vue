<script setup>
import { Head, useForm, Link, usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue'; 
import AdminNavbar from '@/Components/AdminNavbar.vue';
import Swal from 'sweetalert2';

const props = defineProps({
    groups: Array
});

// INITIALIZE SWEETALERT TOAST
const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
    background: '#1a1c2c',
    color: '#4ed4d4',
});

const isEditing = ref(false);
const editId = ref(null);

// State untuk Modal Konfirmasi
const showDeleteModal = ref(false);
const groupIdToDelete = ref(null);

// FORM INITIALIZATION
const form = useForm({
    name: '',
    description: '',
    max_members: 5,
});

// Pantau Flash Message dari Server
watch(() => usePage().props.flash, (flash) => {
    if (flash?.message) {
        Toast.fire({
            icon: 'success',
            title: flash.message
        });
    }
}, { deep: true });

// METHODS
const startEdit = (group) => {
    isEditing.value = true;
    editId.value = group.uuid;
    form.name = group.name;
    form.description = group.description || '';
    form.max_members = group.max_members;
    window.scrollTo({ top: 0, behavior: 'smooth' });

    Toast.fire({
        icon: 'info',
        title: 'MODIFYING_PARTY_DATA'
    });
};

const cancelEdit = () => {
    isEditing.value = false;
    editId.value = null;
    form.reset();
};

const submit = () => {
    if (isEditing.value) {
        form.put(route('groups.update', editId.value), {
            onSuccess: () => {
                cancelEdit();
                Toast.fire({ icon: 'success', title: 'REGISTRY_UPDATED' });
            },
            onError: (err) => {
                Swal.fire({
                    icon: 'error',
                    title: 'UPDATE_FAILED',
                    text: Object.values(err)[0],
                    background: '#1a1c2c',
                    color: '#ff4d4d'
                });
            }
        });
    } else {
        form.post(route('groups.store'), {
            onSuccess: () => {
                form.reset();
                Toast.fire({ icon: 'success', title: 'PARTY_ESTABLISHED' });
            },
            onError: (err) => {
                Swal.fire({
                    icon: 'warning',
                    title: 'REGISTRATION_FAILED',
                    text: Object.values(err)[0],
                    background: '#1a1c2c',
                    color: '#facc15'
                });
            }
        });
    }
};

const confirmAbort = (id) => {
    groupIdToDelete.value = id;
    showDeleteModal.value = true;
};

const executeAbort = () => {
    if (groupIdToDelete.value) {
        form.delete(route('groups.destroy', groupIdToDelete.value), {
            onSuccess: () => {
                showDeleteModal.value = false;
                groupIdToDelete.value = null;
                Toast.fire({ icon: 'success', title: 'PARTY_PURGED' });
            }
        });
    }
};
</script>

<template>
    <Head title="PARTY_REGISTRY" />

    <div class="min-h-screen bg-[#0d1117] p-4 md:p-8 font-['Press_Start_2P'] text-[#4ed4d4] text-[10px] relative">
        <div class="max-w-6xl mx-auto space-y-8">

            <AdminNavbar />

            <div class="flex justify-between items-center border-b-4 border-emerald-900 pb-4">
                <h1 class="text-xl uppercase tracking-widest animate-pulse">Party_Registry_System</h1>
                <Link href="/dashboard" class="text-slate-500 hover:text-white transition-colors uppercase">[Back_to_HQ]</Link>
            </div>

            <div class="grid grid-cols-12 gap-8">
                <div class="col-span-12 lg:col-span-5">
                    <div class="rpg-panel border-emerald-500/50">
                        <h2 class="mb-6 uppercase tracking-tighter text-emerald-500">
                            >> {{ isEditing ? 'UPDATE_PARTY_ID_' + editId.substring(0,8) : 'ISSUE_NEW_PARTY' }}
                        </h2>

                        <form @submit.prevent="submit" class="space-y-6">
                            <div>
                                <label class="block mb-2 text-white uppercase">PARTY_NAME:</label>
                                <input v-model="form.name" type="text"
                                    class="w-full bg-black border-2 border-slate-700 p-2 focus:border-emerald-400 outline-none text-emerald-400 uppercase"
                                    placeholder="Enter party name..." required>
                            </div>

                            <div>
                                <label class="block mb-2 text-white uppercase">MISSION_OBJECTIVE:</label>
                                <textarea v-model="form.description"
                                    class="w-full bg-black border-2 border-slate-700 p-2 text-[8px] uppercase focus:border-emerald-400 focus:ring-0"
                                    style="resize: vertical; min-height: 120px;"></textarea>
                            </div>

                            <div>
                                <label class="block mb-2 text-white uppercase">MEMBER_CAPACITY:</label>
                                <input v-model="form.max_members" type="number"
                                    class="w-full bg-black border-2 border-slate-700 p-2 focus:border-emerald-400 outline-none text-yellow-500">
                            </div>

                            <div class="flex gap-2">
                                <button type="submit" :disabled="form.processing"
                                    class="flex-1 py-3 border-2 border-emerald-400 text-emerald-400 hover:bg-emerald-400 hover:text-black uppercase font-bold transition-all">
                                    {{ form.processing ? 'PROCESSING...' : (isEditing ? 'UPDATE_PARTY' : 'CONFIRM_PARTY') }}
                                </button>
                                <button v-if="isEditing" @click="cancelEdit" type="button"
                                    class="px-4 py-3 border-2 border-slate-500 text-slate-500 hover:bg-slate-500 hover:text-white uppercase">
                                    X
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="col-span-12 lg:col-span-7">
                    <div class="rpg-panel border-slate-700 h-full">
                        <h2 class="text-white mb-6 uppercase tracking-tighter">>> ACTIVE_PARTY_BOARD</h2>

                        <div class="space-y-4">
                            <div v-for="g in groups" :key="g.uuid"
                                class="flex flex-col p-4 bg-slate-900/50 border-l-4 border-emerald-500 hover:bg-slate-800 transition-all">

                                <div class="flex justify-between items-start mb-2">
                                    <div class="flex-1">
                                        <div class="text-[8px] text-slate-500 mb-1 uppercase tracking-tighter">ID: {{ g.uuid.substring(0,8) }} // CODE: {{ g.invite_code }}</div>
                                        <div class="text-white uppercase">{{ g.name }}</div>
                                    </div>
                                    <div class="text-yellow-500 text-[8px] tracking-widest">{{ g.users_count || 0 }} / {{ g.max_members }} MEMBERS</div>
                                </div>

                                <div v-if="g.description"
                                    class="text-[7px] text-slate-500 italic mb-4 border-t border-slate-800 pt-2 leading-loose">
                                    > {{ g.description }}
                                </div>

                                <div class="flex gap-4 self-end mt-2">
                                    <button @click="startEdit(g)"
                                        class="text-emerald-500 hover:text-white text-[8px] uppercase font-bold">[Edit]</button>
                                    <button @click="confirmAbort(g.uuid)"
                                        class="text-red-500 hover:text-white text-[8px] uppercase font-bold">[Purge]</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showDeleteModal"
            class="fixed inset-0 z-[100] flex items-center justify-center bg-black/90 backdrop-blur-md p-4">
            <div class="w-full max-w-lg bg-[#121212] border-2 border-red-600 shadow-[0_0_30px_rgba(220,38,38,0.3)] overflow-hidden rounded-lg">
                <div class="bg-red-600/10 border-b-2 border-red-600 p-4 flex items-center gap-3">
                    <span class="text-red-500 text-lg">⚠</span>
                    <h2 class="text-red-500 font-bold uppercase tracking-tighter text-[10px]">» WARNING: PURGE_PROTOCOL</h2>
                </div>
                <div class="p-8 space-y-6">
                    <div class="border-l-2 border-red-900 pl-4">
                        <p class="text-slate-200 text-[10px] leading-relaxed uppercase">Are you sure you want to terminate this party contract?</p>
                    </div>
                </div>
                <div class="p-6 pt-0 flex gap-4">
                    <button @click="executeAbort" :disabled="form.processing"
                        class="flex-1 py-3 bg-red-600/20 border-2 border-red-600 text-red-500 hover:bg-red-600 hover:text-white transition-all uppercase font-bold text-[9px] rounded active:scale-95">
                        {{ form.processing ? 'PURGING...' : 'PROCEED' }}
                    </button>
                    <button @click="showDeleteModal = false"
                        class="flex-1 py-3 bg-slate-800 border-2 border-slate-600 text-slate-400 hover:bg-slate-700 hover:text-white transition-all uppercase font-bold text-[9px] rounded active:scale-95">
                        CANCEL
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.rpg-panel {
    background-color: #1a1c2c;
    border-width: 4px;
    padding: 1rem;
    position: relative;
    box-shadow: 8px 8px 0px 0px rgba(0, 0, 0, 0.5);
}

textarea {
    resize: none;
}

.fixed {
    animation: fadeIn 0.2s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}
</style>