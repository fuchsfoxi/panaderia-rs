<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Panificadora Amazónica</title>
    {{-- filepath: resources/views/auth/login.blade.php --}}
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

            <form action="{{ url('login') }}" method="POST" class="campos-formulario">
                @csrf

                <div class="grupo-input">
                    <label for="username">Usuario</label>
                    <div class="caja-input">
                        <i class="fas fa-user icon"></i>
                        <input type="text" id="username" name="username" placeholder="Nombre de usuario" required>
                    </div>
                </div>

                <div class="grupo-input">
                    <label for="password">Contraseña</label>
                    <div class="caja-input">
                        <i class="fas fa-key icon"></i>
                        <input type="password" id="password" name="password" placeholder="••••••••••••" required>
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