<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
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
