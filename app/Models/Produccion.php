<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Produccion extends Model
{
    protected $table = 'produccion';

    // esta tabla no tiene created_at/updated_at en la migración
    public $timestamps = false;

    protected $fillable = [
        'fecha',
        'registrado_por_usuario_id',
        'categoria_id',
        'turno_id',
    ];

    protected function casts(): array
    {
        return ['fecha' => 'date'];
    }

    // belongsTo: produccion tiene la FK hacia usuarios_sistema
    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'registrado_por_usuario_id');
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }

    public function turno(): BelongsTo
    {
        return $this->belongsTo(Turno::class, 'turno_id');
    }

    // Una sesión admite varios productos/detalles de su familia.
    public function detallesPan(): HasMany
    {
        return $this->hasMany(DetallePan::class, 'produccion_id');
    }

    public function detallesTorta(): HasMany
    {
        return $this->hasMany(DetalleTorta::class, 'produccion_id');
    }

    public function detallesBocadito(): HasMany
    {
        return $this->hasMany(DetalleBocadito::class, 'produccion_id');
    }
}
