document.addEventListener('DOMContentLoaded', function () {

    // --- Mostrar/ocultar campos según categoría ---
    const botonesCategoria = document.querySelectorAll('.btn-categoria');
    const inputCategoria = document.getElementById('categoria-seleccionada');
    const camposCondicionales = document.querySelectorAll('.campo-condicional');

    function mostrarCamposDe(categoria) {
        camposCondicionales.forEach(function (campo) {
            if (campo.dataset.mostrarEn === categoria) {
                campo.style.display = 'block';
            } else {
                campo.style.display = 'none';
            }
        });
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

    btnCancelar.addEventListener('click', function () {
        formulario.reset();
        botonesCategoria.forEach(function (b) {
            b.classList.remove('activo');
        });
        document.querySelector('.btn-categoria[data-categoria="pan"]').classList.add('activo');
        inputCategoria.value = 'pan';
        mostrarCamposDe('pan');
    });

    // --- Agregar empleados dinámicamente ---
    const btnAgregarEmpleado = document.getElementById('agregar-empleado');
    const popoverEmpleado = document.getElementById('popover-empleado');
    const selectEmpleado = document.getElementById('select-empleado');
    const selectRol = document.getElementById('select-rol');
    const btnConfirmarEmpleado = document.getElementById('confirmar-empleado');
    const empleadosTags = document.getElementById('empleados-tags');
    const empleadosInputs = document.getElementById('empleados-inputs');

    let contadorEmpleados = 0;

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

        const tag = document.createElement('span');
        tag.className = 'empleado-tag';
        tag.innerHTML = `${empleadoNombre} — ${rolNombre} <button type="button">×</button>`;

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

        tag.querySelector('button').addEventListener('click', function () {
            tag.remove();
            inputEmpleado.remove();
            inputRol.remove();
        });

        empleadosTags.insertBefore(tag, btnAgregarEmpleado);

        contadorEmpleados++;
        popoverEmpleado.style.display = 'none';
    });

});