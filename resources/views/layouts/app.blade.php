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
         Va despues de @stack para poder sobrescribir el fondo del body. --}}
    @vite(['resources/css/sidebar.css'])

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="cuerpo-app">

    {{-- Menu lateral reutilizable --}}
    <x-sidebar />

    <main class="app-main">
        @yield('contenido')
    </main>

    {{-- JS propio de la pagina --}}
    @stack('scripts')

</body>
</html>
