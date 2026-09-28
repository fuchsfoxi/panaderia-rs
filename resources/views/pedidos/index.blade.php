@extends('layouts.app')

@section('titulo', 'Pedidos - Panificadora Amazónica')

@push('styles')
    @vite(['resources/css/proximamente.css'])
@endpush

@section('contenido')

<div class="pagina-proximamente">

    <h1>PEDIDOS</h1>

    <div class="carta">
        <h2>Próximamente</h2>
        <p>
            El módulo de pedidos todavía no está disponible.
            Por ahora el sistema solo registra producción.
        </p>
        <a href="{{ route('produccion.index') }}" class="btn-continuar">
            Ir a Producción
        </a>
    </div>

</div>
@endsection
