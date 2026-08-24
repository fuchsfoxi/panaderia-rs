<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detalle_pan_empleado', function (Blueprint $table) {
            $table->foreignId('detalle_pan_id')
                  ->constrained('detalle_pan', 'detalle_pan_id')
                  ->cascadeOnDelete();

            $table->foreignId('empleado_id')
                  ->constrained('empleados', 'empleados_id')
                  ->cascadeOnDelete();

            $table->string('rol', 20);

            // Clave Primaria Compuesta (PK) según ERD
            $table->primary(['detalle_pan_id', 'empleado_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_pan_empleado');
    }
};