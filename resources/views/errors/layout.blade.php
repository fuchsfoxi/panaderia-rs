<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('codigo') - Panificadora Amazónica</title>

    {{-- Estas paginas se muestran cuando algo sale mal, incluso antes de
         iniciar sesion, asi que NO extienden layouts/app: no dependan del
         menu lateral ni de que haya un usuario en sesion. --}}
    @vite(['resources/css/variables.css'])
    @vite(['resources/css/errores.css'])
</head>
<body class="cuerpo-error">
    <div class="error-caja">
        <p class="error-codigo">@yield('codigo')</p>
        <h1 class="error-titulo">@yield('titulo')</h1>
        <p class="error-texto">@yield('texto')</p>

        <div class="error-acciones">
            @yield('acciones')
        </div>
    </div>
</body>
</html>
