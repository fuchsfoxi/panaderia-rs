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
        // Modulo sin implementar: el enlace lleva a una pagina de aviso, no a
        // un 404. Si se implementa, se cambia el destino por el controlador.
        [
            'ruta' => 'pedidos.index',
            'patron' => 'pedidos.*',
            'icono' => 'fa-solid fa-receipt',
            'label' => 'Pedidos',
        ],
    ];
@endphp

{{-- El id lo usa el boton hamburguesa (aria-controls) para apuntar a este
     panel. En menos de 768px se oculta y se abre desde el boton; en
     escritorio se muestra siempre. --}}
<aside class="lateral" id="menu-lateral">
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
            {{-- title: cuando el menu se reduce a solo iconos (tablet, entre
                 769 y 1024px) el texto se oculta y el title queda como nombre
                 accesible y como tooltip del navegador. --}}
            <a href="{{ route($item['ruta']) }}"
               class="lateral__enlace {{ request()->routeIs($item['patron']) ? 'activo' : '' }}"
               title="{{ $item['label'] }}"
               @if (request()->routeIs($item['patron'])) aria-current="page" @endif>
                <i class="lateral__enlace-icono {{ $item['icono'] }}" aria-hidden="true"></i>
                <span class="lateral__enlace-texto">{{ $item['label'] }}</span>
            </a>
        @endforeach
    </nav>

    <div class="lateral__pie">
        <strong>Sesión activa</strong>
        <span class="lateral__usuario">
            {{ auth()->user()?->empleado?->nombre_empleados ?? auth()->user()?->username ?? 'Invitado' }}
        </span>

        {{-- Logout: tiene que ser un POST dentro de un <form>. Un <a href> no
             sirve porque el cierre de sesion cambia el estado del servidor, y
             ademas un GET se podria disparar desde cualquier pagina externa.
             El @csrf es obligatorio. --}}
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="lateral__salir" title="Cerrar sesión">
                <i class="fas fa-sign-out-alt" aria-hidden="true"></i>
                <span class="lateral__salir-texto">Cerrar sesión</span>
            </button>
        </form>
    </div>
</aside>
