# Sprint 6 — Fase 1

## Roles y permisos

Fecha: 2026-10-12. Checkout auditado: `panaderia-rs`, commit `78c802e`.

**Estado de esta fase:** auditoría y propuesta documentadas. **Estado del sistema de autorización por roles: PENDIENTE.** Este informe no constituye implementación ni aprobación de las decisiones de negocio pendientes.

Alcance: lectura de código, inventario de rutas y sondeos sin persistencia. Único archivo creado en el repositorio: este informe. No se modificaron aplicación, autenticación, rutas, pruebas, seeders, migraciones ni datos; no se instalaron paquetes.

### Estado actual

El proyecto declara `laravel/framework: ^13.17` y PHP `^8.3` en `composer.json`. Los comandos locales identificaron **Laravel 13.25.0 y PHP CLI 8.5.10**.

La autenticación utiliza el guard de sesión `web`, el provider Eloquent `users` y `App\Models\Usuario`. `config/auth.php` permite sustituir el modelo mediante `AUTH_MODEL`; el arranque aislado confirmó `Usuario`. Esto no certifica la configuración de otro despliegue.

El flujo existente es:

1. `GET /login`, bajo `guest`, muestra `auth.login`.
2. `POST /login`, también bajo `guest`, valida únicamente que existan `username` y `password`.
3. `Auth::attempt()` busca la cuenta por `username` y verifica la contraseña contra el hash que devuelve `Usuario::getAuthPassword()`.
4. Si la autenticación resulta válida, regenera la sesión y redirige al destino `intended` o a `dashboard`. Si falla, devuelve un mensaje genérico y conserva únicamente el nombre de usuario en el input anterior.
5. `POST /logout`, bajo `auth`, ejecuta `Auth::logout()`, invalida la sesión, regenera la protección CSRF y redirige al login.

El login no comprueba rol ni estado activo. No existe `activo` en usuarios. Tampoco se observó `throttle` en las rutas de login ni un limitador propio en el controlador; su evaluación es una mejora de seguridad separada de la implementación de roles.

`PreventPageCaching` añade cabeceras para evitar almacenamiento HTTP en páginas con `auth` o `guest`. `bootstrap/app.php` configura proxies de loopback, redirección de autenticados desde `guest` al Dashboard y respuestas JSON según la petición. Ninguna de estas medidas concede o restringe permisos por rol.

El grupo web incluye manejo de sesiones y protección contra falsificación de solicitudes del framework. Los formularios de login y logout usan `@csrf`. CSRF y autenticación cumplen funciones distintas de la autorización por rol.

### Archivos revisados

Las rutas siguientes son relativas a la raíz del proyecto, salvo donde se indica `vendor`:

| Área | Archivos relevantes revisados |
|---|---|
| Instrucciones y versión | `AGENTS.md`, `composer.json`, `phpunit.xml` |
| Identidad y relaciones | `app/Models/Usuario.php`, `app/Models/Rol.php`, `app/Models/Empleado.php`, `app/Models/Produccion.php`, `app/Models/Producto.php`, `app/Models/DetallePan.php` |
| Autenticación | `app/Http/Controllers/LoginController.php`, `config/auth.php`, `config/session.php`, `resources/views/auth/login.blade.php` |
| Arranque y middleware | `bootstrap/app.php`, `bootstrap/providers.php`, `app/Providers/AppServiceProvider.php`, `app/Http/Middleware/PreventPageCaching.php` — único middleware propio encontrado |
| Rutas y controladores | `routes/web.php`, `app/Http/Controllers/Controller.php`, `DashboardController.php`, `ProduccionController.php`, `HistorialController.php` en ese mismo directorio |
| Requests | `app/Http/Requests/ConsultarProduccionRequest.php`, `ConsultarHistorialProduccionRequest.php`, `RegistrarProduccionPanRequest.php`, `RegistrarProduccionPorCantidadRequest.php`, `RegistrarProduccionTortaRequest.php`, `RegistrarProduccionBocaditoRequest.php` |
| Producción e historial | `app/Actions/Produccion/RegistrarProduccionPan.php`, `RegistrarProduccionPorCantidad.php`, `ResolverCabeceraProduccion.php`, `ValidarParticipantesProduccion.php`; `app/Queries/ConsultarHistorialProduccion.php` |
| Presentación | `resources/views/components/sidebar.blade.php`, `resources/views/dashboard/index.blade.php`, `resources/views/history/index.blade.php`, `resources/js/dashboard.js`; búsquedas de referencias en `resources/js/produccion.js` |
| Seeders | `database/seeders/DatabaseSeeder.php`, `RolSeeder.php`, `UsuarioSeeder.php`, `EmpleadoSeeder.php`, `CargoSeeder.php`, `RolProduccionSeeder.php` |
| Factory heredada | `database/factories/UserFactory.php` |
| Pruebas existentes y aislamiento | `tests/Pest.php`, `tests/TestCase.php`, `tests/Feature/NavigationTest.php`, `tests/Feature/ModelsRelationshipsTest.php`, `tests/Feature/ProxyHttpsTest.php`, `tests/Support/IsolatedMariaDb.php`, `tests/run-mariadb.php` |
| Contratos del framework instalado | `vendor/laravel/framework/src/Illuminate/Auth/EloquentUserProvider.php`, `Illuminate/Auth/Authenticatable.php`, `Illuminate/Foundation/Configuration/Middleware.php`, `Illuminate/Database/Schema/Blueprint.php` bajo el mismo `src/` |

Se inventarió `database/migrations/` y se buscaron referencias a las tablas auditadas. Migraciones principales leídas:

- `database/migrations/2026_09_14_202616_create_roles_table.php`.
- `database/migrations/2026_09_14_202621_create_empleados_table.php`.
- `database/migrations/2026_09_14_202622_create_usuarios_sistema_table.php`.
- `database/migrations/2026_09_14_205442_create_sessions_table.php`.
- `database/migrations/2026_09_14_202623_create_productos_table.php`.
- `database/migrations/2026_09_14_202624_create_produccion_table.php`.
- `database/migrations/2026_09_14_202631_create_pedidos_table.php`.
- `database/migrations/2026_10_02_180000_add_categoria_and_turno_to_produccion.php`.
- `database/migrations/2026_10_02_200000_add_panes_por_lata_to_productos_and_detalle_pan.php`.
- `database/migrations/2026_10_11_120000_add_registrador_to_production_details.php`.

Se buscaron Gates, Policies, llamadas de autorización, middleware y referencias de roles en `app`, `routes`, `bootstrap`, `config`, `database`, `tests` y vistas. La búsqueda distingue las clases propias de las capacidades incorporadas en Laravel.

### Modelo Usuario

`Usuario` extiende `Illuminate\Foundation\Auth\User`, por lo que cumple el contrato de usuario autenticable. Usa explícitamente `usuarios_sistema`; no hay un modelo `app/Models/User.php` en el inventario actual.

- Asignación masiva permitida: `username`, `password_hash`, `empleado_id`, `rol_id`.
- `password_hash` está oculto al serializar. Esto evita su inclusión ordinaria en JSON, pero no reemplaza el hash ni la validación de entradas.
- `getAuthPassword()` devuelve el atributo `password_hash`.
- **`getAuthPasswordName(): string` ya está implementado y devuelve `password_hash`.** El sondeo aislado comprobó ese retorno. No debe proponerse como un método ausente.
- No hay cast `hashed` ni mutador de contraseña en este modelo. Quien cree o cambie una contraseña deberá aplicar hashing explícito; `UsuarioSeeder` usa `Hash::make()`.
- Eloquent administra los timestamps por defecto; la migración incluye `created_at` y `updated_at`.
- No hay helpers de rol, estado activo, `SoftDeletes` ni un sistema de permisos individuales.

El provider instalado verifica el hash y, si necesita rehash, escribe el atributo indicado por `getAuthPasswordName()`. Existe una prueba de rehash real en `tests/Feature/ModelsRelationshipsTest.php`. **No se ejecutó esa prueba en esta fase**, porque su preparación migra y crea fixtures. El contrato del nombre está comprobado en memoria; la persistencia del rehash y el login contra registros SQL no se certifican con los sondeos de esta auditoría.

### Modelo Rol

`Rol` es un modelo Eloquent sobre `roles`, con `$timestamps = false`, `nombre_roles` como único campo asignable y una relación `usuarios()` de tipo `hasMany(Usuario::class, 'rol_id')`.

`RolSeeder` contiene actualmente **tres** nombres: `Administrador`, `Encargado` y `Operador`. `DatabaseSeeder` llama a ese seeder antes de `UsuarioSeeder`. Utiliza `insert`, por lo que repetirlo puede duplicar nombres: la tabla no tiene una restricción única sobre `nombre_roles`.

`UsuarioSeeder` prepara una cuenta `admin` con `rol_id = 1` y `empleado_id = 1`. El código depende del orden y de una base inicialmente compatible; no demuestra que esos IDs correspondan siempre al Administrador y al empleado esperado. No se encontraron cuentas `operador_manana` ni `operador_noche` en ese seeder.

**Valores declarados por el código: comprobados. Valores actualmente almacenados: no consultados.** No se ejecutaron seeders ni se leyó la base operativa. Antes de cambiar el catálogo habrá que inspeccionar, en lectura, roles duplicados y usuarios asociados a `Encargado`. No eliminar ni convertir esos registros automáticamente.

`roles_produccion` tiene otra responsabilidad: `Maestro` y `Ayudante` describen participación en una producción. `cargos` describe funciones laborales. Ninguno sustituye a `usuarios_sistema.rol_id` para autorizar el acceso al sistema.

### Relación Usuario - Rol

`Usuario::rol()` es `belongsTo(Rol::class, 'rol_id')`. Cada usuario tiene un rol y cada rol puede pertenecer a muchos usuarios. La FK se guarda directamente en `usuarios_sistema`; no existe ni hace falta una tabla intermedia para la matriz acordada.

`Usuario::empleado()` es `belongsTo(Empleado::class, 'empleado_id')`, y `Empleado::usuario()` es `hasOne(Usuario::class, 'empleado_id')`. La restricción única sobre esa FK garantiza como máximo una cuenta por empleado. Un empleado puede existir sin cuenta, pero una cuenta necesita empleado según el esquema declarado.

#### Modelo de datos declarado

| Tabla | Campos y restricciones |
|---|---|
| `roles` | `id`: PK autoincremental; `nombre_roles`: string de 100, obligatorio, sin `unique`. Sin timestamps, estado ni borrado lógico. |
| `usuarios_sistema` | `id`: PK; `username`: string de 50, obligatorio y único; `password_hash`: string de 255, obligatorio; `empleado_id`: FK obligatoria y única hacia `empleados.id`; `rol_id`: FK obligatoria hacia `roles.id`, no única; `created_at` y `updated_at`. Sin `activo`, `remember_token` ni borrado lógico. |
| `empleados` | `id`: PK; `nombre_empleados`: string de 100 obligatorio; `numero_empleados`: string de 20 nullable y sin `unique`; `cargo_id`: FK obligatoria hacia `cargos.id`; `created_at` y `updated_at`. Sin `activo` ni borrado lógico. |
| `sessions` | `id`: string PK; `user_id`: FK nullable hacia `usuarios_sistema.id`, con `nullOnDelete`; `ip_address` y `user_agent`: nullable; `payload`: longText; `last_activity`: integer indexado. Sin timestamps Eloquent. |

Los timestamps declarados mediante `$table->timestamps()` son nullable en el Blueprint instalado. Eloquent los rellena al guardar los modelos; las inserciones directas de `EmpleadoSeeder` no aportan esos valores.

Las FKs de usuario a empleado y rol no declaran cascada ni `nullOnDelete`: no autorizan borrar dependencias y dejar una cuenta huérfana. Su aplicación efectiva depende del esquema desplegado y de que la base haga cumplir las FKs, algo no verificado en SQL durante esta fase. La FK de sesiones sí tiene un comportamiento explícito diferente.

Las relaciones pueden devolver `null` con fixtures incompletos o datos inconsistentes, aunque las FKs de usuario sean obligatorias. Una comprobación de permisos debe denegar ese caso de forma controlada.

**Conclusión de datos:** la estructura `Usuario → Rol` permite Administrador y Operador sin ACL compleja. Faltan comprobaciones de autorización y, para la administración de estados, columnas en usuarios y empleados; esas son necesidades diferentes.

### Estado de autorización actual

| Mecanismo | Evidencia en este checkout |
|---|---|
| Autenticación de rutas internas | Grupo `auth` en `routes/web.php`; confirmado por inventario y sondeos. |
| Middleware propio por rol | No existe. `PreventPageCaching` no comprueba roles. |
| Gates de la aplicación | No hay definiciones en los providers revisados; el arranque aislado registró cero habilidades. |
| Policies de la aplicación | No se encontró `app/Policies`; la resolución en runtime para Usuario, Rol, Empleado, Producto y Produccion devolvió `null`. |
| Permisos individuales | No hay modelo, tablas de permisos, pivotes de permisos ni comprobaciones individuales encontrados. |
| Form Requests | Existen para producción y consultas; todos los `authorize()` revisados devuelven `true`, directamente o por herencia. Validan datos, sin filtrar el rol. |
| Autorización en controllers/Actions | No se encontraron verificaciones de rol, `Gate::authorize()` o autorización por Policy en los flujos revisados. |
| Sidebar | Tres enlaces comunes visibles sin comprobación de rol. |

**Actualmente un usuario autenticado puede alcanzar todas las rutas internas existentes, cualquiera sea su rol.** Registrar una producción sigue sujeto a validación de datos y reglas operativas; autenticarse no garantiza que cualquier payload sea válido. No hay separación de Administrador y Operador en backend.

Esto incluye consultas de todo el historial disponible: el query no restringe por creador, usuario ni un supuesto turno del usuario. No se debe añadir una restricción por propiedad o turno sin una regla funcional que la justifique.

No existen rutas administrativas de Productos, Empleados, Usuarios o Configuración. Hoy una URL inexistente como `/productos` devuelve el comportamiento de ruta no encontrada; ese hecho no equivale a un control de acceso administrativo implementado.

La raíz redirige a login y el endpoint de salud `/up` está configurado por Laravel. Son entradas públicas de infraestructura/presentación, no módulos administrativos internos.

### Matriz acordada

Esta matriz es el requisito funcional recibido, **PENDIENTE de implementación**:

| Acción / módulo | Administrador | Operador |
|---|---|---|
| Ver Dashboard | Sí | Sí |
| Ver gráficos del Dashboard | Sí | Sí |
| Registrar producción | Sí | Sí |
| Ver historial | Sí | Sí |
| Ver detalle de producción | Sí | Sí |
| Crear productos | Sí | No |
| Editar productos | Sí | No |
| Activar/desactivar productos | Sí | No |
| Configurar panes por lata | Sí | No |
| Configurar latas/coches | Sí | No |
| Administrar unidades de medida | Sí | No |
| Crear empleados | Sí | No |
| Editar empleados | Sí | No |
| Activar/desactivar empleados | Sí | No |
| Crear usuarios | Sí | No |
| Editar usuarios | Sí | No |
| Activar/desactivar usuarios | Sí | No |
| Asignar roles | Sí | No |
| Configuración administrativa | Sí | No |

Los únicos roles de destino son **Administrador y Operador**. `admin`, `operador_manana` y `operador_noche` son nombres de cuentas; las dos últimas deben compartir Operador. El nombre de cuenta, `cargo_id` y el rol de participación no conceden acceso.

`produccion.turno_id` continúa siendo el turno de la producción. La migración lo admite nullable para cabeceras históricas; los Requests de registro nuevo lo exigen y el resolver lo guarda expresamente. No inferirlo del nombre de usuario ni de su rol.

El Dashboard actual ofrece métricas y resumen por turno, sin gráficos implementados en la vista y el JavaScript revisados. Cuando existan gráficos o endpoints de datos, deberán compartir la autorización del Dashboard. El detalle actual se despliega dentro de `/history` mediante `<details>`; no existe una ruta `show` independiente.

Productos y empleados se administrarán principalmente mediante **activo/inactivo**, preservando referencias e historial. No se propone eliminación física como comportamiento principal.

### Solución recomendada

**Recomendación técnica, PENDIENTE:** conservar Usuario, Rol, guard, provider y login actuales; añadir **Gates con el middleware nativo `can`**. Laravel distingue Gates para capacidades generales y Policies para acciones sobre recursos, y dispone del middleware `can` y directivas Blade de autorización. [Documentación oficial de autorización de Laravel 13](https://laravel.com/framework/docs/13.x/authorization).

Para esta matriz bastan dos capacidades propuestas:

| Capacidad propuesta | Regla | Aplicación |
|---|---|---|
| `acceder-operacion` | Rol identificado exactamente como Administrador u Operador | Dashboard, Producción e Historial, incluidos sus POST y futuros datos de gráficos/detalles |
| `administrar-sistema` | Rol identificado exactamente como Administrador | Todas las rutas de Productos, Empleados, Usuarios y Configuración |

Los nombres de las capacidades son una propuesta interna, no permisos almacenados en nuevas tablas. Registrar las reglas en `AppServiceProvider::boot()`, con `App\Models\Usuario` como tipo del usuario; centralizar la identificación de roles en helpers del modelo y nombres canónicos constantes de `Rol`.

Comparar el nombre canónico exacto, sin asumir IDs, sin búsquedas parciales, sin usar `username` y sin convertir cualquier valor desconocido a Operador. Para dos roles fijos, mantener esos nombres como catálogo controlado evita necesitar un `slug` nuevo. No se plantea un CRUD libre de roles. Duplicados y posibles cambios de nombre deben tratarse antes de endurecer el catálogo.

El Gate compartido permite denegar por defecto roles desconocidos o ausentes. Esta es una propuesta de seguridad; el destino de las cuentas existentes con `Encargado` requiere decisión antes de habilitarla. El logout debe seguir bajo `auth` únicamente para que incluso una cuenta con rol inválido pueda cerrar sesión.

#### Alternativas evaluadas

| Alternativa | Evaluación para el alcance real |
|---|---|
| Middleware propio por rol | Sería suficiente para agrupar rutas, pero habría que integrar además la regla de presentación. Los Gates permiten reutilizar una misma decisión en `can` y `@can`, sin crear middleware propio. |
| Solo Gates sin aplicarlos a rutas/acciones | Definirlos no protege nada por sí mismo. Deben invocarse mediante `can` en backend. |
| Solo Policies | Útiles para reglas por recurso, pero innecesarias para dos grupos con la misma regla en todos los registros. Configuración tampoco corresponde necesariamente a un modelo. |
| Middleware + Policies | Adecuado cuando aparezcan CRUD y reglas por registro. Se podrá usar el middleware nativo `can` para invocar las Policies, compartiendo los helpers de rol. No crear hoy Policies vacías ni duplicar toda la matriz. |
| Paquete externo o RBAC avanzado | Sin una necesidad demostrada: no hay múltiples roles por usuario ni excepciones individuales en la matriz. No se recomienda Spatie ni tablas adicionales de permisos. |

No usar `Gate::before()` para conceder indiscriminadamente todas las acciones al Administrador: podría saltarse futuras invariantes de negocio, como una protección del último administrador si se confirma.

### Flujo de autorización recomendado

```text
Petición de Usuario
  → middleware web (sesión y protección de solicitudes)
  → auth (identidad autenticada)
  → can:acceder-operacion
      → helper de Usuario → relación Rol → nombre canónico
  → can:administrar-sistema (solo grupo administrativo)
  → Form Request (validación de datos, cuando corresponda)
  → controller
  → Action de negocio / persistencia
```

Ambas comprobaciones propuestas ocurren en backend. En la vista, `@can('administrar-sistema')` consulta esa misma regla para mostrar navegación administrativa. Retirar el enlace no impide una petición directa.

Si en el futuro una Policy gobierna un recurso, autorizar también la acción concreta antes de producir efectos. La autorización no debe trasladarse a cálculos Eloquent, filtros del navegador o al resultado de un formulario.

#### Comportamiento ante acceso no autorizado

| Situación | Respuesta propuesta | Motivo |
|---|---|---|
| Invitado en ruta interna HTML | Redirección 302 al login, conservando `intended` | Mantener el flujo de autenticación existente. |
| Invitado que solicita JSON | HTTP 401 | No tiene identidad autenticada. |
| Operador en sección administrativa | HTTP 403 | Ya está autenticado, pero carece de autorización. Evita aparentar que un nuevo login resolverá el acceso. |
| Usuario sin rol válido | HTTP 403 en módulos protegidos, con logout accesible | Denegar de forma controlada; no conceder privilegios ni producir un error 500. |

Para HTML, proponer una página 403 con «No tienes permisos para acceder a esta sección» y un enlace a una página autorizada o al logout. Para JSON, un error equivalente conservando 403. Una redirección silenciosa al Dashboard no sería la respuesta principal: oculta el rechazo y dificulta verificarlo.

Laravel documenta la redirección de invitados desde `auth` a la ruta de login. [Documentación oficial de autenticación de Laravel 13](https://laravel.com/framework/docs/13.x/authentication). El sondeo de esta fase confirmó 302 para HTML y 401 para JSON en las rutas existentes; el 403 por rol sigue siendo diseño, no comportamiento implementado.

### Rutas compartidas

| Método y ruta existentes | Nombre | Protección actual | Protección futura propuesta |
|---|---|---|---|
| GET/HEAD `/dashboard` | `dashboard` | `auth` | `auth` + `can:acceder-operacion` |
| GET/HEAD `/produccion` | `produccion.index` | `auth` | Igual |
| POST `/produccion` | `produccion.store` | `auth` | Igual |
| POST `/produccion/torta` | `produccion.torta.store` | `auth` | Igual |
| POST `/produccion/bocadito` | `produccion.bocadito.store` | `auth` | Igual |
| GET/HEAD `/history` | `history.index` | `auth` | Igual |
| POST `/logout` | `logout` | `auth` | Mantener `auth` |

Conservar estas URLs y nombres; no renombrar `/history` por conveniencia. Cualquier futura ruta de detalle o endpoint de gráficos debe incorporarse al grupo compartido. Las validaciones actuales de categoría, turno, producto y participantes seguirán siendo necesarias.

### Rutas exclusivas de Administrador

Productos, Empleados, Usuarios y Configuración necesitan un grupo administrativo con `auth`, acceso operativo válido y `can:administrar-sistema`, cubriendo **cada GET/HEAD, POST, PUT/PATCH y operación de estado**.

Las URLs y nombres administrativos aún no están implementados. `/productos`, `/empleados`, `/usuarios` y `/configuracion` son ejemplos de diseño, no rutas confirmadas. Panes por lata, latas/coches y unidades de medida también deben permanecer dentro del límite administrativo, aunque terminen en subrutas o controladores separados.

El Operador puede leer los productos activos y empleados que el formulario de Producción necesita. Eso no le permite administrar dichos catálogos. No aplicar un bloqueo global a toda consulta de `Producto` o `Empleado` que rompa el registro operativo.

#### Sidebar

Actualmente `resources/views/components/sidebar.blade.php` muestra Dashboard, Producción e Historial a todos los autenticados, además del perfil y el formulario de logout. El perfil usa el nombre del empleado y, si falta, el `username`.

Cuando existan las rutas administrativas, incorporar un bloque `@can('administrar-sistema')` con Productos, Empleados, Usuarios y Configuración. Mantener las rutas compartidas para ambos roles, la indicación de sección activa, los atributos accesibles, el comportamiento móvil y el logout POST con `@csrf`.

Resultado esperado: Administrador con siete enlaces de módulos; Operador con tres. No añadir enlaces a rutas inexistentes en la Fase 2 de autorización base. Verificar también el backend mediante URLs y solicitudes construidas manualmente.

### Cambios futuros necesarios

Todos los cambios siguientes están **PENDIENTES** y no se realizaron en esta fase:

| Archivo / área | Cambio futuro y justificación |
|---|---|
| `app/Models/Rol.php` | Nombres canónicos para los dos roles de destino; sin dependencia de IDs. |
| `app/Models/Usuario.php` | Helpers únicos para identificar Administrador y acceso operativo; tolerar relación ausente denegando acceso. Conservar los dos métodos de contraseña ya existentes. |
| `app/Providers/AppServiceProvider.php` | Definir los dos Gates propuestos. No hace falta inventar otro provider para dos reglas. |
| `routes/web.php` | Aplicar `can:acceder-operacion` a rutas compartidas y proteger el grupo administrativo completo con `can:administrar-sistema` cuando exista. Conservar login/guest y logout/auth. |
| `resources/views/components/sidebar.blade.php` | Usar `@can` y añadir enlaces administrativos al existir sus rutas. |
| `resources/views/errors/403.blade.php` — nuevo, propuesto | Mensaje accesible de acceso denegado, preservando el código 403 y evitando enlaces a páginas que el usuario tampoco puede usar. |
| `database/seeders/RolSeeder.php` | Preparar únicamente Administrador/Operador para instalaciones nuevas; hacer el seeder idempotente. Resolver antes los datos heredados de Encargado y posibles duplicados. Cambiar el seeder no transforma por sí solo una base existente. |
| `database/seeders/UsuarioSeeder.php` | Resolver rol y empleado por una identidad comprobada, sin `rol_id = 1` ni `empleado_id = 1`. No reutilizar credenciales de demostración para operación real. Creación de cuentas fuera de esta fase. |
| `database/seeders/EmpleadoSeeder.php` | Al revisar el encadenamiento de seeders, resolver `cargo_id` sin suponer un ID fijo. No utilizar cargo para autorizar. |
| `tests/Feature/NavigationTest.php` | Adaptar fixtures: hoy un Usuario en memoria sin rol atraviesa las páginas. Bajo el diseño nuevo necesitará un rol válido; ampliar verificaciones de menú y conservar logout/CSRF. |
| `tests/Support/IsolatedMariaDb.php` | Las fixtures actuales usan `Rol ficticio`; las pruebas HTTP deberán asignar los roles acordados para no confundir un rechazo esperado del Gate con regresiones funcionales. |
| `tests/Feature/ModelsRelationshipsTest.php` | Conservar prueba de relaciones y rehash; adaptar el rol de sus fixtures HTTP. |
| Futuras pruebas de autorización en `tests/Feature/` | Verificar matriz, métodos, URLs directas, roles desconocidos y ausencia de efectos. Nombres de archivos por definir en implementación. |
| Pruebas HTTP de Dashboard/Producción/Historial | Revisar las que usan las fixtures compartidas, para comprobar ambas identidades sin debilitar los Gates durante el test. |
| Futuras migraciones de `usuarios_sistema` y `empleados` | Añadir `activo` al implementar estados; acordar inicialización de registros existentes y efecto sobre sesiones/selección de participantes. |
| `app/Models/Usuario.php` y `Empleado.php`, en administración de estados | Añadir campo/cast de `activo` cuando exista la columna y validar su uso en las operaciones administrativas. |
| `app/Http/Controllers/LoginController.php`, solo al implementar usuarios inactivos | Aplicar el criterio de cuenta habilitada y definir rechazo, manteniendo guard/provider/sesión. No se necesita modificarlo para los Gates de rol por sí solos. |
| Middleware de estado, solo si se aprueba esa etapa | Revalidar cuenta habilitada en solicitudes de sesiones ya abiertas; rechazar solo en login deja sesiones antiguas habilitadas. Mecanismo y respuesta por confirmar. |
| Futuros controllers/Requests/Actions administrativos | CRUD y validación de Productos, Empleados, Usuarios y Configuración en tareas posteriores; persistir únicamente datos validados, sin `request()->all()`. |
| Futuras Policies en `app/Policies/`, si aparecen reglas por recurso | Autorizar acciones sobre registros y aplicar invariantes confirmadas; no crearlas anticipadamente como duplicación de los Gates. |

`bootstrap/app.php` no necesita un alias nuevo para `can`: el framework instalado ya lo registra. Solo cambiaría si una etapa posterior justifica un middleware propio de estado. `config/auth.php`, la arquitectura del guard y los métodos de contraseña no requieren refactor para esta matriz.

La factory heredada referencia `App\Models\User` y campos de la plantilla (`name`, `email`, `password`), incompatibles con Usuario. Para nuevas pruebas usar fixtures coherentes o adaptar posteriormente una factory de Usuario; no afirmar que `Usuario::factory()` ya funciona, pues tampoco usa `HasFactory` actualmente.

#### ¿Se necesitan nuevas migraciones?

| Objetivo | ¿Nueva migración? | Alcance |
|---|---|---|
| Distinguir Administrador/Operador y aplicar Gates | **No** | Reutilizar `roles` y `usuarios_sistema.rol_id`. |
| Activar/desactivar productos | **No por el campo de estado** | `productos.activo` ya existe, con default true y cast boolean. Falta el flujo administrativo. |
| Activar/desactivar usuarios y empleados | **Sí** | Faltan columnas `activo` en ambas tablas. Fuera de la autorización base y de esta fase. |
| Garantizar nombres de rol únicos | Recomendable posteriormente | Requiere auditar duplicados y resolver referencias antes de añadir `unique(nombre_roles)`. No es requisito estructural para empezar con Gates. |
| Panes por lata | **No por el campo actual** | `productos.panes_por_lata` existe nullable, y `detalle_pan.panes_por_lata_usado` conserva el factor histórico. |
| Hacer configurable latas por coche | **Por determinar** | Hoy es `DetallePan::LATAS_POR_COCHE = 18`, usado en registro y representación histórica. Definir alcance, almacenamiento y conservación del factor histórico antes de diseñar migraciones. |

No alterar las migraciones originales ya existentes para simular que las nuevas columnas siempre estuvieron presentes. Una futura implementación debe usar migraciones incrementales y una estrategia explícita para datos anteriores.

### Pruebas necesarias

**Estado: PENDIENTE.** Especificación para la Fase 2 y, donde se indica, para cuando se implementen los módulos. No se añadieron estos tests ahora.

| Identidad | Dashboard | Producción GET y registros válidos | Historial y detalle | Administración |
|---|---|---|---|---|
| Administrador | 200 | Acceso permitido; POST válido conserva flujo de éxito | 200, filtros y detalles accesibles | GET permitido y mutaciones válidas autorizadas |
| Operador | 200 | Igual que Administrador | Igual que Administrador | 403 en Productos, Empleados, Usuarios y Configuración |
| Invitado | 302 login en HTML / 401 JSON | Rechazado antes de registrar | Rechazado | Rechazado cuando las rutas existan |

Pruebas concretas de autorización base:

1. Administrador y Operador entran a `/dashboard`, `/produccion` y `/history` con datos de prueba coherentes.
2. Ambos pueden registrar Pan, Torta y Bocadito con payload válido; ni el usuario ni su nombre determinan `turno_id`.
3. Ambos ven detalles del historial, incluso los registrados por otra cuenta: no introducir una regla de propiedad que la matriz no pide.
4. Ambos acceden a futuros gráficos/endpoints con la misma protección; no considerar una prueba de gráfico completada mientras ese gráfico no exista.
5. Invitado es rechazado en las siete rutas internas actuales, incluyendo los POST de las tres familias y logout. Cubrir también las futuras rutas administrativas.
6. Roles ausentes/desconocidos y Encargado reciben el rechazo controlado propuesto si no se ha autorizado otra transición. Verificar que logout permanece disponible.
7. Cambiar el ID del rol en fixtures no altera el resultado si su nombre canónico es el correcto. Crear un Operador con ID 1 no lo convierte en Administrador.
8. La misma regla de Gate gobierna backend y navegación; Administrador muestra enlaces administrativos reales y Operador únicamente los tres compartidos.
9. Conservar regresiones de login/logout, redirección intended, rehash y CSRF. Tras un login de Operador con destino administrativo previo, la visita final debe acabar en 403, no conceder acceso por `intended`.

Pruebas de manipulación directa, al existir los módulos administrativos:

- Operador escribe manualmente cada URL de listado, alta y edición; recibe 403 sin depender del sidebar.
- Operador construye POST/PUT/PATCH para crear, editar, cambiar estado y asignar roles; recibe 403 **con payload válido y protección CSRF válida**, evitando confundir un 419 o un error de validación con autorización.
- Cambiar IDs de producto, empleado o usuario en la URL no concede acceso. Comprobar registros existentes para probar 403; cubrir por separado IDs inexistentes y su respuesta 404 según el binding.
- Ningún rechazo crea registros ni modifica campos, roles, estados o configuración. Comprobar diferencias de persistencia en base aislada.
- Añadir `rol_id`, `activo`, `empleado_id` o un registrador ajeno a un payload operativo no cambia la identidad autenticada ni eleva permisos. La producción debe seguir atribuyéndose al usuario de sesión.
- Verificar todos los endpoints secundarios: unidades de medida, panes por lata, parámetros latas/coches y peticiones JSON. No basta probar el GET de la portada.
- No exponer acciones de eliminación física como flujo principal; si una ruta DELETE se considera posteriormente, necesitará autorización y decisión expresa de negocio.

Cuando se implementen estados y se confirmen reglas de administración: probar cuenta inactiva en login y sesión previa, preservación del historial con productos/empleados inactivos, cambios propios de rol/estado, último Administrador y concurrencia de dos cambios que podrían dejar cero administradores. El resultado exacto de estas últimas reglas depende de las decisiones pendientes.

La Fase 2 puede verificar los Gates mediante rutas **exclusivamente de prueba** mientras no existan los CRUD. Eso comprueba el mecanismo, no el acceso a módulos reales. La aceptación de cada módulo quedará pendiente hasta probar sus rutas efectivas; no crear rutas ficticias en producción para obtener un 403 aparente.

#### Verificaciones realizadas en esta fase y límites

| Verificación ejecutada | Resultado | Límite |
|---|---|---|
| `php -v` y `php artisan --version` | PHP 8.5.10; Laravel 13.25.0 | Entorno CLI de este checkout. |
| `php artisan route:list -vv --except-vendor` | Diez rutas de aplicación listadas; siete internas bajo `auth` | No certifica un despliegue remoto; el endpoint de salud configurado no figura en este listado filtrado. |
| Sondeo temporal `php /tmp/sprint6-auditoria-sin-datos.php` | **36 comprobaciones correctas**, salida 0 | Herramienta temporal de inspección, fuera del repositorio; no implementa las pruebas de Fase 2. |
| Guard, provider, contrato y registro de autorización | `web`, Usuario, `password_hash`, cero habilidades Gate y sin Policies para los cinco modelos comprobados | Bootstrap aislado con configuración de testing. |
| Peticiones de invitado | Siete rutas internas: HTML 302 a login; Dashboard JSON 401 | Sesiones en memoria. CSRF utiliza el comportamiento de testing; no fue una prueba de CSRF real. |
| POST vacío de cada familia | Administrador, Operador, Encargado y usuario sin rol: 422 por validación en los doce casos | Usuarios y relaciones en memoria, sin cuentas creadas. Demuestra paso por auth y ausencia de rechazo de rol; no demuestra registro exitoso. |
| Revisión de `git status`, diff de archivos rastreados y diff del informe nuevo | Único cambio del checkout: este informe; sin errores de espacios en el diff | El informe es un archivo nuevo sin agregar al índice; se revisó también mediante `git diff --no-index`. |

El sondeo forzó sesión y caché en memoria, conexión SQLite `:memory:` y bloqueo de ejecución SQL antes de consultar. No ejecutó migraciones, seeders ni guardados de modelos. No se consultaron roles, usuarios, sesiones o credenciales de la base operativa. El informe no incluye secretos ni valores de credenciales.

Las pruebas existentes de navegación y relaciones se leyeron, pero no se ejecutaron: requieren la instancia MariaDB temporal y ejecutan migraciones/fixtures. No se declara que estén pasando en esta fase. No se renderizaron las páginas autenticadas con datos SQL ni se ensayó login contra una cuenta persistida. La conclusión de acceso compartido combina inspección de rutas/controllers/Requests y los sondeos indicados; el rechazo administrativo sigue PENDIENTE.

### Riesgos y decisiones pendientes

| Riesgo / hecho | Evidencia o efecto | Tratamiento propuesto / decisión pendiente |
|---|---|---|
| IDs de rol y empleado hardcodeados | `UsuarioSeeder` utiliza 1 en ambas FKs; `EmpleadoSeeder` también depende de IDs de cargo | Resolver referencias comprobadas y probar con IDs distintos. No suponer que Administrador tiene ID 1. |
| Catálogo contiene Encargado | Seeder con tres roles frente a los dos acordados | **Decisión de transición:** investigar cuentas asociadas y acordar reasignación; no borrar ni convertir automáticamente. En instalaciones nuevas, preparar dos roles. |
| Nombres duplicados o renombrados | `nombre_roles` no es único; seeder no idempotente | Catálogo controlado, validación y posible UNIQUE después de sanear datos. No permitir que un texto libre se convierta en un privilegio. |
| Confiar solo en el menú | Sidebar no limita HTTP | Gates aplicados a cada ruta y método; pruebas de URLs y mutaciones directas. |
| Usuario sin rol o rol eliminado | FKs declaradas obligatorias, pero relaciones en memoria/datos inconsistentes pueden ser null | Denegar por defecto con 403. Mantener integridad y no habilitar borrado de roles como operación rutinaria. |
| Empleado inexistente | Cuenta requiere FK de empleado y enlace único | Verificar existencia/unique al administrar y conservar FKs; fallback visual del sidebar no prueba integridad. |
| Confundir cargo, rol de producción y rol del sistema | Los tres catálogos ya existen | Autorizar únicamente mediante Usuario → Rol; turno pertenece a Producción. |
| Estado inexistente en Usuario/Empleado | No hay campo `activo` ni filtro de estado | Migraciones y flujo posterior. **Decisión:** efecto de desactivar empleado sobre su cuenta y sobre nuevos participantes. Conservar historial. |
| Cambio propio de rol | Administrador tendrá asignación de roles, sin reglas actuales sobre sí mismo | **Decisión:** permitir o bloquear degradación propia, con qué confirmación y recuperación. No inventar regla. |
| Sistema sin Administrador | No existe garantía en código ni esquema | **Decisión:** exigir al menos un Administrador activo y definir recuperación inicial. Si se confirma, proteger transaccionalmente cambios y concurrencia. |
| Desactivar al último Administrador | Estados y controles aún inexistentes | **Decisión:** bloqueo exacto de desactivación/degradación y definición de Administrador efectivo. No asumir aprobado. |
| Sesión vigente tras cambio de rol/estado | No hay comprobación actual de estas condiciones | Comprobar rol en cada petición, sin almacenar privilegios permanentemente en sesión. Al implementar estados, definir rechazo/invalidez de sesiones abiertas. |
| Asignación masiva de atributos sensibles | Usuario permite `rol_id`, `empleado_id` y `password_hash` | Solo entradas validadas y autorizadas; asignación explícita al administrar. Ocultar hashes no protege de una escritura indebida. |
| Contraseña sin hashing automático | Modelo sin mutador/cast hashed | Aplicar `Hash::make()` en futuras escrituras de contraseña; no registrar valores. El seed actual es de demostración y requiere tratamiento posterior antes de uso real. |
| Cambiar latas/coches altera interpretación histórica | Constante 18 usada también al mostrar lotes antiguos | **Decisión:** alcance del parámetro y snapshot/versionado para historia. No modificar la constante como si fuera configuración administrativa ya diseñada. |
| Fotos enlazadas mediante ruta de asset público | Historial usa `asset('storage/'.$detalle->foto)` | El `auth` de la página no protege por sí mismo un archivo servido estáticamente. **Decisión:** confidencialidad requerida para fotos; enlace/servidor públicos no comprobados en esta fase. |
| Ausencia de limitador visible en login | Ruta/controlador revisados sin throttling | Evaluar tarea separada; no modificar autenticación ahora ni declarar protección contra fuerza bruta existente. |

No se proponen roles extra, excepciones individuales, restricciones por turno/propietario, borrado físico rutinario ni reglas automáticas sobre el último Administrador sin confirmación del negocio.

### Plan para Fase 2

1. **Cerrar la transición del catálogo.** Inspección SQL de lectura de roles/cuentas en el entorno autorizado: existencia de Administrador/Operador, duplicados y Encargado. Acordar el tratamiento de cuentas heredadas y preparar una transición explícita antes de negarles acceso.
2. **Implementar identificación central de roles.** Nombres canónicos y helpers en Rol/Usuario, sin IDs fijos, con rechazo controlado de valores ausentes o desconocidos. Mantener autenticación y contrato de contraseña.
3. **Registrar los Gates.** `acceder-operacion` y `administrar-sistema` en el provider existente; comprobar sus reglas en aislamiento.
4. **Aplicar protección backend.** Añadir `can` a las rutas compartidas y reservar el grupo administrativo. Mantener logout accesible bajo auth. No crear CRUD ni módulos ficticios como parte de la protección base.
5. **Dar respuesta al rechazo.** 403 para autenticado sin permiso, mensaje HTML/JSON apropiado; conservar el flujo 302/401 de invitados.
6. **Adaptar fixtures y probar regresiones.** Roles válidos en las pruebas HTTP existentes; nueva matriz de acceso y URLs directas en entorno aislado. No desactivar middleware para hacer pasar pruebas de autorización.
7. **Actualizar presentación cuando existan rutas.** Reutilizar `@can` en sidebar, conservar accesibilidad y navegación móvil, verificar siete enlaces frente a tres. Las pruebas de módulos inexistentes permanecen pendientes.
8. **Revisar resultados y diff.** Autorizar cada método y endpoint secundario, verificar rechazo sin efectos y documentar límites. No marcar módulos IMPLEMENTADOS Y COMPROBADOS por haber probado solo el Gate.

En tareas administrativas posteriores, implementar campos de estado, formularios/Requests/Actions, CRUD y posibles Policies de recursos. Antes de esas tareas, confirmar reglas del último Administrador, cambios propios, relación empleado/cuenta inactiva y configuración latas/coches. La autorización por dos roles puede avanzar sin agregar tablas de permisos; los estados y la configuración tienen requisitos de datos independientes.

#### Documentación y cierre de esta fase

Se inspeccionó la estructura del Vault original `/home/lukagfv/Documentos/Obsidian Vault/PROYECTO_LARAVEL/` y se buscaron temas de roles, permisos y autenticación. Para una implementación posterior, las notas afectadas serán las de `PERSONAL Y USUARIOS/Usuarios.md`, `Roles.md`, `Permisos.md`, `Empleados.md`; `LARAVEL/Models.md`, `Middleware.md`, `Routes.md`, `Requests.md`; `BASE DE DATOS/Migraciones.md` y `Relaciones.md` si cambia el esquema; y `INICIO/Estado de proyecto.md`, `Roadmap.md`. Las decisiones y bugs se integrarán en sus notas existentes tras leerlas íntegramente, verificando sus nombres reales.

**DOCUMENTACIÓN MODIFICADA:** únicamente `SPRINT_6_FASE_1_ROLES_PERMISOS.md`, por el alcance expreso «Crea únicamente el informe». No se modificó el Vault ni `mejora_asignado.md` y no se copió documentación original al repositorio. No quedó autorización de escritura pendiente: no se necesitó escribir fuera del ámbito autorizado.

**CÓDIGO MODIFICADO:** ninguno. **PRUEBAS:** inspección estática, inventario de rutas y 36 comprobaciones aisladas sin SQL, correctas. **PENDIENTES:** implementación de Gates y cobertura de Fase 2; transición de Encargado/duplicados; administración y estados; decisiones de negocio señaladas; comprobación posterior contra base aislada.

Ubicación exacta del informe: `/home/lukagfv/Documentos/PROYECTOS-LARAVEL/panaderia-rs/SPRINT_6_FASE_1_ROLES_PERMISOS.md`.
