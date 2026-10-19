<?php

namespace App\Http\Controllers;

use App\Http\Requests\Users\ResetPasswordRequest;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('users.view');
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:150'], 'active' => ['nullable', Rule::in(['0', '1'])]]);
        $users = User::with('role')->when($filters['q'] ?? null, function ($query, $term): void {
            $query->where(fn ($query) => $query->where('name', 'ilike', '%'.$term.'%')->orWhere('username', 'ilike', '%'.$term.'%')->orWhere('email', 'ilike', '%'.$term.'%'));
        })->when(isset($filters['active']), fn ($query) => $query->where('active', $filters['active']))->orderBy('name')->paginate(15)->withQueryString();

        return view('users.index', compact('users', 'filters'));
    }

    public function create(): View
    {
        Gate::authorize('users.create');
        Gate::authorize('roles.manage');

        $roles = Role::with('permissions')->where('active', true)->orderBy('name')->get();

        return view('users.create', [
            'roles' => $roles,
            'permissionGroups' => Permission::orderBy('group')->orderBy('name')->get()->groupBy('group'),
            'rolePermissions' => $roles->mapWithKeys(fn (Role $role) => [$role->id => $role->permissions->pluck('code')->values()->all()])->all(),
            'selectedPermissions' => [],
            'hasPermissionOverrides' => false,
        ]);
    }

    public function store(StoreUserRequest $request, AccessService $access): RedirectResponse
    {
        $access->createUser($request->user(), $request->validated());

        return redirect()->route('users.index')->with('status', 'Usuario creado correctamente.');
    }

    public function edit(User $user): View
    {
        Gate::authorize('users.update');

        $roles = Role::with('permissions')->where('active', true)->orWhereKey($user->role_id)->orderBy('name')->get();

        return view('users.edit', [
            'user' => $user,
            'roles' => $roles,
            'permissionGroups' => Permission::orderBy('group')->orderBy('name')->get()->groupBy('group'),
            'rolePermissions' => $roles->mapWithKeys(fn (Role $role) => [$role->id => $role->permissions->pluck('code')->values()->all()])->all(),
            'selectedPermissions' => $user->effectivePermissionCodes(),
            'hasPermissionOverrides' => $user->permissionOverrides()->exists(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user, AccessService $access): RedirectResponse
    {
        $access->updateUser($request->user(), $user, $request->validated());

        return redirect()->route('users.index')->with('status', 'Usuario actualizado correctamente.');
    }

    public function deactivate(Request $request, User $user, AccessService $access): RedirectResponse
    {
        Gate::authorize('users.deactivate');
        $data = $request->validate(['active' => ['required', 'boolean']]);
        $access->setUserActive($request->user(), $user, (bool) $data['active']);
        if ($request->user()->is($user) && ! $data['active']) {
            return $this->closeCurrentSession($request, 'La cuenta se desactivó y la sesión se cerró.');
        }

        return redirect()->route('users.index')->with('status', 'Estado de usuario actualizado.');
    }

    public function resetPassword(ResetPasswordRequest $request, User $user, AccessService $access): RedirectResponse
    {
        $access->resetPassword($request->user(), $user, $request->validated('password'), $request->validated('reason'));
        if ($request->user()->is($user)) {
            return $this->closeCurrentSession($request, 'Contraseña restablecida. Inicie sesión nuevamente.');
        }

        return redirect()->route('users.index')->with('status', 'Contraseña restablecida. Se revocaron todas las sesiones de esa cuenta.');
    }

    private function closeCurrentSession(Request $request, string $message): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', $message);
    }
}
