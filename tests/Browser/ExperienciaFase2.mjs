import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';

const { default: puppeteer } = await import(process.env.PUPPETEER_MODULE || 'puppeteer');
const directorio = process.env.FASE2_BROWSER_HTML;
assert.ok(directorio?.startsWith('/tmp/'), 'Usar fixtures ficticios exportados en /tmp.');
const origen = new URL((await readFile(resolve(directorio, 'pan.html'), 'utf8')).match(/data-consulta-url="([^"]+)"/)[1]).origin;
const browser = await puppeteer.launch({ executablePath: process.env.CHROME_BIN || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox', '--disable-dev-shm-usage'] });
const errores = [];
const resultados = [];
try {
    for (const ancho of [360, 412, 1280]) {
        const page = await browser.newPage();
        await page.setViewport({ width: ancho, height: 880, isMobile: ancho < 500, hasTouch: ancho < 500 });
        page.on('pageerror', error => errores.push(error.message));
        let liberarConsulta;
        let liberarBorrado;
        let bloquearConsulta = false;
        let modoBorrado = 'error';
        let enviosBorrado = 0;
        const borrados = [];
        await page.setRequestInterception(true);
        page.on('request', async request => {
            try {
                const url = new URL(request.url());
                if (url.pathname.startsWith('/build/assets/')) {
                    const archivo = resolve('public', '.' + url.pathname);
                    assert.ok(archivo.startsWith(resolve('public/build/assets') + '/'));
                    await request.respond({ status: 200, headers: { 'Access-Control-Allow-Origin': '*' }, contentType: archivo.endsWith('.css') ? 'text/css' : /\.woff2?$/.test(archivo) ? 'font/woff2' : 'application/javascript', body: await readFile(archivo) });
                    return;
                }
                if (request.method() === 'POST' && url.pathname.startsWith('/history/')) {
                    enviosBorrado++;
                    const [, , familia, id] = url.pathname.split('/');
                    if (modoBorrado === 'espera') await new Promise(resolve => { liberarBorrado = resolve; });
                    if (modoBorrado === 'error') {
                        await request.respond({ status: 500, contentType: 'application/json', body: JSON.stringify({ message: 'Fallo simulado: registro conservado.' }) });
                    } else {
                        borrados.push([familia, id]);
                        await request.respond({ status: 200, contentType: 'application/json', body: JSON.stringify({ eliminado: true, familia, detalle: Number(id), redirect: `${origen}/history` }) });
                    }
                    return;
                }
                let nombre;
                if (url.pathname === '/produccion') nombre = url.searchParams.get('categoria') || 'pan';
                else if (url.pathname === '/produccion/registros') {
                    if (bloquearConsulta) await new Promise(resolve => { liberarConsulta = resolve; });
                    nombre = url.searchParams.get('categoria') === 'torta' ? 'registros-torta' : url.searchParams.get('fecha_inicio') === '2026-10-21' ? 'registros-vacio' : url.searchParams.get('turno_id') ? 'registros-noche' : 'registros';
                } else if (url.pathname === '/dashboard') {
                    if (bloquearConsulta) await new Promise(resolve => { liberarConsulta = resolve; });
                    nombre = url.search ? 'dashboard-periodo' : 'dashboard';
                } else if (url.pathname === '/history') nombre = 'history';
                else if (url.pathname === '/empleados') nombre = 'personal';
                if (nombre && request.method() === 'GET') {
                    let html = await readFile(resolve(directorio, nombre + '.html'), 'utf8');
                    if (nombre === 'history') for (const [familia, id] of borrados) html = html.replace(new RegExp(`<article class="carta-historial" data-familia="${familia}" data-detalle-id="${id}"[\\s\\S]*?</article>`), '');
                    await request.respond({ status: 200, contentType: 'text/html; charset=utf-8', body: html });
                } else await request.abort();
            } catch (error) {
                errores.push(error.message);
                if (!request.isInterceptResolutionHandled()) await request.abort();
            }
        });
        await page.goto(`${origen}/produccion`, { waitUntil: 'networkidle0' });
        const cdp = await page.createCDPSession();
        if (ancho === 360) await cdp.send('Emulation.setCPUThrottlingRate', { rate: 4 });
        const maxCambio = await page.evaluate(async () => {
            const botones = [...document.querySelectorAll('[data-categoria]')];
            const tiempos = [];
            for (let n = 0; n < 90; n++) {
                const boton = botones[n % 3];
                const inicio = performance.now(); boton.click(); tiempos.push(performance.now() - inicio);
                if (document.querySelector('#categoria-seleccionada').value !== boton.dataset.categoria) throw Error('Categoría inconsistente');
                await new Promise(requestAnimationFrame);
            }
            return Math.max(...tiempos);
        });
        assert.ok(maxCambio < 1000);
        await page.click('[data-categoria="pan"]');
        await page.select('[data-campo="producto_id"]', await page.$eval('[data-campo="producto_id"] option[data-familia="pan"]', e => e.value));
        await page.$eval('[data-campo="coches"]', e => { e.value = '1'; e.dispatchEvent(new Event('input', { bubbles: true })); });
        await page.$eval('[data-campo="latas_adicionales"]', e => { e.value = '5'; e.dispatchEvent(new Event('input', { bubbles: true })); });
        assert.equal(await page.$eval('[data-total-latas]', e => e.textContent), '23 latas');
        assert.equal(await page.$eval('[data-equivalencia-latas]', e => e.textContent), '1 coches + 5 latas');
        if (ancho < 500) {
            const posiciones = await page.evaluate(() => ['coches', 'latas_adicionales'].map(campo => document.querySelector(`[data-campo="${campo}"]`).getBoundingClientRect().top));
            assert.ok(Math.abs(posiciones[0] - posiciones[1]) < 2, 'Cantidades deben compartir fila.');
        }
        assert.ok(await page.$eval('#campo-turno', e => getComputedStyle(e).display !== 'contents'));
        await page.select('#detalle-hora-finalizacion-hora', '08');
        await page.select('#detalle-hora-finalizacion-minutos', '35');
        assert.deepEqual(await page.$eval('#formulario-produccion', e => new FormData(e).getAll('detalles[0][hora_finalizacion]')), ['08:35']);
        await page.click('[data-categoria="torta"]');
        assert.ok(await page.$eval('#detalle-hora-finalizacion-hora', e => e.disabled && e.value === ''));
        assert.equal(await page.$eval('#campo-turno', e => getComputedStyle(e).display), 'contents');
        assert.ok(await page.$eval('[data-turno-fijo]', e => e.getBoundingClientRect().width <= 1));
        const selectorHora = await page.evaluate(() => {
            const hora = document.querySelector('#detalle-hora-vitrina-hora');
            const minutos = document.querySelector('#detalle-hora-vitrina-minutos');
            const oculto = document.querySelector('[data-campo="hora_vitrina"]');
            const seleccionar = (control, valor) => { control.value = valor; control.dispatchEvent(new Event('change', { bubbles: true })); };
            const posiciones = [hora, minutos].map(e => e.getBoundingClientRect().top);
            const inicial = hora.value === '' && minutos.value === '' && oculto.value === '';
            seleccionar(hora, '14');
            const incompleta = !minutos.checkValidity() && oculto.value === '';
            seleccionar(minutos, '36');
            const valores = new FormData(document.querySelector('#formulario-produccion')).getAll('detalles[0][hora_vitrina]');
            return { inicial, incompleta, valores, mismaFila: Math.abs(posiciones[0] - posiciones[1]) < 2, opciones: [hora.options.length, minutos.options.length], etiquetas: [hora, minutos].every(e => e.labels.length === 1 && e.closest('fieldset').querySelector('legend').textContent.includes('vitrina')) };
        });
        assert.deepEqual(selectorHora, { inicial: true, incompleta: true, valores: ['14:36'], mismaFila: true, opciones: [25, 61], etiquetas: true });
        await page.focus('#detalle-hora-vitrina-hora');
        await page.keyboard.press('Tab');
        assert.equal(await page.evaluate(() => document.activeElement.id), 'detalle-hora-vitrina-minutos');
        await page.click('[data-observacion] summary');
        await page.type('[data-campo="observacion"]', 'Texto sin guardar');
        await page.click('[data-observacion] summary');
        assert.equal(await page.$eval('[data-campo="observacion"]', e => e.value), 'Texto sin guardar');
        await page.click('[data-observacion] summary');
        await page.select('#grupo-produccion', await page.$eval('#grupo-produccion option[data-familia="torta"]', e => e.value));
        assert.equal(await page.$$eval('[data-participante]', e => e.length), 1);
        const estadoLote = await page.$eval('#formulario-produccion', e => [...new FormData(e)].filter(([nombre]) => nombre !== '_token'));
        bloquearConsulta = true;
        await page.select('#registros-categoria', 'torta');
        await page.click('[data-filtros-registros] button');
        await page.waitForSelector('[data-esqueleto]:not([hidden])');
        assert.deepEqual(await page.$eval('#formulario-produccion', e => [...new FormData(e)].filter(([nombre]) => nombre !== '_token')), estadoLote);
        bloquearConsulta = false;
        assert.ok(liberarConsulta); liberarConsulta();
        await page.waitForFunction(() => document.querySelector('[data-esqueleto]').hidden && document.querySelectorAll('[data-resultados-registros] article').length === 1);
        assert.equal(await page.$eval('[data-resultados-registros] article', e => e.dataset.familia), 'torta');
        assert.deepEqual(await page.$eval('#formulario-produccion', e => [...new FormData(e)].filter(([nombre]) => nombre !== '_token')), estadoLote);
        await page.select('#registros-categoria', '');
        await page.$eval('#registros-fecha', e => { e.value = '2026-10-21'; });
        await page.click('[data-filtros-registros] button');
        await page.waitForFunction(() => document.querySelector('[data-resultados-registros]').textContent.includes('No se encontraron'));
        assert.equal(await page.$eval('[data-campo="observacion"]', e => e.value), 'Texto sin guardar');
        await sinOverflow(page);
        await page.screenshot({ path: resolve(directorio, `produccion-${ancho}.png`), fullPage: false });

        await page.goto(`${origen}/history`, { waitUntil: 'networkidle0' });
        const resumenes = await page.$$('.detalles-produccion summary');
        assert.ok(resumenes.length >= 2);
        await resumenes[0].focus(); await page.keyboard.press('Enter');
        await page.waitForFunction(() => document.querySelectorAll('.detalles-produccion[open]').length === 1);
        await resumenes[1].focus(); await page.keyboard.press('Enter');
        await page.waitForFunction(() => document.querySelectorAll('.detalles-produccion[open]').length === 1 && document.querySelectorAll('.detalles-produccion')[1].open && document.querySelectorAll('.detalles-produccion summary')[1].getAttribute('aria-expanded') === 'true');
        assert.equal(await resumenes[1].evaluate(e => e.getAttribute('aria-expanded')), 'true');
        assert.ok(await resumenes[1].evaluate(e => [...e.children].some(hijo => hijo.textContent === 'Ocultar detalles' && getComputedStyle(hijo).display !== 'none')));
        await page.waitForFunction(() => document.querySelector('.detalles-produccion[open]').getAnimations({ subtree: true }).every(a => a.playState !== 'running'));
        await sinOverflow(page);
        await page.screenshot({ path: resolve(directorio, `historial-${ancho}.png`), fullPage: false });
        const botonBorrar = await page.$('.detalles-produccion[open] [data-abrir-eliminacion]');
        await botonBorrar.click();
        await page.click('dialog [value="cancelar"]');
        assert.equal(enviosBorrado, 0);
        await botonBorrar.click();
        await page.click('dialog [value="eliminar"]');
        await page.waitForFunction(() => document.querySelector('dialog [role="alert"]').textContent.includes('Fallo simulado'));
        assert.equal(await page.$$eval('article.carta-historial', e => e.length), resumenes.length);
        await page.click('dialog [value="cancelar"]');
        await botonBorrar.click();
        modoBorrado = 'espera';
        await page.click('dialog [value="eliminar"]');
        await page.waitForFunction(() => document.querySelector('dialog [value="eliminar"]').textContent === 'Eliminando…');
        assert.equal(await page.$$eval('article.carta-historial', e => e.length), resumenes.length, 'No retirar antes de confirmar respuesta.');
        modoBorrado = 'exito';
        assert.ok(liberarBorrado); liberarBorrado();
        await page.waitForFunction(n => document.querySelectorAll('article.carta-historial').length === n, {}, resumenes.length - 1);
        await page.waitForNetworkIdle();
        assert.equal(enviosBorrado, 2);

        await page.goto(`${origen}/dashboard`, { waitUntil: 'networkidle0' });
        await page.waitForFunction(() => document.querySelector('[data-grafico-turnos]')?.width > 0);
        assert.ok(await page.$eval('[data-dashboard]', e => e.classList.contains('entrada-dashboard')));
        const metricasIniciales = await page.$$eval('[data-contador]', nodos => nodos.map(n => Number(n.dataset.contador)));
        assert.deepEqual(metricasIniciales, [2, 26]);
        await page.waitForFunction(() => document.querySelector('[data-dashboard]').getAnimations({subtree:true}).every(a=>a.playState !== 'running') && [...document.querySelectorAll('[data-contador]')].every(e => (e.querySelector('[aria-hidden="true"]')?.textContent || e.textContent).trim() === e.dataset.contador));
        await sinOverflow(page);
        if (ancho === 1280) assert.ok(await page.$eval('[data-sidebar]', e => e.checkVisibility() && e.getBoundingClientRect().width >= 76));
        await page.screenshot({ path: resolve(directorio, `dashboard-${ancho}.png`), fullPage: false });
        bloquearConsulta = true;
        await page.$eval('#dashboard-desde', e => { e.value = '2026-10-22'; });
        await page.$eval('#dashboard-hasta', e => { e.value = '2026-10-24'; });
        await page.click('[data-filtros-dashboard] button');
        await page.waitForSelector('[data-esqueleto]:not([hidden])');
        bloquearConsulta = false; liberarConsulta();
        await page.waitForFunction(() => document.querySelector('[data-metrica="latas"]').dataset.contador === '38');
        assert.deepEqual(await page.$$eval('[data-contador]', nodos => nodos.map(n => Number(n.dataset.contador))), [3, 38]);
        assert.equal(await page.$eval('[data-dashboard]', e => e.classList.contains('entrada-dashboard')), false);
        await page.emulateMediaFeatures([{ name: 'prefers-reduced-motion', value: 'reduce' }]);
        await page.evaluate(() => sessionStorage.clear());
        await page.goto(`${origen}/dashboard`, { waitUntil: 'networkidle0' });
        assert.equal(await page.$eval('[data-dashboard]', e => e.classList.contains('entrada-dashboard')), false);
        assert.equal(await page.$eval('[data-entrada]', e => getComputedStyle(e).animationName), 'none');
        await page.goto(`${origen}/empleados`, { waitUntil: 'networkidle0' });
        await sinOverflow(page);
        resultados.push({ ancho, cambiosCategoria: 90, maxCambioMs: maxCambio, filtrosSinPerderLote: true, tarjetasTeclado: true, borradoConfirmado: true, dashboardReal: true, movimientoReducido: true });
        await page.close();
    }
    assert.deepEqual(errores, []);
    console.log(JSON.stringify({ resultados, errores }, null, 2));
} catch(error) {
    console.error(JSON.stringify({ resultados, errores }));
    throw error;
} finally { await browser.close(); }
async function sinOverflow(page) { assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'Desbordamiento horizontal.'); }
