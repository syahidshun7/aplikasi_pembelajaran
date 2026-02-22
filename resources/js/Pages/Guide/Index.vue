<script setup>
import { Head, useForm, Link, usePage } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import AdminNavbar from '@/Components/AdminNavbar.vue';
import Swal from 'sweetalert2';

const props = defineProps({
    materi: Array // Data dari tabel 'guides'
});

// State untuk UI
const isEditing = ref(false);
const editId = ref(null);
const showDeleteModal = ref(false);
const materiIdToDelete = ref(null);

// 1. INITIALIZE SWEETALERT TOAST
const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
    background: '#1a1c2c',
    color: '#4ed4d4',
    didOpen: (toast) => {
        toast.addEventListener('mouseenter', Swal.stopTimer)
        toast.addEventListener('mouseleave', Swal.resumeTimer)
    }
});

// 2. FORM INITIALIZATION
const form = useForm({
    title: '',
    description: '',
    file: null,
});

// 3. COMPUTED
const getOldFileName = computed(() => {
    if (isEditing.value && editId.value) {
        const currentItem = props.materi.find(item => item.uuid === editId.value);
        if (currentItem && currentItem.file_path) {
            return currentItem.file_path.split('/').pop();
        }
    }
    return null;
});

// 4. METHODS
const handleFileUpload = (e) => {
    form.file = e.target.files[0];
};

const startEdit = (item) => {
    isEditing.value = true;
    editId.value = item.uuid;
    form.title = item.title;
    form.description = item.description || '';
    form.file = null;
    window.scrollTo({ top: 0, behavior: 'smooth' });
    
    Toast.fire({
        icon: 'info',
        title: 'EDIT_MODE_ACTIVATED'
    });
};

const cancelEdit = () => {
    isEditing.value = false;
    editId.value = null;
    form.reset();
};

const submit = () => {
    if (isEditing.value) {
        // UPDATE LOGIC (POST with PATCH Method Trick)
        form.post(route('materi.update', editId.value), {
            forceFormData: true,
            onBefore: (request) => {
                request.data._method = 'patch';
            },
            onSuccess: () => {
                cancelEdit();
                Toast.fire({
                    icon: 'success',
                    title: 'SCROLL_UPDATED'
                });
            },
            onError: (errors) => {
                Swal.fire({
                    icon: 'error',
                    title: 'MODIFICATION_FAILED',
                    text: Object.values(errors)[0],
                    background: '#1a1c2c',
                    color: '#ff4d4d',
                    confirmButtonColor: '#4f46e5'
                });
            }
        });
    } else {
        // STORE LOGIC
        form.post(route('materi.store'), {
            forceFormData: true,
            onSuccess: () => {
                form.reset();
                Toast.fire({
                    icon: 'success',
                    title: 'KNOWLEDGE_INSCRIBED'
                });
            },
            onError: (errors) => {
                Swal.fire({
                    icon: 'warning',
                    title: 'INSCRIPTION_ERROR',
                    text: Object.values(errors)[0],
                    background: '#1a1c2c',
                    color: '#facc15',
                    confirmButtonColor: '#4f46e5'
                });
            }
        });
    }
};

const confirmDelete = (uuid) => {
    materiIdToDelete.value = uuid;
    showDeleteModal.value = true;
};

const executeDelete = () => {
    if (materiIdToDelete.value) {
        form.delete(route('materi.destroy', materiIdToDelete.value), {
            onSuccess: () => {
                showDeleteModal.value = false;
                materiIdToDelete.value = null;
                Toast.fire({
                    icon: 'success',
                    title: 'SCROLL_PURGED'
                });
            },
            onError: () => {
                Toast.fire({
                    icon: 'error',
                    title: 'PURGE_FAILED'
                });
            }
        });
    }
};

// Pantau Flash Message dari Controller (with('message', ...))
watch(() => usePage().props.flash, (flash) => {
    if (flash?.message) {
        Toast.fire({
            icon: 'success',
            title: flash.message
        });
    }
}, { deep: true });

</script>

<template>
    <Head title="GUIDE_ARCHIVE" />

    <div class="min-h-screen bg-[#0d1117] p-4 md:p-8 font-['Press_Start_2P'] text-[#4ed4d4] text-[10px] relative">
        <div class="max-w-6xl mx-auto space-y-8">

            <AdminNavbar />

            <div class="flex justify-between items-center border-b-4 border-indigo-900 pb-4">
                <h1 class="text-xl uppercase tracking-widest animate-pulse">Guide_Library_System</h1>
                <Link href="/dashboard" class="text-slate-500 hover:text-white transition-colors uppercase">[Back_to_HQ]
                </Link>
            </div>

            <div class="grid grid-cols-12 gap-8">
                <div class="col-span-12 lg:col-span-5">
                    <div class="rpg-panel" :class="isEditing ? 'border-green-500/50' : 'border-indigo-500/50'">
                        <h2 class="mb-6 uppercase tracking-tighter"
                            :class="isEditing ? 'text-green-500' : 'text-indigo-400'">
                            >> {{ isEditing ? 'MODIFY_SCROLL_ID_' + editId.substring(0, 8) : 'INSCRIBE_NEW_KNOWLEDGE' }}
                        </h2>

                        <form @submit.prevent="submit" class="space-y-6">
                            <div>
                                <label class="block mb-2 text-white">TITLE_OF_SCROLL:</label>
                                <input v-model="form.title" type="text"
                                    class="w-full bg-black border-2 border-slate-700 p-2 focus:border-indigo-400 outline-none text-indigo-400 uppercase"
                                    placeholder="Enter scroll title..." required>
                            </div>

                            <div>
                                <label class="block mb-2 text-white">KNOWLEDGE_SUMMARY:</label>
                                <textarea v-model="form.description"
                                    class="w-full bg-black border-2 border-slate-700 p-2 text-[8px] uppercase focus:border-indigo-400 focus:ring-0"
                                    placeholder="Describe the content..."
                                    style="resize: vertical; min-height: 140px;"></textarea>
                            </div>

                            <div>
                                <label class="block mb-2 text-indigo-400">ATTACHMENT_PROTOCOL:</label>
                                <div
                                    class="bg-black/40 border-2 border-dashed border-slate-700 p-4 text-center relative">
                                    <input type="file" @change="handleFileUpload"
                                        class="text-[7px] text-slate-500 file:mr-4 file:py-1 file:px-2 file:border-2 file:border-indigo-900 file:bg-indigo-950 file:text-indigo-400 file:uppercase cursor-pointer w-full">

                                    <div v-if="isEditing && getOldFileName" class="mt-3 pt-3 border-t border-slate-800">
                                        <p class="text-[7px] text-slate-500 uppercase tracking-tighter">
                                            Current_Vault_File:
                                            <span class="text-yellow-500 italic">{{ getOldFileName }}</span>
                                        </p>
                                        <p class="text-[6px] text-indigo-900 mt-1 uppercase italic underline">
                                            *Upload new to overwrite current scroll
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="flex gap-2">
                                <button type="submit" :disabled="form.processing"
                                    class="flex-1 py-3 border-2 uppercase font-bold transition-all"
                                    :class="isEditing ? 'border-green-600 text-green-500 hover:bg-green-600 hover:text-black' : 'border-indigo-500 text-indigo-400 hover:bg-indigo-500 hover:text-black'">
                                    {{ form.processing ? 'SYNCING...' : (isEditing ? 'UPDATE_SCROLL' : 'ISSUE_GUIDE') }}
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
                        <h2 class="text-white mb-6 uppercase tracking-tighter">>> ARCHIVE_REGISTRY_BOARD</h2>

                        <div class="space-y-4">
                            <div v-for="item in materi" :key="item.uuid"
                                class="flex flex-col p-4 bg-slate-900/50 border-l-4 border-indigo-500 hover:bg-slate-800 transition-all">

                                <div class="flex justify-between items-start mb-2">
                                    <div class="flex-1">
                                        <div class="text-[8px] text-slate-500 mb-1 uppercase tracking-tighter">REF_ID:
                                            {{ item.uuid }}</div>
                                        <div class="text-white uppercase tracking-tight">{{ item.title }}</div>
                                    </div>
                                    <div v-if="item.file_path" class="text-indigo-400 text-[7px] animate-pulse">
                                        [DOC_ATTACHED]
                                    </div>
                                </div>

                                <div v-if="item.description"
                                    class="text-[7px] text-slate-500 italic mb-4 border-t border-slate-800 pt-2 leading-loose">
                                    > {{ item.description }}
                                </div>

                                <div class="flex gap-4 self-end mt-2">
                                    <button @click="startEdit(item)"
                                        class="text-green-500 hover:text-white text-[8px] uppercase font-bold">[Edit]</button>
                                    <button @click="confirmDelete(item.uuid)"
                                        class="text-red-500 hover:text-white text-[8px] uppercase font-bold">[Purge]</button>
                                </div>
                            </div>

                            <div v-if="materi.length === 0"
                                class="py-12 text-center text-slate-700 italic uppercase text-[8px]">
                                The archive vaults are currently empty...
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showDeleteModal"
            class="fixed inset-0 z-[100] flex items-center justify-center bg-black/90 backdrop-blur-md p-4">
            <div
                class="w-full max-w-lg bg-[#121212] border-2 border-red-600 shadow-[0_0_30px_rgba(220,38,38,0.3)] overflow-hidden rounded-lg">
                <div class="bg-red-600/10 border-b-2 border-red-600 p-4 flex items-center gap-3">
                    <span class="text-red-500 text-lg">⚠</span>
                    <h2 class="text-red-500 font-bold uppercase tracking-tighter text-[10px]">» WARNING: PURGE_PROTOCOL
                    </h2>
                </div>
                <div class="p-8 space-y-6">
                    <div class="border-l-2 border-red-900 pl-4">
                        <p class="text-slate-200 text-[10px] leading-relaxed uppercase">Are you sure you want to
                            permanently delete this knowledge scroll?</p>
                    </div>
                </div>
                <div class="p-6 pt-0 flex gap-4">
                    <button @click="executeDelete" :disabled="form.processing"
                        class="flex-1 py-3 bg-red-600/20 border-2 border-red-600 text-red-500 hover:bg-red-600 hover:text-white transition-all uppercase font-bold text-[9px] rounded active:scale-95">
                        {{ form.processing ? 'PURGING...' : 'EXECUTE' }}
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
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

/* Custom Scrollbar for RPG feel */
.custom-scroll::-webkit-scrollbar {
    width: 4px;
}
.custom-scroll::-webkit-scrollbar-track {
    background: #0d1117;
}
.custom-scroll::-webkit-scrollbar-thumb {
    background: #4f46e5;
}
</style>