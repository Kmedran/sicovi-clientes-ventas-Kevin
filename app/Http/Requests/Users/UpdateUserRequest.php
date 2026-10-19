<?php

namespace App\Http\Requests\Users;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('users.update') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];
        if (is_string($name = $this->input('name'))) {
            $normalized['name'] = preg_replace('/\s+/u', ' ', trim($name));
        }
        if (is_string($email = $this->input('email'))) {
            $normalized['email'] = mb_strtolower(trim($email));
        }
        if (is_string($username = $this->input('username'))) {
            $normalized['username'] = strtolower(trim($username));
        }
        $this->merge($normalized);
    }

    public function rules(): array
    {
        $target = $this->route('user');
        $canAssign = $this->user()->hasPermission('roles.manage');

        return [
            'name' => ['bail', 'required', 'string', 'max:150'],
            'username' => ['bail', 'required', 'string', 'min:3', 'max:40', 'regex:/\A[a-z0-9][a-z0-9._-]{2,39}\z/', Rule::unique('users', 'username')->ignore($target)],
            'email' => ['bail', 'required', 'string', 'email', 'max:254', Rule::unique('users', 'email')->ignore($target)],
            'role_id' => $canAssign ? ['bail', 'required', 'integer', Rule::exists('roles', 'id')->where(fn ($query) => $query->where('active', true)->orWhere('id', $target->role_id))] : ['prohibited'],
            'active' => ['prohibited'],
            'password' => ['prohibited'],
            'is_demo' => ['prohibited'],
            'permissions_present' => $canAssign ? ['sometimes', 'boolean'] : ['prohibited'],
            'permissions' => $canAssign ? ['sometimes', 'array'] : ['prohibited'],
            'permissions.*' => $canAssign ? ['bail', 'string', 'distinct', Rule::exists('permissions', 'code')] : ['prohibited'],
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            if (! $this->boolean('permissions_present') && is_array($permissions = $this->input('permissions')) && $permissions !== []) {
                $validator->errors()->add('permissions', 'Confirme el formulario de permisos para cambiar las asignaciones del usuario.');
            }
        }];
    }
}
