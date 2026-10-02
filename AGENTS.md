# Trabajo y documentación del proyecto

La documentación original está en `/home/lukagfv/Documentos/Obsidian Vault/PROYECTO_LARAVEL/`.

Para cada tarea: analizar el código, reproducir problemas, implementar lo autorizado,
probar, revisar resultados y `git diff`, determinar las notas afectadas y actualizar
solo las necesarias. Actualizar `mejora_asignado.md` cuando corresponda.

Antes de escribir en el Vault, inspeccionar su estructura, buscar el tema y leer
íntegramente las notas que se editarán. Integrar sin borrar su historia. No reorganizar,
renombrar, duplicar ni crear carpetas arbitrarias. Crear una nota solo si no existe un
lugar lógico. Respetar los nombres reales, incluidos los que terminan en `.md.md`.

Documentar funcionalidades, decisiones, bugs, arquitectura y aprendizajes relevantes.
El estado debe reflejar este checkout y la evidencia: PENDIENTE, EN INVESTIGACIÓN,
IMPLEMENTADO, IMPLEMENTADO Y COMPROBADO o DESCARTADO. No considerar terminadas
recomendaciones, discusiones, implementaciones sin probar ni pruebas fallidas.
Explicitar el aislamiento y los límites de las verificaciones.

Usar las notas existentes de LARAVEL para responsabilidades técnicas; PERSONAL Y
USUARIOS para la visión funcional; PENDIENTES/Bugs.md para investigar y resolver el
mismo bug en una única entrada; DECISIONES/Decisiones Laravel.md.md para decisiones
con fecha, contexto, opciones, decisión, motivo y consecuencias. INICIO/Estado de
proyecto.md es un resumen rápido; INICIO/Roadmap.md ordena el desarrollo. PRODUCCION
y STOCK separan flujo, reglas, UI y requerimientos. BASE DE DATOS describe estructura;
APRENDIZAJE contiene explicaciones educativas. No afirmar que existen Requests,
Services o permisos por rol sin comprobarlo. El contrato getAuthPasswordName() sigue
pendiente hasta implementarlo y probarlo.

No escribir secretos, credenciales, hashes reales, tokens, cookies, claves ni el
contenido de .env. Usar ejemplos genéricos. Si el entorno bloquea la escritura en
el Vault, solicitar autorización por la herramienta de escalamiento y trabajar sobre
el original. No guardar una copia de la documentación en este repositorio.

Al terminar informar CÓDIGO MODIFICADO, DOCUMENTACIÓN MODIFICADA, PRUEBAS y PENDIENTES,
incluyendo rutas, motivos, resultados y lo que falta. Indicar expresamente si no
correspondió modificar documentación o si una autorización quedó pendiente.
