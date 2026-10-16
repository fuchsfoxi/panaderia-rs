# Unidades canónicas - Panadería RS

Fecha: 2026-10-15. Estado: **IMPLEMENTADO Y COMPROBADO en MariaDB aislada** para las verificaciones descritas. Aplicación de migración/seeder en la base local y aceptación manual: **PENDIENTES**. Sin commit ni push.

## Problema encontrado

El catálogo operativo contiene solamente «Lata», pero productos.unidad_medida_id es una FK obligatoria. El backend anterior aceptaba cualquier unidad existente para cualquier familia. Torta/Bocadito podían asociarse a Lata aunque su producción ya guardaba cantidades directas correctamente. El nombre no es UNIQUE ni una identidad estable.

## Estado anterior

Auditoría del checkout limpio antes de editar: migraciones, UnidadMedida/Producto/Categoria y detalles, ProductoController, Requests compartidos/específicos, ActualizarProducto, Actions/Requests/controller de Producción, formulario/JS, consulta/vista de Historial, seeders, fixtures y tests. No existía codigo/slug ni una solución canónica alternativa.

Lectura local en transacciones READ ONLY, sin exponer configuración ni cuentas:

- unidades_medida: id BIGINT UNSIGNED PK autoincremental; nombre_unidades_medida VARCHAR(100) NOT NULL; equivalencia_unidades DECIMAL(8,2) NOT NULL DEFAULT 1.00. Solo índice PRIMARY; ningún UNIQUE de nombre.
- Una fila «Lata», equivalencia 1.00; 2 productos Pan asociados; 5 detalles Pan. Cero productos/detalles Torta/Bocadito.
- productos conserva FK NOT NULL a unidades_medida, RESTRICT en actualización/eliminación. No se modifica esta FK.
- ProduccionDesarrolloSeeder creaba/buscaba Lata por nombre; DatabaseSeeder no garantizaba unidades. No hay factories de Producto/UnidadMedida: las pruebas usan fixtures explícitas. UserFactory del scaffold sigue fuera de este alcance.
- GuardarProductoRequest exigía unidad_medida_id integer/exists, sin correspondencia por familia. El formulario tenía selector manual y diagnóstico por nombre/equivalencia.
- Producción e Historial ya diferenciaban las tres familias; no necesitaban reescritura.

## Regla de negocio

| Familia | Código de unidad | Cantidad registrada | Factor |
|---|---|---|---|
| Pan | LATA | INTEGER de latas | panes_por_lata positivo en Productos; snapshot panes_por_lata_usado en detalle_pan |
| Torta | UNIDAD | INTEGER directo de tortas | Ninguno |
| Bocadito | UNIDAD | INTEGER directo de bocaditos | Ninguno |

UNIDAD es identidad de catálogo, no un multiplicador. 12 latas × 30 = 360 panes estimados; 8 tortas siguen siendo 8; 150 bocaditos siguen siendo 150.

## Solución implementada

Columna codigo nullable y UNIQUE, constantes LATA/UNIDAD y mapeo de familia en UnidadMedida. UnidadMedidaSeeder garantiza las identidades sin IDs fijos. CrearProducto y ActualizarProducto delegan la asociación a ResolverUnidadProducto. ProductoController conserva coordinación HTTP; no incorpora reglas de negocio nuevas.

La identidad se resuelve mediante WHERE codigo, respaldado por UNIQUE. Nunca se usa first() por nombre para resolver unidades. El nombre solo se inspecciona en la adopción conservadora de filas legacy del seeder.

## Base de datos

Se agrega exclusivamente unidades_medida.codigo VARCHAR(20) NULL con UNIQUE. MariaDB permite varias unidades legacy con codigo NULL. Los registros anteriores reciben NULL, sin cambiar IDs, nombres, equivalencias, productos, detalles ni relaciones. No se añaden unidad_medida_id/factores a detalle_torta o detalle_bocadito.

La correspondencia categoría/unidad se garantiza en las Actions del flujo administrativo, no mediante un CHECK SQL entre tablas. Escrituras directas de modelo/SQL pueden saltar esa regla y deben respetarla si se implementan nuevos consumidores.

## Migraciones

Nueva: database/migrations/2026_10_15_100000_add_codigo_to_unidades_medida.php.

Up aditivo sin backfill. Down se niega a retirar codigo si existe cualquier código no nulo; requiere una estrategia de conservación explícita. Se probaron preservación de filas previas, códigos NULL, bloqueo de down con códigos y down/up sin códigos exclusivamente en la segunda base desechable.

Las migraciones anteriores no cambian. Aplicar el esquema antes de servir esta versión: el código requiere la columna. **No se ejecutó esta migración en la base local.** Tras revisión, los comandos selectivos son:

```bash
php artisan migrate --path=database/migrations/2026_10_15_100000_add_codigo_to_unidades_medida.php
php artisan db:seed --class=UnidadMedidaSeeder
```

No usar migrate:fresh, truncate ni DatabaseSeeder completo para esta actualización operativa. Ejecutar mantenimiento del catálogo sin escritores concurrentes; el DDL de MariaDB y el seeder no constituyen una operación atómica conjunta.

## Seeder

UnidadMedidaSeeder está registrado en DatabaseSeeder y puede ejecutarse selectivamente:

1. Si el código ya existe, conserva la fila y sus atributos.
2. Si no existe, inspecciona todas las coincidencias por nombre normalizado con LOWER/TRIM.
3. Si hay una sola fila sin código y con equivalencia 1, asigna únicamente codigo, conservando su ID.
4. Si no hay coincidencias, crea LATA/Lata o UNIDAD/Unidad, equivalencia 1.00, con ID generado.
5. Varias coincidencias, otro código o equivalencia distinta de 1 bloquean esa identidad y producen un error explícito. No se elige ninguna fila ni se cambia su equivalencia. Las otras identidades seguras pueden quedar creadas; no interpretar un error del seeder como carga completa.

Repetición secuencial idempotente comprobada. UNIQUE evita códigos duplicados; ejecuciones simultáneas no se certifican como libres de errores y deben evitarse. La equivalencia se conserva por compatibilidad del catálogo y no interviene en cantidades directas.

ProduccionDesarrolloSeeder usa el código LATA y rechaza reasignar el ejemplo Pan Yema si tiene historial con otra categoría/unidad. Sigue limitado a local/testing y separado de DatabaseSeeder. Su repetición y protección histórica se probaron; no se ejecutó en la base local.

## Producto Pan

Forma de registro «Latas», unidad resuelta LATA. panes_por_lata conserva obligatoriedad, entero estricto positivo y límite INT. El formulario habilita/muestra/requiere el factor solo para Pan. Los snapshots existentes no se rellenan ni recalculan.

## Producto Torta

Forma de registro «Unidad / cantidad directa», unidad UNIDAD. Sin selector de Lata y sin factor visible/habilitado. Factor enviado manualmente no vacío sigue rechazado en backend. Producción conserva INTEGER directo.

## Producto Bocadito

Mismo contrato canónico UNIDAD y cantidad directa. Sin selector de Lata/factor. La cantidad no se transforma mediante equivalencia_unidades.

## Seguridad del backend

El formulario no envía unidad_medida_id. GuardarProductoRequest permite omitirlo; si alguien lo envía, exige integer/exists. Las Actions comparan ese ID con la identidad resuelta por código y rechazan asociaciones incompatibles con ValidationException antes de escribir. POST y PATCH manipulados comprobados para las tres familias.

ActualizarProducto conserva orden categoría → producto y transacción con reintentos. ResolverUnidadProducto inspecciona historial con lecturas actuales lockForUpdate, evitando decidir con un snapshot previo a una producción concurrente. Las 18 carreras de edición/primera producción siguen cubiertas, incluida corrección legacy a unidad canónica antes del primer uso.

Excepción explícita de compatibilidad: si ya hay historial, se conserva la asociación actual al editar otros campos; no se corrige silenciosamente. Cambiar categoría/unidad se rechaza y la vista avisa «Asociación histórica conservada» si difiere del canon. Sin historial, un guardado completo aplica el canon. Cambiar solo activo no modifica unidad. No hay reasignación masiva ni corrección automática de históricos.

## Archivos modificados

- `app/Models/UnidadMedida.php`.
- `app/Actions/Productos/CrearProducto.php`.
- `app/Actions/Productos/ResolverUnidadProducto.php`.
- `app/Actions/Productos/ActualizarProducto.php`.
- `app/Http/Controllers/ProductoController.php`.
- `app/Http/Requests/GuardarProductoRequest.php`.
- `database/migrations/2026_10_15_100000_add_codigo_to_unidades_medida.php`.
- `database/seeders/UnidadMedidaSeeder.php`.
- `database/seeders/DatabaseSeeder.php`.
- `database/seeders/ProduccionDesarrolloSeeder.php`.
- `resources/views/productos/form.blade.php`.
- `resources/js/productos.js`.
- `tests/Feature/UnidadesCanonicasTest.php`.
- `tests/Feature/ProductosTest.php`.
- `tests/Feature/ProductosConcurrenciaTest.php`.
- `tests/Feature/ProductionObservationsMigrationTest.php`.
- `tests/Support/productos-fixtures.php`.
- `tests/JavaScript/ProductosTest.mjs`.
- `docs/UNIDADES_CANONICAS_PRODUCTOS.md`: informe solicitado, nuevo; no copia notas del Vault.

## Tests realizados

Todos los tests de persistencia usan tests/run-mariadb.php: servidor temporal sin red en /tmp, dos bases ficticias y verificación de socket/datadir/base mediante IsolatedMariaDb antes de migrar. Nunca se usó la base real para tests destructivos. HTTP con sesiones array, withoutVite y almacenamiento falso; build por separado. Fixtures generales legacy se conservan para probar compatibilidad.

```bash
# Suite relevante inicial, seguida de correcciones explicadas abajo
php tests/run-mariadb.php --compact --filter='UnidadesCanonicasTest|ProductosTest|ProductosConcurrenciaTest|PanUnitsTest|ProduccionPanStoreTest|ProduccionTortaBocaditoStoreTest|HistorialProduccionTest'
# Suite relevante aprobada
PAO_DISABLE=1 php tests/run-mariadb.php --compact --do-not-cache-result --filter='UnidadesCanonicasTest|ProductosTest|ProductosConcurrenciaTest|PanUnitsTest|ProduccionPanStoreTest|ProduccionTortaBocaditoStoreTest|HistorialProduccionTest'
# Verificación adicional final de catálogo/CRUD/migración
PAO_DISABLE=1 php tests/run-mariadb.php --compact --filter='UnidadesCanonicasTest|ProductosTest|ProductionObservationsMigrationTest'
# Suite completa final
PAO_DISABLE=1 php tests/run-mariadb.php --compact
# JavaScript con conteo individual de casos en Node 26
node --test --test-isolation=none tests/JavaScript/*.mjs
npm run build
# Pint ejecutado en los 15 archivos PHP nuevos/modificados, seguido de --test
# Comprobación final del diff
git diff --check
```

Cobertura A–N solicitada: existencia/resolución, IDs generados desplazados, nombres cambiados, idempotencia, UNIQUE/nullables, asignación de las tres familias, POST/PATCH manipulados, catálogo ausente/ambiguo/incompatible, factor de Pan y rechazo en otras familias, snapshots, 12×30=360, 8/150 directos incluso con equivalencia canónica 30 y factor legacy 99, representación de Historial sin latas/factor en otras familias. Producción/Historial conservan los tests previos y sus archivos de implementación intactos.

## Resultado de pruebas

- Primera regresión amplia: 435 aprobadas y 8 fallidas de 443. Se investigó: seis carreras enviaban unidad arbitraria al recategorizar; dos verificaciones históricas recibían error de unidad antes del error de categoría. Se adaptaron payloads al contrato canónico y se priorizó protección histórica con lecturas actuales bajo lock; no se relajaron las reglas para hacer pasar pruebas.
- Regresión relevante corregida: **443 aprobadas, 3.791 aserciones, 0 fallidas**.
- Prueba de migración individual: **1 aprobada, 173 aserciones**.
- Revisión adicional final: **165 aprobadas, 1.212 aserciones, 0 fallidas**; incluye dos casos del seeder de desarrollo y equivalencia canónica 30.
- Suite completa final: **621 aprobadas, 5.682 aserciones, 0 fallidas y 0 omitidas**.
- JavaScript: **29 aprobadas, 0 fallidas/omitidas** (6 Productos, 16 Producción, 7 sidebar). Node --test con aislamiento por proceso resumió inicialmente 3 archivos; --test-isolation=none confirmó los 29 casos.
- npm run build: **aprobado**; aviso opcional fontaine del entorno, sin bloquear compilación.
- Pint del PHP afectado y comprobación --test: **aprobados**. No se afirma formato global del proyecto.
- git diff --check: **aprobado**.

El primer intento de MariaDB en sandbox no abrió socket y requirió escalamiento. La primera corrida de referencia y una corrida individual terminaron sin resumen capturado; no se contabilizan como pruebas aprobadas. Las ejecuciones con resultados anteriores sí se completaron y detuvieron sus instancias. --do-not-cache-result emitió aviso de deprecación; las ejecuciones finales no usan esa opción.

No se ejecutó navegador real ni se certificaron responsive, Safari, dispositivos físicos o sesiones database. La evidencia HTTP/DOM y los dobles JavaScript no sustituyen aceptación visual/operativa.

## Compatibilidad con datos existentes

La migración no cambia asociaciones. El seeder puede adoptar la única Lata local con equivalencia 1 sin cambiar su ID, previa ejecución operativa posterior. Los dos productos y cinco detalles locales quedaron sin cambios porque solo se realizaron lecturas. No se ejecutaron migraciones/seeders de negocio locales.

Las unidades no canónicas mantienen codigo NULL y sus FK válidas. Datos ambiguos se reportan y necesitan revisión explícita. Historial conserva snapshot y presentación, incluso si se edita el factor futuro. Productos con historial y asociación legacy pueden editar nombre/factor/estado conservando unidad; se avisa y no se sanea automáticamente.

## Riesgos encontrados

- Aplicar código sin migrar primero causa consultas a una columna ausente. La actualización operativa debe ejecutar esquema y seeder selectivos tras revisión.
- Una identidad ambigua/incompatible queda pendiente y el backend no guarda productos nuevos de esa familia hasta resolverla. El seeder puede completar la otra identidad segura antes de devolver error.
- La protección histórica depende del protocolo de locks de los escritores. No hay trigger/check SQL que impida violaciones mediante escrituras externas.
- Categorías siguen identificadas por sus nombres reales Pan/Torta/Bocadito, como en la arquitectura existente; esta tarea solo hace canónicas las unidades.
- Down está deliberadamente bloqueado con códigos presentes; no retirar códigos operativos para forzar rollback.

## Pendientes

- Revisar/aplicar selectivamente migración y UnidadMedidaSeeder en la base local. No se hizo como parte de las pruebas ni se desplegó.
- Aceptación manual visual/operativa y sesiones database.
- Revisar explícitamente asociaciones legacy con historial en otros entornos si existen; sin reasignarlas automáticamente. La auditoría local no encontró Torta/Bocadito históricos.
- mejora_asignado.md no existe en este checkout ni se localizó en Documentos; no se recreó arbitrariamente. Seguimiento actualizado en notas existentes del Vault.
- No se implementan cuentas, roles, módulos extra, cambios de servidor/dominio/SSL ni refactors generales.

## Pruebas manuales recomendadas

1. Tras aplicar esquema/seeder selectivos, crear Pan con factor 30: ver «Latas» sin selector; producir 12 latas y verificar snapshot 30/estimación 360.
2. Cambiar factor a 32 y confirmar que el lote previo continúa mostrando 360.
3. Crear Torta y Bocadito: ver registro directo y ningún factor; producir 8 y 150; revisar cantidades iguales en Producción e Historial, sin latas ni factor Pan.
4. En entorno de pruebas, manipular unidad_medida_id en POST/PATCH y comprobar rechazo sin escrituras.
5. Revisar una asociación legacy con historia: aviso y conservación; sin historia, guardar para aplicar el canon.
6. Repetir el seeder selectivo y confirmar mismos códigos/IDs y ausencia de duplicados. Cualquier ambigüedad se resuelve con revisión explícita, no borrado.
7. Comprobar formulario y mensajes con teclado, móvil y JS desactivado. El backend resuelve la unidad sin JS; la actualización visual al cambiar de categoría requiere JS y sin él debe recargarse/reabrirse con la categoría elegida.
