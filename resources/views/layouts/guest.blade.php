<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <x-google-analytics />
    </head>
    <body class="font-sans antialiased text-gray-100">
        <div class="flex flex-col items-center min-h-screen px-4 py-6 guest-page sm:justify-center">
            <div>
                <a href="/">
                    <x-application-logo style="height: 100px;" />
                </a>
            </div>

            <div class="w-full px-6 py-6 mt-6 overflow-hidden bg-gray-900 border border-gray-700 shadow-xl guest-panel sm:max-w-md sm:rounded-lg">
                {{ $slot }}
            </div>
        </div>

        <style>
            html,
            body {
                min-height: 100%;
                margin: 0;
                background-color: #111315 !important;
            }

            .guest-page {
                width: 100%;
                min-height: 100vh;
                background-color: #111315 !important;
            }

            .guest-panel {
                color: #f3f4f6;
                background-color: #1b1e21 !important;
                border-color: #3b4146 !important;
            }

            .guest-panel label,
            .guest-panel p,
            .guest-panel .text-gray-600,
            .guest-panel .text-gray-700 {
                color: #d1d5db !important;
            }

            .guest-panel a {
                color: #f3f4f6;
            }

            .guest-panel a:hover {
                color: #fff;
            }

            .guest-panel input:not([type="checkbox"]),
            .guest-panel select,
            .guest-panel textarea {
                color: #111827;
                background-color: #fff;
            }
        </style>
    </body>
</html>
