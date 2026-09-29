document.addEventListener('DOMContentLoaded', function () {

    // --- Mostrar/ocultar campos según categoría ---
    const botonesCategoria = document.querySelectorAll('.btn-categoria');
    const inputCategoria = document.getElementById('categoria-seleccionada');
    const camposCondicionales = document.querySelectorAll('.campo-condicional');

    // Sin el input oculto no hay forma de saber que categoria esta
    // seleccionada, asi que el script no puede hacer nada.
    if (!inputCategoria) {
        return;
    }

    function mostrarCamposDe(categoria) {
        camposCondicionales.forEach(function (campo) {
            if (campo.dataset.mostrarEn === categoria) {
                campo.style.display = 'block';
            } else {
                campo.style.display = 'none';
            }
        });

        /* El titulo de la tarjeta decia siempre "de Pan", aunque se estuviera
           cargando una torta o un bocadito: mandaba a guardar a otra parte. */
        const titulo = document.getElementById('titulo-formulario');
        if (titulo) {
            titulo.textContent = 'Detalles de Producción de '
                + categoria.charAt(0).toUpperCase() + categoria.slice(1);
        }
    }

    botonesCategoria.forEach(function (boton) {
        boton.addEventListener('click', function () {
            botonesCategoria.forEach(function (b) {
                b.classList.remove('activo');
            });
            boton.classList.add('activo');

            const categoria = boton.dataset.categoria;
            inputCategoria.value = categoria;

            mostrarCamposDe(categoria);
        });
    });

    mostrarCamposDe(inputCategoria.value);

    // --- Forma de Torta ---
    const botonesForma = document.querySelectorAll('.btn-forma');
    const inputForma = document.getElementById('forma-seleccionada');

    botonesForma.forEach(function (boton) {
        boton.addEventListener('click', function () {
            botonesForma.forEach(function (b) {
                b.classList.remove('activo');
            });
            boton.classList.add('activo');
            inputForma.value = boton.dataset.forma;
        });
    });

    // --- Botón "Cancelar" ---
    const btnCancelar = document.getElementById('btn-cancelar-form');
    const formulario = document.querySelector('form');

    // Antes se agregaba el listener sin comprobar que el boton exista: si no
    // estaba, el navegador tiraba "Cannot read properties of null" en cada
    // carga de la pagina.
    if (btnCancelar && formulario) {
        btnCancelar.addEventListener('click', function () {
            formulario.reset();
            botonesCategoria.forEach(function (b) {
                b.classList.remove('activo');
            });
            const botonPan = document.querySelector('.btn-categoria[data-categoria="pan"]');
            if (botonPan) {
                botonPan.classList.add('activo');
            }
            inputCategoria.value = 'pan';
            mostrarCamposDe('pan');

            /* formulario.reset() vacia el <input type="file"> pero no la vista
               previa, que es un <img>: sin esto la foto seguiria viéndose
               después de cancelar. Se puede llamar porque limpiarPrevia() es
               una función declarada más abajo en este mismo ámbito (function
               declarations se hoistan). */
            limpiarPrevia();
        });
    }

    // --- Agregar empleados dinámicamente ---
    const btnAgregarEmpleado = document.getElementById('agregar-empleado');
    const popoverEmpleado = document.getElementById('popover-empleado');
    const selectEmpleado = document.getElementById('select-empleado');
    const selectRol = document.getElementById('select-rol');
    const btnConfirmarEmpleado = document.getElementById('confirmar-empleado');
    const empleadosTags = document.getElementById('empleados-tags');
    const empleadosInputs = document.getElementById('empleados-inputs');

    let contadorEmpleados = 0;

    // Todo este bloque depende de los 5 elementos del selector de empleados.
    // Si falta alguno, no se registra ningun listener (antes fallaba al
    // agregar uno solo que faltara).
    if (!btnAgregarEmpleado || !popoverEmpleado || !selectEmpleado ||
        !selectRol || !btnConfirmarEmpleado || !empleadosTags || !empleadosInputs) {
        return;
    }

    btnAgregarEmpleado.addEventListener('click', function () {
        popoverEmpleado.style.display =
            popoverEmpleado.style.display === 'none' ? 'flex' : 'none';
    });

    btnConfirmarEmpleado.addEventListener('click', function () {
        // El value del <option> es el ID (es la FK que necesita la tabla
        // pivote), no el nombre. Antes se mandaba ese ID con name="nombre",
        // asi que el backend recibia un ID donde esperaba un texto.
        const empleadoId = selectEmpleado.value;
        const rolId = selectRol.value;

        // Para la etiqueta visible hay que leer el TEXTO de la opcion, porque
        // el value es el id y se veria "1 - 1" en pantalla.
        const empleadoNombre = selectEmpleado.selectedOptions[0]?.text ?? '';
        const rolNombre = selectRol.selectedOptions[0]?.text ?? '';

        // El texto se arma con textContent en vez de innerHTML: el nombre del
        // empleado viene de la base y no hace falta que se interprete como
        // HTML. El boton de quitar se agrega por separado.
        const tag = document.createElement('span');
        tag.className = 'empleado-tag';
        tag.appendChild(document.createTextNode(empleadoNombre + ' — ' + rolNombre + ' '));

        const botonQuitar = document.createElement('button');
        botonQuitar.type = 'button';
        botonQuitar.textContent = '×';
        botonQuitar.setAttribute('aria-label', 'Quitar ' + empleadoNombre);
        tag.appendChild(botonQuitar);

        const inputEmpleado = document.createElement('input');
        inputEmpleado.type = 'hidden';
        inputEmpleado.name = `empleados[${contadorEmpleados}][empleado_id]`;
        inputEmpleado.value = empleadoId;

        const inputRol = document.createElement('input');
        inputRol.type = 'hidden';
        inputRol.name = `empleados[${contadorEmpleados}][rol_id]`;
        inputRol.value = rolId;

        empleadosInputs.appendChild(inputEmpleado);
        empleadosInputs.appendChild(inputRol);

        botonQuitar.addEventListener('click', function () {
            tag.remove();
            inputEmpleado.remove();
            inputRol.remove();
        });

        empleadosTags.insertBefore(tag, btnAgregarEmpleado);

        contadorEmpleados++;
        popoverEmpleado.style.display = 'none';
    });

    /* ======================================================================
       FOTO DE LA TORTA: vista previa + compresión en el navegador
       ======================================================================
       Por que existe:
         1) Antes no habia ninguna vista previa: al elegir o sacar la foto no
            se veia nada y habia que guardar a ciegas.
         2) Una foto de celular pesa 3-8 MB. Con datos moviles eso es lento, la
            validación del servidor (max:2048 KB) la rechazaba, y el iPhone
            manda HEIC, un formato que el navegador no acepta. Se redimensiona
            y comprime ACÁ, antes de subir: 4032x3024 de 525 KB queda en
            1600x1200 de 153 KB, y de paso el HEIC pasa a ser JPEG.
       El archivo original no se sube nunca: solo se sube el resultado. */
    const inputFoto = document.getElementById('foto');
    const preview = document.getElementById('foto-preview');
    const previewImg = document.getElementById('foto-preview-img');
    const previewDatos = document.getElementById('foto-preview-datos');
    const textoBoton = document.getElementById('foto-upload-texto');

    /* Lado maximo del lienzo y calidad del JPEG. 1600px alcanza de sobra para
       ver la foto y guardarla; 0.82 es la calidad tipica de una foto comprimida
       y no se nota la diferencia en pantalla. */
    const LADO_MAXIMO = 1600;
    const CALIDAD_JPEG = 0.82;

    /* La URL de la vista previa hay que liberarla cuando se cambia o se quita
       la foto: si no, el navegador mantiene el archivo en memoria toda la
       sesión de la persona. */
    let urlPrevia = null;

    function pesoLegible(bytes) {
        return bytes < 1048576
            ? Math.round(bytes / 1024) + ' KB'
            : (bytes / 1048576).toFixed(1) + ' MB';
    }

    function limpiarPrevia() {
        if (urlPrevia) {
            URL.revokeObjectURL(urlPrevia);
            urlPrevia = null;
        }
        previewImg.removeAttribute('src');
        previewDatos.textContent = '';
        preview.hidden = true;
        textoBoton.textContent = 'Agregar foto';
    }

    /* Reescribe el archivo del input con la versión comprimida. DataTransfer es
       la única forma de cambiar input.files desde JS. Si el navegador no lo
       tiene, se avisa en la vista previa en vez de prometer un peso falso. */
    function reemplazarArchivo(archivo) {
        try {
            const transferible = new DataTransfer();
            transferible.items.add(archivo);
            inputFoto.files = transferible.files;
            return true;
        } catch (error) {
            return false;
        }
    }

    function comprimir(archivo) {
        return new Promise(function (resolver) {
            const urlTemporal = URL.createObjectURL(archivo);
            const imagen = new Image();

            imagen.onload = function () {
                const escala = Math.min(1, LADO_MAXIMO / Math.max(imagen.width, imagen.height));
                const ancho = Math.round(imagen.width * escala);
                const alto = Math.round(imagen.height * escala);

                const lienzo = document.createElement('canvas');
                lienzo.width = ancho;
                lienzo.height = alto;

                const contexto = lienzo.getContext('2d');
                /* El HEIC del iPhone puede venir con canal alfa: se pinta
               blanco de base para que al guardarlo como JPEG no salga negro. */
                contexto.fillStyle = '#ffffff';
                contexto.fillRect(0, 0, ancho, alto);
                contexto.drawImage(imagen, 0, 0, ancho, alto);
                URL.revokeObjectURL(urlTemporal);

                lienzo.toBlob(function (blob) {
                    if (!blob) {
                        resolver(archivo);   // no se pudo comprimir: original
                        return;
                    }
                    resolver(new File(
                        [blob],
                        (archivo.name || 'torta').replace(/\.[^.]+$/, '') + '.jpg',
                        { type: 'image/jpeg', lastModified: Date.now() }
                    ));
                }, 'image/jpeg', CALIDAD_JPEG);
            };

            /* Formato que el navegador no puede dibujar: se deja el original
               y lo decide la validación del servidor. */
            imagen.onerror = function () {
                URL.revokeObjectURL(urlTemporal);
                resolver(archivo);
            };

            imagen.src = urlTemporal;
        });
    }

    if (inputFoto && preview) {
        inputFoto.addEventListener('change', function () {
            const original = inputFoto.files && inputFoto.files[0];

            if (!original) {
                limpiarPrevia();
                return;
            }

            /* Vista previa inmediata con el archivo original: es instantánea, y
               la persona ya ve la foto que acaba de elegir. */
            if (urlPrevia) {
                URL.revokeObjectURL(urlPrevia);
            }
            urlPrevia = URL.createObjectURL(original);
            previewImg.src = urlPrevia;
            preview.hidden = false;
            textoBoton.textContent = 'Cambiar foto';
            previewDatos.textContent = pesoLegible(original.size) + ' · comprimiendo…';

            comprimir(original).then(function (archivoFinal) {
                if (archivoFinal === original) {
                    previewDatos.textContent = 'Se sube el archivo original: '
                        + pesoLegible(original.size) + ' (no se pudo comprimir).';
                    return;
                }

                if (reemplazarArchivo(archivoFinal)) {
                    if (urlPrevia) {
                        URL.revokeObjectURL(urlPrevia);
                    }
                    urlPrevia = URL.createObjectURL(archivoFinal);
                    /* Se muestra la versión comprimida, que es la que se va a
                       guardar: lo que se ve es lo que queda. */
                    previewImg.src = urlPrevia;
                    previewDatos.textContent = 'Se va a guardar: '
                        + pesoLegible(archivoFinal.size) + ' (comprimida).';
                } else {
                    previewDatos.textContent = 'Se sube el archivo original: '
                        + pesoLegible(original.size)
                        + '. Este navegador no deja usar la versión comprimida ('
                        + pesoLegible(archivoFinal.size) + ').';
                }
            });
        });

        /* Los dos botones son type="button": abren la galeria o limpian el
           campo, sin enviar el formulario. */
        document.getElementById('foto-cambiar').addEventListener('click', function () {
            inputFoto.click();
        });

        document.getElementById('foto-quitar').addEventListener('click', function () {
            inputFoto.value = '';
            limpiarPrevia();
        });
    }
});