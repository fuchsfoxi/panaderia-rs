# Sprint 5 — Edición de Usuarios y vinculación con Empleados

Fecha: 2026-10-18. Estado: **IMPLEMENTADO Y COMPROBADO en aislamiento**, preparado para revisión manual. Sin commit, push ni despliegue.

## 1. Estado inicial

Se revisaron Git, AGENTS.md, rutas, middleware/Gates, modelos y relaciones, UsuarioController, FormRequests, AdministrarUsuarios, formularios/listado Blade, CSS, JavaScript, migraciones y pruebas existentes antes de editar. HEAD inicial `15b3668`, con cambios anteriores sin commit de Historial, Productos y Personal: se conservaron. Personal ya agrupaba Usuarios y Empleados; Usuarios permitía crear, cambiar contraseña y activar/desactivar, pero no editar acceso/vínculo.

`usuarios_sistema.empleado_id` ya era nullable, FK a empleados y UNIQUE (`usuarios_sistema_empleado_id_unique`). Usuario pertenece a Empleado; Empleado tiene una cuenta como máximo. Rol de acceso, turno y activo ya existían. No se requieren tablas, índices, migraciones, seeders ni cambios de modelo.

Diagnóstico operativo exclusivamente mediante transacción READ ONLY con rollback y selección explícita de metadatos/campos no secretos: `admin` activo, con empleado vinculado y sin turno; `turn_mañana` y `turn_noche` **ya estaban inactivos**, con sus turnos y sin empleado. Las dos fichas existentes ya tenían rol actual configurado (Maestro y Practicante). Son observaciones previas a esta implementación, no modificaciones realizadas por esta tarea. No se consultaron valores de credenciales/hashes reales ni se alteró ninguna fila operativa.

Base previa de regresión: 148 pruebas/1.381 aserciones aprobadas en MariaDB aislada (Usuarios, concurrencia previa, Unicode y Empleados).

## 2. Archivos creados y modificados en esta tarea

| Archivo | Motivo |
| --- | --- |
| `app/Http/Requests/ActualizarUsuarioRequest.php` (nuevo) | Contrato de edición, autorización, username/rol/turno y vínculo exclusivo opcional. |
| `app/Http/Requests/GestionarUsuarioRequest.php` | Etiqueta y old input seguro de empleado; conserva NULL explícito para no perder una desvinculación al fallar otro campo. |
| `app/Actions/Usuarios/AdministrarUsuarios.php` | Edición transaccional; revalidación del actor, vínculo y turno; protección compartida del último Admin para estado y rol. |
| `app/Http/Controllers/UsuarioController.php` | Preparar formulario/catálogos, llamar Action, distinguir colisión UNIQUE del vínculo y destino accesible al cambiar el propio rol. |
| `routes/web.php` | GET administrativo de edición y PUT/PATCH de actualización; rutas previas conservadas. |
| `resources/views/usuarios/index.blade.php` | Acción Editar con SVG de lápiz junto a contraseña/estado. |
| `resources/views/usuarios/edit.blade.php` (nuevo) | Formulario sin contraseña, selector de empleado, vínculo a su ficha, errores, cancelar/guardar. |
| `resources/views/usuarios/account-fields.blade.php` (nuevo) | Campos de acceso compartidos con creación, evitando duplicarlos. |
| `resources/views/usuarios/create.blade.php` | Reutilizar parcial; creación conserva su lógica y campos de contraseña. |
| `resources/css/usuarios.css` | Separación del lápiz y transición de 200 ms; reutiliza colores, hover/foco y responsive existentes. |
| `resources/js/usuarios.js` | Reutilizar la interacción rol/turno también en edición. |
| `tests/Feature/UsuariosEdicionTest.php` (nuevo) | Edición, vínculos, errores, permisos/CSRF, cuentas compartidas, contraseña, historia y rol laboral. |
| `tests/Feature/UsuariosEdicionConcurrenciaTest.php` (nuevo) | Dos degradaciones, degradación/desactivación y dos vínculos simultáneos. |
| `tests/Support/usuario-edicion-concurrente.php` (nuevo) | Worker de procesos reales exclusivamente contra MariaDB temporal verificada. |
| `tests/Feature/UsuariosTest.php` | Nuevos endpoints en matriz de invitados/Operadores; DELETE sigue prohibido (ahora 405 porque existe PUT/PATCH). |
| `tests/JavaScript/UsuariosTest.mjs` | Tres casos del formulario de edición y regresión del comportamiento previo. |
| Este informe | Entrega, evidencia, decisiones y revisión manual. |

La relación anterior no atribuye a esta tarea los otros archivos ya modificados en el checkout. `public/build` se regeneró con Vite; `public/hot` se conservó.

## 3. Cómo funciona Editar usuario

En Personal → Usuarios cada fila ofrece Editar. `GET /usuarios/{usuario}/editar` carga datos actuales, roles Administrador/Operador, turnos existentes y empleados disponibles. El formulario utiliza CSRF y PATCH hacia `/usuarios/{usuario}`; también se admite PUT. No existe actualización por GET.

Se guardan únicamente username, rol_id, turno_id y empleado_id. El turno es obligatorio para Operador; Administrador se guarda sin turno, siguiendo la regla existente de cuentas. La interfaz oculta/deshabilita el turno solo cuando no corresponde. Sin JavaScript el backend conserva la validación y el formulario puede enviarse normalmente.

Cancelar vuelve a Usuarios sin escribir. Guardar muestra «Usuario actualizado.»; un error retorna al formulario con valores permitidos y mensajes por campo. Si un Administrador cambia su propio rol a Operador, con otro Admin activo, se redirige al Dashboard accesible. La siguiente petición recupera el rol actual mediante el provider normal y deniega Usuarios.

## 4. Validaciones

- Username obligatorio/string/máximo 50 y UNIQUE real, excluyendo exclusivamente al usuario resuelto por route model binding. Nombres nuevos admiten a-z, ñ, números, punto, guion y guion bajo, sin espacios/mayúsculas, igual que crear. Una identidad legacy exacta puede conservarse al editar otros datos, sin normalizarla ni renombrarla automáticamente.
- Se respeta la collation actual: `turn_manana` y `turn_mañana` son equivalentes para UNIQUE. No se cambia el esquema.
- Rol real del catálogo limitado a Administrador/Operador, sin IDs funcionales fijos. Turno existente y requerido para Operador, revalidado en la Action.
- Empleado nullable, entero, existente y no vinculado a otra cuenta. Mensaje: «Este empleado ya está vinculado a otra cuenta.»
- Password, confirmación, hash y activo están prohibidos en edición. IDs/relaciones/campos extra del navegador no determinan destino, permisos ni asignación masiva.
- Old input tiene whitelist de escalares acotados y NULL del vínculo. Nunca guarda contraseñas, confirmaciones, hashes, arrays o extras arbitrarios.

## 5. Usuarios y empleados vinculados

El selector ofrece «Sin empleado vinculado», empleados sin otra cuenta y el empleado actualmente asociado, aunque esté inactivo. Nombre e ID distinguen homónimos; inactivos aparecen identificados. Vincular una ficha laboral no equivale a habilitarla para producir: no se introduce una restricción nueva de activo/rol laboral en cuentas y las selecciones de Producción mantienen sus reglas previas.

Enviar NULL/vacío desvincula; omitir el campo conserva la asociación actual. No se crea, elimina ni edita un empleado. Con vínculo persistido aparece «Editar empleado vinculado» hacia `empleados.edit` de esa ficha, con la pestaña Empleados activa y autorización existente; no se duplican sus campos laborales en Usuarios. El enlace representa la asociación guardada, no una elección todavía sin guardar.

Cambiar acceso o empleado no modifica el rol actual laboral ni las tres tablas de participantes. Se comprobaron filas completas de cabecera, detalles Pan/Torta/Bocadito, pivotes y empleados antes/después, sin diferencias. Tampoco cambia la credencial o estado de la cuenta.

## 6. Seguridad y último Administrador

Se reutilizan `auth`, cuenta activa, `auth.session` y `can:administrar-sistema` en rutas; el FormRequest aplica el mismo Gate. Un Operador recibe 403 incluso por URL directa, JSON o con CSRF válido. CSRF ausente recibe 419 en comprobación con middleware habilitado realmente.

La Action usa `DB::transaction(..., 3)` y el mismo orden de locks de crear/estado/password: roles de acceso por ID → actor actual → cuenta objetivo. Revalida actor activo/Administrador desde BD. Una lectura actual bloqueada impide degradar o desactivar el último Administrador activo; administradores inactivos no cuentan. No se protege un ID o username fijo, ni se cambia el comportamiento autorizado de cuentas compartidas.

El vínculo se comprueba nuevamente dentro de la transacción, con lectura bloqueada del empleado y de cualquier cuenta que lo use. El índice UNIQUE es la defensa final. Si colisiona después de validar, el controller devuelve error de empleado comprensible; se probó la colisión SQL y rollback sin cambios parciales. Los errores SQL genéricos siguen registrando solo mensaje fijo y SQLSTATE, sin bindings/credenciales.

Con dos procesos MariaDB reales, dos degradaciones simultáneas o una degradación junto a desactivación conservan un Admin activo. Dos cuentas intentando enlazar a la misma persona producen un éxito y un rechazo. Todos los escritores futuros de cuentas deberán seguir el protocolo de la Action; no se añade un trigger que gobierne SQL externo.

## 7. Pruebas y resultados

| Verificación | Resultado |
| --- | --- |
| Base previa acotada | 148 pruebas/1.381 aserciones aprobadas. |
| Suite completa `php tests/run-mariadb.php --do-not-cache-result` | **1.019 aprobadas/9.155 aserciones**, cero fallidas/omitidas. Incluye creación/password/estado, Unicode, Producción Pan LATA/UNIDAD, Torta/Bocadito, Historial, filtros, Productos y Personal. |
| Comprobación final `--filter='UsuariosEdicion'` | **44 aprobadas/413 aserciones**, después de reforzar el caso de sesión con login/provider reales. Incluye tres carreras de dos procesos. |
| Matriz anterior de Usuarios ampliada | Seis casos adicionales: GET editar y PUT/PATCH para invitado y Operador; incluidos en suite completa. |
| JavaScript: ejecución directa de los cinco archivos de `tests/JavaScript` | **55 casos aprobados**: Login 8, Producción 19, Productos 10, Sidebar 7, Usuarios 11. |
| `npm run build` | Aprobado. Único aviso preexistente: optimización opcional de fallback requiere fontaine; no se instala dependencia. |
| Pint `--test` de los nueve PHP afectados por esta tarea | Aprobado. No se reformatea el resto del proyecto. |
| `git diff --check` y revisión del diff | Aprobados; cambios anteriores preservados. |
| Chrome headless con vistas Blade/assets compilados | **44/44 combinaciones** a 320/375/768/1280 px, con y sin JS; sin overflow ni excepciones JS. Enlaces Editar/ficha, pestañas, turno según rol, FormData sin contraseña, errores, hover y foco de teclado verificados. Capturas móvil/escritorio revisadas. |

Aislamiento: MariaDB desechable sin red bajo `/tmp/sprint4-*`, datadir y esquema verificados antes de migrar. Fixtures ficticias en transacciones con rollback; carreras en bases vacías por caso, sin `migrate:fresh`, wipe, truncate o limpieza de datos operativos. Instancia de suite `/tmp/sprint4-28eef4b6c718` e instancia final acotada `/tmp/sprint4-f2dcd071f6fb` detenidas. HTTP usa sesiones de prueba/withoutVite; casos previos del conjunto también prueban driver database en la BD temporal.

Chrome: modelos/datos y sesión en memoria, peticiones interceptadas, assets locales compilados y fuentes externas bloqueadas/fallback. No usa ni envía formularios al backend operativo. La persistencia está acreditada por HTTP/MariaDB aparte; no se afirma un E2E Chrome→BD operativa. Artefactos temporales: `/tmp/usuarios-edicion-review` (HTML/resultados/capturas), scripts en `/tmp/usuarios-edicion-{render.php,browser.cjs}`.

Durante desarrollo se corrigió una llamada de query no soportada (`orWhereKey` → `orWhere('id', ...)`), se normalizaron fixtures de tests con refresh, se completó su categoría histórica y se conectó la sesión ficticia del render de errores. No se cambiaron reglas de Producción/Historial para acomodar pruebas. La suite global final indicada pasó; el refuerzo posterior de sesión pasó en la repetición acotada.

## 8. Límites y documentación

- Pendiente aceptación manual en navegador con las cuentas/sesiones locales; dispositivos físicos, Safari, fuentes reales y lector de pantalla no certificados por Chrome headless.
- Las cuentas compartidas estaban inactivas antes de empezar y así se dejaron. Esta tarea no las activa, renombra, cambia turnos o credenciales automáticamente.
- No se modifican datos operativos, migraciones o seeders en esta entrega. No requiere un paso de migración para revisar Editar usuario.
- Se conserva el límite existente: nombres relacionados de cuenta/persona son datos vivos, no snapshots. Esta edición no cambia datos laborales ni pivotes; corregir luego el nombre del empleado desde su módulo conserva el comportamiento previo de presentación histórica.
- No se incorpora auditoría nueva, recuperación de contraseña, control optimista de formularios obsoletos o administración de empleados desde Producción. Dos ediciones válidas consecutivas de una cuenta conservan el último guardado autorizado.
- Notas originales pertinentes de Obsidian: Usuarios, Empleados, Permisos, Controllers, Requests, Decisiones Laravel y Estado de proyecto. Se añade actualización fechada sin borrar historia. `mejora_asignado.md` no existe en checkout/Vault y no se recrea; Roadmap y notas de Bugs no requieren cambios por esta funcionalidad.

## 9. Comprobación manual

1. Iniciar sesión como Administrador y abrir Personal → Usuarios. Comprobar Editar junto a contraseña/estado y ambas pestañas en escritorio/móvil.
2. Abrir Editar de una cuenta de prueba autorizada. Ver valores actuales, ausencia de campos de contraseña y Cancelar sin guardar. No utilizar lotes ni cuentas reales para pruebas destructivas.
3. Cambiar username/rol/turno según corresponda y guardar. Ver mensaje de éxito y datos actualizados; la contraseña previa continúa autenticando sin usar la pantalla específica de cambio.
4. Vincular un empleado disponible; volver a editar y seguir «Editar empleado vinculado». Debe abrir exactamente su ficha en Empleados. El rol laboral permanece independiente del acceso.
5. En otra cuenta, el empleado ocupado no debe ofrecerse; una petición manipulada se rechaza. Retirar la asociación con «Sin empleado vinculado» no elimina al trabajador ni su historia.
6. Intentar retirar el rol del último Admin activo en entorno de prueba: debe mostrar error. Probar con otro Admin activo y comprobar el acceso posterior; cambiar el propio rol a Operador redirige al Dashboard.
7. Verificar con Operador que URLs de edición/escritura reciben 403. Confirmar navegación/Producción dentro de los permisos existentes y cuentas compartidas sin vínculo opcional.
8. Repetir creación de usuario, cambio de contraseña y activación/desactivación en entorno de pruebas. Revisar teclado/foco, errores, selección de turno y pestañas a tamaño móvil.

Listo para revisión manual desde el navegador; sin commit, push ni despliegue automático.
