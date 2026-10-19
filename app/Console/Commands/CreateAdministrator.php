<?php

namespace App\Console\Commands;

use App\Http\Requests\Auth\PasswordRules;
use App\Services\AccessService;
use App\Support\Usernames;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CreateAdministrator extends Command
{
    protected $signature = 'sicovi:crear-administrador';

    protected $description = 'Crea una cuenta administrativa local con contraseña oculta, sin sobrescribir cuentas existentes';

    public function handle(AccessService $access): int
    {
        $name = preg_replace('/\s+/u', ' ', trim((string) $this->ask('Nombre completo')));
        $username = Usernames::normalize((string) $this->ask('Nombre de usuario'));
        $email = mb_strtolower(trim((string) $this->ask('Correo electrónico')));
        $identity = Validator::make(compact('name', 'username', 'email'), [
            'name' => ['required', 'string', 'max:150'],
            'username' => ['required', 'string', 'min:3', 'max:40', 'regex:/\A[a-z0-9][a-z0-9._-]{2,39}\z/D', Rule::unique('users', 'username')],
            'email' => ['required', 'email', 'max:254', Rule::unique('users', 'email')],
        ]);
        if ($identity->fails()) {
            foreach ($identity->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }
        $password = $this->secret('Contraseña (12 o más caracteres, mayúscula, minúscula, número y símbolo)', false);
        $confirmation = $this->secret('Repita la contraseña', false);
        $validator = Validator::make(['password' => $password, 'password_confirmation' => $confirmation], [
            'password' => PasswordRules::confirmed(),
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }
        try {
            $access->createAdministrator(['name' => $name, 'username' => $username, 'email' => $email, 'password' => $password]);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->error($message);
                }
            }

            return self::FAILURE;
        }
        $this->info('Administrador creado. Usuario de acceso: '.$username.'. La contraseña no se muestra ni se guardó en la auditoría.');

        return self::SUCCESS;
    }
}
