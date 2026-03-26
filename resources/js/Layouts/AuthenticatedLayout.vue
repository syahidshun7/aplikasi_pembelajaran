<script setup>
import { Link, usePage, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import FloatingChat from '@/Components/FloatingChat.vue';
import NotificationBell from '@/Components/NotificationBell.vue';
import { toast } from '@/Utils/Alert'; // Satukan import di atas
const page = usePage();
const auth = computed(() => page.props.auth);
const isStaff = computed(() => ['super_admin', 'admin', 'mentor'].includes(String(auth.value?.user?.role || '').toLowerCase()));
const showFloatingChat = computed(() => Boolean(auth.value?.user));
const mobileMenuOpen = ref(false);
const isEmailUnverified = computed(() => !!(auth.value?.user && !auth.value.user.email_verified_at));
const isEmailVerifiedSuccess = computed(() => page.url.includes('verified=1') && !isEmailUnverified.value);
const profileVerificationHref = computed(() => `${route('profile.edit')}#email-verification`);

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

            <div class="hidden md:flex items-center">
                <template v-if="auth.user">
                    <div class="nav-dock">
                        <Link v-if="isStaff" :href="route('admin.dashboard')" class="nav-action nav-action--admin">
                            Admin
                        </Link>

                        <Link :href="route('profile.dashboard')" class="nav-action nav-action--profile">
                            <i class="fi fi-rr-user text-[10px] leading-none"></i>
                            Profile
                        </Link>

                        <Link :href="route('shop.index')" class="nav-action nav-action--shop">
                            <i class="fi fi-rr-shopping-cart text-[10px] leading-none"></i>
                            Shop
                        </Link>

                        <Link :href="route('hall.creations.index')" class="nav-action nav-action--hall">
                            <i class="fi fi-rr-lightbulb-on text-[10px] leading-none"></i>
                            Hall
                        </Link>

                        <NotificationBell />

                        <button @click="handleLogout" class="nav-action nav-action--logout" type="button">
                            <span class="sr-only">Logout</span>
                            [X]
                        </button>
                    </div>
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
                    class="w-full nav-action nav-action--admin justify-center"
                    @click="mobileMenuOpen = false"
                >
                    Admin
                </Link>

                <Link
                    :href="route('profile.dashboard')"
                    class="w-full nav-action nav-action--profile justify-center"
                    @click="mobileMenuOpen = false"
                >
                    <i class="fi fi-rr-user text-[10px] leading-none"></i>
                    Profile
                </Link>

                <Link
                    :href="route('shop.index')"
                    class="w-full nav-action nav-action--shop justify-center"
                    @click="mobileMenuOpen = false"
                >
                    <i class="fi fi-rr-shopping-cart text-[10px] leading-none"></i>
                    Shop
                </Link>

                <Link
                    :href="route('hall.creations.index')"
                    class="w-full nav-action nav-action--hall justify-center"
                    @click="mobileMenuOpen = false"
                >
                    <i class="fi fi-rr-lightbulb-on text-[10px] leading-none"></i>
                    Hall
                </Link>

                <Link
                    :href="route('notifications.index')"
                    class="w-full nav-action nav-action--notifications justify-center"
                    @click="mobileMenuOpen = false"
                >
                    <i class="fi fi-rr-bell text-[10px] leading-none"></i>
                    Notifications
                    <span
                        v-if="Number(page.props?.notificationCenter?.unread_count || 0) > 0"
                        class="rounded-full bg-cyan-300 px-2 py-[2px] text-[8px] font-bold text-black"
                    >
                        {{ Number(page.props?.notificationCenter?.unread_count || 0) }}
                    </span>
                </Link>

                <button
                    @click="handleLogout"
                    class="w-full nav-action nav-action--logout justify-center"
                    type="button"
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
        <footer class="mt-auto border-t-2 border-white/10 bg-[#1a1c2c]/50 p-6 text-center backdrop-blur-md md:p-8">
            <p class="break-words text-[7px] uppercase tracking-[0.18em] text-white/50 sm:text-[8px] sm:tracking-[0.3em]">Build_Ver_1.1.0 // P-Quest Engine</p>
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
