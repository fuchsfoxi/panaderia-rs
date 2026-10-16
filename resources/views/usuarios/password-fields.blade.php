<div class="productos-campo">
    <label for="password">Nueva contraseña</label>
    <div class="usuario-caja-contrasena">
        <input id="password" name="password" type="password" required minlength="8" maxlength="72" autocomplete="new-password"
               aria-describedby="ayuda-password @if ($errors->has('password')) error-password @endif" @error('password') aria-invalid="true" @enderror>
        <button type="button" class="alternar-contrasena" aria-label="Mostrar contraseña" aria-controls="password">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" /><circle cx="12" cy="12" r="3" /></svg>
        </button>
    </div>
    <p class="productos-ayuda" id="ayuda-password">Mínimo 8 caracteres y máximo 72 bytes. Se permiten ñ y otros caracteres Unicode; no se exige combinar mayúsculas, números o símbolos.</p>
    @error('password')<p id="error-password" class="productos-error-campo">{{ $message }}</p>@enderror
</div>
<div class="productos-campo">
    <label for="password_confirmation">Confirmar contraseña</label>
    <div class="usuario-caja-contrasena">
        <input id="password_confirmation" name="password_confirmation" type="password" required minlength="8" maxlength="72" autocomplete="new-password">
        <button type="button" class="alternar-contrasena" aria-label="Mostrar contraseña" aria-controls="password_confirmation">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" /><circle cx="12" cy="12" r="3" /></svg>
        </button>
    </div>
</div>
