<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class GrupoIntegrante extends Pivot
{
    protected $table = 'grupo_integrantes';

    public $timestamps = false;

    public $incrementing = false;

    protected $foreignKey = 'grupo_id';

    protected $relatedKey = 'empleado_id';

    protected $fillable = ['grupo_id', 'empleado_id'];

    public function grupo(): BelongsTo
    {
        return $this->belongsTo(GrupoProduccion::class, 'grupo_id');
    }

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class, 'empleado_id');
    }
}
