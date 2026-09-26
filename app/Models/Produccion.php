<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Produccion extends Model
{
protected $table = 'produccion';

// esta tabla no tiene created_at/updated_at en la migración
public $timestamps = false;

protected $fillable = [
'fecha',
'registrado_por_usuario_id',
    ];

// belongsTo: produccion tiene la FK hacia usuarios_sistema
public function usuario()
    {
return $this->belongsTo(UsuarioSistema::class, 'registrado_por_usuario_id');
    }

// hasOne: la FK vive en detalle_pan, pero por diseño cada producción
// tiene como máximo un detalle de pan asociado (nunca varios)
public function detallePan()
    {
return $this->hasOne(DetallePan::class, 'produccion_id');
    }

// hasOne: mismo caso, cada producción tiene como máximo un detalle de torta
public function detalleTorta()
    {
return $this->hasOne(DetalleTorta::class, 'produccion_id');
    }

// hasOne: mismo caso, cada producción tiene como máximo un detalle de bocadito
public function detalleBocadito()
    {
return $this->hasOne(DetalleBocadito::class, 'produccion_id');
    }

}