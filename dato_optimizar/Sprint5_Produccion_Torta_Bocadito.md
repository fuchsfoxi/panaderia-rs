# Sprint 5 — Producción de Pan, Torta y Bocadito

Fecha: 2026-10-10. Estado: **IMPLEMENTADO Y COMPROBADO mediante HTTP/MariaDB aislada, eventos JavaScript y build**. Migración aplicada en la BD local. Aceptación visual/operativa en navegador: **PENDIENTE**.

## Estado inicial

Laravel 13 instalado; Pan ya tenía `ProduccionController`, dos FormRequests y `RegistrarProduccionPan`. Un POST registra un lote independiente y reutiliza sesión por fecha/categoría/turno. Pan calcula latas y conserva unidad, turno legacy y snapshot de panes por lata. Bloquea categoría/producto/cabeceras dentro de una transacción y rechaza sesiones duplicadas; no existe UNIQUE SQL de sesión.

Había modificaciones previas sin commit en Action/Request/Blade/JS/tests de Pan y documentación, incluido el ajuste de cantidades vacías a cero. Se conservaron. Torta/Bocadito aparecían deshabilitados; solo existían sus modelos, relaciones y tablas. Torta no tenía cantidad.

Referencia inicial: `php artisan test` detectó 240 tests, ejecutó 9 y omitió 231. El runner aislado ejecutó los **240/240**, con **2.618 aserciones**. No se tomó la ejecución con omisiones como cobertura completa.

BD local, lectura selectiva: categorías Pan/Torta/Bocadito; roles Maestro/Ayudante; turnos Mañana/Noche; cero filas históricas de Torta. Las 24 migraciones anteriores estaban aplicadas. No se leyeron cuentas ni credenciales y no se modificó `.env`.

## Archivos creados

- `app/Http/Requests/RegistrarProduccionTortaRequest.php`: validación específica de forma/foto.
- `app/Http/Requests/RegistrarProduccionBocaditoRequest.php`: límite HTTP de Bocadito.
- `app/Http/Requests/RegistrarProduccionPorCantidadRequest.php`: estructura, cantidades, referencias y mensajes compartidos.
- `app/Actions/Produccion/RegistrarProduccionTorta.php`: entrada de Torta y almacenamiento/compensación de foto.
- `app/Actions/Produccion/RegistrarProduccionBocadito.php`: entrada explícita de Bocadito.
- `app/Actions/Produccion/RegistrarProduccionPorCantidad.php`: guardado transaccional común de las dos familias por unidades.
- `app/Actions/Produccion/ResolverCabeceraProduccion.php`: consulta bloqueada, reutilización y rechazo de duplicadas.
- `app/Actions/Produccion/ValidarParticipantesProduccion.php`: regla operacional compartida sin IDs fijos.
- `database/migrations/2026_10_10_120000_add_cantidad_to_detalle_torta.php`: evolución aditiva de Torta.
- `tests/Feature/ProduccionTortaBocaditoStoreTest.php`: 84 casos nuevos, incluidos datasets.
- `dato_optimizar/Sprint5_Produccion_Torta_Bocadito.md`: este informe.

## Archivos modificados

- `app/Http/Controllers/ProduccionController.php`: catálogos de tres familias, consulta por familia y tres entradas de registro delegadas a Actions; respuesta HTTP común.
- `app/Http/Requests/ConsultarProduccionRequest.php`: filtro de familia seguro y recuperación de contexto del POST.
- `app/Http/Requests/RegistrarProduccionPanRequest.php`: mensaje de ruta exclusiva de Pan y recuperación de familia confiable al fallar validación; se conserva la normalización previa.
- `app/Actions/Produccion/RegistrarProduccionPan.php`: delega únicamente participantes/cabeceras a helpers; mantiene cálculo, transacción, orden de locks, detalle, snapshot y eventos de Pivot.
- `app/Models/DetalleTorta.php`: `cantidad` en fillable y cast integer.
- `routes/web.php`: POST de Torta y Bocadito bajo `auth`; conserva GET y POST originales de Pan.
- `resources/views/produccion/index.blade.php`: familias activas, campos pertinentes, catálogo real, errores, old input saneado y resultados de la familia consultada.
- `resources/js/produccion.js`: selección de endpoint/catálogo/campos, exclusión de controles ocultos del POST, participantes de las tres familias y reset con contexto conservado.
- `resources/css/produccion.css`: aplica estilo existente al input file.
- `tests/Feature/ProduccionPanStoreTest.php`: actualiza expectativas de categorías activas/ruta exclusiva sin retirar regresiones de Pan.
- `tests/Feature/ModelsRelationshipsTest.php`: nuevo contrato de cantidad de Torta.
- `tests/Feature/ProductionObservationsMigrationTest.php`: excluye la migración nueva del inventario histórico y comprueba su ciclo completo.
- `tests/Support/IsolatedMariaDb.php`: fixtures de Torta con cantidad cuando el esquema la incluye, conservando pruebas del esquema histórico.
- `tests/JavaScript/ProduccionCantidadTest.mjs`: conserva tres casos previos y añade dos de cambio de familia/recuperación de Torta.
- `mejora_asignado.md`: seguimiento de implementación, evidencia y límites.

Las modificaciones previas de `dato_optimizar/informe_pulido_final_presentacion.md` y el informe previo de coches/latas no pertenecen a esta entrega y se conservaron.

## Migración

Agrega `detalle_torta.cantidad` como INT NOT NULL con `CHECK (cantidad > 0)`, siguiendo los CHECK existentes de Pan/Bocadito. La capacidad técnica máxima es 2.147.483.647; no se inventa un máximo operativo.

El contrato documentado anterior era una fila por torta física: el default transitorio 1 conserva las filas previas. Se retira inmediatamente el default para exigir cantidad explícita en nuevas escrituras SQL. No se editaron migraciones históricas. Down se bloquea si hay cantidades distintas de 1, porque el esquema anterior no puede representarlas sin pérdida.

Estado local: **aplicada, batch 3**. Se revisaron estado inicial, archivo, SQL `--pretend`, ejecución normal y estado final. La conservación de filas y reversión se probaron solo con fixtures; no se hizo rollback local ni se utilizaron `migrate:fresh`/`db:wipe`.

## Torta

`POST /produccion/torta` (`produccion.torta.store`) registra fecha, turno de cabecera, producto activo de Torta, cantidad, forma, observación y participantes. Un detalle representa un lote con cantidad y atributos compartidos. Forma admite texto de hasta 30 caracteres, sin restringirla a las dos opciones de la maqueta.

Foto opcional: archivo JPEG/PNG/WebP válido, máximo técnico de 2 MB; no se admite SVG ni una ruta enviada como texto. Se almacena con nombre generado en el disco estándar `public`, bajo `produccion/tortas/`; la tabla guarda la ruta. El enlace usa `asset()` para respetar el esquema de la petición, incluido HTTPS reconocido por el proxy local. `php artisan storage:link` creó el enlace público estándar.

Ante fallo de escritura de foto se revierte SQL. Ante fallo de detalle/pivot/commit se revierte SQL y se elimina la foto nueva. La compensación también se intenta ante excepciones de programación, que se propagan. Si el propio borrado falla, se reporta sin ocultar la excepción original. No se implementa una cola de limpieza ni atomicidad física conjunta entre SQL y filesystem.

## Bocadito

`POST /produccion/bocadito` (`produccion.bocadito.store`) registra fecha, turno de cabecera, producto activo de Bocadito, cantidad en unidades, observación opcional y participantes. Reutiliza transacción, catálogos, cabecera y reglas de equipo. Conserva su CHECK SQL previo.

## Arquitectura

Controller: lecturas sencillas, selección de Action y respuestas HTTP; no calcula cantidades, crea pivots ni abre transacciones. Requests: tipos, estructura, referencias y límites de campos/archivo. Actions: reglas de negocio y persistencia. Helpers compartidos: sesión y participantes. Models: relaciones/fillable/casts existentes; Blade: presentación y recuperación segura; JS: selección visual y ayudas.

Pan mantiene su Action específica. La escritura común por cantidad sirve únicamente a Torta/Bocadito; no introduce Repositories, DTOs ni dependencias. Las consultas de detalles cargan producto/empleados con eager loading; los roles se resuelven desde la colección de catálogo, sin consultas dentro del bucle Blade.

## Validaciones

- Un único detalle en `detalles[0]`; fecha ISO válida; turno existente y obligatorio en las tres familias, según esta instrucción de proyecto.
- Familia correspondiente al endpoint; producto existente, activo y perteneciente al catálogo correcto, revalidado bajo lock en la Action.
- Cantidad entera estrictamente positiva y dentro del INT; forma obligatoria en Torta.
- Observación opcional de texto, hasta 65.535 bytes de TEXT, incluyendo validación Unicode por bytes.
- Empleados/roles existentes; regla de equipo y ausencia de empleados repetidos en la Action.
- Registrador derivado exclusivamente del usuario autenticado.
- Campos ocultos deshabilitados en frontend; backend mantiene todas las reglas críticas sin depender de JS.
- Old input reducido a escalares/arrays esperados; estructuras rechazadas no provocan HTTP 500. Familia confiable recuperada desde el endpoint aun si `categoria` fue manipulada. Los errores se muestran junto al campo y se ocultan al cambiar a otra familia. La foto debe seleccionarse de nuevo tras un error.

## Participantes

Se buscan Maestro y Ayudante por `nombre_roles_produccion`, nunca por ID fijo. Cada detalle exige exactamente un Maestro y uno o más Ayudantes; un empleado participa una sola vez y tiene un rol por detalle. Se conservan `attach`, custom Pivots y sus eventos.

Para Torta/Bocadito es una **regla provisional reutilizada de Pan por instrucción del usuario**, porque no se encontró una regla específica distinta. No se atribuye automáticamente a la dueña ni implica permisos de acceso o cargos laborales.

## Cabeceras de producción

Se conserva `fecha + categoría + turno = sesión`. Cada envío añade un lote independiente, aunque repita producto. Se bloquea la categoría para serializar escritores del flujo y luego se consulta cabecera con `lockForUpdate`. Cero coincidencias crea; una reutiliza sin cambiar registrador; dos bloquean el registro y la consulta de detalles.

Torta/Bocadito usan el turno de cabecera; no reciben un turno redundante en detalle. Pan conserva su FK legacy coherente con la cabecera. No se añadió UNIQUE ni se clasificaron/backfillearon cabeceras legacy NULL. La protección depende de que los escritores respeten el mismo bloqueo; no se ensayaron solicitudes simultáneas reales.

## Tests

Suite final: **324 ejecutados, 324 aprobados, 0 fallidos, 0 omitidos; 3.300 aserciones** en MariaDB desechable sin red. Incluye los 240 casos de referencia y 84 nuevos. Se prueban ambas familias, cantidad/forma/observación/foto, referencias inactivas/ajenas/inexistentes, tipos malformados, equipo, invitados, autor real, sesiones reutilizadas/duplicadas/separadas, convivencia de las tres familias, fallos del segundo pivot y almacenamiento, además de revalidación directa de Actions. La prueba de migraciones comprueba filas previas, tipo/default/CHECK, falta de cantidad, down protegido y up/down/up.

La primera suite ampliada tuvo siete fallos en aserciones nuevas de sesión realizadas después de seguir la redirección. Se corrigió el momento de comprobación del flash y se repitió la suite completa hasta pasar. No se presentan esas ejecuciones fallidas como evidencia final aprobada.

JavaScript: **5/5** de Producción y **7/7** de sidebar, mediante script/eventos y dobles DOM. Build y Pint de PHP afectado aprobados; `git diff --check` aprobado. Sin navegador real, revisión de geometría ni certificación del driver de sesiones database.

## Comandos ejecutados

| Comando | Motivo y resultado |
|---|---|
| `git status --short`, `rg`, lecturas de archivos y `git diff` | Reconocer checkout, cambios previos, contratos/catálogos/notas y revisar responsabilidades; cambios previos preservados |
| `php artisan test` antes/después | Referencia convencional: inicial 9 aprobados/231 omitidos; final 9 aprobados/315 omitidos. No acredita la cobertura MariaDB |
| `php tests/run-mariadb.php --do-not-cache-result` | Suite real en bases ficticias: inicial 240/2.618; final 324/3.300, todas aprobadas sin omisiones |
| `php artisan migrate:status` antes/después | Comprobar esquema aplicado: 24 anteriores Ran; nueva migración final Ran batch 3. Primera conexión bloqueada por sandbox; repetida con autorización |
| `php artisan tinker --execute='…'` | Lectura únicamente de nombres de categorías/roles/turnos y conteo de Torta: catálogos presentes, 0 filas. Sin cuentas/secretos |
| `php artisan migrate --path=database/migrations/2026_10_10_120000_add_cantidad_to_detalle_torta.php --pretend` | Revisar tres sentencias SQL sin aplicar; correcto |
| `php artisan migrate --path=database/migrations/2026_10_10_120000_add_cantidad_to_detalle_torta.php` | Aplicar solo la aditiva revisada/probada; DONE |
| `php artisan storage:link` | Exponer fotos del disco public mediante enlace estándar; creado correctamente |
| `php artisan route:list --path=produccion` | Confirmar GET único, POST Pan original y dos POST nuevos; 4 rutas |
| `vendor/bin/pint …` y `vendor/bin/pint --test …` | Formatear y comprobar exclusivamente PHP afectado; aprobado. No se limpia estilo ajeno a la tarea |
| `node tests/JavaScript/ProduccionCantidadTest.mjs`, `node tests/JavaScript/SidebarTest.mjs` | Verificar eventos, selección de familia, reset y regresión sidebar; 5 y 7 aprobados |
| `npm run build` | Compilar assets de producción; aprobado. Aviso preexistente de dependencia opcional fontaine |
| `git diff --check` | Comprobar espacios/formato de diff; aprobado tras retirar un espacio final |

El sandbox también bloqueó el socket del primer runner temporal; se repitió con escalamiento. Los runners se detuvieron normalmente y conservan artefactos ficticios en `/tmp/sprint4-*`. No se ejecutaron seeders completos, operaciones destructivas, commit ni push.

## Problemas encontrados

- Las notas históricas describían Torta individual sin cantidad y otras familias sin turno/foto obligatoria. La instrucción actual cambia esos contratos: lote con cantidad, turno en cabecera y foto opcional; se documenta con fecha y sin borrar historia.
- No existía enlace `public/storage`; se creó. Las fotos en este disco son públicas mediante su URL generada.
- La suite convencional omite persistencia sin el runner; se explicita la diferencia.
- El proyecto conserva deuda de formato global ya documentada y catálogos/cabeceras sin UNIQUE. Se formateó el alcance modificado, sin refactor ajeno.

## Pendientes

- Revisión manual en navegador: seleccionar cada familia, registrar con/sin foto, consultar fecha/turno, recuperar errores, limpiar y comprobar escritorio/móvil/teclado.
- Ratificar una regla propia de participantes si el negocio necesita diferenciar Torta/Bocadito.
- Gestión de usuarios, cuentas por turno, administración de productos y ampliación futura de Historial/Dashboard siguen fuera de esta entrega.
- Verificar despliegue/sesiones database y disponibilidad del enlace público de almacenamiento en cada entorno; no se certificaron aquí.

## Recomendaciones

Mantener el bloqueo de categoría en cualquier escritor futuro. Evaluar UNIQUE de sesión únicamente después de revisar duplicados/legacy y acordar la migración correspondiente. Si posteriormente se requieren fotos privadas, cambiar su exposición con un requisito explícito; el alcance actual utiliza almacenamiento Laravel estándar.

Documentación original actualizada en `/home/lukagfv/Documentos/Obsidian Vault/PROYECTO_LARAVEL/`, previa inspección y lectura íntegra, con escalamiento de escritura. Se preserva su historia y no se guarda copia del Vault en el repositorio. Notas afectadas (13):

- `SPRINTS/Sprint 5 - Producción funcional.md`: avance y alcance pendiente.
- `PRODUCCION/Logica de negocio.md`, `PRODUCCION/Pantallas.md`, `PRODUCCION/Requerimientos.md`: contrato funcional e interfaz.
- `LARAVEL/Controllers.md`, `LARAVEL/Requests.md`, `LARAVEL/Services.md`, `LARAVEL/Models.md`, `LARAVEL/Routes.md`: responsabilidades técnicas reales, Actions y endpoints.
- `BASE DE DATOS/Migraciones.md`: evolución y aplicación local.
- `DECISIONES/Decisiones Laravel.md`: fecha, contexto, opciones, decisión, motivo y consecuencias; se utiliza el nombre real existente.
- `INICIO/Estado de proyecto.md`, `INICIO/Roadmap.md`: resumen y siguiente trabajo.
