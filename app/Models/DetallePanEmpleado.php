<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class DetallePanEmpleado extends Pivot
{
    protected $table = 'detalle_pan_empleado';

    public $timestamps = false;

    public $incrementing = false;

    // Claves usadas también al leer la asociación fuera de belongsToMany.
    protected $foreignKey = 'detalle_pan_id';

    protected $relatedKey = 'empleado_id';

    protected $fillable = [
        'detalle_pan_id',
        'empleado_id',
        'rol_produccion_id',
    ];

    public function detallePan()
    {
        return $this->belongsTo(DetallePan::class);
    }

    public function empleado()
    {
        return $this->belongsTo(Empleado::class, 'empleado_id');
    }

    public function rolProduccion()
    {
        return $this->belongsTo(RolProduccion::class);
    }
}
