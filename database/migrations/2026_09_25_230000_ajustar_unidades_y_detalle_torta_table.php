<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unidades_medida', function (Blueprint $table) {
            $table->decimal('equivalencia_unidades', 8, 2)->nullable()->change();
        });

        Schema::table('produccion', function (Blueprint $table) {
            $table->text('observaciones')->nullable()->after('fecha');
        });

        Schema::table('detalle_torta', function (Blueprint $table) {
            $table->foreignId('unidad_medida_id')->constrained('unidades_medida')->after('producto_id');
        });

        Schema::table('detalle_pan', function (Blueprint $table) {
            $table->decimal('cantidad', 10, 2)->change();
        });
    }

    public function down(): void
    {
        Schema::table('detalle_pan', function (Blueprint $table) {
            $table->integer('cantidad')->change();
        });

        Schema::table('detalle_torta', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unidad_medida_id');
        });

        Schema::table('produccion', function (Blueprint $table) {
            $table->dropColumn('observaciones');
        });

        Schema::table('unidades_medida', function (Blueprint $table) {
            $table->decimal('equivalencia_unidades', 8, 2)->default(1.00)->change();
        });
    }
};
