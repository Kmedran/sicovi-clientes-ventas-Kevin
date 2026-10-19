@extends('layouts.app')
@section('title', 'Usuarios')
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div><h1 class="h3 mb-1">Usuarios</h1><p class="text-secondary mb-0">Cuentas y acceso del equipo.</p></div>
    @can('users.create') @can('roles.manage')<a class="btn btn-primary" href="{{ route('users.create') }}">Nuevo usuario</a>@endcan @endcan
</div>
<div class="card border-0 shadow-sm mb-4"><div class="card-body">
    <form method="GET" class="row g-3 align-items-end">
        <div class="col-12 col-md-6"><label for="q" class="form-label">Nombre, usuario o correo</label><input class="form-control" id="q" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="150" placeholder="Buscar cuenta"></div>
        <div class="col-12 col-md-3"><label for="active" class="form-label">Estado</label><select class="form-select" id="active" name="active"><option value="">Todos</option><option value="1" @selected(($filters['active'] ?? '') === '1')>Activo</option><option value="0" @selected(($filters['active'] ?? '') === '0')>Inactivo</option></select></div>
        <div class="col-12 col-md-3 d-flex gap-2"><button class="btn btn-primary" type="submit">Filtrar</button><a class="btn btn-outline-secondary" href="{{ route('users.index') }}">Limpiar</a></div>
    </form>
</div></div>
<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table align-middle mb-0">
    <thead><tr><th scope="col">Usuario</th><th scope="col">Nombre y correo</th><th scope="col">Cargo</th><th scope="col">Estado</th><th scope="col">Último acceso</th><th scope="col" class="text-end">Acciones</th></tr></thead>
    <tbody>@forelse($users as $user)<tr>
        <td class="fw-semibold text-break">{{ $user->username }}</td><td><div class="fw-semibold">{{ $user->name }}</div><div class="small text-secondary text-break">{{ $user->email }}</div></td>
        <td>{{ $user->role->name }}</td><td><span class="badge {{ $user->active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $user->active ? 'Activo' : 'Inactivo' }}</span></td>
        <td class="small">{{ $user->last_login_at?->timezone(config('sicovi.display_timezone', 'America/Guatemala'))->format('d/m/Y H:i') ?? 'Sin acceso' }}</td>
        <td class="text-end"><div class="d-flex justify-content-end gap-2 flex-wrap">
            @can('users.update')<a class="btn btn-sm btn-outline-primary" href="{{ route('users.edit', $user) }}">Editar</a>@endcan
            @can('users.deactivate')<form method="POST" action="{{ route('users.deactivate', $user) }}">@csrf @method('PATCH')<input type="hidden" name="active" value="{{ $user->active ? '0' : '1' }}"><button class="btn btn-sm {{ $user->active ? 'btn-outline-danger' : 'btn-outline-success' }}" type="submit">{{ $user->active ? 'Desactivar' : 'Activar' }}</button></form>@endcan
        </div></td>
    </tr>@empty<tr><td colspan="6" class="text-center text-secondary py-5">No hay usuarios para los filtros indicados.</td></tr>@endforelse</tbody>
</table></div><div class="card-body">{{ $users->links() }}</div></div>
@endsection
