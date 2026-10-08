# Informe técnico de revisión previa al lanzamiento

- **Para:** Dirección del proyecto / propietario del sistema.
- **Elaborado desde el criterio de:** revisión técnica senior del trabajo de desarrollo.
- **Proyecto:** Sistema Panadería, checkout `panaderia-rs`.
- **Fecha:** 8 de octubre de 2026.
- **Referencia de código:** commit `e1365fd`; el árbol de trabajo estaba limpio al iniciar la revisión.
- **Encargo:** revisar controladores prioritariamente, modelos y vistas; detectar código innecesario, problemas y oportunidades de optimización; proponer responsabilidades y capas adecuadas sin implementar cambios.

## 1. Dictamen para dirección

El sistema tiene una base aprovechable. El registro de Pan usa Eloquent, validación de entradas, transacciones, bloqueo pesimista, cálculo de cantidades en servidor y snapshots históricos. Existen pruebas sustanciales de persistencia, relaciones, autenticación y rollback. No recomiendo una reescritura general antes del lanzamiento.

**Mi recomendación es no aprobar todavía un lanzamiento general que presente todas las pantallas actuales como funcionalidades terminadas.** Dashboard e Historial son interfaces de demostración conectadas a rutas reales, pero no a consultas reales. También hay fallos reproducidos en la validación del login y en el renderizado de producción después de rechazar entradas malformadas. El login no incorpora una limitación de intentos en el código revisado.

Un lanzamiento limitado a registrar y consultar Pan exige primero corregir esos fallos, comunicar el alcance real de las otras pantallas y decidir los permisos de acceso. La reorganización del controlador de producción es recomendable, pero tiene menos urgencia que corregir errores de ejecución y datos ficticios visibles.

**Resultado de las verificaciones:**

- Suite existente: **141 pruebas aprobadas, 1.489 aserciones** en MariaDB desechable.
- Reproducciones adicionales fuera del repositorio: **12 pruebas de caracterización aprobadas, 118 aserciones**. Su aprobación confirma el comportamiento defectuoso observado; no significa que se haya corregido.
- Sintaxis PHP: **27 archivos correctos** de `app/` y `routes/`.
- Sintaxis JavaScript: **5 archivos correctos**.
- Pint en modo de inspección: **incumplimientos de estilo en 8 archivos**. No se aplicó el formateador.
- No se modificó código, rutas, modelos, vistas, tests existentes ni documentación previa. El único archivo entregable creado es este informe.

## 2. Alcance, método y límites

### 2.1 Archivos revisados

Se leyeron los **5 controladores**, los **18 modelos** y las **6 vistas Blade** existentes, además de rutas, middleware propio, provider, configuración de autenticación, migraciones relacionadas y pruebas. Se revisaron los cinco scripts de interfaz y partes relevantes de CSS/Vite para confirmar conexiones, referencias y código aparentemente residual.

| Controlador | Responsabilidad actual | Valoración principal |
|---|---|---|
| `ProduccionController` | Consultar contexto de Pan y registrar un detalle con participantes | Es el mayor concentrador de responsabilidades; conservar garantías transaccionales al extraer lógica |
| `LoginController` | Mostrar login, autenticar y cerrar sesión | Flujo básico correcto; validación incompleta y falta de limitación de intentos |
| `DashboardController` | Devolver una vista sin datos | No implementa todavía los indicadores que presenta su interfaz |
| `HistorialController` | Devolver una vista sin datos | No implementa filtros, búsqueda ni listado real |
| `Controller` | Base abstracta de los controladores | Su clase vacía no constituye por sí misma código basura |

| Modelo | Inspección y observación |
|---|---|
| `Cargo` | Tabla, fillable y relación con empleados; formato inconsistente |
| `Categoria` | Productos y producciones; nombres usados como identificadores en producción |
| `DetallePan` | Relaciones, participantes con pivot y snapshot; cantidad canónica en latas |
| `DetallePanEmpleado` | Pivot con claves y rol; conservar su implementación y eventos |
| `DetalleTorta` | Relación a producto/producción y participantes; no implica un flujo HTTP terminado |
| `DetalleTortaEmpleado` | Pivot específico; no eliminar por parecido con otros pivots |
| `DetalleBocadito` | Cantidad, observación y relaciones; escritor HTTP no implementado |
| `DetalleBocaditoEmpleado` | Pivot específico con rol |
| `DetallePedido` | Relaciones y cantidad `decimal:2`; preservar precisión decimal |
| `Empleado` | Relaciones con cargo y usuario; timestamps concordantes con migración |
| `Pedido` | Fechas, entregado y detalles; modelo existente sin módulo HTTP de pedidos |
| `Produccion` | Cabecera, autor inicial, familia, turno y detalles; fecha casteada |
| `Producto` | Categoría, unidad, activo y factor; la edición futura necesita reglas explícitas |
| `Rol` | Usuarios asociados; no equivale a autorización implementada |
| `RolProduccion` | Participaciones en las tres familias; distinto de permisos de usuario |
| `Turno` | Relación nueva de cabecera y relación de detalle conservada |
| `UnidadMedida` | Equivalencia decimal y relaciones; conservar semántica pendiente |
| `Usuario` | Autenticable, relaciones, atributo oculto y contrato de contraseña correcto |

| Vista | Estado funcional observado |
|---|---|
| `auth/login.blade.php` | Formulario real; feedback ausente y recuperación sin conexión |
| `components/sidebar.blade.php` | Navegación compartida y logout real por POST |
| `produccion/index.blade.php` | Registro y consulta real de Pan; otros tipos son preliminares |
| `dashboard/index.blade.php` | Tarjetas y modales con valores estáticos |
| `history/index.blade.php` | Registros y contador ficticios; filtros sin consulta real |
| `history_pedido/index.blade.php` | Esqueleto sin ruta ni uso encontrado en el alcance inspeccionado |

### 2.2 Cómo interpretar los hallazgos

Prioridades:

- **P1:** resolver antes de publicar la funcionalidad afectada.
- **P2:** mejora importante o decisión de negocio que requiere atención antes de ampliar el uso.
- **P3:** limpieza, consistencia o mejora gradual; no justifica por sí sola bloquear el lanzamiento.

Estados:

- **EN INVESTIGACIÓN:** defecto o riesgo detectado, con evidencia indicada; falta su corrección o completar la validación necesaria.
- **PENDIENTE:** recomendación aún no implementada o decisión no aprobada.
- **IMPLEMENTADO Y COMPROBADO:** se reserva para comportamiento ya existente confirmado por las pruebas de este checkout.

Las referencias `archivo:líneas` corresponden al checkout revisado. Los rangos son orientativos para localizar bloques; las rutas se expresan respecto de la raíz del proyecto. No se asignan vulnerabilidades explotadas ni ganancias de rendimiento sin evidencia.

### 2.3 Aislamiento y límites

- PHP CLI observado: **8.5.10**. Laravel instalado, verificado en `vendor/laravel/framework/src/Illuminate/Foundation/Application.php`: **13.25.0**. Composer requiere PHP `^8.3` y Laravel `^13.17`.
- Se usó `tests/run-mariadb.php`, que crea un servidor temporal sin red, con socket local en `/tmp/sprint4-*`, y dos bases ficticias. `IsolatedMariaDb` valida base y directorio del servidor antes de permitir migraciones.
- No se consultó ni migró la base de trabajo. No se ejecutaron seeders contra ella. No se inspeccionó el contenido de `.env`.
- El primer intento de pruebas falló porque el sandbox impedía abrir el socket. Se ejecutó de nuevo mediante la herramienta de escalamiento. El servidor temporal fue detenido al terminar.
- PHPUnit configura sesiones y caché en memoria. Las pruebas existentes de navegación usan en parte usuarios precargados; el test de rehash usa provider Eloquent y persistencia reales en la base aislada.
- Vite está desactivado en las pruebas HTTP mediante `withoutVite()`. Por tanto, aprobar estos tests no comprueba el bundle, descargas de assets, Chart.js en navegador ni la interacción visual.
- No se ejecutó un build, no se hizo recorrido manual en navegador ni medición de carga/concurrencia. Tampoco se verificaron HTTPS, sesión `database`, infraestructura, permisos de despliegue ni configuración productiva.
- Los tests pueden generar cachés Blade y logs de ejecución ignorados por Git; no son cambios de código fuente. Los artefactos de pruebas adicionales se mantienen en `/tmp`, fuera del repositorio.

## 3. Resumen de hallazgos priorizados

| ID | Prioridad | Hallazgo | Evidencia | Estado del trabajo correctivo |
|---|---|---|---|---|
| C01 | P1 | Login acepta tipos incorrectos y puede lanzar TypeError | Reproducción HTTP | EN INVESTIGACIÓN |
| C02 | P1 | Login sin limitación de intentos propia | Código, rutas y 12 intentos consecutivos | EN INVESTIGACIÓN |
| C03 | P1 | Entradas rechazadas rompen la recuperación del formulario de Pan | Cuatro reproducciones HTTP 500 | EN INVESTIGACIÓN |
| C04 | P1 condicionada | Autenticación sin matriz de permisos aplicada | Rutas y búsqueda de autorización | PENDIENTE |
| C05 | P2 | Producción concentra validación, negocio, persistencia y respuesta | Inspección de `store()` e `index()` | PENDIENTE |
| C06 | P2 | Bloqueo de categoría serializa todos los registros de Pan | Orden de bloqueos en código | PENDIENTE |
| C07 | P2 | Unicidad de sesión depende del escritor y no de SQL | Migraciones y manejo de duplicados | PENDIENTE |
| C08 | P2 | Reenviar la misma petición crea un segundo detalle | Reproducción HTTP | PENDIENTE |
| C09 | P2 | Observaciones y participantes carecen de límites operativos | SQL por texto extenso reproducido | EN INVESTIGACIÓN |
| C10 | P2 | POST y GET aceptan formatos de fecha diferentes | Reproducción HTTP | EN INVESTIGACIÓN |
| C11 | P2 | Lecturas sin paginación y optimizaciones de consulta por evaluar | Código y esquema | PENDIENTE |
| C12 | P2 | Reglas dependen de nombres editables del catálogo | Código, ausencia de UNIQUE por nombre | PENDIENTE |
| C13 | P2 condicionada | No se conserva quién agregó cada detalle posterior | Código y prueba existente | PENDIENTE |
| C14 | P3 | Manejo de errores está acoplado a respuesta HTML | Bloque catch y rutas actuales | PENDIENTE |
| C15 | P3 | Métodos vacíos, imports sin uso y estilo desigual | Rutas, búsquedas y Pint | PENDIENTE |
| M01 | P2 condicionada | Invariantes del catálogo y detalles no están centralizadas | Modelos, migraciones y escritor | PENDIENTE |
| M02 | P3 | Tipado de relaciones y casts pueden ganar consistencia | Inventario de modelos | PENDIENTE |
| M03 | P2 condicionada | La futura gestión de usuarios necesita controles de escritura | Fillable y consumidores actuales | PENDIENTE |
| M04 | P3 | Atributos/modelos sin flujo actual no deben borrarse automáticamente | Relaciones y pruebas existentes | PENDIENTE |
| V01 | P1 | Dashboard muestra indicadores ficticios e inconsistentes | Vista, controlador y JS | EN INVESTIGACIÓN |
| V02 | P1 | Historial y filtros no consultan datos reales | Vista, controlador y JS | EN INVESTIGACIÓN |
| V03 | P2 | Login no muestra errores ni recupera usuario | Reproducción y Blade | EN INVESTIGACIÓN |
| V04 | P2 | Imágenes locales referenciadas no existen | Comprobación de archivos | EN INVESTIGACIÓN |
| V05 | P2 | Plantillas repiten estructura y contienen preparación de datos | Inspección Blade | PENDIENTE |
| V06 | P2 | Interacciones incompletas y dependencia de JS | Rutas, vistas y scripts | PENDIENTE |
| V07 | P2 | Modales necesitan semántica y gestión de foco | HTML y handlers actuales | PENDIENTE |
| V08 | P3 | Hay residuos de maquetación y selectores sin consumidores | Búsqueda global | PENDIENTE |
| V09 | P3 | Assets remotos y cargas comunes por revisar | Vistas, CSS y Vite | PENDIENTE |

## 4. Revisión detallada de controladores

### C01. Validación insuficiente en el login — P1

**Ubicación:** `app/Http/Controllers/LoginController.php:26-38`.

Solo se exige `required` a `username` y `password`. Eso permite valores no vacíos que son arrays. En el framework instalado, el provider utiliza `whereIn()` si la credencial de búsqueda es un array; la contraseña llega posteriormente al hasher.

**Evidencia reproducida:**

1. Enviar un username válido de una cuenta ficticia y `password` como array produce `TypeError`, en vez de un error normal de validación.
2. Enviar `username` como array que contiene esa cuenta, con su contraseña ficticia válida, autentica correctamente.

El segundo caso no demuestra acceso sin conocer la contraseña ni SQL injection. Sí demuestra que el contrato de entrada no se cumple. El primero provoca una excepción de servidor desde una ruta pública.

**Recomendación:** usar reglas `required|string` en ambos campos; para el usuario, respetar el máximo de 50 caracteres declarado en la migración. Definir un máximo de entrada de contraseña compatible con la política vigente y las cuentas existentes, sin aplicar reglas de creación de contraseña al login ni truncarla. Evitar normalizaciones que cambien su contenido.

Un `LoginRequest` permitiría aislar estas reglas si se combina con el trabajo de C02. No hace falta crear un servicio genérico solo para llamar a `Auth::attempt()`.

**Aceptación futura:** arrays, objetos y entradas excesivas se rechazan antes del provider; autenticación válida, rehash y redirección intended siguen pasando.

### C02. Login sin limitación de intentos — P1

**Ubicación:** `routes/web.php:13-14`, `LoginController.php:26-53`, `AppServiceProvider.php`.

No se encontró `throttle`, `RateLimiter` ni otro límite de intentos en estas rutas o clases. El grupo `guest` restringe quién puede entrar, pero no cuenta intentos fallidos.

**Reproducción:** doce intentos consecutivos con contraseña ficticia incorrecta terminaron en redirección al login y error de credenciales; ninguno recibió un bloqueo de aplicación.

**Riesgo:** facilita intentos automatizados y consumo de CPU por comprobación de hashes. No se verificó si existe protección adicional en proxy o infraestructura; esa protección no sustituye el comportamiento que debe tener la aplicación.

**Recomendación:** decidir límites y duración, distinguir intentos por usuario normalizado/IP y un límite general por IP, y usar un almacén compartido si hay varias instancias. Mostrar tiempo de espera con mensaje genérico. Evitar bloquear permanentemente una cuenta por peticiones de terceros. Laravel proporciona mecanismos de conteo y comprobación de límites. [Referencia oficial](https://laravel.com/framework/docs/13.x/rate-limiting).

**Aceptación futura:** límite, recuperación tras ventana, comportamiento entre usuarios/IP y login correcto; verificar el almacén de caché que se usará al desplegar.

### C03. El formulario de Pan puede fallar al recuperar entradas rechazadas — P1

**Ubicación:** `ProduccionController.php:72-118`; `resources/views/produccion/index.blade.php:96-136`.

La validación del POST rechaza correctamente arrays en campos escalares. Sin embargo, Laravel conserva la entrada rechazada para mostrar el formulario. La vista presupone que `old()` contiene strings o números: castea un `producto_id` a string y entrega coches, latas u observación directamente al escapado HTML.

**Reproducción real:** con sesión autenticada ficticia, enviar como array cualquiera de:

- `detalles.0.producto_id`;
- `detalles.0.coches`;
- `detalles.0.latas_adicionales`;
- `detalles.0.observacion`.

El POST redirige con su error de validación; el GET posterior de `/produccion` responde **500** en los cuatro casos. No se crearon cabeceras ni detalles. La protección de `fecha` y `turno_id` en `index()` no cubre estos valores anidados.

**Recomendación:** construir un estado seguro para el formulario, aceptando valores escalares solo cuando corresponda y sustituyendo estructuras incorrectas por vacío. Hacer lo mismo con la estructura de participantes. Conservar mensajes de validación y las entradas válidas. No resolverlo convirtiendo arrays a texto JSON ni usando salida HTML sin escapar.

**Aceptación futura:** todos los tipos malformados devuelven un formulario 200 con errores legibles, sin escrituras; comprobar también participantes e índices manipulados. Los tests existentes que solo verifican el rechazo del POST no cubren por sí solos el renderizado posterior.

### C04. Falta una decisión de permisos por rol aplicada al backend — P1 condicionada

**Ubicación:** `routes/web.php:18-25`, modelos `Usuario` y `Rol`, `AppServiceProvider.php`.

Todas las pantallas protegidas y el POST de producción utilizan `auth`. No se encontraron Policies, Gates, middleware de permisos ni comprobaciones de rol en los controladores. Las tablas de roles y relaciones de usuario existen, pero no restringen estas operaciones.

**Consecuencia actual:** el flujo de producción no distingue el rol de la cuenta autenticada. Las pruebas usan un rol ficticio genérico y el registro funciona.

**Recomendación:** dirección debe definir quién consulta, registra, corrige y administra. Si los permisos son distintos, implementarlos en servidor con Gates/Policies y ajustar la navegación a esos mismos permisos. Si todas las cuentas pueden operar igual, documentar esa decisión y no presentar los roles como una restricción existente. Laravel distingue estos mecanismos de autorización. [Referencia oficial](https://laravel.com/framework/docs/13.x/authorization).

**Advertencia de implementación:** el controlador base actual no incorpora el trait `AuthorizesRequests`. Un futuro `$this->authorize()` requeriría prepararlo; también se puede usar `Gate::authorize()` o middleware `can` cuando corresponda.

**Aceptación futura:** pruebas de permitido/403 para cada capacidad definida; ocultar un enlace no basta. No inventar permisos basándose únicamente en nombres de roles.

### C05. Extraer responsabilidades del controlador de producción — P2

**Ubicación:** `ProduccionController.php:24-218`.

`store()` ocupa alrededor de 150 líneas y combina reglas de forma, mensajes, normalización de fecha, reglas de participantes, consulta y bloqueo de catálogos, cálculo, resolución de cabecera, escritura de detalle/pivots, reporte de SQL y redirección. `index()` combina catálogo, recuperación del formulario, validación de filtros y consultas de resultados.

La cantidad de líneas no es el defecto principal: el problema es que una modificación de negocio obliga a editar el mismo método que decide la respuesta HTTP. Además, reutilizar el caso de uso en otro punto acabaría copiando garantías importantes.

**Recomendación concreta:**

1. `RegistrarProduccionPanRequest`: reglas de entrada, mensajes y atributos. Autorizar conforme a C04.
2. `ConsultarProduccionRequest`, o un pequeño validador de contexto: filtros GET explícitos, conservando el tratamiento del old input de un POST rechazado.
3. `RegistrarProduccionPan` en `app/Actions/Produccion/`: coordinar el caso de uso y su transacción. Recibir datos validados y el identificador del registrador, no el objeto HTTP `Request`.
4. Extraer una regla/clase de participantes solo si se reutiliza o dificulta el caso de uso; mantener verificaciones sensibles a concurrencia dentro de la transacción.
5. Mantener inicialmente las consultas de lectura pequeñas en `index()`; crear una clase de consulta cuando Historial/Dashboard compartan filtros o agregaciones.

Los Form Requests están destinados a encapsular validación y autorización de solicitudes complejas. [Referencia oficial](https://laravel.com/framework/docs/13.x/validation#form-request-validation). El nombre y organización de la Action son una propuesta de arquitectura de este informe, no una obligación del framework.

**Beneficio:** separar transporte y negocio sin perder validación, atomicidad, snapshots ni reglas de participantes. Una extracción a helpers privados mejora algo la legibilidad, pero mantiene el acoplamiento al controlador.

**Aceptación futura:** la suite existente sigue pasando, los fallos de C03 quedan corregidos y puede probarse el caso de uso sin fabricar una petición HTTP.

### C06. El bloqueo de categoría es demasiado amplio para escalar — P2

**Ubicación:** `ProduccionController.php:122-130`.

Cada registro bloquea primero la misma fila de categoría Pan. Así dos envíos de fechas y turnos distintos compiten por esa fila hasta terminar la transacción. Esta serialización es deliberada: ayuda a evitar cabeceras duplicadas dentro de este escritor cuando no hay restricción UNIQUE.

**Evidencia:** estructura y orden de `lockForUpdate()`; no se midió espera real ni carga concurrente. Es un potencial cuello de botella, no una afirmación de lentitud ya medida.

**Recomendación:** conservar el mecanismo actual hasta definir y probar una alternativa. Resolver primero la unicidad de sesión (C07) y evaluar después bloqueos por sesión o creación protegida por una restricción SQL. Mantener un orden de bloqueo uniforme y tratar conflictos/deadlocks esperados. Los bloqueos existentes ya están dentro de una transacción, lo que es correcto. [Referencia oficial](https://laravel.com/framework/docs/13.x/queries#pessimistic-locking).

**Aceptación futura:** prueba con conexiones independientes para dos escrituras simultáneas en la misma sesión y en sesiones distintas. Los tests actuales son secuenciales y no certifican esta propiedad.

### C07. Falta protección SQL de la unicidad de sesión — P2

**Ubicación:** `ProduccionController.php:178-191`; migración `2026_10_02_180000_add_categoria_and_turno_to_produccion.php`.

El escritor busca como máximo dos cabeceras para la misma fecha, categoría y turno; rechaza duplicados existentes y reutiliza una cabecera cuando corresponde. La base no tiene UNIQUE sobre esa identidad. Otro escritor, importación o operación directa puede generar duplicados sin cumplir el protocolo de bloqueo.

**Recomendación:** confirmar la regla de negocio antes de diseñar la restricción. Para sesiones Pan, evaluar unicidad de fecha/categoría/turno y revisar duplicados/legacy antes de una migración aditiva. Los valores NULL y familias sin turno requieren análisis: un UNIQUE con columnas nullable no garantiza por sí solo la misma unicidad para sesiones sin turno.

No sustituir el flujo por `firstOrCreate()` esperando que resuelva carreras sin una garantía de base. No borrar ni fusionar cabeceras automáticamente.

**Aceptación futura:** esquema y escritor coinciden; limpieza de datos cuenta con criterio aprobado; inserciones concurrentes convergen en una cabecera o reciben un conflicto controlado.

### C08. No hay idempotencia de envío — P2

**Ubicación:** `ProduccionController.php:194-207`; `resources/js/produccion.js:181-197`.

**Reproducción:** dos POST idénticos válidos generan una cabecera y dos detalles. El sistema permite repetir productos como lotes independientes, y las pruebas existentes verifican esa regla; no debe eliminarse.

**Riesgo adicional:** doble clic, reintento por red o repetición de una solicitud pueden crear accidentalmente dos registros del mismo envío. El handler de submit no bloquea el botón después de un envío válido.

**Recomendación:** definir si se necesita garantizar una sola ejecución por envío. Si se necesita, asignar una clave por formulario/envío y persistir su unicidad de forma transaccional. Un lote nuevo del mismo producto obtiene una clave nueva. Deshabilitar el botón mejora UX, pero no garantiza idempotencia en backend.

**Aceptación futura:** reenvío de la misma clave no duplica; envíos distintos del mismo producto siguen creando detalles separados. Decisión de negocio pendiente, no deduplicar por igualdad de campos.

### C09. Falta limitar observaciones y participantes — P2

**Ubicación:** `ProduccionController.php:82-86,151-165,203-207`; migración de observaciones.

`observacion` solo exige texto y permite longitud ilimitada en la validación. La columna es TEXT. Una observación ficticia ASCII de 70.000 caracteres llegó a SQL, falló por tamaño y terminó con un error genérico de producción; el rollback fue correcto.

`participantes` tiene mínimo, pero no máximo. La aplicación comprueba existencia de empleados/roles con reglas wildcard y después recorre e inserta participantes individualmente. El coste aumenta con la lista recibida. La unicidad de empleados limita los envíos válidos, pero no evita procesar entradas excesivas o repetidas.

**Recomendación:** definir límites útiles para la operación y reflejarlos en Request y UI. Para texto, considerar tamaño en bytes y caracteres multibyte, no asumir que el límite de caracteres equivale a la capacidad de TEXT. Para participantes, usar un máximo acordado, `distinct` para IDs y restricciones de claves permitidas en los arrays, manteniendo exactamente un Maestro y al menos un Ayudante.

Evaluar consultas de existencia por lotes si el perfil muestra un coste significativo. No retirar validaciones por ahorrar consultas. Si se desea agrupar inserts de pivots, comprobar que la alternativa preserve los eventos del Pivot personalizado y los tests de rollback; cambiar a inserción masiva puede alterar ese comportamiento.

**Aceptación futura:** límites rechazados con error del campo antes de escribir, Unicode válido aceptado y participantes válidos persistidos sin pérdida de reglas ni eventos.

### C10. Contrato de fecha inconsistente — P2

**Ubicación:** `ProduccionController.php:39,74,120`.

GET exige `date_format:Y-m-d`; POST acepta `date` y luego interpreta el valor con Carbon. Se reprodujo que `10/05/2026` es aceptado por POST y termina almacenado como `2026-10-05`, mientras esa misma cadena es rechazada como filtro GET.

**Riesgo:** fechas ambiguas que el usuario o un cliente interpreta como día/mes pueden registrar otro día. La UI nativa normalmente envía ISO, pero la ruta admite peticiones construidas fuera del navegador.

**Recomendación:** exigir `Y-m-d` de forma coherente y normalizar solo después de validar. Las reglas sobre fechas futuras, días cerrados o correcciones retroactivas necesitan decisión de negocio; no imponerlas sin autorización.

**Aceptación futura:** los formatos ambiguos se rechazan en POST y GET; la fecha ISO se guarda y redirige sin cambiar de día.

### C11. Optimización de lecturas y consultas — P2

**Ubicación:** `ProduccionController.php:26-31,53-59`; `produccion/index.blade.php:146-147,278`.

El controlador carga todos los empleados, turnos, roles y productos activos, y todos los detalles de una sesión. Usa `with(['producto', 'empleados'])`, que evita el N+1 de esas relaciones en las tarjetas. Ese acierto debe conservarse.

**Mejoras propuestas, sujetas a medición:**

- Seleccionar solo columnas usadas: los selectores de empleados necesitan ID/nombre, no teléfono u otros campos. Si se restringen relaciones, incluir PK/FK necesarias para que Eloquent las reconstruya.
- Filtrar roles Maestro/Ayudante para el selector, pero conservar un mapa adecuado para leer roles históricos. No perder información histórica por filtrar todo el catálogo indiscriminadamente.
- Preparar mapas por ID para los roles y empleados: `firstWhere()` repetido en cada participante hace búsquedas lineales. Esto no es N+1 SQL; es trabajo de colección/PHP.
- Paginar o establecer un volumen de consulta apropiado para detalles de sesión e Historial. No omitir silenciosamente producción por aplicar un `limit` arbitrario.
- Evaluar un índice compuesto para la consulta fecha/categoría/turno. Las FK aportan índices individuales, pero no equivalen automáticamente al índice de ese filtro compuesto. Orden y utilidad deben verificarse con EXPLAIN y datos representativos.
- Cachear catálogos pequeños solo si hay necesidad medida y una invalidación definida. Nunca cachear validación crítica de activo/factor como sustituto de la lectura transaccional.

**Aceptación futura:** igual resultado funcional, consultas verificadas sin N+1 y tiempos/consumo medidos con volumen representativo. No se promete una mejora porcentual porque no hubo benchmark.

### C12. Reglas basadas en nombres y falta de catálogo obligatorio — P2

**Ubicación:** `ProduccionController.php:26,124-126,160`; migraciones de categorías y roles de producción.

Pan, Maestro y Ayudante se identifican por texto. No se encontraron restricciones UNIQUE sobre esos nombres en sus migraciones. Un cambio de nombre puede romper el registro y las repeticiones pueden volver ambiguo `first()`.

Además, sin la categoría Pan, el GET de producción devuelve **404**, reproducido en la base ficticia, aunque la ruta exista. El POST tiene un mensaje específico para categoría faltante, pero el GET no ofrece el mismo diagnóstico.

**Recomendación inmediata:** comprobar disponibilidad y coherencia de catálogos en el despliegue y mostrar una respuesta operativa apropiada cuando falten. A mediano plazo, valorar códigos estables separados de etiquetas editables y restricciones de unicidad consensuadas. No fijar IDs 1/2 ni renombrar catálogos históricos por este informe.

**Aceptación futura:** falta de catálogo no se presenta como ruta inexistente; IDs arbitrarios siguen funcionando y las etiquetas pueden cambiar solo conforme a un contrato definido.

### C13. Trazabilidad del registrador por detalle — P2 condicionada

**Ubicación:** `ProduccionController.php:187-205`, `Produccion.php:16-21`, `DetallePan.php:14-25`; `tests/Feature/ProduccionPanStoreTest.php:418-450`.

La cabecera guarda el usuario que crea la sesión. Si otra cuenta agrega un producto a esa sesión, no se registra en el detalle quién lo agregó ni un timestamp de creación. El test existente confirma que el autor de cabecera sigue siendo el primero.

Esto es coherente si el requisito es únicamente conservar el creador de la sesión. Es insuficiente si el historial debe atribuir cada anotación a su autor o mostrar la hora de ese lote. Los participantes del producto no sustituyen al registrador.

**Recomendación:** decidir el nivel de auditoría antes de presentar “registrado por” en nuevas tarjetas o reportes. Si se exige por detalle, proponer campos aditivos o una auditoría específica, sin atribuir registros antiguos al usuario inicial por suposición.

**Aceptación futura:** dos usuarios en una sesión tienen atribución correcta según el contrato aprobado; datos históricos sin autor individual se identifican como desconocidos.

### C14. Error SQL y respuesta HTTP — P3

**Ubicación:** `ProduccionController.php:209-214`.

El catch de `QueryException` reporta el fallo, devuelve un mensaje genérico y recupera datos. Los tests verifican rollback real por CHECK/FK, incluso después de escribir un pivot y en sesiones preexistentes. Para el formulario HTML actual, es una decisión razonable.

**Recomendación:** conservar este comportamiento durante la extracción. Distinguir internamente conflictos de unicidad, fallos de integridad y problemas temporales cuando se introduzcan, sin filtrar SQL al usuario. Si en el futuro se añade API/JSON, definir sus respuestas por separado: este catch siempre redirige aunque la solicitud espere JSON.

No se observó una fuga de SQL en el feedback HTML. Tampoco se revisó el destino efectivo ni la retención de logs en producción; un futuro sistema de observabilidad debe evitar registrar credenciales o bindings sensibles.

**Aceptación futura:** errores de negocio mantienen mensajes por campo; errores de infraestructura quedan correlacionables para soporte; el usuario recibe una respuesta acorde con el canal solicitado.

### C15. Limpieza y consistencia de controladores — P3

**Ubicación:** `ProduccionController.php:220-250`; `DashboardController.php:5-14`; `HistorialController.php:5-13`.

- `show`, `edit`, `update` y `destroy` de producción están vacíos y no tienen rutas registradas. Son scaffolding candidato a retirar o mantener en backlog hasta implementar el caso de uso.
- Dashboard e Historial importan `Request` y la facade `View` sin usarlas. El helper `view()` no utiliza ese import.
- Hay comentarios `//`, docblocks genéricos y formato inconsistente. Pint lo confirmó en los archivos detallados en la sección de pruebas.
- Los métodos de controlador no declaran tipos de retorno. `index(): View` y operaciones HTML `: RedirectResponse` ayudarían a comunicar contratos, usando las clases de tipo correctas.

**Recomendación:** limpieza pequeña y separada de cambios de negocio. No agregar Services a controladores que solo devuelven una vista ni borrar la clase abstracta base por estar vacía. La prioridad de Dashboard/Historial es conectar funcionalidad real o ajustar alcance, no aumentar sus líneas.

**Aceptación futura:** imports y métodos sin uso resueltos, Pint correcto y rutas/comportamiento conservados. Evitar mezclar un gran diff de formato con correcciones críticas.

## 5. Revisión de modelos y contratos de datos

### M01. Centralizar invariantes cuando aparezcan nuevos escritores — P2 condicionada

**Ubicación:** `Producto.php`, `DetallePan.php`, `Produccion.php`; migraciones de cantidades, familia/turno y factor.

El escritor actual comprueba producto activo de Pan, unidad existente, composición de participantes y cantidad positiva con tope de INT. El esquema agrega FK y CHECK para cantidad; son garantías útiles.

No todas las reglas quedan expresadas en SQL o en un caso de uso reutilizable: un detalle puede recibir un turno diferente al de cabecera mediante otros escritores; los modelos permiten asignar categoría/producto/unidad; los factores `panes_por_lata` y `panes_por_lata_usado` son enteros nullable sin CHECK de positividad.

**Recomendación:** hacer que los futuros escritores pasen por el caso de uso de producción y definir las invariantes del catálogo antes de añadir restricciones. NULL del factor representa información desconocida y está cubierto por tests: no cambiarlo a 0/1 ni inventar un backfill. Si el factor informado debe ser positivo, validar en la futura edición del catálogo y evaluar una restricción aditiva.

La equivalencia/unidad de Pan y la semántica de temporada requieren definición propia. El writer verifica que existe unidad, pero no valida que su nombre/equivalencia represente “lata”; eso no se debe corregir adivinando la regla.

**Aceptación futura:** catálogo y escritor comparten reglas documentadas; valores desconocidos siguen siendo NULL; cambios en producto no recalculan snapshots históricos.

### M02. Tipos de relaciones y casts coherentes — P3

**Ubicación:** conjunto de `app/Models/`.

Hay relaciones tipadas en Produccion/Categoria/Turno y relaciones sin tipo en otros modelos. Añadir `BelongsTo`, `HasMany`, `HasOne` y `BelongsToMany` donde corresponde facilitaría análisis estático y mantenimiento.

Los casts existentes de booleanos, fechas y decimales son correctos según las pruebas. Puede valorarse el cast integer explícito para cantidades y factores cuando ayude al contrato, pero no se reprodujo un error de tipo en lecturas normales de MariaDB. No presentar su ausencia como bug probado.

**Recomendación:** consistencia gradual; mantener `decimal:2` para cantidad de pedidos y equivalencias. No usar float para valores decimales exactos. No añadir casts semánticos a `temporada_fe` hasta definir cómo se usa.

**Aceptación futura:** relaciones mantienen claves correctas; serialización y precisión existentes no cambian accidentalmente.

### M03. Gestión futura de usuarios y credenciales — P2 condicionada

**Ubicación:** `Usuario.php:15-25,41-49` y consumidores actuales.

`password_hash`, `rol_id` y `empleado_id` son fillable. Esto no es por sí solo una vulnerabilidad: no existe aquí un endpoint de gestión de usuarios que persista `$request->all()`. El escritor de producción deriva el registrador del usuario autenticado y no acepta su ID desde el formulario.

**Recomendación para una futura gestión de usuarios:** persistir solo datos validados y permitidos, hashear contraseñas de forma explícita o mediante un contrato de cast acordado, y autorizar cambios de rol/vínculo de empleado. `fillable` no valida ni cifra y `hidden` solo oculta serialización.

**Estado del contrato de contraseña:** `getAuthPasswordName()` **ya está implementado** y devuelve `password_hash`; el test de rehash persistente pasó en esta revisión. El contrato queda **IMPLEMENTADO Y COMPROBADO en MariaDB aislada** para este checkout. No se lo lista como trabajo pendiente ni se infiere que la sesión database esté certificada.

**Aceptación futura:** rehash, ocultamiento y login siguen funcionando; no existe asignación de permisos desde campos no autorizados.

### M04. Diferenciar repetición legítima de código sobrante — P3

Las tres clases de detalle y sus pivots se parecen, pero representan tablas y reglas distintas. Sus relaciones y claves compuestas cuentan con pruebas de persistencia. No recomiendo fusionarlas en un modelo genérico ni introducir herencia solo para reducir unas pocas líneas.

Tampoco deben borrarse automáticamente `turno_id` de DetallePan, `unidad_medida_id`, `panes_por_lata_usado`, las propiedades de claves de Pivot ni `$timestamps = false`. Responden a esquema, lectura de asociaciones o conservación de historia. Empleado y Usuario sí tienen timestamps en las migraciones; su configuración diferente es coherente.

Pedido/DetallePedido, Torta y Bocadito no tienen flujo HTTP completo, pero sus modelos están utilizados por relaciones y pruebas. Clasificarlos como funcionalidad pendiente, no como basura eliminable sin decisión de alcance.

**Recomendación:** mantener relaciones explícitas; retirar comentarios educativos repetitivos o mover futuras explicaciones al lugar documental adecuado solo cuando se autorice. No duplicar ni trasladar documentación histórica en esta tarea.

## 6. Revisión detallada de vistas y scripts asociados

### V01. Dashboard de demostración presentado como producción — P1

**Ubicación:** `DashboardController.php:10-14`; `dashboard/index.blade.php:41-90,113-197`; `resources/js/dashboard.js:25-68`.

El controlador no consulta ni pasa métricas. La vista muestra cantidades, empleados, usuarios y horas fijos; los gráficos contienen arrays de ceros. El botón Aplicar no tiene handler en el script revisado ni formulario que consulte el servidor.

Hay contradicciones visibles: el modal indica **12,5 coches = 150 latas**, pero la regla actual es **18 latas por coche**. 12,5 coches equivaldrían a 225 latas; 150 latas se representarían como 8 coches + 6 latas. También se muestra “Tarde” aunque el catálogo documentado para Pan usa Mañana/Noche.

**Recomendación:** conectar consultas y filtros reales o identificar/retirar temporalmente la pantalla del alcance productivo. Definir indicadores antes de calcularlos: no sumar latas de Pan, unidades de Bocadito y filas de Torta como si fueran una sola cantidad comparable. “Pendiente/completada” necesita una regla y fuente de estado que no se observan en Produccion.

**Aceptación futura:** valores derivados de datos, estados sin datos veraces, filtros efectivos, desglose consistente y unidades correctas. Los tests de navegación 200 no verifican esas métricas.

### V02. Historial sin consulta ni filtros reales — P1

**Ubicación:** `HistorialController.php:9-13`; `history/index.blade.php:56-103`; `resources/js/historial.js:41-49`.

El contador de 23 registros, dos tarjetas, fechas y autores son literales. Filtrar solo ejecuta `console.log`; los enlaces de detalles son `#`. Los turnos tienen IDs 1 y 2 fijos, pese a que las pruebas del flujo de producción comprueban IDs arbitrarios.

**Recomendación:** una consulta GET con categoría, rango de fechas y turno aplicable, validación de rango, datos de catálogo y paginación. Preservar filtros en los enlaces de navegación. Mostrar contador real y estado vacío. Conectar detalles solo cuando exista su lectura y autorización.

**Aceptación futura:** cambiar filtros cambia datos, IDs de catálogo no están fijados, fechas inválidas se explican y ninguna tarjeta de ejemplo aparece como registro real.

### V03. Feedback del login ausente — P2

**Ubicación:** `LoginController.php:51-53`; `auth/login.blade.php:39-70`.

El controlador guarda un error de credenciales y conserva username. La vista no lee `$errors`, no muestra `@error` y el input username no usa `old()`. Se comprobó el recorrido POST fallido → GET login: la sesión tiene el error, pero el HTML no contiene su mensaje.

**Recomendación:** mostrar errores y recuperar el usuario solo cuando sea un string seguro. Añadir atributos adecuados como `autocomplete="username"` y `autocomplete="current-password"`, y asociar mensajes con el campo. Nunca recuperar ni mostrar la contraseña.

**Aceptación futura:** error perceptible, username recuperado, campo de contraseña vacío y valores rechazados de C01 sin provocar otro fallo de vista.

### V04. Imágenes locales inexistentes — P2

**Ubicación:** `auth/login.blade.php:27`; `dashboard/index.blade.php:48,56,64,150,155`.

Se comprobó que no existen en este checkout:

- `public/images/icono_login.png`;
- `public/images/placeholder-pan.jpg`;
- `public/images/placeholder-torta.jpg`;
- `public/images/placeholder-bocadito.jpg`.

El video local referenciado por login sí existe. No se hizo una petición HTTP a un servidor desplegado; un pipeline externo podría aportar assets, pero aquí no se observó ese mecanismo.

**Recomendación:** suministrar recursos válidos en el artefacto de despliegue o retirar las referencias de demo. Un placeholder no debe ser un archivo ausente. Comprobar rutas y mayúsculas en Linux.

**Aceptación futura:** recursos usados responden correctamente desde el artefacto publicado y no hay imágenes rotas en las pantallas incluidas.

### V05. Extraer layout y preparar datos de vista — P2

**Ubicación:** vistas de página; `produccion/index.blade.php:25-32,96-99,144-149,180-183,277-279`.

Cada página repite estructura HTML, head y carga de assets. El sidebar ya es compartido; es una base razonable para un layout común. Producción contiene filtrado de errores por regex, recuperación/conversión de old input y búsqueda de roles/empleados dentro de bucles.

**Recomendación:** crear un componente/layout de páginas autenticadas y componentes pequeños para campo con error, participante y tarjeta. Preparar mapas y estado de formulario seguro fuera de bloques dispersos de Blade. Conservar escaping `{{ }}`, asociaciones label/input, feedback de sesión y carga del manejador BFCache.

No extraer cada div a un componente. Solo separar piezas que tengan una responsabilidad clara o repetición útil. `old()` y `@error` pueden seguir en Blade si el manejo de tipos es seguro y legible.

**Aceptación futura:** vistas más simples, sin regresión de HTML, IDs/names de formulario y errores. No duplicar cargas globales al migrar el sidebar al layout.

### V06. Funcionalidades preliminares y dependencia de JavaScript — P2

**Ubicación:** `produccion/index.blade.php:45-48,199-245`; `produccion.js`; `auth/login.blade.php:69`; `history_pedido/index.blade.php`.

Producción anuncia explícitamente que Torta/Bocadito están pendientes y el backend los rechaza; eso es mejor que simular persistencia. Aun así, se conserva UI preliminar, botones de forma/foto y campos que incrementan el código que mantener.

La recuperación de contraseña no tiene ruta ni handler. La vista de historial de pedidos es un esqueleto sin uso localizado. En producción, agregar participantes y consultar contexto requieren JS; no hay alternativa de formulario GET funcional para ese botón de consulta.

**Recomendación:** acordar el alcance visible del lanzamiento. Ocultar módulos futuros o mantener un estado pendiente inequívoco. Conectar o retirar la acción de recuperación, dando un procedimiento real si el soporte la resuelve manualmente. Evaluar un formulario GET para consulta y una alternativa usable cuando el script de participantes no cargue.

No introducir recuperación de contraseña estándar sobre una tabla inexistente sin adaptar modelo, esquema y canales. `config/auth.php` contiene configuración de broker de plantilla, pero no prueba que el flujo exista.

**Aceptación futura:** cada acción visible hace lo anunciado o explica su indisponibilidad; un fallo de carga de assets no deja al usuario sin diagnóstico.

### V07. Accesibilidad de modales — P2

**Ubicación:** `dashboard/index.blade.php:114-199`; handlers de apertura/cierre en `dashboard.js`.

Los modales son divs con una clase visual. No se encontraron `role="dialog"`, `aria-modal`, gestión de foco, restauración al botón de origen ni cierre por Escape. Los botones de cierre solo tienen un icono, sin un nombre accesible explícito.

**Recomendación:** semántica de diálogo, título asociado, foco inicial, navegación contenida, Escape y restauración de foco. Puede valorarse el elemento `dialog` con un comportamiento compatible con los navegadores objetivo. Probar teclado y lector de pantalla cuando la funcionalidad real se incorpore.

Esto se determinó por código; no se realizó una auditoría de accesibilidad en navegador ni se declara cumplimiento de un estándar completo.

### V08. Código residual y simplificaciones verificables — P3

**Ubicación:** `dashboard/index.blade.php:21`; `resources/css/dashboard.css:28-80`; `produccion/index.blade.php`; `history_pedido/index.blade.php`.

- `/ --- FILTRO DE FECHA --- /` es texto HTML visible, no comentario. Es un residuo concreto de maquetación.
- Las reglas `.barra-lateral`, `.avatar`, `.nav-iconos` y `.cerrar-sesion` de dashboard no tienen la estructura correspondiente en las vistas actuales; el sidebar usa `.sidebar`. Son candidatas a retirar tras una comprobación visual.
- Los atributos `data-label` no tienen consumidores encontrados en los scripts revisados. La variable `$indice` siempre vale 0; puede simplificarse sin cambiar el contrato de POST de un solo detalle.
- `data-indice="0"` sí es utilizado por pruebas existentes: no borrarlo junto con otros atributos sin revisar el contrato de tests/UI.
- Los controles de foto de Torta y `multipart/form-data` son preliminares. Para Pan no se suben archivos; simplificar el enctype solo si se retira esa UI del mismo formulario.
- El archivo de historial de pedidos no es funcional, pero su eliminación depende de si se conservará como trabajo futuro. No se aconseja borrarlo solo por estar vacío.

Los dos bloques CSS de `.popover-empleado` son complementarios; no se comprobó que uno sea duplicación inútil. Puede consolidarse la definición para facilitar mantenimiento, sin afirmar que sobra todo el segundo bloque.

**Aceptación futura:** limpieza basada en referencias reales y verificación visual, sin romper selectores, tests ni módulos previstos.

### V09. Recursos compartidos y dependencias externas — P3

**Ubicación:** `@vite` en vistas/sidebar, `resources/css/*.css`, `vite.config.js`.

Login y Dashboard cargan Font Awesome desde CDN; Historial usa clases de esos iconos, pero no incorpora esa carga. Google Fonts está importado por varias hojas; Producción pide Huninn en CSS sin una importación propia. La apariencia puede variar al entrar directamente a cada página.

`resources/css/app.css` está configurado como entrada de Vite, pero no se encontró su uso desde las vistas. Instrument Sans se configura en Vite y las páginas usan otras familias. Es candidato a revisar, no evidencia de una dependencia instalable que deba borrarse.

**Recomendación:** escoger una estrategia coherente de tipografía/iconos, incluir los recursos necesarios por página o layout y revisar qué entradas se consumen realmente. Para un entorno con conectividad limitada, valorar alojar assets propios. Optimizar Chart.js o el video solo después de medir peso/coste y confirmar la necesidad visual.

No se auditó disponibilidad de los CDN ni se ejecutó build. La resolución real de Vite debe validarse después de cualquier cambio de carga.

## 7. Arquitectura propuesta, sin sobredimensionar el proyecto

### 7.1 Reparto de responsabilidades

| Pieza propuesta | Responsabilidad | No debería hacer |
|---|---|---|
| Controller | Recibir solicitud validada, invocar caso de uso y devolver respuesta | Concentrar reglas del dominio y escrituras detalladas |
| Form Request | Forma/tipos/límites de entrada, mensajes y autorización HTTP | Crear registros ni sustituir validación sensible a concurrencia |
| Action `RegistrarProduccionPan` | Coordinar reglas, transacción, cabecera, detalle y participantes | Depender de redirects, sesión, flash o `Request` |
| Regla de participantes, si se necesita | Validar composición y duplicados de forma reutilizable | Persistir relaciones o decidir permisos de usuario |
| Clase de consulta, cuando se justifique | Filtros y lecturas reutilizadas por Historial/Dashboard | Ejecutar el caso de escritura |
| Modelos Eloquent | Tablas, relaciones, casts y comportamiento propio de entidad | Orquestar la interfaz o aceptar toda la solicitud |
| Policy/Gate | Capacidades autorizadas según matriz aprobada | Inventar permisos por nombres de roles |
| Layout/componentes Blade | Presentación reutilizable y accesible | Hacer consultas de negocio o confiar en tipos de old input |

Flujo recomendado:

```text
Solicitud HTTP
  → autenticación y autorización
  → Form Request: tipos y estructura
  → Controller
  → Action: reglas con datos actuales + transacción
  → Eloquent y restricciones SQL
  → Controller: redirect/feedback
  → Blade: presentación de estado seguro
```

### 7.2 Ejemplo de controlador después de una futura extracción

Este ejemplo es orientativo. **No existe en el checkout** y no autoriza su implementación. Los nombres de clases y firma deberán adaptarse a la decisión final.

```php
public function store(
    RegistrarProduccionPanRequest $request,
    RegistrarProduccionPan $registrar,
): RedirectResponse {
    $produccion = $registrar->ejecutar(
        $request->validated(),
        $request->user()->getAuthIdentifier(),
    );

    return redirect()->route('produccion.index', [
        'fecha' => $produccion->fecha->format('Y-m-d'),
        'turno_id' => $produccion->turno_id,
    ])->with('success', 'Producto de Pan registrado correctamente.');
}
```

La Action mantendría el cálculo en servidor, la lectura estable del factor, comprobación de catálogos, maestro/ayudantes, tratamiento de duplicados y escritura atómica. El esquema muestra el éxito; el tratamiento de ValidationException y fallos SQL deberá conservar el feedback actual según C14. La nueva clase no debe limitarse a copiar 150 líneas y ocultarlas: organizar etapas y nombrar reglas mejora la comprensión.

### 7.3 Capas que no recomiendo añadir ahora

- Un Repository genérico para envolver cada `find`, `create` o relación de Eloquent: añade indirección sin necesidad demostrada de otra persistencia.
- Un `ProduccionService` enorme que concentre Pan, Torta, Bocadito, pedidos, métricas y permisos. Separar casos de uso cuando cada familia se implemente.
- Interfaces para todas las clases, DTOs de cada campo, CQRS completo, event sourcing o microservicios: no hay evidencia de que el proyecto los necesite para este lanzamiento.
- Observers con reglas ocultas y escrituras cruzadas como sustituto del caso de uso explícito. La transacción debe poder comprenderse desde el flujo de registro.

La capa nueva que sí recomiendo como primer paso es **un caso de uso de registro de Pan y sus Form Requests**, con pruebas de regresión. Las clases de consulta se justifican al conectar lecturas reales, no por añadir carpetas.

## 8. Qué conservar del trabajo existente

Se considera **IMPLEMENTADO Y COMPROBADO**, dentro del aislamiento descrito:

- Derivar el registrador de la cuenta autenticada; no aceptar el campo desde POST.
- Autenticación basada en provider estándar, hash oculto, contrato `password_hash` y rehash persistente.
- Regenerar sesión en login y invalidar sesión/token en logout; logout por POST y protección CSRF probada en los casos existentes.
- Protección `auth` de las rutas operativas y navegación común.
- Validar producto activo de Pan, roles permitidos y empleados sin repetir; exactamente un Maestro y al menos un Ayudante.
- Recalcular cantidades en el backend; cantidad canónica entera en latas, independiente del texto/total del navegador.
- Transacción que revierte cabecera, detalle y pivots de la petición fallida, conservando filas anteriores.
- Snapshot nullable del factor, conservado aunque se edite el producto.
- Reutilización de cabecera y detalles independientes para productos repetidos.
- Detección de cabeceras duplicadas, sin escoger una arbitrariamente.
- Relaciones Eloquent y pivots personalizados con claves coherentes y pruebas de persistencia.
- Carga anticipada de producto/empleados en la lectura de producción.
- Escapado Blade y creación de textos de participantes con APIs DOM, sin insertar nombres como HTML.

El middleware de no almacenamiento y el handler BFCache existen y sus respuestas HTTP pasan los tests. En esta revisión no se repitió la verificación de navegación Atrás en un navegador real; no se extiende ese resultado a todos los navegadores.

## 9. Secuencia de trabajo sugerida para aprobar un lanzamiento

### Etapa 1. Corregir fallos y definir alcance visible

1. C01/C02: tipos y límites de login, protección de intentos.
2. C03: recuperación segura de datos rechazados de Pan.
3. V03: errores del login y recuperación segura de username.
4. C09/C10: límites de entrada y fechas coherentes.
5. V01/V02/V06: conectar Dashboard/Historial o excluir explícitamente esas funciones del lanzamiento; resolver acciones que aparentan funcionar.
6. V04: recursos locales válidos en el artefacto.
7. C04: matriz de permisos o decisión explícita de acceso uniforme.

### Etapa 2. Refactorizar con regresiones controladas

1. C05: extraer Request y caso de uso sin cambiar reglas de negocio.
2. C15/M02: limpieza de imports, scaffolding, tipos y formato en cambios separados.
3. V05/V08: simplificar presentación y componentes con pruebas de HTML e interacción.

### Etapa 3. Integridad, auditoría y escala

1. C07: regla de sesión, duplicados/legacy y restricción apropiada.
2. C06: concurrencia con conexiones independientes y posterior reducción del bloqueo amplio.
3. C08/C13/M01: decisiones de idempotencia, atribución y catálogo.
4. C11: paginación, EXPLAIN y perfil con datos representativos.
5. V07/V09: accesibilidad, cargas por página y assets de producción.

**Criterios para aprobar el alcance publicado:**

- No hay 500 por tipos de entrada manejables ni por recuperación del formulario.
- Login limita intentos y muestra feedback genérico sin exponer credenciales.
- Cada pantalla anunciada usa datos reales o presenta un estado de indisponibilidad inequívoco.
- Permisos definidos y probados según lo que decida dirección.
- Registro de Pan conserva atomicidad, cantidades, participantes y snapshots.
- Assets, build e interacción principal verificados en un entorno equivalente al despliegue.
- Se realiza smoke test con sesiones reales del driver productivo, sin usar datos de trabajo para ensayos destructivos.

No se estiman horas sin conocer disponibilidad del practicante, requisitos cerrados y entorno de despliegue. La limpieza de estilo puede tratarse en una tarea pequeña; concurrencia, permisos y módulos reales requieren diseño y aceptación funcional.

## 10. Evidencia de pruebas y revisión final

| Verificación | Comando/procedimiento | Resultado | Límite |
|---|---|---|---|
| Suite existente | `php tests/run-mariadb.php --compact --do-not-cache-result` | 141/141, 1.489 aserciones, salida 0 | MariaDB aislada; no driver de sesión productivo |
| Reproducciones de auditoría | `php tests/run-mariadb.php /tmp/panaderia-review/ReviewTest.php --compact --do-not-cache-result` | 12/12, 118 aserciones, salida 0 | Caracterizan defectos; no son correcciones |
| Sintaxis PHP | `php -l` sobre cada PHP en `app/` y `routes/` | 27 archivos sin fallos | No equivale a análisis estático completo |
| Estilo | `php vendor/bin/pint --test app/Http/Controllers app/Models` | FAIL en 8 archivos | Inspección, sin aplicar formato |
| Sintaxis JS | `node --check` sobre los cinco JS de `resources/js/` | Sin errores | No ejecuta DOM ni librerías en navegador |
| Rutas | `php artisan route:list --path=login -v`, variantes `produccion` y `history` | Middleware y handlers coherentes con lo descrito | Inspección local |
| Assets | Comprobación de existencia de rutas locales | Cuatro imágenes ausentes; video presente | No se inspeccionó artefacto externo |
| Diff | `git diff`, `git diff --check`, `git status` y comparación de archivos previos | Sin cambios de archivos existentes; solo el informe nuevo | Los archivos no versionados requieren revisión directa |

Pint identificó: `Controller.php`, `DashboardController.php`, `HistorialController.php`, `ProduccionController.php`, `Cargo.php`, `RolProduccion.php`, `Empleado.php` y `Rol.php`. Los motivos incluyen imports sin usar, espacios finales, indentación, separación de miembros y comas de arrays.

Las reproducciones temporales cubrieron:

1. TypeError por password como array.
2. Username como array aceptado por autenticación.
3. Error de login guardado pero invisible en HTML.
4. Doce intentos fallidos sin bloqueo HTTP de aplicación.
5. Cuatro variantes de old input de producción que producen 500.
6. Fecha no ISO admitida por POST y rechazada por GET.
7. Observación de 70.000 caracteres que falla en SQL con rollback.
8. GET de producción 404 cuando falta categoría Pan.
9. Reenvío idéntico que produce dos detalles.

Artefactos temporales de evidencia:

- `/tmp/panaderia-review/ReviewTest.php`: pruebas adicionales, sin incorporación al repositorio.
- `/tmp/sprint4-f42e8cacd85b`: servidor/base ficticia de la suite completa, detenido.
- `/tmp/sprint4-e945bb3fdf46`: servidor/base ficticia de las reproducciones, detenido.
- `/tmp/sprint4-5a93e2932c4d`: intento inicial bloqueado por sandbox, sin pruebas completadas.

Estos artefactos son temporales y pueden dejar de estar disponibles. El informe registra resultados y escenarios para poder convertirlos en regresiones permanentes cuando se autoricen cambios.

## 11. Código, documentación y pendientes al cierre

**CÓDIGO MODIFICADO:** ninguno. No se corrigieron los defectos ni se ejecutó Pint en modo de escritura. No se añadieron tests al proyecto.

**DOCUMENTACIÓN MODIFICADA:** se creó solamente `dato_optimizar/informe_revision_pre_lanzamiento.md`, autorizado expresamente por el encargo. Es un informe nuevo de auditoría, no una copia del Vault. No se modificó `mejora_asignado.md` ni ninguna otra nota existente.

La instrucción concreta de esta tarea fue no modificar archivos existentes. Por ello **no correspondió actualizar el Vault durante esta revisión**. Si se autoriza implementar después, los temas a contrastar con las notas originales serán autenticación/controladores en LARAVEL, bugs en PENDIENTES/Bugs.md, reglas de producción en PRODUCCION, permisos en PERSONAL Y USUARIOS y decisiones en DECISIONES/Decisiones Laravel.md.md. Antes de escribir habrá que inspeccionar su estructura y leer las notas completas; no se afirma haber hecho esa revisión documental en este encargo.

**PRUEBAS:** resultados y aislamiento en la sección 10. La suite aprueba el comportamiento ya cubierto; no certifica que los módulos de demostración estén completos ni que los defectos adicionales estén corregidos.

**PENDIENTES:** todos los hallazgos correctivos de la sección 3; decisión de alcance del lanzamiento, permisos, idempotencia, trazabilidad y contratos del catálogo; build/navegador, sesiones database, entorno de despliegue, carga y concurrencia. No quedó una autorización pendiente para terminar este informe. Implementar las recomendaciones requiere un encargo posterior; aquí se solicitó únicamente revisión.

## 12. Prompt original del solicitante

Se conserva literalmente el mensaje que originó esta revisión, incluidos su redacción y errores de escritura:

> actua como un desarrollador senior con mas de 10 años de experiencia que trabaja en una empresa regida para por mi y que se esta encargaando de revisar el codigo de un practicante y debe de dar su informe a su jefe(yo), no quiero que modifiques nada por el momento lo que quiero es que revices los controladores, modelos, views, para ver si hay codigo basura y se puede optimizar el codigo teniendo en cuenta las buenas practicas que se tiene en cuenta priorisa los controladores, sugiere mejoras del codigo, si recomiendas la creacion de una nueva capa para que ayuda a los controladores, da recomendacion de como mejorar, optimizar y adapatatarlo de mejor forma, es importante que noi modifiques los archivos solo se requiere una revicion detallada de lo mencionado de antemano, cuando acabes alli crea una nueva carpeta llamada dato_optimizar donde alli pondras todos los datos que encontraste en un archivo .md de forma detallada como si un empleado da un informe de su jefe para un sistema que se esta revisando para su lanzamiento ya a la vuelta a la esquina, aparte agrega este pront para saber de que fue lo que te pedi
