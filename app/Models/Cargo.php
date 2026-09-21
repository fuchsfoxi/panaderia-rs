<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cargo extends Model
{
protected $table = 'cargos';

// esta tabla no tiene created_at/updated_at en la migración, así que le avisamos a Eloquent
public $timestamps = false;

protected $fillable = [
'nombre_cargos'
    ];

// hasMany: un cargo puede tener VARIOS empleados con ese mismo cargo (1 a muchos)
public function empleados()
    {
return $this->hasMany(Empleado::class, 'cargo_id');
    }

}