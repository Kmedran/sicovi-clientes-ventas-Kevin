@extends('layouts.app')
@section('title', 'Editar rol')
@section('content')
<div class="mb-4"><h1 class="h3">Editar rol: {{ $role->name }}</h1><p class="text-secondary">Los permisos se comprueban en cada solicitud. Conserve un administrador con capacidad de gestionar el acceso.</p></div>
<form method="POST" action="{{ route('roles.update', $role) }}">@csrf @method('PUT')
<div class="card border-0 shadow-sm mb-4"><div class="card-body p-4">
    <div class="row g-3"><div class="col-12 col-md-8"><label class="form-label" for="name">Nombre del rol</label><input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $role->name) }}" required maxlength="100">@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div><div class="col-12 col-md-4"><label class="form-label" for="active">Estado</label><select class="form-select" id="active" name="active"><option value="1" @selected((string) old('active', (int) $role->active) === '1')>Activo</option><option value="0" @selected((string) old('active', (int) $role->active) === '0')>Inactivo</option></select></div><div class="col-12"><label class="form-label" for="description">Descripción</label><textarea class="form-control" id="description" name="description" rows="2" maxlength="500">{{ old('description', $role->description) }}</textarea></div></div>
</div></div>
<div class="row g-4">@foreach($permissionGroups as $group => $permissions)<div class="col-12 col-md-6"><div class="card border-0 shadow-sm h-100"><div class="card-body p-4"><h2 class="h5 mb-3">{{ $group }}</h2>
    @foreach($permissions as $permission)<div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="permission-{{ $permission->id }}" name="permissions[]" value="{{ $permission->code }}" @checked(in_array($permission->code, old('permissions', $assigned), true))><label class="form-check-label" for="permission-{{ $permission->id }}">{{ $permission->name }}<span class="d-block small text-secondary">{{ $permission->code }}</span></label></div>@endforeach
</div></div></div>@endforeach</div>
<div class="d-flex gap-2 mt-4"><button class="btn btn-primary" type="submit">Guardar rol y permisos</button><a class="btn btn-outline-secondary" href="{{ route('roles.index') }}">Cancelar</a></div>
</form>
@endsection
