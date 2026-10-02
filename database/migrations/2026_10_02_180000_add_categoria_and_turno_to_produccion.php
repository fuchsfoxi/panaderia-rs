<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produccion', function (Blueprint $table) {
            $table->foreignId('categoria_id')->nullable()->constrained('categorias');
            $table->foreignId('turno_id')->nullable()->constrained('turnos');
        });
    }

    public function down(): void
    {
        // No perder la clasificación o el turno que ya se hayan asignado.
        if (DB::table('produccion')->whereNotNull('categoria_id')->orWhereNotNull('turno_id')->exists()) {
            throw new RuntimeException('No se puede revertir: hay categorías o turnos de cabecera asignados. Se necesita una estrategia de conservación aprobada.');
        }

        Schema::table('produccion', function (Blueprint $table) {
            $table->dropConstrainedForeignId('turno_id');
            $table->dropConstrainedForeignId('categoria_id');
        });
    }
};
