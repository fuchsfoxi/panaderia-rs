/**
 * FILTROS DEL HISTORIAL
 * --------------------------------------------------------------------------
 * Los filtros viajan por parametros GET, no por AJAX (ver HistorialController
 * para el por que). Este archivo solo hace lo que el HTML no puede:
 *
 *   1. marcar el boton de categoria elegida,
 *   2. mostrar el campo de turno SOLO cuando la categoria es pan (es la unica
 *      con columna de turno en la base),
 *   3. mandar el formulario al tocar un boton de categoria, poniendo el valor
 *      en el input oculto para que viaje en la URL.
 *
 * Todas las busquuras van con null-check: si un elemento no esta en el DOM
 * (porque cambio la vista) el script no lanza error en la consola.
 */
document.addEventListener('DOMContentLoaded', function () {
  const formulario = document.getElementById('formulario-filtros');
  const botones = document.querySelectorAll('.btn-categoria');
  const camposCondicionales = document.querySelectorAll('.campo-condicional');
  const inputCategoria = document.getElementById('filtro-categoria');

  // Sin formulario no hay nada que filtrar: se sale sin tocar nada.
  if (!formulario || !inputCategoria) {
    return;
  }

  // Categoria inicial: la que vino del servidor (o 'pan' por defecto).
  let categoriaActiva = inputCategoria.value || 'pan';

  function actualizarCamposCondicionales() {
    camposCondicionales.forEach(function (campo) {
      campo.classList.toggle('oculto', campo.dataset.mostrarEn !== categoriaActiva);
    });
  }

  function marcarBotonActivo() {
    botones.forEach(function (boton) {
      const esActivo = boton.dataset.categoria === categoriaActiva;
      boton.classList.toggle('active', esActivo);
    });
  }

  function filtrar(categoria) {
    categoriaActiva = categoria;
    // El input oculto es lo que hace que la categoria llegue al servidor.
    inputCategoria.value = categoria;
    marcarBotonActivo();
    actualizarCamposCondicionales();
    formulario.submit();
  }

  botones.forEach(function (boton) {
    boton.addEventListener('click', function () {
      filtrar(boton.dataset.categoria);
    });
  });

  // Al cargar: el estado visual ya viene puesto por el servidor, pero el
  // campo de turno hay que resolverlo siempre (puede venir de una URL con
  // ?turno_id=1 y categoria distinto).
  marcarBotonActivo();
  actualizarCamposCondicionales();
});
