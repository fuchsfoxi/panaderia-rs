# Panadería RS — Recuperación, correcciones de Producción y experiencia móvil

Fecha: 2026-10-23. Estado: **IMPLEMENTADO Y COMPROBADO en aislamiento** para reglas e interfaz descritas. Congelamiento Android: **EN INVESTIGACIÓN**. Aceptación Android/Cloudflare: **PENDIENTE**.

## Recuperación del trabajo interrumpido

Se releyó AGENTS.md y se revisaron git status --short, git diff y los archivos sin seguimiento antes de editar. Había ocho archivos versionados modificados y tres nuevos: TurnoPermitidoProduccion.php y las dos pruebas Feature nuevas indicadas abajo. Se conservaron los once y se continuó sobre ellos; sin reset, clean, reversión, commit ni push.

La sesión anterior había dejado:

- Matriz de Ayudantes en el validador compartido y restricción de turnos mediante Rule en Requests/Action.
- Consulta de grupos activos válidos y categorías habituales por empleado.
- JavaScript para propuestas, Mañana fija, control de envío, hora compacta y ocultación del éxito.
- Blade con nuevos controles y mensajes compactos, todavía sin sus estilos.
- Dos pruebas Feature nuevas y ajustes parciales de los dobles JavaScript.
- Auditoría de arquitectura y línea base previa de 292 pruebas PHP / 2.895 aserciones aprobadas.

Al recuperar, las 20 pruebas JavaScript existentes ya pasaban; las nuevas reglas no tenían aún una ejecución aprobada documentada. Faltaban estilos, actualización de expectativas antiguas, verificación completa de navegador/recursos, documentación y este informe.

Esta sesión comprobó las reglas nuevas, completó CSS y reducción de textos, adaptó las regresiones al contrato solicitado, amplió las pruebas de hora/inicialización y de Torta sin catálogo de Ayudantes, añadió comprobación reproducible de Chrome y verificó recursos compilados sin Vite. Se revisaron los cambios y el diff final. La inspección de capturas detectó que los espacios del HTML impedían usar :empty para ocultar la caja de participantes; se corrigió mediante la clase de estado del componente y se repitieron JavaScript, build, recursos y Chrome.

## Problemas encontrados y causas

| Problema | Evidencia y causa | Resultado |
| --- | --- | --- |
| Torta rechazada sin Ayudante | El validador anterior exigía al menos uno en todas las familias. | Torta acepta solo Maestro, también sin catálogo de Ayudantes. |
| Bocadito admitía varios Ayudantes | La misma validación común solo tenía mínimo, sin cardinalidad exacta. | Exige exactamente uno; cero/dos se rechazan. |
| Torta/Bocadito aceptaban Noche | Se comprobaban existencia del turno y permiso de cuenta, sin restricción por categoría. | Rule compartida y revalidación transaccional rechazan el POST manipulado y la Action directa. |
| Selección manual sin equipos habituales | Producción cargaba todos los empleados, sin usar los grupos ya existentes. | Grupos de la categoría y personas habituales primero; selección manual disponible. |
| Doble clic en Guardar | No existía estado de envío en el formulario. | Un envío válido activa estado/indicador y bloquea otro envío desde esa página. |
| Éxito ocupaba un bloque grande | Flash presentado con el mismo contenedor amplio que otros avisos. | Estado flotante compacto, check y ocultación a los tres segundos. |
| Selector horario grande en Android | Limitación del control nativo reportada por el usuario; no reproducida en un teléfono físico aquí. | Texto HH:MM con teclado numérico, patrón y conversión de cuatro cifras, sin biblioteca. |
| Congelamiento entre categorías | No reproducido en el entorno aislado; no se encontró listener duplicado, bucle infinito ni red síncrona al cambiar categoría. | **EN INVESTIGACIÓN**; no se declara solucionado. |

La protección contra inicializar dos veces el mismo formulario es preventiva y está probada. No demuestra que una inicialización duplicada fuera la causa del reporte. Se conservan listeners únicos y no hay spinner ni retraso artificial en los cambios de categoría.

## Reglas implementadas y relaciones conservadas

| Categoría | Maestro | Ayudante | Turnos |
| --- | --- | --- | --- |
| Pan | Exactamente uno | Mínimo uno | Mañana y Noche |
| Torta | Exactamente uno | Opcional; sin máximo nuevo | Solo Mañana |
| Bocadito | Exactamente uno | Exactamente uno | Solo Mañana |

Se rechazan empleados repetidos, inactivos y roles enviados que no coinciden con el rol actual. Practicantes siguen siendo participantes adicionales según el contrato previo; no sustituyen Ayudantes obligatorios. Administrador respeta la categoría y Operador conserva además su turno asignado: Operador Noche no puede registrar Torta/Bocadito.

Turnos y categorías se resuelven desde catálogo/relaciones, sin IDs fijos. Se mantienen transacciones, bloqueos, referencias y pivotes existentes. Pan LATA conserva 18 latas/coche, factor/snapshot y finalización. El soporte previo de Pan UNIDAD permanece intacto. Torta/Bocadito guardan cantidades directas; Torta conserva forma, foto opcional y hora de vitrina. No se reescriben históricos.

Las relaciones existentes GrupoProduccion.categoria, maestro e integrantes bastan para proponer equipos. Solo se ofrecen grupos activos cuya composición actual es válida. Las personas de esos grupos se priorizan para la categoría; otras personas aptas continúan disponibles. El grupo propone miembros ausentes, conserva los ya añadidos y avisa conflictos de rol. No se persiste grupo_id en el lote ni se reservan personas.

El grupo habitual de Torta sigue configurado sin Ayudantes; el lote puede tenerlos opcionalmente. No se agregó categoría exclusiva al empleado ni reglas de reemplazo, calendario o rotación.

## Cambios de interfaz

- Mañana como texto fijo en Torta/Bocadito; selector de Pan solo con Mañana/Noche para Administrador y relación readonly para Operador.
- Validación de equipo por categoría y selección de grupo con propuestas editables.
- Guardar comunica aria-busy y estado accesible, muestra indicador solo durante un envío válido y evita doble envío. Al mostrarse nuevamente la página se restablece el estado; la política global de autenticación/BFCache se conserva.
- Éxito solo desde flash del servidor tras guardar: «Pan guardado», «Torta guardada» o «Bocadito guardado», región status con aria-live polite, check, aparición suave y ocultación a los tres segundos. No bloquea controles y respeta prefers-reduced-motion.
- Finalización y vitrina conservan nombres y formato del backend. Se aceptan HH:MM o cuatro cifras mediante JavaScript, por ejemplo 0830 → 08:30. Se mantienen required/pattern y validación Laravel 00:00–23:59. No se asigna la hora actual.
- Espacios móviles menores, controles de 44 px, texto de 16 px en inputs móviles, ayudas repetidas retiradas y caja vacía de participantes oculta. Paleta y navegación principal conservadas.

Se conserva el comportamiento anterior: cambiar familia limpia el lote y su foto/equipo; cambiar producto limpia únicamente campos incompatibles; Cancelar conserva fecha/turno/familia. No se agregó almacenamiento de borradores. old input válido continúa recuperándose después de errores del servidor; los archivos deben seleccionarse otra vez como antes.

## Archivos de código modificados o añadidos

Rutas relativas a la raíz del repositorio:

| Archivo | Motivo |
| --- | --- |
| app/Actions/Produccion/ValidarParticipantesProduccion.php | Cardinalidad de Ayudantes según familia. |
| app/Actions/Produccion/ResolverCabeceraProduccion.php | Revalidación del turno por categoría dentro de la transacción. |
| app/Rules/TurnoPermitidoProduccion.php (nuevo) | Regla compartida de turnos permitidos. |
| app/Http/Requests/RegistrarProduccionPanRequest.php | Regla de turno Pan además del permiso de usuario. |
| app/Http/Requests/RegistrarProduccionPorCantidadRequest.php | Regla Mañana para Torta/Bocadito. |
| app/Http/Controllers/ProduccionController.php | Grupos válidos/prioridades, contexto Mañana y éxito compacto. |
| resources/js/produccion.js | Reglas visuales, grupos, turnos, horas, envío, éxito e inicialización protegida. |
| resources/views/produccion/index.blade.php | Controles, mensajes, accesibilidad y Mañana fija. |
| resources/css/produccion.css | Indicador, notificación, movimiento reducido y ajustes móviles. |
| tests/Feature/ProduccionCategoriasMovilTest.php (nuevo) | 20 escenarios de composición, duplicados, turnos, persistencia y relaciones. |
| tests/Feature/ProduccionRecursosCompiladosTest.php (nuevo) | Vite real sin servidor de desarrollo, éxito tras guardar y exportación optativa de fixtures saneados. |
| tests/Browser/ProduccionMovil.mjs (nuevo) | Interacciones y 270 cambios de categoría en Chrome; solo páginas ficticias/peticiones interceptadas. |
| tests/JavaScript/ProduccionCantidadTest.mjs | Dobles actualizados, hora compacta e inicialización repetida. |
| tests/Feature/ProduccionPanStoreTest.php | Mensaje compacto, catálogo permitido y control horario nuevo. |
| tests/Feature/ProduccionTortaBocaditoStoreTest.php | Matriz de Ayudantes/turnos y control horario, conservando persistencia y rollback. |
| tests/Feature/PersonalProduccionTest.php | Opcionalidad en Torta y cardinalidad de Bocadito, conservando roles históricos. |
| tests/Feature/TurnosOperadorTest.php | Permiso de cuenta combinado con categoría. |
| tests/Feature/AutorizacionRolesTest.php | Registro válido por familia y comprobación del turno realmente usado. |
| tests/Feature/HistorialProduccionTest.php | Fixture Bocadito con un Ayudante, conservando consulta conjunta de las tres familias. |

No hay cambios en modelos, rutas, CRUD de Personal, Dashboard o Historial de producción. Sus regresiones se ejecutan en la suite completa. Los recursos de public/build se regeneraron; están ignorados por Git según la configuración existente.

## Migraciones y base de datos

No se necesitan migraciones nuevas. No se consultó ni modificó la base principal, no se cargó personal real y no se ejecutaron seeders operativos, migrate:fresh ni db:wipe. Se reutilizó tests/run-mariadb.php, que inicia MariaDB sin red con socket/datadir propios bajo /tmp/sprint4-*; IsolatedMariaDb comprueba la identidad antes de migrar exclusivamente bases ficticias. Las instancias se detienen al terminar y conservan artefactos.

La existencia y aplicación de migraciones anteriores en otro entorno no se certifica con estas pruebas. No se alteró .env ni el contrato de autenticación.

## Pruebas y resultados

| Verificación | Resultado |
| --- | --- |
| Línea base de la sesión anterior: Producción/Personal/turnos pertinentes | 292 aprobadas / 2.895 aserciones. |
| Reglas nuevas al recuperar | 19 aprobadas / 96 aserciones, antes de añadir el caso sin catálogo de Ayudantes. |
| Suite completa de cierre: php tests/run-mariadb.php --do-not-cache-result | **1.157 aprobadas / 10.273 aserciones**, sin fallidas, errores u omisiones; incluye Historial, Dashboard, Personal, permisos y concurrencia. |
| node --test tests/JavaScript/*.mjs | Cinco archivos aprobados. |
| Ejecución directa de los cinco archivos JavaScript | **60 casos aprobados**: Producción 22, Login 8, Productos 10, Sidebar 9, Usuarios 11; cero fallidas/omitidas. |
| npm run build | Aprobado. Aviso preexistente de fontaine opcional; no impide compilar. |
| Recursos compilados, repetición tras el último ajuste visual | 1 prueba / 22 aserciones aprobadas; HTML/JS/CSS de producción sin servidor Vite. |
| public/hot | Ausente; Blade probado con manifiesto compilado y sin @vite/client. |
| vendor/bin/pint --test --dirty | Aprobado para PHP afectado. No equivale a una auditoría global de formato. |
| git diff --check y revisión de diff/archivos nuevos | Aprobados. |
| Chrome con JavaScript original | 270 cambios de categoría completados a 360/412/1280 px, sin errores; máximo observado del manejador 6 ms. |
| Chrome final con recursos compilados | 270 cambios más comprobaciones de turnos/grupos/roles/horas/envío/éxito; sin errores ni overflow horizontal en los tres anchos. |

Las primeras regresiones detectaron expectativas antiguas que aceptaban Noche en Torta/Bocadito, exigían Ayudante en Torta, admitían varios en Bocadito o comprobaban type=time. Se actualizaron conservando comprobaciones de cantidades, autoría, referencias, permisos, rollback y datos históricos. No se relajaron las reglas para hacer pasar pruebas.

Chrome usa perfil temporal, fuentes externas bloqueadas y respuestas de HTML ficticio exportado por el Feature test; nunca accede al servidor operativo. Los envíos sintéticos se detienen en el harness. La persistencia se prueba separadamente mediante HTTP/Actions contra MariaDB desechable y sesiones de prueba, no mediante un E2E Chrome→base operativa. La prueba de pageshow es sintética; no certifica una restauración natural BFCache del teléfono. La CPU se ralentizó ×4 en 360 px; las medidas locales del manejador no representan latencia de Cloudflare ni todo el tiempo de renderizado.

Artefactos de esta revisión: /tmp/panaderia-movil-final (HTML saneado y capturas), /tmp/panaderia-movil-browser-final.log, /tmp/panaderia-movil-suite-cierre.log y /tmp/panaderia-movil-build-final.log. Son temporales y no se versionan.

### Repetir la comprobación aislada de navegador

Primero compilar y crear un directorio temporal. Exportar solo fixtures con el runner aislado:

```bash
npm run build
mkdir -p /tmp/panaderia-movil-final
PRODUCCION_BROWSER_HTML=/tmp/panaderia-movil-final php tests/run-mariadb.php tests/Feature/ProduccionRecursosCompiladosTest.php
```

Después, usar una instalación de desarrollo de Puppeteer y Chrome disponibles en la máquina:

```bash
PUPPETEER_MODULE=/ruta/a/puppeteer/lib/puppeteer/puppeteer.js \
CHROME_BIN=/ruta/a/chrome \
PRODUCCION_BROWSER_HTML=/tmp/panaderia-movil-final \
node tests/Browser/ProduccionMovil.mjs
```

Puppeteer es una herramienta opcional de prueba; no se añadió como dependencia de la aplicación ni se incorpora al bundle. Si el sandbox bloquea los sockets del runner o de Chrome, se necesita autorización de escalamiento; no cambiar la conexión para usar la base principal.

## Documentación original actualizada

Se inspeccionó la estructura, se buscó el tema y se leyeron las notas antes de escribir en el Vault. Se actualizaron siete originales, con autorización de escalamiento y conservación de su historia:

- PRODUCCION/Logica de negocio.md: matriz actual y conservación de unidades/participantes.
- PRODUCCION/Pantallas.md: controles, éxito, hora, grupos y límites de verificación.
- LARAVEL/Requests.md: regla compartida y responsabilidad HTTP/transaccional.
- PERSONAL Y USUARIOS/Empleados.md: ayuda desde grupos y ausencia de exclusividad.
- PENDIENTES/Bugs.md: una entrada para investigar el congelamiento y otra para la composición/turnos corregidos.
- DECISIONES/Decisiones Laravel.md: decisión fechada con contexto, opciones, motivo y consecuencias. Es el nombre real encontrado; no se creó una variante .md.md.
- INICIO/Estado de proyecto.md: resumen actual y pendientes.

No se encontró mejora_asignado.md en el repositorio ni en Documentos, incluido el Vault; no se recreó. No se reorganizaron carpetas ni se copió la documentación del Vault al repositorio. No quedó autorización documental pendiente.

## Pendientes y decisiones que necesitan confirmación

1. **EN INVESTIGACIÓN:** congelamiento del Android original. No se conoce la causa y no se da por corregido. Capturar secuencia exacta, versión de navegador, producto/categoría, participantes/foto, uso de Atrás y primer error de Console, junto con Performance y carga de assets en Network. Comparar acceso local/túnel y recarga completa. Compartir evidencia saneada, sin cookies, credenciales ni datos de sesión.
2. **PENDIENTE:** aceptación en Android físico, teclado numérico/cámara, Cloudflare, lector de pantalla, Safari y fuentes reales. La prueba headless no los sustituye.
3. Exclusividad entre categorías, autorizaciones especiales de reemplazo y rotación automática necesitan definición posterior; no fueron implementadas. La selección manual segura y las propuestas actuales no dependen de esa decisión.
4. Los pendientes anteriores de carga de empleados/equipos reales y confirmación de identidad no se cierran ni se ejecutan aquí.
5. El bloqueo de doble envío protege la interacción en una página; no crea idempotencia para reintentos independientes de red.

## Prueba manual desde Android mediante Cloudflare Tunnel

Estos pasos son instrucciones para la aceptación pendiente; no se abrió un túnel durante esta tarea.

1. Usar un entorno de aceptación con datos ficticios para probar guardados. Si se abre el checkout operativo, limitarse a revisar interfaz o registrar únicamente producción real autorizada. No usar la base principal como fixture.
2. Ejecutar npm run build. Detener Vite de desarrollo y comprobar que public/hot está ausente; si existe, revisar y retirar únicamente ese marcador de desarrollo. Abrir la página y comprobar que CSS/JS provienen de /build/assets/, sin localhost:5173 en el teléfono.
3. En una terminal iniciar el origen local:

   ```bash
   php artisan serve --host=127.0.0.1 --port=8000
   ```

4. En otra terminal iniciar el túnel:

   ```bash
   cloudflared tunnel --url http://127.0.0.1:8000
   ```

5. Abrir en Chrome Android la URL HTTPS temporal que entregue cloudflared, iniciar sesión con una cuenta autorizada y recargar completamente. No modificar APP_URL ni forzar HTTPS global para esta comprobación; la configuración previa de proxies loopback se conserva.
6. Alternar Pan→Torta→Bocadito→Pan repetidamente. Confirmar producto/campos correctos, que el formulario responde y que no aparece un spinner de cambio de categoría. Recordar que cambiar familia limpia el lote según el comportamiento anterior.
7. Revisar Mañana fija en Torta/Bocadito y ambos turnos permitidos de Pan. Probar también una cuenta Operador Noche: Pan permitido en su turno, Torta/Bocadito con Guardar bloqueado y aviso.
8. Seleccionar grupos de cada familia, comprobar roles/ausencia de duplicados y ajustar manualmente. Torta permite solo Maestro; Pan necesita Ayudante y Bocadito exactamente uno.
9. Probar finalización/vitrina escribiendo 0830/1415, y los límites 00:00/23:59; 24:00 o minutos 60 deben rechazarse. Verificar recuperación de valores al fallar otro campo.
10. En el entorno ficticio, guardar cada familia y pulsar Guardar rápidamente dos veces. Debe mostrarse Guardando…, registrarse un lote y aparecer el éxito compacto aproximadamente tres segundos tras la confirmación del servidor. Confirmar que los demás controles siguen accesibles y activar movimiento reducido para revisar la alternativa sin animación.
11. Comprobar cantidades, turno, participantes, finalización/vitrina y autor en Historial; revisar métricas de Pan en Dashboard y que Personal/Grupos mantengan sus datos. Si hay un error de guardado, no debe aparecer éxito.
12. Si vuelve a congelarse, usar depuración remota Android desde Chrome de escritorio y registrar el primer error/actividad bloqueante con Console, Network y Performance. No exportar información de autenticación. Añadir la evidencia a la entrada 23 existente de PENDIENTES/Bugs.md.

No se hizo commit, push ni despliegue automático.
