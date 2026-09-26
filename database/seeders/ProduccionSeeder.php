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
        $empleado = fn (string $nombre) => DB::table('empleados')->where('nombre_empleados', $nombre)->orderBy('id')->value('id');
        $rol = fn (string $nombre) => DB::table('roles_produccion')->where('nombre_roles_produccion', $nombre)->value('id');

        // Cada linea de detalle incluye los empleados que la produjeron y el
        // rol que tenian, para que las vistas tengan algo real que mostrar.
        $registros = [
            [
                'fecha' => today(),
                'observaciones' => 'Lote del dia, turno de manana.',
                'pan' => [
                    ['producto' => 'Pan Francés', 'cantidad' => 24, 'turno' => 'Mañana', 'empleados' => [['Carlos M.', 'Maestro'], ['Ana R.', 'Ayudante']]],
                    ['producto' => 'Pan Yema', 'cantidad' => 18, 'turno' => 'Mañana', 'empleados' => [['Ana R.', 'Ayudante']]],
                ],
                'bocaditos' => [
                    ['producto' => 'Alfajorcitos', 'cantidad' => 150, 'empleados' => [['Ana R.', 'Maestro']]],
                ],
            ],
            [
                'fecha' => today()->subDay(),
                'observaciones' => null,
                'tortas' => [
                    ['producto' => 'Torta de Chocolate', 'forma' => 'circular', 'empleados' => [['Carlos M.', 'Maestro'], ['Ana R.', 'Ayudante']]],
                ],
                'pan' => [
                    ['producto' => 'Pan Carioca', 'cantidad' => 12, 'turno' => 'Mañana', 'empleados' => [['Carlos M.', 'Maestro']]],
                ],
            ],
            [
                'fecha' => today()->subDays(2),
                'observaciones' => 'Faltaron 2 unidades de convo, se completo con el turno noche.',
                'bocaditos' => [
                    ['producto' => 'Conitos', 'cantidad' => 200, 'empleados' => [['Ana R.', 'Ayudante']]],
                    ['producto' => 'Pionono', 'cantidad' => 150, 'empleados' => [['Carlos M.', 'Maestro']]],
                ],
                'pan' => [
                    ['producto' => 'Pan Integral', 'cantidad' => 30, 'turno' => 'Noche', 'empleados' => [['Carlos M.', 'Maestro'], ['Ana R.', 'Ayudante']]],
                ],
            ],
            [
                'fecha' => today()->subDays(3),
                'observaciones' => null,
                'tortas' => [
                    ['producto' => 'Torta de Vainilla', 'forma' => 'rectangular', 'empleados' => [['Ana R.', 'Maestro']]],
                ],
                'bocaditos' => [
                    ['producto' => 'Empanaditas de Pollo', 'cantidad' => 300, 'empleados' => [['Carlos M.', 'Ayudante']]],
                ],
            ],
        ];

        foreach ($registros as $registro) {
            $produccionId = DB::table('produccion')->insertGetId([
                'fecha' => $registro['fecha'],
                'observaciones' => $registro['observaciones'],
                'registrado_por_usuario_id' => $usuarioId,
            ]);

            foreach ($registro['pan'] ?? [] as $linea) {
                $detalleId = DB::table('detalle_pan')->insertGetId([
                    'produccion_id' => $produccionId,
                    'producto_id' => $producto($linea['producto']),
                    'unidad_medida_id' => $unidadId,
                    'turno_id' => $turno($linea['turno']),
                    'cantidad' => $linea['cantidad'],
                ]);

                foreach ($linea['empleados'] as [$nombreEmpleado, $nombreRol]) {
                    DB::table('detalle_pan_empleado')->insert([
                        'detalle_pan_id' => $detalleId,
                        'empleado_id' => $empleado($nombreEmpleado),
                        'rol_produccion_id' => $rol($nombreRol),
                    ]);
                }
            }

            foreach ($registro['tortas'] ?? [] as $linea) {
                // una torta = un registro, por eso no hay 'cantidad'
                $detalleId = DB::table('detalle_torta')->insertGetId([
                    'produccion_id' => $produccionId,
                    'producto_id' => $producto($linea['producto']),
                    'unidad_medida_id' => $unidadId,
                    'forma' => $linea['forma'],
                ]);

                foreach ($linea['empleados'] as [$nombreEmpleado, $nombreRol]) {
                    DB::table('detalle_torta_empleado')->insert([
                        'detalle_torta_id' => $detalleId,
                        'empleado_id' => $empleado($nombreEmpleado),
                        'rol_produccion_id' => $rol($nombreRol),
                    ]);
                }
            }

            foreach ($registro['bocaditos'] ?? [] as $linea) {
                $detalleId = DB::table('detalle_bocadito')->insertGetId([
                    'produccion_id' => $produccionId,
                    'producto_id' => $producto($linea['producto']),
                    'cantidad' => $linea['cantidad'],
                ]);

                foreach ($linea['empleados'] as [$nombreEmpleado, $nombreRol]) {
                    DB::table('detalle_bocadito_empleado')->insert([
                        'detalle_bocadito_id' => $detalleId,
                        'empleado_id' => $empleado($nombreEmpleado),
                        'rol_produccion_id' => $rol($nombreRol),
                    ]);
                }
            }
        }
    }
}
