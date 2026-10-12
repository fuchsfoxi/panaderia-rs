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
    public function __construct(
        private ResolverCabeceraProduccion $cabeceras,
        private ValidarParticipantesProduccion $participantes,
    ) {}

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

            $produccion = $this->cabeceras->resolver($datos['fecha'], (int) $datos['turno_id'], $categoriaPan, $usuarioId);
            $detallePan = $this->registrarDetalle($produccion, $producto, $detalle, $totalLatas, $usuarioId);
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

        if ($producto?->panes_por_lata !== null && $producto->panes_por_lata <= 0) {
            $errores['detalles.0.producto_id'] = 'El valor de panes por lata del producto debe ser positivo o desconocido.';
        }

        if ($totalLatas <= 0) {
            $errores['detalles.0.coches'] = 'Ingresa al menos un coche o una lata adicional.';
        } elseif ($totalLatas > DetallePan::MAX_CANTIDAD_LATAS) {
            $errores['detalles.0.coches'] = 'El total de latas no puede exceder '.DetallePan::MAX_CANTIDAD_LATAS.'.';
        }

        $errores = array_merge($errores, $this->participantes->errores($detalle['participantes'], $roles, 'Pan'));

        // No se escribe nada hasta comprobar todas las reglas del único detalle.
        if ($errores) {
            throw ValidationException::withMessages($errores);
        }
    }

    private function registrarDetalle(Produccion $produccion, Producto $producto, array $detalle, int $totalLatas, int $usuarioId): DetallePan
    {
        // Un envío crea un lote independiente, aunque su producto ya figure en la sesión.
        return $produccion->detallesPan()->create([
            'producto_id' => $producto->id,
            'registrado_por_usuario_id' => $usuarioId,
            'cantidad' => $totalLatas,
            'unidad_medida_id' => $producto->unidad_medida_id,
            'turno_id' => $produccion->turno_id,
            'panes_por_lata_usado' => $producto->panes_por_lata,
            'observacion' => $detalle['observacion'] ?? null,
            'hora_finalizacion' => $detalle['hora_finalizacion'],
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
