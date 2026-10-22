# Panadería RS — Hora de vitrina de Tortas

Fecha: 2026-10-21. Estado: **IMPLEMENTADO Y COMPROBADO en aislamiento**. Aplicación de la migración operativa y aceptación manual: **PENDIENTES**.

## Diagnóstico inicial

Se revisaron AGENTS.md, todas las migraciones que afectan a detalle_torta, DetalleTorta, RegistrarProduccionTortaRequest, RegistrarProduccionTorta, RegistrarProduccionPorCantidad, ProduccionController, ambas vistas, produccion.js y las pruebas de Producción/Historial y sus dependencias.

El checkout permitía registrar cantidad, forma, foto opcional, observación y participantes de Torta, sin columna, validación ni presentación de hora de vitrina. La cabecera produccion identifica fecha/familia/turno y se comparte entre lotes; cada POST crea un detalle independiente. La hora corresponde a ese detalle.

La arquitectura existente ya separa Request, controller coordinador y Actions: RegistrarProduccionTorta aporta atributos específicos al callback que ejecuta RegistrarProduccionPorCantidad dentro de su transacción. El manejo de fotografía incluye almacenamiento public con nombre generado y compensación ante fallo SQL. El JavaScript ya habilita/deshabilita controles mediante campo-condicional, data-mostrar-en y data-campo, incluyendo limpieza al cambiar familia o cancelar.

Al comenzar solo estaba modificado docs/PRODUCCION_UNIDADES_POR_FAMILIA.md: una línea en blanco añadida. Se conservó exactamente ese cambio y no se editó el archivo. No se ejecutó commit ni push.

## Archivos modificados

Rutas relativas a la raíz del repositorio:

| Archivo | Cambio y motivo |
|---|---|
| database/migrations/2026_10_21_100000_add_hora_vitrina_to_detalle_torta.php | Nueva columna TIME nullable y reversión protegida. |
| app/Models/DetalleTorta.php | Permite hora_vitrina en fillable. |
| app/Http/Requests/RegistrarProduccionTortaRequest.php | Exige HH:MM en servidor y presenta mensajes españoles. |
| app/Actions/Produccion/RegistrarProduccionTorta.php | Persiste la hora mediante el callback transaccional existente. |
| resources/views/produccion/index.blade.php | Campo exclusivo de Torta, recuperación de old input, error accesible y hora en tarjeta reciente. |
| resources/views/history/index.blade.php | Hora dentro de los detalles de cada Torta y ausencia histórica explícita. |
| tests/Feature/ProduccionTortaBocaditoStoreTest.php | Validación/guardado, límites horarios, independencia, conservación de datos/foto/equipo y controles Blade. |
| tests/Feature/HistorialProduccionTest.php | Payload actualizado y presentación/conservación de históricos NULL en ambas consultas. |
| tests/Feature/ProductionObservationsMigrationTest.php | Tipo/nullabilidad, filas y esquemas previos, down protegido y reaplicación. |
| tests/Feature/AutorizacionRolesTest.php | Añade hora válida al fixture Torta, conservando verificaciones de permisos. |
| tests/Feature/PersonalProduccionTest.php | Añade hora válida al fixture Torta sin cambiar reglas de personal. |
| tests/Feature/TurnosOperadorTest.php | Añade hora válida al fixture Torta, conservando restricciones de turno. |
| tests/Feature/ProduccionConcurrenciaTest.php | Añade hora válida al fixture Torta, manteniendo la prueba real de dos conexiones. |
| tests/Support/productos-fixtures.php | Actualiza el payload Torta usado por regresiones de catálogo/producción. |
| tests/JavaScript/ProduccionCantidadTest.mjs | Incluye el nuevo control en el DOM de prueba y verifica conservación, limpieza y deshabilitación. |
| dato_optimizar/informe_hora_vitrina_tortas.md | Informe solicitado con evidencia y comprobación manual. |

RegistrarProduccionPorCantidad, ProduccionController, resources/js/produccion.js y CSS se revisaron y se conservaron: sus mecanismos existentes resuelven la integración. No se crearon controladores, servicios ni tablas adicionales.

## Implementación

### Columna y persistencia

La migración nueva agrega únicamente detalle_torta.hora_vitrina como TIME NULL, sin backfill ni hora automática. No modifica migraciones anteriores ni columnas de Pan/Bocadito. MariaDB devuelve, por ejemplo, 14:15:00; las vistas muestran 14:15. No se utiliza cast datetime ni conversión de zona horaria.

DetalleTorta permite asignar el atributo. El controller continúa pasando validated() a RegistrarProduccionTorta; esta Action lo incluye junto a forma/foto en los atributos del detalle creado por RegistrarProduccionPorCantidad. La escritura comparte la transacción actual con cabecera, cantidad, observación y participantes; los escenarios previos de fallo de pivote y compensación de foto siguen aprobados.

down comprueba primero si existen horas no NULL y bloquea su eliminación para evitar pérdida de información. Solo se probó la reversión en la base ficticia, retirando exclusivamente la hora ficticia de prueba. No es una autorización para revertir datos reales.

### Formulario y validación

El campo «Hora de vitrina» utiliza type=time, step=60, required y name=detalles[0][hora_vitrina]. Solo Torta lo muestra y habilita. Los controles disabled no forman parte del envío de Pan/Bocadito. Reutiliza las clases y los atributos condicionales actuales, sin duplicar lógica JavaScript ni cambiar colores/diseño.

El saneamiento existente de old input incluye hora_vitrina: conserva valores escalares y evita renderizar arrays como texto. Si falla otro dato, se mantiene la hora y la familia de Torta. Cambiar producto dentro de Torta no la borra; cambiar familia o Cancelar la limpia. La foto debe volver a seleccionarse después de un error, conservando el comportamiento anterior.

RegistrarProduccionTortaRequest añade bail|required|date_format:H:i. Mensajes:

- «La hora de vitrina es obligatoria.»
- «La hora de vitrina debe tener un formato válido HH:MM (00:00 a 23:59).»

Se rechazan omisión, NULL, vacío, 24:00, 14:60, 2:15, segundos, texto y arrays. Se aceptan 00:00, 14:15 y 23:59. La validación funciona en servidor independientemente del navegador. La Action, conforme a su contrato, espera estructura previamente validada para este campo; no añade otra validación de hora para llamadas directas.

### Consultas y compatibilidad

Historial muestra «Hora de vitrina: 14:15» dentro de Ver detalles → Producción de la tarjeta de Torta. Para NULL muestra «Hora de vitrina: Sin hora registrada». La tarjeta reciente de Producción presenta los mismos valores. La consulta existente ya carga todos los atributos del detalle: no se modifican Query, filtros, paginación ni cabecera.

Dos lotes de una sesión pueden guardar horas diferentes sin alterar el primero. Se conserva el contrato actual de cantidad: si un lote contiene varias tortas, comparte su hora como comparte forma/observación; esta tarea no lo divide en registros por pieza.

Se mantienen 18 latas por coche, cálculo de panes por lata/snapshot y finalización de Pan LATA; Pan UNIDAD y Bocaditos siguen su flujo. Las cantidades, fotografías, observaciones, autoría, permisos Administrador/Operador y reglas de Maestro/Ayudante/Practicante conservan implementación y pruebas anteriores.

## Resultados de pruebas

| Verificación | Resultado |
|---|---|
| Línea base: php tests/run-mariadb.php --filter='ProduccionTortaBocaditoStoreTest\|HistorialProduccionTest\|ProduccionPanStoreTest' | 280 aprobadas / 2.649 aserciones. |
| Reproducción antes de implementar: nuevos casos de obligatoriedad/formato y guardado | 12 fallidas esperadas: se aceptaban horas ausentes/inválidas y faltaba la presentación. |
| Regresión ampliada de Producción, Historial, migraciones, Pan, Personal, Turnos y concurrencia | 354 aprobadas / 3.627 aserciones; sin fallidas ni omitidas. |
| Suite completa: php tests/run-mariadb.php --do-not-cache-result | **1.033 aprobadas / 9.340 aserciones**, sin fallidas ni omitidas. |
| node --test tests/JavaScript/*.mjs | Cinco archivos aprobados; el reporter de este Node cuenta los archivos. |
| Ejecución directa de los cinco archivos mediante node | **58 casos aprobados**: Producción 20, Login 8, Productos 10, Sidebar 9 y Usuarios 11; cero fallidas/omitidas. |
| npm run build | Aprobado; aviso de dependencia opcional fontaine, sin error de compilación. |
| vendor/bin/pint --test sobre los 12 PHP afectados | Aprobado. |
| git diff --check y revisión del diff | Aprobados; modificación previa de documentación conservada. |

Las verificaciones PHP usan exclusivamente el runner existente: MariaDB desechable sin red, sockets/datadir bajo /tmp y conexiones comprobadas por IsolatedMariaDb. El sandbox impidió iniciar el servidor y se solicitó/obtuvo escalamiento para ejecutar el runner, sin cambiarlo. Los casos nuevos usan fixtures, sesiones array, withoutVite y Storage::fake. El build se verificó por separado. No se aplicaron migraciones ni seeders sobre la base operativa.

Instancias de referencia: línea base /tmp/sprint4-56b7af7a9cef; reproducción /tmp/sprint4-fb6f69c8632e; regresión /tmp/sprint4-48d5cf051bd9; suite final /tmp/sprint4-7b9fa9a955ee. El runner confirmó su detención y conservó artefactos ficticios para inspección.

## Documentación del Vault

Se inspeccionó la estructura, se buscó el tema y se leyeron íntegramente las notas afectadas. Se añadieron entradas fechadas sin borrar su historia, sobre los originales y con autorización de escalamiento tras el bloqueo de escritura. Raíz: /home/lukagfv/Documentos/Obsidian Vault/PROYECTO_LARAVEL/.

| Nota existente | Motivo |
|---|---|
| PRODUCCION/Logica de negocio.md | Regla confirmada, hora individual del lote, compatibilidad y evidencia. |
| PRODUCCION/Pantallas.md | Campo condicional, errores/old input y presentación por tarjeta. |
| PRODUCCION/Requerimientos.md | Amplía el contrato de Torta y sustituye la mención histórica «sin hora». |
| LARAVEL/Requests.md | Validación efectiva y responsabilidades del Request/Action/modelo. |
| BASE DE DATOS/Migraciones.md | Columna, conservación histórica, rollback y aplicación operativa pendiente. |
| DECISIONES/Decisiones Laravel.md | Fecha, contexto, opciones, decisión, motivo y consecuencias; se respetó el nombre real existente. |
| INICIO/Estado de proyecto.md | Resumen del checkout, pruebas y pendientes de aplicación/aceptación. |

No se creó una copia del Vault en el repositorio. mejora_asignado.md no se localizó en checkout/Vault y no se recreó. No correspondió cambiar Roadmap, notas de Personal/Usuarios ni Bugs: no se reordenó el desarrollo ni se modificaron esas funcionalidades; se trata de un requisito nuevo.

## Limitaciones y pendientes

- **PENDIENTE de autorización:** aplicar la migración en la base local operativa. Esta tarea no consultó su estado ni ejecutó DDL/seeders allí. No se inspeccionó ni modificó .env.
- **PENDIENTE:** aceptación manual/visual en navegador, responsive y controles horarios nativos de dispositivos. HTTP/DOM y dobles JavaScript no certifican geometría ni un recorrido navegador → BD operativa.
- **PENDIENTE:** comprobar la operación con las sesiones y almacenamiento reales tras aplicar el esquema autorizado.
- Una hora pertenece al lote actual; el contrato de cantidad queda expresamente sin cambios.
- Los límites anteriores de compensación de fotos ante caída del proceso o fallo de limpieza permanecen; no se agregó infraestructura.
- No se realizó commit, push ni despliegue automático. El contrato getAuthPasswordName() no se intervino ni se declara resuelto en esta tarea.

## Pasos para comprobar manualmente

1. En un entorno de revisión autorizado, revisar migrate:status y el SQL con migrate --pretend limitado al archivo nuevo. Obtener autorización antes de aplicar la migración en la base local real. El comando selectivo preparado, **no ejecutado en esa base**, es:

   ```bash
   php artisan migrate --path=database/migrations/2026_10_21_100000_add_hora_vitrina_to_detalle_torta.php
   ```

2. Con el esquema disponible y assets compilados, entrar como Administrador u Operador con turno permitido y abrir Producción → Torta. Confirmar que aparecen producto, cantidad, hora de vitrina, empleados, observación, forma y fotografía.
3. Registrar un lote de prueba con hora 14:15, cantidad 4, forma, observación, foto opcional y equipo válido: exactamente un Maestro, al menos un Ayudante y Practicantes opcionales. Confirmar éxito y «Hora de vitrina: 14:15» en su tarjeta.
4. Registrar otro lote en la misma fecha/turno con 16:45 y cantidad 7. Consultar Historial → Torta, abrir ambos detalles y comprobar sus horas, cantidades, fotos y equipos respectivos, sin cambios en el primero.
5. Intentar guardar sin hora. Para comprobar servidor, usar las pruebas automatizadas o una petición controlada en entorno aislado que omita el campo o envíe 24:00; el navegador puede impedir escribir ese valor. Esperar error español y ninguna fila/foto guardada.
6. Introducir una hora válida y provocar otro error, por ejemplo cantidad 0. Comprobar que se conserva la hora, forma, observación y equipo; volver a elegir la foto antes de reenviar.
7. Cambiar a Pan y Bocadito: hora de vitrina oculta/deshabilitada y ausente de los campos enviados. Volver a Torta o Cancelar: hora limpia; conservar el contexto previsto por el formulario.
8. Consultar una Torta histórica sin hora si existe: «Hora de vitrina: Sin hora registrada», sin asignación automática ni edición del registro.
9. En entorno de prueba, registrar Pan LATA con 1 coche + 2 latas, factor conocido y hora final; comprobar 20 latas y su estimación. Comprobar también Pan UNIDAD y Bocadito con sus cantidades directas, sin hora de vitrina.
10. Revisar escritorio/móvil, teclado, errores y selección de fotografía. Registrar aceptación visual/operativa por separado de las pruebas automáticas.
