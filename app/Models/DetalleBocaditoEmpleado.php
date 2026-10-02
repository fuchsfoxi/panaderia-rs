<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class DetalleBocaditoEmpleado extends Pivot
{
    protected $table = 'detalle_bocadito_empleado';

    public $timestamps = false;

    public $incrementing = false;

    // Claves usadas también al leer la asociación fuera de belongsToMany.
    protected $foreignKey = 'detalle_bocadito_id';

    protected $relatedKey = 'empleado_id';

    protected $fillable = [
        'detalle_bocadito_id',
        'empleado_id',
        'rol_produccion_id',
    ];

    public function detalleBocadito()
    {
        return $this->belongsTo(DetalleBocadito::class);
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
