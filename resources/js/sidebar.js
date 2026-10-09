const panel = document.querySelector('[data-sidebar]');
const abrir = document.querySelector('[data-sidebar-abrir]');
const cerrar = document.querySelector('[data-sidebar-cerrar]');
const overlay = document.querySelector('[data-sidebar-overlay]');

if (panel && abrir && cerrar && overlay) {
    const movil = window.matchMedia('(max-width: 767px)');
    const contenido = document.querySelector('.dashboard, .pagina-produccion, .pagina-historial');
    let abierto = false;

    const controles = () => [...panel.querySelectorAll('a[href], button:not([disabled])')]
        .filter((elemento) => !elemento.hidden);

    const cambiarEstado = (mostrar, devolverFoco = false) => {
        abierto = movil.matches && mostrar;
        panel.classList.toggle('sidebar-abierto', abierto);
        panel.inert = movil.matches && !abierto;
        abrir.setAttribute('aria-expanded', String(abierto));
        abrir.hidden = !movil.matches;
        cerrar.hidden = !movil.matches;
        overlay.hidden = !abierto;
        if (contenido) contenido.inert = abierto;

        if (abierto) {
            panel.setAttribute('role', 'dialog');
            panel.setAttribute('aria-modal', 'true');
            cerrar.focus();
        } else {
            panel.removeAttribute('role');
            panel.removeAttribute('aria-modal');
            if (devolverFoco && movil.matches) abrir.focus();
        }
    };

    abrir.addEventListener('click', () => cambiarEstado(!abierto, abierto));
    cerrar.addEventListener('click', () => cambiarEstado(false, true));
    overlay.addEventListener('click', () => cambiarEstado(false, true));
    panel.querySelectorAll('a[href]').forEach((enlace) => {
        enlace.addEventListener('click', () => cambiarEstado(false, true));
    });

    document.addEventListener('keydown', (event) => {
        if (!abierto) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            cambiarEstado(false, true);
        } else if (event.key === 'Tab') {
            const elementos = controles();
            const primero = elementos[0];
            const ultimo = elementos.at(-1);
            if (event.shiftKey && document.activeElement === primero) {
                event.preventDefault();
                ultimo.focus();
            } else if (!event.shiftKey && document.activeElement === ultimo) {
                event.preventDefault();
                primero.focus();
            }
        }
    });

    movil.addEventListener('change', () => {
        const focoEnPanel = panel.contains(document.activeElement);
        const focoEnAbrir = document.activeElement === abrir;
        const focoEnCerrar = document.activeElement === cerrar;
        cambiarEstado(false, focoEnPanel);
        if (!movil.matches && (focoEnAbrir || focoEnCerrar)) {
            panel.querySelector('a[href]').focus();
        }
    });
    // Limpiar el estado visual sin interferir con la revalidación BFCache de app.js.
    window.addEventListener('pagehide', () => cambiarEstado(false));
    cambiarEstado(false);
    document.body.classList.add('sidebar-enhanced');
}
