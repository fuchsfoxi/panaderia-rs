<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UnidadMedida extends Model
{
    public const LATA = 'LATA';

    public const UNIDAD = 'UNIDAD';

    public static function codigoParaFamilia(string $familia): ?string
    {
        return match ($familia) {
            'Pan' => self::LATA,
            'Torta', 'Bocadito' => self::UNIDAD,
            default => null,
        };
    }

    protected $table = 'unidades_medida';

    public $timestamps = false;

    protected $fillable = [
        'codigo',
        'nombre_unidades_medida',
        'equivalencia_unidades',
    ];

    protected function casts(): array
    {
        return [
            'equivalencia_unidades' => 'decimal:2',
        ];
    }

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
