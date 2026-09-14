<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detalle_bocadito_empleado', function (Blueprint $table) {
            $table->foreignId('detalle_bocadito_id')->constrained('detalle_bocadito')->cascadeOnDelete();
            $table->foreignId('empleado_id')->constrained('empleados');
            $table->foreignId('rol_produccion_id')->constrained('roles_produccion');
            $table->primary(['detalle_bocadito_id', 'empleado_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_bocadito_empleado');
    }
};