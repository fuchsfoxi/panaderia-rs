<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detalle_torta_empleado', function (Blueprint $table) {
            $table->foreignId('detalle_torta_id')
                  ->constrained('detalle_torta', 'detalle_torta_id')
                  ->cascadeOnDelete();

            $table->foreignId('empleado_id')
                  ->constrained('empleados', 'empleados_id')
                  ->cascadeOnDelete();

            $table->string('rol', 20);

            // Clave Primaria Compuesta (PK) según ERD
            $table->primary(['detalle_torta_id', 'empleado_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_torta_empleado');
    }
};