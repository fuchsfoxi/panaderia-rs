{{-- menú lateral: se usa en las páginas con <x-sidebar /> --}}
<aside class="sidebar">

    {{-- perfil: ícono de usuario y el nombre del empleado --}}
    {{-- el nombre sale de empleados, no de usuarios_sistema (por la relación empleado() del modelo) --}}
    {{-- el ?-> evita que la página se rompa si el usuario no tuviera empleado --}}
    <div class="sidebar-perfil">
        <i class="fas fa-user"></i>
        <span class="sidebar-nombre">{{ auth()->user()->empleado?->nombre_empleados }}</span>
    </div>

    <hr class="sidebar-linea">

    {{-- navegación: un enlace por página --}}
    {{-- route() usa el nombre de la ruta, así si cambia la URL el menú no se rompe --}}
    {{-- routeIs() es true cuando estoy en esa página, y le pone la clase "activo" --}}
    <nav class="sidebar-nav">

        <a href="{{ route('dashboard') }}"
           class="sidebar-link {{ request()->routeIs('dashboard') ? 'activo' : '' }}"
           title="Dashboard" aria-label="Dashboard">
            <i class="fas fa-house"></i>
        </a>

        <a href="{{ route('produccion.index') }}"
           class="sidebar-link {{ request()->routeIs('produccion.*') ? 'activo' : '' }}"
           title="Producción" aria-label="Producción">
            <i class="fas fa-clipboard-list"></i>
        </a>

        {{-- pedidos todavía no tiene ruta, por eso va deshabilitado --}}
        <span class="sidebar-link deshabilitado"
              title="Pedidos (próximamente)" aria-label="Pedidos (próximamente)" aria-disabled="true">
            <i class="fas fa-receipt"></i>
        </span>

        <a href="{{ route('history.index') }}"
           class="sidebar-link {{ request()->routeIs('history.*') ? 'activo' : '' }}"
           title="Historial" aria-label="Historial">
            <i class="fas fa-clock-rotate-left"></i>
        </a>

    </nav>

    <hr class="sidebar-linea">

    {{-- cerrar sesión: va con POST y @csrf porque cambia el estado (no es un enlace) --}}
    {{-- el action es temporal: cuando exista la ruta logout lo cambio por route('logout') --}}
    <form action="#" method="POST" class="sidebar-salir">
        @csrf
        <button type="submit" class="sidebar-link" title="Cerrar sesión" aria-label="Cerrar sesión">
            <i class="fas fa-right-from-bracket"></i>
        </button>
    </form>

</aside>