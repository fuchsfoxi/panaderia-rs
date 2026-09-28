<?php

namespace App\Http\Controllers;

use App\Consultas\LineasProduccion;
use App\Models\Turno;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class HistorialController extends Controller
{
    /**
     * Muestra TODOS los registros de produccion, de las tres categorias
     * (pan, torta, bocadito) mezclados y ordenados del mas reciente al mas
     * antiguo.
     *
     * Antes estas lineas estaban escritas a mano en el HTML como mocks.
     * Ahora salen de la base: LineasProduccion las normaliza en una sola
     * coleccion para que la vista use un unico @forelse.
     *
     * FILTROS: se reciben por parametros GET normales (?categoria=pan&desde=...)
     * y NO por AJAX. Motivos:
     *   - funciona sin JavaScript y se puede compartir por enlace,
     *   - el boton "atras" del celular vuelve al filtro anterior en vez de
     *     perderlo,
     *   - la consulta es chica (unas decenas de lineas) y filtrar en PHP es
     *     mas simple y mas seguro que cambiar LineasProduccion, que ya
     *     funciona y esta verificado.
     * El costo es que se traen todas las lineas y se descartan en memoria;
     * con el volumen de una panaderia es aceptable y, si algun dia molesta,
     * el filtro se baja a la consulta sin cambiar la vista.
     */
    public function index(Request $request)
    {
        $filtros = $this->filtrosValidados($request);

        $registros = $this->aplicarFiltros(
            LineasProduccion::obtener(),
            $filtros
        );

        return view('history.index', [
            'registros' => $registros,
            // total de lineas DESPUES de filtrar, para el texto "N registros
            // encontrados"
            'totalRegistros' => $registros->count(),

            // Para el formulario de filtros.
            'turnos' => Turno::orderBy('id')->get(),
            'filtros' => $filtros,
            // true cuando se aplico algun filtro: la vista avisa si no hay
            // resultados y ofrece limpiar, en vez de decir "todavia no hay
            // produccion" cuando en realidad el filtro no arrojo nada.
            'hayFiltros' => $this->hayFiltros($filtros),
        ]);
    }

    /**
     * Lee y sanea los filtros del request.
     *
     * @return array{categoria: ?string, desde: ?string, hasta: ?string, turno_id: ?int, error: ?string}
     */
    private function filtrosValidados(Request $request): array
    {
        // Se acepta solo 'pan', 'torta' o 'bocadito': cualquier otra cosa se
        // ignora en vez de quedar metida en la URL.
        $categoria = in_array($request->query('categoria'), ['pan', 'torta', 'bocadito'], true)
            ? $request->query('categoria')
            : null;

        $desde = $this->fechaValida($request->query('desde'));
        $hasta = $this->fechaValida($request->query('hasta'));

        $turnoId = $request->query('turno_id');
        $turnoId = is_numeric($turnoId) && (int) $turnoId > 0 ? (int) $turnoId : null;

        $error = null;

        if ($desde && $hasta && $desde > $hasta) {
            // Rango al reves: se avisa en pantalla y se ignoran las dos fechas
            // en vez de devolver una lista vacia sin explicar por que.
            $error = 'La fecha "Desde" es posterior a la fecha "Hasta". Revisar el rango.';
            $desde = null;
            $hasta = null;
        }

        return [
            'categoria' => $categoria,
            'desde' => $desde,
            'hasta' => $hasta,
            'turno_id' => $turnoId,
            'error' => $error,
        ];
    }

    /**
     * Devuelve la fecha en formato Y-m-d o null si no es una fecha real.
     */
    private function fechaValida(mixed $valor): ?string
    {
        if (! is_string($valor) || $valor === '') {
            return null;
        }

        try {
            return Carbon::parse($valor)->format('Y-m-d');
        } catch (\Throwable) {
            // Si no es una fecha valida se ignora: el filtro no debe romper
            // la pagina con una exception.
            return null;
        }
    }

    /**
     * Aplica los filtros sobre la coleccion de lineas.
     *
     * Cada linea de LineasProduccion tiene siempre las mismas claves
     * (tipo, fecha, turno, ...), asi que se filtran sin preguntar por la
     * tabla de la que viene.
     */
    private function aplicarFiltros(Collection $lineas, array $filtros): Collection
    {
        return $lineas
            // 'tipo' es pan / torta / bocadito (en minuscula); la categoria que
            // viaja en la URL tambien lo es.
            ->when($filtros['categoria'], fn ($c) => $c->where('tipo', $filtros['categoria']))

            // 'fecha' ya viene como string Y-m-d, comparable como texto.
            ->when($filtros['desde'], fn ($c) => $c->filter(fn ($l) => $l->fecha >= $filtros['desde']))
            ->when($filtros['hasta'], fn ($c) => $c->filter(fn ($l) => $l->fecha <= $filtros['hasta']))

            // El turno solo existe en las lineas de PAN (es la unica tabla de
            // detalle con columna turno_id), asi que las de torta y bocadito
            // quedan fuera cuando se filtra por turno. El nombre del turno se
            // busca en la lista de la base: la linea ya trae el nombre.
            ->when($filtros['turno_id'], function ($c) use ($filtros) {
                $nombre = Turno::where('id', $filtros['turno_id'])->value('nombre_turnos');

                return $nombre
                    ? $c->filter(fn ($l) => $l->turno === $nombre)
                    : $c->filter(fn () => false);
            })
            ->values();
    }

    private function hayFiltros(array $filtros): bool
    {
        return $filtros['categoria'] !== null
            || $filtros['desde'] !== null
            || $filtros['hasta'] !== null
            || $filtros['turno_id'] !== null;
    }
}
