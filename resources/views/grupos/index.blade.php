@extends('personal.layout')

@section('titulo', 'Personal · Grupos')
@section('accion')
    <a class="productos-boton productos-boton-principal" href="{{ route('grupos.create') }}">+ Nuevo grupo</a>
@endsection

@section('contenido')
    <form class="productos-filtros grupos-filtros" method="GET" action="{{ route('grupos.index') }}" aria-label="Filtros de grupos">
        <div class="productos-campo">
            <label for="categoria-grupos">Categoría de producción</label>
            <select id="categoria-grupos" name="categoria" @if($erroresFiltros->has('categoria')) aria-invalid="true" aria-describedby="error-categoria-grupos" @endif>
                <option value="">Todas las categorías</option>
                @foreach ($categorias->unique('nombre_categorias') as $opcion)
                    <option value="{{ $opcion->nombre_categorias }}" @selected($categoria === $opcion->nombre_categorias)>{{ $opcion->nombre_categorias }}</option>
                @endforeach
            </select>
            @if($erroresFiltros->has('categoria'))<p class="productos-error-campo" id="error-categoria-grupos">{{ $erroresFiltros->first('categoria') }}</p>@endif
        </div>
        <div class="productos-acciones">
            <button type="submit" class="productos-boton productos-boton-principal">Filtrar</button>
            <a class="productos-boton" href="{{ route('grupos.index') }}">Limpiar</a>
        </div>
    </form>
    @if ($erroresFiltros->isNotEmpty())
        <div class="productos-aviso productos-error" role="alert">
            <p>Revisa los filtros para consultar los grupos.</p>
            @if($erroresFiltros->has('page'))<p>{{ $erroresFiltros->first('page') }}</p>@endif
        </div>
    @else
        <div class="productos-titulo-lista"><h2>Equipos habituales</h2><p>{{ $grupos->total() }} grupos encontrados</p></div>
        <section class="productos-lista" aria-label="Grupos de producción">
            @forelse ($grupos as $grupo)
                <article class="producto-fila" data-grupo-id="{{ $grupo->id }}" aria-labelledby="grupo-{{ $grupo->id }}">
                    <div class="producto-datos">
                        <div class="producto-nombre-estado">
                            <h3 id="grupo-{{ $grupo->id }}">{{ $grupo->nombre }}</h3>
                            <span class="producto-estado {{ $grupo->activo ? 'producto-activo' : 'producto-inactivo' }}">{{ $grupo->activo ? 'Activo' : 'Inactivo' }}</span>
                        </div>
                        <p class="producto-contexto">{{ $grupo->categoria?->nombre_categorias ?? 'Categoría no disponible' }} · {{ $grupo->disponibilidad() }}</p>
                        <dl class="usuario-detalles">
                            <div><dt>Maestro responsable</dt><dd>{{ $grupo->maestro?->nombre_empleados ?? 'No disponible' }} · #{{ $grupo->maestro_id }}</dd></div>
                            <div><dt>Ayudantes habituales</dt><dd>{{ $grupo->integrantes->map(fn ($empleado) => $empleado->nombre_empleados.' · #'.$empleado->id)->implode(', ') ?: 'Sin Ayudantes habituales' }}</dd></div>
                        </dl>
                        @include('grupos.advertencias')
                    </div>
                    <div class="productos-acciones producto-acciones">
                        <a class="productos-boton" href="{{ route('grupos.edit', $grupo) }}" aria-label="Editar {{ $grupo->nombre }}">Editar</a>
                        <form method="POST" action="{{ route('grupos.estado', $grupo) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="activo" value="{{ $grupo->activo ? '0' : '1' }}">
                            <button class="productos-boton {{ $grupo->activo ? 'productos-boton-desactivar' : 'productos-boton-principal' }}" type="submit" aria-label="{{ $grupo->activo ? 'Desactivar' : 'Activar' }} {{ $grupo->nombre }}">{{ $grupo->activo ? 'Desactivar' : 'Activar' }}</button>
                        </form>
                    </div>
                </article>
            @empty
                <p class="productos-vacio">No hay grupos registrados para esta categoría.</p>
            @endforelse
        </section>
        @if ($grupos->hasPages())
            <nav class="productos-paginacion" aria-label="Paginación de grupos">
                @if ($grupos->previousPageUrl())<a class="productos-boton" href="{{ $grupos->previousPageUrl() }}" rel="prev">Anterior</a>@endif
                <span>Página {{ $grupos->currentPage() }} de {{ $grupos->lastPage() }}</span>
                @if ($grupos->nextPageUrl())<a class="productos-boton" href="{{ $grupos->nextPageUrl() }}" rel="next">Siguiente</a>@endif
            </nav>
        @endif
    @endif
@endsection
