# Sprint 5 - Cierre

Fecha: 2026-10-11. Repositorio: `fuchsfoxi/panaderia-rs`.

**Estado técnico: IMPLEMENTADO Y COMPROBADO** para Producción de Pan, Torta y
Bocadito e Historial común. La migración nueva está aplicada localmente como
**Ran batch 5**. **Aceptación operativa con usuarios/dispositivos reales:
PENDIENTE**. La evidencia automatizada y su aislamiento se detallan abajo;
no se acredita despliegue ni aceptación del negocio mediante estas pruebas.

## Estado inicial

El checkout comenzó limpio. Se revisaron los dos controllers, los seis Form
Requests, las seis Actions, modelos/relaciones/pivotes, vistas, CSS/JavaScript,
rutas, migraciones, seeders y pruebas relevantes antes de cambiar el flujo.

La implementación existente ya registraba las tres familias. Había separación
HTTP/negocio, transacciones, locks, snapshots de Pan, validación compartida de
participantes y foto opcional de Torta con compensación. No había una razón para
reemplazar esa arquitectura. Historial consultaba exclusivamente DetallePan,
mostraba toda la información inicialmente y anunciaba las otras familias como
«Próximamente». Dashboard ya era funcional para Pan; se conserva ese alcance.

Referencia inicial en MariaDB temporal: **337 aprobadas, 3.442 aserciones**.
JavaScript existente: 12 casos de Producción y 7 de sidebar aprobados.

## Problemas encontrados

1. Historial no consultaba Torta/Bocadito ni ofrecía filtro de familia.
2. Tarjetas saturadas y sin un despliegue de detalles accesible.
3. Autoría individual perdida al reutilizar sesión: el primer usuario quedaba
   en cabecera y no existía autor de detalle. Reproducción añadida antes de
   modificar: dos usuarios/dos lotes, autores esperados distintos; resultado
   NULL/NULL, **1 prueba fallida y 7 aserciones**.
4. Pan copiaba factores de catálogo cero/negativos sin validarlos. NULL sigue
   siendo un valor permitido y representa un factor desconocido.
5. La existencia de empleados solo se comprobaba en el Request; la validación
   de negocio compartida podía llegar al error FK al invocarse sin HTTP.
6. Foto validaba MIME/contenido y tamaño, pero no la extensión del nombre del
   archivo cliente de manera independiente. El nombre almacenado ya era seguro
   y generado por Laravel; se completa el contrato solicitado.
7. Chrome reprodujo en tarjetas de Producción a 320 px un scrollWidth de 321;
   párrafos con mínimo 100 px alcanzaban x=320,55. En móvil los chips registrados
   podían quedar extremadamente estrechos por la distribución lateral.
8. `page` de Historial carecía de validación explícita de tipo/rango.

No se constató N+1 en el Historial previo de Pan: ya precargaba relaciones y
roles de pivotes. La ampliación conserva esa garantía para las tres familias.
No se borraron vistas antiguas ni pruebas sin verificar sus consumidores.

## Cambios realizados

### Backend

| Archivo | Cambio, motivo y efecto |
|---|---|
| `app/Http/Controllers/HistorialController.php` | Delega la consulta a una Query específica y conserva la coordinación Request/vista/errores. Evita meter la consulta de tres tablas en el controller. |
| `app/Queries/ConsultarHistorialProduccion.php` **nuevo** | UNION ALL de identidades/fecha/familia, filtros y paginación SQL de 15 lotes; hidrata solo modelos de la página y precarga relaciones/pivotes. Evita fusionar todos los datos en PHP. |
| `app/Http/Requests/ConsultarHistorialProduccionRequest.php` | Valida categoría y page; conserva GET inválidos sin redirección ni consulta de detalles. |
| `app/Actions/Produccion/RegistrarProduccionPan.php` | Asigna autor autenticado por detalle y rechaza factor conocido <=0. Conserva fórmula, transacción, locks, unidad, turno, hora y snapshot. |
| `app/Actions/Produccion/RegistrarProduccionPorCantidad.php` | Asigna autor de lote para Torta/Bocadito y actualiza comentario de regla confirmada. No duplica escritores ni participantes. |
| `app/Actions/Produccion/ValidarParticipantesProduccion.php` | Comprueba empleados en una consulta ordenada con lockForUpdate dentro de la transacción. Evita depender exclusivamente del Request y mantiene referencias estables hasta attach. |
| `app/Http/Requests/RegistrarProduccionTortaRequest.php` | Añade whitelist de extensión además de MIME/imagen/tamaño. |
| `app/Http/Requests/RegistrarProduccionPorCantidadRequest.php` | Mensaje español para extensión rechazada. |
| `app/Models/DetallePan.php` | Añade fillable de registrador y belongsTo usuario. |
| `app/Models/DetalleTorta.php` | Añade fillable de registrador y belongsTo usuario. |
| `app/Models/DetalleBocadito.php` | Añade fillable de registrador y belongsTo usuario. |
| `database/migrations/2026_10_11_120000_add_registrador_to_production_details.php` **nuevo** | Tres FK nullable hacia usuarios_sistema, sin backfill supuesto; down bloquea antes del primer DROP si hay autores. |
| `app/Http/Controllers/ProduccionController.php` | Precarga usuario de detalle en su consulta para mostrar autor de lote sin N+1. No vuelve a escribir transacciones o reglas. |

### Presentación

| Archivo | Cambio, motivo y efecto |
|---|---|
| `resources/views/history/index.blade.php` | Selector Todos/Pan/Torta/Bocadito y tarjetas breves con details/summary. Presenta datos específicos, foto y autor individual/histórico explícito; todo texto dinámico escapado. |
| `resources/css/historial.css` | Contención de textos, estilos de details/foco/foto y eliminación de estilos sin consumidores confirmados de categoría, meta/foto antigua/enlace antiguo. Conserva la paleta. |
| `resources/views/produccion/index.blade.php` | Muestra registrador de lote si está acreditado. |
| `resources/css/produccion.css` | Quita mínimo de párrafos y apila tarjeta/contenido/chips en móvil. Corrige desbordamiento real sin ocultar overflow. |

### Pruebas y seguimiento

| Archivo | Cambio |
|---|---|
| `tests/Feature/HistorialProduccionTest.php` | Actualiza expectativas legítimas de familias/autoría; añade filtros inválidos, las tres familias, colisiones de IDs, paginación mixta, detalles, históricos, fotos, HTTPS y conteo constante de consultas mixtas. |
| `tests/Feature/ProduccionTortaBocaditoStoreTest.php` | Autor de lote, rechazo de extensión/contenido falso, empleados inexistentes en Action y sesión compartida por dos usuarios. |
| `tests/Feature/RegistrarProduccionPanActionTest.php` | Reproducción/corrección de autoría, empleados inexistentes y factores no positivos. |
| `tests/Feature/ProductionObservationsMigrationTest.php` | Conserva clasificación de 21 originales; amplía evolución DDL para históricos NULL, FK, bloqueo de rollback y down/up de la aditiva nueva. |
| `tests/Feature/ProduccionConcurrenciaTest.php` **nuevo** | Dos procesos/conexiones por familia: una cabecera, dos lotes y cuatro pivotes. Solo base temporal cuya identidad verifica el helper. |
| `tests/Support/registrar-produccion-concurrente.php` **nuevo** | Worker exclusivo del test; exige conexión desechable validada antes de ejecutar la Action. |
| `mejora_asignado.md` | Seguimiento del cierre y límites, preservando historia. |
| `SPRINT_5_CIERRE_CODEX.md` **nuevo** | Este informe y explicación para aprendizaje, solicitado expresamente. |

No cambian rutas, JavaScript de aplicación, dependencias, Dashboard, autenticación,
seeders o migraciones anteriores. Se reutilizan RegistrarProduccionTorta,
RegistrarProduccionBocadito y ResolverCabeceraProduccion sin reemplazarlos.

## Producción Pan

- Producto existente, activo y de Pan revalidado con catálogo real.
- Fecha/turno/hora estructuralmente validados por el Form Request.
- Fuente de turno: `produccion.turno_id`; la Action copia automáticamente ese
  valor a `detalle_pan.turno_id` en cada registro nuevo. La lectura histórica
  usa la cabecera aun si un detalle legacy es incoherente.
- Cantidad canónica: coches × `DetallePan::LATAS_POR_COCHE` + latas adicionales;
  total entero positivo dentro de INT. Se conserva la constante vigente 18,
  sin duplicarla en JavaScript ni resolver por suposición la discrepancia 12/18
  de las notas antiguas.
- Coches/latas vacíos legítimos siguen normalizados a cero; ambos cero se rechazan.
- Unidad deriva del producto. No se aceptan unidad, turno de detalle, cantidad
  calculada ni snapshot enviados como sustitutos por el cliente.
- Snapshot copia factor vigente positivo o NULL; editar el producto no altera
  un lote anterior. Un factor conocido cero/negativo impide registro.
- Hora real HH:MM introducida manualmente, observación y participantes preservados.
- Autor individual tomado de auth, aunque se reutilice cabecera de otra cuenta.
- Rollback SQL y registros simultáneos comprobados en MariaDB aislada.

No se añade un evento de modelo para reparar cualquier modificación SQL legacy:
la sincronización probada corresponde al escritor de producción actual. Edición
histórica queda fuera del alcance.

## Producción Torta

Registro de fecha, turno, producto activo de Torta, cantidad positiva, forma libre
máximo 30 caracteres, observación, equipo y registrador autenticado del lote.
Fotografía opcional:

1. Request exige archivo/imagen, MIME JPEG/PNG/WebP, extensión jpg/jpeg/png/webp
   y máximo 2.048 KB. SVG, texto, ruta cliente, exceso y contenido falso rechazados.
2. RegistrarProduccionTorta delega transacción a RegistrarProduccionPorCantidad.
3. Su callback genera nombre mediante `hashName()` y almacena con
   `Storage::disk('public')->putFileAs()` bajo `produccion/tortas/`.
4. Se guarda solo la ruta generada. Un fallo revierte SQL y compensa eliminando
   el archivo nuevo. Un falso retorno al escribir también impide guardar.
5. El archivo de un lote fallido se elimina; lotes anteriores se conservan.

La vista usa `asset('storage/'.$ruta)` para el disco public local existente y
mantener host/esquema de la petición, incluidos proxies HTTPS. No se cambió
APP_URL. Se conserva el enlace de almacenamiento local existente; no se creó
uno adicional. Filesystem/DB no constituyen una transacción física común.

## Producción Bocadito

Registro de fecha, turno, producto activo y realmente de Bocadito, cantidad
entera positiva, observación, participantes y registrador autenticado por lote.
Reutiliza PorCantidad, ResolverCabeceraProduccion y ValidarParticipantesProduccion;
no recibe reglas de Pan ni foto/forma de Torta por la mera presencia de campos.

## Historial

Cada resultado es **un lote**, no una cabecera. Todas las familias funcionan
con fechas inicial/final inclusivas, turno de cabecera y categoría opcionales;
valor vacío de categoría significa Todos. Productos inactivos siguen visibles
para conservar su historia. No se inventa clasificación de cabeceras legacy NULL.

La Query une en SQL únicamente ID local, fecha y familia de tablas compatibles
con la categoría de cabecera. SQL cuenta/ordena/pagina; solo 15 detalles se
hidratan en PHP. Después carga modelos por familia, producto, cabecera/turno,
usuarios, empleados y unidad solo en Pan. Los roles de los custom Pivots se
cargan en lote, conservando los objetos Pivot existentes.

La clave `familia:ID` evita perder registros cuando Pan/Torta/Bocadito tienen el
mismo ID. Orden: fecha DESC, ID local DESC, familia ASC para empate. No hay
created_at común: este orden es estable, no acredita cronología real entre tablas.
El conteo de consultas permanece constante cuando crece una página con las mismas
familias. No se certifica tiempo de respuesta con millones de filas.

Tarjeta cerrada: familia, producto, fecha, turno, cantidad y Maestro. `details`
nativo expande datos secundarios; funciona con teclado y sin JavaScript/librería.
Pan muestra coches/resto/total/unidad/snapshot/estimación si corresponde/hora;
Torta forma/foto; todas Ayudantes, observación y autor. Para autor histórico NULL
se muestra «desconocido» y creador de sesión separado, sin atribución inventada.

## Validaciones

- Exactamente **1 Maestro y uno o más Ayudantes** en las tres familias. Confirmado
  por la instrucción actual del usuario; comentarios/documentación provisionales
  anteriores se conservan como antecedentes, no como regla vigente.
- No empleados repetidos, roles ajenos a Maestro/Ayudante ni referencias inexistentes.
- Request comprueba estructura/tipos/fechas/referencias. Action comprueba producto
  activo/familia, equipo, factor/cantidades y cabecera dentro de transacción.
- IDs de categorías/turnos/unidades/roles no son identidades hardcodeadas.
- Usuario cliente no decide el autor: se pasa el identificador de auth por separado.
- Filtros malformados, rangos invertidos o páginas inválidas no consultan detalles.
- `auth` sigue protegiendo los tres registros y consultas; validación JavaScript
  no sustituye al servidor. No se inventa política de autorización por rol.

## Arquitectura

```text
Formulario Blade / interacción JS
    ↓ POST autenticado
Form Request específico
    ↓ datos estructuralmente válidos
ProduccionController
    ↓ datos + ID del usuario autenticado
RegistrarProduccionPan / RegistrarProduccionTorta / RegistrarProduccionBocadito
    ↓ reglas, locks y transacción (PorCantidad compartida para Torta/Bocadito)
ValidarParticipantesProduccion + ResolverCabeceraProduccion
    ↓ cabecera reutilizada o nueva
Detalle y custom Pivots mediante relaciones Eloquent
    ↓
MariaDB → commit → redirect de éxito → consulta / Historial
```

Antes, la cuenta vivía únicamente en cabecera. Ahora se conserva ese vínculo y
se añade cuenta en detalle: sesión del usuario A puede contener lote A y lote B
registrado por B sin falsificar ninguno. La migración es nullable porque no se
puede reconstruir el autor histórico a partir del creador de sesión.

La estructura validada del Request no equivale a confianza en el producto:
la Action vuelve a leerlo bajo lock y comprueba su familia/actividad. Esto evita
que cambiar un ID o desactivar un producto entre validación y escritura permita
un registro incorrecto. La validación compartida de participantes evita tres
copias de una misma regla. Las FK y PK compuestas constituyen otra defensa de
integridad; no sustituyen los mensajes/reglas de negocio.

Lectura: ConsultarHistorialProduccionRequest → HistorialController → Query
específica → modelos precargados de página → Blade. Una clase Query no escribe
producción ni crea un Repository genérico; concentra la nueva lectura compleja.

## Pruebas

Comandos y resultados ejecutados:

| Comando / etapa | Resultado |
|---|---|
| `php tests/run-mariadb.php --compact --do-not-cache-result` referencia inicial | 337 aprobadas / 3.442 aserciones. El primer intento sandbox no pudo iniciar socket; se usó escalamiento. |
| `PAO_DISABLE=1 php tests/run-mariadb.php --filter='la Action reutiliza cabecera conserva autor' --do-not-cache-result` antes del fix | 1 fallida / 7 aserciones; confirmó falta de autor por detalle. |
| Primera regresión Historial/Action/PorCantidad | 137 aprobadas / 1 fallida / 1.112 aserciones; fixture de contenido falso infería MIME desde el nombre. Se corrigió usando UploadedFile real, sin relajar reglas. |
| Segunda regresión con migración/concurrencia | 142 aprobadas / 3 fallidas / 1.303 aserciones; cierre duplicado de pipes en el test concurrente. Se corrigió el test. |
| `PAO_DISABLE=1 php tests/run-mariadb.php --filter='ProduccionConcurrenciaTest' --compact --do-not-cache-result` | 3 aprobadas / 33 aserciones, dos procesos independientes por familia. |
| Primera suite completa tras cambios | 358 aprobadas / 3.664 aserciones. |
| `PAO_DISABLE=1 php tests/run-mariadb.php --filter='HistorialProduccionTest' --compact --do-not-cache-result` tras añadir regresión foto HTTPS | 33 aprobadas / 298 aserciones. |
| **`PAO_DISABLE=1 php tests/run-mariadb.php --compact --do-not-cache-result` final** | **359 aprobadas / 3.667 aserciones / 0 fallidas / 0 omitidas**, 18,82 s. |
| `node tests/JavaScript/ProduccionCantidadTest.mjs` | 12 aprobadas / 0 fallidas. |
| `node tests/JavaScript/SidebarTest.mjs` | 7 aprobadas / 0 fallidas. |
| `npm run build` | Aprobado; aviso opcional fontaine preexistente. |
| `PAO_DISABLE=1 vendor/bin/pint --dirty --test` | Aprobado para PHP afectado, incluidos archivos nuevos. No se afirma limpieza global del repo. |
| `git diff --check` y revisión de diff | Aprobados. |
| `PAO_DISABLE=1 APP_ENV=testing php /tmp/sprint5-render.php` | Renderiza Blade con fixtures en memoria, sin consultar BD del proyecto. |
| `node /tmp/sprint5-browser-check.mjs` final | 20/20 combinaciones correctas; sin overflow ni excepciones JS, details por teclado y carga real de imagen/preview. |

PAO_DISABLE solo permite obtener salida de prueba convencional; no cambia reglas
ni configuración de la aplicación. El runner anterior suprimió parte de la salida
redirigida; la referencia se confirmó con salida visible. PHPUnit avisa que
--do-not-cache-result quedará obsoleto: conservar --do-not-record-test-run-history
como futura actualización del comando, sin modificar dependencias ahora.

Cobertura relevante existente conservada: registro y cantidades Pan, turno/unidad,
snapshot, participantes, errores, auth, PanUnits, modelos/relaciones, migraciones,
Torta/Bocadito/fotos y rollback, navegación/login/logout/proxy y Dashboard.
Se añaden **22 casos PHP** a la referencia inicial, más ampliaciones de aserciones.
No se eliminaron tests para aprobar la suite.

### Aislamiento y límites

- Runner: instancia MariaDB desechable bajo /tmp, sin red, dos bases ficticias,
  datadir/base comprobados por IsolatedMariaDb antes de migrar. Concurrencia usa
  migrate:fresh **solo en esa base ficticia verificada**; nunca en la base local.
- HTTP: sesiones array y withoutVite. Assets compilados comprobados aparte.
- Chrome headless: vistas Blade renderizadas con modelos en memoria, servidor
  estático loopback GET/HEAD y perfil temporal; no se enviaron formularios ni
  credenciales. El guardado HTTP/DB se acredita mediante Pest, por separado.
- Responsive: Pan, Torta, Bocadito e Historial a 320, 375, 768, 1024 y 1440 px.
  Revisión incluye campos, imágenes, participantes, popovers, tarjetas y sidebar
  integrado. Detalles expandido con Enter; captura final móvil/escritorio revisada.
- Fuentes externas bloqueadas para evitar espera de red; geometría/capturas con
  fallback. No certifica Huninn cargado, celular físico, Safari, teclado virtual,
  lector de pantalla, captura real de cámara ni túnel Cloudflare operativo.
- Fotos de pruebas: Storage fake para almacenamiento/compensación y PNG ficticio
  servido a Chrome; no se usaron fotografías de negocio.
- El comando de prueba convencional sin runner omite persistencia; no se usa
  como evidencia equivalente. PDO SQLite no está instalado en este entorno.
- La base local recibió solo la nueva migración aditiva; no se crearon lotes,
  empleados, productos, usuarios o fotos reales. No se leyó/imprimió .env.

Evidencia temporal: `/tmp/sprint5-final-tests2.log`, `/tmp/sprint5-history-final.log`,
`/tmp/sprint5-concurrency.log`, `/tmp/sprint5-build.log` y
`/tmp/sprint5-browser/metrics.json`, más capturas de las cuatro vistas en 375/1440.
Los scripts temporales de navegador son auxiliares de verificación, no una nueva
suite frontend/dependencia del producto.

### Aplicación del esquema local

Secuencia efectivamente ejecutada con escalamiento para conexión:

```bash
php artisan migrate:status --no-ansi
php artisan migrate --path=database/migrations/2026_10_11_120000_add_registrador_to_production_details.php --pretend --no-ansi
php artisan migrate --path=database/migrations/2026_10_11_120000_add_registrador_to_production_details.php --no-ansi
php artisan migrate:status --no-ansi
```

26 migraciones anteriores Ran; única nueva pendiente → SQL revisado de tres
columnas/tres FK → DONE → **27 migraciones Ran, nueva batch 5**. Histórico no
rellenado. No se ejecutaron seeders ni otras migraciones/reversiones locales.
La migración debe aplicarse también al desplegar este checkout en otro entorno;
la ejecución local no certifica un despliegue externo.

## Documentación original

Se inspeccionó estructura, buscó el tema y leyó cada nota antes de editar.
Se integran actualizaciones fechadas en **16 notas originales**:

- `LARAVEL/Controllers.md`, `Requests.md`, `Services.md`, `Models.md`, `Blade.md`:
  responsabilidades técnicas, datos/validación/query y presentación reales.
- `BASE DE DATOS/Migraciones.md`, `Relaciones.md`: tres FK nullable, conservación,
  reversión protegida y aplicación local comprobada.
- `PRODUCCION/Logica de negocio.md`, `Flujo de produccion.md`, `Pantallas.md`,
  `Requerimientos.md`: contrato vigente confirmado, flujo/UI y alcance funcional.
- `SPRINTS/Sprint 5 - Producción funcional.md`: cierre técnico y aceptación pendiente.
- `INICIO/Estado de proyecto.md`, `Roadmap.md`: resumen y próximo alcance de administración.
- `DECISIONES/Decisiones Laravel.md`: fecha/contexto/opciones/decisión/motivo/consecuencias.
  Ese es el nombre real encontrado; no se creó una variante .md.md.
- `PENDIENTES/Bugs.md`: entradas únicas 14 autoría, 15 tarjetas móviles y 16 factor
  no positivo; cada una reúne investigación/corrección/evidencia/límites.

Raíz original: `/home/lukagfv/Documentos/Obsidian Vault/PROYECTO_LARAVEL/`.
No se renombra/reorganiza/duplica el Vault. Historia inferior preservada; este
informe independiente se crea por instrucción explícita del usuario.

## Riesgos pendientes

1. **PENDIENTE:** aceptación operativa real y dispositivos/Huninn/Safari/cámara/
   lector de pantalla. Lo probado está delimitado; no declarar producción externa
   certificada por un test aislado.
2. **PENDIENTE:** validación de capacidad histórica 12/18 y catálogo real de factores
   si el negocio cambia el canon. Se conserva 18 vigente, sin inventar unidades/IDs.
3. La compensación de archivo cubre excepciones comprobadas; caída del proceso o
   fallo del propio borrado puede dejar archivo huérfano. La Action ya reporta
   error de borrado; recuperación operativa futura requerirá política explícita.
4. Locks de categoría serializan escritores actuales; no hay UNIQUE SQL de sesión.
   Futuros escritores deben respetar el mismo protocolo. Dos conexiones simultáneas
   actuales por familia están comprobadas; no certifica carga masiva/distribuida.
5. Histórico sin categoría o con tabla/familia incompatible no se clasifica
   automáticamente. Autor/hora/factor desconocidos no se inventan. Planificar
   saneamiento únicamente con evidencia real y autorización específica.
6. Query de historial mantiene memoria limitada a la página. Evaluar índices/plan
   y tiempo de respuesta si el volumen real lo exige; no se midió una base masiva.
7. Sesiones database, permisos por rol y administración quedan para alcance posterior.
   Autenticación actual sí se conserva y prueba; nombres de roles no conceden permisos.
8. Dashboard continúa centrado en Pan e identifica al creador de sesión de forma
   explícita. Ampliación de métricas/autor individual se evaluará en otra tarea.
9. Migración multitabla aditiva y down protegido no implican DDL atómico en MariaDB.
   Aplicaciones/rollback posteriores requieren ventana sin escritores concurrentes.

## Próximo Sprint recomendado

**Sprint 6: administración**, sin implementación en esta tarea:

- Acordar matriz de acceso y política de usuarios; después CRUD de usuarios/empleados.
- Administración de productos/categorías, activación, unidades y factores positivos.
- Validar catálogos operativos con el negocio y mantener trazabilidad histórica.
- Probar permisos/validaciones y ampliar documentación funcional/técnica.

Pedidos, edición/anulación, Stock, rediseño total de Dashboard o nuevas tecnologías
frontend requieren un alcance posterior explícito.
