<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Empleado extends Model
{
// por defecto Eloquent asume que el nombre de la tabla es el plural del nombre del modelo
// pero la tabla se llama diferente, por lo que debemos especificarlo manualmente
protected $table = 'empleados';

// le decimos que solo queremos que se puedan asignar estos campos de manera masiva
// para evitar problemas de seguridad
protected $fillable = [
'nombre_empleados',
'numero_empleados',
'cargo_id',
    ];

// belongsTo: esta tabla tiene la FK (cargo_id), o sea "el empleado pertenece a un cargo"
// permite acceder a los datos de la tabla cargos
public function cargo()
    {
return $this->belongsTo(Cargo::class, 'cargo_id');
    }

// hasOne: la otra tabla (usuarios_sistema) tiene la FK hacia empleados, "el empleado tiene un usuario"
// permite acceder a los datos de la tabla usuarios_sistema
public function usuario()
    {
return $this->hasOne(UsuarioSistema::class, 'empleado_id');
    }

    //"¿La columna FK está en MI tabla, o está en la OTRA tabla?"
        //FK en mi tabla → belongsTo
        //FK en la otra tabla → hasOne (si es 1 a 1) o hasMany (si es 1 a muchos)
}