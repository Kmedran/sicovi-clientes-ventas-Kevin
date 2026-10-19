@extends('layouts.app')
@section('title', 'Nuevo usuario')
@section('content')
<div class="mb-4"><h1 class="h3">Nuevo usuario</h1><p class="text-secondary">La cuenta se creará activa. Asigne únicamente el acceso que necesita.</p></div>
<div class="card border-0 shadow-sm"><div class="card-body p-4">
<form method="POST" action="{{ route('users.store') }}">@csrf
    @include('users._fields')
    @include('users._permissions')
    <p class="small text-secondary mt-4">La contraseña requiere 12 caracteres como mínimo, mayúscula, minúscula, número y símbolo.</p>
    <div class="row g-3"><div class="col-12 col-md-6"><label class="form-label" for="password">Contraseña inicial</label><input class="form-control @error('password') is-invalid @enderror" id="password" type="password" name="password" autocomplete="new-password" minlength="12" maxlength="72" required>@error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror</div><div class="col-12 col-md-6"><label class="form-label" for="password_confirmation">Confirmar contraseña</label><input class="form-control" id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required></div></div>
    <div class="d-flex gap-2 mt-4"><button class="btn btn-primary" type="submit">Crear usuario</button><a class="btn btn-outline-secondary" href="{{ route('users.index') }}">Cancelar</a></div>
</form>
</div></div>
@endsection
