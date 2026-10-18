<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RolProduccion extends Model
{
    public const NOMBRES = ['Maestro', 'Ayudante', 'Practicante'];

    protected $table = 'roles_produccion';

    public $timestamps = false;

    protected $fillable = [
        'nombre_roles_produccion',
    ];

    public function detallePanEmpleados()
    {
        return $this->hasMany(DetallePanEmpleado::class);
    }

    public function detalleTortaEmpleados()
    {
        return $this->hasMany(DetalleTortaEmpleado::class);
    }

    public function detalleBocaditoEmpleados()
    {
        return $this->hasMany(DetalleBocaditoEmpleado::class);
    }
}
