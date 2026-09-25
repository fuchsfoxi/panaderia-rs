<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetalleTortaEmpleado extends Model
{
    protected $table = 'detalle_torta_empleado';

    protected $fillable = [
        'detalle_torta_id',
        'empleados_id',
        'rol_produccion_id',
    ];

    public function detalleTorta()
    {
        return $this->belongsTo(DetalleTorta::class);
    }

    public function empleado()
    {
        return $this->belongsTo(Empleado::class, 'empleados_id');
    }

    public function rolProduccion()
    {
        return $this->belongsTo(RolProduccion::class);
    }
}