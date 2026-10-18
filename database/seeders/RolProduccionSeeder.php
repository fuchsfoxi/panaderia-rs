<?php

namespace Database\Seeders;

use App\Models\RolProduccion;
use Illuminate\Database\Seeder;

class RolProduccionSeeder extends Seeder
{
    public function run(): void
    {
        // Roles operativos; no son cargos ni permisos de acceso.
        // Una producción tendrá exactamente un Maestro y al menos un Ayudante.
        // Practicante es adicional; la composición se valida al registrar producción.
        foreach (RolProduccion::NOMBRES as $nombre) {
            RolProduccion::firstOrCreate(['nombre_roles_produccion' => $nombre]);
        }
    }
}
