<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetallePanEmpleado extends Model
{
    protected $table = 'detalle_pan_empleado';

    public $timestamps = false;
    public $incrementing = false;

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