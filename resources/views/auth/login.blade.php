<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Panadería RS</title>
    {{-- filepath: resources/views/auth/login.blade.php --}}
    @vite(['resources/css/login.css', 'resources/js/login.js'])
</head>
<body>

    <!-- Contenedor del video de fondo -->
    <div class="contenedor-video-fondo">
        <video autoplay muted loop playsinline class="video-fondo">
            <source src="{{ asset('videos/public_videos_fondo_animado.webm') }}" type="video/webm">
            Tu navegador no soporta videos en HTML5.
        </video>
    </div>

    <div class="brillos-fondo" aria-hidden="true">
        <div class="brillo-calido brillo-calido-izquierdo"></div>
        <div class="brillo-calido brillo-calido-derecho"></div>
    </div>

    <div class="bloque-principal-login">

        <div class="encabezado-marca">
            <div class="placa-icono-logo">
                <img src="{{ asset('images/icono_login.svg') }}" alt="Panadería RS" width="32" height="32">
            </div>
            <h1 class="titulo-marca">Panadería RS</h1>
            <p class="subtitulo-marca">Sistema de Gestión de Producción de Panadería</p>
        </div>

        <div class="tarjeta-login">
            <div class="encabezado-tarjeta">
                <h1>¡Hola de nuevo!</h1>
                <p>Ingresa tus credenciales para acceder al obrador digital.</p>
            </div>

            <form action="{{ url('login') }}" method="POST" class="campos-formulario">
                @csrf
                @php
                    $errorCredenciales = session('login_feedback') === 'credentials' && $errors->has('username');
                    $errorEspera = session('login_feedback') === 'throttle';
                    $errorUsuario = $errors->has('username') && ! $errorCredenciales && ! $errorEspera;
                    $segundosRestantes = max(0, (int) session('login_retry_at', 0) - now()->timestamp);
                    $bloqueado = $errorEspera && $segundosRestantes > 0;
                @endphp

                <div class="grupo-input">
                    <label for="username">Usuario</label>
                    <div class="caja-input">
                        <i class="fas fa-user icon"></i>
                        <input type="text" id="username" name="username" placeholder="Nombre de usuario"
                               value="{{ is_string(old('username')) ? old('username') : '' }}"
                               autocomplete="username" maxlength="50" required
                               @if ($errorUsuario) aria-invalid="true" aria-describedby="username-error"
                               @elseif ($errorCredenciales) aria-invalid="true" aria-describedby="login-credentials" @endif>
                    </div>
                    @if ($errorUsuario)
                        @error('username')
                            <p class="mensaje-login" id="username-error" role="alert">{{ $message }}</p>
                        @enderror
                    @endif
                </div>

                <div class="grupo-input">
                    <label for="password">Contraseña</label>
                    <div class="caja-input">
                        <i class="fas fa-key icon"></i>
                        <input type="password" id="password" name="password" placeholder="••••••••••••"
                               autocomplete="current-password" maxlength="1024" required
                               @if ($errors->has('password')) aria-invalid="true" aria-describedby="password-error"
                               @elseif ($errorCredenciales) aria-invalid="true" aria-describedby="login-credentials" @endif>
                        <button type="button" class="alternar-contrasena" aria-label="Mostrar contraseña">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    @error('password')
                        <p class="mensaje-login" id="password-error" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <div class="separador-decorativo">
                    <div class="linea"></div>
                    <div class="diamante"></div>
                    <div class="linea"></div>
                </div>

                <div class="acciones">
                    @if ($errorCredenciales)
                        <p class="mensaje-login" id="login-credentials" role="alert">{{ $errors->first('username') }}</p>
                    @endif
                    @if ($bloqueado)
                        <p class="mensaje-login" id="login-wait" data-login-retry-after="{{ $segundosRestantes }}">Demasiados intentos. Inténtalo de nuevo en <span data-login-seconds>{{ $segundosRestantes }}</span> s.</p>
                        <noscript><p class="ayuda-espera">Cuando termine la espera, recarga la página para volver a intentarlo.</p></noscript>
                    @endif
                    @foreach ($errors->getMessages() as $campo => $mensajes)
                        @if (! in_array($campo, ['username', 'password'], true))
                            @foreach ($mensajes as $mensaje)
                                <p class="mensaje-login" role="alert">{{ $mensaje }}</p>
                            @endforeach
                        @endif
                    @endforeach
                    <input type="submit" value="Iniciar sesión" class="btn-enviar" data-login-submit
                           @if ($bloqueado) disabled aria-describedby="login-wait" @endif>
                </div>
            </form>
        </div>

    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/js/all.min.js"></script>
</body>
</html>
