import { iniciarConsulta } from './consulta-parcial.js';
document.addEventListener('DOMContentLoaded', () => {
    const contenedor = document.querySelector('[data-consulta-registros]');
    if (contenedor) iniciarConsulta(contenedor.querySelector('[data-filtros-registros]'), { contenedor, selector: '[data-resultados-registros]' });
});
