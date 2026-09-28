@extends('errors.layout')

@section('codigo', '404')
@section('titulo', 'Página no encontrada')
@section('texto', 'La dirección que abriste no existe o cambió de nombre. Revisá el enlace o volvé al inicio.')

@section('acciones')
    {{-- Se Offer una sola vez con route('inicio'): esa ruta ya manda al
         dashboard si hay sesión y al login si no, asi que sirve para los dos
         casos sin preguntar nada. --}}
    <a href="{{ route('inicio') }}" class="error-boton">Volver al inicio</a>
@endsection
