@extends('errors.layout')

@section('codigo', '419')
@section('titulo', 'La sesión expiró')
@section('texto', 'La sesión expiró, vuelve a intentarlo. Tarde demasiado desde la última vez que entraste.')

@section('acciones')
    {{-- El 419 aparece cuando el token del formulario ya vencio: la sesion
         queda cerrada, asi que el unico camino es entrar de nuevo. --}}
    <a href="{{ route('login') }}" class="error-boton">Ir a iniciar sesión</a>
@endsection
