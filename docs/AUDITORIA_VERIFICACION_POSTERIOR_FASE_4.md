# Auditoría: verificación posterior a Fase 4

**Fecha:** 3 de noviembre de 2026.  
**Alcance:** revisión de archivos, manifiesto Vite y disponibilidad local; sin iniciar servidores, túneles ni navegar al enlace real. Esta auditoría es el único archivo creado durante la verificación.

## Estado del checkout

`git status --short` mostró:

```text
 M resources/js/indicadores-carga.js
?? docs/AUDITORIA_FASE_4_NAVEGACION_SIDEBAR_MOVIL.md
?? docs/SEMINARIO_FASE_3_NAVEGACION_SIDEBAR_MOVIL.md
?? tests/JavaScript/IndicadoresNavegacionTest.mjs
```

Estos cambios ya existían al comenzar esta verificación. Se añade esta auditoría como archivo sin seguimiento. `public/build/` está excluido por `.gitignore`; `git status --short --ignored public/build` lo identifica con `!!`. Contiene el manifiesto, el manifiesto de fuentes y los recursos generados por el build de la Fase 4. No se recompiló en esta verificación. `git diff --check` pasó.

## Corrección y build

El código fuente de `resources/js/indicadores-carga.js` contiene el contador de navegación y los callbacks de aborto/cancelación asociados a su identidad; mantiene restauración en `pageshow` y `pagehide`. El módulo compilado compartido es:

```text
public/build/assets/indicadores-carga-D1OSxlPX.js
SHA-256 3207593ade9a4c4e80a56a2746fef07a28834da391e94e9664395e4d6395a4d8
```

Se inspeccionó su contenido minificado: contiene la comparación de identidad y el callback asociado a la señal, y no contiene listeners de restauración global para `navigateerror` o `navigatesuccess`. El manifiesto vincula `resources/js/app.js` con `assets/app-BY-bR2Fv.js`, que importa ese módulo compartido; Historial también lo importa. Se comprobó que las referencias a archivos e importaciones del manifiesto existen. Ningún archivo en `resources/js`, `resources/css`, `vite.config.js`, `package.json` o `package-lock.json` tenía fecha posterior a la del manifiesto. Esto y la inspección del contenido respaldan que el build local incluye esta corrección; las fechas no prueban identidad completa byte a byte con todos los insumos ni verifican lo servido remotamente.

## Carga mediante Laravel y posibilidades de versión antigua

Las tres vistas incluyen el componente sidebar, que ejecuta `@vite('resources/js/app.js')`. Las vistas también declaran sus propios CSS y módulos con `@vite`. El Vite de Laravel inspeccionado prioriza `public/hot` si existe; en esta revisión ese archivo está ausente, por lo que, para este checkout y con los defaults revisados, selecciona `public/build/manifest.json`. No se identificaron overrides de la ruta hot o del build en código de la aplicación revisado. El manifiesto se conserva en memoria estática en la implementación de Vite, lo que también puede importar en procesos persistentes.

Esto no demuestra que el enlace público use este checkout ni estos archivos. Un origen distinto, un proceso persistente con manifiesto antiguo, un `public/hot` que aparezca después, cachés del navegador o intermediarios, un service worker instalado anteriormente, o archivos estáticos servidos desde otra ruta pueden dar una versión distinta. No se identificó registro de service worker en el código de recursos revisado; no se inspeccionó el almacenamiento del navegador móvil. El middleware protegido establece `no-store` para páginas con auth/guest, pero no certifica las cabeceras ni las cachés de los assets estáticos o del túnel.

## Disponibilidad observada

La consulta de puertos inicialmente fue bloqueada por el sandbox; `ss -ltn` con autorización mostró que no había escucha en los puertos habituales 80, 443, 8000, 8080 o 5173. La lista de nombres de procesos del host no mostró `cloudflared` ni procesos PHP/servidores web habituales. Había dos puertos loopback de otros servicios cuya identidad no se investigó. Es una instantánea de esta máquina, no prueba el estado de una aplicación servida desde otro host, contenedor o servicio externo. No se conocía ni se consultó el enlace real del túnel.

**Conclusión:** no se puede asegurar que las pruebas móviles funcionen ahora con este checkout: no se observó un origen Laravel ni cloudflared activos en esta máquina. Si el enlace existente corresponde a otro origen, su disponibilidad y versión siguen sin verificarse. No se inició ni cambió ningún servidor o túnel.

## Pasos concretos de preparación y comprobación

1. En este checkout, comprobar solo lectura:

   ```bash
   git status --short
   test ! -e public/hot && printf 'public/hot ausente\n'
   sha256sum public/build/assets/indicadores-carga-D1OSxlPX.js
   ss -ltn
   pgrep -x cloudflared
   ```

   La ausencia de salida de `pgrep` significa que no se encontró ese proceso en la máquina consultada. Si el origen de pruebas habitual está detenido, debe volver a estar disponible junto con el túnel habitual antes de usar el enlace; iniciar procesos cambia su estado operativo y no se realizó en esta auditoría. No se requiere regenerar el build a partir de la evidencia local actual.

2. Conectar el celular al entorno de pruebas aislado ya habilitado y abrir una página con sidebar. En las herramientas de desarrollo remotas del navegador, abrir **Red/Network**, recargar y verificar que se descargó correctamente `app-BY-bR2Fv.js` y su importación `indicadores-carga-D1OSxlPX.js`, sin solicitudes fallidas. El nombre corresponde a este build y cambiará si se vuelve a compilar con contenido diferente. Un archivo en la lista de red por sí solo no prueba que se haya ejecutado correctamente; revisar también errores de consola y, si está disponible, `performance.getEntriesByType('resource')`.

3. Para comparar el contenido realmente entregado por el enlace, desde una terminal con acceso a ese mismo enlace sustituir el marcador por el origen de pruebas, sin añadir cookies ni credenciales al comando:

   ```bash
   curl -fsS 'https://<ORIGEN_DE_PRUEBA>/build/assets/indicadores-carga-D1OSxlPX.js' | sha256sum
   ```

   El resultado debe coincidir con el SHA-256 local indicado arriba. Si el acceso al recurso requiere autenticación adicional, devuelve un error o hay otra ruta pública, usar las herramientas de red autorizadas del entorno y comparar el cuerpo del recurso sin compartir credenciales. Si la ruta o el hash difieren, investigar origen y caché antes de evaluar la corrección. Esta comparación verifica los bytes recibidos por esa solicitud; confirmar por separado que el celular solicitó y ejecutó ese recurso.

4. En el celular, ensayar navegación entre pantallas y Atrás/Adelante; si se comprueba el guardado, usar exclusivamente datos ficticios en un entorno aislado. Medir tiempos antes de atribuir lentitud. El sidebar no fue corregido en Fase 4 y su salto permanece EN INVESTIGACIÓN.

## Límites

No se consultaron datos reales, `.env`, configuración efectiva del túnel o URLs públicas; no se ejecutó una petición real ni se cambió servidor, Cloudflare o base de datos. No se certifica el navegador móvil, las cabeceras reales del recurso, el guardado real ni la latencia. No se hicieron migraciones, commits ni push.
