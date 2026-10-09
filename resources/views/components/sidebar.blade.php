{{-- Navegación compartida; sin JS, el menú móvil permanece en el flujo normal. --}}
@vite('resources/js/app.js')
<button type="button" class="sidebar-menu" data-sidebar-abrir hidden
        aria-label="Abrir menú de navegación" aria-expanded="false" aria-controls="sidebar-panel">
    <svg class="sidebar-icono" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 6h16M4 12h16M4 18h16" /></svg>
    <span>Menú</span>
</button>
<div class="sidebar-overlay" data-sidebar-overlay hidden aria-hidden="true"></div>
<aside class="sidebar" id="sidebar-panel" data-sidebar aria-label="Menú de Panadería RS">
    <button type="button" class="sidebar-cerrar" data-sidebar-cerrar hidden aria-label="Cerrar menú de navegación" aria-controls="sidebar-panel">
        <svg class="sidebar-icono" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m6 6 12 12M6 18 18 6" /></svg>
    </button>

    <div class="sidebar-perfil">
        <span class="sidebar-avatar" aria-hidden="true">
            <svg class="sidebar-icono" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="8" r="4" /><path d="M4 21v-2a8 8 0 0 1 16 0v2" /></svg>
        </span>
        <div class="sidebar-texto sidebar-nombre">
            <span class="sidebar-etiqueta">Usuario</span>
            <span>{{ auth()->user()?->empleado?->nombre_empleados ?? auth()->user()?->username ?? 'Usuario' }}</span>
        </div>
    </div>

    <nav class="sidebar-nav" aria-label="Navegación principal">

        <a href="{{ route('dashboard') }}"
           class="sidebar-link {{ request()->routeIs('dashboard') ? 'activo' : '' }}"
           @if(request()->routeIs('dashboard')) aria-current="page" @endif>
            <svg class="sidebar-icono" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="3" y="3" width="7" height="7" rx="1" /><rect x="14" y="3" width="7" height="7" rx="1" /><rect x="3" y="14" width="7" height="7" rx="1" /><rect x="14" y="14" width="7" height="7" rx="1" /></svg>
            <span class="sidebar-texto">Dashboard</span>
        </a>

        <a href="{{ route('produccion.index') }}"
           class="sidebar-link {{ request()->routeIs('produccion.*') ? 'activo' : '' }}"
           @if(request()->routeIs('produccion.*')) aria-current="page" @endif>
            <svg class="sidebar-icono" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M5 11a4 4 0 0 1 1-7 5 5 0 0 1 6-1 5 5 0 0 1 6 1 4 4 0 0 1 1 7v9H5Z" /><path d="m9 9 1 3m4-3 1 3M5 16h14" /></svg>
            <span class="sidebar-texto">Producción</span>
        </a>

        <a href="{{ route('history.index') }}"
           class="sidebar-link {{ request()->routeIs('history.*') ? 'activo' : '' }}"
           @if(request()->routeIs('history.*')) aria-current="page" @endif>
            <svg class="sidebar-icono" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 11a9 9 0 1 1 2 7M3 4v7h7M12 7v5l3 2" /></svg>
            <span class="sidebar-texto">Historial</span>
        </a>

    </nav>

    <form action="{{ route('logout') }}" method="POST" class="sidebar-salir">
        @csrf
        <button type="submit" class="sidebar-link">
            <svg class="sidebar-icono" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M9 4H4v16h5M9 12h12m-4-4 4 4-4 4" /></svg>
            <span class="sidebar-texto">Cerrar sesión</span>
        </button>
    </form>

</aside>
