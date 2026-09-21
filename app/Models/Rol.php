<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rol extends Model
{
protected $table = 'roles';

public $timestamps = false;

protected $fillable = [
'nombre_roles'
    ];

//hasMany se usa cuando UN registro tuyo puede estar relacionado con
// VARIOS registros de otra tabla.

//Es la versión "multiplicada" de hasOne — recuerda:

//hasOne → "yo tengo uno de esos" (relación 1 a 1)
//hasMany → "yo tengo varios de esos" (relación 1 a muchos)
public function usuarios()
{
return $this->hasMany(Usuario::class, 'rol_id');
}

}