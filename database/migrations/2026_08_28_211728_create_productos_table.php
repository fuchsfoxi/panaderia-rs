<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_p', 100);
            $table->date('temporada_fe')->nullable();
            $table->boolean('activo')->default(true);
            $table->foreignId('categoria_id')->constrained('categorias');
            $table->foreignId('unidad_medida_id')->constrained('unidades_medida');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};