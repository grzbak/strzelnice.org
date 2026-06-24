<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Travel Agency</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans text-gray-800">
    {{ Auth::user()?->email }}

    @include('components.navbar')

    <main class="mt-16">
        @yield('content')
    </main>

    @include('components.footer')

</body>
</html>
