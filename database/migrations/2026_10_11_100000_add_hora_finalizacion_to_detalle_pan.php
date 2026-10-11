<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detalle_pan', function (Blueprint $table) {
            // Sin backfill: se desconoce la hora real de los lotes históricos.
            $table->time('hora_finalizacion')->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('detalle_pan')->whereNotNull('hora_finalizacion')->exists()) {
            throw new RuntimeException('No se puede revertir: hay horas de finalización registradas. Se necesita una estrategia de conservación aprobada.');
        }

        Schema::table('detalle_pan', fn (Blueprint $table) => $table->dropColumn('hora_finalizacion'));
    }
};
