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
        removeAttribute(nombre) { delete this[nombre]; },
        focus() { this.enfocado = true; },
        reportValidity() { return true; },
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
    const camara = elemento('', { campo: 'foto_camara' });
    const hora = elemento('08:35', { campo: 'hora_finalizacion' });
    const preview = elemento();
    const nombreFoto = elemento();
    const iconoFoto = elemento();
    const quitarFoto = elemento();
    const labelFoto = elemento();
    const editor = elemento();
    editor.hidden = true;
    const abrir = elemento();
    const formas = ['circular', 'rectangular'].map(forma => elemento('', { forma }));
    const observacion = elemento('nota', { campo: 'observacion' });
    const empleado = elemento();
    const rol = elemento();
    const output = elemento();
    const tags = elemento();
    tags.replaceChildren = () => {};
    const detalle = elemento();
    const selectores = new Map([
        ['#editor-participantes', editor], ['[data-abrir-participantes]', abrir],
        ['[data-campo="forma"]', forma], ['[data-campo="foto"]', foto], ['[data-campo="foto_camara"]', camara],
        ['[data-preview-foto]', preview], ['[data-nombre-foto]', nombreFoto], ['[data-icono-foto]', iconoFoto],
        ['[data-quitar-foto]', quitarFoto], ['[data-label-foto]', labelFoto],
        ['[data-campo="coches"]', coches], ['[data-campo="latas_adicionales"]', latas],
        ['[data-campo="producto_id"]', producto], ['[data-label="producto_id"]', elemento()], ['[data-total-latas]', output], ['[data-control="empleado"]', empleado],
        ['[data-control="rol"]', rol], ['[data-estado-participantes]', elemento()],
        ['[data-error-detalle]', elemento()], ['[data-participantes]', tags],
    ]);
    detalle.querySelector = selector => selectores.get(selector);
    detalle.querySelectorAll = selector => selector === '[data-forma]' ? formas : selector === '[data-participante]'
        ? [] : selector.startsWith('[data-campo="cantidad"]')
            ? [cantidad, forma, foto, observacion]
            : [coches, latas, producto, cantidad, forma, foto, camara, hora, observacion, empleado, rol];
    // Factor deliberadamente distinto: demostrar que el JS consume el dato publicado por Blade.
    const factor = 7;
    const formulario = elemento('', { latasPorCoche: String(factor), panUrl: '/produccion', tortaUrl: '/produccion/torta', bocaditoUrl: '/produccion/bocadito' });
    formulario.querySelector = selector => selector === '.detalle-pan' ? detalle : elemento();
    const ids = new Map([
        ['formulario-produccion', formulario], ['fecha', elemento('2026-10-09')],
        ['turno', elemento('turno')], ['categoria-seleccionada', elemento(categoria)],
        ['aviso-familia', elemento()], ['consultar-produccion', elemento('', { url: '/produccion' })],
        ['forma-seleccionada', elemento()], ['btn-cancelar-form', elemento()],
    ]);
    const document = elemento();
    document.getElementById = id => ids.get(id);
    const botones = ['pan', 'torta', 'bocadito'].map(categoria => elemento('', { categoria }));
    const condicional = (mostrarEn, controles) => ({
        dataset: { mostrarEn }, style: {}, classList: { contains: () => true },
        querySelectorAll: () => controles,
    });
    const campos = [condicional('pan', [coches, latas, hora]), condicional('torta bocadito', [cantidad]), condicional('torta', [forma, foto, camara, quitarFoto, ...formas])];
    const error = elemento('', { errorFamilia: categoria });
    const registros = elemento('', { registrosFamilia: categoria });
    document.querySelectorAll = selector => selector === '.btn-categoria' ? botones
        : selector === '.campo-condicional' ? campos : selector === '[data-error-familia]' ? [error] : [];
    document.querySelector = () => registros;
    const revocadas = [];
    let numeroUrl = 0;
    const window = elemento();
    window.location = { href: 'https://ejemplo.test/produccion', assign(url) { this.destino = url; } };
    class PreviewURL extends URL {
        static createObjectURL() { return `blob:foto-${++numeroUrl}`; }
        static revokeObjectURL(url) { revocadas.push(url); }
    }
    runInNewContext(codigo, { document, window, URL: PreviewURL, queueMicrotask });
    document.emitir('DOMContentLoaded');
    const actualizar = () => detalle.emitir('input', { target: coches });
    return { coches, latas, producto, observacion, output, formulario, ids, factor, actualizar, cantidad, forma, foto, botones, campos, error, registros, camara, hora, preview, nombreFoto, iconoFoto, quitarFoto, labelFoto, revocadas, formas, editor, abrir, detalle, window };
}

test('preview interpreta vacíos como cero y consume el factor de Blade', () => {
    const e = entorno();
    for (const [coches, latas, total] of [['', '3', 3], ['2', '', 2 * e.factor], ['2', '3', 2 * e.factor + 3]]) {
        e.coches.value = coches;
        e.latas.value = latas;
        e.actualizar();
        assert.equal(e.output.textContent, `${total} latas`);
        assert.doesNotMatch(e.output.textContent, /NaN/);
    }
    e.coches.value = '';
    e.latas.value = '';
    e.actualizar();
    assert.equal(e.output.textContent, '0 latas');
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
    assert.equal(e.output.textContent, '0 latas');
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
        assert.equal(e.camara.disabled, familia !== 'torta');
        assert.equal(e.hora.disabled, familia !== 'pan');
        assert.equal(e.hora.value, '');
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


function seleccionarFoto(e, input, nombre = 'torta.png', tipo = 'image/png') {
    input.value = nombre;
    input.files = [{ name: nombre, type: tipo }];
    input.emitir('change');
}

test('galería y cámara muestran preview local y envían únicamente la selección activa', () => {
    const e = entorno('torta');
    seleccionarFoto(e, e.foto);
    assert.equal(e.preview.hidden, false);
    assert.equal(e.preview.src, 'blob:foto-1');
    assert.equal(e.nombreFoto.textContent, 'torta.png');
    assert.equal(e.foto.name, 'detalles[0][foto]');
    assert.equal(e.camara.name, undefined);
    assert.equal(e.quitarFoto.hidden, false);
    assert.equal(e.labelFoto.textContent, 'Cambiar foto');
    seleccionarFoto(e, e.camara, 'camara.jpg', 'image/jpeg');
    assert.equal(e.preview.src, 'blob:foto-2');
    assert.equal(e.foto.value, '');
    assert.equal(e.foto.name, undefined);
    assert.equal(e.camara.name, 'detalles[0][foto]');
    assert.deepEqual(e.revocadas, ['blob:foto-1']);
});

test('quitar foto vacía los dos selectores y libera la URL de preview', () => {
    const e = entorno('torta');
    seleccionarFoto(e, e.camara);
    e.quitarFoto.emitir('click');
    assert.equal(e.preview.hidden, true);
    assert.equal(e.preview.src, undefined);
    assert.equal(e.foto.value, '');
    assert.equal(e.camara.value, '');
    assert.equal(e.foto.name, 'detalles[0][foto]');
    assert.equal(e.camara.name, undefined);
    assert.equal(e.iconoFoto.hidden, false);
    assert.equal(e.quitarFoto.hidden, true);
    assert.deepEqual(e.revocadas, ['blob:foto-1']);
});

test('cambio de categoría y cancelar limpian preview sin enviar controles ocultos', async () => {
    const e = entorno('torta');
    seleccionarFoto(e, e.camara);
    e.botones.find(boton => boton.dataset.categoria === 'pan').emitir('click');
    assert.equal(e.preview.hidden, true);
    assert.equal(e.camara.disabled, true);
    assert.equal(e.foto.disabled, true);
    assert.equal(e.hora.disabled, false);
    assert.equal(e.hora.value, '');
    e.botones.find(boton => boton.dataset.categoria === 'torta').emitir('click');
    seleccionarFoto(e, e.foto);
    e.formulario.emitir('reset');
    await new Promise(resolve => queueMicrotask(resolve));
    assert.equal(e.preview.hidden, true);
    assert.equal(e.foto.value, '');
    assert.deepEqual(e.revocadas, ['blob:foto-1', 'blob:foto-2']);
});

test('forma conserva texto libre y botones rápidos mantienen su selección accesible', () => {
    const e = entorno('torta');
    assert.equal(e.formas[0]['aria-pressed'], 'true');
    e.formas[1].emitir('click');
    assert.equal(e.forma.value, 'rectangular');
    assert.equal(e.formas[1]['aria-pressed'], 'true');
    e.forma.value = 'Corazón';
    e.forma.emitir('input');
    assert.equal(e.forma.value, 'Corazón');
    assert.equal(e.formas[0]['aria-pressed'], 'false');
    assert.equal(e.formas[1]['aria-pressed'], 'false');
});

test('botón más abre participantes y Escape cierra y devuelve el foco', () => {
    const e = entorno();
    const boton = { hasAttribute: atributo => atributo === 'data-abrir-participantes' };
    e.detalle.emitir('click', { target: { closest: () => boton } });
    assert.equal(e.editor.hidden, false);
    assert.equal(e.abrir['aria-expanded'], 'true');
    let prevenido = false;
    e.editor.emitir('keydown', { key: 'Escape', preventDefault() { prevenido = true; } });
    assert.equal(prevenido, true);
    assert.equal(e.editor.hidden, true);
    assert.equal(e.abrir.enfocado, true);
});

test('preview fallido permite cambiar foto y BFCache conserva la URL seleccionada', () => {
    const e = entorno('torta');
    seleccionarFoto(e, e.foto);
    e.window.emitir('pagehide', { persisted: true });
    assert.equal(e.preview.hidden, false);
    assert.deepEqual(e.revocadas, []);
    e.preview.emitir('error');
    assert.equal(e.preview.hidden, true);
    assert.equal(e.iconoFoto.hidden, false);
    assert.equal(e.quitarFoto.hidden, false);
    assert.equal(e.foto.name, 'detalles[0][foto]');
    assert.deepEqual(e.revocadas, ['blob:foto-1']);
});


test('consulta sigue accesible y utiliza la categoría elegida después de cambiar familia', () => {
    const e = entorno();
    for (const categoria of ['torta', 'bocadito', 'pan']) {
        e.botones.find(boton => boton.dataset.categoria === categoria).emitir('click');
        const boton = e.ids.get('consultar-produccion');
        boton.emitir('click', { currentTarget: boton });
        const destino = e.window.location.destino;
        assert.equal(destino.searchParams.get('fecha'), '2026-10-09');
        assert.equal(destino.searchParams.get('turno_id'), 'turno');
        assert.equal(destino.searchParams.get('categoria'), categoria === 'pan' ? null : categoria);
    }
});
