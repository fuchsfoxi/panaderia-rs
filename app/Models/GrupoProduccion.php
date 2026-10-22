<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class GrupoProduccion extends Model
{
    public const CATEGORIAS = ['Pan', 'Torta', 'Bocadito'];

    public const RELACIONES_COMPOSICION = ['categoria', 'maestro.rolProduccion', 'integrantes.rolProduccion'];

    protected $table = 'grupos_produccion';

    protected $fillable = ['nombre', 'categoria_id', 'maestro_id', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean', 'categoria_id' => 'integer', 'maestro_id' => 'integer'];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }

    public function maestro(): BelongsTo
    {
        return $this->belongsTo(Empleado::class, 'maestro_id');
    }

    public function integrantes(): BelongsToMany
    {
        return $this->belongsToMany(Empleado::class, 'grupo_integrantes', 'grupo_id', 'empleado_id')
            ->using(GrupoIntegrante::class)->orderBy('empleados.id');
    }

    /** Se usa para advertir al consultar y para validar datos bloqueados al guardar. */
    public function problemasComposicion(): array
    {
        $errores = [];
        $categoria = $this->categoria?->nombre_categorias;
        if (! in_array($categoria, self::CATEGORIAS, true)) {
            $errores['categoria_id'] = 'Selecciona una categoría Pan, Torta o Bocadito.';
        }
        if (! $this->maestro?->activo || $this->maestro->rolProduccion?->nombre_roles_produccion !== 'Maestro') {
            $errores['maestro_id'] = 'El responsable debe ser un empleado activo con rol actual Maestro.';
        }
        $cantidad = $this->integrantes->count();
        $mensaje = match ($categoria) {
            'Pan' => $cantidad < 1 ? 'Un grupo de Pan necesita uno o más Ayudantes habituales.' : null,
            'Torta' => $cantidad !== 0 ? 'Un grupo de Torta no tiene Ayudantes habituales.' : null,
            'Bocadito' => $cantidad !== 1 ? 'Un grupo de Bocadito necesita exactamente un Ayudante habitual.' : null,
            default => null,
        };
        if ($mensaje) {
            $errores['integrantes'] = $mensaje;
        }
        foreach ($this->integrantes as $indice => $empleado) {
            if ($empleado->id === $this->maestro_id) {
                $errores['integrantes.'.$indice] = 'El Maestro responsable no puede aparecer también como Ayudante.';
            } elseif (! $empleado->activo || $empleado->rolProduccion?->nombre_roles_produccion !== 'Ayudante') {
                $errores['integrantes.'.$indice] = 'El integrante #'.$empleado->id.' debe estar activo y tener el rol actual Ayudante.';
            }
        }

        return $errores;
    }

    public function disponibilidad(): string
    {
        return $this->categoria?->nombre_categorias === 'Pan' ? 'Sin turno fijo' : 'Solo Mañana';
    }
}
