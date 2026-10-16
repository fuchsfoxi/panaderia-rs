# Mejora visual - Historial de producción

Fecha: 2026-10-15. Estado: **IMPLEMENTADO Y COMPROBADO en HTTP/MariaDB aislada y Chrome headless con fixtures**. Aceptación visual del usuario, dispositivos físicos y sesiones database: **PENDIENTES**. Sin commit ni push.

## Problema visual encontrado

La pantalla funcional desaprovechaba el ancho de escritorio: contenedor limitado a 700 px, selectores de hasta 260 px y filtros repartidos en bloques apilados. Desde/Hasta tenían su propio encabezado y Turno otro bloque; las acciones quedaban visualmente separadas. Las tarjetas mostraban datos como párrafos consecutivos y «Ver detalles» carecía de presencia.

Reproducción previa en Chrome con la vista real y datos ficticios: a 1280 px, contenido de 700 px; Tipo y Turno de 260 px, fechas de 163 px; Tipo, fechas y Turno en tres alturas. A 320/375 px los cuatro controles ocupaban filas separadas. No se reprodujo un fallo funcional de filtros ni de consulta.

## Estado anterior

Checkout inicial limpio. Se inspeccionaron rutas, HistorialController, ConsultarHistorialProduccion, ConsultarHistorialProduccionRequest, vista, CSS, JS, estilos/componente compartido del sidebar, entrada Vite y tests consumidores.

Archivos reales de la pantalla:

- resources/views/history/index.blade.php: formulario GET, errores, contador, agrupación por fecha, tarjetas, details/summary y paginación.
- resources/css/historial.css: estilos propios; importa sidebar.css, que conserva la reserva lateral y navegación móvil existentes.
- resources/js/historial.js: únicamente comentarios; no controla filtros ni detalles. No se modificó.
- Backend existente: pagina hasta 15 lotes, hidrata modelos/relaciones por familia y carga roles de participantes. No se modificó.

Datos disponibles y reutilizados: categoría por clase del detalle, producto, cantidad, fecha/turno de sesión, Maestro/Ayudantes por rol del pivot, observación, autor del lote o autor histórico de sesión. Solo Pan dispone de unidad, snapshot, estimación y hora de finalización; Torta aporta forma/foto. No se añadieron datos ni consultas.

## Cambios realizados

- Contenedor máximo de 1080 px, conservando margen del sidebar, encabezado, paleta, tipografía declarada y radios orgánicos.
- Un bloque de filtros con borde discreto reúne controles y botones.
- Conteo y primera fecha comparten cabecera; fechas posteriores separan grupos con una línea sutil. La fecha no se repite en cada tarjeta.
- Cabecera de tarjeta con insignia de familia, producto y turno; resumen semántico dl/dt/dd para cantidad y Maestro.
- «Ver detalles» mantiene summary nativo, con borde, verde/beige y alineación inferior derecha del resumen. Al abrir muestra las agrupaciones debajo; no hay JS nuevo.
- Detalles organizados con encabezados y una cuadrícula adaptable. Observaciones aparece solo si tiene contenido.
- Cantidad abreviada: latas para Pan, tortas para Torta y bocaditos para Bocadito. No cambia el número almacenado ni se aplica ninguna conversión.
- Se retiraron reglas CSS de envoltorios que ya no existen; no se modificó CSS del sidebar ni de otros módulos.

## Distribución de filtros

Desktop desde 1024 px: cuatro columnas, en orden Tipo de producción | Desde | Hasta | Turno. Tipo/Turno usan proporción 1,25 frente a 1 de cada fecha. Los botones comparten el mismo formulario y bloque, en su fila inferior alineada a la derecha.

Tablet hasta 1023 px: dos columnas. Móvil hasta 599 px: Tipo y Turno ocupan todo el bloque; Desde/Hasta comparten la fila central y las acciones reparten el ancho. Se mantienen exactamente action, method, name, id, valores, opciones y asociaciones de errores existentes.

## Diseño de tarjetas

Cabecera: familia y nombre del producto; Turno a la derecha en escritorio, debajo en móvil. Cantidad destacada y Maestro tienen etiquetas pequeñas y valores legibles. La fecha pertenece al encabezado del grupo, sin inventar ni duplicar horas.

El detalle expandido conserva los datos reales:

- Producción: coches/resto/total, unidad, snapshot, estimación si hay factor positivo y hora exclusivamente en Pan; forma y foto opcional en Torta. Bocadito no muestra esa sección porque su cantidad ya está en el resumen.
- Equipo: Ayudantes y el estado desconocido preexistente cuando faltan registros. Maestro permanece en el resumen.
- Información general: autor individual; para históricos desconocidos, explicación y creador de sesión por separado.
- Observaciones: texto existente, escapado y respetando saltos; sin sección vacía cuando falta.

Se mantiene el comportamiento accesible de details/summary, las fotos lazy con alt/enlace y todos los fallbacks históricos. No se cambia la lógica de snapshot ni la cantidad directa.

## Responsive

| Ancho | Contenido medido | Distribución de filtros | Resultado |
|---|---:|---|---|
| 320 px | 288 px | Tipo completo; fechas juntas; Turno completo; acciones 50/50 | Sin scroll horizontal |
| 375 px | 343 px | Igual que 320 px | Sin scroll horizontal |
| 768 px | 628 px | Dos columnas | Sin scroll horizontal |
| 1280 px | 1080 px | Cuatro columnas: 275 / 220 / 220 / 275 px | Sin scroll horizontal |

Cuadrícula interna de detalles de dos columnas en escritorio/tablet y una en móvil. min-width:0 y overflow-wrap permiten nombres/observaciones largos sin ocultar el desbordamiento global. Las imágenes caben en su sección. La paginación se adapta sin cortar enlaces.

A 320 px se detectó en la captura intermedia que el texto nativo de fecha quedaba justo junto al icono. Se redujo su padding y el margen/relleno del indicador WebKit; se conservaron input date y tamaño de texto. Captura final revisada: ambos controles e iconos contenidos.

## Accesibilidad

Labels reales y únicos, inputs/selects nativos, errores con aria-invalid/aria-describedby y mensajes role=alert conservados. Conteo/estado vacío siguen usando role=status. El formulario tiene nombre accesible; los encabezados de fechas y secciones aclaran jerarquía.

Foco visible de filtros/acciones conservado; summary tiene contorno propio. Tab desde Turno recorre Limpiar → Filtrar → Ver detalles; Enter abre/cierra details. Comprobado en Chrome en los cuatro anchos. Verificación de FormData: siguen presentes categoria, fecha_inicio, fecha_fin y turno_id; no se enviaron formularios desde la prueba visual.

Texto pequeño de tarjetas y contenido expandido usa #33403A sobre #DCD6C4: contraste calculado **7,47:1**. Se evitó #4F6355 sobre beige para ese texto (4,45:1). Labels sobre crema conservan #4F6355, con **5,81:1**. Esto comprueba esas combinaciones, no constituye una auditoría integral WCAG ni una prueba de lector de pantalla.

## Archivos modificados

- resources/views/history/index.blade.php: distribución, jerarquía, agrupaciones y etiquetas de cantidad.
- resources/css/historial.css: estilos y responsive exclusivos de la pantalla.
- tests/Feature/HistorialProduccionTest.php: expectativa de latas abreviada conservando cantidad exacta.
- tests/Feature/ProduccionPanStoreTest.php: misma actualización en la comprobación del resultado de Historial.
- tests/Feature/ProductosTest.php: resultado de Historial expresa 8 tortas/150 bocaditos; conserva rechazos de información Pan en otras familias.
- tests/Feature/DashboardTest.php: solo la expectativa de texto de Historial al final del recorrido; aplicación Dashboard intacta.
- docs/MEJORA_VISUAL_HISTORIAL.md: informe solicitado.

No se eliminaron pruebas ni aserciones. Las cuatro adaptaciones de tests mantienen números, unidades y las demás garantías; no hay cambios de reglas para obtener resultados verdes.

Notas originales del Vault actualizadas, tras inspección y lectura íntegra: LARAVEL/Blade.md (implementación), PRODUCCION/Pantallas.md (presentación funcional) e INICIO/Estado de proyecto.md (resumen/evidencia). Se conservan las entradas anteriores. mejora_asignado.md no existe en este checkout; no se recreó.

## Tests ejecutados

```bash
PAO_DISABLE=1 php tests/run-mariadb.php --compact --filter='HistorialProduccionTest|ProduccionPanStoreTest|ProduccionTortaBocaditoStoreTest|ProductosTest|NavigationTest|AutorizacionRolesTest|DashboardTest'
PAO_DISABLE=1 php tests/run-mariadb.php --compact
node --test --test-isolation=none tests/JavaScript/*.mjs
npm run build
PAO_DISABLE=1 vendor/bin/pint --test tests/Feature/HistorialProduccionTest.php tests/Feature/ProduccionPanStoreTest.php tests/Feature/ProductosTest.php tests/Feature/DashboardTest.php
git diff --check
```

MariaDB desechable sin red bajo /tmp; runner y IsolatedMariaDb verifican socket/datadir/base. Los tests usan sesiones array, withoutVite y fixtures. No se consultó ni modificó la base real, ni se aplicaron allí migraciones/seeders. Compilación de assets verificada por separado.

No hay tests JavaScript específicos de Historial porque no tiene interacción propia en JS; se ejecutaron los 29 existentes, incluido sidebar compartido. Las comprobaciones de esta presentación se hicieron además en Chrome.

Prueba visual temporal, fuera del repositorio:

```bash
php /tmp/historial-visual/render.php
node /tmp/historial-visual/verificar.mjs despues
```

El render usa los modelos reales con relaciones ficticias en memoria; las vistas Blade y assets son reales. Servidor estático GET/HEAD en loopback, perfil Chrome temporal, sin backend operativo. Se incluyeron Pan/Torta/Bocadito, dos fechas, foto ficticia, nombre largo continuo, observación extensa y datos históricos desconocidos.

## Resultado de pruebas

- Primera regresión: **510 aprobadas, 1 fallida**, 4.604 aserciones. Causa investigada: DashboardTest terminaba comprobando en Historial la frase antigua «latas en total». Se actualizó solo esa expectativa, manteniendo el valor exacto; no se modificó Dashboard ni backend.
- Regresión final relevante: **511 aprobadas, 4.604 aserciones, 0 fallidas/omitidas**.
- Suite PHP completa: **621 aprobadas, 5.682 aserciones, 0 fallidas/omitidas**.
- JavaScript: **29 aprobadas, 0 fallidas/omitidas**.
- Build: **aprobado**. Aviso preexistente de paquete opcional fontaine, sin impedir compilación.
- Pint de los cuatro PHP de tests afectados y diff: **aprobados**. Blade se compiló/renderizó en HTTP y en las vistas visuales; no se afirma formato global del repositorio.
- Chrome: **20/20 combinaciones de ancho/estado**, sin scroll horizontal, controles/imágenes fuera del viewport ni excepciones JS. Cuatro anchos × normal cerrado/abierto, vacío, errores y sin JS. Capturas y métricas revisadas; Tab/foco/Enter/labels/errores/FormData comprobados.

Artefactos temporales: /tmp/historial-visual/antes.json, despues.json, php-final.log, php-completa.log, js.log, build.log y capturas antes/después de cada ancho. Capturas finales: despues-320.png, despues-375.png, despues-768.png, despues-1280.png y sus variantes -abierto.png. No se guardan datos operativos ni credenciales.

El sandbox bloqueó el servidor estático; se usó escalamiento para Chrome/loopback y el runner MariaDB. Ambos terminaron; no se deja un servidor de pruebas activo.

## Limitaciones de la validación visual

Se utilizó Chrome headless real con viewport emulado y datos ficticios; no es prueba de un dispositivo físico. Las fuentes externas se bloquearon para aislar la prueba: se midió con fallback sans-serif, sin certificar la carga/geometría de Huninn. La foto visual fue un fixture, no una imagen de negocio.

La prueba estática no envía filtros ni certifica sesiones database: consulta, filtros, paginación, autorización y persistencia se verificaron separadamente mediante HTTP y MariaDB aislada. No se abrió entorno de despliegue ni túnel público. No se certifican Safari/iOS/Firefox, lector de pantalla, zoom del navegador o teclado virtual.

## Pendientes

- Revisión y aceptación visual del usuario en su instalación con fuentes definitivas.
- Comprobar dispositivos físicos, Safari/iOS, fechas según configuración regional, zoom y lector de pantalla.
- Validar el recorrido operativo con su driver de sesiones real, sin crear datos de prueba en la base de negocio.
- No se cambia estado de migraciones/unidades de la tarea anterior ni se afirma cierre total del Sprint 5.

## Pruebas manuales recomendadas

1. Abrir Historial a 320, 375, 768 y ≥1280 px con Huninn cargada; revisar filas de filtros, fechas, tarjetas, textos largos y paginación.
2. Elegir categoría/fechas/turno, Filtrar, avanzar de página y Limpiar; confirmar que el comportamiento previo se conserva.
3. Recorrer controles con Tab y abrir/cerrar Ver detalles con Enter/Espacio; revisar foco y lectura de encabezados/labels.
4. Revisar varias fechas y familias: Pan conserva latas/snapshot/estimación; Torta muestra tortas y Bocadito bocaditos, sin datos Pan.
5. Abrir detalles, foto, observaciones largas y registros históricos desconocidos. Confirmar que no se cortan y no se inventan datos.
6. Provocar filtros inválidos y ausencia de resultados; comprobar mensajes asociados y distribución.
7. Repetir con JS desactivado, zoom aumentado y un dispositivo físico. Sidebar queda con su comportamiento existente.
