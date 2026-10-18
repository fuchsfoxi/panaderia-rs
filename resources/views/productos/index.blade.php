@extends('productos.layout')

@section('titulo', 'Productos')
@section('descripcion', 'Administra los productos disponibles para producción.')
@section('accion')
    <a class="productos-boton productos-boton-principal" href="{{ route('productos.create') }}">+ Nuevo producto</a>
@endsection

@section('contenido')
    <form class="productos-filtros" action="{{ route('productos.index') }}" method="GET" aria-label="Buscar y filtrar productos">
        <div class="productos-campo productos-busqueda">
            <label for="buscar-producto">Buscar producto</label>
            <input id="buscar-producto" type="search" name="q" value="{{ $filtros['q'] }}" maxlength="100" placeholder="Nombre del producto…"
                   @if ($erroresFiltros->has('q')) aria-invalid="true" aria-describedby="error-busqueda" @endif>
            @if ($erroresFiltros->has('q')) <p id="error-busqueda" class="productos-error-campo">{{ $erroresFiltros->first('q') }}</p> @endif
        </div>
        <div class="productos-campo">
            <label for="filtrar-categoria">Categoría</label>
            <select id="filtrar-categoria" name="categoria_id" @if ($erroresFiltros->has('categoria_id')) aria-invalid="true" aria-describedby="error-filtro-categoria" @endif>
                <option value="">Todas</option>
                @foreach ($categorias as $categoria)
                    <option value="{{ $categoria->id }}" @selected($filtros['categoria_id'] === (string) $categoria->id)>{{ $categoria->nombre_categorias }}</option>
                @endforeach
            </select>
            @if ($erroresFiltros->has('categoria_id')) <p id="error-filtro-categoria" class="productos-error-campo">{{ $erroresFiltros->first('categoria_id') }}</p> @endif
        </div>
        <div class="productos-campo">
            <label for="filtrar-estado">Estado</label>
            <select id="filtrar-estado" name="estado" @if ($erroresFiltros->has('estado')) aria-invalid="true" aria-describedby="error-filtro-estado" @endif>
                @foreach (['todos' => 'Todos', 'activos' => 'Activos', 'inactivos' => 'Inactivos'] as $valor => $etiqueta)
                    <option value="{{ $valor }}" @selected(($filtros['estado'] ?: 'todos') === $valor)>{{ $etiqueta }}</option>
                @endforeach
            </select>
            @if ($erroresFiltros->has('estado')) <p id="error-filtro-estado" class="productos-error-campo">{{ $erroresFiltros->first('estado') }}</p> @endif
        </div>
        <div class="productos-acciones">
            <button class="productos-boton productos-boton-principal" type="submit">Filtrar</button>
            <a class="productos-enlace" href="{{ route('productos.index') }}">Limpiar</a>
        </div>
    </form>
    @if ($erroresFiltros->any())
        <div class="productos-aviso productos-error" role="alert">
            <p>La consulta contiene filtros no válidos.</p>
            @if ($erroresFiltros->has('page')) <p>{{ $erroresFiltros->first('page') }}</p> @endif
        </div>
    @endif

    <section aria-labelledby="catalogo-productos">
        <div class="productos-titulo-lista">
            <h2 id="catalogo-productos">Catálogo de productos</h2>
            <p>{{ $productos->total() }} {{ $productos->total() === 1 ? 'producto' : 'productos' }}</p>
        </div>
        <div class="productos-lista">
            @forelse ($productos as $producto)
                <article class="producto-fila" aria-labelledby="producto-{{ $producto->id }}">
                    <div class="producto-datos">
                        <div class="producto-nombre-estado">
                            <h3 id="producto-{{ $producto->id }}">{{ $producto->nombre_p }}</h3>
                            <span class="producto-estado {{ $producto->activo ? 'producto-activo' : 'producto-inactivo' }}">{{ $producto->activo ? 'Activo' : 'Inactivo' }}</span>
                        </div>
                        <p class="producto-contexto">{{ $producto->categoria?->nombre_categorias ?? 'Sin categoría' }} · {{ $producto->unidadMedida?->nombre_unidades_medida ?? 'Sin unidad' }}</p>
                        @if ($producto->categoria?->nombre_categorias === 'Pan')
                            <p class="producto-factor">{{ $producto->panes_por_lata === null ? 'Factor por definir' : $producto->panes_por_lata.' panes por lata' }}</p>
                        @endif
                    </div>
                    <div class="productos-acciones producto-acciones">
                        <a class="productos-boton" href="{{ route('productos.edit', $producto) }}" aria-label="Editar {{ $producto->nombre_p }}">Editar</a>
                        <form action="{{ route('productos.estado', ['producto' => $producto] + array_filter($filtros, fn ($v) => $v !== '') + ['page' => $productos->currentPage()]) }}" method="POST">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="activo" value="{{ $producto->activo ? '0' : '1' }}">
                            <button class="productos-boton {{ $producto->activo ? 'productos-boton-desactivar' : 'productos-boton-principal' }}" type="submit" aria-label="{{ $producto->activo ? 'Desactivar' : 'Activar' }} {{ $producto->nombre_p }}">{{ $producto->activo ? 'Desactivar' : 'Activar' }}</button>
                        </form>
                    </div>
                </article>
            @empty
                <p class="productos-vacio">No hay productos para esta consulta. Puedes ajustar los filtros o crear un producto.</p>
            @endforelse
        </div>
        @if ($productos->hasPages())
            <nav class="productos-paginacion" aria-label="Paginación de productos">
                @if ($productos->onFirstPage()) <span class="productos-boton" aria-disabled="true">Anterior</span>
                @else <a class="productos-boton" href="{{ $productos->previousPageUrl() }}" rel="prev">Anterior</a> @endif
                <span>Página {{ $productos->currentPage() }} de {{ $productos->lastPage() }}</span>
                @if ($productos->hasMorePages()) <a class="productos-boton" href="{{ $productos->nextPageUrl() }}" rel="next">Siguiente</a>
                @else <span class="productos-boton" aria-disabled="true">Siguiente</span> @endif
            </nav>
        @endif
    </section>
@endsection
