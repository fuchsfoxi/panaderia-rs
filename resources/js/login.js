import { inicializarContrasenas } from './contrasenas.js';

document.addEventListener('DOMContentLoaded', function () {
    inicializarContrasenas();

    const espera = document.querySelector('[data-login-retry-after]');
    const botonEnviar = document.querySelector('[data-login-submit]');
    const contador = espera?.querySelector('[data-login-seconds]');
    const segundosRestantes = Number(espera?.dataset.loginRetryAfter);

    if (!espera || !botonEnviar || !contador || !Number.isSafeInteger(segundosRestantes) || segundosRestantes < 0) {
        return;
    }

    // Solo presentación: el backend vuelve a comprobar su RateLimiter en cada POST.
    const deadline = Date.now() + segundosRestantes * 1000;
    let intervalo;

    function actualizarEspera() {
        const segundos = Math.max(0, Math.ceil((deadline - Date.now()) / 1000));
        contador.textContent = String(segundos);
        botonEnviar.disabled = segundos > 0;

        if (segundos === 0) {
            espera.hidden = true;
            botonEnviar.removeAttribute('aria-describedby');
            clearInterval(intervalo);
            document.removeEventListener('visibilitychange', actualizarEspera);
            window.removeEventListener('pageshow', actualizarEspera);
        }
    }

    actualizarEspera();
    if (!espera.hidden) {
        intervalo = setInterval(actualizarEspera, 1000);
        document.addEventListener('visibilitychange', actualizarEspera);
        window.addEventListener('pageshow', actualizarEspera);
    }
});
