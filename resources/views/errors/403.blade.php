@extends('layouts.app')
@section('title','Acceso restringido')
@section('content')<section class="surface p-4 form-narrow mx-auto my-4"><p class="eyebrow">403</p><h1>Acceso restringido</h1><p class="text-secondary">Su cuenta no tiene permiso para realizar esta acción.</p><a class="btn btn-primary" href="{{ auth()->check()?route('dashboard'):route('login') }}">Volver al inicio</a></section>@endsection
