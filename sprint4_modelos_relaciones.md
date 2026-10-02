# Sprint 4 — Modelos y Relaciones

> Actualización posterior, 2026-10-02: las secciones 1–21 se conservan como la auditoría original. El estado vigente de la implementación autorizada, las decisiones resueltas y sus pruebas están en **Decisiones de negocio confirmadas después de la auditoría**, al final. Las propuestas y pendientes originales no sustituyen ese seguimiento.

Fecha de auditoría: **2026-10-02**. Duración planificada: **2 semanas**. Etapa: **ANÁLISIS**; toda corrección propuesta permanece **PENDIENTE de revisión y autorización expresa**.

Cronología oficial indicada por el usuario: Sprint 1, evaluación/análisis de datos; Sprint 2, migraciones y estructura; Sprint 3, Figma y vistas; Sprint 4, modelos y relaciones.

## 1. Resumen ejecutivo

Se leyeron íntegramente las 21 migraciones y los 18 modelos y se contrastaron con el esquema activo de MariaDB mediante consultas de metadatos. Hay **25 tablas activas: 18 de negocio, 6 técnicas declaradas por migraciones y la tabla técnica `migrations` creada por Laravel**. Las 21 migraciones locales figuran en el historial técnico, todas en batch 1; no hay nombres registrados sin archivo ni archivos sin registro. Existen **28 foreign keys**, 27 de negocio y una de sesiones.

Eloquent define **44 relaciones: 27 belongsTo, 13 hasMany y 4 hasOne**. Todas las FK de negocio tienen una relación de pertenencia con tabla, columna y clave destino correctas. Hay tres relaciones hasOne de Producción cuya cardinalidad no está garantizada por la BD, y tres modelos de asociación que siguen esperando `id` aunque sus tablas usan PK compuestas. No falta ningún modelo para una tabla de negocio existente.

La estructura permite registrar fecha, usuario que registra, producto, categoría, pan con unidad y turno, torta con forma/foto, bocaditos con cantidad, y participantes por detalle con rol de producción. El módulo funcional sigue pendiente: `ProduccionController::store()` no guarda nada. Las pantallas usan datos estáticos.

Antes de programar Producción deben resolverse cardinalidad de cabecera/detalle, manejo de las asociaciones compuestas, unidades/fracciones y equivalencias, observaciones, estados y precisión temporal. Foto solo para Torta ya es una decisión funcional del usuario; no necesita reabrirse, pero aún no está validada en backend.

### Evidencia y límites

- Entorno instalado: Laravel **13.25.0**, PHP **8.5.10**, MariaDB **13.0.2**. `composer.json` permite Laravel `^13.17` y PHP `^8.3`; no se cambiaron dependencias.
- Se usaron `getTables`, `getColumns`, `getIndexes`, `getForeignKeys`, `SHOW CREATE TABLE`, versión del servidor y nombres/batches de `migrations`. No se leyeron filas de usuarios, contraseñas, sesiones, producción, pedidos o catálogos. Existencia de tablas no acredita datos cargados ni comportamiento de CRUD.
- El primer acceso de metadatos dentro del sandbox falló con SQLSTATE HY000/código 2002; la lectura autorizada fuera del sandbox funcionó. No se ejecutaron migraciones, seeders ni escrituras SQL.
- La comparación lógica revisó tablas, columnas, tipos, nullables, defaults, PK, índices, FK, acciones y CHECK. No se detectó divergencia entre esas definiciones y las migraciones actuales. No constituye un diff byte a byte de DDL, collation o configuración del servidor.
- Se inspeccionaron controllers, rutas, Blade, JS, seeders, factory, tests, configuración relevante, código del framework y notas existentes. Hubo una segunda búsqueda global antes del cierre.
- El Vault conserva notas de 2026-09-26 que describen una migración correctiva y código ausentes en este checkout y en su historial de migraciones. No se considera que esos cambios estén implementados hoy ni se infiere la causa de la diferencia.
- **Contraste visual de Figma PENDIENTE:** el usuario proporcionó el [archivo real de Panadería RS](https://www.figma.com/design/hyyMZ7prbgIkRWWiYTmrPw/Panader%C3%ADa-RS---Wireframes--Sprint-2-?node-id=0-1). La herramienta web no pudo abrirlo y la integración de Figma no está instalada ni conectada. Las notas de pantalla están vacías y las imágenes referidas no están presentes en el Vault. Se registra el enlace real; la matriz usa los requisitos expresos y las vistas como evidencia de UI, sin afirmar revisión visual del archivo Figma.
- Los cambios de código y archivos sin seguimiento que ya estaban en `git status` provienen del trabajo anterior. Se preservaron; el único archivo creado por esta fase en el repositorio es este informe. `mejora_asignado.md` no se reemplazó ni modificó durante esta auditoría.

## 2. Inventario de tablas

En la matriz, CORRECTO significa correspondencia estructural revisada; no CRUD probado. Las tablas técnicas no requieren modelo de negocio. REVISAR no implica renombrar ni eliminar.

| Tabla | Migración creadora en database/migrations | Primary Key | Foreign Keys | Modelo | Estado |
| --- | --- | --- | --- | --- | --- |
| `cache` | `0001_01_01_000001_create_cache_table.php` | key | — | Sin modelo de negocio | CORRECTO |
| `cache_locks` | `0001_01_01_000001_create_cache_table.php` | key | — | Sin modelo de negocio | CORRECTO |
| `cargos` | `2026_09_14_202615_create_cargos_table.php` | id | — | Cargo | CORRECTO |
| `categorias` | `2026_09_14_202618_create_categorias_table.php` | id | — | Categoria | CORRECTO |
| `detalle_bocadito` | `2026_09_14_202629_create_detalle_bocadito_table.php` | id | produccion_id → produccion.id; producto_id → productos.id | DetalleBocadito | CORRECTO |
| `detalle_bocadito_empleado` | `2026_09_14_202630_create_detalle_bocadito_empleado_table.php` | detalle_bocadito_id, empleado_id | detalle_bocadito_id → detalle_bocadito.id; empleado_id → empleados.id; rol_produccion_id → roles_produccion.id | DetalleBocaditoEmpleado | POSIBLE TABLA PIVOT / REVISAR |
| `detalle_pan` | `2026_09_14_202625_create_detalle_pan_table.php` | id | produccion_id → produccion.id; producto_id → productos.id; turno_id → turnos.id; unidad_medida_id → unidades_medida.id | DetallePan | CORRECTO |
| `detalle_pan_empleado` | `2026_09_14_202626_create_detalle_pan_empleado_table.php` | detalle_pan_id, empleado_id | detalle_pan_id → detalle_pan.id; empleado_id → empleados.id; rol_produccion_id → roles_produccion.id | DetallePanEmpleado | POSIBLE TABLA PIVOT / REVISAR |
| `detalle_pedidos` | `2026_09_14_202632_create_detalle_pedidos_table.php` | id | pedido_id → pedidos.id; producto_id → productos.id; unidad_medida_id → unidades_medida.id | DetallePedido | CORRECTO |
| `detalle_torta` | `2026_09_14_202627_create_detalle_torta_table.php` | id | produccion_id → produccion.id; producto_id → productos.id | DetalleTorta | CORRECTO |
| `detalle_torta_empleado` | `2026_09_14_202628_create_detalle_torta_empleado_table.php` | detalle_torta_id, empleado_id | detalle_torta_id → detalle_torta.id; empleado_id → empleados.id; rol_produccion_id → roles_produccion.id | DetalleTortaEmpleado | POSIBLE TABLA PIVOT / REVISAR |
| `empleados` | `2026_09_14_202621_create_empleados_table.php` | id | cargo_id → cargos.id | Empleado | CORRECTO |
| `failed_jobs` | `0001_01_01_000002_create_jobs_table.php` | id | — | Sin modelo de negocio | CORRECTO |
| `jobs` | `0001_01_01_000002_create_jobs_table.php` | id | — | Sin modelo de negocio | CORRECTO |
| `job_batches` | `0001_01_01_000002_create_jobs_table.php` | id | — | Sin modelo de negocio | CORRECTO |
| `migrations` | Laravel: repositorio de migraciones; no archivo local propio | id | — | Sin modelo de negocio | CORRECTO |
| `pedidos` | `2026_09_14_202631_create_pedidos_table.php` | id | registrado_por_usuario_id → usuarios_sistema.id | Pedido | CORRECTO |
| `produccion` | `2026_09_14_202624_create_produccion_table.php` | id | registrado_por_usuario_id → usuarios_sistema.id | Produccion | REVISAR |
| `productos` | `2026_09_14_202623_create_productos_table.php` | id | categoria_id → categorias.id; unidad_medida_id → unidades_medida.id | Producto | CORRECTO |
| `roles` | `2026_09_14_202616_create_roles_table.php` | id | — | Rol | CORRECTO |
| `roles_produccion` | `2026_09_14_202620_create_roles_produccion_table.php` | id | — | RolProduccion | CORRECTO |
| `sessions` | `2026_09_14_205442_create_sessions_table.php` | id | user_id → usuarios_sistema.id | Sin modelo de negocio | CORRECTO |
| `turnos` | `2026_09_14_202617_create_turnos_table.php` | id | — | Turno | CORRECTO |
| `unidades_medida` | `2026_09_14_202619_create_unidades_medida_table.php` | id | — | UnidadMedida | CORRECTO |
| `usuarios_sistema` | `2026_09_14_202622_create_usuarios_sistema_table.php` | id | empleado_id → empleados.id; rol_id → roles.id | Usuario | CORRECTO |

### Definición completa de columnas y restricciones

La PK `id` de negocio es BIGINT UNSIGNED autoincremental. `foreignId` es BIGINT UNSIGNED. Salvo lo indicado, columnas NOT NULL, sin default explícito, sin enum ni generación. Los nombres físicos de tipos que siguen son los leídos de MariaDB. Un default NULL en columna nullable coincide con la ausencia de valor; los timestamps de Eloquent son técnicos, no fechas de negocio.

En cada tabla se enumeran todos los índices reales. MariaDB crea índices de soporte de FK; no todos fueron declarados separadamente con `index()` en las migraciones. Las políticas de borrado están en la sección 4. Ninguna tabla usa `deleted_at`, softDeletes o ENUM. No hay restricciones cruzadas que impidan asignar un Producto de categoría Torta a `detalle_pan`, ni exclusividad de categoría por cabecera.

#### cache

| Columna | Tipo físico | Nullable | Default | Otro |
| --- | --- | --- | --- | --- |
| `key` | varchar(255) | No | Sin default explícito | — |
| `value` | mediumtext | No | Sin default explícito | — |
| `expiration` | bigint(20) | No | Sin default explícito | — |

Índices: `INDEX(expiration)`; `PRIMARY(key)`.

#### cache_locks

| Columna | Tipo físico | Nullable | Default | Otro |
| --- | --- | --- | --- | --- |
| `key` | varchar(255) | No | Sin default explícito | — |
| `owner` | varchar(255) | No | Sin default explícito | — |
| `expiration` | bigint(20) | No | Sin default explícito | — |

Índices: `INDEX(expiration)`; `PRIMARY(key)`.

#### cargos

| Columna | Tipo físico | Nullable | Default | Otro |
| --- | --- | --- | --- | --- |
| `id` | bigint(20) unsigned | No | Sin default explícito | AUTO_INCREMENT |
| `nombre_cargos` | varchar(100) | No | Sin default explícito | — |

Índices: `PRIMARY(id)`.

#### categorias

| Columna | Tipo físico | Nullable | Default | Otro |
| --- | --- | --- | --- | --- |
| `id` | bigint(20) unsigned | No | Sin default explícito | AUTO_INCREMENT |
| `nombre_categorias` | varchar(100) | No | Sin default explícito | — |

Índices: `PRIMARY(id)`.

#### detalle_bocadito

| Columna | Tipo físico | Nullable | Default | Otro |
| --- | --- | --- | --- | --- |
| `id` | bigint(20) unsigned | No | Sin default explícito | AUTO_INCREMENT |
| `produccion_id` | bigint(20) unsigned | No | Sin default explícito | — |
| `producto_id` | bigint(20) unsigned | No | Sin default explícito | — |
| `cantidad` | int(11) | No | Sin default explícito | — |

Índices: `INDEX(produccion_id)`; `INDEX(producto_id)`; `PRIMARY(id)`.

CHECK activo: `chk_detboc_cantidad`: `cantidad > 0`, creado por SQL ALTER dentro de la migración.

#### detalle_bocadito_empleado

| Columna | Tipo físico | Nullable | Default | Otro |
| --- | --- | --- | --- | --- |
| `detalle_bocadito_id` | bigint(20) unsigned | No | Sin default explícito | — |
| `empleado_id` | bigint(20) unsigned | No | Sin default explícito | — |
| `rol_produccion_id` | bigint(20) unsigned | No | Sin default explícito | — |

Índices: `INDEX(empleado_id)`; `INDEX(rol_produccion_id)`; `PRIMARY(detalle_bocadito_id, empleado_id)`.

#### detalle_pan

| Columna | Tipo físico | Nullable | Default | Otro |
| --- | --- | --- | --- | --- |
| `id` | bigint(20) unsigned | No | Sin default explícito | AUTO_INCREMENT |
| `produccion_id` | bigint(20) unsigned | No | Sin default explícito | — |
| `producto_id` | bigint(20) unsigned | No | Sin default explícito | — |
| `unidad_medida_id` | bigint(20) unsigned | No | Sin default explícito | — |
| `turno_id` | bigint(20) unsigned | No | Sin default explícito | — |
| `cantidad` | int(11) | No | Sin default explícito | — |

Índices: `INDEX(produccion_id)`; `INDEX(producto_id)`; `INDEX(turno_id)`; `INDEX(unidad_medida_id)`; `PRIMARY(id)`.

CHECK activo: `chk_detpan_cantidad`: `cantidad > 0`, creado por SQL ALTER dentro de la migración.

#### detalle_pan_empleado

| Columna | Tipo físico | Nullable | Default | Otro |
| --- | --- | --- | --- | --- |
| `detalle_pan_id` | bigint(20) unsigned | No | Sin default explícito | — |
| `empleado_id` | bigint(20) unsigned | No | Sin default explícito | — |
| `rol_produccion_id` | bigint(20) unsigned | No | Sin default explícito | — |

Índices: `INDEX(empleado_id)`; `INDEX(rol_produccion_id)`; `PRIMARY(detalle_pan_id, empleado_id)`.

#### detalle_pedidos

| Columna | Tipo físico | Nullable | Default | Otro |
| --- | --- | --- | --- | --- |
| `id` | bigint(20) unsigned | No | Sin default explícito | AUTO_INCREMENT |
| `pedido_id` | bigint(20) unsigned | No | Sin default explícito | — |
| `producto_id` | bigint(20) unsigned | No | Sin default explícito | — |
| `unidad_medida_id` | bigint(20) unsigned | No | Sin default explícito | — |
| `cantidad` | decimal(10,2) | No | Sin default explícito | — |
| `especificaciones` | text | Sí | NULL | — |
| `foto_referencia` | varchar(255) | Sí | NULL | — |

Índices: `INDEX(pedido_id)`; `INDEX(producto_id)`; `INDEX(unidad_medida_id)`; `PRIMARY(id)`.

Sin CHECK de positividad para cantidad; no se afirma que valores no positivos existan.

#### detalle_torta

| Columna | Tipo físico | Nullable | Default | Otro |
| --- | --- | --- | --- | --- |
| `id` | bigint(20) unsigned | No | Sin default explícito | AUTO_INCREMENT |
| `produccion_id` | bigint(20) unsigned | No | Sin default explícito | — |
| `producto_id` | bigint(20) unsigned | No | Sin default explícito | — |
| `forma` | varchar(30) | No | Sin default explícito | — |
| `foto` | varchar(255) | Sí | NULL | — |

Índices: `INDEX(produccion_id)`; `INDEX(producto_id)`; `PRIMARY(id)`.

#### detalle_torta_empleado

| Columna | Tipo físico | Nullable | Default | Otro |
| --- | --- | --- | --- | --- |
| `detalle_torta_id` | bigint(20) unsigned | No | Sin default explícito | — |
| `empleado_id` | bigint(20) unsigned | No | Sin default explícito | — |
| `rol_produccion_id` | bigint(20) unsigned | No | Sin default explícito | — |

Índices: `INDEX(empleado_id)`; `INDEX(rol_produccion_id)`; `PRIMARY(detalle_torta_id, empleado_id)`.

#### empleados

| Columna | Tipo físico | Nullable | Default | Otro |
| --- | --- | --- | --- | --- |
| `id` | bigint(20) unsigned | No | Sin default explícito | AUTO_INCREMENT |
| `nombre_empleados` | varchar(100) | No | Sin default explícito | — |
| `numero_empleados` | varchar(20) | Sí | NULL | — |
| `cargo_id` | bigint(20) unsigned | No | Sin default explícito | — |
| `created_at` | timestamp | Sí | NULL | — |
| `updated_at` | timestamp | Sí | NULL | — |

Índices: `INDEX(cargo_id)`; `PRIMARY(id)`.

#### failed_jobs

| Columna | Tipo físico | Nullable | Default | Otro |
| --- | --- | --- | --- | --- |
| `id` | bigint(20) unsigned | No | Sin default explícito | AUTO_INCREMENT |
| `uuid` | varchar(255) | No | Sin default explícito | — |
| `connection` | varchar(255) | No | Sin default explícito | — |
| `queue` | varchar(255) | No | Sin default explícito | — |
| `payload` | longtext | No | Sin default explícito | — |
| `exception` | longtext | No | Sin default explícito | — |
| `failed_at` | timestamp | No | current_timestamp() | — |

Índices: `INDEX(connection, queue, failed_at)`; `UNIQUE(uuid)`; `PRIMARY(id)`.

#### jobs

| Columna | Tipo físico | Nullable | Default | Otro |
| --- | --- | --- | --- | --- |
| `id` | bigint(20) unsigned | No | Sin default explícito | AUTO_INCREMENT |
| `queue` | varchar(255) | No | Sin default explícito | — |
| `payload` | longtext | No | Sin default explícito | — |
| `attempts` | smallint(5) unsigned | No | Sin default explícito | — |
| `reserved_at` | int(10) unsigned | Sí | NULL | — |
| `available_at` | int(10) unsigned | No | Sin default explícito | — |
| `created_at` | int(10) unsigned | No | Sin default explícito | — |

Índices: `INDEX(queue)`; `PRIMARY(id)`.

#### job_batches

| Columna | Tipo físico | Nullable | Default | Otro |
| --- | --- | --- | --- | --- |
| `id` | varchar(255) | No | Sin default explícito | — |
| `name` | varchar(255) | No | Sin default explícito | — |
| `total_jobs` | int(11) | No | Sin default explícito | — |
| `pending_jobs` | int(11) | No | Sin default explícito | — |
| `failed_jobs` | int(11) | No | Sin default explícito | — |
| `failed_job_ids` | longtext | No | Sin default explícito | — |
| `options` | mediumtext | Sí | NULL | — |
| `cancelled_at` | int(11) | Sí | NULL | — |
| `created_at` | int(11) | No | Sin default explícito | — |
| `finished_at` | int(11) | Sí | NULL | — |

Índices: `PRIMARY(id)`.

#### migrations

| Columna | Tipo físico | Nullable | Default | Otro |
| --- | --- | --- | --- | --- |
| `id` | int(10) unsigned | No | Sin default explícito | AUTO_INCREMENT |
| `migration` | varchar(255) | No | Sin default explícito | — |
| `batch` | int(11) | No | Sin default explícito | — |

Índices: `PRIMARY(id)`.

#### pedidos

| Columna | Tipo físico | Nullable | Default | Otro |
| --- | --- | --- | --- | --- |
| `id` | bigint(20) unsigned | No | Sin default explícito | AUTO_INCREMENT |
| `nombre_cliente` | varchar(100) | Sí | NULL | — |
| `fecha_registro` | datetime | No | current_timestamp() | — |
| `fecha_entrega_prometida` | datetime | No | Sin default explícito | — |
| `entregado` | tinyint(1) | No | 0 | — |
| `registrado_por_usuario_id` | bigint(20) unsigned | No | Sin default explícito | — |

Índices: `INDEX(registrado_por_usuario_id)`; `PRIMARY(id)`.

#### produccion

| Columna | Tipo físico | Nullable | Default | Otro |
| --- | --- | --- | --- | --- |
| `id` | bigint(20) unsigned | No | Sin default explícito | AUTO_INCREMENT |
| `fecha` | date | No | Sin default explícito | — |
| `registrado_por_usuario_id` | bigint(20) unsigned | No | Sin default explícito | — |

Índices: `PRIMARY(id)`; `INDEX(registrado_por_usuario_id)`.

#### productos

| Columna | Tipo físico | Nullable | Default | Otro |
| --- | --- | --- | --- | --- |
| `id` | bigint(20) unsigned | No | Sin default explícito | AUTO_INCREMENT |
| `nombre_p` | varchar(100) | No | Sin default explícito | — |
| `temporada_fe` | date | Sí | NULL | — |
| `activo` | tinyint(1) | No | 1 | — |
| `categoria_id` | bigint(20) unsigned | No | Sin default explícito | — |
| `unidad_medida_id` | bigint(20) unsigned | No | Sin default explícito | — |

Índices: `PRIMARY(id)`; `INDEX(categoria_id)`; `INDEX(unidad_medida_id)`.

#### roles

| Columna | Tipo físico | Nullable | Default | Otro |
| --- | --- | --- | --- | --- |
| `id` | bigint(20) unsigned | No | Sin default explícito | AUTO_INCREMENT |
| `nombre_roles` | varchar(100) | No | Sin default explícito | — |

Índices: `PRIMARY(id)`.

#### roles_produccion

| Columna | Tipo físico | Nullable | Default | Otro |
| --- | --- | --- | --- | --- |
| `id` | bigint(20) unsigned | No | Sin default explícito | AUTO_INCREMENT |
| `nombre_roles_produccion` | varchar(100) | No | Sin default explícito | — |

Índices: `PRIMARY(id)`.

#### sessions

| Columna | Tipo físico | Nullable | Default | Otro |
| --- | --- | --- | --- | --- |
| `id` | varchar(255) | No | Sin default explícito | — |
| `user_id` | bigint(20) unsigned | Sí | NULL | — |
| `ip_address` | varchar(45) | Sí | NULL | — |
| `user_agent` | text | Sí | NULL | — |
| `payload` | longtext | No | Sin default explícito | — |
| `last_activity` | int(11) | No | Sin default explícito | — |

Índices: `PRIMARY(id)`; `INDEX(last_activity)`; `INDEX(user_id)`.

#### turnos

| Columna | Tipo físico | Nullable | Default | Otro |
| --- | --- | --- | --- | --- |
| `id` | bigint(20) unsigned | No | Sin default explícito | AUTO_INCREMENT |
| `nombre_turnos` | varchar(100) | No | Sin default explícito | — |

Índices: `PRIMARY(id)`.

#### unidades_medida

| Columna | Tipo físico | Nullable | Default | Otro |
| --- | --- | --- | --- | --- |
| `id` | bigint(20) unsigned | No | Sin default explícito | AUTO_INCREMENT |
| `nombre_unidades_medida` | varchar(100) | No | Sin default explícito | — |
| `equivalencia_unidades` | decimal(8,2) | No | 1.00 | — |

Índices: `PRIMARY(id)`.

#### usuarios_sistema

| Columna | Tipo físico | Nullable | Default | Otro |
| --- | --- | --- | --- | --- |
| `id` | bigint(20) unsigned | No | Sin default explícito | AUTO_INCREMENT |
| `username` | varchar(50) | No | Sin default explícito | — |
| `password_hash` | varchar(255) | No | Sin default explícito | — |
| `empleado_id` | bigint(20) unsigned | No | Sin default explícito | — |
| `rol_id` | bigint(20) unsigned | No | Sin default explícito | — |
| `created_at` | timestamp | Sí | NULL | — |
| `updated_at` | timestamp | Sí | NULL | — |

Índices: `PRIMARY(id)`; `UNIQUE(empleado_id)`; `INDEX(rol_id)`; `UNIQUE(username)`.

### Tablas sin modelo y posible legado

`cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `sessions` y `migrations` son tablas framework/técnicas: **NO NECESARIO** crear modelos de negocio. Sus usos están en configuración y servicios del framework. Ninguna tabla de negocio queda como FALTA MODELO. No se encontró una tabla que pueda declararse legacy; tampoco se elimina ninguna por no tener un controller operativo.

`migrations.id` es INT UNSIGNED, `jobs.created_at` y `job_batches.created_at` son enteros técnicos, y `failed_jobs.failed_at` es timestamp. No se les aplican las reglas de timestamps de modelos de negocio.

## 3. Inventario de modelos

Todos los modelos declaran el namespace App\Models y `$table` explícito. Diecisiete extienden el Model de Eloquent; `Usuario` extiende el User de Foundation/Auth con alias Authenticatable. Ninguno declara `$primaryKey` ni `$keyType`: heredan `id` e `int`. Los tres modelos `Detalle*Empleado` tienen `$incrementing=false`; los demás heredan true.

Ninguno declara `$guarded`; heredan `['*']`, compatible con su whitelist `$fillable`. No hay `$casts` propio ni método `casts()` sobrescrito. El cast `id:int` que aparece al inspeccionar 15 modelos es implícito de Eloquent; los tres no incrementales no lo reciben. Empleado y Usuario usan timestamps heredados true y los otros 16 false, coherentes con sus tablas. `created_at`/`updated_at` de esos dos modelos se tratan como fechas por el framework aunque no aparezcan en getCasts().

| Modelo | Tabla | PK Eloquent | Fillable exacto | Casts efectivos | Relaciones | Problemas/observaciones |
| --- | --- | --- | --- | --- | --- | --- |
| Cargo | `cargos` | id | nombre_cargos | id:int | empleados | Sin desajuste estructural; casts abajo |
| Categoria | `categorias` | id | nombre_categorias | id:int | productos | Sin desajuste estructural; casts abajo |
| DetalleBocadito | `detalle_bocadito` | id | produccion_id, producto_id, cantidad | id:int | produccion, producto | Sin desajuste estructural; casts abajo |
| DetalleBocaditoEmpleado | `detalle_bocadito_empleado` | id [REVISAR] | detalle_bocadito_id, empleado_id, rol_produccion_id | Ninguno | detalleBocadito, empleado, rolProduccion | PK compuesta real / modelo espera id |
| DetallePan | `detalle_pan` | id | produccion_id, producto_id, unidad_medida_id, turno_id, cantidad | id:int | produccion, producto, unidadMedida, turno | Sin desajuste estructural; casts abajo |
| DetallePanEmpleado | `detalle_pan_empleado` | id [REVISAR] | detalle_pan_id, empleado_id, rol_produccion_id | Ninguno | detallePan, empleado, rolProduccion | PK compuesta real / modelo espera id |
| DetallePedido | `detalle_pedidos` | id | pedido_id, producto_id, unidad_medida_id, cantidad, especificaciones, foto_referencia | id:int | pedido, producto, unidadMedida | Sin desajuste estructural; casts abajo |
| DetalleTorta | `detalle_torta` | id | produccion_id, producto_id, forma, foto | id:int | produccion, producto | Sin desajuste estructural; casts abajo |
| DetalleTortaEmpleado | `detalle_torta_empleado` | id [REVISAR] | detalle_torta_id, empleado_id, rol_produccion_id | Ninguno | detalleTorta, empleado, rolProduccion | PK compuesta real / modelo espera id |
| Empleado | `empleados` | id | nombre_empleados, numero_empleados, cargo_id | id:int | cargo, usuario | Sin desajuste estructural; casts abajo |
| Pedido | `pedidos` | id | nombre_cliente, fecha_registro, fecha_entrega_prometida, entregado, registrado_por_usuario_id | id:int | usuario, detallePedidos | Sin desajuste estructural; casts abajo |
| Produccion | `produccion` | id | fecha, registrado_por_usuario_id | id:int | usuario, detallePan, detalleTorta, detalleBocadito | hasOne sin UNIQUE; fecha sin cast propio |
| Producto | `productos` | id | nombre_p, temporada_fe, activo, categoria_id, unidad_medida_id | id:int | categoria, unidadMedida, detallesPan, detallesTorta, detallesBocadito | Sin desajuste estructural; casts abajo |
| Rol | `roles` | id | nombre_roles | id:int | usuarios | Sin desajuste estructural; casts abajo |
| RolProduccion | `roles_produccion` | id | nombre_roles_produccion | id:int | detallePanEmpleados, detalleTortaEmpleados, detalleBocaditoEmpleados | Sin desajuste estructural; casts abajo |
| Turno | `turnos` | id | nombre_turnos | id:int | detallesPan | Sin desajuste estructural; casts abajo |
| UnidadMedida | `unidades_medida` | id | nombre_unidades_medida, equivalencia_unidades | id:int | productos, detallesPan | Sin desajuste estructural; casts abajo |
| Usuario | `usuarios_sistema` | id | username, password_hash, empleado_id, rol_id | id:int | empleado, rol | getAuthPasswordName pendiente |

### Traits, ocultamiento y métodos

No hay traits declarados en las clases propias, ni HasFactory, SoftDeletes, accessors, mutators, scopes, eventos propios, boot/booted, `$touches`, casts personalizados o claves alternativas. Heredan los traits internos de Model. Usuario además hereda Authenticatable, Authorizable, CanResetPassword y MustVerifyEmail del User base. Eso no acredita permisos aprobados, recuperación ni verificación de email: no hay columna email, remember_token ni rutas de esas funciones.

Solo Usuario declara `$hidden=['password_hash']` y un método propio no relacional: `getAuthPassword()`. Los demás métodos propios son las relaciones listadas. No hay PK personalizada implementada correctamente en modelos; las PK compuestas pertenecen a las tablas y siguen sin un contrato de persistencia adecuado en los tres modelos de asociación.

### Fillable, guarded y escritores reales

Todos los fillable coinciden con columnas reales; no incluyen id ni timestamps técnicos. No se justifica recortarlos por estética. `$fillable` limita la asignación masiva; no establece permisos por campo ni sustituye validación.

| Escritor/consumidor | Operación actual | Evaluación |
|---|---|---|
| UsuarioSeeder | `Usuario::create()` con arreglo fijo y Hash::make | Único create de modelo de negocio en código propio; no toma entrada de request |
| CargoSeeder / RolSeeder / EmpleadoSeeder | `DB::table()->insert()` con datos fijos | Query Builder no aplica fillable; no implica vulnerabilidad por entrada externa |
| LoginController | Valida y pasa credenciales a Auth::attempt | No usa create/fill/update con request; provider puede hacer forceFill/save al rehash |
| ProduccionController::store/update/destroy | Stubs | No escritores de Producción implementados |
| NavigationTest | Usuario forceFill y Empleado construido en memoria | Fixture controlado; no crea datos de negocio en BD |

No se encontró `Model::create($request->all())`, update/fill con entrada no confiable, attach/sync/detach funcional ni escritores de Pedidos/Stock. No se confirma vulnerabilidad de mass assignment.

Usuario mantiene username/password_hash/empleado_id/rol_id asignables para escritores controlados. Para futuros formularios de cuenta, rol_id y vínculos requieren autorización. `Produccion.registrado_por_usuario_id` y `Pedido.registrado_por_usuario_id` deberán derivarse de la sesión autenticada, no de un campo cliente; hoy no existe ese escritor. Detalles y asociaciones pueden conservar FK en fillable para arreglos previamente validados. Paths de foto deben proceder del almacenamiento controlado, no confiarse al cliente.

### Casts: necesidad y alcance

No se demostró un fallo de casts en un escritor operativo; hoy son recomendaciones para el contrato futuro. CAST NECESARIO solo cuando un comportamiento aprobado dependa de él; no se agrega ninguno en esta fase. Un cast no valida positividad, categoría, permisos o equivalencias.

| Modelo/campo | Cast candidato | Clasificación | Motivo |
|---|---|---|---|
| Producto.activo | boolean | CAST RECOMENDABLE | Contrato booleano estable en PHP/JSON; la BD almacena tinyint(1) |
| Producto.temporada_fe | date | CAST RECOMENDABLE | Fecha nullable; falta definir significado de temporada antes de reglas |
| Produccion.fecha | date | CAST RECOMENDABLE | Filtros/cálculos por fecha de negocio; respetar interfaz y formato de salida |
| Pedido.entregado | boolean | CAST RECOMENDABLE | No confundir flag de entrega con estados múltiples |
| Pedido.fecha_registro / fecha_entrega_prometida | datetime | CAST RECOMENDABLE | Fechas con hora; comprobar zona horaria y serialización |
| UnidadMedida.equivalencia_unidades | decimal:2 | CAST RECOMENDABLE | Precisión decimal, devuelve representación decimal; no float para exactitud |
| DetallePedido.cantidad | decimal:2 | CAST RECOMENDABLE | Respetar decimal(10,2), no convertir a integer |
| DetallePan.cantidad / DetalleBocadito.cantidad | integer, si se acuerda ese contrato | CAST NO NECESARIO hoy | DB integer; pan requiere primero decidir fracciones de coche |
| PK y FK | Sin casts añadidos por estética | CAST NO NECESARIO | PK incrementales ya se tratan como int; FK no tienen consumidor demostrado que exija otro |
| Empleado/Usuario.created_at y updated_at | Fechas nativas de timestamps | CAST NO NECESARIO | Tratamiento del framework ya presente |
| Usuario.password_hash | hashed, eventualmente | CAST NO NECESARIO hoy | Seeder hashea explícitamente; decidir al crear nuevos escritores, no cambia el contrato getAuthPasswordName |
| Texto, nombres, forma, foto y especificaciones | string nativo | CAST NO NECESARIO | No hay JSON/arrays/encrypted que justifiquen un cast |

No agregar encrypted a password_hash: autenticación necesita un hash verificable, no un secreto cifrado reversible. No se identificó necesidad funcional aprobada para SoftDeletes.

## 4. Mapa de foreign keys

Las 28 FK se comprobaron tanto en archivos como en metadatos activos. Todas apuntan a `id`. ON UPDATE es RESTRICT en todas. ON DELETE sin acción explícita en la migración resulta RESTRICT en este MariaDB. Todas son NOT NULL excepto sessions.user_id; `usuarios_sistema.empleado_id` tiene UNIQUE.

| FK origen | Destino | ON DELETE | Pertenencia Eloquent | Relación inversa actual | Clasificación |
| --- | --- | --- | --- | --- | --- |
| `detalle_bocadito.produccion_id` | `produccion.id` | CASCADE | DetalleBocadito::produccion() | Produccion::detalleBocadito() [HasOne] | CORRECTA; inversa REVISAR cardinalidad |
| `detalle_bocadito.producto_id` | `productos.id` | RESTRICT | DetalleBocadito::producto() | Producto::detallesBocadito() [HasMany] | CORRECTA |
| `detalle_bocadito_empleado.detalle_bocadito_id` | `detalle_bocadito.id` | CASCADE | DetalleBocaditoEmpleado::detalleBocadito() | No declarada | CORRECTA; RELACIÓN INVERSA OPCIONAL |
| `detalle_bocadito_empleado.empleado_id` | `empleados.id` | RESTRICT | DetalleBocaditoEmpleado::empleado() | No declarada | CORRECTA; RELACIÓN INVERSA OPCIONAL |
| `detalle_bocadito_empleado.rol_produccion_id` | `roles_produccion.id` | RESTRICT | DetalleBocaditoEmpleado::rolProduccion() | RolProduccion::detalleBocaditoEmpleados() [HasMany] | CORRECTA |
| `detalle_pan.produccion_id` | `produccion.id` | CASCADE | DetallePan::produccion() | Produccion::detallePan() [HasOne] | CORRECTA; inversa REVISAR cardinalidad |
| `detalle_pan.producto_id` | `productos.id` | RESTRICT | DetallePan::producto() | Producto::detallesPan() [HasMany] | CORRECTA |
| `detalle_pan.turno_id` | `turnos.id` | RESTRICT | DetallePan::turno() | Turno::detallesPan() [HasMany] | CORRECTA |
| `detalle_pan.unidad_medida_id` | `unidades_medida.id` | RESTRICT | DetallePan::unidadMedida() | UnidadMedida::detallesPan() [HasMany] | CORRECTA |
| `detalle_pan_empleado.detalle_pan_id` | `detalle_pan.id` | CASCADE | DetallePanEmpleado::detallePan() | No declarada | CORRECTA; RELACIÓN INVERSA OPCIONAL |
| `detalle_pan_empleado.empleado_id` | `empleados.id` | RESTRICT | DetallePanEmpleado::empleado() | No declarada | CORRECTA; RELACIÓN INVERSA OPCIONAL |
| `detalle_pan_empleado.rol_produccion_id` | `roles_produccion.id` | RESTRICT | DetallePanEmpleado::rolProduccion() | RolProduccion::detallePanEmpleados() [HasMany] | CORRECTA |
| `detalle_pedidos.pedido_id` | `pedidos.id` | CASCADE | DetallePedido::pedido() | Pedido::detallePedidos() [HasMany] | CORRECTA |
| `detalle_pedidos.producto_id` | `productos.id` | RESTRICT | DetallePedido::producto() | No declarada | CORRECTA; RELACIÓN INVERSA OPCIONAL |
| `detalle_pedidos.unidad_medida_id` | `unidades_medida.id` | RESTRICT | DetallePedido::unidadMedida() | No declarada | CORRECTA; RELACIÓN INVERSA OPCIONAL |
| `detalle_torta.produccion_id` | `produccion.id` | CASCADE | DetalleTorta::produccion() | Produccion::detalleTorta() [HasOne] | CORRECTA; inversa REVISAR cardinalidad |
| `detalle_torta.producto_id` | `productos.id` | RESTRICT | DetalleTorta::producto() | Producto::detallesTorta() [HasMany] | CORRECTA |
| `detalle_torta_empleado.detalle_torta_id` | `detalle_torta.id` | CASCADE | DetalleTortaEmpleado::detalleTorta() | No declarada | CORRECTA; RELACIÓN INVERSA OPCIONAL |
| `detalle_torta_empleado.empleado_id` | `empleados.id` | RESTRICT | DetalleTortaEmpleado::empleado() | No declarada | CORRECTA; RELACIÓN INVERSA OPCIONAL |
| `detalle_torta_empleado.rol_produccion_id` | `roles_produccion.id` | RESTRICT | DetalleTortaEmpleado::rolProduccion() | RolProduccion::detalleTortaEmpleados() [HasMany] | CORRECTA |
| `empleados.cargo_id` | `cargos.id` | RESTRICT | Empleado::cargo() | Cargo::empleados() [HasMany] | CORRECTA |
| `pedidos.registrado_por_usuario_id` | `usuarios_sistema.id` | RESTRICT | Pedido::usuario() | No declarada | CORRECTA; RELACIÓN INVERSA OPCIONAL |
| `produccion.registrado_por_usuario_id` | `usuarios_sistema.id` | RESTRICT | Produccion::usuario() | No declarada | CORRECTA; RELACIÓN INVERSA OPCIONAL |
| `productos.categoria_id` | `categorias.id` | RESTRICT | Producto::categoria() | Categoria::productos() [HasMany] | CORRECTA |
| `productos.unidad_medida_id` | `unidades_medida.id` | RESTRICT | Producto::unidadMedida() | UnidadMedida::productos() [HasMany] | CORRECTA |
| `sessions.user_id` | `usuarios_sistema.id` | SET NULL | Framework; FK SIN MODELO de negocio | No declarada | FK SIN MODELO: técnica |
| `usuarios_sistema.empleado_id` | `empleados.id` | RESTRICT | Usuario::empleado() | Empleado::usuario() [HasOne] | CORRECTA |
| `usuarios_sistema.rol_id` | `roles.id` | RESTRICT | Usuario::rol() | Rol::usuarios() [HasMany] | CORRECTA |

### Diagrama textual completo de FK

```text
detalle_bocadito.produccion_id --> produccion.id [DELETE CASCADE]
detalle_bocadito.producto_id --> productos.id [DELETE RESTRICT]
detalle_bocadito_empleado.detalle_bocadito_id --> detalle_bocadito.id [DELETE CASCADE]
detalle_bocadito_empleado.empleado_id --> empleados.id [DELETE RESTRICT]
detalle_bocadito_empleado.rol_produccion_id --> roles_produccion.id [DELETE RESTRICT]
detalle_pan.produccion_id --> produccion.id [DELETE CASCADE]
detalle_pan.producto_id --> productos.id [DELETE RESTRICT]
detalle_pan.turno_id --> turnos.id [DELETE RESTRICT]
detalle_pan.unidad_medida_id --> unidades_medida.id [DELETE RESTRICT]
detalle_pan_empleado.detalle_pan_id --> detalle_pan.id [DELETE CASCADE]
detalle_pan_empleado.empleado_id --> empleados.id [DELETE RESTRICT]
detalle_pan_empleado.rol_produccion_id --> roles_produccion.id [DELETE RESTRICT]
detalle_pedidos.pedido_id --> pedidos.id [DELETE CASCADE]
detalle_pedidos.producto_id --> productos.id [DELETE RESTRICT]
detalle_pedidos.unidad_medida_id --> unidades_medida.id [DELETE RESTRICT]
detalle_torta.produccion_id --> produccion.id [DELETE CASCADE]
detalle_torta.producto_id --> productos.id [DELETE RESTRICT]
detalle_torta_empleado.detalle_torta_id --> detalle_torta.id [DELETE CASCADE]
detalle_torta_empleado.empleado_id --> empleados.id [DELETE RESTRICT]
detalle_torta_empleado.rol_produccion_id --> roles_produccion.id [DELETE RESTRICT]
empleados.cargo_id --> cargos.id [DELETE RESTRICT]
pedidos.registrado_por_usuario_id --> usuarios_sistema.id [DELETE RESTRICT]
produccion.registrado_por_usuario_id --> usuarios_sistema.id [DELETE RESTRICT]
productos.categoria_id --> categorias.id [DELETE RESTRICT]
productos.unidad_medida_id --> unidades_medida.id [DELETE RESTRICT]
sessions.user_id --> usuarios_sistema.id [DELETE SET NULL]
usuarios_sistema.empleado_id --> empleados.id [DELETE RESTRICT]
usuarios_sistema.rol_id --> roles.id [DELETE RESTRICT]
```

Cardinalidades reales: un Cargo puede tener muchos Empleados; un Empleado puede tener cero o un Usuario por UNIQUE y cada Usuario tiene un Empleado; un Rol puede tener muchos Usuarios. Las otras FK no únicas permiten muchos hijos por padre. Las PK de asociaciones impiden repetir el mismo empleado en el mismo detalle, incluso con otro rol. DELETE de Produccion borra sus tres clases de detalle y sus asociaciones por cascada; no borra Productos, Empleados ni RolesProduccion. DELETE de Pedido borra sus detalles. Catálogos y usuarios referenciados por negocio quedan restringidos; eliminar Usuario pone sessions.user_id a NULL si no lo bloquean las otras referencias.

## 5. Relaciones Eloquent existentes

La introspección creó instancias sin consultar filas y comprobó los nombres de claves inferidos por el framework; no se adivinaron a partir de nombres de métodos. Las 44 definiciones son las siguientes. CORRECTA describe resolución de claves; hasOne de Producción requiere además decisión de cardinalidad.

| Método | Tipo | Modelo destino | FK | Clave local/owner | Estado |
| --- | --- | --- | --- | --- | --- |
| Cargo::empleados() | HasMany | Empleado | cargo_id | id | CORRECTA |
| Categoria::productos() | HasMany | Producto | categoria_id | id | CORRECTA |
| DetalleBocadito::produccion() | BelongsTo | Produccion | produccion_id | id | CORRECTA |
| DetalleBocadito::producto() | BelongsTo | Producto | producto_id | id | CORRECTA |
| DetalleBocaditoEmpleado::detalleBocadito() | BelongsTo | DetalleBocadito | detalle_bocadito_id | id | CORRECTA |
| DetalleBocaditoEmpleado::empleado() | BelongsTo | Empleado | empleado_id | id | CORRECTA |
| DetalleBocaditoEmpleado::rolProduccion() | BelongsTo | RolProduccion | rol_produccion_id | id | CORRECTA |
| DetallePan::produccion() | BelongsTo | Produccion | produccion_id | id | CORRECTA |
| DetallePan::producto() | BelongsTo | Producto | producto_id | id | CORRECTA |
| DetallePan::unidadMedida() | BelongsTo | UnidadMedida | unidad_medida_id | id | CORRECTA |
| DetallePan::turno() | BelongsTo | Turno | turno_id | id | CORRECTA |
| DetallePanEmpleado::detallePan() | BelongsTo | DetallePan | detalle_pan_id | id | CORRECTA |
| DetallePanEmpleado::empleado() | BelongsTo | Empleado | empleado_id | id | CORRECTA |
| DetallePanEmpleado::rolProduccion() | BelongsTo | RolProduccion | rol_produccion_id | id | CORRECTA |
| DetallePedido::pedido() | BelongsTo | Pedido | pedido_id | id | CORRECTA |
| DetallePedido::producto() | BelongsTo | Producto | producto_id | id | CORRECTA |
| DetallePedido::unidadMedida() | BelongsTo | UnidadMedida | unidad_medida_id | id | CORRECTA |
| DetalleTorta::produccion() | BelongsTo | Produccion | produccion_id | id | CORRECTA |
| DetalleTorta::producto() | BelongsTo | Producto | producto_id | id | CORRECTA |
| DetalleTortaEmpleado::detalleTorta() | BelongsTo | DetalleTorta | detalle_torta_id | id | CORRECTA |
| DetalleTortaEmpleado::empleado() | BelongsTo | Empleado | empleado_id | id | CORRECTA |
| DetalleTortaEmpleado::rolProduccion() | BelongsTo | RolProduccion | rol_produccion_id | id | CORRECTA |
| Empleado::cargo() | BelongsTo | Cargo | cargo_id | id | CORRECTA |
| Empleado::usuario() | HasOne | Usuario | empleado_id | id | CORRECTA |
| Pedido::usuario() | BelongsTo | Usuario | registrado_por_usuario_id | id | CORRECTA |
| Pedido::detallePedidos() | HasMany | DetallePedido | pedido_id | id | CORRECTA |
| Produccion::usuario() | BelongsTo | Usuario | registrado_por_usuario_id | id | CORRECTA |
| Produccion::detallePan() | HasOne | DetallePan | produccion_id | id | REVISAR: BD permite varios |
| Produccion::detalleTorta() | HasOne | DetalleTorta | produccion_id | id | REVISAR: BD permite varios |
| Produccion::detalleBocadito() | HasOne | DetalleBocadito | produccion_id | id | REVISAR: BD permite varios |
| Producto::categoria() | BelongsTo | Categoria | categoria_id | id | CORRECTA |
| Producto::unidadMedida() | BelongsTo | UnidadMedida | unidad_medida_id | id | CORRECTA |
| Producto::detallesPan() | HasMany | DetallePan | producto_id | id | CORRECTA |
| Producto::detallesTorta() | HasMany | DetalleTorta | producto_id | id | CORRECTA |
| Producto::detallesBocadito() | HasMany | DetalleBocadito | producto_id | id | CORRECTA |
| Rol::usuarios() | HasMany | Usuario | rol_id | id | CORRECTA |
| RolProduccion::detallePanEmpleados() | HasMany | DetallePanEmpleado | rol_produccion_id | id | CORRECTA |
| RolProduccion::detalleTortaEmpleados() | HasMany | DetalleTortaEmpleado | rol_produccion_id | id | CORRECTA |
| RolProduccion::detalleBocaditoEmpleados() | HasMany | DetalleBocaditoEmpleado | rol_produccion_id | id | CORRECTA |
| Turno::detallesPan() | HasMany | DetallePan | turno_id | id | CORRECTA |
| UnidadMedida::productos() | HasMany | Producto | unidad_medida_id | id | CORRECTA |
| UnidadMedida::detallesPan() | HasMany | DetallePan | unidad_medida_id | id | CORRECTA |
| Usuario::empleado() | BelongsTo | Empleado | empleado_id | id | CORRECTA |
| Usuario::rol() | BelongsTo | Rol | rol_id | id | CORRECTA |

No existe belongsToMany, hasManyThrough ni una relación polimórfica. Los métodos camelCase `detallePan`, `detalleTorta`, `detalleBocadito` y `rolProduccion` de las asociaciones infieren correctamente detalle_*_id y rol_produccion_id. No es necesario hacer explícitas esas claves únicamente para que funcionen.

## 6. Relaciones Eloquent faltantes

No falta ningún belongsTo para las 27 FK de negocio. Las inversas que no existen se distinguen de relaciones obligatorias. Una FK no exige declarar ambas direcciones si no hay consumidor.

| Acceso no declarado | Respaldo BD | Clasificación / utilidad |
|---|---|---|
| Usuario → producciones | produccion.registrado_por_usuario_id | RELACIÓN INVERSA OPCIONAL; útil si se filtra por registrador |
| Usuario → pedidos | pedidos.registrado_por_usuario_id | RELACIÓN INVERSA OPCIONAL |
| Producto → detalle de pedidos | detalle_pedidos.producto_id | RELACIÓN INVERSA OPCIONAL |
| UnidadMedida → detalle de pedidos | detalle_pedidos.unidad_medida_id | RELACIÓN INVERSA OPCIONAL |
| DetallePan → asociaciones de empleados | detalle_pan_empleado.detalle_pan_id | Inversa ausente; justificar para mostrar participantes de Pan |
| DetalleTorta → asociaciones de empleados | detalle_torta_empleado.detalle_torta_id | Inversa ausente; justificar para mostrar participantes de Torta |
| DetalleBocadito → asociaciones de empleados | detalle_bocadito_empleado.detalle_bocadito_id | Inversa ausente; justificar para mostrar participantes de Bocadito |
| Empleado → sus 3 clases de asociaciones | empleado_id en cada asociación | Tres RELACIONES INVERSAS OPCIONALES; necesarias solo para consultas por persona |
| Usuario → sesiones | sessions.user_id | FK SIN MODELO técnico; relación inversa NO NECESARIA hoy |

Para el listado de responsables futuro falta un acceso navegable desde cada detalle. Opciones: hasMany de asignaciones con empleado/rol, o belongsToMany con tabla/claves explícitas y withPivot(rol_produccion_id). Son alternativas, no tres modelos faltantes ni obligación de incorporar ambas capas. Las asociaciones ya tienen modelos; debe acordarse su contrato antes de escribir updates/deletes. No hay MODELO SIN FK CORRESPONDIENTE confirmado: todas las relaciones existentes tienen respaldo de FK.

No falta `DetalleTorta::unidadMedida()` en el esquema actual: detalle_torta NO tiene unidad_medida_id. Crear esa relación hoy sería incorrecto, salvo cambio de datos aprobado previamente.

## 7. Usuario / Empleado / Rol

`Usuario` autentica contra `usuarios_sistema`, PK id. `username` varchar(50) único; `password_hash` varchar(255), oculto al serializar. Los timestamps de la cuenta y del empleado sí existen. Usuario::empleado pertenece a Empleado y Empleado::usuario usa hasOne respaldado por empleado_id UNIQUE. Empleado::cargo y Cargo::empleados forman el vínculo de cargo. Usuario::rol y Rol::usuarios también son coherentes.

```text
Cargo --hasMany empleados()--> Empleado --hasOne usuario()--> Usuario
  ^                              ^                           |
  +------- cargo() belongsTo ----+                           |
                                 +-- empleado() belongsTo ---+
Rol --hasMany usuarios()------------------------------------> Usuario
  ^                                                             |
  +-------------------------- rol() belongsTo ------------------+
```

El Empleado no tiene turno_id, ni relación directa a Produccion. Participa a través de las asociaciones de detalle. El Usuario registra Produccion y Pedido mediante registrado_por_usuario_id; quien registra puede diferir del empleado participante. No se deduce su participación operativa por registrar un lote.

Roles de acceso identificados por RolSeeder: Administrador, Encargado y Operador. RolesProduccion es otro catálogo, usado en asignaciones operativas. «Maestro», «Ayudante», «Responsable» y «Pastelera» son textos de las vistas; no acreditan filas del catálogo activo. No hay permisos aplicados por estos roles ni se instala Spatie.

### Contrato de contraseña pendiente

`Usuario::getAuthPassword()` devuelve password_hash, de modo que la verificación del hash puede funcionar. `getAuthPasswordName()` no está sobrescrito y devuelve `password`, heredado de Authenticatable. El provider instalado, si needsRehash es true o se fuerza rehash, hace forceFill con ese nombre y save. Como la tabla tiene password_hash y no password, puede intentar actualizar una columna inexistente y romper ese intento de login. La estructura y ruta de código están comprobadas; no se forzó un rehash contra la BD real.

**IMPORTANTE / PENDIENTE:** mapear el nombre a password_hash y probarlo en BD aislada, preservando tabla y modelo. La corrección no consiste en renombrar la columna. No agregar remember_token ni recuperación de contraseña por la sola presencia de traits heredados.

## 8. Producción

### Preguntas respondidas con evidencia

| Pregunta | Respuesta real |
|---|---|
| ¿Tabla de producción/cabecera? | produccion, con id, fecha y registrado_por_usuario_id |
| ¿Detalle? | Tres tablas: detalle_pan, detalle_torta y detalle_bocadito; no existe detalle_produccion genérico |
| ¿Categoría y producto? | categorias → productos.categoria_id → producto_id de cada detalle |
| ¿Tipo de producto? | No entidad/columna tipo_producto; las variantes aparecen como productos.nombre_p o textos de mock |
| ¿Cantidad? | Pan y Bocadito tienen integer > 0; Torta no tiene cantidad |
| ¿Unidad? | Producto tiene unidad_medida_id; Pan añade una unidad por detalle; Torta/Bocadito no la tienen en su detalle |
| ¿Coche? | El catálogo de unidades puede nombrarlo; no hay tabla coche ni fila de catálogo confirmada; Pan integer no representa directamente 12.5 coches |
| ¿Quién registra? | Usuario por registrado_por_usuario_id, no empleado_id directo |
| ¿Empleado participante? | Las tres asociaciones detalle_*_empleado con empleado_id y rol_produccion_id |
| ¿Turno? | Solo detalle_pan.turno_id; no está en cabecera ni empleado ni detalles de Torta/Bocadito |
| ¿Fecha? | produccion.fecha DATE, sin hora técnica de creación |
| ¿Observación? | No hay columna en producción o sus detalles; el textarea existe en UI |
| ¿Foto? | detalle_torta.foto varchar(255) nullable; ruta/referencia simple, no attachment ni almacenamiento programado |
| ¿Forma? | detalle_torta.forma varchar(30) NOT NULL, sin enum/check para circular/rectangular |

### Cardinalidad y separación por categoría

Los comentarios de Produccion dicen «máximo un detalle por categoría», pero sus FK produccion_id no son UNIQUE. La BD admite múltiples detalles de una misma categoría y varias categorías bajo una cabecera. Eloquent hasOne solo expone un detalle, pudiendo omitir otros si los hubiera. No se verificaron filas duplicadas. Tampoco existe restricción que vincule la categoría del producto con la clase de tabla de detalle.

**NECESITA DECISIÓN DE NEGOCIO:** una cabecera por ítem, una por categoría/lote con varias líneas o un lote con varias categorías. Si se requiere un detalle, debe garantizarse según diseño aprobado; si son varios, revisar la relación. No cambiar hasOne a hasMany sin resolverlo. No introducir automáticamente un detalle genérico.

### Foto solo para Torta

**SOPORTADO PARCIALMENTE.** La estructura tiene foto exclusivamente en detalle_torta, no en detalle_pan ni detalle_bocadito, y un producto relacionado permite identificar su categoría. Puede guardar la referencia de la foto y separar las categorías sin nuevas tablas. Sin embargo foto es nullable, no hay validación condicional y store es un stub; ningún backend exige la foto para Torta o verifica que el Producto pertenezca a esa categoría.

La decisión del usuario está tomada: fotografía de control de calidad requerida solo para Torta; Pan/Bocadito sin esa obligación. La subida, validación de archivo y almacenamiento siguen PENDIENTES. El texto de UI dice obligatoria, pero el input file no usa required y ocultarlo por CSS/JS no evita enviar un archivo conservado al cambiar a Pan. No se implementa nada durante esta auditoría. `detalle_pedidos.foto_referencia` es otra función, no foto de control de calidad de producción.

### Forma de Torta

**SOPORTADO ACTUALMENTE para almacenamiento.** forma varchar(30) admite Circular/Rectangular y los botones actuales envían circular/rectangular. No requiere una tabla nueva ni un enum para almacenar esas opciones. Validar el conjunto permitido y acordar su representación es PENDIENTE; la ausencia de validación no equivale a ausencia de soporte en esquema.

### Asociaciones y pivots

Las tres tablas detalle_*_empleado relacionan muchos detalles con muchos empleados y guardan rol_produccion_id como atributo del vínculo. Son asociaciones de negocio con rol; no se debe descartar ese atributo ni convertirlas sin evaluación en un pivot simple. Cada PK (detalle_id, empleado_id) permite un solo rol por empleado y detalle. No hay id, timestamps ni historia de cambios de rol.

Sus modelos extienden Model, no Pivot; `$incrementing=false` evita esperar id autogenerado, pero no enseña a Eloquent la PK compuesta. La introspección del predicado de guardado, sin ejecutar SQL, produce `where id is null` en los tres. Lecturas y algunos inserts con claves explícitas pueden funcionar; updates/deletes/refresh/find por instancia no tienen un contrato correcto. No es un fallo funcional reproducido mediante escritura porque esta fase lo prohíbe.

**IMPORTANTE:** decidir manejo mediante relación pivot/custom Pivot o consultas acotadas por ambas claves u otro diseño aprobado, preservando datos. No poner un array en `$primaryKey`: Eloquent no soporta PK compuestas de forma nativa. [Referencia oficial sobre claves compuestas](https://laravel.com/framework/docs/13.x/eloquent#composite-primary-keys).

### Diagrama específico de negocio

```text
Usuario --FK de registrador--> Produccion --FK de cabecera--> DetallePan
                                            |                 |-- Producto --> Categoria
                                            |                 |-- UnidadMedida
                                            |                 |-- Turno
                                            |                 +-- DetallePanEmpleado --> Empleado --> Cargo
                                            |                                         +--> RolProduccion
                                            +--> DetalleTorta -- Producto
                                            |       +--> DetalleTortaEmpleado --> Empleado / RolProduccion
                                            +--> DetalleBocadito -- Producto
                                                    +--> DetalleBocaditoEmpleado --> Empleado / RolProduccion
Usuario --FK de registrador--> Pedido --> DetallePedido --> Producto / UnidadMedida
Producto --> UnidadMedida
Usuario --> Empleado / Rol
Pedido <--> Produccion: [NO IMPLEMENTADO: no FK ni asociación de cumplimiento]
Producto/Produccion/Pedido <--> Stock: [NO IMPLEMENTADO: no tablas/modelos de Stock]
Empleado <--> Turno directo: [NO IMPLEMENTADO]
Torta/Bocadito <--> Turno de producción: [NO IMPLEMENTADO]
```

Las flechas desde cabecera a detalle del diagrama muestran navegación de negocio por una FK ubicada en el detalle, no una columna producto_id en Produccion. El mapa exacto de dirección está en sección 4. No hay conexión directa Pedido–Produccion por compartir Producto o Usuario.

## 9. Productos

Producto existe y corresponde a productos. Tiene nombre_p, temporada_fe DATE nullable, activo boolean default true, categoria_id y unidad_medida_id. No hay precio, SKU, inventario actual, imagen propia, tipo_producto, forma de torta en producto ni timestamps.

Categoria::productos y Producto::categoria están correctas. Las dos relaciones con UnidadMedida también. Producto tiene tres hasMany a los detalles de producción; no relación directa a una cabecera, ni inversa de DetallePedido, aunque la FK de pedidos sí existe.

La categoría no tiene un enum/código UNIQUE: nombres arbitrarios, sin unicidad. No se debe depender de IDs fijos o los textos del selector para validar categoría. **NECESITA DECISIÓN DE NEGOCIO:** significado de temporada_fe, unidad predeterminada del producto frente a unidad elegida en Pan, variantes como sabor/nombre de producto y conversiones por producto. La regla para reconocer Pan/Torta/Bocadito debe basarse en datos reales/identificadores acordados, aún sin catálogo consultado.

## 10. Turnos

Turno es una tabla y un modelo, no enum ni string directo de empleado. turnos contiene id y nombre_turnos varchar(100), sin timestamps/UNIQUE/valores restringidos. Solo está relacionado con detalle_pan.turno_id y Turno::detallesPan.

Las vistas de Producción e Historial tienen opciones estáticas 1=Mañana y 2=Noche; Dashboard muestra MAÑANA y TARDE, y las tarjetas Torta/Bocadito dicen Turno Único. No existe TurnoSeeder en este checkout. Esos textos y números no verifican el catálogo activo. Hay inconsistencia UI que requiere acordar nombres y alcance de turnos, no crear una tabla ya existente.

No hay FK Empleado–Turno, Usuario–Turno ni fecha/calendario de asignación laboral. Si se quiere un historial de turnos del personal, es una función y un diseño adicional PENDIENTE de definición.

## 11. Pedidos

Pedido y DetallePedido tienen tablas y relaciones de pertenencia coherentes. Pedido registra nombre_cliente nullable, fecha_registro DATETIME default current_timestamp, fecha_entrega_prometida DATETIME obligatoria, entregado boolean default false y registrado_por_usuario_id.

DetallePedido contiene pedido_id, producto_id, unidad_medida_id, cantidad DECIMAL(10,2), especificaciones TEXT nullable y foto_referencia varchar(255) nullable. Pedido::detallePedidos infiere pedido_id correctamente. DELETE de Pedido casca a sus detalles; Producto/Unidad/Usuario se restringen al borrar si tienen referencias.

No hay tabla/modelo Cliente, contacto/dirección/teléfono de cliente, precio/pago, estado_pedido con varios valores, fecha_entrega_real, responsable laboral directo, reserva de inventario ni vínculo a Produccion. nombre_cliente es una instantánea textual, no una FK de Cliente. `entregado` permite dos situaciones, no cancelación, preparación o entrega parcial. No se infiere un workflow por el nombre del campo.

`resources/views/history_pedido/index.blade.php` es un esqueleto sin datos; no tiene ruta ni controller asociado. No significa que sus modelos sobren: pertenecen al esquema y se relacionan entre sí y con Usuario/Producto/UnidadMedida. Implementación HTTP de Pedidos: PENDIENTE.

## 12. Stock

**NO SOPORTADO por el esquema actual / PENDIENTE de diseño.** No hay tablas ni modelos de stock/inventario, movimientos, entradas/salidas, merma, almacenes, insumos, recetas o reservas. productos no tiene stock_actual y detalle_pan.cantidad es una cantidad de producción, no saldo disponible.

Las 25 tablas activas confirman la ausencia; no se basa solo en nombres de archivos. No existe API/controller/ruta de Stock. El Roadmap histórico menciona insumos/recipes/stock_movements, pero no están implementados aquí. No elegir ahora entre saldos y movimientos, ni inventar cómo Produccion incrementa o Pedido descuenta: esas reglas necesitan definición. No se propone crear modelos de tablas inexistentes como parte automática de este sprint.

## 13. Comparación Base de Datos vs Figma

Comparación contra requisitos expresos y vistas existentes. El enlace real está identificado, pero el contraste visual del archivo Figma permanece PENDIENTE de acceso; no se infieren contenidos adicionales de un diseño no abierto. Soporte de datos no significa pantalla conectada ni funcionalidad probada.

| Elemento Figma/requisito | Datos necesarios | ¿Existe soporte BD? | Modelo/tabla | Acción futura |
|---|---|---|---|---|
| Producto y categoría | nombre + categoría | SOPORTADO ACTUALMENTE | Producto / Categoria | Conectar catálogos; no inventar datos |
| Tipo/sabor de producto | Variante distinguible | SOPORTADO PARCIALMENTE | productos.nombre_p | Decidir si nombres bastan; no crear tipo_producto por convención |
| Fecha de producción | Día del lote | SOPORTADO ACTUALMENTE | Produccion.fecha | Cast/filtros futuros |
| Hora de registro | Fecha/hora técnica | NO SOPORTADO | produccion no tiene created_at | Decidir si se requiere y cómo conservarlo |
| Registrado por | Cuenta registradora | SOPORTADO ACTUALMENTE | Produccion.usuario | Derivar de auth, no recibirla libremente |
| Participantes/responsables | Empleado + rol por detalle | SOPORTADO PARCIALMENTE | detalle_*_empleado | Datos sí; corregir contrato de claves y acceso de listados |
| Turno de Pan | Catálogo + FK | SOPORTADO ACTUALMENTE | Turno / DetallePan | Verificar catálogo; acordar Mañana/Tarde/Noche |
| Turno de Torta/Bocadito o Empleado | Vínculo/alcance | NO SOPORTADO como vínculo | No FK correspondiente | Confirmar si es requisito real |
| Cantidad Pan en coches enteros | cantidad + unidad | SOPORTADO ACTUALMENTE estructural | DetallePan / UnidadMedida | Conectar unidad y exigir >0 |
| 12.5 coches / equivalencia 150 latas | Fracciones y conversión definida | SOPORTADO PARCIALMENTE | Pan integer; equivalencia decimal default 1 | Decidir precisión, unidad canónica y factor real |
| Cantidad Bocaditos | Entero positivo | SOPORTADO ACTUALMENTE | DetalleBocadito.cantidad | Acordar unidad implícita y contrato |
| Cantidad Torta agregada | Cantidad o registro individual | SOPORTADO PARCIALMENTE | DetalleTorta sin cantidad | Decidir una fila por torta vs lote |
| Foto QC únicamente Torta | Referencia de archivo + obligatoriedad condicional | SOPORTADO PARCIALMENTE | DetalleTorta.foto nullable | Validación/subida futura solo Torta |
| Circular/Rectangular | Forma de Torta | SOPORTADO ACTUALMENTE para guardar | DetalleTorta.forma | Validar valores permitidos |
| Observaciones de producción | Texto del lote/detalle | NO SOPORTADO | Textarea sin columna | Acordar dónde pertenece |
| Pendiente/Completada | Estado y transiciones | NO SOPORTADO | Sin estado en producción | Definir semántica antes de columna |
| Total y gráficos | Métrica y agregación por fecha/unidad | SOPORTADO PARCIALMENTE | Cabecera/detalles/productos | Definir qué se cuenta y no sumar unidades incompatibles |
| Historial con filtros | Fecha/categoría/turno | SOPORTADO PARCIALMENTE | Fecha/producto; turno solo Pan | Consultas/paginación pendientes; no N+1 anticipado |
| Cliente de Pedido | Nombre | SOPORTADO ACTUALMENTE básico | Pedido.nombre_cliente nullable | Decidir datos obligatorios y contacto |
| Fecha prometida de entrega | Fecha/hora | SOPORTADO ACTUALMENTE | Pedido.fecha_entrega_prometida | Cast/zona horaria; no fecha real de entrega |
| Estado Pedido | Flag o varios estados | SOPORTADO PARCIALMENTE | Pedido.entregado | Definir si basta booleano |
| Detalle Pedido | Producto/unidad/cantidad/notas/foto referencia | SOPORTADO ACTUALMENTE estructural | DetallePedido | Módulo HTTP pendiente |
| Pedido cubierto por Producción | Asociación y cantidades cumplidas | NO SOPORTADO | No FK/asociación entre ambos | Diseñar solo si se aprueba flujo |
| Stock/saldo/movimientos | Inventario y trazabilidad | NO SOPORTADO | No tablas de Stock | Definir alcance antes de modelos |
| Sidebar con nombre de persona | Usuario → Empleado | SOPORTADO ACTUALMENTE | Usuario.empleado | Navegación ya existe; no autoriza por rol |

## 14. Problemas encontrados

No se confirma ningún hallazgo CRÍTICO que justifique modificar sin aprobación. La tabla incluye prioridad y evidencia; todos los cambios funcionales permanecen PENDIENTES.

| ID | Clasificación | Hallazgo/evidencia | Consecuencia / estado |
|---|---|---|---|
| H01 | IMPORTANTE | Tres modelos de asociación esperan id; tablas tienen PK compuesta | Persistencia por instancia no identifica correctamente el vínculo; resolver antes de updates/deletes |
| H02 | NECESITA DECISIÓN DE NEGOCIO | Produccion hasOne para detalles, sin UNIQUE ni exclusividad de categoría | Lectura puede omitir líneas; no se comprobaron duplicados reales |
| H03 | IMPORTANTE | Usuario lee password_hash pero nombre de escritura heredado password | Rehash puede fallar; pendiente anterior confirmado estructuralmente |
| H04 | NECESITA DECISIÓN DE NEGOCIO | Pan integer versus 12.5 coches en Dashboard | No guardar fracción directamente sin diseño aprobado |
| H05 | NECESITA DECISIÓN DE NEGOCIO | Unidades equivalencia NOT NULL/default 1 y sin CHECK positivo | Desconocido no representable como NULL; factores por definir, no valores reales asumidos |
| H06 | NECESITA DECISIÓN DE NEGOCIO | Observaciones UI sin columna | Decidir cabecera/detalle; no agregar fillable inexistente |
| H07 | NECESITA DECISIÓN DE NEGOCIO | Sin estados/hora de producción; Torta sin cantidad | Definir agregados/estados y granularidad del registro |
| H08 | IMPORTANTE | FK no valida categoría del producto; foto nullable; forma libre | Backend futuro debe aplicar reglas aprobadas; no se exige subir en esta fase |
| H09 | RECOMENDADO | Sin casts propios para fechas/booleans/decimals | Definir contratos y probar; no fallo operativo acreditado |
| H10 | RECOMENDADO | Detalles sin acceso inverso a asociaciones de participantes | Justificado para UI futura; escoger acceso sin capas duplicadas |
| H11 | NECESITA DECISIÓN DE NEGOCIO | Turnos estáticos Mañana/Noche vs TARDE y Turno Único | No IDs/filas confirmados; resolver catálogo y alcance |
| H12 | RECOMENDADO | UserFactory apunta a App/Models/User inexistente y campos de users | Factory inaplicable a Usuario; no usada por tests actuales, no crear User para satisfacerla |
| H13 | OPCIONAL | Imports User sin uso en config/auth.php y DatabaseSeeder | No afectan auth efectiva; limpiar solo como tarea aprobada |
| H14 | IMPORTANTE para preparación de pruebas | Dos migraciones usan ALTER TABLE ADD CONSTRAINT CHECK específico | SQLite no reproduce el dialecto; no se ejecutó ni se promete compatibilidad |
| H15 | RECOMENDADO | Solo 4 seeders de catálogos/personal/cuenta y no idempotentes | Preparar fixtures aisladas sin depender de IDs fijos ni reseed real |
| H16 | NECESITA DECISIÓN DE NEGOCIO | Pedido solo entregado boolean; no FK a Produccion ni Stock | No inferir fulfillment/reservas ni nuevos estados |
| H17 | RECOMENDADO | Notas históricas describen migración/clases ausentes | Separar historia y estado comprobado; no implementar lo ausente por existir una nota |
| H18 | NO NECESARIO | Renombrar usuarios_sistema/password_hash, crear modelos técnicos, instalar paquetes | No resuelve los problemas y contradice el esquema autorizado |

### N+1: actual y potencial

| Archivo/flujo | Consulta/relación | Riesgo real actual | Posible solución futura |
|---|---|---|---|
| components/sidebar.blade.php | auth()->user()->empleado | Posible lazy load de una persona; no hay bucle, no N+1 confirmado | Medir consultas por request antes de modificar |
| DashboardController + dashboard/index | Sin queries ni loops Eloquent; datos fijos | No N+1 de datos de negocio hoy | Al integrar, medir producto/categoría/empleados por listado |
| ProduccionController + produccion/index | No consulta datos; selector estático/vacío | No N+1 de listado Eloquent actual | Diseñar consultas tras definir cardinalidad y asociaciones |
| HistorialController + history/index | Vista fija, filtros console.log | No N+1 actual | Medir eager loading de producto/unidad/turno/registrador/participantes en flujo paginado |
| Modelos Detalle*Empleado / RolProduccion | Relaciones de empleado/rol declaradas, sin consumidor HTTP de listado | N+1 potencial si se recorre una colección haciendo lazy loading | Precargar relaciones realmente consumidas, con query-count test |
| Tests NavigationTest | Empleado precargado en memoria | No mide consultas SQL ni revela N+1 real | Añadir pruebas de consultas solo con BD aislada y flujo integrado |

No se añadió eager loading, autoload de relaciones ni restricciones globales anticipadas.

### Uso y posibles sobrantes

Los 18 modelos aparecen en relaciones entre modelos, además de Usuario en auth, seeder y tests y Empleado en sidebar indirectamente. No hay modelo sin toda referencia global. Varios no son consumidos todavía por controllers de negocio; su estado es SIN USO HTTP OPERATIVO CONFIRMADO, no eliminable. Pedido/DetallePedido conservan respaldo en esquema y relaciones. UserFactory es POSIBLEMENTE SIN USO, con referencia a clase inexistente; no una autorización para borrarla. resources/layouts/app.blade.php contiene solo @vite y no está enlazado como layout en vistas; no se confunde con resources/views/layouts/app.blade.php ni se elimina.

## 15. Decisiones de negocio necesarias

1. ¿Qué representa una cabecera de Producción y cuántas líneas/categorías contiene? Resolver hasOne sin sustituirlo automáticamente.
2. ¿Una fila de detalle_torta equivale a una torta individual o a un lote? ¿Cómo se representan 15 tortas iguales?
3. ¿Se permiten fracciones de coche/lata? ¿Cuál es la unidad canónica y la precisión? ¿La equivalencia depende de producto o cambia con el tiempo?
4. ¿Dónde pertenecen observaciones: cabecera o cada detalle? ¿Se necesitan hora de registro y trazabilidad de cambios?
5. ¿Qué son Pendiente/Completada, cuáles sus transiciones y qué mide Producción Total/los gráficos?
6. ¿Turno corresponde solo a Pan, a todas las categorías, a cabecera o al personal? Acordar Mañana/Tarde/Noche/Único sin asumir IDs.
7. ¿Un empleado puede ejercer más de un rol simultáneo en el mismo detalle? La PK actual solo admite uno. ¿Quién puede figurar como Responsable/Maestro/Ayudante?
8. ¿Qué significa temporada_fe? ¿Cómo se identifica de forma estable la categoría del producto?
9. Para Pedidos/Stock después: estados, entregas parciales/reales, reservas, cumplimiento por producción, merma, unidades y almacenes. No diseñarlos íntegramente ahora.

La fotografía de control de calidad **solo para Torta** y las formas Circular/Rectangular ya están dadas por el usuario. Queda definir el contrato de almacenamiento/validación y presentación; no volver a pedir aprobación de la regla funcional.

## 16. Modelos que deberían modificarse

Propuestas condicionadas a autorización y decisiones; ningún modelo fue modificado.

| Archivo | Cambio justificado futuro | Condición |
|---|---|---|
| app/Models/Usuario.php | Completar getAuthPasswordName sin cambiar tabla/columna | Probar rehash real en aislamiento |
| app/Models/DetallePanEmpleado.php | Resolver identificación/persistencia del vínculo | Escoger estrategia de PK compuesta/pivot |
| app/Models/DetalleTortaEmpleado.php | Mismo contrato de asociación | Misma estrategia coherente |
| app/Models/DetalleBocaditoEmpleado.php | Mismo contrato de asociación | Misma estrategia coherente |
| app/Models/Produccion.php | Revisar tres hasOne y recomendar cast fecha | Cardinalidad aprobada; no anticipar hasMany |
| app/Models/DetallePan.php | Acceso a participantes; cast cantidad si corresponde | Estrategia de asociación/unidades definida |
| app/Models/DetalleTorta.php | Acceso a participantes | No agregar unidad_medida_id que no existe |
| app/Models/DetalleBocadito.php | Acceso a participantes | Estrategia de asociación definida |
| app/Models/Producto.php | Boolean activo y date temporada_fe | Contrato/serialización y significado definidos |
| app/Models/UnidadMedida.php | Decimal equivalencia y, si se usa, inversa pedidos | Contrato de conversiones y consumidor real |
| app/Models/Pedido.php | Boolean entregado/datetimes | No inventar estados ni permisos |
| app/Models/DetallePedido.php | Decimal cantidad | Mantener decimal(10,2) |
| app/Models/Empleado.php | Inversas operativas si la UI realmente las consulta | OPCIONAL; no requiere cambio para Usuario/cargo |

Cargo, Categoria, Rol, RolProduccion y Turno no muestran un desajuste actual que obligue a cambiarlos. No retocar nombres o claves inferidas correctas por uniformidad.

Otros archivos potenciales: UserFactory y tests de modelos, cuando se autoricen fixtures/pruebas. Las migraciones nuevas o correctivas dependen del diseño aprobado; no se propone modificar archivos aplicados en batch 1 como rutina. Controllers/vistas/JS de Producción quedan para su implementación posterior, incluido envío de empleado_id/rol_produccion_id y unidad; no se tocan durante el inicio del Sprint 4.

## 17. Modelos que podrían necesitar crearse

| Grupo | Clasificación | Resultado |
|---|---|---|
| 18 tablas de negocio existentes | Modelo necesario ya existente | No crear nuevos modelos |
| 3 asociaciones detalle–empleado | Pivot / asociación de negocio | Modelos ya existen; evaluar adaptación, no duplicar con otros |
| 7 tablas técnicas/framework | Tabla técnica sin necesidad de modelo de negocio | NO NECESARIO |
| Stock/movimientos/almacenes/insumos | Modelo posiblemente necesario después de diseño | No hay tabla ni requisito detallado aprobado; no nombrar clases definitivas |
| Cliente, TipoProducto, EstadoPedido, attachments | Modelo posiblemente necesario solo si el negocio lo exige | Campos existentes o alcance pendiente; no crear por convención |
| App/Models/User | Clase inexistente referida por factory/imports | NO NECESARIO crear para resolver referencias de scaffold |

No existe «FALTA MODELO» confirmado para una tabla de negocio activa, ni entidad legacy confirmada. Evitar ampliar arquitectura con services/repositories/actions/DTOs por esta auditoría.

## 18. Elementos que NO deben cambiarse

- Nombres reales de las 25 tablas, columnas, PK y FK sin decisión específica; en particular usuarios_sistema, username y password_hash.
- Guard web, provider Eloquent, modelo Usuario y autenticación nativa; no instalar Breeze/Jetstream/Fortify/Spatie.
- Separación Usuario registrador / Empleado participante y Rol de acceso / RolProduccion operativo.
- Tabla Turno ya existente; no duplicarla con un enum o tabla inventada.
- forma/foto de detalle_torta y distinción de foto_referencia de pedidos.
- Whitelists fillable válidas, hidden del hash y timestamps coherentes.
- Claves compuestas y rol de las asociaciones sin una estrategia aprobada; no añadir id automáticamente.
- Cantidades/fechas técnicas versus fechas de negocio y unidades incompatibles.
- Modelos existentes solo porque no tienen controller aún; tablas técnicas no necesitan modelos nuevos.
- Comportamiento funcional y archivos previos de navegación/logout/caché. No ejecutar migraciones, seeders, instalaciones o cambios de UI en esta fase.

## 19. Plan de implementación

Checklist de trabajo futuro, **todo PENDIENTE de autorización**. El agrupamiento en fases no autoriza implementación.

### Fase 1 — Coherencia del modelo de datos actual

- [ ] Revisar este informe y aprobar explícitamente el alcance de implementación.
- [ ] Resolver las decisiones 1–8 de la sección 15; precisar qué entra en Sprint 4.
- [ ] Elegir el contrato de persistencia de asociaciones con PK compuesta, conservando ambos identificadores y el rol.
- [ ] Completar getAuthPasswordName() y comprobar rehash sobre password_hash en BD aislada.
- [ ] Si se aprueban cambios de esquema para cardinalidad, decimales u observaciones, diseñar su evolución y verificar datos existentes en un entorno de ensayo; no sobrescribir migraciones aplicadas por rutina.

### Fase 2 — Relaciones Eloquent

- [ ] Alinear hasOne/hasMany de Produccion con la cardinalidad aprobada.
- [ ] Implementar acceso desde los tres detalles a participantes con la estrategia acordada.
- [ ] Probar todos los vínculos, incluyendo claves inferidas, unicidad y acciones de FK.
- [ ] Añadir inversas Usuario/Producto/Unidad/Empleado solo donde un caso de uso aprobado las necesite.

### Fase 3 — Modelos necesarios para Producción

- [ ] Confirmar que los modelos existentes cubren el contrato aprobado; no crear modelos de detalle duplicados.
- [ ] Añadir únicamente casts justificados para fechas/booleans/decimals y probar formato/precisión.
- [ ] Ajustar fillable solo para columnas aprobadas y escritores controlados; no añadir observaciones o unidad de Torta antes de existir en esquema.
- [ ] Preparar fixtures de Categoría/Producto/Unidad/Turno/RolProduccion/Empleado/Usuario y detalles sin credenciales reales ni dependencia de IDs fijos.
- [ ] Revisar UserFactory para habilitar fixtures de Usuario sin crear un modelo User ajeno al esquema.

### Fase 4 — Preparación para Pedidos/Stock

- [ ] Comprobar contratos de Pedido/DetallePedido y campos de entrega/cantidad.
- [ ] Documentar alcance futuro de estados y conexión a producción/stock, sin implementarlos en este sprint inicial.
- [ ] Mantener Stock pendiente hasta acordar saldo/movimientos, unidades, merma/reservas y entidades.

### Fase 5 — Pruebas y cierre

- [ ] Ejecutar pruebas de lectura, creación/actualización/borrado de asociaciones en MariaDB desechable con esquema fiel.
- [ ] Verificar casts, hidden, fechas, fillable y timestamps, incluida ausencia de columnas técnicas donde no existen.
- [ ] Conservar regresiones de auth/logout/CSRF/intended y comprobar el rehash corregido.
- [ ] Revisar diff y documentación; cerrar solo correcciones implementadas y aprobadas que pasen pruebas.

**Fuera de esta auditoría y del permiso actual:** store funcional, subida de fotos, consultas de dashboard/historial, UI definitiva, permisos, Stock y Pedidos HTTP.

## 20. Pruebas necesarias

### Comprobaciones realizadas durante la auditoría

- Lectura completa de fuentes, esquema de 25 tablas y 28 FK y ledger de 21 migraciones; 2 CHECK activos revisados.
- Introspección sin consultas de filas de los 18 modelos y 44 relaciones: no fillable inexistente, desajuste de timestamps ni belongsTo de negocio sin FK coincidente. Las tres PK compuestas y tres cardinalidades siguen en revisión.
- Predicado de guardado de asociaciones generado sin ejecutar escritura: `id is null`, columna ausente. No se ejecutó un update/delete deliberadamente fallido.
- `php artisan test --compact --do-not-cache-result`: **22 pruebas y 222 aserciones aprobadas**. Cubren navegación/auth/logout/caché, con Usuario/Empleado en memoria y provider simulado; no certifican relaciones/persistencia de Producción o Pedidos ni sesiones database reales.
- `php artisan about --only=environment,drivers`, `php artisan route:list --except-vendor -v`, `composer show laravel/framework` y PHP version: leídos.
- Segunda búsqueda global y comparación del contenido de fuentes contra la instantánea inicial; cambios previos preservados. `git status`, `git diff` y `git diff --check` revisados al cierre.

### Cobertura futura, tras autorización

| Área | Prueba necesaria | Qué comprueba |
|---|---|---|
| Schema y fixture | Esquema aislado del mismo motor y versión compatible | Migraciones, CHECK y FK reales; jamás RefreshDatabase contra la BD de trabajo |
| Pertenencias | Cada FK con fila padre/hijo ficticias | Lectura y claves exactas, incluyendo inference camelCase |
| Usuario–Empleado | Una cuenta por empleado y empleado sin cuenta | UNIQUE y hasOne válido; rol/cargo correctos |
| Produccion–detalle | Múltiples o únicos según decisión; categorías incompatibles | No omitir registros y no mezclar tipos sin regla |
| Asociaciones | Insert, lectura, cambio de rol y delete con ambas claves | No id inexistente, no modificar otro empleado/detalle; clave única |
| Cascadas/restrict/set null | Borrar padres en fixture | Políticas de FK; registrar impacto de borrado autorizado |
| Cantidades/unidades | Enteros/decimales y factores según decisión | Precisión, positividad, no sumas incompatibles |
| Fecha/casts | Fechas nullable, datetime y boolean/decimal JSON | Contrato de salida y zona horaria, sin perder precisión |
| Authenticatable | Rehash y verificación del hash con cuenta ficticia | Escritura solo password_hash y login todavía funcional |
| Mass assignment | Arreglos controlados y campos sensibles | No autorizar rol/registrador mediante entrada arbitraria |
| Timestamps | Create/update en modelos adecuados | Usuario/Empleado actualizan timestamps; otros no esperan columnas ausentes |
| Foto/forma | Validación futura por categoría | Torta exige foto y forma válida; Pan/Bocadito no exigen foto; no subida programada ahora |
| Listados | Consultas medidas antes/después de integrar | Evitar N+1 con número de filas creciente; no eager loading especulativo |
| Pedidos | Detalle decimal y flag/fechas según contrato | No atribuir estados o fulfillment aún inexistentes |

Los CHECK de Pan/Bocadito se agregan con ALTER TABLE ADD CONSTRAINT; no asumir que SQLite soporta ese mismo SQL. Este PHP carece de PDO SQLite según las comprobaciones de la sesión previa; usar pruebas desechables con el motor objetivo cuando se autoricen. No instalar paquetes ni cambiar migraciones para satisfacer automáticamente un motor de pruebas distinto.

## 21. Orden recomendado

1. Revisar el informe y completar el contraste visual del Figma real cuando pueda abrirse el archivo del enlace proporcionado.
2. Acordar cardinalidad de Produccion, granularidad de Torta y unidad/precisión de Pan; cerrar observaciones/estados/turnos que entren en el alcance.
3. Aprobar el alcance funcional de Sprint 4 y preparar el entorno aislado de pruebas del esquema actual.
4. Resolver identificación de las tres asociaciones y contrato de contraseña; probar cada corrección autorizada.
5. Alinear relaciones de Produccion y agregar acceso a participantes según el diseño aprobado.
6. Añadir casts necesarios para los consumidores acordados y preparar fixtures fieles a FK sin IDs asumidos.
7. Comprobar relaciones de Pedidos existentes; mantener Stock/diseños adicionales como pendientes de negocio.
8. Ejecutar cobertura de modelos/datos y regresiones; revisar diff y actualizar documentación con resultados reales.
9. Cerrar Sprint 4 solo tras pruebas. Autorizar posteriormente la implementación de Produccion::store(), subida y conexión de pantallas.

Propuesta temporal, no compromiso de implementación: semana 1 para decisiones, entorno de prueba y contratos de identidad/cardinalidad; semana 2 para relaciones, casts/fixtures y validación. Si faltan decisiones o pruebas, no marcar el sprint terminado por cumplir dos semanas.

### Documentación y estado de entrega

El informe es un artefacto de análisis y no reemplaza mejora_asignado.md. Se actualizaron directamente 14 notas existentes del Vault original, después de leerlas y revisar el contenido propuesto, con autorización de escritura fuera del sandbox. Se releyó lo escrito; no se crearon, movieron ni renombraron notas o carpetas. Se conservaron los registros históricos indicando que no acreditan el checkout actual; no se copió este informe completo ni credenciales.

Rutas relativas a `/home/lukagfv/Documentos/Obsidian Vault/PROYECTO_LARAVEL/`:

| Nota existente actualizada | Información integrada |
|---|---|
| BASE DE DATOS/Migraciones.md | Esquema y ledger actuales; diferencia con migración/seeders históricos |
| BASE DE DATOS/Relaciones.md | 28 FK, acciones de borrado, unicidad y cardinalidades reales |
| BASE DE DATOS/ERD/ERD General.md | Diagrama completo de FK confirmadas y límites del ERD anterior |
| LARAVEL/Models.md | 18 modelos, relaciones, claves compuestas, casts y pendientes |
| PERSONAL Y USUARIOS/Empleados.md.md | Cuenta/cargo, participación operativa y separación del registrador |
| PERSONAL Y USUARIOS/Turnos.md.md | Catálogo real vinculado solo a Pan y alcance pendiente |
| PRODUCCION/Logica de negocio.md.md | Estado operativo real, reglas/decisiones pendientes e historia separada |
| PRODUCCION/Requerimientos.md.md | Soporte de datos y requisitos antes de programar |
| PRODUCCION/Pantallas.md.md | Interacción actual, datos estáticos y diferencias con soporte del esquema |
| STOCK/Requerimientos.md.md | Ausencia de esquema operativo y alcance pendiente de definición |
| DECISIONES/Decisiones de diseño.md.md | Decisión confirmada de foto QC solo para Torta; implementación pendiente |
| INICIO/Estado de proyecto.md | Resumen de auditoría, límites de pruebas y próximos pasos |
| INICIO/Roadmap.md | Sprint 4 oficial de dos semanas; solo auditoría completada |
| DISEÑO/FIGMA/Figma - Proyecto.md | Enlace real aportado por el usuario y contraste visual pendiente de acceso |

Las correcciones y módulos descritos en el checklist permanecen PENDIENTES. No se modificaron modelos, migraciones, controllers, vistas, seeds, factories, tests, paquetes ni datos de negocio durante esta fase.

Referencias oficiales usadas para contrastar comportamiento del framework: [Eloquent y claves](https://laravel.com/framework/docs/13.x/eloquent), [relaciones](https://laravel.com/framework/docs/13.x/eloquent-relationships) y [casts](https://laravel.com/framework/docs/13.x/eloquent-mutators). Las conclusiones sobre este proyecto proceden de sus archivos y metadatos activos, no de adoptar esquemas de ejemplo.

## Decisiones de negocio confirmadas después de la auditoría

Fecha: 2026-10-02. Continuación autorizada del Sprint 4; no implica autorización de módulos HTTP ni aplicación de migraciones en la BD de trabajo.

Estados: **CONFIRMADO** describe una decisión del usuario; **PENDIENTE** trabajo/decisión aún abierta; **IMPLEMENTADO** código existente; **IMPLEMENTADO Y PROBADO** código comprobado con datos ficticios en MariaDB aislada (equivale a IMPLEMENTADO Y COMPROBADO en el Vault). Una decisión confirmada no acredita implementación.

### Decisiones y alcance real

| Decisión | Estado funcional | Estado de soporte actual |
|---|---|---|
| Pan por fecha y sesión Mañana/Noche; no Tarde | CONFIRMADO | produccion.turno_id nullable y relaciones IMPLEMENTADOS Y PROBADOS aisladamente; aplicación real PENDIENTE. detalle_pan.turno_id legacy conservado |
| Una sesión Pan admite varios productos y cantidades diferentes | CONFIRMADO | Produccion::detallesPan hasMany IMPLEMENTADO Y PROBADO |
| Pan: cantidad = total de latas; 1 coche = 18 latas | CONFIRMADO | INTEGER conservado y probado; coches/latas son entrada/salida futura, sin persistencia redundante |
| Panes por lata configurables en Producto y snapshot histórico por DetallePan | CONFIRMADO | Nueva migración nullable y fillable IMPLEMENTADOS Y PROBADOS aisladamente; aplicación real y escritor PENDIENTES |
| Torta diaria, sin turno; varias tortas por producción | CONFIRMADO | Produccion::detallesTorta hasMany IMPLEMENTADO Y PROBADO |
| Un DetalleTorta representa una torta física | CONFIRMADO | Filas independientes con mismo producto, distinta forma/foto/observación: IMPLEMENTADO Y PROBADO en BD aislada; sin columna cantidad |
| Foto QC obligatoria solo Torta; Circular/Rectangular por torta | CONFIRMADO | Campos conservados; validación y subida PENDIENTES de flujo funcional |
| Bocadito diario, sin turno; varios tipos y cantidad entera de unidades por detalle | CONFIRMADO | Produccion::detallesBocadito hasMany IMPLEMENTADO Y PROBADO |
| Observación nullable por cada detalle, nunca en cabecera | CONFIRMADO | Migración nueva y fillable IMPLEMENTADOS Y PROBADOS en aislamiento; aplicación en BD de trabajo PENDIENTE |
| Muchos participantes; un rol por empleado/detalle; varios empleados con mismo rol | CONFIRMADO | Pivots y PK existente IMPLEMENTADOS Y PROBADOS |
| Roles operativos conceptuales Maestro/Ayudante/Practicante, sin IDs fijos | CONFIRMADO | No se alteró catálogo real ni se añadieron seeders; fixtures solo en BD ficticia |
| Una producción contiene una sola familia | CONFIRMADO | categorias y produccion.categoria_id FK nullable IMPLEMENTADOS Y PROBADOS aisladamente; aplicación real PENDIENTE. Enforcement en escritor futuro PENDIENTE |
| Historial: tarjeta resumida más acceso Detalles a toda la información | CONFIRMADO | Requisito documentado; interfaz/consulta de detalles PENDIENTE, sin cambios de UI |
| Hora de agotamiento de Pan pertenece a Stock/disponibilidad | PROPUESTA FUTURA | PENDIENTE DE VALIDACIÓN DEL CLIENTE; NO IMPLEMENTADO |

### Dudas de la sección 15 que quedaron resueltas

| Punto original | Resolución |
|---|---|
| 1. Cabecera, líneas y categorías | CONFIRMADO: muchos detalles de una sola familia; categorias será el catálogo de familias y produccion.categoria_id será la FK |
| 2. Torta individual o lote | RESUELTO: una fila por torta física; no cantidad |
| 3. Fracciones/unidad/equivalencias | CONFIRMADO: cantidad Pan = total de latas INTEGER; 1 coche = 18 latas; panes por lata por Producto y snapshot por DetallePan IMPLEMENTADOS Y PROBADOS aisladamente. Valores reales, máximo operativo y papel de unidad_medida_id PENDIENTES |
| 4. Observaciones/hora/trazabilidad | Observación por detalle RESUELTA; hora y trazabilidad técnica siguen PENDIENTES |
| 5. Estados y métricas | PENDIENTE |
| 6. Turnos y alcance | CONFIRMADO: solo sesión Pan, Mañana/Noche; produccion.turno_id IMPLEMENTADO Y PROBADO aisladamente, PENDIENTE DE APLICACIÓN EN BD DE TRABAJO |
| 7. Roles por participante | RESUELTO: uno por empleado/detalle, varios trabajadores con el mismo rol; elegibilidad/permisos fuera de alcance |
| 8. Temporada/categoría estable | categorias como familias CONFIRMADO e IMPLEMENTADO Y PROBADO en aislamiento; temporada_fe PENDIENTE |
| 9. Pedidos/Stock | PENDIENTE; hora de agotamiento solo propuesta a validar |

### Estrategia de asociaciones elegida e implementada

Se reutilizaron DetallePanEmpleado, DetalleTortaEmpleado y DetalleBocaditoEmpleado como **custom Pivot** nativos. No hay modelos duplicados, nueva PK, paquete de claves compuestas ni array en primaryKey.

Cada Detalle*::empleados() declara belongsToMany con tabla, foreignPivotKey y relatedPivotKey explícitos, using de su clase existente y withPivot('rol_produccion_id'). No requiere timestamps en la asociación.

Contrato de operaciones:

```php
$detalle->empleados()->attach($empleadoId, ['rol_produccion_id' => $rolId]);
$participantes = $detalle->empleados; // $empleado->pivot->rolProduccion
$detalle->empleados()->updateExistingPivot($empleadoId, ['rol_produccion_id' => $otroRolId]);
$detalle->empleados()->detach($empleadoId);
```

Las claves del pivot también se declaran en cada clase para mantener actualizaciones/eliminaciones/refresh coherentes al leerla por consultas existentes (p. ej. RolProduccion::detallePanEmpleados). AsPivot usa las dos claves originales. Se comprobaron SQL de update/delete sin referencia a id, cambios de rol y aislamiento respecto a otros empleados/detalles.

Las pertenencias propias y accesos de RolProduccion se conservaron. Las lecturas directas deben filtrar ambas claves; no usar find($id), destroy($id) ni route binding de una supuesta PK individual. Pivot no incorpora soporte genérico de PK compuestas. La PK real sigue impidiendo repetir al empleado, independientemente del rol.

La búsqueda de consumidores encontró los tres hasOne de Produccion solo en ese modelo; ninguna llamada operativa en controller/Blade/tests. Se sustituyeron por detallesPan/detallesTorta/detallesBocadito. Los métodos singulares de los pivots son belongsTo a su detalle y se conservaron: representan otra dirección.

### Migración creada y transición de observaciones

`database/migrations/2026_10_02_162600_add_observacion_to_production_details.php` añade TEXT nullable observacion a detalle_pan, detalle_torta y detalle_bocadito. TEXT permite notas libres razonables sin imponer aún un límite de formulario no aprobado. Los registros anteriores reciben NULL; no se cambia cantidad, turno, PK, FK o cabecera.

El rollback comprueba las tres tablas antes del primer DROP. Si cualquier observacion no es NULL, lanza una excepción y conserva las columnas; necesita estrategia de conservación aprobada antes de revertir. El test sitúa el texto en la segunda tabla para detectar una eventual reversión parcial. El down y la reaplicación se probaron únicamente en BD ficticia después de retirar explícitamente textos de prueba.

Estado: **IMPLEMENTADO Y PROBADO en MariaDB aislada**. **PENDIENTE de aplicación en BD de trabajo**; no se ejecutó allí. Las 21 migraciones anteriores permanecen idénticas. En esa fase el repositorio tenía 22 migraciones; la continuación actual añade la número 23 de familia/turno, sin tablas adicionales. Los writers futuros que envíen observacion requieren primero desplegar/aplicar esta migración con autorización.

### Familia: decisión estructural confirmada e implementada

**CONFIRMADO, 2026-10-02:** categorias será el catálogo oficial de familias actuales: **Pan, Torta y Bocadito**, exactamente. produccion.categoria_id será la FK a categorias.id; productos.categoria_id ya referencia ese mismo catálogo. No existe otra clasificación paralela.

Antecedente conservado: en la fase anterior esta elección estaba detenida. Se evaluaron reutilizar categoria_id, una familia textual y derivar familia de los detalles. El usuario eligió el catálogo existente; las otras opciones se descartan en esta fase. La FK identifica una cabecera incluso sin detalles; no garantiza por sí sola coherencia de familia entre todas las tablas.

**IMPLEMENTADO:** CategoriaSeeder usa Categoria::firstOrCreate por nombre_categorias para Pan/Torta/Bocadito, sin IDs impuestos ni cambios a registros encontrados. DatabaseSeeder lo incorpora antes de los seeders anteriores; no se cambian sus reglas. No se ejecutó DatabaseSeeder ni CategoriaSeeder en la BD de trabajo. Idempotencia secuencial comprobada; sin UNIQUE en nombre_categorias no se promete unicidad ante ejecuciones concurrentes.

**IMPLEMENTADO:** 2026_10_02_180000_add_categoria_and_turno_to_produccion.php añade categoria_id nullable FK a categorias y turno_id nullable FK a turnos. Una sola migración agrupa ambas columnas del contrato de sesión aprobado. ON DELETE/UPDATE RESTRICT, sin cascadas, backfill ni UNIQUE. En esta fase se llegó a **23 migraciones en código**: 21 originales conservadas, observaciones conservada y familia/turno. El bloque posterior de unidades eleva el total a 24, sin reescribirlas.

**IMPLEMENTADO Y PROBADO:** ambas FK, conservación de filas anteriores con NULL, rechazo de referencias inexistentes, down/up y relaciones verificadas en MariaDB aislada. El down bloquea antes de eliminar columnas si alguna cabecera tiene categoría o turno asignados; una reversión con datos requiere estrategia de conservación aprobada. Se ensayó cada campo por separado y la reaplicación solo con asignaciones ficticias retiradas.

**PENDIENTE DE APLICACIÓN EN BD DE TRABAJO:** las dos migraciones aditivas y CategoriaSeeder. No se aplicó ninguna. categoria_id permanecerá nullable durante transición; NOT NULL se decidirá después de revisar datos, implementar escritores, migrar registros y verificar consumidores.

Relaciones nuevas: Produccion::categoria belongsTo Categoria y Produccion::turno belongsTo Turno; Categoria::producciones y Turno::producciones hasMany. Fillable de Produccion conserva fecha/registrado_por_usuario_id y añade solamente categoria_id/turno_id. Se conservan usuario y los tres hasMany de detalle, así como Turno::detallesPan y DetallePan::turno.

El escritor futuro debe comprobar **produccion.categoria_id == producto.categoria_id** para cada detalle y utilizar únicamente la tabla correspondiente a la familia. No se añadieron eventos, Observers, Triggers, Services ni controllers para impedir mezclas. ProduccionController::store sigue fuera de alcance. La prueba estructural no acredita esas reglas HTTP.

**PENDIENTE:** decidir si se permite más de una cabecera para fecha + categoría o fecha + categoría + turno. No hay UNIQUE para estas combinaciones ni se añade UNIQUE incidental a nombre_categorias.

**PROPUESTA FUTURA / PENDIENTE DE VALIDACIÓN DEL CLIENTE:** otras familias, por ejemplo Café. El catálogo permitiría identificar nuevas familias sin otra columna de tipo, pero primero deben aprobarse sus reglas de negocio. No se creó Café, productos/reglas/tablas ni módulo para esa idea.

### Turno de sesión Pan: transición aditiva implementada

**CONFIRMADO:** Pan por fecha + Mañana/Noche; Torta/Bocadito por fecha, sin turno. produccion.turno_id es la ubicación aprobada del turno de sesión de Pan y permanece nullable para las otras familias. La obligación de turno en Pan se validará con el flujo funcional futuro, no mediante NOT NULL global.

**IMPLEMENTADO Y PROBADO en aislamiento:** Pan persiste con un turno localizado en catálogo, Torta/Bocadito con turno NULL y las cabeceras de transición con categoría/turno NULL. Las relaciones inversas y varios detalles funcionan. detalle_pan.turno_id sigue NOT NULL, con su FK original y relación legacy; no se retiró ni se hizo nullable. La migración no copia turnos automáticamente.

**PENDIENTE DE APLICACIÓN EN BD DE TRABAJO** y PENDIENTE de migración de escritores/consumidores. Tras autorización del flujo, mantener coherencia entre ambos turnos durante la transición. Un backfill necesita revisión específica: no inventar familia/turno para cabeceras vacías, mixtas o ambiguas, ni dividir, fusionar o borrar datos. Retirar detalle_pan.turno_id requerirá otra migración aprobada después de migrar datos y consumidores.

### Datos reales consultados exclusivamente en lectura

2026-10-02, conexión configurada del proyecto por PDO directo, sin arrancar escritores de Laravel. SELECT y metadatos dentro de START TRANSACTION READ ONLY, finalizado con ROLLBACK. El sandbox bloqueó la conexión (2002); la ejecución autorizada fuera de él permitió la lectura sin escrituras.

| Comprobación | Resultado real |
|---|---|
| Cantidad de categorias / valores id,nombre_categorias ordenados por id | 0 / ningún valor |
| Cantidad de productos / relación real producto → categoria por LEFT JOIN | 0 / ninguna relación poblada |
| Valores id,nombre_turnos ordenados por id | Catálogo vacío; Mañana/Noche no existen hoy. No se cargaron ni corrigieron automáticamente |
| Cantidad de producciones | 0 |
| Existencia y cantidad de detalle_pan / detalle_torta / detalle_bocadito | Las tres tablas existen; 0 filas en cada una |
| Producciones con varios turno_id en detalle_pan | Ninguna; no hay datos con los que evaluar sesiones existentes |
| Cabeceras con distintas tablas de detalle o categorías de producto | Ninguna; no hay datos. No acredita enforcement |
| Producto de familia incompatible con tabla de detalle | Ningún caso; conjuntos vacíos |
| Estructura de transición en BD de trabajo | Solo detalle_pan.turno_id NOT NULL; categoria_id/turno_id de cabecera y observaciones todavía ausentes |
| Migraciones 2026_10_02 registradas | Ninguna |

El catálogo de turnos vacío se documenta como **PENDIENTE de carga autorizada** antes de operar Pan. En la fase de familia/turno aún no había TurnoSeeder; la continuación posterior lo implementa y prueba, sin cargarlo en la BD de trabajo ni asumir IDs. Mañana/Noche se usan únicamente como fixtures en MariaDB aislada. No se encontraron otros datos inesperados o inconsistencias en los conjuntos consultados.

### Comandos para una autorización posterior

No ejecutados contra la BD de trabajo. Aplicación selectiva de lo revisado, después de autorizar y comprobar entorno/datos:

```bash
php artisan migrate --path=database/migrations/2026_10_02_162600_add_observacion_to_production_details.php
php artisan migrate --path=database/migrations/2026_10_02_180000_add_categoria_and_turno_to_produccion.php
php artisan db:seed --class=CategoriaSeeder
```

No ejecutar DatabaseSeeder completo para esta carga: contiene seeders previos no idempotentes. Cargar Mañana/Noche requiere autorización posterior específica: TurnoSeeder está IMPLEMENTADO Y PROBADO aisladamente en la continuación descrita abajo. No se ejecutó sobre la BD de trabajo.

### Contrato de Usuario y casts

Usuario::getAuthPasswordName() devuelve password_hash. Se preservaron Usuario, usuarios_sistema, getAuthPassword, hidden, web/provider y controllers. El test realiza login con provider Eloquent real y hash ficticio de menor costo, comprueba el UPDATE sobre password_hash, lectura posterior, segundo login sin rehash innecesario, intended, logout y rechazo de páginas protegidas. No hay columna password.

Casts implementados y probados sobre lecturas/serialización reales en BD ficticia:

- Producto.activo boolean: flags PHP coherentes; no se cambió temporada_fe porque carece de consumidor/regla actual definida.
- Produccion.fecha date: día de la sesión como fecha, sin introducir hora técnica.
- Pedido.entregado boolean y sus dos fechas datetime: flag y fechas con hora, sin inventar estados nuevos.
- UnidadMedida.equivalencia_unidades y DetallePedido.cantidad decimal:2: cadenas decimales exactas, sin conversiones a float. Un cast no define equivalencias de negocio.

La revisión de consumidores no encontró lectores HTTP operativos de esos campos que exigieran conservar otra interfaz; auth y sidebar mantienen sus relaciones. No se añadió cast de cantidad Pan ni se convirtió INTEGER a DECIMAL. No se aplicaron hashed/encrypted/temporada ni casts de FK por estética.

### Pruebas y reproducibilidad

- Baseline anterior: 22 pruebas, 222 aserciones aprobadas.
- Resultado de la fase anterior: **39 pruebas, 425 aserciones, todas aprobadas**. Resultado de catálogo/familia/turno: **45 pruebas, 511 aserciones**, seis casos nuevos y ampliación del test DDL. Resultado de TurnoSeeder: **51 pruebas, 553 aserciones, todas aprobadas**, seis casos adicionales. Resultado vigente tras unidades de Pan: **59 pruebas, 672 aserciones, todas aprobadas**, ocho casos adicionales y ampliación de evolución DDL.
- `php tests/run-mariadb.php --do-not-cache-result` ejecuta **php artisan test** con un servidor MariaDB propio sin red, socket/directorio aleatorios bajo /tmp y dos bases ficticias. Requiere binarios MariaDB y PDO MySQL ya instalados; no instala paquetes ni lee .env para conectar al servidor.
- El helper exige socket bajo /tmp/sprint4-*, nombres de bases de prueba y verifica @@datadir/DATABASE antes de cualquier migración. Nunca hace fallback a la BD del proyecto.
- Fixtures ficticias; pruebas de modelos con transacciones revertidas. La prueba DDL usa otra base aislada para que el autocommit de MariaDB no afecte esos tests. CategoriaSeeder y TurnoSeeder se ejecutaron solo contra esa instancia aislada; nunca DatabaseSeeder completo, migrate:fresh ni db:wipe.
- Sin SPRINT4_TEST_SOCKET, los 37 casos de persistencia/evolución se omiten explícitamente: un `php artisan test` convencional no acredita esa cobertura. Utilizar el runner para ejecutarla completa.
- Primera pasada de la fase anterior: 38/39; una aserción del segundo login esperaba Dashboard tras visitar Historial como invitado. Se corrigió el test para respetar intended('/history'); no se alteró el comportamiento del controller. Pasadas posteriores completas aprobadas.
- En la fase anterior Pint pasó sobre 17 PHP afectados; en esta continuación pasó sobre los ocho PHP afectados. Sintaxis/diff y conservación de fuentes ajenas a la tarea comprobados al cierre. No se repitió build de frontend porque no cambió ningún asset/vista.

Cobertura: múltiples productos/cantidades por cabecera, tortas individuales, notas nullable/independientes, mismo rol para varios empleados, duplicado rechazado por PK, attach/lectura/update/refresh/delete/detach por ambas claves, casts sin pérdida decimal, Usuario/Empleado/Rol/Cargo y rehash/login/logout/navegación. No certifica enforcement de familia/turno, validación foto/forma, CRUD HTTP, sesiones database, navegador nuevo ni despliegue.

### Seguimiento de pendientes y límites

- [x] Tres hasMany plurales y accesos de participantes probados.
- [x] Custom pivots con rol y conservación de PK compuestas probados.
- [x] Contrato password_hash y rehash comprobados.
- [x] Observación por detalle en nueva migración, con pruebas de conservación/rollback protegido.
- [x] Casts justificados y tests aislados reproducibles.
- [x] CONFIRMADO: categorias será el catálogo de familias y produccion.categoria_id será la FK; turno nullable en cabecera aprobado. IMPLEMENTADO Y PROBADO en aislamiento.
- [ ] PENDIENTE DE APLICACIÓN EN BD DE TRABAJO: migración de familia/turno, CategoriaSeeder y TurnoSeeder; migración de consumidores.
- [x] TurnoSeeder Mañana/Noche idempotente por nombre, IMPLEMENTADO Y PROBADO en MariaDB aislada; turnos laborales y relevos fuera de alcance.
- [ ] Nuevas familias como Café: PROPUESTA FUTURA / PENDIENTE DE VALIDACIÓN DEL CLIENTE.
- [x] CONFIRMADO cantidad Pan en latas INTEGER, coche = 18 latas y panes por lata por producto con snapshot histórico; estructura IMPLEMENTADA Y PROBADA en aislamiento.
- [ ] Aplicación real autorizada de panes por lata, valores reales por producto, máximo operativo y papel definitivo de unidad_medida_id.
- [ ] Definir unicidad de sesiones si procede, estados/métricas/hora, temporada_fe y categoría/reglas de Panetón/Turrón.
- [ ] Autorizar y aplicar migración de observaciones en la BD de trabajo, con respaldo y revisión; no se aplicó durante esta tarea.
- [ ] Implementar ProduccionController y validación backend de foto/forma solo en etapa posteriormente autorizada.
- [ ] Historial resumido + Detalles, Dashboard, Stock, Pedidos HTTP y permisos siguen fuera de esta fase.
- [ ] Hora de agotamiento: PROPUESTA / PENDIENTE DE VALIDACIÓN DEL CLIENTE, sin columna de Producción ni diseño de Stock.

En la fase anterior se actualizaron 16 notas existentes del Vault original, preservando historia y distinguiendo pruebas aisladas de estado de BD de trabajo. No se creó ni movió documentación:

| Área del Vault | Notas actualizadas y propósito |
|---|---|
| PRODUCCION | Logica de negocio.md.md, Requerimientos.md.md y Pantallas.md.md: reglas confirmadas, soporte actual e Historial resumen/Detalles pendiente |
| BASE DE DATOS | Migraciones.md y Relaciones.md: migración aditiva de observaciones y cardinalidad/turno transitorio |
| LARAVEL | Models.md: hasMany, custom pivots, casts, rehash y límites probados |
| PERSONAL Y USUARIOS | Turnos.md.md, Empleados.md.md, Usuarios.md.md y Pendientes.md: reglas funcionales y cierre de rehash sin certificar sesiones database |
| DECISIONES | Decisiones de diseño.md.md, Decisiones de base de datos.md.md y Decisiones Laravel.md.md: reglas aprobadas, elección de familia/turno pendiente y estrategia Pivot |
| STOCK | Requerimientos.md.md: hora de agotamiento como PROPUESTA pendiente de validación, NO IMPLEMENTADO |
| INICIO | Estado de proyecto.md y Roadmap.md: resultados reales y pendientes antes de cerrar Sprint 4 |

En la fase anterior mejora_asignado.md registró solo el cierre del contrato de contraseña; ahora añade el seguimiento de catálogo/sesión. Este informe sigue siendo el documento técnico del Sprint 4. No se ejecutó ninguna migración contra la BD real. El trabajo se detiene para revisión; no se continúa con store.

### Cierre de esta continuación — 2026-10-02

Código modificado en esta fase: Categoria/Produccion/Turno, CategoriaSeeder, incorporación en DatabaseSeeder, nueva migración de familia/turno, ProductionFamilyRelationshipsTest y ampliación de ProductionObservationsMigrationTest. Pint solo en los ocho PHP afectados (también retiró el import User sin uso de DatabaseSeeder). Modelos previos, las 21 migraciones originales y la migración de observaciones se conservaron. Tests previos de modelos/auth siguen pasando.

Documentación: este informe, mejora_asignado.md y las notas originales necesarias de BASE DE DATOS (Relaciones, Migraciones, TABLAS/Categorias, ERD General), LARAVEL/Models, PRODUCCION (Logica de negocio y Requerimientos), PERSONAL Y USUARIOS/Turnos, DECISIONES (base de datos y diseño) e INICIO (Estado de proyecto y Roadmap). Las 12 notas originales fueron actualizadas con autorización de escritura fuera del sandbox y verificadas por relectura/hash. Se mantienen nombres reales .md.md e historia, sin copias en el repositorio.

Detención al terminar esta fase: sin migraciones/seeders/escrituras en la BD de trabajo; sin continuar store, Historial, Dashboard, fotos, Stock, Pedidos, permisos, Café o frontend. Observaciones permanecen por detalle y hora de agotamiento como propuesta futura de Stock pendiente de validación del cliente.

### TurnoSeeder y alcance del catálogo — 2026-10-02

**CONFIRMADO:** turnos representa exclusivamente los turnos de PRODUCCIÓN DE PAN, exactamente **Mañana y Noche**. Tarde no pertenece a este catálogo. La persona que anota puede trabajar otro horario o cubrir un relevo temporal, incluso por la tarde: el sistema conserva quién registró mediante produccion.registrado_por_usuario_id. No administra asistencia, horarios laborales, relevos, calendarios ni quién reemplaza a quién; no se persisten turnos laborales ni se crean empleado.turno_id, empleado_turnos o historiales de personal. Esto precisa el alcance funcional sin cambiar estructuras existentes.

**IMPLEMENTADO:** database/seeders/TurnoSeeder.php usa Turno::firstOrCreate por nombre_turnos para Mañana/Noche. DatabaseSeeder lo registra después de CategoriaSeeder y antes de los seeders anteriores; turnos es un catálogo sin dependencias de personal. No se modifica la migración original ni se añade UNIQUE. Idempotencia secuencial; sin restricción UNIQUE no se garantiza unicidad ante ejecuciones concurrentes. No elimina ni renombra registros existentes.

**IMPLEMENTADO Y PROBADO** exclusivamente en MariaDB aislada: tests/Feature/TurnoSeederTest.php añade seis casos: creación exacta y doble ejecución; conservación con IDs arbitrarios Mañana=431/Noche=97 (solo fixtures, sin significado funcional); cabecera Pan por cada nombre y filtrado de sus producciones/detalles legacy respecto al otro turno; Torta/Bocadito con turno NULL tras sembrar el catálogo. Registrado_por_usuario_id y usuario conservados. **51 pruebas/553 aserciones aprobadas**, incluidas las 45 anteriores de relaciones, FK/migraciones, observaciones, pivots y auth/login/logout/rehash. No certifica validación HTTP ni gestión laboral inexistentes. Pint/sintaxis solo sobre tres PHP afectados; git status/diff/diff --check y conservación de cambios previos revisados.

**PENDIENTE DE APLICACIÓN EN BD DE TRABAJO:** TurnoSeeder. La tabla vacía procede de la consulta anterior en READ ONLY; no se volvió a consultar ni escribir la BD de trabajo en esta tarea. El runner creó un servidor desechable sin red bajo /tmp y lo detuvo al finalizar. El primer intento estuvo bloqueado por el sandbox; la ejecución autorizada fuera de él aprobó toda la suite. No se ejecutó DatabaseSeeder completo ni se aplicaron migraciones reales.

Comando selectivo para autorización posterior, **NO ejecutado en BD de trabajo**:

```bash
php artisan db:seed --class=TurnoSeeder
```

Documentación de esta tarea: este informe, mejora_asignado.md y siete notas existentes del Vault: PERSONAL Y USUARIOS/Turnos.md.md, PRODUCCION/Logica de negocio.md.md, PRODUCCION/Requerimientos.md.md, DECISIONES/Decisiones de base de datos.md.md, INICIO/Estado de proyecto.md, INICIO/Roadmap.md y BASE DE DATOS/Migraciones.md. Roadmap/Migraciones se ajustan porque todavía indicaban ausencia de TurnoSeeder. Se conserva la historia.

Pendientes del Sprint 4 permanecen: aplicación autorizada de ambas migraciones aditivas y seeders selectivos, transición de escritores/datos/consumidores antes de retirar turno legacy o exigir categoría, unicidad de sesiones, fracciones/unidades/equivalencias, estados/métricas y temporada_fe. Figma/sesiones database siguen sin certificar; flujo HTTP requiere autorización posterior. Se detiene aquí, sin ampliar alcance.


### Unidades de Pan y configuración por producto — 2026-10-02

**CONFIRMADO:** Bocadito se contabiliza por unidades enteras; Torta también por unidad, una fila DetalleTorta por torta física, con forma/foto/observación/participantes propios y sin columna cantidad. Para Pan, **detalle_pan.cantidad = total de latas producidas**, siempre INTEGER positivo, sin renombrar la columna. Ejemplos válidos: 1, 8, 17, 18, 23 y 36 latas. **1 coche = 18 latas**, capacidad general independiente del producto. No se almacenan coches, latas extra, medios coches ni floats/decimales redundantes.

**CONFIRMADO:** panes por lata depende del producto y debe poder editarse en el futuro. Pan Yema tiene equivalencia confirmada de 12 panes por lata; los valores reales de Francés, Árabe, Hamburguesa y demás productos siguen PENDIENTES. No se cargó Yema ni se hardcodeó por nombre, ni se trasladó esta configuración a unidades_medida.

**IMPLEMENTADO:** migración aditiva única y cohesiva `database/migrations/2026_10_02_200000_add_panes_por_lata_to_productos_and_detalle_pan.php` agrega productos.panes_por_lata y detalle_pan.panes_por_lata_usado como INTEGER nullable, sin default inventado, CHECK nuevo, FK nueva, backfill o modificación de cantidad/unidad_medida_id. Son **24 migraciones en código**: las 21 originales y las dos anteriores de Sprint 4 se conservan byte a byte. Producto añade únicamente panes_por_lata a fillable; DetallePan añade panes_por_lata_usado a fillable y comentarios sobre cantidad/snapshot. No hay casts nuevos ni relaciones alteradas.

El snapshot conserva el factor utilizado en esa producción. Ejemplo conceptual: cantidad = 23 y panes_por_lata_usado = 12 representan 276 panes aunque Producto.panes_por_lata cambie a 14. Las columnas permiten NULL durante transición y para productos sin factor operativo. Los registros antiguos reciben NULL, sin inventar su equivalencia; con snapshot desconocido no se puede acreditar un total histórico de panes. Los modelos no copian automáticamente el parámetro. Sprint 5 deberá copiar el valor vigente al crear el detalle mediante el escritor autorizado. Validación positiva del parámetro corresponde al futuro flujo de Productos; no existe validación HTTP implementada por este bloque.

Down comprueba ambos campos antes del primer DROP y se bloquea si cualquiera contiene un valor no NULL, incluso cero. Revertir con datos requiere una estrategia de conservación aprobada; no elimina silenciosamente parámetros o snapshots. El preflight evita la reversión parcial por datos encontrados; no promete atomicidad de DDL ante fallos del servidor ni protección frente a escritores concurrentes. Aplicación/rollback deberán planificarse sin escritores concurrentes.

**IMPLEMENTADO Y PROBADO exclusivamente en MariaDB aislada:** `tests/Feature/PanUnitsTest.php` añade ocho casos: parámetros desconocidos NULL sin copia automática; creación/edición de producto ficticio con snapshots explícitos 12/14 e historial intacto; seis cantidades enteras 1/8/17/18/23/36 sin conversión a coches. `ProductionObservationsMigrationTest.php` excluye las tres aditivas para iniciar con las 21 originales, y ensaya up/down/up, metadatos INTEGER/nullable/default NULL, registros anteriores de productos/detalles/cabeceras/pivot, conservación de cantidad/FK legacy y bloqueo de rollback con cada campo por separado antes de cualquier DROP.

Resultado completo: **59 pruebas, 672 aserciones, 59 aprobadas, ninguna omitida**, mediante `php tests/run-mariadb.php --do-not-cache-result`. Incluye las 51 anteriores de hasMany, observaciones, custom pivots, categoría/turno, seeders selectivos y auth/login/logout/rehash. Primera pasada: 58/59 por una aserción que esperaba NULL PHP en metadatos; MariaDB informa el default SQL como texto `NULL`. Se corrigió solo la aserción para reconocer esa representación y pasó la suite completa. El sandbox bloqueó inicialmente el servidor temporal; el runner autorizado usó un servidor sin red bajo /tmp/sprint4-* y lo detuvo al finalizar. Pint únicamente en cinco PHP afectados, sintaxis PHP y diff revisados. No acredita conversiones operativas, copia HTTP del snapshot ni reglas HTTP todavía inexistentes.

**PENDIENTE DE APLICACIÓN EN BD DE TRABAJO:** esta migración y las aplicaciones anteriores de Sprint 4. No se consultó ni modificó la BD de trabajo en esta tarea; la evidencia previa de productos vacíos sigue siendo histórica, sin revalidación actual. No hubo migraciones/seeders reales ni modificaciones de datos reales.

Comando selectivo para una autorización posterior, **NO EJECUTADO en BD de trabajo**:

```bash
php artisan migrate --path=database/migrations/2026_10_02_200000_add_panes_por_lata_to_productos_and_detalle_pan.php
```

**Contrato futuro de Sprint 5, NO IMPLEMENTADO:** entrada entera coches + latas adicionales; total_latas = coches * 18 + latas. Representación normal de latas adicionales: 0–17. Ejemplos: 18 → 1 coche; 19 → 1 coche + 1 lata; 23 → 1 coche + 5 latas; 36 → 2 coches; 41 → 2 coches + 5 latas. No se implementó conversión automática. Máximo operativo de coches/cantidad PENDIENTE DE VALIDACIÓN DEL CLIENTE, sin límites arbitrarios. Recomendación pequeña: en Sprint 5 centralizar la capacidad aprobada en una sola configuración de dominio (por ejemplo config/produccion.php, latas_por_coche = 18) y suministrar esa misma regla a la interfaz; no crear arquitectura o archivo de configuración ahora.

**PENDIENTES DE NEGOCIO:** valores reales panes_por_lata de cada producto, máximo operativo, papel definitivo de detalle_pan.unidad_medida_id, semántica de temporada_fe y categoría/reglas de Panetón/Turrón. Se conservan unidad_medida_id y sus FK sin cambiar registros ni crear Seeder de Lata. temporada_fe permanece intacta y no se añaden fechas de temporada.

**PROPUESTA FUTURA / IMPLEMENTACIÓN PENDIENTE:** administración de Productos para crear/editar, modificar panes_por_lata, activar/desactivar y manejar productos temporales. Recomendación: preferir activo = false si existe historial antes que borrar físicamente, sin implementar lógica de eliminación. Interfaz coches + latas, equivalencia visual y manejo de temporada se documentan para etapas posteriores.

Documentación original actualizada en ocho notas existentes: PRODUCCION/Logica de negocio.md.md, PRODUCCION/Requerimientos.md.md, BASE DE DATOS/Migraciones.md, BASE DE DATOS/Relaciones.md, LARAVEL/Models.md, DECISIONES/Decisiones de base de datos.md.md, INICIO/Estado de proyecto.md e INICIO/Roadmap.md. No existen notas propias de Productos/Unidades en el árbol inspeccionado; el contenido queda en las notas actuales, sin duplicados. Este informe y mejora_asignado.md registran el seguimiento del checkout.

El bloque estructural autorizado queda implementado y probado en aislamiento, sin declarar todo Sprint 4 cerrado. Quedan revisión del usuario, aplicación real autorizada y pendientes de negocio descritos, además de las decisiones anteriores de unicidad/estados/métricas/trazabilidad. El flujo funcional, migración de escritores/consumidores y remoción del turno legacy pertenecen a fases posteriormente autorizadas. No se continúan store/update/destroy, Requests, frontend/JavaScript, Historial, Dashboard, fotos, CRUD/eliminación/temporada funcional, Stock/Pedidos, permisos, asistencia/horarios o Café. Se detiene aquí para revisión, sin continuar Sprint 5.
