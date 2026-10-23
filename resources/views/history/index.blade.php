<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial - Panadería RS</title>
    @vite(['resources/css/historial.css', 'resources/js/historial.js'])
</head>
<body class="pagina-con-sidebar">
    <x-sidebar />
    <main class="pagina-historial">
        <p data-estado-eliminacion class="aviso-historial" role="status" hidden></p>
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

        @if (session('success'))
            <p class="aviso-historial" role="status">{{ session('success') }}</p>
        @endif
        @if (session('error') || $errors->has('confirmado'))
            <p class="aviso-historial aviso-historial-error" role="alert">{{ session('error') ?? $errors->first('confirmado') }}</p>
        @endif

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
                    $fecha = $detalle->produccion->fecha->format('Y-m-d');
                @endphp
                @if ($fecha !== $fechaAnterior)
                    @if ($fechaAnterior !== null)
                        <h2 class="fecha-dia fecha-dia-siguiente"><time datetime="{{ $fecha }}">{{ $detalle->produccion->fecha->format('d/m/Y') }}</time></h2>
                    @endif
                    @php $fechaAnterior = $fecha; @endphp
                @endif
                <x-registro-produccion :detalle="$detalle" :eliminable="true" :filtros="$filtros" :pagina="$detalles->currentPage()" />
            @empty
                <p class="estado-vacio" role="status">No se encontraron producciones para los filtros seleccionados.</p>
            @endforelse

            <x-paginacion-registros :pagina="$detalles" />
        @endif
        @can('administrar-sistema')
            <dialog class="confirmacion-historial" data-confirmacion-eliminar aria-labelledby="titulo-eliminar" aria-describedby="aviso-eliminar identidad-eliminar">
                <h2 id="titulo-eliminar">¿Eliminar este registro?</h2>
                <p id="aviso-eliminar">Se eliminará el registro de producción seleccionado. Esta acción no se puede deshacer.</p>
                <dl id="identidad-eliminar">
                    <dt>Producto</dt><dd data-confirmacion-producto></dd>
                    <dt>Fecha</dt><dd data-confirmacion-fecha></dd>
                    <dt>Turno</dt><dd data-confirmacion-turno></dd>
                </dl>
                <form method="dialog" class="acciones-confirmacion" data-responder-eliminacion>
                    <button type="submit" value="cancelar" class="boton-cancelar-registro" autofocus>Cancelar</button>
                    <button type="submit" value="eliminar" class="boton-eliminar-registro">Eliminar definitivamente</button>
                </form>
            </dialog>
        @endcan
    </main>
</body>
</html>
