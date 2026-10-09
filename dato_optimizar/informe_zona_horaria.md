# Informe - Zona horaria

Fecha: 2026-10-09. Proyecto: `panaderia-rs`.

Estado: **IMPLEMENTADO Y COMPROBADO en MariaDB aislada**. Sin commit. No se modificaron el `.env` real ni datos del entorno de trabajo.

## 1. Estado inicial

`config/app.php` tenía `'timezone' => 'UTC'` fijo. `.env.example` no declaraba `APP_TIMEZONE`. El bootstrap instalado de Laravel aplica `app.timezone` como zona por defecto de PHP, de la que depende el reloj Laravel.

Se inspeccionaron configuración, ejemplo de entorno, usos de reloj en código propio/tests, controllers y Requests de fechas. Los usos operativos encontrados son:

- `DashboardController`: `now()->startOfDay()` define el día del resumen.
- `ConsultarProduccionRequest`: `now()->toDateString()` proporciona la fecha por defecto, cuando no hay fecha explícita ni old input.
- Historial: valida fechas explícitas `Y-m-d` y compara directamente la fecha de cabecera; no depende de `now()`.
- `Produccion::fecha`: cast `date`, columna SQL DATE; el escritor conserva la fecha recibida y validada.
- `DashboardTest`: congela el reloj con `config('app.timezone')`; sus casos existentes siguen siendo válidos al cambiar la configuración.

No se encontraron otros usos propios operativos de `Carbon::now()`, `Carbon::today()` o `date()` que requieran cambios. La factory de User del scaffold usa `now()` y las migraciones declaran columnas `date`; no se modificaron. Tampoco se encontró un `bootstrap/cache/config.php` estándar en este checkout.

## 2. Riesgo identificado

UTC puede estar en el día siguiente mientras en Perú todavía es el día anterior. Por ejemplo, `2026-10-10 02:00:00 UTC` corresponde a `2026-10-09 21:00:00` en Lima. Usar UTC para «hoy» podría seleccionar otro día en Dashboard y proponer una fecha adelantada en el formulario.

El problema está en el reloj que genera el día operativo. Una fecha DATE guardada como `2026-10-09` debe seguir siendo esa fecha; no representa un instante que deba convertirse entre zonas.

## 3. Cambios realizados

| Archivo | Cambio | Motivo |
|---|---|---|
| `config/app.php` | `timezone` usa `env('APP_TIMEZONE', 'America/Lima')`; comentario acorde | Centralizar la zona y adoptar Perú como valor por defecto |
| `.env.example` | Añade `APP_TIMEZONE=America/Lima` | Documentar la opción para nuevas instalaciones |
| `tests/Feature/DashboardTest.php` | Añade dos pruebas de configuración/reloj y límite entre días UTC/Lima | Verificar los seis puntos solicitados reutilizando fixtures y runner existentes |
| `mejora_asignado.md` | Añade estado y evidencia de esta corrección | Actualizar el seguimiento sin borrar la historia |
| `dato_optimizar/informe_zona_horaria.md` | Nuevo informe | Registrar impacto, resultados e instrucciones locales |

Notas afectadas del Vault original: `LARAVEL/Controllers.md`, `INICIO/Estado de proyecto.md` y `DECISIONES/Decisiones Laravel.md`. Se incorporan el efecto sobre el reloj, la decisión centralizada y su evidencia, conservando las revisiones anteriores que describían UTC. Se respeta el nombre real del registro de decisiones, sin duplicarlo.

No se modificaron controllers, FormRequests, Action, modelos, migraciones, seeders, vistas, assets, rutas o configuración de tests. No hubo migraciones nuevas, backfill ni cambio de tipos/fechas almacenadas.

## 4. Comportamiento final

Sin `APP_TIMEZONE` explícito, Laravel resuelve `America/Lima`. Si se define la variable, su valor prevalece mediante la configuración central. En el entorno de las pruebas, el bootstrap, `config('app.timezone')`, la zona PHP, `now()` y `today()` resuelven Lima.

No se añaden conversiones en controllers ni ajustes manuales de horas. La prueba del valor por defecto retira temporalmente `APP_TIMEZONE` solo del repositorio de entorno del proceso de test y restaura su estado en `finally`; no toca archivos de entorno.

## 5. Impacto sobre Producción

La fecha propuesta al entrar sin parámetro usa el día local de Perú. Los campos GET explícitos y el old input mantienen la prioridad existente. El POST conserva su validación `Y-m-d` y registra la fecha enviada, sin reinterpretarla ni desplazarla.

`ProduccionController`, `ConsultarProduccionRequest`, `RegistrarProduccionPanRequest` y `RegistrarProduccionPan` permanecen intactos. Las pruebas verifican la fecha del input, la selección de detalles de esa fecha y las regresiones de registro existentes.

## 6. Impacto sobre Dashboard

«Producción de hoy» usa ahora el día de Lima obtenido por el mismo `now()->startOfDay()`. Sigue comparando `produccion.fecha` con una cadena `Y-m-d` y calculando lotes/latas de la sesión correspondiente; no se cambian sus consultas.

Prueba de límite: a las 02:00 UTC del 10/10, cuenta únicamente el lote del 09/10 (11 latas); a las 05:00 UTC, medianoche local, cuenta únicamente el lote del 10/10 (23 latas). La lista reciente y el orden existente conservan su comportamiento.

## 7. Impacto sobre Historial

Los filtros siguen siendo fechas operativas explícitas con límites inclusivos, sin conversión de zona. El filtro exacto del 09/10 recupera el mismo lote antes y después de la medianoche local, y su cast Eloquent sigue mostrando `2026-10-09`.

La prueba compara valores SQL DATE antes/después de ambas solicitudes y confirma que permanecen `2026-10-09` y `2026-10-10`. No se modificó Historial ni se ejecutó corrección alguna sobre fechas existentes.

## 8. Tests

Dos pruebas añadidas a `DashboardTest`:

1. Valor por defecto sin variable de entorno, configuración efectiva, zona PHP y reloj `now()`/`today()`.
2. Instantes UTC a ambos lados de la medianoche de Lima: métricas de Dashboard, fecha por defecto/input de Producción, consulta de sus detalles, filtro de Historial y conservación de fechas SQL/casts DATE.

Se reutilizó la cobertura existente de fechas, filtros, registro, autenticación y navegación. No hubo fallos que corregir.

| Comando | Resultado exacto |
|---|---|
| `php tests/run-mariadb.php --filter=DashboardTest` | 14 pruebas aprobadas, 157 aserciones, salida 0 |
| `php tests/run-mariadb.php --filter='HistorialProduccionTest\|ProduccionPanStoreTest\|NavigationTest'` | 147 pruebas aprobadas, 1.493 aserciones, salida 0 |
| `php tests/run-mariadb.php --do-not-cache-result` | 216 pruebas aprobadas, 2.205 aserciones, sin omisiones, salida 0 |
| `vendor/bin/pint --test config/app.php tests/Feature/DashboardTest.php` | Aprobado, salida 0 |
| `git diff --check` | Sin errores, salida 0 |

No se ejecutó `npm run build`: no cambiaron assets.

El runner MariaDB se ejecutó con escalamiento para su servidor/socket temporal, verificando que las conexiones y migraciones de prueba pertenecieran exclusivamente a bases ficticias. Instancia final detenida; artefactos en `/tmp/sprint4-f1fbc12c1824`. Tests HTTP con sesiones `array` y Vite omitido, sin consultas o escrituras en la base de trabajo. No se inspeccionó el entorno productivo ni se revisó navegador en esta tarea.

## 9. Configuración local necesaria

No se modificó ni se inspeccionó el contenido del `.env` real. Sin una variable explícita, el nuevo valor por defecto ya es `America/Lima`; añadirla no es obligatorio, aunque deja la intención clara:

```dotenv
APP_TIMEZONE=America/Lima
```

Si tu entorno ya define `APP_TIMEZONE` con otro valor, cambia únicamente esa variable para usar Lima. No es necesario modificar otras variables.

Si había configuración cacheada, después de actualizar ejecuta:

```bash
php artisan config:clear
```

También puede usarse `php artisan optimize:clear` si necesitas limpiar las demás cachés de Laravel. Para este cambio, `config:clear` es suficiente. Ninguno de esos comandos se ejecutó en esta tarea: no había un archivo estándar de configuración cacheada y los tests comprobaron la configuración nueva cargada.

## 10. Pendientes

- **PENDIENTE, si corresponde al entorno local:** revisar cualquier override explícito de `APP_TIMEZONE` y limpiar la configuración cacheada según la sección anterior. Esta tarea no certifica overrides ni cachés del despliegue.
- Sin pendientes de implementación para la corrección solicitada. No requiere convertir datos existentes, cambiar el esquema o alterar los módulos funcionales. No se hizo commit.
