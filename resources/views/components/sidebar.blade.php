{{-- Navegación provisional compartida por las páginas protegidas. --}}
<aside class="sidebar">

    <div class="sidebar-perfil">
        <span class="sidebar-nombre">{{ auth()->user()?->empleado?->nombre_empleados ?? auth()->user()?->username ?? 'Usuario' }}</span>
    </div>

    <nav class="sidebar-nav" aria-label="Navegación principal">

        <a href="{{ route('dashboard') }}"
           class="sidebar-link {{ request()->routeIs('dashboard') ? 'activo' : '' }}"
           @if(request()->routeIs('dashboard')) aria-current="page" @endif>
            Dashboard
        </a>

        <a href="{{ route('produccion.index') }}"
           class="sidebar-link {{ request()->routeIs('produccion.*') ? 'activo' : '' }}"
           @if(request()->routeIs('produccion.*')) aria-current="page" @endif>
            Producción
        </a>

        {{-- Pedidos se oculta hasta que exista una ruta funcional. --}}

        <a href="{{ route('history.index') }}"
           class="sidebar-link {{ request()->routeIs('history.*') ? 'activo' : '' }}"
           @if(request()->routeIs('history.*')) aria-current="page" @endif>
            Historial
        </a>

    </nav>

    <form action="{{ route('logout') }}" method="POST" class="sidebar-salir">
        @csrf
        <button type="submit" class="sidebar-link">Cerrar sesión</button>
    </form>

</aside>
