# Refactorización progresiva de Producción — Sprint 5

- **Proyecto:** Panadería RS, checkout `panaderia-rs`.
- **Fecha:** 8 de octubre de 2026.
- **Referencia inicial:** commit `e1365fd`; el informe anterior era un archivo no versionado que ya existía y se preservó.
- **Alcance autorizado:** Producción de Pan, extracción de responsabilidades, seguridad de entrada, regresiones y documentación nueva.
- **Estado:** IMPLEMENTADO Y COMPROBADO en pruebas HTTP y persistencia MariaDB aislada, con los límites de la sección 8.
- **Informe de referencia:** [Auditoría previa al lanzamiento](informe_revision_pre_lanzamiento.md), conservada íntegramente.

## 1. Estado inicial

Antes de editar se revisaron el estado de Git, el controlador completo, la vista y su JavaScript, rutas, modelos, migraciones de cabecera/detalle/pivot/catálogos y las pruebas del flujo. Se confirmó la suite inicial: **141 pruebas, 1.489 aserciones, todas aprobadas**. No había cambios de código previos en el árbol de trabajo; únicamente estaba el informe de auditoría sin versionar.

`ProduccionController` tenía 251 líneas. Su problema principal no era el tamaño aislado, sino la mezcla de responsabilidades:

| Bloque inicial | Responsabilidad real | Destino final |
|---|---|---|
| Reglas, mensajes y atributos de `store()` | Validación de la forma de la solicitud HTTP | `RegistrarProduccionPanRequest` |
| Fecha `date` y posterior interpretación con Carbon | Contrato y normalización de entrada | Fecha ISO explícita en el Request; ya no se interpretan formatos ambiguos |
| Old input, defaults y filtros de `index()` | Contexto HTTP de consulta y recuperación | `ConsultarProduccionRequest` para fecha/turno; Blade para recuperar el detalle seguro |
| Categoría, producto activo y unidad válida | Reglas con estado actual del catálogo | Action, dentro de la transacción |
| Maestro, Ayudantes, duplicados y roles | Reglas del equipo del detalle | Métodos privados de la Action |
| Bloqueos de categoría, producto y cabecera | Concurrencia del caso de uso | Action, conservando orden y alcance |
| Lectura de catálogos y tarjetas | Consultas pequeñas de presentación | Permanecen en `index()` |
| Creación/reutilización de sesión, detalle y pivots | Persistencia coordinada | Action y relaciones Eloquent existentes |
| `QueryException`, reporte y feedback HTML | Traducción del fallo de persistencia a respuesta HTTP | Permanece en `store()` |
| Redirect, éxito y vista | Respuesta HTTP | Controller |

El esquema existente confirmó:

- `produccion.fecha` es DATE y el modelo la castea como fecha.
- `produccion.categoria_id` y `produccion.turno_id` son nullable por la transición histórica; no hay UNIQUE de identidad de sesión.
- `detalle_pan.cantidad` es INT firmado, con CHECK de cantidad positiva.
- El detalle conserva FK de producto, unidad y turno.
- El pivot usa clave primaria compuesta detalle/empleado y FK de rol; el modelo personalizado se usa mediante `using()`.
- `observacion` es TEXT nullable.
- El factor de producto y su snapshot son INTEGER nullable, sin backfill obligatorio.

Se contrastaron especialmente los hallazgos C03, C05, C06, C07, C09 y C10 del informe previo. C03 y C10 requerían corrección; C05 justificaba la extracción; C06/C07 debían preservarse como decisiones pendientes. C09 se atendió en el límite técnico de observación, sin inventar un máximo operativo de participantes.

## 2. Archivos creados

Lista exacta de archivos nuevos de esta tarea:

| Archivo | Motivo |
|---|---|
| `app/Http/Requests/RegistrarProduccionPanRequest.php` | Encapsular reglas HTTP, mensajes y atributos amigables del POST |
| `app/Http/Requests/ConsultarProduccionRequest.php` | Validar contexto fecha/turno y mantener recuperación y avisos del GET |
| `app/Actions/Produccion/RegistrarProduccionPan.php` | Ejecutar el registro de Pan con reglas y persistencia atómica, sin Request HTTP |
| `tests/Feature/RegistrarProduccionPanActionTest.php` | Probar el caso de uso directamente, independientemente del controlador |
| `dato_optimizar/informe_refactorizacion_produccion_sprint5.md` | Documentar implementación, decisiones, pruebas y aprendizaje |

No se crearon Repository, Query Object, DTO, interfaces, nuevos scopes, Services, paquetes ni migraciones.

## 3. Archivos modificados

Lista exacta de archivos existentes modificados:

| Archivo | Cambio |
|---|---|
| `app/Http/Controllers/ProduccionController.php` | Inyectar Requests y Action, mantener consultas y respuesta HTTP, añadir retornos tipados y retirar métodos vacíos sin rutas |
| `app/Models/DetallePan.php` | Añadir constantes nombradas para latas por coche y capacidad existente de cantidad |
| `resources/views/produccion/index.blade.php` | Recuperar old input seguro y usar la constante para límites, texto y representación de cantidades |
| `resources/js/produccion.js` | Leer del formulario el factor publicado por Blade; retirar el literal duplicado de la ayuda visual |
| `tests/Feature/ProduccionPanStoreTest.php` | Añadir regresiones permanentes de HTTP/old input, fechas, tamaño de observación y prioridad del contexto POST |

El cambio en JavaScript fue necesario para centralizar las 18 latas por coche; no cambió la regla ni el diseño. El build recompiló las entradas existentes a `public/build`, que es un artefacto ignorado por Git; no modificó fuentes de otros módulos.

No se modificaron rutas, migraciones, seeders, otros modelos, Dashboard, Historial, Torta, Bocadito, Pedidos, CSS, sidebar, usuarios ni recuperación de contraseña. Las relaciones y claves de los pivots permanecen intactas.

La limpieza de formato se limitó al test de Producción que ya se estaba editando. Pint ajustó una lambda y unos espacios/líneas en blanco; no se eliminaron pruebas anteriores ni se alteraron sus expectativas.

## 4. Arquitectura resultante y flujo

```text
app/
├── Http/
│   ├── Controllers/
│   │   └── ProduccionController.php
│   └── Requests/
│       ├── RegistrarProduccionPanRequest.php
│       └── ConsultarProduccionRequest.php
├── Actions/
│   └── Produccion/
│       └── RegistrarProduccionPan.php
└── Models/
    └── DetallePan.php y relaciones existentes
```

### 4.1 POST de registro

```text
Formulario de Pan
  → ruta POST /produccion y middleware auth
  → RegistrarProduccionPanRequest: validar estructura/tipos
  → ProduccionController::store()
  → RegistrarProduccionPan::ejecutar(datos validados, usuarioId)
      → transacción
      → catálogos y producto, con locks existentes
      → reglas de negocio
      → cabecera nueva o reutilizada
      → detalle con campos derivados
      → participantes mediante Pivot personalizado
      → commit, o rollback si hay una excepción
  → Controller: redirect a fecha/turno de la sesión y mensaje de éxito
  → GET de consulta y Blade: tarjetas y formulario limpio
```

Laravel resuelve y valida el Form Request **antes de entrar al método del controlador**. Por eso `store()` recibe una solicitud con estructura básica válida y utiliza `$request->validated()`. No necesita volver a escribir en el controlador las reglas de arrays, enteros, existencia de IDs o formato de fecha.

El contenedor de Laravel puede construir la Action concreta sin un binding ni una interfaz nueva. El controlador le pasa datos y un identificador entero de usuario; la Action no recibe sesiones, redirects ni un objeto `Request`.

La transacción permanece en la Action porque guardar cabecera, detalle y equipo como una sola operación es una responsabilidad del caso de uso. Si mañana otro punto autorizado invoca esa misma Action con datos previamente validados, obtiene esa misma coordinación de persistencia.

### 4.2 GET y recuperación de errores

`ConsultarProduccionRequest` prepara el contexto HTTP: old input de fecha/turno tiene prioridad sobre query string; si no se indica fecha, usa la fecha actual; el turno de consulta sigue siendo opcional.

Valida la forma original antes de sanear valores para HTML. Si el filtro es inválido, marca `consultaValida()` como false y devuelve el aviso existente mediante `errorConsulta()`. El controlador no carga resultados de sesión en ese caso.

**Esta personalización es intencional:** el GET previo devolvía la misma página con HTTP 200 y un aviso. Un Form Request convencional lanzaría una excepción y redirigiría, cambiando ese comportamiento y pudiendo volver a la misma URL inválida. Por ello este Request sobrescribe `failedValidation()`. `filtros()` devuelve valores seguros para presentación, pero no sustituye la comprobación `consultaValida()` antes de consultar resultados. No se debe asumir que una cadena saneada es un filtro validado ni usar `validated()` cuando este GET ha fallado.

Las verificaciones `exists:turnos,id` siguen siendo validación HTTP de referencia; el Request no consulta sesiones/productos ni escribe datos. El Controller conserva sus consultas pequeñas y el eager loading de producto/empleados.

Los Requests devuelven true en `authorize()`: la autenticación existente continúa en las rutas. Esto no implementa permisos por rol ni cambia quién podía registrar Pan.

### 4.3 Blade y old input

`old()` puede contener entradas que precisamente fueron rechazadas por no tener el tipo correcto. El rechazo de un array en POST no impide que el array quede temporalmente en la sesión para recuperar el formulario.

La vista ahora:

1. Comprueba que el detalle anterior sea un array antes de leer sus campos.
2. Recupera producto, coches, latas y observación solo si son escalares; los convierte a string para HTML. Un valor compuesto queda vacío.
3. Comprueba que participantes sea un array y conserva únicamente entradas estructuralmente recuperables, con IDs escalares.
4. Mantiene el renderizado por catálogo, los errores y los campos válidos.
5. Conserva escaping Blade; no muestra arrays como JSON ni inserta HTML sin escapar.

Sanear la presentación no convierte un valor inválido en dato persistible. La única entrada de escritura del controlador es la validada por el Request. La vista sigue mostrando los errores originales del rechazo.

Esta defensa pequeña permanece junto a la recuperación del formulario en Blade. No se creó una clase nueva de presentación ni se movieron campos de POST al Request de consulta, cuyo alcance es fecha/turno.

## 5. Código movido y organización de la Action

El Controller quedó en **73 líneas**, frente a 251 iniciales. Las consultas de lectura no se envolvieron en otra capa. `store()` invoca el caso de uso, traduce el error SQL del formulario y responde.

La Action tiene 150 líneas repartidas entre un método coordinador y etapas privadas. No es un servicio general de Producción: solo registra un detalle de Pan en su sesión.

| Método | Responsabilidad |
|---|---|
| `ejecutar()` | Abrir transacción, coordinar etapas y devolver Produccion |
| `obtenerCategoriaPan()` | Buscar y bloquear la categoría usada por el escritor |
| `obtenerProducto()` | Buscar producto con unidad y conservar el lock que estabiliza el snapshot |
| `validarReglas()` | Acumular errores de categoría/producto/unidad/cantidad y equipo antes de escribir |
| `erroresDeParticipantes()` | Resolver roles de catálogo, duplicados y composición Maestro/Ayudantes |
| `resolverCabecera()` | Buscar máximo dos sesiones, rechazar duplicadas y reutilizar/crear cabecera |
| `registrarDetalle()` | Crear un lote nuevo con unidad, turno, cantidad y snapshot derivados |
| `registrarParticipantes()` | Asociar empleados y roles mediante attach y Pivot personalizado |

La consulta breve de roles se mantiene en la coordinación y se ejecuta en el mismo punto relativo al código anterior. La regla de participantes sigue siendo específica y suficientemente pequeña para vivir en la Action; no se creó `AsignarEquipoProduccion` por anticipado.

La Action espera forma y tipos ya validados. Sus pruebas directas fabrican datos con esa estructura. Un futuro consumidor no HTTP deberá cumplir ese contrato antes de invocarla; no debe enviar cualquier array ni suponer que se validan allí todos los campos de transporte.

Se retiraron `show`, `edit`, `update` y `destroy` vacíos tras comprobar que no estaban vinculados a rutas ni consumidores en el alcance inspeccionado. No se implementó edición ni borrado de producción.

## 6. Reglas de negocio conservadas

| Regla | Comportamiento conservado y lugar actual |
|---|---|
| Maestro | Exactamente uno por detalle; Action, con rol resuelto por nombre de catálogo |
| Ayudantes | Al menos uno; se permiten más de dos; sin máximo operativo nuevo |
| Roles | Solo Maestro/Ayudante para Pan; no se fijan IDs de roles |
| Empleados | No pueden repetirse dentro del mismo detalle; no se deriva rol desde cargo |
| Producto | Debe existir, estar activo y pertenecer a categoría Pan |
| Turno | El usuario elige una vez; `detalle_pan.turno_id` se copia de `produccion.turno_id` |
| Unidad | `detalle_pan.unidad_medida_id` se deriva de producto; no hay segundo selector |
| Unidad inválida | Se conserva el error controlado por producto antes de la primera escritura |
| Factor | El valor actual de `producto.panes_por_lata` se copia a `panes_por_lata_usado`, incluido NULL |
| Historia | Cambiar producto después no modifica snapshots anteriores |
| Cantidad | Total entero de latas: coches × latas por coche + latas adicionales |
| Límites numéricos | Coches no negativos, latas adicionales 0–17, total positivo hasta INT firmado |
| Cabecera | Identidad de búsqueda fecha/categoría/turno; nueva o reutilizada |
| Autor | El registrador inicial de la cabecera se conserva al agregar nuevos lotes |
| Lotes | Repetir el producto crea detalles independientes; no se suman ni deduplican |
| Duplicados de sesión | Se detectan y rechazan; no se elige una cabecera arbitrariamente |
| Atomicidad | Cabecera/detalle/pivots de la petición se guardan juntos o se revierten |
| Locking | Categoría → producto → cabeceras; roles/unidad se leen como antes, sin añadirles locks |
| Pivots | Se conserva using(DetallePanEmpleado) y attach individual con eventos |
| Lecturas | Se conserva eager loading de producto y empleados para tarjetas |

### 6.1 Significado y fuente del número 18

`DetallePan::LATAS_POR_COCHE = 18` expresa **cantidad de latas por coche**. No es panes por lata ni una equivalencia general de unidades. La cantidad canónica persistida en `detalle_pan.cantidad` continúa en latas.

La constante se ubicó en el modelo del detalle de Pan, como solución pequeña dentro del contexto que interpreta esa cantidad. La Action la usa para calcular; el Request deriva su límite de latas adicionales; Blade la usa al presentar cantidades y la publica en `data-latas-por-coche`; JavaScript lee ese atributo para la ayuda visual. No mantiene un segundo literal de 18 en el flujo de producción.

`DetallePan::MAX_CANTIDAD_LATAS = 2147483647` nombra la capacidad ya utilizada del INT firmado. El máximo de coches sigue siendo 119304647, derivado mediante intdiv. La comprobación final de total sigue en la Action para rechazar combinaciones que, aun cumpliendo cada límite de campo, desbordan la cantidad.

Estas constantes no crean una configuración editable. Cambiar la equivalencia en el futuro requerirá una decisión de negocio y análisis histórico; esta tarea mantiene el valor actual.

### 6.2 Concurrencia conservada

El bloqueo de categoría sigue serializando escritores de Pan, incluso de sesiones distintas. No se consideró una optimización segura retirar ese lock sin la garantía de sesión y pruebas concurrentes correspondientes.

Se conservaron `DB::transaction()` y los tres puntos de `lockForUpdate()`, incluida la consulta de máximo dos cabeceras. No se añadieron retries, UNIQUE, índices ni nuevos locks. Las pruebas secuenciales comprueban persistencia y rollback; no demuestran ausencia de carreras bajo conexiones concurrentes.

## 7. Bugs corregidos dentro del alcance

### 7.1 Recuperación de formulario sin HTTP 500 — C03

**Estado: IMPLEMENTADO Y COMPROBADO** en regresiones HTTP.

El recorrido POST inválido → redirect → GET funciona con arrays en producto/coches/latas/observación. La regresión también cubre estructuras inválidas de detalle/participantes/IDs y arrays en fecha/turno: **12 casos** de recuperación responden 200, muestran errores y no dejan escrituras.

Los campos compuestos rechazados no se convierten a JSON ni se muestran. Los datos válidos y la fecha del formulario se conservan. La regresión previa existente de conservación de participantes completos continúa aprobada.

### 7.2 Fecha coherente con la UI — C10

**Estado: IMPLEMENTADO Y COMPROBADO.**

POST ahora exige `date_format:Y-m-d`, como GET y el input date. Se rechazan `10/05/2026`, timestamps textuales, fechas no completadas con ceros y arrays. Las fechas imposibles también continúan rechazándose por las pruebas previas.

No se cambió la aceptación de días futuros o pasados válidos; cerrar jornadas o permitir correcciones retroactivas no se decidió aquí. Se eliminó la interpretación flexible con Carbon del POST porque el contrato ahora es explícito.

### 7.3 Observación demasiado grande — parte de C09

**Estado: IMPLEMENTADO Y COMPROBADO** para la capacidad técnica de TEXT.

El Request limita la observación a **65.535 bytes**, usando strlen, antes de invocar la Action. Se comprobó el rechazo por campo de ASCII y Unicode de dos/cuatro bytes que exceden ese tamaño. El máximo permitido también se persiste sin truncar, tanto con ASCII como con Unicode.

Esto evita que una cadena demasiado grande llegue a SQL y termine en un error genérico de producción. Es un límite respaldado por el esquema y por pruebas con MariaDB, no una longitud operativa elegida arbitrariamente.

El límite se mantiene en backend; `maxlength` de HTML cuenta caracteres y no reemplazaría la comprobación de bytes. No se añadió un máximo de ayudantes ni una regla nueva de tamaño de equipo.

### 7.4 Prioridad del contexto del formulario

La prioridad old input POST sobre filtros GET era comportamiento existente y se conserva. Una nueva regresión comprueba que query strings inválidas no reemplazan el contexto que el usuario necesita corregir ni ocultan su error de negocio.

## 8. Pruebas, comandos y límites

### 8.1 Resultados

| Momento/verificación | Comando o alcance | Resultado |
|---|---|---|
| Suite inicial | `php tests/run-mariadb.php --compact --do-not-cache-result` | **141/141**, **1.489 aserciones**, salida 0 |
| Reproducción antes de corregir | Mismo runner con `--filter='recupera el formulario después\|rechaza fechas POST\|rechaza observaciones que exceden'` | 19 casos; 8 aprobaron y 11 fallaron; se identificaron defectos y un dataset nuevo incorrecto |
| Verificación intermedia tras extracción | Suite completa | 162/163, 1.750 aserciones; el único fallo restante era ese dataset nuevo |
| Suite ampliada con Action | Suite completa | **179/179**, **1.857 aserciones**, salida 0 |
| Suite final tras formato limitado | Suite completa | **179/179**, **1.857 aserciones**, salida 0 |
| PHP | `php -l` sobre los siete PHP nuevos/modificados de implementación y tests | Siete archivos sin errores |
| JavaScript | `node --check resources/js/produccion.js` | Correcto |
| Pint, inspección inicial | `php vendor/bin/pint --test` sobre los PHP de esta tarea | Detectó formato en ProduccionPanStoreTest; los otros archivos inspeccionados pasaron |
| Pint, aplicación limitada | `php vendor/bin/pint tests/Feature/ProduccionPanStoreTest.php` | Solo ajustes de formato en ese archivo |
| Pint final | Mismo `--test` con siete PHP del alcance | Correcto |
| Bundle | `npm run build` | Correcto; sin instalar paquetes |
| Rutas | `php artisan route:list --path=produccion -v` | GET/POST conservan web/auth y sus nombres/handlers |
| Diff | `git diff`, `git diff --check`, inspección de archivos nuevos y comparación SHA-256 de archivos previos | Solo los cinco archivos existentes declarados cambiaron; informe anterior y mejora_asignado intactos |

El filtro mostrado en la tabla usa `|` como alternativa de la expresión regular; el comando efectivamente ejecutado fue:

```bash
php tests/run-mariadb.php --compact --do-not-cache-result --filter='recupera el formulario después|rechaza fechas POST|rechaza observaciones que exceden'
```

La lista exacta usada para Pint final y PHP lint es:

```text
app/Http/Controllers/ProduccionController.php
app/Http/Requests/RegistrarProduccionPanRequest.php
app/Http/Requests/ConsultarProduccionRequest.php
app/Actions/Produccion/RegistrarProduccionPan.php
app/Models/DetallePan.php
tests/Feature/ProduccionPanStoreTest.php
tests/Feature/RegistrarProduccionPanActionTest.php
```

Se añadieron **38 casos de prueba**: 22 en ProduccionPanStoreTest y 16 en RegistrarProduccionPanActionTest. Se mantuvieron los 141 casos previos. La suite pasó de 1.489 a 1.857 aserciones.

Durante la preparación, Pest interpretó `['2026-10-05']` como un conjunto de argumentos, entregando al test una cadena ISO válida en vez del array malformado buscado. Se corrigió el dataset a `[['2026-10-05']]`. Ese fallo era de la prueba nueva y no del código de fechas; no se eliminó una expectativa para hacer pasar la suite. Los otros diez fallos iniciales correspondían a los cuatro campos de old input, tres formatos no ISO y tres observaciones excesivas.

Vite emitió el aviso de fallback de fuentes optimizado sin el paquete opcional fontaine. El build terminó correctamente; no se añadieron dependencias ni se cambió su configuración por ese aviso.

### 8.2 Cobertura funcional

| Comportamiento | Evidencia |
|---|---|
| Forma/tipos/IDs del POST | Tests previos de entradas inválidas y nuevas regresiones de recuperación |
| Fecha ISO | Cuatro casos nuevos y regresiones previas de fecha válida/imposible |
| Old input de cuatro campos vulnerables | POST rechazado, GET 200, error visible y campo compuesto vacío |
| Participantes malformados | Nuevos casos de estructura e IDs; sin 500 ni escrituras |
| Maestro/Ayudantes | HTTP previo y siete escenarios directos de reglas de la Action |
| Más de dos ayudantes | Test directo válido con un Maestro y cinco Ayudantes |
| Turno/unidad/factor | Tests HTTP previos más registro directo con factor 12/NULL |
| Cabecera/lotes/autor | Tests HTTP previos y test directo de dos registros/snapshots |
| Cabeceras duplicadas | Tests HTTP previos y rechazo directo de la Action |
| SQL y eventos de pivots | Tests anteriores de fallo de detalle/pivot; nuevos fallos en segundo Pivot de sesión nueva/existente |
| Excepción inesperada | Nuevos escenarios de RuntimeException que se propaga y revierte escrituras |
| Texto | ASCII y Unicode aceptados/rechazados por bytes; observación "0" preservada |
| Filtros de lectura | Pruebas existentes de fechas/turnos manipulados, sin turno y separación de tarjetas; nueva prioridad de old input |

Las nuevas pruebas directas no fabrican un Request. Esto protege que Maestro/Ayudantes y atomicidad pertenecen a la Action, y no funcionan únicamente porque se ejecutó un controlador.

### 8.3 Aislamiento y límites

- Se usó el runner existente `tests/run-mariadb.php`, con servidores desechables sin red, sockets en `/tmp/sprint4-*` y bases ficticias verificadas por `IsolatedMariaDb`.
- La creación de sockets necesitó ejecución mediante la herramienta de escalamiento porque el sandbox restringe esa operación. Las instancias se detuvieron al acabar.
- No se consultó, migró ni sembró la base de trabajo. Las migraciones utilizadas son las existentes y solo se aplicaron en las bases temporales. No se inspeccionó el contenido de `.env` ni se documentaron credenciales, tokens o hashes reales.
- La suite usa transacciones exteriores de aislamiento por caso; las llamadas a la Action prueban su transacción anidada y el rollback de la operación. Los tests de migraciones existentes se ejecutaron con su propio aislamiento.
- Sesión/caché son array durante pruebas. Esto no valida el driver database de sesiones productivas.
- Los tests HTTP desactivan Vite para renderizar Blade. El build separado comprueba la compilación; `node --check` verifica sintaxis.
- No se ejecutó un navegador real ni un test de teclado/DOM JavaScript en este entorno. La interacción visual y el uso del bundle publicado requieren smoke test posterior.
- No se hicieron pruebas concurrentes con conexiones independientes ni benchmarks. La conservación de locks se verificó por diff/código; no se certifica su desempeño o unicidad bajo otros escritores.
- Los artefactos de ejecución en storage y public/build son ignorados por Git y no son cambios de código fuente.

Instancias temporales de referencia, detenidas:

- Inicial: `/tmp/sprint4-5951442458f6`.
- Reproducción previa: `/tmp/sprint4-8eea5fe486bd`.
- Verificación intermedia: `/tmp/sprint4-03e179983c17`.
- Suite ampliada: `/tmp/sprint4-471e5c45b6d9`.
- Suite final tras formato: `/tmp/sprint4-7981364c0cf2`.

Los directorios temporales no son entregables permanentes. Las regresiones nuevas sí están incorporadas a los tests del repositorio para poder repetir los escenarios.

## 9. Decisiones que no se tomaron

Todas estas decisiones continúan **PENDIENTES**:

- Identidad y UNIQUE SQL definitivo de sesiones, especialmente familias/turnos nullable y datos legacy.
- Reducción del bloqueo de categoría, retries o nueva estrategia de concurrencia.
- Idempotencia de envío: repetir el mismo producto sigue creando lotes independientes.
- Matriz de permisos por rol: auth no equivale a autorización granular.
- Códigos estables de categorías/roles: se conservan nombres Pan/Maestro/Ayudante.
- Semántica final de unidades, equivalencias y temporada del producto.
- Obligación/positividad del factor de panes por lata: NULL sigue válido.
- Auditoría del registrador/hora por detalle: se conserva el autor original de cabecera.
- Máximo operativo de participantes u observaciones por debajo de la capacidad técnica.
- Paginación, índices y perfil de consultas.
- Mejoras de Login, Dashboard, Historial, Pedidos o familias futuras de la auditoría previa.

Los límites técnicos de entrada implementados y el contrato ISO no definen ninguna de esas reglas de negocio nuevas. No se cambió el esquema para resolverlas anticipadamente.

## 10. Pendientes recomendados, por prioridad

| Prioridad | Pendiente | Razón/criterio para abordarlo |
|---|---|---|
| P1 de verificación | Smoke test del formulario y bundle en navegador con sesión productiva equivalente | Build y tests HTTP no ejecutan la interacción JavaScript real |
| P1 de negocio | Confirmar permisos y alcance visible del lanzamiento | La auditoría global mantiene riesgos de login y pantallas de demostración, fuera de este encargo |
| P2 | Definir unicidad de sesión y revisar datos históricos | Necesario antes de retirar el bloqueo amplio o introducir UNIQUE |
| P2 | Concurrencia real con conexiones independientes | Medir carreras y bloqueo antes de optimizar |
| P2 | Decidir idempotencia y trazabilidad por detalle | Evitar envíos duplicados accidentales y atribuciones no demostradas |
| P2 | Contratos del catálogo/unidades/factores | Centralizar futuras reglas sin inventar equivalencias ni backfill |
| P2 | Máximos operativos y consultas por volumen | Conservar posibilidad de varios ayudantes; optimizar según evidencia |
| P3 | Evaluar presentación/formulario en componentes | Solo cuando facilite mantenimiento o exista repetición suficiente |

No se continuará automáticamente con esas tareas ni con otros módulos después de esta entrega.

## 11. Explicación para aprendizaje y cierre documental

**Un Form Request cuida la entrada.** `integer`, `array`, `exists` y `date_format` responden a “¿puedo interpretar esta petición con la estructura esperada?”. No responden a “¿este producto está activo ahora?” o “¿el equipo tiene el Maestro requerido?”. Esas preguntas se resuelven en la Action con los catálogos leídos dentro de la transacción.

**El Controller cuida la conversación HTTP.** Recibe lo validado, identifica al usuario, invoca el caso de uso y elige redirect/vista/feedback. Mantener el catch SQL allí permite que la Action funcione sin conocer si su caller utiliza un formulario HTML. Una ValidationException de negocio conserva errores por campo compatibles con Laravel.

**La Action cuida la operación completa.** Cabecera, detalle y participantes son una sola intención del usuario. La transacción evita que un detalle quede sin equipo cuando falla un pivot. Los tests que provocan fallo en el segundo pivot comprueban precisamente que no quedan las escrituras previas de ese envío.

**Los Models cuidan las relaciones con el esquema.** No se reimplementó Eloquent ni se envolvió cada consulta en Repository. Se mantuvieron relaciones y el Pivot personalizado; attach sigue disparando sus eventos. Las constantes del detalle explican cómo se interpreta su cantidad, sin configurar un sistema nuevo.

**Blade cuida la presentación, incluso ante datos rechazados.** La validación fallida deja old input para ayudar al usuario. Eso exige distinguir “dato que quiero recuperar” de “dato cuyo tipo puedo renderizar con seguridad”. Vaciar un array malformado y mantener su mensaje es mejor que convertirlo en una cadena inventada.

**No capturar todo también es parte del diseño.** La Action deja propagar QueryException y errores de programación. Laravel/DB revierten la transacción; el Controller convierte solo el error SQL en el feedback existente. Una RuntimeException no se oculta como si fuera un simple error de usuario.

**CÓDIGO MODIFICADO:** cinco archivos existentes y cuatro archivos nuevos de código/tests, detallados en las secciones 2 y 3. Refactorización y correcciones IMPLEMENTADAS Y COMPROBADAS dentro del aislamiento indicado.

**DOCUMENTACIÓN MODIFICADA:** únicamente este nuevo informe. No se sobrescribió la auditoría anterior. Por la restricción expresa de esta tarea, **no correspondió editar el Vault de Obsidian**. `mejora_asignado.md` trata el seguimiento de autenticación y no se modificó: no se implementó login ni se amplió el encargo. No se copió documentación original del Vault al repositorio.

**PRUEBAS:** suite inicial y suite final, lint, Pint, build, rutas y diff en la sección 8. No se afirma verificación en producción ni navegación real.

**PENDIENTES:** secciones 9 y 10. No quedó una autorización pendiente para terminar este alcance; las ejecuciones de MariaDB aislada fueron autorizadas mediante la herramienta correspondiente.
