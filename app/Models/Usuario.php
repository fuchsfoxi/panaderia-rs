<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class Usuario extends Authenticatable{
    // por defecto, Eloquent asume que el nombre de la tabla es el plural del nombre del modelo
    // pero la tabla se llama diferente, por lo que debemos especificarlo manualmente
    protected $table = 'usuarios_sistema';

    // le decimos que solo queremos que se puedan asignar estos campos de manera masiva
    // para evitar problemas de seguridad
    protected $fillable = [
        'username',
        'password_hash',
        'empleado_id',
        'rol_id',
    ];

    // este borra  el campo password_hash de la respuesta cuando se devuelve un usuario, para no exponerlo (json, api, etc)
    protected $hidden = [
        'password_hash',
    ];

    // llama a la relación con el modelo Empleado y Rol, para poder acceder a los datos del empleado y rol desde el usuario
    public function empleado()
    {
        return $this->belongsTo(Empleado::class, 'empleado_id');
    }

    public function rol()
    {
        return $this->belongsTo(Rol::class, 'rol_id');
    }

    // sirve para que Laravel sepa que el campo de la contraseña no se llama "password" sino "password_hash"
    // esto es necesario para que funcione la autenticación con el sistema de login de Laravel
    // ya que Laravel por defecto busca un campo llamado "password" para la autenticación
    public function getAuthPassword()
    {
        return $this->password_hash;
    }


}
