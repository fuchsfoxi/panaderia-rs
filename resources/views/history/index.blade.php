@extends('layouts.app')

@section('titulo', 'historial')

@push('styles')
    @vite(['resources/css/historial.css'])
@endpush

@section('contenido')
        <div class="pagina-historial">

        <div class="titulo-historal">
            <h1> Historial de Producción </h1>
        </div>


        <div class="filtro-categoria">
            <h2>Tipo de producción</h2>

            <div class="categoria-botones">
                <button type="button" class="btn-categoria" data-categoria="pan">Pan</button>
                <button type="button" class="btn-categoria" data-categoria="torta">Torta</button>
                <button type="button" class="btn-categoria" data-categoria="bocadito">Bocadito</button>
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
        <input type="date" id="fecha_inicio" name="fecha_inicio">
    </div>

    <div class="filtro-fecha-hasta">

    <label for="fecha_fin">Hasta</label>
        <i class="far fa-calendar-alt"></i>
    <input type="date" id="fecha_fin" name="fecha_fin">

    </div>
    </div>


    <div class="sub-titulo">
                Turno
    </div>

    <div class="campo campo-condicional" data-mostrar-en="pan" id="campo-turno">
        <label for="turno">Turno</label>
        <select id="turno" name="turno_id">
        <option value="1">Mañana</option>
        <option value="2">Noche</option>
    </select>
    </div>
</div>

<div class=" btn-filtro"> 
    <button type="button" class="btn-limpiar"> Limpiar </button>
    <button type="button" class="btn-filtrar"> Filtrar </button>
</div>


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
     a la mas antigua).

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
            <a href="#" class="detalles-link">detalles →</a>
        </div>
    </div>
@empty
    {{-- Caso sin datos: mensaje amigable en vez de una lista vacia. --}}
    <div class="carta-historial">
        <div class="info-carta">
            <h3>Aún no hay producción registrada</h3>
            <p>Cuando cargues producción desde el formulario de producción, los registros van a aparecer acá.</p>
        </div>
    </div>
@endforelse

</div>
@endsection

@push('scripts')
    @vite(['resources/js/historial.js'])
@endpush
