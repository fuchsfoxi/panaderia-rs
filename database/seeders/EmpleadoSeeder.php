<?php

namespace Database\Seeders;

use App\Models\Cargo;
use App\Models\Empleado;
use Illuminate\Database\Seeder;

class EmpleadoSeeder extends Seeder
{
    public const NOMBRE_ADMINISTRADOR = 'Carlos M.';

    public const NUMERO_ADMINISTRADOR = '001';

    public function run(): void
    {
        foreach ([
            [self::NOMBRE_ADMINISTRADOR, self::NUMERO_ADMINISTRADOR, 'Administrador'],
            ['Ana R.', '002', 'Panadero'],
        ] as [$nombre, $numero, $cargo]) {
            Empleado::firstOrCreate([
                'nombre_empleados' => $nombre,
                'numero_empleados' => $numero,
            ], [
                'cargo_id' => Cargo::where('nombre_cargos', $cargo)->sole()->id,
            ]);
        }
    }
}
