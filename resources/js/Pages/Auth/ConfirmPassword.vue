<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const form = useForm({
    password: '',
});
const showPassword = ref(false);

const submit = () => {
    form.post(route('password.confirm'), {
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Confirm Password" />

        <div class="mb-4 text-sm text-gray-600">
            This is a secure area of the application. Please confirm your
            password before continuing.
        </div>

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="password" value="Password" />
                <div class="relative mt-1">
                    <TextInput
                        id="password"
                        :type="showPassword ? 'text' : 'password'"
                        class="block w-full pr-12"
                        v-model="form.password"
                        required
                        autocomplete="current-password"
                        autofocus
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
                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="mt-4 flex justify-end">
                <PrimaryButton
                    class="ms-4"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    Confirm
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>

