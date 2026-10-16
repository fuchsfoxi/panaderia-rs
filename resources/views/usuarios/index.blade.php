@extends('usuarios.layout')

@section('titulo', 'Usuarios')
@section('descripcion', 'Administra las cuentas de acceso. Una cuenta compartida no identifica a la persona que produjo.')
@section('accion')
    <a class="productos-boton productos-boton-principal" href="{{ route('usuarios.create') }}">+ Nuevo usuario</a>
@endsection

@section('contenido')
    <section class="productos-lista" aria-label="Cuentas de acceso">
        @forelse ($usuarios as $usuario)
            <article class="producto-fila" aria-labelledby="usuario-{{ $usuario->id }}">
                <div class="producto-datos">
                    <div class="producto-nombre-estado">
                        <h2 id="usuario-{{ $usuario->id }}">{{ $usuario->username }}</h2>
                        <span class="producto-estado {{ $usuario->activo ? 'producto-activo' : 'producto-inactivo' }}">{{ $usuario->activo ? 'Activo' : 'Inactivo' }}</span>
                    </div>
                    <dl class="usuario-detalles">
                        <div><dt>Rol</dt><dd>{{ $usuario->rol?->nombre_roles ?? '—' }}</dd></div>
                        <div><dt>Turno</dt><dd>{{ $usuario->turno?->nombre_turnos ?? '—' }}</dd></div>
                        <div><dt>Empleado</dt><dd>{{ $usuario->empleado?->nombre_empleados ?? 'Cuenta compartida' }}</dd></div>
                    </dl>
                    @if ($usuario->id === auth()->id())<p class="productos-ayuda">Tu cuenta</p>@endif
                </div>
                <div class="productos-acciones producto-acciones">
                    <a class="productos-boton" href="{{ route('usuarios.password', $usuario) }}" aria-label="Cambiar contraseña de {{ $usuario->username }}">Cambiar contraseña</a>
                    <form method="POST" action="{{ route('usuarios.estado', $usuario) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="activo" value="{{ $usuario->activo ? '0' : '1' }}">
                        <button class="productos-boton" type="submit" aria-label="{{ $usuario->activo ? 'Desactivar' : 'Activar' }} {{ $usuario->username }}">{{ $usuario->activo ? 'Desactivar' : 'Activar' }}</button>
                    </form>
                </div>
            </article>
        @empty
            <p class="productos-vacio">No hay cuentas de acceso.</p>
        @endforelse
    </section>
    @if ($usuarios->hasPages())
        <nav class="productos-paginacion" aria-label="Paginación de usuarios">
            @if ($usuarios->previousPageUrl())<a class="productos-boton" href="{{ $usuarios->previousPageUrl() }}" rel="prev">Anterior</a>@endif
            <span>Página {{ $usuarios->currentPage() }} de {{ $usuarios->lastPage() }}</span>
            @if ($usuarios->nextPageUrl())<a class="productos-boton" href="{{ $usuarios->nextPageUrl() }}" rel="next">Siguiente</a>@endif
        </nav>
    @endif
@endsection
