<?php

namespace Database\Seeders;

use App\Models\Empleado;
use App\Models\Rol;
use App\Models\UsuarioSistema;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsuarioSeeder extends Seeder
{
    public function run(): void
    {
        // El empleado y el rol se buscan por NOMBRE en vez de escribir el id a
        // mano (antes empleado_id: 1 y rol_id: 1), que quedaban desalineados si
        // cambiaba el orden de los seeders anteriores.
        $empleado = Empleado::where('nombre_empleados', 'Carlos M.')->value('id');
        $rol = Rol::where('nombre_roles', 'Administrador')->value('id');

        // firstOrCreate sobre el modelo en vez de DB::table()->insert():
        // 'username' y 'empleado_id' tienen índice UNIQUE en la base, así que
        // el insert() plano fallaba con "Duplicate entry" al repetir db:seed.
        //
        // El password_hash solo se genera si el usuario NO existe: en las
        // corridas siguientes se conserva el hash original en vez de
        // rehashearlo en cada ejecución.
        UsuarioSistema::firstOrCreate(
            ['username' => 'carlos.m'],
            [
                'password_hash' => Hash::make('password123'),
                'empleado_id' => $empleado,
                'rol_id' => $rol,
            ]
        );
    }
}
