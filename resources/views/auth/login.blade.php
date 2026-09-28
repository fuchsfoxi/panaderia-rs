<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Panificadora Amazónica</title>
    {{-- filepath: resources/views/auth/login.blade.php --}}
    {{-- variables.css primero: login.css usa la paleta de marca y esta
         vista no extiende layouts/app, asi que la carga ella misma. --}}
    @vite(['resources/css/variables.css'])
    @vite(['resources/css/login.css', 'resources/js/login.js'])
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>

    <!-- Contenedor del video de fondo -->
    <div class="contenedor-video-fondo">
        <video autoplay muted loop playsinline class="video-fondo">
            <source src="{{ asset('videos/public_videos_fondo_animado.webm') }}" type="video/webm">
            Tu navegador no soporta videos en HTML5.
        </video>
    </div>

    <div class="brillo-calido brillo-calido-izquierdo"></div>
    <div class="brillo-calido brillo-calido-derecho"></div>

    <div class="bloque-principal-login">

        <div class="encabezado-marca">
            <div class="placa-icono-logo">
                <img src="{{ asset('images/icono_login.png') }}" alt="icono_login">
            </div>
            <h1 class="titulo-marca">PANIFICADORA AMAZÓNICA</h1>
            <p class="subtitulo-marca">Sistema de Gestión de Producción de Panadería</p>
        </div>

        <div class="tarjeta-login">
            <div class="encabezado-tarjeta">
                <h1>¡Hola de nuevo!</h1>
                <p>Ingresa tus credenciales para acceder al obrador digital.</p>
            </div>

            {{-- route('login.attempt') y no url('login'): es la ruta POST que
                 procesa el login. Con GET /login solo, el envio del formulario
                 daba "405 Method Not Allowed". --}}
            <form action="{{ route('login.attempt') }}" method="POST" class="campos-formulario" novalidate>
                {{-- Token anti CSRF: sin esto Laravel responde 419. --}}
                @csrf

                {{-- Errores de validacion y de credenciales. Se muestran juntos
                     arriba del formulario para que el usuario vea el motivo sin
                     tener que buscar el campo. --}}
                @if ($errors->any())
                    <div class="alerta-error" role="alert">
                        <i class="fas fa-circle-exclamation"></i>
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="grupo-input">
                    <label for="username">Usuario</label>
                    <div class="caja-input">
                        <i class="fas fa-user icon"></i>
                        {{-- autocomplete="username" + autofocus: el celular
                             ofrece el usuario guardado y el teclado se abre
                             directo en el campo. old() mantiene lo escrito si
                             el login falla. --}}
                        <input type="text" id="username" name="username"
                               value="{{ old('username') }}"
                               placeholder="Nombre de usuario"
                               autocomplete="username"
                               autocapitalize="none" spellcheck="false"
                               maxlength="50" required autofocus>
                    </div>
                </div>

                <div class="grupo-input">
                    <label for="password">Contraseña</label>
                    <div class="caja-input">
                        <i class="fas fa-key icon"></i>
                        {{-- autocomplete="current-password": es el campo de una
                             cuenta existente, no de una nueva. --}}
                        <input type="password" id="password" name="password"
                               placeholder="••••••••••••"
                               autocomplete="current-password"
                               maxlength="255" required>
                        <button type="button" class="alternar-contrasena" aria-label="Mostrar contraseña">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="separador-decorativo">
                    <div class="linea"></div>
                    <div class="diamante"></div>
                    <div class="linea"></div>
                </div>

                <div class="acciones">
                    <input type="submit" value="Iniciar sesión" class="btn-enviar">
                    <button type="button" class="olvido-contrasena">¿Olvidaste tu contraseña?</button>
                </div>
            </form>
        </div>

    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/js/all.min.js"></script>
</body>
</html>