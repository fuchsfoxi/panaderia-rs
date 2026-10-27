<?php

namespace Tests\Support;

/** Propuesta para ensayos: no aplica grants a ningún servidor. */
class MariaDbRuntimePrivileges
{
    public static function tables(): array
    {
        $permissions = [];
        foreach (['cargos', 'categorias', 'roles', 'roles_produccion', 'turnos', 'unidades_medida'] as $table) {
            $permissions[$table] = ['SELECT'];
        }
        foreach (['empleados', 'grupos_produccion', 'usuarios_sistema', 'productos'] as $table) {
            $permissions[$table] = ['SELECT', 'INSERT', 'UPDATE'];
        }
        foreach (['grupo_integrantes', 'produccion', 'detalle_pan', 'detalle_pan_empleado', 'detalle_torta', 'detalle_torta_empleado', 'detalle_bocadito', 'detalle_bocadito_empleado'] as $table) {
            $permissions[$table] = ['SELECT', 'INSERT', 'DELETE'];
        }
        foreach (['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'] as $table) {
            $permissions[$table] = ['SELECT', 'INSERT', 'UPDATE', 'DELETE'];
        }
        ksort($permissions);

        return $permissions;
    }

    public static function migrationPrivileges(): array
    {
        return ['SELECT', 'INSERT', 'UPDATE', 'DELETE', 'CREATE', 'ALTER', 'DROP', 'INDEX', 'REFERENCES'];
    }
}
