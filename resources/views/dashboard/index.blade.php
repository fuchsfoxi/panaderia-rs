<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Panadería RS</title>
    @vite(['resources/css/dashboard.css'])
</head>
<body class="pagina-con-sidebar">
    <x-sidebar />

    <main class="dashboard">
        <header class="dashboard-encabezado">
            <div>
                <p class="dashboard-etiqueta">Panadería RS</p>
                <h1>Dashboard</h1>
                <p class="dashboard-descripcion">Resumen de los lotes y las latas de Pan.</p>
            </div>
            <nav class="dashboard-accesos" aria-label="Accesos rápidos">
                <a class="dashboard-boton dashboard-boton-principal" href="{{ route('produccion.index') }}">Registrar producción</a>
                <a class="dashboard-boton" href="{{ route('history.index') }}">Ver historial</a>
            </nav>
        </header>

        <section class="dashboard-seccion" aria-labelledby="resumen-hoy">
            <div class="dashboard-titulo-seccion">
                <h2 id="resumen-hoy">Resumen de hoy</h2>
                <time class="dashboard-fecha" datetime="{{ $fechaHoy->toDateString() }}">{{ $fechaHoy->format('d/m/Y') }}</time>
            </div>
            <div class="dashboard-resumen">
                <article class="dashboard-metrica">
                    <h3>Lotes de Pan</h3>
                    <p class="dashboard-valor" data-metrica="lotes">{{ number_format($totalLotesHoy, 0, ',', '.') }}</p>
                    <p>Lotes registrados hoy</p>
                </article>
                <article class="dashboard-metrica">
                    <h3>Total de latas</h3>
                    <p class="dashboard-valor" data-metrica="latas">{{ number_format($totalLatasHoy, 0, ',', '.') }}</p>
                    <p>Cantidad producida hoy</p>
                </article>
            </div>
            @if ($totalLotesHoy === 0)
                <p class="dashboard-vacio" role="status">No hay producción registrada hoy. Puedes comenzar con Registrar producción.</p>
            @endif
        </section>

        <section class="dashboard-seccion" aria-labelledby="produccion-turno">
            <div class="dashboard-titulo-seccion">
                <h2 id="produccion-turno">Producción por turno</h2>
                <span>Pan · Hoy</span>
            </div>
            <div class="dashboard-turnos">
                @forelse ($produccionPorTurno as $turno)
                    <article class="dashboard-turno">
                        <h3>{{ $turno['nombre'] }}</h3>
                        <dl>
                            <div><dt>Lotes</dt><dd>{{ number_format($turno['lotes'], 0, ',', '.') }}</dd></div>
                            <div><dt>Latas</dt><dd>{{ number_format($turno['latas'], 0, ',', '.') }}</dd></div>
                        </dl>
                    </article>
                @empty
                    <p class="dashboard-vacio">No hay turnos disponibles.</p>
                @endforelse
            </div>
        </section>

        <section class="dashboard-seccion" aria-labelledby="ultimas-producciones">
            <div class="dashboard-titulo-seccion">
                <h2 id="ultimas-producciones">Últimas producciones</h2>
                <a class="dashboard-enlace" href="{{ route('history.index') }}">Ver historial completo →</a>
            </div>
            <p class="dashboard-nota">Los últimos cinco lotes de Pan, ordenados por fecha de producción.</p>
            @if ($ultimasProducciones->isEmpty())
                <p class="dashboard-vacio" role="status">Aún no hay lotes de Pan registrados.</p>
            @else
                <p class="dashboard-ayuda-tabla">Desliza la tabla para ver todas las columnas.</p>
                <div class="dashboard-tabla-contenedor" role="region" aria-label="Últimos lotes de Pan" tabindex="0">
                    <table class="dashboard-tabla">
                        <thead>
                            <tr>
                                <th scope="col">Producto</th>
                                <th scope="col">Turno</th>
                                <th scope="col" class="dashboard-cantidad">Cantidad (latas)</th>
                                <th scope="col">Fecha</th>
                                <th scope="col">Registró la sesión</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($ultimasProducciones as $detalle)
                                <tr data-detalle-id="{{ $detalle->id }}">
                                    <th scope="row">{{ $detalle->producto?->nombre_p ?? 'Producto no disponible' }}</th>
                                    <td>{{ $detalle->produccion->turno?->nombre_turnos ?? 'Sin turno registrado' }}</td>
                                    <td class="dashboard-cantidad">{{ number_format($detalle->cantidad, 0, ',', '.') }} latas</td>
                                    <td><time datetime="{{ $detalle->produccion->fecha->format('Y-m-d') }}">{{ $detalle->produccion->fecha->format('d/m/Y') }}</time></td>
                                    <td>{{ $detalle->produccion->usuario?->username ?? 'Usuario no disponible' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </main>
</body>
</html>
