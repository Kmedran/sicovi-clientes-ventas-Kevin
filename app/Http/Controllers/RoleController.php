<?php

namespace App\Http\Controllers;

use App\Http\Requests\Roles\UpdateRoleRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Services\AccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        Gate::authorize('roles.view');

        return view('roles.index', ['roles' => Role::withCount(['users', 'permissions'])->orderBy('name')->get()]);
    }

    public function edit(Role $role): View
    {
        Gate::authorize('roles.manage');

        return view('roles.edit', ['role' => $role, 'permissionGroups' => Permission::orderBy('group')->orderBy('code')->get()->groupBy('group'), 'assigned' => $role->permissions()->pluck('code')->all()]);
    }

    public function update(UpdateRoleRequest $request, Role $role, AccessService $access): RedirectResponse
    {
        $access->updateRole($request->user(), $role, $request->validated());

        return redirect()->route('roles.index')->with('status', 'Rol y permisos actualizados. Los cambios se aplican desde la siguiente solicitud.');
    }
}
