# Auditoría: corrección de dos fallos de Producción

**Fecha:** 3 de noviembre de 2026.  
**Estado:** correcciones comprobadas en regresión dirigida aislada; tres fallos adicionales previos permanecen pendientes.

## Alcance y preservación

Antes de modificar se comprobó la rama `master`, HEAD `3b3f0e5fa8cbfd81fa5f819ae84e337db7600df5` y el estado de Git. CSS, JavaScript, pruebas visuales y auditorías del Dashboard ya tenían cambios locales o no estaban bajo seguimiento. Se conservaron. Los hashes SHA-256 de `resources/js/dashboard.js` y `resources/css/dashboard.css` fueron iguales antes y después de la tarea.

No se modificaron el diseño del Dashboard, CSS, JavaScript, sidebar, indicadores de navegación, reglas de turnos ni lógica de registro. La modificación de `tests/Feature/DashboardTest.php` se limitó a la aserción solicitada y preservó el cambio visual de prueba ya existente.

## 1. Prueba desactualizada del listado

**Prueba:** `tests/Feature/DashboardTest.php`, «hoy y la fecha por defecto siguen el día de Lima sin desplazar DATE ni filtros del historial».

La aserción esperaba que `detallesRegistrados` mostrara únicamente el lote de la fecha local y turno del formulario. El comportamiento vigente y documentado muestra los registros de la categoría activa sin restringir ese listado por fecha o turno. La prueba existente de `ExperienciaVisualFase2Test.php` ya verifica que los parámetros históricos de fecha y turno no restringen el listado automático.

Se actualizó la aserción para comprobar los dos lotes de Pan en el orden vigente y que el lote de la fecha local esté presente. Se conservaron las comprobaciones de reloj de Lima, fecha predeterminada y filtros de Historial. No se modificó `ProduccionController`.

## 2. Categoría explícitamente vacía

**Archivo:** `app/Http/Requests/ConsultarRegistrosProduccionRequest.php`.

El middleware de Laravel convierte una cadena vacía en `null`. La llamada anterior a `query('categoria', 'pan')` devolvía el valor predeterminado tanto para clave ausente como para valor `null`. Por ello, `?categoria=` consultaba Pan en vez de ser rechazada.

`validationData()` ahora inspecciona las claves de la consulta: solo asigna Pan cuando falta `categoria`; si la clave existe, conserva el valor normalizado para la validación `required`, `string` e `in` ya existente. No se duplican reglas de validación.

`tests/Feature/ExperienciaVisualFase2Test.php` añade el caso de espacios y comprueba que la ausencia de categoría consulta Pan cuando también hay una Torta ficticia. La cobertura existente mantiene los casos de las tres familias válidas, categoría vacía, desconocida y arreglo, así como página inválida.

## 3. Pruebas y resultados

- Regresión dirigida de `DashboardVisualFase3Test`, `DashboardCalculosTest`, `DashboardTest`, `ExperienciaVisualFase2Test`, `PanRegistroUnidadTest`, `NavigationTest`, `ProduccionCategoriasMovilTest`, `HistorialProduccionTest` y `PersonalProduccionTest`: **260 pruebas aprobadas / 2.162 aserciones**.
- Regresión ampliada, incluyendo pruebas de guardado de Pan, Torta y Bocadito: **496 pruebas / 493 aprobadas / 3 fallidas / 4.503 aserciones**. Los dos fallos originales dejaron de aparecer; no se declara aprobada la regresión ampliada.
- Comparación específica de guardado en una copia anterior, sin la corrección del Request: **236 pruebas / 233 aprobadas / 3 fallidas / 2.341 aserciones**. Reprodujo los mismos tres fallos adicionales.
- `vendor/bin/pint --test` sobre los tres PHP afectados y `git diff --check`: aprobados.

Las pruebas usaron MariaDB desechable bajo `/tmp/sprint4-*`, sin red. El helper verificó socket, directorio de datos y nombre de base ficticia antes de migrar; se usaron fixtures, transacciones y sesiones de prueba. Las instancias finalizaron detenidas. La primera ejecución dentro del sandbox no pudo abrir el socket y se repitió con autorización. No se modificó ni migró la base de datos operativa.

## 4. Fallos adicionales pendientes

Los tres fallos corresponden a pruebas de cabeceras duplicadas:

- `ProduccionPanStoreTest`: «rechaza cabeceras duplicadas sin elegir una ni alterar los registros existentes».
- `ProduccionTortaBocaditoStoreTest`: «cabeceras duplicadas bloquean cada familia sin seleccionar una arbitraria», casos Torta y Bocadito.

Las pruebas verifican primero el rechazo del guardado ante duplicidad y luego esperan que no se muestre ninguna tarjeta del listado. El listado actual es independiente del contexto de fecha y turno; la aserción visual falla porque se muestra el registro de la categoría. La comparación con una copia anterior reproduce los mismos fallos. No se cambió esa lógica ni se debilitaron las pruebas: queda pendiente investigar y decidir el comportamiento esperado antes de corregirlos.

## 5. Archivos y límites

Archivos modificados para las dos correcciones:

- `app/Http/Requests/ConsultarRegistrosProduccionRequest.php`.
- `tests/Feature/DashboardTest.php`.
- `tests/Feature/ExperienciaVisualFase2Test.php`.

Se actualizaron las notas originales del Vault `LARAVEL/Controllers.md` y `PENDIENTES/Bugs.md`, conservando su historia. No se ejecutó la suite global, una prueba en dispositivo físico ni una prueba contra datos operativos. No se instalaron dependencias y no se realizaron commits, push, reset, checkout o stash.
