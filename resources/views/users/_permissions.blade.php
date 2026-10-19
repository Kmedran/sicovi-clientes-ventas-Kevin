@can('roles.manage')
    @php
        $hasSubmittedPermissions = old('permissions_present') !== null;
        $checkedPermissions = $hasSubmittedPermissions ? old('permissions', []) : ($selectedPermissions ?? []);
        $checkedPermissions = is_array($checkedPermissions) ? array_values($checkedPermissions) : [];
        $permissionState = [
            'rolePermissions' => $rolePermissions ?? [],
            'selectedPermissions' => $checkedPermissions,
        ];
        $groupNames = [
            'users' => 'Usuarios',
            'roles' => 'Roles',
            'settings' => 'Empresa',
            'products' => 'Productos',
            'categories' => 'Categorías',
            'subcategories' => 'Subcategorías',
            'brands' => 'Marcas',
            'units' => 'Unidades',
        ];
    @endphp
    <section class="mt-4" data-user-permissions aria-labelledby="user-permissions-title" aria-describedby="user-permissions-help">
        <input type="hidden" name="permissions_present" value="1">
        <div class="d-flex flex-column flex-sm-row align-items-sm-start justify-content-between gap-3 mb-3">
            <div>
                <h2 class="h5 mb-2" id="user-permissions-title">Permisos de este usuario</h2>
                <p class="small text-secondary mb-0" id="user-permissions-help">Seleccione las acciones que podrá realizar. Los cambios se aplican solo a esta cuenta.</p>
            </div>
            <button class="btn btn-outline-secondary btn-sm flex-shrink-0" type="button" data-reset-user-permissions>Usar permisos del cargo</button>
        </div>
        <p class="small text-secondary mb-3" data-permission-status role="status" aria-live="polite">
            @if($hasSubmittedPermissions)
                Se conservó la selección del formulario. Revísela antes de guardar.
            @elseif($hasPermissionOverrides ?? false)
                Esta cuenta tiene permisos personalizados.
            @elseif(isset($user))
                Se muestran los permisos actuales de esta cuenta.
            @else
                Seleccione un cargo para cargar sus permisos predeterminados.
            @endif
        </p>
        @error('permissions')<div class="alert alert-danger py-2" role="alert">{{ $message }}</div>@enderror
        @error('permissions.*')<div class="alert alert-danger py-2" role="alert">{{ $message }}</div>@enderror
        @error('permissions_present')<div class="alert alert-danger py-2" role="alert">{{ $message }}</div>@enderror
        <div class="row g-3">
            @foreach($permissionGroups as $group => $permissions)
                @php($groupLabel = $groupNames[$group] ?? 'Otros permisos')
                <div class="col-12 col-md-6">
                    <fieldset class="border rounded-3 p-3 h-100" data-permission-group>
                        <legend class="float-none w-auto px-1 fs-6 fw-semibold mb-2">{{ $groupLabel }}</legend>
                        <div class="form-check border-bottom pb-2 mb-3">
                            <input class="form-check-input" type="checkbox" id="user-permission-group-{{ $loop->index }}" data-permission-group-toggle>
                            <label class="form-check-label fw-semibold" for="user-permission-group-{{ $loop->index }}">Todas las acciones de {{ $groupLabel }}</label>
                        </div>
                        @foreach($permissions as $permission)
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="user-permission-{{ $permission->id }}" name="permissions[]" value="{{ $permission->code }}" data-user-permission @checked(in_array($permission->code, $checkedPermissions, true))>
                                <label class="form-check-label" for="user-permission-{{ $permission->id }}">{{ $permission->name }}</label>
                            </div>
                        @endforeach
                    </fieldset>
                </div>
            @endforeach
        </div>
        <p class="small text-secondary mb-0 mt-3" data-permission-count aria-live="polite">{{ count($checkedPermissions) }} acciones seleccionadas</p>
        <script type="application/json" data-user-permission-state>@json($permissionState, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)</script>
    </section>
@else
    <p class="small text-secondary mt-4 mb-0">Los permisos de esta cuenta se conservarán. Para cambiarlos se requiere autorización de administración.</p>
@endcan
