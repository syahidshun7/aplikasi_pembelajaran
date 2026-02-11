<x-guest-layout>
    <div class="font-pixel">
        <div class="mb-8 text-center">
            <h2 class="text-[#009999] text-xl animate-pulse shadow-neon inline-block p-2 border-2 border-[#009999]">
                REGISTER_HERO
            </h2>
        </div>

        <form method="POST" action="{{ route('register') }}" class="space-y-4">
            @csrf

            <div>
                <label for="name" class="block text-[#009999] text-[10px] uppercase mb-2">Nama Karakter</label>
                <input id="name" type="text" name="name" :value="old('name')" required autofocus autocomplete="name"
                    class="w-full bg-black border-2 border-[#333333] text-[#F7F7F7] p-3 focus:border-[#009999] focus:ring-0 text-[10px]" 
                    placeholder="NAMA ANDA..." />
                <x-input-error :messages="$errors->get('name')" class="mt-2 text-red-500 text-[8px]" />
            </div>

            <div>
                <label for="email" class="block text-[#009999] text-[10px] uppercase mb-2">Akses ID (Email)</label>
                <input id="email" type="email" name="email" :value="old('email')" required autocomplete="username"
                    class="w-full bg-black border-2 border-[#333333] text-[#F7F7F7] p-3 focus:border-[#009999] focus:ring-0 text-[10px]" 
                    placeholder="EMAIL@QUEST.COM..." />
                <x-input-error :messages="$errors->get('email')" class="mt-2 text-red-500 text-[8px]" />
            </div>

            <div>
                <label for="password" class="block text-[#009999] text-[10px] uppercase mb-2">Kode Sandi</label>
                <input id="password" type="password" name="password" required autocomplete="new-password"
                    class="w-full bg-black border-2 border-[#333333] text-[#F7F7F7] p-3 focus:border-[#009999] focus:ring-0 text-[10px]" 
                    placeholder="********" />
                <x-input-error :messages="$errors->get('password')" class="mt-2 text-red-500 text-[8px]" />
            </div>

            <div>
                <label for="password_confirmation" class="block text-[#009999] text-[10px] uppercase mb-2">Konfirmasi Sandi</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"
                    class="w-full bg-black border-2 border-[#333333] text-[#F7F7F7] p-3 focus:border-[#009999] focus:ring-0 text-[10px]" 
                    placeholder="********" />
                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2 text-red-500 text-[8px]" />
            </div>

            <div class="flex flex-col gap-4 mt-8">
                <button type="submit" class="w-full bg-[#009999] text-[#F7F7F7] py-4 border-b-4 border-r-4 border-[#006666] active:border-0 active:translate-y-1 font-bold uppercase text-[10px] hover:bg-[#00b3b3] transition-all">
                    Buat Akun Baru
                </button>

                <a class="text-[8px] text-center text-[#F7F7F7] opacity-50 hover:text-[#009999] uppercase" href="{{ route('login') }}">
                    Sudah Terdaftar? Masuk Sini
                </a>
            </div>
        </form>
    </div>
</x-guest-layout>