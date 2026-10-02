<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['detalle_pan', 'detalle_torta', 'detalle_bocadito'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->text('observacion')->nullable();
            });
        }
    }

    public function down(): void
    {
        // Comprobar las tres tablas antes del primer DROP, para no perder textos
        // ni revertir parcialmente si hay observaciones registradas.
        foreach (['detalle_pan', 'detalle_torta', 'detalle_bocadito'] as $table) {
            if (DB::table($table)->whereNotNull('observacion')->exists()) {
                throw new RuntimeException('No se puede revertir: hay observaciones. Se necesita una estrategia de conservación aprobada.');
            }
        }

        foreach (['detalle_pan', 'detalle_torta', 'detalle_bocadito'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('observacion');
            });
        }
    }
};
