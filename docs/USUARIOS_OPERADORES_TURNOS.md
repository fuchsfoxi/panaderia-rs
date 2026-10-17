# Usuarios Operadores y Turnos — Panadería RS

## Aclaración de la regla Pan LATA / UNIDAD — 2026-10-16

La nueva regla confirmada por el usuario tras contrastar el procedimiento físico sustituye cualquier supuesto inferior de que todo Pan usa LATA: Pan admite LATA o UNIDAD según el producto. Coches, 18 latas/coche, factor/snapshot y hora nueva obligatoria se aplican solo a Pan LATA. Pan UNIDAD conserva cantidad directa y factor/snapshot/hora NULL; Torta/Bocadito siguen UNIDAD sin hora. Historial usa la unidad del detalle y Dashboard suma solo latas históricas. No hay detección por nombre, migración nueva ni cambios de datos operativos.

**IMPLEMENTADO Y COMPROBADO en aislamiento:** 872 PHP/7.896 aserciones sin fallidas/omitidas, 51 JS, Chrome 40/40, build, Pint de PHP afectado y diff aprobados. Aceptación operativa del usuario **PENDIENTE**. Informe vigente: [Pan por Lata o Unidad](PAN_REGISTRO_LATA_UNIDAD.md). El resto conserva íntegros el contexto, los resultados y los pendientes históricos de esta revisión anterior; no constituye el contrato actualizado de Pan. Login/Usuarios/turnos/contraseñas permanecen preservados.

Fecha: 2026-10-16. Checkout revisado: HEAD `a0d7c00`, con los cambios de Login/seguridad/UX de las tareas anteriores aún sin commit. Se conservaron esos cambios y el informe de auditoría preexistente.

Estado: **IMPLEMENTADO Y COMPROBADO en las pruebas aisladas indicadas abajo**. La migración queda preparada para revisión: **NO APLICADA a la base operativa**. No se crearon cuentas reales, no se cambiaron credenciales reales y no se hizo commit/push.

## Actualización: ñ, Unicode y RateLimiter — 2026-10-16

**IMPLEMENTADO Y COMPROBADO en aislamiento.** Esta ampliación sustituye la restricción ASCII y el mínimo de 12 caracteres del cierre inicial; los resultados de aquel cierre se conservan como historia más abajo. Se mantienen los botones independientes de mostrar/ocultar contraseña de la mejora UX anterior.

### Collation y UNIQUE comprobados antes de cambiar la validación

Se consultaron exclusivamente metadatos de la conexión operativa dentro de una transacción READ ONLY: columna `usuarios_sistema.username`, índice y versión del servidor. Resultado: **MariaDB 13.0.2**, `VARCHAR(50) NOT NULL`, charset `utf8mb4`, collation de la columna **`utf8mb4_unicode_ci`**, índice `usuarios_sistema_username_unique` con `NON_UNIQUE=0`. No se consultaron filas de cuentas ni hashes; no se modificaron esquema, datos, credenciales o configuración operativos.

Antes de editar la regla, `UsernameUnicodeTest.php` migró exclusivamente una MariaDB desechable y verificó esa misma collation en la columna. Primer INSERT con `turn_manana`: aceptado. Segundo INSERT con `turn_mañana`: **rechazado con SQLSTATE 23000/error 1062**. Las búsquedas por `turn_mañana` y `TURN_MAÑANA` recuperan el ID de la primera cuenta. Resultado real: ambas grafías son equivalentes para este UNIQUE y **no pueden coexistir como cuentas diferentes**. La validación UNIQUE también se comprueba por HTTP en ambos sentidos. Esto no se deduce de una comparación PHP ni del nombre de la collation. Referencia conceptual: [collations de MariaDB](https://mariadb.com/docs/server/reference/data-types/string-data-types/character-sets/character-set-and-collation-overview); el resultado concreto procede de las inserciones y búsquedas reales descritas.

### Reglas y comportamiento vigentes

- Nuevos usernames: `a-z`, `ñ`, números, punto, guion y guion bajo; máximo 50 caracteres, sin espacios ni mayúsculas. Regex UTF-8 `\A[a-zñ0-9_.-]+\z` con modificador `u`. Se conserva exactamente la grafía admitida, sin transliterarla ni renombrar cuentas. `turn_mañana`, `señor_pan`, `turn_noche` y `operador01` se aceptan cuando no existe una cuenta equivalente. `turn mañana`, `nombre con espacio` y `usuario@@` se rechazan. Otras letras Unicode en usernames nuevos quedan fuera de este alfabeto explícito; el Login conserva compatibilidad con nombres heredados.
- Crear/cambiar contraseña: mínimo **8 caracteres Unicode**, máximo **72 bytes**, string/requerida/confirmada y sin NUL. Se usa `mb_strlen` para caracteres y `strlen` para bytes, sin normalizar ni transliterar contraseñas. No hay combinación obligatoria de mayúsculas/números/símbolos. Se aceptan ñ/Ñ, vocales acentuadas, otros alfabetos y emoji dentro del límite. `Hash::make` y el flujo de autenticación no cambian. El aprovisionamiento inicial del seeder mantiene su política independiente.
- UI: ayuda de username permite ñ y ambos campos password tienen `minlength=8`. La ayuda explica Unicode y 72 bytes; `maxlength=72` HTML conserva su límite de longitud y el backend comprueba los bytes reales. Los ojos, `type=password` inicial, nombres de campos y ausencia de value/old se conservan. Contraseñas y confirmación siguen vacías tras errores.

### Identidad de la clave del RateLimiter

Se elimina `Str::transliterate` de la clave. `LoginController` resuelve únicamente el ID por username mediante el índice UNIQUE y la comparación real de la columna; no selecciona hashes ni filtra activo para esta identidad. La entrada SHA-256 pasa a ser `usuario:<id>|<ip>`. Si no existe cuenta, usa `username:<nombre en minúsculas>|<ip>`, sin transliteración y con namespace separado. El provider recibe el username original; `Auth::attempt` sigue exigiendo cuenta activa. Se conservan cinco fallos/60 segundos, availableIn, hit, clear selectivo, countdown y sesiones.

Así `n`/`ñ`, mayúsculas o ñ descompuesta comparten contador cuando MariaDB los resuelve a la misma cuenta. IDs distintos mantienen contadores distintos. Además de dos usernames nuevos con ñ, se prueba un caso heredado real: `usuario_` seguido de a cirílica U+0430 y `usuario_a` con a latina U+0061. MariaDB permite ambas cuentas y la transliteración anterior generaba la misma clave; ahora bloquear la primera no impide autenticar la segunda desde la misma IP. Este caso no amplía el alfabeto de creación.

Consecuencias: una consulta indexada adicional de solo ID por intento de Login válido por forma, incluso cuando ya está limitado; sin caché nueva ni metadatos de collation en cada petición. El cambio de formato de clave no reutiliza contadores anteriores en curso, que caducan con su ventana de 60 segundos; no se vacía la caché ni se alteran sesiones. No hay migración de collation: permitir que `turn_manana` y `turn_mañana` sean cuentas independientes requeriría una decisión posterior de esquema, fuera de esta tarea.

### Pruebas y límites de esta ampliación

`tests/Feature/UsernameUnicodeTest.php` aporta **47 casos**, incluidos crear y autenticar los nombres admitidos, errores de formato/UNIQUE, crear/cambiar/autenticar Unicode, ocho caracteres exactos, siete caracteres multibyte rechazados, 72 bytes exactos aceptados y exceso rechazado, confirmación y NUL, HTML vacío tras error y separación/agrupación correcta del RateLimiter. `UsuariosTest.php` conserva el rechazo de `turn_mañana` como duplicado equivalente de su fixture `turn_manana`, ya no como formato prohibido, y añade símbolos no permitidos.

```bash
PAO_DISABLE=1 php tests/run-mariadb.php --filter='UsernameUnicode|Usuarios|LoginSecurity|AutorizacionRoles|AutorizacionSeeders|Navigation|TurnosOperador' --compact
node --test --test-isolation=none tests/JavaScript/*.mjs
npm run build
vendor/bin/pint --test app/Http/Controllers/LoginController.php app/Http/Requests/CrearUsuarioRequest.php app/Http/Requests/GestionarUsuarioRequest.php app/Rules/PasswordUsuario.php tests/Feature/UsernameUnicodeTest.php tests/Feature/UsuariosTest.php
git diff --check
```

Resultado: **270 PHP aprobadas/2.880 aserciones, sin fallidas ni omitidas; 45 JS aprobadas; build, Pint de seis PHP y diff aprobados**. Pint inicialmente señaló estilo/imports del test nuevo; se corrigió solo ese archivo y la repetición pasó. El aviso opcional preexistente de fontaine no impide el build. No se repite la suite global: la regresión cubre Usuarios, Login, caché array/database, autorización, navegación, seeders y turnos.

Aislamiento: MariaDB temporal sin red, datadir/base verificados por el runner, migraciones y cuentas ficticias solo allí; sesiones array y pruebas existentes con driver database en la instancia desechable, HTTP/DOM sin Vite y scripts con dobles DOM. La primera prueba previa de UNIQUE pasó en `/tmp/sprint4-b1681b4fcbed`; la regresión final terminó en `/tmp/sprint4-abc95831d229`, con instancia detenida y log `/tmp/panaderia-unicode-regression.log`. Los intentos iniciales de conexión/socket bloqueados por sandbox se repitieron con escalamiento. No se imprime contenido de .env ni credenciales operativas.

**PENDIENTE:** aceptación visual de los textos nuevos en navegador/dispositivos y entorno operativo; esta pasada usa HTTP/DOM/build, no una nueva ejecución de Chrome. La matriz Chrome 16/16 pertenece a la mejora previa de los ojos, no a esta ampliación. Las migraciones/cuentas operativas pendientes del módulo se conservan; no se aplica ninguna aquí. `mejora_asignado.md` sigue ausente; no se recrea. Sin commit/push. Las notas originales afectadas del Vault se actualizan conservando su historia.

## Decisiones de negocio

Fuente: instrucción explícita del usuario en esta tarea. Se mantienen los roles Administrador y Operador; los turnos no son roles. El Administrador gestiona cuentas y registra cualquier turno existente. Operador registra únicamente el turno relacionado con su cuenta. Historial sigue siendo consultable sin restricción por turno.

Las cuentas existentes se conservan activas al evolucionar el esquema. La cuenta administradora conserva username, contraseña, empleado, rol y timestamps; recibe turno NULL. No se interpreta un username como regla de autorización ni se usan IDs fijos.

## Diferencia Usuario vs Empleado

Usuario es la cuenta autenticada que registra una cabecera/lote. Empleado es la persona que participa en producción. `Usuario::empleado()` y `Empleado::usuario()` permanecen como vínculo opcional uno a uno; no se eliminan ni se utilizan para inferir participantes.

Las tres familias conservan exactamente un Maestro y al menos un Ayudante, con Empleados reales y distintos en sus pivotes. Se mantiene la distinción entre creador de sesión, autor del lote y equipo. No se atribuye «Maestro» a una cuenta compartida.

Antes de modificar se buscaron todos los usos de empleado_id y de Usuario/Empleado en app, vistas y seeders. El sidebar ya usaba acceso nullsafe y fallback a username. Los escritores/pivotes de equipo emplean Empleados independientes del usuario autenticado. Las regresiones de navegación, equipos e historial se conservan.

## Cuentas compartidas

`turn_manana` y `turn_noche` son los usernames técnicos previstos. **Son cuentas compartidas y NO identifican a la persona física que produjo.** La UI puede identificar el turno mediante su catálogo; no se crean personas ficticias para satisfacer una FK.

No se crean estas cuentas automáticamente ni se añaden credenciales a un seeder. El Administrador las creará desde Usuarios tras revisar y aplicar la migración. Puede haber varias cuentas para el mismo turno: no se introdujo una regla UNIQUE de turno que el negocio no solicitó.

## Esquema anterior

Auditoría local exclusivamente READ ONLY, con conexión escalada por bloqueo de sandbox: SHOW CREATE TABLE, SHOW INDEX, catálogo de roles/turnos y registro de migraciones. No se consultaron hashes ni contraseñas de cuentas operativas.

Tabla real `usuarios_sistema`: PK id, username VARCHAR(50) NOT NULL UNIQUE, password_hash VARCHAR(255), empleado_id BIGINT UNSIGNED NOT NULL UNIQUE con FK a empleados, rol_id obligatorio con FK a roles, created_at/updated_at. Sin turno_id ni activo. InnoDB y collation utf8mb4_unicode_ci. El catálogo mantiene también un rol heredado Encargado; se preserva, pero no se ofrece para nuevas cuentas ni recibe permisos.

Las 28 migraciones existentes constaban aplicadas; la última era la de código canónico de unidades, batch 6. Esta lectura sustituye para este momento la referencia documental anterior que la dejaba pendiente. No se ejecutó ninguna migración, seeder o modificación de datos sobre esa conexión operativa.

## Migraciones nuevas

Archivo nuevo: `database/migrations/2026_10_16_100000_add_access_fields_to_usuarios_sistema.php`. No se editan migraciones antiguas.

| Campo | Evolución |
| --- | --- |
| empleado_id | BIGINT UNSIGNED nullable; conserva UNIQUE y FK a empleados |
| turno_id | BIGINT UNSIGNED nullable, FK a turnos, sin asignaciones automáticas |
| activo | BOOLEAN NOT NULL DEFAULT true; cuentas anteriores quedan activas |

La migración retira/recrea la FK de empleado al cambiar nullabilidad y conserva su índice UNIQUE. Se verificaron los metadatos reales antes/después en MariaDB aislada, además de inserciones que prueban múltiples NULL, rechazo de empleado repetido no NULL y rechazo de referencias inexistentes. La fila anterior, incluidos hash y timestamps ficticios, se compara íntegramente antes/después.

down bloquea antes de DDL si hay cuentas sin Empleado, turnos asignados o cuentas inactivas, para evitar pérdidas o reactivaciones implícitas. También se probó la reversión segura cuando no hay datos incompatibles. Como otras evoluciones del proyecto, varios ALTER de MariaDB no forman una transacción DDL reversible ante cualquier caída; aplicar sin escritores concurrentes y con la revisión habitual de la base.

**Pendiente de aplicación por el usuario**, después de revisar. Comando selectivo de referencia, no ejecutado sobre la base de trabajo:

```bash
php artisan migrate --path=database/migrations/2026_10_16_100000_add_access_fields_to_usuarios_sistema.php
```

El nuevo código consulta activo/turno_id: el esquema debe estar aplicado antes de servir esta versión. No ejecutar migrate:fresh/refresh ni DatabaseSeeder para activar este módulo. Las reconstrucciones destructivas de los tests se limitan al servidor desechable verificado por IsolatedMariaDb.

## Estado activo/inactivo

Usuario incorpora cast boolean de activo y relación turno(). Conserva autenticación password_hash, getAuthPasswordName, empleado y rol. password_hash ya era fillable en este checkout: no se amplió esa capacidad; los formularios no aceptan hashes y la Action persiste únicamente Hash::make de la contraseña validada.

Auth::attempt añade activo=true a las credenciales de consulta. Inactivo, inexistente o contraseña incorrecta producen el mismo mensaje genérico, conservan username y nunca password; todos esos fallos consumen el RateLimiter. La ampliación Unicode modifica únicamente la identidad de su clave como se detalla arriba; conserva ventana, conteo, limpieza al éxito y countdown de UX.

EnsureUsuarioActivo se aplica de forma central a todo el grupo protegido después de auth: si activo es false, ejecuta logout, invalidate y regenerateToken y redirige al Login. El provider recupera la cuenta en cada nueva petición; no se comprueba manualmente en cada controller. Se probó la pérdida de acceso a Dashboard, Producción e Historial con sesiones array y database en MariaDB temporal, incluyendo cambio de ID/token y eliminación de datos de sesión. Las cuentas activas conservan acceso.

## Relación Usuario-Turno

La fuente es usuarios_sistema.turno_id → turnos.id. Crear un Operador exige un turno existente; crear Administrador guarda NULL, incluso si se proporciona un turno válido. empleado_id se omite de la primera UI y se guarda NULL en cuentas nuevas; el administrador existente no se reasocia.

No existe un if por turn_manana/turn_noche ni una constante de ID Mañana/Noche. Los tests cambian el nombre de la cuenta y asignan Noche para demostrar que la relación manda. Un Operador legado sin turno puede autenticarse y consultar las páginas permitidas, pero no registrar; muestra aviso controlado. Esta versión no incorpora edición de rol/turno de cuentas ya existentes.

## Permisos Administrador

Mantiene Dashboard, Producción, Historial y Productos; agrega Usuarios. Las seis rutas nuevas están bajo auth, middleware de cuenta activa, auth.session y can:administrar-sistema. Form Requests reutilizan ese mismo Gate. No hay un sistema nuevo de permisos, roles de turno ni paquetes adicionales.

Listado paginado de 15 cuentas, con eager loading de rol/turno/empleado; muestra usuario, rol, turno, estado, Empleado o «Cuenta compartida», y acciones. Crear usuario, activar/desactivar y establecer contraseña están disponibles solo para Administrador. No hay DELETE, registro público ni recuperación por email.

| Método | Ruta | Función |
| --- | --- | --- |
| GET | /usuarios | Listado |
| GET | /usuarios/crear | Formulario de creación |
| POST | /usuarios | Crear |
| PATCH | /usuarios/{usuario}/estado | Asignar estado explícito |
| GET | /usuarios/{usuario}/password | Formulario de contraseña |
| PATCH | /usuarios/{usuario}/password | Establecer contraseña |

Los cambios de estado son idempotentes. No se puede desactivar al último Administrador activo, ni siquiera a sí mismo. Con otro Administrador activo sí se permite desactivar la propia cuenta; su siguiente petición queda revocada por el middleware. No se depende del ID del administrador inicial.

AdministrarUsuarios ejecuta las escrituras en transacciones: bloquea los roles canónicos en orden, revalida al actor actual activo/Administrador, bloquea la cuenta objetivo y usa una lectura actual bloqueada para contar Administradores activos. Ese orden serializa cambios y evita que dos desactivaciones concurrentes dejen cero administradores. Una prueba con dos procesos/conexiones reales conserva exactamente uno. Las creaciones conservan además UNIQUE de username como respaldo; una colisión SQL se convierte en feedback controlado sin imprimir bindings.

## Permisos Operador

Dashboard, Producción e Historial; Usuarios y Productos denegados por backend y sin enlaces en sidebar. Puede consultar Historial de otros turnos. No crea cuentas, cambia contraseñas, activa/desactiva usuarios ni administra personas. No se restringe la consulta por username.

## Restricción de turno

RegistrarProduccionPanRequest y RegistrarProduccionPorCantidadRequest comparten la regla TurnoPermitidoUsuario; Torta/Bocadito heredan esta última. Se exige ID entero existente y, para Operador, igualdad con su turno real. Sin turno asignado devuelve error de configuración; rol desconocido no puede saltarse la regla.

La misma política está en ValidarTurnoUsuario. ResolverCabeceraProduccion, punto común de las Actions de las tres familias, la revalida antes de crear/reutilizar la cabecera y antes de cualquier detalle/pivote/foto. Lee la cuenta con lock compartido dentro de la transacción, estabilizando activo/rol/turno hasta terminar. Así tampoco basta llamar una Action directamente con un turno ajeno o un rol desconocido. Los endpoints usan los Gates previos como primera barrera.

Se rechaza la inconsistencia de un POST manipulado, con error turno_id y ninguna persistencia. No se sustituye silenciosamente el turno del POST. La matriz cubre Admin Mañana/Noche, ambos Operadores en su turno/en turno ajeno y Operador sin turno, para Pan/Torta/Bocadito.

## Cambios en Producción

Administrador conserva el selector completo del catálogo. Operador ve «Turno asignado» en un input readonly y un hidden con el ID para compatibilidad con el JS existente. ConsultarProduccionRequest fija el contexto GET de registro al turno del Operador, sin confiar en query/old input manipulados. Esto no modifica los filtros de Historial.

Operador sin turno recibe aviso y Guardar deshabilitado; POST directo sigue rechazado. No se modifica produccion.js, el cálculo de cantidades, Maestro/Ayudantes, unidades, snapshots, hora de Pan, cantidades directas de otras familias ni el almacenamiento/compensación de fotos.

Usuarios usa layout propio con sidebar, reutiliza estilos administrativos verde/beige existentes y añade una hoja pequeña para los datos de cuenta. usuarios.js alterna la obligatoriedad/visibilidad/envío de turno según el rol seleccionado e inicializa los ojos mediante el módulo común contrasenas.js, compartido con Login. Sin JS, el selector sigue disponible y las mismas reglas backend se aplican. Password y confirmación carecen de value/old; los errores se escapan y los controles tienen labels/ARIA.

## Gestión de contraseñas

Política vigente tras la ampliación Unicode: required, string, mínimo 8 caracteres, máximo 72 bytes y sin caracteres nulos, con confirmación coincidente. El cierre inicial exigía 12 caracteres; esta nueva instrucción explícita cambia ese mínimo a 8. El máximo respeta bcrypt y evita truncamiento silencioso; se aceptan frases/Unicode dentro de esos límites, sin requisitos arbitrarios de mayúsculas/números/símbolos. No se aplica esta política al Login ni se cambia la credencial inicial ya existente. Se recomienda una contraseña única y aleatoria al crear cuentas reales.

La Action calcula Hash::make antes de persistir y solo actualiza el objetivo. No se solicita la contraseña anterior del Operador. Los Requests y errores controlados conservan únicamente campos no sensibles acotados; nunca contraseñas, confirmación, hashes o campos adicionales. Los errores SQL de escritura se registran solo con un mensaje fijo y SQLSTATE, evitando que una excepción completa incluya bindings de credenciales.

Se activa AuthenticateSession nativo mediante auth.session sobre rutas protegidas. Las sesiones que ya tienen su huella registrada pierden acceso en la siguiente petición cuando cambia el hash; se probó con drivers array/database, sin afectar al administrador que cambió la contraseña. La contraseña anterior deja de autenticar y la nueva autentica.

**Límite real:** sesiones heredadas abiertas antes de usar auth.session pueden carecer de esa huella. El middleware nativo la inicializa en la primera petición protegida; no se promete que un cambio de contraseña previo a esa inicialización las revoque. Desactivación sí comprueba el estado actual independientemente de la huella. No se borran masivamente sesiones ni se añade infraestructura compleja de versiones/revocación.

## Archivos modificados

Esta tarea agrega/modifica, además de preservar el trabajo anterior del Login:

| Área | Rutas |
| --- | --- |
| Esquema/modelo | database/migrations/2026_10_16_100000_add_access_fields_to_usuarios_sistema.php; app/Models/Usuario.php |
| Acceso | app/Http/Controllers/LoginController.php; app/Http/Middleware/EnsureUsuarioActivo.php; routes/web.php |
| Módulo | app/Http/Controllers/UsuarioController.php; app/Actions/Usuarios/AdministrarUsuarios.php |
| Requests de Usuarios | app/Http/Requests/GestionarUsuarioRequest.php, CrearUsuarioRequest.php, CambiarEstadoUsuarioRequest.php, CambiarPasswordUsuarioRequest.php |
| Reglas | app/Rules/PasswordUsuario.php; app/Rules/TurnoPermitidoUsuario.php |
| Producción | app/Actions/Produccion/ValidarTurnoUsuario.php; ResolverCabeceraProduccion.php; app/Http/Requests/RegistrarProduccionPanRequest.php, RegistrarProduccionPorCantidadRequest.php, ConsultarProduccionRequest.php |
| Vistas | resources/views/usuarios/layout.blade.php, index.blade.php, create.blade.php, password.blade.php, password-fields.blade.php; resources/views/produccion/index.blade.php; resources/views/components/sidebar.blade.php |
| Assets | resources/css/usuarios.css; resources/js/usuarios.js; vite.config.js |
| Pruebas nuevas | tests/Feature/UsuariosTest.php, UsuariosMigrationTest.php, UsuariosConcurrenciaTest.php, TurnosOperadorTest.php; tests/JavaScript/UsuariosTest.mjs; tests/Support/usuario-estado-concurrente.php |
| Fixtures/regresión | tests/Support/IsolatedMariaDb.php; tests/Feature/AutorizacionRolesTest.php, NavigationTest.php, ProductionObservationsMigrationTest.php, DashboardTest.php, HistorialProduccionTest.php, ProduccionPanStoreTest.php, ProduccionTortaBocaditoStoreTest.php, RegistrarProduccionPanActionTest.php, ProductosTest.php |
| Informe | docs/USUARIOS_OPERADORES_TURNOS.md |

Las adaptaciones de pruebas conservan sus verificaciones de negocio: los escenarios que registran/consultan cualquier turno usan Administrador; los de autorización/turno prueban explícitamente Operador y rechazos. NavigationTest espera activo=true en el provider simulado. La prueba histórica de las 21 migraciones originales excluye la nueva evolución. El sidebar esperado pasa a cinco enlaces para Admin, conservando tres para Operador. No se modifica el código de Dashboard/Historial/Productos.

## Tests agregados

- Migración: fila previa intacta, activo/default, nullabilidad, FK/UNIQUE reales y pruebas de inserción/rechazo; down protegido y reversión segura, todo en MariaDB desechable.
- Usuarios: autorización de los seis endpoints, listado/formularios sin hashes/contraseñas, creación Operador/Admin, turno condicional/existente, Empleado opcional, username ASCII/único/50 caracteres, tipos y política/confirmación de contraseña, old input seguro.
- Estado: desactivación/reactivación idempotente sin cambiar credenciales/relaciones, Login inactivo genérico y limitado, revocación de sesiones array/database con limpieza y nuevo ID/token, último administrador protegido y carrera real de dos conexiones.
- Password: hash solo del objetivo, credencial anterior rechazada/nueva aceptada, campos vacíos tras error y revocación nativa de sesiones inicializadas, en ambos drivers.
- Turnos: matriz limpia de siete escenarios por tres familias, revalidación directa de Actions para Operador/rol desconocido, sin cabeceras/detalles/pivotes/fotos al denegar; UI Admin/Operador, falta de turno e Historial consultable para otros turnos.
- JS: cinco casos de selector condicional; se ejecutan también los 37 casos previos, incluidos ocho de countdown.

## Resultados reales

```bash
PAO_DISABLE=1 php tests/run-mariadb.php --compact --filter='Usuarios|TurnosOperador|LoginSecurityTest'
PAO_DISABLE=1 php tests/run-mariadb.php --compact
node --test --test-isolation=none tests/JavaScript/*.mjs
npm run build
vendor/bin/pint --test [lista explícita de PHP nuevos/modificados]
git diff --check
```

| Verificación | Resultado |
| --- | --- |
| Específicos finales | **122 aprobadas / 0 fallidas, 1.206 aserciones** |
| Regresión ampliada de escenarios adaptados | **525 aprobadas / 0 fallidas, 4.466 aserciones** |
| Suite PHP final | **741 aprobadas / 0 fallidas / 0 omitidas, 6.878 aserciones** |
| JS completo | **42 aprobadas / 0 fallidas** |
| Build | **APROBADO**, Vite 8.3.0; aviso opcional de fontaine preexistente |
| Pint sobre 37 PHP nuevos/modificados del checkout | **APROBADO** |
| git diff --check | **APROBADO** |

Primera regresión inicial de migración/login/navegación/permisos: 125 aprobadas/1.454 aserciones. Específicos iniciales: 109/1.129. Primera suite completa: 717 aprobadas/20 fallidas por fixtures que asumían Operadores sin turno o con acceso a cualquier turno, borrado de un turno ahora referenciado y expectativas de selector/sidebar anteriores. Se ajustaron esas fixtures a Administrador donde el escenario requiere varios turnos, sin retirar assertions de cantidades/equipo/fotos/historia. La repetición ampliada aprobó los 525 casos. Pint detectó imports/estilo en el worker PHP nuevo; se corrigió únicamente ese archivo y pasó la repetición de los 37 PHP. No se ejecutó Pint global en escritura ni se corrigieron diferencias ajenas.

La revocación database se verificó en la tabla sessions de la BD temporal, además de array. HTTP usa withoutVite; assets compilados por separado. JS ejecuta scripts reales con dobles DOM; no se ejecutó navegador ni se certifica geometría responsive o lector de pantalla. Logs de pruebas en /tmp/usuarios-specific-final-tests.log, /tmp/usuarios-regression-tests.log y /tmp/usuarios-full-final-tests.log. La última suite duró 58,62 s en /tmp/sprint4-ea315c79d186 y su servidor quedó detenido. Cada runner verifica socket/datadir/base de dos bases ficticias bajo /tmp y detiene su instancia al terminar; ninguna prueba escribe datos operativos.

## Riesgos

- La aplicación operativa del nuevo esquema sigue pendiente. Servir el código antes de aplicarlo produciría consultas a columnas ausentes.
- La trazabilidad de una cuenta compartida identifica la cuenta registradora y su turno, no una persona: la identidad humana sigue en los participantes.
- Las garantías de último administrador y coherencia de escritura dependen de usar las Actions y sus locks. SQL/manual u otros futuros escritores deben respetarlas; el esquema no codifica roles mediante IDs.
- La revocación por contraseña tiene el límite documentado de sesiones heredadas sin huella. No se certifica el entorno operativo por las pruebas aisladas.
- UI responsive usa los estilos existentes y fue comprobada por HTML/DOM/build; falta aceptación visual en navegador/dispositivos reales.

## Pendientes

1. Revisar la migración, seguridad, permisos, diseño y pruebas; aplicar después únicamente la migración nueva sobre la base operativa, por decisión del usuario.
2. Tras aplicar, crear manualmente turn_manana y turn_noche desde la interfaz, con contraseñas privadas y los turnos correctos. No se crearon durante esta tarea.
3. Rotar explícitamente la credencial administradora heredada, pendiente del cierre anterior; no se cambió su contraseña ni su Empleado.
4. Aceptación manual y revisión del entorno real de sesiones/HTTPS/cookie segura. No se implementa un servidor ni despliegue.
5. Edición de roles/turnos de cuentas existentes, gestión de Empleados, recuperación y MFA quedan fuera de este módulo básico. Las cuentas Operador anteriores sin turno requieren configuración explícita antes de registrar; no se infiere ni asigna automáticamente.

La documentación original se actualizó en 12 notas existentes del Vault, mediante secciones fechadas y verificación de historia íntegra: PERSONAL Y USUARIOS/Usuarios.md, Turnos.md, Permisos.md y Pendientes.md; BASE DE DATOS/Migraciones.md y TABLAS/Usuarios sistema.md; LARAVEL/Controllers.md, Requests.md y Middleware.md; DECISIONES/Decisiones Laravel.md; PRODUCCION/Logica de negocio.md; INICIO/Estado de proyecto.md. mejora_asignado.md continúa ausente; no se recrea ni se guarda una copia del Vault en el repositorio. No hay autorizaciones pendientes para esta documentación.

## Pruebas manuales

Realizar primero sobre una copia desechable del proyecto y datos ficticios. La base operativa solo se modificará después de la revisión del usuario.

1. Como Admin, abrir Usuarios: cinco enlaces de sidebar, cuenta existente con Empleado intacto y cuentas compartidas sin persona inventada. Revisar teclado y anchos móvil/tablet/escritorio.
2. Crear turn_mañana como Operador/Mañana y turn_noche como Operador/Noche, con credenciales privadas. Comprobar ñ admitida y duplicado equivalente de turn_manana/turn_mañana: no pueden coexistir con esta collation. Verificar rechazo de espacios/símbolos ajenos, confirmación y turno omitido/inexistente; nunca se rellenan passwords tras fallar. Comprobar Unicode de al menos ocho caracteres y hasta 72 bytes, sin combinación obligatoria.
3. Como Operador, abrir por URL o construir POST/PATCH a Usuarios/Productos: acceso denegado. Dashboard/Producción/Historial siguen disponibles.
4. Como Admin, registrar cada familia en Mañana y Noche. Como cada Operador, verificar turno readonly y registro correcto solo en su turno. Manipular hidden/POST hacia el otro: error y ausencia de filas/fotos nuevas.
5. Revisar que Maestro y Ayudantes sigan siendo Empleados seleccionados, y que Historial distinga cuenta registradora, equipo y turno. Consultar otros turnos como Operador.
6. Mantener una sesión Operador abierta y desactivarla desde otra sesión Admin. La siguiente petición protegida debe cerrar sesión y volver al Login. El nuevo intento de Login muestra el mismo error genérico, con rate limit/countdown conservados. Reactivar y comprobar Login.
7. Cambiar la contraseña del Operador sin introducir la anterior. Verificar autenticación anterior rechazada/nueva aceptada y revocación de sesiones inicializadas, sin cerrar otra cuenta.
8. Intentar desactivar el único Administrador activo: rechazo claro y cuenta activa. Con dos administradores ficticios comprobar la regla sin afectar cuentas operativas.
9. Comprobar fallback sin JS en creación: turno visible y validación backend correcta; controles de contraseñas siempre vacíos. No enviar ni copiar credenciales a informes/logs.
