<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($username = $this->input('username'))) {
            $this->merge(['username' => mb_strtolower(trim($username))]);
        }
    }

    public function rules(): array
    {
        return [
            'username' => ['bail', 'required', 'string', 'min:3', 'max:40', 'regex:/\A[a-z0-9][a-z0-9._-]{2,39}\z/'],
            'password' => ['bail', 'required', 'string', 'max:1024'],
        ];
    }
}
