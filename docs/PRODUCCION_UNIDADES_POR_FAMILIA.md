# Producción - Unidades por familia

Fecha: 2026-10-14. **IMPLEMENTADO Y COMPROBADO en aislamiento** mediante suite relevante y completa. Aceptación visual/operativa: **PENDIENTE**. Sin commit ni push.

## Problema detectado

Productos ofrecía el mismo selector de unidad para las tres familias. El catálogo local contiene únicamente «Lata»: podía sugerir que Torta/Bocadito se registraban por latas. No se encontró esa conversión en sus escritores ni en Historial. Producción Pan no mostraba todavía su factor y estimación junto al total de latas.

## Regla de negocio

Confirmada expresamente por el usuario del proyecto para esta tarea:

| Familia | Significado de cantidad | Cálculo |
|---|---|---|
| Pan | Latas | cantidad × panes_por_lata_usado = panes estimados |
| Torta | Tortas producidas | Cantidad directa, sin conversión |
| Bocadito | Bocaditos producidos | Cantidad directa, sin conversión |

Solo Pan utiliza configuración `productos.panes_por_lata` y snapshot `detalle_pan.panes_por_lata_usado`. La equivalencia del catálogo no convierte las cantidades de Torta/Bocadito.

## Estado anterior

El checkout ya incluía cambios sin commit de la mejora de panes_por_lata en Request, formulario/JS y tests de Productos, además de informes sin seguimiento y eliminaciones de documentación. Se conservaron. No se restauraron archivos ni se atribuyen sus eliminaciones a esta entrega.

Pan ya registraba coches × 18 + latas adicionales como total de latas, con snapshot bajo lock. Torta/Bocadito ya validaban y guardaban enteros positivos directamente. Producción decía «Cantidad (unidades)» para esas dos familias y ocultaba/deshabilitaba los campos de Pan. Historial ya separaba latas/unidades y limitaba snapshot/estimación a Pan.

## Auditoría realizada

Se revisaron las migraciones de productos, unidades/categorías, los tres detalles y aditivas de cantidad Torta/factores; modelos Producto, UnidadMedida, Categoria y detalles; Requests de Productos/Producción; Actions por familia, cabeceras/participantes y ActualizarProducto; ProductoController, ProduccionController, HistorialController y ConsultarHistorialProduccion; formulario de Productos, vistas de Producción/Historial, JS/CSS y tests/fixtures/runner relevantes.

No hay código/slug canónico ni UNIQUE de nombre en unidades_medida. No existe un seeder dedicado que garantice «Unidad». Los datos de desarrollo/pruebas no acreditan el catálogo operativo.

La base local se consultó exclusivamente en transacción READ ONLY cerrada con rollback, sin imprimir configuración de conexión ni datos de cuentas. Resultado:

- Catálogo: una fila «Lata», equivalencia 1.00. No existe «Unidad» ni hay duplicados actuales de unidad. El ID observado no se usa como constante en la implementación.
- Categorías: Pan, Torta y Bocadito.
- Productos: 2 Pan; 0 Torta y 0 Bocadito.
- Detalles: 5 Pan, 0 Torta y 0 Bocadito. Cantidades no positivas: 0 en las tres tablas. Snapshots Pan nulos: 0. Productos no Pan con factor: 0.
- `productos.unidad_medida_id`: FK NOT NULL. Ambos factores: INT nullable. Las tres cantidades: INT NOT NULL; sus migraciones exigen positividad.

Estos son conteos del checkout/base local consultados, no una auditoría de otro entorno. No se corrigieron registros automáticamente ni se declara una auditoría exhaustiva de todas las posibles inconsistencias.

## Producto Pan

Conserva nombre, categoría, selector de unidad y panes_por_lata obligatorio/entero positivo. La funcionalidad previa, validación del servidor, edición y protección de categoría/unidad con historia permanecen. No se asigna una unidad por ID constante.

## Producto Torta

Muestra «Forma de registro — Unidad / cantidad directa» y explica que se registra cantidad de tortas. El factor Pan se oculta inicialmente en Blade y se oculta/deshabilita al cambiar categoría mediante JS. El servidor conserva el rechazo de factor no vacío.

Se mantiene el selector de unidad porque la FK es obligatoria y no hay unidad canónica disponible. Se aclara su función como asociación de catálogo; se advierte cuando falta una única «Unidad» con equivalencia 1. No se asigna automáticamente ni se inventan registros.

## Producto Bocadito

Mismo tratamiento de cantidad directa, referido a bocaditos. No requiere ni utiliza panes_por_lata. La asociación de unidad se mantiene explícita con la misma advertencia de catálogo.

## Producción Pan

Conserva los inputs funcionales Coches/Latas adicionales y el total canónico. Añade el factor vigente del producto y total estimado visual: 0 coches + 12 latas, factor 30 → 360 panes. El factor se publica solo en opciones Pan. Cambio de producto, cantidades y reset actualizan la ayuda.

La ayuda no se envía como valor calculado en el POST. La Action sigue derivando cantidad y snapshot desde el producto bloqueado. Factores desconocidos muestran «Sin factor registrado»/«Estimación no disponible»; cantidades inválidas y desbordamiento INT no producen una estimación. Productos matemáticos fuera de la precisión entera segura de JS muestran una advertencia en vez de un entero redondeado.

Las tarjetas de lotes consultados añaden snapshot/estimación usando exclusivamente el detalle histórico, sin recurrir al factor actual.

## Producción Torta

Etiqueta «Cantidad», ayuda de cantidad producida y resultado directo: 8 → «8 tortas». Campos específicos de Pan ocultos/deshabilitados. Mantiene forma, foto opcional, fecha/turno, participantes y Action/Request existentes. Guarda `detalle_torta.cantidad = 8`, sin multiplicación.

## Producción Bocadito

Etiqueta «Cantidad» y resultado directo: 150 → «150 unidades». Sin campos activos de Pan ni conversiones. Guarda `detalle_bocadito.cantidad = 150`. La equivalencia de unidad asociada al producto no interviene.

## Historial

No fue necesario modificar la vista, Query ni controller: ya representan Pan como latas y calculan panes solo con el snapshot; las otras familias muestran unidades directas. Nuevos tests HTTP comprueban 8/150 y ausencia de latas/factores/estimaciones en el historial filtrado de Torta/Bocadito.

Se conserva el test integrado previo: crear Pan con 30, registrar 12 latas → 360, editar producto a 32, comprobar todos los atributos del lote anterior intactos, registrar lote nuevo → 384 y snapshots [30, 32]. También se mantienen regresiones de factores históricos desconocidos.

## Archivos modificados

Cambios propios de esta tarea:

| Archivo | Motivo |
|---|---|
| `resources/views/productos/form.blade.php` | Forma de registro, ayuda de unidad, diagnóstico de catálogo y ocultación inicial del factor fuera de Pan. |
| `resources/js/productos.js` | Alternar bloque de cantidad directa sin recarga, manteniendo el comportamiento previo del factor. |
| `resources/views/produccion/index.blade.php` | Factor/estimación solo Pan, etiqueta Cantidad y resultado directo; tarjetas Pan con snapshot. |
| `resources/js/produccion.js` | Preview por familia, eventos de producto/cantidad, límites y precisión segura. |
| `tests/Feature/ProductosTest.php` | 13 casos nuevos de catálogo, tres estados iniciales e integración de cantidades directas. Conserva las pruebas previas. |
| `tests/JavaScript/ProductosTest.mjs` | Adaptar doble DOM y comprobar visibilidad del registro directo. Archivo ya presente sin seguimiento al iniciar. |
| `tests/JavaScript/ProduccionCantidadTest.mjs` | Cuatro casos nuevos de estimación, cantidades directas y límites; adaptar DOM. |
| `docs/PRODUCCION_UNIDADES_POR_FAMILIA.md` | Informe expresamente solicitado; no es copia de notas del Vault. |

GuardarProductoRequest ya estaba modificado al iniciar por la mejora anterior; no se cambió en esta tarea. No hubo cambios propios en modelos, Actions, controllers, rutas, esquema, CSS o sidebar.

Documentación original del Vault actualizada con autorización de escritura en PRODUCCION/Logica de negocio.md, PRODUCCION/Pantallas.md, LARAVEL/Blade.md, DECISIONES/Decisiones Laravel.md e INICIO/Estado de proyecto.md tras lectura íntegra. Las entradas nuevas conservan la historia; el nombre real de la nota de decisiones es `.md`, sin nota equivalente `.md.md` en la estructura inspeccionada.

`mejora_asignado.md` ya estaba eliminado al iniciar y no se restauró. Su actualización queda **PENDIENTE de decidir su recuperación**, conservando el estado del usuario. No se crearon carpetas ni duplicados del Vault en el repositorio.

## Base de datos

**No se necesita, creó ni ejecutó una migración nueva.** No se aplicaron seeders/backfills ni escrituras en datos reales. Las migraciones existentes sí se ejecutaron en bases ficticias del runner para verificar el esquema real MariaDB.

`unidad_medida_id` sigue siendo obligatorio en Producto y validado contra el catálogo, independientemente de la familia. En Torta/Bocadito es una relación de catálogo: no se copia a sus detalles ni participa en cantidades. No existe unidad_medida_id ni factor Pan en esos dos detalles. `equivalencia_unidades` no es un factor de conversión de estos flujos.

## Validaciones

Se conservan los FormRequests y Actions actuales: Pan exige factor en CRUD; no Pan rechaza factor configurado; IDs reales, producto activo de su familia y cantidades positivas con capacidad INT. La whitelist evita persistir factores/unidad manipulados en detalles no Pan. Sin Services/DTO/helpers nuevos ni generalización de factores.

La identificación informativa de «Unidad» normaliza espacios exteriores y mayúsculas, exige una única coincidencia y equivalencia 1; **solo controla el aviso**, no escoge IDs, no modifica reglas de guardado y no garantiza unicidad SQL. Con ausencia/duplicidad/equivalencia incompatible se advierte. Aun con coincidencia única se conserva selección manual: la identidad canónica requiere decisión separada.

## Tests realizados

| Comando | Qué comprueba / expectativa |
|---|---|
| `php tests/run-mariadb.php --filter='Productos\|ProduccionPan\|PanUnits\|ProduccionTortaBocadito\|HistorialProduccion\|RegistrarProduccionPan' --compact --do-not-cache-result` | Regresión relevante durante implementación: conservar CRUD, escritura, snapshot e historial. |
| `php tests/run-mariadb.php --compact --do-not-cache-result` | Suite PHP completa, incluyendo concurrencia, con cero fallidas/omitidas esperado. |
| `node --test tests/JavaScript/*.mjs` y ejecución directa de los tres archivos | Eventos de scripts reales con dobles DOM; conteos individuales mediante ejecución directa. |
| `npm run build` | Compilar assets reales con Vite. |
| `vendor/bin/pint --test tests/Feature/ProductosTest.php` | Formato del PHP afectado. |
| `git diff --check` y revisión de diff | Cambios acotados y whitespace válido. |

Cobertura A–K: A factor Pan obligatorio; B/C 12 latas y 360; D/E persistencia exacta 8/150; F/G factores ajenos ignorados en producción; H unidades resueltas desde objetos/IDs variables, no constantes; I historial correcto por familia; J snapshots intactos; K CRUD, restricciones de historia y concurrencia en suite completa.

Los tests nuevos usan equivalencia 30, factor heredado 99 y valores manipulados para demostrar independencia de cantidades directas. Esos datos existen exclusivamente en fixtures desechables. También prueban catálogo ausente, único, duplicado e incompatible sin creación/asignación automática; y HTML inicial de Nuevo Producto/edición por familia.

## Resultado de pruebas

- Primera verificación relevante durante implementación: **442 aprobadas, 3.730 aserciones**.
- Relevante ampliada: **452 aprobadas, 3.858 aserciones**, antes de los tres casos finales de Nuevo producto.
- Suite PHP completa: **608 aprobadas, 5.608 aserciones, cero fallidas/omitidas**.
- JS final: **29 casos aprobados** (16 Producción, 6 Productos, 7 sidebar), cero fallidos.
- Build y Pint afectado aprobados. Aviso opcional `fontaine` preexistente, sin impedir compilación. Diff sin errores en la revisión realizada.

Aislamiento: MariaDB desechable sin red bajo `/tmp/sprint4-*`, dos bases ficticias con datadir/base verificados por el helper y detención automática. Suite completa final en `/tmp/sprint4-f8a2dd30d174`. HTTP con sesiones array/withoutVite; assets compilados por separado. El primer intento de runner fue bloqueado por sandbox/socket y se repitió autorizado; no se cuenta como prueba aprobada.

No se ejecutó navegador automatizado en esta tarea: HTTP/DOM y dobles JS comprueban datos, atributos y eventos, no geometría, validación nativa ni apariencia en dispositivos reales. Sin certificación de sesiones database ni despliegue.

## Decisiones técnicas

1. Reutilizar arquitectura ya correcta; cambios limitados a presentación y pruebas.
2. Conservar selector/FK requerida mientras falte identidad canónica segura; aclarar que no convierte cantidades.
3. No crear «Unidad», migraciones visuales, factores nuevos, IDs constantes ni backfills.
4. Mostrar factor actual solo como ayuda de nuevo lote Pan; usar snapshot en tarjetas registradas e historial.
5. Mantener entrada coches/latas existente y capacidad 18; no alterar unidades/cantidades históricas.
6. Conservar paleta, tipografía, espaciados, bordes, responsive y sidebar mediante clases existentes.

## Riesgos encontrados

- **PENDIENTE — Catálogo:** solo «Lata». La selección sigue siendo manual; el backend conserva `exists` y todavía permite asociar ese registro a Torta/Bocadito. El aviso mejora claridad, pero no sanea ni define el catálogo. No se declara resuelta su identidad canónica.
- **PENDIENTE — Unicidad:** el nombre de unidad no tiene UNIQUE ni código estable; una búsqueda por nombre/first() no basta para automatizar sin ambigüedad.
- No hay casos reales no Pan que reparar: 0 productos/0 detalles. Los conteos no acreditan otros entornos ni integridad histórica total.
- El preview depende de JS y puede quedar desactualizado si otro usuario cambia el producto; la Action copia la configuración vigente bajo lock al guardar.
- Sin JS se conserva validación backend y el estado inicial Blade. Cambiar categoría sin JS requiere enviar/reabrir para actualizar bloques; no se acredita transición dinámica en ese modo.
- Nombres históricos siguen la relación Producto, sin snapshot de nombre; comportamiento previo conservado.
- Eliminaciones previas de documentación y mejora_asignado.md conservadas.

## Pendientes

1. **PENDIENTE:** decidir y habilitar el catálogo canónico de «Unidad», revisando duplicados/equivalencia y una identidad estable. Propuesta mínima segura: acordar esa identidad y su garantía de unicidad; después implementar resolución inequívoca para nuevos Productos no Pan y sustituir selector por texto. No reasignar productos con historia ni migrar datos sin aprobación específica. No se ejecutó esa propuesta.
2. **PENDIENTE:** aceptación visual/operativa en escritorio/móvil y sesiones reales.
3. **PENDIENTE:** decidir recuperación de mejora_asignado.md antes de actualizarlo.
Las cinco notas originales del Vault se actualizaron con escalamiento autorizado y se verificaron las escrituras conservando su historia. **No queda autorización de herramientas pendiente.**

## Pruebas manuales recomendadas

1. Nuevo Producto → Pan: configurar 30; comprobar selector y factor obligatorio. Probar vacío/cero/decimal: rechazo sin persistencia.
2. Alternar Pan → Torta → Bocadito → Pan: sin recarga; solo Pan muestra/requiere factor y conserva temporalmente el valor escrito.
3. Torta/Bocadito: ver «Forma de registro — Unidad / cantidad directa», selector explícito y advertencia porque el catálogo local carece de «Unidad». Revisar con el responsable antes de crear productos reales; no usar «Lata» como sustituto conceptual.
4. En entorno de pruebas con catálogo aprobado, producir Pan: 0 coches/12 latas, factor 30 → 360; registrar con hora/equipo válidos.
5. En ese entorno, Torta cantidad 8 → 8 tortas; Bocadito cantidad 150 → 150 unidades. No deben verse controles de latas/factor/total de panes.
6. Consultar las tres sesiones e Historial: Pan con snapshot/estimación; Torta 8 unidades; Bocadito 150 unidades, sin información Pan.
7. Cambiar factor del Pan a 32 y producir otro lote: previo 360, nuevo 384. Categoría/unidad protegidas cuando hay historia.
8. Revisar ancho móvil/escritorio, tabulación, reset, mensajes de error y sidebar con assets compilados. Si se usa build, comprobar ausencia de `public/hot` residual antes de la prueba.
