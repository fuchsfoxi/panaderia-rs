<?php

namespace App\Actions\Produccion;

use App\Models\Produccion;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToWriteFile;
use Throwable;

class RegistrarProduccionTorta
{
    public function __construct(private RegistrarProduccionPorCantidad $registrar) {}

    public function ejecutar(array $datos, int $usuarioId): Produccion
    {
        $ruta = null;
        try {
            return $this->registrar->ejecutar($datos, $usuarioId, 'Torta', function (array $detalle) use (&$ruta): array {
                if ($foto = $detalle['foto'] ?? null) {
                    // Nombre generado; nunca aceptar una ruta suministrada por el cliente.
                    $ruta = 'produccion/tortas/'.$foto->hashName();
                    if (! Storage::disk('public')->putFileAs(dirname($ruta), $foto, basename($ruta))) {
                        throw UnableToWriteFile::atLocation($ruta);
                    }
                }

                return ['forma' => $detalle['forma'], 'foto' => $ruta];
            });
        } catch (Throwable $exception) {
            // La transacción ya revirtió SQL. Compensar también el archivo recién escrito.
            if ($ruta !== null) {
                try {
                    if (! Storage::disk('public')->delete($ruta)) {
                        report(UnableToDeleteFile::atLocation($ruta));
                    }
                } catch (Throwable $limpieza) {
                    report($limpieza);
                }
            }
            throw $exception;
        }
    }
}
