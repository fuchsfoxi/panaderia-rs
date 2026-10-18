@extends('usuarios.layout')

@section('titulo', 'Editar usuario')
@section('formulario-titulo', 'Editar usuario')
@section('descripcion', 'Actualiza el acceso y el vínculo con un empleado existente. La contraseña se cambia por separado.')

@section('contenido')
    @php
        $textoAnterior = function ($campo) use ($usuario) {
            $valor = old($campo, $usuario->$campo);
            return is_scalar($valor) ? (string) $valor : '';
        };
    @endphp
    <form class="productos-formulario" method="POST" action="{{ route('usuarios.update', $usuario) }}" data-editar-usuario>
        @csrf
        @method('PATCH')
        @include('usuarios.account-fields')
        <div class="productos-campo productos-campo-completo">
            <label for="empleado-vinculado">Empleado vinculado</label>
            <select id="empleado-vinculado" name="empleado_id" aria-describedby="ayuda-empleado-vinculado @if($errors->has('empleado_id')) error-empleado-vinculado @endif" @error('empleado_id') aria-invalid="true" @enderror>
                <option value="">Sin empleado vinculado</option>
                @foreach ($empleados as $empleado)
                    <option value="{{ $empleado->id }}" @selected($textoAnterior('empleado_id') === (string) $empleado->id)>{{ $empleado->nombre_empleados }} · #{{ $empleado->id }}{{ $empleado->activo ? '' : ' (Inactivo)' }}</option>
                @endforeach
            </select>
            <p class="productos-ayuda" id="ayuda-empleado-vinculado">Solo se muestran empleados sin otra cuenta y el vínculo actual. Vincular o desvincular no cambia sus datos laborales ni su historial.</p>
            @error('empleado_id')<p id="error-empleado-vinculado" class="productos-error-campo">{{ $message }}</p>@enderror
            @if ($usuario->empleado)
                <a class="productos-enlace" href="{{ route('empleados.edit', $usuario->empleado) }}">Editar empleado vinculado</a>
            @endif
        </div>
        <div class="productos-acciones productos-campo-completo">
            <a class="productos-boton" href="{{ route('usuarios.index') }}">Cancelar</a>
            <button class="productos-boton productos-boton-principal" type="submit">Guardar cambios</button>
        </div>
    </form>
@endsection
