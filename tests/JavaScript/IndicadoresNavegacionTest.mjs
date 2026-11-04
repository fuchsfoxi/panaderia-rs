import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { runInNewContext } from 'node:vm';

const codigo = readFileSync(new URL('../../resources/js/indicadores-carga.js', import.meta.url), 'utf8')
    .replace(/^import .*;\s*/, '').replace('export function', 'function');

function entorno(api = true) {
    const ventana = new EventTarget();
    ventana.location = { href: 'https://example.test/inicio', origin: 'https://example.test' };
    if (api) ventana.navigation = new EventTarget();
    const mensaje = { textContent: '' };
    const estado = { hidden: true, classList: { toggle() {} }, querySelector: () => mensaje };
    let busy = false;
    const documento = { querySelector: selector => selector === '[data-carga-pagina]' ? estado : busy ? {} : null };
    const tareas = [];
    const timeout = (fn, delay = 0) => { const tarea = { fn, delay, cancelada: false }; tareas.push(tarea); return tarea; };
    runInNewContext(codigo, { window: ventana, document: documento, URL,
        HTMLFormElement: class {}, HTMLButtonElement: class {}, setTimeout: timeout,
        clearTimeout: tarea => { if (tarea) tarea.cancelada = true; } });
    const ejecutar = delay => tareas.filter(t => t.delay === delay && !t.cancelada).splice(0).forEach(t => { t.cancelada = true; t.fn(); });
    function navegar() {
        const controlador = new AbortController();
        const evento = new Event('navigate', { cancelable: true });
        Object.assign(evento, { hashChange: false, destination: { sameDocument: false, url: 'https://example.test/destino' }, downloadRequest: null, signal: controlador.signal });
        ventana.navigation.dispatchEvent(evento);
        return { controlador, evento };
    }
    function click() {
        const enlace = { href: 'https://example.test/destino', target: '', hasAttribute: () => false, getAttribute: () => '/destino' };
        const evento = new Event('click', { cancelable: true });
        ventana.closest = selector => selector === 'a[href]' ? enlace : null;
        Object.assign(evento, { button: 0 });
        ventana.dispatchEvent(evento);
        return evento;
    }
    return { ventana, estado, mensaje, ejecutar, navegar, click, ocupar: valor => { busy = valor; } };
}

test('aborto y cancelación tardía anteriores no ocultan la navegación vigente', () => {
    const e = entorno();
    const a = e.navegar();
    const b = e.navegar();
    a.controlador.abort();
    a.evento.preventDefault();
    e.ejecutar(0);
    assert.equal(e.estado.hidden, false);
    e.ventana.navigation.dispatchEvent(new Event('navigateerror'));
    e.ventana.navigation.dispatchEvent(new Event('navigatesuccess'));
    assert.equal(e.estado.hidden, false);
    b.controlador.abort();
    assert.equal(e.estado.hidden, true);
});

test('cancelación vigente y pagehide/pageshow restauran el indicador', () => {
    const e = entorno();
    e.navegar().evento.preventDefault();
    e.ejecutar(0);
    assert.equal(e.estado.hidden, true);
    e.navegar();
    e.ventana.dispatchEvent(new Event('pagehide'));
    assert.equal(e.estado.hidden, true);
    e.navegar();
    e.ventana.dispatchEvent(new Event('pageshow'));
    assert.equal(e.estado.hidden, true);
});

test('alternativa protege navegación posterior y recupera una cancelación o espera', () => {
    const e = entorno(false);
    const anterior = e.click();
    e.click();
    anterior.preventDefault();
    e.ejecutar(0);
    assert.equal(e.estado.hidden, false);
    e.ejecutar(15000);
    assert.equal(e.estado.hidden, true);
    const cancelada = e.click();
    cancelada.preventDefault();
    e.ejecutar(0);
    assert.equal(e.estado.hidden, true);
});

test('el envío propio de producción no muestra el indicador genérico', () => {
    const e = entorno();
    e.ocupar(true);
    const navegacion = e.navegar();
    assert.equal(e.estado.hidden, true);
    navegacion.controlador.abort();
    assert.equal(e.estado.hidden, true);
});
