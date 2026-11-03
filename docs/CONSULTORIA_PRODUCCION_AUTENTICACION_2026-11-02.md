# Panadería RS — Consultoría técnica de Producción y autenticación

## 1. Identificación y alcance

- **Fecha:** 2 de noviembre de 2026.
- **Checkout revisado:** rama `master`, revisión `b6cd736`.
- **Origen:** auditoría de solo lectura solicitada por incidencias durante pruebas móviles.
- **Estado de las correcciones propuestas:** **PENDIENTE**. No se ha autorizado su implementación.
- **Autorización documental posterior:** crear este informe Markdown en `docs/`. No autoriza cambios de código, datos, configuración ni permisos.

Se revisaron rutas, controladores, Requests, Actions, modelos, consultas, Blade, JavaScript, recursos compilados locales y documentación relacionada. Se consultaron datos reales de la base local mediante operaciones de lectura. No se ejecutaron migraciones, seeders, compilaciones ni operaciones reales de login, registro o eliminación.

Este documento recoge evidencia de esta auditoría. Los resultados no certifican una instalación remota ni reconstruyen datos eliminados. Las referencias `archivo:línea` corresponden al checkout indicado.

## 2. Resumen ejecutivo

1. **La redirección actual no cumple el destino solicitado por rol.** Después de autenticar, cualquier usuario vuelve a la URL pendiente en la sesión o a Dashboard. Un Administrador puede terminar en Producción si esa URL estaba pendiente. Los Operadores también llegan a Dashboard cuando no hay destino pendiente.
2. **Empanada mixta está correctamente relacionada en los datos actuales.** Es el producto 30 de Bocadito; su lote existente está en `detalle_bocadito` y su cabecera también corresponde a Bocadito.
3. **No se reprodujo una mezcla persistida de categorías ni la eliminación de una tarjeta equivocada.** No debe declararse resuelto el incidente móvil ni atribuirlo al frontend sin evidencia adicional.
4. **El formulario y las tarjetas de Producción tienen contextos independientes.** Cambiar el formulario a Torta conserva los resultados previamente consultados, que pueden ser de Bocadito. Es un comportamiento confirmado que puede generar confusión, pero su relación con el incidente es una hipótesis.
5. **El borrado utiliza familia e ID.** La simulación con IDs numéricos iguales entre familias conservó la tarjeta correcta. Después del borrado, el foco pasa a la siguiente tarjeta; si esta es Torta, recibe el foco sin ser eliminada.
6. **Existen riesgos de clasificación manual y duplicación futura de productos.** No se encontraron esos problemas en los datos consultados. La edición protege categoría y unidad cuando existe producción asociada.

No se confirmó ningún hallazgo crítico. La redirección requiere una corrección de prioridad alta; la incidencia móvil permanece **EN INVESTIGACIÓN**.

## 3. Evidencia y límites de la verificación

### 3.1 Lectura de la base local

La conexión se realizó mediante PDO, sin arrancar Laravel. Se inició una transacción explícita `READ ONLY`, se ejecutaron consultas `SELECT` y se cerró con `ROLLBACK`. La conexión inicial falló dentro del sandbox; la repetición mediante la herramienta de escalamiento completó la lectura. No se imprimieron credenciales ni se consultaron hashes o contenido de sesiones.

| Verificación | Resultado observado |
|---|---|
| Categorías | Pan: ID 1; Torta: ID 2; Bocadito: ID 3 |
| Nombres duplicados de categorías | Ninguno |
| Productos consultados | 32, dentro del límite de 250 de la consulta |
| Duplicados por nombre normalizado y categoría | Ninguno |
| Nombres normalizados presentes en varias categorías | Ninguno |
| Cabeceras duplicadas por fecha, categoría y turno | Ninguna |
| Cuentas por rol, turno y estado | Un Administrador activo; un Operador activo de Mañana y otro de Noche |

La normalización usada para buscar duplicados compactó espacios y recortó extremos, comparando mediante la base de datos. Este control no determina si productos con nombres diferentes representan comercialmente el mismo producto.

| Familia | Detalles existentes | Producto ausente | Cabecera ausente | Producto de otra categoría | Cabecera de otra categoría | Producto y cabecera discrepan |
|---|---:|---:|---:|---:|---:|---:|
| Pan | 8 | 0 | 0 | 0 | 0 | 0 |
| Torta | 1 | 0 | 0 | 0 | 0 | 0 |
| Bocadito | 1 | 0 | 0 | 0 | 0 | 0 |

**Empanada mixta:** producto ID 30, activo, categoría ID 3 — Bocadito, unidad UNIDAD. El detalle encontrado es `detalle_bocadito.id = 1`, asociado a la cabecera 9 del 31/10/2026, también de categoría Bocadito. No se encontraron detalles actuales de productos cuyo nombre contenga «empanada» en Pan o Torta.

La ausencia de inconsistencias actuales no demuestra que nunca existieran. No se inspeccionaron respaldos, historial de cambios ni la sesión del teléfono para reconstruir el incidente.

### 3.2 Comprobaciones ejecutadas

| Comprobación | Resultado | Aislamiento y alcance |
|---|---|---|
| `node tests/JavaScript/ProduccionCantidadTest.mjs` | 23 aprobadas | Código real con dobles del DOM en memoria; sin navegador ni BD |
| `node tests/JavaScript/ConsultaParcialTest.mjs` | 3 aprobadas | DOM y transporte simulados; sin peticiones reales |
| `node tests/JavaScript/LoginTest.mjs` | 8 aprobadas | Interfaz simulada en memoria; sin autenticación real |
| Matriz del Redirector de Laravel | 9 comprobaciones aprobadas | Redirector y modelos reales, relaciones precargadas y sesión Array; sin bootstrap ni conexión BD |
| Borrado frontend con IDs iguales entre familias | 2 comprobaciones aprobadas | Script real en VM, DOM y `fetch` simulados; sin borrado real |
| Lectura de integridad local | Completada | Transacción READ ONLY y ROLLBACK |
| `git diff --check` al cerrar la auditoría | Sin incidencias | Checkout sin cambios antes de crear este informe |

Las nueve comprobaciones del Redirector confirman el comportamiento actual; no validan el requisito nuevo por rol. Las comprobaciones PHP y JavaScript adicionales se ejecutaron desde entrada estándar, sin crear archivos de prueba.

No se ejecutó la suite PHP que prepara bases de pruebas, porque implica migraciones y escrituras fuera del alcance de la auditoría. No se ejecutó un navegador físico, un recorrido HTTP completo de autenticación ni un borrado contra la base real.

## 4. Hallazgos detallados

### H1. Redirección sin distinción por rol

**Estado:** comportamiento actual **COMPROBADO**; corrección **PENDIENTE**.

**Gravedad:** alta.

**Origen confirmado:** backend, en la selección del destino; no se encontró evidencia de que sea un fallo de permisos.

| Referencia | Responsabilidad y evidencia |
|---|---|
| `routes/web.php:18–20` | GET/POST `/login` pasan por `guest` y `LoginController` |
| `app/Http/Controllers/LoginController.php:74–82`, `authenticate()` | Autentica cuentas activas, regenera sesión y ejecuta `redirect()->intended('dashboard')` |
| `bootstrap/app.php:30`, configuración de middleware | `redirectUsersTo('/dashboard')` envía al usuario ya autenticado a Dashboard al visitar login |
| `app/Models/Usuario.php:45–62` | Relación `rol()` y métodos `esAdministrador()`, `esOperador()`, `puedeAccederOperacion()` |
| `app/Models/Rol.php:9–11` | Nombres de rol Administrador y Operador |
| `app/Providers/AppServiceProvider.php:24–25`, `boot()` | Gates de operación y administración |
| `vendor/laravel/framework/src/Illuminate/Routing/Redirector.php:95–99`, `intended()` | Extrae `url.intended` de la sesión; usa el destino alternativo únicamente cuando no existe |

Los dos turnos no son roles diferentes: ambos usan Operador y se distinguen por `usuarios_sistema.turno_id`. La consulta agrupada confirmó una cuenta activa por turno y una cuenta Administrador activa.

| Destino pendiente | Administrador: resultado actual | Ambos Operadores: resultado actual |
|---|---|---|
| Ninguno | Dashboard | Dashboard |
| Producción | Producción | Producción |
| Dashboard | Dashboard | Dashboard |

**Hecho:** el flujo permite reproducir que un Administrador llegue a Producción. **Límite:** no se inspeccionó la sesión del incidente; no se afirma que su `url.intended` concreto haya sido comprobado.

**Corrección mínima propuesta:** aplicar una misma política de destino en el login exitoso y en la redirección de usuarios ya autenticados: Administrador → Dashboard; Operador → Producción. Para cumplir estrictamente la instrucción, resolver o descartar el destino pendiente que contradiga esa política. Cambiar solamente el argumento alternativo de `intended()` no basta. Conservar el tratamiento de roles no reconocidos y los Gates actuales.

**Verificación posterior:** matriz por rol y turno con y sin destino pendiente, acceso a `/` y `/login` con sesión abierta, logout, cuentas inactivas y acceso directo a módulos. El cambio de destino no debe conceder ni retirar permisos.

**Riesgo de regresión:** se altera intencionalmente el retorno a una página solicitada antes de autenticar. Las pruebas existentes que esperan Dashboard para Operador o preservan `intended` deberán ajustarse al nuevo contrato, manteniendo sus comprobaciones de seguridad.

### H2. Formulario y tarjetas con contextos independientes

**Estado:** comportamiento **COMPROBADO**; vínculo con el incidente **EN INVESTIGACIÓN**.

**Gravedad:** media por confusión funcional.

**Origen del comportamiento:** frontend y diseño actual de consultas independientes; no implica mezcla de datos.

| Referencia | Evidencia |
|---|---|
| `resources/js/produccion.js:282–327`, `mostrarCamposDe()` | Cambia familia, selección, campos, título y endpoint del formulario |
| `resources/js/produccion.js:319–320` | Explicita que los resultados consultados son independientes del lote en edición |
| `resources/views/produccion/index.blade.php:337–350` | Las tarjetas tienen filtros propios de fecha, turno y categoría |
| `resources/js/consultas-produccion.js:1–5` | Inicializa la consulta parcial de registros |
| `resources/js/consulta-parcial.js:21–29`, `consultar()` | Reemplaza exclusivamente la región de resultados recibida |
| `tests/JavaScript/ProduccionCantidadTest.mjs:376–383` | Comprueba que cambiar categoría conserva resultados consultados |

**Secuencia permitida:** consultar Bocadito → cambiar el formulario a Torta → conservar la tarjeta de Empanada mixta debajo. Esto puede parecer una mezcla si se interpreta el botón superior como filtro de toda la pantalla. No se acreditó que esa fuera la secuencia móvil reportada.

**Corrección mínima propuesta:** mantener la independencia actual y explicitar el contexto del formulario y el de la consulta aplicada. Evitar sincronizar filtros automáticamente sin una decisión funcional, pues cambiaría el comportamiento existente.

**Verificación posterior:** probar todas las transiciones de familia con resultados de otra familia, consultas pendientes y fallidas, paginación y formularios con datos. El formulario no debe enviar la categoría del filtro de resultados.

**Riesgo de regresión:** unificar ambos estados puede perder filtros, limpiar datos de registro o consultar una familia no solicitada. Los resultados conservados tras una consulta fallida deben seguir siendo identificables.

### H3. Empanada mixta mostrada o registrada como Torta

**Estado:** **EN INVESTIGACIÓN**; no reproducido como asociación incorrecta.

**Gravedad:** impacto potencial alto si se demostrara persistencia en otra familia. No hay corrupción actual confirmada.

**Origen del incidente:** indeterminado.

| Referencia | Control observado |
|---|---|
| `app/Http/Controllers/ProduccionController.php:34–39`, `index()` | Recupera productos activos y los agrupa por `categoria_id`, resolviendo las categorías por nombre |
| `app/Http/Controllers/ProduccionController.php:106–118` | `store()`, `storeTorta()` y `storeBocadito()` delegan en escritores específicos |
| `app/Models/Producto.php:30–38` | Relaciones por `categoria_id` y `unidad_medida_id` |
| `app/Http/Requests/RegistrarProduccionPanRequest.php:100–104` | Rechaza productos inactivos o ajenos a Pan |
| `app/Http/Requests/RegistrarProduccionPorCantidadRequest.php:31–36` | Valida categoría del endpoint y existencia del producto para Torta/Bocadito |
| `app/Actions/Produccion/RegistrarProduccionPan.php:29–42, 74–88` | Bloquea producto/categoría y comprueba pertenencia antes de registrar |
| `app/Actions/Produccion/RegistrarProduccionPorCantidad.php:23–43` | Torta/Bocadito comprueban estado y categoría del producto bajo transacción y bloqueo antes de escribir |
| `app/Queries/ConsultarHistorialProduccion.php:16–28, 47–61` | Consulta la tabla de cada familia y cabeceras compatibles; hidrata usando claves `familia:id` |
| `resources/views/components/registro-produccion.blade.php:3–18` | La etiqueta de familia se obtiene del tipo de detalle y el nombre de su producto relacionado |

La validación de pertenencia de Torta/Bocadito se completa en la Action: no debe confundirse la regla `exists:productos,id` del Request con el conjunto completo de controles.

**Punto de reproducción:** `resources/views/produccion/index.blade.php:163–169` entrega opciones de las tres familias en el mismo selector, con las ajenas deshabilitadas y sin `hidden` inicial. `resources/js/produccion.js:287–295` aplica después `disabled` y `hidden` y limpia la selección al cambiar de familia. Está comprobada la estructura; no se comprobó cómo se presentó el selector en el teléfono del incidente.

**Hipótesis abiertas:** interpretación de tarjetas de otra consulta; aparición de opciones ajenas deshabilitadas; ejecución incompleta de JavaScript; recursos distintos a los locales; datos históricos que ya no existen. No hay evidencia suficiente para elegir una como causa.

**Corrección mínima propuesta:** no reclasificar Empanada mixta ni cambiar los escritores actuales. Si se reproduce dentro del selector, corregir únicamente la presentación inicial o el filtrado que falle, conservando nombre del campo, IDs reales, endpoints y validaciones. Si se demuestra una relación histórica errónea, preparar una corrección de datos específica y autorizada por separado.

**Verificación posterior:** seleccionar Empanada mixta en Bocadito, recorrer las tres familias, revisar selección y payload, y comprobar que enviarla a Torta o Pan se rechaza sin residuos en una base aislada. Repetir con JavaScript deshabilitado o fallido y con navegación Atrás.

**Riesgo de regresión:** modificar el selector puede romper recuperación de `old()`, Pan LATA/UNIDAD, limpieza de campos y selección de productos. El filtrado visual nunca debe sustituir la comprobación del servidor.

### H4. Eliminación y cambios observados en la tarjeta Torta

**Estado:** borrado cruzado no reproducido; incidente **EN INVESTIGACIÓN**.

**Gravedad:** alta si se acreditara eliminación de otro registro; baja para el movimiento normal del foco descrito a continuación.

**Origen de un fallo de borrado:** no confirmado.

| Referencia | Evidencia |
|---|---|
| `routes/web.php:41–42` | DELETE `/history/{familia}/{detalle}` restringido a administración y familias válidas |
| `app/Http/Requests/EliminarRegistroProduccionRequest.php:9–16` | Exige permiso administrativo y confirmación aceptada |
| `resources/views/components/registro-produccion.blade.php:12, 78–86` | Publica familia e ID y construye la acción de la tarjeta correspondiente |
| `app/Actions/Produccion/EliminarRegistroProduccion.php:15–38`, `ejecutar()` | Resuelve modelo por familia, bloquea cabecera/detalle y borra ese detalle; conserva cabecera mientras tenga detalles |
| `app/Http/Controllers/HistorialController.php:40–48`, `destroy()` | Devuelve familia e ID eliminados y destino de retorno |
| `resources/js/historial.js:28–54` | Conserva referencia a la tarjeta elegida, valida familia/ID de respuesta, mueve foco, retira esa tarjeta y recarga resultados |
| `resources/views/produccion/registros.blade.php:10` | Renderiza tarjetas sin habilitar eliminación |

La consulta de Historial usa `familia:id` al hidratar, y el frontend de borrado conserva la identidad compuesta. IDs numéricos repetidos entre tablas no bastan para identificar una colisión.

**Simulación ejecutada:** dos tarjetas ficticias, Torta ID 1 y Bocadito ID 1. Al confirmar Bocadito, la petición interceptada fue `/history/bocadito/1`. Una respuesta válida retiró solo Bocadito. Una respuesta de familia Torta fue rechazada y no retiró ninguna. La simulación verifica el JavaScript; no prueba el DELETE HTTP ni las restricciones de la base.

**Cambio visual confirmado:** `historial.js:46` enfoca el `summary` de la tarjeta siguiente. Si la siguiente tarjeta es Torta, recibe el foco sin eliminarse. Además, `resources/js/tarjetas-produccion.js:1–11` mantiene un único detalle abierto dentro del mismo grupo. No se demostró que estos comportamientos expliquen el incidente.

No existe una ruta de borrado de productos en el checkout. Debe precisarse si «eliminarlo» significó eliminar un registro en Historial, limpiar el formulario o desactivar un producto. Eliminar un registro no elimina su producto del catálogo.

**Corrección mínima propuesta:** no cambiar el borrado sin reproducción. Si se confirma un problema exclusivamente visual de foco o recarga, limitar la corrección a ese comportamiento. Si se confirma un destino incorrecto, corregir la identidad de la petición sin sustituirla por un ID numérico aislado.

**Verificación posterior:** IDs iguales entre familias, varios detalles en una cabecera, última tarjeta de página, último detalle de una cabecera, fallo de red, respuesta con identidad incorrecta, permisos de Operador y recarga con filtros conservados. Usar exclusivamente fixtures y base aislada para los borrados.

**Riesgo de regresión:** retirar la tarjeta antes de confirmar respuesta, borrar cabeceras compartidas, perder filtros/paginación o modificar permisos. Deben conservarse los controles actuales.

### H5. Clasificación manual y duplicados futuros

**Estado:** riesgos identificados; duplicados o errores actuales no observados.

**Gravedad:** media.

**Origen:** reglas actuales del catálogo; no es prueba del origen del incidente móvil.

| Referencia | Evidencia |
|---|---|
| `app/Actions/Productos/ValidarDatosProducto.php:34–43`, `reglas()` | Valida nombre y categoría existente, sin unicidad del nombre ni clasificación semántica |
| `app/Actions/Productos/CrearProducto.php:15–19`, `ejecutar()` | Crea con los datos validados bajo bloqueo de categoría; no comprueba duplicidad del nombre |
| `app/Actions/Productos/ActualizarProducto.php:17–51`, `ejecutar()` | Coordina bloqueos y prohíbe cambiar categoría/unidad cuando existe producción en cualquiera de las tres familias |
| `database/migrations/2026_09_14_202623_create_productos_table.php:12–17` | Define nombre sin UNIQUE y relaciones por FK |
| `resources/views/productos/form.blade.php:31–38, 65, 75` | Expone categoría, protege estructura con historial y advierte que renombrar cambia el texto visible en producciones anteriores |

El usuario puede asignar manualmente una categoría comercial equivocada o crear nombres repetidos. Los escritores respetarán la categoría almacenada: no deducen la familia por el nombre. La integridad referencial no determina si la clasificación comercial elegida es correcta.

Las FK de los detalles garantizan referencias individuales, pero por sí solas no expresan igualdad de categoría entre producto, cabecera y familia de detalle. El flujo de registro revisado aplica esa comprobación en la aplicación. No se ha demostrado una vía HTTP que la omita.

**Corrección mínima propuesta:** confirmar la política de nombres repetidos antes de imponer unicidad. Si no se permiten, diseñar validación y protección concurrente compatibles con la comparación de nombres utilizada. Revisar la categoría comercial al dar de alta; no inferirla automáticamente por palabras del nombre. No fusionar ni reclasificar históricos sin autorización específica.

**Verificación posterior:** altas simultáneas equivalentes, edición hacia otro nombre existente, productos sin/con historial y carrera entre editar categoría y registrar producción.

**Riesgo de regresión:** rechazar variantes comerciales legítimas, romper importaciones o alterar históricos. La ausencia actual de duplicados no autoriza una limpieza de datos.

## 5. Móvil, renderizado y caché

- No se encontró una consulta backend diferente para móvil en el flujo revisado.
- El cambio de familia del formulario no realiza una petición de red; opera sobre el catálogo entregado por Blade.
- No se encontró almacenamiento local de categorías/productos ni registro de service worker en los recursos revisados.
- `app/Http/Middleware/PreventPageCaching.php:25–27` configura `private, no-store, no-cache, must-revalidate` para páginas cubiertas por autenticación/invitados.
- En el checkout local no existe `public/hot` ni caché de rutas/configuración en `bootstrap/cache`.
- El manifiesto local apunta a `assets/produccion-ByBTE-i9.js` y `assets/historial-BAKgv9Ub.js`. Se inspeccionaron sus controles de familia, selección y validación del borrado, así como la existencia de sus dependencias.

Estas comprobaciones no prueban qué archivos recibió el teléfono, ni el estado del navegador restaurado, del túnel o de una instalación distinta. No se reconstruyó el build; la inspección no equivale a una comparación reproducible de compilación.

Para continuar la investigación deben registrarse: pantalla exacta, navegador/versión, secuencia, producto/familia previa, uso de Atrás, primer error de consola y nombres de assets recibidos. La evidencia debe omitir credenciales, cookies y tokens. Comparar acceso local y túnel sin operaciones destructivas sobre datos reales.

## 6. Orden recomendado de intervención

| Prioridad | Trabajo propuesto | Estado y condición |
|---|---|---|
| Alta | Corregir destino de acceso por rol en login y middleware guest | PENDIENTE de autorización explícita |
| Alta | Reproducir Empanada mixta y la eliminación reportada en el dispositivo afectado | EN INVESTIGACIÓN; sin causa acreditada |
| Media | Aclarar contexto de formulario y consulta aplicada | PENDIENTE de autorización; conservar independencia funcional |
| Condicionada a evidencia | Ajustar selector o comportamiento visual de borrado | PENDIENTE; no aplicar un parche especulativo |
| Media | Definir política de duplicados y revisión de clasificación comercial | PENDIENTE de definición; no modificar datos |

No se recomienda una refactorización de Producción, cambiar permisos, introducir un rol por turno, reasignar Empanada mixta ni reemplazar las Actions existentes para resolver estos hallazgos.

## 7. Criterios de aceptación y regresión

Antes de ejecutar pruebas con escrituras, comprobar la conexión efectiva, el aislamiento de la base, sesiones, cachés y almacenamiento de fotos. No basta con que el nombre del entorno sea `testing`. Las pruebas deben utilizar fixtures ficticios y no alcanzar la base operativa.

- Administrador llega a Dashboard y ambos Operadores a Producción, incluso con destinos pendientes incompatibles.
- La página inicial por rol no modifica los permisos sobre Dashboard, Producción, Historial o administración.
- Pan conserva sus dos formas de registro, conversión de 18 latas por coche, snapshots y validación de cantidades/horas.
- Torta y Bocadito conservan cantidades directas, turnos y composición de participantes; Torta conserva forma, foto opcional y hora de vitrina.
- Un producto de Bocadito no puede registrarse por endpoints Pan/Torta, con rechazo sin residuos en base o almacenamiento aislados.
- Cambiar la familia no conserva una selección ajena ni envía campos incompatibles; los resultados consultados siguen siendo comprensibles.
- Eliminar un lote en pruebas afecta únicamente a su familia/ID y sus relaciones; otros detalles sobreviven y la cabecera se conserva mientras tenga registros.
- Filtros, conteo y paginación siguen coherentes después de consultar o eliminar.
- La validación fallida conserva correctamente los campos permitidos, sin reutilizar una foto ni producto de otra familia.
- Se completa la aceptación en escritorio y teléfono con assets identificados; el comportamiento móvil no se declara comprobado solo mediante dobles del DOM.

## 8. Cierre de la entrega documental

**CÓDIGO MODIFICADO:** ninguno. No se aplicaron correcciones, refactorizaciones, cambios de permisos, configuración o datos.

**DOCUMENTACIÓN MODIFICADA:** únicamente `docs/CONSULTORIA_PRODUCCION_AUTENTICACION_2026-11-02.md`, creado por petición expresa para dejar revisable esta consultoría. No se modificaron las notas originales del Vault ni `mejora_asignado.md`: la autorización posterior se limitó a este archivo en `docs/`. No es una copia del Vault ni reemplaza su documentación original.

**PRUEBAS:** evidencia heredada de la auditoría inmediatamente anterior: 34 casos JavaScript, 9 comprobaciones del Redirector y 2 del borrado frontend aprobados en memoria, más lectura real READ ONLY. Esta entrega solo crea y revisa el informe; no repite pruebas de aplicación ni consultas a la base. La revisión documental y `git diff --check` se realizan después de escribirlo.

**PENDIENTES:** autorización de las correcciones; reproducción del incidente móvil; pruebas HTTP y de persistencia en aislamiento después de corregir; aceptación en dispositivo físico. El alcance documental no permite declarar resueltos los incidentes. No se solicitó autorización de escritura en el Vault.
