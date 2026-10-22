# Informe de carga inicial de empleados y grupos — Panadería RS

Fecha de recuperación y comprobación: 2026-10-21. Rutas relativas al repositorio salvo indicación expresa.

## 1. Diagnóstico después de recuperar la sesión interrumpida

**IMPLEMENTADO Y COMPROBADO en aislamiento según la evidencia documental recuperada:** el comando `personal:cargar-equipos-iniciales`, su Action, las pruebas y el worker concurrente ya existen. `php artisan help personal:cargar-equipos-iniciales` confirma que Laravel registra el comando. El informe solicitado no existía y se crea en esta revisión. No se reimplementó ni se modificó código funcional.

**PENDIENTE: aplicación operativa.** La consulta actual READ ONLY y las dos vistas previas confirman que todavía hay tres empleados y un grupo anterior: faltan seis personas y los cuatro grupos solicitados; Joshelo continúa como Ayudante. El estado actual coincide con las notas de la sesión recuperada, que registran únicamente `--dry-run` sobre la base del proyecto y dejan `--aplicar` pendiente. No hay evidencia de una carga inicial aplicada y persistida. Esta comprobación de estado no equivale a un registro exhaustivo de todos los comandos ejecutados históricamente.

Git al comenzar: rama `master`, HEAD `3e2afa9` (`mejorardell diseño`), 45 archivos locales con cambios: 22 modificados y 23 sin seguimiento, sin cambios preparados en el índice. Los cuatro archivos específicos de carga figuran sin seguimiento. Comparando con `/tmp/panaderia-rs-inicio-huellas.json`, 44 archivos conservan exactamente su contenido; solo difiere `tests/Feature/CargaInicialPersonalTest.php`, coherente con la ampliación de cobertura documentada en la recuperación anterior. La huella permite comprobar diferencias, no reconstruir por sí sola su autoría.

El primer intento de consulta dentro del sandbox falló con `QueryException`, código 2002. Se repitió mediante escalamiento autorizado y finalizó correctamente. Las consultas operativas usaron `SET TRANSACTION READ ONLY`, transacción y rollback. No se ejecutaron migraciones, seeders, carga real, commit ni push en esta revisión.

## 2. Archivos creados y modificados

### Implementación recuperada de esta carga

| Archivo ya presente | Estado en Git | Responsabilidad comprobada |
| --- | --- | --- |
| `app/Actions/Personal/CargarEquiposIniciales.php` | Sin seguimiento | Define siete personas/cuatro equipos; plan READ ONLY, detección de conflictos y aplicación transaccional mediante las Actions existentes. |
| `app/Console/Commands/CargarEquiposInicialesCommand.php` | Sin seguimiento | Comando y opciones `--dry-run`, `--aplicar`, `--administrador`, `--confirmar-joshelo`; limitado a `local`/`testing`. |
| `tests/Feature/CargaInicialPersonalTest.php` | Sin seguimiento | Pruebas de carga, conservación, conflictos, rollback, idempotencia y concurrencia. La recuperación anterior amplió carga parcial, siete ausentes y referencias con IDs desplazados. |
| `tests/Support/carga-personal-concurrente.php` | Sin seguimiento | Worker de aplicación sobre la instancia MariaDB aislada de pruebas. |

La Action reutiliza `app/Actions/Personal/AdministrarEmpleados.php` y `app/Actions/Personal/AdministrarGrupos.php`. No hay un seeder nuevo para esta carga ni una migración específica de sus datos. La migración de Grupos ya figura en la tabla operativa de migraciones; no fue aplicada por esta revisión.

### Dependencias y otros cambios encontrados

El checkout también contiene el módulo de Grupos: `AdministrarGrupos`, `GrupoController`, cuatro Requests de Grupos, modelos `GrupoProduccion`/`GrupoIntegrante`, migración `2026_10_21_110000_create_grupos_produccion_tables.php`, tres vistas `resources/views/grupos/`, tres archivos Feature de Grupos y `tests/Support/grupo-concurrente.php`. Sus modificaciones relacionadas incluyen `app/Models/Empleado.php`, `app/Models/Categoria.php`, `routes/web.php`, las vistas de navegación de Personal y `resources/css/usuarios.css`. El inventario completo al inicio se incluye al final; los cambios de hora de vitrina, Producción e Historial son trabajo previo y no se atribuyen a la creación de este informe.

### Cambios de esta revisión

- Creado: `dato_optimizar/informe_carga_equipos_iniciales.md`, para recuperar el informe faltante con evidencia y límites explícitos.
- Documentación original afectada: `LARAVEL/Services.md` del Vault, únicamente para registrar la ruta del informe y la comprobación actual, conservando su historia.
- Ninguno de los 45 archivos locales iniciales se modifica como parte de esta revisión. No se modifica `mejora_asignado.md`: no se encontró ese archivo en el repositorio ni en la documentación del proyecto.

## 3. Empleados existentes, nuevos y posibles conflictos

Lectura actual de MariaDB local, sin escrituras:

| ID | Nombre persistido | Rol actual | Estado | Tratamiento previsto |
| --- | --- | --- | --- | --- |
| 1 | Carlos M. | Maestro | Activo | Conservar; no pertenece a las siete personas del plan. |
| 2 | Ana R. | Ayudante | Activo | Conservar; no pertenece a las siete personas del plan. |
| 3 | joshelo | Ayudante | Activo | Reutilizar identidad; promoción a Maestro condicionada a confirmación explícita. |

| Persona del plan | Situación observada | Rol previsto | Operación condicionada |
| --- | --- | --- | --- |
| Joshelo | Coincidencia única: `joshelo` #3, Ayudante | Maestro | Modificar solo el rol actual; conservar ID y nombre persistido. |
| Joel | No encontrado | Ayudante | Crear activo. |
| Nilson | No encontrado | Maestro | Crear activo. |
| Paul | No encontrado | Ayudante | Crear activo. |
| Chuy | No encontrado | Maestro | Crear activo. |
| Ibar | No encontrado | Maestro | Crear activo. |
| Alberto | No encontrado | Ayudante | Crear activo. |

Resultado condicionado: **seis altas, una promoción y ninguna ficha duplicada de Joshelo**. No se crean cuentas de acceso. No se fijan los IDs futuros de las altas; roles y categorías se resuelven desde sus catálogos.

El conflicto real es Joshelo #3: su rol actual no permite usarlo como Maestro sin `--confirmar-joshelo=3`. Además, figura como Ayudante del grupo anterior #1, «produccion de panaderia 1». Promoverlo dejaría incompatible esa asignación habitual; la carga advierte y conserva la composición anterior. Carlos, Ana, cuentas y participaciones históricas se conservan.

No se observaron homónimos de las siete personas bajo la comparación implementada. La coincidencia de nombre no acredita identidad humana ni detecta alias o nombres completos distintos. El código también bloquea homónimos, inactivos, variantes no exactas aceptadas por la collation, roles incompatibles de otras personas, catálogos ausentes/ambiguos y grupos coincidentes incompatibles. No intenta corregirlos automáticamente.

## 4. Grupos que se crearán o reutilizarán

| Grupo | Categoría | Maestro | Ayudantes | Vista previa actual |
| --- | --- | --- | --- | --- |
| Panaderos 1 | Pan | Joshelo | Joel | Crear |
| Panaderos 2 | Pan | Nilson | Paul | Crear |
| Tortas | Torta | Chuy | Ninguno | Crear |
| Bocaditos | Bocadito | Ibar | Alberto | Crear |

Ninguno de esos cuatro grupos existe actualmente. Se conserva el grupo #1 «produccion de panaderia 1», categoría Pan, Carlos #1 responsable, Ana #2 y Joshelo #3 ayudantes. Si el plan se aplicara sin cambios externos, quedarían nueve empleados, cinco grupos y cinco asignaciones en `grupo_integrantes` (dos anteriores y tres nuevas).

En una repetición o carga parcial se reutilizan personas y grupos compatibles sin reescribirlos ni cambiar sus timestamps. Un grupo del mismo nombre con diferente composición, categoría o estado bloquea la carga completa. Los grupos representan equipos habituales; no asignan automáticamente participantes a Producción, ni implementan rotación de turnos. La composición de Tortas sin ayudantes corresponde al módulo de Grupos; no cambia las validaciones de Producción.

## 5. Resultado de la vista previa

Comandos ejecutados de nuevo en esta revisión, exclusivamente en modo lectura:

```bash
php artisan personal:cargar-equipos-iniciales --dry-run --administrador=1
```

**Código de salida 1, bloqueo esperado:** Joshelo #3 tiene rol Ayudante y se requiere Maestro. Muestra seis altas y cuatro grupos propuestos, advierte sobre el grupo anterior y termina con «Carga bloqueada. No se ha modificado ningún dato».

```bash
php artisan personal:cargar-equipos-iniciales --dry-run --administrador=1 --confirmar-joshelo=3
```

**Código de salida 0:** plan condicionado de seis altas, promoción de Joshelo y cuatro grupos nuevos. Mantiene la advertencia sobre la incompatibilidad del grupo anterior y termina con «Solo vista previa. Ningún dato modificado. La carga real requiere autorización previa y --aplicar».

El flag en este segundo dry-run sirve para examinar el plan condicionado: **no confirma la identidad, no constituye autorización operativa y no cambia el rol**.

Se compararon las lecturas completas seleccionadas y las huellas de las tablas de negocio antes/después: coincidencia exacta. Empleados: 3; grupos: 1; integrantes: 2; cabeceras de Producción: 5; detalles Pan: 6; participaciones Pan: 12; detalles y participaciones Torta/Bocadito: 0. También coinciden los catálogos y metadatos de usuarios seleccionados. El control acredita ausencia de cambios en lo inspeccionado durante estas comprobaciones, no una auditoría global del servidor.

Evidencia auxiliar de esta revisión en `/tmp/panaderia-informe-evidencias/`: `antes.jsonl`, `despues.jsonl`, `vista-previa-sin-confirmacion.txt` y `vista-previa-condicionada.txt`. Son artefactos temporales; este informe conserva el resultado relevante.

## 6. Pruebas ejecutadas y resultados

### Resultado recuperado de la sesión anterior

Las notas originales `LARAVEL/Services.md`, `PERSONAL Y USUARIOS/Empleados.md`, `BASE DE DATOS/Migraciones.md` e `INICIO/Estado de proyecto.md` registran **232 pruebas aprobadas, 1.883 aserciones, cero fallidas y cero omitidas**, con:

```bash
php tests/run-mariadb.php --filter='CargaInicialPersonalTest|GruposTest|GruposMigrationTest|GruposConcurrenciaTest|EmpleadosTest|PersonalProduccionTest|HistorialProduccionTest' --do-not-cache-result
```

El runner y `Tests\Support\IsolatedMariaDb` fueron revisados: instancia desechable sin red en `/tmp/sprint4-*`, conexión y datadir/base comprobados antes de migrar, bases nuevas y fixtures ficticios. Los `--aplicar` y llamadas a `aplicar()` de las pruebas corresponden a esas bases aisladas, no prueban que se haya aplicado la carga operativa.

La cobertura existente comprueba vista previa sin escrituras, promoción condicionada, altas completas/parciales, repetición sin reescrituras, IDs distintos a los operativos, conservación de cuentas/personas/grupo anterior e historia de las tres familias, homónimos, roles/estados, catálogos, actor e identidad, rollback por fallo de pivote, revalidación y dos cargas concurrentes.

Las notas explican un fallo intermedio de comparación entre atributos en memoria y filas recargadas, corregido comparando valores persistidos antes de la regresión final aprobada. El código actual contiene esa comparación. La caché de Pest no contiene resultados de `CargaInicialPersonalTest`; no permite certificar por sí sola las 232 pruebas. La cifra se presenta como **resultado documental recuperado**, no como una nueva ejecución en esta revisión.

### Comprobaciones ejecutadas ahora

| Comprobación | Resultado |
| --- | --- |
| `php artisan help personal:cargar-equipos-iniciales` | Aprobada; comando y cuatro opciones registrados. |
| `php -l` sobre Action, Command, Feature test y worker de carga | Cuatro archivos sin errores de sintaxis. |
| `vendor/bin/pint --test app/Actions/Personal/CargarEquiposIniciales.php app/Console/Commands/CargarEquiposInicialesCommand.php tests/Feature/CargaInicialPersonalTest.php tests/Support/carga-personal-concurrente.php` | Aprobada, sin modificar archivos. |
| `git diff --check` | Aprobada. |
| Dry-run sin confirmación | Bloqueo correcto, salida 1; sin cambios. |
| Dry-run con confirmación simulada | Plan condicionado válido, salida 0; sin cambios. |
| Comparación READ ONLY antes/después | Coincidencia de filas seleccionadas y huellas de negocio. |

No se repitieron las pruebas con escritura en MariaDB temporal, la suite completa, JavaScript, build ni navegador: esta tarea recupera documentación y no cambia código. La evidencia previa de aplicación/rollback/concurrencia sigue limitada al aislamiento descrito; la carga operativa y su aceptación permanecen pendientes.

## 7. Comando exacto para aplicar la carga inicial

**Solo para una ejecución futura, después de confirmar identidad de Joshelo #3, aceptar el efecto sobre el grupo anterior y autorizar expresamente la carga. No ejecutado en esta revisión:**

```bash
php artisan personal:cargar-equipos-iniciales --aplicar --administrador=1 --confirmar-joshelo=3
```

Ejecutar desde la raíz del repositorio, en el entorno local comprobado. Los IDs 1 y 3 proceden de la lectura actual, no son constantes del código. Antes de usarlo, repetir la vista previa condicionada y verificar que esos IDs y el plan siguen correspondiendo a las personas correctas. Sin `--aplicar`, el comando solo previsualiza; `--dry-run` y `--aplicar` juntos se rechazan. No cambiar el entorno para eludir la restricción a `local`/`testing`.

## 8. Pendientes y recomendaciones

- **PENDIENTE:** confirmar que `joshelo` #3 es la persona indicada y autorizar su promoción y la carga completa. Esta tarea no concede esa autorización.
- **PENDIENTE:** decidir cómo resolver la incompatibilidad que la promoción dejaría en el grupo anterior. La carga lo conserva y advierte; no lo reconfigura.
- **PENDIENTE:** aplicar únicamente cuando exista autorización, previa nueva vista previa. Después, comprobar composición y repetición sin reescrituras. No ejecutar seeders generales ni migraciones como sustituto de esta carga.
- **PENDIENTE:** revisión operativa de identidades y equipos; ausencia de homónimos por nombre no sustituye esa revisión.
- Preservar los cambios locales ajenos a esta tarea. La implementación y las pruebas siguen sin seguimiento en Git; su incorporación futura al control de versiones requiere una tarea posterior. Aquí no se hizo commit ni push.
- No se abre un bug nuevo ni una decisión arquitectónica: esta revisión recupera evidencia y el informe faltante. No corresponde alterar `PENDIENTES/Bugs.md`, Roadmap u otras notas ajenas a este resultado.

## Anexo: inventario completo de Git al comenzar

`M` indica archivo modificado; `??`, archivo sin seguimiento. Todo este inventario ya estaba presente antes de crear el informe:

```text
 M app/Actions/Produccion/RegistrarProduccionTorta.php
 M app/Http/Requests/RegistrarProduccionTortaRequest.php
 M app/Models/Categoria.php
 M app/Models/DetalleTorta.php
 M app/Models/Empleado.php
 M docs/PRODUCCION_UNIDADES_POR_FAMILIA.md
 M resources/css/usuarios.css
 M resources/views/components/sidebar.blade.php
 M resources/views/history/index.blade.php
 M resources/views/personal/layout.blade.php
 M resources/views/produccion/index.blade.php
 M routes/web.php
 M tests/Feature/AutorizacionRolesTest.php
 M tests/Feature/EmpleadosTest.php
 M tests/Feature/HistorialProduccionTest.php
 M tests/Feature/PersonalProduccionTest.php
 M tests/Feature/ProduccionConcurrenciaTest.php
 M tests/Feature/ProduccionTortaBocaditoStoreTest.php
 M tests/Feature/ProductionObservationsMigrationTest.php
 M tests/Feature/TurnosOperadorTest.php
 M tests/JavaScript/ProduccionCantidadTest.mjs
 M tests/Support/productos-fixtures.php
?? app/Actions/Personal/AdministrarGrupos.php
?? app/Actions/Personal/CargarEquiposIniciales.php
?? app/Console/Commands/CargarEquiposInicialesCommand.php
?? app/Http/Controllers/GrupoController.php
?? app/Http/Requests/CambiarEstadoGrupoRequest.php
?? app/Http/Requests/ConsultarGruposRequest.php
?? app/Http/Requests/GestionarGrupoRequest.php
?? app/Http/Requests/GuardarGrupoRequest.php
?? app/Models/GrupoIntegrante.php
?? app/Models/GrupoProduccion.php
?? database/migrations/2026_10_21_100000_add_hora_vitrina_to_detalle_torta.php
?? database/migrations/2026_10_21_110000_create_grupos_produccion_tables.php
?? dato_optimizar/informe_hora_vitrina_tortas.md
?? dato_optimizar/informe_modulo_grupos.md
?? resources/views/grupos/advertencias.blade.php
?? resources/views/grupos/form.blade.php
?? resources/views/grupos/index.blade.php
?? tests/Feature/CargaInicialPersonalTest.php
?? tests/Feature/GruposConcurrenciaTest.php
?? tests/Feature/GruposMigrationTest.php
?? tests/Feature/GruposTest.php
?? tests/Support/carga-personal-concurrente.php
?? tests/Support/grupo-concurrente.php
```
