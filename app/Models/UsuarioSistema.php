<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;

class UsuarioSistema extends Model implements AuthenticatableContract
{
    //
    use Authenticatable;
    protected $table = 'usuarios_sistema';

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

    public function getAuthPassword()
    {
        return $this->password_hash;
    }
}
