<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grupos_produccion', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->foreignId('categoria_id')->constrained('categorias')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('maestro_id')->constrained('empleados')->restrictOnDelete()->restrictOnUpdate();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('grupo_integrantes', function (Blueprint $table) {
            $table->foreignId('grupo_id')->constrained('grupos_produccion')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('empleado_id')->constrained('empleados')->restrictOnDelete()->restrictOnUpdate();
            $table->primary(['grupo_id', 'empleado_id']);
        });
    }

    public function down(): void
    {
        if (DB::table('grupos_produccion')->exists() || DB::table('grupo_integrantes')->exists()) {
            throw new RuntimeException('No se puede revertir: hay grupos que deben conservarse.');
        }

        Schema::dropIfExists('grupo_integrantes');
        Schema::dropIfExists('grupos_produccion');
    }
};
