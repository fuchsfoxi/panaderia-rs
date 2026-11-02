import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';

// Solo fixtures exportados por pruebas aisladas; toda petición se intercepta.
const { default: puppeteer } = await import(process.env.PUPPETEER_MODULE || 'puppeteer');
const directorio = process.env.CARGA_BROWSER_HTML;
assert.ok(directorio?.startsWith('/tmp/'));
const origen = new URL((await readFile(resolve(directorio, 'login.html'), 'utf8')).match(/<form action="([^"]+)"/)[1]).origin;
const browser = await puppeteer.launch({ executablePath: process.env.CHROME_BIN || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox', '--disable-dev-shm-usage'] });
const errores = [];
const resultados = [];
try {
    for (const fallback of (process.env.CARGA_FALLBACK_ONLY ? [true] : [false, true])) {
        for (const ancho of [360, 412, 1280]) {
            console.log(`Verificando ${ancho}, fallback=${fallback}`);
            const page = await browser.newPage();
            page.setDefaultTimeout(30000);
            await page.setViewport({ width: ancho, height: 880, isMobile: ancho < 500, hasTouch: ancho < 500 });
            const estados = [];
            page.on('pageerror', error => errores.push(error.message));
            page.on('console', mensaje => {
                if (mensaje.text().startsWith('estado-prueba:')) estados.push(JSON.parse(mensaje.text().slice(14)));
            });
            await page.evaluateOnNewDocument(fallback => {
                if (fallback) Object.defineProperty(window, 'navigation', { value: undefined });
                window.addEventListener('pageshow', evento => { window.__pruebaPaginaMostrada = !evento.persisted; });
                document.addEventListener('DOMContentLoaded', () => {
                    // Observar antes de que Chromium suspenda el contexto del documento saliente.
                    const observar = evento => {
                        const boton = evento.submitter || evento.target.querySelector?.('[type="submit"]');
                        console.log('estado-prueba:' + JSON.stringify({
                            tipo: evento.type, cancelado: evento.defaultPrevented,
                            texto: boton?.textContent, disabled: boton?.disabled,
                            busy: evento.target.getAttribute?.('aria-busy'),
                            mensaje: document.querySelector('[data-carga-mensaje]')?.textContent,
                            oculto: document.querySelector('[data-carga-pagina]')?.hidden,
                            reducido: document.querySelector('.carga-spinner') && getComputedStyle(document.querySelector('.carga-spinner')).animationName,
                            sinOverflow: document.documentElement.scrollWidth <= innerWidth,
                        }));
                    };
                    window.addEventListener('submit', observar);
                    window.addEventListener('click', observar);
                    if (window.navigation) window.navigation.addEventListener('navigate', observar);
                });
            }, fallback);
            let pausa = false;
            let liberar;
            let avisar;
            const siguiente = () => new Promise(resolve => { avisar = resolve; });
            const peticiones = [];
            const rutas = { '/login': 'login', '/dashboard': 'dashboard', '/history': 'history', '/produccion': 'pan', '/productos': 'productos', '/productos/crear': 'producto' };
            await page.setRequestInterception(true);
            page.on('request', async request => {
                try {
                    const url = new URL(request.url());
                    if (url.pathname.startsWith('/build/assets/')) {
                        const archivo = resolve('public', '.' + url.pathname);
                        assert.ok(archivo.startsWith(resolve('public/build/assets') + '/'));
                        await request.respond({ status: 200, contentType: archivo.endsWith('.css') ? 'text/css' : /\.woff2?$/.test(archivo) ? 'font/woff2' : 'application/javascript', body: await readFile(archivo) });
                    } else if (url.origin === origen && rutas[url.pathname]) {
                        peticiones.push({ url: request.url(), method: request.method(), body: request.postData() });
                        if (pausa) await new Promise(resolve => { liberar = resolve; avisar?.(); avisar = null; });
                        await request.respond({ status: 200, contentType: 'text/html; charset=utf-8', body: await readFile(resolve(directorio, rutas[url.pathname] + '.html')) });
                    } else await request.abort();
                } catch (error) {
                    errores.push(error.message);
                    if (!request.isInterceptResolutionHandled()) await request.abort();
                }
            });
            const ir = ruta => page.goto(origen + ruta, { waitUntil: 'networkidle0' });
            const oculto = () => page.$eval('[data-carga-pagina]', e => e.hidden);
            const terminar = async () => {
                pausa = false;
                const navegacion = page.waitForNavigation({ waitUntil: 'networkidle0' });
                assert.ok(liberar); liberar(); liberar = null;
                await navegacion.catch(error => { throw Error('Terminar navegación: ' + error.message); });
                assert.equal(await oculto(), true);
            };
            const enviar = async selector => {
                pausa = true;
                const recibida = siguiente();
                const anteriores = estados.filter(e => e.tipo === 'submit').length;
                await page.evaluate(selector => { setTimeout(() => {
                    const f = document.querySelector(selector); const b = f.querySelector('[type="submit"]');
                    b.click(); b.click();
                }, 0); }, selector);
                await recibida;
                const envios = estados.filter(e => e.tipo === 'submit').slice(anteriores);
                assert.equal(envios[0].cancelado, false);
                assert.equal(envios.length, 1, 'El segundo click no inicia otro envío.');
                assert.equal(estados.filter(e => e.tipo === 'click').at(-1).cancelado, true);
                assert.equal(envios[0].disabled, false, 'No se pierde el submitter al serializar.');
                assert.equal(envios[0].busy, 'true');
                return envios[0];
            };

            await ir('/login');
            await page.click('[data-login-submit]');
            assert.equal(await oculto(), true, 'La validación HTML no inicia carga.');
            assert.equal(peticiones.length, 1);
            await page.type('[name="username"]', 'usuario.ficticio');
            await page.type('[name="password"]', 'clave-ficticia');
            await page.evaluate(() => window.addEventListener('submit', e => e.preventDefault(), { once: true }));
            await page.click('[data-login-submit]');
            await page.waitForFunction(() => document.querySelector('[data-carga-pagina]').hidden);
            assert.equal(await page.$eval('[data-login-submit]', e => e.textContent), 'Iniciar sesión');
            assert.ok((await enviar('form')).texto.includes('Ingresando…'));
            const login = peticiones.filter(r => r.method === 'POST');
            assert.equal(login.length, 1);
            const datosLogin = new URLSearchParams(login[0].body);
            assert.equal(datosLogin.get('username'), 'usuario.ficticio');
            assert.equal(datosLogin.get('password'), 'clave-ficticia');
            assert.equal(datosLogin.get('_token'), 'csrf-ficticio');
            await terminar();

            await ir('/productos/crear');
            await page.evaluate(() => {
                const f = document.querySelector('[data-form-producto]');
                f.noValidate = true; // Únicamente el fixture de serialización.
                f.querySelector('[name="nombre_p"]').value = 'Producto ficticio';
                const b = f.querySelector('[type="submit"]'); b.name = 'accion'; b.value = 'guardar';
            });
            assert.ok((await enviar('[data-form-producto]')).texto.includes('Guardando…'));
            const guardados = peticiones.filter(r => r.method === 'POST' && new URL(r.url).pathname === '/productos');
            assert.equal(guardados.length, 1);
            const datos = new URLSearchParams(guardados[0].body);
            assert.equal(datos.get('accion'), 'guardar');
            assert.equal(datos.get('nombre_p'), 'Producto ficticio');
            assert.equal(datos.get('_token'), 'csrf-ficticio');
            await terminar();

            await ir('/history');
            assert.ok((await enviar('.filtros-historial')).texto.includes('Buscando…'));
            assert.equal(peticiones.filter(r => r.url.includes('/history?')).length, 1);
            await terminar();
            for (const atributos of ['href="#prueba"', 'href="https://example.invalid/"', 'href="/dashboard" download', 'href="/dashboard" target="_blank"', 'href="mailto:ejemplo@example.invalid"', 'href="/dashboard"']) {
                await page.evaluate(atributos => {
                    const plantilla = document.createElement('template'); plantilla.innerHTML = `<a ${atributos}>Prueba</a>`;
                    const a = plantilla.content.firstChild; document.body.append(a);
                    // Cancelación tardía: no abandonar el fixture ni abrir destinos externos.
                    window.addEventListener('click', e => e.preventDefault(), { once: true });
                    a.click(); a.remove();
                }, atributos);
                await page.waitForFunction(() => document.querySelector('[data-carga-pagina]').hidden).catch(error => {
                    console.log(estados.slice(-3), errores);
                    throw error;
                });
            }
            await page.evaluate(() => { const a = document.createElement('a'); a.href = '#prueba'; document.body.append(a); a.click(); a.remove(); });
            assert.equal(await oculto(), true);
            await page.emulateMediaFeatures([{ name: 'prefers-reduced-motion', value: 'reduce' }]);
            pausa = true;
            const enlaceRecibido = siguiente();
            await page.evaluate(() => { setTimeout(() => document.querySelector('a[href$="/dashboard"]').click(), 0); });
            await enlaceRecibido;
            const navegacion = estados.filter(e => e.mensaje === 'Cargando…').at(-1);
            assert.ok(navegacion, 'Indicador de navegación visible antes de salir.');
            assert.equal(navegacion.oculto, false);
            assert.equal(navegacion.reducido, 'none');
            assert.equal(navegacion.sinOverflow, true);
            await terminar();
            await page.goBack({ waitUntil: 'networkidle0' });
            // app.js conserva la recarga de seguridad cuando Chrome restaura BFCache.
            await page.waitForFunction(() => window.__pruebaPaginaMostrada && !document.body.hidden
                && document.readyState === 'complete' && document.querySelector('.filtros-historial'));
            assert.equal(await oculto(), true, 'Atrás restaura la interfaz.');
            // Ensayo de presentación sin navegar, para inspección visual aislada.
            await page.evaluate(() => {
                const f = document.querySelector('.filtros-historial');
                f.dispatchEvent(new SubmitEvent('submit', { bubbles: true, cancelable: true, submitter: f.querySelector('[type="submit"]') }));
            });
            await page.screenshot({ path: resolve(directorio, `filtro-cargando-${ancho}-${fallback}.png`), fullPage: true });
            await page.evaluate(() => window.dispatchEvent(new PageTransitionEvent('pageshow')));
            assert.equal(await oculto(), true);
            assert.equal(await page.$eval('.btn-filtrar', e => e.textContent), 'Filtrar');
            // Paginación nativa: comprobar que conserva query y usa el mismo indicador.
            pausa = true;
            const paginaRecibida = siguiente();
            await page.evaluate(() => { setTimeout(() => {
                const a = document.createElement('a'); a.href = '/history?page=2&categoria=pan'; a.rel = 'next';
                document.body.append(a); a.click();
            }, 0); });
            await paginaRecibida;
            assert.equal(new URL(peticiones.at(-1).url).searchParams.get('page'), '2');
            assert.equal(new URL(peticiones.at(-1).url).searchParams.get('categoria'), 'pan');
            assert.equal(estados.filter(e => e.mensaje === 'Cargando…').at(-1).oculto, false);
            await terminar();
            resultados.push({ ancho, fallback, resultado: 'aprobado' });
            await page.close();
        }
    }
    assert.deepEqual(errores, []);
    console.log(JSON.stringify({ resultados, errores }, null, 2));
} finally {
    await browser.close();
}
