<script setup>
import { computed } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    status: {
        type: String,
    },
});

const form = useForm({});
const page = usePage();

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
</script>

<template>
    <GuestLayout>
        <Head title="Email Verification" />

        <h2 class="text-yellow-500 text-[10px] text-center mb-6 border-b border-slate-800 pb-4 tracking-widest uppercase">
            -- VERIFY_EMAIL_ACCESS --
        </h2>

        <div class="mb-4 border-2 border-amber-400/50 bg-amber-500/10 p-3 text-[10px] text-amber-200 leading-relaxed">
            EMAIL KAMU BELUM TERVERIFIKASI.
            Untuk melanjutkan fitur tertentu, verifikasi dulu email akun: <span class="text-amber-100">{{ userEmail }}</span>
        </div>

        <div class="mb-4 text-[10px] text-slate-300 leading-relaxed space-y-2">
            <p>Langkah verifikasi:</p>
            <p>1. Buka inbox/spam/promotions email kamu.</p>
            <p>2. Cari email verifikasi dari sistem ini.</p>
            <p>3. Klik link verifikasi di email tersebut.</p>
            <p>4. Kalau link tidak ada/kadaluarsa, klik tombol kirim ulang di bawah.</p>
        </div>

        <div
            class="mb-4 border-2 border-emerald-400/50 bg-emerald-500/10 p-3 text-[10px] font-medium text-emerald-200"
            v-if="verificationLinkSent"
        >
            Link verifikasi baru sudah dikirim. Silakan cek inbox email kamu.
        </div>

        <form @submit.prevent="submit">
            <div class="mt-4 flex items-center justify-between">
                <PrimaryButton
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    Kirim Ulang Email Verifikasi
                </PrimaryButton>

                <button
                    type="button"
                    @click="logout"
                    class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                    Keluar
                </button>
            </div>
        </form>
    </GuestLayout>
</template>
