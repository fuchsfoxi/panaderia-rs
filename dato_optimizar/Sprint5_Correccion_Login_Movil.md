# Problema encontrado

Revisión del checkout y reproducción antes de modificar, 2026-10-11. Se revisaron
`resources/views/auth/login.blade.php`, `resources/css/login.css`,
`resources/js/login.js`, assets de `public/`, `vite.config.js`, manifest,
layout general, viewport y pruebas existentes. El login es un documento autónomo;
no utiliza el layout del dashboard. Ya tenía el viewport correcto, box-sizing
global y `overflow-x: hidden`, pero ninguna media query.

La causa principal era `.brillo-calido-derecho`: su posición absoluta con
`right: -100px`, directamente en el body, extendía el área desplazable 100 px.
El brillo izquierdo también tenía un offset negativo. En Chrome con emulación
móvil, el viewport interior se ensanchaba: 320 → 420 px, 375 → 475 px y
768 → 868 px. La regla existente de overflow del body no resolvía el origen.

Además, los inputs flex tenían `min-width: auto`, que conservaba su ancho mínimo
nativo. Con 320 px, el campo de contraseña medía 177 px y el botón de mostrar
contraseña llegaba a x=316 mientras la tarjeta terminaba en x=300. El título de
40 px y el encabezado sin límite explícito de ancho agravaban la adaptación.
El fondo fijo usaba 100vw, que podía incluir el ancho de la barra vertical.

Los cambios previos de Producción permanecen preservados. Esta tarea no modifica
autenticación, controllers, rutas, sesiones, módulos de negocio ni configuración Vite.

# Logo/icono

Blade solicitaba `images/icono_login.png`, inexistente en el checkout. No era una
ruta relativa: ya usaba `asset()`. Chrome confirmaba `naturalWidth = 0` antes
del cambio. Se recuperó el SVG original de Panadería RS desde el commit
`eada331f93b960be8bdaccf65b95ca366ff98b30`, sin diseñar otro icono.

Se restaura `public/images/icono_login.svg` y Blade usa
`asset('images/icono_login.svg')`, con alt «Panadería RS» y dimensiones 32 × 32.
El archivo es idéntico al original y conserva la paleta. Al servir el build,
Chrome carga la imagen con ancho natural 64 en todos los anchos comprobados.
No hay URL absoluta de localhost en la solución. `public/hot` estaba ausente.
Las pruebas existentes de HTTPS cubren resolución de helpers bajo proxy; la
carga del SVG mediante el túnel real sigue pendiente.

# Archivos modificados

- `resources/css/login.css`: contención del fondo, dimensiones y ajuste móvil.
- `resources/views/auth/login.blade.php`: capa que contiene brillos y asset SVG.
- `public/images/icono_login.svg`: archivo restaurado del historial de Git.
- `mejora_asignado.md`: seguimiento de esta corrección y sus límites.
- `dato_optimizar/Sprint5_Correccion_Login_Movil.md`: este informe.

Documentación original actualizada, sin reorganizar ni borrar historia:

- Obsidian `LARAVEL/Blade.md`: comportamiento del login y responsabilidades CSS/assets.
- Obsidian `PENDIENTES/Bugs.md`: única entrada 13 con reproducción, solución y aceptación pendiente.

# Cambios CSS

Los brillos conservan tamaños, colores, blur y offsets, dentro de `.brillos-fondo`
con position absolute/inset 0 y overflow hidden. Se recorta solo la decoración,
sin ocultar campos o títulos. El video conserva filtro, centrado y object-fit;
su contenedor usa width 100% en lugar de 100vw.

El bloque y la tarjeta mantienen width 100%, límite 480 px y centrado; se permite
su reducción con min-width 0. El encabezado queda limitado al ancho disponible,
y título/descripción admiten ajuste de texto. Los inputs tienen min-width 0 y
el botón de mostrar contraseña no se contrae. El overflow-x existente del body
queda como protección: se comprobó que no hay overflow incluso desactivándolo.

Se conservan paleta, tipografías, video, radios, sombras, botones, separaciones
principales y dimensiones de escritorio. En 1440 px la tarjeta sigue midiendo
480 px, el título 40 px y los inputs usan 15 px. Al quitar el desbordamiento
decorativo también desaparece la barra vertical artificial del caso reproducido.

# Cambios Blade

Se agrupan únicamente los dos brillos en una capa decorativa con aria-hidden.
Se corrige la referencia del logo al SVG restaurado y se añade alt descriptivo.
Se conserva el único meta viewport, @vite, formulario POST, CSRF, nombres de
campos, botón de mostrar contraseña y script de iconos. No cambia JavaScript.

# Responsive

Una media query hasta 480 px ajusta exclusivamente:

- Título: `clamp(28px, 8vw, 36px)`.
- Padding de tarjeta: `clamp(20px, 6vw, 28px)`.
- Fuente de inputs: 16 px, para evitar el zoom habitual al enfocar en móvil.

Los márgenes laterales siguen siendo 20 px. Tablet/escritorio conservan el padding
de tarjeta de 40 px y el título de 40 px. Las reglas de contención funcionan
en todos los anchos, sin añadir dependencias.

Mediciones reales con Chrome headless, assets compilados y alto 900 px:

| Ancho solicitado | Área desplazable antes | Área después | Área sin overflow-x hidden después |
| --- | --- | --- | --- |
| 320 | 420 | 320 | 320 |
| 360 | 460 | 360 | 360 |
| 375 | 475 | 375 | 375 |
| 390 | 490 | 390 | 390 |
| 412 | 512 | 412 | 412 |
| 430 | 530 | 430 | 430 |
| 768 | 868 | 768 | 768 |
| 1440 | 1525 (clientWidth 1425) | 1440 | 1440 |

Comprobación programática: 8/8 anchos correctos; tarjeta centrada, controles
dentro de la tarjeta, fuentes originales y logo cargados. El JavaScript compilado
sigue alternando a type=text y aria-label «Ocultar contraseña». No existen tests
JavaScript específicos del login en el repositorio; se verificó el comportamiento
del script existente en Chrome. Se inspeccionaron capturas antes/después de 375
y 1440 px. Evidencia temporal: `/tmp/rs-login-before-metrics.json`,
`/tmp/rs-login-after-metrics.json` y capturas `/tmp/rs-login-{before,after}-{375,1440}.png`.

# Build

- `npm run build`, antes y después: aprobado, 12 módulos transformados.
- `git diff --check`, antes y después: aprobado, sin errores de whitespace.
- `php tests/run-mariadb.php --filter='NavigationTest|ProxyHttpsTest' --do-not-cache-result`:
  **29 aprobadas, 0 fallidas, 413 aserciones**.
- `node /tmp/rs-login-check.mjs before` y `after`: reproducción y medición Chrome/CDP
  sobre Blade renderizado, servidor estático loopback y build real.
- Aserciones Node sobre las mediciones: 8/8 anchos, toggle y recuperación exacta del SVG correctos.
- Revisión de `git diff` y búsqueda de referencias/medidas/assets: cambios limitados al login.

El aviso opcional de `fontaine` ya estaba antes y no impide compilar. No se
instalaron paquetes ni se modificó configuración. Chrome necesitó escalamiento
por el bloqueo de sockets del sandbox; la ejecución autorizada completó las
mediciones. La escritura del Vault requiere autorización por estar fuera del
workspace; se realiza sobre las dos notas originales.

Aislamiento: Chrome con perfil temporal y servidor estático GET/HEAD en /tmp;
Blade renderizado con sesiones/cache array, sin enviar credenciales ni ejecutar
acciones del login. Tests PHP en MariaDB desechable sin red, dos bases ficticias,
sesiones array y Vite omitido en HTTP. No se modifica la base del proyecto, .env,
configuración del túnel ni datos reales. No se ejecutó la suite completa en esta
tarea porque no cambió lógica PHP; el total informado corresponde al filtro citado.

# Pendientes

**Aceptación visual móvil en navegador real: pendiente.** La comprobación realizada
es Chrome headless con emulación y capturas; no acredita un celular físico,
Safari/iOS, teclado virtual ni el túnel de Cloudflare real.

Con el build generado y public/hot ausente, abrir el login desde el túnel en el
celular y comprobar: título completo, tarjeta centrada, ausencia de desplazamiento
horizontal, logo visible, campos al enfocar y botón de contraseña. No se declara
despliegue, commit ni push.
