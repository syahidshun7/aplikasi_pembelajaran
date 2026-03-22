<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="rpg-panel border-cyan-500">
        <h2 class="text-cyan-400 mb-4">>> JOIN_VIA_HOME_BUTTON</h2>
        <p class="text-[10px] uppercase text-slate-300 leading-relaxed">
            Fitur join via kode sudah dinonaktifkan.
            Untuk bergabung ke party, buka halaman Home User lalu klik tombol
            <span class="text-emerald-400">Request_Access</span> pada kartu party.
        </p>
        <Link
            :href="route('lobby')"
            class="mt-4 inline-block border-2 border-cyan-500 px-4 py-2 text-cyan-300 hover:bg-cyan-500 hover:text-black transition-all uppercase text-[10px]"
        >
            Back_To_Home
        </Link>
    </div>
</template>
