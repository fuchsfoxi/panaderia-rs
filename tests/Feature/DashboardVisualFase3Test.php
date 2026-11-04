<?php

use App\Models\DetalleBocadito;
use App\Models\DetallePan;
use App\Models\DetalleTorta;
use App\Models\Produccion;
use App\Models\Rol;
use App\Models\UnidadMedida;
use Illuminate\Foundation\Vite;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\IsolatedMariaDb;

beforeEach(function () {
    if (! getenv('SPRINT4_TEST_SOCKET')) {
        $this->markTestSkipped('Requiere MariaDB desechable.');
    }
    IsolatedMariaDb::connect();
    expect(Artisan::call('migrate', ['--database' => 'sprint4_test', '--force' => true]))->toBe(0);
    DB::beginTransaction();
    $this->f = IsolatedMariaDb::fixtures(Rol::ADMINISTRADOR);
    $this->travelTo(Carbon::parse('2027-01-02 10:00', 'America/Lima'));
    $this->actingAs($this->f['usuario']);
    app(Vite::class)->useHotFile('/tmp/dashboard-fase3-sin-hot');
});

afterEach(function () {
    $this->travelBack();
    if (config('database.default') === 'sprint4_test') {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
    }
});

function agregarVisualDashboard(array $f, string $familia, array $extra = []): void
{
    $sesion = Produccion::create(['fecha' => '2027-01-02', 'categoria_id' => $f['productos'][$familia]->categoria_id,
        'turno_id' => $f['turno']->id, 'registrado_por_usuario_id' => $f['usuario']->id]);
    $clase = match ($familia) {
        'Pan' => DetallePan::class, 'Torta' => DetalleTorta::class, 'Bocadito' => DetalleBocadito::class
    };
    IsolatedMariaDb::detail($clase, $sesion, $f, $extra);
}

function exportarVisualDashboard(string $nombre, string $html): void
{
    $dir = getenv('DASHBOARD_FASE3_HTML');
    if (! $dir) {
        return;
    }
    if (! str_starts_with($dir, '/tmp/')) {
        throw new RuntimeException('Exportar solo a /tmp.');
    }
    file_put_contents($dir.'/'.$nombre.'.html', $html);
}

test('presenta datos preparados y conserva el resumen histórico', function () {
    agregarVisualDashboard($this->f, 'Pan', ['cantidad' => 2, 'panes_por_lata_usado' => 10, 'hora_finalizacion' => '08:30']);
    agregarVisualDashboard($this->f, 'Torta', ['cantidad' => 4, 'hora_vitrina' => '09:15']);
    agregarVisualDashboard($this->f, 'Bocadito', ['cantidad' => 7]);
    $response = $this->get('/dashboard')->assertOk()->assertSeeText('Producción de hoy')->assertSeeText('Resumen mensual')
        ->assertSeeText('Producciones por fecha')->assertSeeText('Hora de finalización')->assertSeeText('Hora de vitrina')
        ->assertSeeText('Sin hora registrada')->assertSeeText('Bocadito ficticio')->assertSee('data-filtros-dashboard', false);
    expect(substr_count($response->getContent(), '<canvas'))->toBe(1);
    exportarVisualDashboard('dashboard', $response->getContent());
    $filtrado = $this->get('/dashboard?fecha_inicio=2026-12-01&fecha_fin=2026-12-31')->assertOk()->assertSeeText('Resumen del periodo');
    expect($filtrado->viewData('datosDashboard')['hoy']['registros'])->toBe(3);
    exportarVisualDashboard('dashboard-filtrado', $filtrado->getContent());
});

test('comunica cantidades parciales y no presenta ranking incompleto', function () {
    agregarVisualDashboard($this->f, 'Pan', ['cantidad' => 3, 'panes_por_lata_usado' => null]);
    $response = $this->get('/dashboard')->assertOk()->assertSeeText('Total parcial')->assertSeeText('Clasificación no disponible');
    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    $xpath = new DOMXPath($dom);
    expect($xpath->query('//*[@data-pan-mensual="unidades"]//li')->length)->toBe(0);
    exportarVisualDashboard('dashboard-parcial', $response->getContent());
});

test('muestra estados vacíos y tablas de series sin datos', function () {
    $response = $this->get('/dashboard')->assertOk()->assertSeeText('Sin registros este mes.')
        ->assertSeeText('Aún no hay producciones registradas.')->assertSeeText('Ver datos: Últimos 7 días');
    exportarVisualDashboard('dashboard-vacio', $response->getContent());
});

test('advierte si la unidad histórica impide determinar también las latas', function () {
    $unidad = UnidadMedida::create(['nombre_unidades_medida' => 'Desconocida ficticia', 'equivalencia_unidades' => 1]);
    agregarVisualDashboard($this->f, 'Pan', ['cantidad' => 3, 'unidad_medida_id' => $unidad->id]);
    $response = $this->get('/dashboard')->assertOk()->assertSeeText('Total parcial')
        ->assertSeeText('Clasificación no disponible');
    exportarVisualDashboard('dashboard-desconocido', $response->getContent());
});
