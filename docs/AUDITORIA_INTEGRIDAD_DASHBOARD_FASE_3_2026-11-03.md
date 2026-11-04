# Auditoría de integridad posterior al Dashboard Fase 3

**Fecha:** 3 de noviembre de 2026.  
**Alcance:** revisión local de Git, código, pruebas y build; diagnóstico de dos fallos de Producción. La investigación fue de solo lectura; esta auditoría se creó posteriormente por solicitud expresa.

## 1. Resultado

El Dashboard de Fase 3 está repartido entre `HEAD` y modificaciones locales. El commit contiene la vista Blade y los cálculos de Fase 2; CSS, JavaScript y pruebas visuales de Fase 3 permanecen en el directorio de trabajo. El build local inspeccionado contiene señales de los cambios visuales, pero no se verificó qué versión entrega un servidor remoto o el celular.

El fallo de listado por fecha y turno corresponde a una expectativa de prueba que ya no coincide con el comportamiento documentado. El fallo de categoría vacía apunta a un posible defecto funcional: el endpoint interpreta una categoría vacía como si estuviera ausente y aplica el valor predeterminado de Pan.

## 2. Estado Git al investigar

Rama `master`; HEAD `3b3f0e5fa8cbfd81fa5f819ae84e337db7600df5`. La rama estaba dos commits por delante de la referencia local `origin/master`; no se consultó el remoto.

```text
3b3f0e5  2026-11-03 21:31:56 -0500  auditorias
e525614  2026-11-03 20:27:26 -0500  md
616e2f8  2026-11-03 09:45:05 -0500  fitlro - produccion
```

Estado observado al concluir la investigación, antes de crear este documento:

```text
 M resources/css/dashboard.css
 M resources/js/dashboard.js
 M tests/Feature/DashboardTest.php
?? docs/AUDITORIA_DASHBOARD_FASE_3_2026-11-03.md
?? tests/Browser/DashboardFase3.mjs
?? tests/Feature/DashboardVisualFase3Test.php
```

No había archivos preparados en el índice. `public/build/` existe y está ignorado por Git. `git diff --check` pasó. Esta auditoría se añade después como otro archivo sin seguimiento.

El reflog registra el paso de `e525614` a `3b3f0e5` como `commit: auditorias` a las 21:31:56. El commit incluye el controlador, la consulta y pruebas de cálculos del Dashboard, la vista Blade de Fase 3, documentación y el cambio anterior del indicador de carga. La evidencia local identifica el commit y sus archivos; no permite atribuir con certeza su creación a una persona o herramienta.

## 3. Integridad del Dashboard

`resources/views/dashboard/index.blade.php`, `app/Http/Controllers/DashboardController.php`, `app/Queries/ConsultarDashboard.php` y `tests/Feature/DashboardCalculosTest.php` coinciden con `HEAD`. La vista consume el contrato `datosDashboard` preparado por el controlador y la consulta. CSS y JavaScript locales contienen los estilos y selectores utilizados por la vista; las dos pruebas visuales nuevas se conservan sin seguimiento.

El commit por sí solo no representa la entrega visual completa: una copia limitada a HEAD tendría la vista nueva junto con CSS y JavaScript anteriores. Los cambios locales deben conservarse antes de cualquier operación Git que pueda reemplazarlos.

El manifiesto de Vite local apunta a:

```text
assets/dashboard-CX0Khiyw.js
assets/dashboard-_DkjNqS-.css
```

Ambos archivos existen y contienen selectores y estilos característicos de Fase 3. Sus archivos fuente inspeccionados no son posteriores al manifiesto; las referencias del manifiesto existen y el fixture de la prueba visual previa referencia esos mismos nombres. `public/hot` estaba ausente.

Estas observaciones respaldan la correspondencia del build local con el trabajo inspeccionado. No son una reconstrucción reproducible byte a byte de todos los recursos ni prueban la versión servida a través del túnel. No se verificaron archivos ignorados ajenos al build. Un origen diferente, un manifiesto antiguo en un proceso persistente, caché o un `public/hot` posterior pueden cambiar qué recibe el navegador.

## 4. Fallo: listado por fecha y turno

**Prueba:** `tests/Feature/DashboardTest.php`, «hoy y la fecha por defecto siguen el día de Lima sin desplazar DATE ni filtros del historial», aserción de `detallesRegistrados` en la línea 252.

La prueba crea dos lotes de Pan con fechas distintas y espera que el listado inicial de Producción incluya únicamente el lote de la fecha local y turno seleccionados. `ProduccionController::index`, en cambio, entrega a `ConsultarHistorialProduccion` solo la categoría de la sección activa. El listado comprende fechas y turnos de esa familia; fecha y turno siguen siendo datos del formulario de producción.

La documentación funcional existente registra expresamente ese cambio de comportamiento. El fallo también se reprodujo en la Fase 2 con el controlador anterior de Dashboard, en una MariaDB ficticia aislada.

**Diagnóstico:** la aserción del listado está desactualizada respecto del comportamiento documentado. La prueba también comprueba el cambio de día de Lima y los filtros de Historial, por lo que no corresponde eliminarla.

**Corrección mínima propuesta:** ajustar esa aserción para comprobar los lotes de la categoría activa, sin retirar las comprobaciones de fecha local e Historial. Conservar o añadir una prueba explícita de que la fecha y el turno del formulario no filtran el listado automático.

## 5. Fallo: categoría vacía

**Prueba:** `tests/Feature/ExperienciaVisualFase2Test.php`, «listado automático rechaza sección o página malformadas sin mostrar tarjetas», caso `categoria => ''`.

La prueba espera que `?categoria=` sea inválida y no muestre tarjetas. El contrato documentado distingue categoría ausente —usa Pan— y categoría vacía —se rechaza—.

`ConsultarRegistrosProduccionRequest::validationData()` usa `$this->query('categoria', 'pan')`. El middleware `ConvertEmptyStringsToNull` transforma la cadena vacía en `null`; el acceso de Laravel con valor predeterminado también devuelve `pan` para ese `null`. La validación recibe entonces una categoría válida y el endpoint consulta Pan.

Una simulación de lectura en memoria, sin base de datos, observó:

```text
/produccion/registros                 clave ausente; query con default = pan
/produccion/registros?categoria=      clave presente y null; query con default = pan
/produccion/registros?categoria=pan   clave presente y pan; query con default = pan
```

El fallo del caso vacío ya se había reproducido durante la Fase 2 en MariaDB ficticia, también con el controlador anterior de Dashboard. No se ha demostrado que el cambio normal de sección de la interfaz envíe una categoría vacía.

**Diagnóstico:** posible defecto funcional del endpoint frente al contrato documentado; no corresponde debilitar la prueba para aceptar Pan sin una decisión distinta.

**Corrección mínima propuesta:** comprobar la presencia de la clave en la consulta antes de aplicar el valor predeterminado. Usar Pan solo cuando falta la clave; dejar que el validador rechace un valor presente pero vacío o nulo. Cubrir por separado ausencia, vacío, espacios, arreglo, valor desconocido y valores válidos.

## 6. Evidencia y límites

La auditoría de Fase 3 registra una regresión aislada de **152 pruebas: 150 aprobadas y 2 fallidas**, correspondientes a los casos anteriores. En Fase 2 se compararon con una copia temporal y controlador previo; una tercera falla de esa copia se debió a que se excluyó su manifiesto Vite y no se atribuyó al proyecto.

En esta verificación no se repitieron las pruebas con MariaDB ni se ejecutaron migraciones. Solo se inspeccionaron archivos, Git, manifiesto y recursos compilados, y se simuló en memoria la normalización de categoría vacía. No se verificaron el túnel, el navegador móvil, datos reales o el esquema operativo.

## 7. Acciones recomendadas

1. Preservar juntos los cambios locales de Fase 3 y la vista ya incluida en HEAD antes de cualquier operación Git.
2. Con autorización para corregir, actualizar la aserción desactualizada y ajustar el Request de categoría vacía, manteniendo los cambios y sus pruebas claramente separados.
3. Repetir la regresión relevante en una instancia de pruebas aislada y confirmar qué assets entrega el entorno antes de las pruebas móviles.
4. Mantener pendientes la aceptación en dispositivos, la investigación de lentitud y el salto del sidebar.

No se modificaron código, datos, configuración o documentación del Vault durante la investigación. Esta nota se creó después por solicitud expresa. No se hicieron commits, push, reset, checkout o stash.
