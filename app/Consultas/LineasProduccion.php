<?php

namespace App\Consultas;

use App\Models\DetalleBocadito;
use App\Models\DetalleBocaditoEmpleado;
use App\Models\DetallePan;
use App\Models\DetallePanEmpleado;
use App\Models\DetalleTorta;
use App\Models\DetalleTortaEmpleado;
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
     * DRY: configuracion de las 3 categorias en un solo lugar.
     *
     * MODIFICADO: antes existian los metodos dePan(), deTorta() y deBocadito(),
     * que repetian los mismos 30 campos cambiando solo un par de valores. Con
     * esta tabla el mapa de cada categoria sale de aca: agregar una categoria
     * nueva es agregar una fila, no copiar un metodo.
     *
     * El orden de las claves importa: obtener() los recorre en este orden y
     * sortByDesc es estable, asi que dentro de una misma fecha las lineas
     * quedan en el orden pan -> torta -> bocadito.
     */
    private const CATEGORIAS = [
        'pan' => [
            'modelo' => DetallePan::class,
            'eager' => ['producto.categoria', 'unidadMedida', 'turno', 'produccion.usuario'],
            'pivote' => [DetallePanEmpleado::class, 'detalle_pan_id'],
        ],
        'torta' => [
            'modelo' => DetalleTorta::class,
            // OJO: DetalleTorta no tiene relacion unidadMedida aunque la tabla
            // si tiene la columna (agregada por la migracion
            // 2026_09_25_230000). No se pide a proposito: una torta se muestra
            // como "1 torta", no con cantidad, asi que la unidad no hace falta.
            'eager' => ['producto.categoria', 'produccion.usuario'],
            'pivote' => [DetalleTortaEmpleado::class, 'detalle_torta_id'],
        ],
        'bocadito' => [
            'modelo' => DetalleBocadito::class,
            'eager' => ['producto.categoria', 'produccion.usuario'],
            'pivote' => [DetalleBocaditoEmpleado::class, 'detalle_bocadito_id'],
        ],
    ];

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

        // Recorre self::CATEGORIAS en el orden declarado (pan, torta, bocadito).
        $lineas = collect();
        foreach (self::CATEGORIAS as $categoria => $config) {
            $lineas = $lineas->merge(self::deCategoria($categoria, $config, $empleados, $filtroFecha));
        }

        // sortByDesc es estable: mantiene el orden pan -> torta -> bocadito
        // dentro de una misma fecha.
        $lineas = $lineas->sortByDesc('fecha')->values();

        $limite = $opciones['limite'] ?? null;

        return $limite ? $lineas->take($limite) : $lineas;
    }

    /**
     * DRY: una sola consulta + un solo mapeo para las 3 categorias.
     *
     * MODIFICADO: reemplaza dePan()/deTorta()/deBocadito(), que tenian el
     * bloque de mapeo duplicado 3 veces.
     */
    private static function deCategoria(
        string $categoria,
        array $config,
        array $empleados,
        callable $filtroFecha
    ): Collection {
        return $filtroFecha($config['modelo']::with($config['eager']))
            ->get()
            ->map(fn ($d) => self::linea($d, $categoria, $empleados));
    }

    /**
     * DRY: el mapa comun de una linea. Las 13 claves son SIEMPRE las mismas,
     * solo cambia su valor segun la categoria: por eso la vista puede usar un
     * unico @forelse sin preguntar por el tipo.
     */
    private static function linea(mixed $d, string $categoria, array $empleados): object
    {
        $esPan = $categoria === 'pan';
        $esTorta = $categoria === 'torta';

        return (object) [
            'tipo' => $categoria,
            'detalle_id' => $d->id,
            'fecha' => self::fecha($d->produccion?->fecha),
            'producto' => $d->producto?->nombre_p,
            'categoria' => $d->producto?->categoria?->nombre_categorias,

            // sin cantidad a proposito en torta: una torta es un registro.
            // en bocadito la cantidad ya viene en unidades.
            'cantidad' => $esTorta
                ? null
                : ($d->cantidad !== null ? (float) $d->cantidad : null),

            // la unidad y el turno solo existen en detalle_pan; la forma y la
            // foto solo en detalle_torta. En el resto se devuelven en null
            // para que la vista no tenga que preguntar por el tipo.
            'unidad' => $esPan ? $d->unidadMedida?->nombre_unidades_medida : null,
            'turno' => $esPan ? $d->turno?->nombre_turnos : null,
            'forma' => $esTorta ? $d->forma : null,
            'foto' => $esTorta ? $d->foto : null,

            'usuario' => $d->produccion?->usuario?->username,
            'observaciones' => $d->produccion?->observaciones,
            'empleados' => $empleados[$categoria][$d->id] ?? collect(),
        ];
    }

    /**
     * Devuelve [tipo][detalle_id] => Collection de {nombre, rol}.
     */
    private static function empleadosPorDetalle(): array
    {
        $resultado = [];

        // DRY: antes eran 3 bloques identicos que solo cambiaban la clase del
        // pivote y el nombre de la columna FK. Ahora sale de self::CATEGORIAS.
        foreach (self::CATEGORIAS as $categoria => $config) {
            [$modeloPivote, $columnaDetalle] = $config['pivote'];

            $resultado[$categoria] = $modeloPivote::with(['empleado', 'rolProduccion'])
                ->get()
                ->groupBy($columnaDetalle)
                ->map(fn ($grupo) => $grupo
                    ->map(fn ($p) => (object) [
                        'nombre' => $p->empleado?->nombre_empleados,
                        'rol' => $p->rolProduccion?->nombre_roles_produccion,
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
