<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Produccion extends Model
{
    //
    protected $table = 'produccion';

    public $timestamps = false;

    protected $fillable = [
        'fecha',
        'registro_por_usuario_id'
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'registrado_por_usuario_id');
    }




}
