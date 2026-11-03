import { iniciarConsulta } from './consulta-parcial.js';
document.addEventListener('DOMContentLoaded', () => {
    const contenedor = document.querySelector('[data-consulta-registros]');
    const formulario = document.getElementById('formulario-produccion');
    if (!contenedor || !formulario) return;
    const consultar = iniciarConsulta(null, { contenedor, selector: '[data-resultados-registros]', limpiar: true });
    if (!consultar) return;
    formulario.addEventListener('produccion:seccion', event => {
        const url = new URL(contenedor.dataset.consultaUrl, location.href);
        url.searchParams.set('categoria', event.detail.categoria);
        consultar(url);
    });
});
