<?php

namespace Database\Seeders;

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
            CategoriaSeeder::class,
            TurnoSeeder::class,
            RolProduccionSeeder::class,
            RolSeeder::class,
            CargoSeeder::class,
            EmpleadoSeeder::class,
            UsuarioSeeder::class,
            // ProduccionDesarrolloSeeder es provisional y se ejecuta por separado.
        ]);
    }
}
