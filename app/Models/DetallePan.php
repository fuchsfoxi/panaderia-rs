<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DetallePan extends Model
{
    // Solo Pan por LATA usa coches; la unidad histórica decide la magnitud.
    public const LATAS_POR_COCHE = 18;

    // Capacidad positiva de cantidad (INT firmado) en el esquema existente.
    public const MAX_CANTIDAD_LATAS = 2147483647;

    protected $table = 'detalle_pan';

    public $timestamps = false;

    protected $fillable = [
        'produccion_id',
        'registrado_por_usuario_id',
        'producto_id',
        'observacion',
        // Hora local del lote, sin asociar una fecha ficticia mediante un cast datetime.
        'hora_finalizacion',
        'unidad_medida_id',
        'turno_id',
        // Cantidad positiva en la unidad histórica: latas o unidades, siempre entero.
        'cantidad',
        // Valor usado en esta producción; no se recalcula al editar el producto.
        'panes_por_lata_usado',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'registrado_por_usuario_id');
    }

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
