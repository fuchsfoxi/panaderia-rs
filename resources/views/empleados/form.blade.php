@extends('personal.layout')

@section('titulo', $empleado->exists ? 'Editar empleado' : 'Nuevo empleado')
@section('formulario-titulo', $empleado->exists ? 'Editar empleado' : 'Nuevo empleado')
@section('descripcion', 'El rol actual se utilizará en nuevas producciones. Las funciones registradas anteriormente se conservan.')

@section('contenido')
    @php
        $valor = function ($campo) use ($empleado) {
            $dato = old($campo, $campo === 'activo' ? (int) $empleado->activo : $empleado->$campo);
            return is_scalar($dato) ? (string) $dato : '';
        };
    @endphp
    <form class="productos-formulario" method="POST" action="{{ $empleado->exists ? route('empleados.update', $empleado) : route('empleados.store') }}">
        @csrf
        @if ($empleado->exists) @method('PATCH') @endif
        <div class="productos-campo productos-campo-completo">
            <label for="nombre-empleado">Nombre completo</label>
            <input id="nombre-empleado" name="nombre_empleados" value="{{ $valor('nombre_empleados') }}" maxlength="100" required autocomplete="name"
                   @error('nombre_empleados') aria-invalid="true" aria-describedby="error-nombre-empleado" @enderror>
            @error('nombre_empleados')<p class="productos-error-campo" id="error-nombre-empleado">{{ $message }}</p>@enderror
        </div>
        <div class="productos-campo">
            <label for="rol-empleado">Rol actual</label>
            <select id="rol-empleado" name="rol_produccion_id" required @error('rol_produccion_id') aria-invalid="true" aria-describedby="error-rol-empleado" @enderror>
                <option value="">Selecciona un rol</option>
                @foreach ($roles as $rol)
                    <option value="{{ $rol->id }}" @selected($valor('rol_produccion_id') === (string) $rol->id)>{{ $rol->nombre_roles_produccion }}</option>
                @endforeach
            </select>
            @error('rol_produccion_id')<p class="productos-error-campo" id="error-rol-empleado">{{ $message }}</p>@enderror
        </div>
        <div class="productos-campo">
            <label for="estado-empleado">Estado</label>
            <select id="estado-empleado" name="activo" required @error('activo') aria-invalid="true" aria-describedby="error-estado-empleado" @enderror>
                <option value="1" @selected($valor('activo') === '1')>Activo</option>
                <option value="0" @selected($valor('activo') === '0')>Inactivo</option>
            </select>
            @error('activo')<p class="productos-error-campo" id="error-estado-empleado">{{ $message }}</p>@enderror
        </div>
        <div class="productos-acciones productos-campo-completo">
            <button class="productos-boton productos-boton-principal" type="submit">{{ $empleado->exists ? 'Guardar cambios' : 'Guardar empleado' }}</button>
            <a class="productos-boton" href="{{ route('empleados.index') }}">Cancelar</a>
        </div>
    </form>
@endsection
