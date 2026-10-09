# Informe - Dashboard funcional

Fecha: 2026-10-09. Proyecto: `panaderia-rs`.

Estado: **IMPLEMENTADO Y COMPROBADO en MariaDB aislada**. No se hizo commit ni despliegue. La base de trabajo no se consultó ni modificó.

## 1. Estado inicial

`DashboardController@index` únicamente devolvía la vista, sin consultas ni variables de negocio. `/dashboard` ya era el destino posterior al login y estaba protegido por `auth`.

La vista mostraba cantidades estáticas de Pan/Torta/Bocadito, turnos Mañana/Tarde, participantes y usuarios ficticios en modales, filtros de fecha sin consulta y estados Pendiente/Completada sin soporte en los modelos inspeccionados. `dashboard.js` importaba Chart.js y generaba gráficos con ceros. El CSS incluía reglas de una antigua barra lateral que no corresponde al sidebar compartido actual y reservaba espacio lateral para ella.

Se inspeccionaron rutas, Dashboard y sus assets, sidebar, controllers de Producción/Historial, Action de registro de Pan, modelos y relaciones, tipo DATE de `produccion.fecha`, configuración de zona horaria, tests relacionados y runner MariaDB. El checkout estaba limpio al comenzar.

## 2. Objetivo implementado

- Lotes de Pan y total de latas cuya fecha de producción es hoy.
- Resumen de hoy por cada turno existente en el catálogo, incluyendo turnos sin producción con valores cero.
- Cinco últimos lotes de sesiones de Pan, con producto, fecha, turno, cantidad almacenada y registrador de la sesión.
- Accesos mediante `route('produccion.index')` y `route('history.index')`.
- Estados sin producción de hoy, sin lotes históricos y sin catálogo de turnos.
- Interfaz sencilla con tarjetas, tabla y la paleta existente, sin gráficos ni modales de datos ficticios.

## 3. Archivos modificados

| Ruta | Cambio | Motivo |
|---|---|---|
| `app/Http/Controllers/DashboardController.php` | Consultas de lectura, agregado diario por turno, totales y últimos cinco lotes; retorno tipado `View` | Conectar Dashboard con datos reales, sin modificar escritores o modelos |
| `resources/views/dashboard/index.blade.php` | Métricas reales, tarjetas dinámicas, tabla reciente, enlaces nombrados y estados vacíos | Dar una página principal útil, sin datos inventados ni consultas dentro de Blade |
| `resources/css/dashboard.css` | Estilos de tarjetas, secciones, enlaces, tabla desplazable y responsive; conserva paleta/import del sidebar | Integrar la información real y retirar reglas de la maqueta reemplazada |
| `resources/js/dashboard.js` | Retira gráficos/modales ficticios y documenta que la página usa renderizado del servidor | Evitar JavaScript y gráficos innecesarios; la vista deja de cargar este archivo |
| `tests/Feature/DashboardTest.php` (nuevo) | 12 casos ejecutados, con fixtures y MariaDB aislada | Verificar métricas, fechas, familias, turnos, listado, accesos y flujo completo |
| `mejora_asignado.md` | Añade seguimiento y evidencia de esta entrega | Distinguir el Dashboard funcional de la maqueta histórica |
| `dato_optimizar/informe_dashboard_funcional.md` (nuevo) | Informe de alcance, implementación y resultados | Cumplir la entrega documental solicitada |

Las notas afectadas del Vault original son `LARAVEL/Controllers.md`, `LARAVEL/Blade.md`, `PRODUCCION/Pantallas.md`, `INICIO/Estado de proyecto.md`, `INICIO/Roadmap.md` y `DECISIONES/Decisiones Laravel.md`. La actualización registra responsabilidades, funcionamiento, decisiones y límites conservando las secciones anteriores. Se respeta el nombre real `.md` del registro de decisiones, sin duplicarlo con `.md.md` ni crear carpetas.

## 4. Datos mostrados

| Dato | Significado |
|---|---|
| Lotes de Pan | Número de filas de `detalle_pan` en sesiones de Pan con fecha de producción igual a hoy; no cuenta cabeceras ni productos distintos |
| Total de latas | Suma de `detalle_pan.cantidad` de esas filas; representa cantidad producida, no registros |
| Producción por turno | Los mismos lotes y latas diarios agrupados por `produccion.turno_id` |
| Últimas producciones | Cinco lotes de Pan ordenados por fecha de producción DESC y detalle ID DESC; no se limita al día actual |
| Registró la sesión | `produccion.usuario.username`, el registrador de la cabecera; no se inventa un autor individual del lote |

Los valores se formatean con separador de miles. Las cantidades se muestran en latas, evitando conversiones innecesarias. Los productos desactivados conservan su presencia en métricas y lista histórica.

## 5. Consultas Eloquent

La consulta base es `DetallePan::query()->whereHas('produccion.categoria', ...)`, con categoría por nombre `Pan`. Se clona para el agregado diario y para la lista reciente, evitando que joins o columnas del agregado se propaguen al listado.

Producción de hoy y latas:

- Join simple de `detalle_pan.produccion_id` con `produccion.id` para agrupar por el turno canónico de cabecera.
- Comparación directa `produccion.fecha = fechaHoy->toDateString()`; la columna es DATE, no timestamp.
- `COUNT(*) as lotes` y `SUM(detalle_pan.cantidad) as latas`, agrupados por `produccion.turno_id`.
- Los totales se obtienen sumando únicamente los resultados agregados, una fila por turno. No se cargan todos los detalles del día ni se hacen consultas separadas por cada turno.

Últimas producciones:

- La misma restricción de familia Pan.
- `with(['producto', 'produccion.turno', 'produccion.usuario'])`.
- Subconsulta Eloquent correlacionada para ordenar por la fecha de cabecera, igual que Historial.
- Desempate `detalle_pan.id` DESC y `limit(5)` antes de recuperar filas.

`Turno::orderBy('nombre_turnos')->get()` proporciona los nombres y claves reales del catálogo. Blade recibe los datos preparados y no ejecuta consultas propias.

## 6. Manejo de categoría Pan

Se identifica mediante `nombre_categorias = 'Pan'` usando las relaciones ya existentes, siguiendo Historial. No hay IDs fijos de categoría, producto, turno, rol o usuario en la implementación.

Las métricas y últimos lotes se restringen a la familia de la cabecera. Una fila inconsistente de `detalle_pan` que pertenezca a una sesión de otra familia no entra en el Dashboard. Torta y Bocadito quedan fuera, sin implementar sus módulos. Las cabeceras sin categoría no se clasifican automáticamente, igual que en Historial.

## 7. Turnos

Las tarjetas se generan a partir del catálogo `turnos`, ordenado por nombre. Cada tarjeta obtiene el agregado por el ID real de su turno y muestra cero cuando no hay filas del día. El turno de detalle se conserva en el sistema, pero no se usa para agrupar ni etiquetar estas producciones.

Si una sesión de Pan tiene `produccion.turno_id` NULL, sus lotes se incluyen en el total y se muestran en un grupo adicional «Sin turno registrado». Esta etiqueta describe un dato ausente, sin crear un turno ni atribuirle uno desde el detalle legacy.

Los tests desplazan los catálogos antes de crear Mañana/Noche, dejando ambos con IDs mayores que 2, y comprueban explícitamente una discrepancia entre turno de cabecera y detalle.

## 8. Estado vacío

- Sin producción hoy: métricas cero y «No hay producción registrada hoy»; los enlaces siguen disponibles.
- Con lotes anteriores pero ninguno hoy: se mantiene la lista reciente y las métricas diarias permanecen en cero.
- Sin lotes en el sistema: «Aún no hay lotes de Pan registrados».
- Sin turnos disponibles: mensaje explícito; no se inventan opciones.
- Sin categoría Pan en el catálogo: consultas sin resultados, sin `firstOrFail` ni error de renderizado.

## 9. Diseño

Se conservan Huninn, fondo `#F6F2EF`, verdes `#33403A`, `#4F6355`, `#7D9481`, `#AEC0AC` y beige `#DCD6C4`. El contenido tiene ancho máximo, tarjetas redondeadas, espacios consistentes y una jerarquía de secciones sencilla.

Los estilos de contenido se limitan a clases `dashboard-*`. El layout se adapta al ancho disponible; la tabla puede desplazarse horizontalmente mediante teclado y los enlaces tienen foco visible. El sidebar se reutiliza sin modificar su Blade ni su CSS.

Se retiran filtros sin implementación, estados ficticios, imágenes placeholder, gráficos y modales de otras familias. No existía comportamiento de consulta funcional que preservar en esos elementos. Para consultar períodos se ofrece el Historial existente. Se retira el script externo de iconos de esta página porque la nueva vista no usa esos iconos.

No se agregan paquetes. Chart.js ya estaba instalado; su dependencia permanece intacta para evitar cambios ajenos al alcance, pero Dashboard deja de importarlo y cargarlo. No se rediseña la navegación compartida.

## 10. Tests

Se creó `tests/Feature/DashboardTest.php`. Sus 12 casos ejecutados comprueban:

- Usuario autenticado, métricas/estados vacíos y enlaces rápidos nombrados mediante DOM.
- Ausencia completa de catálogos sin error.
- Conteo de lotes frente a cabeceras, suma de latas y exclusión de fechas anteriores/posteriores.
- Exclusión de Torta/Bocadito y de detalles inconsistentes en sus cabeceras.
- Turnos con IDs arbitrarios, turnos sin producción y prevalencia del turno de cabecera.
- Sesiones sin turno sin perder cantidades ni inventar datos.
- Límite de cinco filas, orden por fecha/ID, producto, usuario y relaciones precargadas.
- Productos inactivos y escape del nombre en Blade.
- Cambio de día según el reloj Laravel.
- Login real → Dashboard vacío → Producción → guardar Pan → Dashboard actualizado → Historial con el mismo lote.
- Número de consultas acotado y constante al aumentar los lotes recientes.

`NavigationTest` se conserva y aporta la cobertura existente de invitado redirigido al login, `auth`, sidebar activo, login/logout y caché. No se duplicaron esos casos en el nuevo archivo.

| Comando ejecutado | Resultado exacto |
|---|---|
| `php tests/run-mariadb.php --filter=DashboardTest` | 12 pruebas aprobadas, 120 aserciones, salida 0 |
| `php tests/run-mariadb.php --filter='NavigationTest\|ProduccionPanStoreTest\|RegistrarProduccionPanActionTest\|HistorialProduccionTest'` | 163 pruebas aprobadas, 1.595 aserciones, salida 0 |
| `php tests/run-mariadb.php --do-not-cache-result` | 214 pruebas aprobadas, 2.168 aserciones, sin omisiones, salida 0 |
| `vendor/bin/pint --test app/Http/Controllers/DashboardController.php tests/Feature/DashboardTest.php` | Aprobado, salida 0 |
| `npm run build` | Compilación Vite correcta, 11 módulos transformados, salida 0 |
| `git diff --check` | Sin errores, salida 0 |

No hubo fallos de tests que corregir. Vite emitió el aviso existente de `fontaine` opcional sin impedir la compilación; no se instalaron dependencias. Después del test específico se precisó un texto de la tarjeta a «Lotes registrados hoy»; la suite completa comprueba esa versión final.

El runner inicia MariaDB sin red en `/tmp/sprint4-*` y verifica conexión/datadir antes de migrar exclusivamente las bases ficticias. Se ejecutó con escalamiento para el socket temporal, como en la tarea anterior. La instancia final quedó detenida; artefactos en `/tmp/sprint4-3b802721d439`.

Los tests HTTP usan sesiones `array` y `withoutVite`; la compilación se comprueba aparte. No se abrió un navegador ni se certificó el entorno de trabajo o sesiones `database`. No se consultaron datos reales, ejecutaron seeders allí ni se usó `migrate:fresh` contra la base de desarrollo.

## 11. Rendimiento

El agregado diario se calcula en SQL en una sola consulta y devuelve únicamente grupos por turno. El catálogo se consulta una vez. La lista reciente trae como máximo cinco detalles y utiliza eager loading de producto, cabecera, turno y usuario, sin consultas individuales por fila.

La prueba de consultas precarga el empleado de la cuenta que usa el sidebar, para medir en condiciones iguales. Comprueba que aumentar de uno a diez lotes mantiene el número de consultas y no supera ocho en esa prueba. No es un benchmark con volumen productivo ni certifica índices del entorno real.

No se hace caché del resumen; cada GET recupera lo persistido y refleja el nuevo registro después de volver de Producción. No se agregan índices/migraciones ni capas de consultas adicionales.

## 12. Decisiones técnicas

- Mantener las lecturas sencillas en `DashboardController`, con retorno tipado y sin HTML.
- Reutilizar la consulta relacional de Pan y el orden de Historial, conservando intactos ambos módulos.
- Obtener conteo/suma diarios en un único agregado por turno y derivar el total de esos grupos.
- Usar `now()` una sola vez por solicitud y comparar DATE con `Y-m-d`. Se respeta la zona configurada en `config/app.php`, actualmente UTC, sin cambiarla incidentalmente.
- Priorizar lotes/latas; no calcular coches ni añadir otra constante.
- Mostrar últimas producciones por fecha de producción y detalle ID: no hay timestamp de creación en esos modelos y no se inventa hora de registro.
- Mostrar el registrador de la sesión, sin atribuir autor al lote.
- Mostrar turnos sin lotes y un grupo explícito para cabeceras sin turno, sin modificar datos legacy.
- Retirar del Dashboard los gráficos y controles ficticios; las consultas de períodos ya están en Historial.

## 13. Pendientes

- **PENDIENTE:** aceptación visual en navegador y comprobación operativa con datos del entorno real antes de presentar. La evidencia de esta tarea es aislada y HTTP.
- **PENDIENTE, si corresponde al entorno:** clasificación explícita de cabeceras antiguas sin categoría; no se infiere ni se hace backfill.
- **PENDIENTE, fuera de esta tarea:** rediseño/pulido general del sidebar, métricas de otras familias y cualquier analítica futura que se solicite. No se agregan gráficos, filtros de período ni estados de producción sin requisitos nuevos.
- La fecha se rige por UTC configurado actualmente. Cualquier cambio de zona horaria operativa requiere una tarea explícita, considerando todo el flujo; no se implementó aquí.
- No quedan cambios pendientes de implementación para el Dashboard de Pan dentro del alcance probado. Sin commit ni despliegue.

## 14. Archivos que NO se tocaron

- `ProduccionController`, `RegistrarProduccionPan` y Requests de Producción: conservar registro, validación, transacción, sesiones y participantes.
- `HistorialController`, `ConsultarHistorialProduccionRequest`, vista y assets de Historial: conservar consulta, filtros y paginación ya probados.
- Todos los modelos y pivotes: las relaciones existentes son suficientes.
- Migraciones y seeders: ninguna modificación de esquema o catálogo necesaria.
- `routes/web.php`: los destinos nombrados y su protección `auth` ya eran correctos.
- Login/logout, middleware y configuración de sesión/fecha: conservar funcionamiento actual.
- `resources/views/components/sidebar.blade.php` y `resources/css/sidebar.css`: fuera del pulido visual de esta tarea.
- `package.json`, lockfiles y `vite.config.js`: sin dependencias ni configuración nuevas.
- Tests previos: la regresión existente se ejecuta intacta.
- `.env` y datos reales: no se modifican ni se documenta su contenido.
