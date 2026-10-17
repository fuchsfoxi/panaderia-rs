import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { runInNewContext } from 'node:vm';

const codigo = readFileSync(new URL('../../resources/js/productos.js', import.meta.url), 'utf8');

// Comprobar el script real y su evento; CSS/FormData se verifican en navegador aparte.
function entorno(nombre, valor = '', codigoUnidad = 'LATA', protegido = false) {
    const factor = { value: valor, disabled: false, required: false };
    const grupo = { hidden: false, querySelector: () => factor };
    const forma = { value: '' };
    const eventosUnidad = new Map();
    const unidad = { value: codigoUnidad, addEventListener: (tipo, callback) => eventosUnidad.set(tipo, callback) };
    const grupoUnidad = { hidden: false };
    const noFactor = { hidden: true };
    const registroDirecto = { hidden: true };
    const eventos = new Map();
    const categoria = {
        disabled: protegido,
        selectedOptions: nombre ? [{ value: '701', dataset: { nombre } }] : [],
        addEventListener: (tipo, callback) => eventos.set(tipo, callback),
    };
    const selectores = { '[data-categoria-producto]': categoria, '[data-factor-pan]': grupo,
        '[data-forma-registro]': forma, '[data-registro-directo]': registroDirecto,
        '[data-unidad-registro]': unidad, '[data-grupo-unidad-pan]': grupoUnidad, '[data-no-factor]': noFactor };
    const formulario = { querySelector: selector => selectores[selector] };
    runInNewContext(codigo, { document: { querySelector: () => formulario } });
    return {
        factor, grupo, registroDirecto, forma, unidad, grupoUnidad, noFactor,
        cambiarUnidad(codigo) { unidad.value = codigo; eventosUnidad.get('change')(); },
        cambiar(nombre) {
            categoria.selectedOptions = [{ value: '402', dataset: { nombre } }];
            eventos.get('change')();
        },
    };
}

for (const nombre of ['Pan', 'Torta', 'Bocadito', '']) {
    test(`al cargar ${nombre || 'sin categoría'} el factor tiene visibilidad y obligatoriedad correctas`, () => {
        const { factor, grupo, registroDirecto, forma } = entorno(nombre, nombre === 'Pan' ? '30' : '');
        assert.equal(forma.value, ['Torta', 'Bocadito'].includes(nombre) ? 'Unidad / cantidad directa' : 'Selecciona una categoría');
        assert.equal(registroDirecto.hidden, nombre === 'Pan');
        assert.equal(grupo.hidden, nombre !== 'Pan');
        assert.equal(factor.disabled, nombre !== 'Pan');
        assert.equal(factor.required, nombre === 'Pan');
        assert.equal(factor.value, nombre === 'Pan' ? '30' : '');
    });
}

test('cambiar Pan a Torta o Bocadito retira required y volver a Pan recupera el factor', () => {
    const formulario = entorno('Pan', '30');
    for (const nombre of ['Torta', 'Bocadito']) {
        formulario.cambiar(nombre);
        assert.equal(formulario.grupo.hidden, true);
        assert.equal(formulario.factor.disabled, true);
        assert.equal(formulario.factor.required, false);
        formulario.cambiar('Pan');
        assert.equal(formulario.grupo.hidden, false);
        assert.equal(formulario.factor.disabled, false);
        assert.equal(formulario.factor.required, true);
        assert.equal(formulario.factor.value, '30');
    }
});

test('el script se puede cargar en el listado sin formulario', () => {
    assert.doesNotThrow(() => runInNewContext(codigo, { document: { querySelector: () => null } }));
});

for (const codigo of ['LATA', 'UNIDAD']) {
    test(`Pan permite seleccionar ${codigo} y exige factor solo por latas`, () => {
        const ui = entorno('Pan', '30');
        ui.cambiarUnidad(codigo);
        assert.equal(ui.grupoUnidad.hidden, false);
        assert.equal(ui.unidad.disabled, false);
        assert.equal(ui.factor.disabled, codigo !== 'LATA');
        assert.equal(ui.factor.required, codigo === 'LATA');
        assert.equal(ui.grupo.hidden, codigo !== 'LATA');
        assert.equal(ui.noFactor.hidden, codigo === 'LATA');
    });
}

test('LATA -> UNIDAD -> LATA conserva el valor temporal y solo envía el factor cuando aplica', () => {
    const ui = entorno('Pan', '30');
    ui.cambiarUnidad('UNIDAD');
    assert.equal(ui.factor.disabled, true);
    ui.cambiarUnidad('LATA');
    assert.equal(ui.factor.disabled, false);
    assert.equal(ui.factor.value, '30');
    for (const familia of ['Torta', 'Bocadito']) {
        ui.cambiar(familia);
        assert.equal(ui.grupoUnidad.hidden, true);
        assert.equal(ui.unidad.disabled, true);
        assert.equal(ui.factor.disabled, true);
        assert.equal(ui.registroDirecto.hidden, false);
    }
});

test('la forma de registro permanece deshabilitada con historia', () => {
    const ui = entorno('Pan', '30', 'LATA', true);
    assert.equal(ui.unidad.disabled, true);
    assert.equal(ui.factor.disabled, false);
});
