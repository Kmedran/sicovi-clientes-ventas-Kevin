@extends('layouts.app')
@section('title', 'Roles y permisos')
@section('content')
<div class="mb-4"><h1 class="h3">Roles y permisos</h1><p class="text-secondary">Controle las acciones disponibles para cada perfil.</p></div>
<div class="row g-4">@foreach($roles as $role)
<div class="col-12 col-md-6"><div class="card border-0 shadow-sm h-100"><div class="card-body p-4">
    <div class="d-flex justify-content-between gap-2 mb-3"><h2 class="h5 mb-0">{{ $role->name }}</h2><span class="badge {{ $role->active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $role->active ? 'Activo' : 'Inactivo' }}</span></div>
    <p class="text-secondary">{{ $role->description }}</p><p class="small">{{ $role->users_count }} usuarios · {{ $role->permissions_count }} permisos</p>
    @can('roles.manage')<a class="btn btn-outline-primary btn-sm" href="{{ route('roles.edit', $role) }}">Editar permisos</a>@endcan
</div></div></div>
@endforeach</div>
@endsection
