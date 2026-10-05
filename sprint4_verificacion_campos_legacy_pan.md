# Sprint 4 — Verificación de campos legacy de Pan

**Recomiendo que Sprint 5 use `produccion.turno_id` como fuente de verdad y conserve ambos campos de `detalle_pan`, rellenándolos automáticamente.** El esquema permite esta transición; el flujo de guardado todavía no la implementa.

Auditoría estática del checkout actual. Tomo como aplicadas las tres migraciones y ejecutados los seeders que indicas; no consulté ni modifiqué la BD.

### 1. Estado actual de `detalle_pan`

**HECHO:** contiene:

| Campos | Restricciones relevantes |
|---|---|
| `id`, `produccion_id`, `producto_id` | PK y FK; eliminar producción elimina sus detalles |
| `unidad_medida_id`, `turno_id` | FK obligatorias, sin default explícito |
| `cantidad` | INTEGER con `CHECK (cantidad > 0)` |
| `observacion` | TEXT nullable |
| `panes_por_lata_usado` | INTEGER nullable |

`DetallePan` permite asignar estos campos, no usa timestamps y tiene relaciones con producción, producto, unidad, turno y empleados mediante una tabla pivote con rol de producción.

**DECISIÓN IMPLEMENTADA:** el modelo documenta `cantidad` como total entero de latas. Los tests existentes verifican esa representación mediante fixtures.

### 2. `unidad_medida_id`

**HECHOS:**

- Se define en la migración original de `detalle_pan`.
- `DetallePan::unidadMedida()` es `belongsTo`; `UnidadMedida::detallesPan()` es su inversa.
- `Producto` también tiene una unidad obligatoria y su propia relación.
- Se escribe explícitamente en fixtures y se lee en tests. No encontré uso funcional en controllers, formularios o cálculos de producción.
- No existe Seeder de unidades ni factory de `DetallePan`.

**Alcance del diseño original:** la estructura permite asociar una unidad a cada detalle, incluso distinta de la del producto. **El código no demuestra que esa diferencia fuera un requisito**, ni define una conversión basada en `equivalencia_unidades`.

**PENDIENTE DE NEGOCIO:** precisar si representa la unidad del producto, la de la cantidad producida o una referencia histórica.

Mantenerlo permite conservar esa información, pero también unidades contradictorias. Eliminarlo rompería relaciones/tests y podría perder información histórica que no se puede reconstruir desde un producto modificado.

### 3. `turno_id` de `detalle_pan`

**HECHO:** se creó originalmente como FK obligatoria. Lo utilizan `DetallePan::turno()`, `Turno::detallesPan()` y los tests/fixtures.

Sprint 4 añadió `produccion.turno_id` nullable y sus relaciones, **sin backfill ni sincronización**.

**HECHO:** ambas FK verifican que el turno exista; no garantizan que coincidan. El esquema permite cabecera Mañana con detalle Noche, o cabecera nula con detalle informado.

**DECISIÓN IMPLEMENTADA:** existe la estructura de turno en cabecera. Su precedencia funcional todavía no está implementada.

### 4. Fuente de verdad recomendada para Turno

**RECOMENDACIÓN:** usar **`produccion.turno_id`** para registrar, mostrar y filtrar el turno de la sesión de Pan.

Conservar `detalle_pan.turno_id` como copia de compatibilidad. Mantener dos columnas no implica aceptar dos decisiones independientes.

Una razón funcional para conservar ambos con significados distintos sería permitir varios turnos dentro de una misma producción. **No encontré esa regla implementada**; adoptarla exigiría definir el significado del turno de cabecera.

### 5. Estrategia recomendada para `unidad_medida_id`

**RECOMENDACIÓN para Sprint 5:** conservarlo y copiar en servidor `productos.unidad_medida_id` al crear cada detalle, sin segunda selección.

**Compatibilidad técnica:** sí. **Validez funcional:** debe confirmarse que esa unidad sea adecuada para Pan y no contradiga la cantidad canónica en latas.

`ProduccionController::store()` debería, dentro de una transacción:

1. Validar producto de categoría Pan, turno existente y cantidad positiva en latas.
2. Guardar el turno en la cabecera y copiarlo a cada detalle.
3. Obtener del producto la unidad y `panes_por_lata`; guardar ambos valores en el detalle.
4. Definir el tratamiento de factores nulos y validar los conocidos antes de calcular panes.

**HECHO:** `panes_por_lata_usado` conserva un factor distinto de la unidad. No reemplaza automáticamente `unidad_medida_id`. Actualmente no hay copia automática ni protección contra editar el snapshot; los tests lo asignan explícitamente.

### 6. Compatibilidad

**HECHO:** podemos conservar físicamente las columnas y dejar de pedirlas al usuario o usarlas como decisiones independientes.

**No podemos omitirlas en nuevas inserciones:** ambas siguen siendo NOT NULL. Para dejar de escribirlas habría que modificar posteriormente el esquema. Mientras tanto, deben recibir valores derivados válidos.

### 7. Riesgos

- **HECHO:** no hay restricciones que igualen turnos o unidades entre tablas.
- **RIESGO:** cabeceras históricas nulas o detalles con turnos diferentes requieren revisión antes de migrar datos.
- **HECHO:** Blade fija turnos 1/2; los seeders buscan por nombre y no garantizan esos IDs.
- **HECHO:** la UI pide coches, mientras modelo/tests expresan latas.
- **RIESGO:** conservar el ID de unidad no congela su nombre ni equivalencia si cambia el catálogo.
- **HECHO:** no hay Request ni validación de producción; `store()` está vacío.
- **HECHO:** los tests revisados no acreditan guardado HTTP, sincronización automática ni rechazo de inconsistencias.

### 8. Recomendación para cerrar Sprint 4

**Aceptar ahora:** la estructura aditiva y la conservación de información existente. No hace falta eliminar columnas para cerrar este análisis.

**Documentar tras tu revisión:** precedencia del turno de cabecera, obligación temporal de rellenar ambos campos y semántica pendiente de la unidad.

**Trasladar a Sprint 5:** guardado transaccional, valores derivados, snapshot, lectores de cabecera y pruebas del flujo.

**Limpieza futura:** completar cabeceras únicamente cuando sus detalles permitan determinar un turno inequívoco; resolver conflictos y retirar después la FK/columna legacy de turno. Evaluar la eliminación de la unidad por separado, una vez definido y preservado su valor histórico.

### 9. Archivos revisados

- Modelos: [DetallePan.php](/home/lukagfv/Documentos/PROYECTOS-LARAVEL/panaderia-rs/app/Models/DetallePan.php), `Produccion.php`, `Producto.php`, `UnidadMedida.php`, `Turno.php`, `Categoria.php`, detalles de Torta/Bocadito/Pedido y `DetallePanEmpleado.php`.
- Migraciones: [creación de detalle_pan](/home/lukagfv/Documentos/PROYECTOS-LARAVEL/panaderia-rs/database/migrations/2026_09_14_202625_create_detalle_pan_table.php), creaciones de productos, producción, unidades, turnos y pivote; las tres migraciones de Sprint 4.
- Backend: [ProduccionController.php](/home/lukagfv/Documentos/PROYECTOS-LARAVEL/panaderia-rs/app/Http/Controllers/ProduccionController.php), controllers de historial/dashboard, `AppServiceProvider.php` y `routes/web.php`.
- Catálogos/factories: `CategoriaSeeder.php`, `TurnoSeeder.php`, `DatabaseSeeder.php`, `UserFactory.php`.
- UI: `resources/views/{produccion,history,dashboard}/index.blade.php` y JavaScript relacionado.
- Tests: [PanUnitsTest.php](/home/lukagfv/Documentos/PROYECTOS-LARAVEL/panaderia-rs/tests/Feature/PanUnitsTest.php), `ProductionFamilyRelationshipsTest.php`, `TurnoSeederTest.php`, `ProductionObservationsMigrationTest.php`, `ModelsRelationshipsTest.php` y `tests/Support/IsolatedMariaDb.php`.

**CÓDIGO MODIFICADO:** ninguno. **DOCUMENTACIÓN MODIFICADA:** únicamente este archivo, creado por solicitud expresa como copia completa del informe; no se modificaron otros archivos ni notas del Vault. **PRUEBAS:** inspección estática y diffs de archivos existentes vacíos; no ejecuté tests que escriben datos. **PENDIENTES:** confirmar la semántica de unidad y revisar este diagnóstico antes de implementar.
