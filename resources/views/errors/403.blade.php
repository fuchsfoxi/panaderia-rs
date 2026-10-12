<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso denegado - Panadería RS</title>
    @vite('resources/css/errores.css')
</head>
<body class="pagina-error">
    <main class="tarjeta-error" aria-labelledby="titulo-error">
        <p class="error-marca">Panadería RS</p>
        <p class="error-codigo" aria-hidden="true">403</p>
        <h1 id="titulo-error">Acceso denegado</h1>
        <p class="error-mensaje">No tienes permisos para acceder a esta sección.</p>

        <div class="error-acciones">
            @can('acceder-operacion')
                <a class="error-boton" href="{{ route('dashboard') }}">Volver al Dashboard</a>
            @endcan

            @auth
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button class="error-boton error-boton-secundario" type="submit">Cerrar sesión</button>
                </form>
            @else
                <a class="error-boton" href="{{ route('login') }}">Ir al inicio de sesión</a>
            @endauth
        </div>
    </main>
</body>
</html>
