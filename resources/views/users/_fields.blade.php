@php
    $nameValue = old('name', $user->name ?? '');
    $nameValue = is_string($nameValue) ? $nameValue : '';
    $usernameValue = old('username', $user->username ?? '');
    $usernameValue = is_string($usernameValue) ? $usernameValue : '';
    $emailValue = old('email', $user->email ?? '');
    $emailValue = is_string($emailValue) ? $emailValue : '';
    $roleValue = old('role_id', $user->role_id ?? '');
    $roleValue = is_scalar($roleValue) ? (string) $roleValue : '';
@endphp
<div class="row g-3">
    <div class="col-12 col-md-6"><label class="form-label" for="name">Nombre completo</label><input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ $nameValue }}" required maxlength="150" autocomplete="name">@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    <div class="col-12 col-md-6"><label class="form-label" for="username">Usuario</label><input class="form-control @error('username') is-invalid @enderror" id="username" name="username" value="{{ $usernameValue }}" required minlength="3" maxlength="40" pattern="[A-Za-z0-9][A-Za-z0-9._\-]{2,39}" autocomplete="username" autocapitalize="none" spellcheck="false" aria-describedby="username-help"><div class="form-text" id="username-help">Para iniciar sesión. De 3 a 40 letras sin acentos, números, puntos, guiones o guiones bajos; debe comenzar con letra o número. Se guardará en minúsculas.</div>@error('username')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    <div class="col-12 col-md-6"><label class="form-label" for="email">Correo electrónico</label><input class="form-control @error('email') is-invalid @enderror" id="email" type="email" name="email" value="{{ $emailValue }}" required maxlength="254" autocomplete="email" autocapitalize="none" spellcheck="false" aria-describedby="email-help"><div class="form-text" id="email-help">Correo de contacto de la cuenta. Se guardará en minúsculas.</div>@error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    @can('roles.manage')
    <div class="col-12 col-md-6">
        <label class="form-label" for="role_id">Cargo</label>
        <select class="form-select @error('role_id') is-invalid @enderror" id="role_id" name="role_id" data-user-permission-role required aria-describedby="role-help">
            <option value="">Seleccione un cargo</option>
            @foreach($roles as $role)
                <option value="{{ $role->id }}" @selected($roleValue === (string) $role->id)>{{ $role->name }}{{ $role->active ? '' : ' (inactivo, asignación actual)' }}</option>
            @endforeach
        </select>
        <div class="form-text" id="role-help">Al cambiar el cargo se cargarán sus permisos predeterminados.</div>
        @error('role_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    @else
    <div class="col-12"><span class="text-secondary">Cargo asignado:</span> {{ $user->role->name }}</div>
    @endcan
</div>
