<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pedido extends Model
{
    protected $table = 'pedidos';

    public $timestamps = false;

    protected $fillable = [
        'nombre_cliente',
        'fecha_registro',
        'fecha_entrega_prometida',
        'entregado',
        'registrado_por_usuario_id',
    ];

    protected function casts(): array
    {
        return [
            'entregado' => 'boolean',
            'fecha_registro' => 'datetime',
            'fecha_entrega_prometida' => 'datetime',
        ];
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'registrado_por_usuario_id');
    }

    public function detallePedidos()
    {
        return $this->hasMany(DetallePedido::class);
    }
}
