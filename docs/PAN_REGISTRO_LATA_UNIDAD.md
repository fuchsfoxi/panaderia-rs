# Pan por Lata o Unidad — Panadería RS

Fecha: 2026-10-16. Estado: **IMPLEMENTADO Y COMPROBADO en aislamiento y Chrome headless**. Aceptación del usuario y operación real: **PENDIENTES**. Sin commit ni push.

## Fuente de la regla de negocio

Instrucción explícita del usuario tras contrastar el sistema con los registros físicos y el procedimiento real de la panadería. La categoría Pan incluye productos por LATA y por UNIDAD. La unidad configurada decide el flujo, nunca el nombre ni la palabra «molde». Los ejemplos de panes del encargo no se incorporan como catálogo productivo ni se crean automáticamente. No se adjuntan imágenes privadas.

Se revisó el estado antes de editar: checkout limpio en `ec97c83`. Las mejoras anteriores de Login/seguridad/countdown, Usuarios, activo/inactivo, contraseñas Unicode y turnos ya forman parte de ese commit. Una comparación final confirma que su código, las rutas, migraciones y seeders permanecen idénticos a ese estado.

## Regla final

| Familia y unidad | Cantidad persistida | Factor del producto / snapshot | Hora nueva |
| --- | --- | --- | --- |
| Pan + LATA | Total de latas, INT positivo | Obligatorio positivo / copia bajo lock | Obligatoria HH:MM |
| Pan + UNIDAD | Unidades producidas, INT positivo | NULL / NULL | No aplica: NULL |
| Torta | Tortas, cantidad directa UNIDAD | No aplica | No aplica |
| Bocadito | Bocaditos, cantidad directa UNIDAD | No aplica | No aplica |

Todas conservan fecha/turno, observación y Empleados participantes: exactamente un Maestro y al menos un Ayudante, sin repetidos. Usuario identifica al registrador, no sustituye participantes. Administrador registra cualquier turno existente; Operador solamente su asignación.

## Pan por LATA

Entrada: coches completos no negativos, latas adicionales de 0 a 17 y hora real. El Request conserva la normalización anterior de campo omitido/vacío a cero exclusivamente en este flujo; no convierte datos malformados. La Action vuelve a validar cantidades y hora, exige total positivo y capacidad INT, calcula el total en servidor, copia unidad del producto y factor bloqueado al snapshot. Enviar cantidad directa es contradictorio y se rechaza.

Ejemplo comprobado: `1 * 18 + 4 = 22` latas, factor 30, estimación 660 panes, hora 19:30. Los lotes LATA antiguos sin factor/hora siguen representando desconocidos; no se rellenan automáticamente. Un nuevo lote LATA exige configuración de factor válido y hora.

## Pan por UNIDAD

Entrada única de cantidad directa entera positiva, hasta 2147483647. Se conserva la familia Pan y la tabla detalle_pan. Se guardan cantidad, unidad UNIDAD, panes_por_lata_usado NULL y hora_finalizacion NULL; el producto debe tener panes_por_lata NULL. No se calculan coches, latas ni panes estimados.

Se rechazan coches, latas_adicionales y hora_finalizacion si están presentes, incluso con cero o NULL, tanto por HTTP como por Action directa. No se transforman silenciosamente en otra magnitud. Se rechazan cantidades vacías, cero, negativas, decimales, booleanos, arrays y fuera de INT. El caso de JSON 2.0 se prueba conservando la fracción en el cuerpo de la petición.

## 18 latas por coche

Se confirma **1 coche = 18 latas exclusivamente para Pan + LATA**. La constante existente `DetallePan::LATAS_POR_COCHE` sigue siendo la fuente PHP. Request/helper, Action, Blade e Historial la consumen; Blade publica el valor al JS, sin un segundo 18 de negocio en el script. Las apariciones de 18 en expectativas/fixtures y SVG no son nuevas configuraciones. JS prueba tanto el dato arbitrario 7, para demostrar que lee Blade, como la regla operativa 18. No se crea columna ni opción editable de capacidad.

## Productos

Pan ofrece «Forma de registro»: Por latas / Por unidades. Envía `unidad_registro=LATA|UNIDAD`; el servidor resuelve el ID por código canónico. El formulario no ofrece IDs arbitrarios. Si se proporciona unidad_medida_id manualmente, debe existir y coincidir con el código resuelto; no puede cambiar la semántica.

Pan LATA exige panes_por_lata entero estricto positivo, hasta INT firmado. Pan UNIDAD deshabilita/oculta el factor y muestra «no aplica». Factor no nulo enviado para UNIDAD se rechaza; al omitirlo, incluido LATA→UNIDAD sin historial, se guarda NULL explícitamente. UNIDAD→LATA exige factor válido. Torta y Bocadito no muestran selector, siempre se resuelven a UNIDAD y rechazan LATA/factor contradictorios.

Con historia no se cambia categoría ni unidad. Se conservan orden de locks categoría→producto y lecturas actuales de los tres detalles en ActualizarProducto/ResolverUnidadProducto. La carrera donde una edición convierte LATA→UNIDAD antes de un POST de latas rechaza el POST al releer el producto; la precedencia inversa bloquea el cambio de unidad tras registrar historia. Asociaciones legacy con historia se conservan al editar otros metadatos sin solicitar corrección canónica; se avisa y no habilitan nuevos lotes Pan con unidad desconocida. No se reescribe ningún detalle.

## Producción

RegistrarProduccionPanRequest resuelve el producto y su unidad real; valida producto Pan activo, contrato de campos por unidad y equipo, conservando TurnoPermitidoUsuario. ValidarRegistroPan comparte las reglas numéricas, incompatibilidades, factor y hora con la Action. RegistrarProduccionPan comprueba estructura de un solo detalle, bloquea categoría/producto y vuelve a aplicar el contrato antes de escribir. ResolverCabeceraProduccion conserva la revalidación del actor/turno y los participantes se validan de nuevo dentro de la transacción. El cliente no proporciona unidad/snapshot efectivos ni total calculado.

Los options publican código seguro de unidad y factor solamente para LATA. produccion.js muestra/habilita coches/latas/hora para LATA y cantidad para UNIDAD. Deshabilita y limpia campos incompatibles al cambiar producto/familia, sin perder participantes al cambiar solamente de producto. Familias, formas, fotos de Torta, reset, consulta y contexto de Operador mantienen sus regresiones.

Se corrigió además un problema observado en Chrome: `[data-panes-por-lata]` encontraba la opción antes que el output y reemplazaba el nombre del producto por el factor. Ahora se selecciona `output[data-panes-por-lata]`; el nombre permanece y el factor visible se actualiza. Lo verifican eventos JS y la matriz final de navegador.

Sin JS se mantienen el estado inicial Blade y la validación del servidor. Cambiar producto sin JS requiere un retorno de validación/recarga para adaptar campos; el editor existente de nuevos participantes ya depende de JS. No se declara un flujo completo dinámico sin JS.

## Hora de término

Se exige exclusivamente para nuevos lotes Pan LATA y se persiste en el detalle. No se asigna automáticamente ni se agrega hora a produccion, Torta o Bocadito. Pan UNIDAD no la pide y la guarda NULL. En Historial, LATA sin hora muestra «Sin hora registrada»; UNIDAD omite el campo porque no aplica.

## Historial

La unidad de cada detalle y panes_por_lata_usado determinan su presentación. LATA conserva latas, coches/resto, snapshot, estimación y hora. UNIDAD muestra unidades y omite toda información de coches/latas/factor/estimación/hora, incluso «hora desconocida». Cambiar el producto actual mediante un bypass exclusivo del fixture no altera la interpretación de los detalles anteriores. Una unidad histórica no canónica se identifica como no reconocida, sin suponer que equivale a LATA. Las tarjetas de consulta de Producción siguen el mismo criterio.

## Dashboard

La métrica y el resumen por turno de latas suman únicamente detalles cuya unidad histórica tiene código LATA, mediante CASE SQL. **18 latas + 20 unidades = 18 latas**, con dos lotes. La tabla de últimos lotes muestra la unidad propia de cada fila y su columna se llama Cantidad. Se conserva el contador general de lotes de Pan. Se decidió no agregar otra métrica/rediseño: evita mezclar magnitudes sin ampliar el alcance. Unidad del detalle se precarga con una consulta constante adicional; el test mantiene conteo independiente del número de lotes, con techo de nueve consultas.

## Unidades canónicas

Se mantienen LATA/UNIDAD, codigo UNIQUE y resolución por código, sin IDs fijos ni inferencia por nombre. UnidadMedida expone códigos permitidos por familia: Pan ambos; Torta/Bocadito solo UNIDAD. CrearProducto conserva su delegación al resolvedor. UnidadMedidaSeeder no cambia: gestiona identidades, no decide productos. ProduccionDesarrolloSeeder conserva su ejemplo previo explícito por LATA; no se añade catálogo Molde ni detección textual.

## Esquema de BD

Auditoría operativa exclusivamente READ ONLY, cerrada con rollback, sin credenciales o filas privadas. Se inspeccionaron columnas/restricciones de productos, detalle_pan, unidades_medida y produccion. Se observaron un código LATA y uno UNIDAD; los dos productos Pan existentes usan LATA y tienen factor. No se convierten ni se modifican.

| Campo real | Tipo | Nullable |
| --- | --- | --- |
| productos.unidad_medida_id | BIGINT UNSIGNED, FK | NO |
| productos.panes_por_lata | INT firmado | SÍ |
| detalle_pan.unidad_medida_id | BIGINT UNSIGNED, FK | NO |
| detalle_pan.cantidad | INT firmado, CHECK > 0 | NO |
| detalle_pan.panes_por_lata_usado | INT firmado | SÍ |
| detalle_pan.hora_finalizacion | TIME | SÍ |
| unidades_medida.codigo | VARCHAR(20), UNIQUE | SÍ, para legacy |
| produccion.fecha | DATE | NO |
| produccion.categoria_id / turno_id | BIGINT UNSIGNED, FK | SÍ |

**No se necesita ni se crea una migración.** El esquema real ya representa ambos flujos y sus NULL. No se ejecutan migraciones, seeders, backfills ni SQL de escritura sobre la base operativa. Las migraciones existentes se ejecutan solamente en servidores ficticios del runner.

## Por qué no se agregó es_molde

unidad_medida_id ya define la forma de registro y el detalle conserva su unidad histórica. es_molde/tipo_pan/es_por_unidad duplicarían esa fuente y podrían contradecirla. Ningún nombre determina el flujo. Configurar nuevos productos será una acción explícita del Administrador después de revisar este cambio.

## Archivos modificados

| Ruta | Motivo |
| --- | --- |
| `app/Actions/Produccion/RegistrarProduccionPan.php` | Contrato por unidad y revalidación transaccional de Pan. |
| `app/Actions/Produccion/ValidarRegistroPan.php` | Contrato por unidad y revalidación transaccional de Pan. |
| `app/Actions/Productos/ActualizarProducto.php` | Resolución por código y protección de unidad histórica. |
| `app/Actions/Productos/ResolverUnidadProducto.php` | Resolución por código y protección de unidad histórica. |
| `app/Http/Controllers/DashboardController.php` | Sumar solamente latas históricas y precargar su unidad. |
| `app/Http/Controllers/ProduccionController.php` | Preparar catálogos/unidades y valores semánticos de formulario. |
| `app/Http/Controllers/ProductoController.php` | Preparar catálogos/unidades y valores semánticos de formulario. |
| `app/Http/Requests/GuardarProductoRequest.php` | Validación del factor y del registro según unidad real. |
| `app/Http/Requests/RegistrarProduccionPanRequest.php` | Validación del factor y del registro según unidad real. |
| `app/Models/DetallePan.php` | Códigos permitidos por familia y semántica de cantidad. |
| `app/Models/UnidadMedida.php` | Códigos permitidos por familia y semántica de cantidad. |
| `resources/js/produccion.js` | Selector/controles dinámicos y presentación de la unidad histórica. |
| `resources/js/productos.js` | Selector/controles dinámicos y presentación de la unidad histórica. |
| `resources/views/dashboard/index.blade.php` | Selector/controles dinámicos y presentación de la unidad histórica. |
| `resources/views/history/index.blade.php` | Selector/controles dinámicos y presentación de la unidad histórica. |
| `resources/views/produccion/index.blade.php` | Selector/controles dinámicos y presentación de la unidad histórica. |
| `resources/views/productos/form.blade.php` | Selector/controles dinámicos y presentación de la unidad histórica. |
| `tests/Feature/DashboardTest.php` | Casos nuevos o adaptación de contratos, snapshots, UI y regresiones existentes. |
| `tests/Feature/HistorialProduccionTest.php` | Casos nuevos o adaptación de contratos, snapshots, UI y regresiones existentes. |
| `tests/Feature/ModelsRelationshipsTest.php` | Casos nuevos o adaptación de contratos, snapshots, UI y regresiones existentes. |
| `tests/Feature/PanRegistroUnidadTest.php` | Casos nuevos o adaptación de contratos, snapshots, UI y regresiones existentes. |
| `tests/Feature/PanUnitsTest.php` | Casos nuevos o adaptación de contratos, snapshots, UI y regresiones existentes. |
| `tests/Feature/ProduccionConcurrenciaTest.php` | Base nueva por escenario sin fresh/refresh; conservar carreras e invariantes. |
| `tests/Feature/ProduccionPanStoreTest.php` | Casos nuevos o adaptación de contratos, snapshots, UI y regresiones existentes. |
| `tests/Feature/ProduccionTortaBocaditoStoreTest.php` | Casos nuevos o adaptación de contratos, snapshots, UI y regresiones existentes. |
| `tests/Feature/ProductosConcurrenciaTest.php` | Base nueva por escenario sin fresh/refresh; conservar carreras e invariantes. |
| `tests/Feature/ProductosTest.php` | Casos nuevos o adaptación de contratos, snapshots, UI y regresiones existentes. |
| `tests/Feature/RegistrarProduccionPanActionTest.php` | Casos nuevos o adaptación de contratos, snapshots, UI y regresiones existentes. |
| `tests/Feature/UnidadesCanonicasTest.php` | Casos nuevos o adaptación de contratos, snapshots, UI y regresiones existentes. |
| `tests/Feature/UsuariosConcurrenciaTest.php` | Base nueva por escenario sin fresh/refresh; conservar carreras e invariantes. |
| `tests/Feature/UsuariosMigrationTest.php` | Base nueva por escenario sin fresh/refresh; conservar carreras e invariantes. |
| `tests/JavaScript/ProduccionCantidadTest.mjs` | Casos nuevos o adaptación de contratos, snapshots, UI y regresiones existentes. |
| `tests/JavaScript/ProductosTest.mjs` | Casos nuevos o adaptación de contratos, snapshots, UI y regresiones existentes. |
| `tests/Support/IsolatedMariaDb.php` | Fixtures canónicas/legacy y aislamiento de bases/workers. |
| `tests/Support/productos-fixtures.php` | Fixtures canónicas/legacy y aislamiento de bases/workers. |

No se modifica código de Login, RateLimiter, countdown, Usuarios, contraseñas, activo/inactivo, Gates, rutas, seeders ni migraciones. Los dos tests de Usuarios que requieren bases nuevas cambian solo su preparación/retorno de conexión temporal; mantienen las comprobaciones originales.

## Tests

PanRegistroUnidadTest incorpora **83 casos**: CRUD por código/familia/factor, IDs manipulados, transiciones con/sin historia, producción y Action directa, límites y campos contradictorios, cálculo 22/660/hora, catálogo inválido, Maestro/Ayudante, matriz Admin/Operador por ambos turnos/unidades y snapshots de Historial/Dashboard. Se adaptan fixtures de pruebas a unidades canónicas/factor válido; pruebas de datos legacy/esquemas anteriores los piden explícitamente. No se borran comprobaciones de autorización, cantidades, equipos, fotos o historia.

La preparación de concurrencia/migración ahora usa una base vacía nueva por escenario, con nombre temporal permitido, servidor/datadir verificados y propagación de la identidad a sus workers. El finally restaura la conexión original intacta. **La línea base inicial ejecutó llamadas internas preexistentes a migrate:fresh solo en la instancia desechable**, detectadas después de iniciarla; se informó y se retiraron esas llamadas. Las ejecuciones finales no invocan fresh/refresh. No se borró ni reconstruyó una base operativa. Para liberar /tmp se retiraron con autorización únicamente datadirs ficticios de nueve instancias detenidas de esta tarea, conservando sus logs.

```bash
# Específicas antes de la suite global
PAO_DISABLE=1 php tests/run-mariadb.php --filter='PanRegistroUnidad|UnidadesCanonicas|Productos|ProduccionPan|RegistrarProduccionPan|PanUnits|Dashboard|Historial|ProduccionConcurrencia' --compact
# Suite global solicitada
PAO_DISABLE=1 php tests/run-mariadb.php --compact
node --test --test-isolation=none tests/JavaScript/*.mjs
npm run build
# Lista explícita de 27 PHP afectados, registrada en /tmp/pan-lata-unidad-php-files.json
vendor/bin/pint --test \
  app/Actions/Produccion/RegistrarProduccionPan.php \
  app/Actions/Produccion/ValidarRegistroPan.php \
  app/Actions/Productos/ActualizarProducto.php \
  app/Actions/Productos/ResolverUnidadProducto.php \
  app/Http/Controllers/DashboardController.php \
  app/Http/Controllers/ProduccionController.php \
  app/Http/Controllers/ProductoController.php \
  app/Http/Requests/GuardarProductoRequest.php \
  app/Http/Requests/RegistrarProduccionPanRequest.php \
  app/Models/DetallePan.php \
  app/Models/UnidadMedida.php \
  tests/Feature/DashboardTest.php \
  tests/Feature/HistorialProduccionTest.php \
  tests/Feature/ModelsRelationshipsTest.php \
  tests/Feature/PanRegistroUnidadTest.php \
  tests/Feature/PanUnitsTest.php \
  tests/Feature/ProduccionConcurrenciaTest.php \
  tests/Feature/ProduccionPanStoreTest.php \
  tests/Feature/ProduccionTortaBocaditoStoreTest.php \
  tests/Feature/ProductosConcurrenciaTest.php \
  tests/Feature/ProductosTest.php \
  tests/Feature/RegistrarProduccionPanActionTest.php \
  tests/Feature/UnidadesCanonicasTest.php \
  tests/Feature/UsuariosConcurrenciaTest.php \
  tests/Feature/UsuariosMigrationTest.php \
  tests/Support/IsolatedMariaDb.php \
  tests/Support/productos-fixtures.php
git diff --check
```

## Resultados reales

| Verificación | Resultado |
| --- | --- |
| Línea base relevante | 410 aprobadas / 0 fallidas, 3.590 aserciones |
| Reproducción nueva, antes de implementar | 4 fallidas esperadas, 10 aserciones; Dashboard devolvía 38 en vez de 18 |
| Primera regresión amplia | 474 aprobadas / 22 fallidas; contratos/fixtures antiguos investigados y corregidos |
| Específicas corregidas | **496 aprobadas / 0 fallidas, 4.061 aserciones** |
| Primera suite global | 870 aprobadas / 2 fallidas; payload mixto y fixture de esquema antiguo |
| Comprobación de esas dos adaptaciones | **92 aprobadas / 0 fallidas, 896 aserciones** |
| Suite PHP final | **872 aprobadas / 0 fallidas / 0 omitidas, 7.896 aserciones** |
| JS | **51 aprobadas / 0 fallidas** |
| Chrome final | **40/40**, diez vistas × 320/375/768/1280 px |
| Build | **APROBADO**; aviso opcional preexistente fontaine |
| Pint de 27 PHP afectados | **APROBADO**, sin Pint global en escritura |
| git diff --check | **APROBADO** |

La suite final duró 55,38 s y detuvo `/tmp/sprint4-d836ddf2fd7b`. Log: `/tmp/pan-lata-unidad-full-final.log`; específicas: `/tmp/pan-lata-unidad-regression2.log`; últimas 92: `/tmp/pan-lata-unidad-last-specific.log`; JS/build: `/tmp/pan-lata-unidad-js.log` y `/tmp/pan-lata-unidad-build.log`. Capturas y resultado de Chrome: `/tmp/pan-lata-unidad/`.

PHP usa MariaDB desechable sin red, socket/datadir/base verificados, fixtures, sesiones de prueba array y database en los casos existentes, HTTP/withoutVite y almacenamiento de fotos falso. Chrome usa Blade/modelos en memoria y assets compilados mediante interceptación local, sin BD, POST ni tráfico externo. Se comprobaron FormData, campos activos, transiciones, nombres/factor, 22/660, historia, Dashboard, errores JS y overflow; se revisaron capturas móvil/escritorio. Fuentes externas bloqueadas, medidas con fallback. No equivale a Safari, lector de pantalla, dispositivo físico o aceptación de datos reales.

## Riesgos

- La unidad debe estar bien configurada por el Administrador; no se infiere ni se corrige por nombres.
- Nuevos lotes LATA requieren factor válido; un producto legacy sin factor/unidad canónica necesita revisión explícita. Los históricos desconocidos no se rellenan.
- SQL u otros futuros escritores pueden saltar los locks/protecciones del CRUD; deben respetar el protocolo existente. No se añade trigger entre tablas.
- JS es ayuda visual; el producto puede cambiar entre mostrar y enviar y la Action revalida la configuración bloqueada. Campos contradictorios se rechazan, no se reinterpretan.
- La revisión visual usa fuentes fallback y fixtures; no certifica operación, dispositivos físicos ni configuración de sesiones reales.

## Pendientes

Revisión del usuario de Productos, ambos registros Pan, Historial, Dashboard y tests; aceptación operativa, Safari/dispositivos físicos/fuentes y lector de pantalla. Configurar explícitamente los productos por UNIDAD solo después de aceptar el cambio. No se crean productos reales ni se modifica el catálogo actual. Se conservan los pendientes previos de Usuarios/sesiones/aprovisionamiento. mejora_asignado.md sigue ausente; no se recrea ni se guarda una copia del Vault en el repositorio.

Documentación modificada: este informe y aclaraciones fechadas en docs/PRODUCCION_UNIDADES_POR_FAMILIA.md, docs/UNIDADES_CANONICAS_PRODUCTOS.md, docs/PRODUCTOS_PANES_POR_LATA.md, docs/USUARIOS_OPERADORES_TURNOS.md y docs/AUDITORIA_COMPLETA_PANADERIA_RS.md, conservando íntegros sus textos previos.

En el Vault original se actualizaron diez notas leídas, sin reorganizar ni borrar historia: LARAVEL/Services.md y Requests.md (responsabilidades técnicas); PRODUCCION/Logica de negocio.md, Pantallas.md, Flujo de produccion.md y Requerimientos.md (reglas, interfaz, flujo y alcance); REQUERIMIENTOS/04 - Reglas de negocio.md (fuente física y 18 latas confirmadas); DECISIONES/Decisiones Laravel.md (opciones/decisión/consecuencias); PENDIENTES/Bugs.md (entradas únicas 20 y 21); INICIO/Estado de proyecto.md (resumen). Escritura escalada autorizada, verificada conservando historia íntegra. No quedan autorizaciones de documentación pendientes. No se acredita despliegue, commit o push.

## Pruebas manuales

Realizar primero en una copia desechable con datos ficticios y assets compilados.

1. Crear Pan Por latas con factor 30; vacío/cero/decimal de factor deben rechazarse. Crear otro Pan Por unidades sin factor; factor manipulado debe rechazarse.
2. Cambiar LATA↔UNIDAD antes de tener historia: UNIDAD limpia factor; LATA lo exige. Probar código/ID ajeno y Torta/Bocadito con LATA: rechazo.
3. Registrar LATA: 1 coche + 4 latas, hora 19:30, un Maestro y un Ayudante. Verificar 22 latas, snapshot 30 y 660 panes.
4. Seleccionar Pan UNIDAD: solo cantidad 20, sin hora/coches/latas/factor/estimación. Revisar FormData y persistencia en detalle_pan con ambos NULL. Cambiar de unidad/producto sin perder equipo y sin enviar controles residuales.
5. Intentar cantidad 0/negativa/decimal/excesiva, o coches/latas/hora en UNIDAD: error y ninguna fila/pivote nueva. Verificar también el nombre del producto y factor visible al volver a LATA.
6. Como Admin registrar ambos turnos; como Operador, solo el asignado. Manipular turno: rechazo. Sin Maestro o sin Ayudante: rechazo en ambos flujos.
7. Consultar Producción e Historial: LATA 22/30/660/19:30; UNIDAD 20 unidades sin información de latas ni hora. LATA antiguo sin hora: «Sin hora registrada». Producto con historia no cambia unidad.
8. Dashboard: con 18 latas y 20 unidades el total de latas es 18, nunca 38; la tabla identifica cada magnitud. Torta/Bocadito siguen directos, sin hora; probar forma/foto opcional y cancelación de Torta.
9. Revisar teclado, móvil/escritorio, errores/reset y regresión Login→Usuarios→producción→historial→logout, con JS activo; comprobar las limitaciones documentadas sin JS. No registrar credenciales en capturas/logs.
