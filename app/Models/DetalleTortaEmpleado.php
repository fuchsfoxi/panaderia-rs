<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class DetalleTortaEmpleado extends Pivot
{
    protected $table = 'detalle_torta_empleado';

    public $timestamps = false;

    public $incrementing = false;

    // Claves usadas también al leer la asociación fuera de belongsToMany.
    protected $foreignKey = 'detalle_torta_id';

    protected $relatedKey = 'empleado_id';

    protected $fillable = [
        'detalle_torta_id',
        'empleado_id',
        'rol_produccion_id',
    ];

    public function detalleTorta()
    {
        return $this->belongsTo(DetalleTorta::class);
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
