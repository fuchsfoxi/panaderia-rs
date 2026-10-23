// Carga HTML del servidor y sustituye únicamente la región acordada.
export function iniciarConsulta(formulario, { contenedor, selector, despues = () => {} }) {
    if (!formulario || formulario.dataset.consultaIniciada) return;
    formulario.dataset.consultaIniciada = 'true';
    let pendiente;
    const estado = contenedor.querySelector('[data-estado-consulta]');
    const error = contenedor.querySelector('[data-error-consulta]');
    const esqueleto = contenedor.querySelector('[data-esqueleto]');
    const region = () => contenedor.querySelector(selector);
    async function consultar(url, paginando = false) {
        pendiente?.abort();
        const actual = new AbortController();
        pendiente = actual;
        region().setAttribute('aria-busy', 'true');
        esqueleto.style.top = `${region().offsetTop}px`;
        esqueleto.style.height = `${Math.max(40, Math.min(region().offsetHeight, 240))}px`;
        esqueleto.hidden = false;
        error.hidden = true;
        estado.textContent = 'Consultando producciones…';
        try {
            const respuesta = await fetch(url, { signal: actual.signal, headers: { Accept: 'text/html' }, credentials: 'same-origin' });
            if (!respuesta.ok || respuesta.redirected) throw Error('No se pudo completar la consulta. Revisa tu sesión e inténtalo otra vez.');
            const documento = new DOMParser().parseFromString(await respuesta.text(), 'text/html');
            const nuevo = documento.querySelector(selector);
            if (!nuevo) throw Error('La respuesta no contiene los resultados esperados.');
            if (actual.signal.aborted) return;
            region().replaceWith(nuevo);
            despues(nuevo);
            estado.textContent = nuevo.querySelector('[role="status"]')?.textContent || 'Consulta actualizada.';
            if (paginando) nuevo.focus({ preventScroll: true });
        } catch (fallo) {
            if (fallo.name !== 'AbortError') {
                error.textContent = fallo.message;
                error.hidden = false;
                estado.textContent = 'No se actualizó la consulta.';
            }
        } finally {
            if (pendiente === actual) {
                region().removeAttribute('aria-busy');
                esqueleto.hidden = true;
                pendiente = null;
            }
        }
    }
    formulario.addEventListener('submit', event => {
        event.preventDefault();
        if (!formulario.reportValidity()) return;
        const fin = formulario.querySelector('[name="fecha_fin"]');
        const inicio = formulario.querySelector('[name="fecha_inicio"]');
        if (fin?.type === 'hidden') fin.value = inicio.value;
        const url = new URL(formulario.dataset.consultaUrl || formulario.action, location.href);
        url.search = new URLSearchParams(new FormData(formulario));
        consultar(url);
    });
    contenedor.addEventListener('click', event => {
        const enlace = event.target.closest('.paginacion-historial a');
        if (!enlace || !contenedor.contains(enlace) || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        consultar(enlace.href, true);
    });
}
