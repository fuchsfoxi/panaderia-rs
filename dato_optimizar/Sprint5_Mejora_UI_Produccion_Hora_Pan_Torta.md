# Estado inicial

Fecha: 2026-10-11. Estado final: **IMPLEMENTADO Y COMPROBADO mediante HTTP/MariaDB aislada, eventos JavaScript y build**. **Aceptación visual en navegador: pendiente.**

Checkout inicialmente limpio. Laravel 13 según Composer; Pan, Torta y Bocadito ya disponían de registro funcional. Se revisaron ProduccionController, los Requests específicos y el Request compartido por cantidad; RegistrarProduccionPan/Torta/Bocadito/PorCantidad; ResolverCabeceraProduccion y ValidarParticipantesProduccion; DetallePan/Torta/Bocadito y Produccion; Blade/CSS/JS de Producción, rutas, pruebas y migraciones.

Pan calculaba cantidad como coches × DetallePan::LATAS_POR_COCHE + latas adicionales, con snapshots y participantes por detalle. La interfaz repetía explicaciones, añadía una tarjeta interior y mostraba permanentemente los selectores de participantes. Torta tenía cantidad, forma de texto libre y archivo opcional sin preview. Bocadito funcionaba por cantidad. Los escritores ya compartían cabeceras/reglas, transacciones y locks; no fue necesario rediseñar esa arquitectura.

Antes de editar: `php artisan test` pasó 9 pruebas/31 aserciones, omitió 315 que requieren socket aislado. El runner completo aprobó **324/324 pruebas y 3.300 aserciones**, sin fallidas ni omitidas. Node aprobó los dos archivos existentes (5 casos de Producción y 7 de sidebar). No se utilizó navegador real ni se reprodujo un fallo de negocio en el estado inicial; el problema solicitado era de presentación/UX y ausencia de hora por lote.

# Pan

- Una sola tarjeta beige con fecha/turno, producto, coches/latas, resumen compacto, hora, participantes y observaciones.
- Resumen «Total de latas», valor breve y una única equivalencia «1 coche = 18 latas», publicada desde la constante existente.
- Se retiran explicaciones repetidas del total cero; el error del backend continúa apareciendo cuando corresponde.
- Chips existentes y botón + que abre los selectores de empleado/rol. Tras agregar se cierra el editor y vuelve el foco a +; Escape permite cerrarlo. Se conservan exactamente 1 Maestro y mínimo 1 Ayudante, sin duplicados.
- Observaciones con dos filas y altura inicial menor, redimensionables.
- Botones Cancelar/Guardar. Cancelar conserva fecha, turno y familia y limpia el lote, incluida hora/foto/equipo.
- Botón de consulta conservado, separado del formulario y fuera del bloque de resultados que se oculta al cambiar familia.
- Tarjetas registradas muestran hora HH:MM o «Sin hora registrada».

Paleta, tipografía, tarjetas, redondeados, botones verdes y pestañas existentes conservados. No se agregan gradientes, dependencias, animaciones o paneles decorativos.

# Hora de finalización

Nueva propiedad **detalle_pan.hora_finalizacion**, independiente por lote/producto. No se agrega a produccion. Un envío crea su propio detalle; reutilizar cabecera no cambia la hora de otros lotes.

RegistrarProduccionPanRequest exige `required` y `date_format:H:i`, con mensajes en español. RegistrarProduccionPan copia el dato validado al crear el detalle. DetallePan incorpora fillable; TIME permanece string HH:MM:SS o NULL, sin cast datetime que invente una fecha. Campo Blade `type="time"`, required y step=60, inicialmente vacío, sin asignar automáticamente la hora actual.

La hora se trata como hora local indicada por el usuario para ese lote, sin nuevas reglas de duración, comparación con el reloj o límites de turno. old input la conserva cuando falla otro campo; arrays malformados se sanean como los campos anteriores. Datos históricos sin hora siguen siendo consultables.

# Torta

Formulario principal en tarjeta beige, con fecha/turno, producto, cantidad, participantes, observaciones y botones. Forma/foto se agrupan en panel crema de información adicional al lado en escritorio; hasta 767px se coloca debajo. Anchos mínimos y columnas flexibles evitan desbordamientos por contenido desde CSS, con aceptación geométrica pendiente en navegador.

Circular/Rectangular funcionan como botones de selección accesibles con aria-pressed. Se conserva el input de texto real y su contrato de máximo 30 caracteres: permite otras formas, por ejemplo Corazón, sin restringir el backend a una lista cerrada. Las opciones habituales están definidas en un único foreach ampliable; JS utiliza data-forma. Errores y old input mantienen familia, forma y los demás campos.

Requests, Actions y almacenamiento de Torta conservados.

# Foto

Zona visual con borde suave discontinuo, icono SVG inline de imagen siguiendo el patrón existente del proyecto, formatos/tamaño y dos accesos:

- **Subir foto:** selector de archivo sin capture, para permitir galería/archivos existentes.
- **Tomar foto:** segundo selector con capture="environment", como indicación de cámara trasera cuando el navegador/dispositivo la soporta.

Los dos inputs usan accept="image/jpeg,image/png,image/webp". Solo la selección activa lleva name="detalles[0][foto]"; el otro input pierde name y se vacía. Al salir de Torta ambos quedan disabled. No se requiere DataTransfer ni acceso directo a getUserMedia.

JavaScript muestra miniatura local y nombre mediante URL.createObjectURL. Se ofrece Cambiar foto y Quitar foto; las URLs se revocan al sustituir/quitar/cancelar/cambiar categoría o abandonar la página sin BFCache. Un fallo al decodificar el preview no impide cambiar/quitar la selección. El manejo respeta el estado persisted de pagehide; la revalidación global de páginas del sistema continúa existente.

No hay petición de subida anticipada: el archivo se envía con Guardar. Backend conserva foto opcional, comprobación por contenido JPEG/PNG/WebP hasta 2 MB, nombres generados y compensación de archivo ante fallo SQL. No se promete atomicidad física entre filesystem/BD ni compatibilidad de cámara probada en dispositivos reales. Tras un error el archivo debe seleccionarse otra vez, por restricción del navegador.

# Bocadito

Backend, endpoints, cantidades, participantes, cabeceras y validaciones intactos. Recibe solo presentación común: chips/editor compacto, observaciones menores, agrupación y botones. La regresión completa de Bocadito pasó. No se amplía su funcionalidad.

# Archivos creados

- database/migrations/2026_10_11_100000_add_hora_finalizacion_to_detalle_pan.php.
- dato_optimizar/Sprint5_Mejora_UI_Produccion_Hora_Pan_Torta.md (este informe).

# Archivos modificados

- app/Http/Requests/RegistrarProduccionPanRequest.php: validar hora y mensajes.
- app/Actions/Produccion/RegistrarProduccionPan.php: persistir hora validada.
- app/Models/DetallePan.php: fillable de hora.
- resources/views/produccion/index.blade.php: compactación, hora, panel Torta, selectores de foto y errores/old input.
- resources/css/produccion.css: presentación con paleta existente, responsive y foco/controles compactos; se integra el estilo repetido del editor.
- resources/js/produccion.js: editor, forma rápida, preview, selección única de archivo y limpieza.
- tests/Feature/ProduccionPanStoreTest.php: nuevos casos de hora y ajustes de expectativas UI/fixtures.
- tests/Feature/RegistrarProduccionPanActionTest.php: fixture con hora válida.
- tests/Feature/ProduccionTortaBocaditoStoreTest.php: fixture Pan y contrato UI de Torta.
- tests/Feature/ProductionObservationsMigrationTest.php: nueva aditiva excluida del esquema original y conservación/up/down/up.
- tests/Feature/DashboardTest.php y tests/Feature/HistorialProduccionTest.php: únicamente hora en fixtures de registros nuevos de Pan. No se cambia implementación de esos módulos.
- tests/JavaScript/ProduccionCantidadTest.mjs: actualización de entorno/eventos y casos de foto, forma, hora, editor y consulta.
- mejora_asignado.md: evidencia y pendientes de esta entrega, preservando auditoría previa.

Ocho notas originales de Obsidian actualizadas con adiciones bajo su título, preservando íntegramente el contenido anterior, sin copiar la bóveda al repositorio:

Raíz: `/home/lukagfv/Documentos/Obsidian Vault/PROYECTO_LARAVEL/`.

- PRODUCCION/Pantallas.md: UX/UI y comportamiento de foto.
- PRODUCCION/Logica de negocio.md: hora por lote y reglas conservadas.
- LARAVEL/Requests.md: validación/old input de hora.
- LARAVEL/Models.md: fillable y representación TIME.
- BASE DE DATOS/Migraciones.md: columna, preservación, rollback y aplicación local.
- DECISIONES/Decisiones Laravel.md: fecha/contexto/opciones/decisión/motivo/consecuencias.
- SPRINTS/Sprint 5 - Producción funcional.md: alcance, evidencia y aceptación pendiente.
- INICIO/Estado de proyecto.md: resumen vigente.

Se inspeccionó estructura, se buscó el tema y se leyeron íntegramente las notas editadas. Se utilizó el nombre real de Decisiones Laravel.md, sin crear una variante. La escritura original requirió escalamiento y quedó autorizada/completada. No se modificaron notas ajenas al alcance ni se crearon carpetas.

# Migración

Nueva aditiva: **2026_10_11_100000_add_hora_finalizacion_to_detalle_pan.php**.

SQL revisado mediante pretend:

```sql
alter table `detalle_pan` add `hora_finalizacion` time null
```

Decisión: nullable SQL para conservar históricos de hora desconocida; obligatorio en nuevos POST desde la aplicación. Sin backfill ni valor automático de hora. Las 25 migraciones previas permanecen intactas. Down bloquea si alguna hora no es NULL, siguiendo el patrón de conservación de las aditivas existentes.

Procedimiento real de base local:

1. `php artisan migrate:status`: revisar qué migraciones están aplicadas; 25 previas Ran.
2. Revisar íntegramente el nuevo archivo, su up y protección de down.
3. `php artisan migrate --pretend --path=database/migrations/2026_10_11_100000_add_hora_finalizacion_to_detalle_pan.php`: revisar SQL sin modificar la base; solo ADD TIME NULL.
4. `php artisan migrate --path=database/migrations/2026_10_11_100000_add_hora_finalizacion_to_detalle_pan.php`: aplicación selectiva, DONE.
5. `php artisan migrate:status`: nueva migración **Ran batch 4**; 26 aplicadas.

No se ejecutaron migrate:fresh, db:wipe, rollback local o seeders. No se insertaron lotes de negocio reales ni se leyó/modificó .env. Los tests DDL de preservación/reversión utilizan exclusivamente filas ficticias en servidor desechable. La base local se comprobó mediante resultado de migración/status, sin certificar el recorrido de negocio real allí.

# JavaScript

Se mantiene cambio de endpoint/productos/errores por familia y controles condicionales disabled. Cantidades siguen usando el factor publicado por Blade; vacíos equivalen visualmente a cero y no se envía un total calculado. El output ahora muestra solo N latas.

El + abre selectores existentes, con ARIA/foco/Escape; agregar/quitar y renumerar participantes conserva nombres y reglas. Forma rápida no elimina texto libre. Foto usa listeners change, preview local, name único y limpieza; cancelación/cambio de familia también limpia hora y cantidades. La consulta conserva fecha/turno/categoría y permanece accesible al ocultar resultados de otra familia. No se mueve ninguna validación crítica al frontend.

# Tests

| Verificación | Resultado real final |
|---|---|
| php artisan test | 9 aprobadas, 0 fallidas, 328 omitidas, 31 aserciones; requiere runner para persistencia |
| php tests/run-mariadb.php --do-not-cache-result | **337 aprobadas, 0 fallidas, 0 omitidas; 3.442 aserciones** |
| node tests/JavaScript/ProduccionCantidadTest.mjs | **12 aprobadas, 0 fallidas** |
| node tests/JavaScript/SidebarTest.mjs | **7 aprobadas, 0 fallidas** |
| Pint --test de diez PHP modificados/creados | Aprobado |
| npm run build | Aprobado; aviso opcional fontaine preexistente |
| git diff --check | Aprobado |
| Aceptación visual en navegador | **Pendiente** |

13 casos PHP nuevos: 12 de Pan y 1 contrato de UI de Torta. Se amplían aserciones existentes para hora guardada en detalle, independencia entre productos, old input, y migración histórica. Pan cubre hora faltante/vacía/omitida, inválida/fuera de rango/segundos/array, 00:00/23:59, horas distintas, cabecera reutilizada y lote sin hora compatible. Las cantidades, snapshots, roles, transacciones, fallos SQL/foto y tres familias conservan sus tests anteriores.

UI de Torta comprueba forma Corazón recuperada, botones, accept, galería sin capture, cámara environment, name único, campo hora disabled y consulta fuera de resultados ocultables. JS ejecuta script completo con dobles DOM: cálculo, reset/contexto, categorías/endpoints/opciones/disabled, preview galería/cámara, cambio/quitar, URLs revocadas, formas libres, editor/foco/Escape y consulta por familia. No equivale a FormData/selector de archivo/cámara/diseño probados en navegador.

Aislamiento: runner verifica datadir/socket/base, usa MariaDB temporal sin red y dos bases ficticias en /tmp; pruebas HTTP con sesiones array y Vite omitido. No hay equivalencia con probar cuentas, sesiones database, cámara o registros de negocio del entorno local.

# Comandos ejecutados

| Comando | Motivo | Resultado |
|---|---|---|
| git status --short; rg --files; lecturas de código, tests/migraciones y Vault | Estado inicial, contratos y ubicación documental | Checkout limpio; implementación de tres familias existente y notas relacionadas localizadas |
| php artisan test (antes/después) | Referencia y verificación PHP convencional | 9/315 omitidas inicialmente; 9/328 omitidas finalmente, 0 fallidas |
| node --test tests/JavaScript/*.mjs (inicial) | Referencia JS | 2 archivos aprobados; casos detallados ejecutados después por archivo |
| php tests/run-mariadb.php --do-not-cache-result | Persistencia/regresión completa | Inicial 324/3.300; final 337/3.442, todo aprobado |
| php artisan migrate:status (antes/después) | Inventario de migraciones aplicadas | 25 previas Ran; nueva Ran batch 4 al terminar |
| Lectura de 2026_10_11_100000_add_hora_finalizacion_to_detalle_pan.php | Revisar ADD TIME NULL y down | Solo campo de detalle y rollback protegido |
| php artisan migrate --pretend --path=database/migrations/2026_10_11_100000_add_hora_finalizacion_to_detalle_pan.php | Inspeccionar SQL sin modificar base | Un ALTER TABLE ADD TIME NULL |
| php artisan migrate --path=database/migrations/2026_10_11_100000_add_hora_finalizacion_to_detalle_pan.php | Aplicar la aditiva autorizada | DONE, batch 4 |
| vendor/bin/pint (y --test) con las rutas PHP afectadas | Formato del alcance modificado | Aplicó espacios/saltos en PanStoreTest; comprobación final aprobada |
| node tests/JavaScript/ProduccionCantidadTest.mjs | Eventos de Producción | 12/12 |
| node tests/JavaScript/SidebarTest.mjs | Regresión común de navegación | 7/7 |
| npm run build | Compilar CSS/JS | Aprobado; aviso opcional de fuentes |
| git diff; git diff -w de Blade; git diff --check | Revisar alcance y whitespace | Revisión realizada; check final aprobado |
| python3 /tmp/rs-update-vault.py (escalado) | Integrar adiciones en ocho originales ya leídos | Completado; comprobación de hash previo y preservación íntegra |

La invocación Pint incluyó: RegistrarProduccionPan.php, RegistrarProduccionPanRequest.php, DetallePan.php, la migración nueva y los seis archivos Feature PHP enumerados en Archivos modificados. No se reformateó globalmente PHP ajeno a la tarea. Los scripts temporales de preparación contienen adiciones y operaciones de edición; no son copias de las notas del Vault.

# Problemas encontrados

- Sandbox bloqueó el socket del runner y conexión MariaDB local (error 2002). Se reintentó con escalamiento; runner y migración completados. Advertencias de io_uring/AIO aparecieron en el intento inicial bloqueado; el runner escalado funcionó.
- Primera regresión modificada: 335 aprobadas/1 fallida; la prueba DDL contaba la nueva aditiva entre las 21 originales. Se excluyó correctamente y se amplió el ensayo de hora histórica/up/down/up; suite final aprobada.
- Primera ampliación JS: 10 aprobadas/1 fallida; el test detectó que el reset aún no limpiaba preview. Se corrigió la ubicación de la limpieza en reset; 12/12 finales.
- Revisión de diff detectó que colocar consulta dentro de resultados ocultables la ocultaba al cambiar categoría. Se movió fuera y se añadió cobertura PHP/JS; regresión final aprobada.
- Build informa que fontaine es opcional para optimizar fuentes; no bloquea compilación. No se cambió la configuración de fuentes.

No se comprobó un bug de backend preexistente en el registro de las tres familias. Los fallos intermedios quedaron corregidos; no se presentan pruebas fallidas como entrega terminada.

# Pendientes

**Aceptación visual en navegador: pendiente.** Checklist de esta entrega:

1. Escritorio: identidad/paleta, compactación de Pan y panel lateral de Torta.
2. Móvil/tablet: panel debajo, chips/nombres largos y ausencia de scroll horizontal, especialmente 320/375/768px.
3. Pan: introducir hora real, guardar, registrar otro producto con otra hora y comprobar ambas tarjetas; el nuevo formulario queda sin hora automática.
4. Participantes: agregar Maestro/Ayudantes con +, quitar, reabrir, Escape y teclado/foco.
5. Torta: seleccionar foto desde galería, tomar foto en Android/iOS, cancelar selector, cambiar/quitar y guardar; confirmar mismo backend/archivo.
6. Provocar errores en cada familia: mantener familia/campos/forma/hora/equipo y volver a seleccionar foto.
7. Cambiar categorías, consultar sesión y Cancelar: cantidades/foto/hora/equipo se limpian conforme al flujo y los controles ocultos no se envían.

No hay autorización pendiente para migración o documentación: ambas completadas. Sin commit/push/despliegue. No se declara cerrado todo el Sprint 5 ni se implementan tareas de otros módulos.

# Recomendaciones

Realizar la checklist en navegadores de escritorio y al menos un Android/iOS antes de aceptar visualmente la entrega. Mantener el contrato de entrada HH:MM para cualquier futuro consumidor de RegistrarProduccionPan. Si se requiere revertir con horas existentes, definir previamente una estrategia de conservación; no retirar la columna directamente. Las ampliaciones futuras de forma pueden añadir opciones habituales al foreach sin restringir el texto libre del backend.
