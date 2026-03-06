<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref } from 'vue';

const props = defineProps({
    canResetPassword: {
        type: Boolean,
        default: false,
    },
    status: {
        type: String,
        default: null,
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
    email: '',
    password: '',
    remember: false,
    'cf-turnstile-response': '',
});

const turnstileContainer = ref(null);
const turnstileWidgetId = ref(null);
const turnstileError = ref('');
const showPassword = ref(false);
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

const submit = () => {
    form.post(route('login'), {
        onFinish: () => {
            form.reset('password');
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
        <Head title="Log In | P-QUEST" />
 
        <form @submit.prevent="submit" class="space-y-6">
            <h2 class="text-yellow-500 text-[10px] text-center mb-6 border-b border-slate-800 pb-4 tracking-widest uppercase">
                -- LOGIN_ACCESS --
            </h2>

            <div>
                <label class="block text-[#009999] text-[10px] uppercase mb-2">> USER_ID (EMAIL)</label>
                <input 
                    type="email" 
                    v-model="form.email"
                    class="w-full bg-black border-2 border-[#333333] text-[#F7F7F7] p-3 focus:border-[#009999] focus:ring-0 text-[10px] font-pixel"
                    placeholder="ENTER_EMAIL..."
                    required
                />
                <div v-if="form.errors.email" class="mt-2 text-red-500 text-[8px] italic">{{ form.errors.email }}</div>
            </div>

            <div class="mt-4">
                <label class="block text-[#009999] text-[10px] uppercase mb-2">> ACCESS_CODE</label>
                <div class="relative">
                    <input 
                        :type="showPassword ? 'text' : 'password'" 
                        v-model="form.password"
                        class="w-full bg-black border-2 border-[#333333] text-[#F7F7F7] p-3 pr-12 focus:border-[#009999] focus:ring-0 text-[10px] font-pixel"
                        placeholder="********"
                        required
                    />
                    <button
                        type="button"
                        @click="showPassword = !showPassword"
                        :aria-label="showPassword ? 'Hide password' : 'Show password'"
                        :title="showPassword ? 'Hide password' : 'Show password'"
                        class="absolute right-2 top-1/2 -translate-y-1/2 w-7 h-7 inline-flex items-center justify-center border border-slate-500/70 bg-black/35 text-slate-200 hover:text-cyan-300 hover:border-cyan-400 focus:outline-none"
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

            <div class="flex items-center justify-between mt-4">
                <label class="flex items-center cursor-pointer">
                    <input type="checkbox" v-model="form.remember" class="bg-black border-[#333333] text-[#009999] focus:ring-0 rounded-none">
                    <span class="ms-2 text-[8px] text-slate-400 uppercase">Keep_Session</span>
                </label>
                
                <Link v-if="canResetPassword" :href="route('password.request')" class="text-[8px] text-slate-500 hover:text-[#009999] uppercase">
                    Forgot?
                </Link>
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

            <div class="pt-4">
                <button 
                    type="submit" 
                    :class="{ 'opacity-25': form.processing }" 
                    :disabled="form.processing"
                    class="w-full bg-[#009999] text-black py-4 btn-pixel border-[#006666] font-bold uppercase text-[10px] hover:bg-[#4ed4d4] transition-all"
                >
                    Confirm_Access [ENTER]
                </button>
            </div>

            <p class="text-center text-[8px] text-slate-600 mt-6 uppercase tracking-widest">
                No Account? <Link :href="route('register')" class="text-[#009999] hover:underline">Create_New_Hero</Link>
            </p>
        </form>
    </GuestLayout>
</template>
