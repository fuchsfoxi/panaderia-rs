@extends('personal.layout')

@section('titulo', $grupo->exists ? 'Editar grupo' : 'Nuevo grupo')
@section('formulario-titulo', $grupo->exists ? 'Editar grupo' : 'Nuevo grupo')
@section('descripcion', 'Define el equipo habitual de trabajo. Los participantes de cada producción se registran por separado.')

@section('contenido')
    @include('grupos.advertencias')
    @php
        $valor = function ($campo) use ($grupo) {
            $dato = old($campo, $campo === 'activo' ? (int) $grupo->activo : $grupo->$campo);
            return is_scalar($dato) ? (string) $dato : '';
        };
        $anteriores = old('integrantes', $grupo->integrantes->modelKeys());
        $seleccionados = is_array($anteriores) ? array_map('strval', array_filter($anteriores, 'is_scalar')) : [];
    @endphp
    <form class="productos-formulario" method="POST" action="{{ $grupo->exists ? route('grupos.update', $grupo) : route('grupos.store') }}">
        @csrf
        @if ($grupo->exists) @method('PATCH') @endif
        <div class="productos-campo productos-campo-completo">
            <label for="nombre-grupo">Nombre del grupo</label>
            <input id="nombre-grupo" name="nombre" value="{{ $valor('nombre') }}" maxlength="100" required @error('nombre') aria-invalid="true" aria-describedby="error-nombre-grupo" @enderror>
            @error('nombre')<p class="productos-error-campo" id="error-nombre-grupo">{{ $message }}</p>@enderror
        </div>
        <div class="productos-campo">
            <label for="categoria-grupo">Categoría de producción</label>
            <select id="categoria-grupo" name="categoria_id" required aria-describedby="reglas-grupo @error('categoria_id') error-categoria-grupo @enderror" @error('categoria_id') aria-invalid="true" @enderror>
                <option value="">Selecciona una categoría</option>
                @foreach ($categorias as $categoria)
                    <option value="{{ $categoria->id }}" @selected($valor('categoria_id') === (string) $categoria->id)>{{ $categoria->nombre_categorias }}</option>
                @endforeach
            </select>
            @error('categoria_id')<p class="productos-error-campo" id="error-categoria-grupo">{{ $message }}</p>@enderror
        </div>
        <div class="productos-campo">
            <label for="estado-grupo">Estado</label>
            <select id="estado-grupo" name="activo" required @error('activo') aria-invalid="true" aria-describedby="error-estado-grupo" @enderror>
                <option value="1" @selected($valor('activo') === '1')>Activo</option>
                <option value="0" @selected($valor('activo') === '0')>Inactivo</option>
            </select>
            @error('activo')<p class="productos-error-campo" id="error-estado-grupo">{{ $message }}</p>@enderror
        </div>
        <div class="productos-campo productos-campo-completo">
            <label for="maestro-grupo">Maestro responsable</label>
            <select id="maestro-grupo" name="maestro_id" required @error('maestro_id') aria-invalid="true" aria-describedby="error-maestro-grupo" @enderror>
                <option value="">Selecciona un Maestro activo</option>
                @foreach ($maestros as $maestro)
                    <option value="{{ $maestro->id }}" @selected($valor('maestro_id') === (string) $maestro->id)>{{ $maestro->nombre_empleados }} · #{{ $maestro->id }}{{ ! $maestro->activo || $maestro->rolProduccion?->nombre_roles_produccion !== 'Maestro' ? ' · Asignación no válida: reemplazar' : '' }}</option>
                @endforeach
            </select>
            @error('maestro_id')<p class="productos-error-campo" id="error-maestro-grupo">{{ $message }}</p>@enderror
            @if ($maestros->isEmpty())<p class="productos-ayuda">No hay Maestros activos disponibles. Configura su rol y estado en Empleados.</p>@endif
        </div>
        <fieldset class="grupo-ayudantes productos-campo-completo" aria-describedby="reglas-grupo">
            <legend>Ayudantes habituales</legend>
            <p id="reglas-grupo" class="productos-ayuda">Pan: uno o más Ayudantes, sin turno fijo. Torta: sin Ayudantes, solo Mañana. Bocadito: exactamente un Ayudante, solo Mañana.</p>
            @forelse ($ayudantes as $ayudante)
                <label class="grupo-ayudante" for="ayudante-{{ $ayudante->id }}">
                    <input type="checkbox" id="ayudante-{{ $ayudante->id }}" name="integrantes[]" value="{{ $ayudante->id }}" @checked(in_array((string) $ayudante->id, $seleccionados, true))>
                    <span>{{ $ayudante->nombre_empleados }} · #{{ $ayudante->id }}{{ ! $ayudante->activo || $ayudante->rolProduccion?->nombre_roles_produccion !== 'Ayudante' ? ' · Asignación no válida: retirar' : '' }}</span>
                </label>
            @empty
                <p class="productos-ayuda">No hay Ayudantes activos disponibles. Torta puede registrarse solo con su Maestro.</p>
            @endforelse
            @foreach (collect($errors->get('integrantes'))->merge(collect($errors->get('integrantes.*'))->flatten())->unique() as $mensaje)
                <p class="productos-error-campo" role="alert">{{ $mensaje }}</p>
            @endforeach
        </fieldset>
        <div class="productos-acciones productos-campo-completo">
            <button class="productos-boton productos-boton-principal" type="submit">{{ $grupo->exists ? 'Guardar cambios' : 'Guardar grupo' }}</button>
            <a class="productos-boton" href="{{ route('grupos.index') }}">Cancelar</a>
        </div>
    </form>
@endsection
