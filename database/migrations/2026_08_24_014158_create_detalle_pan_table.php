<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detalle_pan', function (Blueprint $table) {
            $table->id('detalle_pan_id');
            $table->foreignId('produccion_id')->constrained('produccion', 'produccion_id')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos', 'productos_id');
            $table->foreignId('unidad_medida_id')->constrained('unidades_medidas', 'unidades_medidas_id');
            $table->foreignId('turno_id')->constrained('turno', 'turno_id');
            $table->integer('cantidad');
        });

        DB::statement('ALTER TABLE detalle_pan ADD CONSTRAINT chk_detpan_cantidad CHECK (cantidad > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_pan');
    }
};  