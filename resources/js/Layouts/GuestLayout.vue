<script setup>
import { Link, usePage, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppBackgroundLayer from '@/Components/AppBackgroundLayer.vue';
import { toast } from '@/Utils/Alert';

const page = usePage();

const auth = computed(() => page.props.auth || {});
const isStaff = computed(() => ['super_admin', 'admin', 'mentor'].includes(String(auth.value?.user?.role || '').toLowerCase()));

const handleLogout = () => {
    toast.confirm('QUIT GAME?', 'Are you sure you want to exit?')
        .then((result) => {
            if (result.isConfirmed) {
                router.post(route('logout'));
            }
        });
};
</script>

<template>
    <div
        data-app-surface="user"
        class="min-h-screen font-['Press_Start_2P'] selection:bg-[#009999] relative isolate overflow-x-hidden text-[var(--accent)]"
    >
        <AppBackgroundLayer overlay-class="bg-black/60" />

        <nav class="bg-[var(--panel)]/90 backdrop-blur-sm border-b-4 border-[var(--panel-border)] p-4 md:px-8 flex justify-between items-center shadow-2xl sticky top-0 z-50">
            <div class="flex items-center gap-4">
                <Link :href="route('lobby')" class="flex items-center gap-4 group">
                    <div class="w-10 h-10 bg-[var(--bg)] flex items-center justify-center border-b-4 border-r-4 border-[var(--accent)] overflow-hidden group-hover:scale-110 transition-transform">
                        <img src="/images/logo.png" alt="Logo" class="w-7 h-7 object-contain pixelated">
                    </div>
                    <h1 class="text-[var(--accent)] text-[8px] md:text-sm tracking-tighter uppercase group-hover:brightness-125">
                        DOOPTECH
                    </h1>
                </Link>
            </div>

            <div class="flex gap-2 md:gap-4 items-center">
                <template v-if="auth.user">
                    <Link v-if="isStaff" :href="route('admin.dashboard')"
                        class="text-[8px] bg-purple-600/80 text-white px-3 py-2 btn-pixel border-purple-900 uppercase font-bold hover:bg-purple-500 transition-colors">
                        Admin
                    </Link>

                    <Link :href="route('profile.dashboard')"
                        class="text-[8px] bg-[var(--panel-border)]/80 text-[var(--text)] px-3 py-2 btn-pixel border-[var(--panel)] uppercase font-bold hover:brightness-125 transition-colors">
                        Profile
                    </Link>

                    <button @click="handleLogout"
                        class="text-[8px] bg-red-900/80 text-white px-3 py-2 btn-pixel border-red-950 uppercase font-bold hover:bg-red-700 transition-colors">
                        [X]
                    </button>
                </template>

                <template v-else>
                    <Link :href="route('login')"
                        class="text-[8px] bg-[#009999] text-black px-4 py-2 btn-pixel border-[#006666] uppercase font-bold hover:brightness-125 transition-all">
                        Login
                    </Link>
                    <Link :href="route('register')"
                        class="text-[8px] bg-[#facc15] text-black px-4 py-2 btn-pixel border-[#854d0e] uppercase font-bold hover:bg-yellow-400 transition-all">
                        Register
                    </Link>
                </template>
            </div>
        </nav>

        <main class="flex flex-col items-center justify-center min-h-[calc(100vh-80px)] p-6 relative z-10">
            <div class="flex flex-col items-center mb-6">
                <h2 class="text-[var(--accent)] text-[10px] tracking-[0.2em] animate-pulse">
                    > AUTHENTICATION_REQUIRED <
                </h2>
            </div>

            <div class="w-full sm:max-w-md p-8 rpg-panel shadow-[0_0_50px_rgba(0,0,0,0.9)] border-4 border-[var(--panel-border)] bg-[var(--panel)]/95 backdrop-blur-md">
                <slot />
            </div>
        </main>

        <footer class="p-8 text-center bg-[var(--panel)]/50 backdrop-blur-md border-t-2 border-[var(--text)]/10 mt-auto">
            <p class="text-[8px] text-[var(--text)]/50 uppercase tracking-[0.3em]">Build_Ver_1.1.0 // P-Quest Engine</p>
        </footer>
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
    border-style: solid;
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