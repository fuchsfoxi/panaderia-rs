# Actualización del cierre de Sprint 4 — 2026-10-05

## Archivo documental actualizado

Original del Vault: `/home/lukagfv/Documentos/Obsidian Vault/PROYECTO_LARAVEL/SPRINTS/Sprint 4 - Cierre.md`. Se mantiene su estructura e historia; no se crea una copia de la nota en el repositorio.

## Secciones modificadas

Fecha de revisión; Trabajo realizado; Relaciones; Migraciones relacionadas; Reglas de negocio definidas (capacidad confirmada del coche); Problemas encontrados (contradicción histórica resuelta); Decisiones técnicas (contrato de transición); Pruebas realizadas (evidencia y límites; verificación técnica final; contraste visual parcial y observaciones para fases posteriores); Pendientes; Pendientes trasladados al Sprint 5; Pendientes que requieren consulta al negocio; Criterios de cierre; Estado final. Se añade Historial de actualización para conservar explícitamente el estado documental del 04/10 y registrar el cambio del 05/10.

## Hechos incorporados

- **HECHO COMPROBADO:** tres migraciones de Sprint 4 APLICADAS Y VERIFICADAS EN BD DE TRABAJO el 2026-10-05: observacion por detalle; familia/turno nullable con FK en cabecera; parámetro panes_por_lata y snapshot nullable.
- Secuencia comunicada por el responsable: migrate:status inicialmente Pending; migrate --pretend con las adiciones previstas y sin DROP/operaciones destructivas observadas; migrate con DONE; migrate:status final Ran.
- **HECHO COMPROBADO:** CategoriaSeeder y TurnoSeeder EJECUTADOS Y VERIFICADOS EN BD DE TRABAJO mediante carga selectiva y Tinker. Antes estaban vacíos; después Categoria contiene 1 Pan, 2 Torta, 3 Bocadito y Turno contiene 1 Mañana, 2 Noche. Los IDs son los observados en esa BD y no deben hardcodearse.
- **DECISIÓN TÉCNICA:** produccion.turno_id como fuente de verdad; detalle_pan.turno_id obligatorio conservado y sincronizado temporalmente; detalle_pan.unidad_medida_id obligatorio conservado y derivado transitoriamente del producto, sin segunda selección. Se distingue configuración vigente de panes_por_lata del snapshot panes_por_lata_usado.
- **DECISIÓN CONFIRMADA por el responsable del proyecto — 2026-10-05:** 1 coche = 18 latas; detalle_pan.cantidad almacena el total entero de latas producidas, sin almacenar coches de forma redundante. Sprint 5 podrá convertir entrada coches + latas adicionales a total de latas (coches × 18 + latas adicionales); esa conversión todavía no está implementada. La capacidad del coche deja de ser un pendiente de negocio.
- **HECHO COMPROBADO — CONTRASTE VISUAL PARCIAL REALIZADO, 2026-10-05:** el responsable aportó capturas reales de Login, Dashboard de Producción, Ingresar Producción, Historial de Producción e Historial de Pedidos, además de la paleta visual del sistema, y se realizó una revisión visual de esas pantallas. No se acredita inspección del archivo Figma completo; su revisión completa y las pantallas no aportadas quedan expresamente diferidas.
- Se marcan cumplidos los criterios de aplicación real de las tres migraciones, carga de ambos seeders, registro del contrato técnico de transición y contraste visual parcial sobre las capturas disponibles, con revisión completa del archivo Figma diferida explícitamente. Se añade la verificación técnica final del 2026-10-05 y se registra por instrucción final del responsable el estado **CERRADO CON PENDIENTES TRASLADADOS**, con **fecha de cierre: 2026-10-05**. No se acredita implementación funcional de Sprint 5.

## Verificación técnica final del Sprint 4 — 2026-10-05

- Comando ejecutado por el responsable: `php tests/run-mariadb.php --do-not-cache-result`.
- **Resultado: Tests: 59 passed (672 assertions); Duration: 2.11s.**
- Instancia temporal de MariaDB; el runner declaró migraciones exclusivamente sobre dos bases ficticias. La instancia fue detenida correctamente al finalizar.
- **PASAN:** ModelsRelationshipsTest, NavigationTest, PanUnitsTest, ProductionFamilyRelationshipsTest, ProductionObservationsMigrationTest y TurnoSeederTest.
- Login/logout/auth y prevención de caché cubiertos por pruebas; pasan relaciones, pivots, categorías, turnos y migraciones aditivas. Cantidad de Pan continúa siendo total entero de latas.
- Únicamente apareció la advertencia de PHPUnit: `--do-not-cache-result` está deprecated; usar en el futuro `--do-not-record-test-run-history`. No es fallo ni bloqueo de Sprint 4 y no se cambia código para tratarla.
- Evidencia final comunicada por el responsable; esta actualización no reejecuta el runner. El aislamiento no acredita Sprint 5 ni módulos funcionales posteriores.

## Estado Git anterior a esta actualización

Comunicado por el responsable y contrastado mediante inspección de lectura antes de editar: únicamente estaban preparados para commit `sprint4_actualizacion_cierre_2026-10-05.md` y `sprint4_verificacion_campos_legacy_pan.md`; `git diff --cached --check` no mostró errores y `git diff --cached --stat` mostró **2 archivos y 149 inserciones**. Es una instantánea anterior a esta actualización; no se modifica el índice de Git ni el informe legacy.

## Observaciones para fases posteriores

No son trabajo pendiente ni bloqueos de Sprint 4:

- UI de Pan en coches frente a persistencia de total entero de latas.
- Turno corresponde funcionalmente a Pan.
- Adaptar en Sprint 5 la representación visual coches + latas manteniendo detalle_pan.cantidad en latas.
- Historial de Pedidos separado bajo revisión, sin decisión final de eliminación/integración.
- Sidebar, navegación y sistema de diseño corresponden a refinamiento visual posterior.

## Pendientes abiertos

- **PENDIENTE DE NEGOCIO:** significado definitivo/histórico de detalle_pan.unidad_medida_id, valores panes_por_lata todavía desconocidos por producto, máximo operativo, otras reglas realmente no confirmadas, temporadas, estados/métricas y permisos.
- **TRABAJO TRASLADADO A SPRINT 5 — NO IMPLEMENTADO:** ProduccionController::store(), validaciones HTTP, transacciones, catálogo/formulario con IDs reales, cabecera/detalles/participantes, sincronización de turno, derivación de unidad, copia del snapshot, foto de Torta, conexión formulario/backend/BD y pruebas funcionales.
- Historial, Dashboard, Stock, Pedidos funcionales, permisos y limpieza futura de columnas legacy permanecen pendientes en fases posteriores por acordar.
- Cierre registrado por instrucción final del responsable: CERRADO CON PENDIENTES TRASLADADOS, 2026-10-05. Revisión completa del archivo Figma y pantallas no aportadas expresamente diferidas a una fase posterior; el criterio de contraste visual parcial ya está cumplido.

## Verificación y alcance

Evidencia de ejecución en BD aportada por el responsable en la sesión del 2026-10-05; esta actualización no reejecuta comandos Laravel, migraciones, seeders, consultas de BD ni tests que escriban datos. Se contrastaron los artefactos de migración, seeders, store vacío y el informe sprint4_verificacion_campos_legacy_pan.md en código/documentación actuales. Los resultados de tests del 02/10 conservan su carácter histórico y aislado; se incorpora como evidencia vigente la verificación técnica final comunicada del 2026-10-05 (59 pruebas / 672 aserciones, 2.11s, MariaDB temporal). El contraste visual parcial se registra a partir del hecho comunicado por el responsable, limitado a las capturas disponibles; no se atribuye una inspección completa del archivo Figma.

**CÓDIGO FUNCIONAL MODIFICADO: ninguno.** Solo se actualizan el documento original de cierre del Vault y este registro. Controllers, modelos, vistas, JavaScript, tests, migraciones, seeders y los demás documentos del repositorio/Vault se conservan.
