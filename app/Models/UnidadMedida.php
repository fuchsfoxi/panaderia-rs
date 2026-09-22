<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UnidadMedida extends Model
{
protected $table = 'unidades_medida';

public $timestamps = false;

protected $fillable =[
'nombre_unidades_medida',
'equivalencia_unidades'
    ];

// hasMany: una unidad de medida se repite en varios productos
public function productos()
    {
return $this->hasMany(Producto::class, 'unidad_medida_id');
    }

// hasMany: una unidad de medida se repite en varios registros de detalle_pan
public function detallesPan()
    {
return $this->hasMany(DetallePan::class, 'unidad_medida_id');
    }

}