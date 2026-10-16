import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { runInNewContext } from 'node:vm';

const codigo = readFileSync(new URL('../../resources/js/productos.js', import.meta.url), 'utf8');

// Comprobar el script real y su evento; CSS/FormData se verifican en navegador aparte.
function entorno(nombre, valor = '') {
    const factor = { value: valor, disabled: false, required: false };
    const grupo = { hidden: false, querySelector: () => factor };
    const forma = { value: '' };
    const registroDirecto = { hidden: true };
    const eventos = new Map();
    const categoria = {
        selectedOptions: nombre ? [{ value: '701', dataset: { nombre } }] : [],
        addEventListener: (tipo, callback) => eventos.set(tipo, callback),
    };
    const formulario = { querySelector: selector => selector === '[data-categoria-producto]' ? categoria : selector === '[data-factor-pan]' ? grupo : selector === '[data-forma-registro]' ? forma : registroDirecto };
    runInNewContext(codigo, { document: { querySelector: () => formulario } });
    return {
        factor, grupo, registroDirecto, forma,
        cambiar(nombre) {
            categoria.selectedOptions = [{ value: '402', dataset: { nombre } }];
            eventos.get('change')();
        },
    };
}

for (const nombre of ['Pan', 'Torta', 'Bocadito', '']) {
    test(`al cargar ${nombre || 'sin categoría'} el factor tiene visibilidad y obligatoriedad correctas`, () => {
        const { factor, grupo, registroDirecto, forma } = entorno(nombre, nombre === 'Pan' ? '30' : '');
        assert.equal(forma.value, nombre === 'Pan' ? 'Latas' : (nombre ? 'Unidad / cantidad directa' : 'Selecciona una categoría'));
        assert.equal(registroDirecto.hidden, !['Torta', 'Bocadito'].includes(nombre));
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
