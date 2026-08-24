<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('detalle_bocadito', function (Blueprint $table) {
            $table->id('detalle_bocadito_id');
            $table->foreignId('produccion_id')->constrained('produccion', 'produccion_id');
            $table->foreignId('productos_id')->constrained('productos', 'productos_id');
            $table->integer('cantidad_bocaditos');
            
        });

        DB::statement('ALTER TABLE detalle_bocadito ADD CONSTRAINT detalle_bocadito_cantidad_bocaditos CHECK (cantidad_bocaditos >= 0)');
    }



    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detalle_bocadito');
    }
};
