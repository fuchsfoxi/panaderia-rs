<?php

namespace App\Consultas;

use App\Models\DetalleBocadito;
use App\Models\DetallePan;
use App\Models\DetalleTorta;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Normaliza en UNA sola coleccion las lineas de detalle de las tres categorias
 * de produccion (pan, torta, bocadito).
 *
 * Por que existe: historico y produccion muestran el mismo tipo de dato
 * (una linea = un producto producido en una fecha), pero en tablas distintas.
 * Sin esto, cada vista tendria que repetir 3 consultas y 3 bloques de mapeo.
 *
 * Cada linea devuelta es un objeto con SIEMPRE las mismas claves, para que la
 * vista pueda usar un unico @forelse y no tenga que preguntar por el tipo:
 *
 *   tipo          'pan' | 'torta' | 'bocadito'
 *   detalle_id    id de la fila en su tabla de detalle
 *   fecha         'Y-m-d' (string, para agrupar en la vista)
 *   producto      nombre del producto
 *   categoria     nombre de la categoria (Pan / Torta / Bocadito)
 *   cantidad      float o null  (null en torta: 1 torta = 1 registro)
 *   unidad        string o null (nombre de la unidad de medida)
 *   turno         string o null (solo pan)
 *   forma         string o null (solo torta)
 *   foto          string o null (solo torta)
 *   usuario       username de quien registro la produccion
 *   observaciones nota de la produccion
 *   empleados     Collection de {nombre, rol} (vacio si no se pidio)
 */
class LineasProduccion
{
    /**
     * @param  array{conEmpleados?: bool, limite?: int|null, fecha?: string|null}  $opciones
     *   conEmpleados  carga los empleados de cada linea (join al pivote)
     *   limite        corta el resultado a N lineas (para "registrado recientemente")
     *   fecha         filtra por un dia puntual, formato 'Y-m-d'
     */
    public static function obtener(array $opciones = []): Collection
    {
        $conEmpleados = $opciones['conEmpleados'] ?? false;
        $fecha = $opciones['fecha'] ?? null;

        // Consulta los pivotes de empleados una sola vez por tipo y los indexa
        // por detalle_id. Se hace aparte (y no con with()) porque los modelos
        // de detalle todavia no tienen relacion hacia su tabla pivote.
        $empleados = $conEmpleados ? self::empleadosPorDetalle() : [];

        $filtroFecha = $fecha
            ? fn ($q) => $q->whereHas('produccion', fn ($p) => $p->whereDate('fecha', $fecha))
            : fn ($q) => $q;

        $lineas = self::dePan($empleados, $filtroFecha)
            ->merge(self::deTorta($empleados, $filtroFecha))
            ->merge(self::deBocadito($empleados, $filtroFecha));

        // sortByDesc es estable: mantiene el orden pan -> torta -> bocadito
        // dentro de una misma fecha.
        $lineas = $lineas->sortByDesc('fecha')->values();

        $limite = $opciones['limite'] ?? null;

        return $limite ? $lineas->take($limite) : $lineas;
    }

    private static function dePan(array $empleados, callable $filtroFecha): Collection
    {
        return $filtroFecha(DetallePan::with(['producto.categoria', 'unidadMedida', 'turno', 'produccion.usuario']))
            ->get()
            ->map(fn (DetallePan $d) => (object) [
                'tipo' => 'pan',
                'detalle_id' => $d->id,
                'fecha' => self::fecha($d->produccion?->fecha),
                'producto' => $d->producto?->nombre_p,
                'categoria' => $d->producto?->categoria?->nombre_categorias,
                'cantidad' => $d->cantidad !== null ? (float) $d->cantidad : null,
                'unidad' => $d->unidadMedida?->nombre_unidades_medida,
                'turno' => $d->turno?->nombre_turnos,
                'forma' => null,
                'foto' => null,
                'usuario' => $d->produccion?->usuario?->username,
                'observaciones' => $d->produccion?->observaciones,
                'empleados' => $empleados['pan'][$d->id] ?? collect(),
            ]);
    }

    private static function deTorta(array $empleados, callable $filtroFecha): Collection
    {
        // OJO: DetalleTorta no tiene relacion unidadMedida aunque la tabla si
        // tiene la columna (agregada por la migracion 2026_09_25_230000). Por
        // eso no se pide unidad: una torta se muestra como "1 torta", no con
        // cantidad, asi que no hace falta para la vista.
        return $filtroFecha(DetalleTorta::with(['producto.categoria', 'produccion.usuario']))
            ->get()
            ->map(fn (DetalleTorta $d) => (object) [
                'tipo' => 'torta',
                'detalle_id' => $d->id,
                'fecha' => self::fecha($d->produccion?->fecha),
                'producto' => $d->producto?->nombre_p,
                'categoria' => $d->producto?->categoria?->nombre_categorias,
                // sin cantidad a proposito: una torta es un registro
                'cantidad' => null,
                'unidad' => null,
                'turno' => null,
                'forma' => $d->forma,
                'foto' => $d->foto,
                'usuario' => $d->produccion?->usuario?->username,
                'observaciones' => $d->produccion?->observaciones,
                'empleados' => $empleados['torta'][$d->id] ?? collect(),
            ]);
    }

    private static function deBocadito(array $empleados, callable $filtroFecha): Collection
    {
        return $filtroFecha(DetalleBocadito::with(['producto.categoria', 'produccion.usuario']))
            ->get()
            ->map(fn (DetalleBocadito $d) => (object) [
                'tipo' => 'bocadito',
                'detalle_id' => $d->id,
                'fecha' => self::fecha($d->produccion?->fecha),
                'producto' => $d->producto?->nombre_p,
                'categoria' => $d->producto?->categoria?->nombre_categorias,
                'cantidad' => $d->cantidad !== null ? (float) $d->cantidad : null,
                'unidad' => null,
                'turno' => null,
                'forma' => null,
                'foto' => null,
                'usuario' => $d->produccion?->usuario?->username,
                'observaciones' => $d->produccion?->observaciones,
                'empleados' => $empleados['bocadito'][$d->id] ?? collect(),
            ]);
    }

    /**
     * Devuelve [tipo][detalle_id] => Collection de {nombre, rol}.
     */
    private static function empleadosPorDetalle(): array
    {
        $pivotes = [
            'pan' => \App\Models\DetallePanEmpleado::with(['empleado', 'rolProduccion'])
                ->get()->map(fn ($p) => $p->toArray() + [
                    'detalle_id' => $p->detalle_pan_id,
                    'nombre' => $p->empleado?->nombre_empleados,
                    'rol' => $p->rolProduccion?->nombre_roles_produccion,
                ]),
            'torta' => \App\Models\DetalleTortaEmpleado::with(['empleado', 'rolProduccion'])
                ->get()->map(fn ($p) => $p->toArray() + [
                    'detalle_id' => $p->detalle_torta_id,
                    'nombre' => $p->empleado?->nombre_empleados,
                    'rol' => $p->rolProduccion?->nombre_roles_produccion,
                ]),
            'bocadito' => \App\Models\DetalleBocaditoEmpleado::with(['empleado', 'rolProduccion'])
                ->get()->map(fn ($p) => $p->toArray() + [
                    'detalle_id' => $p->detalle_bocadito_id,
                    'nombre' => $p->empleado?->nombre_empleados,
                    'rol' => $p->rolProduccion?->nombre_roles_produccion,
                ]),
        ];

        $resultado = [];

        foreach ($pivotes as $tipo => $pivote) {
            $resultado[$tipo] = $pivote
                ->groupBy('detalle_id')
                ->map(fn ($grupo) => $grupo
                    ->map(fn ($p) => (object) [
                        'nombre' => $p['nombre'],
                        'rol' => $p['rol'],
                    ])
                    ->values()
                )
                ->all();
        }

        return $resultado;
    }

    /**
     * La columna 'fecha' es date y sin $casts vuelve como string.
     */
    private static function fecha(mixed $fecha): ?string
    {
        return $fecha === null ? null : Carbon::parse($fecha)->format('Y-m-d');
    }
}
