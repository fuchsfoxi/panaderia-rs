<?php

namespace App\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use OverflowException;

class ConsultarDashboard
{
    /** Los cálculos fijos nunca dependen del filtro histórico de la pantalla. */
    public function ejecutar(CarbonImmutable $hoy): array
    {
        $hoy = $hoy->setTimezone('America/Lima')->startOfDay();
        $mes = $hoy->startOfMonth();
        $finMes = $hoy->endOfMonth();
        $semana = $hoy->subDays(6);
        $desde = $mes->lessThan($semana) ? $mes : $semana;

        $union = null;
        foreach (['pan' => 'detalle_pan', 'torta' => 'detalle_torta', 'bocadito' => 'detalle_bocadito'] as $familia => $tabla) {
            $consulta = $this->base($tabla, $familia)
                ->join('productos as producto', 'producto.id', '=', 'detalle.producto_id')
                ->whereBetween('produccion.fecha', [$desde->toDateString(), $finMes->toDateString()])
                ->select('produccion.fecha', 'producto.id as producto_id', 'producto.nombre_p')
                ->selectRaw('? as familia, COUNT(*) as registros', [$familia]);
            if ($familia === 'pan') {
                $consulta->leftJoin('unidades_medida as unidad', 'unidad.id', '=', 'detalle.unidad_medida_id')
                    ->selectRaw("SUM(CASE WHEN unidad.codigo = 'LATA' THEN CAST(detalle.cantidad AS DECIMAL(30,0)) ELSE 0 END) as latas")
                    ->selectRaw("SUM(CASE WHEN unidad.codigo = 'UNIDAD' THEN CAST(detalle.cantidad AS DECIMAL(30,0)) WHEN unidad.codigo = 'LATA' AND detalle.panes_por_lata_usado > 0 THEN CAST(detalle.cantidad AS DECIMAL(30,0)) * detalle.panes_por_lata_usado ELSE 0 END) as unidades")
                    ->selectRaw("SUM(CASE WHEN unidad.codigo = 'LATA' AND (detalle.panes_por_lata_usado IS NULL OR detalle.panes_por_lata_usado <= 0) THEN 1 ELSE 0 END) as sin_factor")
                    ->selectRaw("SUM(CASE WHEN unidad.codigo IS NULL OR unidad.codigo NOT IN ('LATA', 'UNIDAD') THEN 1 ELSE 0 END) as unidad_desconocida");
            } else {
                $consulta->selectRaw('0 as latas, SUM(CAST(detalle.cantidad AS DECIMAL(30,0))) as unidades, 0 as sin_factor, 0 as unidad_desconocida');
            }
            $consulta->groupBy('produccion.fecha', 'producto.id', 'producto.nombre_p');
            $union = $union ? $union->unionAll($consulta) : $consulta;
        }
        $filas = $union->get();
        $diario = ['pan' => ['latas' => 0, 'unidades' => 0, 'unidades_completas' => true, 'latas_completas' => true], 'torta' => 0, 'bocadito' => 0, 'registros' => 0];
        $productos = ['pan' => [], 'torta' => [], 'bocadito' => []];
        $panUnidadesCompleto = true;
        $panLatasCompleto = true;
        $conteos = [];
        foreach ($filas as $fila) {
            $familia = $fila->familia;
            $fecha = (string) $fila->fecha;
            $registros = $this->entero($fila->registros);
            $latas = $this->entero($fila->latas);
            $unidades = $this->entero($fila->unidades);
            $incompleto = (int) $fila->sin_factor > 0 || (int) $fila->unidad_desconocida > 0;
            $desconocido = (int) $fila->unidad_desconocida > 0;
            $conteos[$fecha][$familia] = $this->sumar($conteos[$fecha][$familia] ?? 0, $registros);
            if ($fecha === $hoy->toDateString()) {
                $diario['registros'] = $this->sumar($diario['registros'], $registros);
                if ($familia === 'pan') {
                    $diario['pan']['latas'] = $this->sumar($diario['pan']['latas'], $latas);
                    $diario['pan']['unidades'] = $this->sumar($diario['pan']['unidades'], $unidades);
                    $diario['pan']['unidades_completas'] = $diario['pan']['unidades_completas'] && ! $incompleto;
                    $diario['pan']['latas_completas'] = $diario['pan']['latas_completas'] && ! $desconocido;
                } else {
                    $diario[$familia] = $this->sumar($diario[$familia], $unidades);
                }
            }
            if ($fecha < $mes->toDateString()) {
                continue;
            }
            $id = (int) $fila->producto_id;
            $productos[$familia][$id] ??= ['producto_id' => $id, 'producto' => $fila->nombre_p, 'latas' => 0, 'unidades' => 0];
            $productos[$familia][$id]['latas'] = $this->sumar($productos[$familia][$id]['latas'], $latas);
            $productos[$familia][$id]['unidades'] = $this->sumar($productos[$familia][$id]['unidades'], $unidades);
            if ($familia === 'pan') {
                $panUnidadesCompleto = $panUnidadesCompleto && ! $incompleto;
                $panLatasCompleto = $panLatasCompleto && ! $desconocido;
            }
        }

        return [
            'hoy' => $diario,
            'mensual' => [
                'pan_latas' => ['completo' => $panLatasCompleto, 'productos' => $panLatasCompleto ? $this->ranking($productos['pan'], 'latas') : []],
                'pan_unidades' => ['completo' => $panUnidadesCompleto, 'productos' => $panUnidadesCompleto ? $this->ranking($productos['pan'], 'unidades') : []],
                'torta' => $this->ranking($productos['torta'], 'unidades'),
                'bocadito' => $this->ranking($productos['bocadito'], 'unidades'),
            ],
            'series' => ['siete_dias' => $this->serie($semana, $hoy, $conteos), 'mes_actual' => $this->serie($mes, $finMes, $conteos)],
            'actividad' => $this->actividad(),
        ];
    }

    private function base(string $tabla, string $familia): Builder
    {
        return DB::table($tabla.' as detalle')
            ->join('produccion', 'produccion.id', '=', 'detalle.produccion_id')
            ->join('categorias', 'categorias.id', '=', 'produccion.categoria_id')
            ->where('categorias.nombre_categorias', ucfirst($familia));
    }

    private function actividad(): array
    {
        $union = null;
        foreach (['pan' => 'detalle_pan', 'torta' => 'detalle_torta', 'bocadito' => 'detalle_bocadito'] as $familia => $tabla) {
            $hora = match ($familia) {
                'pan' => 'detalle.hora_finalizacion', 'torta' => 'detalle.hora_vitrina', default => 'NULL'
            };
            $consulta = $this->base($tabla, $familia)
                ->leftJoin('productos as producto', 'producto.id', '=', 'detalle.producto_id')
                ->leftJoin('turnos as turno', 'turno.id', '=', 'produccion.turno_id')
                ->select('detalle.id', 'produccion.fecha', 'producto.nombre_p as producto', 'turno.nombre_turnos as turno')
                ->selectRaw('? as categoria, '.$hora.' as hora', [$familia]);
            $union = $union ? $union->unionAll($consulta) : $consulta;
        }

        // Fecha, familia alfabética e ID descendente: no representa hora de creación.
        return DB::query()->fromSub($union, 'actividad')->orderByDesc('fecha')->orderBy('categoria')->orderByDesc('id')->limit(5)->get()
            ->map(fn ($fila) => [
                'id' => (int) $fila->id, 'producto' => $fila->producto ?? 'Producto no disponible',
                'categoria' => $fila->categoria, 'fecha' => $fila->fecha,
                'turno' => $fila->turno ?? 'Sin turno registrado',
                'hora' => $fila->hora === null ? null : substr($fila->hora, 0, 5),
                'tipo_hora' => $fila->hora === null ? null : ($fila->categoria === 'pan' ? 'finalizacion' : 'vitrina'),
                'hora_etiqueta' => $fila->hora === null ? 'Sin hora registrada' : ($fila->categoria === 'pan' ? 'Hora de finalización' : 'Hora de vitrina'),
            ])->all();
    }

    private function serie(CarbonImmutable $desde, CarbonImmutable $hasta, array $conteos): array
    {
        $resultado = [];
        for ($dia = $desde; $dia->lessThanOrEqualTo($hasta); $dia = $dia->addDay()) {
            $fecha = $dia->toDateString();
            $resultado[] = ['fecha' => $fecha, 'pan' => $conteos[$fecha]['pan'] ?? 0, 'torta' => $conteos[$fecha]['torta'] ?? 0, 'bocadito' => $conteos[$fecha]['bocadito'] ?? 0];
        }

        return $resultado;
    }

    private function ranking(array $productos, string $magnitud): array
    {
        $filas = array_values(array_filter($productos, fn ($producto) => $producto[$magnitud] > 0));
        usort($filas, fn ($a, $b) => ($b[$magnitud] <=> $a[$magnitud]) ?: ($a['producto_id'] <=> $b['producto_id']));

        return array_map(fn ($producto) => ['producto_id' => $producto['producto_id'], 'producto' => $producto['producto'], 'cantidad' => $producto[$magnitud]], array_slice($filas, 0, 3));
    }

    private function entero(mixed $valor): int
    {
        $texto = (string) $valor;
        $normalizado = ltrim($texto, '0') ?: '0';
        $limite = (string) PHP_INT_MAX;
        if (! ctype_digit($texto) || strlen($normalizado) > strlen($limite)
            || (strlen($normalizado) === strlen($limite) && strcmp($normalizado, $limite) > 0)) {
            throw new OverflowException('El total del Dashboard supera la capacidad entera disponible.');
        }

        return (int) $texto;
    }

    private function sumar(int $a, int $b): int
    {
        if ($b > PHP_INT_MAX - $a) {
            throw new OverflowException('El total del Dashboard supera la capacidad entera disponible.');
        }

        return $a + $b;
    }
}
