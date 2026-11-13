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

    {{-- * DASHBOARD: pantalla de solo lectura con 4 bloques: hoy, resumen mensual, gráfico y actividad --}}
    {{-- ? Todos los datos salen de UNA sola variable: $datosDashboard (la arma App\Queries\ConsultarDashboard) --}}
    {{-- TODO: el controlador aún pasa variables que esta vista ya no usa ($totalLotesHoy, $ultimasProducciones...). Limpiarlo después --}}
    <main class="dashboard" data-dashboard>

        {{-- * ENCABEZADO: título y accesos rápidos --}}
        {{-- ? data-entrada + --orden: el JS los usa para animar la entrada, uno tras otro --}}
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

        {{-- * FECHA DE REFERENCIA: "hoy" = último día de la serie de 7 días (zona America/Lima) --}}
        @php
            $hoyDashboard = end($datosDashboard['series']['siete_dias'])['fecha'];
            $fechaDashboard = \Illuminate\Support\Carbon::parse($hoyDashboard, 'America/Lima');
        @endphp

        {{-- ================= BLOQUE 1: PRODUCCIÓN DE HOY ================= --}}
        {{-- * 4 tarjetas: Pan, Tortas, Bocaditos y Registros --}}
        <section class="panel-fijo" aria-labelledby="produccion-hoy">
            <div class="dashboard-titulo-seccion">
                <div>
                    <p class="dashboard-kicker">RESUMEN DIARIO</p>
                    <h2 id="produccion-hoy">Producción de hoy</h2>
                </div>
                <time class="dashboard-fecha" datetime="{{ $hoyDashboard }}">{{ $fechaDashboard->format('d/m/Y') }}</time>
            </div>

            <div class="tarjetas-hoy">
                {{-- * Tarjeta de PAN: tiene selector Latas/Unidades porque el pan se registra en dos unidades distintas --}}
                {{-- ? El selector nace "hidden" y dashboard.js lo muestra. Sin JS se ven los dos paneles (latas y unidades) --}}
                <article class="tarjeta-hoy" data-tarjeta-pan>
                    <div class="tarjeta-cabecera">
                        <h3>Pan</h3>
                        <label class="selector-dashboard" hidden data-control-diario>
                            <span class="solo-lectores">Unidad de Pan de hoy</span>
                            <select data-unidad-diaria aria-label="Unidad de Pan de hoy">
                                <option value="latas">Latas</option>
                                <option value="unidades">Unidades</option>
                            </select>
                        </label>
                    </div>

                    {{-- * Un panel por unidad; el JS muestra solo el que elige el selector (data-pan-diario) --}}
                    @foreach (['latas' => 'latas', 'unidades' => 'unidades'] as $clave => $nombre)
                        <div data-pan-diario="{{ $clave }}">
                            <p class="tarjeta-numero">{{ number_format($datosDashboard['hoy']['pan'][$clave], 0, ',', '.') }}</p>
                            <p class="tarjeta-unidad">{{ $nombre }}@unless($datosDashboard['hoy']['pan'][$clave.'_completas']) · <strong class="aviso-parcial">Total parcial</strong>@endunless</p>
                            {{-- ! "_completas" = false cuando hay lotes históricos sin unidad/factor: el total NO es confiable --}}
                            @unless($datosDashboard['hoy']['pan'][$clave.'_completas'])
                                <p class="aviso-explicacion">Faltan datos históricos para calcular el total completo.</p>
                            @endunless
                        </div>
                    @endforeach
                </article>

                {{-- * Tortas, Bocaditos y Registros comparten el mismo molde: [clave => [título, unidad]] --}}
                @foreach (['torta' => ['Tortas', 'unidades'], 'bocadito' => ['Bocaditos', 'unidades'], 'registros' => ['Registros', 'lotes de producción']] as $clave => [$titulo, $unidad])
                    <article class="tarjeta-hoy">
                        <h3>{{ $titulo }}</h3>
                        <p class="tarjeta-numero">{{ number_format($datosDashboard['hoy'][$clave], 0, ',', '.') }}</p>
                        <p class="tarjeta-unidad">{{ $unidad }}</p>
                    </article>
                @endforeach
            </div>
        </section>

        {{-- ================= BLOQUE 2: RESUMEN MENSUAL ================= --}}
        {{-- * Top de productos más elaborados del mes, uno por familia --}}
        <section class="panel-fijo" aria-labelledby="resumen-mensual">
            <div class="dashboard-titulo-seccion">
                <div>
                    <p class="dashboard-kicker">LO MÁS ELABORADO</p>
                    <h2 id="resumen-mensual">Resumen mensual</h2>
                </div>
                <span>{{ $fechaDashboard->format('m/Y') }}</span>
            </div>

            <div class="rankings-dashboard">
                {{-- * Ranking de PAN: igual que arriba, un panel por unidad y un selector independiente --}}
                {{-- ? Este selector NO está sincronizado con el de "Producción de hoy": cada uno controla solo su tarjeta --}}
                <article class="tarjeta-ranking">
                    <div class="tarjeta-cabecera">
                        <h3>Pan</h3>
                        <label class="selector-dashboard" hidden data-control-mensual>
                            <span class="solo-lectores">Unidad del ranking mensual de Pan</span>
                            <select data-unidad-mensual aria-label="Unidad del ranking mensual de Pan">
                                <option value="latas">Latas</option>
                                <option value="unidades">Unidades</option>
                            </select>
                        </label>
                    </div>

                    @foreach (['latas', 'unidades'] as $unidad)
                        @php $ranking = $datosDashboard['mensual']['pan_'.$unidad]; @endphp
                        <div data-pan-mensual="{{ $unidad }}">
                            <p class="ranking-unidad">{{ ucfirst($unidad) }}</p>
                            {{-- * Tres casos: datos incompletos, mes sin registros, o lista normal --}}
                            @if (!$ranking['completo'])
                                <p class="aviso-explicacion" role="status">Clasificación no disponible: faltan datos históricos para calcular {{ $unidad }}.</p>
                            @elseif (count($ranking['productos']) === 0)
                                <p class="ranking-vacio">Sin registros en {{ $unidad }} este mes.</p>
                            @else
                                <ol class="lista-ranking">
                                    @foreach ($ranking['productos'] as $producto)
                                        <li>
                                            <span>{{ $producto['producto'] }}</span>
                                            <strong>{{ number_format($producto['cantidad'], 0, ',', '.') }} <small>{{ $unidad }}</small></strong>
                                        </li>
                                    @endforeach
                                </ol>
                            @endif
                        </div>
                    @endforeach
                </article>

                {{-- * Ranking de TORTAS y BOCADITOS: una sola unidad, no necesitan selector --}}
                @foreach (['torta' => 'Tortas', 'bocadito' => 'Bocaditos'] as $familia => $titulo)
                    <article class="tarjeta-ranking">
                        <h3>{{ $titulo }}</h3>
                        <p class="ranking-unidad">Unidades</p>
                        @if (count($datosDashboard['mensual'][$familia]) === 0)
                            <p class="ranking-vacio">Sin registros este mes.</p>
                        @else
                            <ol class="lista-ranking">
                                @foreach ($datosDashboard['mensual'][$familia] as $producto)
                                    <li>
                                        <span>{{ $producto['producto'] }}</span>
                                        <strong>{{ number_format($producto['cantidad'], 0, ',', '.') }} <small>unidades</small></strong>
                                    </li>
                                @endforeach
                            </ol>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>

        {{-- ================= BLOQUE 3: EVOLUCIÓN (GRÁFICO) ================= --}}
        {{-- * Gráfico de barras dibujado por dashboard.js con Chart.js; aquí solo va el "esqueleto" y los datos --}}
        {{-- ? El gráfico cuenta REGISTROS (lotes) por día, no cantidades: 2 lotes de pan = barra de 2, sean 5 o 50 latas --}}
        {{-- TODO: decidir con la dueña si prefiere ver cantidades (latas/unidades) en vez de número de registros --}}
        <section class="panel-fijo" aria-labelledby="evolucion-titulo">
            <div class="dashboard-titulo-seccion">
                <div>
                    <p class="dashboard-kicker">REGISTROS POR DÍA</p>
                    <h2 id="evolucion-titulo">Evolución de la producción</h2>
                </div>
                {{-- * Selector de período: el JS lo muestra y redibuja el gráfico al cambiar --}}
                <label class="selector-dashboard" hidden data-control-serie>
                    <span class="solo-lectores">Período del gráfico</span>
                    <select data-serie aria-label="Período del gráfico">
                        <option value="siete_dias">Últimos 7 días</option>
                        <option value="mes_actual">Mes actual</option>
                    </select>
                </label>
            </div>

            <div class="tarjeta-evolucion">
                <p class="explicacion-grafico" data-descripcion-serie>Registros diarios por familia. Las fechas futuras del mes son fechas del calendario, no predicciones.</p>

                {{-- * Leyenda propia (círculo / cuadrado / triángulo): las formas ayudan a distinguir sin depender solo del color --}}
                <div class="leyenda-dashboard" aria-label="Familias">
                    <span><i class="marca-pan" aria-hidden="true"></i>Pan</span>
                    <span><i class="marca-torta" aria-hidden="true"></i>Torta</span>
                    <span><i class="marca-bocadito" aria-hidden="true"></i>Bocadito</span>
                </div>

                {{-- ? El nombre data-grafico-turnos es histórico (antes mostraba turnos). Se conserva porque DashboardTest lo comprueba --}}
                <div class="dashboard-canvas"><canvas data-grafico-turnos aria-hidden="true"></canvas></div>
                <p data-error-grafico role="status" hidden>El gráfico no está disponible. Consulta los datos de la tabla.</p>

                {{-- * Los datos viajan en un <script type="application/json"> y dashboard.js los lee con JSON.parse --}}
                <script type="application/json" data-datos-series>@json($datosDashboard['series'])</script>

                {{-- * Tabla alternativa con los mismos datos (accesibilidad y respaldo si falla el gráfico) --}}
                {{-- ? El JS muestra solo la tabla del período elegido; sin JS se ven las dos --}}
                @foreach (['siete_dias' => 'Últimos 7 días', 'mes_actual' => 'Mes actual'] as $periodo => $titulo)
                    <details class="tabla-serie" data-tabla-serie="{{ $periodo }}">
                        <summary>Ver datos: {{ $titulo }}</summary>
                        <table>
                            <caption>{{ $titulo }}: registros por fecha y familia</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Fecha</th>
                                    <th scope="col">Pan</th>
                                    <th scope="col">Torta</th>
                                    <th scope="col">Bocadito</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($datosDashboard['series'][$periodo] as $dia)
                                    <tr>
                                        <th scope="row"><time datetime="{{ $dia['fecha'] }}">{{ \Illuminate\Support\Carbon::parse($dia['fecha'])->format('d/m/Y') }}</time></th>
                                        <td>{{ $dia['pan'] }}</td>
                                        <td>{{ $dia['torta'] }}</td>
                                        <td>{{ $dia['bocadito'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </details>
                @endforeach
            </div>
        </section>

        {{-- ================= BLOQUE 4: ACTIVIDAD RECIENTE ================= --}}
        {{-- * Últimas producciones de TODAS las familias (pan, torta y bocadito) --}}
        {{-- ? Ordenadas por fecha de producción, no por cuándo se registraron --}}
        <section class="panel-fijo" aria-labelledby="actividad-fecha">
            <div class="dashboard-titulo-seccion">
                <div>
                    <p class="dashboard-kicker">ACTIVIDAD</p>
                    <h2 id="actividad-fecha">Producciones por fecha</h2>
                </div>
                <a class="dashboard-enlace" href="{{ route('history.index') }}">Ver historial completo →</a>
            </div>
            <p class="actividad-nota">Ordenadas por fecha de producción; el orden no indica cuándo se registraron.</p>

            <div class="actividad-lista">
                @forelse ($datosDashboard['actividad'] as $registro)
                    <article class="actividad-fila">
                        <div>
                            <span class="actividad-categoria">{{ ucfirst($registro['categoria']) }}</span>
                            <h3>{{ $registro['producto'] }}</h3>
                        </div>
                        <dl>
                            <div><dt>Fecha</dt><dd><time datetime="{{ $registro['fecha'] }}">{{ \Illuminate\Support\Carbon::parse($registro['fecha'])->format('d/m/Y') }}</time></dd></div>
                            <div><dt>Turno</dt><dd>{{ $registro['turno'] }}</dd></div>
                            {{-- ? La etiqueta cambia según la familia: "Hora de finalización" (pan) u "Hora de vitrina" (torta) --}}
                            <div>
                                <dt>{{ $registro['hora'] === null ? 'Hora' : $registro['hora_etiqueta'] }}</dt>
                                <dd>{{ $registro['hora'] ?? 'Sin hora registrada' }}</dd>
                            </div>
                        </dl>
                    </article>
                @empty
                    <p class="ranking-vacio">Aún no hay producciones registradas.</p>
                @endforelse
            </div>
        </section>

        {{-- ! Aquí estaba "Consulta de Pan" (filtro por período, resumen, producción por turno, últimas producciones) --}}
        {{-- ! Se eliminó porque repetía este mismo dashboard. Para filtrar por fecha y turno se usa el Historial --}}
    </main>
</body>
</html>