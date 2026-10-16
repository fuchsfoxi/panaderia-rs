<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('titulo') - Panadería RS</title>
    @vite(['resources/css/usuarios.css', 'resources/js/usuarios.js'])
</head>
<body class="pagina-con-sidebar">
    <x-sidebar />
    <main class="pagina-productos">
        <header class="productos-encabezado">
            <div>
                <p class="marca-pagina">Panadería RS · Administración</p>
                <h1>@yield('titulo')</h1>
                <p class="descripcion-pagina">@yield('descripcion')</p>
            </div>
            @yield('accion')
        </header>
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
