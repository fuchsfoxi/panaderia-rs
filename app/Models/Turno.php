<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Turno extends Model
{
protected $table = 'turnos';

// esta tabla no tiene created_at/updated_at en la migración
public $timestamps = false;

protected $fillable =[
'nombre_turnos'
    ];

// hasMany: un turno (ej. "Mañana") se repite en varios registros de detalle_pan
public function detallesPan()
    {
return $this->hasMany(DetallePan::class, 'turno_id');
    }

}