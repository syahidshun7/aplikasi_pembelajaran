<nav class="bg-[#1a1c2c]/90 backdrop-blur-sm border-b-4 border-[#3d415f] p-4 md:px-8 flex justify-between items-center shadow-2xl sticky top-0 z-50">
    <div class="flex items-center gap-4">
        <Link :href="route('lobby')" class="flex items-center gap-4 group">
            <div class="w-10 h-10 bg-[#0a0c10] flex items-center justify-center border-b-4 border-r-4 border-[#4ed4d4] overflow-hidden group-hover:scale-110 transition-transform">
                <img src="/images/logo.png" alt="Logo" class="w-7 h-7 object-contain pixelated">
            </div>
            <h1 class="text-[#009999] text-[8px] md:text-sm tracking-tighter uppercase group-hover:text-[#4ed4d4]">
                Lobby_Room_01
            </h1>
        </Link>
    </div>

    <div class="flex gap-2 md:gap-4 items-center">
        <template v-if="auth.user">
            <Link v-if="auth.user.role === 'admin'" :href="route('admin.dashboard')"
                class="text-[8px] bg-purple-600/80 text-white px-3 py-2 btn-pixel border-purple-900 uppercase font-bold hover:bg-purple-500 transition-colors">
                Admin
            </Link>

            <Link :href="route('profile.edit')"
                class="text-[8px] bg-[#3d415f]/80 text-white px-3 py-2 btn-pixel border-[#1a1c2c] uppercase font-bold hover:bg-slate-600 transition-colors">
                Profile
            </Link>

            <button @click="handleLogout"
                class="text-[8px] bg-red-900/80 text-white px-3 py-2 btn-pixel border-red-950 uppercase font-bold hover:bg-red-700 transition-colors">
                [X]
            </button>
        </template>

        <template v-else>
            <Link :href="route('login')"
                class="text-[8px] bg-[#009999] text-black px-4 py-2 btn-pixel border-[#006666] uppercase font-bold hover:bg-[#4ed4d4] transition-all">
                Login
            </Link>
            <Link :href="route('register')"
                class="text-[8px] bg-[#facc15] text-black px-4 py-2 btn-pixel border-[#854d0e] uppercase font-bold hover:bg-yellow-400 transition-all">
                Register
            </Link>
        </template>
    </div>
</nav>