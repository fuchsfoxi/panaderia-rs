import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { runInNewContext } from 'node:vm';
import { inicializarContrasenas } from '../../resources/js/contrasenas.js';

const codigo = readFileSync(new URL('../../resources/js/usuarios.js', import.meta.url), 'utf8')
    .replace("import { inicializarContrasenas } from './contrasenas.js';", '');

function entorno(operador, crear = true, tipo = 'crear') {
    const campos = ['password', 'password_confirmation'].map(id => {
        const input = { id, type: 'password', value: 'ejemplo-ficticio' };
        const boton = {
            previousElementSibling: input,
            label: 'Mostrar contraseña',
            addEventListener: (evento, callback) => { assert.equal(evento, 'click'); boton.click = callback; },
            setAttribute: (nombre, valor) => { assert.equal(nombre, 'aria-label'); boton.label = valor; },
        };
        return { input, boton };
    });
    const eventos = new Map();
    const turno = { disabled: false, required: false, value: 'turno-ficticio' };
    const grupo = { hidden: false, querySelector: () => turno };
    const rol = {
        selectedOptions: operador === null ? [] : [{ dataset: { operador: String(operador) } }],
        addEventListener: (evento, callback) => eventos.set(evento, callback),
    };
    const formulario = { querySelector: selector => selector === '[data-rol-usuario]' ? rol : grupo };
    const document = {
        querySelector: selector => crear && selector.split(',').map(s => s.trim()).includes(`[data-${tipo}-usuario]`) ? formulario : null,
        querySelectorAll: () => campos.map(campo => campo.boton),
    };
    runInNewContext(codigo, { document, inicializarContrasenas: () => inicializarContrasenas(document) });
    return { grupo, turno, campos, cambiar(operador) {
        rol.selectedOptions = [{ dataset: { operador: String(operador) } }];
        eventos.get('change')();
    } };
}

for (const operador of [true, false, null]) {
    test(`turno se exige y envía solo para Operador (${operador})`, () => {
        const ui = entorno(operador);
        assert.equal(ui.grupo.hidden, operador !== true);
        assert.equal(ui.turno.disabled, operador !== true);
        assert.equal(ui.turno.required, operador === true);
    });
}

test('cambiar de rol conserva selección temporal pero solo Operador envía turno', () => {
    const ui = entorno(true);
    ui.cambiar(false);
    assert.equal(ui.turno.disabled, true);
    ui.cambiar(true);
    assert.equal(ui.turno.disabled, false);
    assert.equal(ui.turno.value, 'turno-ficticio');
});

test('el listado y la página de contraseña no requieren formulario de creación', () => {
    assert.doesNotThrow(() => runInNewContext(codigo, {
        document: { querySelector: () => null },
        inicializarContrasenas: () => inicializarContrasenas({ querySelectorAll: () => [] }),
    }));
});

for (const crear of [true, false]) {
    test(`${crear ? 'crear usuario' : 'cambiar contraseña'}: cada campo alterna tipo y aria-label independientemente`, () => {
        const { campos } = entorno(true, crear);
        for (const [indice, { input, boton }] of campos.entries()) {
            const otro = campos[1 - indice];
            assert.equal(input.type, 'password');
            assert.equal(boton.label, 'Mostrar contraseña');
            boton.click();
            assert.equal(input.type, 'text');
            assert.equal(boton.label, 'Ocultar contraseña');
            assert.equal(otro.input.type, 'password');
            assert.equal(otro.boton.label, 'Mostrar contraseña');
            boton.click();
            assert.equal(input.type, 'password');
            assert.equal(boton.label, 'Mostrar contraseña');
            assert.equal(input.value, 'ejemplo-ficticio');
        }
        campos[0].boton.click();
        campos[1].boton.click();
        campos[0].boton.click();
        assert.equal(campos[0].input.type, 'password');
        assert.equal(campos[1].input.type, 'text');
    });
}

test('ambos formularios comparten botones que no envían el formulario y campos vacíos', () => {
    const parcial = readFileSync(new URL('../../resources/views/usuarios/password-fields.blade.php', import.meta.url), 'utf8');
    const botones = [...parcial.matchAll(/<button\b[^>]*>/g)].map(match => match[0]);
    assert.equal(botones.length, 2);
    for (const [indice, boton] of botones.entries()) {
        assert.match(boton, /type="button"/);
        assert.match(boton, /aria-label="Mostrar contraseña"/);
        assert.match(boton, new RegExp(`aria-controls="${['password', 'password_confirmation'][indice]}"`));
        assert.doesNotMatch(boton, /\b(?:name|tabindex|disabled)=/);
    }
    const inputs = [...parcial.matchAll(/<input\b[^>]*>/g)].map(match => match[0]);
    assert.equal(inputs.length, 2);
    for (const input of inputs) {
        assert.match(input, /type="password"/);
        assert.doesNotMatch(input, /\bvalue=|old\(|\bdata-/);
    }
    for (const vista of ['create', 'password']) {
        const contenido = readFileSync(new URL(`../../resources/views/usuarios/${vista}.blade.php`, import.meta.url), 'utf8');
        assert.match(contenido, /@include\('usuarios.password-fields'\)/);
    }
});


for (const operador of [true, false, null]) {
    test(`edición: turno inicial y cambios de rol funcionan sin formulario de creación (${operador})`, () => {
        const ui = entorno(operador, true, 'editar');
        assert.equal(ui.grupo.hidden, operador !== true);
        assert.equal(ui.turno.required, operador === true);
        assert.equal(ui.turno.disabled, operador !== true);
        ui.cambiar(true);
        assert.equal(ui.turno.required, true);
        assert.equal(ui.turno.disabled, false);
        ui.cambiar(false);
        assert.equal(ui.turno.disabled, true);
        assert.equal(ui.grupo.hidden, true);
    });
}
