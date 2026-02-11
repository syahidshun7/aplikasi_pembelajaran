<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Log In | P-QUEST" />

        <form @submit.prevent="submit" class="space-y-6">
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
                <input 
                    type="password" 
                    v-model="form.password"
                    class="w-full bg-black border-2 border-[#333333] text-[#F7F7F7] p-3 focus:border-[#009999] focus:ring-0 text-[10px] font-pixel"
                    placeholder="********"
                    required
                />
                <div v-if="form.errors.password" class="mt-2 text-red-500 text-[8px] italic">{{ form.errors.password }}</div>
            </div>

            <div class="flex items-center justify-between mt-4">
                <label class="flex items-center cursor-pointer">
                    <input type="checkbox" v-model="form.remember" class="bg-black border-[#333333] text-[#009999] focus:ring-0 rounded-none">
                    <span class="ms-2 text-[8px] text-slate-400 uppercase">Keep_Session</span>
                </label>
                
                <Link v-if="route().has('password.request')" :href="route('password.request')" class="text-[8px] text-slate-500 hover:text-[#009999] uppercase">
                    Forgot?
                </Link>
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