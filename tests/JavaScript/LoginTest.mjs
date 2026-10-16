import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { runInNewContext } from 'node:vm';
import { inicializarContrasenas } from '../../resources/js/contrasenas.js';

const codigo = readFileSync(new URL('../../resources/js/login.js', import.meta.url), 'utf8')
    .replace("import { inicializarContrasenas } from './contrasenas.js';", '');

function entorno(segundos = null) {
    let reloj = 1_000_000;
    const eventosDocumento = new Map();
    const eventosVentana = new Map();
    const intervalos = new Map();
    const contador = { textContent: String(segundos) };
    const botonEnviar = {
        disabled: segundos !== null,
        descripcion: segundos === null ? null : 'login-wait',
        removeAttribute(nombre) { if (nombre === 'aria-describedby') this.descripcion = null; },
    };
    const espera = segundos === null ? null : {
        dataset: { loginRetryAfter: String(segundos) },
        hidden: false,
        querySelector: () => contador,
    };
    const password = { type: 'password', value: 'texto-ficticio-corregido', disabled: false };
    const toggle = {
        previousElementSibling: password,
        addEventListener: (_, callback) => { toggle.click = callback; },
        setAttribute: (_, valor) => { toggle.label = valor; },
    };
    const redProhibida = () => { assert.fail('El countdown no debe enviar peticiones ni formularios.'); };
    class Reloj extends Date { static now() { return reloj; } }
    runInNewContext(codigo, {
        inicializarContrasenas: () => inicializarContrasenas({ querySelectorAll: () => [toggle] }),
        document: {
            querySelector: selector => ({
                '.alternar-contrasena': toggle,
                '[data-login-retry-after]': espera,
                '[data-login-submit]': botonEnviar,
            })[selector] ?? null,
            getElementById: () => password,
            addEventListener: (evento, callback) => {
                if (evento === 'DOMContentLoaded') callback();
                else eventosDocumento.set(evento, callback);
            },
            removeEventListener: evento => eventosDocumento.delete(evento),
        },
        window: {
            addEventListener: (evento, callback) => eventosVentana.set(evento, callback),
            removeEventListener: evento => eventosVentana.delete(evento),
            fetch: redProhibida,
        },
        Date: Reloj,
        setInterval: (callback, delay) => {
            assert.equal(delay, 1000);
            intervalos.set(1, callback);
            return 1;
        },
        clearInterval: id => intervalos.delete(id),
        fetch: redProhibida,
        XMLHttpRequest: redProhibida,
    });
    return {
        espera, contador, botonEnviar, password, toggle, intervalos,
        avanzar(ms, evento = 'intervalo') {
            reloj += ms;
            if (evento === 'intervalo') for (const callback of intervalos.values()) callback();
            else (evento === 'pageshow' ? eventosVentana : eventosDocumento).get(evento)?.();
        },
    };
}

test('sin bloqueo no cambia el botón ni inicia un contador', () => {
    const ui = entorno();
    assert.equal(ui.botonEnviar.disabled, false);
    assert.equal(ui.intervalos.size, 0);
});

test('countdown muestra 38, 37 y 36 con el botón deshabilitado', () => {
    const ui = entorno(38);
    assert.equal(ui.contador.textContent, '38');
    assert.equal(ui.botonEnviar.disabled, true);
    ui.avanzar(1000);
    assert.equal(ui.contador.textContent, '37');
    ui.avanzar(1000);
    assert.equal(ui.contador.textContent, '36');
    assert.equal(ui.botonEnviar.disabled, true);
});

test('un callback tardío usa deadline y recuperar visibilidad termina la espera', () => {
    const ui = entorno(38);
    ui.avanzar(30_000);
    assert.equal(ui.contador.textContent, '8');
    ui.avanzar(8000, 'visibilitychange');
    assert.equal(ui.contador.textContent, '0');
    assert.equal(ui.espera.hidden, true);
    assert.equal(ui.botonEnviar.disabled, false);
    assert.equal(ui.botonEnviar.descripcion, null);
    assert.equal(ui.intervalos.size, 0);
});

test('pageshow recalcula el tiempo y nunca muestra segundos negativos ni envía peticiones', () => {
    const ui = entorno(38);
    ui.avanzar(90_000, 'pageshow');
    assert.equal(ui.contador.textContent, '0');
    assert.equal(ui.espera.hidden, true);
    assert.equal(ui.botonEnviar.disabled, false);
});

test('durante la espera se puede corregir y mostrar la contraseña sin perder su valor', () => {
    const ui = entorno(38);
    assert.equal(ui.password.disabled, false);
    ui.toggle.click();
    assert.equal(ui.password.type, 'text');
    assert.equal(ui.toggle.label, 'Ocultar contraseña');
    ui.password.value = 'otro-texto-ficticio';
    ui.avanzar(1000);
    assert.equal(ui.password.value, 'otro-texto-ficticio');
    assert.equal(ui.botonEnviar.disabled, true);
});

test('si el plazo visual ya es cero habilita el botón sin iniciar intervalo', () => {
    const ui = entorno(0);
    assert.equal(ui.espera.hidden, true);
    assert.equal(ui.botonEnviar.disabled, false);
    assert.equal(ui.intervalos.size, 0);
});

test('metadatos DOM inválidos conservan el estado estático sin ejecutar HTML', () => {
    for (const valor of ['<script>fallar()</script>', '-1', '1.5', '9007199254740992']) {
        const ui = entorno(valor);
        assert.equal(ui.botonEnviar.disabled, true);
        assert.equal(ui.espera.hidden, false);
        assert.equal(ui.intervalos.size, 0);
    }
});

test('el toggle sigue funcionando cuando no hay mensaje de espera', () => {
    const ui = entorno();
    ui.toggle.click();
    assert.equal(ui.password.type, 'text');
    ui.toggle.click();
    assert.equal(ui.password.type, 'password');
    assert.equal(ui.toggle.label, 'Mostrar contraseña');
});
