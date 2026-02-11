<x-guest-layout>
    <div class="font-pixel">
        <div class="mb-8 text-center">
            <h2 class="text-[#009999] text-xl animate-pulse shadow-neon inline-block p-2 border-2 border-[#009999]">
                USER_LOGIN
            </h2>
        </div>

        <x-auth-session-status class="mb-4" :status="session('status')" />

        <form method="POST" action="{{ route('login') }}" class="space-y-6">
            @csrf

            <div>
                <label for="email" class="block text-[#009999] text-[10px] uppercase mb-2">Akses ID (Email)</label>
                <input id="email" type="email" name="email" :value="old('email')" required autofocus 
                    class="w-full bg-black border-2 border-[#333333] text-[#F7F7F7] p-3 focus:border-[#009999] focus:ring-0 text-[10px]" 
                    placeholder="MASUKKAN EMAIL..." />
                <x-input-error :messages="$errors->get('email')" class="mt-2 text-red-500 text-[8px]" />
            </div>

            <div class="mt-4">
                <label for="password" class="block text-[#009999] text-[10px] uppercase mb-2">Kode Sandi</label>
                <input id="password" type="password" name="password" required autocomplete="current-password"
                    class="w-full bg-black border-2 border-[#333333] text-[#F7F7F7] p-3 focus:border-[#009999] focus:ring-0 text-[10px]" 
                    placeholder="********" />
                <x-input-error :messages="$errors->get('password')" class="mt-2 text-red-500 text-[8px]" />
            </div>

            <div class="block mt-4">
                <label for="remember_me" class="inline-flex items-center">
                    <input id="remember_me" type="checkbox" name="remember" class="bg-black border-[#333333] text-[#009999] focus:ring-0 rounded-none">
                    <span class="ms-2 text-[8px] text-[#F7F7F7] uppercase opacity-70">Ingat Sesi Saya</span>
                </label>
            </div>

            <div class="flex flex-col gap-4 mt-6">
                <button type="submit" class="w-full bg-[#009999] text-[#F7F7F7] py-4 border-b-4 border-r-4 border-[#006666] active:border-0 active:translate-y-1 font-bold uppercase text-[10px] hover:bg-[#00b3b3] transition-all">
                    Konfirmasi Akses
                </button>

                @if (Route::has('password.request'))
                    <a class="text-[8px] text-center text-[#F7F7F7] opacity-50 hover:text-[#009999] uppercase" href="{{ route('password.request') }}">
                        Lupa Kode Sandi?
                    </a>
                @endif
            </div>
        </form>
    </div>
</x-guest-layout>