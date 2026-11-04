# Dashboard Panadería RS — Fase 1: auditoría funcional y propuesta visual

**Fecha:** 3 de noviembre de 2026  
**Estado de la ampliación:** PENDIENTE.  
**Alcance:** inspección estática y propuesta; no se implementaron cambios de código ni se consultó la base de datos.

## 1. Resultado principal

El Dashboard actual presenta datos reales de Pan. La ampliación aprobada para las tres familias, los rankings mensuales, los dos selectores independientes y el gráfico temporal todavía no existe en el checkout.

La actividad reciente requiere una decisión funcional antes de implementarse: las tablas de las tres familias no cuentan con una marca de creación común que permita ordenar de forma fiable los registros por momento de alta. Las horas disponibles representan hechos diferentes y faltan en algunos registros.

## 2. Estado actual

| Función | Estado observado |
|---|---|
| Lotes y latas de Pan de hoy | Implementada. Se cuentan detalles y se suman solo cantidades con unidad histórica LATA. |
| Filtro Desde/Hasta | Implementado. Modifica el resumen y la lista de Pan al período indicado. |
| Producción por turno | Implementada para Pan, con gráfico y valores equivalentes en HTML. |
| Últimas producciones | Cinco lotes de Pan, ordenados por fecha de producción e ID de detalle descendentes. |
| Accesos a Producción e Historial | Implementados mediante rutas nombradas. |
| Estados vacíos y errores de período | Implementados. |
| Métricas de Torta y Bocadito y total diario de las familias | Pendientes en Dashboard. |
| Selector diario de Pan Latas/Unidades | Pendiente. |
| Tres productos principales por categoría y mes | Pendiente. |
| Selector mensual de Pan independiente | Pendiente. |
| Gráfico de últimos siete días/mes actual | Pendiente. El gráfico actual representa latas por turno. |
| Actividad de tres familias con categoría y hora | Pendiente y condicionada por la disponibilidad de horas y del orden histórico. |

La ruta `/dashboard` está bajo autenticación, usuario activo, sesión y capacidad `acceder-operacion`. No se propone cambiar esos controles. La configuración de aplicación define `America/Lima` como zona horaria por defecto; no se inspeccionó el valor efectivo del entorno.

## 3. Requisitos funcionales aprobados

### Producción de hoy

La sección superior mostrará Pan, Tortas, Bocaditos y total de registros. La tarjeta de Pan tendrá un selector Latas/Unidades. No habrá una tarjeta independiente de «Panes estimados».

Criterios propuestos para los cálculos:

- **Registros:** contar cada detalle o lote, no cada cabecera de sesión.
- **Pan en latas:** sumar solo cantidades históricas de Pan registradas en LATA.
- **Pan en unidades:** sumar Pan registrado directamente en UNIDAD y, para registros LATA, `cantidad × detalle_pan.panes_por_lata_usado`.
- Si falta una equivalencia histórica, señalar que el total está incompleto; no sustituirla por cero ni por el factor actual del catálogo.
- Mantener separadas las magnitudes LATA, UNIDAD y cualquier otra unidad histórica; no sumar bolsas con unidades o latas sin una regla histórica aprobada.

### Resumen mensual

Mostrar los tres productos más elaborados de cada familia. El selector Latas/Unidades del ranking Pan será independiente del selector diario. El cálculo de unidades utilizará exclusivamente las equivalencias guardadas en cada detalle histórico. Si faltan factores que puedan afectar el orden, no presentar el ranking parcial como definitivo.

El mes corresponde al mes actual según la fecha de aplicación en `America/Lima`, sujeto a verificar la configuración efectiva. Definir desempates estables para productos con la misma cantidad y estados para menos de tres productos o ausencia de datos.

### Gráfico

Un único gráfico permitirá elegir «Últimos 7 días» o «Mes actual». La propuesta es representar **número de registros por día y familia**: tiene una unidad común y evita sumar latas con unidades. Se recomienda acompañarlo de etiquetas y una tabla o resumen textual accesible para que la información no dependa solo del color. Los siete días incluirían hoy; el mes actual utilizaría sus fechas correspondientes.

### Actividad reciente

La sección mostrará cinco registros con producto, categoría, turno y hora disponible. El alcance exacto de «últimos» necesita decisión:

- Pan LATA: `hora_finalizacion`, que puede faltar en históricos.
- Torta: `hora_vitrina`, que puede faltar en históricos.
- Pan UNIDAD y Bocadito: no cuentan con esas horas registradas.
- Las horas de finalización y vitrina no son necesariamente la hora de alta del registro.
- Los IDs son propios de cada tabla y no forman una cronología global entre familias.

Como alternativa sin modificar el registro ni el esquema, puede usarse fecha de producción y un desempate estable, indicando el criterio en la interfaz. Debe mostrarse «Sin hora registrada» cuando corresponda y no presentarse ese orden como los últimos cinco por momento real de creación. La elección queda pendiente.

## 4. Propuesta de distribución

```text
Dashboard                         Registrar producción | Ver historial

PRODUCCIÓN DE HOY                                       fecha
┌────────────────┬──────────────┬──────────────┬────────────────┐
│ Pan            │ Tortas       │ Bocaditos    │ Registros      │
│ Latas/Unidades │ unidades     │ unidades     │ total de lotes │
└────────────────┴──────────────┴──────────────┴────────────────┘

RESUMEN MENSUAL
┌──────────────────┬──────────────────┬──────────────────┐
│ Pan              │ Torta            │ Bocadito         │
│ Latas/Unidades   │ Tres productos   │ Tres productos   │
│ Tres productos   │ principales      │ principales      │
└──────────────────┴──────────────────┴──────────────────┘

EVOLUCIÓN                         [Últimos 7 días | Mes actual]
Un gráfico y datos equivalentes en texto o tabla accesible

ACTIVIDAD RECIENTE
Producto · categoría · turno · fecha · hora disponible
```

En móvil, usar una o dos columnas según el ancho y apilar los rankings y la actividad. Mantener etiquetas visibles, controles táctiles cómodos, foco y estados vacíos claros. Las animaciones deben ser discretas y respetar movimiento reducido.

El filtro de período existente debe conservarse como consulta claramente separada: no debe hacer que una tarjeta llamada «Producción de hoy» represente otro período. Su ubicación definitiva requiere validación visual.

## 5. Paleta y contraste

Se propone conservar la identidad actual y usar blanco en tarjetas para crear jerarquía. Los cambios de uso se limitarían al Dashboard salvo una decisión posterior expresa; no se modificó ningún token en esta fase.

| Color HEX | Uso propuesto | Justificación |
|---|---|---|
| `#F6F2EF` | Fondo general | Conserva el crema de marca y una superficie cálida. |
| `#FFFFFF` | Tarjetas y superficies | Separa grupos de información y facilita la lectura. |
| `#33403A` | Texto principal y acción destacada | Conserva el verde bosque y ofrece alto contraste. |
| `#4E6254` | Texto secundario | Crea jerarquía sin usar salvia claro para texto pequeño. |
| `#4F6355` | Acciones secundarias e interacción | Conserva el verde medio actual y distingue controles. |
| `#7D9481` | Acentos y series gráficas | Conserva el salvia para áreas acompañadas de etiquetas. |
| `#AEC0AC` | Separadores y fondos suaves | Conserva el tono existente para organización visual. |
| `#DCD6C4` | Superficie secundaria puntual | Mantiene el beige como acento sin cubrir todas las tarjetas. |
| `#7A3325` / `#F3DDDA` | Texto y fondo de error | Reutiliza colores semánticos existentes. |

No se propone descartar los colores originales: cambia principalmente su función en la jerarquía visual. Cálculos para colores sólidos: `#33403A` sobre blanco **10,85:1**; `#4E6254` sobre crema **5,90:1**; `#7D9481` sobre blanco **3,27:1**. Este último no debe emplearse como texto pequeño sobre blanco.

WCAG AA exige normalmente 4,5:1 para texto normal, con excepciones para texto grande; el contraste gráfico se evalúa según la función del elemento. Las cifras anteriores no certifican la interfaz final: hay que comprobar estados, fondos, opacidad, foco y gráficos en la implementación. Referencias: [W3C, contraste de texto](https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html) y [contraste no textual](https://www.w3.org/WAI/WCAG22/Understanding/non-text-contrast.html).

## 6. Análisis técnico

### Archivos actuales

- `app/Http/Controllers/DashboardController.php`: cálculo actual de Pan, turnos y últimos lotes.
- `app/Http/Requests/ConsultarDashboardRequest.php`: reglas del filtro de fechas.
- `resources/views/dashboard/index.blade.php`: tarjetas, filtro, gráfico, tabla y estados.
- `resources/js/dashboard.js`: Chart.js, animaciones y actualización parcial.
- `resources/css/dashboard.css`: composición responsive y estilos de la pantalla.
- `resources/css/tokens.css`: paleta y foco compartidos; evitar cambios globales si solo se rediseña Dashboard.
- `routes/web.php` y `app/Providers/AppServiceProvider.php`: ruta y capacidad existentes, fuera del alcance de modificación.
- `app/Queries/ConsultarHistorialProduccion.php`: referencia de unión de familias, filtros y paginación; sus relaciones y paginación no deben asumirse como la solución óptima para agregaciones.

Las consultas actuales agregan Pan por turno y cargan relaciones de los últimos cinco detalles. Parte de sus criterios puede reutilizarse, pero los cálculos de tres familias, ranking y serie temporal requieren nuevas lecturas. Una clase de consulta específica de Dashboard es una opción a evaluar para mantener el controlador comprensible sin alterar Historial o Producción.

### Rendimiento y responsive

No se ejecutaron consultas ni mediciones; no se acredita un problema de rendimiento. Las nuevas agregaciones podrían recorrer más datos, por lo que corresponde medir tiempos y planes de consulta con volumen representativo antes de proponer índices o cachés.

El CSS actual transforma la tabla en fichas a 767 px y adapta cabecera, filtros y gráfico. Esto muestra una estrategia responsive existente, pero no se realizó una nueva inspección visual del render ni una prueba en dispositivo real; no se atribuyen fallos de geometría sin evidencia.

### Cobertura existente

`tests/Feature/DashboardTest.php` cubre estados vacíos, lotes y latas, turnos, exclusión actual de otras familias, cinco registros de Pan, fechas, nombres y número de consultas. `tests/Feature/PanRegistroUnidadTest.php` verifica que las unidades de Pan no se sumen como latas. `tests/Feature/ExperienciaVisualFase2Test.php` y `tests/Browser/ExperienciaFase2.mjs` incluyen período, gráfico y experiencia visual actuales.

Estas pruebas se inspeccionaron, pero **no se ejecutaron en esta fase**. Algunas expectativas expresan deliberadamente el alcance actual solo Pan y deberán revisarse cuando se implemente la ampliación, conservando las regresiones que sigan siendo válidas.

## 7. Plan de implementación por etapas

1. Acordar orden de actividad, visualización de factores faltantes y convivencia con el filtro existente.
2. Implementar y probar consultas y cálculos de tres familias, fecha local y equivalencias históricas, con datos ficticios aislados.
3. Construir secciones y selectores independientes, preservando permisos, filtro y accesibilidad existentes.
4. Incorporar el gráfico único con alternativa textual y estados vacíos.
5. Verificar responsive, teclado, lector de pantalla, contraste, movimiento reducido y rendimiento medido.

## 8. Pruebas recomendadas

- Tres familias, cero datos, menos de tres productos y empates.
- Cambio de día y mes en Lima, incluyendo fin de mes y año.
- Selectores diario y mensual independientes.
- Pan UNIDAD, Pan LATA con factores históricos distintos y factores ausentes; ausencia de uso del valor actual del catálogo.
- Separación de unidades y bolsas, sin sumar magnitudes incompatibles.
- Turnos, horas faltantes y criterio explícito de orden; productos inactivos e históricos.
- Filtro previo, errores, permisos y prevención de regresiones.
- Anchos móviles/escritorio, zoom, teclado, lector de pantalla, contraste y movimiento reducido.
- Conteo de consultas y tiempos con datos de prueba representativos; sin afirmar lentitud sin medición.

## 9. Alcance de la auditoría

Se revisaron código, rutas, permisos declarados, consultas, estructura de modelos/migraciones y pruebas existentes; se calcularon contrastes de colores sólidos. No se consultó MariaDB, ejecutó navegador ni ejecutaron pruebas funcionales nuevas. No se modificaron código, datos, autenticación, permisos, navegación móvil o registro de producción. La propuesta y los requisitos nuevos permanecen PENDIENTES de implementación y comprobación.
