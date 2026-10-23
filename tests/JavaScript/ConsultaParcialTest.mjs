import assert from 'node:assert/strict';
import { test } from 'node:test';
import { iniciarConsulta } from '../../resources/js/consulta-parcial.js';

function entorno(t) {
    const anteriores = Object.fromEntries(['fetch', 'location', 'FormData', 'DOMParser'].map(nombre => [nombre, globalThis[nombre]]));
    t.after(() => Object.entries(anteriores).forEach(([nombre, valor]) => { globalThis[nombre] = valor; }));
    const eventos = new Map();
    const estado = { textContent: '' }, error = { hidden: true }, esqueleto = { hidden: true, style: {} };
    const peticiones = [];
    let reemplazos = 0;
    function resultado(texto = 'Original') {
        return { texto, atributos: {}, setAttribute(k, v) { this.atributos[k] = v; }, removeAttribute(k) { delete this.atributos[k]; }, querySelector() { return { textContent: texto }; }, focus() {}, replaceWith(nuevo) { actual = nuevo; reemplazos++; } };
    }
    let actual = resultado();
    const fin = { type: 'hidden', value: '' }, inicio = { value: '2026-10-23' };
    const formulario = { dataset: { consultaUrl: '/produccion/registros' }, action: '/history', registros: 0, reportValidity: () => true,
        querySelector: s => s.includes('fecha_fin') ? fin : inicio,
        addEventListener(nombre, callback) { this.registros++; eventos.set(nombre, callback); },
    };
    const contenedor = { querySelector: s => s === '[resultado]' ? actual : s === '[data-estado-consulta]' ? estado : s === '[data-error-consulta]' ? error : esqueleto, addEventListener() {} };
    globalThis.location = { href: 'https://ejemplo.test/produccion' };
    globalThis.FormData = class { *[Symbol.iterator]() { yield ['fecha_inicio', inicio.value]; yield ['fecha_fin', fin.value]; } };
    globalThis.DOMParser = class { parseFromString(texto) { return { querySelector: () => resultado(texto) }; } };
    globalThis.fetch = (url, opciones) => new Promise(resolve => { peticiones.push({ url, opciones, resolve }); });
    iniciarConsulta(formulario, { contenedor, selector: '[resultado]' });
    return { formulario, contenedor, estado, error, esqueleto, peticiones, actual: () => actual, reemplazos: () => reemplazos,
        enviar() { eventos.get('submit')({ preventDefault() {} }); },
    };
}
const completar = (peticion, texto, ok = true) => peticion.resolve({ ok, redirected: false, text: async () => texto });
const estabilizar = () => new Promise(resolve => setImmediate(resolve));

test('skeleton dura únicamente durante la consulta y se reemplaza solo la región esperada', async t => {
    const e = entorno(t);
    e.enviar();
    assert.equal(e.esqueleto.hidden, false);
    assert.equal(e.actual().texto, 'Original');
    assert.equal(e.peticiones[0].url.searchParams.get('fecha_fin'), '2026-10-23');
    completar(e.peticiones[0], 'Resultados nuevos');
    await estabilizar();
    assert.equal(e.esqueleto.hidden, true);
    assert.equal(e.actual().texto, 'Resultados nuevos');
    assert.equal(e.error.hidden, true);
});

test('una consulta fallida conserva los resultados anteriores y muestra error recuperable', async t => {
    const e = entorno(t);
    e.enviar(); completar(e.peticiones[0], '', false);
    await estabilizar();
    assert.equal(e.reemplazos(), 0);
    assert.equal(e.actual().texto, 'Original');
    assert.equal(e.error.hidden, false);
    assert.match(e.error.textContent, /No se pudo/);
    assert.equal(e.esqueleto.hidden, true);
});

test('la consulta reciente gana aunque el transporte entregue después una respuesta cancelada', async t => {
    const e = entorno(t);
    iniciarConsulta(e.formulario, { contenedor: e.contenedor, selector: '[resultado]' });
    assert.equal(e.formulario.registros, 1);
    e.enviar(); e.enviar();
    assert.equal(e.peticiones[0].opciones.signal.aborted, true);
    completar(e.peticiones[1], 'Reciente'); await estabilizar();
    completar(e.peticiones[0], 'Obsoleta'); await estabilizar();
    assert.equal(e.actual().texto, 'Reciente');
    assert.equal(e.reemplazos(), 1);
    assert.equal(e.esqueleto.hidden, true);
});
