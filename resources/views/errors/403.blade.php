@extends('errors.layout')

@section('codigo', '403')
@section('titulo', 'No tenés permiso')
@section('texto', 'Tu usuario no tiene acceso a esta sección. Si creés que es un error, hablá con el administrador.')

@section('acciones')
    <a href="{{ route('inicio') }}" class="error-boton">Volver al inicio</a>
@endsection
