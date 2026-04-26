<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppBackgroundLayer from '@/Components/AppBackgroundLayer.vue';
import UserNavbar from '@/Components/UserNavbar.vue';

const props = defineProps({
    status: { type: Number, default: 500 },
    title: { type: String, default: 'SYSTEM_FAILURE' },
    message: { type: String, default: 'Terjadi gangguan di server.' },
});

const page = usePage();
const isAuthenticated = computed(() => Boolean(page.props?.auth?.user));

const meta = computed(() => {
    if (props.status === 400) {
        return {
            status: 400,
            code: '400_BAD_REQUEST',
            headline: 'Permintaan Tidak Valid',
            actionText: 'Periksa data input lalu coba lagi.',
            accent: 'text-amber-400',
        };
    }

    if (props.status === 401) {
        return {
            status: 401,
            code: '401_UNAUTHORIZED',
            headline: 'Login Diperlukan',
            actionText: 'Kamu perlu login ulang untuk melanjutkan.',
            accent: 'text-orange-300',
        };
    }

    if (props.status === 403) {
        return {
            status: 403,
            code: '403_ACCESS_DENIED',
            headline: 'Akses Ditolak',
            actionText: 'Aksi ini tidak tersedia untuk role atau mode akun kamu saat ini.',
            accent: 'text-rose-300',
        };
    }

    if (props.status === 404) {
        return {
            status: 404,
            code: '404_NOT_FOUND',
            headline: 'Halaman Tidak Ditemukan',
            actionText: 'Periksa URL atau kembali ke halaman utama.',
            accent: 'text-cyan-300',
        };
    }

    if (props.status === 419) {
        return {
            status: 419,
            code: '419_SESSION_EXPIRED',
            headline: 'Sesi Kedaluwarsa',
            actionText: 'Sesi kamu kedaluwarsa. Muat ulang lalu kirim ulang aksinya.',
            accent: 'text-yellow-300',
        };
    }

    if (props.status === 429) {
        return {
            status: 429,
            code: '429_RATE_LIMITED',
            headline: 'Terlalu Banyak Permintaan',
            actionText: 'Aksi terlalu cepat. Tunggu sebentar lalu coba lagi.',
            accent: 'text-orange-300',
        };
    }

    return {
        status: 500,
        code: '500_SERVER_ERROR',
        headline: 'Gangguan Sistem',
        actionText: 'Tunggu sebentar, lalu muat ulang halaman.',
        accent: 'text-red-400',
    };
});

const primaryHref = computed(() => (isAuthenticated.value ? route('lobby') : route('landing')));
const primaryLabel = computed(() => (isAuthenticated.value ? 'Kembali ke Lobby' : 'Kembali ke Landing'));
const footerActionHref = computed(() => (isAuthenticated.value ? route('profile.dashboard') : route('login')));
const footerActionLabel = computed(() => (isAuthenticated.value ? 'Buka Profil' : 'Login'));

const reload = () => window.location.reload();
</script>

<template>
    <Head :title="`Error ${meta.status}`" />

    <div class="relative isolate min-h-screen bg-[#0a0c10] font-['Press_Start_2P'] text-[#4ed4d4] flex flex-col overflow-x-hidden">
        <AppBackgroundLayer overlay-class="bg-black/70" />
        <UserNavbar :show-guest-actions="true" />

        <main class="relative z-10 flex flex-1 items-center justify-center px-4 py-10">
            <section class="w-full max-w-2xl border-2 border-[#3d415f] bg-[#0f172a]/92 p-6 md:p-8">
                <p class="text-[10px] uppercase tracking-wider text-slate-400">
                    {{ meta.code }}
                </p>

                <h1 class="mt-3 text-2xl md:text-3xl font-black uppercase leading-tight" :class="meta.accent">
                    {{ title || meta.headline }}
                </h1>

                <p class="mt-4 text-sm md:text-base leading-relaxed text-slate-200">
                    {{ message || meta.actionText }}
                </p>

                <p class="mt-3 text-[10px] text-slate-400">
                    {{ meta.actionText }}
                </p>

                <div class="mt-6 flex flex-wrap gap-3">
                    <Link
                        :href="primaryHref"
                        class="btn-pixel bg-[#009999] text-black px-4 py-2 text-[10px] uppercase border-[#006666] hover:bg-[#4ed4d4] transition-colors"
                    >
                        {{ primaryLabel }}
                    </Link>
                    <button
                        type="button"
                        @click="reload"
                        class="btn-pixel bg-slate-900 text-cyan-200 px-4 py-2 text-[10px] uppercase border-slate-700 hover:bg-slate-700 transition-colors"
                    >
                        Muat Ulang
                    </button>
                </div>
            </section>
        </main>

        <footer class="relative z-10 mt-auto border-t-2 border-white/10 bg-[#1a1c2c]/55 p-5 text-center backdrop-blur-md">
            <p class="text-[8px] uppercase tracking-[0.2em] text-white/60">
                Build_Ver_1.1.0 // P-Quest Engine
            </p>
            <div class="mt-3">
                <Link :href="footerActionHref" class="text-[8px] uppercase text-cyan-300 hover:text-cyan-200">
                    {{ footerActionLabel }}
                </Link>
            </div>
        </footer>
    </div>
</template>

<style scoped>
.btn-pixel {
    border-width: 1px;
}

.btn-pixel:active {
    transform: translate(1px, 1px);
}
</style>
