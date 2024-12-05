<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        <link rel="icon" type="image/png" href="{{ Vite::asset('resources/images/logo.png') }}">

        <meta property="og:title" content="{{ config('app.name') }}" />
        <meta property="og:type" content="website" />
        <meta property="og:url" content="{{ config('app.url') }}" />
        <meta property="og:logo" content="{{ Vite::asset('resources/images/logo.png') }}" />
        <meta property="og:description" content="Keep sensitive information out of your chat or email logs and send via time-sensitive, one-time-use links." />

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
