<?php

namespace App\Http\Controllers;

use App\Consultas\LineasProduccion;

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
     * Los filtros del formulario (categoria / fechas / turno) NO se
     * implementan todavia: siguen sin hacer nada, igual que antes de este
     * cambio.
     */
    public function index()
    {
        // conEmpleados: false (por defecto) porque esta vista no muestra empleados.
        $registros = LineasProduccion::obtener();

        return view('history.index', [
            'registros' => $registros,
            // total de lineas, para el texto "N registros encontrados"
            'totalRegistros' => $registros->count(),
        ]);
    }
}
