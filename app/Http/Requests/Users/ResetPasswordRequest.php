<?php

namespace App\Http\Requests\Users;

use App\Http\Requests\Auth\PasswordRules;
use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('users.reset_password') ?? false;
    }

    public function rules(): array
    {
        return [
            'password' => PasswordRules::confirmed(),
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }
}
