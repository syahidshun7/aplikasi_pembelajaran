<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Hero Entrance - Authentication</title>
    <link href="https://fonts.googleapis.com/css2?family=VT323&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body {
            background-color: #0d1117;
            background-image: radial-gradient(#161b22 1px, transparent 1px);
            background-size: 20px 20px;
            font-family: 'VT323', monospace;
        }

        .rpg-border {
            border: 2px solid #06b6d4;
            box-shadow: 0 0 15px rgba(6, 182, 212, 0.4), inset 0 0 10px rgba(6, 182, 212, 0.2);
            background: rgba(22, 27, 34, 0.9);
        }
    </style>
</head>

<body class="antialiased text-gray-200">
    <div class="min-h-screen flex flex-col sm:justify-center items-center pt-12 sm:pt-0">
        <div class="mb-8 flex flex-col items-center">
            <a href="/" class="relative group">
                <div class="absolute inset-0 bg-[#00a19a] opacity-20 blur-xl group-hover:opacity-40 transition-opacity rounded-full"></div>

                <img src="{{ asset('images/logo.png') }}" class="relative w-24 h-24 object-contain drop-shadow-[0_0_10px_rgba(0,161,154,0.8)] transition-transform duration-500 hover:scale-110" alt="P Quest Logo">

                <div class="absolute -inset-2 border border-[#00a19a]/30 rounded-full border-dashed animate-[spin_10s_linear_infinite]"></div>
            </a>
        </div>

        <div class="w-full sm:max-w-md mt-6 px-8 py-10 rpg-border overflow-hidden">
            {{ $slot }}
        </div>
    </div>
</body>

</html>