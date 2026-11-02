import '../css/indicadores-carga.css';

// Solo presentación de envíos nativos. Producción y fetch conservan sus mecanismos.
const envios = new Map();
let esperaNavegacion;

function mostrarEstado(texto, navegando = false) {
    const estado = document.querySelector('[data-carga-pagina]');
    if (!estado) return;
    estado.classList.toggle('carga-solo-lectores', !navegando);
    estado.querySelector('[data-carga-mensaje]').textContent = texto;
    estado.hidden = false;
}

function restaurar() {
    clearTimeout(esperaNavegacion);
    for (const [formulario, original] of envios) {
        if (original.boton) {
            original.boton.replaceChildren(...original.contenido);
            if (original.etiqueta === null) original.boton.removeAttribute('aria-label');
            else original.boton.setAttribute('aria-label', original.etiqueta);
            if (original.inhabilitado === null) original.boton.removeAttribute('aria-disabled');
            else original.boton.setAttribute('aria-disabled', original.inhabilitado);
            original.boton.classList.remove('carga-boton');
        }
        if (original.ocupado === null) formulario.removeAttribute('aria-busy');
        else formulario.setAttribute('aria-busy', original.ocupado);
    }
    envios.clear();
    const estado = document.querySelector('[data-carga-pagina]');
    if (estado) {
        estado.hidden = true;
        estado.querySelector('[data-carga-mensaje]').textContent = '';
    }
}

export function mostrarNavegacion() {
    if (envios.size || document.querySelector('#formulario-produccion[aria-busy="true"]')) return;
    mostrarEstado('Cargando…', true);
    clearTimeout(esperaNavegacion);
    // Un enlace abortado en navegadores antiguos no siempre emite otro evento.
    if (!window.navigation) {
        esperaNavegacion = setTimeout(() => {
            if (!envios.size) restaurar();
        }, 15000);
    }
}

function destinoInterno(direccion) {
    const destino = new URL(direccion, window.location.href);
    return /^https?:$/.test(destino.protocol) && destino.origin === window.location.origin;
}

function enEstaVentana(destino) {
    return !destino || destino.toLowerCase() === '_self';
}

// Detener el segundo click antes de que el navegador prepare otro envío nativo.
window.addEventListener('click', evento => {
    const boton = evento.target.closest?.('button, input');
    if (boton?.type === 'submit' && envios.has(boton.form)) evento.preventDefault();
}, true);

// En window ya se ejecutaron las validaciones y los handlers de los formularios.
window.addEventListener('submit', evento => {
    const formulario = evento.target;
    if (!(formulario instanceof HTMLFormElement)) return;
    if (envios.has(formulario)) {
        evento.preventDefault();
        return;
    }
    const boton = evento.submitter || formulario.querySelector('button[type="submit"], input[type="submit"]');
    const metodo = (boton?.getAttribute('formmethod') || formulario.method).toLowerCase();
    const destino = boton?.getAttribute('formtarget') ?? formulario.target;
    const accion = boton?.getAttribute('formaction') || formulario.action;
    if (evento.defaultPrevented || metodo === 'dialog' || !enEstaVentana(destino)
        || !destinoInterno(accion) || formulario.id === 'formulario-produccion'
        || formulario.dataset.consultaIniciada) return;

    const texto = formulario.dataset.cargaTexto || (metodo === 'get' ? 'Buscando…' : 'Procesando…');
    const original = {
        boton: boton instanceof HTMLButtonElement ? boton : null,
        ocupado: formulario.getAttribute('aria-busy'),
    };
    if (original.boton) {
        original.contenido = [...boton.childNodes];
        original.etiqueta = boton.getAttribute('aria-label');
        original.inhabilitado = boton.getAttribute('aria-disabled');
        const spinner = document.createElement('span');
        spinner.className = 'carga-spinner';
        spinner.setAttribute('aria-hidden', 'true');
        boton.replaceChildren(spinner, document.createTextNode(' ' + texto));
        boton.setAttribute('aria-label', texto);
        boton.setAttribute('aria-disabled', 'true');
        boton.classList.add('carga-boton');
    }
    envios.set(formulario, original);
    formulario.setAttribute('aria-busy', 'true');
    mostrarEstado(texto);
    // La presentación es inmediata, antes de que el navegador inicie la petición.
    // Si otro listener cancela después, recuperar la UI al terminar el evento.
    setTimeout(() => {
        if (evento.defaultPrevented && envios.get(formulario) === original) restaurar();
    }, 0);
    // No se deshabilitan controles: FormData conserva campos, CSRF y submitter.
});

if (window.navigation) {
    // Incluye enlaces, paginación y location.assign, después de las cancelaciones de click.
    window.navigation.addEventListener('navigate', evento => {
        if (evento.hashChange || evento.destination.sameDocument || evento.downloadRequest !== null
            || !destinoInterno(evento.destination.url)) return;
        if (evento.defaultPrevented) return;
        mostrarNavegacion();
        evento.signal.addEventListener('abort', restaurar, { once: true });
        setTimeout(() => { if (evento.defaultPrevented) restaurar(); }, 0);
    });
    window.navigation.addEventListener('navigateerror', restaurar);
    window.navigation.addEventListener('navigatesuccess', restaurar);
} else {
    window.addEventListener('click', evento => {
        const enlace = evento.target.closest?.('a[href]');
        if (evento.defaultPrevented || evento.button !== 0 || evento.ctrlKey || evento.metaKey
            || evento.shiftKey || evento.altKey || !enlace || enlace.hasAttribute('download')
            || !enEstaVentana(enlace.target) || !destinoInterno(enlace.href)) return;
        const destino = new URL(enlace.href);
        const actual = new URL(window.location.href);
        if (destino.pathname === actual.pathname && destino.search === actual.search
            && (destino.hash || enlace.getAttribute('href').startsWith('#'))) return;
        mostrarNavegacion();
        setTimeout(() => { if (evento.defaultPrevented) restaurar(); }, 0);
    });
}

window.addEventListener('pageshow', restaurar);
window.addEventListener('pagehide', restaurar);
