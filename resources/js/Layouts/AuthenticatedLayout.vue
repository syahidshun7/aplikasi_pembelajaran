<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppBackgroundLayer from '@/Components/AppBackgroundLayer.vue';
import FloatingChat from '@/Components/FloatingChat.vue';
import UserNavbar from '@/Components/UserNavbar.vue';

const page = usePage();
const auth = computed(() => page.props.auth);
const showFloatingChat = computed(() => Boolean(auth.value?.user));
const isEmailUnverified = computed(() => !!(auth.value?.user && !auth.value.user.email_verified_at));
const isEmailVerifiedSuccess = computed(() => page.url.includes('verified=1') && !isEmailUnverified.value);
const profileVerificationHref = computed(() => `${route('profile.edit')}#email-verification`);
const isStaffPlayMode = computed(() => Boolean(auth.value?.user?.staff_play_mode));
const playerModeNotice = computed(() => String(auth.value?.user?.player_mode_notice || '').trim());
</script>

<template>
    <Head>
        <meta head-key="robots" name="robots" content="noindex,nofollow" />
    </Head>

    <div
        data-app-surface="user"
        class="user-theme-root min-h-screen font-['Press_Start_2P'] selection:bg-[var(--accent)] relative isolate overflow-x-hidden flex flex-col"
    >
        <AppBackgroundLayer />

        <UserNavbar />

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
        <div v-if="isStaffPlayMode" class="relative z-20 px-4 md:px-8 pt-4">
            <div class="border-2 border-cyan-400/60 bg-cyan-500/10 p-3 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                <div class="text-[9px] leading-relaxed text-cyan-100 uppercase tracking-wide">
                    {{ playerModeNotice || 'Mode preview aktif. Reward, leaderboard, dan akses kelas student tidak dihitung.' }}
                </div>
                <Link
                    :href="route('admin.dashboard')"
                    class="text-[8px] bg-cyan-300 text-black px-3 py-2 btn-pixel border-cyan-700 uppercase font-bold hover:bg-cyan-200 transition-colors text-center"
                >
                    Kembali ke Admin
                </Link>
            </div>
        </div>

        <main class="relative z-10 p-4 md:p-8 animate-in fade-in zoom-in-95 duration-500 flex-1">
            <slot />
        </main>
        <footer class="user-theme-footer mt-auto border-t-2 p-6 text-center backdrop-blur-md md:p-8">
            <p class="user-theme-muted break-words text-[7px] uppercase tracking-[0.18em] sm:text-[8px] sm:tracking-[0.3em]">Build_Ver_1.1.0 // P-Quest Engine</p>
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
