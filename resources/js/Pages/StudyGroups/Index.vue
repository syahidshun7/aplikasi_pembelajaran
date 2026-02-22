<script setup>
import { useForm } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

// Form untuk menampung input kode
const joinForm = useForm({
    invite_code: '',
});

const submitJoin = () => {
    joinForm.post(route('groups.join'), {
        onSuccess: () => {
            joinForm.reset();
            Swal.fire({
                icon: 'success',
                title: 'SUCCESS',
                text: 'Berhasil bergabung dengan party!',
                background: '#1a1c2c',
                color: '#4ed4d4'
            });
        },
        onError: (errors) => {
            Swal.fire({
                icon: 'error',
                title: 'JOIN_FAILED',
                text: Object.values(errors)[0],
                background: '#1a1c2c',
                color: '#ff4d4d'
            });
        }
    });
};
</script>

<template>
    <div class="rpg-panel border-cyan-500">
        <h2 class="text-cyan-400 mb-4">>> JOIN_VIA_CODE</h2>
        <form @submit.prevent="submitJoin" class="space-y-4">
            <input 
                v-model="joinForm.invite_code" 
                type="text" 
                class="w-full bg-black border-2 border-slate-700 p-2 text-cyan-400 text-center tracking-widest uppercase"
                placeholder="ENTER_INVITE_CODE"
            >
            <button 
                type="submit" 
                :disabled="joinForm.processing"
                class="w-full py-2 border-2 border-cyan-500 text-cyan-500 hover:bg-cyan-500 hover:text-black transition-all"
            >
                {{ joinForm.processing ? 'CONNECTING...' : 'REQUEST_ACCESS' }}
            </button>
        </form>
    </div>
</template>