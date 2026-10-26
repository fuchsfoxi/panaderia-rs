# Panadería RS — Auditoría y preparación del cierre de credenciales

Fecha de las verificaciones: **26 de octubre de 2026**, zona `America/Lima`.

**IMPLEMENTADO Y COMPROBADO:** auditoría de lectura, respaldo actualizado con restauración, pruebas del cambio de contraseña del administrador y ensayo de rotación con una cuenta ficticia.

**PENDIENTE:** autorización expresa, confirmación de consumidores externos y rotación real de la contraseña de MariaDB. Este informe no autoriza ejecutar el cambio.

Este archivo se incorpora a `docs` por petición expresa del propietario. El informe técnico original y el seguimiento ya estaban guardados en Obsidian; sus rutas figuran al final. Es un informe de esta tarea, con su alcance y resultados, sin sustituir ni modificar auditorías anteriores del repositorio.

## 1. Contexto y alcance

La auditoría de seguridad anterior del 26/10 terminó con **1.239 pruebas aprobadas y 10.819 aserciones**. Corrigió sesiones al desactivar/reactivar, exposición de bindings SQL en logs, receptor de logs de Boost, cabeceras HTTP y dependencias npm; esos cambios se conservaron.

Esta continuación partió del checkout `5928a4c`, con los cambios anteriores aún locales. Se leyó `AGENTS.md`, se revisaron estado y diff de Git, implementación de autenticación y documentación original.

Se distinguieron dos entornos:

- **Servidor original:** consultas de configuración, metadatos y datos dentro de transacciones de solo lectura; generación de respaldo sin modificar registros operativos.
- **Instancias aisladas:** migraciones, fixtures, autenticación HTTP, restauraciones y ensayo de rotación exclusivamente en servidores separados, sin red y con identidad de socket/datadir comprobada.

No se cambiaron contraseñas reales, `APP_KEY`, valores de `.env`, grants ni configuración de escucha. No se ejecutaron migraciones operativas, escrituras de negocio, reescritura Git, commit, push o activación de Cloudflare Tunnel. No se incluyen secretos, hashes de autenticación, cookies ni contenido de `.env` en este informe.

## 2. Credenciales del administrador

**IMPLEMENTADO Y COMPROBADO en aislamiento:** el administrador puede cambiar su propia contraseña desde el formulario existente. Se conserva la implementación productiva: Request con confirmación y límites de bcrypt, Action con `Hash::make`, comprobación del actor Administrador activo y middleware `auth.session`.

La nueva regresión [AdministradorPasswordTest.php](../tests/Feature/AdministradorPasswordTest.php) verifica:

1. Dos sesiones independientes del mismo administrador ficticio.
2. Consulta del formulario y cambio mediante su endpoint HTTP.
3. Hash compatible con la nueva contraseña y rechazo de la anterior.
4. Conservación de identidad, rol, empleado, turno y estado.
5. Rechazo de las dos sesiones anteriores en su siguiente petición protegida.
6. Nuevo login válido con la contraseña nueva.

La revocación ocurre al realizar una petición protegida; no implica cerrar instantáneamente una pantalla ya abierta. Las pruebas existentes cubren también sesiones array/database, cambios antes de la primera visita protegida, permisos, CSRF, logout, último Administrador y rehash.

`Usuario::getAuthPasswordName()` ya existe y devuelve `password_hash`; [LoginSecurityTest.php](../tests/Feature/LoginSecurityTest.php) lo comprueba. El pendiente estructural histórico está **IMPLEMENTADO Y COMPROBADO** en este checkout.

Lectura del servidor original:

- Tres cuentas activas: un Administrador y dos Operadores, todas con bcrypt reconocido.
- Ninguna coincide con los diez literales históricos identificados en llamadas a `Hash::make`, comparados exclusivamente en memoria.
- Seeder actual sin contraseña literal reutilizable; exige configuración privada para crear la cuenta inicial y conserva las cuentas existentes.
- Siete sesiones almacenadas, una autenticada y con huella de contraseña; ninguna autenticada sin huella y ningún error de decodificación.

La reutilización de esos diez literales queda **DESCARTADA para las cuentas examinadas**. Esto no acredita la fortaleza de contraseñas desconocidas ni descarta otros secretos en copias externas. No se hicieron intentos de login ni cambios de contraseña con cuentas reales. La aceptación manual privada del propietario sigue **PENDIENTE**.

## 3. MariaDB y consumidores

| Elemento | Estado observado |
|---|---|
| PHP CLI / Laravel | 8.5.12 / 13.38.0 |
| MariaDB | 13.0.2, servicio activo |
| Configuración Laravel | Entorno local, debug desactivado, sin caché de configuración |
| Cuenta efectiva | `root@localhost`, conexión TCP de loopback |
| Privilegios | Globales con `GRANT OPTION`, además de `PROXY` global |
| Autenticación | `mysql_native_password` y alternativa `unix_socket` |
| Consumidores Laravel | Datos, sesiones, caché, locks, colas, lotes y trabajos fallidos |
| Otras conexiones al consultar | Ninguna observada |
| Escucha MariaDB | Todas las interfaces IPv4/IPv6 en puerto 3306 |
| General log / slow log / binlog / performance_schema | Desactivados |
| Plugins AUDIT / SQL_ERROR | Ninguno encontrado |

Los grants se sanearon antes de comunicarlos; no se mostraron cadenas de autenticación. La comprobación final mediante `DatabaseManager` de Laravel confirmó conexión y consultas de solo lectura, con query logging desactivado.

Se inspeccionaron 240 archivos candidatos legibles en proyectos y configuraciones locales de servicios/editor. La coincidencia exacta del secreto solo apareció en la configuración privada de este proyecto. No se encontraron configuraciones en las rutas habituales examinadas de DBeaver, JetBrains o phpMyAdmin. No se observaron servidores web/FPM/cloudflared ni workers de la aplicación activos. El binario `crontab` y la ruta `/var/spool/cron` estaban ausentes.

**PENDIENTE:** confirmar con el propietario otros equipos, clientes, scripts, proyectos, tareas o configuraciones cifradas. La ausencia puntual de conexiones no demuestra que no existan consumidores intermitentes.

El uso de root por Laravel conserva privilegios excesivos. La escucha amplia requiere revisión de red/firewall; no se comprobó accesibilidad desde otra máquina ni se demostró login remoto de root. Separar la cuenta de aplicación o cambiar la escucha requiere una tarea autorizada específica.

## 4. Respaldo completo y restaurable

Se volvió a verificar el respaldo integral del 24/10: cinco checksums correctos y restauración aislada de 27 tablas/161 filas, con 39 referencias sin huérfanos. Su estructura y 25 tablas coincidían con el original actual; diferían `usuarios_sistema` y `sessions`. Se conservó como antecedente, sin considerarlo snapshot actualizado.

Se creó el respaldo actualizado:

`storage/app/private/respaldos-credenciales/20261026-171031-9de9d3/`

Resultado **IMPLEMENTADO Y COMPROBADO**:

- Dump completo de **27 tablas y 157 filas**, todas InnoDB, incluidas cuentas, sesiones, caché y colas.
- Opciones de conexión transmitidas mediante pipe privado en memoria, sin contraseña en argumentos, variables de entorno o archivo temporal de opciones.
- Restauración en otra instancia sin red, eventos ni registros de consultas, con identidad verificada antes de importar.
- Datos y estructura de las 27 tablas idénticos al original antes y después; **39 referencias FK sin huérfanos**.
- Dos recursos públicos de medios verificados por tamaño y SHA-256, con inventario y archivo comprimido correspondiente.
- Seis artefactos incluidos en `SHA256SUMS`, comprobación independiente final correcta, directorio `0700` y archivos `0600`, excluidos de Git.

Evidencia saneada: [comprobacion-restauracion.json](../storage/app/private/respaldos-credenciales/20261026-171031-9de9d3/comprobacion-restauracion.json), disponible únicamente en este entorno local privado; no se incorpora a Git.

Las instancias restauradas quedaron detenidas. El respaldo contiene datos privados y exige acceso restringido. No incluye usuarios/grants globales, configuración del servidor, `.env`, `APP_KEY`, copia externa ni cifrado. No sustituye un mecanismo de recuperación de acceso administrativo. Su actualidad debe revalidarse antes de rotar si hubo cambios posteriores.

## 5. Exposición histórica en Git

**VULNERABILIDAD CONFIRMADA; remediación PENDIENTE.** La contraseña vigente de MariaDB coincide con contenido histórico del archivo `session-ses_f243.md`, introducido por `78f0b926` el 25/09/2026. La comparación se realizó en memoria, sin publicar el valor.

Se revisaron **1.920 objetos y 941 blobs** alcanzables mediante las referencias locales de `--all`. Ese commit es alcanzable desde `prototipo-estable` y la referencia local `origin/prototipo-estable`; no desde `master` según `--contains`.

Las referencias `origin/*` son estado guardado en el clon: no se consultó el remoto actual. No se examinaron objetos inalcanzables, clones, forks o copias externas. No se reescribió historia ni se ejecutó push forzado.

Se añadió a [.gitignore](../.gitignore) la exclusión de transcripciones `/session-ses_*.md`, conservando las exclusiones anteriores de entornos y respaldos. La comprobación de exclusiones pasó con nombres sintéticos y con los artefactos privados. No elimina el blob histórico ni impide incorporar secretos mediante otros nombres o `git add -f`.

Medidas recomendadas **PENDIENTES**: revisión de cambios preparados antes de cada commit, detección de secretos en precommit/CI y tratamiento coordinado del historial y sus copias después de revocar la credencial. Borrar archivos o commits no sustituye la rotación.

## 6. Plan propuesto de rotación — pendiente de autorización

1. **Confirmar alcance y continuidad.** Obtener autorización expresa para la cuenta y consumidores concretos. Revalidar configuración efectiva, overrides, grants, ambos plugins, conexiones, registros y vigencia del respaldo. Coordinar una pausa breve solo si existen procesos afectados en ese momento. Mantener conexiones administrativas de control abiertas durante la operación.
2. **Generar el secreto privadamente.** Generar dentro del proceso 32 bytes CSPRNG, codificados en 64 caracteres hexadecimales: 256 bits. No mostrarlo en chat, terminal, historial, argumentos, variables de entorno o logs. Si el propietario desea aportar el secreto, introducirlo exclusivamente desde su terminal privada sin eco, fuera de herramientas grabadas. No solicitar contraseñas en Codex.
3. **Preparar recuperación.** Construir la configuración nueva conservando el resto de `.env` y comprobar si `DB_PASSWORD`, una URL u otro override determina la conexión. Preparar `.env.rotation.pending` fuera de `public`, con creación exclusiva, rechazo de symlinks, propietario correcto, modo `0600` y escritura sincronizada antes de cambiar MariaDB. No usar archivos temporales inseguros.
4. **Cambiar solo la autenticación autorizada.** Usar PDO directo sin logger/query listeners de Laravel. Revalidar que no haya superficies activas de registro que capturen el secreto. Aplicar `SET PASSWORD` a la cuenta exacta con quoting PDO y generación validada; conservar grants y alternativas de autenticación. Comunicar errores únicamente por etapa y código, nunca por SQL completo. No sustituir plugins mediante un cambio general de `ALTER USER`.
5. **Publicar coordinadamente.** Verificar una conexión TCP nueva con el secreto nuevo y el rechazo del anterior; publicar la configuración privada mediante rename atómico y actualizar los demás consumidores expresamente incluidos. Limpiar solo caché de configuración si existe; actualmente no la hay. Conservar sesiones y contadores de login. Reabrir únicamente los procesos/clientes afectados que realmente estén activos.
6. **Verificar funcionamiento e integridad.** Comprobar la conexión efectiva de Laravel para datos/sesiones/caché/colas con consultas de lectura. Comparar datos, estructura y relaciones contra el snapshot inmediato previo; las cuentas Laravel deben conservar sus credenciales. Verificar grants/plugins, rechazo del secreto antiguo y ausencia de conexiones anteriores ajenas sin renovar. Buscar coincidencias del secreto nuevo en memoria sin imprimirlo.
7. **Recuperar si hay un fallo.** Antes del cambio, conservar configuración original. Si MariaDB cambia y falla la publicación, usar la configuración privada preparada y las conexiones de control para completar o reparar la actualización. Tras una interrupción del proceso, recuperar desde el archivo protegido sin mostrarlo. La alternativa `unix_socket` existe, pero no se certificó una sesión OS-root de rescate. No reintroducir automáticamente la contraseña comprometida, restaurar datos operativos ni usar `skip-grant-tables`.

**Ensayo IMPLEMENTADO Y COMPROBADO exclusivamente con cuenta ficticia:** nueva conexión válida, contraseña anterior rechazada, conexión de control conservada, ambos plugins intactos, configuración `0600`, rename, recuperación de interrupción simulada, datos ficticios intactos y log del servidor sin la candidata. Instancia final `/tmp/panaderia-rotation-rehearsal-9bc5eb28db4e`, detenida; salida final 0.

El ensayo detectó que MariaDB 13.0.2 rechazaba el placeholder preparado en esta gramática con error 1064. La variante comprobada utiliza PDO directo con quoting bajo las condiciones de registro indicadas. También se comprobó el código 1698 de rechazo por la alternativa socket; no se limita la comprobación al código 1045.

Referencias primarias: [SET PASSWORD de MariaDB](https://mariadb.com/docs/server/reference/sql-statements/account-management-sql-statements/set-password) y [registros de MariaDB](https://mariadb.com/docs/server/server-management/server-monitoring-logs/overview-of-mariadb-logs). La compatibilidad concreta se probó además en la instancia aislada.

## 7. Pruebas, cambios y límites

| Verificación | Resultado |
|---|---|
| Suite existente, repetida antes de la nueva regresión | 1.239 aprobadas / 10.819 aserciones |
| Suite completa final con regresión del administrador | **1.240 aprobadas / 10.848 aserciones**, cero fallos, errores u omisiones; 97,84 s |
| Sintaxis PHP, Pint del nuevo test y revisión de diff | Aprobados |
| Exclusiones Git y permisos del respaldo | Aprobados |
| Restauración del respaldo actualizado | Datos/esquemas íntegros y cero huérfanos |
| Conexión real mediante DatabaseManager de Laravel | Lecturas correctas, sin query logging ni escrituras operativas |
| Ensayo ficticio de rotación y recuperación | Aprobado; sin credenciales reales |

Comando de la suite final:

```bash
env PAO_DISABLE=1 php tests/run-mariadb.php --compact --colors=never --do-not-record-test-run-history
```

El runner verifica servidor/datadir/bases ficticias antes de migrar. Instancia final `/tmp/sprint4-1ae17a6ff274`, detenida. Hubo intentos previos bloqueados por falta de espacio o sockets del sandbox; se conservaron artefactos ficticios detenidos en almacenamiento privado y se repitieron con los permisos necesarios. No se borraron bases originales.

Dos intentos dirigidos de la nueva prueba fallaron por supuestos del cliente de test sobre la sesión y el destino `intended`; se ajustó únicamente la prueba. La suite completa final pasó sin modificar el flujo productivo.

Cambios de código de esta continuación: nueva regresión del administrador y dos líneas de prevención en `.gitignore`. Se conservaron los cambios de seguridad anteriores. No hubo cambios funcionales, de dependencias o migraciones en este cierre. No se repitieron navegador, build, JavaScript ni auditorías de dependencias; no se modificaron assets.

Las pruebas HTTP usan cuentas y sesiones ficticias, sin certificar una interacción manual con la cuenta original. Las lecturas del servidor y las restauraciones no constituyen una aprobación general de otro despliegue.

## 8. Cloudflare Tunnel: alcance aclarado por el propietario

Después del informe inicial, el propietario aclaró que el túnel se utiliza **solo por él y otros desarrolladores que siguen el proyecto**, sin compartirlo con usuarios finales. La evaluación queda acotada al desarrollo compartido: no corresponde convertir todas las condiciones pendientes de producción en requisitos para esas pruebas.

La auditoría no comprobó un mecanismo técnico de restricción de acceso al túnel, su runtime servido o la cadena HTTPS/proxy/cookies del destino. Compartirlo solo con desarrolladores describe la audiencia prevista; no certifica por sí mismo esa configuración.

La exposición histórica de la contraseña de MariaDB sigue siendo un pendiente independiente de esa audiencia. Su rotación continúa preparada y pendiente de autorización expresa. El uso de root y la escucha amplia se mantienen como riesgos observados que deben tratarse según el entorno; no se modificaron automáticamente.

No se activó ningún túnel durante esta tarea ni se acredita un despliegue productivo.

## 9. Documentación original y pendientes

La auditoría y su seguimiento ya estaban guardados en el Vault original:

- `PROYECTO_LARAVEL/LARAVEL/Dependencias y seguridad.md`: informe técnico, pruebas, respaldo y plan.
- `PROYECTO_LARAVEL/PENDIENTES/Bugs.md`: seguimiento en las entradas 18 y 29, sin duplicar la investigación.
- `PROYECTO_LARAVEL/INICIO/Estado de proyecto.md`: resumen del estado comprobado.

Raíz del Vault: `/home/lukagfv/Documentos/Obsidian Vault/`.

Este informe incorpora la aclaración posterior sobre el uso del túnel. Las notas originales conservan el registro de la evaluación realizada antes de esa aclaración. `mejora_asignado.md` no existe actualmente en checkout/Vault y no se recreó; Roadmap no se reordenó.

**PENDIENTES:** confirmación de consumidores externos, autorización expresa y ejecución coordinada de la rotación real. La aclaración del alcance del túnel y la petición de guardar este informe no constituyen autorización para cambiar credenciales.
