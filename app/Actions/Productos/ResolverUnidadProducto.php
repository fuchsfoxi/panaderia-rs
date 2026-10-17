<?php

namespace App\Actions\Productos;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\UnidadMedida;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ResolverUnidadProducto
{
    public function ejecutar(array $datos, ?Producto $producto = null): array
    {
        $familia = Categoria::find($datos['categoria_id'] ?? $producto?->categoria_id)?->nombre_categorias;
        $permitidos = UnidadMedida::codigosParaFamilia($familia ?? '');
        $historico = false;
        if ($producto) {
            foreach (['detallesPan', 'detallesTorta', 'detallesBocadito'] as $relacion) {
                if ($producto->$relacion()->lockForUpdate()->first(['id'])) {
                    $historico = true;
                    break;
                }
            }
        }
        if ($historico && (int) ($datos['categoria_id'] ?? $producto->categoria_id) !== $producto->categoria_id) {
            throw ValidationException::withMessages(['categoria_id' => 'Este producto ya tiene producción registrada. Su categoría no se puede cambiar.']);
        }
        $actual = $producto?->unidadMedida?->codigo;
        $codigo = $datos['unidad_registro'] ?? ($familia === 'Pan' ? $actual : UnidadMedida::UNIDAD);

        // Una asociación legacy con historia se conserva para editar metadatos;
        // no se convierte en una unidad canónica ni habilita nuevos lotes.
        if ($historico && ! in_array($actual, $permitidos, true) && ! array_key_exists('unidad_registro', $datos)) {
            $esperada = $producto->unidad_medida_id;
        } else {
            if (! in_array($codigo, $permitidos, true)) {
                throw ValidationException::withMessages(['unidad_registro' => 'Selecciona una forma de registro válida para la categoría.']);
            }
            if ($historico && $codigo !== $actual) {
                throw ValidationException::withMessages(['unidad_registro' => 'Este producto ya tiene producción registrada. Su unidad de registro no se puede cambiar.']
                    + (array_key_exists('unidad_medida_id', $datos) ? ['unidad_medida_id' => 'Su unidad de medida está protegida por el historial.'] : []));
            }
            $esperada = UnidadMedida::where('codigo', $codigo)->value('id');
            if ($esperada === null) {
                throw ValidationException::withMessages(['unidad_medida_id' => 'Falta la unidad canónica seleccionada. Revisa el catálogo con el responsable del sistema.']);
            }
            $esperada = (int) $esperada;
        }
        if (array_key_exists('unidad_medida_id', $datos) && (int) $datos['unidad_medida_id'] !== $esperada) {
            throw ValidationException::withMessages(['unidad_medida_id' => 'La unidad no corresponde al registro seleccionado o está protegida por el historial.']);
        }
        $esLata = $familia === 'Pan' && $codigo === UnidadMedida::LATA;
        $factor = array_key_exists('panes_por_lata', $datos) ? $datos['panes_por_lata'] : $producto?->panes_por_lata;
        // Omitir el factor al pasar a UNIDAD lo limpia; enviar un valor lo contradice.
        if (! $esLata && (! $historico || in_array($actual, $permitidos, true))) {
            if (isset($datos['panes_por_lata'])) {
                throw ValidationException::withMessages(['panes_por_lata' => 'Los panes por lata solo se aplican a Pan registrado por latas.']);
            }
            $factor = null;
        } elseif ($esLata) {
            if (! is_int($factor) && ! is_string($factor)) {
                throw ValidationException::withMessages(['panes_por_lata' => 'Debes indicar panes por lata como entero positivo.']);
            }
            Validator::make(['panes_por_lata' => $factor], ['panes_por_lata' => ['required', 'integer', 'min:1', 'max:2147483647', 'regex:/\A[0-9]+\z/']])->validate();
        }
        unset($datos['unidad_registro']);
        $datos['unidad_medida_id'] = $esperada;
        $datos['panes_por_lata'] = $factor;

        return $datos;
    }
}
