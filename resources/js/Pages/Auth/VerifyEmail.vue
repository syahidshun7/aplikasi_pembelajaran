<script setup>
import { computed } from 'vue';
import AppBackgroundLayer from '@/Components/AppBackgroundLayer.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import UserNavbar from '@/Components/UserNavbar.vue';
import { useUserTheme } from '@/Composables/useUserTheme';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    status: {
        type: String,
    },
});

const form = useForm({});
const page = usePage();
const { themeMode } = useUserTheme();

const submit = () => {
    form.post(route('verification.send'));
};

const logout = () => {
    router.post(route('logout'), {}, {
        preserveScroll: false,
        preserveState: false,
        replace: true,
    });
};

const verificationLinkSent = computed(
    () => props.status === 'verification-link-sent',
);
const userEmail = computed(() => page.props.auth?.user?.email ?? '-');
const backgroundImage = computed(() => themeMode.value === 'light' ? '/images/bg-loby5.png' : '/images/bg-loby.png');
const backgroundOverlay = computed(() => themeMode.value === 'light' ? 'bg-[#f7f7f7]/88' : 'bg-black/65');
</script>

<template>
    <div
        data-app-surface="user"
        :data-theme="themeMode"
        class="verify-email-page user-theme-root user-theme-root--lobby-detail min-h-screen font-['Press_Start_2P'] selection:bg-[var(--accent)] relative isolate overflow-x-hidden text-[var(--text)]"
    >
        <Head title="Email Verification" />

        <AppBackgroundLayer
            :image="backgroundImage"
            :overlay-class="backgroundOverlay"
            :show-glow="themeMode !== 'light'"
        />

        <UserNavbar class="relative z-20" />

        <main class="relative z-10 flex min-h-[calc(100vh-76px)] items-center justify-center px-4 py-8">
            <section class="verify-email-card w-full max-w-xl border-4 border-[var(--panel-border)] bg-[var(--panel)]/95 p-5 shadow-[8px_8px_0_rgba(0,0,0,0.32)] backdrop-blur-md md:p-8">
                <h2 class="auth-verify-title text-yellow-500 text-[10px] text-center mb-6 border-b border-slate-800 pb-4 tracking-widest uppercase">
                    -- VERIFY_EMAIL_ACCESS --
                </h2>

                <div class="auth-verify-alert mb-4 border-2 border-amber-400/50 bg-amber-500/10 p-3 text-[10px] text-amber-200 leading-relaxed">
                    EMAIL KAMU BELUM TERVERIFIKASI.
                    Kamu tetap bisa eksplor game, tapi submit quest, shop, transfer gold, dan klaim reward terkunci sampai email akun ini diverifikasi:
                    <span class="auth-verify-email text-amber-100">{{ userEmail }}</span>
                </div>

                <div class="auth-verify-steps mb-4 text-[10px] text-slate-300 leading-relaxed space-y-2">
                    <p>Langkah verifikasi:</p>
                    <p>1. Buka inbox/spam/promotions email kamu.</p>
                    <p>2. Cari email verifikasi dari sistem ini.</p>
                    <p>3. Klik link verifikasi di email tersebut.</p>
                    <p>4. Kalau link tidak ada/kadaluarsa, klik tombol kirim ulang di bawah.</p>
                </div>

                <div
                    class="auth-verify-success mb-4 border-2 border-emerald-400/50 bg-emerald-500/10 p-3 text-[10px] font-medium text-emerald-200"
                    v-if="verificationLinkSent"
                >
                    Link verifikasi baru sudah dikirim. Silakan cek inbox email kamu.
                </div>

                <form @submit.prevent="submit">
                    <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <PrimaryButton
                            :class="{ 'opacity-25': form.processing }"
                            :disabled="form.processing"
                        >
                            Kirim Ulang Email Verifikasi
                        </PrimaryButton>

                        <div class="flex items-center justify-between gap-4 sm:justify-end">
                            <Link :href="route('lobby')" class="auth-verify-logout text-sm underline">
                                Kembali
                            </Link>
                            <button
                                type="button"
                                @click="logout"
                                class="auth-verify-logout text-sm underline focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                            >
                                Keluar
                            </button>
                        </div>
                    </div>
                </form>
            </section>
        </main>
    </div>
</template>
