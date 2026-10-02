<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DetallePan extends Model
{
    protected $table = 'detalle_pan';

    public $timestamps = false;

    protected $fillable = [
        'produccion_id',
        'producto_id',
        'observacion',
        'unidad_medida_id',
        'turno_id',
        // Cantidad canónica de Pan: total de latas producidas, siempre entero.
        'cantidad',
        // Valor usado en esta producción; no se recalcula al editar el producto.
        'panes_por_lata_usado',
    ];

    public function empleados(): BelongsToMany
    {
        return $this->belongsToMany(Empleado::class, 'detalle_pan_empleado', 'detalle_pan_id', 'empleado_id')
            ->using(DetallePanEmpleado::class)
            ->withPivot('rol_produccion_id');
    }

    // belongsTo: esta tabla tiene la FK hacia produccion
    public function produccion()
    {
        return $this->belongsTo(Produccion::class, 'produccion_id');
    }

    // belongsTo: esta tabla tiene la FK hacia productos
    public function producto()
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    // belongsTo: esta tabla tiene la FK hacia unidades_medida
    public function unidadMedida()
    {
        return $this->belongsTo(UnidadMedida::class, 'unidad_medida_id');
    }

    // belongsTo: esta tabla tiene la FK hacia turnos
    public function turno()
    {
        return $this->belongsTo(Turno::class, 'turno_id');
    }
}
