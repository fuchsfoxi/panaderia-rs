# Documentación técnica — Estabilización de "Panadería RS"

**Rama:** `prototipo-estable` (5 commits sobre `master` en `1d076e1`)
**Fecha:** 28 de septiembre de 2026
**Alcance:** dejar el prototipo funcional, navegable y usable desde un celular
antes de la prueba con usuarios reales.

Este documento explica **qué se cambió, cómo, con qué herramientas y por qué**.
No es un listado de cambios: es el argumento de cada decisión, incluyendo las
que se descartaron.

---

## Índice

1. [Resumen ejecutivo](#1-resumen-ejecutivo)
2. [El punto de partida: qué estaba roto](#2-el-punto-de-partida-qué-estaba-roto)
3. [Herramientas y método de trabajo](#3-herramientas-y-método-de-trabajo)
4. [Bloque 1 — Login y autenticación](#4-bloque-1--login-y-autenticación)
5. [Bloque 2 — Rutas y navegación](#5-bloque-2--rutas-y-navegación)
6. [Bloque 3 — Diseño responsive](#6-bloque-3--diseño-responsive)
7. [Bloque 4 — Estabilidad y datos](#7-bloque-4--estabilidad-y-datos)
8. [Bloque 5 — Pruebas en celular](#8-bloque-5--pruebas-en-celular)
9. [Decisiones que se descartaron](#9-decisiones-que-se-descartaron)
10. [Cómo se verificó](#10-cómo-se-verificó)
11. [Lo que quedó pendiente](#11-lo-que-quedó-pendiente)
12. [Comandos de referencia](#12-comandos-de-referencia)

---

## 1. Resumen ejecutivo

El prototipo no tenía **una** falla sino cuatro que se Tapaban entre sí:

| # | Falla | Efecto |
|---|---|---|
| 1 | `POST /login` no existía | Login imposible (error 405) |
| 2 | Vite en modo servidor de desarrollo | Desde el celular **no cargaba ni un estilo** |
| 3 | Base de datos vacía | Dashboard e historial en blanco |
| 4 | Cero código responsive | Ilegible en cualquier pantalla angosta |

El orden de trabajo no fue arbitrario. Se resolvió primero lo que **impedía
entrar** (1), después lo que **rompía la navegación** (4 y 2), y al final lo
que **no se notaba hasta usarlo** (filtros, mensajes de error, datos de prueba).

### Resultado

- 46 archivos cambiados, 2.584 líneas agregadas, 421 eliminadas.
- 11 pruebas automáticas, todas en verde.
- Cero excepciones en `storage/logs/laravel.log` recorriendo el sistema entero.
- `migrate:fresh --seed` corre limpio y se puede repetir.
- Verificado por HTTP real sobre `http://192.168.18.35` (la IP de la Wi-Fi), no
  solo en `localhost`.

### El usuario de prueba

| Usuario | Contraseña |
|---|---|
| `carlos.m` | `password123` |

---

## 2. El punto de partida: qué estaba roto

Antes de tocar nada se hizo un diagnóstico de solo lectura. Esto no fue
ceremonial: cambió el plan dos veces.

### 2.1 El bug reportado y su causa real

El síntoma era:

```
405 Method Not Allowed: The POST method is not supported for route login
```

La causa: `routes/web.php` solo declaraba `GET /login`, y el formulario en
`auth/login.blade.php` mandaba `POST` a la misma URL.

`php artisan route:list` lo confirmaba — 5 rutas de aplicación, ninguna de
login por POST.

### 2.2 Hallazgo que cambió el plan: la base estaba vacía

```
usuarios=0  empleados=0  productos=0  produccion=0
detalle_pan=0  detalle_torta=0  detalle_bocadito=0
```

Las 25 tablas existían pero **no había ni una fila**. Sin embargo, el código de
las consultas (`LineasProduccion`, `ResumenDashboard`) ya usaba datos reales, y
los seeders ya eran idempotentes. El trabajo previo estaba bien hecho: faltaba
simplemente correrlo.

Esto generó la pregunta que frenó todo: sin autorización no se podía probar el
login correcto. La resolución ("¿autorizas `db:seed`?") llegó recién después
del Bloque 1, y para entonces el login ya estaba probado contra credenciales
falsas y el logout contra sesión real.

### 2.3 Hallazgo crítico: Vite en modo desarrollo

`public/hot` contenía:

```
http://[::1]:5173
```

Todas las hojas de estilo se cargaban desde ahí. Dos consecuencias:

1. `[::1]` es loopback **IPv6**: ni siquiera funciona en `localhost` si el
   navegador resuelve por IPv4.
2. Desde un celular en la Wi-Fi es imposible: no existe tal dirección en la red.

Es decir: **el sistema se veía perfecto en la laptop del desarrollador y
desnudo en el celular**. Como la prueba es en celular, esto era prioridad cero
junto con el login. Se resolvió borrando `public/hot` y obligando a compilar
(`npm run build`), que además quedó documentado en el README.

### 2.4 Hallazgo que se detuvo a preguntar

`usuarios_sistema` **no tiene columna `activo`**. El esquema es:

```
id, username, password_hash, empleado_id, rol_id, created_at, updated_at
```

El único `activo` del proyecto está en `productos.activo`, que no sirve para
usuarios.

Esto se Amoró con una pregunta explícita antes de seguir, porque la instrucción
era no tocar el esquema. La respuesta fue **opción A**: implementar el login sin
filtro de estado y dejar el código listo y comentado para cuando exista la
columna. Es el patrón correcto aquí: no inventar un filtro sobre una columna
inexistente (daría error de SQL) ni tocar el esquema (fuera de alcance).

### 2.5 Lo que ya estaba bien (y por eso no se tocó)

Es importante decirlo, porque buena parte del proyecto ya era trabajo de
calidad y rehacerlo habría sido destructivo:

| Pieza | Estado |
|---|---|
| `config/auth.php` | Ya apuntaba a `UsuarioSistema` con driver `eloquent` |
| `UsuarioSistema::getAuthPassword()` | Ya devolvía `password_hash` |
| `$hidden` | Ya ocultaba `password_hash` |
| Migraciones | 22 migraciones, todas aplicadas, esquema completo |
| Seeders | 9 de 10 ya usaban `firstOrCreate` |
| `LineasProduccion` / `ResumenDashboard` | Normalización de 3 tablas de detalle en una colección, con reglas documentadas de unidades |
| `ProduccionController@store` | Validación dinámica por categoría + transacción |
| `layouts/app` + `<x-sidebar />` | Layout único con `routeIs()` para el ítem activo |

**Criterio aplicado:** antes de cambiar algo, verificar si ya estaba hecho. Tres
archivos resultaron ser código muerto; el resto se respetó tal cual.

---

## 3. Herramientas y método de trabajo

### 3.1 Herramientas y para qué

| Herramienta | Uso concreto |
|---|---|
| `php artisan route:list` | Inventario de rutas y sus nombres |
| `php artisan migrate:status` | Confirmar que las 22 migraciones estaban aplicadas (por eso `db:seed` era suficiente y no hacía falta `migrate`) |
| `curl` con *cookie jar* | Simular el flujo HTTP real: login → sesión → páginas → logout. Más fiel que leer el código |
| `mysql` (cliente) | Ver datos reales para confirmar que un guardado funcionó |
| `php artisan test` (Pest) | 11 pruebas del cableado de autenticación |
| `./vendor/bin/pint` | Estilo de código PHP. Solo sobre archivos propios: los preexistentes quedan intactos |
| `node --check` | Validar sintaxis de los `.js` sin navegador |
| `npm run build` | Compilar assets; el resultado se comparaba contra los archivos esperados |
| `python3` (parches por reemplazo exacto) | Editar CSS/JS con acentos y caracteres especiales. `sed` y el editor por líneas fallaban con no-ASCII |
| `git` | Un commit por bloque, para poder volver atrás bloque por bloque |

### 3.2 Por qué `curl` y no solo tests

Los tests automatizados corren en `sqlite :memory:`, y **esta máquina no tiene
el driver `pdo_sqlite`** (verificado: `PDO::getAvailableDrivers()` devuelve solo
`mysql`). Cualquier prueba que consulte la base no podía correr, e instalar la
extensión PHP habría sido cambiar el entorno, justo lo que se pidió evitar.

Solución: tests **sin base de datos** para lo que sí se puede verificar
(cableado, middleware, redirecciones, validación, logout) y verificación del
guardado real con peticiones HTTP contra el servidor.

### 3.3 Por qué un commit por bloque

Para poder revertir un bloque sin perder los demás. Si mañana el menú
hamburguesa molesta, `git revert eada331` y los otros cuatro siguen. Con un
commit único de 2.500 líneas, revertir significa reconstruir todo a mano.

### 3.4 Un obstáculo real: la caché de opcode

La primera prueba en vivo dio 405 y 404 en rutas que sí existían, y un error
`Route [login.attempt] not defined` que el CLI no reproducía. Causa:

```
opcache.enable => On
opcache.revalidate_freq => 180
```

El servidor `php artisan serve` del usuario llevaba **3 minutos** sirviendo la
versión vieja de los archivos. Se comprobó con `php artisan route:list` (que sí
veía la ruta nueva) y se resolvió levantando un servidor propio en otro puerto
para las pruebas, sin tocar el proceso del usuario. **Esto no era un bug del
código**: conviene saberlo porque cualquier prueba de este proyecto contra el
serve ya iniciado puede dar resultados falsos durante 3 minutos.

---

## 4. Bloque 1 — Login y autenticación

**Commit:** `69afb84` — *login: agrega autenticación POST, logout y throttling*

### 4.1 Por qué la ruta POST se llama `login.attempt`

Es tentador llamar `login` a la ruta POST. **Eso rompe el sistema.**

Laravel busca la ruta llamada `login` para mandar ahí a quien intenta entrar sin
sesión (el middleware `auth` la usa como destino por defecto). Si el nombre
`login` pasa a ser del POST, el middleware `guest` y el `auth` apuntarían a un
endpoint que no renderiza nada.

Solución: el **GET conserva `login`** (es la página) y el **POST recibe
`login.attempt`**. Cada nombre describe lo que hace: una es la página, la otra
es el intento de enviarla. Está documentado en un comentario en `web.php` para
que nadie lo "simplifique" después.

### 4.2 Por qué `Auth::attempt()` y no `Hash::check()` a mano

Podría haberse escrito:

```php
$user = UsuarioSistema::where('username', $input)->first();
if ($user && Hash::check($password, $user->password_hash)) { ... }
```

Se usó `Auth::attempt()` porque:

1. **Evita que el hash salga del modelo.** Con `Hash::check` hay que leer
   `password_hash` explícitamente; con `Auth::attempt` la columna se resuelve
   sola vía `getAuthPassword()`. Un solo punto donde equivocarse.
2. **Registra la sesión correctamente.** `attempt()` crea la sesión, recuerda
   el token y el "remember me" sin código extra.
3. **Es la vía que ya soporta el guard configurado.** `config/auth.php` ya
   apuntaba a `UsuarioSistema`; `attempt()` lo respeta sin configuración
   adicional.

Dentro del controlador **jamás se nombra `password_hash`**. Solo se pasan
`username` y `password`. El mapeo vive en el modelo, que es donde debe estar.

### 4.3 Por qué el filtro `activo` quedó comentado y no implementado

```php
// Cuando exista una columna 'activo' en usuarios_sistema, el filtro se
// agrega en este mismo array:
//     $buscable['activo'] = true;
// (hoy esa columna no existe en el esquema y consultarla daria error,
//  por eso NO se escribe todavia).
```

Tres razones para dejarlo así y no "resolverlo":

1. **No existe la columna.** `where('activo', true)` sobre una tabla sin esa
   columna es un error de SQL en cada intento de login: se rompería el login
   entero, no solo el filtro.
2. **Usar `empleados` o `roles` como equivalente sería inventar semántica.**
   Que alguien sea panadero no significa que su cuenta esté habilitada.
3. **Queda a un paso.** Cuando exista la columna, es agregar una línea. El
   comentario dice exactamente dónde.

### 4.4 Por qué el límite es por **usuario + IP**

`RateLimiter::tooManyAttempts("login|usuario|ip", 5)`.

Las dos mitades atacan cosas distintas:

| Clave | Ataque que frena |
|---|---|
| Solo usuario | Fuerza bruta contra **una** cuenta conocida |
| Solo IP | Un solo equipo probando **muchas** cuentas |
| Usuario + IP | Ambos, a la vez |

Con 5 intentos por minuto, un atacante tiene que probar 7.200 contraseñas por día
contra una cuenta, y no puede barrer cientos de cuentas desde la misma máquina.

El mensaje dice los segundos exactos que faltan, no un "esperá un momento"
ambiguo, porque un mensaje vago hace que la gente pruebe de nuevo y agote el
límite antes de que expire.

### 4.5 Por qué `session()->regenerate()` y no nada

**Session fixation**: si un atacante logra fijar el identificador de sesión en
el navegador de la víctima (por ejemplo, inyectándolo en un enlace), y la
víctima se loguea sin que ese identificador cambie, el atacante hereda una
sesión válida.

`regenerate()` cambia el identificador **después** de autenticar. Por eso va
justo después de `Auth::attempt()` exitoso y no antes.

### 4.6 Por qué el mensaje de error es genérico

```
Usuario o contraseña incorrectos.
```

Alternativas descartadas: "usuario no encontrado" o "contraseña incorrecta".

Cada una **confirma información útil para un atacante**: la primera le dice qué
cuentas existen, la segunda confirma que encontró el usuario correcto. El
mensaje genérico no revela cuál de los dos falló. La ergonomía se recupera
mostrando el error arriba del formulario, novagando detalles.

### 4.7 Por qué `withInput($request->except('password'))`

Dos beneficios distintos:

- **`except('password')`**: la contraseña **nunca** vuelve al HTML. Es el motivo
  por el que no se usa `->withInput()` a secas: eso rehidrata todos los campos
  del request, incluida la contraseña, y queda en el HTML y en el caché del
  navegador.
- **Lo que sí se conserva**: el usuario escrito, para no obligar a escribirlo de
  nuevo.

### 4.8 Por qué los mensajes están **dentro** del controlador

```php
'username.required' => 'Escribí tu usuario.',
```

La alternativa idiomática es un archivo `lang/es/validation.php` con
`APP_LOCALE=es`. Se hizo así para el login porque el idioma en `.env` es
`local` por máquina: si alguien clona el proyecto y no cambia el `.env`, los
mensajes se leen en inglés. Con los mensajes en el controlador, el login se ve
en español **siempre**.

(Para el resto del sistema la solución fue otra, y está en el Bloque 4: se
agregó el archivo de traducciones y se fijó el idioma en código.)

### 4.9 Por qué `guest` en `/login` y `auth` en las demás

- `guest` en `/login`: un usuario **con sesión** que entra a `/login` a mano ve
  el formulario y puede volver a loguearse. Con `guest` lo mandan al dashboard.
- `auth` en el resto: sin sesión, al login. Ya estaba hecho; se respetó.

Un detalle que faltaba: el middleware `guest` de Laravel 11+ manda a
`/home` si no se configura el destino, y **`/home` no existe** en este proyecto
(va a ser un 404). Por eso:

```php
$middleware->redirectUsersTo(fn () => route('dashboard'));
$middleware->redirectGuestsTo(fn () => route('login'));
```

Se escribe `route('login')` y no el string `'login'` a propósito: si alguien
renombra la ruta, esto se actualiza solo y deja de romperse en silencio.

### 4.10 Por qué logout es POST

Un cierre de sesión con GET se puede disparar desde un `<img src="/logout">` en
cualquier página. Cualquier sitio en el que la persona esté logueada la
cerraría sin que lo pidiera. Por eso es `POST` con `@csrf`, y el botón vive
dentro de un `<form>` en el sidebar.

`invalidate()` borra los datos de sesión y `regenerateToken()` cambia el
`_token`, para que un POST guardado en el historial del navegador no vuelva a
servir.

### 4.11 El autocomplete correcto importa

```html
autocomplete="username"          → ofrece el usuario guardado
autocomplete="current-password"  → "es tu cuenta", no "creá una nueva"
autocapitalize="none"            → "Carlos" no se vuelve "CARLOS"
```

`autocapitalize="none"` es un detalle chico que evita una clase entera de
fallos de login: en muchos celulares el teclado pone la primera letra en
mayúscula sola, y si el usuario se llama `carlos.m`, la comparación falla.

---

## 5. Bloque 2 — Rutas y navegación

**Commit:** `5d06ec9` — *rutas: agrega pedidos (Próximamente), páginas de error y borra archivos muertos*

### 5.1 Por qué las páginas de error llevan su propio layout

`resources/views/errors/` **no extendían** `layouts/app`, y no deben.

Ese layout incluye el menú lateral, que consulta `auth()->user()`. Una página
404 se muestra constantemente **sin sesión** (un enlace viejo, una URL mal
escrita). Si el layout dependiera del menú, el error dentro del error.

Por eso hay un `errors/layout.blade.php` mínimo: documento HTML propio, que
carga `variables.css` y `errores.css` y nada más. No depende de nada que
pueda fallar.

### 5.2 Por qué 419 es importante en un sistema con sesión en base

Con `SESSION_DRIVER=database` y `SESSION_LIFETIME=120`, a las dos horas de
inactividad la sesión desaparece de la tabla `sessions`. El POST siguiente
falla con **419 Page Expired**.

Sin página propia, el usuario ve un error en inglés sin ninguna instrucción.
Con ella ve:

> **419 — La sesión expiró** · La sesión expiró, vuelve a intentarlo. ·
> [Ir a iniciar sesión]

Es el error más probable que se va a ver durante toda la prueba del
martes, y por eso tiene su propia página y no comparte la de 404.

### 5.3 Por qué Pedidos es una página y no un enlace muerto

Había tres opciones: implementar, dejar un `href="#"`, o una página de aviso.
Se eligió la tercera.

- **`href="#"`** es peor que nada: el usuario toca y no pasa nada, y no sabe si
  rompió algo o si la función no existe. En una demo frente a usuarios reales,
  un botón que no hace nada **resta credibilidad a todo lo demás**.
- **Implementarlo** estaba fuera de alcance (y del esquema, que sí tiene
  tablas `pedidos` y `detalle_pedidos`, pero sin requisitos definidos).
- **La página de aviso** convierte una decepción en información: el usuario
  sabe que el módulo existe y que todavía no está. Además, cuando se implemente,
  se cambia **una línea** (el destino del ítem del sidebar).

El ítem del sidebar se agregó al **mismo array `$items`**, así que hereda
automáticamente el resaltado del ítem activo por `routeIs()`. No hay una
excepción para esta sección.

### 5.4 Por qué se borraron cuatro archivos

Se borraron **solo** archivos verificados como inalcanzables:

| Archivo | Por qué estaba muerto |
|---|---|
| `resources/layouts/app.blade.php` | 1 línea con un `@vite` de dashboard. Blade resuelve `layouts.app` a `resources/views/layouts/` |
| `resources/components/sidebar.blade.php` | Barra lateral vieja con 4 `href="#"` y un botón de logout sin POST. El `<x-sidebar />` resuelve a `views/components/` |
| `database/factories/UserFactory.php` | Apuntaba a `App\Models\User`, **clase que no existe** en el proyecto |
| `resources/views/history_pedido/index.blade.php` | Esqueleto: `<html lang="en">` y `<div class="tittle"></div>` vacío, sin ruta ni enlace |
| `use App\Models\User;` en `DatabaseSeeder` | Import de una clase inexistente, sin usarse |

**Por qué importa:** el costo no es el espacio que ocupan. Es que la siguiente
persona que lea el proyecto (o el propio asistente mañana) encuentra un
`App\Models\User` y asume que hay un modelo `User`, o ve un sidebar con logout y
asume que el logout existe. **El código muerto miente.** Borrarlo no cambió una
sola línea de comportamiento — se verificó que nada los referenciaba antes de
borrar (`grep` sobre `app`, `database`, `tests`, `routes`, `config`).

### 5.5 Por qué `dvh` y no `vh`

`vh` significa "1% de la altura de la **ventana**", la altura del **viewport sin
la barra del navegador**. En el celular, cuando esa barra aparece o desaparece
(al scrollear, al abrir el teclado), `100vh` queda más alto que la pantalla
real.

La consecuencia típica: la página de login quedaba con scroll vertical y la
tarjeta cortándose abajo. `dvh` (**dynamic** viewport height) sí sigue a la
barra. Se aplicó en los cuatro CSS que usaban `vh` y se dejó `vh` fuera
completamente.

### 5.6 Por qué `100vw` produce scroll horizontal

`vw` es el ancho de la ventana **incluida la barra de scroll vertical**. Si la
página tiene scroll, `100vw` es ~15px más ancha que el área visible → aparece
scroll horizontal. El login usaba `width: 100vw` y `height: 100vh`. Se cambió
a `100%`, y además se puso `overflow-x: hidden` en `html` como red de seguridad.

---

## 6. Bloque 3 — Diseño responsive

**Commit:** `eada331` — *responsive: menu hamburguesa, una columna en móvil y tipografía de títulos*

### 6.1 El menú hamburguesa: por qué `transform` y no `display`

```css
.lateral          { transform: translateX(-100%); }
.lateral--abierto { transform: translateX(0); }
```

La alternativa obvia es `display: none`. Se descartó por dos razones:

1. **Sin transición.** Con `display` el panel aparece de golpe. Con `transform`
   entra deslizando, que es lo que el usuario espera.
2. **Accesibilidad.** Con `display: none` los enlaces del menú desaparecen del
   árbol de accesibilidad. Con `transform` el panel sigue en el DOM: un lector de
   pantalla lo encuentra, y el JS puede mover el foco.

El panel se mueve, no se reserva espacio: `position: fixed` + `transform`, en
lugar de sacar el menú del flujo. Así el contenido no salta al abrir.

### 6.2 Por qué el breakpoint es 768px y no el 900px que ya estaba

El CSS anterior ponía el menú como **barra horizontal con `overflow-x: auto`**
por debajo de 900px. En un celular de 360px, el menú se iba del lado y había
que scrollear **horizontalmente** para llegar a "Historial". Además, ese bloque
ocultaba `.lateral__pie`, es decir **el botón de logout era inalcanzable desde
el celular**.

768px es un punto que existe en el Bootstrap, pero más importante: en la
práctica el celular queda por debajo y la tablet por encima. El criterio real
fue "que en un celular de 360px quepan el logo, los cuatro enlaces y el logout
sin scroll", y el panel lateral lo cumple mejor que cualquier barra.

**En escritorio (≥768px) no cambió absolutamente nada**: mismas 248px, mismo
fondo, mismos ítems.

### 6.3 Por qué el menú se cierra de tres maneras

Las tres son necesarias **en un celular**, y cada una resuelve un caso real:

| Gesto | Por qué |
|---|---|
| Tocar el fondo | Es lo que hace el usuario al intentar "volver" a la página |
| Tocar un enlace | Navegar y dejar el panel abierto al volver atrás es confuso |
| Escape | Accesibilidad: teclado o lector de pantalla |

Además, al cerrar con Escape el foco vuelve al botón, para que el teclado no
quede perdido en el aire.

### 6.4 Por qué 44px

Es el mínimo recomendado por Material Design y Apple para un control táctil.
Un botón de 26px —el que tenía el "+" de agregar empleado— es casi imposible
de tocar con el dedo. Se aplicó como variable CSS (`--toque-minimo: 44px`)
para que el número esté en **un solo lugar** y sea auditable.

### 6.5 Por qué 16px en los inputs (el detalle que más se subestima)

En iOS, un campo con fuente de **menos de 16px** hace que la página haga zoom
automáticamente al enfocarlo. Y como el zoom **no se revierte** al perder el
foco, el usuario queda desplazado, con la mitad del formulario fuera de
pantalla, y tiene que hacer zoom manual para seguir.

Varios campos estaban en 13px y 15px (`produccion.css` tenía `15px`, y los
selects del popover de empleados `13px`). Se subi todos a 16px. Es un cambio de
**una línea** que evita el problema más frustrante que se puede tener en un
formulario móvil.

### 6.6 Por qué `grid` con `auto-fit` en lugar de media queries

```css
grid-template-columns: repeat(auto-fit, minmax(min(100%, 20rem), 1fr));
```

Esto hace que la grilla ponga tantas columnas como entren en el ancho
disponible, sin saber de antemano el ancho. Con `min(100%, ...)` el `minmax`
nunca fuerza un mínimo mayor que el contenedor, así que **en 360px cae solo a
una columna** sin ningún `@media`.

**Por qué no media queries:** para cada cantidad de columnas había que escribir
la cascada (768px, 1024px, 1280px...) y cada vez que se agrega una tarjeta hay
que revisar las tres. Con `auto-fit` el sistema se adapta solo.

**Por qué no flexbox:** `flex: 1` con `min-width: 260px` (lo que había) produce
el efecto contrario al deseado: en un ancho intermedio las tarjetas se
estrechan hasta 260px en vez de bajar de fila. `auto-fit` da columnas de ancho
constante y salta de fila solo cuando ya no entra.

### 6.7 Por qué el `<canvas>` necesitaba altura **definida**

Este fue el bug más silencioso. El CSS tenía:

```css
.grafico canvas { max-height: 400px; }
```

Y el JS creaba los gráficos con `maintainAspectRatio: false`. Con esa opción,
Chart.js **estira el lienzo hasta llenar el contenedor**. Pero un `max-height`
no le da altura: el contenedor no tenía altura propia, así que el lienzo se
quedaba en su altura mínima y el gráfico se veía aplastado o invisible.

La corrección define la altura en el canvas, no la limita:

```css
.grafico canvas { display: block; width: 100%; height: 280px; }
@media (max-width: 768px) { .grafico canvas { height: 220px; } }
```

Y con `maintainAspectRatio: false` ya presente, Chart.js **redimensiona solo al
girar el celular**: escucha el `resize` de la ventana. No hace falta código
adicional.

### 6.8 Por qué el video de fondo se apaga en el celular

`autoplay muted loop playsinline` ya estaba bien. El problema es otro: un video
de fondo en 4G o con poca batería **consume datos y el navegador lo frena**, y
en muchos navegadores móviles la política de autoplay con sonido o sin
`playsinline` directamente lo bloquea.

En menos de 768px el video se oculta y el fondo queda con `--verde-oscuro`, que
es el **color de respaldo de la paleta**. El login se ve igual de bien, sin
descargar nada. Además se respeta `prefers-reduced-motion`: si la persona pidió
menos animación en el sistema, el video no se reproduce.

### 6.9 La tipografía: por qué Baloo 2 para títulos y Huninn para el texto

Huninn es una sans geométrica redondeada, de peso único (400). Como cuerpo de
texto funciona bien, pero como título se queda **plana**: no tiene el contraste
de peso que hace que un encabezado se lea como encabezado.

Se eligió **Baloo 2** porque:

1. **Es redondeada**, igual que Huninn. Viene de la misma familia visual, así
   que el sistema sigue sintiéndose uno solo. Una tipografía geométrica
   distinta (tipo Inter o Roboto) hubiera roto el carácter redondeado de la
   marca.
2. **Ya estaba cargada.** `login.css` importaba Baloo 2 y Quicksand. No es una
   dependencia nueva: es una fuente que el proyecto ya pedía.
3. **Tenía peso 600 y 700**, así que da contraste real contra el 400 de Huninn.
4. **Redondeada de verdad**: los finales de las letras son suaves, no angulares.

Se aplicó como variable:

```css
--fuente: 'Huninn', sans-serif;              /* texto normal */
--fuente-titulos: 'Baloo 2', var(--fuente);  /* títulos y subtítulos */
```

**Huninn no se tocó para el texto normal**, tal como se pidió.

**Corrección de paso que no estaba en el encargo:** el login usaba
`font-family: 'Quicksand'` en el `body`, con su propio `@import` de Baloo 2 +
Quicksand. Es decir, **el login se veía en una tipografía distinta al resto del
sistema**. Se unificó: las dos fuentes se importan una sola vez, en
`variables.css`, que se carga en todas las páginas. Ahora el login y el
dashboard comparten tipografía.

### 6.10 Por qué una sola fuente de verdad para los colores

`sidebar.css` repetía los hexadecimales de la marca a mano: `#33403A`,
`#7D9481`, `#AEC0AC`… El valor era el correcto, pero **escrito 15 veces en un
archivo y otra vez en cada CSS de página**. Es exactamente la forma en que una
paleta se desincroniza: alguien cambia un color en un lado y no en el otro.

Se reemplazaron **todas** las apariciones por variables, **sin cambiar ningún
valor**. Además se descubrió que `produccion.css` usaba `#C07F4E`, un color
**que no pertenece a la paleta** (era un naranja tierra) → se cambió a
`--verde-medio`, que cumple el mismo rol de acento.

Resultado: **no queda un solo color escrito a mano fuera de `variables.css`**.
Para cambiar la marca se edita un archivo.

### 6.11 Por qué 4 imágenes SVG y no imágenes de mapa de bits

Las vistas referenciaban cuatro imágenes que **no existían**: la carpeta
`public/images/` estaba completamente vacía. El ícono del login y los tres
placeholders del dashboard daban ícono roto.

Se crearon como SVG con la paleta, en vez de como JPG/PNG, porque:

1. **Escalan sin perder calidad** en pantallas de alta densidad, que es
   justamente donde se van a ver (celulares).
2. **Pesan 500 bytes.** Un JPG equivalente son 5–15 KB.
3. **Se pueden versionar y revisar.** Un SVG es texto: se ve en un diff, se
   puede corregir un color con un editor de texto.
4. Los nombres pasaron de `.jpg`/`.png` a `.svg` en las vistas, que ahora
   apuntan a archivos que existen.

---

## 7. Bloque 4 — Estabilidad y datos

**Commit:** `b369585` — *estabilidad: filtros del historial, errores en español y datos de prueba*

### 7.1 Filtros del historial: por qué **GET** y no AJAX

Los botones "Filtrar" y "Limpiar" hacían `console.log`: **los filtros no
filtraban nada**.

Se eligió GET con parámetros normales (`?categoria=pan&desde=2026-09-01`) por
cuatro razones concretas:

1. **Funciona sin JavaScript.** Un formulario es un formulario. Un `fetch` que
   falla deja una pantalla vacía sin explicación.
2. **El botón "atrás" del celular conserva el filtro.** Con AJAX, la URL nunca
   cambia, así que "atrás" vuelve a la página anterior en la navegación, no a la
   vista sin filtro. Es la diferencia entre una app y una página.
3. **El filtro se puede compartir por enlace.** "Mirá estos datos del lunes" es
   un link.
4. **Se puede probar con `curl`.** Importante para verificar sin navegador.

Contra AJAX: en la práctica son 4 números y 2 fechas, el historial tiene decenas
de líneas, y recargar una página no es un problema a esta escala. Si algún día
molesta, el filtro baja a la consulta **sin cambiar la vista**.

### 7.2 Por qué filtrar en PHP y no en SQL

`LineasProduccion::obtener()` ya normaliza las tres tablas de detalle (pan, torta,
bocadito) en **una sola colección** con claves fijas. Filtrar se reduce a
`->where('tipo', ...)`, `->filter(...)`, `->values()`.

**Se decidió no tocar `LineasProduccion`**, que ya funcionaba, estaba
documentado y verificado. Filtrar en la consulta habría significado duplicar
lógica de filtros tres veces (una por tabla de detalle) o reescribir la clase.

El costo es honesto y está anotado en el código: se traen todas las líneas y se
descartan en memoria. Con el volumen de una panadería es aceptable.

### 7.3 Por qué el filtro de turno compara **nombres**

`LineasProduccion` devuelve `'turno' => $d->turno?->nombre_turnos` — el nombre,
no el id. La línea ya viene normalizada con el nombre, así que el filtro compara
nombres y **no hace una consulta extra por línea**.

Un detalle importante: el turno solo existe en `detalle_pan` (es la única de las
tres tablas con columna `turno_id`). Por eso la vista **muestra el campo de
turno solo cuando la categoría es pan**, y la regla se escribe como comentario
en el CSS, el JS y el controlador. Filtrar bocaditos por turno no daría error:
daría cero resultados, que es el comportamiento correcto y comprensible.

### 7.4 Por qué "sin datos" y "sin resultados" son mensajes distintos

Antes había un único `@empty` que decía *"Aún no hay producción registrada"*.
Con un filtro activo que no arroja nada, ese mensaje es **falso**: hay datos, el
filtro no los incluye.

Con un filtro que no coincide, el usuario ve:

> **Ningún registro coincide con el filtro** · Probá con otras fechas, otro tipo
> de producción o quitá el filtro de turno. · Quitar los filtros →

Enseña a usar la pantalla en vez de|reportar un problema que no existe.

### 7.5 Por qué el rango de fechas al revés se avisa

`desde=2026-09-27&hasta=2026-01-01` con comparación de texto devuelve **cero
resultados**, y el usuario ve una lista vacía y cree que no hay producción.

Se detecta la incoherencia, se avisa (*"La fecha Desde es posterior a la
Hasta"*) y se ignoran las dos fechas, de modo que se vea la lista completa en
lugar de una vacío sin explicación.

### 7.6 Por qué los mensajes de validación en español están en **dos** lugares

El framework de Laravel **solo trae traducciones en inglés**
(`vendor/laravel/framework/.../lang/en/` y nada más). No hay `es`.

Se agregaron dos piezas:

1. **`lang/es/validation.php`** con las reglas que usa el sistema y la sección
   `attributes` en español. Sin esto, "The producto id field is required." le
   sale a un panadero como un error de sistema.
2. **El idioma fijado en `AppServiceProvider`**:

   ```php
   config(['app.locale' => 'es', 'app.fallback_locale' => 'es']);
   ```

   **Por qué en código y no solo en `.env`:** el `.env` es local de cada
   máquina. Si alguien clona el proyecto y no cambia `APP_LOCALE`, los mensajes
   vuelven a inglés **sin ningún aviso**. Fijarlo en código hace que el idioma
   sea una propiedad del sistema y no un `.env` olvidado.

   El `fallback_locale` también va en español: si una regla no está
   traducida, sale en español y no en inglés.

### 7.7 Por qué el formulario de producción no mostraba ningún error

El controlador validaba bien, pero **la vista no mostraba nada**: cero
`@error`, cero `session('status')`. El usuario guardaba, la página recargaba, y
no tenía forma de saber si se había guardado o fallado.

Se agregó:

- **Mensaje de éxito** con el flash `->with('status', ...)`.
- **Resumen de errores** arriba, con el icono de aviso.
- **Error debajo de cada campo**, con `@error`.
- **`old()` en todos los campos**, para no perder lo escrito.

Es exactamente el caso donde una prueba con usuarios reales falla: el panadero
carga un lote, no ve confirmación, cree que no se guardó, y lo carga de nuevo.

### 7.8 Por qué los `<select>` no tienen opción preseleccionada

```html
<option value="">-- Selecciona un producto --</option>
```

Si el primer `<option>` fuera el producto real, el formulario se enviaría sin
que nadie eligiera nada, con un producto arbitrario. El placeholder vacío hace
que la validación "El campo producto es obligatorio" signifique algo.

### 7.9 Por qué la foto se guarda en `public/images/tortas` y no en `storage/app/public`

El enunciado pedía `storage/app/public` + `storage:link`. El código **ya** lo
hacía deliberadamente en `public/images/tortas`, con un comentario que lo
explica: el disco `public` de Laravel deja el archivo en `storage/app/public` y
necesita el symlink para ser visible.

Se respetó la decisión existente porque:

1. **Funciona**, y sin symlink: menos pasos de instalación = menos cosas que
   fallar mañana.
2. `public/images` es donde ya vivían las imágenes del proyecto.
3. Cambiarlo introduce un paso nuevo (`storage:link`) que puede fallar.

Lo que **sí** se agregó:

- `public/images/tortas/.gitignore` para que **la carpeta exista en el
  repositorio** y sea escribible, sin que las fotos subidas se suban a Git.
- Un `try/catch` alrededor del `move()`. Si la carpeta no se puede escribir
  (permisos, disco lleno), antes salía una pantalla de error en blanco; ahora
  avisa *"No se pudo guardar la foto"* y **corta la transacción**, para que no
  quede una torta guardada sin foto.

### 7.10 Por qué se corrigieron los `.js` que se rompían en silencio

Este es el tipo de falla que no aparece en el log de PHP:

```js
const btnLimpiar = document.querySelector('.btn-limpiar');
btnLimpiar.addEventListener('click', ...);   // si no existe: TypeError
```

`historial.js` y `produccion.js` accedían a 6 elementos sin comprobar ninguno.
**Si faltaba uno solo, el script entero se cortaba** y dejaban de funcionar los
botones, los modales y el selector de empleados — sin error visible, solo en la
consola.

Se agregó null-check en cada acceso. En el bloque de empleados se cortó **todo
el bloque** si falta cualquiera de los 6 elementos, en vez de fallar a mitad.

Además, en `dashboard.js` los datos de los gráficos se leen con `try/catch`: un
JSON inválido cortaba el script entero y **los modales dejaban de abrir**,
porque viven más abajo en el mismo archivo. Ahora se muestra un aviso en
pantalla.

### 7.11 Por qué `textContent` en vez de `innerHTML`

El nombre del empleado se armaba con:

```js
tag.innerHTML = `${empleadoNombre} — ${rolNombre} <button>×</button>`;
```

Se cambió a `createTextNode` + el botón por separado. Motivo: el nombre viene
de la base, no de una fuente externa, así que el riesgo real es bajo — pero
`innerHTML` **interpreta HTML**, y un nombre con `<` lo rompe. Con
`textContent` el texto se muestra siempre como texto. Además se le agregó
`aria-label` al botón de quitar, que antes no decía qué quitaba.

### 7.12 Por qué 5 empleados y no 2

Con 2, el formulario de producción quedaba sin gente para asignar y seemed un
prototipo vacío. Con 5 hay con quién probar la asignación de maestro y
ayudante, que es la funcionalidad central del formulario.

### 7.13 Por qué `migrate:fresh --seed` se pudo correr sin riesgo

Antes de correrlo se comprobó:

- `migrate:status` → las **22 migraciones aplicadas**. Por eso no hacía falta
  crear nada: el esquema ya estaba.
- `db:seed` **corrido 3 veces seguidas** con los mismos resultados: los
  seeders son idempotentes.
- La base estaba vacía al empezar.

Recién entonces se pidió autorización. **La secuencia importa**: primero se
verificó que era seguro, después se pidió, después se corrió. Nunca al revés.

---

## 8. Bloque 5 — Pruebas en celular

**Commit:** `9594666` — *movil: documenta el acceso por IP de la red y el firewall*

### 8.1 Por qué `npm run build` y no el servidor de desarrollo de Vite

Con `npm run dev`, el HTML generado por `@vite` apunta a
`http://[::1]:5173/...`:

```html
<link rel="stylesheet" href="http://[::1]:5173/resources/css/login.css">
```

`[::1]` es loopback **IPv6**: no existe en la red Wi-Fi. El celular cargaría el
HTML y **ningún estilo**.

Con `npm run build`, los archivos se escriben en `public/build/` y el HTML
apunta a rutas relativas del propio servidor.

Se borró `public/hot` (que es lo que activa el modo desarrollo) y **el README
avisa explícitamente** que para celular hay que compilar. Es la causa número
uno de "en mi compu se ve bien, en el celular no".

### 8.2 Por qué `--host=0.0.0.0`

`php artisan serve` sin argumentos escucha en `127.0.0.1`, que solo acepta
conexiones de la **misma máquina**. Con `--host=0.0.0.0` escucha en todas las
interfaces y el celular puede entrar.

### 8.3 Por qué el firewall no se abre automáticamente

Abrir un puerto es una decisión de seguridad del sistema, no una tarea de
limpieza de código. El README dice **exactamente** qué comando correr, con qué
zona y con `--permanent`, y también cómo probar **sin** dejar el puerto abierto.

### 8.4 Verificación real, no simulada

No se dio por hecho que funcionaba: se levantó el servidor con
`--host=0.0.0.0` y se
entró por la IP de la Wi-Fi:

```
http://192.168.18.35:8013/login   → 200
POST /login (carlos.m)             → 302 → /dashboard
/dashboard                          → 200
CSS, imágenes y video               → 200
referencias al servidor de Vite     → 0
```

Es la misma ruta que va a usar el celular mañana. Lo único que no se pudo
probar fue el renderizado visual real (ver pendientes).

---

## 9. Decisiones que se descartaron

| Decisión considerada | Por qué se descartó |
|---|---|
| **Agregar columna `activo` a `usuarios_sistema`** | Fuera de alcance: no se toca el esquema. Se dejó el código listo y comentado. |
| **Filtro de estado con `empleados` o `roles`** | Habría significado inventar semántica. Que alguien sea panadero no dice si su cuenta está habilitada. |
| **Implementar el módulo de pedidos** | Fuera de alcance. Las tablas existen pero no hay requisitos definidos. |
| **AJAX con `fetch` en los filtros** | Pierde el botón "atrás", no funciona sin JS, complica el manejo de errores y no se puede probar con `curl`. |
| **Filtrar en SQL en `LineasProduccion`** | Requiere reescribir lógica que ya funciona y está verificada. El costo actual es aceptable. |
| **`Auth::attempt()` con `remember: true`** | No hay checkbox de "recordarme" en la pantalla. Agregarlo sería una función nueva. |
| **Instalar `pdo_sqlite`** | Cambiar el entorno del sistema. Se resolvió con tests sin base + verificación HTTP. |
| **Una tipografía geometric para los títulos** | Rompería el carácter redondeado de la marca. Baloo 2 es redondeada y ya estaba cargada. |
| **Un color rojo para los errores** | Está prohibido cambiar la paleta. Los errores usan `--verde-medio` o `--verde-oscuro` con icono de aviso. |
| **Fotos de mapa de bits para los placeholders** | Los SVG escalan, pesan 500 bytes y se versionan como texto. |
| **Un archivo de estilos nuevo para los errores** | Se reaprovecha `variables.css`; las páginas de error deben verse bien aunque el CSS de página no haya cargado. |
| **Borrar el CSS muerto de la barra lateral vieja** | Era inofensivo y estaba fuera del alcance pedido. Se dejó. |

---

## 10. Cómo se verificó

### 10.1 Pruebas automáticas

```
Tests: 11 passed (32 assertions)
```

Cubren: raíz con y sin sesión, las tres páginas internas sin sesión, login con
sesión previa, logout con y sin sesión, validación de campos obligatorios, el
405 original como regresión, y el HTML del login (`@csrf`, `action` correcto,
`type="password"`, `autocomplete`).

`tests/Feature/ExampleTest.php` fallaba antes de empezar (pedía 200 en `/`,
que daba 404). Se ajustó a 302.

### 10.2 Flujo HTTP real

Se simuló el flujo completo con `curl` y un *cookie jar*, que es lo más cerca
de un usuario real sin navegador:

| Prueba | Resultado |
|---|---|
| Login correcto | 302 → `/dashboard`, 200 con datos |
| Contraseña incorrecta (1–5) | "Usuario o contraseña incorrectos." |
| Contraseña incorrecta (6+) | "Demasiados intentos fallidos. Esperá 60 segundos" |
| Usuario conservado | `carlos.m` ✅ |
| **Contraseña en el HTML de vuelta** | **Nunca aparece** ✅ |
| Logout → volver a `/dashboard` | 302 → `/login` ✅ |
| Producción de pan, torta con foto, bocadito | Guardan y aparecen en el historial |
| Foto que no es imagen / sin foto | "La foto tiene que ser una imagen." / "Subí una foto de la torta." |
| Los 5 filtros del historial | Conteos correctos |
| 404 y 419 | Páginas con la paleta del sistema |
| Todos los recursos de las 6 páginas | 200, **0 referencias a Vite** |
| `migrate:fresh --seed` × 2 | Limpio, 25 tablas, mismos datos |
| `db:seed` × 3 | Idempotente |

### 10.3 Estáticos

- Ningún `localhost` ni `127.0.0.1` escrito a mano en vistas o JS.
- Ningún color fuera de `variables.css`.
- Ningún `vh` ni `vw` (salvo dentro de `clamp()`, que es intencional).
- Ninguna variable CSS usada sin definir, y ninguna definida sin usar.
- `node --check` en los 4 archivos JS.
- Los 4 SVG son XML válido.
- Cero `Undefined variable` / `null` en el HTML servido.
- `storage/logs/laravel.log`: **0 errores** tras recorrer todo el sistema.

### 10.4 Lo que NO se verificó

**No se vio ninguna pantalla pintada.** No había navegador conectado a la
sesión. La verificación fue de código y de HTTP: se sabe que el CSS se sirve,
que no hay referencias rotas, que el HTML no trae `undefined`, y que la
sintaxis del JS es válida. **No se sabe cómo se ve.** Eso es lo primero que hay
que hacer al abrir el proyecto.

---

## 11. Lo que quedó pendiente

| # | Pendiente | Por qué |
|---|---|---|
| 1 | **Revisión visual en navegador y celular** | No había navegador conectado. Es lo único que bloquea la confianza en el resultado. |
| 2 | **Filtro por `activo` en usuarios** | La columna no existe en el esquema y no se iba a tocar. Marcado en el código, a una línea. |
| 3 | Fuentes Huninn y Baloo 2 | Vienen de Google Fonts. Sin internet, se ve con la fuente del sistema. Se pueden bajar a `public/fonts`. |
| 4 | Íconos de Font Awesome | Vienen de cdnjs. Sin internet desaparecen, pero el layout no se rompe. |
| 5 | Filtros del historial colapsables | Se apilaron en móvil como se pidió; no se verificó si en 360px ocupan demasiado para un `<details>`. |
| 6 | "Pendiente"/"Completada" del dashboard en `-` | No hay columna de estado en el esquema. Es una decisión de negocio. |
| 7 | Tests que toquen la base | Esta máquina no tiene `pdo_sqlite`. Se compensó con verificación HTTP. |
| 8 | Estilo en 2 archivos y en las migraciones | Los reporta Pint, pero son preexistentes y no se tocaron. |

---

## 12. Comandos de referencia

### Puesta en marcha

```bash
composer install
cp .env.example .env
php artisan key:generate
mariadb -u root -p -e "CREATE DATABASE \`panaderia-rs\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php artisan migrate:fresh --seed
npm install
npm run build
php artisan serve
```

### Desde el celular

```bash
npm run build                                    # obligatorio
php artisan serve --host=0.0.0.0 --port=8000     # en toda la red
ip -4 addr show scope global                     # ver la IP de la Wi-Fi
```

Desde el celular: `http://192.168.18.35:8000`

Si no entra, probablemente el firewall (CachyOS):

```bash
sudo firewall-cmd --get-active-zones
sudo firewall-cmd --zone=trusted --add-port=8000/tcp --permanent
sudo firewall-cmd --reload
```

### Desarrollo

```bash
npm run dev                     # Vite con recarga (NO para celular)
php artisan serve

php artisan route:list          # ver rutas
php artisan migrate:status      # ver migraciones aplicadas
php artisan db:seed             # datos de prueba (idempotente)
php artisan test                # pruebas
./vendor/bin/pint               # formato
./vendor/bin/pint --test        # ver qué falla sin tocar nada
php artisan cache:clear         # si un cambio parece no aplicarse
```

### Si algo parece no aplicarse

```bash
rm -f public/hot                 # salir del modo desarrollo de Vite
npm run build                    # recompilar
php artisan optimize:clear       # limpiar cachés
```

Y recordar: el `opcache.revalidate_freq` es de 180 segundos. Un
`php artisan serve` ya iniciado puede servir código viejo durante 3 minutos.

### Volver atrás

```bash
git log --oneline master..prototipo-estable   # ver los 5 commits
git checkout master                           # volver al original
```

Commits, del más reciente al más antiguo:

```
9594666  movil: documenta el acceso por IP de la red y el firewall
b369585  estabilidad: filtros del historial, errores en español y datos de prueba
eada331  responsive: menu hamburguesa, una columna en móvil y tipografía de títulos
5d06ec9  rutas: agrega pedidos (Próximamente), páginas de error y borra archivos muertos
69afb84  login: agrega autenticación POST, logout y throttling
```

---

## 13. Conclusión

El sistema está **funcional y navegable**: se entra, se ve producción real, se
registra producción (incluida la foto desde la cámara), el historial filtra y
el menú funciona. El acceso desde el celular está probado a nivel de red.

Lo que **no** está verificado es el aspecto visual. La lógica y el cableado se
comprobaron de manera objetiva; que las pantallas se vean bien es lo único que
falta confirmar, y es una tarea de cinco minutos frente a una pantalla.

Las decisiones quedaron argumentadas en el código (comentarios en español en
cada parte no obvia de la autenticación, de los filtros y de los hacks
responsive) para que se puedan sustentar en una revisión sin tener que
reconstruir el razonamiento.

Las dos piezas que quedaron deliberadamente afuera —el filtro de usuarios
activos y el aspecto visual— son las únicas que requieren una decisión o una
mirada. Ninguna de las dos bloquea el uso del sistema mañana.
