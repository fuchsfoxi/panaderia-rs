<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProduccionSeeder extends Seeder
{
    public function run(): void
    {
        // Datos de demostracion. No se corre si ya hay produccion cargada,
        // para que db:seed se pueda re-ejecutar sin duplicar historial.
        if (DB::table('produccion')->exists()) {
            return;
        }

        $usuarioId = DB::table('usuarios_sistema')->where('username', 'carlos.m')->value('id');

        $producto = fn (string $nombre) => DB::table('productos')->where('nombre_p', $nombre)->value('id');
        $unidadId = DB::table('unidades_medida')->where('nombre_unidades_medida', 'unidad')->value('id');
        $turno = fn (string $nombre) => DB::table('turnos')->where('nombre_turnos', $nombre)->value('id');

        $registros = [
            [
                'fecha' => today(),
                'observaciones' => 'Lote del dia, turno de manana.',
                'pan' => [['Pan Francés', 24, 'Mañana'], ['Pan Yema', 18, 'Mañana']],
                'bocaditos' => [['Alfajorcitos', 150]],
            ],
            [
                'fecha' => today()->subDay(),
                'observaciones' => null,
                'tortas' => [['Torta de Chocolate', 'circular']],
                'pan' => [['Pan Carioca', 12, 'Mañana']],
            ],
            [
                'fecha' => today()->subDays(2),
                'observaciones' => 'Faltaron 2 unidades de convo, se completo con el turno noche.',
                'bocaditos' => [['Conitos', 200], ['Pionono', 150]],
                'pan' => [['Pan Integral', 30, 'Noche']],
            ],
            [
                'fecha' => today()->subDays(3),
                'observaciones' => null,
                'tortas' => [['Torta de Vainilla', 'rectangular']],
                'bocaditos' => [['Empanaditas de Pollo', 300]],
            ],
        ];

        foreach ($registros as $registro) {
            $produccionId = DB::table('produccion')->insertGetId([
                'fecha' => $registro['fecha'],
                'observaciones' => $registro['observaciones'],
                'registrado_por_usuario_id' => $usuarioId,
            ]);

            foreach ($registro['pan'] ?? [] as [$nombre, $cantidad, $nombreTurno]) {
                DB::table('detalle_pan')->insert([
                    'produccion_id' => $produccionId,
                    'producto_id' => $producto($nombre),
                    'unidad_medida_id' => $unidadId,
                    'turno_id' => $turno($nombreTurno),
                    'cantidad' => $cantidad,
                ]);
            }

            foreach ($registro['tortas'] ?? [] as [$nombre, $forma]) {
                // una torta = un registro, por eso no hay 'cantidad'
                DB::table('detalle_torta')->insert([
                    'produccion_id' => $produccionId,
                    'producto_id' => $producto($nombre),
                    'unidad_medida_id' => $unidadId,
                    'forma' => $forma,
                ]);
            }

            foreach ($registro['bocaditos'] ?? [] as [$nombre, $cantidad]) {
                DB::table('detalle_bocadito')->insert([
                    'produccion_id' => $produccionId,
                    'producto_id' => $producto($nombre),
                    'cantidad' => $cantidad,
                ]);
            }
        }
    }
}
