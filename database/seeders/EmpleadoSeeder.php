<?php

namespace Database\Seeders;

use App\Models\Cargo;
use App\Models\Empleado;
use Illuminate\Database\Seeder;

class EmpleadoSeeder extends Seeder
{
    public function run(): void
    {
        // El cargo se busca por NOMBRE en vez de escribir el id a mano (antes
        // cargo_id: 5 y cargo_id: 1). Si el orden de inserción de los cargos
        // cambia, los ids quedan desalineados.
        $cargo = fn (string $nombre) => Cargo::where('nombre_cargos', $nombre)->value('id');

        $empleados = [
            ['nombre' => 'Carlos M.', 'numero' => '001', 'cargo' => 'Administrador'],
            ['nombre' => 'Ana R.', 'numero' => '002', 'cargo' => 'Panadero'],
        ];

        foreach ($empleados as $empleado) {
            Empleado::firstOrCreate(
                ['nombre_empleados' => $empleado['nombre']],
                [
                    'numero_empleados' => $empleado['numero'],
                    'cargo_id' => $cargo($empleado['cargo']),
                ]
            );
        }
    }
}
