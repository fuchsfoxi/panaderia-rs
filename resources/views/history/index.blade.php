@extends('layouts.app')

@section('titulo', 'Historial')

@push('styles')
    @vite(['resources/css/historial.css'])
@endpush

@section('contenido')
        <div class="pagina-historial">

    <div class="titulo-historal">
        <h1> Historial de Producción </h1>
    </div>

    {{-- ==========================================================================
         FILTROS
         --------------------------------------------------------------------------
         Se filtering con parametros GET normales (?categoria=pan&desde=...),
         no con AJAX. Cada recarga vuelve a traer los resultados del servidor,
         asi que el boton "atras" del celular conserva el filtro aplicado y el
         filtro se puede compartir por enlace.

         El <form> envuelve TODOS los controles, por eso el boton de categoria
         y el de "Filtrar" mandan los mismos datos. El boton de categoria es
         type="button" y lo activa historial.js, que pone el valor en el input
         oculto y envia el formulario. Sin JavaScript siguen funcionando las
         fechas y el turno con el boton "Filtrar".
         ========================================================================== --}}
    <form action="{{ route('history.index') }}" method="GET" id="formulario-filtros">

        {{-- Categoria seleccionada. La escribe historial.js al tocar un
             boton, y llega desde la URL cuando se recarga o se comparte el
             enlace. --}}
        <input type="hidden" name="categoria" id="filtro-categoria"
               value="{{ $filtros['categoria'] ?? 'pan' }}">

        <div class="filtro-categoria">
            <h2>Tipo de producción</h2>

            <div class="categoria-botones">
                {{-- La clase "active" la pone el servidor segun el filtro
                     aplicado, para que el boton se vea marcado ya al cargar y
                     no solo despues de que corra el JS. --}}
                <button type="button" class="btn-categoria {{ ($filtros['categoria'] ?? 'pan') === 'pan' ? 'active' : '' }}"
                        data-categoria="pan">Pan</button>
                <button type="button" class="btn-categoria {{ $filtros['categoria'] === 'torta' ? 'active' : '' }}"
                        data-categoria="torta">Torta</button>
                <button type="button" class="btn-categoria {{ $filtros['categoria'] === 'bocadito' ? 'active' : '' }}"
                        data-categoria="bocadito">Bocadito</button>
            </div>
        </div>

        <div class="filtro-fecha-general">

            <div class="sub-titulo">
                <h2>Fecha</h2>
            </div>

            <div class="filtro-fecha-principal">

                <div class="filtro-fecha-desde">
                    <label for="fecha_inicio">Desde</label>
                    <i class="far fa-calendar-alt"></i>
                    <input type="date" id="fecha_inicio" name="desde" value="{{ $filtros['desde'] }}">
                </div>

                <div class="filtro-fecha-hasta">
                    <label for="fecha_fin">Hasta</label>
                    <i class="far fa-calendar-alt"></i>
                    <input type="date" id="fecha_fin" name="hasta" value="{{ $filtros['hasta'] }}">
                </div>
            </div>

            <div class="sub-titulo">
                Turno
            </div>

            {{-- El turno solo se muestra al filtrar por PAN, porque es la unica
                 categoria cuya tabla de detalle tiene columna de turno. --}}
            <div class="campo campo-condicional" data-mostrar-en="pan" id="campo-turno">
                <label for="turno">Turno</label>
                <select id="turno" name="turno_id">
                    <option value="">-- Todos los turnos --</option>
                    {{-- MODIFICADO: antes las <option> "1 = Mañana / 2 = Noche"
                         estaban fijas en el HTML. Ahora salen de la tabla turnos
                         y el value es el id real. --}}
                    @foreach ($turnos as $turno)
                        <option value="{{ $turno->id }}"
                            @selected((int) $filtros['turno_id'] === (int) $turno->id)>
                            {{ $turno->nombre_turnos }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="btn-filtro">
            {{-- Limpiar es un <a> a la pagina sin filtros: funciona sin JS y
                 no necesita el boton "reset" del formulario. --}}
            <a href="{{ route('history.index') }}" class="btn-limpiar"> Limpiar </a>
            <button type="submit" class="btn-filtrar"> Filtrar </button>
        </div>
    </form>

    {{-- Rango de fechas al reves: lo avisa el controlador y se ignoran las
         fechas, en vez de mostrar una lista vacia sin explicacion. --}}
    @if ($filtros['error'])
        <div class="alerta alerta-error" role="alert">
            <i class="fas fa-circle-exclamation"></i> {{ $filtros['error'] }}
        </div>
    @endif

{{-- MODIFICADO: el total ahora viene del controlador, no del numero fijo
     "23 registros encontrados" que estaba hardcodeado en el HTML. --}}
<div class="datos-encontrados">
    <i class="far fa-clipboard"></i> {{ $totalRegistros }} registros encontrados
</div>

{{-- MODIFICADO: este bloque reemplaza los 2 registros de prueba fijos
     ("20 OCT 2026 / Pan carioco / 1 coche / rosa" y "19 OCT 2026 / ...")
     que estaban escritos a mano en el HTML.

     Ahora se recorre $registros, que HistorialController obtiene de la
     base (App\Consultas\LineasProduccion normaliza pan + torta +
     bocadito en una sola coleccion, ordenada de la fecha mas reciente
     a la mas antigua) y ya viene filtrada.

     Cada $registro es un objeto con claves fijas: fecha, producto,
     categoria, cantidad, unidad, turno, forma, foto, usuario. --}}
@forelse ($registros as $registro)
    {{-- Cabecera de fecha. Se imprime UNA vez por dia: cuando la fecha de
         la linea actual es distinta de la de la linea anterior. --}}
    @if ($loop->first || $registro->fecha !== $registros[$loop->index - 1]->fecha)
        <div class="fecha-dia">
            {{ strtoupper(date('d M Y', strtotime($registro->fecha))) }}
        </div>
    @endif

    <div class="carta-historial">
        {{-- Solo las tortas tienen columna 'foto'. Para pan y bocadito no
             hay imagen guardada, asi que no se renderiza el <img>
             (la URL via.placeholder.com del mock era un servicio muerto). --}}
        @if ($registro->foto)
            <img src="{{ $registro->foto }}" alt="{{ $registro->producto }}" class="foto-producto">
        @endif

        <div class="info-carta">
            <h3>{{ $registro->producto }}</h3>
            @if ($registro->cantidad !== null)
                {{-- Pan y bocadito llevan cantidad + unidad de medida. --}}
                <p>Cantidad: {{ rtrim(rtrim(number_format($registro->cantidad, 2, ',', '.'), '0'), ',') }}
                    {{ $registro->unidad }}</p>
            @else
                {{-- Una torta es un registro, sin cantidad. Se muestra la
                     forma y, si hay, el turno no aplica. --}}
                <p>1 {{ strtolower($registro->categoria) }}@if ($registro->forma) {{ $registro->forma }}@endif</p>
            @endif
        </div>

        <div class="meta-carta">
            <span><i class="far fa-user"></i> ingreso: {{ $registro->usuario ?? 'sistema' }}</span>
        </div>
    </div>
@empty
    {{-- Sin datos. El mensaje cambia segun haya filtros o no: si hay filtros
         y no hay resultados, el problema no es que falten datos. --}}
    <div class="carta-historial">
        <div class="info-carta">
            @if ($hayFiltros)
                <h3>Ningún registro coincide con el filtro</h3>
                <p>Probá con otras fechas, otro tipo de producción o quitá el filtro de turno.</p>
                <a href="{{ route('history.index') }}" class="detalles-link">
                    Quitar los filtros <i class="fas fa-arrow-right" aria-hidden="true"></i>
                </a>
            @else
                <h3>Aún no hay producción registrada</h3>
                <p>Cuando cargues producción desde el formulario de producción, los registros van a aparecer acá.</p>
            @endif
        </div>
    </div>
@endforelse

</div>
@endsection

@push('scripts')
    @vite(['resources/js/historial.js'])
@endpush
