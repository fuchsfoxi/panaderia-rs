<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios_sistema', function (Blueprint $table) {
            $table->dropForeign(['empleado_id']);
        });
        Schema::table('usuarios_sistema', function (Blueprint $table) {
            // change no elimina el índice UNIQUE existente.
            $table->unsignedBigInteger('empleado_id')->nullable()->change();
            $table->foreign('empleado_id')->references('id')->on('empleados');
            $table->foreignId('turno_id')->nullable()->constrained('turnos');
            $table->boolean('activo')->default(true);
        });
    }

    public function down(): void
    {
        if (DB::table('usuarios_sistema')->whereNull('empleado_id')->orWhereNotNull('turno_id')->orWhere('activo', false)->exists()) {
            throw new RuntimeException('No se puede revertir: hay cuentas compartidas, turnos asignados o cuentas inactivas. Se requiere una estrategia de conservación.');
        }
        Schema::table('usuarios_sistema', function (Blueprint $table) {
            $table->dropForeign(['empleado_id']);
            $table->dropForeign(['turno_id']);
        });
        Schema::table('usuarios_sistema', function (Blueprint $table) {
            $table->unsignedBigInteger('empleado_id')->nullable(false)->change();
            $table->foreign('empleado_id')->references('id')->on('empleados');
            $table->dropColumn(['turno_id', 'activo']);
        });
    }
};
