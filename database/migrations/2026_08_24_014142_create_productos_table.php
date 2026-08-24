<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id('productos_id');
            $table->string('nombre_producto', 50);
            $table->date('temporada_fecha')->nullable();
            $table->boolean('activo')->default(true);
            $table->foreignId('categorias_id')->constrained('categorias', 'categorias_id');
            $table->foreignId('unidades_medidas_id')->constrained('unidades_medidas', 'unidades_medidas_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
