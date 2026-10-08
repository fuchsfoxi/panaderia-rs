<?php

namespace App\Actions\Produccion;

use App\Models\Categoria;
use App\Models\DetallePan;
use App\Models\Produccion;
use App\Models\Producto;
use App\Models\RolProduccion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegistrarProduccionPan
{
    /**
     * Recibe la estructura validada por el límite HTTP; devuelve la sesión registrada.
     */
    public function ejecutar(array $datos, int $usuarioId): Produccion
    {
        return DB::transaction(function () use ($datos, $usuarioId): Produccion {
            // Conservar el orden de adquisición de locks del escritor original.
            $categoriaPan = $this->obtenerCategoriaPan();
            $roles = RolProduccion::whereIn('nombre_roles_produccion', ['Maestro', 'Ayudante'])
                ->get()->keyBy('id');
            $detalle = $datos['detalles'][0];
            $producto = $this->obtenerProducto((int) $detalle['producto_id']);
            $totalLatas = (int) $detalle['coches'] * DetallePan::LATAS_POR_COCHE + (int) $detalle['latas_adicionales'];

            $this->validarReglas($detalle, $categoriaPan, $producto, $roles, $totalLatas);

            $produccion = $this->resolverCabecera($datos['fecha'], (int) $datos['turno_id'], $categoriaPan, $usuarioId);
            $detallePan = $this->registrarDetalle($produccion, $producto, $detalle, $totalLatas);
            $this->registrarParticipantes($detallePan, $detalle['participantes']);

            return $produccion;
        });
    }

    private function obtenerCategoriaPan(): ?Categoria
    {
        // Serializa este flujo mientras no exista una garantía UNIQUE SQL de sesión.
        return Categoria::where('nombre_categorias', 'Pan')->lockForUpdate()->first();
    }

    private function obtenerProducto(int $productoId): ?Producto
    {
        // El producto bloqueado mantiene estable el factor copiado al snapshot.
        return Producto::with('unidadMedida')->where('id', $productoId)->lockForUpdate()->first();
    }

    private function validarReglas(array $detalle, ?Categoria $categoriaPan, ?Producto $producto, Collection $roles, int $totalLatas): void
    {
        $errores = [];
        if (! $categoriaPan) {
            $errores['categoria'] = 'La categoría Pan no está disponible en el catálogo.';
        }

        if (! $producto || ! $producto->activo || $producto->categoria_id !== $categoriaPan?->id) {
            $errores['detalles.0.producto_id'] = 'Debe seleccionar un producto activo de la categoría Pan.';
        } elseif (! $producto->unidadMedida) {
            $errores['detalles.0.producto_id'] = 'El producto no tiene una unidad de medida válida. Revise su catálogo.';
        }

        if ($totalLatas <= 0 || $totalLatas > DetallePan::MAX_CANTIDAD_LATAS) {
            $errores['detalles.0.coches'] = 'El total de latas debe ser mayor que cero y no exceder '.DetallePan::MAX_CANTIDAD_LATAS.'.';
        }

        $errores = array_merge($errores, $this->erroresDeParticipantes($detalle['participantes'], $roles));

        // No se escribe nada hasta comprobar todas las reglas del único detalle.
        if ($errores) {
            throw ValidationException::withMessages($errores);
        }
    }

    private function erroresDeParticipantes(array $participantes, Collection $roles): array
    {
        $errores = [];
        $empleados = [];
        $maestros = 0;
        $ayudantes = 0;
        foreach ($participantes as $posicion => $participante) {
            $empleadoId = (int) $participante['empleado_id'];
            if (isset($empleados[$empleadoId])) {
                $errores['detalles.0.participantes'][] = 'Un empleado no puede repetirse dentro del mismo detalle.';
            }
            $empleados[$empleadoId] = true;
            $rol = $roles->get($participante['rol_produccion_id']);
            if (! $rol) {
                $errores["detalles.0.participantes.$posicion.rol_produccion_id"] = 'Para Pan solo se permiten los roles Maestro y Ayudante.';
            } elseif ($rol->nombre_roles_produccion === 'Maestro') {
                $maestros++;
            } else {
                $ayudantes++;
            }
        }
        if ($maestros !== 1) {
            $errores['detalles.0.participantes'][] = 'Cada detalle de Pan debe tener exactamente un Maestro.';
        }
        if ($ayudantes < 1) {
            $errores['detalles.0.participantes'][] = 'Cada detalle de Pan debe tener al menos un Ayudante.';
        }

        return $errores;
    }

    private function resolverCabecera(string $fecha, int $turnoId, Categoria $categoriaPan, int $usuarioId): Produccion
    {
        $producciones = Produccion::where('fecha', $fecha)
            ->where('categoria_id', $categoriaPan->id)
            ->where('turno_id', $turnoId)
            ->lockForUpdate()->limit(2)->get();
        if ($producciones->count() > 1) {
            throw ValidationException::withMessages([
                'produccion' => 'Existen varias sesiones de Pan para esta fecha y turno. Revise las cabeceras duplicadas antes de registrar otro producto.',
            ]);
        }

        return $producciones->first() ?? Produccion::create([
            'fecha' => $fecha,
            'registrado_por_usuario_id' => $usuarioId,
            'categoria_id' => $categoriaPan->id,
            'turno_id' => $turnoId,
        ]);
    }

    private function registrarDetalle(Produccion $produccion, Producto $producto, array $detalle, int $totalLatas): DetallePan
    {
        // Un envío crea un lote independiente, aunque su producto ya figure en la sesión.
        return $produccion->detallesPan()->create([
            'producto_id' => $producto->id,
            'cantidad' => $totalLatas,
            'unidad_medida_id' => $producto->unidad_medida_id,
            'turno_id' => $produccion->turno_id,
            'panes_por_lata_usado' => $producto->panes_por_lata,
            'observacion' => $detalle['observacion'] ?? null,
        ]);
    }

    private function registrarParticipantes(DetallePan $detallePan, array $participantes): void
    {
        // attach conserva el Pivot personalizado y sus eventos de creación.
        foreach ($participantes as $participante) {
            $detallePan->empleados()->attach($participante['empleado_id'], [
                'rol_produccion_id' => $participante['rol_produccion_id'],
            ]);
        }
    }
}
