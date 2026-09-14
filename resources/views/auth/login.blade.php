<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - Panificadora Amazónica</title>
{{-- filepath: resources/views/auth/login.blade.php --}}
@vite(['resources/css/login.css', 'resources/js/login.js'])</head>
<body>

<div class="warm-glow warm-glow-left"></div>
<div class="warm-glow warm-glow-right"></div>
<div class="bg-decoration-text">Tradición &amp; IA</div>

<div class="main-login-block">

    <div class="branding-header">
        <div class="logo-icon-plate">
            <img src="{{ asset('images/icono_login.png') }}" alt="icono_login">
        </div>
        <h1 class="brand-title">PANIFICADORA AMAZÓNICA</h1>
        <p class="brand-subtitle">Sistema de Gestión de Producción de Panadería</p>
    </div>

    <div class="login-card">
        <div class="card-header">
            <h1>¡Hola de nuevo!</h1>
            <p>Ingresa tus credenciales para acceder al obrador digital.</p>
        </div>

        <form action="{{ url('login') }}" method="POST" class="form-fields">
            @csrf

            <div class="input-group">
                <label for="username">Usuario</label>
                <div class="input-box">
                    <i class="fas fa-user icon"></i>
                    <input type="text" id="username" name="username" placeholder="Nombre de usuario" required>
                </div>
            </div>

            <div class="input-group">
                <label for="password">Contraseña</label>
                <div class="input-box">
                    <i class="fas fa-key icon"></i>
                    <input type="password" id="password" name="password" placeholder="••••••••••••" required>
                    <button type="button" class="toggle-password" aria-label="Mostrar contraseña">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <div class="decorative-separator">
                <div class="line"></div>
                <div class="diamond"></div>
                <div class="line"></div>
            </div>

            <div class="actions">
                <input type="submit" value="Iniciar sesión" class="btn-submit">
                <button type="button" class="forgot-password">¿Olvidaste tu contraseña?</button>
            </div>
        </form>
    </div>

    <div class="login-footer">PanaderIA v2.4 • Con amor y levadura</div>

</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/js/all.min.js"></script>
</body>
</html>