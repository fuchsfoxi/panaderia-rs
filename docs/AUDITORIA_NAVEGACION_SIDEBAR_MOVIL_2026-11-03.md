# Panadería RS — Auditoría de navegación y sidebar móvil

**Fecha:** 3 de noviembre de 2026.

**Checkout auditado:** rama `master`, HEAD `616e2f8d7cc41a970e24f6e1bc3490b6a2c110b6`; árbol de trabajo limpio al iniciar la auditoría.

**Estado de ambos problemas:** EN INVESTIGACIÓN.

**Modalidad:** inspección estática de solo lectura; este informe se creó posteriormente por solicitud expresa.

## Resumen ejecutivo

En las pruebas comunicadas, al navegar entre Dashboard, Producción e Historial desde un celular aparece el indicador de carga mientras la página anterior permanece visible varios segundos. Después de guardar una producción, el sidebar aparece temporalmente expandido. No se observó el mismo comportamiento en la laptop. Ambos dispositivos estaban conectados al mismo Wi-Fi y accedían mediante Cloudflare Tunnel; los guardados móviles se habían comprobado previamente.

La navegación entre estas pantallas utiliza documentos completos. El indicador aparece sobre el documento saliente mientras se espera la nueva página, por lo que la permanencia temporal de la pantalla anterior es compatible con el diseño actual. **No hay mediciones que permitan atribuir la duración observada** a la red, el túnel, Laravel, MariaDB, la descarga de recursos o el dispositivo.

En móvil existe una secuencia que puede explicar el salto del sidebar: el HTML y CSS presentan primero el menú completo como alternativa sin JavaScript; cuando se ejecuta el módulo compartido se aplica el estado móvil cerrado. El guardado exitoso redirige a una página nueva, donde esa inicialización se repite. Esta secuencia está confirmada en código, pero **no se ha demostrado que sea el salto observado en el celular**.

## Alcance y límites

Se revisaron rutas, middleware, controladores, consultas, vistas Blade, CSS, JavaScript y pruebas existentes, además de rama, HEAD y estado Git. No se inició sesión, accedió a datos reales, envió formularios, ejecutó pruebas automatizadas, compiló recursos ni midió la red o el servidor. No se leyeron secretos ni el contenido de `.env`. La inspección describe el código fuente de este checkout; no confirma los recursos servidos al teléfono ni la configuración efectiva del túnel.

## Hallazgo 1 — Navegación y aviso de carga

**Estado: EN INVESTIGACIÓN.** La duración informada no está medida ni reproducida durante la auditoría.

### Evidencia confirmada

- `routes/web.php:43` define las rutas de operación bajo `auth`, `EnsureUsuarioActivo`, `auth.session` y `can:acceder-operacion`. `resources/views/components/sidebar.blade.php:27` contiene enlaces HTML normales hacia las tres pantallas.
- Las vistas cargan CSS y JavaScript mediante Vite: `resources/views/dashboard/index.blade.php:7`, `resources/views/produccion/index.blade.php:7` y `resources/views/history/index.blade.php:7`. El componente compartido carga `resources/js/app.js` en `resources/views/components/sidebar.blade.php:2`.
- `resources/js/indicadores-carga.js:37` muestra «Cargando…» en navegaciones internas. Usa el evento `navigate` cuando existe Navigation API y un listener de clic como alternativa. Restaura el estado en abortos, errores, éxito, `pageshow` y `pagehide`; el fallback sin Navigation API incluye una restauración a los 15 segundos. Ese plazo no es una medición del problema.
- `resources/css/indicadores-carga.css:15` presenta el aviso como elemento fijo no bloqueante. La navegación no sustituye ni vacía la página saliente mientras se espera el nuevo documento.
- Las consultas parciales de filtros siguen otro recorrido: `resources/js/consulta-parcial.js:11` usa `fetch`, cancela la consulta previa y sustituye una región. Esto no cambia los enlaces principales a navegación parcial.

### Hipótesis pendientes

Los callbacks de aborto y restauración de `resources/js/indicadores-carga.js:108` comparten el estado del indicador sin identificar explícitamente la navegación que lo activó. Una cancelación anterior podría restaurarlo durante otra navegación consecutiva. Es una posible carrera que requiere una prueba específica; no se ha demostrado que ocurra ni que explique la espera comunicada.

La latencia puede estar en la solicitud del documento, el procesamiento del servidor, la descarga de recursos o el pintado. No se ha aislado ninguna de estas fases. Compartir Wi-Fi no garantiza condiciones idénticas de red, caché, navegador o capacidad de procesamiento para ambos dispositivos.

## Hallazgo 2 — Sidebar después de guardar

**Estado: EN INVESTIGACIÓN.** El mecanismo de inicialización existe; su relación con el síntoma real está pendiente de reproducción.

### Evidencia confirmada

- `resources/views/components/sidebar.blade.php:1` incluye el botón de apertura inicialmente oculto y el panel presente en el HTML.
- `resources/css/sidebar.css:205` establece para anchos de hasta 767 px un sidebar completo en el flujo, como alternativa sin JavaScript. `resources/css/sidebar.css:247` aplica el panel móvil oculto y fijo cuando existe la clase `sidebar-enhanced`.
- `resources/js/sidebar.js:77` inicializa el panel cerrado y añade `sidebar-enhanced`. Si hay un pintado antes de ejecutar el módulo, puede verse el menú completo y después retirarse del flujo, desplazando el contenido. No se midió ese intervalo.
- En escritorio el sidebar tiene inicialmente 76 px de ancho y se expande por foco o hover (`resources/css/sidebar.css:35` y `:195`). Esto ofrece una explicación plausible de la diferencia visual entre tamaños de pantalla, no una prueba del dispositivo observado.
- El formulario de Producción usa POST nativo (`resources/views/produccion/index.blade.php:90`) y su propio indicador «Guardando…» (`resources/js/produccion.js:56`). Tras guardar, `app/Http/Controllers/ProduccionController.php:117` redirige a `produccion.index` con contexto y mensaje de éxito. El nuevo documento vuelve a inicializar el sidebar. El código inicializa `abierto` en `false`; no se identificó persistencia del estado abierto entre páginas.

## Posibles fuentes de demora

| Capa | Evidencia disponible | Pendiente |
|---|---|---|
| Red y Cloudflare Tunnel | Se utilizó un túnel según el contexto aportado. `bootstrap/app.php:18` configura confianza en proxies locales. | Medir conexión y primer byte; comparar con acceso directo equivalente en un entorno aislado. La configuración del código no demuestra que el túnel sea la causa. |
| Laravel y MariaDB | `DashboardController.php:22` ejecuta agregaciones y carga producciones. `ProduccionController.php:32` carga catálogos, empleados, grupos y registros. `app/Queries/ConsultarHistorialProduccion.php:14` realiza una unión paginada y carga relaciones. | Medir tiempo de respuesta y consultas con datos de prueba. La existencia de consultas no acredita lentitud. |
| Recursos y navegador | Las vistas cargan CSS y módulos Vite; `resources/css/sidebar.css:1` declara una fuente externa. | Verificar recursos realmente servidos, tiempos de descarga, procesamiento y primer pintado; no se comprobó la cascada de red. |
| Caché e historial | `app/Http/Middleware/PreventPageCaching.php:15` añade directivas de no almacenamiento a respuestas de rutas de autenticación. `resources/js/app.js:4` recarga al restaurar un documento desde BFCache. | Comparar enlace normal, recarga y Atrás/Adelante sin atribuirles tiempos no medidos. |

El POST exitoso añade un recorrido distinto al de un enlace: primero se procesa el formulario y después se sigue la redirección a un GET de Producción. Una recarga completa vuelve a solicitar y procesar el documento; Atrás/Adelante puede activar la lógica de BFCache citada. Las consultas parciales de filtros usan `fetch` y sustitución de región.

## Pruebas propuestas

Realizar las pruebas en un entorno aislado, con cuentas y datos ficticios, sin operar sobre registros reales:

1. Anotar dispositivo, versión del navegador, ancho de viewport y disponibilidad de `window.navigation`. Repetir navegación entre las tres páginas en ambos sentidos, con caché fría y caliente, en móvil y laptop.
2. Registrar por separado inicio de navegación, DNS/conexión/TLS, espera al primer byte, descarga del documento, CSS/JS, `DOMContentLoaded` y primer pintado. Evitar registrar o compartir cookies, credenciales, cabeceras de autorización y datos personales.
3. Cuando sea posible, comparar túnel y acceso directo equivalente al mismo servidor de pruebas. Contrastar el primer byte con métricas del servidor y de consultas recogidas de manera segura. Esta comparación permitirá separar las fases sin presuponer su causa.
4. Grabar una traza de rendimiento o video del primer pintado móvil y observar cuándo aparece `sidebar-enhanced`. Repetir con CPU y red controladas, comparando enlace, recarga y redirección posterior a un POST de prueba aislado.
5. Probar cancelaciones y clics consecutivos con y sin Navigation API, además de Atrás/Adelante y navegación con JavaScript deshabilitado. Comprobar indicador, foco, accesibilidad y recuperación del menú.

`tests/Browser/IndicadoresCarga.mjs:47` usa solicitudes interceptadas y pausas simuladas; `tests/Browser/ProduccionMovil.mjs:23` intercepta solicitudes para evitar acceso al servidor operativo. No se ejecutaron en esta auditoría y no prueban el rendimiento del túnel ni del dispositivo físico.

## Propuesta mínima y riesgos

Si la prueba confirma el pintado inicial del sidebar, aplicar el estado móvil mejorado lo suficientemente pronto para evitar el cambio de disposición y conservar una alternativa accesible cuando JavaScript esté deshabilitado o falle. Verificar ancho móvil y escritorio, foco, `inert`, apertura, cierre y navegación por teclado. Ocultar el panel anticipadamente sin recuperación podría impedir acceder al menú.

Para la espera, medir primero la fase dominante. Si se reproduce la carrera del indicador, asociar su restauración a la navegación correspondiente y probar cancelaciones superpuestas. El cambio del indicador por sí solo no reduce el tiempo de respuesta. Una modificación incorrecta puede ocultarlo antes de tiempo, dejarlo visible o interferir con la revalidación al volver con Atrás.

## Estado y próximos pasos

No se modificó código durante la auditoría original. Este informe es la única modificación solicitada posteriormente. No se ejecutaron pruebas ni mediciones; las dos incidencias siguen **EN INVESTIGACIÓN**. Quedan pendientes su reproducción controlada, la identificación de la fase que consume tiempo y la validación del mecanismo visual en el dispositivo. No se propone implementar una corrección hasta obtener esa evidencia.
