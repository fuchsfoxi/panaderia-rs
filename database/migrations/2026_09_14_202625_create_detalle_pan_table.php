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
            $table->id();
            $table->foreignId('produccion_id')->constrained('produccion')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos');
            $table->foreignId('unidad_medida_id')->constrained('unidades_medida');
            $table->foreignId('turno_id')->constrained('turnos');
            $table->integer('cantidad');
        });

        DB::statement('ALTER TABLE detalle_pan ADD CONSTRAINT chk_detpan_cantidad CHECK (cantidad > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_pan');
    }
};