<?php

namespace App\Consultas;

use App\Models\DetalleBocadito;
use App\Models\DetallePan;
use App\Models\DetalleTorta;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Agregaciones para el dashboard.
 *
 * MODIFICADO: las tarjetas de categoria, las 3 cartas de produccion y los
 * 3 graficos tenian numeros fijos en el HTML/JS (12.5 coches, 15 unidades,
 * 900 unidades, y los datasets [0,0,0] de Chart.js). Todo eso sale de aca.
 *
 * REGLA IMPORTANTE sobre las unidades:
 *   - detalle_torta  NO tiene cantidad: 1 torta = 1 registro. Se cuenta.
 *   - detalle_bocadito tiene cantidad pero NO tiene unidad_medida_id: la
 *     cantidad ya esta expresada en unidades.
 *   - detalle_pan    tiene cantidad Y unidad_medida_id, que pueden diferir
 *     (unidad / lata / coche). Por eso el total de pan se devuelve AGRUPADO
 *     POR UNIDAD: sumar 24 unidad + 3 coche no es una operacion valida.
 */
class ResumenDashboard
{
    /** Las 3 categorias, en el orden en que las muestra la vista. */
    public const CATEGORIAS = ['Pan', 'Torta', 'Bocadito'];

    /** Los 3 modelos de detalle, para consultarlos de forma uniforme. */
    private const MODELOS = [DetallePan::class, DetalleTorta::class, DetalleBocadito::class];

    /**
     * Totales de HOY para las 3 tarjetas de categoria.
     *
     * @return array<string, mixed>  clave = nombre de categoria
     */
    public static function tarjetasDeHoy(): array
    {
        $lineas = LineasProduccion::obtener([
            'conEmpleados' => true,
            'fecha' => today()->format('Y-m-d'),
        ]);

        $tarjetas = [];

        foreach (self::CATEGORIAS as $categoria) {
            $propias = $lineas->where('categoria', $categoria);

            $tarjetas[$categoria] = [
                'lineas' => $propias->count(),

                // Pan: agrupado por unidad, porque cada linea puede estar en
                // una unidad distinta. [['unidad' => 'unidad', 'total' => 84]]
                'porUnidad' => $categoria === 'Pan'
                    ? $propias->whereNotNull('unidad')
                        ->groupBy('unidad')
                        ->map(fn (Collection $grupo) => (float) $grupo->sum('cantidad'))
                        ->map(fn (float $total, string $unidad) => [
                            'unidad' => $unidad,
                            'total' => $total,
                        ])
                        ->values()
                        ->all()
                    : [],

                // Torta: no hay cantidad, cada registro es una torta.
                // Bocadito: la cantidad ya viene en unidades.
                'total' => $categoria === 'Torta'
                    ? $propias->count()
                    : (float) $propias->sum('cantidad'),

                // Texto listo para pintar, respetando las reglas de arriba.
                'texto' => self::textoTotal($categoria, $propias),

                // Lineas de hoy de esta categoria: las usan los 3 modales.
                'lineasDeHoy' => $propias->values(),
            ];
        }

        return $tarjetas;
    }

    /**
     * Las 3 cartas de produccion (Total / Pendiente / Completada).
     *
     * @return array<string, int|string|null>
     */
    public static function cartas(): array
    {
        $lineasHoy = LineasProduccion::obtener(['fecha' => today()->format('Y-m-d')]);

        return [
            // MODIFICADO: era un "0" fijo. Ahora es la cantidad de lineas de
            // produccion registradas hoy.
            'total' => $lineasHoy->count(),

            // SIN FUENTE DE DATOS: no existe ninguna columna de estado en
            // produccion, detalle_pan, detalle_torta ni detalle_bocadito. La
            // unica columna booleana del proyecto es pedidos.entregado, que
            // es de pedidos y no de produccion. Se dejan en null y la vista
            // los muestra como "-": inventar un estado seria mostrar un dato
            // falso. Hay que definir el concepto antes de calcularlo.
            'pendiente' => null,
            'completada' => null,
        ];
    }

    /**
     * Datos de los 3 graficos de Chart.js.
     *
     * La metrica es la CANTIDAD DE LINEAS DE PRODUCCION, no la suma de
     * cantidades: en un mismo grafico conviven pan (que tiene cantidad),
     * tortas (1 por registro) y bocaditos, y sumar 84 panes con 2 tortas no
     // significa nada.
     *
     * @return array{dia: array, semana: array, mes: array}
     */
    public static function graficos(): array
    {
        $hoy = today();

        return [
            'dia' => [
                'categorias' => self::CATEGORIAS,
                'hoy' => self::conteoPorFechaYCategoria($hoy->format('Y-m-d')),
                'ayer' => self::conteoPorFechaYCategoria($hoy->copy()->subDay()->format('Y-m-d')),
            ],

            'semana' => [
                // El mock usa Lun..Sab (6 dias), sin domingo.
                // OJO: startOfWeek()/endOfWeek() MUTAN el Carbon que reciben
                // (Carbon 2), asi que hace falta copy() en cada llamada: sin
                // copy, los dos extremos terminan siendo la misma fecha y el
                // rango queda vacio.
                'labels' => ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'],
                'valores' => self::conteoPorSemana($hoy->copy()->startOfWeek(), $hoy->copy()->endOfWeek()),
            ],

            'mes' => [
                // El mock usa Sem 1..4. Mismo cuidado con copy().
                'labels' => ['Sem 1', 'Sem 2', 'Sem 3', 'Sem 4'],
                'valores' => self::conteoPorSemanaDelMes($hoy->copy()->startOfMonth(), $hoy->copy()->endOfMonth()),
            ],
        ];
    }

    /**
     * Conteo de lineas por categoria para UNA fecha.
     *
     * @return array<string, int>  ['Pan' => 2, 'Torta' => 0, ...]
     */
    private static function conteoPorFechaYCategoria(string $fecha): array
    {
        $conteo = array_fill_keys(self::CATEGORIAS, 0);

        foreach (self::MODELOS as $modelo) {
            $modelo = new $modelo;
            $tabla = $modelo->getTable();

            $filas = $modelo->newQuery()
                ->join('produccion', "{$tabla}.produccion_id", '=', 'produccion.id')
                ->join('productos', "{$tabla}.producto_id", '=', 'productos.id')
                ->join('categorias', 'productos.categoria_id', '=', 'categorias.id')
                ->whereDate('produccion.fecha', $fecha)
                ->groupBy('categorias.nombre_categorias')
                ->selectRaw('categorias.nombre_categorias as categoria, COUNT(*) as total')
                ->get();

            foreach ($filas as $fila) {
                $conteo[$fila->categoria] = ($conteo[$fila->categoria] ?? 0) + (int) $fila->total;
            }
        }

        return $conteo;
    }

    /**
     * Cuenta de lineas por dia de la semana (Lun=0 ... Dom=6) en un rango.
     * El domingo se descarta porque la vista solo muestra Lun..Sab.
     *
     * @return array<int, int>
     */
    private static function conteoPorSemana(Carbon $desde, Carbon $hasta): array
    {
        $valores = array_fill(0, 7, 0);

        foreach (self::conteoPorFecha($desde, $hasta) as $fecha => $total) {
            $dia = Carbon::parse($fecha)->dayOfWeekIso; // 1 = lunes ... 7 = domingo
            $valores[$dia - 1] += $total;
        }

        // quita el domingo (ultima posicion)
        array_pop($valores);

        return $valores;
    }

    /**
     * Cuenta de lineas por semana del mes (1..4), en un rango.
     *
     * @return array<int, int>
     */
    private static function conteoPorSemanaDelMes(Carbon $desde, Carbon $hasta): array
    {
        $valores = array_fill(1, 4, 0);

        foreach (self::conteoPorFecha($desde, $hasta) as $fecha => $total) {
            $semana = (int) ceil(Carbon::parse($fecha)->day / 7); // 1..5
            if ($semana >= 1 && $semana <= 4) {
                $valores[$semana] += $total;
            }
        }

        ksort($valores);

        return array_values($valores);
    }

    /**
     * Conteo de lineas por fecha, sumando las 3 tablas de detalle.
     * Se hacian 3 consultas (una por tipo) y se fusionan en PHP.
     *
     * @return array<string, int>  ['2026-09-26' => 3, ...]
     */
    private static function conteoPorFecha(Carbon $desde, Carbon $hasta): array
    {
        $conteo = [];

        foreach (self::MODELOS as $modelo) {
            $modelo = new $modelo;
            $tabla = $modelo->getTable();

            $filas = $modelo->newQuery()
                ->join('produccion', "{$tabla}.produccion_id", '=', 'produccion.id')
                ->whereBetween('produccion.fecha', [$desde->format('Y-m-d'), $hasta->format('Y-m-d')])
                ->groupBy('produccion.fecha')
                ->selectRaw('produccion.fecha as fecha, COUNT(*) as total')
                ->get();

            foreach ($filas as $fila) {
                $clave = Carbon::parse($fila->fecha)->format('Y-m-d');
                $conteo[$clave] = ($conteo[$clave] ?? 0) + (int) $fila->total;
            }
        }

        return $conteo;
    }

    /**
     * Arma el texto del total de una tarjeta respetando las reglas de unidad.
     */
    private static function textoTotal(string $categoria, Collection $lineas): string
    {
        if ($categoria === 'Torta') {
            $n = $lineas->count();

            return $n . ' ' . ($n === 1 ? 'torta' : 'tortas');
        }

        if ($categoria === 'Bocadito') {
            return self::numero((float) $lineas->sum('cantidad')) . ' unidades';
        }

        // Pan: puede mezclar unidades, asi que se lista cada una.
        $porUnidad = $lineas->whereNotNull('unidad')
            ->groupBy('unidad')
            ->map(fn (Collection $grupo) => self::numero((float) $grupo->sum('cantidad')) . ' ' . $grupo->first()->unidad);

        // Sin lineas no se puede saber cual es la unidad, asi que no se
        // inventa una: se muestra solo el 0.
        return $porUnidad->isEmpty()
            ? '0'
            : $porUnidad->implode(' + ');
    }

    /**
     * Numero sin decimales in utiles: 24.00 -> "24", 12.50 -> "12,5".
     */
    private static function numero(float $n): string
    {
        $texto = rtrim(rtrim(number_format($n, 2, ',', '.'), '0'), ',');

        return $texto === '' ? '0' : $texto;
    }
}
