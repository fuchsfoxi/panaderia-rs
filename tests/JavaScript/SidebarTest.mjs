import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { runInNewContext } from 'node:vm';

const codigo = readFileSync(new URL('../../resources/js/sidebar.js', import.meta.url), 'utf8');

// Dobles mínimos del DOM para comprobar eventos/foco; no simulan CSS ni un navegador.
function entorno(esMovil = true) {
    let document;
    const elemento = () => {
        const atributos = new Map();
        const eventos = new Map();
        const clases = new Set();
        return {
            hidden: false,
            inert: false,
            classList: {
                add: (clase) => clases.add(clase),
                toggle: (clase, activo) => activo ? clases.add(clase) : clases.delete(clase),
                contains: (clase) => clases.has(clase),
            },
            setAttribute: (nombre, valor) => atributos.set(nombre, valor),
            removeAttribute: (nombre) => atributos.delete(nombre),
            getAttribute: (nombre) => atributos.get(nombre),
            addEventListener: (evento, accion) => eventos.set(evento, accion),
            emitir: (evento, datos = {}) => eventos.get(evento)?.(datos),
            focus() { document.activeElement = this; },
        };
    };
    const panel = elemento();
    const abrir = elemento();
    const cerrar = elemento();
    const overlay = elemento();
    const contenido = elemento();
    const enlaces = [elemento(), elemento(), elemento()];
    const logout = elemento();
    const controles = [cerrar, ...enlaces, logout];
    panel.querySelectorAll = (selector) => selector === 'a[href]' ? enlaces : controles;
    panel.querySelector = () => enlaces[0];
    panel.contains = (actual) => actual === panel || controles.includes(actual);
    const selectores = new Map([
        ['[data-sidebar]', panel], ['[data-sidebar-abrir]', abrir],
        ['[data-sidebar-cerrar]', cerrar], ['[data-sidebar-overlay]', overlay],
        ['.dashboard, .pagina-produccion, .pagina-historial', contenido],
    ]);
    document = elemento();
    document.body = elemento();
    document.querySelector = (selector) => selectores.get(selector);
    const movil = elemento();
    movil.matches = esMovil;
    const window = elemento();
    window.matchMedia = () => movil;
    runInNewContext(codigo, { document, window });
    return { panel, abrir, cerrar, overlay, contenido, enlaces, logout, document, movil, window };
}

test('escritorio conserva la navegación disponible y oculta los controles móviles', () => {
    const e = entorno(false);
    assert.equal(e.panel.inert, false);
    assert.equal(e.abrir.hidden, true);
    assert.equal(e.cerrar.hidden, true);
    assert.equal(e.contenido.inert, false);
});

test('móvil abre el diálogo, actualiza ARIA y enfoca el botón de cerrar', () => {
    const e = entorno();
    assert.equal(e.panel.inert, true);
    assert.equal(e.abrir.hidden, false);
    e.abrir.emitir('click');
    assert.equal(e.panel.inert, false);
    assert.equal(e.panel.getAttribute('role'), 'dialog');
    assert.equal(e.panel.getAttribute('aria-modal'), 'true');
    assert.equal(e.abrir.getAttribute('aria-expanded'), 'true');
    assert.equal(e.overlay.hidden, false);
    assert.equal(e.contenido.inert, true);
    assert.equal(e.document.activeElement, e.cerrar);
});

test('botón overlay y enlaces cierran y devuelven el foco', () => {
    const e = entorno();
    for (const control of [e.cerrar, e.overlay, ...e.enlaces]) {
        e.abrir.emitir('click');
        control.emitir('click');
        assert.equal(e.abrir.getAttribute('aria-expanded'), 'false');
        assert.equal(e.panel.inert, true);
        assert.equal(e.contenido.inert, false);
        assert.equal(e.overlay.hidden, true);
        assert.equal(e.panel.getAttribute('aria-modal'), undefined);
        assert.equal(e.document.activeElement, e.abrir);
    }
});

test('Escape cierra y Tab recorre el panel en ambas direcciones', () => {
    const e = entorno();
    const tecla = (key, shiftKey = false) => {
        let evitado = false;
        e.document.emitir('keydown', { key, shiftKey, preventDefault: () => { evitado = true; } });
        return evitado;
    };
    assert.equal(tecla('Escape'), false);
    e.abrir.emitir('click');
    assert.equal(tecla('Tab', true), true);
    assert.equal(e.document.activeElement, e.logout);
    assert.equal(tecla('Tab'), true);
    assert.equal(e.document.activeElement, e.cerrar);
    assert.equal(tecla('Escape'), true);
    assert.equal(e.document.activeElement, e.abrir);
    assert.equal(e.contenido.inert, false);
});

test('cambio de breakpoint restaura foco y libera el contenido', () => {
    const e = entorno();
    e.abrir.emitir('click');
    e.movil.matches = false;
    e.movil.emitir('change');
    assert.equal(e.panel.inert, false);
    assert.equal(e.contenido.inert, false);
    assert.equal(e.document.activeElement, e.enlaces[0]);
    e.movil.matches = true;
    e.movil.emitir('change');
    assert.equal(e.panel.inert, true);
    assert.equal(e.document.activeElement, e.abrir);
});

test('pagehide elimina el estado abierto sin modificar scroll o sesión', () => {
    const e = entorno();
    e.abrir.emitir('click');
    e.window.emitir('pagehide');
    assert.equal(e.contenido.inert, false);
    assert.equal(e.overlay.hidden, true);
    assert.equal(e.abrir.getAttribute('aria-expanded'), 'false');
});

test('una página sin componente no requiere controles ni modifica el documento', () => {
    runInNewContext(codigo, { document: { querySelector: () => null } });
});
