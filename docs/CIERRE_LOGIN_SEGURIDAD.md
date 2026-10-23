# Cierre Login y seguridad — Panadería RS

Fecha: 2026-10-16. Checkout auditado: HEAD `a0d7c00`, con el informe no versionado `docs/AUDITORIA_COMPLETA_PANADERIA_RS.md` ya presente y preservado.

Estado: **IMPLEMENTADO Y COMPROBADO en pruebas HTTP/DOM y MariaDB aislada**. Aceptación manual y rotación de la credencial administradora existente: **PENDIENTES**. Sin commit ni push.

## Problemas encontrados

- El backend devolvía errores, pero Blade no consumía `$errors` ni `old('username')`.
- Validación limitada a `required`, sin tipos ni máximos. Un password enviado como array producía un error 500 en `password_verify()`; reproducido antes de corregir.
- No había RateLimiter registrado ni protección de intentos en POST /login.
- UsuarioSeeder incluía una contraseña literal; no se reproduce su valor en este informe.
- Se mostraba recuperación de contraseña sin implementación y una marca distinta.

## Estado anterior

Se revisaron routes/web.php, LoginController, Usuario, config/auth.php, config/session.php, bootstrap/app.php, Blade/CSS/JS del login, UsuarioSeeder, esquema y tests existentes antes de modificar la implementación.

Autenticación efectiva: guard `web` de sesión, provider `users` Eloquent y modelo `App\Models\Usuario`; tabla `usuarios_sistema`, username VARCHAR(50) único y password_hash VARCHAR(255). `getAuthPassword()` lee password_hash. `getAuthPasswordName()` **ya existía** y devolvía password_hash; también existía un test de rehash real. Ambos se conservan y se comprobaron nuevamente.

GET/POST login ya usaban guest; rutas internas auth y Gates Administrador/Operador; logout era POST. Auth::attempt, regeneración de sesión, invalidate y regenerateToken ya funcionaban. El middleware nativo `throttle` está disponible como alias de ThrottleRequests, pero ninguna protección de login lo utilizaba. El `throttle` de config/auth.php pertenece al broker de recuperación, no limita POST /login.

UsuarioSeeder ya preservaba una cuenta admin existente y resolvía rol/empleado mediante `sole()`. Se conserva esa identidad y conducta, sustituyendo únicamente el origen inseguro de la contraseña inicial. No se consultaron ni modificaron cuentas reales para esta tarea; la coincidencia de la cuenta local con la contraseña antigua proviene de la auditoría aportada por el usuario.

## Cambios implementados

Validación nativa con mensajes en español, feedback escapado en Blade, conservación exclusiva de username válido, RateLimiter nativo y aprovisionamiento explícito. Se retira el botón de recuperación inexistente y se unifica la marca. No se agregan paquetes, Requests, Services, rutas, tablas ni migraciones. No cambian Gates, rutas de negocio, controladores de otros módulos ni login.js.

## Feedback de errores

Blade renderiza todos los mensajes de `$errors` en un bloque visible con `role="alert"`, lista, contraste oscuro sobre fondo claro y ajuste de texto. `@error` asocia los campos al bloque mediante aria-describedby/aria-invalid; el borde señala el campo rechazado.

Cuenta inexistente y contraseña incorrecta devuelven exactamente: «Las credenciales ingresadas no son correctas.». No se revela si una cuenta existe. También se muestran campos requeridos, tipos, máximos y espera por intentos. Los mensajes nunca interpolan la contraseña.

Después del fallo se usa `old('username')`, con comprobación de tipo y escape Blade. Password no tiene value ni old. En validación HTML solo se guarda username si es string de hasta 50 caracteres; arrays, valores excesivos, contraseña y campos adicionales no se guardan como old input. Ante validación JSON se conserva la respuesta nativa 422 con errores por campo.

## Validaciones

| Campo | Reglas |
| --- | --- |
| username | bail, required, string, max:50, coherente con el esquema |
| password | bail, required, string, max:1024 |

El límite de 1024 caracteres acota la entrada de autenticación; no es una regla de creación ni el tamaño del hash. No hay confirmed, regex ni requisitos de fortaleza en login. Una contraseña corta ya existente continúa autenticando, comprobado con una cuenta ficticia. TrimStrings nativo limpia espacios de username y excluye password.

## Rate limiting

LoginController usa la fachada RateLimiter: admite cinco fallos por combinación de username normalizado e IP en una ventana de 60 segundos desde el primer fallo. La clave lleva prefijo login y SHA-256 de username normalizado + separador + IP; no contiene contraseña. Se normaliza con minúsculas y transliteración para agrupar variantes de mayúsculas/acentos. La búsqueda de Auth conserva el username recibido tras el middleware nativo.

Antes del sexto intento se comprueba tooManyAttempts; el rechazo HTML redirige al formulario con username y «Demasiados intentos. Inténtalo de nuevo en N segundos.». N se obtiene de availableIn. No se consulta la credencial mientras el par esté limitado, aunque la contraseña sea correcta. Al vencer la ventana puede intentarse de nuevo. Los fallos de tipos/requeridos se rechazan antes de Auth y no consumen intentos de credenciales.

Tras Auth::attempt exitoso, clear limpia solamente ese par. Otra cuenta bajo la misma IP y el mismo username desde otra IP tienen contadores separados. No se añade otro throttle sobre la ruta para evitar un segundo contador con un comportamiento distinto. Se comprobó el almacén array y también el almacén database persistente en MariaDB desechable.

## Seguridad de credenciales

`.env.example` incorpora exclusivamente `INITIAL_ADMIN_PASSWORD=` sin valor. `config/auth.php` lo expone internamente como auth.initial_admin_password, compatible con la configuración cacheada de Laravel. No hay fallback literal, impresión de la contraseña ni logging del valor.

Esta variable debe proporcionarse mediante el entorno privado únicamente cuando se vaya a crear admin por primera vez, con una contraseña única y aleatoria de al menos 16 caracteres y hasta 72 bytes. El máximo evita truncamiento bcrypt; el mínimo se aplica solo al aprovisionamiento. Nunca versionar el valor real, incorporarlo a comandos compartidos o incluirlo en documentación/logs. Después de crear la cuenta puede retirarse del entorno; si se usa caché de configuración, debe regenerarse al cambiarlo. No se modificó el .env real ni se ejecutó limpieza de configuración local.

**La contraseña existente no fue rotada.** Eliminar el literal del código no corrige automáticamente una cuenta ya creada con él. Su rotación explícita sigue pendiente y debe realizarse antes de exponer el acceso a un entorno compartido.

## UsuarioSeeder

1. Si admin existe, retorna sin alterar contraseña, rol o empleado, incluso si la variable falta o cambió.
2. Si no existe, exige configuración string con longitud válida; de lo contrario lanza RuntimeException informativa sin incluir el valor ni crear la cuenta.
3. Resuelve el rol Administrador y el empleado inicial por las identidades existentes mediante sole(). Configuración válida no evita el rechazo de relaciones ambiguas o ausentes.
4. Guarda únicamente Hash::make de la contraseña configurada.

Repetir el seeder conserva ID y todos los atributos existentes. Se probó con una segunda contraseña configurada y después sin configuración: no cambia el hash. Las pruebas usan datos ficticios/contraseñas aleatorias. El aprovisionamiento presupone los catálogos y el empleado inicial existentes; no crea operadores ni un CRUD de usuarios. La idempotencia probada es secuencial; no se certifica ejecución simultánea de seeders.

## Sesiones

Se conservan regenerate tras login, intended, Auth::logout, invalidate y regenerateToken. Los tests comprueban cambio de identificador, eliminación de datos privados, nuevo token y rechazo de rutas tras logout. Se fuerza la comprobación real de CSRF en tests de POST login; navegación ya cubre logout con token ausente/incorrecto/válido y GET logout 405.

Configuración efectiva inspeccionada mediante una lista limitada de opciones no sensibles: guard web, sesiones database, caché database, HttpOnly true, SameSite lax, Secure null y configuración sin caché. No se imprimieron valores de cookies ni credenciales de configuración.

SESSION_SECURE_COOKIE permanece dependiente del entorno; no se fuerza true en desarrollo HTTP. En producción debe usarse HTTPS y SESSION_SECURE_COOKIE=true, con proxies confiables correctamente configurados. La regresión conserva HTTP local y HTTPS mediante el proxy loopback ya existente. No se modificó ni certificó un servidor de producción.

## Branding

Título y encabezado usan «Panadería RS», coherente con el logo y el resto de la aplicación. Se mantienen video, logo SVG, fuentes, tarjeta, responsive y botón de mostrar contraseña. Se elimina el botón y CSS de «¿Olvidaste tu contraseña?»; no se implementa recuperación ni una acción alternativa.

## Archivos modificados

| Ruta | Motivo |
| --- | --- |
| app/Http/Controllers/LoginController.php | Validación, old input restringido y RateLimiter |
| config/auth.php | Configuración inicial y uso del import correcto de Usuario |
| database/seeders/UsuarioSeeder.php | Eliminar credencial literal y preservar cuentas existentes |
| .env.example | Variable de aprovisionamiento vacía |
| resources/views/auth/login.blade.php | Errores, old username, ARIA, límites, marca y retiro de recuperación |
| resources/css/login.css | Feedback con contraste, borde de error y retiro de estilo sin consumidor |
| tests/Feature/LoginSecurityTest.php | 25 casos nuevos de autenticación, feedback, validación, limitación, CSRF y plantilla de entorno |
| tests/Feature/AutorizacionSeedersTest.php | 7 casos nuevos de configuración insegura y ampliación de idempotencia/preservación |
| docs/CIERRE_LOGIN_SEGURIDAD.md | Informe solicitado de este cierre |

La documentación original se actualiza en sus notas existentes del Vault, conservando historia: LARAVEL/Controllers.md, LARAVEL/Blade.md, PERSONAL Y USUARIOS/Usuarios.md, PERSONAL Y USUARIOS/Pendientes.md, PENDIENTES/Bugs.md, DECISIONES/Decisiones Laravel.md, INICIO/Estado de proyecto.md e INICIO/Roadmap.md. Se comprobó el nombre real Decisiones Laravel.md; no existe una variante .md.md en este Vault. mejora_asignado.md no se encontró en checkout/Vault y las notas existentes ya registraban su ausencia; no se recrea.

## Tests realizados

Regresión relevante:

```bash
PAO_DISABLE=1 php tests/run-mariadb.php --compact --filter='LoginSecurityTest|NavigationTest|ProxyHttpsTest|AutorizacionRolesTest|AutorizacionSeedersTest|rehash del login'
```

Incluye GET guest, rechazo GET/POST para autenticados, credenciales válidas/incorrectas/cuenta inexistente, errores realmente presentes en el siguiente HTML, old username/password vacío, campos vacíos, username de 50/51 caracteres y array, password array/entero/booleano/excesivo, cinco fallos/sexto bloqueo, espera/expiración, normalización, separación por usuario/IP, limpieza selectiva tras éxito, logout, CSRF real, rutas protegidas, permisos actuales y rehash real. El test de .env.example exige valores vacíos/null en variables de secretos y la nueva variable vacía. El seeder se ejecuta y verifica en BD ficticia, no mediante mocks.

Después se ejecutaron:

```bash
PAO_DISABLE=1 php tests/run-mariadb.php --compact
npm run build
vendor/bin/pint --test app/Http/Controllers/LoginController.php config/auth.php database/seeders/UsuarioSeeder.php tests/Feature/AutorizacionSeedersTest.php tests/Feature/LoginSecurityTest.php
git diff --check
```

PAO_DISABLE desactiva únicamente el formato de salida del asistente de tests. No altera assertions ni omite casos. No se ejecutó Pint en modo escritura ni sobre el proyecto completo.

## Resultado de pruebas

| Verificación final | Resultado |
| --- | --- |
| Regresión relevante | **142 aprobadas / 0 fallidas, 1.457 aserciones** |
| Suite PHP completa | **653 aprobadas / 0 fallidas, 5.978 aserciones** |
| Casos omitidos finales | **0** |
| npm run build | **APROBADO**, Vite 8.3.0 |
| Pint de cinco archivos PHP modificados | **APROBADO** |
| git diff --check | **APROBADO** |

Antes de la corrección se ejecutaron casos nuevos de feedback, validación, marca y limitación que fallaron y reprodujeron el payload array con 500. El primer intento de MariaDB quedó bloqueado por el socket del sandbox; se repitió con escalamiento, usando exclusivamente el servidor temporal. En la primera regresión posterior hubo 131 aprobadas y 11 fallidas: el cliente de prueba no transportaba la cookie al siguiente GET y las assertions leían el error ya serializado como JSON. Se corrigió el transporte explícito de la cookie y la lectura mediante los helpers de test; no se cambió el serializador del proyecto. La repetición final dio los 142/0 indicados. Pint señaló la referencia plenamente cualificada preexistente del modelo en config/auth.php; se ajustó su import dentro del archivo modificado y pasó la repetición.

Build emitió el aviso opcional de fontaine ya conocido; terminó con exit code 0. No se instalaron paquetes para resolver ese aviso.

Aislamiento: MariaDB desechable sin red, dos bases ficticias bajo /tmp/sprint4-*, comprobación de datadir/base antes de migrar, transacciones de fixtures y sesiones array. HTTP con withoutVite y DOM real; assets compilados por separado. La caché database se probó en la base desechable, no en la base de trabajo. Instancias detenidas al terminar. Evidencia de la regresión en /tmp/login-relevant-tests.log y de la suite completa en /tmp/login-full-tests.log; servidor final /tmp/sprint4-ffac9cb51107. No se ejecutaron seeders/migraciones sobre la BD operativa.

## Riesgos restantes

- Cuenta administradora existente: preservada por requisito; la credencial antigua puede seguir activa hasta su rotación explícita. No se volvió a comprobar contra datos reales.
- La limitación es por usuario/IP, no un bloqueo global de cuenta. Requiere conservar un almacén de caché persistente y compartido si hay varias instancias; el default actual es database.
- Las verificaciones HTTP usaron sesiones array: no certifican persistencia de sesiones database, navegador/dispositivo real ni un entorno publicado.
- Fuentes/iconos externos y video conservan las dependencias existentes; no se ha hecho una auditoría visual de navegador en esta tarea.

## Pendientes

- Rotar explícitamente la credencial administradora conocida; no cambiarla silenciosamente mediante el seeder.
- Aceptación manual del login en escritorio/móvil y comprobación del entorno real con sesiones database.
- HTTPS/cookie segura al configurar producción, fuera de esta tarea.
- Recuperación de contraseña, MFA, gestión de usuarios y cuentas operativas siguen fuera del alcance; la interfaz ya no promete recuperación disponible.
- Formato global preexistente: no revisado ni corregido. No se declara aprobado Pint global.

## Pruebas manuales recomendadas

1. Con assets compilados y sin public/hot residual, abrir /login como invitado en escritorio y móvil. Comprobar marca, logo, campos y ausencia de recuperación.
2. Introducir un usuario existente con contraseña incorrecta: confirmar mensaje genérico visible, username conservado y password vacío. Repetir con usuario inexistente; el mensaje debe ser idéntico.
3. Enviar campos vacíos. El navegador debe indicar required. Para comprobar feedback del servidor, omitir temporalmente la validación HTML desde herramientas de desarrollo: mensajes en español y ningún password recuperado.
4. Realizar cinco intentos incorrectos con el mismo username/IP; el siguiente debe mostrar segundos de espera. Una contraseña válida también espera mientras ese par esté limitado. Tras la espera, autenticar correctamente.
5. En una nueva ventana de intentos, fallar varias veces y luego autenticar con éxito. Cerrar sesión y comprobar que se permiten cinco fallos nuevos antes del bloqueo.
6. Comprobar que otra cuenta de prueba en la misma IP no queda bloqueada por los fallos anteriores; no crear cuentas operativas para esta verificación.
7. Ya autenticado, abrir /login: debe redirigir al destino actual. Cerrar sesión, usar Atrás/recargar y abrir una URL protegida: debe exigir login. Repetir con el driver database del entorno real.
8. Con cuentas de prueba ya disponibles, confirmar los permisos actuales de Administrador/Operador, sin modificar datos de negocio.
9. Si se revisa el aprovisionamiento manual, hacerlo únicamente en una BD desechable con sus catálogos: sin variable no crea admin; con credencial aleatoria válida lo crea; repetir con otra configuración o sin ella conserva su credencial. No ejecutar este ensayo sobre cuentas reales.
