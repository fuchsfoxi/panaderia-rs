<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial - Panadería RS</title>
    @vite(['resources/css/historial.css'])
</head>
<body class="pagina-con-sidebar">
    <x-sidebar />
    <main class="pagina-historial">
        <div class="titulo-historal">
            <p class="marca-pagina">Panadería RS</p>
            <h1>Historial de producción</h1>
            <p class="descripcion-pagina">Consulta Pan, Torta y Bocadito por fecha, turno y categoría.</p>
        </div>

        <form class="filtros-historial" action="{{ route('history.index') }}" method="GET" aria-label="Filtros del historial">
            <div class="campos-filtros">
                <div class="campo filtro-categoria">
                    <label for="categoria">Tipo de producción</label>
                    <select id="categoria" name="categoria" @if ($erroresFiltros->has('categoria')) aria-invalid="true" aria-describedby="error-categoria" @endif>
                        <option value="">Todos</option>
                        @foreach (['pan' => 'Pan', 'torta' => 'Torta', 'bocadito' => 'Bocadito'] as $clave => $nombre)
                            <option value="{{ $clave }}" @selected($filtros['categoria'] === $clave)>{{ $nombre }}</option>
                        @endforeach
                    </select>
                    @if ($erroresFiltros->has('categoria'))
                        <p class="error-filtro" id="error-categoria" role="alert">{{ $erroresFiltros->first('categoria') }}</p>
                    @endif
                </div>
                <div class="campo filtro-fecha-desde">
                    <label for="fecha_inicio">Desde</label>
                    <input type="date" id="fecha_inicio" name="fecha_inicio" value="{{ $filtros['fecha_inicio'] }}" @if ($erroresFiltros->has('fecha_inicio')) aria-invalid="true" aria-describedby="error-fecha-inicio" @endif>
                    @if ($erroresFiltros->has('fecha_inicio'))
                        <p class="error-filtro" id="error-fecha-inicio" role="alert">{{ $erroresFiltros->first('fecha_inicio') }}</p>
                    @endif
                </div>
                <div class="campo filtro-fecha-hasta">
                    <label for="fecha_fin">Hasta</label>
                    <input type="date" id="fecha_fin" name="fecha_fin" value="{{ $filtros['fecha_fin'] }}" @if ($erroresFiltros->has('fecha_fin')) aria-invalid="true" aria-describedby="error-fecha-fin" @endif>
                    @if ($erroresFiltros->has('fecha_fin'))
                        <p class="error-filtro" id="error-fecha-fin" role="alert">{{ $erroresFiltros->first('fecha_fin') }}</p>
                    @endif
                </div>
                <div class="campo filtro-turno" id="campo-turno">
                    <label for="turno">Turno</label>
                    <select id="turno" name="turno_id" @if ($erroresFiltros->has('turno_id')) aria-invalid="true" aria-describedby="error-turno" @endif>
                        <option value="">Todos los turnos</option>
                        @foreach ($turnos as $turno)
                            <option value="{{ $turno->id }}" @selected($filtros['turno_id'] === (string) $turno->id)>{{ $turno->nombre_turnos }}</option>
                        @endforeach
                    </select>
                    @if ($erroresFiltros->has('turno_id'))
                        <p class="error-filtro" id="error-turno" role="alert">{{ $erroresFiltros->first('turno_id') }}</p>
                    @endif
                </div>
            </div>
            <div class="btn-filtro">
                <a href="{{ route('history.index') }}" class="btn-limpiar">Limpiar</a>
                <button type="submit" class="btn-filtrar">Filtrar</button>
            </div>
        </form>

        @if ($erroresFiltros->isNotEmpty())
            <p class="estado-vacio" role="alert">Revisa los filtros indicados para consultar el historial.</p>
            @if ($erroresFiltros->has('page'))
                <p class="error-filtro" role="alert">{{ $erroresFiltros->first('page') }}</p>
            @endif
        @else
            <div class="cabecera-resultados">
                <p class="datos-encontrados" role="status">{{ $detalles->total() }} registros encontrados</p>
                @if ($detalles->isNotEmpty())
                    <h2 class="fecha-dia"><time datetime="{{ $detalles->first()->produccion->fecha->format('Y-m-d') }}">{{ $detalles->first()->produccion->fecha->format('d/m/Y') }}</time></h2>
                @endif
            </div>
            @php
                $fechaAnterior = null;
                $latasPorCoche = \App\Models\DetallePan::LATAS_POR_COCHE;
            @endphp
            @forelse ($detalles as $detalle)
                @php
                    $esPan = $detalle instanceof \App\Models\DetallePan;
                    $esTorta = $detalle instanceof \App\Models\DetalleTorta;
                    $familia = $esPan ? 'Pan' : ($esTorta ? 'Torta' : 'Bocadito');
                    $fecha = $detalle->produccion->fecha->format('Y-m-d');
                    $maestros = $detalle->empleados->filter(fn ($empleado) => $empleado->pivot->rolProduccion?->nombre_roles_produccion === 'Maestro');
                    $ayudantes = $detalle->empleados->filter(fn ($empleado) => $empleado->pivot->rolProduccion?->nombre_roles_produccion === 'Ayudante');
                @endphp
                @if ($fecha !== $fechaAnterior)
                    @if ($fechaAnterior !== null)
                        <h2 class="fecha-dia fecha-dia-siguiente"><time datetime="{{ $fecha }}">{{ $detalle->produccion->fecha->format('d/m/Y') }}</time></h2>
                    @endif
                    @php $fechaAnterior = $fecha; @endphp
                @endif
                <article class="carta-historial" data-familia="{{ strtolower($familia) }}" data-detalle-id="{{ $detalle->id }}">
                    <header class="cabecera-carta">
                        <div class="identidad-producto">
                            <span class="etiqueta-familia">{{ $familia }}</span>
                            <h3>{{ $detalle->producto?->nombre_p ?? 'Producto no disponible' }}</h3>
                        </div>
                        <p class="turno-carta"><span>Turno: </span><strong>{{ $detalle->produccion->turno?->nombre_turnos ?? 'Sin turno registrado' }}</strong></p>
                    </header>
                    <dl class="resumen-carta">
                        <div class="dato-cantidad">
                            <dt>Cantidad: </dt><dd>{{ $detalle->cantidad }} <span>{{ $esPan ? ($detalle->unidadMedida?->codigo === 'LATA' ? 'latas' : ($detalle->unidadMedida?->codigo === 'UNIDAD' ? 'unidades' : 'de unidad no identificada')) : ($esTorta ? 'tortas' : 'bocaditos') }}</span></dd>
                        </div>
                        <div>
                            <dt>Maestro: </dt><dd>{{ $maestros->pluck('nombre_empleados')->implode(', ') ?: 'Sin Maestro registrado' }}</dd>
                        </div>
                    </dl>
                    <details class="detalles-produccion">
                        <summary>Ver detalles <span aria-hidden="true">→</span></summary>
                        <div class="contenido-detalles">
                            @if ($esPan && $detalle->unidadMedida?->codigo === 'LATA')
                                <section class="grupo-detalles">
                                    <h4>Producción</h4>
                                    <p>{{ intdiv($detalle->cantidad, $latasPorCoche) }} coches + {{ $detalle->cantidad % $latasPorCoche }} latas adicionales</p>
                                    <p>Total de latas: <strong>{{ $detalle->cantidad }}</strong></p>
                                    <p>Unidad de medida: {{ $detalle->unidadMedida?->nombre_unidades_medida ?? 'Sin unidad registrada' }}</p>
                                    <p>Panes por lata utilizado: <strong>{{ $detalle->panes_por_lata_usado ?? 'Sin factor registrado' }}</strong></p>
                                    @if ($detalle->panes_por_lata_usado > 0)
                                        <p>Total estimado de panes: <strong>{{ $detalle->cantidad * $detalle->panes_por_lata_usado }}</strong></p>
                                    @endif
                                    <p>Hora de finalización: {{ $detalle->hora_finalizacion !== null ? substr($detalle->hora_finalizacion, 0, 5) : 'Sin hora registrada' }}</p>
                                </section>
                            @elseif ($esPan)
                                <section class="grupo-detalles">
                                    <h4>Producción</h4>
                                    <p>Unidad de medida: {{ $detalle->unidadMedida?->codigo === 'UNIDAD' ? 'UNIDAD' : 'Unidad histórica no identificada' }}</p>
                                </section>
                            @elseif ($esTorta)
                                <section class="grupo-detalles">
                                    <h4>Producción</h4>
                                    <p>Forma: {{ $detalle->forma }}</p>
                                    @if ($detalle->foto)
                                        <a href="{{ asset('storage/'.$detalle->foto) }}" target="_blank" rel="noopener">
                                            <img class="foto-torta" src="{{ asset('storage/'.$detalle->foto) }}" alt="Foto de {{ $detalle->producto?->nombre_p ?? 'la torta' }}" loading="lazy" width="480" height="320">
                                        </a>
                                    @endif
                                </section>
                            @endif
                            <section class="grupo-detalles">
                                <h4>Equipo</h4>
                                <p>Ayudantes: {{ $ayudantes->pluck('nombre_empleados')->implode(', ') ?: 'Sin Ayudantes registrados' }}</p>
                            </section>
                            <section class="grupo-detalles">
                                <h4>Información general</h4>
                                @if ($detalle->registrado_por_usuario_id !== null)
                                    <p>Registró el lote: {{ $detalle->usuario?->username ?? 'Usuario no disponible' }}</p>
                                @else
                                    <p>Registrador del lote: desconocido (registro histórico).</p>
                                    <p>Registró la sesión: {{ $detalle->produccion->usuario?->username ?? 'Usuario no disponible' }}</p>
                                @endif
                            </section>
                            @if ($detalle->observacion !== null && $detalle->observacion !== '')
                                <section class="grupo-detalles grupo-observacion">
                                    <h4>Observaciones</h4>
                                    <p class="observacion">{{ $detalle->observacion }}</p>
                                </section>
                            @endif
                        </div>
                    </details>
                </article>
            @empty
                <p class="estado-vacio" role="status">No se encontraron producciones para los filtros seleccionados.</p>
            @endforelse

            @if ($detalles->hasPages())
                <nav class="paginacion-historial" aria-label="Paginación del historial">
                    @if ($detalles->onFirstPage())
                        <span>Anterior</span>
                    @else
                        <a href="{{ $detalles->previousPageUrl() }}" rel="prev">Anterior</a>
                    @endif
                    <span>Página {{ $detalles->currentPage() }} de {{ $detalles->lastPage() }}</span>
                    @if ($detalles->hasMorePages())
                        <a href="{{ $detalles->nextPageUrl() }}" rel="next">Siguiente</a>
                    @else
                        <span>Siguiente</span>
                    @endif
                </nav>
            @endif
        @endif
    </main>
</body>
</html>
