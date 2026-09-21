<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetallePan extends Model
{
protected $table = 'detalle_pan';

public $timestamps = false;

protected $fillable = [
'produccion_id',
'producto_id',
'unidad_medida_id',
'turno_id',
'cantidad',
    ];

// belongsTo: esta tabla tiene la FK hacia produccion
public function produccion()
    {
return $this->belongsTo(Produccion::class, 'produccion_id');
    }

// belongsTo: esta tabla tiene la FK hacia productos
public function producto()
    {
return $this->belongsTo(Producto::class, 'producto_id');
    }

// belongsTo: esta tabla tiene la FK hacia unidades_medida
public function unidadMedida()
    {
return $this->belongsTo(UnidadMedida::class, 'unidad_medida_id');
    }

// belongsTo: esta tabla tiene la FK hacia turnos
public function turno()
    {
return $this->belongsTo(Turno::class, 'turno_id');
    }

}