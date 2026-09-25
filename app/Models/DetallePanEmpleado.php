<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetallePanEmpleado extends Model
{
    protected $table = 'detalle_pan_empleado';

    protected $fillable = [
        'detalle_pan_id',
        'empleados_id',
        'rol_produccion_id',
    ];

    public function detallePan()
    {
        return $this->belongsTo(DetallePan::class);
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