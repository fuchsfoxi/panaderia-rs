@extends('layouts.app')

@section('titulo', 'Dashboard - Panificadora Amazónica')

@push('styles')
    @vite(['resources/css/dashboard.css'])
@endpush

@section('contenido')



<div class="contenido_informacion">

    <div class="encabezado-superior">
        <div class="titulo_inicio">
            <h1>DASHBOARD DE PRODUCCIÓN</h1>
        </div>

        <!-- FILTRO DE FECHA -->
        <div class="filtro-fecha-inicio">
            <div class="filtro-fecha_desde">
                <label for="fecha_inicio">Desde</label>
                <i class="far fa-calendar-alt"></i>
                <input type="date" id="fecha_inicio" name="fecha_inicio">
            </div>

            <div class="filtro_fecha_hasta">
                <label for="fecha_fin">Hasta</label>
                <i class="far fa-calendar-alt"></i>
                <input type="date" id="fecha_fin" name="fecha_fin">
            </div>

            <div class="filtro_fecha_boton">
                <button type="button" id="filtrar">Aplicar</button>
            </div>
        </div>
    </div>

    <div class="encabezado_periodo">
        <strong>HOY</strong>
        Producción | MAÑANA
    </div>

    {{-- MODIFICADO: estas 3 tarjetas tenían numeros fijos (12.5 Coches,
         15 Unidades, 900 Unidades). Ahora cada valor sale de
         ResumenDashboard::tarjetasDeHoy().

         $tarjetas viene del controlador, indexado por nombre de categoría:
         'Pan', 'Torta', 'Bocadito'. --}}
    <div class="fila_categorias">
        {{-- PAN: el total se muestra POR UNIDAD, porque detalle_pan guarda
             una unidad_medida_id por línea y sumar 24 unidad + 3 coche no
             es una operación válida. --}}
        <div class="tarjeta_categoria">
            <img src="{{ asset('images/placeholder-pan.jpg') }}" alt="Pan" class="imagen_categoria">
            <div class="valor_categoria">
                {{-- 'texto' ya viene formateado ("84 unidad" o "5 lata + 2 coche"). --}}
                {{ $tarjetas['Pan']['texto'] }}
            </div>
            <button type="button" class="btn-detalles" data-abrir-modal="modal-pan">Detalles <i class="fas fa-arrow-right"></i></button>
        </div>

        {{-- TORTA: no hay columna 'cantidad' en detalle_torta, 1 registro = 1
             torta, así que se cuenta la cantidad de líneas. El mock decía
             "15 Unidades", lo que contradecía el esquema. --}}
        <div class="tarjeta_categoria">
            <img src="{{ asset('images/placeholder-torta.jpg') }}" alt="Torta" class="imagen_categoria">
            <div class="valor_categoria">
                {{ $tarjetas['Torta']['texto'] }}
            </div>
            <button type="button" class="btn-detalles" data-abrir-modal="modal-torta">Detalles <i class="fas fa-arrow-right"></i></button>
        </div>

        {{-- BOCADITO: tiene 'cantidad' pero no 'unidad_medida_id', así que la
             cantidad ya está en unidades. --}}
        <div class="tarjeta_categoria">
            <img src="{{ asset('images/placeholder-bocadito.jpg') }}" alt="Bocadito" class="imagen_categoria">
            <div class="valor_categoria">
                {{ $tarjetas['Bocadito']['texto'] }}
            </div>
            <button type="button" class="btn-detalles" data-abrir-modal="modal-bocadito">Detalles <i class="fas fa-arrow-right"></i></button>
        </div>
    </div>

    <div class="encabezado_periodo">
        <strong>HOY</strong>
        Producción | TARDE
    </div>

    {{-- MODIFICADO: las 3 cartas tenían un "0" fijo. "Total" ahora es la
         cantidad de líneas de producción de HOY.
         "Pendiente" y "Completada" NO tienen fuente de datos: no existe
         ninguna columna de estado en produccion ni en sus tablas de detalle,
         así que llegan en null y se muestran como "-". --}}
    <div class="cartas-general">
        <div class="carta">
            <h2>Producción Total</h2>
            <p id="produccion_total">{{ $cartas['total'] }}</p>
        </div>

        <div class="carta">
            <h2>Producción Pendiente</h2>
            {{-- Sin dato en la base: se muestra "-" en vez de un 0 inventado. --}}
            <p id="produccion_pendiente">{{ $cartas['pendiente'] ?? '-' }}</p>
        </div>

        <div class="carta">
            <h2>Producción Completada</h2>
            <p id="produccion_completada">{{ $cartas['completada'] ?? '-' }}</p>
        </div>
    </div>

    <div class="graficos">
        <div class="grafico">
            <h2>Producción por Día</h2>
            <canvas id="grafico_dia"></canvas>
        </div>

        <div class="grafico">
            <h2>Producción por Semana</h2>
            <canvas id="grafico_semana"></canvas>
        </div>

        <div class="grafico">
            <h2>Producción por Mes</h2>
            <canvas id="grafico_mes"></canvas>
        </div>
    </div>

</div>

{{-- MODIFICADO: los 3 modales completos estaban escritos a mano con datos
     inventados (productos, cantidades, empleados "Manuel Rojas", "Rosa Díaz",
     "Ana Torres", "Luis Paredes" y "Registrado por rosa.diaz").

     Ahora se recorren $tarjetas['<Categoria>']['lineasDeHoy'], que son las
     líneas de producción reales de HOY de esa categoría.

     NOTA: una línea puede no tener empleados asignados, y produccion NO tiene
     created_at (timestamps = false), así que no se puede mostrar la hora de
     registro: solo el usuario. --}}

<!-- Modal: detalle de Pan -->
<div class="overlay-modal" id="modal-pan">
    <div class="caja-modal">
        <button type="button" class="cerrar-modal" data-cerrar-modal><i class="fas fa-times"></i></button>
        <h2>Detalle de Producción — Pan</h2>
        <p class="subtitulo-modal">Hoy — {{ $tarjetas['Pan']['texto'] }}</p>

        <h3>Desglose por tipo</h3>
        {{-- Un renglón por producto, agrupando las líneas del mismo producto. --}}
        <ul class="lista-desglose">
            @forelse ($tarjetas['Pan']['lineasDeHoy']->groupBy('producto') as $producto => $lineas)
                <li>
                    <span>{{ $producto }}</span>
                    <span>{{ rtrim(rtrim(number_format($lineas->sum('cantidad'), 2, ',', '.'), '0'), ',') }} {{ $lineas->first()->unidad }}</span>
                </li>
            @empty
                <li><span>Sin producción de pan hoy</span><span>0</span></li>
            @endforelse
        </ul>

        <h3>Empleados a cargo</h3>
        <ul class="lista-empleados">
            @forelse ($tarjetas['Pan']['lineasDeHoy']->pluck('empleados')->flatten()->unique(fn ($e) => $e->nombre . $e->rol) as $empleado)
                <li class="chip-empleado"><span>{{ $empleado->nombre }}</span><span class="etiqueta-rol">{{ $empleado->rol }}</span></li>
            @empty
                <li class="chip-empleado"><span>Sin empleados asignados</span></li>
            @endforelse
        </ul>

        <div class="registrado-por">
            <i class="fas fa-user-check"></i>
            @if ($registradoPor = $tarjetas['Pan']['lineasDeHoy']->pluck('usuario')->filter()->unique())
                Registrado por <strong>{{ $registradoPor->implode(', ') }}</strong> — hoy
            @else
                Sin registro de usuario
            @endif
        </div>
    </div>
</div>

<!-- Modal: detalle de Torta -->
<div class="overlay-modal" id="modal-torta">
    <div class="caja-modal">
        <button type="button" class="cerrar-modal" data-cerrar-modal><i class="fas fa-times"></i></button>
        <h2>Detalle de Producción — Torta</h2>
        <p class="subtitulo-modal">{{ $tarjetas['Torta']['texto'] }} registradas hoy</p>

        <h3>Tortas del día</h3>
        {{-- Cada línea de detalle_torta es una torta, con su 'forma' y su
             'foto' (columnas que solo existen en esta tabla). --}}
        <div class="galeria-tortas">
            @forelse ($tarjetas['Torta']['lineasDeHoy'] as $torta)
                <div class="tarjeta-torta-individual">
                    <img src="{{ $torta->foto ?: asset('images/placeholder-torta.jpg') }}" alt="{{ $torta->producto }}">
                    <p class="tipo-torta">{{ $torta->producto }}</p>
                    <p class="forma-torta">{{ ucfirst($torta->forma ?? 'sin definir') }}</p>
                </div>
            @empty
                <p>No se registraron tortas hoy.</p>
            @endforelse
        </div>

        <h3>Empleada a cargo</h3>
        <ul class="lista-empleados">
            @forelse ($tarjetas['Torta']['lineasDeHoy']->pluck('empleados')->flatten()->unique(fn ($e) => $e->nombre . $e->rol) as $empleado)
                <li class="chip-empleado"><span>{{ $empleado->nombre }}</span><span class="etiqueta-rol">{{ $empleado->rol }}</span></li>
            @empty
                <li class="chip-empleado"><span>Sin empleados asignados</span></li>
            @endforelse
        </ul>

        <div class="registrado-por">
            <i class="fas fa-user-check"></i>
            @if ($registradoPor = $tarjetas['Torta']['lineasDeHoy']->pluck('usuario')->filter()->unique())
                Registrado por <strong>{{ $registradoPor->implode(', ') }}</strong> — hoy
            @else
                Sin registro de usuario
            @endif
        </div>
    </div>
</div>

<!-- Modal: detalle de Bocadito -->
<div class="overlay-modal" id="modal-bocadito">
    <div class="caja-modal">
        <button type="button" class="cerrar-modal" data-cerrar-modal><i class="fas fa-times"></i></button>
        <h2>Detalle de Producción — Bocadito</h2>
        <p class="subtitulo-modal">{{ $tarjetas['Bocadito']['texto'] }} registradas hoy</p>

        <h3>Desglose por tipo</h3>
        <ul class="lista-desglose">
            @forelse ($tarjetas['Bocadito']['lineasDeHoy']->groupBy('producto') as $producto => $lineas)
                <li>
                    <span>{{ $producto }}</span>
                    <span>{{ rtrim(rtrim(number_format($lineas->sum('cantidad'), 0, ',', '.'), '0'), ',') }} uds</span>
                </li>
            @empty
                <li><span>Sin producción de bocaditos hoy</span><span>0</span></li>
            @endforelse
        </ul>

        <h3>Empleados a cargo</h3>
        <ul class="lista-empleados">
            @forelse ($tarjetas['Bocadito']['lineasDeHoy']->pluck('empleados')->flatten()->unique(fn ($e) => $e->nombre . $e->rol) as $empleado)
                <li class="chip-empleado"><span>{{ $empleado->nombre }}</span><span class="etiqueta-rol">{{ $empleado->rol }}</span></li>
            @empty
                <li class="chip-empleado"><span>Sin empleados asignados</span></li>
            @endforelse
        </ul>

        <div class="registrado-por">
            <i class="fas fa-user-check"></i>
            @if ($registradoPor = $tarjetas['Bocadito']['lineasDeHoy']->pluck('usuario')->filter()->unique())
                Registrado por <strong>{{ $registradoPor->implode(', ') }}</strong> — hoy
            @else
                Sin registro de usuario
            @endif
        </div>
    </div>
</div>

{{-- MODIFICADO: los 3 gráficos de Chart.js tenían los datos fijos
     (labels y data: [0,0,0] / [0,0,0,0,0,0] / [0,0,0,0]) hardcodeados en
     resources/js/dashboard.js. Ahora el controlador pasa $graficos
     (DashboardController -> ResumenDashboard::graficos()) y el JS lo lee de
     este bloque JSON.

     @json() escapa el contenido, así que es seguro usarlo como <script>. --}}
<script id="datos-graficos" type="application/json">@json($graficos)</script>
@endsection

@push('scripts')
    @vite(['resources/js/dashboard.js'])
@endpush
