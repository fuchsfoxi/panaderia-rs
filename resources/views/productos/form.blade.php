@extends('productos.layout')

@section('titulo', $producto->exists ? 'Editar producto' : 'Nuevo producto')
@section('descripcion', $producto->exists ? 'Actualiza los datos del producto y conserva su historial.' : 'Añade un producto al catálogo de producción.')
@section('accion')
    <a class="productos-enlace" href="{{ route('productos.index') }}">Volver a Productos</a>
@endsection

@section('contenido')
    @php
        $familiaActual = $categorias->first(fn ($categoria) => $valores['categoria_id'] === (string) $categoria->id)?->nombre_categorias;
        $cantidadDirecta = in_array($familiaActual, ['Torta', 'Bocadito'], true);
        $codigoActual = \App\Models\UnidadMedida::codigoParaFamilia($familiaActual ?? '');
        $unidadHistorica = $tieneHistoria ? $producto->unidadMedida : null;
        $asociacionLegacy = $tieneHistoria && $unidadHistorica?->codigo !== $codigoActual;
    @endphp
    @if ($categorias->isEmpty() || ! $unidades->has(\App\Models\UnidadMedida::LATA) || ! $unidades->has(\App\Models\UnidadMedida::UNIDAD))
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
            <label for="unidad-producto">Forma de registro</label>
            <input id="unidad-producto" type="text" readonly data-forma-registro
                   value="{{ $familiaActual === 'Pan' ? 'Latas' : ($cantidadDirecta ? 'Unidad / cantidad directa' : 'Selecciona una categoría') }}"
                   aria-describedby="ayuda-unidad @if ($errors->has('unidad_medida_id')) error-unidad @endif" @if ($errors->has('unidad_medida_id')) aria-invalid="true" @endif>
            @error('unidad_medida_id') <p class="productos-error-campo" id="error-unidad">{{ $message }}</p> @enderror
            <p class="productos-ayuda" id="ayuda-unidad">La categoría determina la unidad automáticamente: Pan usa LATA; Torta y Bocadito usan UNIDAD, sin conversiones.</p>
            @if ($asociacionLegacy)
                <p class="productos-aviso" role="status">Asociación histórica conservada: {{ $unidadHistorica?->nombre_unidades_medida }}. Difiere de la unidad canónica de esta categoría. Requiere revisión manual; guardar no la reasigna.</p>
            @endif
        </div>
        <div class="productos-campo productos-campo-completo" data-registro-directo @if (! $cantidadDirecta) hidden @endif>
            <span>Forma de registro</span>
            <p>Unidad / cantidad directa</p>
            <p class="productos-ayuda">Torta: cantidad de tortas. Bocadito: cantidad de bocaditos. La unidad del catálogo no aplica conversiones a la cantidad producida.</p>
        </div>
        <p class="productos-ayuda productos-campo-completo" id="ayuda-estructura">{{ $tieneHistoria ? 'Este producto tiene producción registrada: su categoría y unidad permanecen protegidas.' : 'La categoría determina la unidad. Solo puedes cambiar la categoría mientras el producto no tenga producción registrada.' }}</p>
        <div class="productos-campo productos-campo-completo" data-factor-pan @if ($familiaActual !== 'Pan') hidden @endif>
            <label for="factor-producto">Panes por lata</label>
            <input id="factor-producto" name="panes_por_lata" type="number" inputmode="numeric" min="1" max="2147483647" step="1" value="{{ $valores['panes_por_lata'] }}"
                   @disabled($familiaActual !== 'Pan')
                   @required($categorias->contains(fn ($categoria) => $categoria->nombre_categorias === 'Pan' && $valores['categoria_id'] === (string) $categoria->id))
                   aria-describedby="ayuda-factor @if ($errors->has('panes_por_lata')) error-factor @endif" @if ($errors->has('panes_por_lata')) aria-invalid="true" @endif>
            <p class="productos-ayuda" id="ayuda-factor">Obligatorio para Pan: indica cuántos panes contiene una lata. Para Torta y Bocadito debe estar vacío. La cantidad de latas se registra en Producción; los lotes anteriores conservan su factor registrado.</p>
            @error('panes_por_lata') <p class="productos-error-campo" id="error-factor">{{ $message }}</p> @enderror
        </div>
        <p class="productos-ayuda productos-campo-completo">{{ $producto->exists ? 'Editar el nombre también cambia el nombre visible en producciones anteriores. El estado se administra desde el listado.' : 'El producto se creará activo y estará disponible para producción.' }}</p>
        <div class="productos-acciones productos-campo-completo">
            <button class="productos-boton productos-boton-principal" type="submit" @disabled($categorias->isEmpty() || ! $unidades->has(\App\Models\UnidadMedida::LATA) || ! $unidades->has(\App\Models\UnidadMedida::UNIDAD))>{{ $producto->exists ? 'Guardar cambios' : 'Crear producto' }}</button>
            <a class="productos-boton" href="{{ route('productos.index') }}">Cancelar</a>
        </div>
    </form>
@endsection
