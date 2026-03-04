<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref } from 'vue';

const props = defineProps({
    jobs: {
        type: Array,
        default: () => [],
    },
    turnstile: {
        type: Object,
        default: () => ({
            enabled: false,
            site_key: null,
        }),
    },
});

const form = useForm({
    name: '',
    email: '',
    job_id: '',
    password: '',
    password_confirmation: '',
    'cf-turnstile-response': '',
});

const isJobModalOpen = ref(false);
const turnstileContainer = ref(null);
const turnstileWidgetId = ref(null);
const turnstileError = ref('');
const selectedJob = computed(() => props.jobs.find((job) => String(job.id) === String(form.job_id)) ?? null);
const isTurnstileEnabled = computed(() => !!(props.turnstile?.enabled && props.turnstile?.site_key));

const renderTurnstile = async () => {
    if (!isTurnstileEnabled.value) return;
    await nextTick();

    if (!window.turnstile || !turnstileContainer.value || turnstileWidgetId.value !== null) {
        return;
    }

    turnstileWidgetId.value = window.turnstile.render(turnstileContainer.value, {
        sitekey: props.turnstile.site_key,
        callback: (token) => {
            form['cf-turnstile-response'] = token;
            turnstileError.value = '';
        },
        'expired-callback': () => {
            form['cf-turnstile-response'] = '';
        },
        'error-callback': () => {
            form['cf-turnstile-response'] = '';
            turnstileError.value = 'Widget CAPTCHA gagal dimuat. Refresh halaman lalu coba lagi.';
        },
    });
};

const loadTurnstileScript = async () => {
    if (!isTurnstileEnabled.value) return;

    if (window.turnstile) {
        await renderTurnstile();
        return;
    }

    const scriptId = 'cf-turnstile-script';
    const existing = document.getElementById(scriptId);
    if (existing) {
        existing.addEventListener('load', renderTurnstile, { once: true });
        return;
    }

    const script = document.createElement('script');
    script.id = scriptId;
    script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
    script.async = true;
    script.defer = true;
    script.addEventListener('load', renderTurnstile, { once: true });
    document.head.appendChild(script);
};

const openJobModal = () => {
    isJobModalOpen.value = true;
};

const closeJobModal = () => {
    isJobModalOpen.value = false;
};

const chooseJob = (jobId) => {
    form.job_id = String(jobId);
    isJobModalOpen.value = false;
};

const submit = () => {
    form.post(route('register'), {
        onFinish: () => {
            form.reset('password', 'password_confirmation');
            if (window.turnstile && turnstileWidgetId.value !== null) {
                window.turnstile.reset(turnstileWidgetId.value);
            }
            form['cf-turnstile-response'] = '';
        },
    });
};

onMounted(async () => {
    await loadTurnstileScript();
});
</script>

<template>
    <GuestLayout>
        <Head title="Register | P-QUEST" />

        <form @submit.prevent="submit" class="space-y-4">
            <h2 class="text-yellow-500 text-[10px] text-center mb-6 border-b border-slate-800 pb-4 tracking-widest uppercase">
                -- NEW_HERO_REGISTRATION --
            </h2>

            <div>
                <label class="block text-[#009999] text-[8px] uppercase mb-1">Hero_Name</label>
                <input type="text" v-model="form.name" :class="[
                    'w-full bg-black border-2 text-white p-2 focus:ring-0 text-[10px] font-pixel',
                    form.errors.name ? 'border-red-500 focus:border-red-500' : 'border-[#333333] focus:border-[#009999]'
                ]" required />
                <div v-if="form.errors.name" class="mt-2 text-red-500 text-[8px] italic">{{ form.errors.name }}</div>
            </div>

            <div class="mt-3">
                <label class="block text-[#009999] text-[8px] uppercase mb-1">Email_Address</label>
                <input type="email" v-model="form.email" :class="[
                    'w-full bg-black border-2 text-white p-2 focus:ring-0 text-[10px] font-pixel',
                    form.errors.email ? 'border-red-500 focus:border-red-500' : 'border-[#333333] focus:border-[#009999]'
                ]" required />
                <div v-if="form.errors.email" class="mt-2 text-red-500 text-[8px] italic">{{ form.errors.email }}</div>
            </div>

            <div class="mt-3">
                <label class="block text-[#009999] text-[8px] uppercase mb-1">Jobs_Path</label>
                <div :class="[
                    'border-2 p-3 bg-black',
                    form.errors.job_id ? 'border-red-500' : 'border-[#333333]'
                ]">
                    <button
                        type="button"
                        @click="openJobModal"
                        class="w-full text-left border-2 border-[#333333] hover:border-[#009999] transition-all p-3 bg-[#0d1117]"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-[8px] uppercase text-[#009999] mb-1">Selected_Job</p>
                                <p v-if="selectedJob" class="text-[9px] uppercase text-white">{{ selectedJob.name }}</p>
                                <p v-else class="text-[9px] uppercase text-slate-500">-- Choose Job Path --</p>
                            </div>
                            <span class="text-[8px] uppercase px-3 py-1 bg-[#009999] text-black border border-[#006666]">Choose</span>
                        </div>
                    </button>
                    <input type="hidden" v-model="form.job_id" />
                </div>
                <div v-if="isJobModalOpen" class="fixed inset-0 z-[70] flex items-center justify-center p-4">
                    <div class="absolute inset-0 bg-black/80" @click="closeJobModal"></div>
                    <div class="relative z-10 w-full max-w-6xl border-2 border-white/10 bg-[#1a1c2c]/95 backdrop-blur-md p-4 md:p-6 shadow-2xl max-h-[92vh] overflow-hidden">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-[10px] md:text-xs uppercase text-white">Select Job Path</h3>
                            <button type="button" @click="closeJobModal" class="text-[8px] uppercase px-3 py-1 bg-slate-700 text-white border border-slate-500 hover:bg-slate-600">
                                Close
                            </button>
                        </div>

                        <div class="jobs-carousel flex gap-3 overflow-x-auto pb-2 snap-x snap-mandatory">
                            <article
                                v-for="(job, index) in jobs"
                                :key="job.id"
                                class="job-card snap-start shrink-0 w-[190px] h-[230px] p-[4px] border shadow-[0_6px_12px_rgba(15,23,42,0.25)]"
                                :class="{
                                    'bg-gradient-to-br from-sky-800 to-cyan-700 border-sky-200/70': index % 4 === 0,
                                    'bg-gradient-to-br from-indigo-800 to-violet-700 border-indigo-200/70': index % 4 === 1,
                                    'bg-gradient-to-br from-emerald-800 to-teal-700 border-emerald-200/70': index % 4 === 2,
                                    'bg-gradient-to-br from-cyan-800 to-blue-700 border-cyan-200/70': index % 4 === 3,
                                    'ring-2 ring-[#4ed4d4]': String(form.job_id) === String(job.id),
                                }"
                            >
                                <div class="bg-black/20 border border-white/60 p-2 h-full flex flex-col">
                                    <div class="text-[7px] uppercase tracking-wide text-white/85 mb-1">
                                        Class Card
                                    </div>
                                    <div class="h-[105px] border border-white/60 bg-white/10 overflow-hidden flex items-center justify-center">
                                        <img
                                            v-if="job.emblem_path"
                                            :src="`/storage/${job.emblem_path}`"
                                            :alt="`${job.name} emblem`"
                                            class="w-full h-full object-cover"
                                        />
                                        <img
                                            v-else
                                            src="/images/logo.png"
                                            :alt="`${job.name} default`"
                                            class="w-10 h-10 object-contain opacity-90"
                                        />
                                    </div>
                                    <div class="mt-2 border border-white/60 bg-black/20 px-2 py-2 flex-1">
                                        <p class="text-[8px] uppercase text-white leading-snug line-clamp-2">
                                            {{ job.name }}
                                        </p>
                                        <p class="text-[7px] font-sans text-white/80 mt-1">
                                            Path ID: #{{ job.id }}
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        @click="chooseJob(job.id)"
                                        class="mt-2 text-[7px] uppercase px-2 py-1.5 border font-bold transition-colors"
                                        :class="String(form.job_id) === String(job.id)
                                            ? 'bg-[#4ed4d4] text-black border-[#006666]'
                                            : 'bg-slate-800 text-white border-slate-500 hover:bg-slate-700'"
                                    >
                                        {{ String(form.job_id) === String(job.id) ? 'Selected' : 'Use This Job' }}
                                    </button>
                                </div>
                            </article>
                        </div>
                        <div class="mt-4 text-[8px] text-white/70 uppercase">
                            Pilih jobs path yang sesuai, lalu lanjutkan registrasi.
                        </div>
                    </div>
                </div>
                <div v-if="form.errors.job_id" class="mt-2 text-red-500 text-[8px] italic">{{ form.errors.job_id }}</div>
            </div>

            <div class="mt-3">
                <label class="block text-[#009999] text-[8px] uppercase mb-1">Password</label>
                <input type="password" v-model="form.password" :class="[
                    'w-full bg-black border-2 text-white p-2 focus:ring-0 text-[10px] font-pixel',
                    form.errors.password ? 'border-red-500 focus:border-red-500' : 'border-[#333333] focus:border-[#009999]'
                ]" required />
                <div v-if="form.errors.password" class="mt-2 text-red-500 text-[8px] italic">{{ form.errors.password }}</div>
            </div>

            <div class="mt-3">
                <label class="block text-[#009999] text-[8px] uppercase mb-1">Confirm_Password</label>
                <input type="password" v-model="form.password_confirmation" :class="[
                    'w-full bg-black border-2 text-white p-2 focus:ring-0 text-[10px] font-pixel',
                    form.errors.password_confirmation ? 'border-red-500 focus:border-red-500' : 'border-[#333333] focus:border-[#009999]'
                ]" required />
                <div v-if="form.errors.password_confirmation" class="mt-2 text-red-500 text-[8px] italic">{{ form.errors.password_confirmation }}</div>
            </div>

            <div v-if="isTurnstileEnabled" class="mt-3">
                <label class="block text-[#009999] text-[8px] uppercase mb-2">Anti_Bot_Verification</label>
                <div class="border-[#333333] p-2">
                    <div class="w-full flex justify-center">
                        <div ref="turnstileContainer"></div>
                    </div>
                </div>
                <div v-if="turnstileError" class="mt-2 text-red-500 text-[8px] italic">
                    {{ turnstileError }}
                </div>
                <div v-if="form.errors.captcha || form.errors['cf-turnstile-response']" class="mt-2 text-red-500 text-[8px] italic">
                    {{ form.errors.captcha || form.errors['cf-turnstile-response'] }}
                </div>
            </div>

            <div class="pt-6">
                <button type="submit" :disabled="form.processing" class="w-full bg-[#facc15] text-black py-4 btn-pixel border-[#854d0e] font-bold uppercase text-[10px] hover:bg-yellow-400 transition-all">
                    Register_Now
                </button>
            </div>

            <Link :href="route('login')" class="block text-center text-[8px] text-slate-500 uppercase mt-4 hover:text-[#009999]">
                Already Registered? Login_Here
            </Link>
        </form>
    </GuestLayout>
</template>

<style scoped>
.jobs-carousel {
    scrollbar-width: thin;
    scrollbar-color: #475569 #1f2937;
}

.jobs-carousel::-webkit-scrollbar {
    height: 8px;
}

.jobs-carousel::-webkit-scrollbar-track {
    background: #1f2937;
}

.jobs-carousel::-webkit-scrollbar-thumb {
    background: #475569;
}

.job-card {
    transition: transform 180ms ease, box-shadow 180ms ease;
}

.job-card:hover {
    transform: translateY(-4px) rotate(-0.6deg);
    box-shadow: 0 14px 24px rgba(15, 23, 42, 0.32);
}
</style>
