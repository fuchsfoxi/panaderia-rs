# Sprint 6 — Fase 2


## Autorización Administrador / Operador

Fecha: 2026-10-12. Checkout inicial: `cec3407`, repositorio local `panaderia-rs`.

**Estado: IMPLEMENTADO Y COMPROBADO mediante HTTP y MariaDB aislada.** La aceptación visual en navegador real y los módulos administrativos permanecen **PENDIENTES**. No se acredita despliegue, commit o push.

### Reanudación después del reinicio — 2026-10-12

Se ejecutaron primero `git status`, `git diff --stat` y `git diff`, y se revisaron los cinco archivos sin seguimiento. HEAD sigue en `cec3407`, rama `master`: **14 archivos rastreados modificados y 5 archivos nuevos sin seguimiento**. No se descartó, restauró ni sobrescribió trabajo sin revisar. Se leyó íntegramente Fase 1 y este informe recuperado.

**Ya estaba hecho:** constantes de Rol, helpers de Usuario, ambos Gates, seis rutas operativas bajo `auth`/`can`, logout fuera del Gate, página 403 y su CSS/Vite, sidebar con `@can`, tres seeders por identidad, fixtures, regresiones de consultas y 80 pruebas de autorización/seeders. El contrato `getAuthPasswordName()` también estaba implementado desde antes de esta fase. La revisión y las pruebas nuevas confirman que no quedó una implementación funcional incompleta dentro del alcance autorizado.

**Faltaba cerrar:** comprobar otra vez este checkout tras el reinicio, distinguir evidencia nueva del registro previo y actualizar las notas originales del Vault, que todavía describían autorización pendiente. **Completado ahora:** verificación completa, esta sección de recuperación, actualización del mismo Problema 7 en `mejora_asignado.md` e integración autorizada en 11 notas del Vault. **No fue necesario volver a modificar aplicación, rutas, seeders o tests ni crear archivos adicionales en el repositorio.** Los cinco archivos sin seguimiento ya estaban presentes al reanudar.

| Verificación ejecutada en la reanudación | Resultado exacto |
|---|---|
| `php tests/run-mariadb.php --compact --do-not-cache-result --filter=Autorizacion` | **80/80 aprobadas, 719 aserciones**, salida 0; instancia `/tmp/sprint4-778dd2a60999`, detenida. |
| `php tests/run-mariadb.php --compact --do-not-cache-result` | **439/439 aprobadas, 4.386 aserciones, 0 fallidas y 0 omitidas**, salida 0; instancia `/tmp/sprint4-bfd38defa675`, detenida. |
| `node --test-reporter=spec tests/JavaScript/ProduccionCantidadTest.mjs` | **12 aprobadas, 0 fallidas y 0 omitidas**, salida 0. |
| `node --test-reporter=spec tests/JavaScript/SidebarTest.mjs` | **7 aprobadas, 0 fallidas y 0 omitidas**, salida 0. |
| `npm run build` | Salida 0, incluye `errores.css`; aviso preexistente de fontaine opcional. Sin instalar paquetes. |
| `vendor/bin/pint --dirty --test` | Aprobado, salida 0. |
| `vendor/bin/pint --test tests/Feature/AutorizacionRolesTest.php tests/Feature/AutorizacionSeedersTest.php` | Aprobado, salida 0; revisión explícita de PHP nuevo sin seguimiento. |
| `git diff --check` y comprobación de espacios de los cinco archivos nuevos | Sin errores. |
| `php artisan route:list -vv --except-vendor` | Diez rutas de aplicación; seis con `auth` + `can:acceder-operacion`; logout solo `auth` como restricción de identidad. |

El primer intento de MariaDB dentro del sandbox terminó con salida 1 **antes de ejecutar pruebas**: `Bind on unix socket: Operation not permitted`, instancia `/tmp/sprint4-9abcdf55b847` detenida. Se repitió con autorización de escalamiento. No constituye un fallo del producto ni se utilizó la base operativa como alternativa.

El runner crea MariaDB sin red y únicamente dos bases ficticias; `IsolatedMariaDb` verifica socket, datadir y nombre de base antes de migrar. HTTP usa sesiones array/`withoutVite`; las pruebas de relación ausente la simulan en memoria sin romper FKs. Los tests de autorización no omiten Gates. La suite completa conserva las regresiones de autenticación/rehash, navegación/CSRF, Dashboard, Producción, Historial, migraciones, concurrencia y proxies. No hubo lectura o escritura de la base operativa, seeders reales, eliminación de registros, cambios de lógica de Producción o nuevos CRUD.

**DOCUMENTACIÓN MODIFICADA ahora:** este informe, `mejora_asignado.md` y las notas originales siguientes, bajo `/home/lukagfv/Documentos/Obsidian Vault/PROYECTO_LARAVEL/`:

- `PERSONAL Y USUARIOS/Roles.md`, `Permisos.md`, `Usuarios.md` y `Pendientes.md`: matriz funcional y alcance pendiente.
- `LARAVEL/Models.md`, `Routes.md`, `Middleware.md` y `Blade.md`: responsabilidades técnicas, seeders, rutas y 403.
- `INICIO/Estado de proyecto.md` y `Roadmap.md`: resumen comprobado y desarrollo posterior.
- `DECISIONES/Decisiones Laravel.md`: nombre real existente, decisión fechada con contexto, opciones, motivo y consecuencias.

Se inspeccionó la estructura, se buscaron los temas y se leyeron íntegramente las 11 notas antes de escribir. La escritura se autorizó mediante escalamiento, se verificó que no habían cambiado durante la revisión y se comprobó la conservación íntegra de su texto previo. No se copiaron notas del Vault al repositorio, ni se crearon/renombraron notas o carpetas. No correspondió modificar estructura de base de datos, flujo/reglas de Producción ni agregar una entrada duplicada de bugs. **No quedó autorización pendiente.**

Las secciones siguientes conservan el registro encontrado antes del reinicio. Sus resultados históricos no se ejecutaron de nuevo para reproducir fallos ya corregidos; la evidencia nueva es la tabla anterior. Los módulos administrativos, estados, decisiones del último Administrador, catálogo sin UNIQUE, sesiones database y aceptación visual/operativa permanecen PENDIENTES según los límites detallados más abajo.

### Estado inicial

Se leyó completo `SPRINT_6_FASE_1_ROLES_PERMISOS.md` antes de cambiar código y se contrastó con el checkout. Usuario ya autentica mediante guard `web`, provider Eloquent y `password_hash`, incluyendo `getAuthPasswordName()`. Todas las rutas internas usaban únicamente `auth`, sin Gates, Policies o filtros por rol. El sidebar ofrecía tres módulos comunes.

Reproducción previa: un test HTTP con una cuenta ficticia Encargado esperaba 403 en Dashboard y obtuvo 200: **1 prueba fallida, 2 aserciones**. Este rechazo era el comportamiento nuevo requerido, no un fallo de autenticación.

No existen CRUD ni rutas de Productos, Empleados, Usuarios o Configuración. Continúan fuera de esta fase.

### Datos reales registrados en el informe recuperado

El informe encontrado atribuye los siguientes datos al usuario y a una verificación manual mediante Tinker. **Es un antecedente recuperado, no una comprobación nueva de esta reanudación**; no se volvió a consultar ni se modificó la base operativa:

| ID observado en esa base | Nombre |
|---|---|
| 1 | Administrador |
| 2 | Encargado |
| 3 | Operador |

Único usuario real actual comunicado: **admin / Administrador**, relacionado con **Carlos M.** No hay usuarios asociados a Encargado. Los IDs son datos de esa base y no contratos de autorización. No se eliminaron esos registros ni se crearon cuentas operativas.

### Cambios realizados

| Archivo | Cambio |
|---|---|
| `app/Models/Rol.php` | Constantes `ADMINISTRADOR` y `OPERADOR`; formato ajustado con Pint. Relaciones y tabla conservadas. |
| `app/Models/Usuario.php` | Helpers `esAdministrador()`, `esOperador()`, `puedeAccederOperacion()`. |
| `app/Providers/AppServiceProvider.php` | Registro de las dos capacidades mediante Gates. |
| `routes/web.php` | Subgrupo `can:acceder-operacion` dentro de `auth`; logout fuera del subgrupo. Mismas URLs/nombres. |
| `resources/views/components/sidebar.blade.php` | `@can('acceder-operacion')` sobre los tres enlaces existentes. Sin enlaces administrativos. |
| `resources/views/errors/403.blade.php` — creado | Página de rechazo, destinos según autorización y logout POST/CSRF. |
| `resources/css/errores.css` — creado | Paleta, tarjeta adaptable, controles y foco visible para la página 403. |
| `vite.config.js` | Entrada CSS de errores para `@vite` y build. |
| `database/seeders/RolSeeder.php` | Dos roles funcionales mediante `firstOrCreate`, sin borrar roles previos. |
| `database/seeders/EmpleadoSeeder.php` | Cargos resueltos por nombre mediante `sole()`; empleados por nombre/número mediante `firstOrCreate`; identidad de empleado de demostración centralizada. |
| `database/seeders/UsuarioSeeder.php` | Resuelve Administrador y empleado por identidad, rechaza resultados ambiguos; si admin existe, lo conserva sin reasignar rol, empleado o contraseña. |
| `tests/Support/IsolatedMariaDb.php` | Fixtures con Operador por defecto y parámetro de nombre de rol. No convierte nombres desconocidos. |
| `tests/Feature/NavigationTest.php` | Rol Operador precargado en la cuenta de navegación en memoria. Gates reales activos. |
| `tests/Feature/DashboardTest.php` | Reinicia la relación de rol antes de cada medición de consultas. Conserva límite y comprobación de crecimiento. |
| `tests/Feature/HistorialProduccionTest.php` | Mismo ajuste en dos mediciones de consultas; conserva comprobaciones de carga de pivots. |
| `tests/Feature/AutorizacionRolesTest.php` — creado | Matriz, helpers/Gates, HTTP, invitados, rechazo/logout, IDs arbitrarios, 403, tres familias y cambio de rol en sesión. |
| `tests/Feature/AutorizacionSeedersTest.php` — creado | Idempotencia, preservación, referencias con IDs arbitrarios y rechazo de rol ambiguo. |
| `mejora_asignado.md` | Actualización del mismo Problema 7 con estado, evidencia y límites; diagnóstico original conservado. |
| `SPRINT_6_FASE_2_AUTORIZACION.md` — creado | Este informe. Fase 1 permanece como auditoría histórica sin modificaciones. |

No se cambiaron login, guard/provider, métodos de contraseña, controllers/Requests/Actions operativos, modelos de producción, turnos, snapshots ni migraciones. No se añadieron paquetes, Policies anticipadas, middleware propio, tablas de permisos o estados de usuarios/empleados.

### Rol

`Rol::ADMINISTRADOR = 'Administrador'` y `Rol::OPERADOR = 'Operador'` centralizan los nombres funcionales. La autorización compara exactamente `rol.nombre_roles`: no usa IDs, cargos, username, texto parcial ni roles de participación Maestro/Ayudante.

Encargado no pertenece a la matriz de destino y queda denegado sin eliminarse. No se añade un rol alternativo ni se promueve un rol desconocido.

### Usuario

- `esAdministrador(): bool`: verdadero únicamente con nombre canónico Administrador.
- `esOperador(): bool`: verdadero únicamente con nombre canónico Operador.
- `puedeAccederOperacion(): bool`: verdadero para cualquiera de los dos anteriores.

Los helpers consultan la relación Eloquent existente y utilizan acceso nullsafe. Si no hay relación, devuelven false. No lanzan un error 500 por esa ausencia. La relación se carga una vez por instancia mediante el mecanismo normal de Eloquent; no se guardan privilegios permanentemente en sesión.

La siguiente petición real recupera Usuario desde el provider; el test de cambio de rol reinicia el guard entre peticiones para reproducir esa recarga y comprueba que una sesión previamente válida recibe 403 tras persistir Encargado. No se cambió la arquitectura de autenticación.

### Gates

| Capacidad | Administrador | Operador | Encargado / desconocido / sin rol |
|---|---|---|---|
| `acceder-operacion` | Permitido | Permitido | Denegado |
| `administrar-sistema` | Permitido | Denegado | Denegado |

Registrados en `AppServiceProvider::boot()`, con `Usuario` como identidad. No hay un `Gate::before()` que permita indiscriminadamente todas las acciones. `administrar-sistema` está implementado y probado, pero **no protege módulos reales aún inexistentes**. Al crear cada módulo deberá aplicarse en sus rutas y acciones pertinentes.

El alias `can` ya es nativo de Laravel 13 y no requiere cambios en `bootstrap/app.php`. La regla usada en Blade es la misma del backend; ocultar enlaces es presentación, no la protección de solicitudes directas.

### Rutas

| Método | URL | Nombre | Protección |
|---|---|---|---|
| GET/HEAD | `/dashboard` | `dashboard` | `auth` + `can:acceder-operacion` |
| GET/HEAD | `/produccion` | `produccion.index` | Igual |
| POST | `/produccion` | `produccion.store` | Igual |
| POST | `/produccion/torta` | `produccion.torta.store` | Igual |
| POST | `/produccion/bocadito` | `produccion.bocadito.store` | Igual |
| GET/HEAD | `/history` | `history.index` | Igual |
| POST | `/logout` | `logout` | **Solo `auth` como restricción de identidad**, conservando sesión/CSRF del stack web |

GET/POST login mantienen `guest`; raíz y salud mantienen su comportamiento. `route:list -vv --except-vendor` confirma diez rutas de aplicación y los filtros nuevos. No se crearon rutas administrativas ficticias, ni siquiera para comprobar su Gate mediante HTTP.

### Flujo

```text
Petición web
→ sesión y protección de solicitudes del framework
→ auth
→ can:acceder-operacion
→ Gate → Usuario → Rol por nombre canónico
→ Form Request y su validación existente
→ Controller
→ Action y sus reglas/transacción existentes
```

En logout el flujo omite el Gate operativo. En futuros módulos administrativos habrá que aplicar `can:administrar-sistema` antes de ejecutar acciones administrativas.

### 403

Autenticado sin permiso: HTTP 403. Invitado HTML: redirección al login. Invitado JSON: HTTP 401, conservando el comportamiento anterior. Los rechazos JSON autenticados permanecen 403 mediante el manejo de excepciones existente.

La vista muestra **«No tienes permisos para acceder a esta sección.»**, sin exponer Gates, roles o rutas de diagnóstico. Usa la paleta `#33403A`, `#4F6355`, `#7D9481`, `#AEC0AC`, `#DCD6C4`, `#F6F2EF`, Huninn con fallback y controles con foco visible.

Solo muestra «Volver al Dashboard» si pasa `acceder-operacion`. Una cuenta sin acceso operativo puede cerrar sesión y regresar al login, evitando enviarla otra vez a una zona prohibida. Logout es formulario POST con `@csrf`; una vista 403 sin autenticación ofrece login. Se comprobó el HTML/DOM de destinos y formulario para ambas identidades válidas y las tres clases de rechazo.

No se añadieron enlaces administrativos al sidebar. Administrador y Operador conservan Dashboard, Producción e Historial; estilos y JavaScript del sidebar permanecen.

### Seeders

**No se ejecutaron contra la base local operativa.** Todas las pruebas de seeders escribieron únicamente en la base temporal verificada.

- RolSeeder prepara solo Administrador/Operador en una instalación nueva; repetirlo reutiliza los roles por nombre y conserva IDs. Encargado existente se conserva, así como otros registros previos. No sanea duplicados heredados ni reasigna cuentas.
- EmpleadoSeeder elimina los IDs fijos de cargo. `sole()` exige una coincidencia exacta y rechaza dependencias ausentes o ambiguas; no elige silenciosamente la primera. Identifica sus empleados de demostración por nombre y número, reutilizándolos sin actualizar datos anteriores. Los dos campos no son una nueva garantía UNIQUE en el esquema.
- UsuarioSeeder elimina `rol_id = 1` y `empleado_id = 1`. Resuelve el rol por constante y el empleado por la identidad de demostración centralizada en EmpleadoSeeder. Conserva la generación previa de contraseña de demostración; no se modificó su política ni se registra su valor en este informe. Si admin ya existe, no lo sobrescribe ni lo vuelve a promover.

Idempotencia comprobada para estos **tres seeders individualmente**. No se declara idempotente `DatabaseSeeder` completo: CargoSeeder y otros catálogos originales tienen comportamientos distintos y quedaron fuera del cambio. No ejecutar seeding completo para reparar una base existente. La idempotencia secuencial no constituye una garantía de concurrencia sin restricciones únicas.

No se crean `operador_manana` ni `operador_noche`; las cuentas de test son ficticias y aisladas. Nombre de cuenta y turno siguen siendo independientes.

### Pruebas registradas antes del reinicio

| Comando | Resultado exacto |
|---|---|
| `php tests/run-mariadb.php --do-not-cache-result --filter='Encargado recibe 403 en Dashboard'` — antes de implementación | **1 fallida, 2 aserciones**: esperaba 403 y recibió 200. |
| `php tests/run-mariadb.php --do-not-cache-result --filter=Autorizacion` | **80 aprobadas, 719 aserciones**, salida 0. |
| `php tests/run-mariadb.php --compact --do-not-cache-result` — primera regresión | **439 pruebas: 436 aprobadas, 3 fallidas; 4.384 aserciones**. Diferencias de una consulta por relación de rol retenida en el mismo usuario de testing. |
| `php tests/run-mariadb.php --compact --do-not-cache-result` — final | **439/439 aprobadas, 4.386 aserciones, 0 fallidas y 0 omitidas**, salida 0. |
| `node --test-reporter=spec tests/JavaScript/ProduccionCantidadTest.mjs` | **12 aprobadas, 0 fallidas/omitidas**, salida 0. |
| `node --test-reporter=spec tests/JavaScript/SidebarTest.mjs` | **7 aprobadas, 0 fallidas/omitidas**, salida 0. |
| `npm run build` | Correcto, salida 0; incluye `errores.css`. Aviso preexistente de fontaine opcional, sin instalar paquetes. |
| `vendor/bin/pint --dirty --test` | Correcto, salida 0. |
| `vendor/bin/pint --test tests/Feature/AutorizacionRolesTest.php tests/Feature/AutorizacionSeedersTest.php` | Correcto, salida 0; cubre explícitamente archivos nuevos sin agregar al índice. |
| `git diff --check` | Correcto, salida 0; revisión adicional de nuevos archivos con diff sin índice. |
| `php artisan route:list -vv --except-vendor` | Diez rutas de aplicación, sin nuevas URLs y con `can` en las seis operativas. |

Los intentos dentro del sandbox no pudieron disponer del socket MariaDB temporal y terminaron antes de ejecutar tests; no son pruebas fallidas del producto. Se repitieron mediante escalamiento. El runner arranca MariaDB **sin red**, crea solo `sprint4_models_test` y `sprint4_migration_test` bajo `/tmp/sprint4-*`, y comprueba datadir/base antes de migrar. Se detuvo la instancia final `/tmp/sprint4-4bf1cb578496`; los artefactos ficticios quedan para inspección.

El aislamiento permite migraciones existentes, seeders, transacciones y pruebas de concurrencia sobre fixtures, sin leer o escribir datos operativos. HTTP usa sesiones array y `withoutVite`; los assets se compilan por separado. Los tests ordinarios de Laravel omiten CSRF por defecto; la regresión existente de logout activa su comprobación real. No se hizo bypass de Gates ni conversión de roles desconocidos para aprobar pruebas.

Cobertura nueva: helpers y ambos Gates, acceso directo a páginas, rechazo HTML/JSON en las seis rutas, invitados, ausencia de efectos de registro, logout tras rechazo, formulario 403, Operador con ID 1 y Administrador con ID distinto, registro real de las tres familias por ambos roles, selección explícita de Noche con cuenta de nombre mañana, foto de Torta, snapshot/hora de Pan, autor individual tomado de sesión y rechazo tras cambio de rol persistido. La relación ausente se simula en memoria: el esquema real mantiene FK obligatoria y no se deshabilitó para crear una cuenta huérfana.

### Regresiones

La suite final incluye login/logout, rehash de `password_hash`, destino intended, navegación, Dashboard, Producción, Historial unificado, modelos/relaciones, migraciones, concurrencia y Proxy HTTPS.

Las tres diferencias de conteo no provenían de consultas por lote nuevas: al reutilizar un Usuario en dos solicitudes de test, el primer Gate cargaba `rol` y el segundo usaba la relación ya cargada. Se reinicia solo esa relación antes de cada medición. El Gate sigue resolviendo el rol y ejecutándose; se conservan la igualdad entre tamaños, el límite de Dashboard y la única consulta de roles de pivots. No se eliminaron tests ni se relajaron los límites.

Administrador y Operador guardan Pan/Torta/Bocadito con las mismas Actions/reglas. Maestro/Ayudantes, categorías, turno explícito, autor individual, snapshots, latas/coches, fotos, histórico y productos inactivos conservan comportamiento. Dashboard funcional no fue alterado.

### Riesgos pendientes

- Nombres de roles sin UNIQUE en la base: no se saneó catálogo existente ni se certifica seguridad de seeding simultáneo. `sole()` evita selección ambigua al crear la cuenta demo; una futura normalización necesita revisar referencias.
- CRUD y rutas administrativas inexistentes: `administrar-sistema` deberá aplicarse a cada método/endpoint real cuando se incorporen. No se declara terminado ningún módulo por tener su Gate.
- Estados `usuarios_sistema.activo` y `empleados.activo` pendientes de fase posterior; sin migraciones nuevas ahora. Definir efecto sobre login, sesiones abiertas y participantes.
- Decisiones del último Administrador, degradación propia, desactivación y recuperación pendientes de confirmación; no se implementaron restricciones de negocio adicionales.
- Rate limiting y presentación de errores del login, política/aprovisionamiento de contraseñas y recuperación pendientes; la credencial demo del seeder no es una solución de aprovisionamiento productivo.
- Driver de sesiones database y configuración efectiva del despliegue no certificados por sesiones array. Datos reales iniciales atribuidos al usuario, sin reauditar la base.
- Fotos de Torta permanecen en disco public: proteger la página no protege archivos estáticos. Su confidencialidad y recuperación ante caídas siguen pendientes.
- `LATAS_POR_COCHE` conserva su valor actual. Configuración editable y snapshot/versionado de equivalencias históricas se diseñarán después.
- Aceptación visual de 403 en navegador/dispositivos reales pendiente; HTML/DOM y build comprobados, sin declarar revisión visual manual.

### Próxima fase recomendada

Administración de catálogos por tareas acotadas: comenzar con Productos, que ya tiene `activo` y factor panes por lata, aplicar `administrar-sistema` a todas sus rutas y probar acceso/denegación sin pérdida de historial. Definir después Empleados/Usuarios y sus estados mediante migraciones aditivas, con las decisiones de administración confirmadas antes de incorporarlas.

No activar configuración de latas/coches hasta resolver el contrato histórico. No crear operadores reales hasta la fase de usuarios. Policies solo si aparecen reglas sobre recursos que las justifiquen.

### Documentación y límites de cierre

`mejora_asignado.md` actualiza la misma entrada de roles y conserva su diagnóstico histórico. El informe de Fase 1 no se reescribió para borrar su estado anterior. Las notas originales afectadas del Vault fueron inspeccionadas y leídas íntegramente; la actualización se integra en sus archivos originales mediante la autorización de escritura exigida por el entorno.

No corresponde modificar notas de estructura de base de datos ni crear notas de bugs duplicadas: no hubo nuevas migraciones y la carencia de autorización estaba planificada en Fase 1. Los riesgos de autenticación y negocio continúan señalados como pendientes.

Ubicación exacta: `/home/lukagfv/Documentos/PROYECTOS-LARAVEL/panaderia-rs/SPRINT_6_FASE_2_AUTORIZACION.md`.
