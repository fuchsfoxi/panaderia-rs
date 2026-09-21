<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
protected $table = 'productos';

public $timestamps = false;

protected $fillable = [
'nombre_p',
'temporada_fe',
'activo',
'categoria_id',
'unidad_medida_id',
    ];

// belongsTo: productos tiene la FK hacia categorias
public function categoria()
    {
return $this->belongsTo(Categoria::class, 'categoria_id');
    }

// belongsTo: productos tiene la FK hacia unidades_medida
public function unidadMedida()
    {
return $this->belongsTo(UnidadMedida::class, 'unidad_medida_id');
    }

// hasMany: un mismo producto puede aparecer en VARIOS registros de detalle_pan
// (distintos días de producción usan el mismo producto una y otra vez)
public function detallesPan()
    {
return $this->hasMany(DetallePan::class, 'producto_id');
    }

// hasMany: mismo caso, un producto puede aparecer en varios registros de detalle_torta
public function detallesTorta()
    {
return $this->hasMany(DetalleTorta::class, 'producto_id');
    }

// hasMany: mismo caso, un producto puede aparecer en varios registros de detalle_bocadito
public function detallesBocadito()
    {
return $this->hasMany(DetalleBocadito::class, 'producto_id');
    }

}