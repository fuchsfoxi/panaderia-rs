{{--
    MENU LATERAL REUTILIZABLE
    ------------------------------------------------------------------
    Un solo lugar donde vive la navegacion del sistema. Se incluye desde
    resources/views/layouts/app.blade.php, que a su vez lo usa en TODAS las
    paginas, asi que cambiar un item (o agregar uno) se hace aca y nada mas:
    antes cada vista traia su propio <html> completo y no habia menu
    compartido.

    El item activo se detecta solo con request()->routeIs() usando el patron
    de la ruta, asi que las vistas no tienen que pasarle nada.

    Para agregar una seccion: agregar una fila a $items. Nada mas.
--}}
@php
    $items = [
        [
            'ruta' => 'dashboard',
            'patron' => 'dashboard',
            'icono' => 'fa-solid fa-gauge-high',
            'label' => 'Dashboard',
        ],
        [
            'ruta' => 'produccion.index',
            'patron' => 'produccion.*',
            'icono' => 'fa-solid fa-bread-slice',
            'label' => 'Producción',
        ],
        [
            'ruta' => 'history.index',
            'patron' => 'history.*',
            'icono' => 'fa-solid fa-clock-rotate-left',
            'label' => 'Historial',
        ],
    ];
@endphp

<aside class="lateral">
    <div class="lateral__marca">
        <span class="lateral__marca-icono">
            <i class="fa-solid fa-bread-slice"></i>
        </span>
        <span class="lateral__marca-texto">
            <span class="lateral__marca-nombre">Panificadora</span>
            <span class="lateral__marca-sub">Amazónica</span>
        </span>
    </div>

    <nav class="lateral__nav" aria-label="Navegación principal">
        <span class="lateral__grupo-titulo">Navegación</span>

        @foreach ($items as $item)
            {{-- routeIs() con el patron cubre subrutas: produccion.store
                 tambien marca Produccion como activo. --}}
            <a href="{{ route($item['ruta']) }}"
               class="lateral__enlace {{ request()->routeIs($item['patron']) ? 'activo' : '' }}"
               @if (request()->routeIs($item['patron'])) aria-current="page" @endif>
                <i class="lateral__enlace-icono {{ $item['icono'] }}"></i>
                <span>{{ $item['label'] }}</span>
            </a>
        @endforeach
    </nav>

    <div class="lateral__pie">
        <strong>Sesión activa</strong>
        {{ auth()->user()?->empleado?->nombre_empleados ?? auth()->user()?->username ?? 'Invitado' }}
    </div>
</aside>
