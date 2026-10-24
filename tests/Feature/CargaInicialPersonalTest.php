<?php

use App\Actions\Personal\AdministrarGrupos;
use App\Actions\Personal\CargarEquiposIniciales;
use App\Models\Categoria;
use App\Models\DetalleBocadito;
use App\Models\DetallePan;
use App\Models\DetalleTorta;
use App\Models\Empleado;
use App\Models\GrupoIntegrante;
use App\Models\GrupoProduccion;
use App\Models\Produccion;
use App\Models\Rol;
use App\Models\RolProduccion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\IsolatedMariaDb;

beforeEach(function () {
    if (! getenv('SPRINT4_TEST_SOCKET')) {
        $this->markTestSkipped('Requiere MariaDB desechable.');
    }
    expect(IsolatedMariaDb::migrateInNewDatabase())->toBe(0);
    // Ejercitar toda la carga con referencias distintas a los IDs de la base operativa.
    foreach (['roles' => 101, 'roles_produccion' => 201, 'categorias' => 301,
        'empleados' => 401, 'usuarios_sistema' => 501, 'grupos_produccion' => 601] as $tabla => $inicio) {
        DB::statement('ALTER TABLE `'.$tabla.'` AUTO_INCREMENT = '.$inicio);
    }
    $this->fixtures = IsolatedMariaDb::fixtures(Rol::ADMINISTRADOR);
    $this->actor = $this->fixtures['usuario']->id;
    $this->joshelo = $this->fixtures['empleados'][2];
    $this->joshelo->update(['nombre_empleados' => 'joshelo']);
    $this->anterior = app(AdministrarGrupos::class)->guardar([
        'nombre' => 'Equipo anterior de prueba', 'categoria_id' => $this->fixtures['productos']['Pan']->categoria_id,
        'maestro_id' => $this->fixtures['empleados'][0]->id,
        'integrantes' => $this->fixtures['empleados']->slice(1)->pluck('id')->all(), 'activo' => true,
    ], $this->actor);
    $this->opciones = ['--administrador' => $this->actor, '--confirmar-joshelo' => $this->joshelo->id];
    $this->carga = app(CargarEquiposIniciales::class);
});

afterEach(function () {
    if (getenv('SPRINT4_CASE_DATABASE')) {
        IsolatedMariaDb::restoreDatabase();
    }
});

function filasCarga(array $tablas): array
{
    return collect($tablas)->mapWithKeys(fn ($tabla) => [$tabla => DB::table($tabla)->get()->toJson()])->all();
}

test('dry run bloquea el rol incompatible de Joshelo y no cambia una sola fila', function () {
    $tablas = ['empleados', 'grupos_produccion', 'grupo_integrantes', 'usuarios_sistema', 'roles_produccion', 'categorias'];
    $antes = filasCarga($tablas);
    $this->artisan('personal:cargar-equipos-iniciales', ['--dry-run' => true, '--administrador' => $this->actor])
        ->expectsOutputToContain('Vista previa READ ONLY')->expectsOutputToContain('Carga bloqueada')->assertFailed();
    $plan = $this->carga->previsualizar($this->actor);
    expect($plan['errores'])->not->toBe([])->and($plan['advertencias'])->toHaveCount(1)
        ->and(collect($plan['empleados'])->where('operacion', 'Crear'))->toHaveCount(8)
        ->and(filasCarga($tablas))->toBe($antes);
});

test('vista previa confirmada es READ ONLY y planifica ocho altas una promoción y cuatro grupos', function () {
    $antes = filasCarga(['empleados', 'grupos_produccion', 'grupo_integrantes', 'usuarios_sistema']);
    DB::enableQueryLog();
    DB::flushQueryLog();
    try {
        $plan = $this->carga->previsualizar($this->actor, $this->joshelo->id);
        $consultas = collect(DB::getQueryLog())->pluck('query')->implode("\n");
        expect($consultas)->toContain('SET TRANSACTION READ ONLY')->not->toMatch('/\b(insert|update|delete|alter|create|drop|truncate)\b/i');
    } finally {
        DB::disableQueryLog();
    }
    expect($plan['errores'])->toBe([])->and(collect($plan['empleados'])->where('operacion', 'Crear'))->toHaveCount(8)
        ->and(collect($plan['empleados'])->where('operacion', 'Modificar rol'))->toHaveCount(1)
        ->and(collect($plan['grupos'])->where('operacion', 'Crear'))->toHaveCount(4)
        ->and(filasCarga(array_keys($antes)))->toBe($antes);
    $this->artisan('personal:cargar-equipos-iniciales', ['--dry-run' => true] + $this->opciones)->assertSuccessful();
    expect(filasCarga(array_keys($antes)))->toBe($antes);
});

test('aplica toda la carga reutilizando Joshelo y repetición no reescribe datos ni timestamps', function () {
    $this->artisan('personal:cargar-equipos-iniciales', ['--aplicar' => true] + $this->opciones)->assertSuccessful();
    $personas = Empleado::whereIn(DB::raw('LOWER(nombre_empleados)'), ['joshelo', 'joel', 'nilson', 'paul', 'leo', 'arturo', 'chuy', 'ibar', 'alberto'])->get();
    expect($personas)->toHaveCount(9)->and($personas->firstWhere('nombre_empleados', 'joshelo')->id)->toBe($this->joshelo->id)
        ->and($personas->filter(fn ($e) => $e->rolProduccion->nombre_roles_produccion === 'Maestro'))->toHaveCount(4)
        ->and($personas->filter(fn ($e) => $e->rolProduccion->nombre_roles_produccion === 'Ayudante'))->toHaveCount(4)
        ->and($personas->filter(fn ($e) => $e->rolProduccion->nombre_roles_produccion === 'Practicante'))->toHaveCount(1);
    foreach (CargarEquiposIniciales::EQUIPOS as $equipo) {
        $grupo = GrupoProduccion::where('nombre', $equipo['nombre'])->sole();
        expect($grupo->problemasComposicion())->toBe([])->and($grupo->categoria->nombre_categorias)->toBe($equipo['categoria'])
            ->and(mb_strtolower($grupo->maestro->nombre_empleados))->toBe(mb_strtolower($equipo['maestro']))
            ->and($grupo->ayudantes()->pluck('nombre_empleados')->all())->toBe($equipo['ayudantes'])
            ->and($grupo->practicantes()->pluck('nombre_empleados')->all())->toBe($equipo['practicantes']);
    }
    $tablas = ['empleados', 'grupos_produccion', 'grupo_integrantes', 'usuarios_sistema', 'roles_produccion', 'categorias'];
    // Un timestamp antiguo hace observable cualquier reescritura innecesaria al repetir.
    DB::table('empleados')->update(['updated_at' => '2026-01-01 00:00:00']);
    DB::table('grupos_produccion')->update(['updated_at' => '2026-01-01 00:00:00']);
    $antes = filasCarga($tablas);
    $this->artisan('personal:cargar-equipos-iniciales', ['--aplicar' => true] + $this->opciones)->assertSuccessful();
    expect(filasCarga($tablas))->toBe($antes);
    $plan = $this->carga->previsualizar($this->actor);
    expect($plan['errores'])->toBe([])->and(collect($plan['empleados'])->pluck('operacion')->unique()->all())->toBe(['Reutilizar'])
        ->and(collect($plan['grupos'])->pluck('operacion')->unique()->all())->toBe(['Reutilizar']);
});

test('con nueve personas ausentes crea el equipo completo y repetir conserva todas las filas', function () {
    $this->joshelo->update(['nombre_empleados' => 'Trabajador anterior de prueba']);
    $opciones = ['--administrador' => $this->actor];
    $plan = $this->carga->previsualizar($this->actor);
    expect($plan['errores'])->toBe([])
        ->and(collect($plan['empleados'])->where('operacion', 'Crear'))->toHaveCount(9);

    $this->artisan('personal:cargar-equipos-iniciales', ['--aplicar' => true] + $opciones)->assertSuccessful();
    expect(Empleado::count())->toBe(12)->and(GrupoProduccion::count())->toBe(5)
        ->and(DB::table('grupo_integrantes')->count())->toBe(7)
        ->and($this->anterior->fresh()->problemasComposicion())->toBe([]);
    $antes = filasCarga(['empleados', 'grupos_produccion', 'grupo_integrantes']);
    $this->artisan('personal:cargar-equipos-iniciales', ['--aplicar' => true] + $opciones)->assertSuccessful();
    expect(filasCarga(array_keys($antes)))->toBe($antes);
});

test('reutiliza una carga parcial compatible y completa solo las personas y grupos ausentes', function () {
    $equipo = CargarEquiposIniciales::EQUIPOS[1];
    $maestro = Empleado::create(['nombre_empleados' => $equipo['maestro'],
        'rol_produccion_id' => $this->fixtures['roles']['Maestro']->id, 'activo' => true]);
    $ayudante = Empleado::create(['nombre_empleados' => $equipo['ayudantes'][0],
        'rol_produccion_id' => $this->fixtures['roles']['Ayudante']->id, 'activo' => true]);
    $leo = Empleado::create(['nombre_empleados' => 'Leo', 'rol_produccion_id' => $this->fixtures['roles']['Ayudante']->id, 'activo' => true]);
    $arturo = Empleado::create(['nombre_empleados' => 'Arturo', 'rol_produccion_id' => $this->fixtures['roles']['Practicante']->id, 'activo' => true]);
    $grupo = app(AdministrarGrupos::class)->guardar([
        'nombre' => $equipo['nombre'], 'categoria_id' => $this->fixtures['productos']['Pan']->categoria_id,
        'maestro_id' => $maestro->id, 'integrantes' => [$ayudante->id, $leo->id, $arturo->id], 'activo' => true,
    ], $this->actor);
    $antes = [$maestro->fresh()->getAttributes(), $ayudante->fresh()->getAttributes(), $grupo->fresh()->getAttributes()];
    $plan = $this->carga->previsualizar($this->actor, $this->joshelo->id);
    expect($plan['errores'])->toBe([])
        ->and(collect($plan['empleados'])->where('operacion', 'Crear'))->toHaveCount(4)
        ->and(collect($plan['grupos'])->where('operacion', 'Crear'))->toHaveCount(3);

    $this->artisan('personal:cargar-equipos-iniciales', ['--aplicar' => true] + $this->opciones)->assertSuccessful();
    expect([$maestro->fresh()->getAttributes(), $ayudante->fresh()->getAttributes(), $grupo->fresh()->getAttributes()])->toBe($antes)
        ->and(Empleado::count())->toBe(11)->and(GrupoProduccion::count())->toBe(5)
        ->and(DB::table('grupo_integrantes')->count())->toBe(7);
});

test('conserva otras personas cuentas grupos y las tres familias históricas', function () {
    $tablas = ['usuarios_sistema', 'roles', 'roles_produccion', 'categorias', 'turnos', 'produccion'];
    foreach (['Pan' => DetallePan::class, 'Torta' => DetalleTorta::class, 'Bocadito' => DetalleBocadito::class] as $familia => $modelo) {
        $cabecera = Produccion::create(['fecha' => '2026-10-21', 'categoria_id' => $this->fixtures['productos'][$familia]->categoria_id,
            'turno_id' => $this->fixtures['turno']->id, 'registrado_por_usuario_id' => $this->actor]);
        $detalle = IsolatedMariaDb::detail($modelo, $cabecera, $this->fixtures);
        $detalle->empleados()->attach($this->joshelo->id, ['rol_produccion_id' => $this->fixtures['roles']['Ayudante']->id]);
        $tablas[] = $detalle->getTable();
        $tablas[] = $detalle->getTable().'_empleado';
    }
    $otrasPersonas = Empleado::whereKey($this->fixtures['empleados']->take(2)->pluck('id'))->get()->toJson();
    $grupoAnterior = $this->anterior->fresh()->getAttributes();
    $pivotesAnteriores = DB::table('grupo_integrantes')->where('grupo_id', $this->anterior->id)->get()->toJson();
    $antes = filasCarga($tablas);
    $josheloAntes = $this->joshelo->fresh()->getAttributes();
    $this->carga->aplicar($this->actor, $this->joshelo->id);
    expect(filasCarga($tablas))->toBe($antes)
        ->and(Empleado::whereKey($this->fixtures['empleados']->take(2)->pluck('id'))->get()->toJson())->toBe($otrasPersonas)
        ->and($this->anterior->fresh()->getAttributes())->toBe($grupoAnterior)
        ->and(DB::table('grupo_integrantes')->where('grupo_id', $this->anterior->id)->get()->toJson())->toBe($pivotesAnteriores)
        ->and($this->anterior->fresh()->problemasComposicion())->not->toBe([]);
    $despues = $this->joshelo->fresh()->getAttributes();
    unset($despues['rol_produccion_id'], $despues['updated_at'], $josheloAntes['rol_produccion_id'], $josheloAntes['updated_at']);
    expect($despues)->toBe($josheloAntes);
});

test('bloquea homónimos incluso cuando la collation equipara mayúsculas', function () {
    Empleado::create(['nombre_empleados' => 'JOSHELO', 'rol_produccion_id' => $this->fixtures['roles']['Maestro']->id, 'activo' => true]);
    $antes = filasCarga(['empleados', 'grupos_produccion', 'grupo_integrantes']);
    $this->artisan('personal:cargar-equipos-iniciales', ['--aplicar' => true] + $this->opciones)->expectsOutputToContain('Homónimos de Joshelo')->assertFailed();
    expect(filasCarga(array_keys($antes)))->toBe($antes);
});

test('bloquea otras personas con rol incompatible o inactivas antes de la primera escritura', function (string $caso) {
    if ($caso === 'Joshelo inactivo') {
        $this->joshelo->update(['activo' => false]);
    } else {
        Empleado::create(['nombre_empleados' => 'Joel', 'rol_produccion_id' => $this->fixtures['roles'][$caso === 'Joel Maestro' ? 'Maestro' : 'Ayudante']->id,
            'activo' => $caso !== 'Joel inactivo']);
    }
    $antes = filasCarga(['empleados', 'grupos_produccion', 'grupo_integrantes']);
    $this->artisan('personal:cargar-equipos-iniciales', ['--aplicar' => true] + $this->opciones)->assertFailed();
    expect(filasCarga(array_keys($antes)))->toBe($antes);
})->with(['Joshelo inactivo', 'Joel Maestro', 'Joel inactivo']);

test('no modifica grupos coincidentes con otra composición ni elige grupos homónimos', function (bool $duplicado) {
    $this->anterior->update(['nombre' => 'Panaderos 1']);
    if ($duplicado) {
        GrupoProduccion::create($this->anterior->only(['nombre', 'categoria_id', 'maestro_id', 'activo']));
    }
    $antes = filasCarga(['empleados', 'grupos_produccion', 'grupo_integrantes']);
    $this->artisan('personal:cargar-equipos-iniciales', ['--aplicar' => true] + $this->opciones)->assertFailed();
    expect(filasCarga(array_keys($antes)))->toBe($antes);
})->with([false, true]);

test('catálogos ausentes o ambiguos bloquean toda la carga sin crearlos', function (string $caso) {
    if ($caso === 'rol duplicado') {
        RolProduccion::create(['nombre_roles_produccion' => 'Maestro']);
    } elseif ($caso === 'rol equivalente') {
        RolProduccion::create(['nombre_roles_produccion' => 'MAESTRO']);
    } elseif ($caso === 'categoría duplicada') {
        Categoria::create(['nombre_categorias' => 'Torta']);
    } elseif ($caso === 'categoría equivalente') {
        Categoria::create(['nombre_categorias' => 'TORTA']);
    } else {
        $this->fixtures['roles']['Maestro']->update(['nombre_roles_produccion' => 'Responsable legacy']);
    }
    $antes = filasCarga(['empleados', 'grupos_produccion', 'grupo_integrantes', 'roles_produccion', 'categorias']);
    $this->artisan('personal:cargar-equipos-iniciales', ['--aplicar' => true] + $this->opciones)->assertFailed();
    expect(filasCarga(array_keys($antes)))->toBe($antes);
})->with(['rol duplicado', 'rol equivalente', 'categoría duplicada', 'categoría equivalente', 'rol ausente']);

test('el actor debe seguir siendo Administrador activo y la identidad confirmada debe coincidir', function (string $caso) {
    $opciones = $this->opciones;
    if ($caso === 'Operador') {
        $this->fixtures['rol']->update(['nombre_roles' => Rol::OPERADOR]);
    } elseif ($caso === 'inactivo') {
        DB::table('usuarios_sistema')->where('id', $this->actor)->update(['activo' => false]);
    } else {
        $opciones['--confirmar-joshelo'] = $this->fixtures['empleados'][0]->id;
    }
    $antes = filasCarga(['empleados', 'grupos_produccion', 'grupo_integrantes', 'usuarios_sistema']);
    $this->artisan('personal:cargar-equipos-iniciales', ['--aplicar' => true] + $opciones)->assertFailed();
    expect(filasCarga(array_keys($antes)))->toBe($antes);
})->with(['Operador', 'inactivo', 'otra identidad']);

test('un fallo de pivote revierte ocho altas promoción y todos los grupos nuevos', function () {
    $antes = filasCarga(['empleados', 'grupos_produccion', 'grupo_integrantes', 'usuarios_sistema']);
    $dispatcher = Model::getEventDispatcher();
    Model::setEventDispatcher(clone $dispatcher);
    try {
        GrupoIntegrante::creating(function ($pivot) {
            $pivot->empleado_id = 999999;
        });
        $this->artisan('personal:cargar-equipos-iniciales', ['--aplicar' => true] + $this->opciones)->assertFailed();
        expect(filasCarga(array_keys($antes)))->toBe($antes);
    } finally {
        Model::setEventDispatcher($dispatcher);
    }
});

test('aplicar vuelve a comprobar los datos aunque una vista previa anterior fuese válida', function () {
    expect($this->carga->previsualizar($this->actor, $this->joshelo->id)['errores'])->toBe([]);
    $this->joshelo->update(['activo' => false]);
    $antes = filasCarga(['empleados', 'grupos_produccion', 'grupo_integrantes']);
    expect(fn () => $this->carga->aplicar($this->actor, $this->joshelo->id))->toThrow(ValidationException::class)
        ->and(filasCarga(array_keys($antes)))->toBe($antes);
});

test('sin aplicar el comando es solo vista previa y rechaza flags contradictorios o IDs inválidos', function (array $opciones, bool $correcto) {
    $antes = filasCarga(['empleados', 'grupos_produccion', 'grupo_integrantes']);
    $comando = $this->artisan('personal:cargar-equipos-iniciales', $opciones + $this->opciones);
    $correcto ? $comando->assertSuccessful() : $comando->assertFailed();
    expect(filasCarga(array_keys($antes)))->toBe($antes);
})->with([[[], true], [['--dry-run' => true, '--aplicar' => true], false], [['--administrador' => 'malformado'], false], [['--confirmar-joshelo' => 0], false]]);

test('dos cargas simultáneas reutilizan las filas confirmadas sin duplicar personas ni grupos', function () {
    $procesos = [];
    try {
        DB::beginTransaction();
        DB::table('roles_produccion')->where('id', $this->fixtures['roles']['Maestro']->id)->lockForUpdate()->first();
        foreach (range(1, 2) as $numero) {
            $proceso = proc_open([PHP_BINARY, base_path('tests/Support/carga-personal-concurrente.php')], [
                0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'],
            ], $pipes, base_path());
            $procesos[] = [$proceso, $pipes];
            fwrite($pipes[0], json_encode(['actor' => $this->actor, 'joshelo' => $this->joshelo->id], JSON_THROW_ON_ERROR)."\n");
            fclose($pipes[0]);
            stream_set_timeout($pipes[1], 15);
            $listo = trim(fgets($pipes[1]) ?: '');
            expect($listo)->toStartWith('LISTO:');
            $conexion = (int) substr($listo, 6);
            $espera = false;
            $limite = microtime(true) + 5;
            do {
                $consulta = DB::selectOne('SELECT INFO AS consulta FROM information_schema.PROCESSLIST WHERE ID = ?', [$conexion])?->consulta ?? '';
                $espera = str_contains($consulta, 'for update') && (str_contains($consulta, 'from `roles_produccion`') || str_contains($consulta, 'from `grupos_produccion`'));
                if (! $espera) {
                    usleep(10000);
                }
            } while (! $espera && microtime(true) < $limite);
            expect($espera)->toBeTrue('El worker debe intentar adquirir el contexto bloqueado antes de liberarlo.');
        }
        DB::commit();
        foreach ($procesos as [$proceso, $pipes]) {
            $salida = trim(stream_get_contents($pipes[1]));
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            expect(proc_close($proceso))->toBe(0, $error)->and($salida)->toBe('APLICADO');
        }
        $procesos = [];
        expect(Empleado::count())->toBe(11)->and(GrupoProduccion::count())->toBe(5)
            ->and(DB::table('grupo_integrantes')->count())->toBe(7);
        $plan = $this->carga->previsualizar($this->actor);
        expect($plan['errores'])->toBe([])->and(collect($plan['empleados'])->pluck('operacion')->unique()->all())->toBe(['Reutilizar'])
            ->and(collect($plan['grupos'])->pluck('operacion')->unique()->all())->toBe(['Reutilizar']);
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

test('completa Panaderos 2 anterior agregando solo Leo y Arturo y conserva el resto', function () {
    $nilson = Empleado::create(['nombre_empleados' => 'Nilson', 'rol_produccion_id' => $this->fixtures['roles']['Maestro']->id]);
    $paul = Empleado::create(['nombre_empleados' => 'Paul', 'rol_produccion_id' => $this->fixtures['roles']['Ayudante']->id]);
    $grupo = app(AdministrarGrupos::class)->guardar([
        'nombre' => 'Panaderos 2', 'categoria_id' => $this->fixtures['productos']['Pan']->categoria_id,
        'maestro_id' => $nilson->id, 'integrantes' => [$paul->id], 'activo' => true,
    ], $this->actor);
    $plan = $this->carga->previsualizar($this->actor, $this->joshelo->id);
    expect($plan['errores'])->toBe([])->and(collect($plan['grupos'])->firstWhere('nombre', 'Panaderos 2')['operacion'])->toBe('Completar integrantes');
    $this->carga->aplicar($this->actor, $this->joshelo->id);
    $grupo->refresh();
    expect($grupo->maestro_id)->toBe($nilson->id)->and($grupo->ayudantes()->pluck('nombre_empleados')->all())->toBe(['Paul', 'Leo'])
        ->and($grupo->practicantes()->pluck('nombre_empleados')->all())->toBe(['Arturo'])
        ->and(GrupoProduccion::where('nombre', 'Panaderos 2')->count())->toBe(1);
    expect($this->carga->previsualizar($this->actor)['errores'])->toBe([]);
});

test('bloquea Leo como Practicante Arturo en otro grupo o Joel en Panaderos 2 sin corregir silenciosamente', function (string $caso) {
    if ($caso === 'Leo') {
        Empleado::create(['nombre_empleados' => 'Leo', 'rol_produccion_id' => $this->fixtures['roles']['Practicante']->id]);
    } elseif ($caso === 'Arturo') {
        $arturo = Empleado::create(['nombre_empleados' => 'Arturo', 'rol_produccion_id' => $this->fixtures['roles']['Practicante']->id]);
        $this->anterior->integrantes()->attach($arturo->id);
    } else {
        $this->anterior->update(['nombre' => 'Panaderos 2']);
        $joel = Empleado::create(['nombre_empleados' => 'Joel', 'rol_produccion_id' => $this->fixtures['roles']['Ayudante']->id]);
        $this->anterior->integrantes()->attach($joel->id);
    }
    $antes = filasCarga(['empleados', 'grupos_produccion', 'grupo_integrantes']);
    expect($this->carga->previsualizar($this->actor, $this->joshelo->id)['errores'])->not->toBe([]);
    $this->artisan('personal:cargar-equipos-iniciales', ['--aplicar' => true] + $this->opciones)->assertFailed();
    expect(filasCarga(array_keys($antes)))->toBe($antes);
})->with(['Leo', 'Arturo', 'Joel']);
