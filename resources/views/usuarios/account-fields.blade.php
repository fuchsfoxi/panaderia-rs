        <div class="productos-campo productos-campo-completo">
            <label for="username">Usuario</label>
            <input id="username" name="username" value="{{ $textoAnterior('username') }}" maxlength="50" required autocomplete="off" autocapitalize="none" spellcheck="false"
                   aria-describedby="ayuda-username @if ($errors->has('username')) error-username @endif" @error('username') aria-invalid="true" @enderror>
            <p class="productos-ayuda" id="ayuda-username">Usa minúsculas, números, punto, guion o guion bajo, sin espacios. Se permite ñ. Ejemplo: turn_mañana.</p>
            @error('username')<p id="error-username" class="productos-error-campo">{{ $message }}</p>@enderror
        </div>
        <div class="productos-campo">
            <label for="rol-usuario">Rol</label>
            <select id="rol-usuario" name="rol_id" data-rol-usuario required @error('rol_id') aria-invalid="true" aria-describedby="error-rol" @enderror>
                <option value="">Selecciona un rol</option>
                @foreach ($roles as $rol)
                    <option value="{{ $rol->id }}" data-operador="{{ $rol->nombre_roles === \App\Models\Rol::OPERADOR ? 'true' : 'false' }}" @selected($textoAnterior('rol_id') === (string) $rol->id)>{{ $rol->nombre_roles }}</option>
                @endforeach
            </select>
            @error('rol_id')<p id="error-rol" class="productos-error-campo">{{ $message }}</p>@enderror
        </div>
        <div class="productos-campo" data-turno-usuario>
            <label for="turno-usuario">Turno</label>
            <select id="turno-usuario" name="turno_id" @error('turno_id') aria-invalid="true" aria-describedby="error-turno" @enderror>
                <option value="">Selecciona un turno</option>
                @foreach ($turnos as $turno)
                    <option value="{{ $turno->id }}" @selected($textoAnterior('turno_id') === (string) $turno->id)>{{ $turno->nombre_turnos }}</option>
                @endforeach
            </select>
            <p class="productos-ayuda">Obligatorio para Operador. Las cuentas Administrador no tienen turno autorizado.</p>
            @error('turno_id')<p id="error-turno" class="productos-error-campo">{{ $message }}</p>@enderror
        </div>
