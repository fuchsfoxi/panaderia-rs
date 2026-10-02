<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DetalleBocadito extends Model
{
    protected $table = 'detalle_bocadito';

    public $timestamps = false;

    protected $fillable = [
        'produccion_id',
        'producto_id',
        'observacion',
        'cantidad',
    ];

    public function empleados(): BelongsToMany
    {
        return $this->belongsToMany(Empleado::class, 'detalle_bocadito_empleado', 'detalle_bocadito_id', 'empleado_id')
            ->using(DetalleBocaditoEmpleado::class)
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
}
