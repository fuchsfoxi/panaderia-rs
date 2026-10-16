const formulario = document.querySelector('[data-form-producto]');

if (formulario) {
    const categoria = formulario.querySelector('[data-categoria-producto]');
    const grupo = formulario.querySelector('[data-factor-pan]');
    const factor = grupo?.querySelector('input');
    const forma = formulario.querySelector('[data-forma-registro]');
    const registroDirecto = formulario.querySelector('[data-registro-directo]');

    if (categoria && grupo && factor) {
        const actualizarFactor = () => {
            const esPan = categoria.selectedOptions[0]?.dataset.nombre === 'Pan';
            if (forma) {
                forma.value = esPan ? 'Latas' : (['Torta', 'Bocadito'].includes(categoria.selectedOptions[0]?.dataset.nombre) ? 'Unidad / cantidad directa' : 'Selecciona una categoría');
            }
            grupo.hidden = !esPan;
            factor.disabled = !esPan;
            factor.required = esPan;
            if (registroDirecto) {
                registroDirecto.hidden = !['Torta', 'Bocadito'].includes(categoria.selectedOptions[0]?.dataset.nombre);
            }
        };
        categoria.addEventListener('change', actualizarFactor);
        actualizarFactor();
    }
}
