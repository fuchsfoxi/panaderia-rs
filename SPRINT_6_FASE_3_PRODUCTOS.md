# Sprint 6 — Fase 3
## Administración de Productos

Fecha: 2026-10-12. Repositorio: `fuchsfoxi/panaderia-rs`. Checkout inicial: `master`, `8595a11 feat: implementar autorizacion administrador y operador`.

**Estado: IMPLEMENTADA Y COMPROBADA con HTTP/MariaDB aislada y Chrome headless.** Aceptación operativa en dispositivos reales y persistencia de sesiones `database`: **PENDIENTES**. Sin commit ni push.

### Estado inicial

`git status` confirmó checkout limpio y actualizado con `origin/master`; HEAD y remoto coincidieron con la solicitud. Se leyeron íntegramente Fase 1, Fase 2 y `AGENTS.md`. Fase 1 describe una auditoría histórica sin autorización; el código vigente ya contiene los Gates y rutas de Fase 2. `getAuthPasswordName()` ya existe y su regresión pasó: no se volvió a implementar ni se presenta como pendiente del checkout.

Producto tenía modelo, relaciones, activo y factor/snapshot, pero no controller, Requests, pantalla ni rutas administrativas. Reproducción HTTP previa en MariaDB temporal: `Administrador abre el módulo Productos` esperaba 200 y recibió **404; 1 prueba fallida, 2 aserciones**. No era un fallo de Gates: el módulo todavía no existía.

### Auditoría de Producto

Se revisaron Producto, Categoria, UnidadMedida, los tres Detalle*, Produccion, migraciones original/aditiva de factor, ProduccionDesarrolloSeeder, catálogos, ProduccionController, Requests y las seis Actions de Producción, Query/vista de Historial, vista/JS de Producción y pruebas de relaciones, unidades, guardado, historial, navegación/autorización y concurrencia.

| Campo real | Significado y uso comprobados |
|---|---|
| `nombre_p` | VARCHAR(100) obligatorio. Se utiliza en opciones/listados de Producción y mediante la relación `producto` en Historial. No hay snapshot del nombre. |
| `temporada_fe` | DATE nullable, fillable, sin cast específico ni consumidor funcional. El seeder de desarrollo lo deja NULL. No hay regla de temporada aprobada. |
| `activo` | TINYINT(1), default 1, cast boolean. ProduccionController filtra activos y las Actions revalidan el estado bajo lock. Historial no filtra por activo. |
| `categoria_id` | FK obligatoria a categorias. Clasifica el producto; Pan/Torta/Bocadito se resuelven por catálogo y nombre, sin IDs asumidos. |
| `unidad_medida_id` | FK obligatoria a unidades_medida. Pan copia la unidad a detalle_pan; Torta/Bocadito no tienen una FK equivalente en su detalle, pero Producto sigue requiriendo unidad. |
| `panes_por_lata` | INT firmado nullable. Parámetro de Pan; el escritor copia su valor al crear `detalle_pan.panes_por_lata_usado`. |

**Esquema real comprobado en lectura:** un script temporal usó `SET TRANSACTION READ ONLY`, consultó columnas/índices y catálogos, contó grupos de nombres repetidos y terminó con rollback. Coincide con las migraciones: **cero grupos duplicados**, sin UNIQUE de nombre; categorías Pan/Torta/Bocadito; única unidad disponible **Lata**. Esta unidad procede del contexto provisional de desarrollo y no se codifica como identidad definitiva. No se leyeron usuarios, credenciales, hashes o sesiones ni se imprimió el contenido de `.env`.

El sandbox bloqueó inicialmente la conexión (código 2002); la lectura se completó con escalamiento autorizado. No hubo escrituras, seeders o migraciones en la base operativa. **No se necesita ni se creó una migración.**

### Reglas confirmadas

- Solo Administrador administra Productos. Operador recibe 403 con payload válido, incluso con CSRF real válido; no ve el enlace.
- Nombre requerido, texto de hasta 100 caracteres, espacios Unicode normalizados, sin caracteres de control. Se conservan acentos, puntuación y mayúsculas.
- **Se permiten nombres repetidos tanto al crear como al editar.** No existe una regla de unicidad solicitada ni UNIQUE; no se añadió una restricción de aplicación parcial o una migración. La identidad permanece en el ID del registro. Una futura regla de unicidad necesita acuerdo/auditoría; hoy no se promete exclusión de duplicados, concurrentes o secuenciales.
- Categoría y unidad reales, obligatorias; crear/editar utiliza exclusivamente las familias funcionales Pan/Torta/Bocadito.
- Pan admite NULL o entero entre 1 y **2147483647**, límite técnico de INT, no máximo operativo inventado. Torta/Bocadito deben guardar NULL; un factor enviado deliberadamente se rechaza con validación.
- Sin uso en ninguno de los tres detalles, pueden cambiar categoría/unidad. Con uso, se bloquean ambas modificaciones, aunque cambie únicamente una.
- Nombre, factor futuro de Pan y estado siguen editables con historia. No se actualizan lotes ni snapshots.
- Crear usa el default activo de la BD. Estado se asigna explícitamente por su endpoint; no se realiza toggle ciego ni borrado físico.

### Arquitectura implementada

```text
Route de Productos
→ web (sesión / CSRF)
→ auth
→ middleware nativo can:administrar-sistema → Gate existente
→ Form Request (tipos, estructura y referencias)
→ ProductoController
→ Producto::create o ActualizarProducto::ejecutar
→ MariaDB
→ redirect con mensaje / errores
→ GET y vista Blade
```

El GET de listado recorre Request → controller → consulta Eloquent paginada → Blade. El GET crear/editar prepara catálogos y valores; todavía no guarda. La validación HTTP y la regla histórica tienen responsabilidades distintas: un ID puede existir y aun así estar prohibido cambiarlo por su historia.

### Controller

`ProductoController` existe exclusivamente para administración, sin añadir métodos a ProduccionController. Las rutas lo llaman; recibe Requests validados, Producto por binding y la Action inyectada. Devuelve `View` para leer y `RedirectResponse` para mutar.

`index` aplica búsqueda parametrizada, categoría/estado, orden nombre/ID, `with(['categoria','unidadMedida'])` y 15 productos por página. `%`, `_` y `!` se escapan para búsqueda literal. Los GET inválidos muestran errores y una lista vacía sin bucle de redirects; páginas/old input malformados se reducen a valores seguros antes de HTML. `create/edit` preparan catálogos y protección visual histórica. `store` crea con datos validados. `update/estado` delegan en la Action. Los errores SQL se reportan y muestran un aviso genérico con input conservado, sin exponer consultas en la pantalla.

### Requests

| Clase nueva | Quién la utiliza y qué recibe | Qué produce / problema que evita |
|---|---|---|
| `GuardarProductoRequest` — abstracta | Base de creación y edición; campos del formulario | Normaliza nombre y valida referencias/factor con la misma regla para ambas entradas. `after()` verifica familia contra Categoria real. Evita reglas divergentes, factores de otra familia y atributos extra persistidos. |
| `CrearProductoRequest` | Laravel al entrar a `store` con POST | Contrato específico de creación usando la validación común. Entrega `validated()`; no valida ni persiste estado/temporada enviados como extras. |
| `ActualizarProductoRequest` | Laravel al entrar a `update` con PUT/PATCH | Mismo contrato de campos, sin duplicar reglas. La historia se revalida después en la Action, no se decide únicamente al abrir el formulario. |
| `CambiarEstadoProductoRequest` | Laravel en PATCH estado | Exige `activo` boolean. Controller convierte el estado validado y envía únicamente ese campo. Evita estados arbitrarios y mutaciones laterales de nombre/categoría. |
| `ConsultarProductosRequest` | Laravel al entrar a `index`; query string | Valida q/categoría/estado/page, devuelve filtros escalares, página segura, errores y bandera de validez. Evita arrays/rangos inválidos y loops por GET malformados. |

`authorize()` mantiene la responsabilidad de permisos en el middleware `can` de todas las rutas. No es un bypass del Gate. Laravel rechaza errores de mutación con redirect/old input en HTML o 422 en JSON. Los rechazos de autorización ocurren antes de validar estos payloads.

### Actions/Services si se usaron

`App\Actions\Productos\ActualizarProducto` es la única Action administrativa nueva. ProductoController la llama para editar o asignar estado. Recibe ID y array limitado a datos ya validados; devuelve el Producto actualizado o lanza una ValidationException con mensaje de negocio. No existe un Service/Repository genérico nuevo ni se modifica `app/Actions/Produccion`.

Dentro de la transacción identifica categoría actual/destino, bloquea ambas por ID ordenado con `sharedLock`, recupera Producto con `lockForUpdate` y comprueba que el contexto de categoría no cambió a una tercera categoría sin bloquear. Si cambió, pide volver a abrir el formulario; si desapareció el destino, lo rechaza. No adquiere nuevas categorías después de bloquear el producto.

Solo cuando se intenta cambiar categoría/unidad consulta los tres detalles con **lecturas actuales bajo lock**, conservando la protección aun si una lectura anterior abrió un snapshot REPEATABLE READ. Si existe historia, lanza errores de campo y no escribe ni siquiera el nombre enviado junto con el cambio prohibido. Si pasa, actualiza únicamente atributos admitidos. La transacción permite tres intentos para errores de concurrencia que Laravel reconoce.

**Aprendizaje de concurrencia comprobado:** la primera versión bloqueaba solo Producto. El caso inverso, edición primero y Producción esperando, reprodujo error MariaDB **1020, Record has changed since last read** al releer el producto. La solución administrativa respeta el orden existente **categoría → producto**: el lock compartido de categoría hace esperar a Producción antes de leer Producto, y también evita cruzar el orden de FK/locks al cambiar familia. Los escritores operativos se conservaron. Las 18 carreras finales prueban ambos órdenes para categoría, unidad y desactivación, en Pan/Torta/Bocadito.

Los workers se sincronizan cuando la primera conexión ya adquirió locks; el test observa la segunda consulta bloqueante en PROCESSLIST antes de liberarlos. MariaDB puede mostrar `Statistics` antes de registrar una trx de InnoDB: por eso no se deduce ausencia de espera de `INNODB_TRX.trx_state`. Se comprueba el resultado persistido tras liberar la primera conexión, no solo que ambos procesos arrancaron.

### Rutas

| Método | URL | Nombre | Controller |
|---|---|---|---|
| GET/HEAD | `/productos` | `productos.index` | index |
| GET/HEAD | `/productos/crear` | `productos.create` | create |
| POST | `/productos` | `productos.store` | store |
| GET/HEAD | `/productos/{producto}/editar` | `productos.edit` | edit |
| PUT/PATCH | `/productos/{producto}` | `productos.update` | update |
| PATCH | `/productos/{producto}/estado` | `productos.estado` | estado |

No existe DELETE. Binding mantiene la respuesta de recurso inexistente; las pruebas de autorización usan registros existentes. `route:list -vv --except-vendor` confirma **16 rutas de aplicación**, incluidas las seis nuevas, todas con `auth` y `can:administrar-sistema`. Rutas/nombres de Producción, Dashboard, Historial, login y logout conservados.

### Autorización

Se reutiliza el Gate existente, sin tocar Rol, Usuario, AppServiceProvider o bootstrap. Administrador lista, crea, edita, activa y desactiva mediante HTTP. Operador recibe 403 HTML/JSON en los GET y POST/PUT/PATCH, sin cambios persistidos; tres mutaciones también se comprueban con el bypass de CSRF de testing desactivado y una protección válida. Invitado recibe 302 login HTML / 401 JSON; CSRF sigue teniendo su responsabilidad independiente en el stack web.

No se crean tablas de permisos, Policies, roles, cuentas por turno, paquetes externos o permisos por IDs. `acceder-operacion` y logout conservan su comportamiento.

### Vista

`productos/layout.blade.php` comparte documento, sidebar, encabezado, flash y resumen de errores entre las dos pantallas del módulo. No refactoriza los documentos de otras pantallas. `index.blade.php` usa filas compactas dentro de un contenedor común, categoría/unidad, estado textual y factor de Pan; acciones y formularios semánticos. `form.blade.php` sirve crear/editar con labels, errores asociados, ayuda contextual y controles adecuados. CSS conserva la paleta orgánica y estilo existentes; sin framework nuevo.

### Crear Producto

Formulario separado en `/productos/crear`; nombre, categoría real, unidad real y factor opcional. Catálogos vacíos muestran aviso y deshabilitan Guardar. POST crea un producto activo y redirige al listado con flash. Valores extra `id`, `activo` y `temporada_fe` no se guardan desde esta entrada. Funciona sin JavaScript; las ayudas explican cuándo el factor debe estar vacío.

### Editar Producto

Formulario por binding, valores actuales/old input saneados, mismo límite de nombre/referencias/factor. Con historia, categoría/unidad aparecen deshabilitadas y sus valores actuales se envían mediante hidden; esto es UX, la protección real sigue en la Action. PUT y PATCH comparten endpoint. Cambiar nombre informa que también cambiará su presentación histórica. Tras éxito vuelve al formulario actualizado.

### Activar / Desactivar

Acciones del listado con POST y spoofing PATCH, CSRF y estado deseado explícito 0/1. Usan la misma Action con locks, preservan filtros/página al regresar y son idempotentes: repetir Desactivar no reactiva. No cambian otras propiedades ni eliminan relaciones. Desactivar antes de una nueva producción provoca rechazo; desactivar después conserva el lote que ya se registró. Reactivar vuelve a permitir registro.

### Categoría

Se usa la identidad del catálogo, con nombres funcionales para el formulario y validación. Sin IDs fijos. Una categoría existente ajena a las tres familias se rechaza al guardar; el listado puede mostrar/filtrar catálogos heredados para inspección. Sin CRUD de categorías.

### Unidad de medida

Seleccionada entre registros reales, obligatoria para las tres familias. No se presupone Lata, no se crea otra unidad ni se ofrece CRUD. Las pruebas usan unidades ficticias con IDs dinámicos y prueban cambio de unidad sin historia. Con historia en cualquiera de las familias se bloquea su cambio estructural.

### Panes por lata

NULL representa desconocido. Pan admite entero positivo dentro de INT; se rechazan cero, negativos, decimales, floats JSON, booleanos, arrays, notación científica y overflow. Para Torta/Bocadito, factor no nulo enviado manualmente produce 422; nunca se conserva un factor escondido al cambiar de Pan a esas familias sin historia.

`productos.js` solo ayuda a presentar el campo: lee el nombre de la categoría seleccionada, muestra/habilita el input para Pan y lo oculta/deshabilita en otras opciones. Su valor no se envía para otra familia. Sin JS el campo permanece disponible con ayuda explícita y toda la validación sigue en backend. No filtra catálogos en cliente ni transforma la pantalla en SPA.

### Protección del histórico

**Categoría/unidad:** uso en cualquiera de los tres detalles bloquea ambas modificaciones. Se consulta otra vez al escribir, dentro de transacción y bajo el protocolo compatible con Producción. Si Producción gana la carrera, edición estructural se rechaza; si edición de categoría gana antes del primer lote, un registro enviado para la familia anterior se rechaza; si gana cambio de unidad, Pan registra la unidad nueva coherente.

**Snapshot:** prueba HTTP registra Pan con factor 20, cambia Producto a 24 y registra otro lote. Persistencia final **[20, 24]**; el detalle previo conserva exactamente sus atributos. Historial muestra ambos factores y el nuevo nombre. No se actualiza `detalle_pan.panes_por_lata_usado` ni se migra el histórico.

**Nombre:** Historial lee `detalle->producto->nombre_p`, por lo que renombrar el producto cambia el nombre visible de producciones anteriores. No hay snapshot de nombre nuevo ni se promete identidad nominal histórica congelada.

**Inactividad:** Historial conserva Producto y relaciones. Las tres familias prueban desactivación → opción ausente → ID manipulado rechazado → historia intacta → reactivación y nuevo lote. No se debilitó ningún Request/Action de Producción.

### Temporada

La búsqueda global solo encontró fillable, migración, seeder de desarrollo y documentación/prueba que conserva ausencia de cast. `temporada_fe` no participa en consultas, UI, activación automática o cálculos. Se conserva en esquema/modelo y una edición administrativa preserva su valor aunque se envíe un campo extra diferente. No se muestra en el formulario. Semántica y automatismo de temporada: **PENDIENTES**, sin inventar reglas de Panetón/Turrón.

### Sidebar

Nuevo enlace real Productos bajo `@can('administrar-sistema')`, con SVG decorativo, `routeIs('productos.*')` y `aria-current`. Administrador: cuatro módulos. Operador: tres. Sin enlaces muertos a Usuarios/Empleados/Configuración. La regresión de Fase 2 se adaptó a este nuevo comportamiento sin eliminar sus verificaciones de acceso.

### Responsive

Chrome headless con assets reales de Vite y vistas Blade con fixtures en memoria: **35/35 combinaciones** = siete vistas/estados (listado, crear, editar Pan histórico, Torta, Bocadito, errores, catálogo vacío) × **320/375/768/1024/1440 px**. Sin scroll horizontal ni controles saliendo del viewport; incluye nombre continuo de 100 caracteres, texto largo y paginación. Se inspeccionaron capturas de listado 375/1440 y formulario 375 en `/tmp/sprint6-productos-preview/`.

Cuatro cambios de categoría comprobaron visibilidad, disabled y `FormData`; foco visible con Tab, menú móvil/Escape y fallback sin JS a 320 px comprobados. Labels y referencias `aria-describedby`/`aria-invalid` también se validaron por DOM/HTTP con errores reales. Estados se expresan con texto además del color. No hay modal o nueva biblioteca.

Aislamiento visual: servidor estático loopback, sin POST, lectura SQL bloqueada en el renderer y fuentes externas bloqueadas en la matriz; medidas con fallback, sin certificar Huninn, Safari, celular físico o lector de pantalla. El primer verificador detectó únicamente un favicon no montado en la herramienta temporal; se enlazó el favicon existente, se repitió la matriz y terminó con **cero errores JS/assets**. No se modificó la aplicación por ese artefacto.

### Pruebas

| Comando ejecutado | Resultado exacto |
|---|---|
| `php tests/run-mariadb.php --compact --do-not-cache-result --filter='Administrador abre el módulo Productos'` — antes del módulo | 1 fallida, 2 aserciones; 404 en lugar de 200. |
| `php tests/run-mariadb.php --compact --do-not-cache-result --filter=ProductosTest` — primera validación | 106 pruebas: 97 aprobadas, 9 fallidas, 603 aserciones. Se corrigieron serialización JSON del test, snapshots de atributos recargados y conservación de cookie de sesión; no se relajó validación. |
| `php tests/run-mariadb.php --compact --do-not-cache-result tests/Feature/ProductosTest.php tests/Feature/ProductosConcurrenciaTest.php` — primera concurrencia | 118 pruebas: 106 aprobadas, 12 fallidas, 682 aserciones; observador de espera InnoDB no representaba el estado Statistics. |
| `php tests/run-mariadb.php --compact --do-not-cache-result tests/Feature/ProductosConcurrenciaTest.php` — con producto como único lock | 12 pruebas: 6 aprobadas, 6 errores, 114 aserciones. El worker inverso no devolvía JSON por error SQL 1020; sondeo individual confirmó el error real antes de corregir el orden de locks. |
| Mismo comando — orden categoría → producto corregido | **12/12 aprobadas, 169 aserciones**, salida 0, antes de añadir los seis casos de estado. |
| `php tests/run-mariadb.php --compact --do-not-cache-result` — final | **566/566 aprobadas, 5.267 aserciones, 0 fallidas/errores/omitidas**, salida 0. Incluye **127 casos nuevos: 109 funcionales y 18 carreras reales**. |
| `node --test-reporter=spec tests/JavaScript/ProduccionCantidadTest.mjs` | **12/12**, 0 fallidas/omitidas, salida 0. |
| `node --test-reporter=spec tests/JavaScript/SidebarTest.mjs` | **7/7**, 0 fallidas/omitidas, salida 0. |
| `npm run build` | Salida 0; incluye productos.css/productos.js. Aviso fontaine opcional preexistente, sin instalar paquetes. |
| `vendor/bin/pint --dirty --test` | Aprobado, salida 0. |
| `vendor/bin/pint --test` con los once PHP nuevos de aplicación/pruebas | Aprobado, salida 0; revisión explícita porque están sin seguimiento. |
| `git diff --check` y diff sin índice de archivos nuevos | Sin errores de espacios. |
| `php artisan route:list -vv --except-vendor` | 16 rutas; seis administrativas con auth/can; ninguna DELETE. |
| `php /tmp/sprint6_productos_render.php` + `node /tmp/sprint6_productos_browser.cjs` | Siete vistas sin SQL; responsive **35/35**, estados JS/teclado/sidebar/fallback correctos, salida 0. Herramientas temporales fuera del repositorio. |

El runner MariaDB crea un servidor sin red y dos bases ficticias bajo `/tmp/sprint4-*`; IsolatedMariaDb verifica socket, datadir y base antes de migrar. La instancia final `/tmp/sprint4-26f093ce255c` quedó detenida. Los intentos bloqueados por sandbox no ejecutaron tests y se repitieron con autorización. HTTP usa sesiones array/withoutVite; los assets se compilan y verifican aparte. Las carreras usan procesos independientes, escrituras/migrate:fresh solo en la base temporal verificada. El test de catálogo vacío elimina únicamente fixtures aisladas sin historia; eso no crea un flujo de eliminación en la aplicación.

El test de consultas compara el listado con 3 y 23 productos: conteo constante y una sola carga en lote de unidad/categoría por página, sin fijar un límite absoluto artificial. La búsqueda/categoría/estado/paginación se prueban sobre registros reales ficticios, incluyendo `%_!` literal y filtros malformados. El flujo de recuperación de errores conserva explícitamente la cookie de sesión, igual que las regresiones existentes, para comprobar la página correspondiente al POST.

### Regresiones

La suite final incluye login/logout, rehash, Gates, navegación, Dashboard, Pan/Torta/Bocadito, Historial, snapshots, autoría, fotos, turnos, participantes, migraciones, concurrencia y Proxy HTTPS. No se eliminaron tests. Solo se ajustó AutorizacionRolesTest para esperar Productos en el menú de Administrador; Operador conserva ausencia de ese enlace. No se modificaron Actions/Requests/controllers/vistas funcionales de Producción/Historial ni `DetallePan::LATAS_POR_COCHE`.

### Archivos creados y modificados

**Creados (17):**

- `app/Http/Controllers/ProductoController.php`.
- `app/Http/Requests/GuardarProductoRequest.php`, `CrearProductoRequest.php`, `ActualizarProductoRequest.php`, `CambiarEstadoProductoRequest.php`, `ConsultarProductosRequest.php`.
- `app/Actions/Productos/ActualizarProducto.php`.
- `resources/views/productos/layout.blade.php`, `index.blade.php`, `form.blade.php`.
- `resources/css/productos.css`, `resources/js/productos.js`.
- `tests/Feature/ProductosTest.php`, `ProductosConcurrenciaTest.php`.
- `tests/Support/productos-fixtures.php`, `producto-concurrente.php`: fixtures compartidas y worker aislado; sin código de prueba accesible por rutas de la aplicación.
- `SPRINT_6_FASE_3_PRODUCTOS.md`.

**Modificados (5):** `routes/web.php` (seis rutas), `resources/views/components/sidebar.blade.php` (enlace autorizado), `vite.config.js` (assets), `tests/Feature/AutorizacionRolesTest.php` (menú Admin actual) y `mejora_asignado.md` (avance del mismo problema de autorización).

Producto y los demás modelos, migraciones, seeders, Gates, autenticación, configuración de sesiones, Composer/NPM y archivos funcionales operativos se conservaron. Los artefactos de build están ignorados por Git. No se modificó `.env`, no hubo cuentas nuevas ni escrituras operativas.

### Riesgos pendientes

- Aceptación en dispositivos reales, Safari y lector de pantalla **PENDIENTE**. La emulación headless no equivale a esas pruebas ni a carga por túnel/despliegue.
- Sesiones `database` y operación real **PENDIENTES**; la suite usa sesiones array. La base operativa solo se auditó en lectura.
- Nombre histórico cambia al renombrar; no hay snapshot de nombre. Es el comportamiento explícito de esta fase, no un registro congelado.
- Duplicados permitidos; una eventual prohibición/UNIQUE requiere decisión y auditoría. No hay garantía de unicidad concurrente porque no se implementa esa regla.
- Última edición válida de nombre/factor prevalece; no se incorpora versionado optimista de formularios.
- La protección histórica/concurrente depende de usar las rutas/Actions y el mismo protocolo de locks. SQL manual, seeders de desarrollo u otros escritores futuros pueden saltarla; no usar el seeder para reparar el catálogo administrativo.
- Catálogo definitivo de unidades, máximo operativo del factor y semántica de `temporada_fe` **PENDIENTES**. No convertir Lata provisional ni temporadas en reglas inventadas.
- Usuarios/Empleados/Configuración, estados de cuentas, último Administrador, rate limiting y los riesgos previos de fotos/recuperación **PENDIENTES**, fuera del módulo.

### Próxima fase recomendada

Administración de Empleados, acotada y protegida por administrar-sistema, tras definir estados y efecto sobre selección de participantes/cuentas. Usuarios requiere además decisiones del último Administrador, cambios propios y aprovisionamiento. Configuración de unidades y latas/coches se diseñará después de resolver su contrato histórico. No se implementó ninguna de esas fases ni se crearon operadores.

### Documentación técnica

Se inspeccionó/buscó el Vault original y se leyeron íntegramente las notas afectadas: `LARAVEL/Models.md`, `Requests.md`, `Controllers.md`, `Services.md`, `Routes.md`, `Middleware.md` y `Blade.md`; `PERSONAL Y USUARIOS/Permisos.md`; `PRODUCCION/Requerimientos.md`; `INICIO/Estado de proyecto.md` y `Roadmap.md`; `DECISIONES/Decisiones Laravel.md` (nombre real .md). Las 12 notas originales se actualizaron con autorización por escalamiento: se comprobaron hashes previos y preservación íntegra de la historia después de escribir, sin duplicar carpetas/notas. El informe contiene la explicación extensa para aprendizaje; no se añadió comentario tutorial innecesario al código ni una copia del Vault al repositorio. **No quedó autorización pendiente.** No correspondió modificar notas de estructura de BD o abrir un bug duplicado para este desarrollo planificado; el hallazgo de concurrencia quedó documentado en la decisión y en este informe.

Ubicación: `/home/lukagfv/Documentos/PROYECTOS-LARAVEL/panaderia-rs/SPRINT_6_FASE_3_PRODUCTOS.md`.
