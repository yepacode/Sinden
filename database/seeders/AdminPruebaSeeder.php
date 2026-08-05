<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Crea (o actualiza) un usuario ADMINISTRADOR de prueba.
 * Idempotente: se puede correr varias veces sin duplicar; si el correo ya
 * existe, actualiza el nombre/clave y le garantiza el rol.
 *
 * Uso:
 *   php artisan db:seed --class=AdminPruebaSeeder --force
 */
class AdminPruebaSeeder extends Seeder
{
    public function run(): void
    {
        // ==== Datos del usuario de prueba (puede cambiarlos si quiere) ====
        $nombre = 'Administrador Prueba';
        $email  = 'admin.prueba@sinden.com.co';
        $clave  = 'Sinden2026*';
        // ==================================================================

        // Asegurar que el rol exista (por si aun no se ha sembrado).
        Role::firstOrCreate(['name' => 'Administrador']);

        // Crear o actualizar el usuario.
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name'     => $nombre,
                'password' => Hash::make($clave),
                'activo'   => true,
            ]
        );

        if (! $user->hasRole('Administrador')) {
            $user->assignRole('Administrador');
        }

        $this->command->info('====================================================');
        $this->command->info('  Usuario ADMINISTRADOR de prueba listo:');
        $this->command->info('     Correo: ' . $email);
        $this->command->info('     Clave:  ' . $clave);
        $this->command->info('  Ingrese en: https://ordenes.sinden.com.co/login');
        $this->command->info('====================================================');
    }
}
