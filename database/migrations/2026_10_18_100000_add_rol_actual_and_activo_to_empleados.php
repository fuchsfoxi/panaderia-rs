<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empleados', function (Blueprint $table) {
            // Se conserva la FK y el valor de cargo de cada empleado existente.
            $table->unsignedBigInteger('cargo_id')->nullable()->change();
            $table->foreignId('rol_produccion_id')->nullable()->constrained('roles_produccion');
            $table->boolean('activo')->default(true);
        });
        // Sin inferir roles desde cargos ni participaciones históricas.
    }

    public function down(): void
    {
        if (DB::table('empleados')->whereNull('cargo_id')->orWhereNotNull('rol_produccion_id')->orWhere('activo', false)->exists()) {
            throw new RuntimeException('No se puede revertir: hay empleados sin cargo, roles actuales o estados inactivos que deben conservarse.');
        }
        Schema::table('empleados', function (Blueprint $table) {
            $table->dropForeign(['rol_produccion_id']);
            $table->dropColumn(['rol_produccion_id', 'activo']);
            $table->unsignedBigInteger('cargo_id')->nullable(false)->change();
        });
    }
};
