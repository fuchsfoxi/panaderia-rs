document.addEventListener('DOMContentLoaded', () => {
  const botonesCategoria = document.querySelectorAll('.btn-categoria');
  const camposCondicionales = document.querySelectorAll('.campo-condicional');
  const btnLimpiar = document.querySelector('.btn-limpiar');
  const btnFiltrar = document.querySelector('.btn-filtrar');
  const fechaInicio = document.getElementById('fecha_inicio');
  const fechaFin = document.getElementById('fecha_fin');
  const turno = document.getElementById('turno');

  let categoriaActiva = 'pan';

  function actualizarCamposCondicionales() {
    camposCondicionales.forEach(campo => {
      const mostrarEn = campo.dataset.mostrarEn;
      campo.classList.toggle('oculto', mostrarEn !== categoriaActiva);
    });
  }

  function seleccionarCategoria(boton) {
    botonesCategoria.forEach(btn => btn.classList.remove('active'));
    boton.classList.add('active');
    categoriaActiva = boton.dataset.categoria;
    actualizarCamposCondicionales();
  }

  botonesCategoria.forEach(boton => {
    boton.addEventListener('click', () => seleccionarCategoria(boton));
  });

  // Estado inicial: Pan activo (coincide con el diseño)
  const botonPan = document.querySelector('.btn-categoria[data-categoria="pan"]');
  if (botonPan) seleccionarCategoria(botonPan);

  btnLimpiar.addEventListener('click', () => {
    fechaInicio.value = '';
    fechaFin.value = '';
    if (turno) turno.selectedIndex = 0;
    if (botonPan) seleccionarCategoria(botonPan);
  });

  btnFiltrar.addEventListener('click', () => {
    // Por ahora es una vista estática sin backend real conectado.
    // Aquí luego se hará el fetch/submit al controlador correspondiente.
    console.log('Filtrando con:', {
      categoria: categoriaActiva,
      desde: fechaInicio.value,
      hasta: fechaFin.value,
      turno: turno ? turno.value : null
    });
  });
});