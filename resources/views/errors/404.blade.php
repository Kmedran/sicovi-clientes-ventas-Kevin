@extends('layouts.app')
@section('title','Página no encontrada')
@section('content')<section class="surface p-4 form-narrow mx-auto my-4"><p class="eyebrow">404</p><h1>Página no encontrada</h1><p class="text-secondary">El recurso solicitado no está disponible.</p><a class="btn btn-primary" href="{{ auth()->check()?route('dashboard'):route('login') }}">Volver al inicio</a></section>@endsection
