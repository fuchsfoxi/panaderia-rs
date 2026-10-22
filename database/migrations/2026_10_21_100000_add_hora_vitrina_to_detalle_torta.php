<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detalle_torta', function (Blueprint $table) {
            // Los lotes históricos conservan NULL: su hora real se desconoce.
            $table->time('hora_vitrina')->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('detalle_torta')->whereNotNull('hora_vitrina')->exists()) {
            throw new RuntimeException('No se puede revertir: hay horas de vitrina registradas. Se necesita una estrategia de conservación aprobada.');
        }

        Schema::table('detalle_torta', fn (Blueprint $table) => $table->dropColumn('hora_vitrina'));
    }
};
