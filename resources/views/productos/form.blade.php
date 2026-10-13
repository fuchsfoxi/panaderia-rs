@extends('productos.layout')

@section('titulo', $producto->exists ? 'Editar producto' : 'Nuevo producto')
@section('descripcion', $producto->exists ? 'Actualiza los datos del producto y conserva su historial.' : 'Añade un producto al catálogo de producción.')
@section('accion')
    <a class="productos-enlace" href="{{ route('productos.index') }}">Volver a Productos</a>
@endsection

@section('contenido')
    @if ($categorias->isEmpty() || $unidades->isEmpty())
        <p class="productos-aviso" role="status">Para guardar un producto necesitas categorías y unidades de medida disponibles. Revisa el catálogo con el responsable del sistema.</p>
    @endif
    <form class="productos-formulario" data-form-producto action="{{ $producto->exists ? route('productos.update', $producto) : route('productos.store') }}" method="POST">
        @csrf
        @if ($producto->exists) @method('PATCH') @endif
        <div class="productos-campo productos-campo-completo">
            <label for="nombre-producto">Nombre</label>
            <input id="nombre-producto" name="nombre_p" type="text" value="{{ $valores['nombre_p'] }}" maxlength="100" required
                   @if ($errors->has('nombre_p')) aria-invalid="true" aria-describedby="error-nombre" @endif>
            @error('nombre_p') <p class="productos-error-campo" id="error-nombre">{{ $message }}</p> @enderror
        </div>
        <div class="productos-campo">
            <label for="categoria-producto">Categoría</label>
            <select id="categoria-producto" name="categoria_id" data-categoria-producto required @disabled($tieneHistoria)
                    aria-describedby="ayuda-estructura @if ($errors->has('categoria_id')) error-categoria @endif" @if ($errors->has('categoria_id')) aria-invalid="true" @endif>
                <option value="">Selecciona una categoría</option>
                @foreach ($categorias as $categoria)
                    <option value="{{ $categoria->id }}" data-nombre="{{ $categoria->nombre_categorias }}" @selected($valores['categoria_id'] === (string) $categoria->id)>{{ $categoria->nombre_categorias }}</option>
                @endforeach
            </select>
            @if ($tieneHistoria) <input type="hidden" name="categoria_id" value="{{ $producto->categoria_id }}"> @endif
            @error('categoria_id') <p class="productos-error-campo" id="error-categoria">{{ $message }}</p> @enderror
        </div>
        <div class="productos-campo">
            <label for="unidad-producto">Unidad de medida</label>
            <select id="unidad-producto" name="unidad_medida_id" required @disabled($tieneHistoria)
                    aria-describedby="ayuda-estructura @if ($errors->has('unidad_medida_id')) error-unidad @endif" @if ($errors->has('unidad_medida_id')) aria-invalid="true" @endif>
                <option value="">Selecciona una unidad</option>
                @foreach ($unidades as $unidad)
                    <option value="{{ $unidad->id }}" @selected($valores['unidad_medida_id'] === (string) $unidad->id)>{{ $unidad->nombre_unidades_medida }}</option>
                @endforeach
            </select>
            @if ($tieneHistoria) <input type="hidden" name="unidad_medida_id" value="{{ $producto->unidad_medida_id }}"> @endif
            @error('unidad_medida_id') <p class="productos-error-campo" id="error-unidad">{{ $message }}</p> @enderror
        </div>
        <p class="productos-ayuda productos-campo-completo" id="ayuda-estructura">{{ $tieneHistoria ? 'Este producto tiene producción registrada: su categoría y unidad permanecen protegidas.' : 'La categoría y la unidad podrán cambiarse mientras el producto no tenga producción registrada.' }}</p>
        <div class="productos-campo productos-campo-completo" data-factor-pan>
            <label for="factor-producto">Panes por lata <span class="productos-opcional">(opcional)</span></label>
            <input id="factor-producto" name="panes_por_lata" type="number" inputmode="numeric" min="1" max="2147483647" step="1" value="{{ $valores['panes_por_lata'] }}"
                   aria-describedby="ayuda-factor @if ($errors->has('panes_por_lata')) error-factor @endif" @if ($errors->has('panes_por_lata')) aria-invalid="true" @endif>
            <p class="productos-ayuda" id="ayuda-factor">Solo para Pan. Deja vacío si el factor todavía se desconoce; para Torta y Bocadito debe estar vacío. Los lotes anteriores conservan su factor registrado.</p>
            @error('panes_por_lata') <p class="productos-error-campo" id="error-factor">{{ $message }}</p> @enderror
        </div>
        <p class="productos-ayuda productos-campo-completo">{{ $producto->exists ? 'Editar el nombre también cambia el nombre visible en producciones anteriores. El estado se administra desde el listado.' : 'El producto se creará activo y estará disponible para producción.' }}</p>
        <div class="productos-acciones productos-campo-completo">
            <button class="productos-boton productos-boton-principal" type="submit" @disabled($categorias->isEmpty() || $unidades->isEmpty())>{{ $producto->exists ? 'Guardar cambios' : 'Crear producto' }}</button>
            <a class="productos-boton" href="{{ route('productos.index') }}">Cancelar</a>
        </div>
    </form>
@endsection
