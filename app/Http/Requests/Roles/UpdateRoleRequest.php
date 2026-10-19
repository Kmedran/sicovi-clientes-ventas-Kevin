<?php

namespace App\Http\Requests\Roles;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('roles.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($name = $this->input('name')) ? preg_replace('/\s+/u', ' ', trim($name)) : $name,
            'active' => $this->input('active', '0'),
            'permissions' => $this->input('permissions', []),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['bail', 'required', 'string', 'max:100', function ($attribute, $value, $fail): void {
                if (Role::whereRaw('lower(btrim(name)) = ?', [mb_strtolower($value)])->whereKeyNot($this->route('role')->getKey())->exists()) {
                    $fail('Ya existe un rol con ese nombre.');
                }
            }],
            'description' => ['nullable', 'string', 'max:500'],
            'active' => ['required', 'boolean'],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['bail', 'string', 'distinct', Rule::exists('permissions', 'code')],
            'code' => ['prohibited'],
        ];
    }
}
