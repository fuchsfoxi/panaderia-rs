# Seminario: diagnóstico de navegación móvil y sidebar en Panadería RS

**Fecha:** 3 de noviembre de 2026  
**Estado:** EN INVESTIGACIÓN  
**Alcance:** diagnóstico y propuesta; sin correcciones implementadas.

## 1. Introducción

Durante el uso de Panadería RS en un celular se reportaron dos comportamientos: al cambiar entre Dashboard, Producción e Historial, aparece un indicador de carga mientras la pantalla anterior permanece visible varios segundos; después de guardar una producción, el menú lateral puede aparecer expandido momentáneamente. El celular y la laptop utilizan el mismo enlace de Cloudflare Tunnel y la misma red Wi-Fi, pero la navegación en la laptop se percibe fluida.

El objetivo de este seminario es explicar el funcionamiento observado en el código, distinguir lo reproducido de lo que todavía es una hipótesis y establecer un camino seguro para encontrar la causa real. Compartir conexión y túnel no significa que los dispositivos tengan la misma caché, navegador, capacidad de procesamiento o condiciones de red.

## 2. Resumen de hallazgos

| Tema | Evidencia | Estado y límite |
|---|---|---|
| Página anterior durante la navegación | Los enlaces abren documentos completos y el indicador se superpone a la página saliente. | Funcionamiento explicado por el código; la duración de la espera no está medida. |
| Lentitud en el celular | Se conoce el reporte y los posibles puntos de espera. | EN INVESTIGACIÓN: no se ha atribuido a red, túnel, servidor, consultas, recursos ni navegador. |
| Sidebar expandido | Una simulación previa retrasó JavaScript y reprodujo el panel en flujo antes de `sidebar-enhanced`. | Mecanismo reproducido en un documento ficticio; falta relacionarlo con el celular real. |
| Carrera del indicador | En eventos sintéticos, el aborto de una navegación anterior ocultó el indicador de otra posterior. | Reproducida en aislamiento; falta comprobar el orden de eventos en navegación real. |

## 3. Cómo funciona la navegación

### 3.1. Enlaces y documentos completos

Los enlaces de `resources/views/components/sidebar.blade.php` llevan a las rutas de Dashboard, Producción e Historial. Al pulsarlos, el navegador solicita un documento nuevo. Mientras no se reemplaza el documento actual, la pantalla anterior puede permanecer visible.

`resources/js/indicadores-carga.js` muestra «Cargando…» para la navegación interna. El aviso es un elemento fijo y no bloqueante definido en `resources/css/indicadores-carga.css`. Su presencia explica por qué el usuario ve una señal de espera sobre la pantalla anterior; no mide ni causa por sí sola los segundos reportados.

Cuando existe Navigation API, el módulo escucha `navigate` y registra un manejador de aborto. También escucha `navigateerror` y `navigatesuccess`. Sin esa API, utiliza un manejador de clic y un temporizador de recuperación de 15 segundos. En ambos casos se escucha `pageshow` y `pagehide`. La función `restaurar()` limpia el estado compartido y oculta el aviso. El temporizador es una medida de recuperación para el camino alternativo, no una medición de la latencia observada.

### 3.2. Guardado y redirección

Guardar producción tiene un recorrido distinto del enlace del menú. `resources/js/produccion.js` presenta «Guardando…» y administra el estado del formulario. El envío es un POST nativo. Después de completar el registro, `app/Http/Controllers/ProduccionController.php` responde con una redirección a `produccion.index`, que genera un GET de un nuevo documento.

Por ello, una medición del guardado debe separar la duración del POST de la del GET posterior. En cambio, las consultas parciales de filtros usan `fetch` y actualizan una región: no describen el recorrido normal de los enlaces principales.

### 3.3. Historial del navegador

`resources/js/app.js` recarga una página recuperada desde BFCache cuando `pageshow.persisted` es verdadero, como parte de la revalidación de acceso. Atrás y Adelante pueden, por tanto, seguir un recorrido distinto de un enlace normal. La prueba debe distinguirlos.

## 4. Qué puede consumir tiempo

Una navegación atraviesa varias fases, y no se midió ninguna en el celular durante esta investigación:

1. Resolución DNS, conexión y TLS cuando corresponda.
2. Tránsito por la red y Cloudflare Tunnel hasta el origen.
3. Procesamiento de Laravel, incluyendo middleware, consultas y construcción de la respuesta.
4. Espera al primer byte y descarga del documento.
5. Descarga y procesamiento de CSS, JavaScript y otros recursos.
6. Inicialización de la página y pintado en el navegador.

Los controladores consultan datos: Dashboard realiza agregaciones; Producción carga catálogos, empleados, grupos y registros; Historial consulta resultados paginados. La existencia de estas operaciones no demuestra que sean lentas. Las vistas usan recursos de Vite y el CSS del sidebar declara una fuente externa; tampoco se ha demostrado que esos recursos provoquen la espera. Hay que medir los recursos realmente recibidos por el dispositivo y no asumir que coinciden con el código fuente revisado.

## 5. Estado inicial del sidebar

En `resources/views/components/sidebar.blade.php`, el panel forma parte del HTML y el botón de apertura comienza oculto. A 767 px o menos, `resources/css/sidebar.css` muestra el panel completo dentro del flujo como alternativa si JavaScript no se ejecuta. `resources/js/sidebar.js` prepara el estado cerrado, administra foco, atributos y `inert`, y añade `sidebar-enhanced` al `body`. Con esa clase, el CSS presenta el panel móvil como fijo y oculto hasta que se abre.

Si el navegador pinta el documento antes de ejecutar el módulo, puede mostrar el menú completo y después retirarlo del flujo. La Fase 2 reprodujo esta secuencia con HTML ficticio y una retención deliberada del módulo: el contenido cambió de posición 236 px a 360 y 767 px. Esa cifra describe el fixture, no el teléfono ni un tiempo de carga. A 768 y 1280 px el fixture conservó el panel compacto sin ese desplazamiento.

La misma fase ensayó un POST ficticio seguido de una redirección y observó que el documento resultante alcanzaba el estado `sidebar-enhanced`; no midió por separado el desplazamiento durante esa redirección. El controlador real vuelve a Producción tras el guardado, lo que hace plausible que se repita la inicialización. Todavía no se ha observado el primer pintado del teléfono para atribuirle el síntoma reportado.

## 6. Condición de carrera del indicador

Los manejadores de navegación comparten `restaurar()`, sin asociar explícitamente su efecto visual con la navegación que activó el aviso. En la Fase 3 se ejecutó el código fuente en memoria con objetos de eventos controlados:

1. Se emitió una navegación ficticia A y luego otra B; el indicador estaba visible.
2. Se abortó A después de iniciar B.
3. El indicador quedó oculto, aunque B era la navegación más reciente del escenario.

También se emitieron eventos sintéticos de éxito y error después de iniciar otra navegación y se observó la restauración global. Estas pruebas demuestran el comportamiento del código bajo el orden impuesto, no que un navegador real produzca ese orden ni que la carrera explique la duración de la espera. La Fase 2 había reproducido el aborto sintético; su intento con clics reales consecutivos no terminó correctamente y no se considera aprobado.

## 7. Propuestas y riesgos

| Propuesta posterior | Propósito | Riesgo y comprobaciones |
|---|---|---|
| Medir antes de optimizar la navegación. | Localizar la fase que consume tiempo y actuar sobre ella. | Cambios sin medición pueden afectar una capa que no causa el problema. Comparar dispositivo, caché, túnel y origen de prueba. |
| Investigar un estado móvil cerrado antes del primer pintado, si se confirma el mecanismo en el celular. | Evitar que el panel completo aparezca transitoriamente. | Ocultarlo antes de que los controles funcionen puede impedir navegar. Comprobar fallos y ausencia de JS, foco, `inert`, teclado, apertura, cierre y cambio de ancho. |
| Asociar la restauración del indicador a la navegación vigente, si se confirma la carrera en un navegador real. | Evitar que una navegación antigua altere el aviso de una posterior. | Podría dejar el aviso visible u ocultarlo antes de tiempo. Comprobar éxito, error, aborto, clics consecutivos, formularios, alternativa sin Navigation API y BFCache. |

Estas propuestas requieren autorización antes de modificar código. Corregir el indicador no reduce por sí mismo la latencia de red o servidor.

## 8. Plan de verificación

Realizar las pruebas en un entorno aislado, con cuenta y datos ficticios, sin crear registros de producción reales:

1. Registrar modelo del celular, navegador y versión, ancho de pantalla y disponibilidad de Navigation API. Repetir los mismos recorridos en laptop y celular, con caché fría y caliente.
2. Capturar en herramientas remotas los tiempos de DNS, conexión/TLS, primer byte, descarga del documento y recursos, ejecución de JavaScript y primer pintado. Registrar errores y cancelaciones sin conservar credenciales, cookies ni datos personales.
3. Cuando sea posible, comparar el túnel con acceso directo equivalente al mismo servidor de pruebas. Contrastar el tiempo de respuesta con mediciones seguras del servidor y de sus consultas.
4. Grabar el primer pintado e identificar cuándo se añade `sidebar-enhanced` en navegación por enlace, recarga y POST ficticio con redirección.
5. Ensayar navegaciones consecutivas, cancelación, error, éxito y Atrás/Adelante en navegadores pertinentes, con y sin Navigation API cuando sea viable.
6. Tras una corrección autorizada, repetir los recorridos y verificar accesibilidad, estado sin JavaScript, fallo de inicialización, escritorio y cambios de tamaño.

## 9. Archivos de referencia

- `resources/views/components/sidebar.blade.php`: estructura del menú y enlaces.
- `resources/css/sidebar.css`: alternativa móvil sin JS y estado mejorado.
- `resources/js/sidebar.js`: inicialización y controles del menú.
- `resources/js/indicadores-carga.js` y `resources/css/indicadores-carga.css`: ciclo y presentación del indicador.
- `resources/js/produccion.js` y `app/Http/Controllers/ProduccionController.php`: estado del envío y redirección después del guardado.
- `resources/js/app.js`: revalidación al restaurar BFCache.
- `docs/AUDITORIA_NAVEGACION_SIDEBAR_MOVIL_2026-11-03.md` y `docs/INFORME_FASE_2_NAVEGACION_SIDEBAR_MOVIL_2026-11-03.md`: antecedentes y límites de las pruebas previas.

## 10. Alcance de la evidencia

La Fase 3 consistió en inspección del código y una simulación de eventos en Node en memoria, sin acceso a red ni base de datos. No se midió el celular, el servidor ni Cloudflare Tunnel; no se comprobó el orden de eventos reales en un navegador móvil. No se modificaron código, datos, configuraciones o dependencias para el diagnóstico. Ambas incidencias permanecen EN INVESTIGACIÓN y ninguna corrección se considera implementada o comprobada.
