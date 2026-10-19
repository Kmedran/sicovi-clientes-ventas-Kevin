<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'SICOVI') · Gestión comercial</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<a class="skip-link" href="#main">Ir al contenido</a>
<header class="app-header">
    <nav class="navbar navbar-expand-lg navbar-dark container-fluid px-lg-4" aria-label="Navegación principal">
        <a class="navbar-brand brand" href="{{ auth()->check() ? route('dashboard') : route('login') }}">SICOVI<span>Compras · Ventas · Inventario</span></a>
        @auth
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navigation" aria-controls="navigation" aria-expanded="false" aria-label="Abrir menú"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="navigation">
            <ul class="navbar-nav ms-lg-4 me-auto">
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">Inicio</a></li>
                @can('products.view')<li class="nav-item"><a class="nav-link {{ request()->routeIs('products.*') ? 'active' : '' }}" href="{{ route('products.index') }}">Productos</a></li>@endcan
                @foreach(['categories'=>'Categorías','subcategories'=>'Subcategorías','brands'=>'Marcas','units'=>'Unidades'] as $key=>$label)
                @can($key.'.view')<li class="nav-item"><a class="nav-link {{ request()->route('catalog') === $key ? 'active' : '' }}" href="{{ route('catalogs.index',$key) }}">{{ $label }}</a></li>@endcan
                @endforeach
                @can('users.view')<li class="nav-item"><a class="nav-link" href="{{ route('users.index') }}">Usuarios</a></li>@endcan
                @can('roles.view')<li class="nav-item"><a class="nav-link" href="{{ route('roles.index') }}">Roles</a></li>@endcan
                @can('settings.view')<li class="nav-item"><a class="nav-link" href="{{ route('settings.edit') }}">Empresa</a></li>@endcan
            </ul>
            <div class="account-nav"><a href="{{ route('account.edit') }}">{{ auth()->user()->name }}</a><form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="btn btn-sm btn-outline-light">Salir</button></form></div>
        </div>
        @endauth
    </nav>
</header>
<main id="main" class="container-fluid main-content" tabindex="-1">
    @if(session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
    @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger" role="alert"><strong>Revise los datos para continuar.</strong><ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @yield('content')
</main>
<footer class="app-footer"><span>SICOVI</span> Sistema Integrado de Compras, Ventas e Inventario</footer>
</body>
</html>
