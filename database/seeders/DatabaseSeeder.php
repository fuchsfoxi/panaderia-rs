<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            CargoSeeder::class,
            RolSeeder::class,
            CategoriaSeeder::class,
            TurnoSeeder::class,
            UnidadMedidaSeeder::class,
            RolProduccionSeeder::class,
            EmpleadoSeeder::class,
            UsuarioSeeder::class,
            ProductoSeeder::class,
            ProduccionSeeder::class,
        ]);
    }
}
