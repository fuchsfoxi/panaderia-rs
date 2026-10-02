<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Categoria extends Model
{
    protected $table = 'categorias';

    public $timestamps = false;

    protected $fillable = [
        'nombre_categorias',
    ];

    public function productos()
    {
        return $this->hasMany(Producto::class);
    }

    public function producciones(): HasMany
    {
        return $this->hasMany(Produccion::class, 'categoria_id');
    }
}
