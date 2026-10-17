@extends('productos.layout')

@section('titulo', $producto->exists ? 'Editar producto' : 'Nuevo producto')
@section('descripcion', $producto->exists ? 'Actualiza los datos del producto y conserva su historial.' : 'Añade un producto al catálogo de producción.')
@section('accion')
    <a class="productos-enlace" href="{{ route('productos.index') }}">Volver a Productos</a>
@endsection

@section('contenido')
    @php
        $familiaActual = $categorias->first(fn ($categoria) => $valores['categoria_id'] === (string) $categoria->id)?->nombre_categorias;
        $esPan = $familiaActual === 'Pan';
        $esLata = $esPan && $valores['unidad_registro'] === \App\Models\UnidadMedida::LATA;
        $unidadHistorica = $tieneHistoria ? $producto->unidadMedida : null;
        $asociacionLegacy = $tieneHistoria && ! in_array($unidadHistorica?->codigo, \App\Models\UnidadMedida::codigosParaFamilia($familiaActual ?? ''), true);
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
        <div class="productos-campo" data-grupo-unidad-pan @if (! $esPan) hidden @endif>
            <label for="unidad-producto">Forma de registro</label>
            <select id="unidad-producto" name="unidad_registro" data-unidad-registro required @disabled(! $esPan || $tieneHistoria)
                    aria-describedby="ayuda-unidad @if ($errors->has('unidad_registro')) error-registro @endif @if ($errors->has('unidad_medida_id')) error-unidad @endif" @if ($errors->has('unidad_registro') || $errors->has('unidad_medida_id')) aria-invalid="true" @endif>
                @if ($asociacionLegacy) <option value="">Unidad histórica pendiente de revisión</option> @endif
                <option value="LATA" @selected($valores['unidad_registro'] === 'LATA')>Por latas</option>
                <option value="UNIDAD" @selected($valores['unidad_registro'] === 'UNIDAD')>Por unidades</option>
            </select>
            @if ($tieneHistoria && $esPan && ! $asociacionLegacy)
                <input type="hidden" name="unidad_registro" value="{{ $unidadHistorica->codigo }}">
            @endif
            @error('unidad_registro') <p class="productos-error-campo" id="error-registro">{{ $message }}</p> @enderror
            <p class="productos-ayuda" id="ayuda-unidad">Pan permite registrar por latas o por unidades. Elige la forma que utiliza el producto en producción.</p>
        </div>
        <div class="productos-campo" data-registro-directo @if ($esPan) hidden @endif>
            <label for="registro-directo-producto">Forma de registro</label>
            <input id="registro-directo-producto" type="text" readonly data-forma-registro value="Unidad / cantidad directa">
            <p class="productos-ayuda">Torta y Bocadito siempre usan UNIDAD, sin conversiones.</p>
        </div>
        @error('unidad_medida_id') <p class="productos-error-campo productos-campo-completo" id="error-unidad">{{ $message }}</p> @enderror
        @if ($asociacionLegacy)
            <p class="productos-aviso productos-campo-completo" role="status">Asociación histórica conservada: {{ $unidadHistorica?->nombre_unidades_medida }}. Requiere revisión manual; guardar no la reasigna ni habilita nuevos lotes con una unidad no canónica.</p>
        @endif
        <p class="productos-ayuda productos-campo-completo" data-no-factor @if ($esLata) hidden @endif>Panes por lata: no aplica al registro por unidades.</p>
        <p class="productos-ayuda productos-campo-completo" id="ayuda-estructura">{{ $tieneHistoria ? 'Este producto tiene producción registrada: su categoría y unidad permanecen protegidas.' : 'Puedes cambiar categoría y forma de registro mientras el producto no tenga producción registrada.' }}</p>
        <div class="productos-campo productos-campo-completo" data-factor-pan @if (! $esLata) hidden @endif>
            <label for="factor-producto">Panes por lata</label>
            <input id="factor-producto" name="panes_por_lata" type="number" inputmode="numeric" min="1" max="2147483647" step="1" value="{{ $valores['panes_por_lata'] }}"
                   @disabled(! $esLata)
                   @required($esLata)
                   aria-describedby="ayuda-factor @if ($errors->has('panes_por_lata')) error-factor @endif" @if ($errors->has('panes_por_lata')) aria-invalid="true" @endif>
            <p class="productos-ayuda" id="ayuda-factor">Obligatorio para Pan por latas: indica cuántos panes contiene una lata. Para registro por unidades no aplica y debe estar vacío. La cantidad de latas se registra en Producción; los lotes anteriores conservan su factor registrado.</p>
            @error('panes_por_lata') <p class="productos-error-campo" id="error-factor">{{ $message }}</p> @enderror
        </div>
        <p class="productos-ayuda productos-campo-completo">{{ $producto->exists ? 'Editar el nombre también cambia el nombre visible en producciones anteriores. El estado se administra desde el listado.' : 'El producto se creará activo y estará disponible para producción.' }}</p>
        <div class="productos-acciones productos-campo-completo">
            <button class="productos-boton productos-boton-principal" type="submit" @disabled($categorias->isEmpty() || ! $unidades->has(\App\Models\UnidadMedida::LATA) || ! $unidades->has(\App\Models\UnidadMedida::UNIDAD))>{{ $producto->exists ? 'Guardar cambios' : 'Crear producto' }}</button>
            <a class="productos-boton" href="{{ route('productos.index') }}">Cancelar</a>
        </div>
    </form>
@endsection
