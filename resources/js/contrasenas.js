export function inicializarContrasenas(raiz = document) {
    raiz.querySelectorAll('.alternar-contrasena').forEach(boton => {
        // Cada botón controla únicamente el input que lo precede en su caja.
        const input = boton.previousElementSibling;
        if (!input || input.type !== 'password') return;

        boton.addEventListener('click', () => {
            const estaOculta = input.type === 'password';
            input.type = estaOculta ? 'text' : 'password';
            boton.setAttribute('aria-label', estaOculta ? 'Ocultar contraseña' : 'Mostrar contraseña');
        });
    });
}
