# Documentación de cambios — Sistema Panadería RS

Documentación de todo lo trabajado en esta sección, en orden cronológico. Cada
fase indica **qué se cambió**, **por qué**, **cómo se verificó** y **qué quedó
pendiente**.

| Fase | Commit | Contenido |
|---|---|---|
| 1. Correcciones de auditoría | `78f0b92` | 5 correcciones puntuales sobre código existente |
| 2. Esquema de base de datos | `c7a91f7` | 1 migración + 6 seeders |
| 3. Vistas conectadas a la base | `83e54ba` | 3 controladores + 3 vistas + JS + 2 consultas |

---

## Fase 1 — Correcciones de auditoría

Objetivo: arreglar defectos concretos sin refactorizar nada ajeno. Los 5
puntos fueron los que pediste explícitamente.

### 1.1 Font Awesome no cargaba en 3 vistas

`login.blade.php`, `dashboard/index.blade.php` y `history/index.blade.php`
usaban clases `fa-*` / `far fa-*` pero no tenían el `<link>` de la CDN, así que
los íconos salían como texto vacío.

```html
<!-- AGREGADO -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
```

Se agregó **solo el `<link>`**. Los `<script>` de Font Awesome que ya estaban en
las vistas se dejaron como estaban: la librería CSS ya trae sus íconos, así que
son carga redundante, pero removerlos implicaba revisar el JavaScript que
depende de ellos y quedaba fuera del alcance pedido.

### 1.2 Comentario HTML roto en el dashboard

En `dashboard/index.blade.php` había un "comentario" escrito con barras en lugar
de `<!-- -->`:

```diff
-        / --- FILTRO DE FECHA --- /
+        <!-- FILTRO DE FECHA -->
```

Iba a producir basura en el DOM frente a los demás comentarios de la vista.

### 1.3 Entrada de Vite inexistente

`vite.config.js` declaraba un CSS que no existe en el proyecto:

```diff
-                'resources/css/produccion-index.css',
```

`npm run build` fallaba. Se quitó la entrada. **Verificado: el build pasa.**

### 1.4 Consolidación del modelo de autenticación

`app/Models/Usuario.php` modelaba la tabla `usuarios_sistema`, pero el proyecto
ya tenía un modelo equivalente y el `auth.php` apuntaba al equivocado. Se
consolidó todo en `UsuarioSistema` (tabla `usuarios_sistema`), que implementa
`Authenticatable`:

```php
class UsuarioSistema extends Model implements AuthenticatableContract
{
    use Authenticatable;

    protected $table = 'usuarios_sistema';

    // evita exponer el hash si el usuario se serializa a JSON
    protected $hidden = ['password_hash'];

    public function empleado() { return $this->belongsTo(Empleado::class, 'empleado_id'); }
    public function rol()     { return $this->belongsTo(Rol::class, 'rol_id'); }
}
```

Archivos actualizados para apuntar al modelo correcto:

- `config/auth.php` → `model` = `App\Models\UsuarioSistema`
- `app/Models/Empleado.php` → relación `usuarioSistema`
- `app/Models/Produccion.php` → `registradoPorUsuario()` / `usuario()`
- `app/Models/Pedido.php` → `usuarioSistema()`
- `app/Models/Rol.php` → `usuarios()`
- `app/Models/Usuario.php` → **eliminado**

`getPassword()` **no** se tocó: lee `password_hash` de la base directamente, no
usa `$hidden`, y `Authenticatable` no lo necesita. Funcionaba y no se iba a
romper.

### 1.5 Carpeta de imágenes

Las vistas hacen `asset('images/...')` pero `public/images/` no existía. Se creó
con un `.gitkeep`.

> **Pendiente:** el `.gitkeep` no es suficiente. Las vistas referencian 4+
> archivos de imagen que no están en el repo y van a dar 404 hasta que se
> agreguen.

---

## Fase 2 — Esquema de base de datos y seeders

### 2.1 Migración `2026_09_25_230000_ajustar_unidades_y_detalle_torta_table.php`

Cuatro ajustes de esquema que el modelo de datos necesitaba:

| Cambio | Motivo |
|---|---|
| `unidades_medida.equivalencia_unidades` → `decimal(8,2)` **nullable** | No todos los factores de conversión se conocen |
| `produccion.observaciones` → `text` **nullable** después de `fecha` | Las notas del lote son opcionales |
| `detalle_torta.unidad_medida_id` → FK a `unidades_medida` | Toda producción necesita unidad de medida |
| `detalle_pan.cantidad` → `decimal(10,2)` (antes `integer`) | La producción necesita decimales (12.5 coches) |

`up()` y `down()` están ambos implementados. **Verificado:** se aplicó, se
revisó que los datos previos se preservaran, se probó el `down()` y se volvió a
aplicar.

### 2.2 Decisiones de datos acordadas

Definidas antes de escribir los seeders, no se improvisaron:

- **Categorías:** `Pan`, `Torta`, `Bocadito`
- **Turnos:** `Mañana`, `Noche`
- **Unidades:** `unidad` (equivalencia `1.00`), `lata` (`NULL`), `coche` (`NULL`)

> `NULL` en `equivalencia_unidades` significa **"conversión todavía no
> configurada"**, no `0` ni `1`. Mientras los factores reales de `lata` y `coche` no
> estén confirmados, no se inventan. Por eso los 10 productos de ejemplo se
> asignan a `unidad` y ninguno a `lata`/`coche`: sembrar pan en coches daría
> reportes con números falsos.

### 2.3 Seeders creados (6) + `DatabaseSeeder`

| Archivo | Carga |
|---|---|
| `database/seeders/CategoriaSeeder.php` | 3 categorías |
| `database/seeders/TurnoSeeder.php` | 2 turnos |
| `database/seeders/UnidadMedidaSeeder.php` | 3 unidades (no pisa equivalencias existentes) |
| `database/seeders/RolProduccionSeeder.php` | `Maestro`, `Ayudante` |
| `database/seeders/ProductoSeeder.php` | 10 productos (4 pan, 2 torta, 4 bocadito) |
| `database/seeders/ProduccionSeeder.php` | 4 fechas de producción con 10 líneas de detalle + 13 asignaciones de empleados |
| `database/seeders/DatabaseSeeder.php` | **modificado**: registra los 6 en orden de FK |

Los 5 seeders de catálogos usan **Eloquent + `firstOrCreate()`** sobre el modelo
correspondiente, para que se puedan re-ejecutar sin duplicar. `ProduccionSeeder`
tiene una guarda `if (DB::table('produccion')->exists()) return;` para no
duplicar historial.

### 2.4 Un bug encontrado durante la verificación

El primer `migrate:fresh --seed` falló:

```
Call to undefined method Illuminate\Database\Query\Builder::firstOrCreate()
  en database/seeders/CategoriaSeeder.php:13
```

`firstOrCreate()` es un método de **Eloquent**, no del Query Builder. Los 5
seeders de catálogos se reescribieron para usar los modelos. Corregido y
verificado.

### 2.5 Estado de la base tras la fase

```
categorias=3   turnos=2          unidades_medida=3   roles_produccion=2
cargos=5       roles=3           empleados=2         usuarios_sistema=1
productos=10   produccion=4
detalle_pan=4  detalle_torta=2   detalle_bocadito=4
detalle_pan_empleado=6  detalle_torta_empleado=3  detalle_bocadito_empleado=4
```

> **Pendiente:** los 2 datos que estaban cargados a mano en la base y que
> `migrate:fresh` borró fueron `categorias = "Panes"` y
> `roles_produccion = "Maestro"`. El primero se reemplazó por `Pan` según lo
> acordado; el segundo lo recrea el seeder con el mismo nombre.

---

## Fase 3 — Vistas conectadas a la base de datos

Objetivo: reemplazar los datos estáticos de las vistas Blade por registros
reales de la base, usando controlador → ruta → vista.

### 3.1 Rutas — sin cambios necesarios

Las tres rutas ya existían y apuntaban correctamente a los métodos de los
controladores:

```php
Route::get('/dashboard',  [DashboardController::class,  'index'])->name('dashboard');
Route::get('/produccion', [ProduccionController::class, 'index'])->name('produccion.index');
Route::get('/history',     [HistorialController::class, 'index'])->name('history.index');
```

> **Aclaración:** el historial está en **`/history`**, no en `/historial`. Un
> `curl` a `/historial` da 404 aunque todo esté bien.

### 3.2 El problema de fondo: 3 tablas sin relación entre sí

La producción se guarda en `detalle_pan`, `detalle_torta` y `detalle_bocadito`.
Son tablas distintas, sin FK entre ellas, y **una torta no tiene cantidad**
(1 registro = 1 torta). "Los registros de producción" por lo tanto **no son una
consulta**: hay que unir 3 fuentes.

Para no triplicar el mapeo en cada vista se crearon 2 clases de consulta.

#### `app/Consultas/LineasProduccion.php` (nueva)

Normaliza las 3 tablas en **una** colección de objetos con claves siempre
iguales, para que la vista use un solo `@forelse` sin preguntar por el tipo:

```php
LineasProduccion::obtener([
    'conEmpleados' => true,   // carga empleados vía pivote
    'limite'       => 6,      // "registrado recientemente"
    'fecha'        => '2026-09-26',  // filtra por día
]);
```

Cada línea expone: `tipo`, `detalle_id`, `fecha`, `producto`, `categoria`,
`cantidad`, `unidad`, `turno`, `forma`, `foto`, `usuario`, `observaciones`,
`empleados`.

#### `app/Consultas/ResumenDashboard.php` (nueva)

Agregaciones del dashboard: totales por categoría, las 3 cartas y los 3 gráficos.
Hace 3 consultas `groupBy` (una por tabla de detalle) y las fusiona en PHP, en
vez de un `UNION` que sería frágil entre motores de base de datos.

### 3.3 Controladores (3)

| Controlador | Variables que pasa a la vista |
|---|---|
| `HistorialController@index` | `$registros`, `$totalRegistros` |
| `ProduccionController@index` | `$lineas`, `$productos`, `$turnos`, `$empleados`, `$rolesProduccion` |
| `DashboardController@index` | `$tarjetas`, `$cartas`, `$graficos` |

> `ProduccionController` conserva sus 6 métodos de resource (`index`, `store`,
> `show`, `edit`, `update`, `destroy`). `store()` sigue vacío.

### 3.4 Vistas Blade (3)

#### `history/index.blade.php`

Reemplazados 2 registros de prueba fijos (`20 OCT 2026 / Pan carioco / 1 coche /
rosa` y `19 OCT 2026`) por un `@forelse` sobre `$registros`. El total
`23 registros encontrados` pasó a ser dinámico.

La cabecera de fecha se imprime **una sola vez por día**:

```blade
@if ($loop->first || $registro->fecha !== $registros[$loop->index - 1]->fecha)
    <div class="fecha-dia">
        {{ strtoupper(date('d M Y', strtotime($registro->fecha))) }}
    </div>
@endif
```

El `<img>` solo se renderiza si la línea tiene foto: únicamente las tortas
tienen esa columna. Para pan y bocadito no hay imagen, y la URL
`via.placeholder.com` del mock era un servicio ya dado de baja.

#### `produccion/index.blade.php`

Reemplazadas 3 tarjetas fijas (`Pan Carioca / 3 coches`, `Torta de Chocolate /
Circular`, `Alfajorcitos / 150 unidades`) por un `@forelse` sobre `$lineas`.

Además se llenaron 3 `<select>` que tenían `<option>` escritas a mano:

| Select | Antes | Ahora |
|---|---|---|
| `#turno` | `1 = Mañana`, `2 = Noche` | tabla `turnos`, `value` = id |
| `#select-empleado` | 5 empleados inventados | tabla `empleados`, `value` = id |
| `#select-rol` | `Maestro`, `Ayudante` | tabla `roles_produccion`, `value` = id |
| `#producto` | **vacío** (solo placeholder) | 10 productos activos |

#### `dashboard/index.blade.php`

Reemplazados los números fijos de las 3 tarjetas de categoría
(`12.5 Coches`, `15 Unidades`, `900 Unidades`), los `0` de las 3 cartas, y los
3 modales completos con datos inventados (productos, cantidades, empleados
"Manuel Rojas", "Rosa Díaz", "Ana Torres", "Luis Paredes", y
`Registrado por rosa.diaz`).

### 3.5 `resources/js/dashboard.js`

Los 3 gráficos de Chart.js tenían los datos fijos en el archivo:

```js
data: [0, 0, 0]            // grafico_dia
data: [0, 0, 0, 0, 0, 0]  // grafico_semana
data: [0, 0, 0, 0]         // grafico_mes
```

Ahora la vista inyecta los datos y el JS los lee:

```blade
<script id="datos-graficos" type="application/json">@json($graficos)</script>
```

```js
const graficos = JSON.parse(document.getElementById('datos-graficos').textContent);
```

`@json()` escapa el contenido, así que es seguro dentro de un `<script>`.

### 3.6 Bugs encontrados y corregidos durante la implementación

Los cuatro aparecieron recién al ejecutar, no al leer el código:

**a) Relaciones inexistentes.** `LineasProduccion`Pedía eager-load de
`unidadMedida` en `DetalleTorta`, pero ese modelo **no tiene esa relación**
aunque la tabla sí tiene la columna. Sacado el eager-load.

**b) `join()` con los argumentos mal ordenados.** Laravel espera
`join($tabla, $columna, '=', $columna)`. Estaba pasando 3 argumentos con el `=`
dentro del string, generando SQL inválido (`'=detalle_pan.produccion_id'`).

**c) `startOfWeek()` / `endOfWeek()` mutan el objeto.** En Carbon 2 estos
métodos modifican la instancia que reciben. El código hacía:

```php
$hoy->startOfWeek(), $hoy->endOfWeek()   // ambos sobre el MISMO objeto
```

Como el primero ya había movido `$hoy`, el segundo volvía a moverse, y el rango
terminaba siendo `[2026-09-27, 2026-09-27]` — un solo día. Resultado: los
gráficos de semana y mes salían **todos en 0**. Se corrigió con `copy()` en cada
llamada.

**d) Pérdida de métodos al sobrescribir.** Al reescribir
`ProduccionController.php` se perdieron los métodos `store`, `show`, `edit`,
`update` y `destroy` del resource. Detectado con `git diff` y restaurados.

### 3.7 Decisiones de negocio tomadas en esta fase

Estas 4 no se podían inventar: cambian lo que muestra la pantalla.

**1. El total de pan se agrupa por unidad, no se suma.**
`detalle_pan` tiene `unidad_medida_id` por línea, así que sumar `24 unidad` +
`3 coche` no es una operación válida. La tarjeta muestra `"42 unidad"`, o
`"5 lata + 2 coche"` si se mezclan.

**2. La torta se cuenta, no se suma.** La tarjeta decía `15 Unidades`, pero
`detalle_torta` **no tiene columna `cantidad`**: 1 registro = 1 torta. Ahora
muestra `0 tortas` / `2 tortas`. Se cambió el dato del mock porque contradecía el
esquema acordado en la fase 2.

**3. Los gráficos cuentan líneas, no suman cantidades.** En un mismo gráfico
conviven 84 panes con 2 tortas. Sumarlos no significa nada, así que la métrica es
**cantidad de registros** por categoría o período.

**4. "Pendiente" y "Completada" quedan en `-`.** No existe columna de estado en
`produccion` ni en sus 3 tablas de detalle. La única booleana del proyecto es
`pedidos.entregado`, que es de pedidos, no de producción. **No se inventó el
estado**: hay que definir el concepto y la columna antes de calcularlo. Solo
`Producción Total` se calcula (líneas registradas hoy).

---

## Verificación

### Comandos ejecutados

```bash
php -l <cada archivo PHP>                    # sintaxis
php artisan migrate:fresh --seed --force     # reconstruye la base
php artisan view:cache                       # compila todas las vistas Blade
node --check resources/js/dashboard.js       # sintaxis JS
npm run build                                # Vite
php -S 127.0.0.1:8123 -t public              # servidor para probar
php artisan test
```

### Resultado

- `GET /dashboard` → **200**
- `GET /produccion` → **200**
- `GET /history` → **200**

**Datos renderizados desde la base:**

```
DASHBOARD   Pan 42 unidad | Torta 0 tortas | Bocadito 150 unidades
            cartas: total=3, pendiente=-, completada=-
            gráficos: hoy {Pan:2, Torta:0, Bocadito:1}
                      ayer {Pan:1, Torta:1, Bocadito:0}
                      semana [Lun..Sáb] = [0,0,2,3,2,3]
                      mes [Sem 1..4] = [0,0,0,10]
            modales: desglose, empleados y "registrado por" reales

PRODUCCIÓN  #turno 2 opts | #producto 10 | #select-empleado 2 | #select-rol 2
            6 tarjetas con producto, cantidad, unidad y empleados reales

HISTORIAL   10 registros, 4 grupos de fecha, sin fechas repetidas
```

**Datos estáticos restantes en las 3 vistas: 0.** Se verificó buscando
`12.5`, `900 Unidades`, `23 registros`, `Pan carioco`, `via.placeholder`, `Rosa P.`,
`Luis F.`, `Marta S.`, `3 coches` y `data: [0` en el HTML renderizado y en el
JS.

**Estados vacíos probados** en las 3 vistas (con rollback de transacción):
mensaje amigable en vez de lista vacía.

### Tests

`php artisan test` mantiene los **2 fallos preexistentes**:

```
FAIL  ExampleTest — GET / devuelve 404 (la raíz no tiene ruta)
```

Es un test de ejemplo de Laravel que no se ajustó a este proyecto. **No lo
introdujeron estos cambios.**

---

## Pendientes

### Alta prioridad

| # | Tema | Detalle |
|---|---|---|
| 1 | **Rutas sin autenticación** | `/dashboard`, `/produccion` y `/history` son públicas. Ahora muestran datos reales de la base, así que **cualquiera sin login los ve**. Ya estaba señalado en la auditoría; pasó de "mostrar mocks" a "mostrar datos de producción". |
| 2 | **4 seeders preexistentes no son idempotentes** | `CargoSeeder`, `RolSeeder`, `EmpleadoSeeder` y `UsuarioSeeder` usan `insert()` plano. El segundo `db:seed` falla con `Duplicate entry 'carlos.m' for key 'usuarios_sistema_username_unique'`. Los 6 seeders nuevos sí son idempotentes. Mientras tanto, usar **`migrate:fresh --seed`**. |
| 3 | **Factores de conversión de `lata` y `coche`** | Están en `NULL` a propósito. Hay que confirmarlos y actualizar `unidades_medida.equivalencia_unidades`. |

### Media prioridad

| # | Tema | Detalle |
|---|---|---|
| 4 | `DetalleTorta` incompleto | La migración agregó `unidad_medida_id` pero el modelo no tiene la relación ni el campo en `$fillable`. Los datos están (id=1) pero el modelo no los alcanza. |
| 5 | `store()` vacío | `ProduccionController@store()` no hace nada, así que el formulario de producción no guarda. |
| 6 | `produccion.js` manda nombre en vez de id | Toma `selectEmpleado.value` y crea un input `name="nombre"`. Ahora las options valen id, así que mandaría el id con nombre de campo `nombre`. Bug preexistente; irrelevante mientras `store()` esté vacío. Se arregla junto con el #5. |
| 7 | Filtros del historial sin implementar | Categoría / fecha desde-hasta / turno: los botones solo hacen `console.log`. Se decidió **no** implementarlos en esta fase. |
| 8 | Imágenes faltantes | `public/images/` tiene solo `.gitkeep`; las vistas piden `placeholder-pan.jpg`, `placeholder-torta.jpg`, `placeholder-bocadito.jpg` y más. |
| 9 | "Producción Pendiente / Completada" sin definir | Falta la columna de estado y el concepto de negocio. |
| 10 | `pint` y `phpstan` | El repo ya fallaba el formateo antes de estos cambios; no se ejecutaron para no generar ruido. |
| 11 | Paginación | El historial carga todos los registros. Con volumen real hace falta `paginate()` + enlaces. |
