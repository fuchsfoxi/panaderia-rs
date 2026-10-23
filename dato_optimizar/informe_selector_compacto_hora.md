# Selector compacto de hora — Panadería RS

Fecha: 23 de octubre de 2026.

**IMPLEMENTADO Y COMPROBADO en aislamiento y Chrome automatizado. Android físico: PENDIENTE.**

Antes se introducía texto como `0236` o `02:36`. Ahora finalización y vitrina utilizan dos selectores horizontales: Hora (00–23) y Minutos (00–59), con opciones iniciales HH/MM vacías, sin asignar la hora actual, tarjeta adicional ni instrucciones repetidas.

Un componente Blade compartido recupera ambas selecciones desde el valor saneado de `old()`. Cada control tiene etiqueta y un fieldset/legend identifica la hora correspondiente; los errores del servidor se asocian a ambos selectores. Se mantiene el estilo existente y controles de al menos 44 px.

JavaScript combina exclusivamente selecciones completas en un campo oculto con el nombre anterior, por ejemplo `detalles[0][hora_vitrina]=14:36`. Los selectores no tienen nombre de envío: no generan parámetros adicionales ni duplicados. Si falta una parte, el valor compuesto queda vacío y la validación nativa impide continuar cuando es obligatorio. El campo oculto comienza deshabilitado hasta que JavaScript sincroniza los controles y aplica la categoría: sin JavaScript no se reenvía una hora antigua distinta de lo seleccionado; Laravel mantiene el rechazo de la hora requerida ausente.

Se conservaron las reglas actuales: finalización obligatoria en Pan LATA y vitrina obligatoria en Torta; Pan UNIDAD y Bocadito no envían esas horas. Cambiar categoría, pasar de LATA a UNIDAD o cancelar limpia y deshabilita los controles según el comportamiento existente. Cambiar de producto compatible conserva la hora. No se cambiaron Requests, Actions, esquema, turnos, participantes, cálculos ni otros módulos.

## Archivos de esta tarea

- Nuevo: `resources/views/components/selector-hora.blade.php`.
- Modificados: `resources/views/produccion/index.blade.php`, `resources/js/produccion.js`, `resources/css/produccion.css`.
- Pruebas adaptadas y ampliadas: `tests/JavaScript/ProduccionCantidadTest.mjs`, `tests/Feature/ProduccionPanStoreTest.php`, `tests/Feature/ProduccionTortaBocaditoStoreTest.php`, `tests/Browser/ExperienciaFase2.mjs`, `tests/Browser/ProduccionMovil.mjs`.
- Informe: `dato_optimizar/informe_selector_compacto_hora.md`.
- Documentación original: `PRODUCCION/Pantallas.md` del Vault; actualización integrada sin borrar las entregas anteriores. No se encontró `mejora_asignado.md`.

Los demás cambios locales pertenecen a las fases anteriores y se conservaron. No se hizo commit ni push.

## Pruebas y resultados

- **274 pruebas PHP / 2564 aserciones aprobadas** con `php tests/run-mariadb.php --filter='ProduccionPanStoreTest|ProduccionTortaBocaditoStoreTest|ProduccionCategoriasMovilTest|ExperienciaVisualFase2Test|ProduccionRecursosCompiladosTest'`. Servidor MariaDB temporal sin red y bases ficticias; sin acceso a la base principal. Incluye persistencia de horas límite, rechazo de valores incompletos/imposibles en Laravel, old input, cantidades, categorías, grupos, turnos y participantes.
- **23 casos JavaScript de Producción aprobados** y los seis archivos de pruebas JavaScript existentes aprobados mediante `node --test tests/JavaScript/*.mjs` (**64 casos internos**). Se comprueban composición HH:MM, 00:00/23:59, selección parcial sin concatenaciones inválidas, recuperación inicial, limpieza y deshabilitación.
- **Chrome a 360, 412 y 1280 px aprobado** con el arnés actualizado `ExperienciaFase2.mjs`: selectores en una fila, rangos exactos, opciones iniciales vacías, rechazo de selección parcial, etiquetas/fieldset y teclado Tab, un solo valor de FormData, limpieza al cambiar familia y ausencia de desbordamiento. Regresiones del recorrido existente también aprobadas. Se sirvieron HTML ficticios renderizados por Laravel y los assets compilados reales, interceptando solicitudes; la persistencia se verifica por separado en PHP. No es una prueba contra la aplicación operativa ni Android real.
- **`npm run build`, Pint de PHP afectado y `git diff --check`: correctos.** No hay `public/hot`; las vistas de prueba usan Vite compilado. Advertencia opcional preexistente de fontaine sin impedir la compilación. El arnés anterior `ProduccionMovil.mjs` se adaptó a los selectores; el ejecutado en esta tarea es `ExperienciaFase2.mjs`.

Logs de esta sesión: `/tmp/panaderia-selector-hora-{php,js,build,browser}.log`. Fixtures/capturas: `/tmp/panaderia-selector-hora-browser/`. Son artefactos temporales. No se volvió a ejecutar la suite PHP global: se ejecutó la regresión pertinente de 274 pruebas.

## Pendiente de comprobación en Android

Con recursos compilados y el Tunnel habitual, abrir Producción en Android y probar Pan LATA y Torta: seleccionar ambas partes, intentar continuar dejando una vacía, corregir otro campo tras un error y comprobar la recuperación de la hora. Probar 00:00/23:59, cambiar categorías, Pan UNIDAD y Cancelar. Confirmar comodidad de los menús nativos, controles en una fila, foco y lector de pantalla. Registrar ejemplos únicamente en un entorno con datos ficticios.

No se verificó un dispositivo físico ni un lector de pantalla. Esta mejora no declara resuelto el congelamiento Android de la investigación anterior.
