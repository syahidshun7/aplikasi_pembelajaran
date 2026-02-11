<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-[#0d1117] font-pixel selection:bg-[#009999]">
        <div class="fixed inset-0 pointer-events-none bg-[linear-gradient(rgba(18,16,16,0)_50%,rgba(0,0,0,0.25)_50%),linear-gradient(90deg,rgba(255,0,0,0.06),rgba(0,255,0,0.02),rgba(0,0,255,0.06))] z-50 bg-[length:100%_2px,3px_100%]"></div>

        <div class="z-10 flex flex-col items-center">
            <Link href="/">
                <div
                    class="w-10 h-10 bg-[#0a0c10] flex items-center justify-center border-b-4 border-r-4 border-[#1a1c2c] overflow-hidden">
                    <img src="images/logo.png" alt="Logo" class="w-7 h-7 object-contain pixelated">
                </div>
            </Link>
            <h1 class="text-[#009999] text-xs mb-8 tracking-[0.2em] animate-pulse">AUTHENTICATION_REQUIRED</h1>
        </div>

        <div class="w-full sm:max-w-md mt-6 px-8 py-10 rpg-panel z-10 shadow-[0_0_20px_rgba(0,0,0,0.5)]">
            <slot />
        </div>

        <div class="mt-8 text-[8px] text-slate-600 z-10">
            SECURE_CONNECTION_ESTABLISHED // PORT_8080
        </div>
    </div>
</template>