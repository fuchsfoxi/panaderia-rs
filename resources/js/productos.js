const formulario = document.querySelector('[data-form-producto]');

if (formulario) {
    const categoria = formulario.querySelector('[data-categoria-producto]');
    const grupo = formulario.querySelector('[data-factor-pan]');
    const factor = grupo?.querySelector('input');

    if (categoria && grupo && factor) {
        const actualizarFactor = () => {
            const esPan = categoria.selectedOptions[0]?.dataset.nombre === 'Pan';
            grupo.hidden = !esPan;
            factor.disabled = !esPan;
        };
        categoria.addEventListener('change', actualizarFactor);
        actualizarFactor();
    }
}
