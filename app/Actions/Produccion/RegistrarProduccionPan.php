<?php

namespace App\Actions\Produccion;

use App\Models\Categoria;
use App\Models\DetallePan;
use App\Models\Produccion;
use App\Models\Producto;
use App\Models\RolProduccion;
use App\Models\UnidadMedida;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RegistrarProduccionPan
{
    public function __construct(
        private ResolverCabeceraProduccion $cabeceras,
        private ValidarParticipantesProduccion $participantes,
        private ValidarRegistroPan $registro,
    ) {}

    /**
     * Recibe la estructura validada por el límite HTTP; devuelve la sesión registrada.
     */
    public function ejecutar(array $datos, int $usuarioId): Produccion
    {
        return DB::transaction(function () use ($datos, $usuarioId): Produccion {
            Validator::make($datos, [
                'detalles' => ['required', 'array', 'size:1', 'required_array_keys:0'],
                'detalles.0' => ['required', 'array'],
                'detalles.0.producto_id' => ['required', 'integer'],
                'detalles.0.participantes' => ['required', 'array'],
            ])->validate();
            // Conservar el orden de adquisición de locks del escritor original.
            $categoriaPan = $this->obtenerCategoriaPan();
            $roles = RolProduccion::whereIn('nombre_roles_produccion', RolProduccion::NOMBRES)
                ->get()->keyBy('id');
            $detalle = $datos['detalles'][0];
            $producto = $this->obtenerProducto((int) $detalle['producto_id']);
            $this->validarReglas($detalle, $categoriaPan, $producto, $roles);
            $cantidad = $producto->unidadMedida->codigo === UnidadMedida::LATA
                ? (int) $detalle['coches'] * DetallePan::LATAS_POR_COCHE + (int) $detalle['latas_adicionales']
                : (int) $detalle['cantidad'];

            $produccion = $this->cabeceras->resolver($datos['fecha'], (int) $datos['turno_id'], $categoriaPan, $usuarioId);
            $detallePan = $this->registrarDetalle($produccion, $producto, $detalle, $cantidad, $usuarioId);
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

    private function validarReglas(array $detalle, ?Categoria $categoriaPan, ?Producto $producto, Collection $roles): void
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

        foreach ($this->registro->errores($detalle, $producto) as $campo => $mensajes) {
            $errores['detalles.0.'.$campo] = $mensajes;
        }

        $errores = array_merge($errores, $this->participantes->errores($detalle['participantes'], $roles, 'Pan'));

        // No se escribe nada hasta comprobar todas las reglas del único detalle.
        if ($errores) {
            throw ValidationException::withMessages($errores);
        }
    }

    private function registrarDetalle(Produccion $produccion, Producto $producto, array $detalle, int $cantidad, int $usuarioId): DetallePan
    {
        // Un envío crea un lote independiente, aunque su producto ya figure en la sesión.
        return $produccion->detallesPan()->create([
            'producto_id' => $producto->id,
            'registrado_por_usuario_id' => $usuarioId,
            'cantidad' => $cantidad,
            'unidad_medida_id' => $producto->unidad_medida_id,
            'turno_id' => $produccion->turno_id,
            'panes_por_lata_usado' => $producto->panes_por_lata,
            'observacion' => $detalle['observacion'] ?? null,
            'hora_finalizacion' => $producto->unidadMedida->codigo === UnidadMedida::LATA ? $detalle['hora_finalizacion'] : null,
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
