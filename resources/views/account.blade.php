@extends('layouts.app')
@section('title', 'Mi cuenta')
@section('content')
<div class="mb-4"><h1 class="h3">Mi cuenta</h1><p class="text-secondary mb-1">{{ auth()->user()->name }}</p><p class="small text-secondary text-break">Usuario: <strong>{{ auth()->user()->username }}</strong> · {{ auth()->user()->email }}</p></div>
<div class="card border-0 shadow-sm" style="max-width: 680px"><div class="card-body p-4">
    <h2 class="h5 mb-3">Cambiar contraseña</h2>
    <p class="small text-secondary">Use al menos 12 caracteres con mayúsculas, minúsculas, números y símbolos. Al guardar, se cerrarán todas sus sesiones.</p>
    <form method="POST" action="{{ route('account.password') }}">
        @csrf @method('PUT')
        <div class="mb-3"><label class="form-label" for="current_password">Contraseña actual</label><input class="form-control @error('current_password') is-invalid @enderror" id="current_password" type="password" name="current_password" autocomplete="current-password" required>@error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="mb-3"><label class="form-label" for="password">Nueva contraseña</label><input class="form-control @error('password') is-invalid @enderror" id="password" type="password" name="password" autocomplete="new-password" minlength="12" maxlength="72" required>@error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="mb-4"><label class="form-label" for="password_confirmation">Confirmar nueva contraseña</label><input class="form-control" id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required></div>
        <button class="btn btn-primary" type="submit">Actualizar contraseña</button>
    </form>
</div></div>
@endsection
