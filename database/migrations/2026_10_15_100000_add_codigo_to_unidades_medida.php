<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unidades_medida', function (Blueprint $table) {
            $table->string('codigo', 20)->nullable()->unique();
        });
    }

    public function down(): void
    {
        if (DB::table('unidades_medida')->whereNotNull('codigo')->exists()) {
            throw new RuntimeException('No se puede revertir: existen identidades canónicas. Se requiere una estrategia de conservación aprobada.');
        }
        Schema::table('unidades_medida', function (Blueprint $table) {
            $table->dropUnique(['codigo']);
            $table->dropColumn('codigo');
        });
    }
};
