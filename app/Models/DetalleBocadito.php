<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetalleBocadito extends Model
{
protected $table = 'detalle_bocadito';

public $timestamps = false;

protected $fillable = [
'produccion_id',
'producto_id',
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

}