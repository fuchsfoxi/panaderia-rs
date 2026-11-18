import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';

const { default: puppeteer } = await import(process.env.PUPPETEER_MODULE || 'puppeteer');
const dir = process.env.DASHBOARD_FASE3_HTML;
assert.ok(dir?.startsWith('/tmp/'));
const html = await readFile(resolve(dir, 'dashboard.html'), 'utf8');
const origen = new URL(html.match(/class="dashboard-enlace" href="([^"]+)/)[1]).origin;
const browser = await puppeteer.launch({ executablePath: process.env.CHROME_BIN || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox', '--disable-dev-shm-usage'] });
const resultados = [];
const errores = [];
try {
    for (const ancho of [320, 360, 768, 1280]) {
        for (const js of [true, false]) {
            const page = await browser.newPage();
            await page.setViewport({ width: ancho, height: 900, isMobile: ancho < 500, hasTouch: ancho < 500 });
            await page.setJavaScriptEnabled(js);
            const peticiones = [];
            page.on('pageerror', error => errores.push(error.message));
            await page.setRequestInterception(true);
            page.on('request', async req => {
                try {
                    const url = new URL(req.url());
                    if (url.origin === origen && url.pathname.startsWith('/build/assets/')) {
                        const archivo = resolve('public', '.' + url.pathname);
                        assert.ok(archivo.startsWith(resolve('public/build/assets') + '/'));
                        return await req.respond({ status: 200, contentType: archivo.endsWith('.css') ? 'text/css' : /\.woff2?$/.test(archivo) ? 'font/woff2' : 'application/javascript', body: await readFile(archivo) });
                    }
                    if (url.origin === origen && url.pathname === '/dashboard') {
                        peticiones.push(url.search);
                        const nombre = url.searchParams.has('parcial') ? 'dashboard-parcial' : url.searchParams.has('vacio') ? 'dashboard-vacio' : url.searchParams.has('desconocido') ? 'dashboard-desconocido' : 'dashboard';
                        return await req.respond({ status: 200, contentType: 'text/html; charset=utf-8', body: await readFile(resolve(dir, nombre + '.html'), 'utf8') });
                    }
                    await req.abort();
                } catch (error) { errores.push(error.message); if (!req.isInterceptResolutionHandled()) await req.abort(); }
            });
            await page.goto(origen + '/dashboard', { waitUntil: 'networkidle0' });
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, `overflow ${ancho}, js ${js}`);
            assert.equal(await page.$$eval('canvas', e => e.length), 1);
            assert.equal(await page.$$eval('.tarjeta-hoy', e => e.length), 4);
            if (js) {
                await page.waitForFunction(() => !document.querySelector('[data-control-diario]').hidden && document.querySelector('[data-grafico-turnos]').width > 0);
                await page.waitForFunction(() => performance.getEntriesByType('resource').some(r => /chart-.*\.js/.test(r.name)));
                assert.equal(await page.$eval('[data-error-grafico]', e => e.hidden), true);
                assert.equal(await page.$eval('[data-pan-diario="latas"]', e => e.hidden), false);
                assert.equal(await page.$eval('[data-pan-diario="unidades"]', e => e.hidden), true);
                const n = peticiones.length;
                await page.select('[data-unidad-diaria]', 'unidades');
                assert.equal(await page.$eval('[data-pan-diario="unidades"]', e => e.hidden), false);
                assert.equal(await page.$eval('[data-pan-mensual="latas"]', e => e.hidden), false);
                await page.select('[data-unidad-mensual]', 'unidades');
                assert.equal(await page.$eval('[data-pan-mensual="unidades"]', e => e.hidden), false);
                assert.equal(await page.$eval('[data-pan-diario="unidades"]', e => e.hidden), false);
                assert.equal(peticiones.length, n);
                await page.select('[data-serie]', 'mes_actual');
                assert.equal(await page.$eval('[data-tabla-serie="mes_actual"]', e => e.hidden), false);
                assert.equal(await page.$eval('[data-tabla-serie="siete_dias"]', e => e.hidden), true);
                assert.match(await page.$eval('[data-descripcion-serie]', e => e.textContent), /no predicciones/);
                // Medida del gráfico: Latas muestra solo Pan; Unidades agrega Torta y Bocadito
                assert.equal(await page.$eval('[data-medida]', e => e.value), 'latas');
                assert.match(await page.$eval('[data-descripcion-serie]', e => e.textContent), /Latas de Pan/);
                assert.deepEqual(await page.$$eval('[data-leyenda-unidades]', e => e.map(x => x.hidden)), [true, true]);
                await page.select('[data-medida]', 'unidades');
                assert.match(await page.$eval('[data-descripcion-serie]', e => e.textContent), /Unidades producidas/);
                assert.deepEqual(await page.$$eval('[data-leyenda-unidades]', e => e.map(x => x.hidden)), [false, false]);
                assert.equal(await page.$eval('[data-error-grafico]', e => e.hidden), true);
                assert.equal(peticiones.length, n);
                await page.$eval('[data-unidad-diaria]', e => e.focus());
                assert.equal(await page.$eval('[data-unidad-diaria]', e => e.matches(':focus-visible')), true);
                await page.keyboard.press('Tab');
                assert.equal(await page.evaluate(() => document.activeElement.matches('[data-unidad-mensual]')), true);
                await page.goto(origen + '/dashboard?parcial=1', { waitUntil: 'networkidle0' });
                await page.select('[data-unidad-diaria]', 'unidades');
                assert.match(await page.$eval('[data-pan-diario="unidades"]', e => e.textContent), /Total parcial/);
                await page.select('[data-unidad-mensual]', 'unidades');
                assert.match(await page.$eval('[data-pan-mensual="unidades"]', e => e.textContent), /Clasificación no disponible/);
                await page.goto(origen + '/dashboard?desconocido=1', { waitUntil: 'networkidle0' });
                assert.match(await page.$eval('[data-pan-diario="latas"]', e => e.textContent), /Total parcial/);
                assert.match(await page.$eval('[data-pan-mensual="latas"]', e => e.textContent), /Clasificación no disponible/);
                await page.goto(origen + '/dashboard?vacio=1', { waitUntil: 'networkidle0' });
                assert.match(await page.$eval('.actividad-lista', e => e.textContent), /Aún no hay producciones/);
                await page.goto(origen + '/dashboard', { waitUntil: 'networkidle0' });
                await page.emulateMediaFeatures([{ name: 'prefers-reduced-motion', value: 'reduce' }]);
                assert.equal(await page.evaluate(() => matchMedia('(prefers-reduced-motion: reduce)').matches), true);
            } else {
                assert.equal(await page.$eval('[data-pan-diario="latas"]', e => e.hidden), false);
                assert.equal(await page.$eval('[data-pan-diario="unidades"]', e => e.hidden), false);
                assert.equal(await page.$eval('[data-tabla-serie="mes_actual"]', e => e.hidden), false);
                assert.equal(await page.$eval('[data-control-diario]', e => e.hidden), true);
                assert.equal(await page.$eval('[data-control-medida]', e => e.hidden), true);
            }
            await page.screenshot({ path: resolve(dir, `dashboard-${ancho}-${js ? 'js' : 'nojs'}.png`), fullPage: true });
            resultados.push({ ancho, js, resultado: 'aprobado' });
            await page.close();
        }
    }
    assert.deepEqual(errores, []);
    console.log(JSON.stringify({ resultados, errores }, null, 2));
} finally { await browser.close(); }
