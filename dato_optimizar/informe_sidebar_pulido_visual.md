# Informe - Sidebar y pulido visual

Fecha: 2026-10-09. Proyecto: `panaderia-rs`.

Estado: **IMPLEMENTADO Y COMPROBADO mediante HTTP, MariaDB aislada, eventos JavaScript y build**. Revisión visual real en navegador: **PENDIENTE**. Sin commit ni push; sin operaciones sobre datos de trabajo.

## 1. Estado inicial

El componente compartido era un menú horizontal provisional en el flujo de la página: nombre de usuario, tres enlaces y logout. No tenía iconos, estados compacto/expandido ni adaptación como panel móvil. Ya resolvía correctamente rutas activas, `aria-current`, empleado/username y logout POST con CSRF.

Dashboard e Historial cargaban Huninn por separado; Producción declaraba la familia sin importarla en su hoja. Cada hoja importaba `sidebar.css`. Dashboard tenía padding interno y Producción/Historial padding de body de 40px. El componente cargaba `app.js`, que revalida páginas restauradas desde BFCache. Font Awesome existe únicamente en login mediante CDN, sin sistema común de iconos en las páginas protegidas.

Se inspeccionaron componente, tres vistas/hojas, `app.js`, rutas, Vite, package.json, NavigationTest y DashboardTest. El checkout estaba limpio al iniciar.

## 2. Objetivo

Menú lateral flotante compacto con iconos, expansión por hover/foco sin mover el contenido, navegación móvil accesible y presentación coherente. Conservar los módulos funcionales y sus contratos, sin nuevas rutas, dependencias o funciones de negocio.

## 3. Archivos modificados

| Ruta | Cambio | Motivo |
|---|---|---|
| `resources/views/components/sidebar.blade.php` | SVG, textos, perfil, botones móviles, panel identificado y overlay; conserva enlaces/POST | Navegación compartida accesible |
| `resources/css/sidebar.css` | Sidebar fijo/expandible, panel móvil, fallback, foco y espacio común; importa Huninn | Centralizar presentación y evitar superposición del contenido |
| `resources/js/sidebar.js` | Nuevo módulo de eventos, estado móvil y foco | Interacción progresiva sin dependencias |
| `resources/js/app.js` | Solo importa `sidebar.js` | Reutilizar entrada Vite; conservar íntegro el manejador BFCache |
| `resources/views/dashboard/index.blade.php` | Clase `pagina-con-sidebar` en body | Aplicar espacio común |
| `resources/views/produccion/index.blade.php` | Misma clase en body | Aplicar espacio común |
| `resources/views/history/index.blade.php` | Misma clase en body | Aplicar espacio común |
| `resources/css/dashboard.css` | Retira padding interno duplicado e importación propia de Huninn | Consistencia con el espacio común |
| `resources/css/produccion.css` | Retira padding de body; mínimos de ancho, campos y adaptación de columnas en móvil | Mantener el formulario usable con el sidebar |
| `resources/css/historial.css` | Retira padding/importación duplicados; normaliza margen superior del título | Integración con el espacio común |
| `tests/Feature/NavigationTest.php` | Amplía tres casos existentes con iconos, controles, IDs y fallback sin JS; adapta texto del botón | Comprobar estructura y contratos sin probar diseño exacto |
| `tests/JavaScript/SidebarTest.mjs` | Siete casos con Node y dobles mínimos de DOM | Verificar eventos y retorno/ciclo del foco sin librerías |
| `mejora_asignado.md` | Añade seguimiento y evidencia de esta entrega | Preservar historia del proyecto |
| `dato_optimizar/informe_sidebar_pulido_visual.md` | Nuevo informe | Documentar implementación y límites |

Notas originales actualizadas en el Vault: `LARAVEL/Blade.md`, `DISEÑO/SISTEMA DE DISEÑO/Componentes UI.md`, `DECISIONES/Decisiones de diseño.md`, `INICIO/Estado de proyecto.md` e `INICIO/Roadmap.md`. Se inspeccionaron estructura/tema y se leyeron íntegramente antes de editar los originales, conservando su historia. La escritura fuera del workspace fue autorizada mediante escalamiento según AGENTS.md; no quedan autorizaciones pendientes.

## 4. Sidebar escritorio

A partir de 768px: fijo a la izquierda, separado 16px del borde superior/lateral, altura `100dvh - 32px` con fallback `100vh`, radio 24px y sombra sutil del propio verde. Ancho compacto 76px; expandido 244px. Avatar arriba, navegación central y logout separado abajo; admite desplazamiento vertical en viewports bajos.

`:focus-within` expande al navegar con teclado. En dispositivos con hover, `:hover` expande; se evita depender del hover táctil. Las etiquetas aparecen por opacidad sin eliminarlas del árbol accesible. Incluye Dashboard, Producción, Historial y Cerrar sesión. La expansión se superpone sobre una parte del contenido: el espacio reservado permanece fijo y no produce salto del layout.

Activo: fondo verde claro, texto verde oscuro y `aria-current="page"`. Hover: verde oscuro-medio. Transiciones de 120/160ms, anuladas con `prefers-reduced-motion`.

## 5. Sidebar móvil

Hasta 767px, con JS inicializado: botón flotante «Menú», panel lateral de hasta 280px (limitado al ancho disponible), separado 12px de los bordes y overlay verde translúcido. Botón de cerrar dentro del panel. Puede cerrarse con ese botón, overlay, selección de enlace o Escape.

Abrir actualiza `aria-expanded`, activa `role="dialog"`/`aria-modal`, libera `inert` del panel, vuelve inerte el contenido principal y enfoca Cerrar. Tab/Shift+Tab recorren los controles del panel. Cerrar restaura contenido y foco al botón Menú. Cambiar breakpoint libera el estado modal y mantiene el foco en un control disponible. `pagehide` limpia el estado visual.

No se modifica overflow de body ni se bloquea permanentemente el scroll. El panel tiene scroll propio y `overscroll-behavior: contain`; el overlay evita gestos táctiles sobre el fondo. Sin JS, el menú completo queda visible en el flujo normal, con enlaces y logout utilizables; los controles móviles permanecen ocultos.

## 6. Usuario

Se conserva `auth()->user()?->empleado?->nombre_empleados`, con fallback a `username` y después «Usuario». No se añaden consultas manuales, fotos, roles o cargos. Compacto muestra un icono de perfil; expandido muestra etiqueta Usuario y nombre escapado, con ajuste para nombres largos.

## 7. Navegación

Solo tres enlaces reales: `route('dashboard')`, `route('produccion.index')` y `route('history.index')`. El activo sigue usando `request()->routeIs('dashboard')`, `produccion.*` y `history.*` desde Blade.

Cerrar sesión conserva formulario independiente `method="POST"`, `route('logout')`, `@csrf` y botón submit. No se añadió GET logout, rutas nuevas ni enlaces ficticios. Los tests existentes conservan cobertura de login, invitados, sesiones, CSRF y forms no anidados.

## 8. Iconos

SVG inline simples: perfil, cuadrícula, pan, historial, salida, menú y cierre. Tamaño común 24px, trazo `currentColor`, sin IDs internos ni recursos externos. `aria-hidden="true"` y `focusable="false"`: el texto/etiqueta del control aporta el nombre accesible. No se amplía la dependencia CDN del login ni se instala otra librería.

## 9. Accesibilidad

- Enlaces para navegación y botones para acciones; logout mantiene POST/CSRF.
- Expansión de escritorio por foco, con contornos `:focus-visible` visibles.
- Etiquetas legibles al expandir y accesibles también en compacto; no se usan tooltips como único nombre.
- `aria-current` en el enlace activo; controles móviles apuntan al único `sidebar-panel` mediante `aria-controls`.
- `aria-expanded`, estado modal e `inert` sincronizados con el panel móvil; Escape, ciclo de Tab y retorno del foco.
- Movimiento reducido respetado; controles de 48/52px, panel desplazable.

Los pares de texto/fondo del sidebar se revisaron mediante cálculo de luminancia sRGB: crema sobre verde muy oscuro 9,75:1, crema sobre hover 5,81:1 y texto oscuro sobre verde claro activo 5,65:1. La etiqueta verde clara sobre fondo oscuro tiene también 5,65:1. No equivale a auditar un navegador o lector de pantalla. La aceptación visual y con tecnología asistiva sigue pendiente.

## 10. Integración con las páginas

Las tres vistas reciben únicamente una clase en body. El espacio común es 24px arriba, 32px a la derecha, 48px abajo y 108px a la izquierda en escritorio. Sus contenedores se mantienen centrados en el área restante con los máximos existentes (Dashboard 1120px, Producción 1200px, Historial 700px).

Dashboard conserva tarjetas, métricas, tabla y accesos. Producción conserva formulario, registros, campos y JS; solo ajusta límites de ancho, wrap y una columna en móvil. Historial conserva filtros, tarjetas y paginación; únicamente integra padding y margen del título. Fondo crema y Huninn permanecen. La importación de Huninn se comparte desde `sidebar.css` para estas páginas.

## 11. Responsive

- **768px o más:** sidebar fijo compacto, expandible por foco y hover cuando existe; contenido reserva 108px a la izquierda.
- **767px o menos con JS:** botón/panel móvil, contenido a ancho normal con márgenes de 16px y 80px de separación superior para el botón.
- **767px o menos sin JS:** menú completo en flujo; body con 24px superiores y 16px laterales.
- El breakpoint existente del Dashboard a 600px para accesos rápidos permanece. Producción pasa a una columna en móvil sin alterar campos o reglas.

## 12. JavaScript

`sidebar.js` se importa desde la entrada existente `app.js`, sin cambiar Vite. Detecta el componente, registra eventos, sincroniza atributos/clases, gestiona foco y breakpoint mediante `matchMedia`, y limpia el estado al salir. Una página sin componente no se modifica.

No consulta BD, no detecta rutas activas por URL, no altera autenticación/sesión, filtros, métricas, cantidades o participantes. No añade framework, paquetes ni solicitudes. El manejador existente `pageshow` de BFCache permanece íntegro: una restauración sigue ocultando el body y recargando para revalidar acceso.

## 13. Tests

`NavigationTest`: amplía los tres renderizados protegidos existentes, sin duplicar pruebas de autenticación. Comprueba tres enlaces exactos, etiquetas/iconos, activo, nombre de empleado, fallback username, logout POST/CSRF, ausencia de forms anidados, panel único, controles accesibles y disponibilidad del HTML sin JS.

`SidebarTest.mjs`: siete casos de escritorio, apertura móvil/ARIA, cierres, Escape/Tab, breakpoint/foco, pagehide y ausencia del componente. Ejecutan el módulo real contra dobles mínimos del DOM: comprueban eventos/estado, no CSS, geometría, navegador real ni lector de pantalla.

| Comando | Resultado exacto |
|---|---|
| `php tests/run-mariadb.php --filter=NavigationTest` | 20 aprobadas, 313 aserciones, salida 0 |
| `php tests/run-mariadb.php --filter='DashboardTest\|HistorialProduccionTest\|ProduccionPanStoreTest\|NavigationTest'` | 161 aprobadas, 1.716 aserciones, salida 0 |
| `php tests/run-mariadb.php --do-not-cache-result` | 216 aprobadas, 2.271 aserciones, sin omisiones, salida 0 |
| `vendor/bin/pint --test tests/Feature/NavigationTest.php resources/views/components/sidebar.blade.php resources/views/dashboard/index.blade.php resources/views/produccion/index.blade.php resources/views/history/index.blade.php` | Aprobado, salida 0 |
| `node --check resources/js/sidebar.js` | Sintaxis válida, salida 0 |
| `node --test tests/JavaScript/SidebarTest.mjs` | Archivo aprobado, salida 0; el reporter por defecto agrupa el archivo |
| `node --test --test-reporter=spec --test-isolation=none tests/JavaScript/SidebarTest.mjs` | 7 casos aprobados, 0 fallidos/omitidos, salida 0 |
| `git diff --check` | Sin errores, salida 0 |

MariaDB usa servidor/socket y bases desechables del runner, sin operaciones sobre la base de trabajo. Instancia final detenida; artefactos: `/tmp/sprint4-18e8f39ec588`. Tests HTTP con sesiones array y Vite omitido; assets comprobados mediante build separado. No se inspeccionó `.env` real. No se utilizó navegador: la revisión conceptual se basa en código, DOM renderizado y pruebas de eventos.

## 14. Build

`npm run build`: aprobado, salida 0; Vite 8.3.0, 12 módulos transformados, compilación reportada de 549ms. Manifiesto, CSS y entrada JS generados correctamente; archivos de build ignorados por Git.

El plugin de fuentes avisa sobre `fontaine` opcional para fallbacks optimizados. No bloquea el build; no se instala ni se cambia esa configuración fuera de alcance. Sin cambios en package.json, lockfile o vite.config.js.

## 15. Archivos funcionales no modificados

DashboardController, ProduccionController, HistorialController, Actions, FormRequests, modelos/pivotes, migraciones/seeders, consultas, rutas, login/logout, middleware y configuración de timezone permanecen intactos. No se cambian métricas, filtros, roles Maestro/Ayudante, cálculo coches/latas ni datos almacenados. `produccion.js` y `historial.js` no se modifican. En `app.js`, solo se agrega el import del módulo visual.

## 16. Pendientes

- **PENDIENTE:** aceptación visual real en escritorio/tablet/móvil y comprobación con lector de pantalla, siguiendo la checklist. No se afirma esa validación a partir de PHP/Node.
- Un layout Blade maestro podría reducir repetición si futuras pantallas lo justifican; no se crea en esta tarea.
- No hay pendientes funcionales identificados en la navegación solicitada. Sin commit ni push.

## 17. Revisión manual recomendada

- [ ] Escritorio: login → Dashboard; sidebar compacto separado de bordes y contenido sin quedar debajo.
- [ ] Hover y teclado: expandir, leer usuario y todas las etiquetas, incluido Historial; comprobar foco visible y regreso a compacto.
- [ ] Navegación: Dashboard → Producción → Historial; activo correcto, datos/formulario/filtros usables.
- [ ] Tablet: comprobar ancho 768px y cambio a 767px, orientación y contenido sin desbordamiento.
- [ ] Móvil: abrir Menú, recorrer enlaces, cerrar por botón/overlay/Escape; comprobar retorno de foco y panel desplazable.
- [ ] Teclado: Tab/Shift+Tab dentro del panel; al cerrarlo recuperar acceso al contenido. Probar zoom y movimiento reducido.
- [ ] Sin JS: menú móvil completo visible, enlaces y logout disponibles.
- [ ] Logout: botón Cerrar sesión; regresar a login y comprobar que Atrás no permite consultar páginas protegidas.
