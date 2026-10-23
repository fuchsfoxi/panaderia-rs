<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Panadería RS</title>
    @vite(['resources/css/dashboard.css', 'resources/js/dashboard.js'])
</head>
<body class="pagina-con-sidebar">
    <x-sidebar />

    <main class="dashboard" data-dashboard>
        <header class="dashboard-encabezado" data-entrada style="--orden:0">
            <div>
                <p class="dashboard-etiqueta">Panadería RS</p>
                <h1>Dashboard</h1>
                <p class="dashboard-descripcion">Tu producción de Pan, a la vista.</p>
            </div>
            <nav class="dashboard-accesos" aria-label="Accesos rápidos">
                <a class="dashboard-boton dashboard-boton-principal" href="{{ route('produccion.index') }}">Registrar producción</a>
                <a class="dashboard-boton" href="{{ route('history.index') }}">Ver historial</a>
            </nav>
        </header>

        <form class="dashboard-periodo" action="{{ route('dashboard') }}" method="GET" data-filtros-dashboard>
            <span class="dashboard-periodo-titulo">Consultar periodo</span>
            <div class="dashboard-campo"><label for="dashboard-desde">Desde</label><input type="date" id="dashboard-desde" name="fecha_inicio" value="{{ $inicio }}" required></div>
            <div class="dashboard-campo"><label for="dashboard-hasta">Hasta</label><input type="date" id="dashboard-hasta" name="fecha_fin" value="{{ $fin }}" required></div>
            <button type="submit" class="dashboard-boton dashboard-boton-principal">Consultar</button>
            <a href="{{ route('dashboard') }}" class="dashboard-enlace">Hoy</a>
        </form>
        <p class="solo-lectores" data-estado-consulta role="status"></p><p class="error-filtro" data-error-consulta role="alert" hidden></p>
        <div class="esqueleto-listado" data-esqueleto hidden aria-hidden="true"><span></span><span></span></div>
        <div data-dashboard-contenido tabindex="-1">
        @if($erroresFiltros->isNotEmpty())<div class="dashboard-vacio dashboard-error" role="alert"><p>Revisa el periodo indicado.</p><ul>@foreach($erroresFiltros->all() as $mensaje)<li>{{ $mensaje }}</li>@endforeach</ul></div>@endif
        <section class="dashboard-seccion" aria-labelledby="resumen-hoy" data-entrada style="--orden:1">
            <div class="dashboard-titulo-seccion">
                <h2 id="resumen-hoy">{{ $periodoExplicito ? 'Resumen del periodo' : 'Resumen de hoy' }}</h2>
                <span class="dashboard-fecha">
                    @if($periodoExplicito)
                        <time datetime="{{ $inicio }}">{{ \Illuminate\Support\Carbon::parse($inicio)->format('d/m/Y') }}</time> — <time datetime="{{ $fin }}">{{ \Illuminate\Support\Carbon::parse($fin)->format('d/m/Y') }}</time>
                    @else
                        <time datetime="{{ $fechaHoy->toDateString() }}">{{ $fechaHoy->format('d/m/Y') }}</time>
                    @endif
                </span>
            </div>
            <div class="dashboard-resumen">
                <article class="dashboard-metrica">
                    <h3>Lotes de Pan</h3>
                    <p class="dashboard-valor" data-contador="{{ $totalLotesHoy }}" data-metrica="lotes">{{ number_format($totalLotesHoy, 0, ',', '.') }}</p>
                    <p>{{ $periodoExplicito ? 'Lotes del periodo' : 'Lotes registrados hoy' }}</p>
                </article>
                <article class="dashboard-metrica">
                    <h3>Total de latas</h3>
                    <p class="dashboard-valor" data-contador="{{ $totalLatasHoy }}" data-metrica="latas">{{ number_format($totalLatasHoy, 0, ',', '.') }}</p>
                    <p>Solo Pan registrado por latas</p>
                </article>
            </div>
            @if ($totalLotesHoy === 0)
                <p class="dashboard-vacio" role="status">{{ $periodoExplicito ? 'No hay producción registrada en este periodo.' : 'No hay producción registrada hoy. Puedes comenzar con Registrar producción.' }}</p>
            @endif
        </section>

        <section class="dashboard-seccion dashboard-analisis" aria-labelledby="produccion-turno" data-entrada style="--orden:2">
            <div class="dashboard-titulo-seccion">
                <h2 id="produccion-turno">Producción por turno</h2>
                <span>Pan · {{ $periodoExplicito ? 'Periodo seleccionado' : 'Hoy' }}</span>
            </div>
            <div class="dashboard-distribucion">
                <div class="dashboard-grafico">
                    <h3>Latas por turno</h3>
                    @if($totalLatasHoy > 0)
                        <div class="dashboard-canvas"><canvas data-grafico-turnos aria-hidden="true"></canvas></div>
                        <script type="application/json" data-datos-turnos>@json($produccionPorTurno->values())</script>
                        <p class="dashboard-grafico-error" data-error-grafico role="status" hidden>El gráfico no está disponible. Los valores están en el resumen por turno.</p>
                    @else
                        <div class="dashboard-grafico-vacio"><span aria-hidden="true">↗</span><p>Aún no hay latas para representar.</p></div>
                    @endif
                    <p class="dashboard-nota">Las unidades de Pan se incluyen en los lotes, sin sumarse como latas.</p>
                </div>
            <div class="dashboard-turnos" aria-label="Datos de producción por turno">
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
            </div>
        </section>

        <section class="dashboard-seccion" aria-labelledby="ultimas-producciones" data-entrada style="--orden:3">
            <div class="dashboard-titulo-seccion">
                <h2 id="ultimas-producciones">Últimas producciones</h2>
                <a class="dashboard-enlace" href="{{ route('history.index') }}">Ver historial completo →</a>
            </div>
            <p class="dashboard-nota">{{ $periodoExplicito ? 'Los últimos cinco lotes de Pan del periodo seleccionado.' : 'Los últimos cinco lotes de Pan, ordenados por fecha de producción.' }}</p>
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
                                <th scope="col" class="dashboard-cantidad">Cantidad</th>
                                <th scope="col">Fecha</th>
                                <th scope="col">Registró la sesión</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($ultimasProducciones as $detalle)
                                <tr data-detalle-id="{{ $detalle->id }}">
                                    <th scope="row">{{ $detalle->producto?->nombre_p ?? 'Producto no disponible' }}</th>
                                    <td data-etiqueta="Turno">{{ $detalle->produccion->turno?->nombre_turnos ?? 'Sin turno registrado' }}</td>
                                    <td data-etiqueta="Cantidad" class="dashboard-cantidad">{{ number_format($detalle->cantidad, 0, ',', '.') }} {{ $detalle->unidadMedida?->codigo === 'LATA' ? 'latas' : ($detalle->unidadMedida?->codigo === 'UNIDAD' ? 'unidades' : 'de unidad no identificada') }}</td>
                                    <td data-etiqueta="Fecha"><time datetime="{{ $detalle->produccion->fecha->format('Y-m-d') }}">{{ $detalle->produccion->fecha->format('d/m/Y') }}</time></td>
                                    <td data-etiqueta="Registró la sesión">{{ $detalle->produccion->usuario?->username ?? 'Usuario no disponible' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
        </div>
    </main>
</body>
</html>
