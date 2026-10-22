<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('titulo', 'Personal') - Panadería RS</title>
    @vite(['resources/css/usuarios.css', 'resources/js/usuarios.js'])
</head>
<body class="pagina-con-sidebar">
    <x-sidebar />
    <main class="pagina-productos pagina-personal">
        <header class="productos-encabezado">
            <div>
                <p class="marca-pagina">Panadería RS · Administración</p>
                <h1>Personal</h1>
                <p class="descripcion-pagina">Administra las cuentas de acceso y el personal de producción.</p>
            </div>
            @yield('accion')
        </header>
        <nav class="personal-pestanas" aria-label="Secciones de Personal">
            <a class="personal-pestana {{ request()->routeIs('usuarios.*') ? 'activa' : '' }}" href="{{ route('usuarios.index') }}"
               @if(request()->routeIs('usuarios.*')) aria-current="page" @endif>Usuarios</a>
            <a class="personal-pestana {{ request()->routeIs('empleados.*') ? 'activa' : '' }}" href="{{ route('empleados.index') }}"
               @if(request()->routeIs('empleados.*')) aria-current="page" @endif>Empleados</a>
            <a class="personal-pestana {{ request()->routeIs('grupos.*') ? 'activa' : '' }}" href="{{ route('grupos.index') }}"
               @if(request()->routeIs('grupos.*')) aria-current="page" @endif>Grupos</a>
        </nav>
        @hasSection('formulario-titulo')
            <h2 class="personal-formulario-titulo">@yield('formulario-titulo')</h2>
            <p class="personal-formulario-descripcion">@yield('descripcion')</p>
        @endif
        @if (session('success'))
            <p class="productos-aviso" role="status">{{ session('success') }}</p>
        @endif
        @if ($errors->any())
            <div class="productos-aviso productos-error" role="alert">
                <p>Revisa los datos antes de guardar.</p>
                <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
        @yield('contenido')
    </main>
</body>
</html>
