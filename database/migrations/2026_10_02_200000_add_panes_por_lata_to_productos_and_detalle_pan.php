<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->integer('panes_por_lata')->nullable();
        });

        Schema::table('detalle_pan', function (Blueprint $table) {
            $table->integer('panes_por_lata_usado')->nullable();
        });
    }

    public function down(): void
    {
        // Revisar ambos campos antes del primer DROP para conservar parámetros
        // y snapshots históricos sin una reversión parcial por datos presentes.
        if (DB::table('productos')->whereNotNull('panes_por_lata')->exists()
            || DB::table('detalle_pan')->whereNotNull('panes_por_lata_usado')->exists()) {
            throw new RuntimeException('No se puede revertir: hay parámetros o snapshots de panes por lata. Se necesita una estrategia de conservación aprobada.');
        }

        Schema::table('detalle_pan', function (Blueprint $table) {
            $table->dropColumn('panes_por_lata_usado');
        });

        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn('panes_por_lata');
        });
    }
};
