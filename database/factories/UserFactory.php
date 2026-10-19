<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return ['name' => fake()->name(), 'username' => fake()->unique()->lexify('usuario-????????????'), 'email' => fake()->unique()->safeEmail(), 'password' => Str::random(32), 'role_id' => Role::where('code', 'viewer')->firstOrFail()->id, 'active' => true];
    }
}
