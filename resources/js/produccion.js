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
        const nombre = selectEmpleado.value;
        const rol = selectRol.value;

        const tag = document.createElement('span');
        tag.className = 'empleado-tag';
        tag.innerHTML = `${nombre} — ${rol} <button type="button">×</button>`;

        const inputNombre = document.createElement('input');
        inputNombre.type = 'hidden';
        inputNombre.name = `empleados[${contadorEmpleados}][nombre]`;
        inputNombre.value = nombre;

        const inputRol = document.createElement('input');
        inputRol.type = 'hidden';
        inputRol.name = `empleados[${contadorEmpleados}][rol]`;
        inputRol.value = rol;

        empleadosInputs.appendChild(inputNombre);
        empleadosInputs.appendChild(inputRol);

        tag.querySelector('button').addEventListener('click', function () {
            tag.remove();
            inputNombre.remove();
            inputRol.remove();
        });

        empleadosTags.insertBefore(tag, btnAgregarEmpleado);

        contadorEmpleados++;
        popoverEmpleado.style.display = 'none';
    });

});