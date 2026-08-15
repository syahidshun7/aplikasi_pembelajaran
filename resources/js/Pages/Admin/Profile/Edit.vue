<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref } from 'vue';
import AdminNavbar from '@/Components/AdminNavbar.vue';

const props = defineProps({
    user: Object,
    mustVerifyEmail: Boolean,
    status: String,
    studyGroups: {
        type: Array,
        default: () => [],
    },
    hasGlobalAccess: Boolean,
});

const photoInput = ref(null);
const photoObjectUrl = ref('');
const avatarUrl = computed(() => photoObjectUrl.value || (
    props.user?.profile_photo ? `/storage/${props.user.profile_photo}` : ''
));
const roleLabel = computed(() => String(props.user?.role || '').toUpperCase().replaceAll('_', ' '));

const profileForm = useForm({
    _method: 'PATCH',
    name: props.user?.name || '',
    username: props.user?.username || '',
    email: props.user?.email || '',
    bio: props.user?.bio || '',
    experience: props.user?.experience || '',
    location: props.user?.location || '',
    skills_text: Array.isArray(props.user?.skills) ? props.user.skills.join(', ') : '',
    profile_photo: null,
});

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const verificationForm = useForm({});

const selectPhoto = () => photoInput.value?.click();

const handlePhoto = (event) => {
    const file = event.target.files?.[0] || null;
    if (!file) return;

    if (photoObjectUrl.value) URL.revokeObjectURL(photoObjectUrl.value);
    photoObjectUrl.value = URL.createObjectURL(file);
    profileForm.profile_photo = file;
};

const updateProfile = () => {
    profileForm.post(route('admin.profile.update'), {
        preserveScroll: true,
        onSuccess: () => {
            profileForm.profile_photo = null;
        },
    });
};

const updatePassword = () => {
    passwordForm.put(route('password.update'), {
        preserveScroll: true,
        errorBag: 'updatePassword',
        onSuccess: () => passwordForm.reset(),
    });
};

const sendVerification = () => {
    verificationForm.post(route('verification.send'), {
        preserveScroll: true,
    });
};

onBeforeUnmount(() => {
    if (photoObjectUrl.value) URL.revokeObjectURL(photoObjectUrl.value);
});
</script>

<template>
    <Head title="STAFF_PROFILE" />

    <div class="min-h-screen bg-[#0d1117] p-4 font-['Press_Start_2P'] text-[10px] text-[#4ed4d4] md:p-8">
        <div class="mx-auto max-w-7xl space-y-6">
            <AdminNavbar />

            <section class="rpg-panel border-emerald-500/50">
                <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div>
                        <p class="text-[8px] uppercase text-emerald-300">Staff_Identity</p>
                        <h1 class="mt-3 text-base uppercase text-white md:text-xl">Profile_Settings</h1>
                        <p class="mt-3 max-w-2xl font-sans text-[13px] leading-relaxed text-slate-400">
                            Kelola identitas dan keamanan akun operasional.
                        </p>
                    </div>
                    <Link
                        :href="route('dashboard')"
                        class="border border-slate-600 px-3 py-2 text-[8px] uppercase text-slate-300 hover:border-cyan-400 hover:text-white"
                    >
                        Back_To_Dashboard
                    </Link>
                </div>
            </section>

            <div
                v-if="status === 'profile-updated'"
                class="border border-emerald-500 bg-emerald-500/10 px-4 py-3 text-[8px] uppercase text-emerald-300"
            >
                Profile berhasil diperbarui.
            </div>
            <div
                v-if="status === 'verification-link-sent'"
                class="border border-cyan-500 bg-cyan-500/10 px-4 py-3 text-[8px] uppercase text-cyan-200"
            >
                Link verifikasi sudah dikirim. Periksa inbox atau folder spam.
            </div>

            <div class="grid gap-6 lg:grid-cols-[minmax(0,1.45fr)_minmax(300px,0.55fr)]">
                <section class="rpg-panel border-cyan-500/40">
                    <div class="border-b border-slate-700 pb-4">
                        <h2 class="text-[11px] uppercase text-white">Account_Information</h2>
                        <p class="mt-2 font-sans text-[12px] text-slate-400">Nama, username, email, dan avatar memakai akun utama yang sama.</p>
                    </div>

                    <form class="mt-5 space-y-5" @submit.prevent="updateProfile">
                        <div class="flex flex-col gap-4 border border-slate-700 bg-black/20 p-4 sm:flex-row sm:items-center">
                            <div class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden border-2 border-cyan-500 bg-slate-950">
                                <img v-if="avatarUrl" :src="avatarUrl" alt="" class="h-full w-full object-cover">
                                <span v-else class="text-[7px] uppercase text-slate-500">No_Image</span>
                            </div>
                            <div>
                                <input ref="photoInput" type="file" accept="image/png,image/jpeg" class="hidden" @change="handlePhoto">
                                <button
                                    type="button"
                                    class="border border-cyan-500 px-3 py-2 text-[8px] uppercase text-cyan-300 hover:bg-cyan-400 hover:text-black"
                                    @click="selectPhoto"
                                >
                                    Select_Avatar
                                </button>
                                <p class="mt-2 font-sans text-[11px] text-slate-500">JPG atau PNG, maksimal 2 MB.</p>
                                <p v-if="profileForm.errors.profile_photo" class="mt-2 font-sans text-[11px] text-rose-400">{{ profileForm.errors.profile_photo }}</p>
                            </div>
                        </div>

                        <label class="block">
                            <span class="text-[8px] uppercase text-slate-400">Display_Name</span>
                            <input v-model="profileForm.name" type="text" required class="admin-input mt-2">
                            <span v-if="profileForm.errors.name" class="mt-2 block font-sans text-[11px] text-rose-400">{{ profileForm.errors.name }}</span>
                        </label>

                        <label class="block">
                            <span class="text-[8px] uppercase text-slate-400">Username</span>
                            <input
                                v-model="profileForm.username"
                                type="text"
                                required
                                minlength="3"
                                maxlength="32"
                                pattern="[a-z0-9._-]{3,32}"
                                class="admin-input mt-2"
                            >
                            <span v-if="profileForm.errors.username" class="mt-2 block font-sans text-[11px] text-rose-400">{{ profileForm.errors.username }}</span>
                        </label>

                        <label class="block">
                            <span class="text-[8px] uppercase text-slate-400">Email</span>
                            <input v-model="profileForm.email" type="email" required class="admin-input mt-2">
                            <span v-if="profileForm.errors.email" class="mt-2 block font-sans text-[11px] text-rose-400">{{ profileForm.errors.email }}</span>
                        </label>

                        <div class="grid gap-5 border-t border-slate-700 pt-5 md:grid-cols-2">
                            <label class="block">
                                <span class="text-[8px] uppercase text-slate-400">Location</span>
                                <input
                                    v-model="profileForm.location"
                                    type="text"
                                    maxlength="255"
                                    placeholder="Jakarta"
                                    class="admin-input mt-2"
                                >
                                <span v-if="profileForm.errors.location" class="mt-2 block font-sans text-[11px] text-rose-400">{{ profileForm.errors.location }}</span>
                            </label>

                            <label class="block">
                                <span class="text-[8px] uppercase text-slate-400">Experience_Or_Tagline</span>
                                <input
                                    v-model="profileForm.experience"
                                    type="text"
                                    maxlength="255"
                                    placeholder="Laravel Developer / Educator"
                                    class="admin-input mt-2"
                                >
                                <span v-if="profileForm.errors.experience" class="mt-2 block font-sans text-[11px] text-rose-400">{{ profileForm.errors.experience }}</span>
                            </label>
                        </div>

                        <label class="block">
                            <span class="text-[8px] uppercase text-slate-400">Skills</span>
                            <input
                                v-model="profileForm.skills_text"
                                type="text"
                                maxlength="1000"
                                placeholder="Laravel, Vue, Teaching, UI Design"
                                class="admin-input mt-2"
                            >
                            <span class="mt-2 block font-sans text-[11px] text-slate-500">Pisahkan setiap skill dengan koma.</span>
                            <span v-if="profileForm.errors.skills_text" class="mt-2 block font-sans text-[11px] text-rose-400">{{ profileForm.errors.skills_text }}</span>
                        </label>

                        <label class="block">
                            <span class="text-[8px] uppercase text-slate-400">Bio</span>
                            <textarea
                                v-model="profileForm.bio"
                                maxlength="2000"
                                rows="5"
                                placeholder="Ceritakan pengalaman, fokus kerja, atau keahlianmu."
                                class="admin-input mt-2 min-h-[120px] resize-y"
                            />
                            <span class="mt-2 block text-right font-sans text-[10px] text-slate-600">{{ profileForm.bio.length }}/2000</span>
                            <span v-if="profileForm.errors.bio" class="mt-2 block font-sans text-[11px] text-rose-400">{{ profileForm.errors.bio }}</span>
                        </label>

                        <div
                            v-if="mustVerifyEmail && !user.email_verified_at"
                            class="border border-amber-500/60 bg-amber-500/10 p-4"
                        >
                            <p class="text-[8px] uppercase leading-relaxed text-amber-300">Email belum terverifikasi.</p>
                            <button
                                type="button"
                                :disabled="verificationForm.processing"
                                class="mt-3 border border-amber-400 px-3 py-2 text-[8px] uppercase text-amber-200 hover:bg-amber-300 hover:text-black disabled:opacity-50"
                                @click="sendVerification"
                            >
                                Send_Verification
                            </button>
                        </div>

                        <button
                            type="submit"
                            :disabled="profileForm.processing"
                            class="border border-emerald-500 bg-emerald-500/10 px-4 py-3 text-[8px] uppercase text-emerald-300 hover:bg-emerald-400 hover:text-black disabled:opacity-50"
                        >
                            {{ profileForm.processing ? 'Saving...' : 'Save_Profile' }}
                        </button>
                    </form>
                </section>

                <div class="space-y-6">
                    <section class="rpg-panel border-indigo-500/40">
                        <h2 class="text-[10px] uppercase text-white">Access_Context</h2>
                        <dl class="mt-5 space-y-4">
                            <div>
                                <dt class="text-[7px] uppercase text-slate-500">Role</dt>
                                <dd class="mt-2 text-[9px] uppercase text-indigo-300">{{ roleLabel }}</dd>
                            </div>
                            <div>
                                <dt class="text-[7px] uppercase text-slate-500">Job_Or_Program</dt>
                                <dd class="mt-2 text-[9px] uppercase text-cyan-300">{{ user.job || 'Not_Assigned' }}</dd>
                            </div>
                            <div>
                                <dt class="text-[7px] uppercase text-slate-500">Study_Group_Access</dt>
                                <dd class="mt-2 text-[9px] uppercase text-emerald-300">
                                    {{ hasGlobalAccess ? 'Global_Access' : `${studyGroups.length}_Assigned` }}
                                </dd>
                            </div>
                        </dl>

                        <div v-if="!hasGlobalAccess" class="mt-5 space-y-2 border-t border-slate-700 pt-4">
                            <div v-for="group in studyGroups" :key="group.uuid" class="border border-slate-700 bg-black/20 p-3">
                                <p class="text-[8px] uppercase text-white">{{ group.name }}</p>
                                <p class="mt-2 text-[7px] uppercase text-slate-500">{{ group.job || 'No_Job' }} / {{ group.role }}</p>
                            </div>
                            <p v-if="studyGroups.length === 0" class="font-sans text-[11px] text-slate-500">Belum ada Study Group yang ditugaskan.</p>
                        </div>
                    </section>

                    <section class="rpg-panel border-amber-500/40">
                        <h2 class="text-[10px] uppercase text-white">Account_Security</h2>
                        <form class="mt-5 space-y-4" @submit.prevent="updatePassword">
                            <label class="block">
                                <span class="text-[7px] uppercase text-slate-400">Current_Password</span>
                                <input v-model="passwordForm.current_password" type="password" required autocomplete="current-password" class="admin-input mt-2">
                                <span v-if="passwordForm.errors.current_password" class="mt-2 block font-sans text-[11px] text-rose-400">{{ passwordForm.errors.current_password }}</span>
                            </label>
                            <label class="block">
                                <span class="text-[7px] uppercase text-slate-400">New_Password</span>
                                <input v-model="passwordForm.password" type="password" required autocomplete="new-password" class="admin-input mt-2">
                                <span v-if="passwordForm.errors.password" class="mt-2 block font-sans text-[11px] text-rose-400">{{ passwordForm.errors.password }}</span>
                            </label>
                            <label class="block">
                                <span class="text-[7px] uppercase text-slate-400">Confirm_Password</span>
                                <input v-model="passwordForm.password_confirmation" type="password" required autocomplete="new-password" class="admin-input mt-2">
                                <span v-if="passwordForm.errors.password_confirmation" class="mt-2 block font-sans text-[11px] text-rose-400">{{ passwordForm.errors.password_confirmation }}</span>
                            </label>
                            <button
                                type="submit"
                                :disabled="passwordForm.processing"
                                class="border border-amber-500 px-3 py-2 text-[8px] uppercase text-amber-300 hover:bg-amber-400 hover:text-black disabled:opacity-50"
                            >
                                {{ passwordForm.processing ? 'Updating...' : 'Update_Password' }}
                            </button>
                        </form>
                    </section>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.rpg-panel {
    @apply relative border-4 bg-[#1a1c2c] p-5;
    box-shadow: 8px 8px 0 0 rgba(0, 0, 0, 0.5);
}

.admin-input {
    @apply block w-full border-2 border-slate-700 bg-[#0f101a] px-3 py-3 font-sans text-[13px] text-white outline-none transition-colors focus:border-cyan-400 focus:ring-0;
}
</style>
