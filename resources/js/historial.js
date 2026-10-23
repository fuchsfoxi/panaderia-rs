import './tarjetas-produccion.js';

const dialogo = document.querySelector('[data-confirmacion-eliminar]');
if (dialogo && typeof dialogo.showModal === 'function') {
    let pendiente = null;
    let enviando = false;
    const confirmar = dialogo.querySelector('[value="eliminar"]');
    const cancelar = dialogo.querySelector('[value="cancelar"]');
    const error = document.createElement('p');
    error.className = 'error-filtro';
    error.setAttribute('role', 'alert');
    dialogo.appendChild(error);
    document.querySelectorAll('[data-eliminar-registro]').forEach(formulario => {
        const boton = formulario.querySelector('[data-abrir-eliminacion]');
        boton.addEventListener('click', () => {
            pendiente = formulario;
            error.textContent = '';
            for (const campo of ['producto', 'fecha', 'turno']) dialogo.querySelector(`[data-confirmacion-${campo}]`).textContent = formulario.dataset[campo];
            confirmar.disabled = false;
            dialogo.showModal();
        });
        boton.disabled = false;
    });
    dialogo.querySelector('[data-responder-eliminacion]').addEventListener('submit', async evento => {
        if (evento.submitter?.value !== 'eliminar' || !pendiente || enviando) return;
        evento.preventDefault();
        const formulario = pendiente;
        const tarjeta = formulario.closest('.carta-historial');
        const estado = document.querySelector('[data-estado-eliminacion]');
        enviando = true;
        confirmar.disabled = true;
        cancelar.disabled = true;
        confirmar.textContent = 'Eliminando…';
        formulario.querySelector('[name="confirmado"]').value = '1';
        error.textContent = '';
        try {
            const respuesta = await fetch(formulario.action, { method: 'POST', body: new FormData(formulario), headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            const datos = await respuesta.json().catch(() => ({}));
            if (!respuesta.ok || datos.eliminado !== true || datos.familia !== tarjeta.dataset.familia || String(datos.detalle) !== tarjeta.dataset.detalleId) {
                throw Error(datos.message || 'No se pudo eliminar el registro. Revisa tu sesión e inténtalo otra vez.');
            }
            dialogo.close();
            estado.hidden = false;
            estado.textContent = 'Registro eliminado.';
            tarjeta.nextElementSibling?.querySelector('summary')?.focus({ preventScroll: true });
            if (tarjeta.animate && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                tarjeta.style.overflow = 'hidden';
                await tarjeta.animate([{ height: tarjeta.offsetHeight + 'px', opacity: 1, marginBottom: '12px' }, { height: '0px', opacity: 0, marginBottom: '0px', paddingTop: '0px', paddingBottom: '0px' }], { duration: 220, easing: 'ease-in', fill: 'forwards' }).finished;
            }
            tarjeta.remove();
            // Recargar la consulta confirmada conserva filtros, conteo y paginación del servidor.
            window.location.assign(datos.redirect);
        } catch (fallo) {
            error.textContent = fallo.message || 'No se pudo confirmar la eliminación. Actualiza el historial antes de reintentar.';
            confirmar.disabled = false;
        } finally {
            enviando = false;
            cancelar.disabled = false;
            confirmar.textContent = 'Eliminar definitivamente';
        }
    });
    dialogo.addEventListener('cancel', evento => { if (enviando) evento.preventDefault(); });
    dialogo.addEventListener('close', () => { pendiente = null; });
}
