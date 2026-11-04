# Auditoría: tres fallos de regresión de Producción

**Fecha:** 3 de noviembre de 2026.  
**Estado:** tres pruebas corregidas y comprobadas en aislamiento; suite global con fallos adicionales pendientes.

## 1. Resultado

Los tres fallos correspondían a expectativas visuales desactualizadas en pruebas de cabeceras duplicadas: una para Pan y dos para Torta y Bocadito. Esperaban que el listado no mostrara tarjetas al detectar cabeceras duplicadas para la fecha y turno del formulario. La funcionalidad vigente mantiene el listado independiente y presenta los registros de la categoría activa.

No se encontró un defecto en la protección contra duplicados dentro de los casos comprobados. No se modificaron el controlador, las consultas ni las reglas de producción.

## 2. Causa de cada fallo y corrección

### Pan

`tests/Feature/ProduccionPanStoreTest.php`, prueba «rechaza cabeceras duplicadas sin elegir una ni alterar los registros existentes», verificaba el rechazo del guardado y después exigía que no apareciera ninguna tarjeta. Esa última expectativa contradice el listado independiente por categoría.

La prueba ahora conserva el rechazo, mensaje, redirección y datos del formulario, y exige que el detalle existente de Pan aparezca en el listado de Pan. También compara las dos cabeceras, el detalle y las relaciones de participantes antes y después del intento, y comprueba que no se creen detalles de otras familias.

### Torta y Bocadito

`tests/Feature/ProduccionTortaBocaditoStoreTest.php`, prueba «cabeceras duplicadas bloquean cada familia sin seleccionar una arbitraria», se ejecuta para Torta y Bocadito. En ambos casos la expectativa de ocultar todas las tarjetas quedó desactualizada por la misma razón.

La prueba ahora verifica el mensaje y la redirección de validación, conserva los datos del formulario y exige que el detalle existente aparezca en la categoría correspondiente. Compara antes y después las cabeceras, atributos del detalle —incluida la cantidad—, pivotes de participantes y archivos del almacenamiento ficticio. Comprueba además que no se creen detalles de otras familias.

## 3. Integridad comprobada

Para cada familia, las pruebas aisladas comprueban que:

1. El intento de registrar sobre cabeceras duplicadas se rechaza con el mensaje de validación esperado.
2. No se crea una cabecera o detalle adicional.
3. Las cabeceras y el detalle existente conservan sus atributos y cantidades.
4. Las relaciones de participantes conservan sus valores.
5. La interfaz sigue mostrando el registro válido en el listado de la categoría activa.

Torta y Bocadito también comprueban que no cambie el listado de archivos del almacenamiento ficticio. Estas comprobaciones corresponden a los escenarios de prueba; no certifican datos o sesiones operativas.

## 4. Pruebas y resultados

- `ProduccionPanStoreTest` y `ProduccionTortaBocaditoStoreTest`: **236 pruebas aprobadas / 2.376 aserciones**.
- Regresión dirigida de Producción, Dashboard, navegación, filtros, permisos e Historial: **496 pruebas aprobadas / 4.538 aserciones**.
- Suite global final: **1.260 aprobadas / 12 fallidas / 22 omitidas / 11.260 aserciones**, sobre 1.294 casos. No se declara aprobada la suite global.
- `vendor/bin/pint --test` sobre los dos archivos de pruebas y `git diff --check`: aprobados.

Las pruebas usaron una copia bajo `/tmp` y MariaDB desechable sin red; el helper verificó la identidad de la base y el directorio de datos antes de migrar. Se utilizaron fixtures, transacciones y almacenamiento ficticio. Las instancias terminaron detenidas. No se utilizó ni migró la base operativa.

La primera ejecución no llegó a correr pruebas porque `/tmp` estaba lleno. Con autorización se retiraron solo directorios de datos de instancias ficticias ya detenidas, conservando sus registros. La primera suite global de la copia produjo advertencias de preparación; se repitió con un archivo de entorno de pruebas vacío en la copia, sin modificar la configuración del proyecto.

## 5. Fallos adicionales de la suite global

Los 12 fallos de usuarios y acceso se reprodujeron en una copia anterior, sin los cambios de esta tarea:

- Once casos de `UsuariosTest` esperan redirección de Operador a Dashboard y reciben Producción.
- `AdministradorPasswordTest` espera volver a Usuarios tras el recorrido de cambio de contraseña y recibe Dashboard.

Estos resultados requieren investigación separada. Las aserciones fallidas interrumpen los recorridos, por lo que no demuestran por sí solas que el resto de los flujos de sesión y contraseña funcione correctamente. Los 22 casos omitidos requieren el runner específico de privilegios MariaDB.

## 6. Archivos y preservación

Archivos modificados por esta tarea:

- `tests/Feature/ProduccionPanStoreTest.php`.
- `tests/Feature/ProduccionTortaBocaditoStoreTest.php`.

Se actualizó la nota original del Vault `PENDIENTES/Bugs.md`, en las entradas existentes de listado por categoría y destino de login, conservando la historia.

Al finalizar, la rama seguía en `master`, HEAD `3b3f0e5`, con los cambios locales anteriores preservados. Los hashes del CSS y JavaScript del Dashboard no cambiaron. No se instalaron dependencias, crearon migraciones, hicieron commits o push, ni se usaron reset, checkout o stash.
