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
            // El contrato anterior era una fila por torta física: preservar como 1.
            $table->integer('cantidad')->default(1);
        });
        DB::statement('ALTER TABLE detalle_torta ALTER COLUMN cantidad DROP DEFAULT');
        DB::statement('ALTER TABLE detalle_torta ADD CONSTRAINT chk_dettorta_cantidad CHECK (cantidad > 0)');
    }

    public function down(): void
    {
        if (DB::table('detalle_torta')->where('cantidad', '<>', 1)->exists()) {
            throw new RuntimeException('No se puede revertir: hay cantidades de Torta distintas de uno. Se necesita una estrategia de conservación aprobada.');
        }
        DB::statement('ALTER TABLE detalle_torta DROP CONSTRAINT chk_dettorta_cantidad');
        Schema::table('detalle_torta', fn (Blueprint $table) => $table->dropColumn('cantidad'));
    }
};
