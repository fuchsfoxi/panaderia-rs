# Panadería RS — Auditoría integral de seguridad

**Fecha de la auditoría:** 2 de noviembre de 2026.

**Checkout:** rama `master`, commit `b6cd736a04ab2bbf950e1fe0ae24a3c6fa392964`, con cambios locales sin commit en Producción, autenticación y pruebas. La revisión cubrió el árbol de trabajo observado, no solo el commit.
**Modalidad:** diagnóstico de solo lectura. La creación posterior de este informe fue autorizada por separado.

## I. Resumen ejecutivo

No se identificó una vulnerabilidad crítica actualmente explotable con la evidencia obtenida. **El despliegue definitivo no está aprobado todavía:** se observaron un servidor PHP local y un proceso Cloudflare Tunnel conectado a su origen, pero no se verificaron la política de acceso del túnel, las cookies recibidas por el navegador, las reglas efectivas del firewall ni el servidor definitivo.

Las fortalezas observadas incluyen separación de rutas mediante Gates, validación de producción en servidor, transacciones y bloqueos, una cuenta MariaDB restringida y sanitización central del reporte de excepciones SQL. `symfony/mime` está en la versión corregida para CVE-2026-45070. Las consultas actuales de avisos para los locks de Composer y NPM no devolvieron vulnerabilidades conocidas.

Los riesgos principales son la exposición mediante un servidor de desarrollo, la escucha de MariaDB en todas las interfaces con firewall efectivo no verificable durante esta revisión, fotografías servidas desde almacenamiento público y las limitaciones de las cuentas compartidas y del control de intentos.

**Límites:** revisión estática, metadatos del sistema, consulta de avisos y consultas MariaDB `READ ONLY` con `ROLLBACK`. No se hicieron ataques activos, pruebas HTTP con cuentas reales, migraciones, seeders ni pruebas con escrituras. Las pruebas HTTP y de persistencia de las correcciones recientes de Producción y login permanecen pendientes. No se certifican dispositivos físicos, despliegue definitivo, copias remotas o redes no inspeccionadas.

### Contexto de exposición y configuración observada

- Un proceso PHP escuchaba en `127.0.0.1:8000` desde el directorio `public` del proyecto; un proceso `cloudflared` apuntaba a ese origen. La audiencia y política Cloudflare Access no se comprobaron.
- PHP-FPM estaba inactivo; no se identificó Nginx activo. Esta observación describe el equipo revisado, no un servidor futuro.
- El entorno declara `APP_ENV=local` y `APP_DEBUG=true`, pero `config/app.php:43` fija expresamente `debug=false`. No se encontró caché de configuración; no se presentó el valor de `.env` como una filtración confirmada.
- La lectura de MariaDB identificó la cuenta dedicada de aplicación; las consultas de integridad encontraron 9 detalles de Pan, 2 de Torta y 2 de Bocadito, sin referencias ausentes, cantidades no positivas o discrepancias de familia en esos registros. No se encontraron cabeceras duplicadas por fecha/categoría/turno.

## II. Inventario de controles

| Control | Ubicación | Estado | Evidencia y límite |
|---|---|---|---|
| Login y limitación de intentos | `app/Http/Controllers/LoginController.php:30` | Implementado, pendiente de validación | Cinco fallos por identidad/IP durante 60 segundos; sesión regenerada al autenticar. No se repitió el recorrido HTTP. |
| Logout y estado activo | `LoginController.php:20`; `app/Http/Middleware/EnsureUsuarioActivo.php:12` | Implementado, pendiente de validación | Logout invalida sesión; cuenta inactiva se desconecta en la siguiente petición protegida. |
| Revocación por contraseña/desactivación | `app/Actions/Usuarios/AdministrarUsuarios.php:42`; `vendor/laravel/framework/src/Illuminate/Session/Middleware/AuthenticateSession.php:45` | Implementado, pendiente de validación | Con sesiones `database`, la desactivación elimina las del objetivo; `auth.session` compara la huella de contraseña. Sin nueva prueba de sesiones reales. |
| Roles y acceso administrativo | `routes/web.php:24`; `app/Providers/AppServiceProvider.php:22` | Implementado, pendiente de validación | Gates separados para operación y administración; falta repetir regresión HTTP directa. |
| CSRF | `vendor/laravel/framework/src/Illuminate/Foundation/Configuration/Middleware.php:486` | Implementado, pendiente de validación | El grupo web incluye protección CSRF y los formularios revisados incluyen token. No se enviaron peticiones de prueba. |
| Producto, categoría, turno y participantes | `app/Actions/Produccion/RegistrarProduccionPan.php:67`; `RegistrarProduccionPorCantidad.php:21` | Implementado, pendiente de validación | Revalidación y bloqueos transaccionales; integridad actual consultada sin cruces. |
| Borrado administrativo | `app/Actions/Produccion/EliminarRegistroProduccion.php:13`; `app/Http/Requests/EliminarRegistroProduccionRequest.php:9` | Implementado, pendiente de validación | Gate, confirmación, familia e ID seleccionan el detalle. No se ejecutó borrado. |
| Cookies | `config/session.php:172` | Implementado, pendiente de validación | `HttpOnly` y `SameSite=Lax` por defecto; `Secure` depende del HTTPS reconocido. Sin inspección de cookies del túnel. |
| Cabeceras y caché de páginas | `app/Http/Middleware/SecurityHeaders.php:11`; `PreventPageCaching.php:11` | Implementado, pendiente de validación | Protección de marcos, `nosniff`, política de referrer y `no-store` presentes en código; respuestas reales pendientes. |
| Cuenta MariaDB del runtime | `config/database.php:67` | Implementado y comprobado | Conexión de lectura identificó cuenta dedicada sin privilegios globales más allá de `USAGE`, sin privilegios de esquema ni delegación; permisos específicos sobre 24 tablas. No se inspeccionaron todas las cuentas administrativas. |
| Validación de imágenes | `app/Http/Requests/RegistrarProduccionTortaRequest.php:12` | Implementado, pendiente de validación | JPEG/PNG/WebP, extensión y máximo 2 MB; nombre generado. El archivo se almacena en disco público. |
| Depuración Laravel | `config/app.php:33` | Implementado y comprobado | El checkout fija `debug=false`, sin caché de configuración observada. La configuración web futura debe verificarse de nuevo. |
| Symfony MIME | `vendor/symfony/mime/Header/ParameterizedHeader.php:58` | Implementado y comprobado | Lock e instalado coinciden en 8.1.10 y el código valida nombres de parámetros. [Aviso oficial](https://symfony.com/blog/cve-2026-45070-email-header-injection-via-non-token-characters-in-mime-parameter-names). |
| Firewall efectivo y política del túnel | Sistema/Cloudflare | No verificable | UFW figura habilitado en configuración, pero `sudo` no interactivo requirió contraseña; no se inspeccionaron reglas activas ni Cloudflare Access. |
| MFA y atribución individual de cuentas compartidas | Login y modelo de usuarios | No implementado | No se encontró MFA ni identidad individual para quien utiliza una cuenta de turno. |
| Restauración vigente en el destino | Respaldos privados | No verificable | Hay evidencia documental de restauraciones anteriores; no se repitió una restauración ni se verificó una copia externa actual. |

## III. Hallazgos técnicos

### H01 — Servidor de desarrollo y túnel activos

**Gravedad: alta como condición de exposición; explotación no demostrada.** PHP escuchaba en loopback y `cloudflared` apuntaba a ese origen. Si el túnel permite acceso a terceros, la aplicación se expone mediante un servidor de desarrollo cuya configuración, aislamiento y operación no equivalen a un despliegue definitivo. No se comprobó la audiencia ni la política de acceso efectiva.

**Evidencia:** procesos y sockets observados; `public/index.php` es el punto de entrada del proyecto. PHP-FPM inactivo y Nginx no identificado como activo.

**Recomendación:** antes de una exposición pública definitiva, preparar servidor web y PHP-FPM con `public/` como raíz, identidad limitada, HTTPS y política de acceso definida. [Laravel: configuración del servidor](https://laravel.com/docs/13.x/deployment).

**Prueba posterior:** revisar configuración efectiva, identidad de procesos, certificado y acceso desde un cliente autorizado.

### H02 — MariaDB escucha en todas las interfaces

**Gravedad: alta si el puerto es accesible desde una red no confiable; accesibilidad externa no demostrada.** Los sockets mostraron escucha IPv4/IPv6 en 3306; MariaDB informó `skip_networking=OFF` y `require_secure_transport=OFF`. La conexión local de la auditoría no negoció TLS.

**Impacto y condición:** una conexión remota alcanzable podría permitir intentos de autenticación o exposición del tráfico según firewall, cuentas y red. La cuenta de aplicación observada está restringida a loopback. No se hizo sondeo remoto y las reglas activas no pudieron revalidarse.

**Recomendación:** comprobar firewall y topología. Limitar escucha si no se requiere acceso remoto; si se requiere, restringir origen y usar transporte autenticado y cifrado.

**Prueba posterior:** lectura administrativa autorizada de firewall/grants y conectividad controlada desde segmentos aprobados.

### H03 — Fotografías en almacenamiento público

**Gravedad: media; confidencialidad condicionada a la política de acceso.** `app/Actions/Produccion/RegistrarProduccionTorta.php:19` guarda fotos en el disco público; `resources/views/components/registro-produccion.blade.php:47` genera enlaces a `storage/`. El nombre es generado y la validación restringe formato/tamaño.

**Impacto y condición:** quien conozca una URL accesible puede solicitar la imagen sin pasar necesariamente por los Gates de Historial. La exposición efectiva depende del servidor y túnel; no se solicitó ninguna imagen real.

**Recomendación:** confirmar si las fotos son públicas por diseño. Si requieren privacidad, servirlas mediante autorización o enlaces temporales y evitar ejecución de scripts en directorios de carga.

**Prueba posterior:** con imágenes ficticias, comprobar acceso anónimo, acceso por rol, expiración y formatos rechazados.

### H04 — Límite de intentos por identidad e IP

**Gravedad: media.** `app/Http/Controllers/LoginController.php:53` aplica el límite por combinación de identidad e IP y consulta la identidad antes de comprobarlo. No se encontró un límite complementario global o por IP en las rutas revisadas.

**Impacto y condición:** intentos distribuidos entre identidades o direcciones pueden eludir ese contador específico y consumir recursos. Las cuentas de turno compartidas dificultan atribuir cada acceso a una persona. No se hicieron intentos de fuerza bruta y se desconoce la protección externa del túnel.

**Recomendación:** evaluar límite complementario por IP/red y protección externa; evaluar MFA o una capa adicional para Administrador, conservando mensajes genéricos y operación legítima.

**Prueba posterior:** ensayos limitados y autorizados con cuentas ficticias, NAT compartido, varias identidades y recuperación legítima.

### H05 — Credencial histórica en Git y copias

**Gravedad: media residual; vigencia actual no comprobada.** El historial alcanzable conserva una transcripción con términos de credenciales. No se encontraron archivos sensibles rastreados en el árbol actual entre los patrones examinados; `.gitignore:3–15` excluye variantes de entorno, respaldos y transcripciones.

La documentación posterior, `LARAVEL/Dependencias y seguridad.md`, registra la rotación de la credencial administrativa expuesta y el rechazo de la anterior el 30/10. No se volvió a probar ni comparar esa credencial con valores actuales. Las notas históricas que la describen como vigente fueron superadas por ese cierre.

**Impacto y condición:** clones y respaldos pueden conservar secretos; el riesgo depende de vigencia, reutilización y acceso a las copias. El análisis cubrió objetos alcanzables, no copias remotas ni todos los objetos inalcanzables.

**Recomendación:** mantener la revocación, gestionar coordinadamente historial y copias, e incorporar detección de secretos antes de publicar.

**Prueba posterior:** revisión autorizada de custodia y vigencia sin mostrar valores, e inventario de copias.

### H06 — Aislamiento de respaldos y registros

**Gravedad: media.** `.env` tenía permisos `0600`; `storage/app/private` y `storage/logs`, directorios `0700`. El proceso PHP actual y esas rutas pertenecen al mismo usuario de sistema.

**Impacto y condición:** si se compromete PHP o su cuenta de sistema, puede alcanzar recursos privados que ese usuario puede leer. No se demostró ejecución remota ni lectura indebida. El canal configurado es `stack/single`; no se acreditó una política completa de rotación y alertas. El log principal observado ocupaba aproximadamente 33 MB y el filesystem tenía espacio disponible.

**Recomendación:** separar la identidad web de la custodia administrativa y respaldos; definir retención, rotación, alertas y copias externas protegidas.

**Prueba posterior:** revisar permisos/ACL con identidades reales, ensayar restauración autorizada y comprobar alarmas sin exponer contenidos sensibles.

### H07 — Configuración PHP del runtime web

**Gravedad: media, condicionada a la configuración efectiva.** CLI y el binario FPM disponibles informaron PHP 8.5.12, que incluye las correcciones publicadas para CVE-2026-109618 y CVE-2026-109619. FPM estaba inactivo; no se comprobó mediante petición la configuración completa del worker del futuro despliegue.

En CLI, `session.upload_progress.enabled` está activado y `session.serialize_handler` es `php`. PHP describe para CVE-2026-115560 una combinación adicional específica que incluye cambiar el serializador en tiempo de ejecución antes de iniciar sesión. No se encontró ese cambio en el código propio revisado; **no se demostró que el proyecto cumpla las condiciones de explotación**. [Aviso oficial de PHP](https://www.php.net/archive/2026.php).

El lock de producción incluye 16 paquetes que requieren PHP `>=8.4.1`, aunque `composer.json` declara `^8.3`. La comprobación de plataforma del lock pasó con CLI 8.5.12.

**Recomendación:** verificar la versión e INI del runtime web efectivo y seguir la mitigación temporal oficial mientras corresponda.

**Prueba posterior:** inspección del worker real, revisión de configuración que modifique serialización y regresión autorizada tras actualizar.

### H08 — Defensa adicional contra XSS y cabeceras

**Gravedad: baja como endurecimiento pendiente; XSS no demostrado.** Las búsquedas estáticas no encontraron Blade sin escape `{!! ... !!}` ni `innerHTML` en los recursos revisados. Se observaron expresiones Blade escapadas y `textContent`; las consultas SQL crudas encontradas usan valores enlazados o expresiones fijas. No se confirmó inyección SQL.

`app/Http/Middleware/SecurityHeaders.php:15` define CSP para `frame-ancestors`, no una CSP completa contra scripts. HSTS y cabeceras del servidor definitivo no se verificaron.

**Impacto y condición:** una inyección no detectada tendría menos defensa adicional en el navegador. No se hicieron ataques activos de XSS.

**Recomendación:** diseñar una CSP compatible con los recursos existentes y verificar HSTS en el extremo HTTPS.

**Prueba posterior:** pruebas autorizadas con datos ficticios de nombres, observaciones y archivos; comprobar cabeceras y funcionamiento de interfaz.

## IV. Recomendaciones priorizadas

### P0 — Exposición pública

No hay una vulnerabilidad crítica o alta **demostrada como explotable desde Internet** en esta revisión. Antes de continuar exponiendo el sistema a una audiencia pública debe comprobarse quién alcanza el túnel y si MariaDB es accesible externamente. Si se demuestra acceso no autorizado, tratarlo como bloqueo inmediato.

### P1 — Antes del despliegue

1. Preparar y verificar servidor web/FPM, HTTPS, identidad de ejecución y política del túnel.
2. Verificar firewall efectivo, alcance de MariaDB y necesidad de TLS para la topología final.
3. Ejecutar regresión aislada de autenticación, roles, IDOR, CSRF, sesiones, producción e Historial.
4. Decidir confidencialidad de fotografías y separar custodia/respaldos del usuario PHP.
5. Verificar runtime PHP, cookies, límites de subida y mitigaciones aplicables.
6. Definir protección complementaria de login y acceso administrativo.

### P2 — Evolución posterior

- CSP más completa y monitoreo de cabeceras.
- Rotación y retención de logs con alertas.
- Detección preventiva de secretos y gestión coordinada de copias históricas.
- Mejorar atribución individual y reducir cuentas compartidas, sujeto a decisión funcional.
- Revisar consistencia histórica y duplicados periódicamente sin correcciones automáticas.

## V. Plan de correcciones y riesgos de regresión

1. **Definir el destino:** inventariar servidor, dominio, terminación TLS, acceso de usuarios y ubicación de MariaDB. De ello dependen las reglas de red y cookies.
2. **Preparar infraestructura aislada:** servidor web/FPM, usuario de sistema, permisos, respaldos y logging. Verificar que el proceso no pueda escribir código ni leer custodia administrativa.
3. **Cerrar exposición:** aplicar la política aprobada de túnel, firewall y MariaDB; comprobarla desde clientes autorizados.
4. **Ajustar aplicación:** protección complementaria de login, privacidad de fotos y cabeceras, conservando Gates y reglas actuales.
5. **Ejecutar regresión autorizada:** primero con datos ficticios en aislamiento; después aceptación no destructiva del destino.
6. **Validar recuperación:** restauración, monitoreo, retención de logs y respuesta a incidentes antes de declarar disponibilidad productiva.

Riesgos de regresión: bloquear usuarios legítimos por límites de login; romper fotos al cambiar almacenamiento; invalidar cookies por HTTPS mal configurado; afectar sesiones, caché o colas si se cambian grants sin comprobar su uso.

## VI. Evaluación final

**Protegido en el código revisado:** autorización por rol para rutas, validaciones de producción, transacciones, CSRF del grupo web, regeneración/invalidez de sesión, escape en plantillas revisadas, cuenta MariaDB restringida y parche Symfony MIME.

**Sin protección completa o con cobertura condicionada:** fotografías públicas por URL, MFA, atribución individual de cuentas compartidas, defensa adicional contra intentos distribuidos, CSP amplia, aislamiento de respaldos respecto del usuario PHP y política operativa de logs.

**Pendiente de comprobar:** firewall activo, acceso real al túnel, cookies y HTTPS extremo a extremo, runtime web definitivo, estado administrativo de otras cuentas MariaDB, restauración vigente y regresiones HTTP/persistencia de las correcciones recientes.

**Estado para producción: no aprobado todavía.** La conclusión se basa en verificaciones operativas pendientes y no afirma que exista una explotación crítica demostrada.

Composer audit consultó el lock sin avisos ni paquetes abandonados. La consulta al registro NPM para 123 paquetes tampoco devolvió avisos. Estos resultados reflejan las bases consultadas en la fecha de auditoría y no garantizan ausencia futura de vulnerabilidades. El código y la versión instalada de Symfony MIME 8.1.10 coinciden con la versión corregida documentada por el fabricante.

## Alcance documental y conservación

**CÓDIGO MODIFICADO:** ninguno para la auditoría o la entrega documental. Los cambios de aplicación y pruebas ya existían en el árbol de trabajo y se conservaron.

**DOCUMENTACIÓN MODIFICADA:** únicamente este informe en `docs/`, creado a solicitud expresa. No se modificaron notas del Vault ni se creó una copia de ellas: la autorización de esta entrega se limitó al informe solicitado.

**PRUEBAS:** consultas e inspecciones de solo lectura descritas arriba; no se ejecutaron pruebas destructivas, migraciones, seeders, ataques activos ni recorridos HTTP con cuentas reales. La creación del informe no repite la auditoría ni convierte resultados históricos en pruebas nuevas.

**PENDIENTES:** las verificaciones y decisiones de despliegue de las secciones IV–VI. Ninguna corrección de seguridad fue implementada o autorizada en esta entrega. Sin modificación de datos, credenciales, configuración, commit, push o despliegue.
