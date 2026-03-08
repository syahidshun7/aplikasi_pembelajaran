<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>
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
        </script>
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead

    <link rel="preload" as="image" href="/images/bg-loby.png">
    <link rel="preload" as="image" href="/images/logo.png">
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
