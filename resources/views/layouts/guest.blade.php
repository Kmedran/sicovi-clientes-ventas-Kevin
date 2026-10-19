<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'SICOVI') · Gestión comercial</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="login-page" style="background-image: url('{{ asset('images/sicovi-login-background.png') }}')">
    <a class="skip-link" href="#main">Ir al contenido</a>
    <main id="main" class="login-main" tabindex="-1">
        @yield('content')
    </main>
</body>
</html>
