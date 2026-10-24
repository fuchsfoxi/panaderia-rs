<?php

use App\Actions\Productos\ImportarProductosCsv;
use App\Models\Producto;
use App\Models\Rol;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\IsolatedMariaDb;

beforeEach(function () {
    if (! getenv('SPRINT4_TEST_SOCKET')) {
        $this->markTestSkipped('Requiere MariaDB desechable.');
    }
    expect(IsolatedMariaDb::migrateInNewDatabase())->toBe(0);
    foreach (['categorias', 'unidades_medida', 'productos'] as $tabla) {
        DB::statement('ALTER TABLE `'.$tabla.'` AUTO_INCREMENT = 301');
    }
    $this->fixtures = IsolatedMariaDb::fixtures(Rol::ADMINISTRADOR);
    $this->archivoCsv = tempnam(sys_get_temp_dir(), 'panaderia-csv-');
    $this->importar = app(ImportarProductosCsv::class);
});

afterEach(function () {
    if (isset($this->archivoCsv) && is_file($this->archivoCsv)) {
        unlink($this->archivoCsv);
    }
    if (getenv('SPRINT4_CASE_DATABASE')) {
        IsolatedMariaDb::restoreDatabase();
    }
});

function escribirCatalogoCsv(string $archivo, array $filas, bool $bom = false): void
{
    $handle = fopen($archivo, 'wb');
    if ($bom) {
        fwrite($handle, "\xEF\xBB\xBF");
    }
    fputcsv($handle, ImportarProductosCsv::ENCABEZADOS, escape: '');
    foreach ($filas as $fila) {
        fputcsv($handle, $fila, escape: '');
    }
    fclose($handle);
}

function snapshotImportacion(): array
{
    return collect(['productos', 'categorias', 'unidades_medida', 'empleados', 'produccion', 'detalle_pan', 'detalle_torta', 'detalle_bocadito'])
        ->mapWithKeys(fn ($tabla) => [$tabla => DB::table($tabla)->orderBy('id')->get()->toJson()])->all();
}

test('vista previa es READ ONLY y aplica las familias y unidades canónicas sin IDs fijos', function () {
    escribirCatalogoCsv($this->archivoCsv, [
        ['Prueba lata', 'Pan', 'LATA', '18'], ['Prueba cantidad', 'Pan', 'UNIDAD', ''],
        ['Prueba torta', 'Torta', 'UNIDAD', ''], ['Prueba bocadito', 'Bocadito', 'UNIDAD', ''],
    ], true);
    $antes = snapshotImportacion();
    DB::enableQueryLog();
    DB::flushQueryLog();
    try {
        $plan = $this->importar->ejecutar($this->archivoCsv);
        $sql = collect(DB::getQueryLog())->pluck('query')->implode("\n");
        expect($sql)->toContain('SET TRANSACTION READ ONLY')->not->toMatch('/\b(insert|update|delete|alter|create|drop|truncate)\b/i');
    } finally {
        DB::disableQueryLog();
    }
    expect($plan['nuevos'])->toBe(4)->and($plan['existentes'])->toBe(0)->and($plan['rechazados'])->toBe(0)
        ->and($plan['aplicado'])->toBeFalse()->and(snapshotImportacion())->toBe($antes);
    $this->artisan('productos:importar-csv', ['archivo' => $this->archivoCsv])->expectsOutputToContain('Nuevos: 4')->assertSuccessful();
    expect(snapshotImportacion())->toBe($antes);
    $plan = $this->importar->ejecutar($this->archivoCsv, true);
    expect($plan['aplicado'])->toBeTrue()->and(Producto::count())->toBe(7);
    foreach (['Prueba lata' => ['Pan', 'LATA', 18], 'Prueba cantidad' => ['Pan', 'UNIDAD', null],
        'Prueba torta' => ['Torta', 'UNIDAD', null], 'Prueba bocadito' => ['Bocadito', 'UNIDAD', null]] as $nombre => [$categoria, $codigo, $factor]) {
        $producto = Producto::where('nombre_p', $nombre)->sole();
        expect($producto->categoria->nombre_categorias)->toBe($categoria)->and($producto->unidadMedida->codigo)->toBe($codigo)
            ->and($producto->panes_por_lata)->toBe($factor)->and($producto->activo)->toBeTrue();
    }
    $antes = snapshotImportacion();
    $this->artisan('productos:importar-csv', ['archivo' => $this->archivoCsv, '--aplicar' => true])
        ->expectsOutputToContain('Nuevos: 0 · Existentes: 4 · Rechazados: 0')->assertSuccessful();
    expect(snapshotImportacion())->toBe($antes);
});

test('deduplica por collation nombre normalizado y categoría incluso dentro del CSV', function () {
    $producto = $this->fixtures['productos']['Pan'];
    $producto->update(['nombre_p' => 'Árbol   ficticio', 'activo' => false]);
    escribirCatalogoCsv($this->archivoCsv, [
        ['arbol ficticio', 'Pan', 'LATA', 12],
        ['Nuevo   ejemplo', 'Pan', 'UNIDAD', ''], ['NUEVO ejemplo', 'Pan', 'UNIDAD', ''],
        ['nuevo ejemplo', 'Torta', 'UNIDAD', ''],
    ]);
    $anterior = $producto->fresh()->getAttributes();
    $plan = $this->importar->ejecutar($this->archivoCsv, true);
    expect($plan['nuevos'])->toBe(2)->and($plan['existentes'])->toBe(2)->and($plan['rechazados'])->toBe(0)
        ->and(Producto::count())->toBe(5)->and($producto->fresh()->getAttributes())->toBe($anterior);
});

test('una fila rechazada bloquea el archivo completo e informa número y motivo', function (array $invalido, string $mensaje) {
    escribirCatalogoCsv($this->archivoCsv, [['Ejemplo válido', 'Pan', 'UNIDAD', ''], $invalido]);
    $antes = snapshotImportacion();
    $plan = $this->importar->ejecutar($this->archivoCsv, true);
    expect($plan['aplicado'])->toBeFalse()->and($plan['nuevos'])->toBe(1)->and($plan['rechazados'])->toBe(1)
        ->and($plan['filas'][1]['numero'])->toBe(3)->and($plan['filas'][1]['mensaje'])->toContain($mensaje)
        ->and(snapshotImportacion())->toBe($antes);
    $this->artisan('productos:importar-csv', ['archivo' => $this->archivoCsv, '--aplicar' => true])->assertFailed();
    expect(snapshotImportacion())->toBe($antes);
})->with([
    'nombre vacío' => [['', 'Pan', 'UNIDAD', ''], 'obligatorio'],
    'nombre largo' => [[str_repeat('a', 101), 'Pan', 'UNIDAD', ''], '100'],
    'categoría desconocida' => [['Ejemplo', 'Otra', 'UNIDAD', ''], 'categoría'],
    'unidad desconocida' => [['Ejemplo', 'Pan', 'CAJA', ''], 'unidad'],
    'torta por latas' => [['Ejemplo', 'Torta', 'LATA', ''], 'registro válida'],
    'bocadito por latas' => [['Ejemplo', 'Bocadito', 'LATA', ''], 'registro válida'],
    'lata sin factor' => [['Ejemplo', 'Pan', 'LATA', ''], 'lata'],
    'factor cero' => [['Ejemplo', 'Pan', 'LATA', '0'], 'mayores'],
    'factor negativo' => [['Ejemplo', 'Pan', 'LATA', '-2'], 'positivo'],
    'factor decimal' => [['Ejemplo', 'Pan', 'LATA', '1.5'], 'positivo'],
    'factor excesivo' => [['Ejemplo', 'Pan', 'LATA', '2147483648'], '2147483647'],
    'factor en unidad' => [['Ejemplo', 'Pan', 'UNIDAD', '12'], 'cantidad directa'],
    'factor en torta' => [['Ejemplo', 'Torta', 'UNIDAD', '12'], 'cantidad directa'],
    'columnas extra' => [['Ejemplo', 'Pan', 'UNIDAD', '', 'sobrante'], 'cuatro columnas'],
    'UTF-8 inválido' => [["Inv\xFFlido", 'Pan', 'UNIDAD', ''], 'UTF-8'],
]);

test('duplicados incompatibles históricos o del mismo CSV no se sobrescriben', function (string $caso) {
    $producto = $this->fixtures['productos']['Pan'];
    $filas = [[$producto->nombre_p, 'Pan', 'LATA', 13]];
    if ($caso === 'duplicados existentes') {
        Producto::create($producto->only(['nombre_p', 'categoria_id', 'unidad_medida_id', 'panes_por_lata']));
    } elseif ($caso === 'duplicado interno') {
        $filas = [['Ejemplo nuevo', 'Pan', 'LATA', 12], ['EJEMPLO NUEVO', 'Pan', 'LATA', 13]];
    }
    escribirCatalogoCsv($this->archivoCsv, $filas);
    $antes = snapshotImportacion();
    $plan = $this->importar->ejecutar($this->archivoCsv, true);
    expect($plan['rechazados'])->toBe(1)->and($plan['aplicado'])->toBeFalse()->and(snapshotImportacion())->toBe($antes);
})->with(['factor distinto', 'duplicados existentes', 'duplicado interno']);

test('fallo al guardar un producto revierte todos los nuevos del archivo', function () {
    escribirCatalogoCsv($this->archivoCsv, [['Ejemplo uno', 'Pan', 'UNIDAD', ''], ['Ejemplo dos', 'Torta', 'UNIDAD', '']]);
    $antes = snapshotImportacion();
    $dispatcher = Model::getEventDispatcher();
    Model::setEventDispatcher(clone $dispatcher);
    $intentos = 0;
    try {
        Producto::creating(function () use (&$intentos) {
            if (++$intentos === 2) {
                throw new RuntimeException('Fallo de prueba');
            }
        });
        expect(fn () => $this->importar->ejecutar($this->archivoCsv, true))->toThrow(RuntimeException::class)
            ->and($intentos)->toBe(2)->and(snapshotImportacion())->toBe($antes);
    } finally {
        Model::setEventDispatcher($dispatcher);
    }
});

test('plantilla solo tiene encabezados y no crea productos', function () {
    $antes = snapshotImportacion();
    $plan = $this->importar->ejecutar(base_path('database/imports/productos.csv'), true);
    expect($plan['nuevos'])->toBe(0)->and($plan['rechazados'])->toBe(0)->and($plan['filas'])->toBe([])
        ->and(snapshotImportacion())->toBe($antes);
});

test('rechaza encabezados incorrectos archivos ausentes y flags incompatibles', function () {
    file_put_contents($this->archivoCsv, "nombre,categoria,unidad,factor\n");
    expect(fn () => $this->importar->ejecutar($this->archivoCsv))->toThrow(ValidationException::class);
    $this->artisan('productos:importar-csv', ['archivo' => $this->archivoCsv])->expectsOutputToContain('Encabezados esperados')->assertFailed();
    $this->artisan('productos:importar-csv', ['archivo' => $this->archivoCsv, '--dry-run' => true, '--aplicar' => true])->assertFailed();
    $this->artisan('productos:importar-csv', ['archivo' => $this->archivoCsv.'-ausente'])->assertFailed();
});

test('dos importaciones simultáneas no duplican productos al esperar el mismo catálogo', function () {
    escribirCatalogoCsv($this->archivoCsv, [['Prueba concurrente', 'Pan', 'UNIDAD', '']]);
    $procesos = [];
    try {
        DB::beginTransaction();
        DB::table('categorias')->where('id', $this->fixtures['productos']['Pan']->categoria_id)->lockForUpdate()->first();
        foreach (range(1, 2) as $numero) {
            $proceso = proc_open([PHP_BINARY, base_path('tests/Support/importar-productos-concurrente.php')], [
                0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'],
            ], $pipes, base_path());
            $procesos[] = [$proceso, $pipes];
            fwrite($pipes[0], json_encode(['archivo' => $this->archivoCsv], JSON_THROW_ON_ERROR)."\n");
            fclose($pipes[0]);
            stream_set_timeout($pipes[1], 15);
            $listo = trim(fgets($pipes[1]) ?: '');
            expect($listo)->toStartWith('LISTO:');
            $conexion = (int) substr($listo, 6);
            $espera = false;
            $limite = microtime(true) + 5;
            do {
                $consulta = DB::selectOne('SELECT INFO AS consulta FROM information_schema.PROCESSLIST WHERE ID = ?', [$conexion])?->consulta ?? '';
                $espera = str_contains($consulta, 'for update') && str_contains($consulta, 'from `categorias`');
                if (! $espera) {
                    usleep(10000);
                }
            } while (! $espera && microtime(true) < $limite);
            expect($espera)->toBeTrue();
        }
        DB::commit();
        $resultados = [];
        foreach ($procesos as [$proceso, $pipes]) {
            $salida = trim(stream_get_contents($pipes[1]));
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            expect(proc_close($proceso))->toBe(0, $error);
            $resultados[] = json_decode($salida, true, flags: JSON_THROW_ON_ERROR);
        }
        $procesos = [];
        expect(array_column($resultados, 'aplicado'))->toBe([true, true])
            ->and(array_sum(array_column($resultados, 'nuevos')))->toBe(1)
            ->and(array_sum(array_column($resultados, 'existentes')))->toBe(1)
            ->and(Producto::where('nombre_p', 'Prueba concurrente')->count())->toBe(1);
    } finally {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        foreach ($procesos as [$proceso, $pipes]) {
            if (is_resource($proceso)) {
                proc_terminate($proceso);
                foreach ($pipes as $pipe) {
                    if (is_resource($pipe)) {
                        fclose($pipe);
                    }
                }
                proc_close($proceso);
            }
        }
    }
});
