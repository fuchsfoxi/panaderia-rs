# Auditoría de la Fase 4: indicador de navegación y sidebar móvil

**Fecha:** 3 de noviembre de 2026.  
**Alcance:** corrección del indicador y evaluación de la alternativa para el sidebar. La lentitud móvil y el salto del sidebar en el dispositivo real continúan EN INVESTIGACIÓN.

## Resultado

Se modificó el indicador para identificar la navegación vigente y evitar que la cancelación o el temporizador de una anterior borren el estado de otra. La condición se verificó con eventos sintéticos, no con navegaciones consecutivas reales en el celular.

No se modificó el sidebar. El HTML y CSS proporcionan un menú visible sin JavaScript; el módulo solo lo oculta después de instalar su comportamiento. Aplicar una clase que lo oculte antes de cargar el módulo dejaría el menú sin acceso si la carga o inicialización falla. Una alternativa que conserve recuperación sin JS y ante fallos, y mantenga el mismo diseño y accesibilidad, requiere un cambio más amplio y comprobación específica. Conforme a la restricción de la tarea, se detuvo esa modificación. El salto inicial reproducido en Fase 2 sigue pendiente.

## Indicador: cambio y fundamentos

`resources/js/indicadores-carga.js` asigna una identidad creciente a cada navegación. Los callbacks de aborto, cancelación tardía y temporizador del camino sin Navigation API solo restauran el estado cuando su identidad sigue vigente. Una restauración global por `pageshow` o `pagehide` invalida las identidades anteriores. La recuperación tardía de un formulario cancelado devuelve sus controles y, si ya comenzó una navegación posterior, conserva su indicador.

Los eventos globales `navigateerror` y `navigatesuccess` no identifican la navegación que los produjo; ya no restauran directamente el estado compartido. El aborto se atiende mediante la señal del propio `NavigateEvent`; el estándar describe que el algoritmo de aborto señala esa propiedad antes de emitir `navigateerror`. La salida o recuperación del documento se atiende mediante `pagehide`/`pageshow`. No se intercepta la navegación ni se añade un temporizador. Se conserva el temporizador preexistente de 15 segundos del camino sin Navigation API. Referencia: [HTML Standard: aborto de una navegación](https://html.spec.whatwg.org/multipage/nav-history-apis.html#abort-the-ongoing-navigation).

El proyecto usa enlaces de documento completo; no se comprobó el comportamiento de una navegación interceptada por código externo. Los eventos sintéticos de éxito/error sin identidad no prueban el orden real de un navegador. No se modificó CSS, el formulario de producción, el controlador, las rutas ni lógica de negocio.

## Pruebas y resultados

- Se examinaron `tests/JavaScript/SidebarTest.mjs`, `tests/Browser/IndicadoresCarga.mjs` y `tests/Feature/IndicadoresCargaTest.php`. El navegador existente necesita fixtures exportados por una prueba que ejecuta migraciones; no se ejecutó esa preparación ni se tocó ninguna base de datos.
- `node tests/JavaScript/IndicadoresNavegacionTest.mjs`: **4 casos aprobados**, con el módulo fuente y DOM/eventos simulados. Comprueban aborto y cancelación anteriores, eventos globales sin identidad, cancelación vigente, `pagehide/pageshow`, fallback y exclusión del indicador de producción ocupado. Las señales y su orden se controlaron en la prueba.
- `node --test tests/JavaScript/*.mjs`: **8 archivos aprobados**, incluidos sidebar, producción y la nueva prueba. Los dobles de DOM no certifican CSS, red o navegación real.
- Chrome headless, fixture HTML independiente con JavaScript y CSS fuente e interceptación de todas las solicitudes: en 360 px, sidebar cerrado con JS, apertura y Escape, y panel estático visible sin JS; en 1280 px, panel fijo visible con y sin JS. Un POST ficticio recibió una redirección y terminó en el documento de destino para ambos anchos con JS. No se ejecutó Blade ni el controlador, ni se usaron datos reales. La primera ejecución dentro del sandbox falló por restricciones de socket; la repetición escalada pasó.
- `npm run build`: aprobado; Vite mostró el aviso de que `fontaine` es opcional. El build genera recursos locales en `public/build`; no se instaló ninguna dependencia ni se realizó despliegue.
- `git diff --check`: aprobado. No se realizaron migraciones, cambios de base de datos, commit ni push.

## Limitaciones y próximos pasos

No se probaron teléfono físico, Safari, lector de pantalla, navegación real consecutiva, errores reales de red ni BFCache real. El POST ficticio no demuestra el guardado Laravel de Pan, Torta o Bocadito. El indicador se considera implementado y comprobado solo bajo las condiciones de aislamiento descritas. El sidebar sigue sin corrección y la duración de navegación no tiene causa demostrada.

Para comprobar en el celular, con un entorno aislado y datos ficticios, cargar los recursos compilados correspondientes a este checkout, abrir las tres pantallas, tocar enlaces consecutivos y observar el indicador; ensayar también Atrás/Adelante y, únicamente con datos ficticios, el guardado y redirección. Registrar navegador, versión, caché, tiempos de red y primer pintado. Si se prueba JavaScript desactivado, comprobar que los enlaces del menú sigan accesibles. No atribuir la demora a ninguna capa sin mediciones.
