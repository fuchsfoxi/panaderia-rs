document.addEventListener('DOMContentLoaded', function () {
    const formulario = document.getElementById('formulario-produccion');
    // Blade publica la constante del backend; el navegador solo calcula una ayuda visual.
    const latasPorCoche = Number(formulario.dataset.latasPorCoche);
    const inputCategoria = document.getElementById('categoria-seleccionada');
    const botonesCategoria = document.querySelectorAll('.btn-categoria');
    const detalle = formulario.querySelector('.detalle-pan');
    const fecha = document.getElementById('fecha');
    const turno = document.getElementById('turno');
    const guardar = formulario.querySelector('button[type="submit"]');
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
        const numeroCoches = coches.value === '' && !coches.validity.badInput ? 0 : coches.valueAsNumber;
        const numeroLatas = latas.value === '' && !latas.validity.badInput ? 0 : latas.valueAsNumber;
        const total = numeroCoches * latasPorCoche + numeroLatas;
        const valido = Number.isSafeInteger(numeroCoches) && numeroCoches >= 0
            && Number.isSafeInteger(numeroLatas) && numeroLatas >= 0 && numeroLatas < latasPorCoche
            && Number.isSafeInteger(total);

        // Solo texto informativo: ningún total calculado se incorpora al POST.
        detalle.querySelector('[data-total-latas]').textContent = valido
            ? (total > 0
                ? `${numeroCoches} coche(s) + ${numeroLatas} latas = ${total} latas en total`
                : '0 latas en total. Ingresa al menos un coche o una lata adicional.')
            : `Introduce coches enteros y de 0 a ${latasPorCoche - 1} latas.`;
    }

    function mostrarCamposDe(categoria) {
        const nombres = { pan: 'Pan', torta: 'Torta', bocadito: 'Bocadito' };
        if (!Object.hasOwn(nombres, categoria)) categoria = 'pan';
        const cambio = inputCategoria.value !== categoria;
        document.querySelectorAll('.campo-condicional').forEach(campo => {
            const activo = campo.dataset.mostrarEn.split(' ').includes(categoria);
            campo.style.display = activo ? (campo.classList.contains('campo') ? 'flex' : 'block') : 'none';
            campo.querySelectorAll('input, select, textarea, button').forEach(control => {
                control.disabled = !activo;
            });
        });
        const producto = detalle.querySelector('[data-campo="producto_id"]');
        Array.from(producto.options).forEach(option => {
            if (!option.dataset.familia) return;
            option.disabled = option.dataset.familia !== categoria;
            option.hidden = option.disabled;
        });
        if (cambio) {
            producto.value = '';
            detalle.querySelectorAll('[data-campo="cantidad"], [data-campo="forma"], [data-campo="foto"], [data-campo="observacion"]')
                .forEach(control => { control.value = ''; });
            detalle.querySelector('[data-participantes]').replaceChildren();
            detalle.querySelector('[data-error-detalle]').textContent = '';
            actualizarParticipantes(detalle);
        }
        inputCategoria.value = categoria;
        formulario.action = formulario.dataset[`${categoria}Url`];
        botonesCategoria.forEach(boton => {
            const activo = boton.dataset.categoria === categoria;
            boton.classList.toggle('activo', activo);
            boton.setAttribute('aria-pressed', String(activo));
        });
        document.querySelectorAll('[data-error-familia]').forEach(error => {
            error.hidden = error.dataset.errorFamilia !== categoria;
        });
        // Las tarjetas consultadas pertenecen a la familia cargada por el GET.
        const registros = document.querySelector('[data-registros-familia]');
        if (registros) registros.hidden = registros.dataset.registrosFamilia !== categoria;
        guardar.disabled = false;
        titulo.textContent = `Detalles de Producción de ${nombres[categoria]}`;
        detalle.querySelector('[data-label="producto_id"]').textContent = `Producto ${nombres[categoria]}`;
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
        if (!boton) return;
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
        if (inputCategoria.value !== 'pan') url.searchParams.set('categoria', inputCategoria.value);
        window.location.assign(url);
    });

    document.getElementById('btn-cancelar-form').addEventListener('click', () => formulario.reset());
    formulario.addEventListener('reset', () => {
        const contexto = { fecha: fecha.value, turno: turno.value, categoria: inputCategoria.value };
        // El reset nativo recuperaría old(); después dejamos cantidades en cero y conservamos la sesión.
        queueMicrotask(() => {
            fecha.value = contexto.fecha;
            turno.value = contexto.turno;
            inputCategoria.value = contexto.categoria;
            detalle.querySelectorAll('[data-campo], [data-control="empleado"], [data-control="rol"]')
                .forEach(control => {
                    control.value = ['coches', 'latas_adicionales'].includes(control.dataset.campo) ? '0' : '';
                });
            detalle.querySelector('[data-participantes]').replaceChildren();
            detalle.querySelector('[data-error-detalle]').textContent = '';
            mostrarCamposDe(inputCategoria.value);
            actualizarParticipantes(detalle);
            actualizarTotal(detalle);
        });
    });

    formulario.addEventListener('submit', event => {
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

    mostrarCamposDe(inputCategoria.value);
    actualizarParticipantes(detalle);
    actualizarTotal(detalle);
});
