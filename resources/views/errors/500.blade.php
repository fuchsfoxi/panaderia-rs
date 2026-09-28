@extends('errors.layout')

@section('codigo', '500')
@section('titulo', 'Algo falló de nuestro lado')
@section('texto', 'El sistema tuvo un problema al procesar la página. No se guardó nada a medias. Probá de nuevo en unos segundos; si sigue igual, avisá al administrador.')

@section('acciones')
    {{-- Con APP_DEBUG=false esta es la pantalla que se ve ante cualquier
         excepción inesperada. Con APP_DEBUG=true Laravel muestra su propia
         página de depuración y esta nunca aparece. --}}
    <a href="{{ route('inicio') }}" class="error-boton">Volver al inicio</a>
@endsection
