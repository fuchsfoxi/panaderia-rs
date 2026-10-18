// Los filtros y la paginación siguen siendo GET nativos; solo la confirmación usa JS.
const dialogo = document.querySelector('[data-confirmacion-eliminar]');

if (dialogo && typeof dialogo.showModal === 'function') {
    let pendiente = null;
    const confirmar = dialogo.querySelector('[value="eliminar"]');

    document.querySelectorAll('[data-eliminar-registro]').forEach(formulario => {
        const boton = formulario.querySelector('[data-abrir-eliminacion]');
        boton.addEventListener('click', () => {
            pendiente = formulario;
            for (const campo of ['producto', 'fecha', 'turno']) {
                dialogo.querySelector(`[data-confirmacion-${campo}]`).textContent = formulario.dataset[campo];
            }
            confirmar.disabled = false;
            dialogo.showModal();
        });
        boton.disabled = false;
    });

    dialogo.querySelector('[data-responder-eliminacion]').addEventListener('submit', evento => {
        if (evento.submitter?.value === 'eliminar' && pendiente && !confirmar.disabled) {
            confirmar.disabled = true;
            pendiente.querySelector('[name="confirmado"]').value = '1';
            pendiente.requestSubmit();
        }
    });

    dialogo.addEventListener('close', () => { pendiente = null; });
}
