@extends('usuarios.layout')

@section('titulo', 'Cambiar contraseña')
@section('descripcion', 'Establece una contraseña nueva sin solicitar la anterior.')

@section('contenido')
    <p class="productos-aviso">Cuenta: {{ $usuario->username }}. Sus sesiones anteriores se revalidarán en la siguiente petición protegida.</p>
    <form class="productos-formulario" method="POST" action="{{ route('usuarios.password.update', $usuario) }}">
        @csrf
        @method('PATCH')
        @include('usuarios.password-fields')
        <div class="productos-acciones productos-campo-completo">
            <button class="productos-boton productos-boton-principal" type="submit">Guardar contraseña</button>
            <a class="productos-boton" href="{{ route('usuarios.index') }}">Cancelar</a>
        </div>
    </form>
@endsection
