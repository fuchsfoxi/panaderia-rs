<?php

namespace App\Actions\Produccion;

use App\Models\Categoria;
use App\Models\Produccion;
use App\Models\Producto;
use App\Models\RolProduccion;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegistrarProduccionPorCantidad
{
    public function __construct(
        private ResolverCabeceraProduccion $cabeceras,
        private ValidarParticipantesProduccion $participantes,
    ) {}

    /** La estructura de entrada viene validada por el Request de cada familia. */
    public function ejecutar(array $datos, int $usuarioId, string $familia, Closure $atributos): Produccion
    {
        return DB::transaction(function () use ($datos, $usuarioId, $familia, $atributos): Produccion {
            // Mismo orden de locks que Pan. Serializa la familia incluso sin UNIQUE.
            $categoria = Categoria::where('nombre_categorias', $familia)->lockForUpdate()->first();
            $roles = RolProduccion::whereIn('nombre_roles_produccion', ['Maestro', 'Ayudante'])->get()->keyBy('id');
            $datosDetalle = $datos['detalles'][0];
            $producto = Producto::whereKey($datosDetalle['producto_id'])->lockForUpdate()->first();
            $errores = [];
            if (! $categoria) {
                $errores['categoria'] = "La categoría $familia no está disponible en el catálogo.";
            }
            if (! $producto || ! $producto->activo || $producto->categoria_id !== $categoria?->id) {
                $errores['detalles.0.producto_id'] = "Debe seleccionar un producto activo de la categoría $familia.";
            }
            $cantidad = filter_var($datosDetalle['cantidad'], FILTER_VALIDATE_INT);
            if ($cantidad === false || $cantidad < 1 || $cantidad > 2147483647) {
                $errores['detalles.0.cantidad'] = 'La cantidad debe ser un entero positivo dentro de la capacidad de almacenamiento.';
            }
            // Regla provisional compartida con Pan; todavía no hay otra específica.
            $errores = array_merge($errores, $this->participantes->errores($datosDetalle['participantes'], $roles, $familia));
            if ($errores) {
                throw ValidationException::withMessages($errores);
            }

            $produccion = $this->cabeceras->resolver($datos['fecha'], (int) $datos['turno_id'], $categoria, $usuarioId);
            $relacion = match ($familia) {
                'Torta' => 'detallesTorta',
                'Bocadito' => 'detallesBocadito',
            };
            $detalle = $produccion->$relacion()->create([
                'producto_id' => $producto->id,
                'cantidad' => $cantidad,
                'observacion' => $datosDetalle['observacion'] ?? null,
            ] + $atributos($datosDetalle));
            foreach ($datosDetalle['participantes'] as $participante) {
                $detalle->empleados()->attach($participante['empleado_id'], [
                    'rol_produccion_id' => $participante['rol_produccion_id'],
                ]);
            }

            return $produccion;
        });
    }
}
