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

        /**
         * Cada linea de detalle incluye los empleados que la produjeron y el
         * rol que tenian, para que las vistas tengan algo real que mostrar.
         *
         * MODIFICADO: antes solo habia produccion de HOY y de los 3 dias
         * anteriores. Con eso el dashboard se veia casi vacio: el grafico
         * "Produccion por Mes" divide el mes en Sem 1 a Sem 4, y los 4 dias
         * caian todos en la ultima semana, dejando 3 de 4 semanas en cero.
         *
         * Ahora hay historial repartido en las ultimas 5 semanas, y ademas se
         * mantienen los registros de HOY, que son los que muestran las
         * tarjetas del dashboard (ResumenDashboard filtra por today()).
         *
         * NO se inventan datos futuros: un dia que todavia no ocurrio se
         * muestra en cero, que es la verdad.
         */
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

            /*
             * Historial de las semanas anteriores: le da movimiento al grafico
             * "por Mes" y volumen al historial al filtrar por rango de fechas.
             */
            [
                'fecha' => today()->subDays(6),
                'observaciones' => 'Produccion de cierre de semana.',
                'pan' => [
                    ['producto' => 'Pan Francés', 'cantidad' => 20, 'turno' => 'Mañana', 'empleados' => [['Luis P.', 'Maestro']]],
                    ['producto' => 'Pan Carioca', 'cantidad' => 15, 'turno' => 'Noche', 'empleados' => [['Ana R.', 'Ayudante']]],
                ],
                'tortas' => [
                    ['producto' => 'Torta de Chocolate', 'forma' => 'circular', 'empleados' => [['Rosa D.', 'Maestro']]],
                ],
            ],
            [
                'fecha' => today()->subDays(8),
                'observaciones' => null,
                'bocaditos' => [
                    ['producto' => 'Alfajorcitos', 'cantidad' => 180, 'empleados' => [['Marta G.', 'Maestro']]],
                ],
                'pan' => [
                    ['producto' => 'Pan Yema', 'cantidad' => 22, 'turno' => 'Mañana', 'empleados' => [['Luis P.', 'Maestro'], ['Ana R.', 'Ayudante']]],
                ],
            ],
            [
                'fecha' => today()->subDays(12),
                'observaciones' => 'Se produjo para el fin de semana.',
                'pan' => [
                    ['producto' => 'Pan Integral', 'cantidad' => 28, 'turno' => 'Mañana', 'empleados' => [['Carlos M.', 'Maestro']]],
                ],
                'bocaditos' => [
                    ['producto' => 'Empanaditas de Pollo', 'cantidad' => 250, 'empleados' => [['Marta G.', 'Ayudante']]],
                ],
            ],
            [
                'fecha' => today()->subDays(15),
                'observaciones' => null,
                'tortas' => [
                    ['producto' => 'Torta de Vainilla', 'forma' => 'rectangular', 'empleados' => [['Rosa D.', 'Maestro'], ['Luis P.', 'Ayudante']]],
                ],
                'pan' => [
                    ['producto' => 'Pan Francés', 'cantidad' => 26, 'turno' => 'Noche', 'empleados' => [['Carlos M.', 'Maestro']]],
                ],
            ],
            [
                'fecha' => today()->subDays(19),
                'observaciones' => 'Lote de rutina.',
                'bocaditos' => [
                    ['producto' => 'Conitos', 'cantidad' => 160, 'empleados' => [['Ana R.', 'Maestro']]],
                    ['producto' => 'Pionono', 'cantidad' => 120, 'empleados' => [['Marta G.', 'Ayudante']]],
                ],
            ],
            [
                'fecha' => today()->subDays(22),
                'observaciones' => null,
                'pan' => [
                    ['producto' => 'Pan Carioca', 'cantidad' => 18, 'turno' => 'Mañana', 'empleados' => [['Luis P.', 'Maestro']]],
                    ['producto' => 'Pan Yema', 'cantidad' => 20, 'turno' => 'Mañana', 'empleados' => [['Ana R.', 'Ayudante']]],
                ],
                'tortas' => [
                    ['producto' => 'Torta de Chocolate', 'forma' => 'circular', 'empleados' => [['Rosa D.', 'Maestro']]],
                ],
            ],
            [
                'fecha' => today()->subDays(26),
                'observaciones' => null,
                'bocaditos' => [
                    ['producto' => 'Alfajorcitos', 'cantidad' => 200, 'empleados' => [['Marta G.', 'Maestro']]],
                ],
            ],
            [
                'fecha' => today()->subDays(29),
                'observaciones' => 'Inicio de la serie del mes.',
                'pan' => [
                    ['producto' => 'Pan Integral', 'cantidad' => 32, 'turno' => 'Mañana', 'empleados' => [['Carlos M.', 'Maestro'], ['Luis P.', 'Ayudante']]],
                ],
                'tortas' => [
                    ['producto' => 'Torta de Vainilla', 'forma' => 'rectangular', 'empleados' => [['Rosa D.', 'Maestro']]],
                ],
            ],
            [
                'fecha' => today()->subDays(33),
                'observaciones' => null,
                'pan' => [
                    ['producto' => 'Pan Francés', 'cantidad' => 24, 'turno' => 'Noche', 'empleados' => [['Carlos M.', 'Maestro']]],
                ],
                'bocaditos' => [
                    ['producto' => 'Conitos', 'cantidad' => 190, 'empleados' => [['Ana R.', 'Ayudante']]],
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
