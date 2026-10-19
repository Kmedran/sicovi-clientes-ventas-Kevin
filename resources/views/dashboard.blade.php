@extends('layouts.app')
@section('title', 'Inicio')
@section('content')
<div class="page-heading"><div><p class="eyebrow">ESPACIO DE TRABAJO</p><h1>Bienvenido, {{ auth()->user()->name }}</h1><p class="text-secondary">Organice la información de su empresa y mantenga sus catálogos al día.</p></div><span class="date-label">{{ now()->timezone(config('sicovi.display_timezone'))->format('d/m/Y') }}</span></div>
<div class="row g-3 mb-4">@foreach($catalogs as $key=>$catalog)<div class="col-12 col-sm-6 col-xl-4"><a class="catalog-card" href="{{ $key === 'products' ? route('products.index') : route('catalogs.index',$key) }}"><span class="catalog-card-label">{{ $catalog['name'] }}</span><strong>{{ $catalog['count'] }}</strong><span>Consultar catálogo <span aria-hidden="true">→</span></span></a></div>@endforeach</div>
<section class="welcome-panel"><div class="panel-accent"></div><div><h2>Información clara, trabajo ordenado</h2><p class="mb-0">Use el menú para acceder a las opciones disponibles para su rol. Desde Mi cuenta puede actualizar su contraseña.</p><a class="btn btn-outline-primary mt-3" href="{{ route('account.edit') }}">Mi cuenta</a></div></section>
@endsection
