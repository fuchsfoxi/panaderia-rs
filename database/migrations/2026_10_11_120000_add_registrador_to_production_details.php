<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['detalle_pan', 'detalle_torta', 'detalle_bocadito'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                // El creador de la sesión no acredita quién registró cada lote histórico.
                $table->foreignId('registrado_por_usuario_id')->nullable()->constrained('usuarios_sistema');
            });
        }
    }

    public function down(): void
    {
        foreach (['detalle_pan', 'detalle_torta', 'detalle_bocadito'] as $tabla) {
            if (DB::table($tabla)->whereNotNull('registrado_por_usuario_id')->exists()) {
                throw new RuntimeException('No se puede revertir: hay registradores de lote. Se necesita una estrategia de conservación aprobada.');
            }
        }
        foreach (['detalle_pan', 'detalle_torta', 'detalle_bocadito'] as $tabla) {
            Schema::table($tabla, fn (Blueprint $table) => $table->dropConstrainedForeignId('registrado_por_usuario_id'));
        }
    }
};
