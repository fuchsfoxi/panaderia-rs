<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DetalleTorta extends Model
{
    protected $table = 'detalle_torta';

    public $timestamps = false;

    protected $fillable = [
        'produccion_id',
        'registrado_por_usuario_id',
        'producto_id',
        'cantidad',
        'hora_vitrina',
        'observacion',
        'forma',
        'foto',
    ];

    protected function casts(): array
    {
        return ['cantidad' => 'integer'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'registrado_por_usuario_id');
    }

    public function empleados(): BelongsToMany
    {
        return $this->belongsToMany(Empleado::class, 'detalle_torta_empleado', 'detalle_torta_id', 'empleado_id')
            ->using(DetalleTortaEmpleado::class)
            ->withPivot('rol_produccion_id');
    }

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
