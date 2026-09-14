<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detalle_torta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produccion_id')->constrained('produccion')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos');
            $table->string('forma', 30);
            $table->string('foto', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_torta');
    }
};