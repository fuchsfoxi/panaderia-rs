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
        Schema::create('detalle_torta', function (Blueprint $table) {
            $table->id('detalle_torta_id');
            $table->foreignId('produccion_id')->constrained('produccion', 'produccion_id');
            $table->foreignId('productos_id')->constrained('productos', 'productos_id');
            $table->string('forma', 50);
            $table->string('foto', 255);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detalle_torta');
    }
};
