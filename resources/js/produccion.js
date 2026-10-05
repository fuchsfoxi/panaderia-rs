document.addEventListener('DOMContentLoaded', function () {
    const formulario = document.getElementById('formulario-produccion');
    const inputCategoria = document.getElementById('categoria-seleccionada');
    const botonesCategoria = document.querySelectorAll('.btn-categoria');
    const detalle = formulario.querySelector('.detalle-pan');
    const fecha = document.getElementById('fecha');
    const turno = document.getElementById('turno');
    const guardar = formulario.querySelector('button[type="submit"]');
    const avisoFamilia = document.getElementById('aviso-familia');
    const titulo = formulario.querySelector('.formulario-card-header h3');

    function participantes(detalle) {
        return Array.from(detalle.querySelectorAll('[data-participante]'));
    }

    function actualizarParticipantes(detalle) {
        const tags = participantes(detalle);
        // Solo se renumeran participantes; el producto siempre es detalles[0].
        tags.forEach((tag, j) => {
            tag.querySelectorAll('[data-participante-campo]').forEach(input => {
                input.name = `detalles[0][participantes][${j}][${input.dataset.participanteCampo}]`;
            });
        });
        const empleados = new Set(tags.map(tag => tag.querySelector('[data-participante-campo="empleado_id"]').value));
        const maestros = tags.filter(tag => tag.dataset.rolNombre === 'Maestro').length;
        const ayudantes = tags.filter(tag => tag.dataset.rolNombre === 'Ayudante').length;
        const selectEmpleado = detalle.querySelector('[data-control="empleado"]');
        const selectRol = detalle.querySelector('[data-control="rol"]');

        Array.from(selectEmpleado.options).forEach(option => {
            option.disabled = empleados.has(option.value);
        });
        Array.from(selectRol.options).forEach(option => {
            option.disabled = option.dataset.rolNombre === 'Maestro' && maestros > 0;
        });
        [selectEmpleado, selectRol].forEach(select => {
            if (select.selectedOptions[0]?.disabled) {
                select.value = '';
            }
        });
        detalle.querySelector('[data-estado-participantes]').textContent =
            `${maestros} Maestro(s) y ${ayudantes} Ayudante(s). Se requiere 1 Maestro y mínimo 1 Ayudante.`;
    }

    function actualizarTotal(detalle) {
        const coches = detalle.querySelector('[data-campo="coches"]');
        const latas = detalle.querySelector('[data-campo="latas_adicionales"]');
        const total = coches.valueAsNumber * 18 + latas.valueAsNumber;
        const valido = Number.isSafeInteger(coches.valueAsNumber) && coches.valueAsNumber >= 0
            && Number.isSafeInteger(latas.valueAsNumber) && latas.valueAsNumber >= 0 && latas.valueAsNumber <= 17
            && Number.isSafeInteger(total);

        // Solo texto informativo: ningún total calculado se incorpora al POST.
        detalle.querySelector('[data-total-latas]').textContent = valido
            ? `${coches.value} coche(s) + ${latas.value} latas = ${total} latas en total`
            : 'Introduce coches enteros y de 0 a 17 latas.';
    }

    function mostrarCamposDe(categoria) {
        document.querySelectorAll('.campo-condicional').forEach(campo => {
            const activo = campo.dataset.mostrarEn === categoria;
            campo.style.display = activo ? 'block' : 'none';
            // Ocultar visualmente no excluye controles del POST; disabled sí.
            campo.querySelectorAll('input, select, textarea, button').forEach(control => {
                control.disabled = !activo;
            });
        });
        inputCategoria.value = categoria;
        botonesCategoria.forEach(boton => {
            boton.classList.toggle('activo', boton.dataset.categoria === categoria);
        });
        const esPan = categoria === 'pan';
        guardar.disabled = !esPan;
        avisoFamilia.hidden = esPan;
        const nombres = { pan: 'Pan', torta: 'Torta', bocadito: 'Bocadito' };
        titulo.textContent = `Detalles de Producción de ${nombres[categoria]}`;
    }

    function agregarParticipante(detalle) {
        const selectEmpleado = detalle.querySelector('[data-control="empleado"]');
        const selectRol = detalle.querySelector('[data-control="rol"]');
        const empleado = selectEmpleado.selectedOptions[0];
        const rol = selectRol.selectedOptions[0];
        const error = detalle.querySelector('[data-error-detalle]');
        if (!empleado?.value || !rol?.value) {
            error.textContent = 'Selecciona un empleado y su rol de producción.';
            return;
        }
        const tags = participantes(detalle);
        if (tags.some(tag => tag.querySelector('[data-participante-campo="empleado_id"]').value === empleado.value)) {
            error.textContent = 'Este empleado ya participa en este producto.';
            return;
        }
        if (rol.dataset.rolNombre === 'Maestro' && tags.some(tag => tag.dataset.rolNombre === 'Maestro')) {
            error.textContent = 'Este producto ya tiene un Maestro.';
            return;
        }

        const tag = document.createElement('span');
        tag.className = 'empleado-tag';
        tag.dataset.participante = '';
        tag.dataset.rolNombre = rol.dataset.rolNombre;
        tag.appendChild(document.createTextNode(`${empleado.textContent} — ${rol.textContent} `));
        const quitar = document.createElement('button');
        quitar.type = 'button';
        quitar.dataset.quitarParticipante = '';
        quitar.textContent = '×';
        quitar.setAttribute('aria-label', `Quitar a ${empleado.textContent}`);
        tag.appendChild(quitar);
        Object.entries({ empleado_id: empleado.value, rol_produccion_id: rol.value }).forEach(([campo, valor]) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.dataset.participanteCampo = campo;
            input.value = valor;
            tag.appendChild(input);
        });
        detalle.querySelector('[data-participantes]').appendChild(tag);
        selectEmpleado.value = '';
        selectRol.value = '';
        error.textContent = '';
        actualizarParticipantes(detalle);
    }

    botonesCategoria.forEach(boton => {
        boton.addEventListener('click', () => mostrarCamposDe(boton.dataset.categoria));
    });

    detalle.addEventListener('click', event => {
        const boton = event.target.closest('button');
        if (!boton || inputCategoria.value !== 'pan') return;
        if (boton.hasAttribute('data-agregar-participante')) {
            agregarParticipante(detalle);
        } else if (boton.hasAttribute('data-quitar-participante')) {
            boton.closest('[data-participante]').remove();
            detalle.querySelector('[data-error-detalle]').textContent = '';
            actualizarParticipantes(detalle);
        }
    });
    detalle.addEventListener('input', event => {
        if (['coches', 'latas_adicionales'].includes(event.target.dataset.campo)) {
            actualizarTotal(detalle);
        }
    });

    document.getElementById('consultar-produccion').addEventListener('click', event => {
        if (!fecha.reportValidity() || !turno.reportValidity()) return;
        const url = new URL(event.currentTarget.dataset.url, window.location.href);
        url.searchParams.set('fecha', fecha.value);
        url.searchParams.set('turno_id', turno.value);
        window.location.assign(url);
    });

    const inputForma = document.getElementById('forma-seleccionada');
    const botonesForma = document.querySelectorAll('.btn-forma');
    botonesForma.forEach(boton => {
        boton.addEventListener('click', () => {
            botonesForma.forEach(otro => otro.classList.toggle('activo', otro === boton));
            inputForma.value = boton.dataset.forma;
        });
    });

    document.getElementById('btn-cancelar-form').addEventListener('click', () => formulario.reset());
    formulario.addEventListener('reset', () => {
        const contexto = { fecha: fecha.value, turno: turno.value };
        // El reset nativo recuperaría old(); después vaciamos el producto y conservamos la sesión.
        queueMicrotask(() => {
            fecha.value = contexto.fecha;
            turno.value = contexto.turno;
            detalle.querySelectorAll('[data-campo], [data-control="empleado"], [data-control="rol"]')
                .forEach(control => { control.value = ''; });
            detalle.querySelector('[data-participantes]').replaceChildren();
            detalle.querySelector('[data-error-detalle]').textContent = '';
            inputForma.value = '';
            botonesForma.forEach(boton => boton.classList.remove('activo'));
            mostrarCamposDe('pan');
            actualizarParticipantes(detalle);
            actualizarTotal(detalle);
        });
    });

    formulario.addEventListener('submit', event => {
        if (inputCategoria.value !== 'pan') {
            event.preventDefault();
            return;
        }
        const tags = participantes(detalle);
        const ids = tags.map(tag => tag.querySelector('[data-participante-campo="empleado_id"]').value);
        const valido = tags.filter(tag => tag.dataset.rolNombre === 'Maestro').length === 1
            && tags.some(tag => tag.dataset.rolNombre === 'Ayudante') && new Set(ids).size === ids.length;
        detalle.querySelector('[data-error-detalle]').textContent = valido ? ''
            : 'Cada producto necesita exactamente 1 Maestro, mínimo 1 Ayudante y empleados sin repetir.';
        if (!valido) {
            event.preventDefault();
            detalle.querySelector('[data-control="empleado"]').focus();
        }
        // Estas ayudas no reemplazan la validación definitiva de store().
    });

    mostrarCamposDe('pan');
    actualizarParticipantes(detalle);
    actualizarTotal(detalle);
});
