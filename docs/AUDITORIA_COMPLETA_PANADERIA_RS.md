# Auditoría completa — Panadería RS

## Fecha y estado del repositorio

- Fecha: 15 de octubre de 2026, zona de negocio `America/Lima`.
- Checkout auditado: rama `master`, revisión `a0d7c00`. Al inicio, `git status` indicó sincronización con la referencia local `origin/master` y `nothing to commit, working tree clean`. No se consultó el remoto para verificar su actualidad.
- **Cambios previos del usuario: ninguno.**
- Alcance: auditoría; no se corrigió, refactorizó ni añadió código. No se ejecutaron migraciones ni seeders sobre la base local, ni commit/push.
- Único entregable dentro del repositorio: `docs/AUDITORIA_COMPLETA_PANADERIA_RS.md`.
- La instrucción específica de esta auditoría limita la escritura a este informe: no correspondió actualizar el Vault, `mejora_asignado.md` ni otros documentos.

Método y límites de evidencia:

1. Lectura del código, configuración, 28 migraciones, modelos, rutas, Requests, Actions, consultas, vistas, CSS, JS, seeders y pruebas del checkout.
2. Copia temporal de los archivos versionados y de las dependencias instaladas en `/tmp/auditoria-panaderia-9igqmryt`, sin copiar el `.env` real. Tests, compilación, cachés, logs, HTML y capturas se generaron allí o en otros directorios temporales, fuera del original. Se usó configuración ficticia de pruebas.
3. MariaDB desechable mediante `tests/run-mariadb.php`, socket sin red en `/tmp/sprint4-*`, dos bases ficticias y comprobación de datadir/base por `Tests\Support\IsolatedMariaDb`. Las migraciones/seeders de los tests afectan exclusivamente esas bases.
4. Base local: conexiones PDO sin arrancar Laravel; transacciones explícitas `SET TRANSACTION READ ONLY` y `START TRANSACTION READ ONLY`, cerradas con rollback. Solo metadatos y resultados agregados. El indicador de sesión `@@tx_read_only` devolvió 0; no representa el modo particular de la transacción iniciada mediante `START TRANSACTION READ ONLY` y no se usa como prueba de su modo. No se intentó ninguna escritura para comprobarlo.
5. Chrome headless real, perfil temporal, vistas renderizadas por Laravel con fixtures y assets del build, servidas por loopback: ocho vistas × tres anchos. Es una comprobación de renderizado aislado, **no un recorrido interactivo completo contra el servidor Laravel ni aceptación de usuarios**.
6. No se inspeccionó un servidor de destino, certificados de dominio ni una instalación productiva. No se ejecutó pentesting destructivo ni una consulta de vulnerabilidades de dependencias contra bases externas. La ausencia de hallazgos no certifica ausencia de vulnerabilidades.

## Resumen ejecutivo

**El núcleo funcional está avanzado y sus regresiones automáticas pasan. El sistema todavía no está aprobado para exponerse en producción.**

Pan registra latas, calcula coches de 18 latas y conserva el factor histórico. Torta y Bocadito guardan cantidades enteras directas. Las tres familias exigen realmente en backend exactamente un Maestro y al menos un Ayudante. Catálogo, historial, acceso por Gates y concurrencia tienen implementación y pruebas reales.

La suite final aprobó **621 pruebas PHP / 5.682 aserciones**, las **29 pruebas JS** y el build. Pint global falló por formato preexistente en 25 archivos. Se reprodujo que el login redirige ante credenciales inválidas, pero su vista no muestra el mensaje de error.

**Hallazgo crítico confirmado:** una cuenta administradora local coincide con la contraseña literal predeterminada del seeder. El informe omite tanto la contraseña como su hash e identidad. La rotación y el aprovisionamiento seguro son obligatorios antes de publicar. Tampoco hay limitación de intentos en la ruta de login del repositorio.

La base local tiene todas las migraciones registradas y las unidades LATA/UNIDAD; no requiere aplicar una migración solo porque un informe anterior la describa como pendiente. Tiene dos productos Pan, cinco detalles Pan y una cuenta administradora; carece de productos Torta/Bocadito y de cuentas con rol Operador. Los flujos de esas familias se acreditan con fixtures, no con operación real.

## Porcentaje de completitud

**COMPLETADO: 78,75%**

**FALTANTE: 21,25%**

**PREPARACIÓN PARA DESPLIEGUE: 42,50%**

Completitud = suma de la rúbrica exacta A–I: **78,75 / 100**. Faltante = `100 − 78,75 = 21,25` puntos. Es completitud acreditada por la evidencia disponible; parte de la brecha corresponde a validación pendiente, no a código necesariamente ausente.

Preparación para despliegue = área **I exclusivamente**, normalizada: `4,25 / 10 × 100 = 42,50%`. Se utiliza esta área porque sus nueve componentes describen el camino operativo al servidor, con los pesos fijados por el usuario. No se divide la completitud funcional entre otro denominador ni se inventan pesos adicionales. Los riesgos de acceso/seguridad de E/F se evalúan además como puertas obligatorias P0: **el porcentaje de despliegue no autoriza publicar mientras exista un P0 abierto**.

No existe un porcentaje medido de usuarios satisfechos ni de disponibilidad productiva. No se extrapola la tasa de tests aprobados a completitud del producto.

## Tabla de puntuación

| Área | Máximo | Obtenido | Estado |
|------|--------|----------|--------|
| A. Producción y reglas de negocio | 25 | 19,00 | 🟡 Reglas comprobadas; aceptación y ciclo horario incompletos |
| B. Productos y catálogo | 10 | 9,00 | 🟡 Implementado; aceptación operativa pendiente |
| C. Historial | 10 | 9,50 | 🟡 Funcional; aceptación UX pendiente |
| D. Dashboard | 7 | 6,25 | 🟡 Resumen real limitado a Pan |
| E. Login, usuarios y acceso | 10 | 7,50 | 🟡 Acceso implementado; cuentas y feedback incompletos |
| F. Validaciones, seguridad e integridad | 10 | 8,50 | 🔴 Seguridad de acceso pendiente antes de publicación |
| G. UX / responsive general | 8 | 5,75 | 🟡 Renderizado aislado; aceptación manual pendiente |
| H. Tests y calidad técnica | 10 | 9,00 | 🟡 Tests/build aprobados; formato global fallido |
| I. Preparación para despliegue | 10 | 4,25 | 🔴 Entorno productivo y ensayo no acreditados |
| **TOTAL** | **100** | **78,75** | **ROJO para despliegue; funcionalidad avanzada** |

Cada factor usa solamente 100%, 75%, 50%, 25% o 0%, conforme a la escala solicitada. La calificación de pantallas con UX pendiente se limita a 75%; el 100% de una regla backend significa que esa regla concreta fue comprobada, no que toda su pantalla haya sido aceptada manualmente.

| Subapartado | Máximo | Factor | Obtenido | Evidencia / motivo del límite |
|---|---:|---:|---:|---|
| A. Pan | 8 | 75% | 6,00 | HTTP/Action, snapshot y 12×30 probados; operación manual pendiente |
| A. Torta | 5 | 75% | 3,75 | Cantidad directa, foto y equipo probados; sin catálogo local de esta familia |
| A. Bocadito | 5 | 75% | 3,75 | Cantidad directa y equipo probados; sin catálogo local de esta familia |
| A. Maestro/Ayudantes | 4 | 100% | 4,00 | Action compartida y casos negativos de las tres familias |
| A. Turnos/horas/estado | 3 | 50% | 1,50 | Turnos reales; finalización solo Pan; sin inicio, creación o estado productivo |
| B. CRUD/productos | 4 | 75% | 3,00 | Crear/editar/estado probados; aceptación manual pendiente; no DELETE deliberado |
| B. Categorías | 2 | 100% | 2,00 | Tres familias resueltas por identidad real y validadas, sin IDs fijos |
| B. Unidades canónicas | 2 | 100% | 2,00 | Código UNIQUE, seeder/resolución/manipulación probados; ambas unidades locales presentes |
| B. Protección histórica | 2 | 100% | 2,00 | Bloqueo de categoría/unidad, snapshots y carreras comprobados |
| C. Consultas/filtros | 3 | 100% | 3,00 | Fechas, familia, turno, errores y paginación SQL probados |
| C. Presentación por familia | 3 | 100% | 3,00 | Valores 12/360, 8 y 150 en HTML/fixtures; no latas en otras familias |
| C. Detalle completo | 2 | 100% | 2,00 | Equipo, autor, observación, snapshot y hora cuando existen; foto/forma Torta |
| C. Responsive/UX | 2 | 75% | 1,50 | CSS/DOM, headless y capturas; falta aceptación manual |
| D. Datos reales | 3 | 100% | 3,00 | Agregaciones SQL de Pan; sin contadores inventados |
| D. Métricas/gráficos | 2 | 75% | 1,50 | Métricas y desglose por turno; cobertura de familias limitada a Pan |
| D. Fechas/timezone/vacío | 1 | 100% | 1,00 | Configuración America/Lima, fecha del negocio y estados vacíos probados |
| D. Responsive | 1 | 75% | 0,75 | Renderizado headless; aceptación manual pendiente |
| E. Autenticación | 4 | 75% | 3,00 | Login, hash y rehash funcionan; formulario no representa los errores |
| E. Logout/sesión | 1 | 100% | 1,00 | Invalidate/regenerateToken, pruebas HTTP y roundtrip database aislado |
| E. Usuarios operativos | 2 | 25% | 0,50 | Hay una cuenta administradora; ninguna operadora ni aprovisionamiento completo |
| E. Permisos/roles necesarios | 2 | 100% | 2,00 | Dos Gates por identidad de rol, rechazo de rol desconocido; matriz probada |
| E. Protección de rutas | 1 | 100% | 1,00 | Auth/can también en POST/PATCH, no solo ocultación en menú |
| F. Backend validation | 3 | 100% | 3,00 | Requests de escritura y consulta, límites/tipos y errores probados |
| F. Reglas cruzadas | 2 | 100% | 2,00 | Familia/producto activo, unidad canónica, equipo, historial y autor del servidor |
| F. Integridad BD | 2 | 75% | 1,50 | FK/PK/CHECK; faltan garantías SQL de sesión/catálogos y varias reglas cruzadas |
| F. Seguridad HTTP/auth | 2 | 50% | 1,00 | CSRF/auth/Gates; contraseña inicial vigente y login sin throttling |
| F. Concurrencia | 1 | 100% | 1,00 | Transacciones/locks y pruebas de carreras reales de los flujos actuales |
| G. Desktop | 2 | 75% | 1,50 | CSS y navegador aislado; falta aceptación humana |
| G. Tablet | 1 | 75% | 0,75 | Viewport 768 y revisión de breakpoints; no dispositivo físico |
| G. Móvil | 2 | 75% | 1,50 | Viewport 375 y revisión de capturas; no Safari/cámara real |
| G. Mensajes/feedback | 1 | 50% | 0,50 | Producción/Productos/Historial tienen feedback; Login no muestra errores |
| G. Accesibilidad básica | 1 | 75% | 0,75 | Labels/ARIA/foco/sidebar; sin auditoría completa de teclado/lector/contraste |
| G. Consistencia visual | 1 | 75% | 0,75 | Paleta y navegación compartidas; marca distinta en Login y detalles por aceptar |
| H. Tests PHP | 4 | 100% | 4,00 | 621 aprobados, 5.682 aserciones, cero fallidos/omitidos en corrida final |
| H. Tests JS | 2 | 100% | 2,00 | 29 casos aprobados; scripts reales con dobles DOM |
| H. Build | 1 | 100% | 1,00 | npm run build, manifest y assets generados correctamente |
| H. Arquitectura/mantenibilidad | 2 | 75% | 1,50 | Controllers pequeños, Actions/Requests; scaffold/comentarios obsoletos y vistas extensas |
| H. Lint/format/diff | 1 | 50% | 0,50 | Diff limpio; Pint global falla en 25 archivos; sin formatear |
| I. Configuración producción | 2 | 25% | 0,50 | Configuración parametrizada, ejemplo local; no configuración de destino verificada |
| I. Migraciones/seeders reproducibles | 2 | 75% | 1,50 | Esquema/test de migración pasan en MariaDB; SQLite del ejemplo incompatible y cuenta inicial insegura |
| I. Build assets | 1 | 100% | 1,00 | Compilación aprobada, lockfile y sin public/hot en original |
| I. Almacenamiento/permisos | 1 | 50% | 0,50 | Disco público, compensación de foto y symlink local; permisos/servido del destino no probados |
| I. Sesiones/cache | 1 | 50% | 0,50 | Tablas/config y roundtrip database aislado; cookies, cache y operación del destino pendientes |
| I. Servidor web | 1 | 25% | 0,25 | public/index.php y .htaccess; sin virtualhost/FPM de destino validado |
| I. Dominio/HTTPS | 1 | 0% | 0,00 | Proxy loopback probado; ningún dominio/certificado real verificado |
| I. Checklist deploy/rollback | 1 | 0% | 0,00 | No hay runbook operativo ensayado en el checkout; este informe no equivale a uno ejecutado |

## Arquitectura actual

### Plataforma e inventario

| Componente | Estado observado |
|---|---|
| PHP CLI | 8.5.10; composer.json requiere `^8.3` |
| Laravel | Restricción `^13.17`; composer.lock e instalación: **v13.25.0** |
| Herramientas PHP | Pest 5.1.1, PHPUnit 13.3.0, Pint 1.30.5. PHPUnit instalado exige PHP ≥8.4.1: el mínimo del runtime de producción no basta para este entorno de desarrollo/tests |
| Node/npm | Node 26.11.1, npm 12.2.0 en esta máquina |
| Frontend | Blade, CSS propio, JavaScript nativo; sin SPA React/Vue/Livewire |
| Assets | Vite 8.3.0, laravel-vite-plugin 3.2.0, Tailwind 4.3.3; plugin Tailwind configurado, pantallas principales con CSS propio |
| Chart.js | 4.5.1 declarado/instalado; no importado por el Dashboard actual |
| Dependencias | composer.json/composer.lock y package.json/package-lock.json presentes; se verificó con dependencias instaladas, no con una instalación nueva desde cero |
| Configuración | config/app, auth, database, session, cache, queue, filesystems, logging, mail, services |

`app/` contiene Models, Http/Controllers, Http/Requests, Http/Middleware, Actions/Productos, Actions/Produccion, Queries y Providers. **No hay carpeta Services ni Policies propias.** La autorización se resuelve con Gates; no falta una Policy solo por no usar ese patrón.

- Controllers: `LoginController`, `DashboardController`, `ProductoController`, `ProduccionController`, `HistorialController` y base `Controller`. Los dos coordinadores principales tienen 114 y 107 líneas respectivamente; no son controladores monolíticos grandes.
- Models (18): `Cargo`, `Categoria`, `DetalleBocadito`, `DetalleBocaditoEmpleado`, `DetallePan`, `DetallePanEmpleado`, `DetallePedido`, `DetalleTorta`, `DetalleTortaEmpleado`, `Empleado`, `Pedido`, `Produccion`, `Producto`, `Rol`, `RolProduccion`, `Turno`, `UnidadMedida`, `Usuario`.
- Requests (11): `ConsultarProductosRequest`, `GuardarProductoRequest`, `CrearProductoRequest`, `ActualizarProductoRequest`, `CambiarEstadoProductoRequest`, `ConsultarProduccionRequest`, `RegistrarProduccionPanRequest`, `RegistrarProduccionPorCantidadRequest`, `RegistrarProduccionTortaRequest`, `RegistrarProduccionBocaditoRequest`, `ConsultarHistorialProduccionRequest`. Los dos Requests PorCantidad/GuardarProducto abstraen reglas compartidas; Login valida mediante Request ordinario en controller.
- Actions (9): `CrearProducto`, `ActualizarProducto`, `ResolverUnidadProducto`; `RegistrarProduccionPan`, `RegistrarProduccionTorta`, `RegistrarProduccionBocadito`, `RegistrarProduccionPorCantidad`, `ResolverCabeceraProduccion`, `ValidarParticipantesProduccion`.
- Query: `app/Queries/ConsultarHistorialProduccion.php`.
- Middleware propio: `PreventPageCaching`; auth, guest, can, sesión y CSRF del framework. `bootstrap/app.php` confía solo en proxies loopback y establece redirecciones.
- Vistas (10 bajo resources/views): Login; Dashboard; Productos index/form/layout; Producción index; Historial history/index; sidebar; error 403; `history_pedido/index` sin ruta operativa. `resources/layouts/app.blade.php` está fuera de views y contiene solo una entrada Vite: no es el layout general efectivamente usado por esas pantallas.
- CSS (8): app, login, dashboard, productos, produccion, historial, sidebar, errores. JS (7): app, login, dashboard, productos, produccion, historial, sidebar. `app.js` importa sidebar; dashboard/historial JS son comentarios, sin funcionalidad ficticia.
- 19 archivos Feature PHP, uno Unit de ejemplo; tres suites JS. Inventario de pruebas detallado en Tests.

### Rutas reales

No existe `routes/auth.php`; la autenticación está en `routes/web.php`.

| Acceso | Método / ruta | Responsabilidad |
|---|---|---|
| Público | GET `/` | Redirige a Login |
| guest | GET y POST `/login` | Formulario/autenticación |
| auth | POST `/logout` | Cierra sesión |
| auth + can:administrar-sistema | GET `/productos`, `/productos/crear`, `/productos/{producto}/editar` | Catálogo/formularios |
| auth + can:administrar-sistema | POST `/productos`; PUT/PATCH `/productos/{producto}`; PATCH `/productos/{producto}/estado` | Crear/editar/estado |
| auth + can:acceder-operacion | GET `/dashboard`, `/produccion`, `/history` | Operación y consultas |
| auth + can:acceder-operacion | POST `/produccion`, `/produccion/torta`, `/produccion/bocadito` | Un lote por envío de su familia |
| Salud | GET `/up` | Health route del framework; no acredita integridad de todos los módulos |

No existen rutas CRUD de usuarios, empleados, turnos, categorías, pedidos o stock. `routes/console.php` solo añade `inspire`; no hay scheduler de negocio propio.

### Responsabilidades y transacciones

Los Requests validan el contrato HTTP; Controllers coordinan, consultan y redirigen; Actions escriben y revalidan reglas bajo locks. PorCantidad reutiliza persistencia/equipo/cabecera para Torta/Bocadito y Torta compensa el archivo si falla SQL. El historial pagina un UNION SQL antes de hidratar relaciones; evita traer todo el historial a memoria.

`RegistrarProduccionPan` y `RegistrarProduccionPorCantidad` usan `DB::transaction`; bloquean categoría → producto y participantes ordenados. `ActualizarProducto` comparte orden de locks, usa lecturas actuales para historial y hasta tres intentos transaccionales. La categoría bloqueada serializa todos los registros de esa familia, incluso fechas/turnos distintos: correcto en las carreras verificadas, con posible límite de rendimiento futuro.

Límites: las Actions asumen datos ya validados por Requests; no todas validan por sí mismas turno/fecha/tipos. Un consumidor futuro deberá respetar ese límite. El formulario de Producción tiene 370 líneas y su JS 336: son puntos de mantenimiento, no prueba de defecto funcional. Hay comentarios que todavía dicen que permisos o escritores HTTP no existen, aunque ya existen. La factoría scaffold apunta a `App\Models\User`, clase inexistente; las pruebas actuales usan fixtures de `Usuario` y no esa factoría.

## Base de datos

### Migraciones y esquema consolidado

Las 28 migraciones se revisaron; no se ejecutó ninguna sobre datos locales. Las migraciones originales se agrupan en cache/jobs, catálogos, empleados/usuarios, productos, producción/detalles/pivotes, pedidos/detalles y sessions. Las siete ampliaciones de negocio son observaciones, categoría/turno de cabecera, factor/snapshot, cantidad Torta, finalización Pan, registrador del detalle y códigos de unidad.

| Tabla | PK, relaciones y restricciones relevantes |
|---|---|
| cargos | BIGINT UNSIGNED id; nombre VARCHAR(100) obligatorio; sin timestamps ni UNIQUE de nombre |
| roles | id; nombre obligatorio sin UNIQUE; identifica Administrador/Operador por nombre |
| turnos | id; nombre obligatorio sin UNIQUE; fuente de catálogo, no ID fijo |
| categorias | id; nombre obligatorio sin UNIQUE; Pan/Torta/Bocadito por nombre |
| roles_produccion | id; nombre obligatorio sin UNIQUE; Maestro/Ayudante por nombre |
| unidades_medida | id; nombre obligatorio; equivalencia DECIMAL(8,2) default 1; codigo VARCHAR(20) nullable UNIQUE; permite varias filas legacy NULL |
| empleados | id; nombre obligatorio; número VARCHAR(20) nullable; cargo_id FK NOT NULL; created_at/updated_at |
| usuarios_sistema | id; username VARCHAR(50) UNIQUE; password_hash VARCHAR(255) NOT NULL; empleado_id FK UNIQUE y rol_id FK obligatorios; timestamps |
| productos | id; nombre VARCHAR(100) obligatorio; temporada_fe nullable; activo boolean default true; categoría/unidad FK NOT NULL; panes_por_lata INT nullable; sin timestamps |
| produccion | id; fecha DATE NOT NULL; registrador de sesión FK obligatorio; categoria_id y turno_id FK nullable por compatibilidad histórica; sin timestamps ni UNIQUE fecha/categoría/turno |
| detalle_pan | id; FK a producción, producto, unidad y turno obligatorias; cantidad INT NOT NULL CHECK >0; snapshot INT nullable; observación TEXT nullable; hora_finalizacion TIME nullable; registrador de lote FK nullable; sin timestamps |
| detalle_torta | id; producción/producto FK obligatorias; forma VARCHAR(30) NOT NULL; foto VARCHAR(255) nullable; cantidad INT NOT NULL CHECK >0, sin default final; observación y registrador nullable; sin unidad, factor ni timestamps |
| detalle_bocadito | id; producción/producto FK obligatorias; cantidad INT NOT NULL CHECK >0; observación y registrador nullable; sin unidad, factor ni timestamps |
| detalle_pan_empleado | PK compuesta detalle_pan_id/empleado_id; FK rol_produccion_id obligatoria; sin timestamps |
| detalle_torta_empleado | Misma estructura para detalle_torta |
| detalle_bocadito_empleado | Misma estructura para detalle_bocadito |
| pedidos | id; cliente nullable; fechas registro/entrega, entregado y registrador FK; estructura sin flujo HTTP operativo |
| detalle_pedidos | id; pedido/producto/unidad FK; cantidad DECIMAL(10,2); especificaciones/foto nullable; sin flujo HTTP operativo |
| sessions | PK string id; user_id FK nullable a usuarios_sistema con SET NULL; IP/agent nullable; payload y last_activity indexado |
| cache/cache_locks | PK key; expiración indexada; tablas disponibles para caché/locks de infraestructura |
| jobs/job_batches/failed_jobs | Tablas de cola y fallos del scaffold; jobs propios no encontrados |

Todos los `foreignId()->constrained()` implican relaciones indexadas en MariaDB. En tablas de negocio, las eliminaciones de cabecera a detalles y de detalle a participantes tienen CASCADE; el resto de relaciones usa restricciones por defecto, salvo `sessions.user_id` SET NULL. Las cabeceras/productos no tienen ruta DELETE, lo que reduce el riesgo de borrado accidental desde la aplicación. SQL externo sí podría borrar una cabecera y sus detalles por CASCADE.

Las ampliaciones se preservan sin inventar historia: snapshot, hora y registrador pueden ser NULL. La migración de cantidad Torta preserva filas antiguas como 1 antes de retirar el default. Varias funciones `down()` rechazan revertir si existen observaciones, clasificación, factores, cantidades distintas de 1, horas, registradores o códigos: **rollback de aplicación no equivale a rollback de esquema**.

La sintaxis `ALTER TABLE ... ADD CONSTRAINT ... CHECK` y las operaciones posteriores sobre constraints están diseñadas para MariaDB/MySQL, no para el SQLite sugerido en `.env.example`/phpunit.xml. La infraestructura real de integración evita esa incompatibilidad usando MariaDB; no se certifica SQLite como motor de despliegue.

### Lectura local comprobada

| Dato agregado | Resultado |
|---|---:|
| Migraciones del checkout no registradas | 0 |
| Cabeceras producción | 4 |
| Detalles Pan / Torta / Bocadito | 5 / 0 / 0 |
| Productos | 2, ambos Pan activos con factor definido |
| Categorías | 3 |
| Turnos | 2: una fila Mañana y una Noche |
| Unidades | 2: una LATA y una UNIDAD |
| Asociaciones de producto incompatibles con canon | 0 |
| Empleados / roles de producción | 2 / 2 |
| Usuarios / roles de acceso | 1 / 3 |
| Cuentas Administrador / Operador | 1 / 0 |
| Sesiones almacenadas | 3; no se leyó su payload ni identificadores |
| Cabeceras duplicadas por fecha/categoría/turno | 0 |
| Cabeceras sin categoría o turno | 0 |
| Detalles Pan sin snapshot | 0 |
| Detalles Pan sin hora finalización | 4 de 5 |
| Equipos Pan que incumplen Maestro/Ayudante | 0 de 5 |
| Administradores que conservan contraseña literal del seeder | 1 |

Metadatos `information_schema` confirmaron tipos, nullabilidad, índices y FK del esquema real. En `produccion`, además de PRIMARY, hay índices separados de las tres FK; no un índice compuesto por fecha/categoría/turno. No se extrajeron datos personales, credenciales, hashes ni sesiones al informe.

### Riesgos de integridad

- El protocolo de locks garantiza la sesión en los escritores actuales, pero SQL no tiene UNIQUE compuesto. Un escritor externo puede duplicarla; el resolver detecta duplicados y rechaza el guardado posterior.
- Nombres de categorías, turnos, roles y roles de producción no son UNIQUE. Los seeders secuenciales usan firstOrCreate/sole, pero una carga externa o concurrente puede crear ambigüedades.
- La cardinalidad Maestro/Ayudantes, familia del producto, igualdad de turno de detalle/cabecera y correspondencia categoría/unidad no se expresan como restricciones SQL entre tablas; dependen de los escritores autorizados.
- Factor/snapshot nullable no lleva CHECK positivo en BD. El CRUD/Action controla valores; una escritura externa podría saltarse la regla.
- El historial une categoría de cabecera con la familia de detalle: una cabecera legacy sin categoría no aparece. Hoy el conteo local de ese caso es cero; no se generaliza a otro entorno.
- Nombres de productos y personas se leen por relación actual, sin snapshot del nombre. Renombrar cambia lo visible en registros históricos; la UI de Productos lo advierte.

## Login y autenticación

**🟡 PARCIAL; mecanismo backend IMPLEMENTADO Y COMPROBADO en aislamiento.**

Evidencias: `routes/web.php`, `app/Http/Controllers/LoginController.php`, `app/Models/Usuario.php`, `config/auth.php`, `resources/views/auth/login.blade.php`, NavigationTest/AutorizacionRolesTest/ProxyHttpsTest.

- GET Login funciona para guest y redirige al Dashboard cuando ya hay sesión.
- POST exige username/password, usa Auth::attempt, regenera ID de sesión y respeta destino solicitado. El modelo usa usuarios_sistema y getAuthPassword devuelve password_hash.
- **getAuthPasswordName() sí está implementado en este checkout y devuelve password_hash.** Además de leerlo, se forzó el rehash mediante EloquentUserProvider con un usuario ficticio persistido; cambió el hash en la columna correcta y verificó la contraseña ficticia. No se modificó una cuenta real. Su estado ya no es “no implementado”; falta convertir este caso específico en regresión permanente si se exige mantenerlo cubierto.
- Logout es POST con CSRF; invalida sesión y regenera token. PreventPageCaching/app.js atienden caché y restauración BFCache.
- Cookies: HttpOnly true y SameSite lax por defecto; secure depende de SESSION_SECURE_COOKIE. La configuración local no fija esta última; HTTPS/cookies del destino deben comprobarse.

Defectos/riesgos:

1. La vista no usa `$errors`, @error ni old('username'). Con login ficticio inválido se obtuvo redirección 302 y la siguiente vista **no contenía “Credenciales incorrectas.”** La respuesta backend existe, su presentación falta.
2. No hay `throttle` en POST /login ni RateLimiter propio. No se verificó protección equivalente en un proxy externo.
3. Username/password solo tienen required; faltan validación explícita de tipo/longitud y casos específicos de entrada malformada para este formulario.
4. “¿Olvidaste tu contraseña?” es un botón sin handler/ruta. config/auth conserva broker scaffold, pero no hay tabla/rutas/modelo operativo de recuperación. No funciona como flujo de recuperación.
5. Branding Login: “Panificadora Amazónica”, mientras módulos operativos usan Panadería RS. No es una falla de autenticación, sí de consistencia.
6. Una contraseña inicial conocida sigue vigente localmente. **P0**, independientemente de que el login funcione.

## Dashboard

**🟡 PARCIAL / REQUIERE VALIDACIÓN.**

`DashboardController@index` consulta DetallePan y producción real, agrega COUNT/SUM por turno y fecha de cabecera, muestra últimos cinco lotes y carga relaciones. La vista explicita que resume **Pan**. No mezcla el autor de sesión con un supuesto autor de lote: la tabla etiqueta “Registró la sesión”.

Métricas disponibles: lotes de hoy, latas de hoy, lotes/latas por turno y registros recientes. Cero resultados y ausencia de turnos tienen mensajes; cabeceras sin turno se presentan como desconocidas. Usa now() y timezone America/Lima, sin convertir la fecha de negocio a UTC ficticia.

Chart.js está declarado, pero no hay import/instanciación, canvas ni gráfico real. No se exige instalar gráficos para considerar útil el Dashboard; la limitación funcional es que no resume Torta/Bocadito. Ampliarlo debe separar unidades por familia; sumar latas y tortas en un solo total sería incorrecto.

DashboardTest verifica consultas/fechas/casos vacíos. Se cargó la vista real en Chrome a 375/768/1280. **PENDIENTE DE ACEPTACIÓN MANUAL** de información, tabla desplazable, navegación y lectura en dispositivos reales.

## Productos

**🟡 PARCIAL / núcleo IMPLEMENTADO Y COMPROBADO en aislamiento.**

`ProductoController`, Requests de Productos y Actions Crear/Actualizar/ResolverUnidad implementan crear, listar/buscar/filtrar/paginar, editar y activar/desactivar. No hay eliminación; es coherente con conservar historial, no un CRUD roto por omitir DELETE.

- Categorías permitidas: Pan/Torta/Bocadito, validadas contra el catálogo real.
- Pan requiere panes_por_lata entero positivo estricto ≤2147483647. Ausente, cero, negativo, decimal y tipos incompatibles se rechazan; JS/Blade alternan visible/disabled/required por familia.
- Torta/Bocadito rechazan factor no vacío. No lo usan para convertir su cantidad.
- ResolverUnidadProducto asigna por código UNIQUE: Pan LATA, otras familias UNIDAD. No acepta una unidad manipulada incompatible ni depende de IDs constantes.
- Con historial, categoría/unidad quedan protegidas por Action bajo locks, además de los controles de vista. El factor futuro/nombre/estado pueden editarse y el snapshot previo permanece intacto.
- Excepción legacy explícita: una unidad asociada antes del canon se conserva si hay historia. La vista avisa; no se reasignan históricos automáticamente.
- Activar/desactivar es idempotente: se asigna el estado enviado, no se invierte ciegamente.

`UnidadMedidaSeeder` adopta solo una coincidencia legacy inequívoca y equivalencia 1, crea la identidad si no existe y falla ante ambigüedad. Puede completar una identidad segura antes de informar error en otra: no es una operación global atómica de catálogo. No se certifica ejecución simultánea de seeders.

La base local ya contiene ambas identidades, dos productos Pan y cero productos de otras familias. Para operar el alcance completo falta preparar catálogo de negocio real, sin usar el seeder de desarrollo como catálogo productivo. Formularios/listado y scripts están probados automáticamente; **PENDIENTE DE ACEPTACIÓN MANUAL**.

## Producción Pan

**🟡 PARCIAL por aceptación operativa; reglas centrales IMPLEMENTADAS Y COMPROBADAS.**

`RegistrarProduccionPanRequest` exige familia pan, fecha Y-m-d, turno existente, exactamente un detalle en índice 0, producto existente, hora H:i, coches enteros ≥0, latas adicionales entre 0 y 17, observación TEXT dentro de capacidad y participantes con IDs válidos.

`RegistrarProduccionPan` comprueba categoría Pan, producto activo de esa categoría, unidad existente, factor positivo o históricamente desconocido y equipo correcto. Calcula únicamente en servidor:

`total_latas = coches × 18 + latas_adicionales`

`panes_estimados = total_latas × panes_por_lata_usado`

El total debe ser positivo y caber en INT firmado. Se guarda cantidad en latas, unidad del producto, turno de cabecera, registrador autenticado, observación, hora final y copia del factor vigente bloqueado. El navegador solo presenta una estimación y no determina el snapshot enviado.

**Caso exigido comprobado:** 0 coches + 12 latas; factor 30 → cantidad 12, snapshot 30, estimación **360**. Editar a 32 conserva el lote antiguo; un lote posterior usa 32. Cubierto en ProductosTest/UnidadesCanonicasTest/PanUnitsTest y fixtures del renderizado.

No se guarda la descomposición original coches/latas: se reconstruye con división/resto de 18. Se permiten lotes nuevos del mismo producto en una sesión: no se sobrescribe el anterior. El resolver se niega a elegir arbitrariamente entre cabeceras duplicadas.

Límites: permite factor NULL heredado y comprueba existencia de unidad, no que todo producto legacy de Pan ya tenga código LATA. El CRUD nuevo sí aplica canon; el historial no se reescribe. La base local no tiene productos incompatibles ni snapshots vacíos. Falta recorrido operativo humano completo.

## Producción Torta

**🟡 PARCIAL por preparación local/aceptación; flujo IMPLEMENTADO Y COMPROBADO en aislamiento.**

Request específico hereda cantidad INT positiva 1..2147483647, familia torta, fecha/turno/producto/equipo y observación. Añade forma obligatoria de hasta 30 caracteres y foto opcional JPEG/PNG/WebP, validada por contenido/extensión, máximo 2 MB. SVG no permitido.

RegistrarProduccionTorta delega en RegistrarProduccionPorCantidad: producto activo de categoría Torta, exactamente un Maestro y al menos un Ayudante, cabecera y autor del servidor. Foto con nombre generado en disco público; elimina/compensa archivo si falla la transacción y reporta fallos de limpieza.

**8 se guarda y se muestra como 8 tortas**, incluso si una asociación legacy tiene equivalencia o factor ajenos. No hay columna de latas, unidad o panes_por_lata en detalle_torta. La forma/foto/observación/equipo aparecen donde corresponde.

No existe hora de inicio, creación o finalización Torta, ni estado/botón finalizar. No hay producto Torta local para operación real. Pruebas de almacenamiento usan disco falso; no prueban permisos ni URL pública del servidor final. Cámara de móvil y foto física: **PENDIENTE DE ACEPTACIÓN MANUAL**.

## Producción Bocadito

**🟡 PARCIAL por preparación local/aceptación; flujo IMPLEMENTADO Y COMPROBADO en aislamiento.**

RegistrarProduccionBocaditoRequest/Action usan contrato por cantidad con producto activo de Bocadito, fecha/turno y equipo. **150 se guarda y se muestra como 150 bocaditos**, sin latas/factor ni equivalencia multiplicativa. Observación opcional y autor del lote quedan persistidos. No tiene foto ni forma; no son requisitos confirmados para esta familia.

No hay producto Bocadito local ni horas/estado productivo. Falta aceptación operativa completa con catálogo real; las cantidades, validaciones e historial sí se comprobaron con fixtures.

## Equipo de producción

**🟢 COMPLETO en la regla backend especificada; IMPLEMENTADO Y COMPROBADO.**

`ValidarParticipantesProduccion::errores()` se usa en las tres familias, dentro de transacción. Cuenta exactamente un rol Maestro y al menos un Ayudante; rechaza otros roles, empleados inexistentes y repetidos. Los nombres de roles provienen de catálogo, sin IDs fijos. Bloquea empleados en orden de ID; pivotes tienen PK compuesta para impedir duplicar el mismo empleado en un detalle.

El cargo de recursos humanos no sustituye el rol de esa producción: no se exige que empleado.cargo diga “Maestro”. No hay relación que autorice inventar esa equivalencia. UI permite seleccionar/agregar/quitar, pero la garantía no depende de JS.

Pruebas Pan/PorCantidad/Action cubren ausencia, duplicidad y rol inválido; los cinco lotes Pan locales cumplen la composición. No hay CRUD operativo de empleados/cargos; hoy se aprovisionan por seeders/procedimiento externo.

## Turnos

**🟢 COMPLETO como selección de turno de producción; 🟡 PARCIAL si se incluye administración/ciclo horario.**

TurnoSeeder hace firstOrCreate por nombre Mañana/Noche; tabla turnos es la fuente de verdad. Producción cabecera tiene turno_id, Pan conserva también turno_id en detalle. Torta/Bocadito obtienen turno por cabecera. FormRequests usan exists, las consultas y vistas usan IDs del catálogo y nombres reales. Los tests desplazan IDs para detectar supuestos numéricos fijos.

No hay horas de inicio/fin por Turno ni CRUD de turnos. No hay turno_id en Usuario ni asignación cuenta-turno. Las pruebas de autorización confirman que el nombre de cuenta no limita el turno registrado; eso es una decisión de arquitectura actual, **no un bug deducido por el auditor**. Antes de crear cuentas operativas debe acordarse si son personales o compartidas; no se impone un rol diferente por mañana/noche.

## Hora de finalización

| Elemento | Estado | Evidencia |
|---|---|---|
| Hora final Pan | IMPLEMENTADO | TIME nullable en BD; H:i requerido en altas; guardado en Action y visualización en Producción/Historial |
| Hora final Pan histórico | PARCIAL | 4/5 filas locales NULL; la UI informa desconocida; no corresponde inventarlas |
| Hora final Torta/Bocadito | NO IMPLEMENTADO | Sin columnas, Requests ni representación |
| Hora inicio | NO IMPLEMENTADO | Sin campo/flujo en ninguna familia |
| Hora creación del lote | NO IMPLEMENTADO | Producción y tres detalles no tienen created_at/updated_at |
| Botón finalizar | NO IMPLEMENTADO | Guardar registra el lote; no hay transición separada |
| Estado de producción | NO IMPLEMENTADO | No hay estado en proceso/finalizado/cancelado ni endpoints |
| Fecha productiva | IMPLEMENTADO | Produccion.fecha DATE, independiente del reloj de alta |

La hora final Pan es un valor local introducido manualmente, no un timestamp automático verificable de finalización. No hay control temporal inicio≤fin ni fecha de finalización para cruces de medianoche; no debe afirmarse duración del lote. Definir el ciclo completo es P1 de cierre, no una funcionalidad ya terminada.

## Historial

**🟡 PARCIAL únicamente por aceptación UX; consulta y representación IMPLEMENTADAS Y COMPROBADAS en aislamiento.**

ConsultarHistorialProduccionRequest valida desde/hasta (orden inclusivo), familia, turno existente y página positiva. GET inválido muestra errores sin redirección circular ni consultar datos como si fuera válido. ConsultarHistorialProduccion une las tres tablas, filtra en SQL y pagina 15 lotes antes de cargar producto, autores, turno, empleados/roles y unidad Pan.

La vista `resources/views/history/index.blade.php` agrupa por fecha, conserva filtros al paginar, muestra estados sin resultados y ofrece details/summary nativo. Pan presenta latas, coches/resto, snapshot, estimación y hora cuando existe; Torta/Bocadito muestran cantidades directas; Torta forma/foto; todas equipo, observación y autor.

Si no consta autor del lote, lo declara desconocido y etiqueta por separado quién registró la sesión: no atribuye a ese usuario el lote histórico. No calcula retroactivamente snapshots/hora desconocidos. Los filtros usan turno de cabecera. Las cabeceras sin clasificación quedan fuera de las consultas de familia; no se encontraron localmente, pero deben revisarse al migrar otro entorno.

HistorialProduccionTest, pruebas integradas de familias y Chrome con 12/8/150 acreditan datos y DOM. Las fechas pueden repetirse entre páginas por paginación de lotes, comportamiento correcto. **PENDIENTE DE ACEPTACIÓN MANUAL** de filtros, detalles, paginación, fotografías, texto largo y lectura con teclado/dispositivos.

## Usuarios y permisos

**🟡 PARCIAL.**

| Capacidad | Realidad actual |
|---|---|
| Administrador | Modelo/rol/Gate y una cuenta local comprobados |
| Operador | Modelo/rol/Gate comprobados con fixtures; cero cuentas locales |
| Usuario Turno Mañana/Noche | No aprovisionados como capacidad operativa; no existe vínculo usuario-turno |
| Roles | Administrador/Operador definidos por constantes/nombre; tabla local tiene tres filas de roles, no solo dos |
| Permisos | Gates acceder-operacion y administrar-sistema; no matriz de permisos editable |
| Acceso operaciones | Administrador y Operador: Dashboard, Producción, Historial |
| Administración catálogo | Solo Administrador, también en endpoints directos |
| Rol desconocido | Acceso protegido rechazado; logout sigue disponible |
| CRUD de usuarios | NO IMPLEMENTADO: sin controller, Requests, vistas ni rutas |
| Gestión empleados | Seeder/modelo, sin CRUD operativo |
| Activación/desactivación de cuenta | No hay campo activo ni flujo dedicado |
| Cambio/recuperación de contraseña | Sin flujo operativo dedicado |

AutorizacionRolesTest verifica ambos roles, acceso directo, rechazo de terceros y revalidación tras cambiar el rol persistido. AutorizacionSeedersTest verifica resolución por identidad/IDs variables y conservación de cuentas existentes. UsuarioSeeder crea solo la cuenta inicial y no altera una existente al repetirlo, incluyendo su contraseña: **volver a ejecutar el seeder no rota la credencial**.

No se recomienda añadir paquetes/roles por turno solo por convenciones. El acceso actual resuelve la separación Administrador/Operador; falta acordar y ejecutar aprovisionamiento y ciclo de vida de cuentas. Puede resolverse inicialmente mediante procedimiento administrativo seguro sin obligar a construir un CRUD visual completo.

## Responsive y UI

**🟡 PARCIAL / PENDIENTE DE ACEPTACIÓN MANUAL.**

Hay navegador real disponible y se utilizó: Chrome headless, vistas reales renderizadas con fixtures, CSS/JS del build, perfil nuevo y servidor estático loopback. Se cargaron Login, Dashboard, listado Productos, nuevo Producto, Producción Pan/Torta/Bocadito e Historial a **375, 768 y 1280 px**. Se esperó la carga de estilos; capturas iniciales antes de cargar CSS se descartaron como artefactos de la verificación.

Se inspeccionaron capturas de Login móvil, Producción Pan móvil e Historial móvil/escritorio. En la verificación cargada no se observó overflow horizontal del documento en esos anchos ni excepciones JS no capturadas. Esto no prueba todos los estados, zoom, mensajes ni interacción con datos reales. Los controles date/time adoptan formato de locale del navegador, aunque el backend espera formatos canónicos.

CSS incluye breakpoints de sidebar a 767 px, catálogos/historial a anchos intermedios, wrapping y tabla Dashboard desplazable. Sidebar móvil tiene overlay, aria-expanded, dialog, inert y gestión de foco/Escape/Tab; sus eventos se cubren con dobles DOM en SidebarTest. Sin JS la navegación tiene fallback, pero agregar participantes nuevos en Producción requiere JS; no se declara funcionamiento integral de alta sin él.

Accesibilidad existente: labels, leyendas, textos alternativos, aria-current, alert/status y foco visible en múltiples módulos. Pendiente: recorrido humano completo con teclado/lector, contraste, zoom 200%, orientación, textos largos y cámara/galería reales. El Login no representa errores ni usa los mismos rótulos de marca que los otros módulos.

Las fotos de prueba no contienen datos reales. El elemento de preview de foto sin src puede figurar como imagen incompleta en mediciones DOM aun estando oculto; eso, por sí solo, no se registra como imagen rota visible del producto. Fuentes Google y Font Awesome CDN son dependencias externas que pueden afectar carga/fallback.

## Validaciones

| Caso | Control real / resultado de auditoría |
|---|---|
| Negativos/cero | Cantidades directas y total Pan deben ser >0; coches/latas individuales ≥0 |
| Decimal donde se espera entero | Requests integer y validación adicional estricta de factor; casos negativos cubiertos |
| Overflow | Límites INT de cantidad/factor; total Pan comprobado después de convertir coches |
| IDs inexistentes | exists en HTTP, FK en BD; participantes/producto se revalidan bajo lock |
| Familia/producto incorrectos | Action rechaza producto fuera de categoría o inactivo |
| Unidad manipulada | CRUD compara con canon/caso histórico; detalles no Pan no persisten unidad enviada |
| Turno inválido | Request exige turno existente; sin IDs fijos |
| Maestro ausente/duplicado | Action rechaza en las tres familias |
| Ayudante ausente | Action rechaza en las tres familias |
| Empleado repetido | Action + PK compuesta de pivote |
| Autor manipulado | Action usa identificador autenticado; no autor del cliente |
| Campos vacíos/estructuras malformadas | Requests de negocio, errores y saneado de old input; Login tiene validación mucho menos completa |
| Fecha inválida / filtros invertidos | date_format y after_or_equal; errores GET renderizados |
| Foto inválida | Tipo/contenido/extensión/tamaño y ruta generada; almacenamiento/compensación probados |
| Historial | Categoría/unidad protegidas; snapshot no se recalcula; valores desconocidos no se inventan |
| Concurrencia | Producto/categoría/empleados/cabecera bajo transacción; tests de carreras actuales aprobados |

La ausencia de límites de negocio más pequeños que INT no prueba un bug: esos límites no fueron definidos. Sí conviene acordarlos si se necesita detectar errores humanos de magnitud. Las reglas cruzadas no se garantizan para SQL externo ni para un nuevo consumidor que omita Requests/protocolo de locks.

## Seguridad

**🔴 BLOQUEANTE antes de publicación.**

Controles confirmados:

- Grupo web con CSRF del framework y tokens en formularios mutantes; NavigationTest comprueba ausencia/token incorrecto con middleware real habilitado para esos casos.
- Auth y Gates en rutas, incluidos POST/PATCH; esconder enlaces no es el único control.
- Fillable y datos validados/whitelists en Actions; no se usa request()->all() para persistir indiscriminadamente.
- Consultas parametrizadas; búsqueda de Productos escapa comodines `%`/`_` con carácter de escape explícito.
- Blade usa salida escapada en campos de negocio; JS de producción agrega texto mediante textContent/createTextNode.
- Sesión regenerada al login e invalidada al salir; password_hash oculto en serialización del modelo.
- Fotos con nombres generados; no se acepta una ruta de archivo arbitraria suministrada por cliente; SVG excluido.
- `.env` real no versionado; `.gitignore` excluye también variantes, auth.json, claves de storage y assets/hot.

Hallazgos y límites:

1. **SEC-01 / P0:** UsuarioSeeder contiene una contraseña literal inicial. Control READ ONLY con password_verify, sin exportar hashes/contraseña, confirmó una cuenta administradora local coincidente. Debe rotarse y cambiar el procedimiento de alta segura antes de servir una instalación basada en esos datos.
2. **SEC-02 / P0:** Login sin limitación de intentos propia ni middleware throttle. No se hizo fuerza bruta sobre la cuenta real. Una defensa externa solo puede darse por resuelta cuando se configure y pruebe.
3. **SEC-03 / P0 de configuración:** entorno local APP_ENV=local/APP_DEBUG=true; es normal para desarrollo, pero no una configuración publicable. Config de destino debe usar production/debug false, secreto propio y cookies HTTPS. No se afirma que un servidor externo esté mal configurado: no fue inspeccionado.
4. Fotos se sirven por public/storage y sus URL no pasan por auth. Es una decisión de visibilidad por confirmar si contienen información de negocio sensible; no se asume que una URL pública sea automáticamente una fuga crítica.
5. Font Awesome se carga desde CDN y hay fuentes remotas. No se encontró SRI/CSP propio en esas entradas; endurecimiento y disponibilidad pendientes según política de despliegue.
6. El escaneo de archivos versionados buscó patrones de claves privadas, tokens de proveedor y claves de acceso comunes sin imprimir coincidencias sensibles. No encontró esos patrones. **No equivale a escanear toda la historia Git, binarios ni todos los formatos de secreto.** La credencial inicial del seeder sí es un hallazgo aunque ese escáner no la clasifique como token.
7. Recuperación de contraseña scaffold no implementada; no se probó envío de correo. No se asume que existan MFA, auditoría de accesos o bloqueo/desactivación de cuentas.

## Tests

### Ejecuciones finales válidas

| Verificación | Resultado |
|---|---|
| Relevantes: ProduccionPanStoreTest, ProduccionTortaBocaditoStoreTest, UnidadesCanonicasTest, AutorizacionRolesTest | **316 aprobados, 2.967 aserciones, 0 fallidos**, 9,20 s |
| Suite PHP completa | **621 aprobados, 5.682 aserciones, 0 fallidos/omitidos**, 32,00 s |
| JavaScript | **29 aprobados, 0 fallidos/omitidos** |
| npm run build | **APROBADO**, Vite 8.3.0 |
| vendor/bin/pint --test global | **FALLIDO**, 25 archivos con diferencias de formato; no se formateó |
| git diff --check | **APROBADO** |
| Comprobación adicional getAuthPasswordName/rehash | **APROBADA**, usuario ficticio persistido, transacción revertida |
| Sesión database adicional | **APROBADA**, guardar/leer marcador ficticio con DatabaseSessionHandler; no prueba de extremo a extremo productiva |
| Login inválido y presentación del error | Backend 302; **DEFECTO REPRODUCIDO:** error ausente en vista siguiente |
| Renderizado Chrome | Ocho vistas × tres anchos; no sustituye aceptación manual |

Comandos ejecutados en la copia temporal, con `.env` ficticio y PAO deshabilitado para obtener salida íntegra:

```bash
PAO_DISABLE=1 php tests/run-mariadb.php --compact --filter='ProduccionPanStoreTest|ProduccionTortaBocaditoStoreTest|UnidadesCanonicasTest|AutorizacionRolesTest'
PAO_DISABLE=1 php tests/run-mariadb.php --compact
node --test --test-isolation=none tests/JavaScript/*.mjs
npm run build
vendor/bin/pint --test
```

`git diff --check` y `git status` se ejecutaron en el checkout original. No se ejecutó composer setup/test: sus scripts pueden modificar .env, claves, migraciones o cachés del original. No se ejecutaron formateadores en modo escritura.

Archivos Feature incluidos: AutorizacionRolesTest, AutorizacionSeedersTest, DashboardTest, ExampleTest, HistorialProduccionTest, ModelsRelationshipsTest, NavigationTest, PanUnitsTest, ProduccionConcurrenciaTest, ProduccionPanStoreTest, ProduccionTortaBocaditoStoreTest, ProductosConcurrenciaTest, ProductosTest, ProductionFamilyRelationshipsTest, ProductionObservationsMigrationTest, ProxyHttpsTest, RegistrarProduccionPanActionTest, TurnoSeederTest y UnidadesCanonicasTest. Unit/ExampleTest solo comprueba true; no se presenta como cobertura de negocio.

JS: ProductosTest (6), ProduccionCantidadTest (16) y SidebarTest (7). Ejecutan scripts con dobles DOM: cubren eventos, cantidad, familia, fotos y foco simulado, no navegadores reales. El conteo usa `--test-isolation=none` para obtener casos individuales y no solo tres archivos.

Pint detectó diferencias en CargoSeeder; 19 migraciones originales de cargos/roles/turnos/categorías/unidades/roles_producción/empleados/usuarios/productos/producción/detalles/pivotes/pedidos/sessions; config/auth.php; Models RolProduccion/Empleado/Cargo; y Controller base. Son 25 archivos en total. Predominan indentación, llaves, importaciones y salto final. No se atribuye a esta auditoría ni se confunde con fallos funcionales.

### Incidencias del entorno de verificación

- Primer MariaDB temporal: socket bloqueado por sandbox (`Operation not permitted`); se repitió con autorización. No es fallo de aplicación.
- La copia deliberadamente no tenía .env real: intentos iniciales produjeron MissingAppKeyException; con clave ficticia pero sin archivo .env hubo 316 avisos de file_get_contents. Se diagnosticó con ExampleTest. Se preparó solo configuración ficticia temporal y se repitió la selección: 316 aprobados sin esos avisos; después pasó la suite completa. Ningún código se corrigió para hacer pasar los tests.
- La primera ejecución con PAO no entregó resumen y se interrumpió; otra ejecución sin clave válida agotó su límite. No se cuentan como aprobadas ni se inventa un número de fallos sin resumen válido.
- La acumulación de instancias desechables agotó espacio temporal y dos intentos de diagnóstico no pudieron inicializar InnoDB (`error 28`, espacio insuficiente). Se eliminaron exclusivamente datos ficticios propios detenidos, conservando logs, y se completaron las corridas válidas.
- Build emitió aviso de paquete opcional fontaine para fallbacks de fuentes. La compilación salió con código 0; no se instaló ni cambió ninguna dependencia.

Las limitaciones de setup son evidencia de requisitos de la infraestructura, no motivos para borrar los resultados finales ni afirmar que la base local recibió pruebas destructivas. Las únicas comprobaciones fallidas del checkout que permanecen son Pint global y el defecto reproducido de feedback Login; las corridas finales de las suites son verdes.

## Preparación para despliegue

**🔴 NO LISTO PARA PUBLICACIÓN. Preparación acreditada: 4,25/10 = 42,50%.**

`.env.example` contiene valores de desarrollo: APP_ENV local, APP_DEBUG true, APP_URL localhost, APP_TIMEZONE America/Lima, DB_CONNECTION sqlite, SESSION_DRIVER/CACHE_STORE/QUEUE_CONNECTION database. Variables relevantes: APP_KEY propia del destino; APP_ENV/DEBUG/URL/TIMEZONE/LOCALE; DB_CONNECTION/HOST/PORT/DATABASE/USERNAME/PASSWORD; SESSION_DRIVER/LIFETIME/ENCRYPT/DOMAIN y SESSION_SECURE_COOKIE; CACHE_STORE; QUEUE_CONNECTION; FILESYSTEM_DISK; LOG_CHANNEL/LEVEL; MAIL y, solo si se elige, almacenamiento externo. No se copian ni muestran valores secretos.

- **PHP/Composer:** runtime según lock y extensiones de MariaDB/imagen/archivos; instalar desde lock, distinguir dependencias productivas y de desarrollo. Las versiones de la máquina del auditor no prueban el servidor.
- **Motor BD:** elegir MariaDB compatible y probar el esquema real; no usar SQLite del ejemplo como promesa de compatibilidad. Base local alineada, pero ningún destino inspeccionado. No ejecutar fresh/reset/refresh sobre datos de negocio.
- **Migraciones:** 28 aplicadas localmente según tabla migrations; nuevas instalaciones requieren esquema y catálogos controlados. Los tests prueban creación/migraciones y protecciones; no una actualización de un backup productivo real.
- **Seeders:** categoría/unidades/turnos/roles de producción/roles/cargos deben aprovisionarse con revisión. DatabaseSeeder llama también empleados y UsuarioSeeder, por lo que no debe tratarse como carga productiva inocua. ProduccionDesarrolloSeeder está guardado a local/testing y fuera de DatabaseSeeder; no usar para producción.
- **Credenciales:** rotar la inicial y aprovisionar operadores antes de abrir acceso. Seeder idempotente no cambia contraseña existente.
- **Assets:** build aprobado, manifest generado; public/build local existe y está ignorado por Git; public/hot ausente. Distribuir artefactos compilados del mismo release y verificar @vite sin servidor de desarrollo. No se generaron assets nuevos en el checkout original.
- **Node:** el lock de Vite/plugin declara `^20.19.0 || >=22.12.0`; aquí se probó Node 26.11.1. No se certifica cualquier versión por existir package.json.
- **Fotos/storage:** enlace public/storage existe localmente. Config public apunta a storage/app/public; permisos de escritura y exposición estática deben verificarse en destino, además de copia/backup de imágenes. No usar chmod 777 como procedimiento automático.
- **Sesión/cache:** tablas incluidas; driver database configurado localmente y roundtrip de sesión aislado aprobado. Probar login/logout en sesiones reales del destino, secure cookies, expiración, varias solicitudes y caché tras el procedimiento de publicación.
- **Colas:** configuración database y tablas existen; no se hallaron Jobs/dispatch de negocio propios. Worker no es requisito funcional demostrado para estas pantallas; será necesario si se habilitan tareas asíncronas. No se confunde el script dev con supervisor productivo.
- **Logs:** configuración stack/single; falta comprobar permisos, rotación/retención y observabilidad en destino. No se leyó ni volcó el contenido de logs reales por riesgo de datos sensibles.
- **Servidor web:** public/index.php y .htaccess presentes. Falta destino verificable con document root public, FPM/runtime, protección de .env/.git, límites de upload y rutas.
- **Proxy/HTTPS:** confianza restringida a loopback probada por ProxyHttpsTest. Un balanceador remoto requerirá configuración específica; no ampliar confianza sin conocer topología. No hay dominio/certificado/renovación verificados ni ensayo de HTTPS real.
- **Backup/rollback:** varios down se niegan con historia; plan debe conservar esquema compatible, restauración de datos y fotos y release anterior. No existe runbook ensayado ni prueba de restauración productiva.

## Riesgos técnicos

| ID | Hallazgo | Estado de evidencia | Impacto / clasificación |
|---|---|---|---|
| R01 | Contraseña predeterminada vigente en administrador local | COMPROBADO por comparación sin exponer datos | P0; acceso con credencial conocida si se publica esa instalación |
| R02 | Login sin throttling del repositorio | COMPROBADO por rutas/controller | P0 antes de exposición; control de borde no acreditado |
| R03 | Destino production/servidor/dominio sin validar | NO COMPROBADO, no se afirma que esté configurado mal | P0 de preparación |
| R04 | Rollback de esquema bloqueado intencionalmente con datos | COMPROBADO por migraciones/tests | P0: necesita estrategia/backup antes de actualizar |
| R05 | Catálogo solo Pan y ninguna cuenta Operador | COMPROBADO por lectura local | P0 para operar el alcance completo acordado |
| R06 | Mensajes de login invisibles y recuperación aparente sin flujo | COMPROBADO en vista/sonda aislada | P1: feedback y claridad funcional |
| R07 | Horas/estado incompletos, sin creación de lotes | COMPROBADO en esquema/Requests | P1 de cierre; no declarar trazabilidad temporal inexistente |
| R08 | Reglas críticas no expresadas totalmente en SQL | COMPROBADO | P1/P2 según garantía: nuevos escritores deben seguir protocolo |
| R09 | Serialización por categoría e índices sin fecha compuesta | COMPROBADO en código/metadatos; sin carga masiva medida | P2; posible degradación al crecer |
| R10 | Doble envío crea dos lotes válidos | Deducido del escritor que siempre crea detalle; no prueba de duplicado local | P2; acordar idempotencia de envío sin prohibir lotes legítimos repetidos |
| R11 | Upload público y terceros para fuentes/iconos | COMPROBADO; sensibilidad/política por definir | P2; visibilidad, disponibilidad y endurecimiento |
| R12 | Factoría obsoleta/comentarios e inconsistencia de formato | COMPROBADO; suites actuales no dependen de la factoría | P1 de calidad, sin fallo funcional actual |

### Discrepancias documentales

- `AGENTS.md` indica que getAuthPasswordName sigue pendiente hasta implementarlo/probarlo. El método **ya existe** y esta auditoría comprobó rehash persistido ficticio. No se actualizó ese archivo por la restricción de escritura.
- `docs/UNIDADES_CANONICAS_PRODUCTOS.md` documenta migración/seeder locales pendientes en su fecha de intervención. **Hoy todas las migraciones están aplicadas y ambos códigos existen**; sus conteos antiguos no representan la base auditada.
- `docs/PRODUCCION_UNIDADES_POR_FAMILIA.md` describe selección manual y riesgo de aceptar unidad arbitraria. El código posterior tiene ResolverUnidadProducto, mapeo por código y pruebas de rechazo: esa limitación ya no describe el nuevo CRUD.
- `docs/PRODUCTOS_PANES_POR_LATA.md` refiere conteos locales anteriores con un producto; hoy son dos. No invalida los snapshots ni sus pruebas, sí impide reutilizar su inventario sin verificar.
- `docs/MEJORA_VISUAL_HISTORIAL.md` conserva aceptación humana/sesiones reales pendientes. Sus 621/29 coinciden con la nueva ejecución; no se reutilizaron como resultado propio sin correrlos.
- Comentarios de Requests todavía dicen “permisos pendientes/fuera de alcance”, aunque `routes/web.php` y Gates los aplican. Un comentario de PanUnitsTest dice que no hay escritor HTTP, aunque ya existe. La fuente de verdad es el código ejecutable actual.

## Funcionalidades terminadas

**IMPLEMENTADO Y COMPROBADO dentro del alcance aislado indicado:**

- Regla Pan en latas/coches y snapshot; independencia del factor posterior.
- Cantidad directa Torta/Bocadito, sin conversiones de Pan.
- Exactamente un Maestro y mínimo un Ayudante por lote en las tres familias.
- Crear/editar/activar/desactivar productos con control histórico y unidades canónicas.
- Consultar Historial con filtros/paginación y presentación por familia/equipo/observaciones/autores.
- Selección real de turnos sin IDs constantes; hora final Pan en altas.
- Login/hash/rehash, logout y autorización Administrador/Operador en backend.
- Transacciones/locks y regresiones de concurrencia del flujo actual.
- Build y suites PHP/JS.

Esta lista identifica capacidades técnicas verificadas; no otorga aceptación integral de sus pantallas ni despliegue al módulo completo.

## Funcionalidades parciales

- Producción de las tres familias como operación diaria aceptada: catálogo real no Pan/cuentas y aceptación pendientes.
- Login: mecanismo funcional, feedback incompleto, sin limitación de intentos.
- Usuarios: roles/Gates implementados; aprovisionamiento y ciclo de vida incompletos.
- Horas/estado: solo fecha de negocio y finalización manual Pan.
- Dashboard: información real de Pan, cobertura de otras familias pendiente de alcance.
- UX/responsive: CSS y renderizado aislado, sin aceptación humana multidispositivo.
- Calidad: tests/build verdes, Pint global fallido, scaffold/comentarios obsoletos.
- Deployment: piezas del runtime presentes, entorno/seguridad/rollback sin acreditar.

## Funcionalidades faltantes

- Flujo de administrar cuentas/cambiar o revocar credenciales; puede ser procedimiento seguro antes de un CRUD visual.
- Cuentas operativas y productos reales Torta/Bocadito en la instalación local auditada.
- Inicio/creación/finalización para todas las familias y estados/botón finalizar si se confirma ese ciclo de negocio. No confundir con el campo Pan ya existente.
- Recuperación de contraseña funcional desde su botón; alternativa válida inicial: retirar la promesa y definir soporte administrativo.
- Entorno de servidor/dominio/HTTPS productivo verificado y runbook de actualización/restauración.

Pedidos y stock tienen estructura parcial o contexto, pero no son módulos operativos demostrados ni requisitos puntuados de esta gestión de producción. Exportaciones, gráficos avanzados, roles por turno y MFA no se inventan como obligaciones del 100% solicitado.

## Pendientes P0

**6 tareas bloqueantes antes de publicar el alcance completo. Ninguna fue implementada durante la auditoría.**

| ID | Tarea / criterio de salida | Archivos o módulos | Complejidad | Estimación |
|---|---|---|---|---|
| P0-01 | Rotar credencial predeterminada en la instalación que se vaya a usar; eliminar el alta insegura mediante secreto literal y comprobar login con nueva credencial sin documentarla | UsuarioSeeder, aprovisionamiento Usuario, configuración segura del destino | MEDIA | 1–3 h |
| P0-02 | Definir y verificar límite de intentos de Login en aplicación o borde, con error/espera controlados y prueba repetible | routes/web.php, LoginController o proxy; pruebas auth | MEDIA | 1–3 h |
| P0-03 | Preparar runtime/BD/servidor de destino: production/debug false, secreto propio, document root public, permisos acotados, logs y restricción de archivos sensibles; instalar desde locks | config, .env de destino, public, storage, bootstrap/cache, FPM/web | ALTA | 4–8 h |
| P0-04 | Configurar dominio/HTTPS y cookies secure; validar redirecciones, assets/fotos y confianza de proxies según topología real | DNS/TLS/proxy, bootstrap/app.php, config/session.php | MEDIA | 2–4 h + espera de DNS |
| P0-05 | Preparar backup de BD/fotos, probar restauración y ensayar actualización/rollback conservador en copia; revisar migraciones/seeders selectivos sin reset de datos | migrations, seeders, storage, releases y runbook | ALTA | 4–8 h |
| P0-06 | Acordar/provisionar cuentas operativas y catálogo real de las tres familias; aceptación mínima del circuito login→guardar→historial→logout en staging con sesiones database y fotos reales de prueba | Usuarios/empleados/catálogos, Productos, Producción, Historial | MEDIA | 3–6 h con responsable operativo |

P0-03/04/05 son preparación no acreditada, no afirmaciones de fallos de un servidor no inspeccionado. P0-06 no obliga a cuenta fija por turno: requiere una decisión operativa y acceso seguro suficiente para el equipo real.

## Pendientes P1

**6 tareas necesarias antes de considerar cerrado el proyecto.**

| ID | Tarea / criterio de salida | Archivos o módulos | Complejidad | Estimación |
|---|---|---|---|---|
| P1-01 | Mostrar errores y conservar username seguro en Login; validar tipo/longitud; corregir promesa del botón de recuperación y unificar marca acordada | LoginController, auth/login.blade.php, login CSS/JS, tests auth | BAJA | 1–3 h |
| P1-02 | Acordar ciclo temporal: definir alcance de inicio/creación/finalización/estado para todas las familias y, si se requiere, implementarlo con histórico desconocido preservado y pruebas | Produccion/detalles, migrations, Requests/Actions, vistas/historial | ALTA | Definición 1–2 h; implementación 8–16 h |
| P1-03 | Definir ciclo de vida de cuentas: alta, cambio de clave, revocación/desactivación y responsable; implementar mecanismo mínimo seguro y probarlo | Usuario, autenticación, herramienta administrativa/Requests/rutas si aplica | MEDIA | 4–8 h; CRUD visual completo opcional aparte |
| P1-04 | Aceptación humana desktop/tablet/móvil, teclado, zoom, mensajes, errores, estados vacíos, sidebar, fechas y cámara/galería; resolver defectos reproducidos y registrar evidencias | Vistas/CSS/JS de los seis módulos y sidebar | MEDIA | 4–8 h, según defectos |
| P1-05 | Acordar garantías de integridad y protocolo de nuevos escritores; tratar duplicidad de identidades/cabeceras antes de añadir restricciones compatibles, sin inventar backfills | Catálogos, produccion, migrations y Actions | MEDIA | 4–8 h |
| P1-06 | Dejar comprobación de calidad reproducible: resolver 25 diferencias Pint en trabajo posterior, retirar scaffold inconsistente/comentarios obsoletos y añadir regresiones permanentes de defectos auth/rehash/sesión detectados | 25 archivos de Pint, UserFactory, Requests/tests/guía de pruebas | MEDIA | 3–6 h |

El ciclo temporal no fue completamente definido por un contrato de estados; su estimación depende de esa decisión. Declararlo no aplicable requiere acuerdo explícito del alcance, no subir la puntuación por decisión unilateral del auditor.

## Pendientes P2

**7 tareas importantes que pueden esperar tras una primera versión segura.**

| ID | Tarea | Archivos o módulos | Complejidad | Estimación |
|---|---|---|---|---|
| P2-01 | Medir consultas con volumen real y evaluar índice por fecha/categoría/turno y alcance de locks antes de optimizar | Dashboard, Query Historial, Actions, índices | MEDIA | 3–6 h |
| P2-02 | Acordar si Dashboard resume las tres familias; ampliar métricas sin sumar unidades incompatibles | DashboardController, vista/tests Dashboard | MEDIA | 3–6 h |
| P2-03 | Acordar si nombres históricos deben congelarse; mantener comportamiento actual o diseñar snapshot sin reconstruir nombres pasados | Productos/personas, detalles, Historial | MEDIA | Decisión 1 h; cambio opcional 4–8 h |
| P2-04 | Definir privacidad de fotos y política de servido/retención; endurecer si las imágenes deben requerir auth | Storage, fotos Torta, servidor | MEDIA | 2–5 h |
| P2-05 | Revisar dependencias externas de fuentes/iconos y fallback, integridad/CSP según política; comprobar carga lenta/sin CDN | login/sidebar CSS, Login Blade, Vite | BAJA | 2–4 h |
| P2-06 | Documentar y ensayar seeders selectivos: errores parciales de identidades, ambigüedad, repetición y ejecución sin escritores concurrentes | UnidadMedidaSeeder y seeders de catálogos/runbook | MEDIA | 2–4 h |
| P2-07 | Acordar idempotencia de envíos frente a doble clic/reintento, conservando lotes legítimos repetidos; implementar si el riesgo operativo lo exige | Producción JS/Requests/Actions y BD | MEDIA | 3–6 h |

P2-02 completa la cobertura reducida del Dashboard si ese alcance se confirma. P2-03/04/05/07 dependen de política/necesidad y no se presentan como bugs ya comprobados en operación local.

## Pendientes P3

**4 mejoras futuras opcionales, fuera de los requisitos necesarios para el 100% de esta rúbrica.**

| ID | Mejora | Archivos o módulos | Complejidad | Estimación |
|---|---|---|---|---|
| P3-01 | CRUD visual completo de usuarios/empleados si el procedimiento seguro inicial resulta insuficiente | Nuevos flujos administrativos sobre modelos existentes | ALTA | 12–24 h |
| P3-02 | Recuperación autoservicio de contraseña con canal aprobado; no reutilizar ciegamente broker scaffold | Auth, notificaciones y vistas/rutas | ALTA | 6–12 h |
| P3-03 | Gráficos, exportaciones y reportes avanzados con requisitos concretos | Dashboard/Historial, Chart.js si se decide usar | MEDIA | 4–12 h |
| P3-04 | Automatizar pipeline y pruebas de navegador/aceptación repetibles; evaluar módulos Pedidos/Stock como proyectos separados | CI, infraestructura de tests y módulos fuera de alcance | ALTA | Pipeline 6–12 h; módulos requieren estimación propia |

## Qué falta exactamente para llegar al 100%

| Área | Brecha | Cierre verificable |
|---|---:|---|
| A | 6,00 | Aceptar flujos reales de las tres familias; cerrar contrato temporal/estado de P1-02 y probarlo |
| B | 1,00 | Aceptación operativa de listado/alta/edición/estado con catálogo real, errores e historia protegida |
| C | 0,50 | Aceptación de filtros/detalles/paginación/fotos/textos largos en dispositivos y teclado |
| D | 0,75 | Acordar/completar cobertura de métricas por familia y aceptar responsive; gráficos no obligatorios |
| E | 2,50 | Corregir feedback de Login y preparar usuarios/lifecycle acordado, sin imponer relación usuario-turno |
| F | 1,50 | Resolver credencial y control de intentos; cerrar garantías de integridad necesarias para escritores autorizados |
| G | 2,25 | Aceptación manual transversal, mensajes/auth, accesibilidad y consistencia; corregir defectos encontrados |
| H | 1,00 | Formato global limpio y mantenimiento de scaffold/comentarios/contratos cubiertos |
| I | 5,75 | Runtime/servidor/dominio/TLS/storage/sesión-cache verificados, migración/seed seguro y despliegue/restauración ensayados |
| **Total pendiente** | **21,25** | **Evidencia nueva de cierre por subapartado; no suma de horas ni cantidad de archivos** |

Los mismos trabajos pueden mejorar varias áreas; las brechas se suman por criterios, no se asignan puntos extra por duplicar tareas. Las estimaciones son esfuerzo aproximado de una persona con acceso disponible; no incluyen esperas de aprobaciones, hosting/DNS o redefinición de requisitos. Tampoco garantizan que no aparezcan defectos nuevos en aceptación.

## Orden recomendado de trabajo

1. **P0-01/02:** credenciales y protección del Login antes de cualquier exposición. Complejidad MEDIA; 2–6 h en conjunto.
2. **P0-03/04/05:** preparar staging/destino, TLS y respaldo/restauración; ensayar instalación/actualización desde locks y copia de datos. Complejidad ALTA; 10–20 h más esperas.
3. **P1-01 + P0-06:** feedback Login, cuentas/catalogación y aceptación mínima de los tres circuitos completos. BAJA/MEDIA; 4–9 h.
4. **P1-02/03:** acordar horas/estado y cuentas; implementar exclusivamente lo aprobado en una tarea posterior. MEDIA/ALTA; 5–10 h de mecanismos mínimos más 8–16 h si se confirma ciclo completo.
5. **P1-04/05/06 y P2-02:** aceptación completa, integridad/mantenibilidad y alcance Dashboard. MEDIA; 14–28 h según decisiones/defectos.
6. Tras una versión segura y aceptada, priorizar P2 por evidencia de uso/volumen. P3 permanece opcional y no bloquea esta rúbrica.

Este orden es una recomendación de trabajo futuro, no autorización de implementación dentro de esta auditoría.

## Checklist antes del despliegue

- [ ] Cerrar P0-01: credencial inicial rotada y alta segura, sin secretos en Git/docs/logs.
- [ ] Cerrar P0-02: limitación de Login comprobada y mensajes comprensibles.
- [ ] Confirmar alcance de cuentas/turnos y crear accesos operativos sin IDs constantes.
- [ ] Catálogos Pan/Torta/Bocadito, LATA/UNIDAD, Mañana/Noche y equipo listos; ningún seeder de desarrollo en producción.
- [ ] PHP/extensiones/Composer y motor MariaDB de destino compatibles con lock y migraciones.
- [ ] APP_ENV production, APP_DEBUG false, APP_URL real HTTPS, APP_TIMEZONE America/Lima y clave propia del entorno.
- [ ] Document root public; .env/.git/archivos privados inaccesibles por HTTP; permisos de storage/bootstrap/cache acotados.
- [ ] Dominio/TLS y renovación; cookies Secure/HttpOnly/SameSite verificadas tras proxy real.
- [ ] Backup consistente de BD y fotos; restauración probada; rollback de release compatible con schema y sus down protegidos.
- [ ] Migraciones revisadas en copia/staging; aplicar solo necesarias en destino, sin fresh/reset/refresh ni backfill ficticio.
- [ ] Seeders selectivos probados, sin alta de contraseña conocida ni ambigüedades; no confundir DatabaseSeeder con carga inocua.
- [ ] Build del mismo release, manifest accesible, sin public/hot ni dependencia del dev server.
- [ ] Storage público/enlace/URLs de fotos operativos según política; upload válido/inválido y limpieza comprobados.
- [ ] Login/logout y sesiones database probados en peticiones reales; cache y logs verificados.
- [ ] Circuitos Pan 12×30=360, Torta 8 y Bocadito 150 aceptados; edición futura no cambia snapshot previo.
- [ ] Operador no administra productos; usuario ajeno/no autenticado no accede por URL directa.
- [ ] Maestro/Ayudantes, fechas, IDs manipulados y errores revisados; aceptación manual móvil/tablet/escritorio y teclado.
- [ ] Suites y build del release pasan; Pint global resuelto si se exige cierre total de calidad; evidencias guardadas sin datos sensibles.
- [ ] Logs/backup/monitoreo y responsable de incidentes definidos; worker solo si se necesitan trabajos asíncronos.

## Checklist después del despliegue

- [ ] Verificar dominio/HTTPS, cookies, redirects y ausencia de debug/secretos mediante consultas no destructivas.
- [ ] Comprobar Login, Dashboard, Productos según rol, Producción e Historial con cuentas de prueba aprobadas.
- [ ] Confirmar sesión/logout y no recuperar datos sensibles con Atrás/BFCache sin revalidar acceso.
- [ ] Verificar fecha de negocio, turnos, snapshots y cantidades; conciliar con registros de prueba autorizados.
- [ ] Confirmar assets compilados, fotos y errores/estados vacíos en móvil/escritorio.
- [ ] Revisar errores/latencia/logs con acceso restringido y política de retención; no copiar payloads sensibles al informe.
- [ ] Verificar backup programado/restaurable, espacio de disco y conservación de imágenes.
- [ ] Registrar release, migraciones realmente ejecutadas y resultado de aceptación; mantener disponible el plan de recuperación.

## Resultado final

**Estado general: ROJO para despliegue. Completitud 78,75%; faltante 21,25%; preparación para despliegue 42,50%.**

El sistema tiene un núcleo funcional implementado y probado con cobertura real de las tres familias. No es un prototipo vacío ni una instalación aprobada para publicar. La principal brecha inmediata es seguridad/provisión/entorno; la de cierre incluye feedback, ciclo temporal y aceptación humana.

Pendientes inventariados: **6 P0, 6 P1, 7 P2 y 4 P3**. Tests finales: **621 PHP aprobados / 0 fallidos**, **29 JS aprobados / 0 fallidos**, build aprobado. Pint global fallido por 25 archivos previos; no se corrigieron.

**CÓDIGO MODIFICADO:** ninguno. **DOCUMENTACIÓN MODIFICADA:** únicamente este informe de auditoría, por solicitud expresa; Vault y mejora_asignado sin cambios. **PRUEBAS:** ejecutadas en copia/BD/almacenamiento aislados, con lectura local exclusivamente READ ONLY. **PENDIENTES:** los anteriores; no se presentó una recomendación ni un ensayo aislado como implementación desplegada. Las autorizaciones necesarias para esas comprobaciones se concedieron; no queda una autorización pendiente para este entregable.

Control final comprobado del checkout: `git status` muestra únicamente el informe nuevo; `git status --short` devuelve `?? docs/AUDITORIA_COMPLETA_PANADERIA_RS.md`. Diff de archivos versionados e índice sin cambios; comprobación de whitespace del diff y del informe aprobada. La comparación de tamaño/mtime de archivos originales, incluidos ignorados, no detectó cambios, eliminaciones ni archivos añadidos aparte del informe. No se hizo commit ni push.
