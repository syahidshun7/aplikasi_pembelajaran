<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div 
        class="min-h-screen bg-[#0d1117] font-['Press_Start_2P'] selection:bg-[#009999] relative overflow-x-hidden text-[#4ed4d4] bg-cover bg-center bg-no-repeat bg-fixed"
        style="background-image: url('/images/bg-loby.png');" 
    >
        <div class="absolute inset-0 bg-black/60 z-0"></div>

        <div class="fixed inset-0 pointer-events-none bg-[linear-gradient(rgba(18,16,16,0)_50%,rgba(0,0,0,0.25)_50%),linear-gradient(90deg,rgba(255,0,0,0.06),rgba(0,255,0,0.02),rgba(0,0,255,0.06))] z-40 bg-[length:100%_2px,3px_100%] opacity-20"></div>

        <nav class="bg-[#1a1c2c]/90 backdrop-blur-sm border-b-4 border-[#3d415f] p-4 md:px-8 flex justify-between items-center shadow-2xl relative z-50">
            <div class="flex items-center gap-4">
                <Link href="/" class="flex items-center gap-4 group">
                    <div class="w-10 h-10 bg-[#0a0c10] flex items-center justify-center border-b-4 border-r-4 border-[#4ed4d4] overflow-hidden group-hover:scale-110 transition-transform">
                        <img src="/images/logo.png" alt="Logo" class="w-7 h-7 object-contain pixelated">
                    </div>
                    <h1 class="text-[#009999] text-[8px] md:text-sm tracking-tighter uppercase group-hover:text-[#4ed4d4]">
                        Lobby_Room_01
                    </h1>
                </Link>
            </div>

            <div class="flex gap-4">
                <Link :href="route('login')" 
                    class="text-[8px] bg-[#009999] text-black px-4 py-2 btn-pixel border-[#006666] uppercase font-bold hover:bg-[#4ed4d4] transition-all">
                    Login
                </Link>
                <Link :href="route('register')" 
                    class="text-[8px] bg-[#facc15] text-black px-4 py-2 btn-pixel border-[#854d0e] uppercase font-bold hover:bg-yellow-400 transition-all">
                    Register
                </Link>
            </div>
        </nav>

        <div class="flex flex-col items-center justify-center min-h-[calc(100vh-80px)] p-6 relative z-10">
            <div class="flex flex-col items-center mb-6">
                <h2 class="text-[#009999] text-[10px] tracking-[0.2em] animate-pulse">
                    > AUTHENTICATION_REQUIRED <
                </h2>
            </div>

            <div class="w-full sm:max-w-md p-8 rpg-panel shadow-[0_0_50px_rgba(0,0,0,0.9)] border-4 border-[#3d415f] bg-[#1a1c2c]/95 backdrop-blur-md">
                <slot />
            </div>

             <footer class="p-8 text-center bg-[#1a1c2c]/50 backdrop-blur-md border-t-2 border-white/10 mt-auto">
                <p class="text-[8px] text-white/50 uppercase tracking-[0.3em]">Build_Ver_1.1.0 // P-Quest Engine</p>
            </footer>
        </div>
    </div>
</template>

<style scoped>
.rpg-panel {
    position: relative;
    box-shadow: 12px 12px 0px 0px rgba(0, 0, 0, 0.5);
}

.btn-pixel {
    border-bottom-width: 4px;
    border-right-width: 4px;
}

.btn-pixel:active {
    border-bottom-width: 0px;
    border-right-width: 0px;
    transform: translate(2px, 2px);
}

.pixelated {
    image-rendering: pixelated;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.4; }
}
.animate-pulse {
    animation: pulse 2s infinite;
}
</style>