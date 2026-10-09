# Informe - Cloudflare Tunnel HTTPS y Trusted Proxy

Fecha: 2026-10-09. Proyecto: `panaderia-rs`.

Estado: **IMPLEMENTADO Y COMPROBADO mediante pruebas HTTP y MariaDB aislada**. Prueba real con Quick Tunnel/celular **PENDIENTE**. Sin commit ni push.

## 1. Problema encontrado

El navegador se conecta por HTTPS a Cloudflare. El proceso local cloudflared entrega la solicitud al origen mediante `http://127.0.0.1:8000`. La conexión interna HTTP no describe el protocolo público: la terminación TLS ocurre antes de Laravel.

Cloudflare comunica el protocolo del visitante mediante `X-Forwarded-Proto`. Sin un proxy confiable configurado, Laravel/Symfony ignora ese encabezado y considera HTTP la request interna. Los helpers pueden generar acciones y redirecciones HTTP desde una página abierta por HTTPS, causando la advertencia de envío inseguro. [Referencia oficial de encabezados Cloudflare](https://developers.cloudflare.com/fundamentals/reference/http-headers/).

Se reprodujo la causa con requests HTTP sintéticas antes del cambio: `isSecure()` devolvió false y `route('login')`/`asset()` generaron HTTP pese al encabezado HTTPS recibido desde loopback IPv4 e IPv6. No se inició un túnel ni se reprodujo la interfaz del aviso en un navegador real.

## 2. Estado inicial

- Checkout limpio al comenzar. `composer.json` requiere `laravel/framework: ^13.17`; versión realmente instalada **13.25.0**. Symfony HTTP Foundation instalado: **v8.1.4**.
- `bootstrap/app.php`: `PreventPageCaching` al inicio del middleware global y redirect de autenticados a `/dashboard`; sin `trustProxies()` propio. El middleware nativo `Illuminate\Http\Middleware\TrustProxies` ya pertenece al stack global instalado.
- No se encontró middleware propio TrustProxies, configuración `trustedproxy`, reconocimiento manual de Cloudflare ni `URL::forceScheme()` en el código inspeccionado.
- `config/app.php`: `'url' => env('APP_URL', 'http://localhost')`. `.env.example`: `APP_URL=http://localhost:8000`. No se leyó ni modificó el contenido del `.env` real.
- `config/session.php`: secure desde `SESSION_SECURE_COOKIE` sin valor forzado, domain desde `SESSION_DOMAIN`, same_site desde `SESSION_SAME_SITE` con valor por defecto `lax`, HttpOnly por defecto true. `.env.example` declara domain null y driver database, sin imponer secure.
- Login usa POST a `url('login')`. Sidebar usa POST a `route('logout')`, con CSRF. Rutas y controller de autenticación existentes conservan auth/guest, regeneración e invalidación de sesión.
- `vite.config.js` usa laravel-vite-plugin con entradas CSS/JS existentes; scripts npm `dev: vite` y `build: vite build`. `public/hot` estaba **ausente** y `public/build/manifest.json` **presente**. No se encontraron URLs localhost:5173/127.0.0.1:5173 escritas en resources/configuración Vite.
- Se inspeccionaron NavigationTest, runner MariaDB, soporte de aislamiento, API instalada de middleware, constantes Request, helpers Vite y preparación de cookies de Symfony.

## 3. Solución implementada

Único cambio de ejecución en **`bootstrap/app.php`**, dentro de `withMiddleware()`:

```php
// cloudflared local conecta al origen por loopback; no confiar en proxies remotos.
$middleware->trustProxies(
    at: ['127.0.0.1', '::1'],
    headers: Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO,
);
```

Se reutiliza el import existente `Illuminate\Http\Request`. La firma `trustProxies(array|string|null $at = null, ?int $headers = null)` y las cuatro constantes se comprobaron en vendor instalado. El patrón coincide con [trusted proxies de Laravel 13](https://laravel.com/framework/docs/requests#configuring-trusted-proxies).

El middleware nativo procesa los encabezados antes de ejecutar las rutas. No se añade otro middleware ni se cambian formularios, rutas o controladores.

## 4. Seguridad

Se confía exactamente en el peer de conexión (`REMOTE_ADDR`) **127.0.0.1** o **::1**. No se confía en todo 127.0.0.0/8, rangos de LAN, IPs públicas de Cloudflare, IPs remotas de Internet ni comodines `*`/`**`. La IP que declara `X-Forwarded-For` no convierte al remitente en proxy confiable.

Solo se habilitan `X-Forwarded-For`, `X-Forwarded-Host`, `X-Forwarded-Port` y `X-Forwarded-Proto`. No se habilitan adicionalmente `Forwarded` RFC 7239, prefix ni una máscara para AWS/Traefik. No se obliga HTTPS globalmente ni se identifica/hardcodea un dominio temporal.

Las pruebas con peers IPv4 remoto, IPv6 remoto y LAN demuestran que esos cuatro encabezados no pueden imponer esquema, host, puerto o IP. Symfony conserva la request original cuando el peer no es confiable. Para IP forwarding aplica su interpretación nativa de la cadena; no se inventa lógica basada en `CF-Connecting-IP` ni se certifica la IP final de un túnel real no ejecutado.

Límite de confianza: cualquier proceso local que conecte desde esas dos IPs puede enviar encabezados aceptados. Laravel no identifica el binario cloudflared. Esta configuración corresponde al origen local solicitado; si posteriormente el proxy conecta desde Docker u otra máquina, se debe revisar la dirección real y su frontera de confianza, sin ampliar automáticamente a todos los proxies. El comando manual de `artisan serve` conserva su bind local predeterminado.

## 5. X-Forwarded-Proto

Al llegar una request HTTP con `REMOTE_ADDR=127.0.0.1` o `::1` y `X-Forwarded-Proto: https`, TrustProxies configura los peers/headers confiables en Symfony. `Request::isSecure()` pasa a true; el esquema usado por los helpers pasa a HTTPS.

El Host público se conserva si llega en `Host`, como se simuló para el túnel. Si un proxy local envía `X-Forwarded-Host` y `X-Forwarded-Port`, también se reconocen. Sin puerto forwarded, HTTPS resuelve el puerto público 443; la prueba no deja el puerto HTTP interno en las URLs. Otro caso prueba forwarding explícito con puerto 8443 e IP de ejemplo.

Sin esos encabezados, HTTP local sigue siendo HTTP. Un cliente fuera de loopback con el mismo encabezado HTTPS permanece no seguro y conserva su host/puerto/IP originales.

## 6. Login y formularios

`url('login')`, `route('logout')` y `route('produccion.store')` utilizan el esquema/host reconocido de la request. Por eso sus acciones permanecen HTTPS cuando el protocolo público forwarded es HTTPS, sin cambiar Blade.

Se comprobó login → destino protegido solicitado → formulario de producción/logout → rechazo de GET logout → logout POST → rechazo de nuevo acceso como invitado. Las redirecciones a login, destino solicitado y dashboard mantienen HTTPS. Logout sigue exclusivamente POST; las pruebas previas de CSRF permanecen aprobadas.

También se prueba generación HTTPS mediante `route()`, `asset()` y `redirect()->route()` dentro del middleware real. No debería aparecer el aviso causado por estas acciones HTTP generadas por Laravel; la confirmación visual en el celular queda pendiente y no se extiende a causas ajenas a esta corrección.

## 7. Vite

- **`npm run dev`** inicia el servidor Vite de desarrollo/HMR. El plugin escribe su dirección en **`public/hot`**.
- Si **`public/hot` existe**, `@vite` lee ese archivo y genera URLs hacia el servidor de desarrollo, incluso si ya existe un build. Un celular puede intentar acceder a su propio localhost/127.0.0.1:5173, no al equipo donde corre Laravel.
- **`npm run build`** genera assets compilados y manifest en **`public/build`**.
- Para el túnel se usa **build con `public/hot` ausente**. Laravel resuelve el manifest y sirve las URLs de `/build` bajo el host/esquema público de la request, sin necesitar exponer el puerto 5173.

Detener una sesión de Vite dev antes de preparar la prueba externa evita que vuelva a crear hot. Comprobar/eliminar `public/hot` si quedó de una sesión anterior, aun después de compilar. No se eliminó ni modificó ese archivo en esta tarea: estaba ausente.

No se cambió arquitectura, entradas, scripts, CSS o JS. No se ejecutó `npm run build` porque no se modificaron assets y el usuario realizará ese paso. Las pruebas de navegación omiten Vite mediante `withoutVite()`; la prueba de `asset()` verifica URLs, pero no certifica contenido/build ni carga real de recursos desde el celular.

## 8. APP_URL

**Sin cambios.** Para los helpers normales de rutas, acciones, redirects y assets dentro de HTTP, Laravel obtiene host/esquema de la request reconocida. Los tests usan el host público de ejemplo sin cambiar `config('app.url')`.

`APP_URL` sigue sirviendo como base al generar URLs fuera de una request HTTP (Artisan, tareas/colas y enlaces preparados sin una request pública). Una URL Quick Tunnel es temporal: si se necesitan enlaces externos desde esos contextos, su configuración debe revisarse específicamente en esa ejecución.

También se encontró `config/filesystems.php`: el disco public configura su URL como `APP_URL.'/storage'`; esa configuración explícita de `Storage::url()` es distinta de `asset()`/`route()` dentro de HTTP. No se cambia el disco ni se promete corregir URLs configuradas explícitamente. No se inspeccionaron overrides reales de entorno ni se hardcodeó la URL temporal en ningún archivo.

## 9. Cookies y sesión

**Sin cambios en `config/session.php` o `.env.example`.** No se obliga `SESSION_SECURE_COOKIE=true`, ni se cambia dominio, same_site, driver o política de sesión para esta prueba local.

Con secure null (valor por defecto), Symfony prepara las cookies con Secure cuando la request se reconoce como HTTPS. En HTTP local no lo activa. Se comprobaron ambas respuestas con defaults explícitos limitados al proceso de test: secure null, domain null (cookie del host actual), same_site lax. Se comprueba regeneración del ID al login e invalidación de datos/ID/token al logout.

Los overrides de `SESSION_SECURE_COOKIE`, `SESSION_DOMAIN` o `SESSION_SAME_SITE` en el entorno real no fueron inspeccionados. Un override explícito conserva su efecto. No se certifica el driver database del entorno de trabajo: las pruebas HTTP usan sesiones array y fixtures aislados.

## 10. Tests

| Comando / momento | Resultado exacto |
|---|---|
| `php tests/run-mariadb.php --filter=ProxyHttpsTest`, antes de cambiar bootstrap, primer intento sandbox | Salida 1: MariaDB temporal no disponible; log confirma `Bind on unix socket: Operation not permitted`. Ningún test ejecutado |
| Mismo comando antes del cambio, con escalamiento para socket temporal | 7 pruebas: 4 aprobadas y 3 fallidas, 22 aserciones, salida 1. Reproducción: HTTP/URLs HTTP desde ambos loopbacks y forwarding ignorado |
| Mismo comando después del cambio, nuevo intento | Salida 1 antes de tests: `/tmp` sin espacio, error 28 al preparar el log InnoDB. No fue una regresión de código |
| `vendor/bin/pest --filter=ProxyHttpsTest --do-not-cache-result`, después del cambio | **7 aprobadas, 28 aserciones, salida 0** |
| `php tests/run-mariadb.php --filter=NavigationTest` | **22 aprobadas, 385 aserciones, salida 0** |
| `php tests/run-mariadb.php --do-not-cache-result` | **228 aprobadas, 2.401 aserciones, sin omisiones, salida 0** |
| `vendor/bin/pint --test bootstrap/app.php tests/Feature/NavigationTest.php tests/Feature/ProxyHttpsTest.php` | Aprobado, salida 0 |
| `git diff --check` | Sin errores, salida 0 |

ProxyHttpsTest tiene siete casos, sin consultas a BD: HTTPS desde IPv4/IPv6 loopback, forwarding explícito de host/puerto/IP, rechazo de tres peers no confiables y HTTP local normal. Sus dos rutas de diagnóstico se registran únicamente durante los tests; no se incorporan a `routes/web.php`.

NavigationTest reutiliza su fixture de usuario y añade dos recorridos, HTTP local y HTTPS forwarded. Simula solo la lectura de credenciales de la cuenta; hash, guard, middleware, controller y sesión son reales. El resto de la suite conserva su cobertura anterior.

Los comandos MariaDB se ejecutaron con escalamiento por la restricción del socket Unix, usando `tests/run-mariadb.php` sin modificarlo. Crea servidor desechable sin red y dos bases ficticias en `/tmp/sprint4-*`; el soporte verifica datadir/base antes de migrar. No se conectó a la BD de trabajo ni se ejecutó migrate:fresh. Instancias detenidas al finalizar. Artefactos de navegación: `/tmp/sprint4-92e50a56cfb8`; suite completa: `/tmp/sprint4-0a103a9d0f82`.

Con autorización se retiraron exclusivamente los subdirectorios `mariadb-data` de los dos intentos iniciales de esta tarea (`/tmp/sprint4-38ad3ccdda55` y `/tmp/sprint4-9baf166f21ca`), ya detenidos, para liberar espacio. Sus logs se conservaron; no se retiraron artefactos ajenos ni datos del proyecto.

## 11. Archivos modificados

| Ruta | Cambio y motivo |
|---|---|
| `bootstrap/app.php` | Trusted proxies nativos limitados a dos loopbacks y cuatro headers para reconocer HTTPS público |
| `tests/Feature/ProxyHttpsTest.php` | Siete casos pequeños de HTTPS/URLs/forwarding y límites de confianza |
| `tests/Feature/NavigationTest.php` | Dos recorridos que verifican formularios, login, redirects, cookies con defaults y logout POST por ambos esquemas |
| `mejora_asignado.md` | Seguimiento de esta corrección con resultados y prueba móvil pendiente, conservando historia |
| `dato_optimizar/informe_cloudflare_https_proxy.md` | Informe solicitado, evidencia, seguridad y pasos manuales |

Notas necesarias del Vault original: `LARAVEL/Middleware.md` (frontera de confianza y cookies), `LARAVEL/Blade.md` (flujo de assets por túnel), `PENDIENTES/Bugs.md` (una entrada para investigación/corrección del aviso), `DECISIONES/Decisiones Laravel.md` (decisión con contexto/opciones/consecuencias) e `INICIO/Estado de proyecto.md` (resumen y límite de verificación). El registro de decisiones real termina en `.md`; se respeta ese nombre sin crear un duplicado `.md.md`. Actualización por escalamiento del filesystem, sobre las notas originales y conservando su historia.

## 12. Archivos NO modificados

DashboardController, ProduccionController, HistorialController y LoginController; Actions, FormRequests, modelos, migraciones, seeders y `routes/web.php`; login Blade, sidebar y demás vistas; CSS/JS, diseño, `vite.config.js`, package/composer y dependencias; `config/app.php`, `config/session.php`, `.env.example`, timezone, `.env` real y runner/soporte de MariaDB.

No se cambia lógica funcional de autenticación, producción, historial o dashboard. No se inició cloudflared, servidor Laravel ni Vite dev; no se ejecutó optimize:clear ni build; no hubo commit, push o despliegue.

## 13. Pasos manuales posteriores

**PENDIENTE:** ejecutar esta única checklist para comprobar el entorno real y el aviso del celular.

1. [ ] `npm run build`
2. [ ] comprobar/eliminar `public/hot` si existe
3. [ ] `php artisan optimize:clear`
4. [ ] `php artisan serve`
5. [ ] iniciar cloudflared: `cloudflared tunnel --url http://127.0.0.1:8000`
6. [ ] abrir URL HTTPS desde celular
7. [ ] probar login
8. [ ] probar logout
9. [ ] comprobar que ya no aparece advertencia de envío inseguro
