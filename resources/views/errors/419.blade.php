@extends('layouts.app')
@section('title','La sesión ha vencido')
@section('content')<section class="surface p-4 form-narrow mx-auto my-4"><p class="eyebrow">419</p><h1>La sesión ha vencido</h1><p class="text-secondary">Vuelva a iniciar sesión e intente de nuevo.</p><a class="btn btn-primary" href="{{ auth()->check()?route('dashboard'):route('login') }}">Volver al inicio</a></section>@endsection
