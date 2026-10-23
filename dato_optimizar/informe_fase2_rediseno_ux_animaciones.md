# Panadería RS — Fase 2: diseño, UX y animaciones

Fecha: 23 de octubre de 2026. Estado del checkout local, sin commit ni push.

## Alcance y conservación del trabajo anterior

Se leyeron `AGENTS.md`, el estado y diff de Git, incluidos los archivos sin seguimiento, y se revisaron Producción, Historial, Dashboard, Personal, Requests, Actions, modelos, relaciones y pruebas antes de editar. Había 20 archivos con trabajo local de la fase anterior: se conservaron sus implementaciones y se extendieron únicamente las partes necesarias. Diez permanecen idénticos al inicio, entre ellos las Actions y Requests de registro, la regla de turnos, las pruebas específicas por categoría y el informe anterior. El diff respecto a HEAD contiene ambas fases; no representa únicamente esta entrega.

Ya existían la selección por categoría habitual sin exclusividad, la propuesta de grupos sin reemplazo, validación de participantes y turnos en Laravel, horas compactas, prevención de doble envío y notificación de éxito confirmada por el servidor. Se reutilizaron; no se reconstruyeron.

No se conectaron las pruebas a MariaDB operativa ni se alteraron sus registros. No hay migraciones nuevas, seeders, dependencias nuevas, cambios de permisos ni nuevas acciones de eliminación. Las migraciones de prueba se ejecutaron exclusivamente en las bases ficticias del servidor temporal sin red creado por `tests/run-mariadb.php`.

## Estado de las 15 mejoras (apartados 2–16 de la solicitud)

**IMPLEMENTADO** describe código disponible. **PROBADO** indica únicamente las verificaciones mencionadas aquí. **PENDIENTE** identifica límites de aceptación. **NO REPRODUCIDO** no equivale a corregido.

| Mejora | Estado y evidencia |
| --- | --- |
| 1. Información redundante | IMPLEMENTADO Y PROBADO: se retiraron instrucciones permanentes; ayudas necesarias permanecen para lectores de pantalla y los errores siguen disponibles. |
| 2. Turno fijo oculto | IMPLEMENTADO Y PROBADO: Torta/Bocadito no muestran el turno en el formulario; Laravel conserva Mañana y valida solicitudes manipuladas. Historial y tarjetas muestran el turno. |
| 3. Pan compacto | IMPLEMENTADO Y PROBADO: cantidades en una fila desde 340 px; resumen breve con estado vacío, equivalencias y estimación. Pan por unidades conserva su representación propia. |
| 4. Equipo de trabajo | IMPLEMENTADO Y PROBADO: grupo opcional, botón textual, participantes compactos con rol; sin recuadro vacío grande ni reemplazo automático. |
| 5. Observaciones desplegables | IMPLEMENTADO Y PROBADO: cierre sin pérdida de texto; apertura inicial si hay contenido recuperado o error; persistencia en servidor. |
| 6. Horas | IMPLEMENTADO Y PROBADO: `0236` y `2:36` → `02:36`; `14:36` válido; `236` y `25:70` rechazados. Placeholder HH:MM, teclado numérico y contrato de servidor conservados. |
| 7. Espaciados móviles | IMPLEMENTADO Y PROBADO en Chrome a 360/412 px y escritorio: controles accesibles, inputs de 16 px, sin desbordamiento horizontal. Android físico PENDIENTE. |
| 8. Filtros de registros | IMPLEMENTADO Y PROBADO: fecha, turno, categoría y paginación de 15; consulta independiente sin perder el lote en edición. Reutiliza la consulta de Historial. |
| 9. Tarjetas desplegables | IMPLEMENTADO Y PROBADO: una abierta a la vez, teclado, estado accesible, información histórica y transiciones progresivas compartidas. |
| 10. Historial | IMPLEMENTADO Y PROBADO: jerarquía renovada y componente compartido; filtros, resultados, fotos, datos históricos y autorizaciones conservados. |
| 11. Dashboard | IMPLEMENTADO Y PROBADO: estructura nueva, KPI reales, periodo validado, gráfico responsivo, alternativa textual y estados vacíos. |
| 12. Bienvenida | IMPLEMENTADO Y PROBADO: luz cálida, cascada y cifras hasta los valores reales en unos 1350 ms, una vez por pestaña; sin bloquear ni repetir al consultar. |
| 13. Microinteracciones | IMPLEMENTADO Y PROBADO: guardado existente conservado, skeleton solo durante fetch, categorías sin espera, desaparición después de eliminación confirmada. Congelamiento Android: NO REPRODUCIDO, EN INVESTIGACIÓN. |
| 14. Identidad | IMPLEMENTADO Y PROBADO visualmente en capturas de Chrome: paleta orgánica, navegación lateral conservada, tipografía y componentes uniformes. |
| 15. Calidad | PROBADO: suite PHP completa, JavaScript, compilación, formato, diff y navegador. PENDIENTE: aceptación en Android real mediante Tunnel y otros motores de navegador. |

## Producción y reglas conservadas

- Pan: exactamente un Maestro y al menos un Ayudante; Mañana o Noche. Torta: exactamente un Maestro, Ayudante opcional, solo Mañana. Bocadito: exactamente un Maestro y un Ayudante, solo Mañana. Se conserva el tratamiento vigente de Practicantes adicionales y se rechazan empleados duplicados.
- Categorías y turnos se resuelven con las relaciones/reglas existentes, sin IDs fijos. La categoría habitual prioriza equipos sin introducir exclusividad ni permisos de reemplazo.
- Se mantiene Pan por latas, equivalencia de 18 latas por coche, snapshots de panes por lata y estimaciones; Pan de molde por unidades y cantidades de Torta/Bocadito no se convierten a latas.
- No se eliminaron formas de torta, fotografía, finalización, vitrina, observaciones ni campos adicionales funcionales. Cerrar observaciones conserva el texto; cambiar de categoría mantiene el contrato previo de reinicio de los campos que no corresponden.
- Los filtros independientes actualizan únicamente los registros. Tienen estados de carga/error, cancelación de solicitudes anteriores, paginación y estado vacío; un error conserva los resultados anteriores. Sin JavaScript, el formulario permite consultar Historial mediante navegación normal.

## Historial y componentes reutilizados

`ConsultarHistorialProduccion` y `ConsultarHistorialProduccionRequest` siguen siendo la fuente de consulta/validación para el Historial y la lista en Producción. Se comparte `registro-produccion` para datos, cantidad principal, turno, participantes históricos, autor, horas, foto y observaciones; `paginacion-registros` comparte navegación.

Los detalles usan `<details>/<summary>` nativos con exclusividad por grupo y refuerzo JavaScript mediante un único listener delegado. El texto alterna Ver detalles/Ocultar detalles. La transición de altura utiliza mejora progresiva de CSS: navegadores sin soporte mantienen la apertura nativa funcional. El movimiento reducido suprime la animación.

La eliminación de Historial conserva confirmación, Gate, CSRF, Action transaccional y navegación HTML. Cuando el cliente solicita JSON, el controlador confirma la identidad eliminada después del éxito de la Action. La tarjeta desaparece solo tras esa confirmación y después se actualiza la página filtrada para mantener conteos/paginación. Un error conserva la tarjeta y muestra el mensaje en el diálogo. Producción no incorpora botones de eliminación.

## Dashboard y animaciones

El Dashboard conserva el alcance real de sus métricas: **Pan**. Los lotes cuentan registros de Pan; el total de latas suma solo aquellos cuya unidad histórica es LATA. Las unidades se incluyen en lotes, sin sumarlas como latas. No se añadieron KPI de Torta/Bocadito ni conversiones inventadas. Se conserva el grupo «Sin turno registrado» para datos antiguos cuando corresponde.

El periodo predeterminado es hoy para KPI y gráfico; las últimas cinco producciones conservan su alcance histórico previo. Al consultar un periodo explícito, también se acotan las últimas cinco al periodo. Los filtros inválidos presentan errores y no muestran agregados de una consulta inválida. El Request reutiliza las reglas de fecha de Historial.

Chart.js existente se carga mediante importación dinámica solo si hay gráfico; se destruye la instancia anterior al actualizar. Hay tabla/listado de turnos accesible y estado sin datos. No hay datos estáticos de demostración en las vistas de aplicación.

La bienvenida usa opacidad/transformación y luz radial sutil; las cifras animadas tienen un equivalente accesible con el valor final. El HTML ya contiene el valor verdadero antes de JavaScript. La sesión de pestaña evita repetir la entrada y los filtros no disparan contadores de nuevo. `prefers-reduced-motion` elimina entrada, transición de categorías y desaparición animada. Los skeletons se superponen durante cargas reales sin desplazar el contenido, sin temporizadores artificiales.

## Archivos de esta fase

Modificados durante Fase 2 (algunos ya tenían cambios de Fase 1):

```text
app/Http/Controllers/DashboardController.php
app/Http/Controllers/HistorialController.php
app/Http/Controllers/ProduccionController.php
resources/css/dashboard.css
resources/css/historial.css
resources/css/produccion.css
resources/js/dashboard.js
resources/js/historial.js
resources/js/produccion.js
resources/views/dashboard/index.blade.php
resources/views/history/index.blade.php
resources/views/produccion/index.blade.php
routes/web.php
tests/Feature/EliminarHistorialProduccionTest.php
tests/Feature/HistorialProduccionTest.php
tests/Feature/PersonalProduccionTest.php
tests/Feature/ProduccionPanStoreTest.php
tests/Feature/ProduccionTortaBocaditoStoreTest.php
tests/Feature/ProductosTest.php
tests/Feature/TurnosOperadorTest.php
tests/JavaScript/ProduccionCantidadTest.mjs
```

Nuevos durante Fase 2:

```text
app/Http/Requests/ConsultarDashboardRequest.php
resources/css/consultas.css
resources/css/registros.css
resources/js/consulta-parcial.js
resources/js/consultas-produccion.js
resources/js/tarjetas-produccion.js
resources/views/components/paginacion-registros.blade.php
resources/views/components/registro-produccion.blade.php
resources/views/produccion/registros.blade.php
tests/Browser/ExperienciaFase2.mjs
tests/Feature/ExperienciaVisualFase2Test.php
tests/JavaScript/ConsultaParcialTest.mjs
dato_optimizar/informe_fase2_rediseno_ux_animaciones.md
```

Los ajustes de expectativas existentes corresponden a etiquetas/unidades específicas de categoría, orden y marcado de tarjetas, y alcance del selector de turno dentro del formulario de registro. Se mantienen las aserciones de integridad y reglas de negocio; no se relajaron validaciones para obtener pruebas verdes.

## Resultados de verificación

| Comprobación | Resultado |
| --- | --- |
| Línea base pertinente antes de cambios | 92 pruebas / 757 aserciones correctas. |
| `php tests/run-mariadb.php` — suite completa final | **1176 pruebas / 10411 aserciones correctas**, sin fallos ni omisiones; aproximadamente 84 s. |
| Exportación y pruebas específicas de experiencia | 17 pruebas / 114 aserciones correctas en servidor MariaDB temporal independiente. |
| Revisión final de Dashboard y experiencia | 31 pruebas / 271 aserciones correctas después de ajustar el marcado semántico de las fechas del periodo. |
| `node --test tests/JavaScript/*.mjs` | **63 pruebas correctas**: Producción 22, consultas 3, login 8, productos 10, sidebar 9, usuarios 11. |
| `npm run build` | Correcto; se genera el manifest de Vite y los recursos de producción. Advertencia opcional existente de fontaine sin impedir la compilación. |
| Pint y `git diff --check` | Correctos. |
| Chrome automatizado con recursos compilados | 360, 412 y 1280 px: sin errores JavaScript ni desbordamiento; categorías, equipo, horas, observaciones, filtros sin pérdida de lote, teclado, exclusividad, eliminación, KPI, gráfico y movimiento reducido correctos. |

Se prueban registros por latas/unidades, participantes y duplicados, turnos manipulados, cálculos/snapshots, grupos, persistencia, roles/autorizaciones, CSRF, rollback y regresiones de Personal/Historial/Dashboard con datos ficticios. El filtro paginado se compara con los resultados de Historial y se comprueba que no escribe registros. La consulta del Dashboard distingue unidades de latas y verifica periodos e inválidos.

Los primeros pases detectaron expectativas de presentación antiguas y un origen incorrecto en el arnés del navegador; se corrigieron y se repitieron las verificaciones. Una ejecución temporal falló antes de iniciar las pruebas por falta de espacio en `/tmp` (InnoDB, error 28). Se conservaron, moviéndolos a `storage/logs/pruebas-conservadas/fase2/`, artefactos de servidores de prueba **ya detenidos**; no se borraron datos ni se tocó MariaDB operativa. La suite final completa pasó después de resolver ese límite del entorno.

Los fixtures de navegador fueron renderizados por Laravel en MariaDB aislada y exportados a `/tmp/panaderia-fase2-final/`. El navegador intercepta las solicitudes: sirve esos HTML y archivos reales de `public/build/assets`; simula espera/error/éxito de consultas y eliminación. Por tanto, verifica la interfaz y sus estados de transporte; las escrituras y permisos reales del backend se verifican por separado mediante PHP. No es una sesión conectada a la base principal ni una prueba física de Android.

Reproducción del arnés, con Puppeteer/Chrome ya disponibles en el entorno:

```bash
FASE2_BROWSER_HTML=/tmp/panaderia-fase2-final php tests/run-mariadb.php --filter=ExperienciaVisualFase2Test
PUPPETEER_MODULE=/ruta/local/a/puppeteer.js FASE2_BROWSER_HTML=/tmp/panaderia-fase2-final node tests/Browser/ExperienciaFase2.mjs
```

Los logs de esta sesión permanecen en `/tmp/panaderia-fase2-*.log`; los artefactos temporales pueden desaparecer al reiniciar el equipo.

## Rendimiento, límites y pendientes

- **NO REPRODUCIDO / EN INVESTIGACIÓN:** congelamiento al alternar Pan/Torta/Bocadito en Android. Se hicieron 270 cambios automatizados (90 por resolución), también con CPU ralentizada 4× a 360 px, sin bloqueo ni error JavaScript. Esto no demuestra que el fallo del teléfono esté resuelto. La transición de 160 ms es cancelable, no espera para cambiar el formulario y no añade listeners por cambio.
- Los tiempos del handler medidos en Chrome fueron inferiores a 100 ms en la matriz final previa al cierre; no representan tiempo total de render ni rendimiento de un teléfono real. No se realizó un benchmark con grandes volúmenes operativos.
- La paginación limita resultados a 15 por página; se reutiliza la carga de relaciones de Historial. Chart.js se separa en un chunk de aproximadamente 150 KB sin comprimir / 52 KB gzip; no se agregaron bibliotecas. Las consultas reales sobre periodos muy extensos necesitan observarse con el volumen de producción antes de justificar índices o caché adicionales.
- **PENDIENTE:** aceptación en Android real, teclado numérico concreto del dispositivo, Chrome mediante Cloudflare y otros navegadores. La interpolación de altura depende del soporte CSS; en motores sin él funciona el desplegable nativo sin esa transición.
- No hay decisiones nuevas de exclusividad/reemplazo pendientes: se mantuvo el comportamiento vigente. No se declara corregido el contrato `getAuthPasswordName()`; continúa fuera del alcance, conforme a AGENTS.md.

## Validación manual desde Android mediante Cloudflare Tunnel

1. Usar una instancia con datos ficticios o un entorno de prueba para registrar/eliminar ejemplos. No probar eliminación ni registrar ejemplos en datos operativos sin intención real de hacerlo.
2. Ejecutar `npm run build`. Confirmar que no hay un archivo `public/hot` activo y que no se ejecuta Vite de desarrollo. Levantar la aplicación con la configuración local habitual, por ejemplo `php artisan serve --host=127.0.0.1 --port=8000`.
3. En otra terminal ejecutar `cloudflared tunnel --url http://127.0.0.1:8000` y abrir en Android la URL HTTPS que devuelve. No guardar en documentación cookies, credenciales ni tokens. Mantener el tratamiento de proxy/HTTPS que ya utiliza el proyecto.
4. Probar 360/412 px: alternar categorías varias veces, seleccionar productos por latas y unidades, grupos y participantes; comprobar turnos permitidos, duplicados, cálculos y campos específicos. Verificar que Torta admite solo Maestro.
5. Introducir `0236`, `2:36`, `14:36`, `236` y `25:70` en finalización/vitrina. Cerrar y abrir observaciones; provocar una validación y confirmar que se recupera el texto.
6. Durante un lote sin guardar, cambiar fecha/categoría/turno de la consulta de registros y paginar. El lote debe permanecer intacto; el skeleton solo debe aparecer mientras hay una solicitud pendiente. Probar un día sin resultados.
7. Guardar una producción válida: un único envío, estado Guardando… y notificación breve solo después de éxito. Confirmar cantidades, turno, foto/forma, equipo y observación en Historial. En datos ficticios, probar cancelar y confirmar la eliminación autorizada.
8. Abrir dos tarjetas sucesivas; solo la última queda abierta. Probar teclado externo/lector de pantalla cuando estén disponibles. Activar «reducir animaciones» y repetir.
9. Visitar Dashboard: comprobar cifras contra registros conocidos del entorno de prueba, cambiar periodo y volver. La bienvenida no debe repetirse en la misma pestaña; los datos llegan sin esperas artificiales.
10. Si ocurre congelamiento, conservar la hora, categoría/producto, secuencia, versión de Chrome/Android y orientación. Con depuración USB, capturar Console y Performance de Chrome, incluido el long task y stack si existe, y solicitudes fallidas de Network, **sin cookies, tokens ni datos personales**. Incorporar la evidencia a la entrada existente del bug, sin duplicarla.

## Documentación original actualizada

Se integraron los cambios preservando la historia de las notas originales del Vault:

- `PRODUCCION/Pantallas.md`: interfaz de Fase 2 y límites comprobados.
- `LARAVEL/Controllers.md`: consulta parcial, periodo del Dashboard y respuesta JSON de eliminación.
- `LARAVEL/Requests.md`: reutilización de validación y Request del Dashboard.
- `DECISIONES/Decisiones Laravel.md`: decisión fechada con contexto, opciones, motivo y consecuencias.
- `INICIO/Estado de proyecto.md`: estado resumido y aceptación pendiente.
- `PENDIENTES/Bugs.md`: seguimiento dentro de la entrada existente del congelamiento, sin declararlo resuelto.

No se encontró `mejora_asignado.md`; no se creó un sustituto. Este informe fue solicitado expresamente dentro del repositorio y no sustituye ni duplica las notas completas del Vault.
