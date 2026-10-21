# Sprint 5 — Normalización de colores y menú móvil

Fecha: 2026-10-19. Checkout inicial limpio (`git status --short` vacío). Estado inicial: EN INVESTIGACIÓN. Estado final: IMPLEMENTADO Y COMPROBADO en aislamiento (alcance y pendientes abajo). No se modifica negocio, autenticación, permisos ni datos operativos.

## 1. Diagnóstico previo a los cambios

Inspección realizada antes de editar CSS/Blade/JS. Las entradas Vite son hojas por pantalla; `app.css` contiene Tailwind pero ninguna vista actual lo carga. `resources/layouts/app.blade.php` solo tiene una directiva Vite de Dashboard y no es el layout utilizado. Las páginas usan `<x-sidebar />`; Productos tiene layout propio y Personal comparte `personal/layout`, Usuarios importa Productos. Dashboard, Producción, Historial y Productos importan `sidebar.css`. Login y 403 son independientes. No conviene cargar Tailwind globalmente para esta tarea porque cambiaría estilos existentes.

### Inventario cromático y duplicaciones

- `dashboard.css`, `historial.css`, `productos.css`, `errores.css`: cuatro bloques `:root` con las mismas seis variables verde/beige/crema. Login tiene otro bloque con nombres distintos para esos mismos valores.
- `produccion.css`: 18 apariciones crema, 23 verde oscuro, 23 verde claro, 11 verde medio, tres beige y dos errores #7A3325. `sidebar.css` también repite colores literales.
- Rojos de error dispares: #7A3325 (Producción), #8A3030 (Historial), #7A312B (Login); Productos usa verdes incluso para errores. Historial destructivo usa #B65B4E/#F2E0DA/#793B32, y hover #C96B5D con blanco. Productos solo cambia a #C96B5D con blanco en hover/foco.
- `usuarios.css` usa un fallback `--color-dark` que solo estaba definido por Login.
- RGB con alfa: sombras/overlay Sidebar, bordes y backdrop Historial, sombra 403; RGBA negro en sombras del Login. Sin HSL/HSLA. Sin colores nominales de diseño en declaraciones: `transparent` y `currentColor` son valores funcionales que deben conservarse. La flecha SVG embebida en Producción incluye #4F6355 codificado.
- Blade no declara colores inline; sus estilos inline solo controlan visibilidad de campos de Producción. JS no define colores y Dashboard actual no implementa gráficos: muestra métricas y tabla mediante Blade.
- No se encontraron cambios de color entre media queries de móvil/escritorio. Sí hay sobrescrituras de layout legítimas en Producción (wrap, anchos mínimos y tarjetas) y estilos globales de body distintos por página. No se eliminan indiscriminadamente.

### Menú y accesibilidad

`sidebar-menu` es fixed a 16px, con padding-top:80px del body solo al activar JS. Esta compensación protege inicialmente la cabecera pero no impide tapar controles durante el scroll. El panel y overlay deben seguir fijos como diálogo; el disparador puede reservar su altura real en flujo normal. El selector JS de contenido excluye `.pagina-productos`, usado por Productos y Personal: su fondo no queda inert al abrir el diálogo. Se revisarán teclado, cierre, foco y breakpoint.

Blanco sobre rojo #C96B5D no alcanza AA para texto normal. Placeholder verde sage y texto de carga de foto requieren contraste más oscuro. Los mensajes y estados existentes tienen texto además del color.

### Assets y pantallas físicas

`public/hot` ausente en la inspección inicial; `public/build/manifest.json` presente. No se ha abierto un túnel. Las diferencias percibidas en teléfono/laptop no pueden atribuirse a CSS sin comparar pantalla, navegador y versión de assets. Los seis colores base ya son idénticos: se conservarán, sin inventar una paleta móvil distinta.

## 2. Paleta final, justificación y variables globales

Fuente única en `resources/css/tokens.css`. Responsabilidades: superficies, marca/bordes, texto/foco, estados y componentes. Los tokens no dependen del ancho. Se conserva el diseño y la paleta efectivamente encontrada; no había una segunda paleta móvil que recuperar.

| Responsabilidad / tokens | Valor final | Motivo |
| --- | --- | --- |
| Fondo `--color-bg` y campos `--color-field-bg` | #F6F2EF | Crema original; campo deriva del fondo |
| Tarjetas `--color-surface` | #DCD6C4 | Beige original |
| Marca `--color-primary` | #33403A | Verde oscuro original; también texto principal |
| Hover de marca `--color-primary-hover` | #4F6355 | Verde medio original |
| Decoración `--color-primary-soft` | #7D9481 | Sage reservado para decoración |
| Bordes `--color-border` y éxito `--color-success` | #AEC0AC | Verde claro original; éxito deriva de él |
| Texto secundario `--color-text-muted` y placeholder | #4E6254 | Ajuste mínimo del verde medio para AA sobre beige |
| Texto inverso / foco | Fondo crema / hover de marca | Alias por responsabilidad |
| Acción destructiva `--color-danger` | #C96B5D | Rojo suave solicitado, usado en hover/foco |
| Fondo destructivo `--color-danger-soft` | #F3DDDA | Unifica confirmación/feedback de error |
| Texto de error `--color-danger-text` | #7A3325 | Conserva el rojo oscuro de Producción, legible sobre fondos claros |
| Texto sobre rojo sólido `--color-danger-on-solid` | #211512 | Evita blanco de contraste insuficiente |
| Pestañas | Superficie / primary / success | Alias compartidos para Producción y Personal |
| Overlay / backdrop / sombra | RGB de verde oscuro al 35% / 45% / 10% | Conserva las transparencias existentes |

No se añadieron tokens sin consumidores: se omitieron surface-soft, border-soft y warning-soft de la referencia inicial porque no hay componentes que los necesiten. Tampoco se aplica #B85A4D como hover con texto oscuro: el rojo original #C96B5D permite el contraste buscado y mantiene el diseño existente.

Excepciones justificadas: blanco del título sobre video y RGBA negros de sombras del Login permanecen específicos de esa composición. La flecha del select de Producción conserva #4F6355 codificado dentro de su data URI SVG: una imagen embebida no hereda variables del documento; es decorativa y sigue coincidiendo con el verde de marca. Bordes traslúcidos de Historial y sombra 403 derivan de tokens mediante `color-mix`, con sus porcentajes originales, sin crear un token por porcentaje.

### Carga mediante Vite

`sidebar.css` importa tokens; Dashboard/Producción/Historial/Productos ya importaban Sidebar y Usuarios importa Productos. Login y 403 importan tokens directamente; `app.css` también, para que su entrada existente sea coherente si se utiliza. Cada entrada compilada que los consume contiene una sola definición de `--color-bg`; se quitaron los cinco bloques locales repetidos. No se añade una entrada Vite, una directiva Blade extra ni una importación de Tailwind a las vistas. Los bundles de páginas distintas contienen los tokens necesarios para poder cargarse independientemente.

## 3. Archivos modificados

| Ruta | Cambio |
| --- | --- |
| `resources/css/tokens.css` | Paleta semántica, esquema claro, foco/placeholder y movimiento reducido compartidos |
| `resources/css/app.css` | Import del archivo común por la entrada global existente |
| `resources/css/sidebar.css` | Tokens, botón en flujo y visibilidad inmediata al abrir |
| `resources/css/dashboard.css` | Sustituir paleta local por tokens, sin redistribuir métricas/tabla |
| `resources/css/produccion.css` | Literales por tokens, error común, texto legible de foto y pestañas compartidas |
| `resources/css/historial.css` | Tokens, mensajes/acciones destructivas y transparencias derivadas |
| `resources/css/productos.css` | Tokens, errores/éxito y contraste de Desactivar |
| `resources/css/usuarios.css` | Tokens, pestañas compartidas y eliminación de fallback definido solo por Login |
| `resources/css/login.css` | Tokens, placeholder/foco legibles, toggle con área mínima de 44×44 px |
| `resources/css/errores.css` | 403 comparte paleta sin cambiar su estructura |
| `resources/js/sidebar.js` | Incluir `.pagina-productos` en inert y enfocar tras pintar el panel, con cancelación si se cierra |
| `tests/JavaScript/SidebarTest.mjs` | Dobles que reconocen clases y regresiones de inert y cierre antes del frame de foco |
| Este informe | Diagnóstico anterior a los cambios y resultados finales |

Sin cambios en Blade, controladores, autenticación, permisos, migraciones, Vite config ni dependencias. El build generado permanece ignorado por Git. No hubo cambios iniciales ajenos que sobrescribir.

## 4. Corrección del menú móvil

Hasta 767 px el disparador pasa de fixed a static, con ancho de contenido y separación normal de 24 px (la escala de espaciado existente). Se elimina el padding-top:80px condicional del body. La altura real del botón reserva espacio incluso si cambia la fuente o el texto: esa separación es un espacio entre componentes, no una compensación calculada para un elemento superpuesto. No se añaden márgenes a páginas individuales. El botón conserva verde, radio de 16 px y altura mínima de 48 px. Al desplazarse sale con el contenido; se accede regresando al inicio, sin una capa flotante sobre filtros o formularios.

Solo panel y overlay siguen fijos: representan el diálogo modal y deben cubrir el viewport, con scroll interno para navegación y max-width adaptativo. Se conserva la navegación de escritorio de 76/244 px desde 768 px. Sin JS el panel sigue visible en flujo.

La prueba real de teclado detectó un problema previo adicional: la transición `visibility` al abrir podía impedir el `cerrar.focus()` síncrono. El estado abierto ahora anima solo transform, haciendo visible el panel inmediatamente. Se conserva el cierre animado y se respeta movimiento reducido. El foco inicial se aplica después de pintar el panel (dos requestAnimationFrame), con preventScroll y comprobación de que siga abierto; un frame único aún fallaba con movimiento reducido en Chrome. Las pruebas finales comprueban el resultado sin forzar el foco manualmente. El JS conserva ARIA, Escape, ciclo Tab, retorno de foco y breakpoint; el selector ampliado también deja inert a Productos y Personal.

## 5. Diferencias visuales antes/después

| Comprobación | Antes | Después |
| --- | --- | --- |
| Producción a 320 px, inicio | Menú fixed y=16–64; contenido y=80 | Menú static y=24–72; contenido y=96 |
| Tras scroll de 200 px | Menú permanece hasta y=64 sobre contenido desplazado | Menú termina en y=-128 y no cubre controles |
| Producción a 1280 px | Contenido y=24, menú móvil oculto | Mismos valores |
| Fondo y texto de páginas de operación | rgb(246,242,239) / rgb(51,64,58) | Idénticos en los seis anchos |
| Textos secundarios sobre beige | #4F6355, 4,45:1 | #4E6254, aproximadamente 4,52:1 |
| Desactivar/Eliminar en hover | Blanco sobre #C96B5D, 3,66:1 | #211512 sobre #C96B5D, 4,87:1 |
| Errores/éxito | Rojos distintos o error verde | Tokens comunes y mensajes/estados textuales existentes |

Se conservaron tarjetas, formularios, campos, tabs, filtros y orden de información. Capturas antes/después en `/tmp/panaderia-colores/before/` y `/tmp/panaderia-colores/after/`, con imágenes a 320, 390 y 1280 px; artefactos temporales, no un despliegue. Los JSON de resultados incluyen los seis anchos. Las capturas se toman tras estabilizar animaciones; las mediciones/interacciones principales se hicieron con movimiento normal.

Capturas de referencia: [Producción antes](/tmp/panaderia-colores/before/produccion-pan-320.png), [Producción después](/tmp/panaderia-colores/after/produccion-pan-320.png), [Historial después](/tmp/panaderia-colores/after/history-390.png), [confirmación](/tmp/panaderia-colores/after/confirmacion-390.png).

## 6. Vite y Cloudflare

- `npm run build` aprobado; se generaron manifest y assets versionados. Permanece el aviso opcional preexistente de fontaine; no requiere instalarlo para compilar.
- `public/hot` ausente tanto al inicio como tras compilar. No se eliminó ni se editó. El Vite instalado en `vendor/laravel/framework/src/Illuminate/Foundation/Vite.php` comprueba hot antes del manifest: si ese archivo aparece, `@vite` usará su servidor aunque exista build.
- HTML renderizado por Blade/`@vite` con host ficticio usa `/build/assets/...`; sin `@vite/client`, localhost, 127.0.0.1 ni puerto 5173 en URLs de assets. Chrome consume esos mismos archivos compilados mediante interceptación local. Sin tokens CSS indefinidos ni definiciones de paleta duplicadas por entrada.
- La suite completa incluye `NavigationTest` y `ProxyHttpsTest`, que verifican navegación/proxy HTTPS en aislamiento. Sus pruebas HTTP usan `withoutVite`: no acreditan carga externa de assets, comprobada aquí por separado a nivel local.
- Se conserva el flujo solicitado `npm run build` → `php artisan serve --host=127.0.0.1 --port=8000` → `cloudflared tunnel --url http://127.0.0.1:8000`. Antes de probar externamente, verificar hot ausente y recargar el teléfono para confirmar que consume los nombres de assets del manifest actual. No se ejecutó un túnel ni se certifica un teléfono físico. No se cambió .env, URL pública, credenciales o configuración sensible.

## 7. Pruebas ejecutadas y resultados

1. `npm run build`: aprobado con el aviso opcional descrito. Se repitió al finalizar los ajustes CSS para dejar assets actuales.
2. `node --test --test-isolation=none tests/JavaScript/*.mjs`: **57 aprobadas, 0 fallidas/omitidas** (incluye regresiones nuevas de aislamiento y foco pendiente del sidebar). El primer intento tras ampliar el selector falló porque el doble DOM reconocía solo la cadena antigua; se adaptó el doble a clases y se añadió el caso Productos/Personal. No se eliminaron expectativas previas.
3. `PAO_DISABLE=1 php tests/run-mariadb.php --compact --do-not-cache-result`: **1.019 aprobadas, 9.158 aserciones, 0 fallidas/omitidas**, 74,80 s. Incluye toda la regresión relevante de navegación, Login, Dashboard, Historial, Productos, Personal y Producción. Aviso de deprecación de `--do-not-cache-result`; no es un fallo. El primer intento en sandbox no pudo abrir el socket; se repitió con autorización fuera del sandbox. Instancia `/tmp/sprint4-2703daf0212f`, detenida por el runner; datos/logs ficticios conservados.
4. Chrome headless con viewport/tacto emulado: **66/66 antes y 66/66 después**, 11 vistas × 320/375/390/768/1280/1440 px; cero overflow de documento y cero excepciones JS. Vistas: Dashboard, Producción Pan/Torta/Bocadito, Historial con detalles, Productos, Usuarios, Empleados, formularios de Usuario/Empleado y Login. Menú: separación de cabecera, dimensiones, ARIA, inert, cierre por botón/overlay/Escape y ciclo Tab/Shift+Tab en las páginas móviles; confirmación Historial abre/cierra sin enviar eliminación. El primer verificador simulaba mal Shift y seleccionaba el form en lugar del botón del diálogo; corregido. La comprobación posterior de foco sí expuso la transición de visibilidad real y motivó el ajuste descrito.
5. Comprobaciones adicionales de Chrome: fallback sin JS en seis páginas a 320/768 px (**12/12**); abrir mediante Enter y tap, cambio a escritorio liberando inert/foco, movimiento reducido y colores calculados de peligro. Capturas estables adicionales de menú abierto y confirmación. La primera comprobación con movimiento reducido falló en el foco inicial; se corrigió el momento de enfoque y se repitió, sin ocultar esa condición.
6. `git diff --check`: aprobado. Revisión de todos los diffs de código y de la carga/imports; sin cambios de negocio ni archivos sensibles.
7. Comprobación estática: ninguna referencia de token sin definición y ningún token sin consumidores. Cálculos de contraste: oscuro/crema 9,75:1, oscuro/verde claro 5,65:1, texto error/rojo claro 6,94:1; el contraste no depende del viewport.

### Aislamiento y alcance

MariaDB crea un datadir propio bajo /tmp y arranca con `--skip-networking`; `IsolatedMariaDb` verifica socket, datadir y nombre de BD antes de migrar. Se usaron únicamente fixtures y sesiones de prueba; ninguna prueba destructiva se ejecutó contra la base operativa.

Chrome usa Blade/modelos reales con fixtures en memoria y sesión/cache array. El renderizador temporal fuerza una conexión de base vacía ajena a la operativa para que una consulta accidental falle. Navegación y recursos se interceptan; no hay POST, red externa, credenciales reales ni acceso E2E Chrome→BD. Fuentes remotas, Font Awesome externo y video remoto/local no cargado en la matriz quedan fuera: las medidas usan fuentes fallback, especialmente en Login. Los scripts y capturas residen exclusivamente en /tmp, y Puppeteer/Chrome ya estaban instalados fuera del proyecto; no se añadieron dependencias.

## 8. Documentación y pendientes

Notas pertinentes del Vault: `DISEÑO/SISTEMA DE DISEÑO/Paleta de colores.md`, `DISEÑO/SISTEMA DE DISEÑO/Componentes UI.md`, `LARAVEL/Blade.md` y `PENDIENTES/Bugs.md`. Se inspeccionó estructura, se buscó el tema y se leyeron completas antes de preparar adiciones, conservando historia. Actualizadas las cuatro notas originales con escalamiento autorizado porque el Vault está fuera del área de escritura; se verificó la incorporación y se conservó íntegra su historia. No se guarda copia del Vault en este repositorio.

`mejora_asignado.md` no existe en este checkout ni se encontró en Documentos; no se crea un duplicado de seguimiento sin un original lógico.

PENDIENTE: aceptación visual del usuario con Huninn/Quicksand originales y video/CDN del Login; teléfono físico/Safari, pantalla/gestión del color, lector de pantalla, zoom y sesión operativa mediante Cloudflare con assets actuales. No se afirma una auditoría WCAG completa: las combinaciones de texto revisadas alcanzan AA, pero no se certifican video dinámico, estados disabled exentos de contraste ni bordes decorativos. El contrato getAuthPasswordName() sigue pendiente, fuera del alcance de esta tarea. Sin commit, push, despliegue o túnel automático.
