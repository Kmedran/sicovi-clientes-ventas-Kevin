<?php

namespace App\Console\Commands;

use App\Services\DemoUserService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateDemoUsers extends Command
{
    protected $signature = 'sicovi:crear-usuarios-demo {--inicial=m : De una a cuatro letras para identificar los usuarios y correos de demostración}';

    protected $description = 'Crea cuatro cuentas de demostración únicamente en un entorno local habilitado, sin restablecer cuentas existentes';

    public function handle(DemoUserService $demoUsers): int
    {
        try {
            $initial = $this->option('inicial');
            if (! is_string($initial)) {
                throw ValidationException::withMessages(['initial' => 'Indique una inicial de una a cuatro letras ASCII.']);
            }
            $results = $demoUsers->provision($initial);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->error($message);
                }
            }

            return self::FAILURE;
        } catch (Throwable) {
            // Exception payloads can contain connection data; never echo or log them here.
            $this->error('No se pudieron preparar las cuentas. Revise las migraciones, los roles y la habilitación explícita de demostración en la base local o de pruebas.');

            return self::FAILURE;
        }

        $this->warn('Uso exclusivo de demostración local. Las cuentas existentes conservan sus contraseñas y estado.');
        $this->table(['Usuario', 'Correo', 'Rol', 'Estado'], array_map(fn (array $result) => [
            $result['username'], $result['email'], $result['role'], $result['status'] === 'created' ? 'Creada' : 'Existente, sin cambios',
        ], $results));

        return self::SUCCESS;
    }
}
