<?php

namespace App\Actions\Productos;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\UnidadMedida;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use LogicException;

class ImportarProductosCsv
{
    public const ENCABEZADOS = ['nombre_p', 'categoria', 'unidad_registro', 'panes_por_lata'];

    public function __construct(private ValidarDatosProducto $validar, private ResolverUnidadProducto $unidades, private CrearProducto $crear) {}

    public function ejecutar(string $archivo, bool $aplicar = false): array
    {
        $filas = $this->leer($archivo);
        if (DB::transactionLevel() !== 0 || ! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            throw new LogicException('La importación requiere MariaDB y una conexión sin transacción previa.');
        }
        if ($aplicar) {
            return DB::transaction(function () use ($filas): array {
                $plan = $this->plan($filas, true);
                if ($plan['rechazados'] === 0) {
                    foreach ($plan['filas'] as $fila) {
                        if ($fila['estado'] === 'Nuevo') {
                            $this->crear->ejecutar($fila['datos']);
                        }
                    }
                    $plan['aplicado'] = true;
                }

                return $plan;
            }, 3);
        }
        DB::statement('SET TRANSACTION READ ONLY');
        DB::beginTransaction();
        try {
            return $this->plan($filas, false);
        } finally {
            DB::rollBack();
        }
    }

    private function leer(string $archivo): array
    {
        if (! is_file($archivo) || ! is_readable($archivo) || filesize($archivo) > 5 * 1024 * 1024) {
            throw ValidationException::withMessages(['archivo' => 'Selecciona un archivo CSV legible de hasta 5 MiB.']);
        }
        $handle = fopen($archivo, 'rb');
        if ($handle === false) {
            throw ValidationException::withMessages(['archivo' => 'No se pudo abrir el CSV.']);
        }
        try {
            $encabezados = fgetcsv($handle, escape: '');
            if ($encabezados) {
                $encabezados[0] = preg_replace('/^\xEF\xBB\xBF/', '', $encabezados[0] ?? '');
            }
            if ($encabezados !== self::ENCABEZADOS) {
                throw ValidationException::withMessages(['archivo' => 'Encabezados esperados, separados por comas: '.implode(',', self::ENCABEZADOS).'.']);
            }
            $filas = [];
            $numero = 1;
            while (($fila = fgetcsv($handle, escape: '')) !== false) {
                $numero++;
                if ($fila === [null]) {
                    continue;
                }
                if (count($filas) >= 10000) {
                    throw ValidationException::withMessages(['archivo' => 'El CSV admite hasta 10000 filas de datos. Divide el catálogo en archivos más pequeños.']);
                }
                $filas[] = ['numero' => $numero, 'valores' => $fila];
            }

            return $filas;
        } finally {
            fclose($handle);
        }
    }

    private function plan(array $filas, bool $bloquear): array
    {
        // El mismo orden categoría → producto de las Actions existentes.
        $consulta = Categoria::whereIn('nombre_categorias', ['Pan', 'Torta', 'Bocadito'])->orderBy('id');
        $categorias = ($bloquear ? $consulta->lockForUpdate() : $consulta)->get();
        if ($bloquear) {
            UnidadMedida::whereIn('codigo', ['LATA', 'UNIDAD'])->orderBy('id')->sharedLock()->get();
        }
        $columna = DB::selectOne("SHOW FULL COLUMNS FROM productos LIKE 'nombre_p'");
        $collation = $columna->Collation ?? '';
        if (! preg_match('/\Autf8mb4_[a-z0-9_]+\z/', $collation)) {
            throw new LogicException('Revisa la collation de productos.nombre_p: la importación requiere utf8mb4.');
        }
        $plan = ['nuevos' => 0, 'existentes' => 0, 'rechazados' => 0, 'aplicado' => false, 'filas' => []];
        $previstos = [];
        foreach ($filas as $entrada) {
            $fila = ['numero' => $entrada['numero'], 'nombre' => '', 'estado' => 'Rechazado', 'mensaje' => '', 'datos' => null];
            try {
                if (count($entrada['valores']) !== count(self::ENCABEZADOS)) {
                    throw ValidationException::withMessages(['fila' => 'Se esperan cuatro columnas. Revisa las comas y las comillas.']);
                }
                foreach ($entrada['valores'] as $valor) {
                    if (! mb_check_encoding($valor ?? '', 'UTF-8')) {
                        throw ValidationException::withMessages(['fila' => 'Guarda el archivo con codificación UTF-8.']);
                    }
                }
                $csv = array_combine(self::ENCABEZADOS, array_map(fn ($valor) => trim($valor ?? ''), $entrada['valores']));
                $fila['nombre'] = $csv['nombre_p'];
                $coincidencias = $categorias->where('nombre_categorias', $csv['categoria']);
                if ($coincidencias->count() !== 1) {
                    throw ValidationException::withMessages(['categoria' => 'La categoría debe existir una sola vez y llamarse Pan, Torta o Bocadito.']);
                }
                $datos = $this->validar->normalizar([
                    'nombre_p' => $csv['nombre_p'], 'categoria_id' => $coincidencias->sole()->id,
                    'unidad_registro' => $csv['unidad_registro'], 'panes_por_lata' => $csv['panes_por_lata'] === '' ? null : $csv['panes_por_lata'],
                ]);
                $validator = Validator::make($datos, $this->validar->reglas($datos), $this->validar->mensajes(), $this->validar->atributos());
                $validator->after(fn ($validator) => $this->validar->adicionales($validator, $datos));
                $validator->validate();
                $datos = $this->unidades->ejecutar($datos) + ['unidad_registro' => $csv['unidad_registro']];
                $fila['nombre'] = $datos['nombre_p'];
                $consulta = Producto::where('categoria_id', $datos['categoria_id'])
                    ->whereRaw("TRIM(REGEXP_REPLACE(nombre_p, '[[:space:]]+', ' ')) = ?", [$datos['nombre_p']])->orderBy('id');
                $existentes = ($bloquear ? $consulta->lockForUpdate() : $consulta)->get();
                if ($existentes->count() > 1) {
                    throw ValidationException::withMessages(['nombre_p' => 'Hay varios productos equivalentes en esta categoría (IDs '.$existentes->pluck('id')->implode(', ').'). Revisa los duplicados existentes.']);
                }
                $anterior = $existentes->first()?->getAttributes();
                $origen = $anterior ? 'Producto existente #'.$anterior['id'] : '';
                // WEIGHT_STRING permite comparar en memoria con la misma collation de MariaDB,
                // sin realizar una consulta por cada pareja de filas del archivo.
                $peso = DB::selectOne('SELECT HEX(WEIGHT_STRING(? COLLATE '.$collation.')) AS clave', [$datos['nombre_p']])->clave;
                $clave = $datos['categoria_id'].':'.$peso;
                if ($anterior === null && isset($previstos[$clave])) {
                    $anterior = $previstos[$clave]['datos'];
                    $origen = 'Repetido de la fila '.$previstos[$clave]['numero'];
                }
                if ($anterior !== null) {
                    if ((int) $anterior['unidad_medida_id'] !== (int) $datos['unidad_medida_id']
                        || ($anterior['panes_por_lata'] === null ? null : (int) $anterior['panes_por_lata']) !== ($datos['panes_por_lata'] === null ? null : (int) $datos['panes_por_lata'])) {
                        throw ValidationException::withMessages(['producto' => $origen.': la unidad o panes por lata son distintos. No se sobrescribirá.']);
                    }
                    $fila['estado'] = 'Existente';
                    $fila['mensaje'] = $origen.'. Se conserva sin cambios, incluido su estado activo/inactivo.';
                    $plan['existentes']++;
                } else {
                    $fila['estado'] = 'Nuevo';
                    $fila['mensaje'] = 'Crear producto activo en '.$csv['categoria'].'.';
                    $fila['datos'] = $datos;
                    $previstos[$clave] = ['numero' => $fila['numero'], 'datos' => $datos];
                    $plan['nuevos']++;
                }
            } catch (ValidationException $exception) {
                $fila['mensaje'] = implode(' ', array_merge(...array_values($exception->errors())));
                $plan['rechazados']++;
            }
            $plan['filas'][] = $fila;
        }

        return $plan;
    }
}
