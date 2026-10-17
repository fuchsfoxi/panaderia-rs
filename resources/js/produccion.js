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
    const editorParticipantes = detalle.querySelector('#editor-participantes');
    const abrirParticipantes = detalle.querySelector('[data-abrir-participantes]');
    const forma = detalle.querySelector('[data-campo="forma"]');
    const formas = detalle.querySelectorAll('[data-forma]');
    const fotos = [detalle.querySelector('[data-campo="foto"]'), detalle.querySelector('[data-campo="foto_camara"]')];
    const preview = detalle.querySelector('[data-preview-foto]');
    const nombreFoto = detalle.querySelector('[data-nombre-foto]');
    const iconoFoto = detalle.querySelector('[data-icono-foto]');
    const quitarFoto = detalle.querySelector('[data-quitar-foto]');
    const labelFoto = detalle.querySelector('[data-label-foto]');
    let urlFoto = null;

    function mostrarEditor(abierto) {
        editorParticipantes.hidden = !abierto;
        abrirParticipantes.setAttribute('aria-expanded', String(abierto));
        if (abierto) detalle.querySelector('[data-control="empleado"]').focus();
    }

    function actualizarForma() {
        formas.forEach(boton => {
            const activo = forma.value.toLowerCase() === boton.dataset.forma;
            boton.classList.toggle('activo', activo);
            boton.setAttribute('aria-pressed', String(activo));
        });
    }

    function liberarPreview() {
        if (urlFoto) URL.revokeObjectURL(urlFoto);
        urlFoto = null;
        preview.removeAttribute('src');
        preview.hidden = true;
    }

    function limpiarFoto() {
        liberarPreview();
        fotos.forEach(input => { input.value = ''; input.removeAttribute('name'); });
        fotos[0].name = 'detalles[0][foto]';
        nombreFoto.textContent = 'Tomar o subir foto';
        iconoFoto.hidden = false;
        quitarFoto.hidden = true;
        labelFoto.textContent = 'Subir foto';
    }

    fotos.forEach(input => input.addEventListener('change', () => {
        const archivo = input.files[0];
        if (!archivo) return;
        liberarPreview();
        fotos.forEach(otro => {
            otro.removeAttribute('name');
            if (otro !== input) otro.value = '';
        });
        // Solo el selector elegido tiene nombre: evita enviar dos archivos foto.
        input.name = 'detalles[0][foto]';
        nombreFoto.textContent = archivo.name;
        quitarFoto.hidden = false;
        labelFoto.textContent = 'Cambiar foto';
        if (['image/jpeg', 'image/png', 'image/webp'].includes(archivo.type)) {
            urlFoto = URL.createObjectURL(archivo);
            preview.src = urlFoto;
            preview.hidden = false;
            iconoFoto.hidden = true;
        } else {
            iconoFoto.hidden = false;
            nombreFoto.textContent = 'Selecciona una imagen JPG, PNG o WEBP.';
        }
    }));
    preview.addEventListener('error', () => {
        liberarPreview();
        iconoFoto.hidden = false;
        nombreFoto.textContent = 'No se pudo mostrar la imagen. Cambia la foto si es necesario.';
    });
    quitarFoto.addEventListener('click', () => { limpiarFoto(); fotos[0].focus(); });
    window.addEventListener('pagehide', event => {
        if (!event.persisted) liberarPreview();
    });
    formas.forEach(boton => boton.addEventListener('click', () => {
        forma.value = boton.dataset.forma;
        actualizarForma();
    }));
    forma.addEventListener('input', actualizarForma);

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
            tags.length ? `${maestros} Maestro · ${ayudantes} Ayudante(s)` : '1 Maestro y mínimo 1 Ayudante.';
    }

    function codigoPan() {
        const opcion = detalle.querySelector('[data-campo="producto_id"]').selectedOptions[0];
        return opcion?.value ? opcion.dataset.unidadCodigo : 'LATA';
    }

    function actualizarCamposProducto(limpiar = false) {
        const categoria = inputCategoria.value;
        const codigo = codigoPan();
        document.querySelectorAll('.campo-condicional').forEach(campo => {
            const activo = campo.dataset.mostrarEn.split(' ').includes(categoria)
                && (categoria !== 'pan' || !campo.dataset.panRegistro || campo.dataset.panRegistro === codigo);
            campo.style.display = activo ? (campo.classList.contains('campo') ? 'flex' : 'block') : 'none';
            campo.querySelectorAll('input, select, textarea, button').forEach(control => {
                control.disabled = !activo;
                if (limpiar && !activo && control.dataset.campo && !['foto', 'foto_camara'].includes(control.dataset.campo)) control.value = '';
            });
        });
        const tipo = detalle.querySelector('[data-tipo-registro]');
        if (tipo) tipo.textContent = categoria !== 'pan' || codigo === 'UNIDAD'
            ? 'Tipo de registro: Por unidades' : (codigo === 'LATA' ? 'Tipo de registro: Por latas' : 'Unidad de registro no disponible');
    }

    function actualizarTotal(detalle) {
        if (inputCategoria.value !== 'pan' || codigoPan() === 'UNIDAD') {
            detalle.querySelector('output[data-panes-por-lata]').textContent = '';
            detalle.querySelector('[data-total-panes]').textContent = '';
            const cantidad = detalle.querySelector('[data-campo="cantidad"]');
            const numero = cantidad.valueAsNumber;
            const valido = !cantidad.validity.badInput && Number.isInteger(numero) && numero > 0 && numero <= 2147483647;
            detalle.querySelector('[data-resultado-directo]').textContent = valido
                ? `${numero} ${inputCategoria.value === 'torta' ? 'tortas' : 'unidades'}`
                : 'Introduce una cantidad entera positiva.';
            return;
        }
        if (codigoPan() !== 'LATA') {
            detalle.querySelector('[data-total-latas]').textContent = 'Unidad de registro no disponible';
            detalle.querySelector('output[data-panes-por-lata]').textContent = '';
            detalle.querySelector('[data-total-panes]').textContent = '';
            return;
        }
        const coches = detalle.querySelector('[data-campo="coches"]');
        const latas = detalle.querySelector('[data-campo="latas_adicionales"]');
        const numeroCoches = coches.value === '' && !coches.validity.badInput ? 0 : coches.valueAsNumber;
        const numeroLatas = latas.value === '' && !latas.validity.badInput ? 0 : latas.valueAsNumber;
        const total = numeroCoches * latasPorCoche + numeroLatas;
        const valido = Number.isSafeInteger(numeroCoches) && numeroCoches >= 0
            && Number.isSafeInteger(numeroLatas) && numeroLatas >= 0 && numeroLatas < latasPorCoche
            && Number.isSafeInteger(total) && total <= 2147483647;

        // Solo texto informativo: ningún total calculado se incorpora al POST.
        detalle.querySelector('[data-total-latas]').textContent = valido
            ? `${total} latas`
            : `Introduce coches enteros y de 0 a ${latasPorCoche - 1} latas.`;

        const producto = detalle.querySelector('[data-campo="producto_id"]').selectedOptions[0];
        const factor = Number(producto?.dataset.panesPorLata);
        const factorValido = Number.isInteger(factor) && factor > 0;
        detalle.querySelector('output[data-panes-por-lata]').textContent = factorValido
            ? String(factor) : (producto?.value ? 'Sin factor registrado' : 'Selecciona un producto');
        const estimado = total * factor;
        detalle.querySelector('[data-total-panes]').textContent = !factorValido
            ? 'Estimación no disponible'
            : (!valido || total <= 0 ? 'Introduce una cantidad válida de latas.'
                : (Number.isSafeInteger(estimado) ? `${estimado} panes` : 'Estimación fuera del rango de precisión.'));
    }

    function mostrarCamposDe(categoria) {
        const nombres = { pan: 'Pan', torta: 'Torta', bocadito: 'Bocadito' };
        if (!Object.hasOwn(nombres, categoria)) categoria = 'pan';
        const cambio = inputCategoria.value !== categoria;
        const producto = detalle.querySelector('[data-campo="producto_id"]');
        Array.from(producto.options).forEach(option => {
            if (!option.dataset.familia) return;
            option.disabled = option.dataset.familia !== categoria;
            option.hidden = option.disabled;
        });
        if (cambio) {
            producto.value = '';
            detalle.querySelectorAll('[data-campo]')
                .forEach(control => { control.value = ['coches', 'latas_adicionales'].includes(control.dataset.campo) ? '0' : ''; });
            limpiarFoto();
            mostrarEditor(false);
            detalle.querySelector('[data-participantes]').replaceChildren();
            detalle.querySelector('[data-error-detalle]').textContent = '';
            actualizarParticipantes(detalle);
        }
        actualizarForma();
        inputCategoria.value = categoria;
        actualizarCamposProducto(true);
        actualizarTotal(detalle);
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
        mostrarEditor(false);
        abrirParticipantes.focus();
    }

    botonesCategoria.forEach(boton => {
        boton.addEventListener('click', () => mostrarCamposDe(boton.dataset.categoria));
    });

    detalle.addEventListener('click', event => {
        const boton = event.target.closest('button');
        if (!boton) return;
        if (boton.hasAttribute('data-abrir-participantes')) {
            mostrarEditor(editorParticipantes.hidden);
        } else if (boton.hasAttribute('data-agregar-participante')) {
            agregarParticipante(detalle);
        } else if (boton.hasAttribute('data-quitar-participante')) {
            boton.closest('[data-participante]').remove();
            detalle.querySelector('[data-error-detalle]').textContent = '';
            actualizarParticipantes(detalle);
        }
    });
    editorParticipantes.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            event.preventDefault();
            mostrarEditor(false);
            abrirParticipantes.focus();
        }
    });
    detalle.addEventListener('input', event => {
        if (['coches', 'latas_adicionales', 'cantidad'].includes(event.target.dataset.campo)) {
            actualizarTotal(detalle);
        }
    });
    detalle.querySelector('[data-campo="producto_id"]').addEventListener('change', () => { actualizarCamposProducto(true); actualizarTotal(detalle); });

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
            limpiarFoto();
            mostrarEditor(false);
            actualizarForma();
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
            mostrarEditor(true);
        }
        // Estas ayudas no reemplazan la validación definitiva de store().
    });

    mostrarCamposDe(inputCategoria.value);
    actualizarParticipantes(detalle);
    actualizarTotal(detalle);
});
