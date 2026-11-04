# Auditoría — Dashboard Fase 2: cálculos y consultas

**Fecha:** 3 de noviembre de 2026.  
**Estado:** cálculos implementados y comprobados mediante pruebas aisladas; regresión ampliada con dos fallos previos identificados. La presentación visual permanece pendiente.

## Alcance y cambios

`DashboardController` conserva los datos y el filtro de la interfaz actual, y entrega adicionalmente `datosDashboard` a la vista. La nueva consulta `app/Queries/ConsultarDashboard.php` prepara los datos fijos de hoy y mes sin depender de los parámetros Desde/Hasta. No se modificaron Blade, CSS, JavaScript, rutas, permisos, registro de producción ni esquema.

Contrato preparado para la futura interfaz:

- `hoy.pan`: `latas`, `unidades`, `latas_completas` y `unidades_completas`; `hoy.torta`, `hoy.bocadito` y `hoy.registros` completan los totales. Registros cuenta detalles, no cabeceras.
- `mensual.pan_latas` y `mensual.pan_unidades`: `completo` y hasta tres `productos` con ID, nombre y cantidad. Un resultado incompleto no expone una clasificación como definitiva. `mensual.torta` y `mensual.bocadito` contienen hasta tres productos por cantidad directa.
- `series.siete_dias` y `series.mes_actual`: filas de fecha y conteos por familia. Siete días incluyen hoy; mes actual representa el mes calendario completo, incluso fechas futuras con cero o con registros ya fechados para ellas.
- `actividad`: hasta cinco detalles de las tres familias con ID local, nombre, categoría, fecha, turno, hora, tipo y etiqueta. Se ordena por fecha descendente, familia alfabética ascendente e ID local descendente. Este desempate es estable y **no representa el orden real de creación**.

La fecha fija se calcula en `America/Lima`. El filtro de período preexistente conserva su efecto sobre las métricas y lista actuales, y no modifica los nuevos datos fijos. Se incluyen productos inactivos y se empata por ID ascendente.

## Criterios históricos y límites

Pan LATA se suma como latas. Las unidades incluyen Pan UNIDAD y Pan LATA multiplicado por `detalle_pan.panes_por_lata_usado`, nunca por el valor actual del producto. Un factor nulo o no positivo marca unidades incompletas; una unidad Pan histórica desconocida marca incompletas ambas magnitudes. Los valores diarios son sumas parciales explícitamente acompañadas de indicadores de integridad. Si el mes está incompleto, el ranking afectado devuelve `completo=false` y una lista vacía para no presentarlo como definitivo.

Torta y Bocadito suman directamente sus cantidades de detalle, según el contrato de producción existente. No se combinan magnitudes de familias para el gráfico: este cuenta registros. La consulta solo clasifica detalles cuya categoría de cabecera coincide con la familia, como el historial actual; cabeceras sin categoría o inconsistentes no se clasifican automáticamente.

Pan expone `hora_finalizacion` y Torta `hora_vitrina` cuando existen. Bocadito y filas sin hora devuelven `hora=null` y `hora_etiqueta=Sin hora registrada`. No se fabrica un instante ni se equipara una hora operativa con la hora de creación. El resultado puede incluir fechas futuras presentes en los datos: la actividad sigue el orden por fecha solicitado, sin aplicar un filtro de hoy.

Las sumas se realizan en SQL con DECIMAL y se comprueban contra el límite entero de PHP antes de convertir o acumular; un total fuera de rango produce una excepción en lugar de una cifra redondeada. La solución requiere PHP de 64 bits para cantidades superiores al límite de 32 bits; el caso de 4.294.967.294 se comprobó en el entorno aislado.

## Consultas y rendimiento

Se añaden dos lecturas a la petición: una unión agrupada por fecha/producto/familia para datos diarios, mensuales y series; y otra unión que limita la actividad global a cinco filas. No hay consulta por producto o fila. La prueba de conteo conserva el requisito de consultas constantes al aumentar lotes y ajusta el máximo de nueve a once por las dos lecturas nuevas.

La unión agrupada se materializa en PHP para el intervalo que cubre el mes completo y los siete días; la memoria puede crecer con la cantidad de combinaciones fecha/producto. No se midieron volúmenes representativos ni planes de ejecución; medir antes de introducir índices o cachés. Se requieren en el esquema servido las columnas históricas ya presentes en las migraciones del checkout. No se inspeccionó el esquema operativo.

## Pruebas

- `DashboardCalculosTest`: **12 aprobadas, 78 aserciones**. Incluye las tres familias, dos unidades Pan, factores históricos distintos/ausentes, unidad desconocida, rankings, empates, productos inactivos, fechas futuras dentro del mes, siete días, cambio de año en Lima, bisiesto, detalles de una misma cabecera, horas ausentes, roles/invitado y precisión superior a 32 bits.
- Regresión ampliada final `DashboardCalculosTest|DashboardTest|PanRegistroUnidadTest|ExperienciaVisualFase2Test|NavigationTest`: **148 ejecutadas, 146 aprobadas, 2 fallidas, 1.190 aserciones**. Se ejecutó antes de añadir la última prueba exclusiva de precisión, que pasó en la ejecución específica posterior. No se declara la regresión completa aprobada.
- Los dos fallos se reprodujeron con `DashboardController` y `DashboardTest` de HEAD en una copia bajo `/tmp`: la expectativa antigua del listado de Producción por fecha/turno y el caso de categoría vacía. La comparación adicional de 32 casos tuvo un tercer fallo propio de la copia porque se excluyó su manifiesto Vite; no se cuenta como fallo del proyecto. Estos resultados no autorizan modificar Producción fuera de alcance.
- Pint de los PHP afectados y `git diff --check`: aprobados. La primera ejecución en sandbox no pudo iniciar el socket de MariaDB; se repitió con autorización.

Las pruebas usaron servidores MariaDB temporales bajo `/tmp/sprint4-*`, sin red, con datadir y base verificados antes de migrar, fixtures ficticios, transacciones y sesiones array. Instancias detenidas al finalizar. No se tocó base de datos real ni se ejecutaron migraciones operativas. La comparación previa usó una copia temporal sin `.env` con dependencias copiadas y configuración ficticia.

## Pendientes

- Investigar separadamente los dos fallos previos de Producción; no fueron corregidos aquí.
- Fase 3: presentar métricas, selectores independientes, ranking, gráfico y actividad; comunicar incompletitud y significado de cada hora; conservar filtro histórico y accesibilidad.
- Comprobar rendimiento con volumen representativo y compatibilidad del esquema real mediante procedimientos autorizados. Probar navegador, dispositivos y aceptación visual cuando exista la interfaz.
- No se realizó commit, push, despliegue ni instalación de dependencias. Se conservaron cambios previos ajenos a esta tarea.
