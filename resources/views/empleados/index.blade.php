@extends('personal.layout')

@section('titulo', 'Personal · Empleados')
@section('accion')
    <a class="productos-boton productos-boton-principal" href="{{ route('empleados.create') }}">+ Nuevo empleado</a>
@endsection

@section('contenido')
    <section class="productos-lista" aria-label="Empleados de producción">
        @forelse ($empleados as $empleado)
            <article class="producto-fila" aria-labelledby="empleado-{{ $empleado->id }}">
                <div class="producto-datos">
                    <div class="producto-nombre-estado">
                        <h2 id="empleado-{{ $empleado->id }}">{{ $empleado->nombre_empleados }}</h2>
                        <span class="producto-estado {{ $empleado->activo ? 'producto-activo' : 'producto-inactivo' }}">{{ $empleado->activo ? 'Activo' : 'Inactivo' }}</span>
                    </div>
                    <p class="producto-contexto">Rol actual: {{ $empleado->rolProduccion?->nombre_roles_produccion ?? 'Pendiente de asignar' }} · #{{ $empleado->id }}</p>
                </div>
                <div class="productos-acciones producto-acciones">
                    <a class="productos-boton" href="{{ route('empleados.edit', $empleado) }}" aria-label="Editar {{ $empleado->nombre_empleados }}">Editar</a>
                    <form method="POST" action="{{ route('empleados.estado', $empleado) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="activo" value="{{ $empleado->activo ? '0' : '1' }}">
                        <button class="productos-boton {{ $empleado->activo ? 'productos-boton-desactivar' : 'productos-boton-principal' }}" type="submit"
                                aria-label="{{ $empleado->activo ? 'Desactivar' : 'Activar' }} {{ $empleado->nombre_empleados }}">{{ $empleado->activo ? 'Desactivar' : 'Activar' }}</button>
                    </form>
                </div>
            </article>
        @empty
            <p class="productos-vacio">No hay empleados registrados.</p>
        @endforelse
    </section>
    @if ($empleados->hasPages())
        <nav class="productos-paginacion" aria-label="Paginación de empleados">
            @if ($empleados->previousPageUrl())<a class="productos-boton" href="{{ $empleados->previousPageUrl() }}" rel="prev">Anterior</a>@endif
            <span>Página {{ $empleados->currentPage() }} de {{ $empleados->lastPage() }}</span>
            @if ($empleados->nextPageUrl())<a class="productos-boton" href="{{ $empleados->nextPageUrl() }}" rel="next">Siguiente</a>@endif
        </nav>
    @endif
@endsection
