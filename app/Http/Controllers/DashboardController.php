<?php

namespace App\Http\Controllers;

use App\Queries\ConsultarDashboard;
use Carbon\CarbonImmutable;
use Illuminate\View\View;

class DashboardController extends Controller
{
    // El Dashboard no tiene filtros: siempre muestra hoy, el mes actual y la actividad reciente.
    // Para consultar un período concreto se usa el Historial.
    public function index(ConsultarDashboard $consultar): View
    {
        $datosDashboard = $consultar->ejecutar(CarbonImmutable::now('America/Lima'));

        return view('dashboard.index', compact('datosDashboard'));
    }
}
