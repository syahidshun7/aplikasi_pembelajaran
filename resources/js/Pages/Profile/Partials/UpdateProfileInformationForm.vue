<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { toast } from '@/Utils/Alert';
import Cropper from 'cropperjs';
import 'cropperjs/dist/cropper.css';

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
const avatarPreview = ref(user.profile_photo ? `/storage/${user.profile_photo}` : '');
const cropModalOpen = ref(false);
const cropSourceUrl = ref('');
const cropImageRef = ref(null);
let cropperInstance = null;
let cropSourceObjectUrl = null;
let avatarObjectUrl = null;

const form = useForm({
    _method: 'PATCH',
    name: user.name,
    username: user.username || '', // Sekarang akan terisi dari $userData di Controller
    email: user.email,
    job_id: user.job_id || '',
    bio: user.bio || '',
    experience: user.experience || '',
    location: user.location || '',
    skills_text: Array.isArray(user.skills) ? user.skills.join(', ') : (user.skills || ''),
    profile_photo: null,
});

const verificationForm = useForm({});

const browsePhoto = () => {
    photoInput.value.click();
};

const handleFileChange = (e) => {
    const file = e.target.files && e.target.files[0] ? e.target.files[0] : null;
    e.target.value = '';
    if (!file) {
        avatarPreview.value = user.profile_photo ? `/storage/${user.profile_photo}` : '';
        return;
    }

    openCropper(file);
};

const submit = () => {
    form.post(route('profile.update'), {
        preserveScroll: true,
        onSuccess: () => {
            form.profile_photo = null;
        },
    });
};

const openCropper = async (file) => {
    if (cropSourceObjectUrl) {
        URL.revokeObjectURL(cropSourceObjectUrl);
    }
    cropSourceObjectUrl = URL.createObjectURL(file);
    cropSourceUrl.value = cropSourceObjectUrl;
    cropModalOpen.value = true;

    await nextTick();

    if (!cropImageRef.value) return;
    if (cropperInstance) {
        cropperInstance.destroy();
        cropperInstance = null;
    }

    cropperInstance = new Cropper(cropImageRef.value, {
        aspectRatio: 1,
        viewMode: 1,
        autoCropArea: 1,
        background: false,
        responsive: true,
    });
};

const closeCropper = () => {
    cropModalOpen.value = false;
    if (cropperInstance) {
        cropperInstance.destroy();
        cropperInstance = null;
    }
    if (cropSourceObjectUrl) {
        URL.revokeObjectURL(cropSourceObjectUrl);
        cropSourceObjectUrl = null;
    }
    cropSourceUrl.value = '';
};

const applyCrop = () => {
    if (!cropperInstance) return;
    const canvas = cropperInstance.getCroppedCanvas({
        width: 512,
        height: 512,
        imageSmoothingEnabled: true,
        imageSmoothingQuality: 'high',
    });

    canvas.toBlob((blob) => {
        if (!blob) return;
        const croppedFile = new File([blob], 'avatar.png', { type: 'image/png' });
        form.profile_photo = croppedFile;

        if (avatarObjectUrl) {
            URL.revokeObjectURL(avatarObjectUrl);
        }
        avatarObjectUrl = URL.createObjectURL(blob);
        avatarPreview.value = avatarObjectUrl;

        closeCropper();
    }, 'image/png', 0.92);
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

onBeforeUnmount(() => {
    closeCropper();
    if (avatarObjectUrl) {
        URL.revokeObjectURL(avatarObjectUrl);
        avatarObjectUrl = null;
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
                            <img v-if="avatarPreview" 
                                 :src="avatarPreview" 
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
                            class="profile-avatar-button inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-[10px] text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
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

            <div>
                <InputLabel for="location" value="Location" />
                <TextInput
                    id="location"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.location"
                    autocomplete="address-level2"
                    placeholder="e.g. Jakarta"
                />
                <InputError class="mt-2" :message="form.errors.location" />
            </div>

            <div>
                <InputLabel for="experience" value="Experience / Tagline" />
                <TextInput
                    id="experience"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.experience"
                    placeholder="e.g. Front-end Developer"
                />
                <InputError class="mt-2" :message="form.errors.experience" />
            </div>

            <div>
                <InputLabel for="skills_text" value="Skills" />
                <TextInput
                    id="skills_text"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.skills_text"
                    placeholder="Vue, Laravel, UI Design"
                />
                <p class="mt-2 text-[10px] text-slate-500">
                    Pisahkan skill dengan koma supaya tampil sebagai badge di profil.
                </p>
                <InputError class="mt-2" :message="form.errors.skills_text" />
            </div>

            <div class="md:col-span-2">
                <InputLabel for="bio" value="Bio" />
                <textarea
                    id="bio"
                    v-model="form.bio"
                    class="mt-1 block min-h-[120px] w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    placeholder="Ceritakan sedikit tentang dirimu, fokus belajar, atau karya yang sedang kamu bangun."
                />
                <InputError class="mt-2" :message="form.errors.bio" />
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

        <Modal :show="cropModalOpen" maxWidth="xl" @close="closeCropper">
            <div class="p-4">
                <h3 class="text-sm font-semibold text-slate-900 uppercase tracking-widest">Crop Avatar 1:1</h3>
                <div class="mt-4 h-[360px] w-full overflow-hidden border border-slate-200 bg-slate-100">
                    <img ref="cropImageRef" :src="cropSourceUrl" alt="Crop avatar" class="max-h-[360px] w-full object-contain" />
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <button
                        type="button"
                        class="inline-flex items-center px-4 py-2 border border-slate-300 rounded-md text-[10px] uppercase tracking-widest text-slate-600 hover:bg-slate-50"
                        @click="closeCropper"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center px-4 py-2 border border-indigo-600 bg-indigo-600 rounded-md text-[10px] uppercase tracking-widest text-white hover:bg-indigo-500"
                        @click="applyCrop"
                    >
                        Use Crop
                    </button>
                </div>
            </div>
        </Modal>

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
