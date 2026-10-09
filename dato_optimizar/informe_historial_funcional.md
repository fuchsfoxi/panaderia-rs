# Informe - Historial funcional

Fecha: 2026-10-09. Proyecto: `panaderia-rs`.

Estado: **IMPLEMENTADO Y COMPROBADO en MariaDB aislada**. La tarea continúa después de una interrupción: los cambios de implementación y las pruebas ya estaban guardados; se completa la documentación sin repetir pruebas ni hacer commit.

## 1. Estado inicial encontrado

`GET /history`, con nombre `history.index`, ya estaba protegido por `auth`. `HistorialController@index` únicamente devolvía `history.index`, sin consultar la base de datos. La página era una maqueta con tarjetas estáticas; su JavaScript cambiaba categorías y escribía los filtros en consola.

Antes de implementar se inspeccionaron las rutas, `HistorialController`, `ProduccionController`, `RegistrarProduccionPan`, ambos Requests de Producción, los modelos y pivotes implicados, las migraciones correspondientes, las vistas de Historial/Producción, sus assets y el soporte de pruebas. Producción de Pan ya registra cabecera, lote y participantes mediante la Action existente y una transacción.

## 2. Problemas encontrados

- Contador fijo de «23 registros encontrados» y fechas fijas.
- Producto «Pan carioco», cantidades y usuarios ficticios.
- Opciones de turno con IDs 1 y 2 escritos en la vista.
- Imágenes placeholder externas y enlaces «detalles» sin destino funcional.
- Filtros fuera de un formulario de consulta; Filtrar solo ejecutaba `console.log`.
- Torta y Bocadito parecían opciones operativas aunque sus historiales no estaban implementados.
- No existían consulta Eloquent, paginación, validación del GET ni estados vacíos/errores de consulta.

La ausencia de un listado Eloquent inicial no demuestra un N+1 anterior. La implementación nueva previene ese problema y lo comprueba con una prueba de número de consultas.

## 3. Archivos modificados

| Ruta | Cambio | Motivo |
|---|---|---|
| `app/Http/Controllers/HistorialController.php` | Consulta lotes de sesiones de Pan, aplica filtros validados, carga relaciones y pagina 15 lotes | Conectar la pantalla con datos persistidos y limitar la carga |
| `app/Http/Requests/ConsultarHistorialProduccionRequest.php` (nuevo) | Valida fechas/turno GET y expone errores sin redirección | Mantener la validación fuera del controller y seguir el patrón de consulta de Producción |
| `resources/views/history/index.blade.php` | Formulario GET, turnos de BD, tarjetas reales, participantes, errores, estado vacío y paginación | Sustituir datos ficticios conservando la estructura visual |
| `resources/js/historial.js` | Retira listeners de la maqueta y deja constancia del uso de controles HTML nativos | Evitar consultas ficticias y duplicación de lógica del servidor; la vista ya no carga este archivo |
| `resources/css/historial.css` | Ajustes para categorías deshabilitadas, texto variable, observaciones, errores y paginación | Mantener legibles los nuevos datos sin cambiar paleta ni identidad visual |
| `tests/Feature/HistorialProduccionTest.php` (nuevo) | 23 casos con sus datasets, MariaDB aislada y fixtures con IDs desplazados | Verificar datos, filtros, acceso, participantes, paginación y consultas |
| `mejora_asignado.md` | Añade seguimiento de esta entrega y evidencia de navegación/registro/consulta | Distinguir el estado vigente de las auditorías históricas |
| `dato_optimizar/informe_historial_funcional.md` (nuevo) | Informe solicitado | Registrar alcance, decisiones, pruebas y límites |

Las notas afectadas del Vault original son `LARAVEL/Controllers.md`, `LARAVEL/Requests.md`, `LARAVEL/Blade.md`, `PRODUCCION/Pantallas.md`, `PERSONAL Y USUARIOS/Turnos.md`, `INICIO/Estado de proyecto.md`, `INICIO/Roadmap.md` y `DECISIONES/Decisiones Laravel.md`. Sus actualizaciones distinguen la comprobación aislada de la base de trabajo y conservan el contenido anterior. El nombre real del registro de decisiones en este Vault termina en `.md`; no se crea otro archivo con `.md.md`.

## 4. Funcionamiento final

1. El usuario inicia sesión y entra a Dashboard/Producción mediante las rutas existentes.
2. Registra Pan con el flujo ya implementado: `RegistrarProduccionPan` crea o reutiliza la sesión y guarda un lote con sus participantes.
3. Al entrar a Historial aparece el lote persistido, sin necesidad de datos intermedios de JavaScript.
4. Cada tarjeta muestra fecha, turno de sesión, producto, total almacenado en latas, equivalencia en coches/latas, observación, Maestro, Ayudantes y usuario registrador de la sesión.
5. Filtrar envía un GET. Limpiar vuelve a `/history` sin parámetros. Los enlaces de paginación conservan los filtros válidos.

El contador representa lotes (`DetallePan`), no cabeceras. Los productos desactivados siguen apareciendo en su historial. Torta y Bocadito están deshabilitados con «Próximamente». No se implementa una página/modal de detalles: la información requerida queda visible en la tarjeta y se retira el enlace sin destino.

## 5. Consulta y relaciones Eloquent utilizadas

La consulta parte de `DetallePan`, restringe mediante `whereHas('produccion')` y `whereHas('categoria')` las sesiones cuya categoría de catálogo se llama `Pan`. No presupone un ID de categoría.

Se reutilizan las relaciones existentes:

- `DetallePan::produccion()` → cabecera y fecha.
- `Produccion::categoria()` → clasificación de la sesión.
- `Produccion::turno()` → turno canónico.
- `Produccion::usuario()` → cuenta que registró la sesión.
- `DetallePan::producto()` → nombre del producto.
- `DetallePan::empleados()` → empleados y pivote `DetallePanEmpleado`.
- `DetallePanEmpleado::rolProduccion()` → rol de cada asociación.

`with(['producto', 'produccion.turno', 'produccion.usuario', 'empleados'])` carga las relaciones del listado. Después se reúnen los pivotes de la página en una colección Eloquent y se ejecuta `load('rolProduccion')` en lote. Se reutiliza la relación del pivote sin crear relaciones paralelas ni consultar un rol por participante.

Orden: fecha de cabecera DESC, obtenida mediante subconsulta Eloquent correlacionada, y `detalle_pan.id` DESC como desempate. Paginación de 15 detalles con total real. No se carga el conjunto completo de producciones.

## 6. Filtros implementados

| Parámetro | Validación | Comportamiento |
|---|---|---|
| `fecha_inicio` | Opcional, fecha válida con formato `Y-m-d` | Fecha de cabecera mayor o igual al valor |
| `fecha_fin` | Opcional, fecha válida con formato `Y-m-d`; igual o posterior al inicio cuando existe | Fecha de cabecera menor o igual al valor |
| `turno_id` | Opcional, entero existente en `turnos` | Filtra exclusivamente `produccion.turno_id` |

Los límites son inclusivos. Se permiten solo desde, solo hasta y filtros vacíos. Los tipos malformados, arrays, fechas imposibles, rango invertido y turnos inexistentes producen errores visibles en la misma página, sin redirección a sí misma ni consulta de detalles. Los valores para atributos HTML se reducen a escalares y Blade los escapa; las consultas usan exclusivamente datos validados.

Las opciones se obtienen con `Turno::orderBy('nombre_turnos')->get()`. En la paginación se añaden únicamente los filtros validados no vacíos. Ningún parámetro de categoría habilita Torta/Bocadito.

## 7. Participantes

Se utiliza el `belongsToMany` existente, su custom Pivot y el atributo `rol_produccion_id`. El rol se resuelve desde `pivot->rolProduccion` y se compara por `nombre_roles_produccion` con `Maestro` o `Ayudante`; no se presuponen IDs.

La vista lista los nombres guardados. Los datos incompletos muestran «Sin Maestro registrado» o «Sin Ayudantes registrados» sin inventar empleados. No se revalida ni reescribe el histórico. La regla de exactamente un Maestro y al menos un Ayudante permanece en el escritor existente, que no se modifica.

## 8. Tests

Se creó `tests/Feature/HistorialProduccionTest.php`; no se modificaron tests previos. Cubre autenticación, estado vacío, registro real desde login, producto/cantidad, observación, Maestro y varios Ayudantes, fechas inclusivas/opcionales, turno de cabecera frente al legacy, IDs arbitrarios, familias, productos inactivos, errores malformados, paginación con filtros, orden y consultas constantes. También comprueba escape de HTML y participantes ausentes.

Comandos ejecutados antes de la interrupción:

| Comando | Resultado exacto |
|---|---|
| `php tests/run-mariadb.php --filter=NavigationTest` | 20 pruebas aprobadas, 239 aserciones, salida 0 |
| `php tests/run-mariadb.php --filter=HistorialProduccionTest` | Primera ejecución: 23 pruebas, 22 aprobadas, 1 fallida, 183 aserciones, salida 1 |
| `php tests/run-mariadb.php --filter='HistorialProduccionTest\|ProduccionPanStoreTest\|RegistrarProduccionPanActionTest\|NavigationTest'` | Después de corregir la preparación de la prueba: 163 pruebas aprobadas, 1.595 aserciones, salida 0 |
| `php artisan test` | 202 pruebas registradas: 2 aprobadas, 200 omitidas, 3 aserciones, salida 0; no equivale a la suite completa comprobada |
| `php tests/run-mariadb.php --do-not-cache-result` | Suite completa: 202 pruebas aprobadas, 2.048 aserciones, ninguna omitida, salida 0 |
| `vendor/bin/pint --test app/Http/Controllers/HistorialController.php app/Http/Requests/ConsultarHistorialProduccionRequest.php tests/Feature/HistorialProduccionTest.php` | Aprobado, salida 0 |
| `npm run build` | Vite compiló correctamente, salida 0; aviso no bloqueante sobre `fontaine` opcional |
| `git diff --check` | Sin errores, salida 0 |

La prueba fallida comparaba la primera petición con otra que ya tenía el empleado del sidebar cargado en la cuenta del guard: obtuvo 9 frente a 10 consultas. Se precargó esa relación en la preparación para estabilizar una lectura ajena al listado. La prueba pasó después y comprueba que pasar de 1 a 15 lotes mantiene el número de consultas, incluida una sola consulta de roles.

Dos intentos de iniciar el runner dentro del sandbox fueron bloqueados por la disponibilidad del servidor/socket local, sin llegar a ejecutar tests. Se reintentaron con escalamiento; las ejecuciones comprobadas usan MariaDB temporal en `/tmp/sprint4-*`, sin red y con bases ficticias. La instancia final se detuvo y dejó artefactos en `/tmp/sprint4-f513b076ad65`.

Los tests HTTP usan sesiones `array` y omiten Vite; la compilación de assets se verificó aparte. El recorrido LOGIN → DASHBOARD → PRODUCCIÓN → registrar Pan → HISTORIAL → filtrar fecha → filtrar turno se revisó conceptualmente y se ejecutó mediante pruebas HTTP con provider y persistencia reales en la base aislada. No se hizo una nueva revisión visual en navegador ni se certificó el driver de sesiones `database` o la base de trabajo.

## 9. Decisiones técnicas

- Un registro del historial corresponde a un lote, para paginar realmente los productos y sus participantes.
- Reutilizar un FormRequest siguiendo el GET de Producción: conservar la página ante filtros inválidos y bloquear la consulta de resultados.
- Mantener consulta en el controller de lectura, sin introducir Services, Repositories ni modificar el escritor.
- Resolver categoría, turnos y roles desde el catálogo y sus relaciones existentes.
- Usar únicamente el turno de cabecera; conservar intacto `detalle_pan.turno_id` por compatibilidad.
- Calcular coches con `intdiv(cantidad, DetallePan::LATAS_POR_COCHE)` y resto con `%`, conservando siempre la cantidad real almacenada.
- Etiquetar al usuario como «Registró la sesión»: la cabecera conserva al primer registrador cuando se añaden otros lotes; no existe autor individual por detalle.
- Mantener formulario, limpieza y navegación nativos, con la lógica de consulta en Laravel.
- No inferir la familia de cabeceras antiguas cuyo `categoria_id` sea NULL. Quedan fuera de la consulta de sesiones clasificadas como Pan; no se hace backfill ni se cambia el esquema.

## 10. Pendientes

- **PENDIENTE:** revisión visual y aceptación con datos del entorno de trabajo antes de la presentación. La comprobación de esta tarea es aislada; no se consultó ni modificó esa base.
- **PENDIENTE:** tratar, si existen en el entorno real, cabeceras históricas sin categoría mediante una decisión explícita de clasificación. No se inventa su categoría en lectura.
- **PENDIENTE:** Torta y Bocadito, expresamente fuera de esta tarea; no se implementaron sus registros ni consultas.
- Sin cambios pendientes de implementación para el historial de Pan dentro del alcance probado. No se realizó commit ni despliegue.

## 11. Archivos que NO se tocaron intencionalmente

- `routes/web.php`: `/history` y su protección `auth` ya eran correctos.
- `app/Http/Controllers/ProduccionController.php`: conservar consulta/registro funcional y su separación con la Action.
- `app/Actions/Produccion/RegistrarProduccionPan.php`: conservar transacción, reglas, sesiones, snapshots y participantes.
- `app/Http/Requests/ConsultarProduccionRequest.php` y `RegistrarProduccionPanRequest.php`: conservar contrato del registro.
- Modelos, relaciones y pivotes existentes: suficientes para el historial, sin duplicarlos.
- Migraciones y seeders: no existe un bloqueo que requiera cambiar estructura o datos.
- `resources/views/produccion/index.blade.php` y assets de Producción: reducir el riesgo de afectar el formulario.
- Login, Dashboard, middleware, sidebar y `vite.config.js`: conservar navegación, autenticación, caché e identidad visual actuales.
- Tests previos y dependencias: mantener su cobertura y evitar cambios ajenos a esta tarea.
