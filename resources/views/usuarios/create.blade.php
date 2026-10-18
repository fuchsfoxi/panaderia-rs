@extends('usuarios.layout')

@section('titulo', 'Nuevo usuario')
@section('formulario-titulo', 'Nuevo usuario')
@section('descripcion', 'Crea una cuenta de acceso. Los Operadores comparten una cuenta por turno y no necesitan un Empleado asociado.')

@section('contenido')
    @php($textoAnterior = fn ($campo) => is_scalar(old($campo)) ? (string) old($campo) : '')
    <form class="productos-formulario" method="POST" action="{{ route('usuarios.store') }}" data-crear-usuario>
        @csrf
        @include('usuarios.account-fields')
        @include('usuarios.password-fields')
        <p class="productos-ayuda productos-campo-completo">La cuenta se creará activa. No se vinculará con un Empleado ni se modificará el equipo de producción.</p>
        <div class="productos-acciones productos-campo-completo">
            <button class="productos-boton productos-boton-principal" type="submit" @disabled($roles->isEmpty())>Crear usuario</button>
            <a class="productos-boton" href="{{ route('usuarios.index') }}">Cancelar</a>
        </div>
    </form>
@endsection
