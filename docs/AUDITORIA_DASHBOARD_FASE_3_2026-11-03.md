# Dashboard Fase 3 — auditoría visual y funcional

**Fecha:** 3 de noviembre de 2026.  
**Estado:** implementación y comprobación aislada; aceptación en dispositivos y regresión completa pendientes.

## Implementación

La vista de Dashboard presenta cuatro tarjetas de hoy, tres rankings mensuales, evolución temporal y actividad de las tres familias con el contrato de Fase 2. Pan tiene selectores de latas/unidades independientes para hoy y mes. Los resultados parciales se identifican y los rankings incompletos muestran un aviso en lugar de una clasificación. Sin JavaScript se muestran ambas magnitudes y sus avisos, y permanecen accesibles las tablas de datos del gráfico.

Un único canvas Chart.js presenta barras agrupadas de registros para siete días o el mes calendario completo. También conserva la visualización anterior de latas por turno del período consultado como tercera opción; el botón en la consulta histórica la selecciona en el mismo gráfico. El gráfico cuenta registros, no suma cantidades heterogéneas. Incluye leyenda con nombres y formas y tablas HTML; una nota aclara que los días futuros no son predicciones. Los colores de las familias sobre blanco tienen los contrastes calculados: Pan `#33403A` 10,85:1; Torta `#855332` 6,40:1; Bocadito `#23577A` 7,73:1. Son colores informativos, no sustituyen las etiquetas ni la tabla.

La actividad indica que el orden se basa en fecha de producción, no creación; muestra categoría, fecha, turno y hora con su significado o ausencia. El filtro histórico conserva su consulta parcial y resultados previos, separados de los datos fijos. Sus cambios no alteran los selectores de hoy/mes ni la serie seleccionada; el gráfico por turno se actualiza si está activo. No se añadieron consultas ni se cambió el contrato backend, sidebar, indicador, registro de producción, permisos o esquema.

El CSS se limita a Dashboard: fondo crema y superficies blancas, texto y foco verdes, con marrón y azul exclusivamente para diferenciar series. Cuatro columnas de tarjetas en escritorio pasan a dos en anchos intermedios y una hasta 360 px; rankings y actividad se apilan en móvil. Se conserva la preferencia de movimiento reducido y las transiciones existentes.

## Verificación

- `npm run build`: aprobado; aviso opcional preexistente `fontaine`. Genera recursos locales en `public/build`.
- Regresión PHP aislada con `DashboardVisualFase3Test`, `DashboardCalculosTest`, `DashboardTest`, `ExperienciaVisualFase2Test`, `PanRegistroUnidadTest` y `NavigationTest`: **152 pruebas, 150 aprobadas, 2 fallidas, 1.218 aserciones**. Las dos fallidas son las mismas de Producción documentadas y reproducidas con código anterior en Fase 2: expectativa antigua del listado por fecha/turno y categoría vacía. No se corrigieron ni se afirma que la regresión total haya pasado.
- Ejecución posterior específica `DashboardVisualFase3Test`: **4 aprobadas / 28 aserciones**, con caso adicional de unidad histórica desconocida.
- Chrome headless: **8 combinaciones aprobadas** de 320/360/768/1280 px con/sin JS. Vistas Blade renderizadas con datos ficticios y recursos compilados; todas las solicitudes interceptadas, fuentes externas bloqueadas. Se verificaron selectores independientes sin peticiones, avisos de ambas magnitudes incompletas, gráficos y tablas, filtro histórico, vista por turno, estados vacíos, Tab/foco, preferencia de movimiento reducido y ausencia de overflow horizontal. Sin errores JS. Capturas `/tmp/panaderia-dashboard-fase3/dashboard-360-js.png` y `dashboard-1280-js.png` revisadas.
- `Pint --test` en el nuevo test PHP y `git diff --check`: aprobados. La primera ejecución de MariaDB y Chrome dentro del sandbox fue bloqueada por sockets y se repitió con autorización.

MariaDB temporal en `/tmp/sprint4-*` con `--skip-networking`, datadir/base verificados, fixtures y transacciones ficticias; instancias detenidas. Chrome no conecta a la base operativa. No se instalaron dependencias ni se ejecutaron migraciones operativas.

## Límites

No se comprobó en celular físico, Safari, lector de pantalla real, zoom real ni Cloudflare. La validación de colores se limita a contrastes calculados de combinaciones concretas, no equivale a una certificación WCAG completa. Quedan pendientes la aceptación visual/operativa y la investigación separada de los dos fallos previos. La lentitud móvil y el salto del sidebar continúan en investigación.

Durante el trabajo cambió el `HEAD` del checkout; la vista Blade incorporada durante la tarea pasó a formar parte del `HEAD` actual mientras CSS, JS y pruebas permanecían como cambios locales. No se ejecutaron commits ni push por esta tarea; se preservó el estado existente.
