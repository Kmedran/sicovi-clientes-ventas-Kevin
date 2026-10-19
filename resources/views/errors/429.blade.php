@extends('layouts.app')
@section('title','Demasiadas solicitudes')
@section('content')<section class="surface p-4 form-narrow mx-auto my-4"><p class="eyebrow">429</p><h1>Demasiadas solicitudes</h1><p class="text-secondary">Espere un momento antes de volver a intentarlo.</p><a class="btn btn-primary" href="{{ auth()->check()?route('dashboard'):route('login') }}">Volver al inicio</a></section>@endsection
