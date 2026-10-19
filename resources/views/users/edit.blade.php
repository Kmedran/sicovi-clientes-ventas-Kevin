@extends('layouts.app')
@section('title', 'Editar usuario')
@section('content')
<div class="mb-4"><h1 class="h3">Editar usuario</h1><p class="text-secondary">{{ $user->name }} · {{ $user->active ? 'Activo' : 'Inactivo' }}</p></div>
<div class="card border-0 shadow-sm mb-4"><div class="card-body p-4">
<form method="POST" action="{{ route('users.update', $user) }}">@csrf @method('PUT')
    @include('users._fields')
    @include('users._permissions')
    <div class="d-flex gap-2 mt-4"><button class="btn btn-primary" type="submit">Guardar cambios</button><a class="btn btn-outline-secondary" href="{{ route('users.index') }}">Volver</a></div>
</form>
</div></div>
@can('users.reset_password')
<div class="card border-0 shadow-sm"><div class="card-body p-4">
    <h2 class="h5">Restablecer contraseña</h2><p class="small text-secondary">Se cerrarán todas las sesiones de esta cuenta. Use al menos 12 caracteres con mayúscula, minúscula, número y símbolo.</p>
    <form method="POST" action="{{ route('users.reset-password', $user) }}">@csrf @method('PUT')
        <div class="row g-3"><div class="col-12 col-md-6"><label class="form-label" for="password">Nueva contraseña</label><input class="form-control @error('password') is-invalid @enderror" id="password" type="password" name="password" autocomplete="new-password" minlength="12" maxlength="72" required>@error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror</div><div class="col-12 col-md-6"><label class="form-label" for="password_confirmation">Confirmar contraseña</label><input class="form-control" id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required></div></div>
        <div class="mt-3"><label class="form-label" for="reason">Motivo del restablecimiento</label><textarea class="form-control @error('reason') is-invalid @enderror" id="reason" name="reason" required minlength="5" maxlength="500" rows="2">{{ old('reason') }}</textarea>@error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <button class="btn btn-outline-danger mt-3" type="submit">Restablecer y cerrar sesiones</button>
    </form>
</div></div>
@endcan
@endsection
