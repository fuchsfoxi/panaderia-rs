# Panadería RS — Fase 2: verificación de navegación y sidebar móvil

**Fecha:** 3 de noviembre de 2026.

**Checkout examinado:** rama `master`, HEAD `616e2f8d7cc41a970e24f6e1bc3490b6a2c110b6`.

**Estado:** incidencias EN INVESTIGACIÓN. Este informe complementa `docs/AUDITORIA_NAVEGACION_SIDEBAR_MOVIL_2026-11-03.md`.

## Resumen

Una simulación aislada reprodujo el mecanismo que puede provocar la expansión temporal del sidebar: cuando se retrasa deliberadamente la inicialización de JavaScript, el panel móvil aparece completo y luego sale del flujo al añadirse `sidebar-enhanced`. La simulación confirma el mecanismo con HTML ficticio, pero no demuestra que haya causado el síntoma observado en el teléfono.

Una segunda prueba, con eventos sintéticos, mostró que el aborto de una navegación anterior puede ocultar el indicador de una navegación posterior si los eventos ocurren en el orden ensayado. Falta verificar si ese orden ocurre durante navegaciones reales. La causa de la espera de varios segundos sigue sin medición ni atribución.

## Alcance y aislamiento

Se revisaron las pruebas existentes y se usó Chrome con Puppeteer y respuestas ficticias interceptadas. No se accedió a producción, cuentas o registros reales; no se modificaron el código de aplicación, sus configuraciones, dependencias ni datos. No se ejecutaron migraciones, seeders, compilaciones ni se leyó `.env`.

La primera ejecución de Chrome dentro del sandbox falló por una restricción del entorno. Las simulaciones completadas se ejecutaron con autorización fuera del sandbox. Sus scripts de prueba se crearon en `/tmp`, fuera del repositorio, y no forman parte de la aplicación. Las solicitudes se interceptaron; la prueba del sidebar rechazaba orígenes externos y eliminó la importación de la fuente externa del CSS servido al fixture.

El HTML fue construido para la prueba: no es una página Blade renderizada y no reproduce todos los elementos y recursos del sistema. Se utilizó código fuente de CSS y JavaScript, adaptando en memoria las importaciones para servirlo en el fixture; no se verificaron los archivos compilados servidos al celular.

## Pruebas existentes

Se inspeccionaron `tests/Browser/IndicadoresCarga.mjs` y `tests/Browser/ProduccionMovil.mjs`. Ambos interceptan solicitudes y esperan fixtures HTML en `/tmp`. No se encontraron los fixtures necesarios y no se ejecutaron estas pruebas.

Los preparadores revisados, `tests/Feature/IndicadoresCargaTest.php:14` y `tests/Feature/ProduccionRecursosCompiladosTest.php:17`, ejecutan migraciones sobre una MariaDB de prueba y crean datos ficticios. Se evitó ejecutarlos para mantener el alcance sin cambios persistentes. Por ello no se atribuye a las pruebas existentes ningún resultado aprobado o fallido en esta fase.

## Prueba del sidebar

Se sirvió un documento ficticio con estructura representativa del componente y el CSS/JavaScript fuente compartido. Se retuvo deliberadamente `sidebar.js` antes de liberarlo y se observaron el panel y la posición del contenido antes y después de la inicialización.

| Viewport | Antes de inicializar | Después de inicializar | Cambio vertical del contenido en el fixture |
|---|---|---|---:|
| 360 px | Visible, estático y de ancho completo | Oculto y fijo | −236 px |
| 767 px | Visible, estático y de ancho completo | Oculto y fijo | −236 px |
| 768 px | Visible, fijo y compacto | Visible, fijo y compacto | 0 px |
| 1280 px | Visible, fijo y compacto | Visible, fijo y compacto | 0 px |

Los 236 px son una medida de este fixture y sus dimensiones; no son un tiempo ni una medida del dispositivo real. La retención del módulo fue deliberada: no se cuantificó cuánto tarda en cargarse en condiciones reales.

En 360 px se completaron además una navegación por enlace, una recarga y un POST ficticio respondido con una redirección a Producción. En los tres casos la página resultante llegó al estado `sidebar-enhanced`. El desplazamiento se midió en la carga inicial con el módulo retenido; no se midió por separado durante la recarga ni tras la redirección. No se ejecutó el controlador real ni se guardó una producción.

**Evidencia de código:** `resources/views/components/sidebar.blade.php:1` entrega el panel en el documento; `resources/css/sidebar.css:205` define el menú móvil en el flujo como alternativa sin JavaScript; `resources/css/sidebar.css:247` establece el panel fijo y oculto con `sidebar-enhanced`; `resources/js/sidebar.js:77` inicializa el estado y añade esa clase. El guardado real devuelve una redirección en `app/Http/Controllers/ProduccionController.php:117`.

**Conclusión:** mecanismo reproducido en una simulación controlada. Pendiente observar el primer pintado y la inicialización en el celular real para vincularlo con la incidencia informada.

## Prueba del indicador

Se cargó el código fuente de `resources/js/indicadores-carga.js` en una página ficticia y se sustituyó Navigation API por un `EventTarget` controlado. Se emitieron dos eventos de navegación y, después, se abortó el primero. El indicador, que se había mostrado para ambos eventos, quedó oculto tras ese aborto. El resultado es coherente con `resources/js/indicadores-carga.js:108`, donde cada señal de aborto llama a la misma función global de restauración.

En el ensayo del fallback sin Navigation API, una cancelación tardía mostró el indicador inmediatamente y lo ocultó después de procesar la cancelación. Esta prueba no simula todos los comportamientos de un navegador real.

Una prueba adicional con respuestas retenidas y clics consecutivos no terminó correctamente: una ejecución agotó el tiempo de espera y otra se interrumpió. No se considera aprobada. **Queda sin verificar** si una navegación real produce el orden de eventos sintético ni cómo se comporta el indicador con clics rápidos sobre el teléfono.

## Navegación lenta: causas pendientes

No se midieron DNS, conexión, túnel, primer byte, respuesta Laravel, consultas MariaDB, descarga de recursos ni pintado en el celular. No es posible atribuir a ninguna de esas capas los segundos observados. El indicador sobre la página saliente es compatible con la navegación completa descrita en `resources/js/indicadores-carga.js:37` y en los enlaces de `resources/views/components/sidebar.blade.php:27`; esto explica la presentación durante la espera, no su duración.

Una navegación normal solicita un documento nuevo. Una recarga repite la carga de la página. El guardado real usa POST y, al completarse correctamente, redirige a un GET de Producción. Atrás/Adelante puede activar la recarga de seguridad de BFCache en `resources/js/app.js:4`. Estos recorridos deben medirse por separado.

## Metodología propuesta para medición real

Usar únicamente un entorno aislado con cuenta y datos ficticios, sin información de producción:

1. Registrar modelo del dispositivo, navegador, versión, ancho del viewport y disponibilidad de Navigation API. Repetir el mismo recorrido en móvil y laptop con caché fría y caliente, sin inventar tiempos ni descartar variaciones.
2. Usar herramientas de desarrollo remotas para observar DNS, conexión y TLS, espera al primer byte, descarga del documento y recursos, `DOMContentLoaded`, ejecución de JavaScript y primer pintado. Registrar también errores de recursos y cancelaciones.
3. Comparar, cuando sea posible, acceso mediante túnel y acceso directo equivalente al mismo servidor aislado. Contrastar primer byte con tiempos del servidor y consultas obtenidos sin exponer datos sensibles.
4. Capturar una traza de rendimiento o video del primer pintado móvil e identificar cuándo se añade `sidebar-enhanced`. Repetir para enlace normal, recarga y POST ficticio seguido de redirección.
5. Ensayar clics consecutivos y cancelaciones reales con y sin Navigation API, y comprobar Atrás/Adelante. No conservar ni compartir cookies, credenciales, cabeceras de autorización o datos personales.

## Propuestas mínimas y riesgos

Para el sidebar, la evidencia simulada justifica investigar una inicialización del estado móvil mejorado antes del primer pintado, conservando una alternativa accesible si JavaScript está deshabilitado o falla. Comprobar foco, `inert`, navegación por teclado, apertura y cierre, tamaños móviles y escritorio. Ocultar el panel anticipadamente sin una recuperación adecuada puede impedir el acceso al menú.

Para el indicador, primero reproducir el orden de eventos con navegación real; si se confirma, asociar cada restauración a la navegación que activó el aviso. Probar abortos, errores, clics consecutivos y Atrás/Adelante. Una corrección defectuosa puede ocultar el aviso antes de tiempo o dejarlo visible. El indicador no reduce por sí mismo la latencia.

No se implementó ninguna corrección. Pendientes: reproducción en dispositivo, medición por capas, ejecución de las pruebas existentes cuando se disponga de fixtures aislados adecuados y validación de accesibilidad.

## Estado del repositorio

Al inicio y al final de las pruebas, la rama era `master` y el HEAD el indicado arriba. El único archivo sin seguimiento era el informe de auditoría anterior, ya existente antes de esta fase; no había diferencias de código registradas. Este informe se añadió posteriormente por solicitud expresa. No se modificó la documentación del Vault durante la fase de pruebas, que debía evitar cambios persistentes.
