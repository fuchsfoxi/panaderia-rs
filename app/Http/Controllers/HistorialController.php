<?php

namespace App\Http\Controllers;

use App\Consultas\LineasProduccion;
use App\Models\Turno;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * ============================================================================
 * HISTORIAL DE PRODUCCION
 * ============================================================================
 *
 * Muestra todas las líneas de producción de las tres categorías (pan, torta,
 * bocadito) mezcladas, de la más reciente a la más antigua, con filtros de
 * categoría, rango de fechas y turno.
 *
 * ---------------------------------------------------------------------------
 * LA IDEA QUE SOSTIENE TODO ESTE ARCHIVO
 * ---------------------------------------------------------------------------
 *
 * En la base hay TRES tablas de detalle distintas:
 *
 *     detalle_pan        (producto, unidad_medida, turno, cantidad)
 *     detalle_torta      (producto, unidad_medida, forma, foto)
 *     detalle_bocadito   (producto, cantidad)
 *
 * Y cada una tiene su propia tabla pivote de empleados
 * (detalle_pan_empleado, detalle_torta_empleado, detalle_bocadito_empleado).
 *
 * Sin nada que las unifique, esta pantalla tendría que hacer tres consultas y
 * tres bloques de mapeo, y la vista tres @forelse distintos.
 *
 * Lo que hace App\Consultas\LineasProduccion (que YA existía antes de este
 * trabajo y no se modificó) es recorrer las tres y devolver UNA sola colección
 * donde cada elemento tiene SIEMPRE las mismas claves:
 *
 *     tipo, detalle_id, fecha, producto, categoria, cantidad, unidad,
 *     turno, forma, foto, usuario, observaciones, empleados
 *
 * Por eso la vista usa un único @forelse y nunca pregunta "¿esto venía de la
 * tabla de tortas?".
 *
 * Esa normalización es la que hace que los filtros de abajo sean tan simples:
 * como todas las líneas tienen las mismas claves, el filtro es el mismo código
 * para las tres categorías.
 *
 * ---------------------------------------------------------------------------
 * REGLAS DE UNIDADES, QUE SON LA PARTE DELICADA DEL MODELO
 * ---------------------------------------------------------------------------
 *
 * Estas reglas explican por qué el filtro por turno no siempre tiene sentido,
 * están implementadas en LineasProduccion:
 *
 *   - detalle_torta NO tiene columna 'cantidad': 1 registro = 1 torta.
 *     Por eso 'cantidad' llega en null y la vista muestra "1 torta".
 *
 *   - detalle_bocadito tiene 'cantidad' pero NO tiene 'unidad_medida_id':
 *     la cantidad ya está expresada en unidades.
 *
 *   - detalle_pan tiene 'cantidad' Y 'unidad_medida_id', que pueden ser
 *     distintos (unidad / lata / coche). Por eso sumar 24 unidad + 3 coche
 *     NO es una operación válida, y el dashboard agrupa el pan por unidad
 *     en vez de sumarlo todo junto.
 *
 *   - El TURNO solo existe en detalle_pan. Es la única de las tres tablas con
 *     columna 'turno_id'. Por eso el campo de turno en la pantalla de filtros
 *     solo se muestra cuando la categoría es pan, y por eso una línea de
 *     torta trae 'turno' en null.
 */
class HistorialController extends Controller
{
    /**
     * Pantalla de historial con filtros.
     *
     * ---------------------------------------------------------------------------
     * POR QUÉ FILTROS CON GET Y NO CON AJAX
     * ---------------------------------------------------------------------------
     *
     * Los botones "Filtrar" y "Limpiar" reciben la categoría, las fechas y el
     * turno por la URL:
     *
     *     /history?categoria=pan&desde=2026-09-01&hasta=2026-09-28&turno_id=1
     *
     * Se eligió GET, y no una llamada fetch() con JavaScript, por cuatro
     * razones concretas:
     *
     *  1. Funciona sin JavaScript. Un <form> es un <form>. Con fetch, si el
     *     script falla o el navegador lo bloquea, el usuario queda mirando una
     *     pantalla vacía sin explicación.
     *
     *  2. El botón "atrás" del celular conserva el filtro. Con AJAX la URL
     *     nunca cambia, así que "atrás" vuelve a la página anterior de la
     *     navegación y no a la vista sin filtro. Esa es la diferencia entre
     *     una página y una aplicación.
     *
     *  3. El filtro se puede compartir por enlace: "mirá estos datos del
     *     lunes" es una URL.
     *
     *  4. Se puede verificar con curl, sin navegador. Eso fue justamente lo
     *     que permitió comprobar los filtros durante el desarrollo.
     *
     * EL COSTO, DICHO ABIERTO: se traen TODAS las líneas y se descartan las
     * que no cumplen el filtro, en memoria. Con el volumen de una panadería
     * (decenas de líneas por semana) es perfectamente aceptable. Si algún día
     * molesta, el filtro se baja a la consulta SQL y la vista no cambia,
     * porque ya recibe una colección filtrada.
     */
    public function index(Request $request)
    {
        // Paso 1: leer y limpiar lo que vino por la URL. Nunca se confía en
        // los parámetros: cualquiera puede escribir cualquier cosa en la URL.
        $filtros = $this->filtrosValidados($request);

        // Paso 2: traer TODAS las líneas, ya normalizadas en una sola
        // colección por LineasProduccion, y luego quedarse con las que
        // cumplen el filtro.
        $registros = $this->aplicarFiltros(
            LineasProduccion::obtener(),
            $filtros
        );

        // Paso 3: pasarlo todo a la vista.
        return view('history.index', [
            'registros' => $registros,

            // Total DESPUÉS de filtrar, para el texto "N registros
            // encontrados". Con el total antes de filtrar, la pantalla
            // decía "29 registros" y abajo mostraba 2.
            'totalRegistros' => $registros->count(),

            // Para el formulario de filtros: el <select> de turno sale de la
            // tabla turnos, no de valores escritos a mano en el HTML.
            'turnos' => Turno::orderBy('id')->get(),

            // Los filtros ya limpios, para repintar el formulario con lo que
            // el usuario eligió.
            'filtros' => $filtros,

            // true cuando hay algún filtro aplicado. La vista lo usa para
            // distinguir dos cosas muy distintas: "no hay producción" de
            // "tu filtro no arroja nada". Con un filtro activo que no
            // coincide, decir "todavía no hay producción registrada" sería
            // mentira.
            'hayFiltros' => $this->hayFiltros($filtros),
        ]);
    }

    /**
     * Lee y sanea los filtros del request.
     *
     * Todo lo que se usa después sale de acá, ya validado. Se acepta
     * únicamente:
     *   - categoría: una de 'pan', 'torta', 'bocadito'
     *   - desde / hasta: fechas en formato Y-m-d
     *   - turno_id: un entero positivo
     *
     * Cualquier otra cosa se descarta en silencio en lugar de generar un
     * error. Una URL manipulada no debe poder romper la página.
     *
     * @return array{categoria: ?string, desde: ?string, hasta: ?string, turno_id: ?int, error: ?string}
     */
    private function filtrosValidados(Request $request): array
    {
        // in_array con strict=true: '1' no cuenta como 'pan'. Sin el
        // strict, un tipo de dato distinto podría colarse.
        $categoria = in_array($request->query('categoria'), ['pan', 'torta', 'bocadito'], true)
            ? $request->query('categoria')
            : null;

        $desde = $this->fechaValida($request->query('desde'));
        $hasta = $this->fechaValida($request->query('hasta'));

        // is_numeric antes de convertir: por ejemplo 'turno_id=abc' daría 0
        // con un simple (int), y 0 no sería un id válido.
        $turnoId = $request->query('turno_id');
        $turnoId = is_numeric($turnoId) && (int) $turnoId > 0 ? (int) $turnoId : null;

        $error = null;

        // Rango al revés: Desde posterior a Hasta.
        // Sin esta comprobación, la comparación de textos del filtro
        // devolvería cero resultados y el usuario vería una lista vacía,
        // sin entender por qué.
        if ($desde && $hasta && $desde > $hasta) {
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
     * Devuelve la fecha en formato Y-m-d, o null si no es una fecha real.
     *
     * Carbon::parse() lanza una excepción si no puede interpretar el texto
     * (por ejemplo, si alguien escribe desde=ayer). Se atrapa y se devuelve
     * null, para que un valor inválido en la URL no tire la página.
     *
     * El formato de salida es siempre Y-m-d, que es como se guardan las
     * fechas. Eso permite después comparar con el operador > y < directamente
     * entre strings: en ISO 8601 el orden alfabético coincide con el
     * cronológico ('2026-09-01' < '2026-09-28').
     */
    private function fechaValida(mixed $valor): ?string
    {
        if (! is_string($valor) || $valor === '') {
            return null;
        }

        try {
            return Carbon::parse($valor)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Aplica los filtros sobre la colección de líneas.
     *
     * Cada línea tiene siempre las mismas claves, así que el filtro es el
     * mismo para las tres categorías y no hace falta preguntar de qué tabla
     * viene cada una.
     *
     * Se usan los métodos when() de Collection: aplacan un filtro SOLO si la
     * condición se cumple, así que la cadena de filtros se lee como
     * "si hay categoría, filtrá por categoría".
     */
    private function aplicarFiltros(Collection $lineas, array $filtros): Collection
    {
        return $lineas
            // 'tipo' es pan / torta / bocadito en minúsculas (lo define
            // LineasProduccion), y el valor de la URL también lo es, así que
            // comparan directo.
            ->when(
                $filtros['categoria'],
                fn ($c) => $c->where('tipo', $filtros['categoria'])
            )

            // Rango de fechas. 'fecha' ya viene como string Y-m-d, así que
            // se puede comparar con >= y <= directamente.
            ->when(
                $filtros['desde'],
                fn ($c) => $c->filter(fn ($l) => $l->fecha >= $filtros['desde'])
            )
            ->when(
                $filtros['hasta'],
                fn ($c) => $c->filter(fn ($l) => $l->fecha <= $filtros['hasta'])
            )

            // Turno.
            //
            // La línea YA trae el nombre del turno resuelto, así que en vez
            // de comparar un id que no viene, se busca el nombre una sola vez
            // y se compara contra el nombre de cada línea. Cuesta una consulta
            // en total, no una por línea.
            //
            // Y como el turno SOLO existe en el pan (ver el comentario de la
            // clase), las líneas de torta y bocadito tienen 'turno' en null y
            // quedan afuera. Es el comportamiento correcto: al filtrar
            // bocaditos por turno no hay resultados, porque un bocadito no
            // tiene turno.
            ->when($filtros['turno_id'], function ($c) use ($filtros) {
                $nombre = Turno::where('id', $filtros['turno_id'])->value('nombre_turnos');

                // Si el turno no existe en la base, no se muestra nada en vez
                // de romper.
                return $nombre
                    ? $c->filter(fn ($l) => $l->turno === $nombre)
                    : $c->filter(fn () => false);
            })

            // filter() conserva las claves originales del array (0, 1, 3, 7
            // si eliminó el 2), y la vista recorre con @forelse. values()
            // las vuelve a indexar de cero para que el índice coincida con la
            // posición.
            ->values();
    }

    /**
     * ¿Hay algún filtro aplicado?
     *
     * Lo usa la vista para no mostrar un mensaje equivocado. Sin filtros y
     * sin resultados: "todavía no hay producción registrada". Con filtros y
     * sin resultados: "ningún registro coincide con el filtro", que es la
     * verdad y además indica qué hacer.
     */
    private function hayFiltros(array $filtros): bool
    {
        return $filtros['categoria'] !== null
            || $filtros['desde'] !== null
            || $filtros['hasta'] !== null
            || $filtros['turno_id'] !== null;
    }
}
