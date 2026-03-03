<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>@yield('title', config('app.name', 'Laravel'))</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
        @stack('head')
    </head>
    <body class="min-h-screen bg-gray-100 text-gray-900 antialiased">
        <main class="mx-auto w-full max-w-4xl p-6">
            @yield('content')
        </main>

        @livewireScripts
        @stack('scripts')
    </body>
</html>
