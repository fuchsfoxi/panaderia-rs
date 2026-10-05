# Sprint 5 — Auditoría de entrada de Producción

Fecha: 2026-10-05. Estado: **ANÁLISIS COMPROBADO EN CÓDIGO; IMPLEMENTACIÓN PENDIENTE**.

## 1. Alcance y conclusión

Auditoría exclusiva del recorrido Ingresar Producción → POST /produccion → ProduccionController::store(), sus modelos y esquema. Se revisaron fuentes actuales, sin ejecutar Laravel, requests HTTP, migraciones, seeders ni consultas a BD. La comprobación de campos se realizó leyendo el HTML de Blade con un parser estático, sin renderizarlo ni ejecutar un navegador.

**HECHO:** el formulario tiene destino POST, multipart/form-data y @csrf, pero store() está vacío. index() solo devuelve la vista y no carga catálogos. El selector de producto contiene únicamente una opción vacía. El flujo todavía no permite elegir un producto real ni persistir producción.

**HECHO:** hoy existe un formulario plano para un solo producto. Para varias tortas o detalles de Bocadito, y para asociar participantes/observaciones por detalle, se necesita un contrato de detalles repetibles; cambiar solo store() no basta.

**DECISIONES CONFIRMADAS PARA ESTA AUDITORÍA:** Pan usa turno de cabecera, copia legacy de turno, unidad derivada del producto, cantidad en latas enteras, 18 latas por coche y snapshot del factor vigente. Torta y Bocadito no usan turno. Cada torta física es un detalle propio; Bocadito admite varios detalles con cantidad entera en unidades. No se reabren esas decisiones.

Las tres migraciones de Sprint 4 y los seeders de Categoria/Turno se consideran aplicados según la evidencia aportada en esta conversación; no se revalidan sus ejecuciones aquí. No se conoce por esta inspección la disponibilidad actual de productos, unidades, empleados, roles de producción o factores operativos en la BD.

## 2. Recorrido y transporte actuales

- resources/views/produccion/index.blade.php carga resources/js/produccion.js y resources/css/produccion.css mediante @vite.
- x-sidebar carga adicionalmente resources/js/app.js: controla restauración BFCache, no transforma el payload de producción. El formulario de logout es independiente.
- routes/web.php declara GET /produccion → index(), nombre produccion.index, y POST /produccion → store(), nombre produccion.store; ambas rutas están bajo auth y el grupo web. No hacen falta rutas nuevas para guardar Pan.
- No hay submit handler, fetch, axios ni FormData en produccion.js: el navegador envía el formulario nativamente.
- No hay FormRequest de producción ni validaciones implementadas en store(). @csrf protege el envío, pero no valida referencias, cantidades o familia.
- Todos los controles de datos inspeccionados carecen de required y disabled. Los number tienen min="0"; no permiten acreditar obligatoriedad ni la restricción SQL > 0. No hay validación de datos de producción en servidor.

### Campos HTTP existentes

| Control actual | name enviado | Valor/origen | Visibilidad con JavaScript |
|---|---|---|---|
| Token de formulario | _token | Generado por @csrf; no se registra su contenido | Oculto |
| Familia seleccionada | categoria | Hidden inicial pan; JS lo cambia a torta/bocadito | Botones visibles, valor oculto |
| Fecha | fecha | Entrada date, sin default explícito | Todas las familias |
| Turno | turno_id | Opciones fijas 1 Mañana, 2 Noche | Pan |
| Producto | producto_id | Solo opción value=""; no hay productos cargados | Todas |
| Coches de Pan | cantidad_coches | Entrada number, min 0 | Pan |
| Unidades de Bocadito | cantidad_unidades | Entrada number, min 0 | Bocadito |
| Observación | observaciones | Texto, plural en HTTP | Todas |
| Forma de Torta | forma | Hidden vacío; JS copia circular/rectangular | Botones de Torta |
| Foto de Torta | foto | Un solo input file, accept="image/*" | Label de Torta; input siempre oculto |
| Selector provisional de empleado | Sin name | Nombres hardcodeados | Popover común |
| Selector provisional de rol | Sin name | Maestro/Ayudante hardcodeados | Popover común |
| Participante agregado | empleados[i][nombre] | Hidden creado por JS | Tag visible |
| Rol del participante agregado | empleados[i][rol] | Hidden creado por JS | Texto del tag |

Los dos selects del popover no se envían por sí mismos. Solo pulsar Agregar genera los hidden empleados[i][nombre/rol]. Si no se agrega nadie, no hay arreglo empleados. Quitar un tag elimina sus dos inputs; los índices pueden quedar discontinuos.

### Lo que realmente puede llegar al POST

Cambiar familia solo modifica style.display y categoria; no deshabilita, vacía ni elimina los controles de las otras familias. Por ello **ocultar no equivale a excluir del envío**. Un campo oculto puede conservar valores anteriores, incluida una foto seleccionada para Torta antes de volver a Pan.

Representación textual del formulario actual (no es una captura de una petición real):

```text
POST /produccion
Content-Type: multipart/form-data; boundary=<generado por navegador>

_token=<valor CSRF generado por Laravel>
categoria=pan|torta|bocadito
fecha=<fecha elegida o vacío>
turno_id=1|2
producto_id=
cantidad_coches=<valor o vacío>
cantidad_unidades=<valor o vacío>
observaciones=<texto o vacío>
forma=<vacío|circular|rectangular>
foto=<parte de archivo si se seleccionó>
empleados[0][nombre]=<nombre del selector, si se agregó>
empleados[0][rol]=<rol textual, si se agregó>
```

Sin archivo seleccionado no existe una foto utilizable como UploadedFile, aunque el control siga formando parte del formulario. Los campos vacíos pueden normalizarse a null al procesarse; no son datos válidos por el mero hecho de existir el name.

### Elementos visuales o hardcodeados

**HECHOS:**

- Botones Pan/Torta/Bocadito: type="button", data-categoria, sin name; JS escribe categoria. Estos slugs son discriminadores de familia, no IDs de Categoria.
- Formas Circular/Rectang.: botones type="button", sin name; solo el hidden forma transporta la elección. Sin JS no se escribe la selección.
- Los IDs numéricos hardcodeados de catálogos son turno_id=1/2. No hay IDs reales de Producto, Categoria, Empleado o RolProduccion suministrados por la vista. Los id de HTML son identificadores del DOM, no claves de BD.
- Empleados fijos: Carlos M., Ana R., Rosa P., Luis F., Marta S. Roles fijos del selector: Maestro y Ayudante. No se consulta su identidad ni existencia en BD.
- Encabezado fijo «Detalles de Producción de Pan» y badge «Activo»: JS no adapta ese título ni acredita activo de Producto.
- Tarjetas «Registrado recientemente», productos, fechas, cantidades, participantes y «Turno Único» de Torta/Bocadito son HTML estático fuera del formulario; no se envían ni prueban persistencia. «Turno Único» contradice el contrato funcional sin turno de esas familias.
- Cancelar llama form.reset(), vuelve a Pan y oculta campos, pero no elimina tags/inputs dinámicos ni reinicia el contador de empleados. Un reset no elimina nodos: los participantes agregados siguen presentes. Tampoco limpia las clases visuales de selección de forma. Es un riesgo de información residual que deberá corregirse al conectar el flujo.
- CSS oculta inicialmente .campo-condicional; JS muestra los de Pan al cargar. Sin JS no hay selección funcional de familia/forma/participantes ni campos condicionales visibles.

## 3. Qué exige el esquema y qué debe obtener store()

### Cabecera común

| Campo de produccion | Esquema | Origen recomendado |
|---|---|---|
| id | PK autogenerada | BD |
| fecha | DATE NOT NULL | HTTP validado |
| registrado_por_usuario_id | FK NOT NULL a usuarios_sistema | Usuario autenticado; nunca un ID enviado por el cliente |
| categoria_id | FK nullable a categorias | Resolver la familia admitida en catálogo y comprobar todos los productos |
| turno_id | FK nullable a turnos | HTTP validado solo para Pan; null para Torta/Bocadito |

**RECOMENDACIÓN:** exigir una familia coherente para toda producción nueva aunque categoria_id sea nullable por compatibilidad. Resolver pan → Pan, torta → Torta, bocadito → Bocadito por nombre de catálogo, sin asumir IDs; detectar catálogo ausente/ambiguo. Los nombres de Categoria/Turno no tienen UNIQUE en estas migraciones. Comparar contra la categoría real de cada producto: las FK no impiden mezclar familias o escribir un producto de Torta en detalle_pan.

### Detalles y participantes

| Tabla | Campos obligatorios para insertar | Opcionales/nullable | Relaciones de escritura |
|---|---|---|---|
| detalle_pan | produccion_id, producto_id, unidad_medida_id, turno_id, cantidad INTEGER > 0 | observacion TEXT, panes_por_lata_usado INTEGER | Produccion::detallesPan(); DetallePan::empleados() |
| detalle_torta | produccion_id, producto_id, forma VARCHAR(30) | foto VARCHAR(255), observacion TEXT | Produccion::detallesTorta(); DetalleTorta::empleados() |
| detalle_bocadito | produccion_id, producto_id, cantidad INTEGER > 0 | observacion TEXT | Produccion::detallesBocadito(); DetalleBocadito::empleados() |

Las tres relaciones empleados() usan belongsToMany, custom Pivot y withPivot('rol_produccion_id'). Cada tabla detalle_*_empleado necesita detalle_*_id, empleado_id y rol_produccion_id, todas FK. Su PK compuesta es (detalle_*_id, empleado_id): un empleado no puede aparecer dos veces, incluso con distinto rol, dentro del mismo detalle. Puede participar en detalles diferentes y varios empleados pueden compartir el mismo rol.

**HECHO:** las BD exigen IDs, no nombres. empleados.nombre_empleados y roles_produccion.nombre_roles_produccion no son UNIQUE en las migraciones revisadas. Buscar automáticamente los nombres abreviados del formulario no garantiza identidad. RolProduccion es rol operativo en el detalle; no es cargo_id ni rol de acceso del usuario. La cuenta registradora tampoco implica participación del empleado asociado a esa cuenta.

**HECHO:** no hay observación ni participantes en la cabecera. Los tres detalles tienen observacion singular, pero el formulario usa observaciones plural. Eloquent no transforma ese nombre automáticamente. No se deben pasar request()->all() ni archivos directamente a create(): hacen falta whitelist y mapeo por familia.

## 4. PAN: diagnóstico de los diez puntos

| Punto | Estado actual y necesidad |
|---|---|
| 1. Visibles | Familia, fecha, turno, producto vacío, cantidad en coches, participantes comunes y observaciones. |
| 2. names reales | categoria=pan, fecha, turno_id, producto_id, cantidad_coches, observaciones; empleados[i][nombre/rol] solo después de Agregar. También pueden enviarse cantidad_unidades, forma y foto ocultos. |
| 3. Solo visual/JS | Botones de familia, tags y botones +/×; los selects de participante no tienen name. No hay cálculo visual ni HTTP de total_latas. |
| 4. Hardcodeados | Label coches, texto de cabecera, nombres/roles y tarjetas estáticas; Mañana/Noche y su asociación a 1/2. |
| 5. IDs fijos | turno_id=1/2. No hay categoría_id o producto real; nombres de empleados/roles no son IDs. |
| 6. Necesita BD | Cabecera común; cada detalle con producto, FK de producción, unidad obligatoria, turno obligatorio y cantidad entera > 0; observacion y snapshot nullable. |
| 7. Derivar servidor | Registrador de auth; categoria_id del catálogo; produccion_id recién creado; turno legacy desde cabecera; unidad desde Producto; cantidad = coches × 18 + latas_adicionales; panes_por_lata_usado desde configuración del producto. |
| 8. Faltan formulario | Productos reales, latas adicionales, estructura de varios detalles, IDs de empleado/rol y asociación por detalle, errores de validación/éxito y recuperación de entrada. No faltan inputs de registrador/unidad/snapshot: deben derivarse. |
| 9. Escribir | produccion → detallesPan → empleados con rol_produccion_id en detalle_pan_empleado, dentro de una transacción. Producto/Categoria/Turno/Empleado/RolProduccion se consultan; no se crean como efecto lateral del registro. |
| 10. Incompatibilidades | Coches no son cantidad de BD; faltan latas adicionales y FK de unidad/turno derivadas; producto_id vacío; participantes textuales; observaciones plural; datos residuales de otras familias; min 0 frente a CHECK > 0. |

**DECISIÓN CONFIRMADA:** produccion.turno_id es la única decisión de turno. Copiarla a detalle_pan.turno_id es compatibilidad temporal, no otro selector. No persistir coches o latas adicionales en nuevas columnas; almacenar solo total_latas en cantidad.

**DECISIÓN CONFIRMADA:** productos.panes_por_lata es la configuración vigente y detalle_pan.panes_por_lata_usado el snapshot al crear el registro. No hay hook en estos modelos ni escritor que copie el valor hoy. No usar ese factor para convertir coches a latas: esa conversión usa 18; el factor sirve para calcular panes a partir de latas.

**RECOMENDACIÓN:** validar coches y latas_adicionales como enteros no negativos y total calculado > 0, dentro de INTEGER firmado de BD (hasta 2147483647). Ese límite técnico no sustituye el máximo operativo pendiente. Una entrada canónica puede limitar latas adicionales a 0–17; es una propuesta de validación/UI, no una restricción SQL ni un requisito adicional confirmado en esta auditoría. Si se aceptan más latas adicionales, la fórmula sigue siendo válida y debe definirse su normalización.

**PENDIENTE DE NEGOCIO:** valores desconocidos de panes_por_lata, máximo operativo y significado definitivo/histórico de unidad_medida_id. **Propuesta transitoria:** si el factor es desconocido, conservar snapshot null y no mostrar un total de panes inventado; decidir esta política antes del guardado. Copiar una unidad válida del producto es compatible con el esquema, pero no resuelve por sí solo su semántica operativa.

## 5. TORTA: diagnóstico de los diez puntos

| Punto | Estado actual y necesidad |
|---|---|
| 1. Visibles | Familia, fecha, producto vacío, participantes/observaciones comunes, botones de forma y label para foto. No hay cantidad de tortas ni repetidor de piezas. |
| 2. names reales | categoria=torta, fecha, producto_id, observaciones, forma, foto; empleados[i][nombre/rol]. Turno y ambas cantidades permanecen en el formulario y pueden enviarse ocultos. |
| 3. Solo visual/JS | Botones de forma y familia sin name; tags. La selección de forma se copia al hidden por JS; la etiqueta «Obligatorio» no valida foto. |
| 4. Hardcodeados | circular/rectangular, nombres/roles, «foto obligatoria», título todavía de Pan y tarjeta «Turno Único». Las formas son valores de UI, no IDs. |
| 5. IDs fijos | No hay IDs reales de sus catálogos. El turno 1/2 sigue en un control oculto aunque Torta no deba usarlo. |
| 6. Necesita BD | Cabecera con familia Torta y turno null; un DetalleTorta por pieza con producto_id y forma NOT NULL; foto ruta/string nullable y observacion nullable. No existe cantidad, turno_id ni unidad_medida_id en detalle_torta. |
| 7. Derivar servidor | Registrador, categoría y producción; turno de cabecera null; referencia de foto generada por almacenamiento del archivo validado. Nunca guardar un objeto de archivo o una ruta arbitraria del cliente en la columna foto. |
| 8. Faltan formulario | Productos reales; repetidor de tortas; forma/foto/observacion/participantes independientes por pieza; IDs de participantes/roles; errores y manejo de archivo tras rechazo. No añadir cantidad a la BD para contar tortas: contar filas. |
| 9. Escribir | produccion → detallesTorta (una fila por torta física) → detalle_torta_empleado con rol operativo por participante. Los archivos requieren compensación si falla la persistencia; la transacción SQL no revierte el filesystem. |
| 10. Incompatibilidades | Un solo archivo y forma no describen varias tortas; observación/participantes son comunes; forma puede estar vacía; foto nullable en BD pese a label obligatorio; turno residual; ausencia de identidad real del producto. |

### Preparación actual de la foto

**HECHO:** el form ya usa multipart/form-data. El input foto no tiene multiple ni required; accept="image/*" filtra el selector del navegador, no valida el archivo en servidor. El label for="foto" permite abrirlo aunque el input tenga display:none. No existe preview, compresión, lectura FileReader, upload asíncrono, almacenamiento, persistencia de ruta ni limpieza implementada. Cambiar de categoría no elimina el archivo seleccionado.

**HECHO:** foto VARCHAR(255) nullable almacena una referencia, no los bytes. forma VARCHAR(30) es obligatoria pero no tiene enum/CHECK que limite las opciones a circular/rectangular. La whitelist de formas y la validación HTTP están pendientes del escritor.

**PENDIENTE A PRECISAR PARA TORTA:** obligatoriedad funcional de foto y sus excepciones, formatos/tamaño/almacenamiento. No deducir un límite de archivo ni obligatoriedad de servidor únicamente del texto visual. El payload propuesto admite un archivo por pieza para poder aplicar la política que se acuerde.

## 6. BOCADITO: diagnóstico de los diez puntos

| Punto | Estado actual y necesidad |
|---|---|
| 1. Visibles | Familia, fecha, producto vacío, cantidad en unidades, participantes y observaciones comunes. |
| 2. names reales | categoria=bocadito, fecha, producto_id, cantidad_unidades, observaciones; empleados[i][nombre/rol]. Turno, coches, forma y foto pueden enviarse ocultos. |
| 3. Solo visual/JS | Botones de familia y tags; no hay repetidor de productos ni observaciones/participantes por fila. |
| 4. Hardcodeados | Nombres/roles, label unidades, título de Pan y tarjeta «Turno Único». La cantidad 150 de la tarjeta no es un campo enviado. |
| 5. IDs fijos | El turno 1/2 permanece oculto. Ningún producto/empleado/rol está identificado realmente por ID. |
| 6. Necesita BD | Cabecera con categoría Bocadito y turno null; detalle con producto_id, produccion_id y cantidad INTEGER > 0; observacion nullable. No existen turno/unidad/foto/forma en este detalle. |
| 7. Derivar servidor | Registrador, categoría, producción y turno de cabecera null; mapear cantidad_unidades a cantidad mientras se use el formulario plano actual. No hay conversión de unidades a latas. |
| 8. Faltan formulario | Productos reales, varios detalles, cantidad/observación/participantes por fila e IDs de participante/rol; errores y resultado recuperable. |
| 9. Escribir | produccion → detallesBocadito → detalle_bocadito_empleado con rol_produccion_id, transaccionalmente. |
| 10. Incompatibilidades | Una sola cantidad/producto no representa varios detalles; names distintos de BD; cantidades vacías/cero; relaciones textuales y residuos de campos de Pan/Torta. |

## 7. Contrato que necesita store() y límites de la primera fase

**PROPUESTA, todavía no implementada:** un envío representa una producción de una sola familia, con fecha y arreglo detalles no vacío. Turno solo en la cabecera de Pan; producto, cantidades/formas/fotos/observaciones y participantes por detalle. Las claves de entrada deben validarse antes de escribir.

1. Resolver la familia admitida y obtener su categoría real del catálogo. Comprobar existencia/categoría de cada producto; no confiar en labels, familia enviada o valores de HTML como prueba de integridad.
2. Validar la fecha y el turno de Pan contra el catálogo; Torta/Bocadito deben persistir turno null. Recomendación para el contrato nuevo: rechazar campos propios de otra familia, en vez de perpetuar residuos ocultos. Para la primera fase solo Pan, rechazar explícitamente torta/bocadito y comunicar que aún no están implementados.
3. Validar arreglos de detalles y participantes. Cada empleado y rol debe existir; rechazar duplicados de empleado dentro del mismo detalle antes del attach para respetar la PK. La misma persona puede figurar en diferentes detalles.
4. Construir solo los atributos permitidos, derivando usuario, categoría, producción, unidad, turno legacy, cantidad de Pan y snapshot. Las claves derivadas no deben aceptarse como decisiones del cliente.
5. Escribir cabecera, detalles y pivots en una transacción; devolver errores visibles y redirección/confirmación de éxito, sin declarar las tarjetas estáticas como historial real.
6. Para Torta, validar/almacenar archivos por detalle y compensar archivos si falla el guardado. Ese trabajo no es necesario para la primera implementación exclusiva de Pan.

**PENDIENTES, no restricciones ya implementadas:** mínimo de participantes por detalle, roles permitidos por familia, política para productos inactivos/temporadas, factor desconocido, máximos operativos, fotos y unicidad/reintentos de sesiones. El esquema no obliga a un participante ni impone UNIQUE por fecha/familia/turno. Recomendación: cargar productos activos para el formulario y comprobar en servidor la política acordada; no presentar activo=true como enforcement actual.

## 8. Archivos y evidencia revisados

### Entrada, navegación y verificación estática

- resources/views/produccion/index.blade.php: form y names (25–129), tarjetas estáticas (139–184).
- resources/js/produccion.js: visibilidad (8–32), forma (34–46), cancelar (48–60), participantes (62–109).
- resources/css/produccion.css: .campo-condicional y foto-upload.
- resources/views/components/sidebar.blade.php; resources/js/app.js; vite.config.js: carga adicional y separación del form de logout.
- routes/web.php; bootstrap/app.php; app/Http/Controllers/ProduccionController.php; app/Providers/AppServiceProvider.php.
- tests/Feature/NavigationTest.php: comprueba action/method/enctype/CSRF del form, sin probar store funcional.
- tests/Feature/PanUnitsTest.php: fixtures explícitas y snapshot/cantidad; no existe copia automática HTTP.

### Modelos

app/Models/Produccion.php, DetallePan.php, DetalleTorta.php, DetalleBocadito.php, Producto.php, Categoria.php, Turno.php, UnidadMedida.php, Empleado.php, RolProduccion.php, DetallePanEmpleado.php, DetalleTortaEmpleado.php y DetalleBocaditoEmpleado.php.

### Migraciones leídas

Todas bajo database/migrations/:

- 2026_09_14_202617_create_turnos_table.php
- 2026_09_14_202618_create_categorias_table.php
- 2026_09_14_202619_create_unidades_medida_table.php
- 2026_09_14_202620_create_roles_produccion_table.php
- 2026_09_14_202621_create_empleados_table.php
- 2026_09_14_202623_create_productos_table.php
- 2026_09_14_202624_create_produccion_table.php
- 2026_09_14_202625_create_detalle_pan_table.php
- 2026_09_14_202626_create_detalle_pan_empleado_table.php
- 2026_09_14_202627_create_detalle_torta_table.php
- 2026_09_14_202628_create_detalle_torta_empleado_table.php
- 2026_09_14_202629_create_detalle_bocadito_table.php
- 2026_09_14_202630_create_detalle_bocadito_empleado_table.php
- 2026_10_02_162600_add_observacion_to_production_details.php
- 2026_10_02_180000_add_categoria_and_turno_to_produccion.php
- 2026_10_02_200000_add_panes_por_lata_to_productos_and_detalle_pan.php

CategoriaSeeder.php y TurnoSeeder.php se leyeron para comprobar resolución por nombre y ausencia de IDs asumidos; no se ejecutaron.

## 9. Payload HTTP recomendado

**PROPUESTA CONCRETA, no contrato ya implementado.** Mantener POST /produccion, multipart/form-data y CSRF. Los bloques JSON siguientes representan la estructura lógica de los campos, no un envío JSON con binarios. En HTML/FormData, usar names con corchetes; por ejemplo detalles[0][producto_id] y detalles[0][participantes][0][empleado_id]. Laravel recibirá arreglos anidados y los archivos mediante la estructura de files.

Los textos ID_REAL_* son marcadores de IDs obtenidos de los catálogos; se sustituyen por enteros reales al construir el envío. No son valores literales aceptables por el servidor ni IDs hardcodeados. Cada envío incluye además _token generado por Laravel, cuyo contenido no se reproduce.

No enviar registrado_por_usuario_id, produccion_id, categoria_id, unidad_medida_id, turno legacy del detalle, panes_por_lata_usado ni cantidad calculada de Pan. Tampoco incluir turno_id en Torta/Bocadito.

### Pan

```json
{
  "categoria": "pan",
  "fecha": "2026-10-05",
  "turno_id": "ID_REAL_TURNO",
  "detalles": [
    {
      "producto_id": "ID_REAL_PRODUCTO_PAN",
      "coches": 1,
      "latas_adicionales": 5,
      "observacion": "Nota de este detalle",
      "participantes": [
        {
          "empleado_id": "ID_REAL_EMPLEADO",
          "rol_produccion_id": "ID_REAL_ROL_PRODUCCION"
        }
      ]
    }
  ]
}
```

Names: categoria, fecha, turno_id, detalles[i][producto_id], detalles[i][coches], detalles[i][latas_adicionales], detalles[i][observacion], detalles[i][participantes][j][empleado_id/rol_produccion_id]. Más productos se agregan como detalles[1], detalles[2], etc.

Resultado esperado del ejemplo: detalle_pan.cantidad = 1 × 18 + 5 = 23 latas; turno copiado de cabecera, unidad y factor obtenidos del producto. Un cambio posterior del factor del producto no modifica el snapshot ya guardado. Propuesta: observacion opcional y participantes=[] admitidos mientras el negocio no establezca otro mínimo; esta política deberá confirmarse.

### Torta

```json
{
  "categoria": "torta",
  "fecha": "2026-10-05",
  "detalles": [
    {
      "producto_id": "ID_REAL_PRODUCTO_TORTA",
      "forma": "circular",
      "foto": "ARCHIVO_MULTIPART_PIEZA_0",
      "observacion": "Nota de la primera torta",
      "participantes": [
        {
          "empleado_id": "ID_REAL_EMPLEADO_A",
          "rol_produccion_id": "ID_REAL_ROL_PRODUCCION"
        }
      ]
    },
    {
      "producto_id": "ID_REAL_PRODUCTO_TORTA",
      "forma": "rectangular",
      "foto": "ARCHIVO_MULTIPART_PIEZA_1",
      "observacion": "Nota de la segunda torta",
      "participantes": [
        {
          "empleado_id": "ID_REAL_EMPLEADO_B",
          "rol_produccion_id": "ID_REAL_ROL_PRODUCCION"
        }
      ]
    }
  ]
}
```

foto representa una parte de archivo real: enviar detalles[0][foto] y detalles[1][foto] como input file/FormData con bytes, no como strings, nombres de archivo o rutas. Names restantes: detalles[i][producto_id], detalles[i][forma], detalles[i][observacion] y participantes anidados. Dos elementos crean dos DetalleTorta, incluso si comparten producto. No enviar cantidad, unidad ni turno. Store guardará en foto la referencia generada tras validar/almacenar cada archivo, según la política aprobada.

### Bocadito

```json
{
  "categoria": "bocadito",
  "fecha": "2026-10-05",
  "detalles": [
    {
      "producto_id": "ID_REAL_PRODUCTO_BOCADITO_A",
      "cantidad": 150,
      "observacion": "Nota del primer detalle",
      "participantes": [
        {
          "empleado_id": "ID_REAL_EMPLEADO",
          "rol_produccion_id": "ID_REAL_ROL_PRODUCCION"
        }
      ]
    },
    {
      "producto_id": "ID_REAL_PRODUCTO_BOCADITO_B",
      "cantidad": 80,
      "observacion": "Nota del segundo detalle",
      "participantes": []
    }
  ]
}
```

Names: categoria, fecha, detalles[i][producto_id], detalles[i][cantidad], detalles[i][observacion] y participantes anidados. cantidad es entero positivo de unidades, sin conversión a latas. No enviar turno, coches, forma, foto ni unidad del detalle. Un arreglo de participantes vacío necesita tratamiento explícito como lista vacía si se usa un formulario HTML, donde la ausencia de sus inputs no serializa automáticamente [].

## 10. Archivos exactos para la primera implementación de Pan

**PLAN PROPUESTO; ninguno se modifica en esta auditoría.** Primera fase: solo Pan, admite uno o varios detalles, usa catálogos reales, deriva campos y guarda transaccionalmente sin conectar módulos de historial o tarjetas recientes. Reutiliza modelos/esquema actuales y validación en controller, sin FormRequest o Service nuevos por convención.

| Archivo | Modificación necesaria en esa primera fase |
|---|---|
| app/Http/Controllers/ProduccionController.php | index(): cargar productos Pan, turno, empleados y roles de producción desde catálogo. store(): validar el payload de Pan, rechazar familias todavía no implementadas, derivar atributos, crear cabecera/detalles/pivots en transacción y responder con errores/éxito. |
| resources/views/produccion/index.blade.php | Sustituir opciones vacías/fijas por catálogos reales; campos por detalle para producto/coches/latas adicionales/observacion/participantes con IDs; turno solo de cabecera; mostrar errores, conservar entrada y confirmar éxito sin simular historial real. |
| resources/js/produccion.js | Gestionar varios detalles y participantes asociados por fila, names anidados, cantidades visuales y eliminación/reset; deshabilitar/excluir campos ajenos a Pan y evitar que Torta/Bocadito aparenten estar implementados. La aritmética cliente es informativa; servidor vuelve a calcular. |
| tests/Feature/ProduccionPanStoreTest.php (nuevo) | Pruebas HTTP en MariaDB aislada: guardado de cabecera/varios detalles/pivots, 23 latas en el ejemplo, igualdad de turno legacy, unidad y snapshot derivados, factor/histórico según política acordada, auth/registrador, referencias/familia/cantidades manipuladas, rollback sin parciales y mensajes de resultado. |
| tests/Feature/NavigationTest.php | Adaptar las pruebas que hoy renderizan GET /produccion sin BD de negocio para usar fixtures aisladas cuando index() consulte catálogos; conservar regresiones de menú, auth, CSRF y prevención de caché. |

**No hacen falta cambios previstos** en rutas, migraciones, seeders, modelos/pivots, CSS, vite.config.js ni runner para este plan: la ruta POST, assets, relaciones, columnas y descubrimiento automático de tests ya existen. Las fixtures de la nueva prueba pueden cargar parámetros y roles por sí mismas usando soporte aislado existente, sin modificar seeders ni crear datos reales. La foto de Torta y Bocadito funcional se implementan en fases posteriores del flujo, no como requisito de la primera escritura de Pan.

**CÓDIGO MODIFICADO:** ninguno. **DOCUMENTACIÓN MODIFICADA:** únicamente este informe nuevo, solicitado expresamente en la raíz; no se editaron notas del Vault ni otros documentos. **PRUEBAS:** búsquedas/lecturas y comprobación estática de controles/names de Blade; no se probaron envíos en navegador ni persistencia y no se reejecutó la suite Laravel. **PENDIENTES:** revisar el contrato propuesto y resolver políticas señaladas antes de autorizar su implementación. Los cambios/deleciones preexistentes del checkout se preservan; no se restauran archivos como parte de esta auditoría.
