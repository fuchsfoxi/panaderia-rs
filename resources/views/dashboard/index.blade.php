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
                <p class="dashboard-descripcion">Tu producción, a la vista.</p>
            </div>
            <nav class="dashboard-accesos" aria-label="Accesos rápidos">
                <a class="dashboard-boton dashboard-boton-principal" href="{{ route('produccion.index') }}">Registrar producción</a>
                <a class="dashboard-boton" href="{{ route('history.index') }}">Ver historial</a>
            </nav>
        </header>

        @php
            $hoyDashboard = end($datosDashboard['series']['siete_dias'])['fecha'];
            $fechaDashboard = \Illuminate\Support\Carbon::parse($hoyDashboard, 'America/Lima');
        @endphp
        <section class="panel-fijo" aria-labelledby="produccion-hoy">
            <div class="dashboard-titulo-seccion"><div><p class="dashboard-kicker">RESUMEN DIARIO</p><h2 id="produccion-hoy">Producción de hoy</h2></div><time class="dashboard-fecha" datetime="{{ $hoyDashboard }}">{{ $fechaDashboard->format('d/m/Y') }}</time></div>
            <div class="tarjetas-hoy">
                <article class="tarjeta-hoy" data-tarjeta-pan>
                    <div class="tarjeta-cabecera"><h3>Pan</h3><label class="selector-dashboard" hidden data-control-diario> <span class="solo-lectores">Unidad de Pan de hoy</span><select data-unidad-diaria aria-label="Unidad de Pan de hoy"><option value="latas">Latas</option><option value="unidades">Unidades</option></select></label></div>
                    @foreach (['latas' => 'latas', 'unidades' => 'unidades'] as $clave => $nombre)
                        <div data-pan-diario="{{ $clave }}"><p class="tarjeta-numero">{{ number_format($datosDashboard['hoy']['pan'][$clave], 0, ',', '.') }}</p><p class="tarjeta-unidad">{{ $nombre }}@unless($datosDashboard['hoy']['pan'][$clave.'_completas']) · <strong class="aviso-parcial">Total parcial</strong>@endunless</p>@unless($datosDashboard['hoy']['pan'][$clave.'_completas'])<p class="aviso-explicacion">Faltan datos históricos para calcular el total completo.</p>@endunless</div>
                    @endforeach
                </article>
                @foreach (['torta' => ['Tortas', 'unidades'], 'bocadito' => ['Bocaditos', 'unidades'], 'registros' => ['Registros', 'lotes de producción']] as $clave => [$titulo, $unidad])
                    <article class="tarjeta-hoy"><h3>{{ $titulo }}</h3><p class="tarjeta-numero">{{ number_format($datosDashboard['hoy'][$clave], 0, ',', '.') }}</p><p class="tarjeta-unidad">{{ $unidad }}</p></article>
                @endforeach
            </div>
        </section>

        <section class="panel-fijo" aria-labelledby="resumen-mensual">
            <div class="dashboard-titulo-seccion"><div><p class="dashboard-kicker">LO MÁS ELABORADO</p><h2 id="resumen-mensual">Resumen mensual</h2></div><span>{{ $fechaDashboard->format('m/Y') }}</span></div>
            <div class="rankings-dashboard">
                <article class="tarjeta-ranking"><div class="tarjeta-cabecera"><h3>Pan</h3><label class="selector-dashboard" hidden data-control-mensual><span class="solo-lectores">Unidad del ranking mensual de Pan</span><select data-unidad-mensual aria-label="Unidad del ranking mensual de Pan"><option value="latas">Latas</option><option value="unidades">Unidades</option></select></label></div>
                    @foreach (['latas', 'unidades'] as $unidad)
                        @php $ranking = $datosDashboard['mensual']['pan_'.$unidad]; @endphp
                        <div data-pan-mensual="{{ $unidad }}"><p class="ranking-unidad">{{ ucfirst($unidad) }}</p>
                            @if (!$ranking['completo']) <p class="aviso-explicacion" role="status">Clasificación no disponible: faltan datos históricos para calcular {{ $unidad }}.</p>
                            @elseif (count($ranking['productos']) === 0) <p class="ranking-vacio">Sin registros en {{ $unidad }} este mes.</p>
                            @else <ol class="lista-ranking">@foreach ($ranking['productos'] as $producto)<li><span>{{ $producto['producto'] }}</span><strong>{{ number_format($producto['cantidad'], 0, ',', '.') }} <small>{{ $unidad }}</small></strong></li>@endforeach</ol> @endif
                        </div>
                    @endforeach
                </article>
                @foreach (['torta' => 'Tortas', 'bocadito' => 'Bocaditos'] as $familia => $titulo)
                    <article class="tarjeta-ranking"><h3>{{ $titulo }}</h3><p class="ranking-unidad">Unidades</p>
                        @if (count($datosDashboard['mensual'][$familia]) === 0) <p class="ranking-vacio">Sin registros este mes.</p>
                        @else <ol class="lista-ranking">@foreach ($datosDashboard['mensual'][$familia] as $producto)<li><span>{{ $producto['producto'] }}</span><strong>{{ number_format($producto['cantidad'], 0, ',', '.') }} <small>unidades</small></strong></li>@endforeach</ol> @endif
                    </article>
                @endforeach
            </div>
        </section>

        <section class="panel-fijo" aria-labelledby="evolucion-titulo">
            <div class="dashboard-titulo-seccion"><div><p class="dashboard-kicker">REGISTROS POR DÍA</p><h2 id="evolucion-titulo">Evolución de la producción</h2></div><label class="selector-dashboard" hidden data-control-serie><span class="solo-lectores">Período del gráfico</span><select data-serie aria-label="Período del gráfico"><option value="siete_dias">Últimos 7 días</option><option value="mes_actual">Mes actual</option><option value="turnos">Pan por turno del período consultado</option></select></label></div>
            <div class="tarjeta-evolucion"><p class="explicacion-grafico" data-descripcion-serie>Registros diarios por familia. Las fechas futuras del mes son fechas del calendario, no predicciones.</p>
                <div class="leyenda-dashboard" aria-label="Familias"><span><i class="marca-pan" aria-hidden="true"></i>Pan</span><span><i class="marca-torta" aria-hidden="true"></i>Torta</span><span><i class="marca-bocadito" aria-hidden="true"></i>Bocadito</span></div>
                <div class="dashboard-canvas"><canvas data-grafico-turnos aria-hidden="true"></canvas></div>
                <p data-error-grafico role="status" hidden>El gráfico no está disponible. Consulta los datos de la tabla.</p>
                <script type="application/json" data-datos-series>@json($datosDashboard['series'])</script>
                @foreach (['siete_dias' => 'Últimos 7 días', 'mes_actual' => 'Mes actual'] as $periodo => $titulo)
                    <details class="tabla-serie" data-tabla-serie="{{ $periodo }}"><summary>Ver datos: {{ $titulo }}</summary><table><caption>{{ $titulo }}: registros por fecha y familia</caption><thead><tr><th scope="col">Fecha</th><th scope="col">Pan</th><th scope="col">Torta</th><th scope="col">Bocadito</th></tr></thead><tbody>@foreach ($datosDashboard['series'][$periodo] as $dia)<tr><th scope="row"><time datetime="{{ $dia['fecha'] }}">{{ \Illuminate\Support\Carbon::parse($dia['fecha'])->format('d/m/Y') }}</time></th><td>{{ $dia['pan'] }}</td><td>{{ $dia['torta'] }}</td><td>{{ $dia['bocadito'] }}</td></tr>@endforeach</tbody></table></details>
                @endforeach
            </div>
        </section>

        <section class="panel-fijo" aria-labelledby="actividad-fecha">
            <div class="dashboard-titulo-seccion"><div><p class="dashboard-kicker">ACTIVIDAD</p><h2 id="actividad-fecha">Producciones por fecha</h2></div><a class="dashboard-enlace" href="{{ route('history.index') }}">Ver historial completo →</a></div>
            <p class="actividad-nota">Ordenadas por fecha de producción; el orden no indica cuándo se registraron.</p>
            <div class="actividad-lista">@forelse ($datosDashboard['actividad'] as $registro)
                <article class="actividad-fila"><div><span class="actividad-categoria">{{ ucfirst($registro['categoria']) }}</span><h3>{{ $registro['producto'] }}</h3></div><dl><div><dt>Fecha</dt><dd><time datetime="{{ $registro['fecha'] }}">{{ \Illuminate\Support\Carbon::parse($registro['fecha'])->format('d/m/Y') }}</time></dd></div><div><dt>Turno</dt><dd>{{ $registro['turno'] }}</dd></div><div><dt>{{ $registro['hora'] === null ? 'Hora' : $registro['hora_etiqueta'] }}</dt><dd>{{ $registro['hora'] ?? 'Sin hora registrada' }}</dd></div></dl></article>
            @empty <p class="ranking-vacio">Aún no hay producciones registradas.</p> @endforelse</div>
        </section>
        <section class="panel-fijo historico" aria-labelledby="consulta-historica"><div class="dashboard-titulo-seccion"><div><p class="dashboard-kicker">CONSULTA DE PAN</p><h2 id="consulta-historica">Consultar período histórico</h2></div></div><p class="actividad-nota">El filtro actualiza solamente el resumen histórico de Pan.</p>
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
                    <p>Consulta el detalle por turno o muéstralo en el gráfico de evolución.</p>
                    <button type="button" class="dashboard-boton" data-ver-turnos hidden>Ver gráfico por turno</button>
                    <script type="application/json" data-datos-turnos>@json($produccionPorTurno->values())</script>
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
        </section>
    </main>
</body>
</html>
