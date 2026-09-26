<?php

namespace App\Http\Controllers;

use App\Consultas\LineasProduccion;
use App\Models\Empleado;
use App\Models\Producto;
use App\Models\RolProduccion;
use App\Models\Turno;
use Illuminate\Http\Request;

class ProduccionController extends Controller
{
    /**
     * Formulario de produccion + listado de "Registrado recientemente".
     *
     * MODIFICADO: antes el bloque de tarjetas y los <select> del formulario
     * estaban completos con datos fijos en el HTML (3 tarjetas de ejemplo y
     * 5 empleados / 2 roles / 2 turnos inventados). Ahora todo sale de la base.
     */
    public function index()
    {
        return view('produccion.index', $this->datosVista());
    }

    /**
     * Todas las variables que necesita la vista, en un solo lugar.
     *
     * @return array<string, mixed>
     */
    private function datosVista(): array
    {
        return [
            // MODIFICADO: las 3 tarjetas fijas ("Pan Carioca / 3 coches",
            // "Torta de Chocolate / Circular", "Alfajorcitos / 150 unidades")
            // ahora son lineas reales de produccion, de la mas reciente a la
            // mas antigua, con los empleados que las produjeron (conEmpleados).
            'lineas' => LineasProduccion::obtener([
                'conEmpleados' => true,
                'limite' => 6,
            ]),

            // MODIFICADO: el <select id="producto"> estaba vacio salvo el
            // placeholder. Se llenan solo los productos activos.
            'productos' => Producto::where('activo', true)
                ->orderBy('nombre_p')
                ->get(),

            // MODIFICADO: estos 3 <select> tenian <option> escrito a mano
            // (turno 1/2, 5 empleados, Maestro/Ayudante). Ahora el value es el
            // id real de la base, que es la FK que necesita la tabla pivote.
            'turnos' => Turno::orderBy('id')->get(),
            'empleados' => Empleado::orderBy('nombre_empleados')->get(),
            'rolesProduccion' => RolProduccion::orderBy('id')->get(),
        ];
    }

    public function store(Request $request)
    {
        // pendiente, se hace al final del sprint
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
