
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Panadería RS</title>

    {{-- * Archivo: resources/views/auth/login.blade.php --}}

    {{-- * Cargamos los archivos CSS y JavaScript mediante Vite. --}}
    @vite(['resources/css/login.css', 'resources/js/login.js'])
</head>
<body>

    {{-- * VIDEO DE FONDO
         * Reproduce una animación decorativa automáticamente,
         * sin sonido y de forma continua.
    --}}
    <div class="contenedor-video-fondo">
        <video autoplay muted loop playsinline class="video-fondo">
            <source src="{{ asset('videos/public_videos_fondo_animado.webm') }}" type="video/webm">
            Tu navegador no soporta videos en HTML5.
        </video>
    </div>

    {{-- * BRILLOS DECORATIVOS
         * Los elementos se crean en HTML.
         * Su apariencia, posición y animación se definen en login.css.
         * aria-hidden evita que los lectores de pantalla los anuncien.
    --}}
    <div class="brillos-fondo" aria-hidden="true">
        <div class="brillo-calido brillo-calido-izquierdo"></div>
        <div class="brillo-calido brillo-calido-derecho"></div>
    </div>

    {{-- * CONTENEDOR PRINCIPAL DEL LOGIN --}}
    <div class="bloque-principal-login">

        {{-- * IDENTIDAD VISUAL DE LA PANADERÍA
             * Muestra el logotipo, nombre y descripción del sistema.
        --}}
        <div class="encabezado-marca">
            <div class="placa-icono-logo">
                <img src="{{ asset('images/icono_login.svg') }}" alt="Panadería RS" width="32" height="32">
            </div>
            <h1 class="titulo-marca">Panadería RS</h1>
            <p class="subtitulo-marca">Sistema de Gestión de Producción de Panadería</p>
        </div>

        {{-- * TARJETA DEL FORMULARIO DE ACCESO --}}
        <div class="tarjeta-login">
            <div class="encabezado-tarjeta">

                {{-- ? ¿Conviene utilizar h2 aquí?
                     Ya existe un h1 para el nombre de la panadería.
                --}}
                <h1>¡Hola de nuevo!</h1>
                <p>Ingresa tus credenciales para acceder al obrador digital.</p>
            </div>

            {{-- * FORMULARIO DE AUTENTICACIÓN
                 * Envía las credenciales al servidor mediante POST.
            --}}
            <form action="{{ url('login') }}" method="POST" class="campos-formulario">

                {{-- ! SEGURIDAD: No eliminar @csrf.
                     Protege las solicitudes POST contra ataques CSRF.
                --}}
                @csrf

                @php
                    // * Identificamos los distintos errores del inicio de sesión.
                    $errorCredenciales = session('login_feedback') === 'credentials' && $errors->has('username');
                    $errorEspera = session('login_feedback') === 'throttle';
                    $errorUsuario = $errors->has('username') && ! $errorCredenciales && ! $errorEspera;

                    // * Calculamos los segundos restantes de espera.
                    $segundosRestantes = max(0, (int) session('login_retry_at', 0) - now()->timestamp);

                    // ! El formulario se bloquea si todavía existe tiempo de espera.
                    $bloqueado = $errorEspera && $segundosRestantes > 0;
                @endphp

                {{-- * CAMPO USUARIO
                     * Permite escribir el nombre de usuario.
                     * Conserva el valor anterior cuando hay un error.
                --}}
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

                    {{-- * Mostramos el error específico del usuario si existe. --}}
                    @if ($errorUsuario)
                        @error('username')
                            <p class="mensaje-login" id="username-error" role="alert">{{ $message }}</p>
                        @enderror
                    @endif
                </div>

                {{-- * CAMPO CONTRASEÑA
                     * El tipo password oculta los caracteres ingresados.
                     * El botón permite alternar su visibilidad.
                --}}
                <div class="grupo-input">
                    <label for="password">Contraseña</label>
                    <div class="caja-input">
                        <i class="fas fa-key icon"></i>

                        <input type="password" id="password" name="password" placeholder="••••••••••••"
                               autocomplete="current-password" maxlength="1024" required
                               @if ($errors->has('password')) aria-invalid="true" aria-describedby="password-error"
                               @elseif ($errorCredenciales) aria-invalid="true" aria-describedby="login-credentials" @endif>

                        {{-- * Botón utilizado para mostrar u ocultar la contraseña. --}}
                        <button type="button" class="alternar-contrasena" aria-label="Mostrar contraseña">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>

                    {{-- * Mostramos los errores de validación de la contraseña. --}}
                    @error('password')
                        <p class="mensaje-login" id="password-error" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                {{-- * SEPARADOR DECORATIVO
                     * Divide visualmente los campos y el botón de acceso.
                --}}
                <div class="separador-decorativo">
                    <div class="linea"></div>
                    <div class="diamante"></div>
                    <div class="linea"></div>
                </div>

                {{-- * ACCIONES Y MENSAJES DE AUTENTICACIÓN --}}
                <div class="acciones">

                    {{-- ! CREDENCIALES INCORRECTAS
                         * Mostramos el error cuando el usuario o la contraseña
                         * no coinciden con las credenciales esperadas.
                    --}}
                    @if ($errorCredenciales)
                        <p class="mensaje-login" id="login-credentials" role="alert">{{ $errors->first('username') }}</p>
                    @endif

                    {{-- ! BLOQUEO TEMPORAL
                         * Se informa cuánto tiempo falta para volver a intentar.
                         * El botón de acceso permanece deshabilitado.
                    --}}
                    @if ($bloqueado)
                        <p class="mensaje-login" id="login-wait" data-login-retry-after="{{ $segundosRestantes }}">Demasiados intentos. Inténtalo de nuevo en <span data-login-seconds>{{ $segundosRestantes }}</span> s.</p>

                        {{-- * Alternativa si JavaScript está deshabilitado. --}}
                        <noscript>
                            <p class="ayuda-espera">Cuando termine la espera, recarga la página para volver a intentarlo.</p>
                        </noscript>
                    @endif

                    {{-- * ERRORES ADICIONALES
                         * Recorremos los mensajes no asociados directamente
                         * a los campos username y password.
                    --}}
                    @foreach ($errors->getMessages() as $campo => $mensajes)
                        @if (! in_array($campo, ['username', 'password'], true))
                            @foreach ($mensajes as $mensaje)
                                <p class="mensaje-login" role="alert">{{ $mensaje }}</p>
                            @endforeach
                        @endif
                    @endforeach

                    {{-- * BOTÓN DE INICIO DE SESIÓN
                         * Envía el formulario cuando no existe un bloqueo.
                    --}}
                    <input type="submit" value="Iniciar sesión" class="btn-enviar" data-login-submit
                           @if ($bloqueado) disabled aria-describedby="login-wait" @endif>

                    {{-- TODO: Al modificar este formulario, comprobar
                         los mensajes de error, el contador y el desbloqueo.
                    --}}
                </div>
            </form>
        </div>

    </div>

    {{-- * Cargamos Font Awesome para los iconos del formulario. --}}
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/js/all.min.js"></script>
</body>
</html>
