# Panadería RS — Personal / Grupos, fase 1

Fecha: 2026-10-21.

**Estado: IMPLEMENTADO Y COMPROBADO en aislamiento.** Migración operativa, aceptación manual y fase 2 de rotación/selección automática: **PENDIENTES**.

## Diagnóstico inicial

Se leyó íntegramente AGENTS.md y se revisaron empleados, roles_produccion, categorias, turnos, sus modelos/migraciones, AdministrarEmpleados, Requests, rutas y vistas de Personal. También se revisaron las tres asociaciones históricas detalle_pan_empleado, detalle_torta_empleado y detalle_bocadito_empleado y el runner MariaDB existente.

Personal ya ofrecía Usuarios y Empleados mediante personal.layout, CSS compartido, formularios nativos y Gate administrar-sistema. AdministrarEmpleados revalida al actor y guarda rol/estado bajo transacción y bloqueos. No existían tablas, modelos ni rutas de Grupos. La infraestructura reutilizable permitió añadir la sección sin refactorizar módulos ajenos ni instalar dependencias.

Las tablas históricas tienen PK compuesta detalle/empleado y guardan el rol utilizado en esa producción. Esa información es un hecho histórico, distinto de la composición habitual de un equipo y del rol actual de una persona.

El checkout tenía 17 archivos modificados/nuevos de tareas anteriores, incluida la migración de hora_vitrina, sus pruebas, su informe y docs/PRODUCCION_UNIDADES_POR_FAMILIA.md. Se registraron huellas al comenzar y se verificó que los 17 permanecen byte por byte idénticos. La documentación anterior del Vault también se conservó.

Línea base: 176 pruebas / 1.470 aserciones aprobadas sobre Empleados, Personal/Producción y Tortas/Bocaditos.

## Estructura implementada

Migración nueva: database/migrations/2026_10_21_110000_create_grupos_produccion_tables.php.

| Tabla | Estructura |
| --- | --- |
| grupos_produccion | id; nombre VARCHAR(100); categoria_id FK a categorias; maestro_id FK a empleados; activo BOOLEAN default true; created_at y updated_at administrativos. |
| grupo_integrantes | grupo_id FK a grupos_produccion; empleado_id FK a empleados; PK compuesta (grupo_id, empleado_id). |

Se utilizó la estructura propuesta. El responsable singular se representa mediante maestro_id; los integrantes de esta fase son exclusivamente Ayudantes habituales. No se copian nombre, rol actual ni estado de empleados. Tampoco hay turno_id, tabla de rotación, calendario, conversión de datos o asociación a las tablas de producción.

Las cuatro FK usan RESTRICT para eliminación y actualización. La PK evita duplicar un integrante dentro del mismo grupo. No hay exclusividad global de Maestro/Ayudante ni UNIQUE de nombre: no existe una regla confirmada que impida reutilizar personas entre equipos o áreas.

La migración es aditiva y no modifica archivos anteriores. Su down se bloquea si existen grupos o integrantes; únicamente se comprobó down/up con configuración ficticia retirada explícitamente. No se ejecutó rollback ni migración sobre la base operativa. La creación de dos tablas mediante DDL MariaDB no promete atomicidad conjunta ante una caída: su aplicación requiere revisión y planificación en el entorno autorizado.

## Relaciones Eloquent

| Modelo | Relación o responsabilidad |
| --- | --- |
| GrupoProduccion | categoria(): belongsTo Categoria; maestro(): belongsTo Empleado; integrantes(): belongsToMany Empleado mediante grupo_integrantes, usando GrupoIntegrante. |
| GrupoIntegrante | Custom Pivot sin id artificial ni timestamps; grupo(): belongsTo GrupoProduccion y empleado(): belongsTo Empleado. Sus dos claves identifican las operaciones nativas. |
| Empleado | gruposComoMaestro(): hasMany GrupoProduccion; gruposComoAyudante(): belongsToMany GrupoProduccion. |
| Categoria | gruposProduccion(): hasMany GrupoProduccion. |

GrupoProduccion centraliza las categorías admitidas, las relaciones necesarias para consultar composición y problemasComposicion(). Esta última comprueba las relaciones actuales sin escribir datos: sirve tanto para advertencias de lectura como para la validación dentro de la transacción con empleados bloqueados. El listado precarga relaciones de la página, evitando consultas por cada integrante.

Las relaciones quedan disponibles para fase 2; no se agregó relación o escritura de grupo en un lote de producción. Turno y los modelos históricos permanecen sin modificaciones de esta tarea.

## Reglas y validaciones

| Categoría real | Maestro | Ayudantes habituales | Disponibilidad del equipo |
| --- | --- | --- | --- |
| Pan | Exactamente uno, activo y con rol actual Maestro. | Uno o más activos con rol actual Ayudante; se admiten equipos de uno y de dos. | Sin turno fijo. Rotación cada siete días pendiente. |
| Torta | Exactamente uno, activo y con rol actual Maestro. | Ninguno. | Solo Mañana, derivado de su categoría. |
| Bocadito | Exactamente uno, activo y con rol actual Maestro. | Exactamente uno, activo y con rol actual Ayudante. | Solo Mañana, derivado de su categoría. |

Las categorías y roles se resuelven desde los catálogos reales por sus referencias/nombres; no hay IDs fijos. Practicante no se acepta como Ayudante habitual.

GuardarGrupoRequest valida nombre requerido de hasta 100 caracteres sin controles, categoría permitida/existente, responsable singular existente, integrantes como lista de referencias existentes sin duplicados y activo boolean explícito. Normaliza espacios del nombre y representa los checkboxes omitidos como lista vacía, necesaria para Torta. No convierte arrays o entradas malformadas en referencias válidas. Los valores extra de rol/turno no se persisten.

El guardado revalida bajo bloqueo que todos estén activos y tengan el rol actual correcto, que el Maestro no aparezca también entre Ayudantes y que la composición corresponda a la categoría. Se conserva la validación estructural en Request y la comprobación de composición en servidor, sin depender del navegador. La Action espera datos estructuralmente validados, conforme al patrón del proyecto.

Cambiar categoría está permitido con composición válida. Guardar desde el formulario exige composición íntegra incluso cuando se elige estado Inactivo. La acción específica Desactivar siempre se permite aunque existan asignaciones obsoletas. Activar o reactivar vuelve a validar toda la composición.

Si se desactiva o cambia el rol de una persona después de configurar un equipo, se conserva el grupo, su estado y sus asignaciones. Listado y edición muestran una advertencia y explican qué corregir. Las asignaciones incompatibles siguen visibles para retirarlas/reemplazarlas explícitamente; no se reparan ni borran silenciosamente.

## Seguridad y transacciones

Las seis acciones web de Grupos —incluido PUT/PATCH para actualizar— reutilizan auth, EnsureUsuarioActivo, auth.session y can:administrar-sistema. Los Requests vuelven a exigir Administrador activo y AdministrarGrupos comprueba al actor actual desde BD bajo sharedLock. Operador recibe 403 incluso con datos válidos; invitado va al login. CSRF real se comprobó. No existe DELETE ni mutación mediante GET.

AdministrarGrupos utiliza DB::transaction con tres intentos. Al editar bloquea el grupo; consulta categoría y roles mediante lecturas actuales compartidas, y empleados mediante lockForUpdate por ID ordenado. Se mantiene el orden roles → empleados usado por AdministrarEmpleados.

Los integrantes actuales se leen con lockForUpdate antes de aplicar diferencias mediante detach/attach del custom Pivot. Esto evita que una lectura consistente anterior conserve un equipo obsoleto después de esperar a otro editor. Grupo y asignaciones se confirman o revierten juntos; se inyectó un fallo SQL en la segunda inserción y se verificó rollback tanto al crear como al editar, incluyendo nombre y asignaciones retiradas.

Cinco carreras con dos procesos reales comprobaron:

1. Cambio de rol laboral anterior al guardado: el grupo obsoleto se rechaza.
2. Cambio de estado laboral anterior al guardado: el grupo obsoleto se rechaza.
3. Grupo guardado antes del cambio de rol: se conservan ambas operaciones y aparece advertencia.
4. Grupo guardado antes de la desactivación laboral: se conserva el grupo y aparece advertencia.
5. Dos ediciones del mismo grupo: la segunda trabaja sobre los integrantes confirmados por la primera y guarda exactamente su equipo solicitado.

El test observa la segunda consulta de bloqueo mediante PROCESSLIST mientras el primer proceso conserva su lock: MariaDB puede mostrar Statistics antes de publicar la transacción en INNODB_TRX. No se utiliza un sleep arbitrario como evidencia de solapamiento.

La última edición serializada prevalece; no se introdujo versionado optimista. Escrituras externas o futuras deben respetar las validaciones y el protocolo; las FK/PK no verifican por sí solas rol/estado/composición entre tablas.

## Interfaz

Personal mantiene su layout y paleta verde bosque/beige y ofrece Usuarios | Empleados | Grupos. El sidebar conserva Personal activo en las rutas nuevas. Se reutilizan botones, formularios, avisos y estilos existentes; solo se añaden clases específicas para filtros, checkboxes y advertencias.

Grupos muestra nombre, categoría, Maestro y Ayudantes con nombre/#ID, disponibilidad, estado y Editar/Activar/Desactivar. Tiene filtro GET por categoría, estado vacío, orden estable por nombre/ID y paginación de quince con filtro conservado. Los filtros inválidos se presentan sin bucle de redirección ni resultados engañosos.

Crear/Editar permite nombre, categoría, Maestro, checkboxes de Ayudantes y estado. Los nuevos candidatos son activos con rol compatible; al editar se incluyen además las asignaciones anteriores incompatibles claramente marcadas. old input se sanea por tipos y conserva selección tras errores. Los nombres se escapan con Blade. Todos los formularios funcionan sin un JavaScript nuevo.

La disponibilidad Solo Mañana es información derivada de categoría en esta fase administrativa: no aplica restricciones adicionales al formulario de Producción ni modifica turnos de cuentas.

## Archivos de esta tarea

Rutas relativas al repositorio. Los archivos locales previos de hora_vitrina no forman parte de esta lista de modificaciones nuevas.

| Archivo | Motivo |
| --- | --- |
| database/migrations/2026_10_21_110000_create_grupos_produccion_tables.php | Dos tablas nuevas, FK restrictivas, PK compuesta y down protegido. |
| app/Models/GrupoProduccion.php | Entidad de grupo, composición, relaciones y disponibilidad. |
| app/Models/GrupoIntegrante.php | Custom Pivot con ambas claves. |
| app/Models/Empleado.php | Inversas de Maestro/Ayudante habitual. |
| app/Models/Categoria.php | Inversa de grupos por categoría. |
| app/Actions/Personal/AdministrarGrupos.php | Guardado/estado transaccionales con revalidación y lecturas actuales. |
| app/Http/Controllers/GrupoController.php | Coordinación de consultas, formularios y mutaciones. |
| app/Http/Requests/GestionarGrupoRequest.php | Autorización y mensajes españoles comunes. |
| app/Http/Requests/GuardarGrupoRequest.php | Normalización y estructura del guardado. |
| app/Http/Requests/CambiarEstadoGrupoRequest.php | Estado boolean explícito. |
| app/Http/Requests/ConsultarGruposRequest.php | Filtro/página y presentación de errores GET. |
| routes/web.php | Rutas administrativas nuevas bajo el grupo existente. |
| resources/views/personal/layout.blade.php | Tercera pestaña. |
| resources/views/components/sidebar.blade.php | Estado activo de Personal también en Grupos. |
| resources/views/grupos/index.blade.php | Listado, filtro, estado, avisos y paginación. |
| resources/views/grupos/form.blade.php | Crear/Editar sin dependencias nuevas. |
| resources/views/grupos/advertencias.blade.php | Advertencia compartida de composición actual. |
| resources/css/usuarios.css | Presentación específica de Grupos, con tokens existentes. |
| tests/Feature/GruposTest.php | Reglas, CRUD, permisos, CSRF, históricos y rollback. |
| tests/Feature/GruposMigrationTest.php | Preservación, estructura, FK/PK y down/up aislados. |
| tests/Feature/GruposConcurrenciaTest.php | Cinco carreras reales. |
| tests/Support/grupo-concurrente.php | Worker con sincronización explícita para esas carreras. |
| tests/Feature/EmpleadosTest.php | Actualiza exclusivamente la expectativa de dos a tres pestañas. |
| dato_optimizar/informe_modulo_grupos.md | Informe solicitado. |

AdministrarEmpleados, los Requests/Actions/vistas de Producción, sus cálculos, turnos de usuarios y tablas históricas no se modificaron en esta tarea.

## Resultados de pruebas

| Verificación | Resultado final |
| --- | --- |
| Línea base de Empleados, Personal/Producción y Tortas/Bocaditos | 176 aprobadas / 1.470 aserciones. |
| Regresión ampliada de Grupos, migración, concurrencia, Empleados, Personal/Producción, Tortas/Bocaditos e Historial | **310 aprobadas / 2.609 aserciones**, sin fallidas ni omitidas. |
| Suite completa: php tests/run-mariadb.php --do-not-cache-result | **1.109 aprobadas / 9.979 aserciones**, sin fallidas ni omitidas. Incluye los 76 casos nuevos de esta fase. |
| Cinco archivos JavaScript ejecutados directamente con node | **58 aprobadas**: Login 8, Producción 20, Productos 10, Sidebar 9 y Usuarios 11. |
| npm run build | Aprobado. Aviso preexistente de dependencia opcional fontaine; sin error de compilación. |
| Pint --test sobre los 17 PHP afectados | Aprobado. |
| git diff --check y revisión de cambios nuevos/archivos añadidos | Aprobados. |
| Comparación de huellas de los 17 archivos locales anteriores | Conservados íntegramente. |

Comando de la regresión ampliada:

```bash
php tests/run-mariadb.php --filter='GruposTest|GruposMigrationTest|GruposConcurrenciaTest|EmpleadosTest|PersonalProduccionTest|ProduccionTortaBocaditoStoreTest|HistorialProduccionTest' --do-not-cache-result
```

Las pruebas usan exclusivamente el runner existente con MariaDB desechable sin red bajo /tmp, datadir/base verificados por IsolatedMariaDb y bases ficticias nuevas para DDL/concurrencia. Los casos HTTP nuevos utilizan fixtures, sesiones array y withoutVite; los assets se verificaron por separado con build. El runner requirió ejecución fuera del sandbox para iniciar el servidor, con autorización de escalamiento. No se modificó el runner.

Instancias aprobadas de referencia: línea base /tmp/sprint4-3b1be31e41ce; regresión final /tmp/sprint4-dc97edd87275; suite completa /tmp/sprint4-eedcc1e64e6d. El runner confirmó su detención y conservó artefactos ficticios para inspección.

Las pasadas intermedias detectaron una llamada de consulta no disponible en la edición y la comprobación explícita de activo que faltaba en la autorización del Request; se corrigieron. Se ajustaron además la detección de concurrencia a PROCESSLIST y la restauración del empleado del test desde una instancia fresca. Los fallos intermedios se resolvieron y la suite final completa pasó.

## Documentación de Obsidian

Se inspeccionó la estructura, se buscó el tema y se leyeron íntegramente las notas afectadas, reutilizando también la lectura de esta conversación cuando su contenido ya era conocido. Se actualizaron únicamente siete originales con entradas fechadas y con autorización de escalamiento tras el bloqueo del filesystem. Se conservaron la historia y las entradas previas de hora_vitrina.

Raíz: /home/lukagfv/Documentos/Obsidian Vault/PROYECTO_LARAVEL/.

| Nota | Actualización |
| --- | --- |
| PERSONAL Y USUARIOS/Empleados.md | Tercera sección, equipos habituales, reglas por categoría y advertencias laborales. |
| PERSONAL Y USUARIOS/Permisos.md | Administración exclusiva y límites de la nueva sección. |
| LARAVEL/Models.md | Modelos, relaciones, Pivot y comprobación compartida de composición. |
| LARAVEL/Services.md | Responsabilidades de controller/Requests/Action, transacción y concurrencia. |
| BASE DE DATOS/Migraciones.md | Estructura aditiva, conservación, FK/PK, rollback y aplicación pendiente. |
| DECISIONES/Decisiones Laravel.md | Fecha, contexto, opciones, decisión, motivo y consecuencias. Se respetó su nombre real. |
| INICIO/Estado de proyecto.md | Resumen comprobable y pendientes de operación/fase 2. |

No se crearon carpetas ni copias del Vault en el repositorio. mejora_asignado.md sigue ausente en checkout/Vault y no se recreó. No correspondió cambiar Bugs ni reordenar Roadmap: es una funcionalidad nueva y la rotación permanece explícitamente pendiente.

## Comprobación manual

1. En un entorno de revisión autorizado, revisar el estado de migraciones y SQL pretend limitado al archivo nuevo. **Solicitar autorización antes de aplicar una migración a la base operativa.** El comando selectivo preparado, no ejecutado allí, es:

   ```bash
   php artisan migrate --path=database/migrations/2026_10_21_110000_create_grupos_produccion_tables.php
   ```

2. Con el esquema disponible y los assets compilados, entrar como Administrador y abrir Personal. Comprobar Usuarios | Empleados | Grupos y la pestaña/sidebar activos al entrar en Grupos.
3. Crear un grupo Pan con un Maestro y un Ayudante activo. Crear otro con dos Ayudantes. Confirmar que ninguno solicita ni guarda turno fijo.
4. Crear Torta con solo Maestro y Bocadito con Maestro más un Ayudante. Confirmar que ambos indican Solo Mañana. Intentar las composiciones incompatibles y comprobar rechazo sin grupos parciales.
5. Filtrar por categoría; comprobar estado vacío y paginación si hay más de quince grupos. Los grupos de otras categorías no deben mezclarse en el resultado filtrado.
6. Editar nombre, Maestro, Ayudantes y categoría con composición válida. Verificar que otro grupo que comparta personas conserva su configuración.
7. Desactivar y reactivar. Desde Empleados, desactivar un integrante o cambiar su rol. Volver a Grupos: debe aparecer la advertencia manteniendo las asignaciones. La reactivación debe rechazarse hasta corregir el equipo; Desactivar debe seguir disponible.
8. Provocar un error de formulario y comprobar que conserva nombre/selecciones compatibles. Revisar controles, foco y lectura en móvil/escritorio, incluidos nombres largos y advertencias.
9. Acceder como Operador e intentar URLs y POST/PATCH válidos de Grupos: esperar 403. Un invitado debe ir al login.
10. Consultar Historial antes/después de las ediciones/desactivaciones de un grupo: participantes reales, roles históricos, cantidades, fotografías y horas deben conservarse. No se proponen pruebas destructivas ni lotes ficticios sobre datos operativos.

## Riesgos y pendientes de fase 2

- **PENDIENTE de autorización:** aplicar la migración nueva en la base operativa antes de usar Grupos allí. No se consultó ni alteró esa base, no se ejecutaron seeders y no se inspeccionó ni modificó .env. Los pendientes anteriores de hora_vitrina permanecen separados.
- **PENDIENTE:** aceptación visual/manual en navegador, dispositivos y sesiones/operación reales. HTTP/DOM y build no certifican geometría ni un recorrido navegador → base operativa. No se ejecutó navegador real en esta tarea.
- **PENDIENTE:** rotación de equipos completos Pan entre Mañana/Noche cada siete días. Falta definir fecha de referencia y tratamiento de cambios de equipos, excepciones y futuras consultas por período. No se calculó rotación ni se creó tabla anticipada.
- **PENDIENTE:** selección de equipos/empleados al registrar producción y confirmación de quién participó realmente. Editar un grupo nunca debe modificar los pivotes históricos.
- La composición habitual de Torta sin Ayudantes se limita a Grupos. La validación actual de participantes de Producción sigue exigiendo Maestro/Ayudante en las tres familias y no se cambió por instrucción expresa. Su futura integración necesita un contrato propio.
- Un grupo puede quedar activo con composición obsoleta tras un cambio laboral; se muestra advertencia, sin alterar automáticamente configuración ni historia. No hay asignación automática en esta fase que lo utilice.
- Las reglas de rol/composición requieren los escritores de aplicación; se conservaron FK/PK y bloqueos, sin triggers, exclusividad global ni control optimista de formularios.
- Sin commit, push ni despliegue automático. El contrato getAuthPasswordName() no se intervino ni se declara resuelto por esta tarea.
