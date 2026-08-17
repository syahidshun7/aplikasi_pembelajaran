<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @php
            $defaultTitle = config('app.name', 'DOOPTECH');
            $defaultDescription = 'DOOPTECH adalah aplikasi pembelajaran berbasis game yang menghubungkan pemula dan profesional dalam satu ekosistem belajar.';
            $defaultImage = url('/images/bg-loby2.webp');
        @endphp
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">

        <title inertia>{{ $defaultTitle }}</title>
        <meta name="description" content="{{ $defaultDescription }}">
        <meta name="robots" content="index,follow">
        <meta property="og:site_name" content="{{ $defaultTitle }}">
        <meta property="og:type" content="website">
        <meta property="og:title" content="{{ $defaultTitle }}">
        <meta property="og:description" content="{{ $defaultDescription }}">
        <meta property="og:image" content="{{ $defaultImage }}">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $defaultTitle }}">
        <meta name="twitter:description" content="{{ $defaultDescription }}">
        <meta name="twitter:image" content="{{ $defaultImage }}">
        <link rel="icon" type="image/png" href="/images/logo.png">
        <link rel="apple-touch-icon" href="/images/logo.png">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @php
            $bladeUser = null;
            if (auth()->check()) {
                $bladeUser = [
                    'id' => auth()->user()->id,
                    'name' => auth()->user()->name,
                    'username' => auth()->user()->username,
                    'email' => auth()->user()->email,
                ];
            }
        @endphp
        <script>
            window.Laravel = Object.assign({}, window.Laravel || {}, {
                user: @json($bladeUser),
            });
            // (intentionally empty)
        </script>
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead

    <link rel="preload" as="image" href="/images/bg-loby.webp">
    <link rel="preload" as="image" href="/images/logo.png">
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
