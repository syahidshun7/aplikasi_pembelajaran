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
    username: '',
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
const showPassword = ref(false);
const showPasswordConfirmation = ref(false);
const selectedJob = computed(() => props.jobs.find((job) => String(job.id) === String(form.job_id)) ?? null);
const isTurnstileEnabled = computed(() => !!(props.turnstile?.enabled && props.turnstile?.site_key));
const isComingSoonJob = (job) => String(job?.status || 'active') === 'coming_soon';
const isSelectedJob = (job) => String(form.job_id) === String(job?.id);

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

const chooseJob = (job) => {
    if (isComingSoonJob(job)) return;

    form.job_id = String(job.id);
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

        <form @submit.prevent="submit" class="auth-register-form space-y-4">
            <h2 class="text-yellow-500 text-[10px] text-center mb-6 border-b border-[var(--panel-border)] pb-4 tracking-widest uppercase">
                -- NEW_HERO_REGISTRATION --
            </h2>

            <div>
                <label class="block text-[var(--accent)] text-[8px] uppercase mb-1">Hero_Name</label>
                <input type="text" v-model="form.name" :class="[
                    'w-full bg-[var(--bg)] border-2 text-[var(--text)] p-2 focus:ring-0 text-[10px] font-pixel placeholder:text-[var(--text-muted)]',
                    form.errors.name ? 'border-red-500 focus:border-red-500' : 'border-[var(--panel-border)] focus:border-[var(--accent)]'
                ]" required />
                <div v-if="form.errors.name" class="mt-2 text-red-500 text-[8px] italic">{{ form.errors.name }}</div>
            </div>

            <div class="mt-3">
                <label class="block text-[var(--accent)] text-[8px] uppercase mb-1">Username</label>
                <input
                    type="text"
                    v-model="form.username"
                    minlength="3"
                    maxlength="32"
                    pattern="[a-z0-9._-]{3,32}"
                    autocomplete="username"
                    :class="[
                        'w-full bg-[var(--bg)] border-2 text-[var(--text)] p-2 focus:ring-0 text-[10px] font-pixel placeholder:text-[var(--text-muted)]',
                        form.errors.username ? 'border-red-500 focus:border-red-500' : 'border-[var(--panel-border)] focus:border-[var(--accent)]'
                    ]"
                    required
                />
                <div v-if="form.errors.username" class="mt-2 text-red-500 text-[8px] italic">{{ form.errors.username }}</div>
            </div>

            <div class="mt-3">
                <label class="block text-[var(--accent)] text-[8px] uppercase mb-1">Email_Address</label>
                <input type="email" v-model="form.email" :class="[
                    'w-full bg-[var(--bg)] border-2 text-[var(--text)] p-2 focus:ring-0 text-[10px] font-pixel placeholder:text-[var(--text-muted)]',
                    form.errors.email ? 'border-red-500 focus:border-red-500' : 'border-[var(--panel-border)] focus:border-[var(--accent)]'
                ]" required />
                <div v-if="form.errors.email" class="mt-2 text-red-500 text-[8px] italic">{{ form.errors.email }}</div>
            </div>

            <div class="mt-3">
                <label class="block text-[var(--accent)] text-[8px] uppercase mb-1">Jobs_Path</label>
                <div :class="[
                    'auth-job-selector border-2 p-3 bg-[var(--bg)]',
                    form.errors.job_id ? 'border-red-500' : 'border-[var(--panel-border)]'
                ]">
                    <button
                        type="button"
                        @click="openJobModal"
                        class="auth-job-selector__button w-full text-left border-2 border-[var(--panel-border)] hover:border-[var(--accent)] transition-all p-3 bg-[var(--bg)]"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-[8px] uppercase text-[var(--accent)] mb-1">Selected_Job</p>
                                <p v-if="selectedJob" class="text-[9px] uppercase text-[var(--text)]">{{ selectedJob.name }}</p>
                                <p v-else class="text-[9px] uppercase text-[var(--text-muted)]">-- Choose Job Path --</p>
                            </div>
                            <span class="text-[8px] uppercase px-3 py-1 bg-[#009999] text-black border border-[#006666]">Choose</span>
                        </div>
                    </button>
                    <input type="hidden" v-model="form.job_id" />
                </div>
                <div v-if="isJobModalOpen" class="auth-job-modal fixed inset-0 z-[70] flex items-center justify-center p-4">
                    <div class="auth-job-modal__backdrop absolute inset-0 bg-black/80" @click="closeJobModal"></div>
                    <div class="auth-job-modal__surface relative z-10 w-full max-w-6xl border-2 border-[var(--text)]/10 bg-[var(--panel)]/95 backdrop-blur-md p-4 md:p-6 shadow-2xl max-h-[92vh] overflow-hidden">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-[10px] md:text-xs uppercase text-[var(--text)]">Select Job Path</h3>
                            <button type="button" @click="closeJobModal" class="text-[8px] uppercase px-3 py-1 bg-[var(--panel-border)] text-[var(--text)] border border-[var(--panel-border)] hover:brightness-125">
                                Close
                            </button>
                        </div>

                        <div class="jobs-carousel flex gap-3 overflow-x-auto pb-2 snap-x snap-mandatory">
                            <article
                                v-for="(job, index) in jobs"
                                :key="job.id"
                                class="job-card relative snap-start shrink-0 w-[190px] h-[230px] p-[4px] border shadow-[0_6px_12px_rgba(15,23,42,0.25)] overflow-hidden"
                                :aria-disabled="isComingSoonJob(job)"
                                :class="[
                                    isComingSoonJob(job)
                                        ? 'job-card--locked cursor-not-allowed bg-gradient-to-br from-slate-800 to-zinc-700 border-slate-300/60'
                                        : {
                                            'bg-gradient-to-br from-sky-800 to-cyan-700 border-sky-200/70': index % 4 === 0,
                                            'bg-gradient-to-br from-indigo-800 to-violet-700 border-indigo-200/70': index % 4 === 1,
                                            'bg-gradient-to-br from-emerald-800 to-teal-700 border-emerald-200/70': index % 4 === 2,
                                            'bg-gradient-to-br from-cyan-800 to-blue-700 border-cyan-200/70': index % 4 === 3,
                                            'ring-2 ring-[var(--accent)]': isSelectedJob(job),
                                        },
                                ]"
                            >
                                <div v-if="isComingSoonJob(job)" class="pointer-events-none absolute inset-0 z-20">
                                    <div class="register-coming-soon-chain register-coming-soon-chain--left"></div>
                                    <div class="register-coming-soon-chain register-coming-soon-chain--right"></div>
                                    <div class="absolute left-1/2 top-1/2 flex h-14 w-14 -translate-x-1/2 -translate-y-1/2 items-center justify-center border-4 border-slate-300 bg-black/75 text-slate-100 shadow-[0_0_18px_rgba(148,163,184,0.65)]">
                                        <i class="fi fi-rr-lock text-2xl leading-none"></i>
                                    </div>
                                    <div class="absolute left-1/2 top-3 -translate-x-1/2 border-2 border-slate-300 bg-black/85 px-2 py-1 text-[6px] uppercase tracking-[0.18em] text-slate-100 shadow-[3px_3px_0_rgba(0,0,0,0.45)]">
                                        Coming Soon
                                    </div>
                                </div>
                                <div class="bg-black/20 border border-white/60 p-2 h-full flex flex-col">
                                    <div class="flex items-center justify-between gap-2 text-[7px] uppercase tracking-wide text-white/85 mb-1">
                                        <span>Class Card</span>
                                        <span
                                            class="border px-1.5 py-0.5 text-[5px]"
                                            :class="isComingSoonJob(job) ? 'border-slate-200 bg-slate-300/15 text-slate-100' : 'border-white/40 bg-white/10 text-white/85'"
                                        >
                                            {{ isComingSoonJob(job) ? 'Soon' : 'Active' }}
                                        </span>
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
                                        :disabled="isComingSoonJob(job)"
                                        @click="chooseJob(job)"
                                        class="mt-2 text-[7px] uppercase px-2 py-1.5 border font-bold transition-colors"
                                        :class="isComingSoonJob(job)
                                            ? 'bg-slate-700/80 text-slate-300 border-slate-400 cursor-not-allowed'
                                            : isSelectedJob(job)
                                                ? 'bg-[#4ed4d4] text-black border-[#006666]'
                                                : 'bg-slate-800 text-white border-slate-500 hover:bg-slate-700'"
                                    >
                                        {{ isComingSoonJob(job) ? 'Locked' : isSelectedJob(job) ? 'Selected' : 'Use This Job' }}
                                    </button>
                                </div>
                            </article>
                        </div>
                        <div class="mt-4 text-[8px] text-[var(--text-muted)] uppercase">
                            Pilih jobs path yang sesuai, lalu lanjutkan registrasi.
                        </div>
                    </div>
                </div>
                <div v-if="form.errors.job_id" class="mt-2 text-red-500 text-[8px] italic">{{ form.errors.job_id }}</div>
            </div>

            <div class="mt-3">
                <label class="block text-[var(--accent)] text-[8px] uppercase mb-1">Password</label>
                <div class="relative">
                    <input :type="showPassword ? 'text' : 'password'" v-model="form.password" :class="[
                        'w-full bg-[var(--bg)] border-2 text-[var(--text)] p-2 pr-12 focus:ring-0 text-[10px] font-pixel placeholder:text-[var(--text-muted)]',
                        form.errors.password ? 'border-red-500 focus:border-red-500' : 'border-[var(--panel-border)] focus:border-[var(--accent)]'
                    ]" required />
                    <button
                        type="button"
                        @click="showPassword = !showPassword"
                        :aria-label="showPassword ? 'Hide password' : 'Show password'"
                        :title="showPassword ? 'Hide password' : 'Show password'"
                        class="absolute right-2 top-1/2 -translate-y-1/2 w-7 h-7 inline-flex items-center justify-center border border-[var(--text-muted)]/70 bg-[var(--bg)]/35 text-[var(--text-muted)] hover:text-[var(--accent)] hover:border-[var(--accent)] focus:outline-none"
                    >
                        <svg v-if="!showPassword" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z" />
                            <circle cx="12" cy="12" r="3" />
                        </svg>
                        <svg v-else xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17.94 17.94A10.93 10.93 0 0 1 12 19c-7 0-11-7-11-7a21.77 21.77 0 0 1 5.06-5.94" />
                            <path d="M1 1l22 22" />
                            <path d="M9.53 9.53A3 3 0 0 0 12 15a3 3 0 0 0 2.47-5.47" />
                            <path d="M14.47 14.47 9.53 9.53" />
                            <path d="M21 12s-1.37-2.4-3.62-4.36" />
                        </svg>
                    </button>
                </div>
                <div v-if="form.errors.password" class="mt-2 text-red-500 text-[8px] italic">{{ form.errors.password }}</div>
            </div>

            <div class="mt-3">
                <label class="block text-[var(--accent)] text-[8px] uppercase mb-1">Confirm_Password</label>
                <div class="relative">
                    <input :type="showPasswordConfirmation ? 'text' : 'password'" v-model="form.password_confirmation" :class="[
                        'w-full bg-[var(--bg)] border-2 text-[var(--text)] p-2 pr-12 focus:ring-0 text-[10px] font-pixel placeholder:text-[var(--text-muted)]',
                        form.errors.password_confirmation ? 'border-red-500 focus:border-red-500' : 'border-[var(--panel-border)] focus:border-[var(--accent)]'
                    ]" required />
                    <button
                        type="button"
                        @click="showPasswordConfirmation = !showPasswordConfirmation"
                        :aria-label="showPasswordConfirmation ? 'Hide password confirmation' : 'Show password confirmation'"
                        :title="showPasswordConfirmation ? 'Hide password confirmation' : 'Show password confirmation'"
                        class="absolute right-2 top-1/2 -translate-y-1/2 w-7 h-7 inline-flex items-center justify-center border border-[var(--text-muted)]/70 bg-[var(--bg)]/35 text-[var(--text-muted)] hover:text-[var(--accent)] hover:border-[var(--accent)] focus:outline-none"
                    >
                        <svg v-if="!showPasswordConfirmation" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z" />
                            <circle cx="12" cy="12" r="3" />
                        </svg>
                        <svg v-else xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17.94 17.94A10.93 10.93 0 0 1 12 19c-7 0-11-7-11-7a21.77 21.77 0 0 1 5.06-5.94" />
                            <path d="M1 1l22 22" />
                            <path d="M9.53 9.53A3 3 0 0 0 12 15a3 3 0 0 0 2.47-5.47" />
                            <path d="M14.47 14.47 9.53 9.53" />
                            <path d="M21 12s-1.37-2.4-3.62-4.36" />
                        </svg>
                    </button>
                </div>
                <div v-if="form.errors.password_confirmation" class="mt-2 text-red-500 text-[8px] italic">{{ form.errors.password_confirmation }}</div>
            </div>

            <div v-if="isTurnstileEnabled" class="mt-3">
                <label class="block text-[var(--accent)] text-[8px] uppercase mb-2">Anti_Bot_Verification</label>
                <div class="border-[var(--panel-border)] p-2">
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
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full bg-[#facc15] text-black py-4 btn-pixel border-[#854d0e] font-bold uppercase text-[10px] hover:bg-yellow-400 transition-all flex items-center justify-center gap-2"
                    :class="{ 'opacity-60': form.processing }"
                >
                    <span v-if="form.processing" class="inline-block h-4 w-4 border-2 border-black border-t-transparent rounded-full animate-spin"></span>
                    <span>{{ form.processing ? 'Registering...' : 'Register_Now' }}</span>
                </button>
            </div>

            <Link :href="route('login')" class="block text-center text-[8px] text-[var(--text-muted)] uppercase mt-4 hover:text-[var(--accent)]">
                Already Registered? Login_Here
            </Link>
        </form>
    </GuestLayout>
</template>

<style scoped>
.jobs-carousel {
    scrollbar-width: thin;
    scrollbar-color: var(--panel-border) var(--bg);
}

.jobs-carousel::-webkit-scrollbar {
    height: 8px;
}

.jobs-carousel::-webkit-scrollbar-track {
    background: var(--bg);
}

.jobs-carousel::-webkit-scrollbar-thumb {
    background: var(--panel-border);
}

.job-card {
    transition: transform 180ms ease, box-shadow 180ms ease;
}

.job-card:hover {
    transform: translateY(-4px) rotate(-0.6deg);
    box-shadow: 0 14px 24px rgba(15, 23, 42, 0.32);
}

.job-card--locked {
    filter: grayscale(0.92) saturate(0.42) brightness(0.86);
}

.job-card--locked:hover {
    transform: none;
    box-shadow: 0 6px 12px rgba(15, 23, 42, 0.25);
}

.register-coming-soon-chain {
    position: absolute;
    left: -24%;
    right: -24%;
    top: 50%;
    height: 14px;
    transform: translateY(-50%) rotate(var(--chain-rotation));
    background-image:
        radial-gradient(ellipse at center, transparent 0 38%, rgba(226, 232, 240, 0.98) 39% 54%, transparent 55%),
        radial-gradient(ellipse at center, transparent 0 38%, rgba(100, 116, 139, 0.95) 39% 54%, transparent 55%);
    background-position: 0 0, 14px 0;
    background-size: 28px 14px;
    filter: drop-shadow(0 0 6px rgba(148, 163, 184, 0.85));
    opacity: 0.98;
}

.register-coming-soon-chain--left {
    --chain-rotation: -18deg;
}

.register-coming-soon-chain--right {
    --chain-rotation: 18deg;
}
</style>
