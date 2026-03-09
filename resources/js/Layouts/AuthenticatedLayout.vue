<script setup>
import { Link, usePage, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import FloatingChat from '@/Components/FloatingChat.vue';
import { toast } from '@/Utils/Alert'; // Satukan import di atas
const page = usePage();
const auth = computed(() => page.props.auth);
const isStaff = computed(() => ['admin', 'mentor'].includes(String(auth.value?.user?.role || '').toLowerCase()));
const showFloatingChat = computed(() => Boolean(auth.value?.user));
const mobileMenuOpen = ref(false);
const isEmailUnverified = computed(() => !!(auth.value?.user && !auth.value.user.email_verified_at));
const isEmailVerifiedSuccess = computed(() => page.url.includes('verified=1') && !isEmailUnverified.value);
const profileVerificationHref = computed(() => `${route('profile.edit', { tab: 'profile' })}#email-verification`);

const handleLogout = () => {
    toast.confirm('QUIT GAME?', 'Are you sure you want to exit?')
        .then((result) => {
            if (result.isConfirmed) {
                // Gunakan route() langsung, Inertia/Ziggy akan menanganinya
                mobileMenuOpen.value = false;
                router.post(route('logout'));
            }
        });
};

</script>

<template>
    <div class="min-h-screen bg-[#0d1117] font-['Press_Start_2P'] selection:bg-[#009999] relative overflow-x-hidden text-[#4ed4d4] bg-cover bg-center bg-no-repeat bg-fixed flex flex-col"
        style="background-image: url('/images/bg-loby.png');">
        <div class="absolute inset-0 bg-black/70 z-0"></div>

        <div
            class="fixed inset-0 pointer-events-none bg-[linear-gradient(rgba(18,16,16,0)_50%,rgba(0,0,0,0.25)_50%),linear-gradient(90deg,rgba(255,0,0,0.06),rgba(0,255,0,0.02),rgba(0,0,255,0.06))] z-40 bg-[length:100%_2px,3px_100%] opacity-10">
        </div>

        <nav
            class="bg-[#1a1c2c]/90 backdrop-blur-sm border-b-4 border-[#3d415f] p-4 md:px-8 flex justify-between items-center shadow-2xl sticky top-0 z-50">
            <div class="flex items-center gap-4">
                <Link :href="route('lobby')" class="flex items-center gap-4 group" @click="mobileMenuOpen = false">
                    <div
                        class="w-10 h-10 bg-[#0a0c10] flex items-center justify-center border-b-4 border-r-4 border-[#4ed4d4] overflow-hidden group-hover:scale-110 transition-transform">
                        <img src="/images/logo.png" alt="Logo" class="w-7 h-7 object-contain pixelated">
                    </div>
                    <h1
                        class="text-[#009999] text-[8px] md:text-sm tracking-tighter uppercase group-hover:text-[#4ed4d4]">
                        DOOPTECH
                    </h1>
                </Link>
            </div>

            <div class="hidden md:flex gap-4 items-center">
                <template v-if="auth.user">
                    <Link v-if="isStaff" :href="route('admin.dashboard')"
                        class="text-[8px] bg-purple-600/80 text-white px-3 py-2 btn-pixel border-purple-900 uppercase font-bold hover:bg-purple-500 transition-colors">
                        Admin
                    </Link>

                    <Link :href="route('profile.edit')"
                        class="text-[8px] bg-cyan-300 text-black px-3 py-2 btn-pixel border-cyan-700 uppercase font-bold hover:bg-cyan-200 transition-colors inline-flex items-center gap-1.5 shadow-[0_0_12px_rgba(45,212,191,0.28)]">
                        <i class="fi fi-rr-user text-[10px] leading-none"></i>
                        Profile
                    </Link>

                    <Link :href="route('shop.index')"
                        class="text-[8px] bg-yellow-400 text-black px-3 py-2 btn-pixel border-yellow-700 uppercase font-bold hover:bg-yellow-300 transition-colors inline-flex items-center gap-1.5 shadow-[0_0_12px_rgba(250,204,21,0.35)]">
                        <i class="fi fi-rr-shopping-cart text-[10px] leading-none"></i>
                        Shop
                    </Link>

                    <button @click="handleLogout"
                        class="text-[8px] bg-red-900/80 text-white px-3 py-2 btn-pixel border-red-950 uppercase font-bold hover:bg-red-700 transition-colors">
                        [X]
                    </button>
                </template>
            </div>

            <button
                v-if="auth.user"
                type="button"
                class="md:hidden inline-flex items-center justify-center w-10 h-10 border-2 border-slate-600 bg-slate-900/70 text-cyan-300"
                @click="mobileMenuOpen = !mobileMenuOpen"
                :aria-expanded="mobileMenuOpen ? 'true' : 'false'"
                aria-label="Toggle menu"
            >
                <i :class="mobileMenuOpen ? 'fi fi-rr-cross-small' : 'fi fi-rr-menu-burger'" class="text-[14px]"></i>
            </button>
        </nav>

        <div
            v-if="auth.user && mobileMenuOpen"
            class="md:hidden relative z-50 px-4 pb-4"
        >
            <div class="bg-[#1a1c2c]/95 backdrop-blur-sm border-2 border-[#3d415f] p-3 space-y-2 shadow-2xl">
                <Link
                    v-if="isStaff"
                    :href="route('admin.dashboard')"
                    class="w-full text-[8px] bg-purple-600/80 text-white px-3 py-2 btn-pixel border-purple-900 uppercase font-bold hover:bg-purple-500 transition-colors inline-flex items-center justify-center"
                    @click="mobileMenuOpen = false"
                >
                    Admin
                </Link>

                <Link
                    :href="route('profile.edit')"
                    class="w-full text-[8px] bg-cyan-300 text-black px-3 py-2 btn-pixel border-cyan-700 uppercase font-bold hover:bg-cyan-200 transition-colors inline-flex items-center justify-center gap-1.5 shadow-[0_0_12px_rgba(45,212,191,0.28)]"
                    @click="mobileMenuOpen = false"
                >
                    <i class="fi fi-rr-user text-[10px] leading-none"></i>
                    Profile
                </Link>

                <Link
                    :href="route('shop.index')"
                    class="w-full text-[8px] bg-yellow-400 text-black px-3 py-2 btn-pixel border-yellow-700 uppercase font-bold hover:bg-yellow-300 transition-colors inline-flex items-center justify-center gap-1.5 shadow-[0_0_12px_rgba(250,204,21,0.35)]"
                    @click="mobileMenuOpen = false"
                >
                    <i class="fi fi-rr-shopping-cart text-[10px] leading-none"></i>
                    Shop
                </Link>

                <button
                    @click="handleLogout"
                    class="w-full text-[8px] bg-red-900/80 text-white px-3 py-2 btn-pixel border-red-950 uppercase font-bold hover:bg-red-700 transition-colors"
                >
                    [X]
                </button>
            </div>
        </div>

        <div v-if="isEmailUnverified" class="relative z-20 px-4 md:px-8 pt-4">
            <div class="border-2 border-amber-400/60 bg-amber-500/10 p-3 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                <div class="text-[9px] leading-relaxed text-amber-100 uppercase tracking-wide">
                    Email belum terverifikasi. Buka profile untuk kirim ulang verifikasi, lalu cek inbox/spam.
                </div>
                <Link
                    :href="profileVerificationHref"
                    class="text-[8px] bg-amber-300 text-black px-3 py-2 btn-pixel border-amber-700 uppercase font-bold hover:bg-amber-200 transition-colors text-center"
                >
                    Buka Profile
                </Link>
            </div>
        </div>
        <div v-else-if="isEmailVerifiedSuccess" class="relative z-20 px-4 md:px-8 pt-4">
            <div class="border-2 border-emerald-400/60 bg-emerald-500/15 p-3 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                <div class="text-[9px] leading-relaxed text-emerald-100 uppercase tracking-wide">
                    Verifikasi email berhasil. Semua fitur akun sekarang sudah terbuka.
                </div>
                <Link
                    :href="route('lobby')"
                    class="text-[8px] bg-emerald-300 text-black px-3 py-2 btn-pixel border-emerald-700 uppercase font-bold hover:bg-emerald-200 transition-colors text-center"
                >
                    Ke Home User
                </Link>
            </div>
        </div>

        <main class="relative z-10 p-4 md:p-8 animate-in fade-in zoom-in-95 duration-500 flex-1">
            <slot />
        </main>
        <footer class="p-8 text-center bg-[#1a1c2c]/50 backdrop-blur-md border-t-2 border-white/10 mt-auto">
            <p class="text-[8px] text-white/50 uppercase tracking-[0.3em]">Build_Ver_1.1.0 // P-Quest Engine</p>
        </footer>

        <FloatingChat v-if="showFloatingChat" />
    </div>
</template>

<style scoped>
/* Pastikan font pixelated untuk logo */
.pixelated {
    image-rendering: pixelated;
}

/* Button Style */
.btn-pixel {
    border-bottom-width: 4px;
    border-right-width: 4px;
}

.btn-pixel:active {
    border-bottom-width: 0px;
    border-right-width: 0px;
    transform: translate(2px, 2px);
}

/* Animasi Fade In untuk perpindahan halaman */
.animate-in {
    animation-fill-mode: forwards;
}
</style>
