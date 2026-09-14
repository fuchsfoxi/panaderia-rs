<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedidos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_cliente', 100)->nullable();
            $table->dateTime('fecha_registro')->useCurrent();
            $table->dateTime('fecha_entrega_prometida');
            $table->boolean('entregado')->default(false);
            $table->foreignId('registrado_por_usuario_id')->constrained('usuarios_sistema');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedidos');
    }
};