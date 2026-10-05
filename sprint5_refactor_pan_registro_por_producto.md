# Sprint 5 — Pan: registro por producto

Fecha: 2026-10-05.

Estado: IMPLEMENTADO Y COMPROBADO mediante pruebas HTTP y MariaDB aislada.
La comprobación manual de la interfaz en navegador permanece PENDIENTE.

Este informe describe el refactor del checkout actual. Los informes anteriores
se conservan como historia y no se sobrescribieron. La documentación de esta
tarea se creó en la raíz por indicación explícita del usuario.

## 1. Motivo del cambio

El formulario anterior permitía agregar varios productos y enviarlos juntos.
Ese comportamiento suponía que los productos terminaban al mismo tiempo.
El trabajo real requiere registrar cada producto cuando termina su producción.

Ejemplo operativo ilustrativo:

- 07:30: termina Pan Yema y se registra ese producto.
- 09:00: termina Pan Francés y se registra ese producto.
- 11:00: termina otro lote de Pan Yema y se registra nuevamente.

Estas horas explican el caso de negocio; el sistema no las guarda ni las muestra.
El refactor permite peticiones sucesivas y conserva cada lote como un registro
independiente. No espera a cerrar toda la jornada.

## 2. Significado de Produccion y DetallePan

`Produccion` representa una sesión de Pan identificada por la combinación:

```text
fecha + categoria_id real de Pan + turno_id real
```

`DetallePan` representa un producto registrado durante esa sesión. Dos registros
del mismo producto son dos detalles diferentes, aunque compartan la cabecera.

| Petición | Cabecera | Detalle nuevo | Cantidad guardada |
| --- | --- | --- | --- |
| Pan Yema, 0 coches + 9 latas | Crear si no existe | Primer lote | 9 |
| Pan Francés, 2 coches + 3 latas | Reutilizar sesión | Segundo lote | 39 |
| Pan Yema, 1 coche + 0 latas | Reutilizar sesión | Tercer lote | 18 |

El primer y el tercer lote no se suman silenciosamente a 27 latas.
Cada detalle conserva su observación, participantes, unidad y snapshot.

## 3. Flujo operativo y técnico

```text
GET /produccion?fecha=AAAA-MM-DD&turno_id=<ID real>
  → index(): catálogos y contexto de fecha/turno
  → Blade: un único formulario y tarjetas de la sesión
  → JavaScript: cantidades visuales y participantes
  → POST /produccion con detalles[0]
  → store(): validación estructural y de IDs
  → DB::transaction(): validación de negocio y catálogos
  → buscar o crear Produccion
  → crear un NUEVO DetallePan
  → insertar participantes en detalle_pan_empleado
  → COMMIT
  → redirección a index con fecha y turno
  → mensaje de éxito, tarjeta nueva y formulario de producto vacío
```

El contexto puede consultarse mediante el botón
“Ver producción de esta fecha y turno”. JavaScript construye una URL GET con
solo `fecha` y `turno_id`; no incorpora producto, observación ni participantes.
La consulta usa los valores seleccionados sin exigir completar el producto.
Consultar otro contexto vuelve a cargar la página y descarta el borrador local.

Tras guardar correctamente, fecha y turno permanecen seleccionados. Producto,
coches, latas, observación y participantes quedan vacíos para el siguiente lote.

## 4. Archivos modificados en esta tarea

| Archivo | Motivo |
| --- | --- |
| `app/Http/Controllers/ProduccionController.php` | Contexto de consulta, un detalle por POST, reutilización segura y tarjetas reales. |
| `resources/views/produccion/index.blade.php` | Formulario único, textos y tarjetas de DetallePan; conserva feedback y recuperación. |
| `resources/js/produccion.js` | Retirar múltiples bloques y mantener participantes, cálculo, consulta y cancelar. |
| `resources/css/produccion.css` | Ajustes locales para fieldset, participantes y contenido largo de tarjetas. |
| `tests/Feature/ProduccionPanStoreTest.php` | Adaptar peticiones sucesivas y comprobar el nuevo contrato, tarjetas y rollback. |
| `sprint5_refactor_pan_registro_por_producto.md` | Documentar decisiones, comportamiento, evidencia y pendientes. |

`NavigationTest.php` no necesitó cambios en esta tarea. El checkout ya tenía
cambios previos en ese archivo y en otros archivos; se conservaron. La comparación
de hashes tomada al inicio confirma cambios nuevos solo en los cinco archivos
de código/pruebas enumerados y la creación de este informe.

No se cambiaron modelos, rutas, middleware, migraciones ni seeders. Las migraciones
existentes se ejecutaron exclusivamente dentro del runner de pruebas aislado.
No se ejecutaron migraciones ni seeders sobre la base de datos del proyecto.

## 5. Contrato HTTP: un único detalle

Se conserva el contenedor anidado para reducir cambios innecesarios:

```text
categoria = pan
fecha
turno_id
detalles[0][producto_id]
detalles[0][coches]
detalles[0][latas_adicionales]
detalles[0][observacion]
detalles[0][participantes][j][empleado_id]
detalles[0][participantes][j][rol_produccion_id]
```

`0` es el único índice de producto permitido. `j` es la posición del participante
y puede tomar varios valores. Se valida `array`, `size:1` y
`required_array_keys:0`: no basta con enviar un detalle bajo otro índice.
Dos detalles en el mismo POST se rechazan antes de escribir.

El nombre HTTP `latas_adicionales` permanece por compatibilidad. Su etiqueta,
ayuda y mensaje de rango dicen “Latas” o “0 a 17 latas”. El botón principal
dice “Registrar producto”.

## 6. Cambios en index()

Se siguen resolviendo Categoria Pan por `nombre_categorias = 'Pan'` y los
productos activos de esa categoría. Turnos, empleados y roles proceden de
catálogos reales; no hay IDs hardcodeados.

El método ahora recibe `Request` y recupera fecha y turno. El input anterior de
un POST rechazado tiene prioridad sobre la query. Si no se indica fecha, usa
la fecha actual del servidor; no elige un turno por defecto.

La consulta exige fecha en formato `Y-m-d` y, cuando se proporciona turno, un
entero que exista en `turnos`. Sin turno válido no se buscan detalles. Parámetros
GET inválidos muestran un mensaje comprensible; arrays manipulados se normalizan
antes de renderizar atributos HTML para evitar errores técnicos en la vista.

Se buscan hasta dos cabeceras con la misma fecha, categoría Pan y turno:

- Ninguna: se muestra la sesión vacía.
- Una: se cargan sus detalles con producto y empleados mediante eager loading.
- Más de una: se muestra la inconsistencia y no se elige una cabecera ni se
  presentan tarjetas de una selección arbitraria.

Los detalles se ordenan por `detalle_pan.id` ascendente. Los nombres de roles de
las tarjetas se obtienen del catálogo ya cargado y del rol guardado en cada pivot.

## 7. Cambios en store() y validación

La validación estructural conserva las reglas existentes de fecha válida, turno
real, producto real, cantidades enteras y participantes con empleado/rol reales.
Ahora exige exactamente un detalle en `detalles[0]`.

Dentro de la transacción se resuelven los catálogos y se comprueba:

- Categoría Pan disponible, resuelta por nombre.
- Producto activo y perteneciente realmente a Pan.
- Unidad de medida válida, derivada del producto.
- Total de latas mayor que cero y dentro del INTEGER firmado.
- Roles permitidos, composición de participantes y ausencia de duplicados.

La cantidad se calcula siempre en el servidor:

```php
$totalLatas = (int) $detalle['coches'] * 18 + (int) $detalle['latas_adicionales'];
```

| Coches | Latas | Cantidad |
| --- | --- | --- |
| 0 | 1 | 1 |
| 0 | 9 | 9 |
| 1 | 0 | 18 |
| 1 | 5 | 23 |
| 2 | 3 | 39 |

Coches admite enteros no negativos; latas admite enteros entre 0 y 17.
Se mantiene el máximo técnico de 119304647 coches y la comprobación final de
2147483647 latas para evitar desbordamiento. Son límites de almacenamiento,
no máximos operativos confirmados por el negocio.

La fecha validada se normaliza con Carbon a `Y-m-d` para buscar la cabecera,
persistirla y mantener el mismo contexto en la redirección.

## 8. Búsqueda y reutilización de cabecera

Después de validar el detalle, se consultan las cabeceras por fecha normalizada,
ID real de Pan y turno validado. La consulta usa `lockForUpdate()` y `limit(2)`;
solo hacen falta dos coincidencias para detectar la inconsistencia.

Si no existe una cabecera, `Produccion::create()` recibe exclusivamente fecha,
categoría, turno y el identificador del usuario autenticado. Si existe una sola,
se reutiliza sin modificar su fecha, turno, categoría o registrador original.
Otro usuario puede agregar un detalle válido a la misma sesión; la cabecera
conserva quién la creó. Este refactor no incorpora autoría individual al detalle.

Si aparecen dos o más cabeceras, se lanza `ValidationException` con un mensaje
sobre sesiones duplicadas. No hay selección arbitraria, consolidación automática
ni borrado de registros existentes.

## 9. Creación del detalle, snapshot y pivot

`$produccion->detallesPan()->create()` crea un detalle nuevo y asigna su
`produccion_id` mediante la relación Eloquent. Los atributos se construyen
explícitamente; no se usa `$request->all()` para persistir.

El navegador propone producto, cantidades, observación y participantes. El
servidor controla la cabecera elegida, categoría, registrador de cabecera,
cantidad canónica, unidad, turno legacy y snapshot. Campos manipulados como
`cantidad`, `total_latas`, `produccion_id`, unidad o snapshot no deciden lo guardado.

`detalle_pan.turno_id` copia exactamente el turno de la cabecera. La unidad se
deriva de `producto.unidad_medida_id`. El factor actual `producto.panes_por_lata`
se copia a `detalle_pan.panes_por_lata_usado`, incluyendo NULL cuando corresponda.
No se agregan columnas para coches o latas.

Por ejemplo, un primer lote puede conservar snapshot 12 y otro lote posterior
snapshot 14 si cambió el producto. El detalle anterior sigue con 12; reutilizar
la cabecera no actualiza ni recalcula sus datos históricos.

La conversión `1 coche = 18 latas` es independiente de un factor como
`Pan Yema = 12 panes por lata`. El factor no interviene en coches → latas.

Por cada participante, `empleados()->attach()` inserta una asociación en
`detalle_pan_empleado` con `detalle_pan_id`, `empleado_id` y `rol_produccion_id`.
El rol pertenece a la participación en ese detalle, no al Cargo del empleado.
Los pivots anteriores no se reemplazan mediante sync ni se modifican.

Se requiere exactamente un Maestro y uno o más Ayudantes. Los roles se resuelven
por `nombre_roles_produccion`, sin suponer IDs. Se rechaza repetir un empleado en
el mismo detalle; puede aparecer en otros detalles, incluso con otro rol.

## 10. Transacción, bloqueos y rollback

`DB::transaction()` agrupa resolución de catálogos, validación de negocio,
búsqueda/creación de cabecera, creación del detalle y escritura de pivots.
La validación estructural ocurre antes de entrar; ninguna escritura la precede.
Si la función termina correctamente, Laravel confirma las escrituras.
Si lanza una excepción, Laravel revierte la operación de esa petición.

| Situación | Resultado ante un fallo |
| --- | --- |
| Cabecera creada en esta petición | Se revierte junto con el detalle y los pivots nuevos. |
| Cabecera que ya tenía productos | Permanece, con todos sus detalles y pivots anteriores. |
| Falla el segundo pivot | Se revierte el primer pivot nuevo y el detalle nuevo. |

No se borran detalles antiguos para compensar un error. La base de datos revierte
las escrituras de la transacción; en pruebas se verifica que todos los registros
previos permanecen iguales, además de los conteos.

Un `QueryException` se reporta internamente y produce un mensaje seguro con
`withErrors()` y `withInput()`. “No se registró ningún dato” se refiere a la
petición fallida: los datos de peticiones anteriores siguen existiendo. La vista
no presenta SQLSTATE ni el contenido técnico de la excepción.

Se bloquea la fila Categoria Pan para coordinar los escritores que pasan por
este store. También se bloquean el producto y las cabeceras consultadas. El
bloqueo del producto mantiene estable el factor durante la copia del snapshot.

## 11. Limitación: falta de UNIQUE SQL

Las migraciones inspeccionadas de `produccion` no definen una restricción UNIQUE
sobre fecha, categoría y turno. La prueba de duplicados confirma que el esquema
aislado permite crear dos cabeceras coincidentes.

El bloqueo de categoría coordina este flujo, pero no sustituye una garantía
UNIQUE para todos los escritores posibles. Otros procesos que no sigan este
protocolo todavía pueden dejar duplicados. Los bloqueos serializan todos los
registros Pan de este store, aunque sean de fechas o turnos diferentes.

Las pruebas realizadas son secuenciales: no certifican concurrencia real entre
varios procesos. La restricción UNIQUE, una estrategia para cabeceras legacy
duplicadas y las pruebas de concurrencia quedan PENDIENTES. No se creó migración
ni se inspeccionó o corrigió la base de datos operativa.

## 12. Cambios en Blade y construcción de tarjetas

Se retiraron numeración de productos, botones de agregar/eliminar producto y
plantilla de clonación. Solo queda un fieldset para `detalles[0]`. Se conserva la
selección de participantes y la reconstrucción del producto rechazado con old().

La sección inferior reemplaza los ejemplos estáticos por tarjetas reales de la
sesión consultada. Cada detalle genera su propio `<article>`, incluso si varios
detalles tienen el mismo producto. Muestra nombre, cantidades, roles con empleados
y observación cuando existe. Blade escapa los textos al renderizarlos.

La representación de cantidad es normalizada:

```php
$coches = intdiv($registro->cantidad, 18);
$latas = $registro->cantidad % 18;
```

Por ejemplo, cantidad 39 se muestra como 2 coches + 3 latas, además del total.
La tarjeta muestra fecha y turno; no inventa una hora exacta. El ID ascendente es
el orden disponible de inserción, no evidencia de una hora de finalización.

Los estilos existentes siguen vigentes. Solo se añadieron ajustes locales de
fieldsets, envoltura de participantes y tarjetas, y observaciones largas.

## 13. Cambios en JavaScript, cancelación y feedback

Se retiraron clonación, eliminación y reindexación de múltiples detalles. El
producto siempre conserva índice 0. Solo se renumeran los participantes al
agregarlos o quitarlos para construir sus nombres HTTP.

Se mantienen el cálculo visual sin hidden total, prevención de empleado repetido,
prevención de segundo Maestro y ayuda sobre composición requerida. Son ayudas:
store vuelve a validar todas las reglas con independencia del navegador.

Cancelar ejecuta reset y, después del reset nativo, vacía producto, cantidades,
observación, selectores auxiliares y participantes. Conserva la fecha y turno
actuales, vuelve a Pan y deja el único formulario listo para otro registro.
No recupera los valores de producto de old() después de cancelar.

Se conservan `$errors`, errores por campo, errores de participantes, old() y los
roles de accesibilidad `alert`/`status`. Los errores sin ubicación propia se
muestran en el resumen; los del producto se muestran junto al campo. La alerta
de duplicados evita repetir el mismo texto si ya está en el resumen de errores.

El éxito cambia a “Producto de Pan registrado correctamente.”. Es flash de
sesión y desaparece tras consumirse. El cambio de familia continúa deshabilitando
la posibilidad de registrar Torta o Bocadito y mostrando su estado pendiente.

## 14. Pruebas adaptadas y nuevas

Se conserva la cobertura de invitados, manipulación de campos derivados, IDs
inexistentes, productos inactivos/de otra familia, unidad inválida, cantidades,
snapshot NULL, roles prohibidos, duplicados y composición de participantes.

Los casos de varios detalles enviados juntos se adaptaron a peticiones sucesivas:
comprueban que la primera crea la sesión y la siguiente reutiliza esa misma
cabecera, conservando productos, observaciones y participantes independientes.

Se añadieron comprobaciones de:

- Rechazar dos detalles y un detalle con índice distinto de cero.
- Repetir producto creando dos detalles, sin sumar ni alterar el primer snapshot.
- Conservar el registrador original al agregar un producto con otro usuario.
- Crear otra cabecera cuando cambia la fecha o el turno.
- Calcular los cinco ejemplos de cantidades de esta tarea.
- Rechazar cabeceras duplicadas sin alterar registros previos.
- Conservar contexto en la redirección y limpiar solo los campos del producto.
- Mostrar tarjetas separadas, en orden estable, con producto, roles y observación.
- Excluir tarjetas de otra fecha, turno o categoría y omitir observación ausente.
- Mostrar también la observación de texto `"0"`: su contenido no debe descartarse
  por la conversión implícita de PHP a booleano.
- No mostrar tarjetas ajenas con consultas inválidas, incluidos arrays manipulados.

Los cuatro casos de rollback provocan errores SQL reales: CHECK de cantidad al
crear DetallePan o FK de rol al insertar el segundo pivot, tanto con cabecera
nueva como existente. Antes del fallo se comprueban las escrituras realizadas;
después se comparan cabeceras, detalles y pivots con el estado previo.

Las pruebas usan catálogos ficticios con IDs desplazados y dinámicos. El runner
crea una instancia MariaDB sin red, con dos bases ficticias, y la detiene al salir.
Cada prueba revierte su transacción de aislamiento. No se usa la BD del proyecto.

## 15. Resultados finales comprobados

| Verificación | Resultado |
| --- | --- |
| `php -l app/Http/Controllers/ProduccionController.php` | Sin errores de sintaxis. |
| `php -l tests/Feature/ProduccionPanStoreTest.php` | Sin errores de sintaxis. |
| `node --check resources/js/produccion.js` | Exit 0, sin errores de sintaxis. |
| ProduccionPanStoreTest | 82 passed, 797 assertions. |
| NavigationTest | 20 passed, 239 assertions. |
| Suite completa | 141 passed, 1489 assertions. |
| `git diff --check` | Sin errores. |

Comandos de pruebas ejecutados, en ese orden para la verificación final:

```bash
php tests/run-mariadb.php --filter=ProduccionPanStoreTest --do-not-record-test-run-history
php tests/run-mariadb.php --filter=NavigationTest --do-not-record-test-run-history
php tests/run-mariadb.php --do-not-record-test-run-history
git diff --check
```

La primera ejecución de las pruebas adaptadas detectó problemas en el propio
test: URL de retorno no establecida en los fallos de rollback y uso de modelKeys()
sobre una colección común de fixtures. Se corrigieron y se repitió la verificación.
Los resultados de la tabla corresponden al código y pruebas finales.
La revisión final corrigió la condición de observación en Blade para mostrar
también el texto `"0"`; se repitieron Pan, NavigationTest y la suite completa.

Las pruebas HTTP comprueban el HTML y el comportamiento de Laravel/MariaDB;
`node --check` comprueba sintaxis. No ejecutan las interacciones JavaScript en
un navegador ni verifican visualmente el CSS.

## 16. PENDIENTES

- Prueba manual en navegador: registrar lotes sucesivos y repetidos, consultar
  otra fecha/turno, agregar/quitar participantes, cancelar y comprobar feedback.
- Confirmar legibilidad de tarjetas y observaciones en pantallas pequeñas.
- Decidir restricción UNIQUE fecha/categoría/turno y tratamiento de duplicados
  existentes antes de una eventual migración; no consolidar automáticamente.
- Comprobar concurrencia con varios procesos y revisar el alcance del bloqueo.
- Decidir si se requiere hora exacta de registro: no hay timestamps ni columnas
  nuevas en DetallePan; la interfaz no muestra horas ficticias.
- Torta y Bocadito: siguen pendientes; no se implementó ni diseñó su nuevo flujo.
- Confirmar la unidad real con negocio; Lata sigue siendo provisional y Pan Yema
  es un producto de desarrollo. Los IDs observados no son reglas del sistema.
- Confirmar máximos operativos y demás políticas de negocio todavía abiertas.

No se hizo commit ni se avanzó a otra familia.
