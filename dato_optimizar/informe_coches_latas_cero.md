# Informe - Coches y latas adicionales independientes

Fecha: 2026-10-09. Proyecto: `panaderia-rs`.

Estado: **IMPLEMENTADO Y COMPROBADO mediante HTTP/MariaDB aislada y eventos JavaScript**. Build aprobado. Aceptación manual en navegador **PENDIENTE**. Pint de PHP modificado aprobado; Pint global **FALLIDO por formato preexistente en 29 archivos ajenos a esta tarea**, detallado abajo. Sin commit/push.

## 1. Problema encontrado

El backend ya permitía un cero explícito en coches y registrar solo latas adicionales. El bloqueo práctico estaba en que ambos inputs empezaban vacíos y tenían `required`, y el FormRequest rechazaba valores vacíos en cualquiera de las dos cantidades. El preview usaba directamente `valueAsNumber`; un vacío producía NaN y un mensaje de cantidad inválida.

Reproducción previa con el validador instalado y las reglas numéricas reales del Request, sin consultar BD: `[0, 12]` válido; `['', 12]` inválido por coches; `[2, '']` inválido por latas. No se encontró `min:1` en coches: ya tenía `min:0`. Los mínimos de participantes son reglas diferentes y se conservaron.

## 2. Flujo actual inspeccionado

1. `routes/web.php`: POST `/produccion`, nombre `produccion.store`, dentro de auth.
2. `resources/views/produccion/index.blade.php`: envía `detalles[0][coches]` y `detalles[0][latas_adicionales]` junto con producto, fecha, turno, observación y participantes. Publica `DetallePan::LATAS_POR_COCHE` en `data-latas-por-coche`.
3. `resources/js/produccion.js`: calcula solamente el preview; no envía cantidad/total derivados. Gestiona participantes y reset con conservación de fecha/turno.
4. `RegistrarProduccionPanRequest`: valida estructura, cantidades, IDs, observación y participantes antes de store. Antes no tenía normalización propia de las cantidades.
5. `ProduccionController::store()`: pasa `$request->validated()` y el identificador del usuario autenticado a `App\Actions\Produccion\RegistrarProduccionPan::ejecutar()`.
6. La Action calcula total, valida reglas de negocio y crea/reutiliza cabecera, inserta detalle y vincula participantes en transacción. Es el único punto de cálculo/persistencia del total en el backend. No existe un Service separado usado por este store.
7. `DetallePan::cantidad` guarda el total entero de latas. No hay columnas redundantes de coches/latas adicionales. El modelo ya define `LATAS_POR_COCHE` y `MAX_CANTIDAD_LATAS`.

Se inspeccionaron además pruebas Store/Action/unidades/consultas, el runner MariaDB y el ciclo de FormRequest instalado. Laravel aplica `prepareForValidation()` antes de construir/evaluar el validador; el stack global convierte strings vacíos a null. Referencia: [preparación de entrada en Laravel](https://laravel.com/framework/docs/validation#preparing-input-for-validation).

## 3. Validación anterior

- Coches: required, integer, min:0 y máximo derivado de la capacidad de cantidad/LATAS_POR_COCHE.
- Latas adicionales: required, integer, between:0 hasta `LATAS_POR_COCHE - 1`.
- Los vacíos se rechazaban antes de llegar a la Action. No se normalizaban a cero en el Request.
- La Action ya rechazaba total menor o igual a cero y total superior a `MAX_CANTIDAD_LATAS`, con un mensaje conjunto poco específico para quien dejaba ambos campos en cero.
- Blade mostraba cantidades vacías, min=0, step=1 y required en ambos inputs. JS clasificaba los vacíos como cantidades inválidas.

Se conserva el límite existente de latas adicionales menores que `LATAS_POR_COCHE`: representa el resto además de coches completos. Las combinaciones solicitadas caben en ese contrato. No se modifica la equivalencia operativa ni se introduce una nueva capacidad.

## 4. Cambios implementados

El Request añade `prepareForValidation()` para normalizar cantidades vacías. Los tipos y límites siguen comprobándose en backend. Las latas adicionales pasan de `between` a `min:0`/`max:` con el mismo límite, para poder dar mensajes españoles separados de negativo y exceso.

La Action conserva cálculo y reglas, separando únicamente el mensaje de total cero del mensaje de desbordamiento. Blade muestra cero inicialmente y elimina required individual. JS admite vacíos en el preview y deja cantidades en cero al limpiar producto. No se cambia diseño, CSS o arquitectura.

## 5. Normalización de vacíos

Para cada detalle que ya sea un array válido, se inspeccionan coches/latas adicionales y se convierten estrictamente **null o `''` en el entero 0**. Un campo ausente también se trata como null y queda en cero. Se normaliza antes de reglas numéricas y antes de la comprobación cruzada de la Action.

No se usa conversión indiscriminada a int, empty() o un cast que transforme `'abc'` en 0. Texto no numérico, decimales, números negativos y arrays conservan su entrada para ser rechazados. No se fabrica un array de detalles cuando está ausente/malformado, ni se cambia el índice del producto. Se mantiene la protección previa al recuperar old input malformado.

`required` sigue en las reglas numéricas como garantía estructural **después de normalizar**: el cero es una entrada no vacía válida para Laravel. No obliga a introducir manualmente ambos valores y no necesita nullable, porque los vacíos legítimos ya son cero cuando se evalúan integer y los límites. Los tests comprueban ceros normalizados también en old input tras rechazar el total cero.

## 6. Regla de validación cruzada

Permanece en `RegistrarProduccionPan::validarReglas()`, inmediatamente después del cálculo existente y antes de insertar cabecera/detalle/participantes. Solo llega entrada validada desde el Request.

Total menor o igual a cero produce error en `detalles.0.coches` con **«Ingresa al menos un coche o una lata adicional.»**. Con ambas cantidades enteras no negativas, esto impide la combinación 0/0. No se añade otro cálculo cruzado a un callback del Request ni se depende de JavaScript.

Un total superior a `MAX_CANTIDAD_LATAS` conserva el rechazo previo, ahora con mensaje independiente. Cualquier validación fallida deja intactos cabecera, detalle y pivots, comprobado en MariaDB aislada.

## 7. Cálculo total de latas

Se mantiene literalmente el punto de cálculo existente de la Action:

```php
$totalLatas = (int) $detalle['coches'] * DetallePan::LATAS_POR_COCHE + (int) $detalle['latas_adicionales'];
```

La constante sigue siendo la fuente canónica. No se cambia el modelo ni se escribe su valor numérico en código nuevo. La Action persiste `$totalLatas` en `detalle_pan.cantidad`; los datos derivados no se aceptan desde un total enviado por el cliente.

Resultados: 0/12 guarda 12; 2/0 guarda `2 * LATAS_POR_COCHE`; 2/5 guarda `2 * LATAS_POR_COCHE + 5`. Historial/Dashboard siguen leyendo esa misma cantidad. El cálculo del navegador sigue siendo exclusivamente informativo.

## 8. Frontend

Ambos inputs conservan nombres/IDs, type=number, step=1 y min=0. Muestran value=0 cuando no hay un valor anterior. Required individual se elimina; producto, fecha y turno conservan su contrato. Latas adicionales conserva el max derivado de la constante.

La ayuda dice **«Ingresa al menos un coche o una lata adicional.»**. Los valores válidos anteriores se conservan al recuperar errores; arrays rechazados se saneaban a vacío en Blade y ese comportamiento seguro se mantiene.

El preview interpreta un vacío válido como cero. Usa `validity.badInput` para distinguir una entrada numérica malformada del vacío legítimo; no publica NaN. Con ambas cantidades en cero muestra el total cero y la ayuda. Conserva validación visual de enteros, no negativos y rango de latas, sin convertirse en fuente de validación.

El factor procede del data attribute ya publicado por Blade, sin literal de capacidad. Limpiar producto deja cantidades en 0, elimina selección/observación/participantes y conserva fecha/turno como antes. No se alteran controles de participantes, sidebar, colores, layout o responsive.

## 9. Tests añadidos/modificados

Se amplía **ProduccionPanStoreTest**, sin crear otra suite PHP:

- Dataset de guardado: conserva cinco casos anteriores y añade 0/12, 2/0, 2/5, vacío/12, 2/vacío, null/12 y 2/null.
- Cada uno verifica cantidad usando la constante, producto, turno, cabecera y pivots de Maestro/Ayudante. Con fecha de hoy fijada solo en el test, consulta Dashboard e Historial y verifica el mismo total/lote persistido.
- Tres casos de 0/0, ambos `''` y ambos null: mensaje cruzado, old input normalizado a 0 y ausencia de escrituras.
- Dos casos de cantidad omitida: se normaliza ese campo y se guarda utilizando el otro.
- Se sustituyen los rechazos anteriores de un null aislado por rechazos de `'abc'` en ambos campos. Se conservan negativos, decimales, arrays, cantidades fuera de rango/desbordamiento y demás reglas.
- Se adaptan expectativas del mensaje de total cero y del formulario limpio, verificando value=0, min=0, step=1, ausencia de required y data attribute derivado del canon.

Nuevo **tests/JavaScript/ProduccionCantidadTest.mjs**: tres casos de eventos reales del script completo con dobles mínimos del DOM: preview de vacíos/factor publicado, rechazo visual de números inválidos/badInput y reset con cantidades cero/contexto conservado. Se usa deliberadamente un factor de ejemplo distinto en el doble para detectar un eventual hardcode en JS; no se cambia la constante real. No comprueba geometría ni validación nativa en un navegador.

## 10. Resultados exactos de pruebas

| Comando | Resultado |
|---|---|
| `php tests/run-mariadb.php --filter='ProduccionPanStoreTest\|RegistrarProduccionPanActionTest\|PanUnitsTest\|DashboardTest\|HistorialProduccionTest'` | Primera regresión: **178 aprobadas, 1.738 aserciones, salida 0**. Se ejecutó antes de ampliar las aserciones de consultas en los casos de guardado y añadir los dos casos de campos omitidos |
| `php tests/run-mariadb.php --do-not-cache-result` | Verificación del estado final: **240 aprobadas, 2.618 aserciones, sin omisiones, salida 0**. Incluye todas las ampliaciones finales |
| `node --test tests/JavaScript/ProduccionCantidadTest.mjs` | Salida 0; el reporter predeterminado de Node resume un archivo aprobado |
| `node --test --test-isolation=none --test-reporter=tap tests/JavaScript/ProduccionCantidadTest.mjs` | **3 casos aprobados, 0 fallidos/omitidos, salida 0**, mostrando el detalle de los casos |
| `vendor/bin/pint --test app/Http/Requests/RegistrarProduccionPanRequest.php app/Actions/Produccion/RegistrarProduccionPan.php tests/Feature/ProduccionPanStoreTest.php` | Aprobado, salida 0 |
| `vendor/bin/pint --test` | **Fallido, salida 1**, por formato en 29 archivos preexistentes ajenos al cambio. No se presenta como aprobado |
| `npm run build` | Aprobado, salida 0: Vite transforma 12 módulos y genera manifest/assets. Aviso sobre `fontaine` opcional sin impedir build |
| `git diff --check` | Sin errores, salida 0 |

El fallo global de Pint afecta `app/Http/Controllers/Controller.php`; los modelos `Cargo.php`, `RolProduccion.php`, `Empleado.php`, `Rol.php`; `config/auth.php`; 19 migraciones originales (las 18 `2026_09_14_202615` a `2026_09_14_202632`, y `2026_09_14_205442_create_sessions_table.php`); seeders `CargoSeeder.php`, `EmpleadoSeeder.php`, `RolSeeder.php`, `UsuarioSeeder.php`. Incluye espacios finales, separación, indentación, comas, posición de llaves y otros fixers de estilo. Se comparó el contenido de **cada uno de los 29 archivos con HEAD: idénticos**, demostrando que no fueron modificados en esta tarea. No se hizo una limpieza de formato fuera del alcance autorizado.

Los tests HTTP usan sesiones array, Vite omitido y cuentas/fixtures en MariaDB desechable. Runner sin red, dos bases ficticias y verificación de datadir/base antes de migrar; se ejecutó con escalamiento por el bloqueo conocido de sockets Unix del sandbox. Artefactos: `/tmp/sprint4-abbcded22c49` (primera regresión), `/tmp/sprint4-42683220e87e` (suite final); instancias detenidas. No se operó sobre datos del proyecto ni se ejecutó migrate:fresh.

Antes de iniciar había solo 22 MB libres en /tmp. Con autorización se retiraron únicamente las dos bases ficticias detenidas de la tarea anterior, en `/tmp/sprint4-92e50a56cfb8/mariadb-data` y `/tmp/sprint4-0a103a9d0f82/mariadb-data`, conservando logs. No se borraron datos reales ni artefactos ajenos.

## 11. Archivos modificados

| Ruta | Cambio y motivo |
|---|---|
| `app/Http/Requests/RegistrarProduccionPanRequest.php` | Normalización previa de vacíos, mismos límites con min/max y mensajes españoles por cantidad |
| `app/Actions/Produccion/RegistrarProduccionPan.php` | Mensajes diferenciados de total cero/exceso, reutilizando regla/cálculo/transacción existentes |
| `resources/views/produccion/index.blade.php` | Ceros por defecto, cantidades sin required y ayuda cruzada, conservando old input seguro |
| `resources/js/produccion.js` | Preview de vacíos sin NaN/badInput y reset de cantidades a cero |
| `tests/Feature/ProduccionPanStoreTest.php` | Casos/expectativas de cantidades, normalización, ausencia de escrituras, formulario y consulta de Dashboard/Historial |
| `tests/JavaScript/ProduccionCantidadTest.mjs` | Tres comprobaciones pequeñas del script/eventos de preview y reset |
| `mejora_asignado.md` | Seguimiento, evidencia, fallo de Pint global y revisión manual pendiente |
| `dato_optimizar/informe_coches_latas_cero.md` | Informe solicitado con flujo, decisiones, resultados y prueba manual |

Build regenera archivos ignorados de `public/build`; no modifica fuentes CSS ni configuración Vite.

Notas originales afectadas: `LARAVEL/Requests.md` (normalización y responsabilidades), `PRODUCCION/Logica de negocio.md` (cantidades y regla positiva existente), `PRODUCCION/Pantallas.md` (inputs/ayuda/preview/reset), `PENDIENTES/Bugs.md` (una entrada para causa y solución del bloqueo), `DECISIONES/Decisiones Laravel.md` (decisión de normalizar sin duplicar negocio), `INICIO/Estado de proyecto.md` (resumen y pendientes). Se conserva su historia sin duplicar o renombrar notas; nombre real del registro de decisiones respetado. Integración mediante escalamiento del filesystem sobre el Vault original, sin copia documental en el repositorio.

## 12. Archivos NO modificados

Todos los controllers, incluido ProduccionController; modelo DetallePan y sus constantes, demás modelos, migraciones, seeders, categorías, roles o composición de participantes; routes/web.php; timezone, trusted proxies/bootstrap, Cloudflare, login/logout y sidebar; CSS, diseño/colores/responsive, configuración Vite, dependencias y .env real; Torta/Bocadito; runner/soporte de aislamiento y tests de Dashboard/Historial/Action.

No hay refactor general, nueva arquitectura, otra fórmula de negocio o nuevas columnas. No se inicia cloudflared ni servidor Laravel. No hubo commit/push.

## 13. Riesgos o pendientes

**PENDIENTE:** aceptación manual de las siete comprobaciones siguientes en navegador. Los eventos JS usan dobles del DOM y los tests HTTP omiten Vite; el build aprobado no sustituye la revisión visual ni certifica el entorno real o sus sesiones database.

**PENDIENTE fuera de alcance:** corregir y volver a comprobar el formato global de los 29 archivos indicados. Pint de los PHP modificados ya pasa; no se da por superado Pint global.

El límite previo de latas adicionales menores que LATAS_POR_COCHE y la capacidad INT del total siguen vigentes. No se altera la capacidad canónica ni se resuelven discrepancias históricas documentadas de equivalencias mediante esta tarea. Vacíos ahora equivalen a cero; el rechazo de total cero continúa siendo obligatorio. No quedan implementaciones pendientes para esa corrección comprobada en el entorno aislado.

## 14. Prueba manual recomendada

- coches 0 / latas 12
- coches 2 / latas 0
- coches 2 / latas 5
- coches 0 / latas 0
- dejar uno de los dos vacío
- intentar valores negativos
- comprobar que Historial y Dashboard muestran el resultado esperado
