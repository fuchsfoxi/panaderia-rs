const formulario = document.querySelector('[data-form-producto]');

if (formulario) {
    const categoria = formulario.querySelector('[data-categoria-producto]');
    const grupo = formulario.querySelector('[data-factor-pan]');
    const factor = grupo?.querySelector('input');
    const unidad = formulario.querySelector('[data-unidad-registro]');
    const grupoUnidad = formulario.querySelector('[data-grupo-unidad-pan]');
    const registroDirecto = formulario.querySelector('[data-registro-directo]');
    const forma = formulario.querySelector('[data-forma-registro]');
    const noFactor = formulario.querySelector('[data-no-factor]');

    if (categoria && grupo && factor && unidad) {
        const actualizarFactor = () => {
            const esPan = categoria.selectedOptions[0]?.dataset.nombre === 'Pan';
            const esLata = esPan && unidad.value === 'LATA';
            grupoUnidad.hidden = !esPan;
            // Categoría disabled identifica un producto protegido por historial.
            unidad.disabled = !esPan || categoria.disabled;
            grupo.hidden = !esLata;
            factor.disabled = !esLata;
            factor.required = esLata;
            registroDirecto.hidden = esPan;
            noFactor.hidden = esLata;
            forma.value = ['Torta', 'Bocadito'].includes(categoria.selectedOptions[0]?.dataset.nombre)
                ? 'Unidad / cantidad directa' : 'Selecciona una categoría';
        };
        categoria.addEventListener('change', actualizarFactor);
        unidad.addEventListener('change', actualizarFactor);
        actualizarFactor();
    }
}
