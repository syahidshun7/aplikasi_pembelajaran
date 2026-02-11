<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>P-QUEST | RPG Task Management</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        body {
            background-color: #0d1117;
            background-image: radial-gradient(#161b22 1px, transparent 1px);
            background-size: 20px 20px;
            font-family: 'Press Start 2P', cursive;
        }

        .shadow-neon { box-shadow: 0 0 15px rgba(0, 153, 153, 0.4); }
        
        .nav-border {
            border-bottom: 4px solid #333333;
            box-shadow: 0 4px 0 0 #1a1c2c;
        }

        .btn-pixel {
            @apply border-b-4 border-r-4 transition-all active:translate-y-1 active:border-0;
        }
    </style>
</head>
<body class="antialiased text-[#F7F7F7]">

    <nav class="nav-border bg-black/90 w-full sticky top-0 z-50 px-6 py-4">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div class="flex items-center gap-3">
                <img src="{{ asset('images/logo.png') }}" alt="Logo" class="w-8 h-8 drop-shadow-[0_0_5px_#009999]">
                <span class="text-[#009999] text-[10px] hidden md:block uppercase tracking-tighter">P-Quest_OS</span>
            </div>

            <div class="flex gap-4 md:gap-8">
                @if (Route::has('login'))
                    @auth
                        <a href="{{ url('/dashboard') }}" class="text-[#009999] text-[8px] uppercase hover:text-white transition-colors">
                            [ Ke_Dashboard ]
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="text-[#F7F7F7] text-[8px] uppercase hover:text-[#009999] transition-colors">
                            _Masuk
                        </a>

                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="text-[#009999] text-[8px] border border-[#009999] px-3 py-1 hover:bg-[#009999] hover:text-black transition-all uppercase">
                                Daftar_Hero
                            </a>
                        @endif
                    @endauth
                @endif
            </div>
        </div>
    </nav>

    <div class="min-h-[calc(100vh-80px)] flex flex-col items-center justify-center p-6 relative">
        
        <div class="mb-10 relative">
            <div class="absolute inset-0 bg-[#009999] opacity-20 blur-3xl rounded-full scale-150"></div>
            <img src="{{ asset('images/logo.png') }}" alt="Logo" class="relative w-32 h-32 drop-shadow-[0_0_20px_rgba(0,153,153,0.8)] animate-pulse">
        </div>

        <div class="text-center mb-12">
            <h1 class="text-[#009999] text-2xl md:text-4xl mb-4 tracking-tighter">
                P-QUEST_PROJECT
            </h1>
            <p class="text-[10px] md:text-xs text-slate-500 uppercase tracking-widest">
                Ubah Tugas Harian Menjadi <span class="text-[#009999]">Epic Quests</span>
            </p>
        </div>

        <div class="w-full max-w-2xl border-2 border-[#333333] bg-black/80 p-6 mb-12 shadow-neon relative">
            <div class="absolute -top-3 left-6 bg-black px-2 text-[#009999] text-[8px]">SYSTEM_STATUS</div>
            <div class="space-y-4 text-[10px] md:text-xs leading-relaxed">
                <p class="text-[#009999]"> [>] NAV_MODULE... <span class="text-white">ONLINE</span></p>
                <p class="text-[#009999]"> [>] PERMISION... <span class="text-white">REQUIRED</span></p>
                <p class="text-slate-400 italic">"Pilih jalurmu melalui navigasi di atas atau tombol di bawah untuk memulai petualangan."</p>
            </div>
        </div>

        <div class="w-full max-w-xs">
            <a href="{{ route('login') }}" class="block w-full text-center bg-[#009999] text-white py-4 btn-pixel border-[#006666] text-[10px] uppercase shadow-neon">
                Mulai Akses
            </a>
        </div>

        <div class="mt-12 text-[8px] text-slate-600 uppercase tracking-widest">
            &copy; 2026 P-Quest Engine // Build v1.0.4-Alpha
        </div>
    </div>
</body>
</html>