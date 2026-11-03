import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { runInNewContext } from 'node:vm';
import { iniciarConsulta } from '../../resources/js/consulta-parcial.js';

const codigo = readFileSync(new URL('../../resources/js/consultas-produccion.js', import.meta.url), 'utf8').replace(/^import .*;$/gm, '');
const estabilizar = () => new Promise(resolve => setImmediate(resolve));

function entorno(t) {
    const anteriores = { fetch: globalThis.fetch, DOMParser: globalThis.DOMParser };
    t.after(() => Object.assign(globalThis, anteriores));
    const eventos = new Map(), peticiones = [];
    const estado = { textContent: '' }, error = { hidden: true }, esqueleto = { hidden: true, style: {} };
    const resultado = texto => ({
        texto, offsetTop: 0, offsetHeight: 100,
        setAttribute() {}, removeAttribute() {},
        replaceChildren() { this.texto = ''; },
        replaceWith(nuevo) { actual = nuevo; },
        querySelector() { return { textContent: this.texto }; },
        focus() { this.enfocado = true; },
    });
    let actual = resultado('Pan inicial');
    const contenedor = {
        dataset: { consultaUrl: '/produccion/registros' }, contains: () => true,
        querySelector(s) { return s === '[data-resultados-registros]' ? actual : s === '[data-estado-consulta]' ? estado : s === '[data-error-consulta]' ? error : esqueleto; },
        addEventListener(nombre, callback) { eventos.set(nombre, callback); },
    };
    const formulario = { addEventListener(nombre, callback) { eventos.set(nombre, callback); } };
    const document = {
        querySelector: () => contenedor, getElementById: () => formulario,
        addEventListener(nombre, callback) { eventos.set(nombre, callback); },
    };
    globalThis.DOMParser = class { parseFromString(texto) { return { querySelector: () => resultado(texto) }; } };
    globalThis.fetch = (url, opciones) => new Promise((resolve, reject) => peticiones.push({ url: new URL(url), opciones, resolve, reject }));
    runInNewContext(codigo, { document, iniciarConsulta, URL, location: { href: 'https://ejemplo.test/produccion?categoria=pan&page=3' } });
    eventos.get('DOMContentLoaded')();
    return {
        peticiones, estado, error, esqueleto, actual: () => actual,
        cambiar(categoria) { eventos.get('produccion:seccion')({ detail: { categoria } }); },
        completar(indice, texto, ok = true) { peticiones[indice].resolve({ ok, redirected: false, text: async () => texto }); },
        paginar(href) { eventos.get('click')({ target: { closest: () => ({ href }) }, preventDefault() {} }); },
    };
}

test('conserva la carga inicial del servidor y consulta solo la sección activa desde página uno', async t => {
    const e = entorno(t);
    assert.equal(e.peticiones.length, 0);
    assert.equal(e.actual().texto, 'Pan inicial');
    for (const categoria of ['torta', 'bocadito', 'pan']) {
        e.cambiar(categoria);
        assert.equal(e.actual().texto, '');
        assert.equal(e.esqueleto.hidden, false);
        const indice = e.peticiones.length - 1;
        assert.equal(e.peticiones[indice].url.pathname, '/produccion/registros');
        assert.deepEqual([...e.peticiones[indice].url.searchParams], [['categoria', categoria]]);
        e.completar(indice, `Registros ${categoria}`);
        await estabilizar();
        assert.equal(e.actual().texto, `Registros ${categoria}`);
        assert.equal(e.esqueleto.hidden, true);
    }
});

test('cambios rápidos cancelan solicitudes y descartan éxitos y errores obsoletos', async t => {
    const e = entorno(t);
    e.cambiar('torta'); e.cambiar('bocadito'); e.cambiar('pan');
    assert.equal(e.peticiones[0].opciones.signal.aborted, true);
    assert.equal(e.peticiones[1].opciones.signal.aborted, true);
    e.completar(2, 'Pan reciente'); await estabilizar();
    e.completar(0, 'Torta obsoleta');
    e.peticiones[1].reject(Error('Error obsoleto'));
    await estabilizar();
    assert.equal(e.actual().texto, 'Pan reciente');
    assert.equal(e.error.hidden, true);
    assert.equal(e.esqueleto.hidden, true);
});

test('un fallo no restaura tarjetas de otra sección y permite recuperarse con un listado vacío', async t => {
    const e = entorno(t);
    e.cambiar('torta'); e.completar(0, '', false); await estabilizar();
    assert.equal(e.actual().texto, '');
    assert.equal(e.error.hidden, false);
    assert.equal(e.esqueleto.hidden, true);
    e.cambiar('torta'); e.completar(1, 'No hay registros en esta sección.'); await estabilizar();
    assert.equal(e.actual().texto, 'No hay registros en esta sección.');
    assert.equal(e.error.hidden, true);
});

test('paginar conserva familia y descarta una página pendiente al cambiar de sección', async t => {
    const e = entorno(t);
    e.paginar('https://ejemplo.test/produccion/registros?categoria=pan&page=2');
    assert.equal(e.peticiones[0].url.searchParams.get('categoria'), 'pan');
    assert.equal(e.peticiones[0].url.searchParams.get('page'), '2');
    e.completar(0, 'Pan página 2'); await estabilizar();
    assert.equal(e.actual().enfocado, true);
    e.paginar('https://ejemplo.test/produccion/registros?categoria=pan&page=3');
    e.cambiar('torta');
    assert.equal(e.peticiones[1].opciones.signal.aborted, true);
    assert.equal(e.actual().texto, '');
    e.completar(2, 'Torta página 1'); await estabilizar();
    e.completar(1, 'Pan página 3 obsoleta'); await estabilizar();
    assert.equal(e.actual().texto, 'Torta página 1');
});
