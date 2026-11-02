# Panadería RS — Revisión técnica de HTTP, HTTPS y preparación del entorno de ensayo

**Fecha de la revisión:** 31 de octubre de 2026.

**Zona horaria del proyecto:** America/Lima.

**Objetivo de planificación:** preparar un ensayo y evaluar el futuro despliegue antes del 7 de noviembre de 2026.

**Carácter de este documento:** informe detallado de las lecturas y del análisis realizados; no es un registro de una instalación o un despliegue ejecutados.

## 1. Qué se hizo y cuál es el resultado

Se revisó el estado del proyecto y del equipo disponible para determinar si existe una base técnica con la que preparar un entorno de ensayo. Se consultaron versiones, extensiones, dependencias bloqueadas, recursos compilados, configuración de Laravel, servicios, puertos, permisos de archivos, variables de MariaDB y reglas activas del firewall. También se contrastaron los requisitos de despliegue y HTTPS con documentación oficial.

**Resultado:** el código y los runtimes disponibles proporcionan una base compatible para un ensayo independiente, pero todavía no hay un servicio HTTP/HTTPS de Panadería RS preparado en este equipo. Faltan la elección del destino, el servidor web, la configuración de FPM para la aplicación, la identidad de ejecución, la ubicación y permisos del despliegue, el nombre de acceso y la estrategia de certificados.

La revisión del entorno fue **exclusivamente de lectura**. No se instaló software, no se iniciaron ni detuvieron servicios, no se modificaron reglas de red, no se ejecutaron migraciones o seeders y no se realizaron escrituras de negocio. Las etapas A, B, C y D de MariaDB no se repitieron.

Posteriormente, el propietario solicitó expresamente crear este archivo `docs/http.md`. Esa solicitud autoriza guardar este informe; no autoriza ejecutar las propuestas de implementación que aparecen más adelante.

El servidor local **no se considera elegido como servidor definitivo**. Los resultados del inventario corresponden únicamente al equipo examinado. No certifican un VPS, otro ordenador, una máquina virtual, un contenedor o un servicio administrado que todavía no se han seleccionado.

## 2. Cómo deben interpretarse los estados

| Estado | Significado en este informe |
|---|---|
| **IMPLEMENTADO Y COMPROBADO** | Existe evidencia concreta de la implementación y de la comprobación descritas, con sus límites. |
| **PENDIENTE** | Falta una decisión, autorización, implementación o verificación. No implica automáticamente un fallo de código. |
| **EN INVESTIGACIÓN** | Hay un reporte o una hipótesis cuya causa o resolución no se ha acreditado. |
| **DESCARTADO** | La evidencia permite descartar una hipótesis dentro del alcance indicado; no significa seguridad universal. |

Encontrar un paquete instalado no demuestra que su servicio esté activo. Encontrar un servicio activo no demuestra que exista una configuración correcta para Laravel. Encontrar un puerto en escucha no demuestra por sí mismo que sea accesible desde Internet. Encontrar una configuración en disco tampoco sustituye comprobar el comportamiento del proceso que atenderá las peticiones.

## 3. Estado inicial del proyecto y conservación de archivos

Se leyó `AGENTS.md` antes de continuar. Se revisaron el estado de Git, la rama, el último commit y los archivos existentes en `docs/`.

| Elemento | Resultado observado |
|---|---|
| Rama | `master` |
| HEAD | `0a0ddf8b1ff0cda88ab42ede88a85377b0c9b99d` |
| Fecha del commit | 31/10/2026, 16:53:54, America/Lima |
| Asunto del commit | `fix: corregir CVE-2026-45070  en symfony/mime` |
| Cambio local previo | `docs/AUDITORIA_PREPARACION_PRODUCCION_2026-10-30.md`, sin seguimiento |
| Cambios preparados en staging | Ninguno |
| Cambios pendientes en `composer.lock` | Ninguno: el parche ya figura en HEAD |

Estado al iniciar la revisión del entorno y al iniciar esta tarea documental:

```text
?? docs/AUDITORIA_PREPARACION_PRODUCCION_2026-10-30.md
```

El informe histórico del 30/10 se conservó sin editar. Se contrastó su contenido con la huella previamente conocida y coincidió. También se compararon los archivos originales con el manifiesto de la regresión aislada anterior: no se encontraron diferencias de contenido en los archivos incluidos en ese manifiesto.

Este informe no sustituye ni corrige retroactivamente la auditoría del 30/10. Aquella documenta un momento anterior a la finalización de la transición de credenciales y del parche MIME. Sus pendientes históricos no deben interpretarse como reapertura de trabajos ya comprobados después.

No se creó ningún commit ni se hizo push durante la revisión o al guardar este documento.

## 4. Método de trabajo y límites de acceso

### 4.1 Lecturas del proyecto

Se consultaron los manifiestos de dependencias, la configuración y los puntos relevantes del código. Entre los archivos examinados estuvieron:

- `AGENTS.md`.
- `composer.json` y `composer.lock`.
- `package.json` y metadatos de paquetes instalados de Vite y su plugin Laravel.
- `config/app.php`, `config/session.php`, `config/boost.php` y `config/filesystems.php`.
- Configuración de base de datos, caché, colas y logging, mediante campos seleccionados.
- `bootstrap/app.php` y `routes/console.php`.
- Reglas de validación de fotografías de Torta.
- `public/build/manifest.json`, los archivos referenciados y el enlace `public/storage`.
- El informe de auditoría histórica y el resumen de la regresión anterior.

No se ejecutaron herramientas de formateo, compilación o regeneración. No se publicaron assets ni se ejecutaron comandos de limpieza de caché.

### 4.2 Lecturas del sistema

Se consultaron versiones de binarios, módulos PHP, paquetes instalados, estados de systemd, sockets en escucha, permisos y ACL. Se leyeron campos concretos de la configuración de FPM y de MariaDB.

Las salidas extensas o potencialmente sensibles se capturaron en memoria y se filtraron. No se publicó un `phpinfo()` completo, el contenido de `.env`, variables de entorno completas, contraseñas, tokens, claves ni hashes de autenticación.

### 4.3 Bloqueos del sandbox y escalamiento

Los primeros intentos de consultar systemd y sockets encontraron bloqueos del aislamiento: no se podía acceder al bus del sistema o abrir el socket netlink. La conexión TCP local de la lectura de MariaDB también fue bloqueada inicialmente.

Se repitieron las lecturas necesarias con el escalamiento correspondiente. Para las reglas activas del firewall se utilizó lectura administrativa mediante PolicyKit. Esto permitió consultar las reglas cargadas; **no se añadieron, quitaron ni modificaron reglas**.

El primer intento de lectura de MariaDB terminó con SQLSTATE `HY000` y código `2002`. Se omitió el mensaje completo de la excepción. La repetición fuera del sandbox conectó correctamente con la identidad dedicada. Por tanto, aquel intento no se interpretó como fallo de credenciales o avería del servidor.

Estos permisos técnicos de lectura no autorizan instalar software, cambiar configuración, crear usuarios o publicar el sistema.

## 5. PHP CLI, PHP-FPM y extensiones

### 5.1 Versiones e INI

| Componente | Resultado |
|---|---|
| PHP CLI | **8.5.12**, NTS |
| PHP-FPM | **8.5.12**, SAPI `fpm-fcgi` |
| Binarios | `/usr/bin/php` y `/usr/bin/php-fpm` |
| INI principal de ambos | `/etc/php/php.ini` |
| Directorio de INI adicionales | `/etc/php/conf.d` |
| INI adicionales informados por CLI | Ninguno |

Se compararon módulos y opciones relevantes de los dos binarios. Ambos disponen de las extensiones necesarias para los requisitos revisados. Entre las relevantes están `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `hash`, `mbstring`, `openssl`, `PDO`, `pdo_mysql`, `session`, `tokenizer` y XML. También están disponibles, entre otras, `bcmath`, `gd`, `intl`, `zip`, `zlib` y Zend OPcache.

**Límite:** consultar el binario FPM y sus archivos de configuración no equivale a probar una petición atendida por un worker. FPM estaba inactivo. La verificación de la SAPI que servirá HTTP sigue pendiente en el entorno que se prepare.

### 5.2 Compatibilidad real del lock

Se ejecutó:

```bash
composer check-platform-reqs --lock
```

El resultado fue satisfactorio con el PHP CLI instalado: no se reportaron requisitos de plataforma incumplidos.

`composer.json` declara PHP `^8.3`, y Laravel 13.38.0 admite esa rama. Sin embargo, el conjunto concreto de dependencias de ejecución del lock requiere **PHP >=8.4.1**: se localizaron 16 paquetes con esa restricción exacta. `symfony/mime 8.1.10` es uno de ellos.

Consecuencias:

1. No se debe contratar o preparar un destino con PHP 8.3 para instalar este lock sin cambios.
2. PHP 8.5.12 satisface el conjunto comprobado.
3. La coincidencia de versión y extensiones entre los binarios CLI y FPM proporciona una base compatible, pero falta la prueba del servicio web efectivo.
4. La diferencia entre el mínimo declarado y el mínimo real es un pendiente de requisitos/documentación del proyecto; no se modificó el manifiesto en esta tarea.
5. No se empleó `--ignore-platform-reqs` ni se alteraron las verificaciones de Composer.

### 5.3 Configuración relevante de seguridad y recursos

| Directiva | Valor observado en la configuración base | Interpretación |
|---|---|---|
| `display_errors` | Off | Evita la salida directa de errores PHP por esta configuración. |
| `log_errors` | On | El destino y la operación del logging deben comprobarse en el servicio final. |
| `allow_url_include` | Off | No se habilita inclusión remota mediante esta directiva. |
| `expose_php` | On | Conviene revisar la divulgación del banner al preparar el servicio. |
| `session.serialize_handler` | `php` | No se observó la configuración inicial `php_binary`/`php_serialize` del caso especial descrito por PHP. |
| `session.upload_progress.enabled` | On | Mitigación temporal recomendada por PHP todavía pendiente. |
| `file_uploads` | On | Compatible con la carga de fotografías. |
| `upload_max_filesize` | 2M | Debe armonizarse con los límites del servidor web y la aplicación. |
| `post_max_size` | 8M | Incluye el cuerpo multipart completo, no solo la foto. |
| `max_file_uploads` | 20 | No implica que el proyecto necesite aceptar veinte fotos por petición. |
| `memory_limit` | 128M | Requiere medir consumo y concurrencia del servicio real. |
| `opcache.enable` | On | Disponible; no se midieron rendimiento o invalidación en workers activos. |
| `opcache.enable_cli` | On | También activado para CLI. |
| `cgi.fix_pathinfo` en FPM | On | Debe revisarse junto con la configuración que limite ejecución al front controller. |

No se interpretó la ausencia de valores en `disable_functions` u `open_basedir` como una vulnerabilidad demostrada. Esas opciones no sustituyen una identidad de sistema correcta, aislamiento y permisos mínimos.

### 5.4 Aviso de PHP sobre upload progress

Se consultó el aviso oficial del 23 de octubre de 2026. PHP recomienda configurar temporalmente `session.upload_progress.enabled=0` mientras llega la siguiente corrección regular, como protección adicional frente al caso identificado como CVE-2026-115560.

La condición descrita exige una combinación específica: configurar el serializador nativo como `php_binary` o `php_serialize`, cambiarlo a `php` en ejecución mediante `ini_set()` y llamar a `session_start()` manteniendo habilitado upload progress.

En la configuración revisada el serializador base es `php`. Además, una búsqueda en el código propio no encontró el cambio de serializador ni el recorrido nativo descrito. Esto permite clasificarlo como **riesgo condicionado / configuración a tratar**, no como RCE reproducida en Panadería RS. No se ejecutó un payload ni una prueba ofensiva.

La mitigación, si se autoriza, debe quedar aplicada antes de procesar la petición, en la configuración pertinente de PHP/FPM. No debe presentarse como resuelta por un `ini_set()` tardío dentro de Laravel: PHP procesa el seguimiento de carga antes de ejecutar el script.

Referencias oficiales: [aviso de PHP del 23/10](https://www.php.net/archive/2026.php#2026-10-23-1) y [funcionamiento de Session Upload Progress](https://www.php.net/manual/en/session.upload-progress.php).

## 6. Composer, Laravel, Node.js y recursos compilados

| Elemento | Versión o resultado observado |
|---|---|
| Composer | 2.11.1 |
| Laravel | 13.38.0 |
| Symfony Mime en el lock | 8.1.10 |
| Node.js | 26.12.1 |
| npm | 12.2.1 |
| Vite instalado | 8.3.5 |
| Laravel Vite Plugin instalado | 3.2.0 |
| `@tailwindcss/vite` instalado | 4.3.3 |

Vite y el plugin Laravel declaran Node `^20.19.0 || >=22.12.0`; la versión instalada satisface esos rangos. No se actualizaron paquetes para obtener estos resultados.

La lectura del manifiesto compilado encontró **26 entradas y 26 archivos referenciados**, sin faltantes. Las entradas fuente revisadas también existen y `public/hot` está ausente. Esto evita que, en el estado observado, Laravel priorice un servidor Vite de desarrollo mediante ese archivo.

**Qué se comprobó:** coherencia estructural del manifiesto y existencia de sus recursos.

**Qué no se comprobó en esta revisión:** una compilación nueva, la equivalencia de cada bundle con su fuente actual, el comportamiento visual en navegador o la descarga desde otro equipo.

No se ejecutaron `npm install`, `npm run build`, `npm update`, `composer install` o `composer update` durante la revisión del entorno.

## 7. Servicios instalados, activos y puertos

### 7.1 Inventario relevante

| Servicio o paquete | Disponibilidad observada | Estado de servicio |
|---|---|---|
| MariaDB | Instalado, paquete 13.0.2 | Activo y habilitado |
| PHP-FPM | Instalado, paquete 8.5.12 | Inactivo y deshabilitado |
| Nginx | No detectado en PATH ni en los paquetes consultados | Unidad no encontrada |
| Apache | No detectado en PATH ni en los paquetes consultados | Unidad no encontrada |
| Caddy | Paquete no encontrado | No se acreditó un servicio configurado |
| Certbot | Paquete no encontrado | No se configuró un mecanismo de emisión/renovación |
| UFW | Disponible | Activo y habilitado |
| Firewalld | Sin unidad disponible | No activo |
| Cloudflared | Paquete 2026.10.2 instalado | Servicio inactivo; unidad de arranque no encontrada |
| Docker | Paquete 29.9.2 instalado | Servicio y socket inactivos/deshabilitados |
| Podman | Paquete no encontrado | No se acreditó su disponibilidad |

No detectar Nginx/Apache mediante PATH y el gestor de paquetes no equivale a inspeccionar todos los posibles binarios o imágenes de contenedor del disco. Sí establece que no se dispone de una instalación estándar detectada y preparada para este proyecto.

### 7.2 Puertos observados

MariaDB escucha en `0.0.0.0:3306` y `[::]:3306`; también existe su socket Unix en `/run/mysqld/mysqld.sock`.

No se observaron listeners de un servidor web en 80/443. Había puertos de loopback usados por herramientas del escritorio/desarrollo y servicios de resolución o descubrimiento de red. Esos puertos no se identificaron como una instalación HTTP de Panadería RS.

No se realizó un sondeo desde otro ordenador ni una prueba desde Internet. No se iniciaron `php artisan serve`, Vite, Nginx, Apache, FPM o Cloudflare Tunnel para completar el inventario.

## 8. Configuración de PHP-FPM y problema de permisos

### 8.1 Pool encontrado

Se leyeron campos seleccionados de `/etc/php/php-fpm.conf` y `/etc/php/php-fpm.d/www.conf`:

| Parámetro | Valor observado |
|---|---|
| Pool | `www` |
| Usuario / grupo | `http` / `http` |
| UID / GID de `http` | 33 / 33 |
| Socket previsto | `/run/php-fpm/php-fpm.sock` |
| Propietario/grupo del socket | `http` / `http` |
| Gestor de procesos | `dynamic` |
| `pm.max_children` | 5 |
| `pm.start_servers` | 2 |
| Mínimo / máximo de workers libres | 1 / 3 |
| Error log global de FPM | `syslog` |
| `ProtectHome` de la unidad | `no` |

No se publicaron variables `env[]` ni configuraciones con posibles secretos. El servicio permaneció inactivo; no se validó el socket mediante una petición FastCGI.

### 8.2 Permisos observados

| Ruta | Modo | Observación |
|---|---|---|
| `/home/lukagfv` | `0700` | El usuario `http` no tiene permiso de recorrido por esta ruta. |
| Directorio del proyecto | `0755`, propietario del usuario del proyecto | No resuelve el bloqueo del directorio personal superior. |
| `public` | `0755` | Legible para otros si toda la ruta superior fuese accesible. |
| `public/index.php` | `0644` | No basta para servirlo si un directorio superior impide acceso. |
| `.env` | `0600`, propietario del usuario del proyecto | No es legible por el pool `http` con estos permisos. |
| `storage/logs` | `0700` | El pool actual no puede escribir aquí. |
| `storage/app/private` | `0700` | Mantiene protección local; no es accesible por ese pool. |
| `storage/app/public` | `0755`, propietario del usuario del proyecto | El pool actual no tiene escritura de directorio. |
| `bootstrap/cache` | `0755`, propietario del usuario del proyecto | El pool actual no tiene escritura de directorio. |

Las ACL consultadas no mostraron concesiones adicionales que resolvieran estos impedimentos. El modo `0777` mostrado por `lstat` para `public/storage` corresponde al enlace simbólico y no significa que su destino tenga permisos generales de escritura.

**Conclusión:** activar el pool tal como está no deja este checkout listo para Laravel. La ubicación bajo el directorio personal, la lectura de configuración y las escrituras necesarias no son compatibles con esa identidad.

La propuesta es preparar una ubicación independiente de despliegue, por ejemplo bajo una ruta de servicio que se apruebe, con identidades y permisos deliberados. No se propone abrir indiscriminadamente el directorio personal ni usar `chmod 777`.

Debe distinguirse al usuario que actualiza el código del usuario sin privilegios que ejecuta PHP. El primero necesita desplegar; el segundo debería leer el código, disponer del acceso mínimo a la configuración de aplicación y escribir solo donde Laravel y los adjuntos lo requieran. La custodia administrativa y los respaldos no deben quedar al alcance del proceso PHP.

Referencia: [permisos de directorios en el despliegue de Laravel](https://laravel.com/framework/docs/13.x/deployment#directory-permissions).

## 9. Configuración efectiva de Laravel, consultada sin publicar secretos

Para leer la configuración se cargaron las fases de entorno y configuración de Laravel, sin ejecutar un kernel HTTP ni iniciar sesiones, caché o jobs. Solo se imprimieron campos permitidos; no se mostró el contenido de `.env` ni el valor de APP_KEY.

| Campo | Resultado observado |
|---|---|
| Entorno | `local` |
| Debug | `false` |
| Esquema/host de la URL de aplicación | HTTP / localhost |
| Zona horaria | America/Lima |
| APP_KEY | Presente; valor no mostrado |
| Caché de configuración | Ausente |
| Conexión por defecto | `mariadb` |
| Host / puerto de base de datos | `127.0.0.1` / 3306 |
| Esquema efectivo | `panaderia-rs` |
| Cuenta configurada | `panaderia_rs_app` |
| Override DB_URL | No presente |
| Sesiones | `database`, conexión por defecto |
| Cookie HttpOnly | `true` |
| Cookie SameSite | `lax` |
| Cookie Secure | `null`, sin valor explícito |
| Serialización de sesiones Laravel | `json` |
| Caché y locks | Database, conexión por defecto |
| Colas | Database, conexión por defecto |
| Lotes y trabajos fallidos | Conexión `mariadb` |
| Logging Laravel | `stack` con canal `single` |
| Permiso configurado para log single | `0600` |
| Boost | Deshabilitado |

La serialización `json` de las sesiones Laravel es un dato distinto de `session.serialize_handler` del módulo nativo de PHP. No deben confundirse al evaluar el aviso de PHP.

El valor `Secure=null` no demuestra un fallo por sí solo: su comportamiento depende del reconocimiento de HTTPS en la petición. Sin embargo, no se ha acreditado todavía esa cadena en un servicio real. En un entorno que deba funcionar exclusivamente con HTTPS conviene definir y comprobar explícitamente la política de cookies.

## 10. Lectura del estado efectivo de MariaDB

### 10.1 Cómo se realizó

Se usó PDO directamente con la configuración de conexión ya existente, sin imprimir las credenciales ni pasarlas como argumentos de un cliente de consola. Se verificó que la configuración correspondiera a la identidad dedicada y al host esperado.

La consulta se ejecutó dentro de una transacción **READ ONLY**, seguida de ROLLBACK. No se consultaron filas de negocio, contraseñas de usuarios, hashes, sesiones almacenadas o tablas de privilegios para repetir la auditoría anterior.

La forma de la consulta fue una lectura de `VERSION()`, `CURRENT_USER()`, `DATABASE()` y variables seleccionadas del servidor. El ROLLBACK cierra la transacción de lectura; no se utilizó como supuesto mecanismo para hacer reversibles cambios de cuentas o configuración, que no se ejecutaron.

### 10.2 Resultados saneados

| Campo | Resultado |
|---|---|
| Versión | 13.0.2-MariaDB |
| Identidad autenticada | `panaderia_rs_app@127.0.0.1` |
| Base seleccionada | `panaderia-rs` |
| Datadir | `/var/lib/mysql/` |
| Socket | `/run/mysqld/mysqld.sock` |
| Puerto | 3306 |
| `bind_address` | Sin restricción explícita en el valor devuelto; la escucha amplia se confirmó con `ss`. |
| `skip_networking` | 0 |
| General log / slow query log / binlog | Desactivados |
| Event scheduler | OFF |
| Zona horaria | SYSTEM; zona del sistema `-05` |

**Conclusión comprobada ahora:** Laravel continúa autenticando con la cuenta dedicada, no con root. La suficiencia y restricción exacta de sus permisos se apoya en las verificaciones anteriores; en esta revisión no se repitió la matriz de grants ni se intentaron operaciones negativas sobre la base real.

No se modificaron `panaderia-rs` ni `panaderia_rs`, cuentas, contraseñas, plugins, privilegios o configuración. No se ejecutaron A/B/C/D otra vez.

## 11. Firewall activo y alcance de la conclusión de red

Se distinguieron tres evidencias:

1. **Configuración persistida:** UFW habilitado, IPv6 habilitado y políticas de entrada/forward DROP y salida ACCEPT.
2. **Estado de servicio:** UFW activo.
3. **Reglas realmente cargadas:** consultas administrativas de nftables e iptables/ip6tables.

La tercera lectura es importante: un archivo de configuración y un servicio activo no bastan para afirmar qué reglas están cargadas.

Las reglas examinadas tienen estas propiedades:

- INPUT DROP y FORWARD DROP en IPv4 e IPv6.
- OUTPUT ACCEPT.
- Loopback permitido.
- Entrada de conexiones RELATED/ESTABLISHED permitida.
- Cadenas de usuario de entrada vacías en ambas familias.
- Excepciones propias de ICMP, DHCP y descubrimiento multicast, que no equivalen a permitir nuevas conexiones TCP externas a 3306.

La salida JSON de nftables contenía matches de compatibilidad `xt` para conntrack sin detallar todos sus estados. Para no inferirlos a partir del nombre, se completó la lectura con `iptables-save` e `ip6tables-save`; allí se comprobó explícitamente RELATED/ESTABLISHED.

**Conclusión del recorrido de reglas:** el firewall local observado bloquea nuevas entradas TCP externas a MariaDB, aunque el servidor escuche en todas las interfaces. Loopback continúa disponible para Laravel y existen las excepciones de conexiones ya establecidas.

**Límites:** no se hizo sondeo desde otro equipo, no se revisó el router/NAT o firewall de un proveedor y no se certifican reglas futuras. Cualquier cambio posterior de red o preparación de contenedores requiere revisar de nuevo su efecto. No se abrió 3306, 80, 443 ni otro puerto durante esta tarea.

## 12. Qué falta para servir Laravel mediante HTTP y HTTPS

### 12.1 Document root y ejecución PHP

El servidor web debe servir exclusivamente el directorio `public/`. No se debe servir la raíz del repositorio ni mover allí `index.php` para compensar una configuración incorrecta.

La configuración futura debe resolver rutas Laravel mediante `public/index.php`, servir los recursos estáticos necesarios y restringir la ejecución PHP al front controller. Un archivo subido o un archivo privado no debe convertirse en script ejecutable por una regla FastCGI demasiado amplia.

`.env`, `.git`, respaldos, candidatos administrativos y documentación privada deben quedar fuera de las rutas públicas. El enlace de almacenamiento debe seguir apuntando solo a adjuntos públicos autorizados, nunca al directorio privado.

No se escribieron bloques Nginx/Apache ni se configuró FastCGI en esta revisión. La configuración final depende del servidor elegido, de su socket FPM, de los nombres de acceso y de quién termina TLS.

Referencia: [configuración de servidor en Laravel 13](https://laravel.com/framework/docs/13.x/deployment#server-configuration).

### 12.2 Alternativas para certificados

| Alternativa | Qué permite | Qué queda por decidir o preparar |
|---|---|---|
| CA local para ensayo privado | HTTPS confiable en los dispositivos donde se instale explícitamente esa confianza. | Nombres, dispositivos, distribución y custodia de la CA/certificados. |
| Certificado público para un dominio controlado | Confianza pública y acceso por un nombre real. | Dominio, DNS, método de validación, renovación y accesibilidad elegida. |
| HTTPS gestionado por la infraestructura elegida | Delegar parte de la operación TLS al destino o a su proxy. | Capacidades reales del proveedor, conexión al origen y configuración de proxies/cookies. |

Un certificado autofirmado que el navegador no confía todavía no constituye un ensayo completo de HTTPS válido. Let's Encrypt no emite certificados para `localhost`; para ensayo local puede utilizarse una CA local confiada por los dispositivos.

Para un dominio real, HTTP-01 exige validación por el puerto 80; DNS-01 permite acreditar el control del dominio mediante DNS sin abrir ese puerto para el desafío. Tener un certificado mediante DNS-01 no hace accesible el servidor automáticamente ni decide si el acceso será privado o público.

Se debe definir renovación, custodia de claves y comprobación desde los dispositivos. Nada de eso se instaló o configuró ahora.

Fuentes: [certificados para localhost](https://letsencrypt.org/docs/certificates-for-localhost/) y [tipos de desafío ACME](https://letsencrypt.org/docs/challenge-types/).

### 12.3 Proxies, URLs y cookies

El código actual confía en `127.0.0.1` y `::1` y en los headers forwarded configurados en `bootstrap/app.php`. Ese alcance se estableció para un proxy local. No debe trasladarse sin análisis a una topología donde el proxy esté en otro equipo o contenedor.

Si un proxy termina TLS y comunica con el origen por HTTP, Laravel debe reconocer de forma fiable que la petición original fue HTTPS. De ello dependen los enlaces, formularios, redirecciones y el comportamiento de cookies.

El ensayo debe comprobar al menos:

- Certificado válido y nombre correcto.
- Ausencia de contenido mixto y formularios/redirecciones HTTP inesperados.
- Headers forwarded aceptados solo desde proxies realmente confiables.
- Configuración del proxy que impida que un cliente suplante esos headers.
- Cookie Secure bajo la política HTTPS elegida, junto con HttpOnly y SameSite.
- Login, logout, expiración y cambio de contraseña desde más de un dispositivo.

Estas son verificaciones futuras; no se hicieron peticiones HTTP contra cuentas o sesiones operativas para probarlas. Fuente: [proxies confiables de Laravel](https://laravel.com/framework/docs/13.x/requests#configuring-trusted-proxies).

## 13. Almacenamiento, fotografías, logs y mantenimiento

Se verificó que `public/storage` apunta a `storage/app/public`. No se modificó el enlace.

La regla propia de fotografías de Torta permite archivo de imagen JPEG/PNG/WebP, comprueba extensión y establece un máximo de 2048 KiB. PHP tiene `upload_max_filesize=2M` y `post_max_size=8M`. El servidor web futuro deberá tener límites coherentes con esos valores y con el cuerpo multipart completo.

No se subieron fotografías ni se crearon archivos de prueba durante esta revisión. La lectura de reglas no sustituye la prueba de cámara/galería, errores de carga, permisos del almacenamiento y recuperación frente a un fallo de escritura.

Laravel resuelve logging mediante `stack` con el canal `single`, cuyos archivos nuevos tienen permiso configurado `0600`. No se acreditó una política operativa completa de rotación, retención, alertas o revisión de espacio. Esto sigue pendiente para el destino. No se cambiaron canales ni se limpiaron logs.

Las sesiones, caché, locks, colas, lotes y fallos utilizan MariaDB y necesitan conservar el acceso mediante la cuenta restringida. No se debe resolver una falta de permisos volviendo a root o ampliando grants sin análisis.

`routes/console.php` contiene el comando de ejemplo `inspire`; la búsqueda realizada no localizó una necesidad propia de workers/scheduler que justifique activarlos automáticamente. Tener configurado el driver database no demuestra por sí mismo que existan trabajos de aplicación que necesiten un worker permanente. Ese requisito se decidirá según los flujos realmente usados.

## 14. Recursos del equipo y opciones de alojamiento

### 14.1 Capacidad observada

| Recurso | Fotografía del momento de la lectura |
|---|---|
| Sistema | CachyOS Linux, familia Arch, distribución rolling |
| Procesadores disponibles según `nproc` | 8 |
| Memoria total | Aproximadamente 5,5 GiB |
| Memoria disponible | Aproximadamente 2,3 GiB |
| Swap total / usada | Aproximadamente 5,5 GiB / 3,0 GiB |
| Disco raíz total / disponible | Aproximadamente 473 GiB / 300 GiB |
| `/tmp` | tmpfs de aproximadamente 2,8 GiB, con unos 1,6 GiB disponibles |

Estos valores cambian con el uso del escritorio y no constituyen una prueba de carga. Hay disco suficiente en la fotografía observada, pero no se han medido concurrencia, latencia, uso real de workers o comportamiento con cortes de energía/red. El uso de swap aconseja medir antes de aumentar servicios o reservar memoria para una máquina virtual.

### 14.2 Opciones identificadas

| Opción | Ventaja disponible | Requisito o límite |
|---|---|---|
| Ensayo nativo independiente en el equipo local | PHP, FPM, MariaDB y herramientas ya instalados. | Instalar/preparar servidor web y ubicación propia; no usar la configuración operativa como banco de pruebas. |
| Contenedores locales | Docker está instalado. | Su servicio no está activo; faltan imágenes, redes, volúmenes, límites y permisos aprobados. No montar datos o secretos operativos por comodidad. |
| Máquina virtual u otro equipo local | Puede separar mejor el sistema de ensayo del escritorio y acercarlo al futuro destino. | No se comprobó que exista una VM/hypervisor preparada; faltan recursos, sistema, red y administración. |
| Servidor remoto o plataforma administrada | Puede separar disponibilidad y operación del equipo de desarrollo. | No se eligió proveedor, capacidad, presupuesto, región, acceso, PHP, base de datos ni política de respaldo. |

La disponibilidad de Docker no significa que esté preparado un entorno de contenedores. Tampoco se recomienda una VM local sin comprobar antes los recursos necesarios. No se contrató ni se accedió a un servidor remoto.

El funcionamiento de una prueba local no demuestra que el equipo sea apropiado como instalación definitiva: faltan decisiones sobre disponibilidad, soporte, actualizaciones, red, energía, recuperación y responsables.

Referencia para valorar aislamiento y privilegios de contenedores: [seguridad de Docker Engine](https://docs.docker.com/engine/security/).

## 15. Ensayo frente a instalación definitiva

| Aspecto | Entorno de ensayo | Instalación definitiva |
|---|---|---|
| Datos | Ficticios y aislados; sin escrituras sobre producción. | Datos operativos, con transferencia y continuidad expresamente planificadas. |
| Credenciales | Propias del ensayo; sin acceso accidental a la base operativa. | Identidad de ejecución restringida y custodia administrativa separada. |
| APP_KEY | Configuración propia del ensayo; no modificar ni reutilizar por comodidad la operativa. | Preservar y trasladar de forma privada la clave requerida por la instalación existente; no regenerarla automáticamente. |
| Acceso | Participantes y dispositivos concretos, con alcance limitado. | Usuarios autorizados, disponibilidad y soporte definidos. |
| HTTPS | Confianza correcta en todos los dispositivos de prueba. | Certificado y renovación acordes con el acceso definitivo. |
| Respaldo | Ensayar creación/restauración sin afectar producción. | Periodicidad, retención, copia externa, alertas y recuperación comprobadas en destino. |
| Mantenimiento | Cambios controlados para reproducir el futuro servicio. | Responsables, ventanas, actualizaciones, logs y recuperación operativa. |

La cuenta operativa `panaderia_rs_app` debe conservarse restringida. Para el ensayo hace falta una identidad aislada: podría ser otra cuenta sobre una base separada o el mismo nombre únicamente dentro de otro servidor independiente. No se decidió ni se creó ninguna de esas alternativas.

Si el servidor definitivo cambia, también deben decidirse la ubicación de MariaDB y el alcance de red de su identidad. No se puede asumir que una cuenta limitada a `127.0.0.1` permite una base remota, ni abrir 3306 o ampliar hosts automáticamente para resolverlo.

## 16. Lectura actual de los hallazgos de la auditoría del 30/10

| Hallazgo o pendiente | Estado al cierre de la revisión del 31/10 |
|---|---|
| H01: credencial vigente expuesta | Revocación completada y comprobada previamente; no se repite. Tratamiento del historial/clones/copias sigue PENDIENTE. |
| H02: root en el runtime | Transición a cuenta restringida completada previamente. Identidad dedicada revalidada ahora por una lectura de conexión. |
| H03: escucha amplia de MariaDB | Continúa confirmada. Firewall local activo revalidado con bloqueo de nuevas entradas TCP externas; prueba desde otro equipo y política del futuro destino PENDIENTES. |
| H04: Symfony Mime vulnerable | Corregido en 8.1.10 y regresión aislada completada previamente; parche confirmado en el commit actual. |
| H05: configuración local distinta de producción | Continúa: entorno local/HTTP y sin servidor web HTTPS preparado. |
| H06: mínimo PHP declarado frente al lock | Continúa la diferencia; este lock requiere >=8.4.1 aunque el manifiesto admita ^8.3. |
| Recuperación del despliegue definitivo | PENDIENTE; las restauraciones anteriores no certifican un destino todavía no elegido. |
| Aceptación física de dispositivos y sesiones | PENDIENTE. |
| Congelamiento Android | EN INVESTIGACIÓN; esta revisión no lo reprodujo ni lo descartó. |
| Retención y vigilancia de logs | PENDIENTE de acreditar en el servicio que se prepare. |
| Borrado de Historial y alcance del Dashboard | Decisiones funcionales pendientes de aceptación; no se modifican para preparar HTTP. |

La escucha amplia, la falta de servidor web, el impedimento de permisos y los valores PHP son observaciones concretas. La accesibilidad desde Internet, la capacidad para una carga real, la recuperación en un servidor futuro y la aceptación móvil son verificaciones pendientes. No deben presentarse como fallos ya reproducidos ni como éxitos ya obtenidos.

## 17. Evidencia previa de pruebas y verificaciones hechas ahora

### 17.1 Antecedente de regresión tras el parche MIME

Se leyó el resumen de la regresión aislada anterior. No se volvió a ejecutar esa batería en la revisión del entorno ni al escribir este documento.

| Suite anterior | Aprobadas | Fallidas | Errores | Omitidas | Aserciones |
|---|---:|---:|---:|---:|---:|
| General | 1240 | 0 | 0 | 22 | 10848 |
| Permisos MariaDB | 22 | 0 | 0 | 0 | 178 |
| Casos únicos combinados | **1262** | **0** | **0** | **0** | **11026** |

Los 22 casos adicionales corresponden a los omitidos por la general; no se suman dos veces. Esa ejecución anterior utilizó MariaDB desechables, con datos ficticios y controles de aislamiento. Sus resultados no equivalen a una nueva prueba HTTP/FPM/HTTPS en el equipo actual.

El resumen leído estaba en `/tmp/panaderia-mime-regression-kdhqyyyu/combined-summary.json`. Es evidencia temporal: no se garantiza conservación después de reinicios o limpieza. La documentación anterior registra su alcance y límites.

### 17.2 Verificaciones de esta revisión

- Compatibilidad de plataforma del lock: aprobada con PHP CLI 8.5.12.
- Versiones y extensiones de CLI/FPM: inventariadas.
- Manifiesto compilado: 26 entradas, sin archivos referenciados faltantes.
- Configuración efectiva de Laravel: campos permitidos consultados sin imprimir secretos.
- Cuenta MariaDB efectiva y variables seleccionadas: lectura READ ONLY satisfactoria.
- Firewall persistido y activo IPv4/IPv6: consultado y contrastado.
- Servicios y puertos: observados sin iniciar o detener procesos de servicio.
- Permisos y ACL: revisados, con impedimentos identificados para el pool actual.
- Conservación del informe histórico y de los archivos del snapshot anterior: comprobada por contenido.
- `git diff --check`: sin incidencias.

### 17.3 Qué no se hizo

No se hizo una compilación, una prueba de carga, una navegación real, un login contra cuentas operativas, una carga de fotos, una restauración, un reinicio, una prueba desde otro dispositivo o una comprobación de HTTPS mediante un servidor iniciado para esta tarea.

No se verificaron una cadena TLS real, renovación de certificados, headers vistos por un navegador, comportamiento de cookies en el futuro proxy ni capacidad de producción. Esos resultados continúan PENDIENTES.

## 18. Registro de comandos y consultas

La tabla resume los comandos efectivamente utilizados para las lecturas. Las listas de propiedades y las salidas se limitaron al objetivo; no deben interpretarse como instrucciones para publicar información sensible sin filtrar.

| Grupo | Comandos o mecanismos utilizados | Resultado o precaución |
|---|---|---|
| Instrucciones y Git | Lectura de `AGENTS.md`; `git --no-optional-locks status --short`; `git branch --show-current`; `git log -1`; `git diff --check`; consulta del staging | Estado y conservación, sin add/commit/push. |
| PHP CLI | `php --version`, `php --ini`, `php -m` | Versión, INI y módulos. |
| PHP-FPM | `php-fpm --version`, `php-fpm -m`; información INI seleccionada mediante captura/filtrado | No se arrancó FPM ni se publicó phpinfo completo. |
| Dependencias | `composer --version`, `php artisan --version`, `composer check-platform-reqs --lock` | Lecturas y comprobación de plataforma; sin instalación o actualización. |
| Frontend | `node --version`, `npm --version`; lectura de package.json instalados y manifest.json | Compatibilidad y existencia de recursos; sin build. |
| Paquetes del sistema | `command -v`; `pacman -Q` para paquetes concretos | Ausencias normales no se interpretaron como una instalación fallida. |
| Servicios | `systemctl is-active`, `systemctl is-enabled`; propiedades seleccionadas de la unidad FPM | Sin start/stop/restart/enable/disable. |
| Puertos | `ss -lntup`, `ss -lxnp` | Lectura de listeners, no sondeo externo. |
| Permisos | Lectura de stat/lstat, `getfacl -cp`, `getent passwd http`, `getent group http` | Metadatos y ACL; no chmod/chown/setfacl. |
| Configuración del sistema | Lectura filtrada de PHP/FPM, archivos MariaDB y UFW | Sin volcar configuraciones sensibles completas. |
| Firewall activo | `nft -j list ruleset`, `iptables-save -t filter`, `ip6tables-save -t filter`, con lectura administrativa cuando fue necesaria | Interpretación de reglas cargadas; sin modificación. |
| Recursos | `free -h`, `df -h / /tmp`, `nproc` | Fotografía de capacidad, no benchmark. |
| Laravel efectivo | Fases `LoadEnvironmentVariables` y `LoadConfiguration`, con salida de campos seleccionados | Sin kernel HTTP ni APIs de sesión/caché/cola que pudieran escribir. |
| MariaDB efectivo | PDO, transacción READ ONLY, SELECT de identidad y variables, ROLLBACK | Sin leer filas privadas, sin DML/DDL y sin repetir A/B/C/D. |
| Conservación y antecedentes | Comparación de contenido mediante huellas de archivos y lectura del resumen JSON de regresión | No se imprimieron hashes de autenticación ni secretos. |

Las lecturas de servicios/red y de MariaDB inicialmente bloqueadas se repitieron con escalamiento. Los estados finales consignados corresponden a las lecturas que sí pudieron completarse, no a deducciones hechas a partir de un error de permisos.

## 19. Plan propuesto hasta antes del 7 de noviembre

**Todo este apartado es PENDIENTE: no se ejecutó durante la revisión ni por guardar este informe.** Las fechas son una propuesta de organización, no una promesa de puesta en producción.

| Fecha propuesta | Prioridad y trabajo | Condición para avanzar |
|---|---|---|
| 31/10–01/11 | Elegir el entorno de ensayo, participantes, dispositivos, acceso local/remoto, nombre y estrategia HTTPS. | Destino concreto y alcance de acceso acordados; sin asumir que será el definitivo. |
| 01–02/11 | Preparar copia independiente, identidad de sistema, almacenamiento, servidor web/FPM y base ficticia. | Aislamiento de producción, permisos mínimos y runtime compatible comprobados. |
| 02–03/11 | Configurar HTTPS, reconocimiento del proxy, cookies y mitigación PHP; comprobar bloqueo de archivos privados. | Certificados confiables en dispositivos, URLs correctas y superficie pública controlada. |
| 03–05/11 | Ejecutar aceptación de flujos reales de uso con datos ficticios: sesiones, fotos, categorías, participantes, Historial y Dashboard. Investigar el reporte Android. | Evidencia de cada flujo; fallos reproducidos y corregidos dentro de alcance autorizado. |
| 05–06/11 | Ensayar respaldo/restauración, reinicio y recuperación del entorno de ensayo; revisar logs y límites. | Recuperación y continuidad demostradas sin afectar producción. |
| Antes del 07/11 | Revisar resultados y decidir aceptación o pendientes. Preparar el plan particular del destino definitivo, si ya se eligió. | No confundir una suite aprobada con aprobación operativa del servicio final. |

No se debe usar `composer setup` como procedimiento automático sobre la instalación actual: el script del proyecto incluye generación de clave y migraciones, entre otras operaciones. Tampoco se debe ejecutar `optimize:clear` como limpieza indiscriminada: puede afectar la caché predeterminada y excedería el objetivo de conservar sesiones/contadores y datos operativos.

Un despliegue posterior deberá usar versiones bloqueadas y un procedimiento revisado. Las tareas de instalación, descubrimiento de paquetes, cachés, permisos, migraciones y publicación tienen efectos distintos y requieren delimitarse antes de ejecutarlas.

## 20. Autorizaciones necesarias para la implementación futura

La revisión y la creación de este documento no conceden autorización para:

1. Instalar o activar Nginx/Apache/Caddy, FPM, Docker u otros servicios.
2. Crear directorios de servicio, identidades de ejecución o cambiar propietarios, grupos, ACL o permisos.
3. Modificar PHP/FPM, incluida la mitigación de upload progress o sus límites.
4. Crear bases/cuentas de ensayo, asignar permisos o ejecutar migraciones/seeders, aunque se propongan aislados.
5. Generar/publicar certificados, cambiar DNS o instalar confianza en dispositivos.
6. Abrir puertos, modificar firewall, cambiar la escucha de MariaDB o activar un túnel.
7. Crear o publicar configuraciones de entorno, cambiar proxies/cookies o preparar secretos de un nuevo entorno.
8. Ejecutar pruebas que escriban datos, restauraciones o reinicios de ensayo.
9. Transferir datos operativos, APP_KEY o credenciales al futuro servidor.
10. Hacer commits, push, despliegues o tratamiento del historial Git.

Antes de implementar, la autorización debe identificar el destino, las rutas, el alcance de datos y red, las identidades y el mecanismo de recuperación. No hace falta repetir A/B/C/D para preparar una propuesta de ensayo; sus resultados previos se conservan.

## 21. Decisiones que faltan y siguiente paso recomendado

El siguiente paso recomendado es **decidir el entorno de ensayo y su forma de acceso**. Para ello faltan, como mínimo:

- Dónde se ejecutará: copia nativa local, contenedor, otra máquina/VM o infraestructura remota.
- Quiénes probarán y desde qué dispositivos y red.
- Si será privado o accesible remotamente, y mediante qué mecanismo autorizado.
- Qué nombre o dominio se usará y quién controla su DNS, si corresponde.
- Si se usará una CA local o un certificado público/gestionado.
- Qué recursos, presupuesto y responsable de administración estarán disponibles.
- Qué criterio de aceptación debe cumplirse antes del 7 de noviembre.

Con esas respuestas se puede preparar un procedimiento de instalación concreto y revisable. Instalar un servidor web ahora sin fijar esas decisiones podría crear una configuración que no sirva para el ensayo ni para el destino final.

## 22. Referencias y relación con la documentación existente

Este documento es un registro nuevo solicitado expresamente para `docs/`. No es una copia de las notas completas del Vault. El informe histórico del 30/10 y los originales de la documentación del proyecto se conservan.

- Auditoría anterior del repositorio: [AUDITORIA_PREPARACION_PRODUCCION_2026-10-30.md](AUDITORIA_PREPARACION_PRODUCCION_2026-10-30.md).
- Documentación técnica original: `PROYECTO_LARAVEL/LARAVEL/Dependencias y seguridad.md` en el Vault indicado por `AGENTS.md`.
- Seguimiento de los problemas existentes: `PROYECTO_LARAVEL/PENDIENTES/Bugs.md`, sin crear investigaciones duplicadas en esta tarea.

Fuentes oficiales consultadas durante el análisis del entorno:

1. [Despliegue de Laravel 13](https://laravel.com/framework/docs/13.x/deployment).
2. [Proxies confiables de Laravel](https://laravel.com/framework/docs/13.x/requests#configuring-trusted-proxies).
3. [Aviso PHP sobre session upload progress, 23/10/2026](https://www.php.net/archive/2026.php#2026-10-23-1).
4. [Manual de Session Upload Progress](https://www.php.net/manual/en/session.upload-progress.php).
5. [Desafíos de Let's Encrypt](https://letsencrypt.org/docs/challenge-types/).
6. [Certificados para localhost](https://letsencrypt.org/docs/certificates-for-localhost/).
7. [Seguridad de Docker Engine](https://docs.docker.com/engine/security/).

Las versiones y estados son una fotografía del 31/10/2026. Antes de una implementación posterior deben revalidarse los datos que puedan cambiar, los avisos de seguridad y el estado del destino seleccionado. No se presenta este documento como garantía permanente ni como certificación de seguridad absoluta.

## 23. Cierre y estado de esta tarea documental

**CÓDIGO MODIFICADO:** ninguno. No se modificaron reglas de negocio, controladores, modelos, vistas, migraciones, dependencias o configuración.

**DOCUMENTACIÓN MODIFICADA:** se creó únicamente `docs/http.md` por solicitud expresa del propietario. El informe histórico del 30/10 se conserva íntegro. No se modificaron las notas del Vault, AGENTS.md o los archivos operativos al crear este documento. `mejora_asignado.md` no existe en el checkout examinado y no se recrea.

**PRUEBAS Y REVISIÓN:** las lecturas técnicas y sus límites se describen en los apartados anteriores. Al guardar el informe se revisaron estructura, contenido, referencias, patrones de valores secretos, whitespace y conservación del estado Git. El enlace local al informe previo existe y los bloques de código están balanceados. El análisis de patrones no encontró candidatos de secretos; no constituye una garantía universal. Se corrigieron únicamente cuatro espacios finales de Markdown detectados en la primera pasada. No se repiten por esta escritura las consultas administrativas, la suite, el build o la conexión MariaDB.

**PENDIENTES:** decisiones, autorizaciones e implementación del entorno de ensayo; aceptación en dispositivos y evaluación del destino definitivo. Ninguna recomendación se considera implementada por aparecer en este archivo.

No se inició ni detuvo ningún servicio, no se abrieron puertos, no se cambió configuración o credenciales, no se ejecutaron migraciones/seeders ni se modificaron datos. No se repitieron A/B/C/D. Sin commit, push, reescritura Git, despliegue o activación de Cloudflare Tunnel.

Trabajo documental limitado al archivo solicitado; la implementación permanece detenida hasta instrucciones posteriores.
