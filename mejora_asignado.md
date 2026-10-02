# Mejoras asignadas - Sistema de Login y Autenticación

**Fecha del análisis:** 2026-10-01.

**Objetivo:** documentar mejoras pendientes, su evidencia, archivos afectados, orden de trabajo y criterios de aceptación. Este documento no autoriza ni implementa cambios en el sistema.

**Actualización de implementación (2026-10-01):** se implementaron y comprobaron la navegación básica y logout por solicitud del usuario. La auditoría original se conserva como referencia del estado inicial; las casillas actualizadas y la sección «Navegación básica» indican el trabajo realizado. Las demás mejoras de autenticación continúan pendientes.

**Alcance y límites:** se inspeccionaron las rutas registradas, código propio, configuración efectiva, dependencias instaladas, implementación del framework y referencias globales. Laravel instalado: **13.25.0**; Composer exige `^13.17`; PHP CLI: **8.5.10**. La conexión MariaDB no estuvo disponible: la inspección de esquema obtuvo `PDOException`, código `2002`. La estructura de tablas aquí descrita procede de las migraciones; no se confirmó el esquema activo, los usuarios existentes ni la configuración de producción. No se ejecutaron migraciones, seeders ni intentos de autenticación. Las líneas son aproximadas y corresponden a la revisión indicada.

Las prioridades expresan urgencia; las etiquetas de la sección 19 expresan estado o necesidad. Una condición de despliegue no debe presentarse como una vulnerabilidad explotada. No se confirmó ningún problema de prioridad Crítica.

## 1. Resumen del estado actual

El login es propio, pequeño y utiliza la autenticación estándar de Laravel por sesiones. No se encontraron Breeze, Fortify, Jetstream, Sanctum, Passport, Socialite ni Spatie Permission en las dependencias o implementación.

| Elemento | Implementación comprobada |
|---|---|
| Entrada HTTP | `public/index.php` carga `bootstrap/app.php` |
| Rutas | `routes/web.php`; no existen `routes/auth.php` ni `routes/api.php` |
| GET | `/login`, nombre `login`, `LoginController::index()` |
| POST | `/login`, sin nombre, `LoginController::authenticate(Request $request)` |
| Vista | `resources/views/auth/login.blade.php` |
| Formulario | POST HTML tradicional a `url('login')`; envía `_token`, `username`, `password` |
| Middleware | Grupo `web` y `guest`; destinos protegidos por `web` y `auth` |
| Guard | `web`, driver `session` |
| Provider | `users`, driver `eloquent`, modelo `App\Models\Usuario` |
| Modelo / tabla | `Usuario` extiende `Illuminate\Foundation\Auth\User`; tabla `usuarios_sistema` |
| Credenciales | Búsqueda por `username`; comprobación del hash obtenido de `password_hash` |
| Validación | `required` para ambos campos, dentro del controlador; no hay Form Request |
| Hash | bcrypt, coste configurado 12; rehash automático activo |
| Sesión | Driver `database`, tabla `sessions`, conexión predeterminada `mariadb` |
| Redirección | `redirect()->intended('dashboard')`; autenticados en login van a `/dashboard` |
| Destinos | `/dashboard`, `/produccion` GET/POST, `/history` |
| Logout / recuperación | Sin implementación; existen elementos de interfaz incompletos |

El guard regenera la sesión destruyendo la anterior; el controlador vuelve a regenerarla. La siguiente petición recupera al usuario por `id`, no por username. El handler de sesiones guarda IP, user agent y última actividad, pero no constituye una auditoría histórica de accesos.

`resources/js/login.js` únicamente alterna la visibilidad de la contraseña. Laravel Boost, dependencia de desarrollo, añade `InjectBoost` y JavaScript de diagnóstico en el entorno inspeccionado (`local`, debug activado): usa `fetch`/`sendBeacon` hacia `POST /_boost/browser-logs` para consola y errores, sin sustituir el POST de login. No se observó captura automática de valores del formulario.

No se encontraron otros guards, login administrativo separado, autenticación API/móvil, tokens, OAuth, OTP, 2FA, listeners propios de login ni controles por estado. `roles_produccion` tiene una función de negocio distinta de los roles de acceso.

## 2. Problemas encontrados

### Problema 1: Intentos de login sin limitación

**Prioridad:** Alta.

**Archivo:** `routes/web.php`; `app/Http/Controllers/LoginController.php`.

**Ubicación:** POST `/login`, línea 15; `authenticate()`, líneas 21–29.

**Situación actual:** la ruta usa `web` y `guest`. El controlador valida y llama a `Auth::attempt()` sin `throttle`, `RateLimiter` ni bloqueo. El timebox del guard no limita el número de solicitudes. El throttle de `config/auth.php` corresponde a recuperación de contraseña, no al login.

**Problema:** no existe protección propia contra adivinación repetida de credenciales.

**Impacto:** seguridad de cuentas y consumo de CPU por bcrypt. La combinación con una contraseña inicial predecible aumenta el riesgo si esta se mantiene en un entorno accesible.

**Qué debería hacerse:** establecer una política explícita de limitación con expiración, mensaje genérico y recuperación automática.

**Cómo debería hacerse:** decidir y documentar límites según el uso real; implementar con `RateLimiter` en el flujo actual una clave por username e IP y, si se justifica, un límite agregado por IP. Consultar el contador antes del intento, incrementarlo tras fallos y limpiarlo tras éxito. Compartir un cache adecuado entre instancias: `CACHE_STORE=database` está configurado localmente y existen migraciones de cache, pero su operación debe verificarse. No fijar un número como si fuera un requisito del negocio. No cambiar mayúsculas del username sin comprobar su semántica. Si se adopta middleware `throttle` para un límite de peticiones, diferenciarlo de un contador de credenciales fallidas.

**Archivos que probablemente deberán modificarse:** `app/Http/Controllers/LoginController.php`; `routes/web.php` y `app/Providers/AppServiceProvider.php` solo si se elige un limitador nombrado de ruta; nuevos tests.

### Problema 2: Cuenta administrativa inicial con contraseña conocida

**Prioridad:** Alta, condicionada a su uso fuera de demostración.

**Archivo:** `database/seeders/UsuarioSeeder.php`.

**Ubicación:** `run()`, líneas 16–20.

**Situación actual:** crea `admin`, con `Hash::make('1234')`, `empleado_id=1` y `rol_id=1`. `DatabaseSeeder` lo invoca después de crear roles, cargos y empleados.

**Problema:** hashear correctamente no compensa una contraseña fija y predecible. No se confirmó si la cuenta existe o conserva esa contraseña en una base activa.

**Impacto:** acceso no autorizado si se despliega ese usuario sin cambiar su credencial.

**Qué debería hacerse:** separar datos de demostración del aprovisionamiento de cuentas reales y revisar las cuentas ya creadas.

**Cómo debería hacerse:** verificar el procedimiento de despliegue y el uso del seeder; definir aprovisionamiento controlado con una credencial no incluida en el código. Modificar únicamente la generación futura no rota cuentas existentes. Si existe exposición, ejecutar después de autorización un procedimiento de rotación y revocación de sesiones. Preservar el orden de FK del seeding y no ejecutar `DatabaseSeeder` para reparar una base existente.

**Archivos que probablemente deberán modificarse:** `database/seeders/UsuarioSeeder.php`; `database/seeders/DatabaseSeeder.php` si se separan entornos; `README.md` para documentar el procedimiento; tests.

### Problema 3: Nombre de contraseña incorrecto para rehash automático

**Prioridad:** Media.

**Archivo:** `app/Models/Usuario.php`.

**Ubicación:** `getAuthPassword()`, líneas 40–42; contrato heredado `getAuthPasswordName()`.

**Situación actual:** la lectura devuelve `password_hash`, pero `getAuthPasswordName()` heredado devuelve `password`. El provider instalado escribe el nuevo hash mediante ese nombre en `vendor/laravel/framework/src/Illuminate/Auth/EloquentUserProvider.php`, método `rehashPasswordIfRequired()`.

**Problema:** si el hash necesita actualización, Laravel intenta escribir una columna `password` no declarada en `usuarios_sistema`. El rehash ocurre antes de completar el login. No se verificaron hashes activos ni se reprodujo la escritura en una base real.

**Impacto:** una contraseña válida puede provocar error SQL y dejar al usuario sin autenticación después de cambiar el coste o introducir hashes de un coste anterior.

**Qué debería hacerse:** hacer coherentes el nombre autenticable de contraseña y su lectura, manteniendo `password_hash`.

**Cómo debería hacerse:** añadir en `Usuario` una implementación explícita del nombre de columna; conservar inicialmente el lector existente para reducir el alcance. Ejemplo orientativo, no implementado:

```php
public function getAuthPasswordName()
{
    return 'password_hash';
}
```

Crear un usuario de prueba con un hash de coste menor al configurado, autenticarlo en una base aislada y verificar actualización de `password_hash`, ausencia de escritura a `password` y éxito del login. No modificar `vendor`, renombrar la columna ni desactivar rehash para ocultar el defecto.

**Archivos que probablemente deberán modificarse:** `app/Models/Usuario.php`; nuevos tests de modelo y login.

### Problema 4: Logout inexistente y formulario temporal sin destino

**Prioridad:** Media.

**Archivo:** `routes/web.php`; `app/Http/Controllers/LoginController.php`; `resources/views/components/sidebar.blade.php`.

**Ubicación:** grupo `auth`; controlador completo; formulario de salida del componente, líneas 47–54.

**Situación actual:** no existe ruta/método logout, `Auth::logout()`, invalidación ni regeneración CSRF de cierre. El componente tiene POST a `#` y `@csrf`, con comentario de acción temporal. No se encontraron inclusiones de ese componente en las vistas actuales.

**Problema:** no hay mecanismo funcional de salida. Si se conectara el placeholder, enviaría a la URL actual, no cerraría sesión: en dashboard/history normalmente devolvería 405 y en producción podría alcanzar `store()`.

**Impacto:** sesiones que el usuario no puede terminar explícitamente, especialmente en equipos compartidos. `expire_on_close=false` significa que cerrar el navegador no garantiza logout.

**Qué debería hacerse:** implementar un logout por POST dentro de `auth` y ofrecer una salida visible en las páginas utilizadas.

**Cómo debería hacerse:** añadir un método al controlador existente; registrar POST `/logout` nombrado `logout`; ejecutar `Auth::logout()`, `$request->session()->invalidate()`, `$request->session()->regenerateToken()` y redirigir al login. Comprobar comportamiento sin `remember_token`: actualmente no existe remember me, por lo que no debe habilitarse como efecto lateral. Actualizar la acción del componente y decidir cómo integrarlo o añadir la salida a las vistas activas. Cambiar solo el componente sin incluirlo no resuelve la funcionalidad.

**Archivos que probablemente deberán modificarse:** `routes/web.php`; `app/Http/Controllers/LoginController.php`; `resources/views/components/sidebar.blade.php`; vistas activas `resources/views/dashboard/index.blade.php`, `resources/views/produccion/index.blade.php`, `resources/views/history/index.blade.php` según la integración elegida; tests.

### Problema 5: Credenciales sin validación de tipos ni longitud de username

**Prioridad:** Media.

**Archivo:** `app/Http/Controllers/LoginController.php`.

**Ubicación:** `authenticate()`, líneas 21–24.

**Situación actual:** ambas reglas son únicamente `required`; `username` se almacena en una columna de 50 caracteres. Laravel recorta username globalmente y excluye password del trim.

**Problema:** arrays no vacíos pueden superar `required`: el provider trata un username array como `whereIn`, mientras que una contraseña array puede causar un error de tipo. No se demostró un bypass de contraseña.

**Impacto:** peticiones malformadas, errores y contrato de entrada ambiguo.

**Qué debería hacerse:** exigir strings y longitud compatible para username sin modificar arbitrariamente las contraseñas válidas.

**Cómo debería hacerse:** mantener inicialmente la validación local y usar `required|string|max:50` para username y `required|string` para password. Decidir un máximo de contraseña solo tras revisar compatibilidad con las cuentas y el algoritmo; no añadir una regla mínima de creación al login que bloquee cuentas existentes. Evitar `exists` para username, porque permite diferenciar existencia y duplica consultas. No hacer trim ni lowercase de password. No imponer lowercase al username sin revisar collation y datos reales.

**Archivos que probablemente deberán modificarse:** `app/Http/Controllers/LoginController.php`; nuevos tests; la vista si se añade `maxlength="50"` como ayuda, conservando validación servidor.

### Problema 6: Errores e input anterior no aparecen en el formulario

**Prioridad:** Baja.

**Archivo:** `resources/views/auth/login.blade.php`.

**Ubicación:** formulario, líneas 39–71; username, línea 46.

**Situación actual:** Laravel almacena errores e input anterior, y el controlador conserva únicamente username al fallar credenciales. La vista no usa `$errors`, `@error` ni `old('username')`.

**Problema:** el backend devuelve información que la pantalla no muestra.

**Impacto:** usuario sin explicación del fallo, repetición innecesaria del username y accesibilidad deficiente de errores.

**Qué debería hacerse:** mostrar validación y error genérico de credenciales y recuperar únicamente username.

**Cómo debería hacerse:** usar salida Blade escapada para `old('username')` y mensajes; asociar errores con los inputs mediante `aria-describedby`, `aria-invalid` y un contenedor de aviso accesible. No rellenar password ni distinguir usuario inexistente de contraseña incorrecta. Añadir `autocomplete="username"` y `autocomplete="current-password"` sin cambiar nombres de inputs.

**Archivos que probablemente deberán modificarse:** `resources/views/auth/login.blade.php`; tests de renderizado; `resources/css/login.css` solo si resulta necesario para avisos legibles.

### Problema 7: Roles persistidos sin autorización en rutas

**Prioridad:** Media, condicionada a restricciones de negocio aún no definidas.

**Archivo:** `routes/web.php`; `app/Models/Usuario.php`.

**Ubicación:** grupo `auth`, líneas 19–24; relación `rol()`.

**Situación actual:** existe `rol_id` y roles Administrador, Encargado y Operador, pero todos los usuarios autenticados pasan la misma comprobación. No hay gates, policies o middleware propio que utilice el rol. `ProduccionController::store()` está pendiente de implementación.

**Problema:** la autenticación no impone separación por rol. No se puede declarar un permiso concreto incumplido sin conocer la matriz de acceso.

**Impacto:** posible acceso excesivo cuando se incorporen operaciones que deban restringirse.

**Qué debería hacerse:** acordar una matriz de capacidades antes de introducir denegaciones.

**Cómo debería hacerse:** especificar permisos por operación y rol, comprobar backend y añadir pruebas de 403 y acceso permitido. Para pocas capacidades, valorar gates en `AppServiceProvider` y `can` en rutas; si se necesita una policy asociada a un recurso real, definirla entonces. No hardcodear que todo requiere rol 1 ni confundir `cargo_id` o `roles_produccion` con autorización. Mantener acceso actual hasta que haya una regla aprobada.

**Archivos que probablemente deberán modificarse:** `app/Providers/AppServiceProvider.php`; `routes/web.php`; controladores protegidos solo si necesitan autorización contextual; tests. No se justifica instalar Spatie ni crear tablas nuevas todavía.

### Problema 8: Configuración local que requiere revisión antes de producción

**Prioridad:** Alta si esa configuración se utiliza en un despliegue público; no es un fallo del entorno local por sí mismo.

**Archivo:** `.env`; `config/session.php`; `composer.json`.

**Ubicación:** `APP_ENV=local`, `APP_DEBUG=true`, `APP_URL` HTTP; opción `secure`, línea 172; Boost en `require-dev`.

**Situación actual:** cookie HttpOnly, SameSite lax, dominio null; Secure no forzado; sesiones sin cifrado del payload, serialización JSON. Boost se activa en local o con debug. El despliegue real no se inspeccionó.

**Problema:** reutilizar estas condiciones en producción puede exponer errores y diagnóstico o transportar credenciales/cookies sin HTTPS.

**Impacto:** confidencialidad de credenciales/sesiones e información interna. No se afirma exposición pública actual.

**Qué debería hacerse:** validar el entorno de destino sin cambiar el desarrollo local innecesariamente.

**Cómo debería hacerse:** revisar HTTPS efectivo, terminación TLS, proxies, `APP_ENV`, `APP_DEBUG`, URL y `SESSION_SECURE_COOKIE=true` en producción; asegurar exclusión o desactivación de herramientas de desarrollo. Mantener HttpOnly y SameSite lax salvo requisito comprobado. Inspeccionar headers `Set-Cookie` reales. Evaluar cifrado de payload solo si la política de protección de datos lo requiere; base64 no cifra. No cambiar driver, cookie o clave como refactor incidental.

**Archivos que probablemente deberán modificarse:** configuración del despliegue; `.env.example` y `README.md` para documentar; `config/session.php` o `bootstrap/app.php` únicamente si se detecta una necesidad concreta de defaults o proxies. Ningún cambio de Composer es obligatorio por este hallazgo.

### Problema 9: Recuperación de contraseña anunciada sin implementación

**Prioridad:** Baja.

**Archivo:** `resources/views/auth/login.blade.php`; `config/auth.php`.

**Ubicación:** botón `olvido-contrasena`, línea 69; broker `passwords.users`, líneas 95–101.

**Situación actual:** botón `type="button"` sin acción ni listener. Existe configuración de broker, pero no rutas de recuperación, migración de `password_reset_tokens` ni email en `usuarios_sistema`.

**Problema:** la interfaz aparenta una función inexistente. Activar el broker predeterminado sin adaptar identidad/canal no resolvería el problema.

**Impacto:** confusión y cuentas sin un procedimiento de recuperación explicado.

**Qué debería hacerse:** decidir si corresponde recuperación gestionada por un administrador o autoservicio por un canal verificado.

**Cómo debería hacerse:** definir identidad, autorización, canal, expiración, auditoría y revocación de sesiones antes de implementar. Si la recuperación será administrativa, presentar instrucciones concretas en la vista. Si se aprueba autoservicio, diseñar sus rutas y esquema en una tarea separada; no inventar email ni una tabla ya existente.

**Archivos que probablemente deberán modificarse:** `resources/views/auth/login.blade.php`; `README.md`; `config/auth.php` solo si se implementa un broker adaptado. Nuevos endpoints/migraciones quedan fuera del alcance hasta definir el mecanismo.

### Problema 10: Tests y factory del esqueleto no cubren el modelo real

**Prioridad:** Baja.

**Archivo:** `tests/Feature/ExampleTest.php`; `database/factories/UserFactory.php`.

**Ubicación:** prueba GET `/`; `UserFactory::definition()`.

**Situación actual:** el test espera 200 en `/`, que está registrado como redirección. La factory referencia `App\Models\User`, inexistente, y genera `name`, `email`, `password` y `remember_token`, columnas ajenas al esquema real. No se encontró cobertura de autenticación ni uso actual de la factory.

**Problema:** el scaffold no valida el flujo y puede inducir errores al desarrollar nuevos tests.

**Impacto:** regresiones no detectadas y preparación de datos incorrecta.

**Qué debería hacerse:** crear tests específicos usando `Usuario` y sus FK, y adecuar el test raíz al contrato de redirección.

**Cómo debería hacerse:** usar inicialmente creación explícita de Rol/Cargo/Empleado/Usuario con hash de prueba, en una base aislada. No usar el seeder administrativo como fixture de producción. Revisar `phpunit.xml`, que declara SQLite en memoria, session/cache array y bcrypt coste 4. Si se justifica una factory de Usuario por reutilización, crearla con el esquema real y adaptar el soporte de factories del modelo; no hacerlo por obligación. Eliminar o sustituir la factory antigua solo después de comprobar referencias.

**Archivos que probablemente deberán modificarse:** `tests/Feature/ExampleTest.php`; nuevos tests; `database/factories/UserFactory.php` tras revisión; `app/Models/Usuario.php` solo si se adopta soporte de factories.

### Problema 11: Contrato de escritura de hashes no centralizado

**Prioridad:** Baja; riesgo futuro, no almacenamiento plano observado.

**Archivo:** `app/Models/Usuario.php`; `database/seeders/UsuarioSeeder.php`.

**Ubicación:** fillable de `password_hash`; `Hash::make()` del seeder.

**Situación actual:** el seeder hashea correctamente. El modelo no contiene cast `hashed` ni mutador de contraseña, y no se encontraron endpoints de creación/edición de usuarios.

**Problema:** un futuro escritor puede asignar texto plano a `password_hash` si no conoce el contrato.

**Impacto:** riesgo de implementación futura y mantenimiento; no justifica atribuir un incidente al login existente.

**Qué debería hacerse:** definir un único contrato antes de implementar gestión de credenciales.

**Cómo debería hacerse:** decidir entre escritores explícitos con `Hash::make` y un cast `hashed` en `password_hash`; probar valores nuevos y hashes existentes con la versión instalada. No introducir doble hashing ni cambiar el significado del atributo para otros consumidores. Revisar también que `rol_id` solo se asigne desde entradas autorizadas; no hay mass assignment de request en el login actual.

**Archivos que probablemente deberán modificarse:** `app/Models/Usuario.php` si se elige cast; `database/seeders/UsuarioSeeder.php` si debe adaptarse; tests del contrato. No requiere Services o Repositories.

### Problema 12: JavaScript externo en pantalla de credenciales sin SRI

**Prioridad:** Baja.

**Archivo:** `resources/views/auth/login.blade.php`.

**Ubicación:** script de Font Awesome servido por cdnjs, línea 76.

**Situación actual:** ejecuta JavaScript externo sin atributo de integridad. No se observó código que registre credenciales.

**Problema:** dependencia externa con capacidad de ejecución en el documento del login.

**Impacto:** exposición a fallos de cadena de suministro; no es evidencia de compromiso.

**Qué debería hacerse:** valorar integridad o distribución local del asset sin alterar el diseño.

**Cómo debería hacerse:** verificar contenido y versión exactos antes de calcular SRI; usar `crossorigin` compatible, o integrar un asset local autorizado. Si se adopta CSP, probar Vite, el script de alternar contraseña y Boost local. No inventar un hash de integridad ni instalar paquetes durante esta etapa.

**Archivos que probablemente deberán modificarse:** `resources/views/auth/login.blade.php`; `vite.config.js` solo si se adopta distribución local; configuración de headers solo si se aprueba CSP.

## 3. Mejoras de seguridad

Esta matriz agrupa las recomendaciones y distingue las protecciones ya presentes. Los pasos detallados se encuentran en los problemas referenciados.

| Tema | Qué ocurre actualmente | Qué debería ocurrir y por qué | Cómo implementarlo | Archivos afectados |
|---|---|---|---|---|
| Fuerza bruta | Sin contador/límite | Fallos limitados con expiración, para reducir adivinación y carga | Política por username/IP; incrementar fallos, limpiar éxito; problema 1 | Controlador; rutas/provider si se usa limitador nombrado; tests |
| Contraseña inicial | Seeder con valor conocido | Aprovisionamiento seguro y rotación si ya existe exposición | Separar demo/producción y revisar cuentas; problema 2 | Seeders, documentación de despliegue |
| Rehash | Lee `password_hash`, escribe nombre heredado `password` | Lectura/escritura coherentes para actualizar hashes | Corregir `getAuthPasswordName()`; problema 3 | `Usuario.php`, tests |
| Logout | Ausente | Cerrar guard y destruir sesión voluntariamente | POST auth + CSRF, logout/invalidate/regenerateToken; problema 4 | Rutas, controlador, interfaz activa, tests |
| Tipos de entrada | Solo required | Strings y username de hasta 50 caracteres | Reglas servidor y pruebas de arrays; problema 5 | Controlador, vista opcional, tests |
| Roles | Persistidos, sin control | Aplicar únicamente permisos aprobados | Matriz y gates/policies justificados; problema 7 | Provider, rutas/controladores, tests |
| Cookies/transporte | Local HTTP, Secure no forzado | HTTPS y Secure en producción, conservar HttpOnly/lax | Revisar configuración y headers reales; problema 8 | Entorno de despliegue, documentación |
| Escritura de hashes | Correcta en seeder, sin automatismo del modelo | Contrato único para futuros escritores | Hash explícito o cast probado; problema 11 | Modelo/seeder si corresponde, tests |
| Script externo | Sin SRI | Reducir confianza externa | SRI real o distribución local; problema 12 | Vista y assets si corresponde |
| Session fixation | Guard regenera destruyendo sesión anterior y controlador regenera | Mantener protección, no falta una llamada | Prueba de ID anterior/nuevo; sin refactor necesario | Tests; controlador permanece igual |
| CSRF | `@csrf` y `PreventRequestForgery` en web | Mantener protección del login y del futuro logout | No excluir login; probar fallback de token y origen en Laravel 13 | Tests y futuro formulario logout |
| Mensajes | Credenciales incorrectas genérico | Mantener indistinción y mostrarlo al usuario | Blade escapado; no añadir `exists` | Vista, tests |
| Sesiones | JSON en BD, payload sin cifrado | Mantener driver salvo requisito; evaluar acceso a datos | Restringir acceso BD; cifrado solo con motivo y pruebas | Entorno/configuración si se justifica |

**No aplicar sin nuevos requisitos:** bloqueos/inactividad no existen en modelo/esquema; remember me no existe en formulario ni en llamada; no hay redirección controlada directamente por un parámetro del login. No añadir estados, tokens o filtros de redirect como si corrigieran un defecto demostrado. Verificar host/proxies del despliegue antes de afirmar un open redirect.

## 4. Mejoras de arquitectura

`LoginController` tiene dos métodos breves y una sola responsabilidad de sesión. No hay servicios, repositorios, acciones o DTOs duplicados. No se justifica introducir esas capas para envolver `Auth::attempt()`.

La validación local es adecuada al tamaño actual. Primero corregir reglas y añadir rate limiting manteniendo el flujo. Un Form Request puede ser útil si esas dos responsabilidades crecen lo suficiente para dificultar la lectura; es una decisión posterior, no un requisito de Laravel 13. Si se adopta, su `authorize()` deberá permitir invitados y sus reglas conservar los campos reales. Elegir una única ubicación para el limitador, evitando duplicar contadores entre request y controlador.

La vista contiene formulario y presentación, sin consultas propias de autenticación. El sidebar accede a `auth()->user()->empleado`, pero no está incluido actualmente; integrar componentes exige comprobar contexto autenticado y relaciones, no mover consultas del login a una nueva capa.

`bootstrap/app.php` usa la configuración moderna de middleware. No crear un Kernel antiguo ni migrar providers por asumir una versión anterior. Los residuos del scaffold deben tratarse como limpieza separada del arreglo funcional.

## 5. Mejoras de rutas

Las rutas GET y POST del login están correctamente separadas por método y ambas en `guest`. Los destinos están en `auth`; no se detectaron duplicados ni prefijos conflictivos.

Mejoras concretas: añadir un nombre al POST para evitar la URL literal en la vista y registrar logout dentro de `auth`. Ejemplo de organización futura, **no aplicada**:

```php
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'index'])->name('login');
    Route::post('/login', [LoginController::class, 'authenticate'])->name('login.authenticate');
});

Route::middleware('auth')->group(function () {
    // Mantener aquí las rutas dashboard, produccion e history existentes.
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
});
```

`logout()` y `login.authenticate` son propuestas, todavía no existen. No es necesario crear `routes/auth.php`, cambiar URI o nombres existentes, ni dividir grupos. `Route::redirect('/', '/login')` está registrado como ANY; no se encontró una necesidad de cambiarlo. No crear recuperación/register/API sin un requisito.

## 6. Mejoras de controllers

| Controller | Responsabilidad actual | Mejora justificada |
|---|---|---|
| `LoginController` | `index()` devuelve login; `authenticate()` valida, intenta, regenera y redirige | Tipos, limitador, logout y pruebas. Puede permanecer como único controlador de sesiones |
| `DashboardController` | `index()` devuelve `dashboard.index` | No necesita lógica de login; mantener protección en rutas |
| `HistorialController` | `index()` devuelve `history.index` | Igual; permisos solo cuando exista matriz aprobada |
| `ProduccionController` | `index()` devuelve vista; `store()` pendiente | No usarlo como logout ni completar producción dentro de esta tarea; autorización contextual solo si procede |

No hay constructor ni dependencias inyectadas propias en `LoginController`, métodos largos, privados ocultos o consultas directas innecesarias. Los imports `Request`/`View` sin uso en dashboard/historial son limpieza menor, no una mejora funcional de autenticación; no ampliar esta tarea para eliminarlos.

El fallback puede expresarse como `redirect()->intended(route('dashboard'))` para vincularlo al nombre existente, manteniendo intended. Es una mejora de mantenimiento opcional, no un error de redirección actual.

## 7. Mejoras del modelo User

El modelo real se llama **`Usuario`**; no existe `app/Models/User.php`.

- Conservar `$table = 'usuarios_sistema'`, identificador `id`, timestamps y las relaciones `empleado()`/`rol()`.
- Corregir el nombre de atributo de contraseña para rehash; no renombrar la tabla o columna.
- Conservar `$hidden = ['password_hash']`.
- `$fillable` limita atributos pero permite `rol_id` y `password_hash`: revisar escritores futuros, no atribuir mass assignment al login actual.
- No hay casts, scopes, mutadores o SoftDeletes propios. No agregarlos sin un requisito. El cast `hashed` tiene sentido únicamente con un contrato de escritura probado (problema 11).
- No añadir `remember_token` por anticipación: `Auth::attempt()` usa remember=false y la vista no ofrece esa opción.
- No añadir email, estado o bloqueo sin diseño y autorización. Las capacidades heredadas de recuperación/verificación no constituyen flujos implementados.
- `HasFactory` solo sería necesario si se decide usar una factory del modelo; las pruebas pueden preparar registros explícitos.

## 8. Mejoras de validación

Contrato actual: `username=required`, `password=required`. Normalización global: trim de username y conversión de vacío a null; password está excluido del trim. No hay validación propia de rol o estado.

Contrato recomendado inicial:

```php
'username' => ['required', 'string', 'max:50'],
'password' => ['required', 'string'],
```

Definir longitud máxima de password solo con compatibilidad comprobada; no convertir reglas de fortaleza para creación en condiciones de entrada al login. Mantener mensajes de fallo genéricos y presentar los de validación de forma accesible. No usar `exists`, lowercase automático o sanitización que cambie la contraseña. La collation real y las cuentas deben revisarse antes de alterar sensibilidad del username.

Form Request: **opcional**; no existe actualmente y no es imprescindible para estas reglas. El bloqueo de intentos debe tener una ubicación única con pruebas de contador y ventana.

## 9. Mejoras del frontend del login

El formulario ya tiene método POST, `@csrf`, labels relacionados mediante `for/id`, inputs con nombres correctos y `required`. El botón de visibilidad tiene `type="button"` y su JavaScript actualiza el aria-label. Mantenerlos.

Mejoras justificadas por ausencias verificadas:

1. Mostrar `$errors`/`@error` con Blade escapado; conservar mensaje genérico.
2. Recuperar `old('username')`; nunca conservar password.
3. Añadir autocomplete de usuario y contraseña actual y asociación accesible de errores.
4. Dar una función real o explicación al botón de recuperación según el mecanismo aprobado.
5. Probar el logout visible: el sidebar existe, pero no está incluido.
6. Valorar SRI/asset local para el script externo.

No se detectó AJAX de login ni interceptores, localStorage/sessionStorage para credenciales. No convertir el formulario a SPA o AJAX. Un estado de envío/deshabilitación temporal es opcional: no se demostró un bug de doble envío; si se añade, probar navegación atrás y reintento tras error. No añadir remember me ni rediseñar el aspecto por esta auditoría.

## 10. Código duplicado

No se encontró código propio repetido de autenticación, un segundo `authenticate()` ni rutas de login duplicadas.

**Archivo A:** `app/Http/Controllers/LoginController.php`, `authenticate()`, línea 32.

**Archivo B:** `vendor/laravel/framework/src/Illuminate/Auth/SessionGuard.php`, `updateSession()`.

**Código duplicado o responsabilidad duplicada:** ambos regeneran la sesión durante un login correcto. El guard instalado usa `regenerate(true)` y el controlador usa `regenerate()`.

**Recomendación:** conservar la llamada explícita del controlador. Es una defensa clara y compatible con el flujo actual; no representa un defecto funcional observado. No editar el framework ni retirar regeneración para reducir una llamada. Verificar ID/token mediante pruebas. No hay una centralización nueva necesaria.

## 11. Código legacy o posiblemente obsoleto

| Elemento | Evidencia / referencias | Uso observado | Verificar antes de retirar |
|---|---|---|---|
| `database/factories/UserFactory.php` | Importa User inexistente y columnas del scaffold | Sin consumidores encontrados en tests/seeders actuales | Autodescubrimiento de factories y herramientas externas; sustituir fixtures antes de retirar |
| Import `App\Models\User` en `config/auth.php` | Provider real usa Usuario; import sin uso | No determina modelo activo | Overrides `AUTH_MODEL` y config efectiva |
| Import `App\Models\User` en `database/seeders/DatabaseSeeder.php` | Invoca seeders propios | Import sin uso | Que nuevas tareas no lo utilicen; el seeder completo sí está activo |
| Provider database comentado en `config/auth.php` | Ejemplo de tabla users | Inactivo | No confundir con provider users activo |
| Broker passwords.users | Tabla de tokens no declarada y sin endpoints | Configurado, sin flujo consumidor encontrado | Requisito de recuperación y código de despliegue |
| `resources/views/components/sidebar.blade.php` | Placeholder de logout y acceso a usuario/empleado | Sin inclusión encontrada | Búsqueda global de componentes, vistas dinámicas y uso manual; integrar si corresponde |
| `resources/layouts/app.blade.php` | Fuera de carpeta estándar de views | Sin referencia encontrada | Configuración de paths de vistas y consumidores externos |
| `resources/views/history_pedido/index.blade.php` | Sin ruta/controller asociado encontrado | Sin uso detectado | Inclusiones dinámicas y desarrollo pendiente |
| `tests/Feature/ExampleTest.php` | Espera 200 de raíz ahora redirigida | Test existente, no ejecutado en auditoría | Actualizar contrato de prueba, no borrar cobertura por conveniencia |

El historial Git explica que `Usuario` fue adaptado al esquema y posteriormente a Authenticatable. Esa personalización está activa y no es legacy por diferir del scaffold. Ninguna fila autoriza eliminación automática.

## 12. Archivos que deberían modificarse

Los archivos condicionales solo se tocarán si se confirma su decisión asociada. Riesgo se refiere al cambio futuro, no a una modificación realizada.

| Archivo | Cambio recomendado | Prioridad | Riesgo |
|---|---|---|---|
| `app/Models/Usuario.php` | Nombre de contraseña para rehash; cast/HasFactory solo si se aprueban | Media | Alto si altera lectura o dobla hash |
| `app/Http/Controllers/LoginController.php` | Tipos, limitador, logout; preservar regeneración e intended | Alta | Medio: sesiones, contador y errores |
| `routes/web.php` | POST logout; nombre de POST login; permisos condicionados | Media | Medio: middleware y contratos |
| `app/Providers/AppServiceProvider.php` | Limitador nombrado o gates solo según estrategia elegida | Alta/Media, condicional | Medio: alcance global |
| `resources/views/auth/login.blade.php` | Errores, old username, autocomplete, action nombrada, recuperación y SRI | Baja | Bajo; evitar reexponer password |
| `resources/js/login.js` | Solo si se aprueba estado de envío | Baja, opcional | Bajo: reintentos y botón visibility |
| `resources/css/login.css` | Solo avisos legibles si lo requieren | Baja, condicional | Bajo |
| `resources/views/components/sidebar.blade.php` | Acción logout real | Media | Medio si se integra sin contexto auth |
| `resources/views/dashboard/index.blade.php` | Acceso a salida visible si se integra allí | Media | Bajo/Medio: integración de componente |
| `resources/views/produccion/index.blade.php` | Salida visible sin formularios anidados | Media | Medio: ya contiene formulario |
| `resources/views/history/index.blade.php` | Salida visible | Media | Bajo |
| `app/Http/Controllers/ProduccionController.php` | Autorización contextual solo con matriz aprobada; no completar store aquí | Media, condicional | Medio: negocio |
| `app/Http/Controllers/DashboardController.php` | Solo autorización contextual si las rutas no bastan | Media, condicional | Bajo/Medio |
| `app/Http/Controllers/HistorialController.php` | Solo autorización contextual si procede | Media, condicional | Bajo/Medio |
| `database/seeders/UsuarioSeeder.php` | Aprovisionamiento sin contraseña fija en entorno real | Alta, condicionada | Medio: creación de cuentas |
| `database/seeders/DatabaseSeeder.php` | Separación demo/producción; import residual tras revisión | Alta/Baja, según tarea | Medio: orden de FK |
| `database/factories/UserFactory.php` | Revisar/sustituir scaffold tras localizar consumidores | Baja | Bajo/Medio: fixtures |
| `tests/Feature/ExampleTest.php` | Afirmar redirección real de raíz | Baja | Bajo |
| `.env.example` | Documentar condiciones de producción sin secretos | Alta, condicionada | Bajo |
| `README.md` | Aprovisionamiento, recuperación y despliegue | Baja | Bajo |
| `config/auth.php` | Import residual; broker solo con diseño de recuperación | Baja, condicional | Alto si cambia provider |
| `config/session.php` | Solo necesidad de defaults demostrada; Secure puede fijarse en entorno | Alta, condicionada | Alto si invalida sesiones |
| `bootstrap/app.php` | Solo necesidad comprobada de proxies/configuración | Alta, condicionada | Alto: middleware global |
| `vite.config.js` | Solo asset local si estrategia de integridad lo requiere | Baja, condicional | Medio: build |

La configuración real de despliegue puede estar fuera del repositorio; no se inventa un archivo para ella. `.env` local no necesita cambios para simular producción. No se recomiendan cambios a migraciones existentes, guard, provider, tabla o `vendor` para las correcciones principales.

## 13. Archivos nuevos recomendados

**Necesarios para cobertura futura, todavía no creados:**

- `tests/Feature/LoginAuthenticationTest.php` (propuesto): GET/POST, fallos, sesiones, intended, guest/auth, entradas inválidas y rehash.
- `tests/Feature/LogoutAuthenticationTest.php` (propuesto): cuando exista logout, POST, CSRF, invalidación, token y protección posterior.
- `tests/Feature/LoginRateLimitingTest.php` (propuesto): contadores, ventana, éxito, aislamiento por identidad/IP y comportamiento JSON.

**Solo si la complejidad o reutilización lo justifican:**

- `app/Http/Requests/Auth/LoginRequest.php` (propuesto): reglas y mensajes del login; eventualmente limitador si se decide ubicarlo allí. No es necesario para corregir dos reglas ni debe duplicar autenticación/contador.
- `database/factories/UsuarioFactory.php` (propuesto): fixtures del esquema real si muchos tests necesitan reutilizarlos; resolver FK y soporte `HasFactory` antes de adoptarla.

No se recomiendan Services, Repositories, Actions, DTOs, otro controlador, middleware de roles, nuevas tablas de tokens o migraciones de estados sin requisitos adicionales. En esta etapa el único archivo nuevo es `mejora_asignado.md`.

## 14. Plan de implementación

Las casillas marcadas reflejan únicamente tareas implementadas y comprobadas. Las pruebas se preparan desde el principio y se ejecutan por cada cambio; la fase 5 es la validación integrada, no el primer momento para probar.

### Fase 1 - Correcciones críticas

El título indica urgencia de revisión; no hay vulnerabilidad Crítica confirmada.

- [ ] Comprobar esquema activo, collation y configuración del despliegue mediante inspección autorizada.
- [ ] Preparar tests aislados del comportamiento actual y regresión de rehash.
- [ ] Corregir el nombre de columna autenticable sin cambiar `password_hash`.
- [ ] Definir e implementar rate limiting con pruebas.
- [ ] Comprobar uso del admin inicial y rotar/revocar solo si corresponde.
- [ ] Validar HTTPS/debug/Secure de producción, conservando el entorno local.

### Fase 2 - Autenticación

- [ ] Reforzar validación de tipos y longitud de username.
- [x] Implementar logout completo por POST protegido.
- [x] Integrar una salida visible sin enviar al endpoint de producción ni anidar forms.
- [ ] Mostrar errores y recuperar username con salida escapada.
- [ ] Añadir autocomplete y asociación accesible de errores.
- [ ] Definir el comportamiento real del botón de recuperación.
- [ ] Mantener y verificar intended, guard, provider y regeneración.

### Fase 3 - Arquitectura

- [ ] Nombrar POST login y actualizar action sin cambiar URI ni campos.
- [ ] Decidir si un Form Request aporta claridad; conservar validación local si no.
- [ ] Definir contrato de escritura de hashes antes de añadir gestión de usuarios.
- [ ] Confirmar matriz de roles antes de introducir restricciones.
- [ ] Aplicar solo autorizaciones aprobadas y probarlas.
- [ ] Valorar integridad del script externo como cambio independiente.

### Fase 4 - Limpieza

- [ ] Revisar referencias a UserFactory y imports User residuales.
- [ ] Adaptar fixtures y test de raíz al esquema/contrato reales.
- [ ] Verificar uso de sidebar/layout/history_pedido antes de cualquier retirada.
- [ ] Documentar decisiones de recuperación y aprovisionamiento.
- [ ] Mantener limpieza separada de correcciones funcionales y sin editar vendor.

### Fase 5 - Pruebas

- [ ] Ejecutar pruebas de login, logout y limitador en base aislada.
- [ ] Validar cookies y CSRF con middleware real, no solo tests que lo omiten.
- [ ] Verificar persistencia con driver database en entorno desechable equivalente.
- [x] Probar navegación hacia dashboard/produccion/history y acceso denegado tras logout.
- [ ] Probar expiración de sesión con el driver database.
- [ ] Comprobar permisos solo para la matriz aprobada.
- [x] Ejecutar build si cambian assets y revisar interfaz con teclado.
- [x] Revisar diff, rutas efectivas y ausencia de cambios fuera del alcance.

## 15. Orden exacto recomendado

1. Confirmar alcance autorizado y revisar Git; inspeccionar esquema/despliegue pendiente sin alterar datos.
2. Preparar base de pruebas independiente y fixtures con FK reales; revisar que no apunten a MariaDB de trabajo.
3. Crear pruebas de contrato actual y una regresión que demuestre el fallo de rehash.
4. Corregir `getAuthPasswordName()` y ejecutar sus pruebas.
5. Corregir tipos de credenciales y probar arrays, vacíos y username largo.
6. Acordar límite/ventana; implementar contador único y probarlo.
7. En paralelo operativo, verificar credencial inicial y despliegue; rotar o ajustar solo si existe exposición y autorización.
8. Añadir POST logout y método; comprobar invalidación antes de conectar la interfaz.
9. Integrar salida en vistas activas y verificar que no se aniden formularios.
10. Mostrar errores/old username y añadir autocomplete sin almacenar password.
11. Nombrar POST login y actualizar action; conservar GET `login` e intended.
12. Decidir recuperación de contraseña; implementar solo el mecanismo aprobado o instrucciones claras.
13. Acordar autorización por roles, y después aplicar y probar cada capacidad si es requerida.
14. Evaluar Form Request/contrato de hashes/SRI únicamente como cambios separados justificados.
15. Revisar scaffold y referencias; adaptar tests/factory/imports sin retirar código no comprobado.
16. Ejecutar suite, verificaciones HTTP reales de sesión/CSRF/cookies y build cuando proceda.
17. Revisar diff y `route:list`, documentar resultados y entregar los cambios para revisión antes de desplegar.

## 16. Pruebas necesarias

No existe cobertura específica de los casos siguientes. Los tests propuestos no se ejecutaron durante la auditoría.

| Caso | Estado actual | Criterio de aceptación / momento |
|---|---|---|
| Raíz `/` | Existe ExampleTest, pero espera 200 | Actualizar a redirección `/login` antes de cambios |
| Invitado abre GET login | No existe | 200, formulario con action correcto, names y token |
| Usuario válido | No existe | Auth guard web, usuario correcto y redirect dashboard |
| Contraseña incorrecta | No existe | Invitado, error genérico, sin password en old input |
| Username inexistente | No existe | Mismo mensaje/contrato visible que contraseña incorrecta |
| Campos vacíos | No existe | Validación, sin attempt; navegador redirect y JSON 422 |
| Credenciales arrays | No existe | Tras mejora: rechazo de validación, sin 500 ni consulta whereIn |
| Username >50 | No existe | Tras mejora: rechazo coherente; no cambiar password |
| Password con espacios | No existe | No se recorta; verificación con valor exacto |
| Rehash | No existe | Hash anterior actualizado en `password_hash`; login exitoso; sin columna password |
| Sesión regenerada | No existe | ID diferente, sesión anterior destruida; token renovado |
| Persistencia database | No existe | Cookie permite siguiente GET; `sessions` con id usuario y metadatos, sin contraseña |
| Autenticado vuelve a login | No existe | GET y POST redirigen dashboard mediante guest |
| Invitado en destinos | No existe | Redirección login; JSON 401 según negociación |
| Intended | No existe | GET protegido, login y retorno a destino original; fallback dashboard |
| Sesión expirada | No existe | GET protegido vuelve a login; revisar token antiguo en POST |
| CSRF login | No existe | Token inválido/sin token con origen no aceptado: 419; válido: continúa |
| Origen Laravel 13 | No existe | Diferenciar aceptación same-origin de fallback a token; no exigir 419 con origen aceptado |
| Errores renderizados | No existe | Tras mejora: mensaje visible, username escapado; password vacío |
| Rate limiting | No existe | Tras mejora: umbral/ventana definidos, bloqueo, expiración, limpieza por éxito |
| Aislamiento del contador | No existe | No bloquear indiscriminadamente otros usuarios/IP; probar estrategia aprobada |
| Logout | Función aún inexistente | Tras implementación: POST protegido cierra guard y redirige login |
| Sesión tras logout | No existe | Datos anteriores invalidados, token nuevo, destinos inaccesibles sin autenticación |
| Logout GET/CSRF | No existe | GET no cierra sesión; POST sin protección válida rechazado |
| Salida desde producción | No existe | Logout usa endpoint propio; no envía ni altera form de producción |
| Roles/permisos | Sin política implementada | Crear solo tras matriz: permitido/403 en backend por operación |
| Remember me | No existe en producto | No habilitar ni crear pruebas funcionales de una opción inexistente; conservar attempt sin remember |
| Bloqueados/inactivos | No existe en esquema/flujo | No inventar estados; pruebas solo si se aprueba esa función |
| Hash oculto | No existe | Serialización de Usuario no expone password_hash |
| Cookies en producción | Sin prueba | Set-Cookie Secure/HttpOnly/SameSite correctos con HTTPS real |

**Preparación segura:** `phpunit.xml` declara SQLite en memoria y drivers session/cache array. Verificar configuración efectiva antes de ejecutar tests; usar esquema aislado y `RefreshDatabase` solo allí. `tests/Pest.php` no activa actualmente ese trait. No modificar ni poblar la base MariaDB del proyecto. Los tests Laravel suelen omitir la protección CSRF; para demostrarla, habilitar el middleware en una prueba apropiada o usar HTTP en un entorno desechable. Las pruebas con sesiones array no demuestran persistencia database ni destrucción física de una fila anterior: cubrir ambas capas según el caso.

## 17. Riesgos de la implementación

| Parte delicada | Riesgo | Prevención |
|---|---|---|
| Guard/provider | Perder acceso o consultar modelo inexistente | Conservar web/users/Usuario y verificar config efectiva, incluidos overrides |
| Tabla/columnas | Romper FK de sesiones, producción y pedidos | No renombrar usuarios_sistema/password_hash/id; contrastar esquema activo |
| Rehash | Escribir campo equivocado o doble hash | Cambio pequeño en nombre; probar hash viejo/nuevo antes de expandir casts |
| Rutas | Romper formulario y redirección de invitados | Conservar GET `login`, URI y nombres existentes; añadir POST name sin colisiones |
| Middleware | Permitir invitados o bloquear login | Mantener web/guest/auth y orden de sesiones; probar HTML y JSON |
| Sesiones | Invalidación accidental o sesión antigua válida | Preservar regeneración; probar ID, handler database, cookie y logout |
| Intended | Perder retorno al destino | No sustituir por redirect fijo; probar `/produccion` y `/history` |
| Formulario | Cambiar credenciales o reexponer password | Conservar names; solo old username; salida escapada |
| Normalización | Bloquear cuentas existentes | No lowercase/trim password; verificar collation antes de modificar username |
| Limitador | Bloqueo global de usuarios legítimos | Política explícita, ventana, claves, IP de proxies y cache compartido probados |
| Logout UI | Enviar a store o anidar forms | Endpoint propio e integración fuera del formulario de producción |
| Roles | Denegar accesos hoy permitidos sin requisito | Matriz aprobada y pruebas por capacidad antes de aplicar restricciones |
| Seeder | No rotar usuarios existentes o violar FK | Separar aprovisionamiento de rotación; no reseed de base real |
| Tests | Limpiar base de trabajo | Aislamiento comprobado antes de RefreshDatabase/migraciones de prueba |
| Assets/CSP | Romper iconos, Vite o diagnóstico local | Cambio independiente; probar build, navegador y entorno local |

## Elementos que actualmente funcionan y NO deberían cambiarse

Esta sección corresponde al punto 18 del plan.

- Uso del sistema estándar de Auth con provider Eloquent; no reemplazarlo por familiaridad con otro paquete.
- Modelo `Usuario` adaptado al esquema existente y lectura de `password_hash`; completar el contrato, no sustituirlo por User.
- Consulta por username parametrizada y verificación del hash fuera de SQL.
- `Hash::make()` en el seeder: el defecto es la contraseña elegida, no el hashing.
- `$hidden` para password_hash y ausencia de asignación masiva de request en login.
- Separación GET/POST y middleware guest/auth.
- Protección `web`, `@csrf` y middleware moderno `PreventRequestForgery`.
- Regeneración ya presente después del login; no afirmar que falta ni eliminarla como limpieza.
- Error genérico de credenciales y conservación solo de username; mejorar su presentación.
- Redirección intended y fallback operativo a dashboard; conservar destino previo.
- `redirectUsersTo('/dashboard')` para autenticados en login.
- Cookie HttpOnly, SameSite lax y serialización JSON; revisar Secure en producción de forma separada.
- Formulario HTML tradicional y JavaScript limitado a visibilidad de password.
- Labels/IDs existentes y botón de visibilidad que no envía el formulario.
- Estructura moderna de bootstrap; no introducir un Kernel o providers antiguos.
- Roles/cargos y sus relaciones: no cambiar su significado por interpretar nombres.

## 19. Estado de cada mejora

`PENDIENTE`: trabajo identificado aún no realizado. `RECOMENDADO`: mejora justificada no bloqueante. `IMPORTANTE`: corrección prioritaria. `CRÍTICO`: solo para una condición crítica confirmada; ninguna está confirmada en esta auditoría. `NO NECESARIO`: cambio que no se justifica con la evidencia actual. Todas las implementaciones requieren autorización posterior.

| Mejora | Estado | Problema / solución |
|---|---|---|
| Rate limiting | `IMPORTANTE` | Intentos ilimitados; política y contador probado |
| Contrato de rehash | `IMPORTANTE` | Nombre heredado password; mapear a password_hash |
| Admin inicial | `IMPORTANTE` | Credencial conocida; comprobar uso y aprovisionar/rotar si corresponde |
| Validación de tipos | `IMPORTANTE` | Arrays superan required; strings y límite username |
| Logout | `IMPLEMENTADO Y COMPROBADO` | POST protegido, invalidación, nuevo token CSRF y salida en las tres páginas; ver Navegación básica |
| Errores/old username | `PENDIENTE` | Datos flash invisibles; renderizado escapado |
| Autocomplete/accesibilidad | `RECOMENDADO` | Faltan ayudas de credenciales y errores asociados |
| Configuración producción | `IMPORTANTE` | Despliegue no verificado; HTTPS/debug/Secure |
| Roles | `PENDIENTE` | Falta política; definir matriz antes de restringir |
| Recuperación | `PENDIENTE` | Botón inerte; definir mecanismo real |
| Pruebas del flujo | `IMPORTANTE` | Sin cobertura; base aislada y regresiones |
| Factory/imports scaffold | `RECOMENDADO` | Referencias residuales; verificar consumidores |
| Nombre POST login | `RECOMENDADO` | Sin name; añadir sin cambiar URI |
| Contrato de escritura hash | `RECOMENDADO` | Riesgo futuro; decidir antes de gestión de usuarios |
| Integridad script externo | `RECOMENDADO` | Sin SRI; valorar asset local/integridad comprobada |
| Form Request | `NO NECESARIO` | Opcional solo si la lógica crece; controlador actual pequeño |
| Services/Repositories/Actions/DTOs | `NO NECESARIO` | No hay complejidad que justifique capas nuevas |
| Otra regeneración de sesión | `NO NECESARIO` | Ya existe en guard y controlador; mantener y probar |
| Nuevo sistema de Auth/guard/provider | `NO NECESARIO` | Implementación estándar adecuada al esquema |
| Remember me / 2FA / estados | `NO NECESARIO` | No implementados ni exigidos; tareas separadas si se solicitan |
| Eliminar código sin verificar | `NO NECESARIO` | Revisar referencias antes de cualquier limpieza |

**Condiciones críticas confirmadas:** ninguna. Si una verificación posterior demuestra exposición de la credencial administrativa o del entorno debug, reevaluar prioridad y registrar evidencia sin introducir secretos en este documento.

**Entrega de la auditoría original:** únicamente documentación. La implementación posterior de navegación y logout se detalla a continuación.

## Navegación básica

Implementada y comprobada el 2026-10-01. Es una navegación provisional; no representa el diseño visual definitivo.

### Componente reutilizado y funcionamiento

Se reutilizó `resources/views/components/sidebar.blade.php` mediante `<x-sidebar />` al inicio del body de Dashboard, Producción e Historial. Laravel resuelve el componente Blade anónimo correctamente, comprobado al renderizar las tres vistas y abrirlas en Chrome. El componente es la única fuente del menú; no se crearon otros componentes ni layouts.

Muestra Dashboard (`route('dashboard')`), Producción (`route('produccion.index')`), Historial (`route('history.index')`) y Cerrar sesión. Pedidos se oculta temporalmente porque no tiene una ruta funcional; no se creó ninguna ruta, módulo ni controller de pedidos. Los enlaces usan texto y no dependen de Font Awesome.

La sección activa se determina en Blade con `request()->routeIs('dashboard')`, `request()->routeIs('produccion.*')` y `request()->routeIs('history.*')`. Se aplica la clase `activo` y `aria-current="page"`. La navegación tiene `<nav aria-label="Navegación principal">`, enlaces reales y foco visible de teclado.

Se confirmó que `App\Models\Usuario::empleado()` es una relación `belongsTo` válida. Se muestra `nombre_empleados`, con acceso null-safe y fallback a `username` y después «Usuario». No se añadieron consultas explícitas ni cambios al modelo; se conserva el acceso a la relación existente.

### CSS y ajustes mínimos necesarios

El CSS antiguo de Dashboard usa `.barra-lateral`, `.avatar`, `.nav-iconos` y `.cerrar-sesion`, mientras el componente usa `.sidebar` y clases `sidebar-*`. Esa CSS antigua no coincide con el componente y tampoco está compartida con Producción/Historial. Se conservó sin cambios y se añadió `resources/css/sidebar.css`, importado por los tres CSS existentes. Sus reglas se limitan al menú y lo colocan en el flujo normal, sin posición fija ni superposición. No se cambiaron las reglas de tarjetas, gráficos, filtros, modales, tipografía, colores generales ni distribución del contenido.

Al añadir logout, `document.querySelector('form')` de Producción seleccionaría el nuevo primer formulario. Se asignó `id="formulario-produccion"` al formulario existente y se cambió únicamente el selector de Cancelar en `resources/js/produccion.js`. Se comprobó en Chrome que Cancelar limpia el formulario y restablece Pan, y que Guardar sigue enviando POST multipart con CSRF a `route('produccion.store')`. Su controller `store()` sigue pendiente de implementación, como antes; esta comprobación verifica el envío, no el almacenamiento de producción.

La compilación inicial falló porque Vite registraba `resources/css/produccion-index.css`, un archivo inexistente y sin referencias en las vistas. Se retiró solo esa entrada de `vite.config.js`; después `npm run build` terminó correctamente. No se instalaron dependencias.

### Logout y protección backend

Se añadió `POST /logout`, nombre `logout`, dentro del grupo `auth`, usando el controller existente. `LoginController::logout()` ejecuta `Auth::logout()`, invalida la sesión, regenera el token CSRF y redirige a `route('login')`. El componente usa `route('logout')`, `method="POST"`, `@csrf` y `<button type="submit">`. GET `/logout` devuelve 405 y no cierra la sesión.

El formulario de logout se cierra dentro del componente antes del contenido. En Producción el formulario de producción aparece después, separado. Se comprobó tanto el HTML original como el DOM del navegador: no hay formularios anidados. Dashboard, Producción (GET/POST), Historial y logout siguen protegidos mediante `auth`; no se añadieron condiciones por rol.

### Todos los archivos cambiados

| Archivo | Cambio |
|---|---|
| `resources/views/components/sidebar.blade.php` | Menú reutilizado, texto, usuario con fallback, activo accesible y logout real |
| `resources/views/dashboard/index.blade.php` | Inclusión de `<x-sidebar />` |
| `resources/views/produccion/index.blade.php` | Inclusión y ID del formulario de producción |
| `resources/views/history/index.blade.php` | Inclusión de `<x-sidebar />` |
| `resources/css/sidebar.css` (nuevo) | Estilos mínimos compartidos del menú |
| `resources/css/dashboard.css` | Import del CSS compartido |
| `resources/css/produccion.css` | Import del CSS compartido |
| `resources/css/historial.css` | Import del CSS compartido |
| `resources/js/produccion.js` | Cancelar selecciona el formulario de producción por ID |
| `app/Http/Controllers/LoginController.php` | Método logout |
| `routes/web.php` | POST logout protegido |
| `vite.config.js` | Retirada de entrada a CSS inexistente |
| `tests/Feature/NavigationTest.php` (nuevo) | Cobertura de navegación, sesión, login y logout |
| `tests/Feature/ExampleTest.php` | Expectativa de redirección de la raíz al login |
| `mejora_asignado.md` | Resultados, decisiones y casillas comprobadas |

### Comprobaciones realizadas

- [x] `php artisan test`: 18 pruebas aprobadas, 181 aserciones.
- [x] Autenticado puede abrir las tres páginas y ve los tres enlaces y una única sección activa.
- [x] Invitados redirigidos al login; `auth` sigue presente en páginas y operaciones.
- [x] Logout elimina datos de sesión, cambia ID/token y vuelve inaccesibles las páginas protegidas.
- [x] Logout GET rechazado; POST sin token o con token incorrecto devuelve 419 al activar la comprobación CSRF real en tests, sin un origen aceptado que omita el fallback de token.
- [x] Login conserva fallback Dashboard, intended Producción/Historial y regeneración de sesión.
- [x] Navegador Chrome: nueve recorridos entre las tres páginas, incluyendo el enlace a la página actual; activo, foco visible y revisión visual de capturas.
- [x] Navegador Chrome: logout por POST desde cada página y nueve redirecciones posteriores al login al intentar acceder a destinos protegidos.
- [x] Navegador Chrome: formularios separados, Cancelar y envío de Guardar a POST `/produccion`, sin errores JavaScript.
- [x] `npm run build`, sintaxis PHP y Pint de los tests modificados/nuevos correctos.
- [x] `php artisan route:list` y variante `-v`: GET/POST login, GET dashboard, GET/POST produccion, GET history, POST logout; sin rutas ficticias.
- [x] `git status`, `git diff` y `git diff --check` revisados.

**Aislamiento y límites de las pruebas:** PHP de este entorno no tiene PDO SQLite disponible. Los tests usan `Usuario` y `Empleado` en memoria; en los casos de login se simula solo la recuperación de la cuenta y se ejecutan la validación real del hash, guard, controller y sesión. La verificación en navegador utilizó un servidor desechable con cuenta ficticia, sesiones `file`, middleware y endpoints reales; scripts, capturas y sesiones quedaron en `/tmp/panaderia-navigation-check`, fuera del repositorio. No se ejecutaron migraciones ni seeders ni se modificó la base de trabajo. Esto no certifica consultas/persistencia en MariaDB ni el driver de sesiones `database`.

### Pendiente

- [ ] Diseño visual definitivo del menú y su adaptación a pantallas pequeñas; decidir orientación, iconos y distribución en esa etapa.
- [ ] Revisar la CSS antigua de `.barra-lateral` y valorar un layout general cuando se aborde el diseño.
- [ ] Definir y aprobar una matriz de permisos para Administrador, Encargado y Operador antes de implementar autorización backend y visibilidad por rol.
- [ ] Validar autenticación y persistencia/expiración de sesiones con MariaDB y driver `database` en un entorno aislado equivalente.
- [ ] Completar las demás mejoras de autenticación identificadas en la auditoría; no se consideran terminadas por implementar el menú.
