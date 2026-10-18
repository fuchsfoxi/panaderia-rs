# Módulo Personal: Usuarios y Empleados

Fecha: 2026-10-18. Estado final: **IMPLEMENTADO Y COMPROBADO en MariaDB aislada y Chrome headless con fixtures**. Migración local aplicada; aceptación manual del usuario **PENDIENTE**. Sin commit, push ni despliegue automático.

## 1. Estado inicial del proyecto y diagnóstico previo

Checkout HEAD `15b3668`, con los cambios anteriores de Historial/Productos sin commit, que deben conservarse. Usuarios ya funciona con Gate administrativo, cuentas compartidas por turno, activación/contraseñas y protección del último Administrador. Empleados tiene nombre VARCHAR(100), número nullable, cargo obligatorio y timestamps; no tiene estado ni rol actual. Cargos, roles de acceso y roles de producción son catálogos distintos. Las participaciones guardan rol_produccion_id en sus tres pivotes y no deben actualizarse al cambiar el rol actual.

Lectura operativa autorizada mediante transacción READ ONLY y rollback, sin consultar valores de credenciales: dos empleados, ambos con seis participaciones Pan. Carlos M. (ID 1) tiene cuatro como Maestro y dos como Ayudante y está asociado al usuario admin. Ana R. (ID 2) tiene dos como Maestro y cuatro como Ayudante, sin cuenta vinculada. Sin participaciones Torta/Bocadito. No hay candidato actual a eliminación física segura. Las cuentas turn_mañana y turn_noche no tienen Empleado asociado y conservan sus turnos reales. Ningún usuario/empleado se modificó en este diagnóstico.

No puede deducirse el rol actual del cargo ni de esas participaciones mixtas. Se necesita rol actual nullable hacia roles_produccion y estado activo, conservando el rol de los pivotes. Cargo debe admitir NULL para crear trabajadores con el formulario solicitado sin inventar un cargo. Los empleados legacy quedarán pendientes de asignación explícita de rol antes de nuevas selecciones. No se eliminarán empleados, no se reasignarán cuentas ni se ejecutarán seeders de empleados.

Línea base aislada: 288 pruebas/2.716 aserciones de Usuarios, Producción y seeders aprobadas. Al abrir este informe el desarrollo y la aplicación del esquema estaban EN INVESTIGACIÓN; las secciones siguientes registran el resultado final.

## 2. Tablas y relaciones encontradas

| Entidad | Relación y responsabilidad |
| --- | --- |
| `empleados` | Persona participante, PK `id`, nombre de hasta 100 caracteres y timestamps. Número nullable sin UNIQUE. Cargo legacy preservado. |
| `cargos` | Catálogo de ocupaciones; `empleados.cargo_id` lo referencia. No equivale a Maestro/Ayudante ni concede permisos. |
| `roles` | Roles de acceso de `usuarios_sistema`: Administrador/Operador. No determina el papel del trabajador en un lote. |
| `roles_produccion` | Catálogo reutilizado para Maestro, Ayudante y Practicante. No se crea otra tabla de roles. |
| `usuarios_sistema` | `empleado_id` nullable, FK y UNIQUE: una persona puede no tener cuenta, y como máximo una cuenta se vincula con un empleado. Turno/estado pertenecen a la cuenta. |
| `detalle_pan_empleado`, `detalle_torta_empleado`, `detalle_bocadito_empleado` | PK compuesta detalle/empleado. `rol_produccion_id` guarda el papel histórico de ese participante en ese lote. FK de empleado/rol RESTRICT; cascada solo al eliminar el detalle. |

La única migración nueva es `2026_10_18_100000_add_rol_actual_and_activo_to_empleados.php`:

- Añade `empleados.rol_produccion_id` nullable y FK a `roles_produccion`. NULL conserva la incertidumbre de los registros previos.
- Añade `empleados.activo`, booleano con default true. Los antiguos empleados se conservan y no se desactivan automáticamente.
- Hace nullable `cargo_id`, conservando FK, índice y valores previos. Permite crear trabajadores con los tres campos solicitados sin inventar un cargo.
- No añade turno a empleados ni modifica sus pivotes, nombres, números, cuentas o timestamps previos.
- `down()` se bloquea antes de DDL si existen roles actuales, empleados inactivos o cargos NULL. No se ejecutó rollback operativo.

### Aplicación local comprobada

Antes de aplicar, había 29 migraciones previas y solo esta nueva pendiente. La migración de cuentas del 16/10 ya estaba aplicada como batch 7 y las cuentas compartidas existían: actualiza la evidencia de notas anteriores sin atribuir su creación a esta tarea.

Se revisó `migrate --pretend` y se aplicó únicamente el archivo nuevo; quedó **Ran, batch 8**. Después se ejecutó exclusivamente `RolProduccionSeeder`, idempotente y ampliado a los tres nombres canónicos: conservó Maestro/Ayudante y añadió Practicante. No se ejecutó DatabaseSeeder, CargoSeeder, EmpleadoSeeder o UsuarioSeeder.

Se compararon antes/después las columnas previas de empleados y todas las filas de producción, detalles, pivotes, cargos y metadatos funcionales de cuentas. Resultado: **filas previas conservadas, cero empleados creados, tres cuentas sin cambios, dos roles actuales pendientes de asignar**. No se consultaron valores de contraseñas/hashes reales. No se hicieron registros reales para probar la funcionalidad. Artefacto sin credenciales: `/tmp/personal-esquema-aplicado.json`.

## 3. Archivos creados o modificados

| Archivo o grupo | Motivo |
| --- | --- |
| `app/Http/Controllers/EmpleadoController.php` | Listar, formularios, crear, editar y estado; coordina Request/Action y mensajes. |
| `app/Actions/Personal/AdministrarEmpleados.php` | Transacciones, bloqueo de persona y revalidación del actor administrativo; escribe solo nombre/rol actual/estado. |
| `app/Http/Requests/GestionarEmpleadoRequest.php` | Gate y mensajes/atributos compartidos de empleados. |
| `app/Http/Requests/GuardarEmpleadoRequest.php` | Nombre Unicode, normalización de espacios, longitud, rol permitido y estado booleano. |
| `app/Http/Requests/CambiarEstadoEmpleadoRequest.php` | Estado explícito; no invierte ni permite cambiar otros atributos. |
| `app/Models/Empleado.php` | Rol actual, cast de activo y scope local de disponibilidad; conserva cargo/usuario. Formato del archivo normalizado por Pint. |
| `app/Models/RolProduccion.php` | Lista canónica de tres roles, sin IDs fijos. |
| `database/migrations/2026_10_18_100000_add_rol_actual_and_activo_to_empleados.php` | Evolución mínima y conservación legacy. |
| `database/seeders/RolProduccionSeeder.php` | Completar Practicante de forma idempotente sin crear personas. |
| `routes/web.php` | Entrada Personal y seis acciones de Empleados bajo administración; rutas Usuarios conservadas. |
| `resources/views/personal/layout.blade.php` | Encabezado Personal, descripción exacta y navegación compartida de dos pestañas. |
| `resources/views/usuarios/layout.blade.php`, `index.blade.php`, `create.blade.php`, `password.blade.php` | Reutilizar el layout y mantener títulos de formularios, listado y acciones previas. |
| `resources/views/empleados/index.blade.php`, `form.blade.php` | Listado de trabajadores y formulario común de creación/edición. |
| `resources/views/components/sidebar.blade.php`, `resources/css/usuarios.css` | Personal en el menú; pestañas y presentación responsive con estilos existentes. |
| `app/Http/Controllers/ProduccionController.php` | Nuevas selecciones limitadas a empleados activos con rol actual configurado. |
| `app/Actions/Produccion/ValidarParticipantesProduccion.php` | Activo/rol actual revalidados bajo bloqueo; Practicante no cuenta como Ayudante. |
| `app/Actions/Produccion/RegistrarProduccionPan.php`, `RegistrarProduccionPorCantidad.php`, `app/Http/Requests/RegistrarProduccionPanRequest.php` | Reutilizar el validador compartido con los tres roles permitidos. |
| `resources/views/produccion/index.blade.php`, `resources/js/produccion.js` | Identificar trabajador, mostrar su rol actual sin edición libre y conservar el payload de participantes. |
| `resources/views/history/index.blade.php` | Mostrar Practicantes desde el rol del pivote, además de Maestro/Ayudantes; conserva el diseño y la eliminación anterior. |
| `tests/Feature/EmpleadosTest.php`, `EmpleadosMigrationTest.php`, `PersonalProduccionTest.php` | 72 casos nuevos de Personal, esquema, seguridad e integración/historia. |
| `tests/Support/IsolatedMariaDb.php` | Fixtures con roles actuales explícitos únicamente cuando existe el esquema nuevo. |
| `tests/Feature/ProduccionPanStoreTest.php`, `RegistrarProduccionPanActionTest.php`, `ProductosTest.php` | Adaptar preparaciones de roles actuales y texto del sidebar manteniendo las aserciones de negocio. |
| `tests/Feature/ProductionObservationsMigrationTest.php`, `UsuariosMigrationTest.php` | Seleccionar únicamente migraciones anteriores al estado histórico bajo prueba. |
| `tests/JavaScript/ProduccionCantidadTest.mjs` | Verificar selección automática del rol actual y protección del control. |
| Este informe | Diagnóstico, implementación, evidencia y revisión manual solicitados. |

Se preservaron los cambios previos sin commit de eliminación del Historial y hover de Productos. Esta tarea no modifica `UsuarioController`, `AdministrarUsuarios`, Requests/JS de Usuarios, autenticación, contraseñas, reglas del último Administrador, reglas de cantidades o backend de Productos.

## 4. Funcionamiento de las dos pestañas

Sidebar muestra **Personal** solo al Administrador. `/personal` redirige a `/usuarios`, la pestaña por defecto. Se conservan todas las URLs y nombres `usuarios.*` anteriores.

El encabezado común es «Personal» y «Administra las cuentas de acceso y el personal de producción.». Las pestañas son enlaces nativos a Usuarios/Empleados, con selección verde y `aria-current="page"`. No necesitan JavaScript ni frameworks; cada página carga exclusivamente su listado o formulario. No hay tarjetas informativas, previews ni contenido adicional debajo del listado, salvo la paginación existente.

Usuarios conserva **+ Nuevo usuario**, crear, cambiar contraseña, activar/desactivar, rol de acceso, turno y protección administrativa. Las cuentas compartidas `turn_mañana` y `turn_noche` mantienen identidad, turno, estado y relaciones.

Empleados ofrece **+ Nuevo empleado**, nombre, rol actual, estado, Editar y Activar/Desactivar. Listado de 15 por página, orden por nombre e ID para desempate. El identificador `#ID` distingue homónimos. Los registros legacy sin rol muestran «Pendiente de asignar» dentro de su fila.

## 5. Flujo de creación y edición de empleados

1. Administrador abre Personal → Empleados → + Nuevo empleado.
2. Introduce nombre completo, rol actual y estado. No hay contraseña, cuenta, cargo inventado ni turno fijo.
3. Laravel valida y normaliza espacios horizontales. Admite letras Unicode, marcas de acentuación, espacios, puntos, apóstrofos y guiones; rechaza HTML, números como nombre, arrays, controles y más de 100 caracteres.
4. La Action revalida actor activo/Administrador, comprueba el rol canónico y guarda solo los tres atributos permitidos dentro de transacción. Los atributos adicionales del cliente no se persisten.
5. Editar reutiliza el formulario; conserva número/cargo legacy y no modifica cuentas ni pivotes.
6. Activar/desactivar utiliza PATCH con un estado explícito e idempotente. Desactivar a una persona vinculada a una cuenta no desactiva esa cuenta: son entidades independientes.
7. Cancelar vuelve al listado mediante GET. Todos los formularios mutadores incluyen CSRF.

El esquema no declara UNIQUE sobre nombre o número. Se permiten homónimos como personas con IDs distintos; no se inventó una restricción de identidad por nombre. Evitar altas accidentales de la misma persona sigue siendo una responsabilidad administrativa; no hay DNI u otra identidad personal acordada en este alcance.

## 6. Manejo de los tres roles

`roles_produccion` se reutiliza tanto desde `empleados.rol_produccion_id` como desde los pivotes; son referencias con responsabilidades diferentes:

- **Empleado:** rol actual, modificable por el Administrador.
- **Participación:** rol utilizado en ese lote, conservado al registrar.
- **Cuenta:** Administrador/Operador en `roles`, independiente de los dos anteriores.

Maestro, Ayudante y Practicante se resuelven por catálogo/nombre canónico, nunca por ID numérico fijo. El formulario exige un rol actual existente y permitido. Carlos puede pasar Practicante → Ayudante → Maestro; cada nuevo lote utiliza su rol vigente y los lotes anteriores conservan su rol previo.

No se deducen roles de nombres, cargos, permisos de cuenta, turnos ni participaciones antiguas. Los dos trabajadores existentes necesitan configuración manual antes de nuevas selecciones; sus roles históricos mixtos se mantienen intactos.

## 7. Integración con Producción

Se conserva la interfaz y sus flujos Pan LATA, Pan UNIDAD, Torta y Bocadito. La selección de empleados usa un scope local: activo y rol actual Maestro/Ayudante/Practicante. No es un filtro global del modelo, por lo que el Historial sigue recuperando inactivos.

Cada opción identifica nombre e ID. Al seleccionar, el rol actual se refleja en el control existente, deshabilitado para edición. Agregar recupera ese rol del trabajador seleccionado y genera los mismos hidden `empleado_id`/`rol_produccion_id` de siempre. El operador no dispone de creación/edición de empleados en Producción.

El servidor no confía en el control deshabilitado: `ValidarParticipantesProduccion` recupera y bloquea empleados ordenados por ID dentro de las transacciones existentes, verifica activo y coincidencia exacta entre rol enviado y rol actual. Un cambio mientras el formulario estaba abierto produce error y solicita actualizar la selección. Pan también reutiliza esta validación preliminar en su Request; todas las Actions la ejecutan antes de escribir.

Composición obligatoria para las cuatro variantes:

- Exactamente un Maestro.
- Uno o más Ayudantes, conservando la ausencia de un máximo arbitrario.
- Sin empleados repetidos.
- Practicantes opcionales adicionales; nunca satisfacen el mínimo de Ayudantes.

Las validaciones de producto, unidad, cantidades, coches/latas, snapshot, hora, forma/foto, turno permitido y usuario registrador permanecen. Las Actions de empleados toman bloqueo del empleado y los escritores de producción lo vuelven a comprobar antes de persistir; no se añadió una prueba nueva específica con dos procesos compitiendo por el cambio de rol. La concurrencia preexistente sigue cubierta por la suite completa.

## 8. Protección del Historial y autorización

Cambiar rol actual o estado no modifica `detalle_*_empleado`. El rol mostrado en consulta/Historial procede del pivote, no del empleado actual. Se añade la línea de Practicantes solo cuando existen, manteniendo el diseño de las tarjetas. No se eliminan trabajadores ni hay ruta DELETE para Empleados.

Las pruebas registran las cuatro variantes con Practicante, promueven y desactivan al trabajador y verifican pivotes intactos e información histórica visible. Otra matriz crea lotes sucesivos durante Practicante → Ayudante → Maestro y comprueba los tres roles históricos distintos.

Editar un nombre corrige la identidad mostrada a través de la misma relación, también en registros anteriores: no existe snapshot histórico de nombres y esta tarea no lo inventa. No se recomienda renombrar un empleado de prueba con historia para convertirlo en otra persona real; la sustitución segura es desactivar esa ficha y crear una nueva.

Todas las rutas de Personal/Empleados heredan `auth`, `EnsureUsuarioActivo`, `auth.session` y `can:administrar-sistema`. Los tres FormRequests vuelven a utilizar el Gate. La Action comprueba el actor dentro de la transacción. Operadores e invitados no pueden crear/editar/activar/desactivar mediante URLs o payloads manipulados. Se comprueba además Operador con CSRF real válido y rechazo de tokens ausentes/incorrectos para el Administrador. GET no cambia estado; DELETE no está definido.

## 9. Diagnóstico de empleados existentes y posible limpieza futura

| Empleado observado | Asociaciones existentes | Diagnóstico |
| --- | --- | --- |
| Carlos M., ID 1, número 001 | Usuario admin; seis participaciones Pan: cuatro Maestro y dos Ayudante | Conservar ficha y asociaciones. No candidato a eliminación física segura. |
| Ana R., ID 2, número 002 | Sin cuenta; seis participaciones Pan: dos Maestro y cuatro Ayudante | Conservar ficha e historial. No candidato a eliminación física segura. |

La coincidencia con los nombres del seeder de desarrollo no demuestra por sí sola que una persona sea ficticia ni autoriza borrar su historia. No se cambiaron nombres, cargos, números, roles previos, estados o asociaciones existentes para «limpiarlos».

El estado activo nuevo toma el default de esquema; el rol actual queda NULL, sin backfill. Maestro/Ayudante históricos no se reinterpretan. Las dos cuentas de turno mantienen `empleado_id` NULL; el vínculo previo de admin con Carlos se conserva.

Limpieza futura: hoy no hay candidatos desvinculados. Una eventual investigación podría considerar fichas sin cuentas, sin participaciones de ninguna familia y sin otras FK, tras confirmar que son datos de prueba; no se implementa ni ejecuta esa eliminación. Para reemplazar las actuales, usar desactivación y altas reales mediante la interfaz. No se añade un calendario, asignación permanente de turno, recuperación o fusión de personas.

## 10. Pruebas ejecutadas y resultados

| Comprobación | Resultado |
| --- | --- |
| Línea base Usuarios/Producción/seeders | 288 aprobadas, 2.716 aserciones. |
| Primera suite completa con integración | 963 aprobadas, 8.628 aserciones. |
| Casos nuevos finales: `EmpleadosTest`, `EmpleadosMigrationTest`, `PersonalProduccionTest` | **72 aprobados, 625 aserciones**. |
| Suite completa final `php tests/run-mariadb.php --do-not-cache-result` | **969 aprobadas, 8.718 aserciones, cero fallidas/omitidas**. |
| Cinco archivos JavaScript ejecutados individualmente con Node | **52 aprobados**: Login 8, Producción 19, Productos 10, sidebar 7, Usuarios 8. |
| Chrome headless con vistas/relaciones ficticias | **40/40 combinaciones**, sin overflow ni excepciones JS. |
| `npm run build` | Aprobado; aviso opcional preexistente de fontaine, sin instalar dependencias. |
| Pint de los 24 PHP afectados y `git diff --check` | Aprobados. Revisión de diff y archivos nuevos realizada. |
| Migración local selectiva, catálogo y comparación de datos previos | Aplicada batch 8; filas previas, cuentas y asociaciones conservadas; cero empleados creados. |

Los 72 casos nuevos cubren ambas pestañas y contenido exclusivo, rutas/actor/CSRF, formularios, nombres inválidos, homónimos admitidos, paginación, crear/editar/estado, conservación de cargo/número/cuenta, legado sin rol, esquema/FK/defaults/rollback protegido, seeder idempotente, los cuatro flujos de producción, rol manipulado/obsoleto, inactivo, Practicante adicional, ascensos e historia. Las pruebas existentes comprueban Usuarios/contraseñas/último Admin y concurrencia, Pan LATA/UNIDAD, Torta/Bocadito, filtros, Historial, eliminación anterior y Productos.

### Aislamiento y alcance visual

MariaDB temporal sin red bajo `/tmp/sprint4-*`, con socket/datadir/base verificados por el runner existente antes de migrar. La suite final utilizó `/tmp/sprint4-f948557755dd`, quedó detenida. Fixtures dentro de transacciones y bases nuevas verificadas para DDL; las migraciones históricas no utilizan el esquema futuro en sus preparaciones. Casos nuevos con sesiones array/withoutVite y almacenamiento falso; la suite conserva las verificaciones previas de sesiones/caché database en aislamiento.

Chrome reutiliza Puppeteer disponible fuera del proyecto. Renderiza Blade y modelos reales con relaciones ficticias en memoria, assets compilados y solicitudes interceptadas. No conecta al servidor operativo ni envía altas/producciones reales. Se revisaron Usuarios/listado/nuevo/contraseña, Empleados/listado/nuevo/editar/errores, Producción y dos estados sin JS en 320/375/768/1280 px. Navegación entre pestañas, selección automática/protegida de los tres roles, hidden enviados en los cuatro flujos, formularios y ausencia de overflow comprobados. Las capturas móvil/escritorio se inspeccionaron; fuentes externas bloqueadas y métricas con fallback. Persistencia se prueba aparte por HTTP/MariaDB, no es un E2E conectado de Chrome a BD.

Artefactos temporales: `/tmp/personal-diagnostico.php`, `/tmp/personal-diagnostico.json`, `/tmp/personal-aplicar-esquema.php`, `/tmp/personal-esquema-aplicado.json`, `/tmp/personal-render.php`, `/tmp/personal-browser.cjs` y `/tmp/personal-review/` (capturas y resultados). No contienen credenciales reales ni copias de notas del Vault.

Intentos iniciales no considerados aprobados: sandbox bloqueó conexiones/Chrome y se repitieron con escalamiento. Dos preparaciones preexistentes asumían roles libres o borraban un rol ahora referenciado; se adaptaron fixtures sin retirar aserciones. Un test nuevo buscaba «Personal» en todo Producción en vez del enlace administrativo y se acotó al enlace. El arnés visual necesitó esperar la navegación antes de capturar; el preflight SQL de duplicados necesitó una selección explícita para ONLY_FULL_GROUP_BY. Esos arneses se corrigieron y las verificaciones finales aprobaron. No se detectó ni corrigió un bug ajeno para obtener esos resultados.

## 11. Pendientes, riesgos y revisión manual

- **PENDIENTE funcional:** asignar por Administración el rol actual de las dos fichas existentes o desactivarlas y registrar trabajadores reales. Mientras el rol sea NULL no aparecen en nuevas selecciones de Producción; su historia y cuentas siguen disponibles.
- **PENDIENTE:** aceptación manual del usuario, dispositivos físicos/Safari, fuentes remotas, lector de pantalla y funcionamiento de sesiones en el entorno final. Los anchos emulados y el envío con fixtures están comprobados, no certifican operación real.
- Nombres homónimos permitidos e identidad por ID; no se añade una regla de deduplicación personal sin un identificador acordado.
- Editar nombre afecta su presentación histórica, por la relación existente; cambiar rol/estado conserva la función histórica.
- La migración local ya se aplicó; otros entornos deberán aplicar únicamente la migración y el seeder selectivo. DDL múltiple no es una transacción atómica entre todos los ALTER; no se certifica tolerancia a caída o DDL concurrente.
- El catálogo de roles sigue sin UNIQUE por nombre. El preflight local comprobó que no había ambigüedades; el seeder es idempotente secuencial, no una garantía de seeding simultáneo. No se añadieron paquetes ni tablas paralelas.
- No se implementan eliminación física, fusión, cambio de usuario asociado, credenciales de empleados ni calendario de turnos.

### Revisión desde el navegador

1. Entrar como Administrador y abrir **Personal**: debe iniciar en Usuarios. Verificar que Crear usuario, Cambiar contraseña y estados conservan su funcionamiento usando un entorno de prueba.
2. Abrir Empleados y comprobar las dos fichas conservadas con rol pendiente. Decidir sus roles reales antes de guardarlos; no asumirlos desde los históricos.
3. Crear un empleado real desde + Nuevo empleado cuando se confirme su identidad y rol. Para probar promociones/desactivación, usar primero un entorno con datos ficticios.
4. En Producción, seleccionar Maestro y al menos un Ayudante; añadir Practicante si corresponde. Revisar selección por nombre/ID y rol actual no editable desde ese formulario.
5. En el entorno de prueba, cambiar rol/desactivar tras registrar y confirmar que Historial mantiene la función anterior y que el inactivo desaparece del selector nuevo.
6. Comprobar pestañas, formulario y navegación móvil/escritorio, y denegación de Personal con una cuenta Operador.

### Documentación del Vault

Notas actualizadas: `PERSONAL Y USUARIOS/Empleados.md`, `PERSONAL Y USUARIOS/Usuarios.md`, `PERSONAL Y USUARIOS/Permisos.md`, `PRODUCCION/Logica de negocio.md`, `BASE DE DATOS/Migraciones.md`, `LARAVEL/Controllers.md`, `DECISIONES/Decisiones Laravel.md` e `INICIO/Estado de proyecto.md`. Se aplicaron las ocho adiciones sobre los originales mediante escalamiento autorizado, tras revisar sus diffs y verificar hashes. Se comprobó la conservación íntegra de las entradas históricas. Se utilizan los nombres reales; no se crean carpetas ni copias en el repositorio. Roadmap no cambia de orden y no se declara cerrado todo Sprint 5. `mejora_asignado.md` sigue ausente en checkout/Vault y no se recrea. Ninguna autorización quedó pendiente.

**Listo para revisión manual en la instalación local.** Sin commit, push, despliegue automático o eliminación de empleados/usuarios.
