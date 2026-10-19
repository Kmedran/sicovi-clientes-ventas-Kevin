@extends('layouts.app')
@section('title','Servicio temporalmente no disponible')
@section('content')<section class="surface p-4 form-narrow mx-auto my-4"><p class="eyebrow">503</p><h1>Servicio temporalmente no disponible</h1><p class="text-secondary">Intente de nuevo en unos momentos.</p><a class="btn btn-primary" href="{{ auth()->check()?route('dashboard'):route('login') }}">Volver al inicio</a></section>@endsection
