import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';

// Herramienta de desarrollo opcional, sin dependencia en la aplicación ni acceso a la BD.
// PUPPETEER_MODULE=/ruta/puppeteer/lib/puppeteer/puppeteer.js
// PRODUCCION_BROWSER_HTML=/tmp/fixtures node tests/Browser/ProduccionMovil.mjs [--baseline]
const { default: puppeteer } = await import(process.env.PUPPETEER_MODULE || 'puppeteer');
const directorio = process.env.PRODUCCION_BROWSER_HTML;
assert.ok(directorio?.startsWith('/tmp/'), 'Usar exclusivamente HTML de fixtures aislados en /tmp/.');
const baseline = process.argv.includes('--baseline');
const browser = await puppeteer.launch({
    executablePath: process.env.CHROME_BIN || '/opt/google/chrome/chrome',
    headless: true, args: ['--no-sandbox', '--disable-dev-shm-usage'],
});
const errores = [];
const resultados = [];
try {
    for (const ancho of [360, 412, 1280]) {
        const page = await browser.newPage();
        await page.setViewport({ width: ancho, height: 850, isMobile: ancho < 500, hasTouch: ancho < 500 });
        page.on('pageerror', error => errores.push(error.message));
        await page.setRequestInterception(true);
        page.on('request', async request => {
            const url = new URL(request.url());
            try {
                if (url.pathname.startsWith('/build/assets/')) {
                    assert.equal(request.method(), 'GET');
                    const file = resolve('public', '.' + url.pathname);
                    assert.ok(file.startsWith(resolve('public/build/assets') + '/'));
                    const nombre = url.pathname.split('/').at(-1);
                    const original = baseline && /^produccion-.*\.(js|css)$/.test(nombre)
                        ? resolve(directorio, 'produccion.' + nombre.split('.').at(-1))
                        : baseline && ['sidebar.css', 'tokens.css'].includes(nombre) ? resolve('resources/css', nombre) : file;
                    await request.respond({ status: 200, headers: { 'Access-Control-Allow-Origin': '*' }, contentType: file.endsWith('.css') ? 'text/css' : /\.woff2?$/.test(file) ? 'font/woff2' : 'application/javascript', body: await readFile(original) });
                } else if (url.pathname === '/produccion' && request.method() === 'GET') {
                    const familia = url.searchParams.get('categoria') || 'pan';
                    assert.ok(['pan', 'torta', 'bocadito', 'exito', 'operador-noche'].includes(familia));
                    await request.respond({ status: 200, contentType: 'text/html; charset=utf-8', body: await readFile(resolve(directorio, familia + '.html')) });
                } else {
                    await request.abort(); // Ninguna petición llega al servidor operativo.
                }
            } catch (error) {
                errores.push(error.message);
                if (!request.isInterceptResolutionHandled()) await request.abort();
            }
        });
        await page.goto('http://localhost/produccion', { waitUntil: 'networkidle0' });
        if (ancho === 360) {
            const cdp = await page.createCDPSession();
            await cdp.send('Emulation.setCPUThrottlingRate', { rate: 4 });
        }
        const transiciones = await page.evaluate(async () => {
            const duraciones = [];
            const botones = [...document.querySelectorAll('[data-categoria]')];
            for (let n = 0; n < 90; n++) {
                const boton = botones[n % botones.length];
                const inicio = performance.now();
                boton.click();
                duraciones.push(performance.now() - inicio);
                if (document.querySelector('#categoria-seleccionada').value !== boton.dataset.categoria) throw Error('Categoría inconsistente');
                await new Promise(requestAnimationFrame);
            }
            return { cantidad: duraciones.length, maxMs: Math.max(...duraciones), promedioMs: duraciones.reduce((a, b) => a + b, 0) / duraciones.length };
        });
        assert.ok(transiciones.maxMs < 1000, 'Cambio de categoría bloqueado más de un segundo.');
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'Desbordamiento horizontal.');
        if (!baseline) {
            await comprobarInteracciones(page);
            await page.screenshot({ path: resolve(directorio, `produccion-${ancho}.png`), fullPage: true });
        }
        resultados.push({ ancho, ...transiciones });
        await page.close();
    }
    assert.deepEqual(errores, []);
    console.log(JSON.stringify({ modo: baseline ? 'baseline' : 'final', resultados, errores }, null, 2));
} catch (error) {
    console.error(JSON.stringify({ errores, resultados }));
    throw error;
} finally {
    await browser.close();
}

async function comprobarInteracciones(page) {
    const resultado = await page.evaluate(() => {
        const $ = s => document.querySelector(s);
        const change = control => control.dispatchEvent(new Event('change', { bubbles: true }));
        const categoria = familia => $(`[data-categoria="${familia}"]`).click();
        const elegirGrupo = familia => {
            const grupo = $('#grupo-produccion');
            grupo.value = [...grupo.options].find(o => o.dataset.familia === familia).value;
            change(grupo);
        };
        const tags = () => [...document.querySelectorAll('[data-participante]')];
        const checks = [];
        const verificar = (valor, mensaje) => { if (!valor) throw Error(mensaje); checks.push(mensaje); };
        const simularEnvio = form => {
            let rechazado;
            form.addEventListener('submit', event => {
                rechazado = event.defaultPrevented;
                event.preventDefault(); // El harness detiene también el envío aceptado por la app.
            }, { once: true });
            form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
            return rechazado;
        };
        for (const familia of ['pan', 'torta', 'bocadito']) {
            categoria(familia);
            verificar($('#turno').disabled === (familia !== 'pan'), 'Selector turno: ' + familia);
            verificar($('#turno-manana').disabled === (familia === 'pan'), 'Mañana fija: ' + familia);
            verificar([...$('#grupo-produccion').options].filter(o => o.value && !o.disabled).every(o => o.dataset.familia === familia), 'Grupos por familia: ' + familia);
            elegirGrupo(familia);
            verificar(tags().length === (familia === 'torta' ? 1 : 2), 'Equipo propuesto: ' + familia);
            elegirGrupo(familia);
            verificar(tags().length === (familia === 'torta' ? 1 : 2), 'Grupo sin duplicados: ' + familia);
            const opcionesEmpleados = [...$('[data-control="empleado"]').options].filter(o => o.value);
            const prioridad = opcionesEmpleados.map(o => (o.dataset.categorias || '').split(' ').includes(familia));
            verificar(!prioridad.some((valor, indice) => valor && prioridad.slice(0, indice).includes(false)), 'Empleados habituales primero: ' + familia);
            verificar($('[data-control="rol"]').disabled, 'Rol actual protegido: ' + familia);
            const form = $('#formulario-produccion');
            if (familia === 'pan') {
                $('#turno').value = [...$('#turno').options].find(o => o.textContent === 'Mañana').value;
            }
            const producto = $('[data-campo="producto_id"]');
            producto.value = [...producto.options].find(o => o.dataset.familia === familia).value;
            change(producto);
            if (familia === 'pan') {
                $('[data-campo="coches"]').value = '1';
                $('#detalle-hora-finalizacion-hora').value = '08';
                $('#detalle-hora-finalizacion-minutos').value = '30';
                change($('#detalle-hora-finalizacion-minutos'));
                verificar($('[data-campo="hora_finalizacion"]').value === '08:30', 'Hora finalización compacta');
            } else {
                $('[data-campo="cantidad"]').value = '12';
            }
            if (familia === 'torta') {
                $('[data-campo="forma"]').value = 'circular';
                const hora = $('[data-campo="hora_vitrina"]');
                $('#detalle-hora-vitrina-hora').value = '14';
                change($('#detalle-hora-vitrina-hora'));
                verificar(!$('#detalle-hora-vitrina-minutos').checkValidity() && hora.value === '', 'Hora incompleta rechazada');
                $('#detalle-hora-vitrina-minutos').value = '15';
                change($('#detalle-hora-vitrina-minutos'));
                verificar(hora.value === '14:15' && $('#detalle-hora-vitrina-minutos').checkValidity(), 'Hora vitrina compacta');
            }
            verificar(form.checkValidity(), 'Formulario válido: ' + familia);
            if (familia !== 'torta') {
                tags().find(t => t.dataset.rolNombre === 'Ayudante').querySelector('button').click();
                verificar(simularEnvio(form), 'Falta Ayudante rechazada: ' + familia);
                elegirGrupo(familia);
            }
            if (familia === 'bocadito') {
                const rolAyudante = [...$('[data-control="rol"]').options].find(o => o.dataset.rolNombre === 'Ayudante').value;
                verificar(opcionesEmpleados.filter(o => o.dataset.rolId === rolAyudante).every(o => o.disabled), 'Segundo Ayudante bloqueado');
            }
            // Evento submit sin navegar: confirma reglas y bloqueo; ningún POST sale al servidor.
            verificar(!simularEnvio(form) && form.getAttribute('aria-busy') === 'true', 'Inicio envío: ' + familia);
            verificar(simularEnvio(form), 'Doble envío bloqueado: ' + familia);
            // persisted=true activa además la recarga de autenticación de app.js.
            window.dispatchEvent(new PageTransitionEvent('pageshow', { persisted: false }));
            verificar(!$('#formulario-produccion button[type="submit"]').disabled, 'Rehabilitación al mostrar página: ' + familia);
            const maestro = tags().find(t => t.dataset.rolNombre === 'Maestro');
            maestro.querySelector('button').click();
            verificar(simularEnvio(form), 'Falta Maestro rechazada: ' + familia);
        }
        return checks;
    });
    assert.ok(resultado.length >= 30);
    await page.goto('http://localhost/produccion?categoria=operador-noche', { waitUntil: 'networkidle0' });
    await page.click('[data-categoria="torta"]');
    assert.ok(await page.$eval('#formulario-produccion button[type="submit"]', e => e.disabled), 'Operador Noche no puede registrar Torta');
    await page.click('[data-categoria="pan"]');
    assert.equal(await page.$eval('#formulario-produccion button[type="submit"]', e => e.disabled), false);
    await page.emulateMediaFeatures([{ name: 'prefers-reduced-motion', value: 'reduce' }]);
    await page.goto('http://localhost/produccion?categoria=exito', { waitUntil: 'domcontentloaded' });
    assert.equal(await page.$eval('[data-exito-produccion]', e => e.getAttribute('role')), 'status');
    assert.equal(await page.$eval('[data-exito-produccion] svg path', e => getComputedStyle(e).animationName), 'none');
    await page.waitForFunction(() => document.querySelector('[data-exito-produccion]').hidden, { timeout: 6000 });
    assert.ok(await page.$eval('[data-categoria="pan"]', e => !e.disabled));
}
