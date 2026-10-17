# Productos - Panes por lata

## Aclaración de la regla Pan LATA / UNIDAD — 2026-10-16

La nueva regla confirmada por el usuario tras contrastar el procedimiento físico sustituye cualquier supuesto inferior de que todo Pan usa LATA: Pan admite LATA o UNIDAD según el producto. Coches, 18 latas/coche, factor/snapshot y hora nueva obligatoria se aplican solo a Pan LATA. Pan UNIDAD conserva cantidad directa y factor/snapshot/hora NULL; Torta/Bocadito siguen UNIDAD sin hora. Historial usa la unidad del detalle y Dashboard suma solo latas históricas. No hay detección por nombre, migración nueva ni cambios de datos operativos.

**IMPLEMENTADO Y COMPROBADO en aislamiento:** 872 PHP/7.896 aserciones sin fallidas/omitidas, 51 JS, Chrome 40/40, build, Pint de PHP afectado y diff aprobados. Aceptación operativa del usuario **PENDIENTE**. Informe vigente: [Pan por Lata o Unidad](PAN_REGISTRO_LATA_UNIDAD.md). El resto conserva íntegros el contexto, los resultados y los pendientes históricos de esta revisión anterior; no constituye el contrato actualizado de Pan. Login/Usuarios/turnos/contraseñas permanecen preservados.

Fecha: 2026-10-12. Estado: **IMPLEMENTADO Y COMPROBADO en aislamiento**. Aceptación operativa del usuario: **PENDIENTE**. Sin commit ni push.

## Problema encontrado

El CRUD ya tenía `panes_por_lata`, pero Pan podía guardarse sin factor y la interfaz lo presentaba como opcional. No cumplía la nueva regla de configurar obligatoriamente cuántos panes contiene una lata.

Antes de modificar la implementación se auditaron rutas, Producto/Categoria/UnidadMedida/DetallePan, migraciones, seeders, ProductoController, Requests, ActualizarProducto, Blade, CSS, JavaScript, tests y su integración con Producción e Historial. Se ejecutó una referencia de pruebas y se reprodujo el problema.

## Estado anterior

- Categoria tiene `nombre_categorias`, sin slug/código. CategoriaSeeder resuelve Pan/Torta/Bocadito por nombre mediante `firstOrCreate`, sin IDs fijos.
- Producto ya incluye el factor en fillable y tiene relaciones con categoría, unidad y los tres tipos de detalle.
- La migración `2026_10_02_200000_add_panes_por_lata_to_productos_and_detalle_pan.php` define `productos.panes_por_lata` y `detalle_pan.panes_por_lata_usado` como INTEGER nullable, sin default/backfill. Su rollback protege valores existentes.
- Crear/editar comparten `resources/views/productos/form.blade.php`; no hay create/edit separados.
- GuardarProductoRequest concentra reglas de CrearProductoRequest/ActualizarProductoRequest. Permitía NULL o entero positivo en Pan, rechazando factor no vacío en Torta/Bocadito.
- El JS ya ocultaba/deshabilitaba el campo fuera de Pan, pero no alternaba required.
- ActualizarProducto comprueba historial de las tres familias dentro de transacción, bloqueando categoría/unidad y respetando locks categoría → producto.
- Las rutas requieren auth y can:administrar-sistema. Existen FormRequests/Actions; no existen Services/DTO/Policies propios del módulo que haya que duplicar.
- Producción bloquea Producto y copia el factor vigente al snapshot del lote. Historial usa ese snapshot para estimar panes.

La base local fue consultada exclusivamente en transacción **READ ONLY**, cerrada con rollback: ambas columnas son INT nullable. Conteos agregados: un producto Pan con factor positivo, ninguno NULL/inválido y ningún producto Torta/Bocadito. No se leyeron cuentas/credenciales/sesiones ni se realizaron escrituras de negocio.

## Solución implementada

Reutilizar el Request común para exigir factor en Pan, identificado consultando la categoría real. Mantener rechazo de factores incompatibles y normalización existente de campo omitido a NULL. Blade retira «opcional» y explica factor frente a cantidad de latas; JS alterna required con hidden/disabled.

**No se creó ni ejecutó una migración nueva.** INTEGER nullable ya soporta la funcionalidad y permite representar otras familias e históricos desconocidos. NOT NULL o un backfill obligaría a inventar información y no corresponde.

## Archivos modificados

| Archivo del repositorio | Motivo |
|---|---|
| `app/Http/Requests/GuardarProductoRequest.php` | Factor obligatorio por identidad real de categoría y mensajes españoles. |
| `resources/views/productos/form.blade.php` | Etiqueta/ayuda y required inicial de Pan. |
| `resources/js/productos.js` | Required dinámico junto a visibilidad/habilitación. |
| `tests/Feature/ProductosTest.php` | Adaptar contrato anterior y ampliar cobertura HTTP/integración. |
| `tests/JavaScript/ProductosTest.mjs` | Seis pruebas de estados iniciales/transiciones. |
| `docs/PRODUCTOS_PANES_POR_LATA.md` | Informe solicitado. |

Documentación original del Vault actualizada con autorización y conservación íntegra de historia:

- `LARAVEL/Requests.md`: validación y normalización.
- `LARAVEL/Blade.md`: UI, fallback y verificación en Chrome.
- `DECISIONES/Decisiones Laravel.md`: decisión fechada, contexto, opciones, motivo y consecuencias. Ese es el nombre real; no existe allí una nota `.md.md` equivalente.
- `INICIO/Estado de proyecto.md`: resumen del estado y límites.

Durante esta tarea se observaron eliminaciones concurrentes de informes anteriores, `dato_optimizar/*` y `mejora_asignado.md`. No fueron realizadas por esta implementación ni se restauraron. La primera versión de este informe también desapareció y se volvió a crear para cumplir la solicitud. **PENDIENTE de respuesta:** recuperar o conservar la eliminación de `mejora_asignado.md`; no se actualizó ese archivo ausente. El seguimiento completo queda disponible en este informe y en el Vault. Las escrituras del Vault fueron autorizadas y completadas; no quedó autorización de herramientas pendiente.

## Validaciones añadidas

- Pan: obligatorio al crear y al editar; omitido, NULL y vacío se rechazan.
- Mensaje: «Debes indicar cuántos panes contiene una lata.»
- Se conservan entero positivo estricto, mínimo 1 y máximo 2147483647 compatible con INT firmado. Cero, negativos, texto, decimales, floats, notación científica, booleanos, arrays y overflow siguen rechazados.
- Mensaje del mínimo: «Los panes por lata deben ser mayores que 0.»
- Torta/Bocadito: no obligatorio; factor no vacío continúa siendo inválido.
- Categoría/unidad existentes y whitelist de atributos conservadas.

El ID se interpreta mediante FILTER_VALIDATE_INT, igual que `integer` del Laravel instalado, antes de consultar el nombre de categoría. Esto evita consultas con arrays y elusión de required con referencias aceptadas como `+701` o un número JSON integral. Esa compatibilidad pertenece al ID; no flexibiliza el tipo del factor.

No hay comparaciones con IDs constantes ni reglas nuevas en ProduccionController.

## Comportamiento del formulario

| Categoría seleccionada | Factor | required | FormData |
|---|---|---|---|
| Pan | Visible y habilitado | Sí | Incluido |
| Torta | Oculto y deshabilitado | No | Omitido |
| Bocadito | Oculto y deshabilitado | No | Omitido |
| Sin categoría | Oculto y deshabilitado | No | Omitido |

JS utiliza `data-nombre` generado desde Categoria y el evento change, sin recarga. Editar Pan muestra su valor vigente; los errores conservan old input saneado por el controlador y asociaciones ARIA/mensajes existentes.

Alternar temporalmente Pan → otra familia → Pan conserva el valor escrito en memoria del formulario. Guardar otra familia sin historial limpia la configuración persistida. Con producción previa, categoría/unidad continúan protegidas y enviadas mediante hidden actuales; el backend revalida la restricción.

La cantidad de latas permanece únicamente en Producción. Se conservan CSS, tamaños, paleta, tipografía, bordes, espaciados y responsive. No hubo rediseño.

Sin JS se conserva el fallback previo: campo disponible con ayuda y servidor validando. Blade incorpora required en Pan inicialmente seleccionado; la ocultación y cambio dinámico de required requieren JS. Una selección nueva sin JS recibe la validación del servidor. No se acredita ocultación dinámica sin JavaScript.

## Comportamiento del backend

Rule::requiredIf depende de la categoría consultada por el ID enviado y su nombre almacenado. HTML inválido vuelve con mensajes/old input; JSON recibe 422. Tests comparan datos antes/después para comprobar que solicitudes inválidas no escriben.

Torta/Bocadito con factor 30 manipulado producen error y no persisten. Se conservó el rechazo explícito existente, evitando descartar silenciosamente el dato incompatible.

En una recategorización permitida desde Pan, JS omite el control deshabilitado. `prepareForValidation()` ya normalizaba omisión a NULL; validated entrega ese NULL y ActualizarProducto limpia el factor previo. Tests específicos cubren ambas familias.

Historial bloquea categoría/unidad en las tres familias. Factor/nombre siguen editables según las reglas actuales. Un Pan antiguo NULL puede configurar un factor válido sin rellenar snapshots históricos desconocidos. Estado, autorización, whitelist y temporada_fe conservan su comportamiento.

## Integración con Producción

No se modificó el código de Producción/Historial, modelos ni migraciones. Se comprobó por HTTP:

1. Crear Pan francés con factor 30.
2. Registrar cero coches y 12 latas adicionales.
3. Verificar cantidad 12, snapshot 30 y estimación 360 en Historial.
4. Editar el producto a 32 con historial existente.
5. Comparar todos los atributos del detalle anterior: permanecen idénticos.
6. Registrar otro lote: snapshots `[30, 32]`, estimaciones 360/384.

La cantidad canónica sigue en latas y la capacidad existente de un coche sigue en 18. Producción mantiene compatibilidad con factores antiguos desconocidos; su eliminación requeriría otra decisión de negocio.

## Tests realizados

| Comando/verificación | Propósito y expectativa |
|---|---|
| `php tests/run-mariadb.php --filter=ProductosTest --compact --do-not-cache-result` previo | Referencia del CRUD: pruebas anteriores deben pasar. |
| `php tests/run-mariadb.php --filter='crear y editar Pan exigen factor' --compact --do-not-cache-result` previo al cambio del Request | Reproducir el defecto: seis expectativas nuevas deben fallar porque antes se guardaba sin factor. |
| `php tests/run-mariadb.php --filter=Productos --compact --do-not-cache-result` | CRUD, concurrencia y casos relacionados deben pasar con el cambio. |
| `php tests/run-mariadb.php --compact --do-not-cache-result` | Suite PHP completa para detectar regresiones, repetida tras la protección de representaciones de ID. |
| `node --test tests/JavaScript/*.mjs` y ejecución directa de cada `.mjs` | Código JS real/eventos con dobles DOM; ejecución directa identifica los 25 casos individuales. |
| `npm run build` | Compilación de assets CSS/JS del formulario con Vite. |
| `vendor/bin/pint --test app/Http/Requests/GuardarProductoRequest.php tests/Feature/ProductosTest.php` | Formato del PHP modificado. |
| `php -l` de los dos archivos PHP modificados | Sintaxis válida. |
| `git diff --check` y revisión del diff | Whitespace correcto y cambios acotados. |
| Chrome headless con Blade real/assets compilados | 7 estados × 5 anchos, transiciones, FormData, errores, historial protegido y fallback inicial sin JS. |

Cobertura solicitada A–I: Pan válido; ausencia/cero/negativo; Torta/Bocadito sin factor; IDs variables; edición conservando/modificando factor; restricciones actuales con historial. También se prueban texto inválido, tipos malformados/límites, errores/old input, limpieza al recategorizar y snapshots conocidos/desconocidos.

Las expectativas antiguas que aceptaban NULL fueron adaptadas al nuevo contrato; no se eliminaron tests. La prueba de historial que intenta cambiar a Pan envía ahora factor válido para alcanzar la protección de la Action y comprobar que sigue rechazando la categoría.

La suite final incluye ProductosConcurrenciaTest (18 carreras reales), ProduccionConcurrenciaTest, ProduccionPanStoreTest, RegistrarProduccionPanActionTest, ProduccionTortaBocaditoStoreTest, PanUnitsTest, ModelsRelationshipsTest, ProductionFamilyRelationshipsTest, ProductionObservationsMigrationTest e HistorialProduccionTest, además de autorización y otras regresiones.

## Resultado de los tests

- Referencia anterior: **109 aprobadas, 634 aserciones**.
- Reproducción previa: **6/6 fallidas**, esperadas porque el servidor guardaba en lugar de devolver error. Luego corregidas.
- Filtrado Productos tras primera implementación: **162 aprobadas, 1.131 aserciones**.
- Primera suite completa: **591 aprobadas, 5.437 aserciones**.
- Suite completa final: **595 aprobadas, 5.461 aserciones, cero fallidas/omitidas**.
- JS: **25 aprobadas**: 6 Productos + 12 Producción + 7 sidebar.
- Chrome: **35/35**, cero errores JS, sin overflow/controles fuera del viewport; factor enviado solo en Pan e identidad protegida con historia. Capturas móvil/escritorio revisadas.
- Build, Pint afectado, sintaxis y diff aprobados. Vite mantiene aviso preexistente del paquete opcional fontaine; no impide compilar.

Aislamiento: runner crea MariaDB sin red bajo `/tmp/sprint4-*`, verifica datadir/base y detiene la instancia. Instancia final: `/tmp/sprint4-83c6348134cf`. Escrituras/migraciones de pruebas únicamente en bases ficticias; HTTP usa sesiones array/withoutVite. Assets se verifican aparte. El sandbox bloqueó sockets/loopback; las ejecuciones necesarias se repitieron mediante escalamiento autorizado, sin contar los intentos bloqueados como pruebas ejecutadas.

Chrome usa fixtures en memoria, servidor estático de solo lectura y tráfico externo bloqueado; no envía formularios ni accede a la base operativa. Medidas con fuentes fallback, sin acreditar descarga externa de Huninn. Scripts/capturas temporales: `/tmp/panaderia-factor-render.php`, `/tmp/panaderia-factor-browser.cjs` y `/tmp/panaderia-factor-verificacion/`. No se instalaron dependencias nuevas.

## Decisiones técnicas

1. Usar el nombre real del catálogo, sin slug/código/IDs constantes nuevos.
2. Reutilizar GuardarProductoRequest para crear/editar; no agregar capas ni engordar controladores.
3. Exigir factor en el CRUD manteniendo nullable para otras familias y datos históricos.
4. Conservar rechazo de factores incompatibles y limpieza mediante omisión → NULL existente.
5. Conservar el valor temporal al alternar categorías; validar la categoría final al guardar.
6. Mantener locks y snapshots, probando integración con escrituras reales solo en aislamiento.
7. No inventar defaults ni copiar configuración actual a históricos desconocidos.

## Riesgos o pendientes encontrados

- **PENDIENTE:** aceptación en navegador habitual, dispositivos reales/Safari/iOS, lector de pantalla, sesiones database y despliegue. Las pruebas aisladas no acreditan esos entornos.
- **PENDIENTE previo:** catálogo definitivo de unidades. No se presupone unidad Lata/Unidad por familia.
- Categoria no tiene garantía UNIQUE de nombre. Duplicados/renombrados requieren decisión separada; no se corrigieron automáticamente.
- FormRequests protegen el flujo HTTP; llamadas directas a modelo/SQL/Action no incorporan por sí mismas esta validación. Futuros escritores deben validar y respetar locks.
- Pan antiguo con NULL deberá configurar factor al guardar edición completa. Producción mantiene compatibilidad con snapshots/factores desconocidos.
- Renombrar Producto cambia el nombre visible en históricos porque no hay snapshot de nombre; comportamiento previo conservado.
- Ocultación/required dinámicos necesitan JS cargado; fallback descrito arriba.
- No se repararon/clasificaron producciones antiguas ni se modificaron cuentas/autenticación u otros pendientes ajenos.
- Eliminaciones concurrentes de documentación conservadas; no se restauraron informes anteriores automáticamente.

Pruebas manuales sugeridas:

1. Nuevo producto → Pan: ingresar 30, nombre/unidad válidos y guardar.
2. Nuevo producto → Torta/Bocadito: comprobar campo oculto y guardar sin factor.
3. Pan vacío/cero/negativo/decimal: verificar rechazo. Para ver el mensaje exacto del servidor puede deshabilitarse temporalmente la validación HTML; no debe persistir dato inválido.
4. Editar Pan sin historial: alternar categorías, volver a Pan, conservar 30 y guardar 32.
5. Editar Pan con producción: categoría/unidad protegidas; factor editable sin alterar snapshot anterior.
6. En entorno de pruebas, registrar 12 latas con 30, cambiar a 32 y registrar otro lote: Historial conserva 360 y muestra 384 en el nuevo.
7. Revisar espaciados, teclado y errores en escritorio/móvil con assets compilados; `public/hot` debe estar ausente cuando no se usa Vite dev.

## Recomendaciones para Torta y Bocadito

Mantener factor NULL: sus flujos actuales registran cantidad, no el factor de Pan. Antes de crear parámetros propios, definir reglas/unidades con el negocio y decidir si necesitan snapshot por lote. No reutilizar este campo ni generalizar el escritor por anticipado. No se implementaron cambios grandes ni reglas nuevas para estas familias.
