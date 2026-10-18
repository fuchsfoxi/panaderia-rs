# Eliminación de registros del Historial y hover de Productos

Fecha: 2026-10-18.

Estado: **IMPLEMENTADO Y COMPROBADO en MariaDB aislada y Chrome headless con fixtures**. Listo para revisión manual; aceptación del usuario y dispositivos físicos **PENDIENTES**. Sin commit ni push.

## 1. Estado inicial encontrado

- Checkout inicialmente limpio, HEAD `15b3668`. Historial ya reunía Pan, Torta y Bocadito mediante `ConsultarHistorialProduccion`, con filtros GET de fechas inclusivas, categoría y turno, y paginación de 15 detalles. Cada tarjeta era un **detalle/lote individual**, no una cabecera.
- `produccion` es una sesión compartida por fecha/categoría/turno. Sus relaciones `detallesPan`, `detallesTorta` y `detallesBocadito` son `hasMany`. Un producto puede repetirse en distintos lotes de la misma sesión.
- Los tres detalles tienen PK local independiente. Por eso el ID sin familia no identifica de manera suficiente una tarjeta.
- Cada detalle tiene participantes mediante `detalle_*_empleado`, con PK compuesta detalle/empleado y `rol_produccion_id`. Las FK existentes eliminan los pivotes en cascada al borrar su detalle; borrar una cabecera también eliminaría sus detalles en cascada. No era seguro borrar directamente la cabecera seleccionando una tarjeta.
- No existía endpoint de eliminación ni confirmación en Historial. `historial.js` no tenía interacción y no se cargaba en la vista, aunque ya figuraba entre las entradas de Vite.
- Ya existían `auth`, cuenta activa, `auth.session`, Gate `administrar-sistema` y SVG inline. Se reutilizan estos mecanismos.
- Productos ya activaba/desactivaba con PATCH y un valor explícito de `activo`. Desactivar y Editar compartían el mismo estilo base; Activar utilizaba la variante verde principal.
- Se revisaron rutas, controllers, Requests, Actions de registro, Query del historial, modelos/relaciones, migraciones/FK, Blade, CSS, JavaScript, configuración y pruebas existentes antes de editar.
- Línea base: **178 pruebas/1.216 aserciones aprobadas** de Historial y Productos en MariaDB temporal.

## 2. Archivos modificados y motivo

| Archivo | Motivo |
| --- | --- |
| `app/Actions/Produccion/EliminarRegistroProduccion.php` | Resolver familia/ID, eliminar un detalle dentro de transacción y retirar únicamente cabeceras vacías. |
| `app/Http/Requests/EliminarRegistroProduccionRequest.php` | Autorización administrativa, confirmación obligatoria y selección de filtros de retorno. |
| `app/Http/Controllers/HistorialController.php` | Coordinar eliminación, éxito y errores SQL/registro desaparecido con retorno al Historial. |
| `routes/web.php` | Añadir `history.destroy`, exclusivamente DELETE y administrador. |
| `resources/views/history/index.blade.php` | Acción administrativa dentro de detalles, CSRF, método DELETE, cuadro de confirmación y mensajes. |
| `resources/js/historial.js` | Abrir confirmación, identificar el lote y enviar solo al confirmar. |
| `resources/css/historial.css` | Botón rojo suave, alineación inferior derecha, diálogo adaptable y avisos. |
| `resources/views/productos/index.blade.php` | Añadir una clase exclusiva a Desactivar, sin alterar el formulario ni su comportamiento. |
| `resources/css/productos.css` | Hover/focus-visible destructivo limitado a Desactivar y transición de 200 ms. |
| `tests/Feature/EliminarHistorialProduccionTest.php` | 25 escenarios de eliminación, aislamiento y seguridad sobre MariaDB desechable. |
| `dato_optimizar/informe_eliminacion_historial_hover_productos.md` | Informe solicitado y guía de revisión manual. |

Documentación original afectada, conservando todas sus entradas históricas:

- Vault `LARAVEL/Controllers.md`: responsabilidades del controller, Request, Action y ruta.
- Vault `PRODUCCION/Pantallas.md`: botón, confirmación, teclado, responsive y estilos de Productos.
- Vault `PERSONAL Y USUARIOS/Permisos.md`: capacidad de eliminación exclusiva del Administrador.
- Vault `DECISIONES/Decisiones Laravel.md`: decisión de borrar por familia/detalle y conservar cabeceras compartidas/fotos.
- Vault `INICIO/Estado de proyecto.md`: estado y evidencia de esta entrega.

El nombre real de la nota de decisiones es `Decisiones Laravel.md`; no existe una variante `.md.md` en esta estructura. `mejora_asignado.md` no se encontró en el checkout ni en el Vault; no se recrea. No corresponde modificar Roadmap, esquema, reglas de cantidades, registros de bugs ajenos ni pendientes anteriores de autenticación en esta tarea.

Las cinco adiciones al Vault se aplicaron sobre los originales mediante escalamiento autorizado, después de revisar sus diffs y comprobar sus hashes de lectura. Se verificó la conservación íntegra del contenido histórico en las cinco notas. No quedó ninguna autorización pendiente ni se guardaron copias de esas notas en el repositorio.

## 3. Cómo funciona la eliminación

1. El Administrador abre «Ver detalles» y encuentra «Eliminar registro» al final, alineado a la derecha.
2. Ese botón es `type="button"`: abrir el cuadro no envía el formulario. Permanece deshabilitado hasta inicializar la confirmación, evitando un borrado accidental si JavaScript no está disponible.
3. Un único `<dialog>` nativo muestra **¿Eliminar este registro?**, el aviso solicitado y producto, fecha y turno del lote seleccionado. Los valores usan escape de Blade y `textContent`.
4. Cancelar recibe el foco inicial. Cancelar o Escape cierran el diálogo sin enviar nada y devuelven el foco al botón de origen.
5. «Eliminar definitivamente» marca `confirmado=1` y envía el formulario de esa tarjeta con POST, `_method=DELETE` y CSRF. Laravel lo procesa como DELETE. El botón de confirmación se deshabilita durante ese envío para evitar repeticiones inmediatas.
6. La Action resuelve la tabla mediante una lista cerrada de familias, busca el detalle y toma bloqueos de cabecera y detalle dentro de `DB::transaction`.
7. Se borra únicamente ese detalle. Las FK existentes retiran sus asociaciones con empleados.
8. Se consultan las **tres** relaciones de la cabecera con lecturas actuales bloqueadas. Si queda cualquier detalle, se conserva la cabecera; si no queda ninguno, se elimina dentro de la misma transacción. Esto incluye protección de cabeceras legacy mixtas sin clasificarlas ni modificarlas.
9. Error SQL revierte toda la transacción y muestra un mensaje genérico. Un registro que ya no existe devuelve un aviso sin afectar otros datos. Se preservan los filtros escalares permitidos y la página de origen; no se acepta una URL de retorno arbitraria.

La cabecera se bloquea antes que el detalle, compatible con el orden de los escritores existentes. No se cambian esos escritores ni se crean capas o dependencias adicionales.

## 4. Relaciones y datos que se eliminan

| Destino confirmado | Datos retirados |
| --- | --- |
| `pan` + ID | Una fila de `detalle_pan` y sus filas de `detalle_pan_empleado`. |
| `torta` + ID | Una fila de `detalle_torta` y sus filas de `detalle_torta_empleado`. |
| `bocadito` + ID | Una fila de `detalle_bocadito` y sus filas de `detalle_bocadito_empleado`. |
| Cabecera sin ningún detalle restante | Solo su fila de `produccion`. |

Se conservan otros lotes del mismo producto, los detalles de cualquier familia restante, sus pivotes y las cabeceras que aún los contienen. No se eliminan productos, empleados, cuentas, roles, turnos, categorías o unidades.

**Fotos de Torta:** se retira su referencia al eliminar el detalle, pero no el archivo físico. El esquema no garantiza propiedad exclusiva de una ruta de foto histórica; borrarla podría afectar otra referencia. La conservación se comprueba con almacenamiento falso. Limpieza de archivos huérfanos no implementada ni ejecutada.

Consecuencia funcional de un borrado definitivo: el lote desaparece de las consultas y agregados existentes. Las comprobaciones de «tiene historial» de Productos siguen basándose en la existencia de detalles; borrar el último detalle puede dejar al producto sin esa protección histórica. No se cambia la regla existente ni se añade una auditoría/recuperación de lotes eliminados.

## 5. Controles de seguridad implementados

- Ruta `DELETE /history/{familia}/{detalle}`, nombre `history.destroy`.
- Middleware web/CSRF, `auth`, `EnsureUsuarioActivo`, `auth.session` y `can:administrar-sistema`.
- `EliminarRegistroProduccionRequest::authorize()` reutiliza el mismo Gate. No se confía en rol enviado por el cliente ni en ocultar botones.
- Confirmación `required|accepted` en el servidor. Es una barrera contra envíos accidentales, no sustituye la autorización ni pretende demostrar por sí sola un clic humano.
- Familia limitada a `pan`, `torta` o `bocadito`; ID numérico. La familia resuelve una clase fija, nunca un nombre de tabla aportado libremente.
- DELETE específico del detalle vinculado a la cabecera bloqueada. La transacción incluye cascadas y eventual eliminación de cabecera.
- GET, POST sin suplantación DELETE y PATCH sobre ese destino producen 405 y no modifican datos.
- CSRF ausente/incorrecto produce 419; Operador con token válido y payload manipulado obtiene 403. Invitados e inactivos regresan al login.
- Las redirecciones usan `history.index` y una lista de parámetros permitidos, conservando fechas, categoría, turno y página. Los mensajes no muestran SQL al usuario.

## 6. Cambios visuales realizados

- Tarjetas, filtros, resúmenes y despliegue nativo existentes conservados. La acción nueva ocupa una fila final del contenido desplegado y se alinea a la derecha.
- Papelera SVG inline, siguiendo el mecanismo existente y sin biblioteca nueva. Rojo suave sobre fondo claro, altura mínima de 44 px y foco visible.
- Confirmación con paleta crema/verde, ancho limitado por el viewport, texto largo divisible, alto máximo y botones con ajuste de línea en móvil.
- Productos: Desactivar conserva fondo transparente, borde verde suave, texto oscuro, tamaño y forma. Hover y focus-visible aplican fondo/borde `#C96B5D`, texto blanco y transición de 200 ms; el foco conserva un contorno de 2 px.
- Editar conserva su apariencia y Activar su variante verde. No se modificaron JavaScript, rutas, Actions o reglas de estado de Productos.

## 7. Pruebas ejecutadas y resultados

| Comprobación | Resultado |
| --- | --- |
| Línea base `php tests/run-mariadb.php --filter='HistorialProduccionTest\|ProductosTest' --do-not-cache-result` | 178 aprobadas, 1.216 aserciones. |
| Regresión ampliada: eliminación, Historial, Productos, Pan LATA/UNIDAD y Torta/Bocadito | 377 aprobadas, 2.581 aserciones. |
| Suite completa `php tests/run-mariadb.php --do-not-cache-result` | **897 aprobadas, 8.093 aserciones, cero fallidas/omitidas**. Incluye los 25 casos nuevos. |
| Cinco archivos existentes `tests/JavaScript/*.mjs`, ejecutados individualmente con Node | **51 casos aprobados**: Login 8, Producción 18, Productos 10, sidebar 7 y Usuarios 8. |
| Chrome headless, 320/375/768/1280 px | **16/16 combinaciones**: Historial Admin, Historial Operador, Productos y Historial sin JS. Sin overflow horizontal ni excepciones JS. |
| `npm run build` | Aprobado. Aviso opcional preexistente de fontaine; no se instaló. |
| Pint de los cinco PHP afectados | Aprobado tras aplicar formato. No se ejecutó una limpieza global ajena a esta tarea. |
| Revisión de archivos nuevos, `git diff` y `git diff --check` | Aprobada. |

Los casos nuevos comprueban: eliminación confirmada de las cuatro variantes; mismo producto en varios lotes/cabeceras; participantes conservados; cabecera legacy mixta; IDs iguales entre tablas; ausencia/rechazo de confirmación; Operador, invitado e inactivo; CSRF real; métodos incorrectos; familia/ID inválidos; registro desaparecido; rollback tras fallo SQL posterior al borrado del detalle; y conservación del archivo de Torta.

La regresión existente comprueba registro/consulta de Pan por LATA y UNIDAD, Torta, Bocadito, filtros inclusivos y paginación de Historial, y activación/desactivación idempotente de Productos. Chrome comprueba alineación de la acción, foco inicial y retorno, Cancelar/Escape sin envío, selección correcta del lote al confirmar, preservación de filtros en el formulario, campos DELETE/confirmación/CSRF, ausencia de controles del Operador, eliminación deshabilitada sin JS y detalles nativos todavía utilizables. En Productos comprueba colores calculados normales/hover/foco, duración de transición, dimensiones conservadas y ausencia de rojo en Editar/Activar.

### Aislamiento y límites de la evidencia

MariaDB se ejecutó sin red en directorios `/tmp/sprint4-*`; el soporte existente verifica datadir/base antes de migrar o preparar fixtures. La instancia de la suite completa fue `/tmp/sprint4-4df94a3d5306` y quedó detenida. HTTP usa sesiones array/withoutVite en los casos nuevos; otros casos existentes de la suite también cubren sesiones/caché database dentro del servidor ficticio. No hubo consultas ni escrituras deliberadas en la base operativa, migraciones/seeders operativos ni modificación de `.env`.

Chrome usa vistas Blade/modelos reales con relaciones ficticias en memoria y assets compilados. Todas sus solicitudes se interceptan localmente; tráfico externo bloqueado y fuentes fallback. El envío confirmado se captura y cancela en el arnés, sin backend real. **La persistencia efectiva se acredita por las pruebas HTTP/MariaDB; cancelar no genera envío en navegador y el servidor rechaza confirmaciones ausentes/no aceptadas.** No se presenta esta combinación como un recorrido E2E conectado entre Chrome y MariaDB.

Arneses y evidencia visual temporales: `/tmp/historial-productos-render.php`, `/tmp/historial-productos-browser.cjs` y `/tmp/historial-productos-review/` (JSON de resultados y capturas de móvil/escritorio revisadas). Se reutilizó Puppeteer/Chrome ya disponible fuera del proyecto, sin añadir dependencias. `public/hot` existente se conservó; el render aislado seleccionó expresamente el build.

Primeros intentos: sandbox bloqueó el socket de MariaDB y el arranque de Chrome; se repitieron mediante escalamiento y aprobaron. La primera ejecución específica tuvo tres expectativas fallidas al comparar modelos recién creados sin columnas/defaults recargados con modelos recuperados; se corrigió el snapshot inicial usando `fresh`, manteniendo la comparación completa de atributos y participantes. El primer arnés de navegador tenía el origen de los assets en otro puerto; se corrigió solo el arnés. Ninguno de esos intentos fallidos se considera prueba aprobada. Con Node 26, la salida de `node --test` agrupaba por archivo; se ejecutaron los cinco archivos individualmente para verificar los 51 casos internos.

## 8. Pendientes o limitaciones

- **PENDIENTE:** aceptación manual del usuario, dispositivos físicos/Safari, fuentes remotas y lector de pantalla. Los anchos emulados y el teclado están comprobados en Chrome.
- **PENDIENTE:** validación operativa en el entorno de despliegue y sus sesiones reales. Se conservan los pendientes anteriores de migraciones/cuentas documentados en el Vault; esta tarea no los ejecuta ni los declara cerrados.
- El borrado es definitivo; no se implementó papelera, recuperación ni bitácora nueva. Las fotos físicas se conservan por la limitación de propiedad descrita arriba.
- Se mantiene la página de origen; si se elimina su último elemento puede quedar una página vacía. Los filtros permanecen disponibles y se puede volver mediante la paginación existente.
- La eliminación necesita JavaScript y soporte de `<dialog>` para confirmar; si no están disponibles queda deshabilitada. Consultar, filtrar y desplegar detalles conservan su comportamiento nativo.
- Los bloqueos de eliminación están implementados y el rollback está probado; no se añadió una prueba específica de carrera entre eliminar y registrar. Las pruebas concurrentes preexistentes de Producción/Productos sí pasaron en la suite completa. Futuros escritores deben respetar los bloqueos y las FK actuales.

### Revisión manual sugerida con datos de prueba

1. Administrador: filtrar Historial, abrir un lote y comprobar producto/fecha/turno del cuadro; cancelar y repetir con Escape.
2. Confirmar un lote de una cabecera con varios detalles: debe desaparecer solo ese lote y mostrarse el mensaje conservando filtros. Repetir con el último detalle y las cuatro variantes de producción en un entorno de prueba.
3. Operador: comprobar ausencia del botón y rechazo del DELETE manipulado.
4. Productos: revisar Desactivar con ratón/teclado, Editar y Activar; comprobar sus funciones usando productos ficticios.
5. Repetir la lectura y confirmación en móvil y escritorio.

**Listo para revisión manual.** No se hizo commit, push, despliegue ni eliminación de datos reales para verificar esta entrega.
