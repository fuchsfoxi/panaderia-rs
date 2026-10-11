import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { runInNewContext } from 'node:vm';

const codigo = readFileSync(new URL('../../resources/js/produccion.js', import.meta.url), 'utf8');

// Ejecutar el script completo y sus eventos con dobles del DOM; no simular diseño/navegador.
function entorno(categoria = 'pan') {
    const elemento = (value = '', dataset = {}) => ({
        value, dataset, textContent: '', options: [], selectedOptions: [],
        validity: { badInput: false }, eventos: new Map(), classList: { toggle() {}, remove() {} },
        get valueAsNumber() { return this.value === '' ? NaN : Number(this.value); },
        setAttribute(nombre, valor) { this[nombre] = valor; },
        addEventListener(nombre, callback) { this.eventos.set(nombre, callback); },
        emitir(nombre, event = {}) { this.eventos.get(nombre)?.(event); },
    });
    const coches = elemento('0', { campo: 'coches' });
    const latas = elemento('0', { campo: 'latas_adicionales' });
    const producto = elemento('producto', { campo: 'producto_id' });
    producto.options = ['', 'pan', 'torta', 'bocadito'].map(familia => ({
        value: familia, dataset: familia ? { familia } : {}, disabled: false, hidden: false,
    }));
    const cantidad = elemento('4', { campo: 'cantidad' });
    const forma = elemento('circular', { campo: 'forma' });
    const foto = elemento('', { campo: 'foto' });
    const observacion = elemento('nota', { campo: 'observacion' });
    const empleado = elemento();
    const rol = elemento();
    const output = elemento();
    const tags = elemento();
    tags.replaceChildren = () => {};
    const detalle = elemento();
    const selectores = new Map([
        ['[data-campo="coches"]', coches], ['[data-campo="latas_adicionales"]', latas],
        ['[data-campo="producto_id"]', producto], ['[data-label="producto_id"]', elemento()], ['[data-total-latas]', output], ['[data-control="empleado"]', empleado],
        ['[data-control="rol"]', rol], ['[data-estado-participantes]', elemento()],
        ['[data-error-detalle]', elemento()], ['[data-participantes]', tags],
    ]);
    detalle.querySelector = selector => selectores.get(selector);
    detalle.querySelectorAll = selector => selector === '[data-participante]'
        ? [] : selector.startsWith('[data-campo="cantidad"]')
            ? [cantidad, forma, foto, observacion]
            : [coches, latas, producto, cantidad, forma, foto, observacion, empleado, rol];
    // Factor deliberadamente distinto: demostrar que el JS consume el dato publicado por Blade.
    const factor = 7;
    const formulario = elemento('', { latasPorCoche: String(factor), panUrl: '/produccion', tortaUrl: '/produccion/torta', bocaditoUrl: '/produccion/bocadito' });
    formulario.querySelector = selector => selector === '.detalle-pan' ? detalle : elemento();
    const ids = new Map([
        ['formulario-produccion', formulario], ['fecha', elemento('2026-10-09')],
        ['turno', elemento('turno')], ['categoria-seleccionada', elemento(categoria)],
        ['aviso-familia', elemento()], ['consultar-produccion', elemento()],
        ['forma-seleccionada', elemento()], ['btn-cancelar-form', elemento()],
    ]);
    const document = elemento();
    document.getElementById = id => ids.get(id);
    const botones = ['pan', 'torta', 'bocadito'].map(categoria => elemento('', { categoria }));
    const condicional = (mostrarEn, controles) => ({
        dataset: { mostrarEn }, style: {}, classList: { contains: () => true },
        querySelectorAll: () => controles,
    });
    const campos = [condicional('pan', [coches, latas]), condicional('torta bocadito', [cantidad]), condicional('torta', [forma, foto])];
    const error = elemento('', { errorFamilia: categoria });
    const registros = elemento('', { registrosFamilia: categoria });
    document.querySelectorAll = selector => selector === '.btn-categoria' ? botones
        : selector === '.campo-condicional' ? campos : selector === '[data-error-familia]' ? [error] : [];
    document.querySelector = () => registros;
    runInNewContext(codigo, { document, queueMicrotask });
    document.emitir('DOMContentLoaded');
    const actualizar = () => detalle.emitir('input', { target: coches });
    return { coches, latas, producto, observacion, output, formulario, ids, factor, actualizar, cantidad, forma, foto, botones, campos, error, registros };
}

test('preview interpreta vacíos como cero y consume el factor de Blade', () => {
    const e = entorno();
    for (const [coches, latas, total] of [['', '3', 3], ['2', '', 2 * e.factor], ['2', '3', 2 * e.factor + 3]]) {
        e.coches.value = coches;
        e.latas.value = latas;
        e.actualizar();
        assert.match(e.output.textContent, new RegExp(`= ${total} latas en total$`));
        assert.doesNotMatch(e.output.textContent, /NaN/);
    }
    e.coches.value = '';
    e.latas.value = '';
    e.actualizar();
    assert.equal(e.output.textContent, '0 latas en total. Ingresa al menos un coche o una lata adicional.');
});

test('preview distingue valores negativos decimales fuera de rango y badInput de un vacío válido', () => {
    const e = entorno();
    for (const [coches, latas] of [['-1', '3'], ['1.5', '0'], ['0', '-1'], ['0', '2.5'], ['0', String(e.factor)]]) {
        e.coches.value = coches;
        e.latas.value = latas;
        e.actualizar();
        assert.match(e.output.textContent, /^Introduce coches enteros/);
    }
    e.coches.value = '';
    e.coches.validity.badInput = true;
    e.latas.value = '3';
    e.actualizar();
    assert.match(e.output.textContent, /^Introduce coches enteros/);
});

test('limpiar producto deja cantidades en cero y conserva fecha y turno', async () => {
    const e = entorno();
    e.coches.value = '2';
    e.latas.value = '3';
    e.formulario.emitir('reset');
    await new Promise(resolve => queueMicrotask(resolve));
    assert.equal(e.coches.value, '0');
    assert.equal(e.latas.value, '0');
    assert.equal(e.producto.value, '');
    assert.equal(e.observacion.value, '');
    assert.equal(e.ids.get('fecha').value, '2026-10-09');
    assert.equal(e.ids.get('turno').value, 'turno');
    assert.match(e.output.textContent, /0 latas en total\. Ingresa al menos/);
});


test('cambiar familia selecciona endpoint catálogo campos y errores correspondientes', () => {
    const e = entorno();
    assert.equal(e.formulario.action, '/produccion');
    for (const familia of ['torta', 'bocadito', 'pan']) {
        e.botones.find(boton => boton.dataset.categoria === familia).emitir('click');
        assert.equal(e.ids.get('categoria-seleccionada').value, familia);
        assert.equal(e.formulario.action, familia === 'pan' ? '/produccion' : `/produccion/${familia}`);
        assert.equal(e.coches.disabled, familia !== 'pan');
        assert.equal(e.latas.disabled, familia !== 'pan');
        assert.equal(e.cantidad.disabled, familia === 'pan');
        assert.equal(e.forma.disabled, familia !== 'torta');
        assert.equal(e.foto.disabled, familia !== 'torta');
        assert.equal(e.error.hidden, familia !== 'pan');
        assert.equal(e.registros.hidden, familia !== 'pan');
        assert.equal(e.observacion.value, '');
        for (const option of e.producto.options.filter(option => option.dataset.familia)) {
            assert.equal(option.disabled, option.dataset.familia !== familia);
            assert.equal(option.hidden, option.disabled);
        }
        for (const boton of e.botones) {
            assert.equal(boton['aria-pressed'], String(boton.dataset.categoria === familia));
        }
    }
});

test('recuperar Torta mantiene familia old input y reset conserva su sesión', async () => {
    const e = entorno('torta');
    assert.equal(e.formulario.action, '/produccion/torta');
    assert.equal(e.forma.value, 'circular');
    assert.equal(e.cantidad.value, '4');
    assert.equal(e.observacion.value, 'nota');
    assert.equal(e.error.hidden, false);
    e.formulario.emitir('reset');
    await new Promise(resolve => queueMicrotask(resolve));
    assert.equal(e.ids.get('categoria-seleccionada').value, 'torta');
    assert.equal(e.formulario.action, '/produccion/torta');
    assert.equal(e.cantidad.value, '');
    assert.equal(e.forma.value, '');
    assert.equal(e.ids.get('fecha').value, '2026-10-09');
    assert.equal(e.ids.get('turno').value, 'turno');
});
