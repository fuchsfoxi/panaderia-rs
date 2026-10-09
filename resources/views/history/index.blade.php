<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Producción</title>
    @vite(['resources/css/historial.css'])
</head>
<body>
    <x-sidebar />
    <main class="pagina-historial">
        <div class="titulo-historal">
            <h1>Historial de Producción</h1>
        </div>

        <div class="filtro-categoria">
            <h2>Tipo de producción</h2>
            <div class="categoria-botones">
                <button type="button" class="btn-categoria active" aria-pressed="true">Pan</button>
                <button type="button" class="btn-categoria" disabled>Torta · Próximamente</button>
                <button type="button" class="btn-categoria" disabled>Bocadito · Próximamente</button>
            </div>
        </div>

        <form action="{{ route('history.index') }}" method="GET">
            <div class="filtro-fecha-general">
                <div class="sub-titulo"><h2>Fecha</h2></div>
                <div class="filtro-fecha-principal">
                    <div class="filtro-fecha-desde">
                        <label for="fecha_inicio">Desde</label>
                        <input type="date" id="fecha_inicio" name="fecha_inicio" value="{{ $filtros['fecha_inicio'] }}" @if ($erroresFiltros->has('fecha_inicio')) aria-invalid="true" aria-describedby="error-fecha-inicio" @endif>
                        @if ($erroresFiltros->has('fecha_inicio'))
                            <p class="error-filtro" id="error-fecha-inicio" role="alert">{{ $erroresFiltros->first('fecha_inicio') }}</p>
                        @endif
                    </div>
                    <div class="filtro-fecha-hasta">
                        <label for="fecha_fin">Hasta</label>
                        <input type="date" id="fecha_fin" name="fecha_fin" value="{{ $filtros['fecha_fin'] }}" @if ($erroresFiltros->has('fecha_fin')) aria-invalid="true" aria-describedby="error-fecha-fin" @endif>
                        @if ($erroresFiltros->has('fecha_fin'))
                            <p class="error-filtro" id="error-fecha-fin" role="alert">{{ $erroresFiltros->first('fecha_fin') }}</p>
                        @endif
                    </div>
                </div>
                <div class="campo campo-condicional" id="campo-turno">
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
        @else
            <div class="datos-encontrados" role="status">{{ $detalles->total() }} registros encontrados</div>
            @php
                $fechaAnterior = null;
                $latasPorCoche = \App\Models\DetallePan::LATAS_POR_COCHE;
            @endphp
            @forelse ($detalles as $detalle)
                @php
                    $fecha = $detalle->produccion->fecha->format('Y-m-d');
                    $maestros = $detalle->empleados->filter(fn ($empleado) => $empleado->pivot->rolProduccion?->nombre_roles_produccion === 'Maestro');
                    $ayudantes = $detalle->empleados->filter(fn ($empleado) => $empleado->pivot->rolProduccion?->nombre_roles_produccion === 'Ayudante');
                @endphp
                @if ($fecha !== $fechaAnterior)
                    <div class="fecha-dia"><time datetime="{{ $fecha }}">{{ $detalle->produccion->fecha->format('d/m/Y') }}</time></div>
                    @php $fechaAnterior = $fecha; @endphp
                @endif
                <article class="carta-historial" data-detalle-id="{{ $detalle->id }}">
                    <div class="info-carta">
                        <h3>{{ $detalle->producto?->nombre_p ?? 'Producto no disponible' }}</h3>
                        <p>Turno: {{ $detalle->produccion->turno?->nombre_turnos ?? 'Sin turno registrado' }}</p>
                        <p>Cantidad: {{ $detalle->cantidad }} latas en total</p>
                        <p>{{ intdiv($detalle->cantidad, $latasPorCoche) }} coches + {{ $detalle->cantidad % $latasPorCoche }} latas</p>
                        <p>Maestro: {{ $maestros->pluck('nombre_empleados')->implode(', ') ?: 'Sin Maestro registrado' }}</p>
                        <p>Ayudantes: {{ $ayudantes->pluck('nombre_empleados')->implode(', ') ?: 'Sin Ayudantes registrados' }}</p>
                        <p class="observacion">Observación: {{ $detalle->observacion ?? 'Sin observación' }}</p>
                    </div>
                    <div class="meta-carta">
                        <span>Registró la sesión: {{ $detalle->produccion->usuario?->username ?? 'Usuario no disponible' }}</span>
                    </div>
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
