<?php

namespace App\Http\Controllers;

use App\Consultas\ResumenDashboard;

class DashboardController extends Controller
{
    /**
     * MODIFICADO: la vista mostraba tarjetas de categoria con numeros fijos
     * ("12.5 Coches", "15 Unidades", "900 Unidades"), 3 cartas en 0 y los
     * datasets [0,0,0] de los graficos. Todo eso ahora viene de la base.
     */
    public function index()
    {
        return view('dashboard.index', [
            // Tarjetas por categoria (solo HOY). Cada una trae 'texto' ya
            // formateado y 'lineasDeHoy' para alimentar los modales.
            'tarjetas' => ResumenDashboard::tarjetasDeHoy(),

            // Las 3 cartas de produccion (total / pendiente / completada).
            'cartas' => ResumenDashboard::cartas(),

            // Datos de los 3 graficos, ya listos para Chart.js.
            'graficos' => ResumenDashboard::graficos(),
        ]);
    }
}
