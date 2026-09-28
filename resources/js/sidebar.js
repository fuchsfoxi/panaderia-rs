/**
 * MENU HAMBURGUESA (celular)
 * --------------------------------------------------------------------------
 * En menos de 768px el menu lateral se oculta y se abre desde el boton
 * hamburguesa como panel. Este archivo hace la parte que el CSS no puede:
 * abrir, cerrar y mantener el estado accesible.
 *
 * Sin librerias: son 5 eventos del DOM.
 *
 * El menu se cierra de tres maneras, todas necesarias en un celular:
 *   1. tocando el fondo oscuro,
 *   2. tocando cualquier enlace del menu (navegar y dejar el panel abierto
 *      hacia atras es lo mas incomodo),
 *   3. apretando Escape.
 */
document.addEventListener('DOMContentLoaded', function () {
    const boton = document.getElementById('app-burger');
    const velo = document.getElementById('app-velo');
    const menu = document.getElementById('menu-lateral');
    const body = document.body;

    // El layout siempre trae los tres, pero si alguno faltara el script
    // lanzaria un error por consola en cada pagina.
    if (!boton || !velo || !menu) {
        return;
    }

    function abrir() {
        menu.classList.add('lateral--abierto');
        velo.classList.add('activo');
        body.classList.add('menu-abierto');
        boton.setAttribute('aria-expanded', 'true');
        boton.setAttribute('aria-label', 'Cerrar menú');
    }

    function cerrar() {
        menu.classList.remove('lateral--abierto');
        velo.classList.remove('activo');
        body.classList.remove('menu-abierto');
        boton.setAttribute('aria-expanded', 'false');
        boton.setAttribute('aria-label', 'Abrir menú');
    }

    function estaAbierto() {
        return menu.classList.contains('lateral--abierto');
    }

    boton.addEventListener('click', function () {
        if (estaAbierto()) {
            cerrar();
        } else {
            abrir();
        }
    });

    // 1. Fondo oscuro.
    velo.addEventListener('click', cerrar);

    // 2. Cualquier enlace del menu.
    menu.querySelectorAll('a').forEach(function (enlace) {
        enlace.addEventListener('click', cerrar);
    });

    // 3. Tecla Escape.
    document.addEventListener('keydown', function (evento) {
        if (evento.key === 'Escape' && estaAbierto()) {
            cerrar();
            // El foco vuelve al boton para que el teclado no quede perdido.
            boton.focus();
        }
    });
});
