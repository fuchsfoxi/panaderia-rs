document.addEventListener('DOMContentLoaded', function () {
    const botonAlternar = document.querySelector('.alternar-contrasena');
    const inputContrasena = document.getElementById('password');

    if (!botonAlternar || !inputContrasena) {
        return;
    }

    botonAlternar.addEventListener('click', function () {
        const estaOculta = inputContrasena.type === 'password';
        inputContrasena.type = estaOculta ? 'text' : 'password';
        botonAlternar.setAttribute('aria-label', estaOculta ? 'Ocultar contraseña' : 'Mostrar contraseña');
    });
});