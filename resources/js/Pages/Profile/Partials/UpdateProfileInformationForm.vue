<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { nextTick, onMounted, ref } from 'vue';
import { toast } from '@/Utils/Alert';

// 1. Tangkap props 'user' yang dikirim dari ProfileController@edit
const props = defineProps({
    user: {
        type: Object,
    },
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
    jobs: {
        type: Array,
        default: () => [],
    },
});

// 2. Gunakan props.user sebagai sumber data utama form agar sinkron dengan Controller
const user = props.user || usePage().props.auth.user;
const photoInput = ref(null);
const showVerificationModal = ref(false);
const verificationNoticeRef = ref(null);
const verificationActionRef = ref(null);

const form = useForm({
    _method: 'PATCH',
    name: user.name,
    username: user.username || '', // Sekarang akan terisi dari $userData di Controller
    email: user.email,
    job_id: user.job_id || '',
    profile_photo: null,
});

const verificationForm = useForm({});

const browsePhoto = () => {
    photoInput.value.click();
};

const handleFileChange = (e) => {
    form.profile_photo = e.target.files[0];
};

const submit = () => {
    form.post(route('profile.update'), {
        preserveScroll: true,
        onSuccess: () => {
            form.profile_photo = null;
        },
    });
};

const openVerificationModal = () => {
    showVerificationModal.value = true;
};

const closeVerificationModal = () => {
    showVerificationModal.value = false;
};

const sendVerificationEmail = () => {
    verificationForm.post(route('verification.send'), {
        preserveScroll: true,
        onSuccess: () => {
            closeVerificationModal();
        },
    });
};

const focusVerificationSection = async () => {
    await nextTick();

    if (!verificationNoticeRef.value) {
        return;
    }

    verificationNoticeRef.value.scrollIntoView({
        behavior: 'smooth',
        block: 'center',
    });

    verificationActionRef.value?.focus();
};

onMounted(() => {
    if (props.status === 'verification-link-sent') {
        toast.success('EMAIL VERIFIKASI TERKIRIM', 'Cek inbox/spam email kamu lalu klik link verifikasi.');
    }

    if (props.status === 'email-verification-required') {
        toast.error('EMAIL BELUM TERVERIFIKASI', 'Kirim ulang verifikasi dari halaman profile untuk membuka fitur tertentu.');
    }

    const shouldFocusVerification = window.location.hash === '#email-verification'
        || props.status === 'email-verification-required';

    if (shouldFocusVerification && props.mustVerifyEmail && user.email_verified_at === null) {
        focusVerificationSection();
    }
});
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900">
                Profile Information
            </h2>
            <p class="mt-1 text-sm text-gray-600">
                Update your account's profile information, avatar, and username.
            </p>
        </header>

        <form @submit.prevent="submit" class="mt-6 space-y-6">
            <div>
                <InputLabel for="profile_photo" value="Character Portrait" />
                
                <div class="mt-2 flex items-center gap-5">
                    <div class="relative group">
                        <div class="h-20 w-20 rounded-full overflow-hidden border-4 border-indigo-500 bg-slate-200 shadow-[4px_4px_0px_0px_rgba(0,0,0,0.2)]">
                            <img v-if="user.profile_photo" 
                                 :src="'/storage/' + user.profile_photo" 
                                 class="h-full w-full object-cover">
                            <div v-else class="h-full w-full flex items-center justify-center bg-indigo-100 text-indigo-400">
                                <span class="text-xs uppercase font-bold">No_Img</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col gap-2">
                        <input
                            ref="photoInput"
                            type="file"
                            class="hidden"
                            @change="handleFileChange"
                            accept="image/*"
                        />
                        
                        <button
                            type="button"
                            @click="browsePhoto"
                            class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-[10px] text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                        >
                            [ Edit_Avatar ]
                        </button>
                        
                        <p v-if="form.profile_photo" class="text-[10px] text-green-600 font-bold italic">
                            >> NEW_FILE: {{ form.profile_photo.name }}
                        </p>
                    </div>
                </div>
                <InputError class="mt-2" :message="form.errors.profile_photo" />
            </div>

            <div>
                <InputLabel for="username" value="Username" />
                <TextInput
                    id="username"
                    type="text"
                    class="mt-1 block w-full bg-gray-50"
                    v-model="form.username"
                    required
                    autocomplete="username"
                    placeholder="e.g. shadow_hunter"
                />
                <InputError class="mt-2" :message="form.errors.username" />
            </div>

            <div>
                <InputLabel for="name" value="Display Name" />
                <TextInput
                    id="name"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.name"
                    required
                    autofocus
                    autocomplete="name"
                />
                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div>
                <InputLabel for="email" value="Email" />
                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
                    autocomplete="username"
                />
                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div>
                <InputLabel for="job_id" value="Job / Path" />
                <select
                    id="job_id"
                    v-model="form.job_id"
                    class="mt-1 block w-full bg-[#0d1117] border-2 border-slate-700 text-cyan-400 p-2 text-[10px] focus:border-cyan-400 outline-none"
                >
                    <option value="">-- Choose Job --</option>
                    <option v-for="job in jobs" :key="job.id" :value="job.id">
                        {{ job.name }}
                    </option>
                </select>
                <InputError class="mt-2" :message="form.errors.job_id" />
            </div>

            <div
                v-if="mustVerifyEmail && user.email_verified_at === null"
                id="email-verification"
                ref="verificationNoticeRef"
                tabindex="-1"
                class="scroll-mt-28 rounded border border-amber-400/40 bg-amber-500/10 p-3 focus:outline-none focus:ring-2 focus:ring-amber-300/70"
            >
                <p class="mt-2 text-[9px] text-amber-300 uppercase leading-relaxed">
                    Email belum terverifikasi. Beberapa fitur dibatasi sampai verifikasi selesai.
                    <button
                        type="button"
                        ref="verificationActionRef"
                        @click="openVerificationModal"
                        class="ml-1 text-[9px] text-cyan-300 underline hover:text-cyan-100 focus:outline-none"
                    >
                        Verifikasi sekarang
                    </button>
                </p>
                <div v-show="status === 'verification-link-sent'" class="mt-2 text-[9px] font-medium text-emerald-300 uppercase">
                    Link verifikasi baru sudah dikirim ke email kamu.
                </div>
            </div>

            <div class="flex items-center gap-4">
                <PrimaryButton :disabled="form.processing">Sync_Data</PrimaryButton>

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p v-if="form.recentlySuccessful" class="text-sm text-gray-600">Saved.</p>
                </Transition>
            </div>
        </form>

        <Modal :show="showVerificationModal" @close="closeVerificationModal">
            <div class="p-6">
                <h3 class="text-sm font-semibold text-slate-900 uppercase">Verifikasi Email</h3>
                <p class="mt-3 text-sm text-slate-700 leading-relaxed">
                    Kami akan kirim ulang link verifikasi ke <strong>{{ user.email }}</strong>.
                    Setelah klik link dari email, kamu akan diarahkan ke Home dengan notifikasi verifikasi berhasil.
                </p>
                <div class="mt-5 flex items-center justify-end gap-3">
                    <button
                        type="button"
                        class="px-3 py-2 text-xs font-semibold uppercase border border-slate-300 text-slate-700 hover:bg-slate-100"
                        @click="closeVerificationModal"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        class="px-3 py-2 text-xs font-semibold uppercase bg-emerald-600 text-white border border-emerald-700 hover:bg-emerald-500 disabled:opacity-60"
                        :disabled="verificationForm.processing"
                        @click="sendVerificationEmail"
                    >
                        Kirim Link Verifikasi
                    </button>
                </div>
            </div>
        </Modal>
    </section>
</template>
