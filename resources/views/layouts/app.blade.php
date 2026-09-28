{{--
    LAYOUT BASE DEL SISTEMA
    ------------------------------------------------------------------
    Nuevo: antes cada vista (dashboard, produccion, history) traia su propio
    documento <html> completo, sin layout comun. Eso obligaba a duplicar el
    <head>, la tipografia y el menu en cada pagina.

    Ahora el shell vive aca: menu lateral + area de contenido. Cada pagina
    aporta solo lo suyo con @section/@push:

        @extends('layouts.app')
        @section('titulo', '...')
        @push('styles')  @vite([...css de la pagina...])  @endpush
        @section('contenido')  ...  @endsection
        @push('scripts') @vite([...js de la pagina...])  @endpush

    El menu se incluye una sola vez, desde aca (ver components/sidebar).
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('titulo', 'Panificadora Amazónica')</title>

    {{-- Paleta de marca + tipografia. Va PRIMERO: las variables tienen que
         existir antes de que cualquier CSS las use. DRY: antes cada CSS
         traia su propio :root duplicado. --}}
    @vite(['resources/css/variables.css'])

    {{-- CSS propio de la pagina (dashboard / produccion / historial) --}}
    @stack('styles')

    {{-- Menu lateral: se carga en todas las paginas, una sola definicion.
         Va despues de @stack para poder sobrescribir el fondo del body, y
         para que las reglas del panel movil queden por encima del CSS de la
         pagina. --}}
    @vite(['resources/css/sidebar.css'])

</head>
<body class="cuerpo-app">

    {{-- Boton hamburguesa. Solo se ve en menos de 768px (ver sidebar.css), en
         escritorio queda oculto y el menu se muestra siempre. aria-expanded
         lo mantiene actualizado sidebar.js para que un lector de pantalla
         sepa si el panel esta abierto. --}}
    <button type="button" class="app-burger" id="app-burger"
            aria-label="Abrir menú" aria-expanded="false" aria-controls="menu-lateral">
        <i class="material-symbols-rounded" aria-hidden="true">menu</i>
    </button>

    {{-- Fondo oscuro detras del panel. Tocar aca lo cierra (sidebar.js).
         No lleva [hidden] porque la visibilidad la maneja el CSS, que es lo
         que permite la transicion. --}}
    <div class="app-velo" id="app-velo"></div>

    {{-- Menu lateral reutilizable. --}}
    <x-sidebar />

    <main class="app-main">
        @yield('contenido')
    </main>

    {{-- JS propio de la pagina --}}
    @stack('scripts')

    {{-- Menu hamburguesa. Va al final para que el DOM ya este completo. --}}
    @vite(['resources/js/sidebar.js'])
</body>
</html>
