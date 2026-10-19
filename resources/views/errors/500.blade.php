@extends('layouts.app')
@section('title','No se pudo completar la operación')
@section('content')<section class="surface p-4 form-narrow mx-auto my-4"><p class="eyebrow">500</p><h1>No se pudo completar la operación</h1><p class="text-secondary">Intente de nuevo en unos momentos. Si continúa, comuníquese con su administrador.</p><a class="btn btn-primary" href="{{ auth()->check()?route('dashboard'):route('login') }}">Volver al inicio</a></section>@endsection
