# Panificadora Amazónica — Sistema de Producción

Sistema web de control de producción de una panadería: registro de producción
de pan, tortas y bocaditos, historial de producción y dashboard.

- Laravel 13 · PHP 8.5 · MariaDB · Vite
- Blade con CSS y JS propios, sin frameworks CSS
- Autenticación propia sobre `App\Models\UsuarioSistema` (tabla `usuarios_sistema`)

> Documentación con el detalle de cada decisión técnica (por qué se hizo lo que
> se hizo, qué alternativas se descartaron y qué quedó pendiente):
> [`DOCUMENTACION-TECNICA.md`](DOCUMENTACION-TECNICA.md)

---

## 1. Requisitos

| Herramienta | Versión |
|---|---|
| PHP | 8.3 o superior, con `pdo_mysql` |
| Composer | 2.x |
| Node.js | 20 o superior (para compilar los estilos) |
| MariaDB / MySQL | 10.6 o superior |

## 2. Puesta en marcha (desde cero)

```bash
# 1. Dependencias de PHP
composer install

# 2. Archivo de entorno
cp .env.example .env
php artisan key:generate

# 3. Base de datos: crearla una sola vez
mariadb -u root -p -e "CREATE DATABASE \`panaderia-rs\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

En `.env` revisar `DB_USERNAME` y `DB_PASSWORD`.

```bash
# 4. Tablas + datos de ejemplo
php artisan migrate:fresh --seed

# 5. Estilos y scripts compilados
npm install
npm run build

# 6. Levantar el sistema
php artisan serve
```

Abrir <http://localhost:8000>.

### Usuario de prueba

| Usuario | Contraseña |
|---|---|
| `carlos.m` | `password123` |

`db:seed` se puede volver a correr las veces que haga falta: los seeders usan
`firstOrCreate()` y `ProduccionSeeder` corta si ya hay producción, así que no
duplica nada.

### Migraciones incrementally

```bash
php artisan migrate --seed   # aplica solo lo que falta
php artisan migrate:status   # ver qué se aplicó
```

## 3. Para desarrollar con recarga de estilos

```bash
npm run dev     # servidor de Vite con recarga automática
php artisan serve
```

> **Importante:** con `npm run dev` corriendo, las páginas cargan los estilos
> desde el servidor de Vite (`http://localhost:5173`). Eso **no funciona desde
> un celular** ni desde otra máquina de la red. Para probar en el celular hay
> que usar `npm run build` (ver sección 5).

## 4. Comandos útiles

```bash
php artisan route:list      # ver las rutas y sus nombres
php artisan test            # pruebas
./vendor/bin/pint           # formato de código PHP
npm run build               # recompilar estilos
```

## 5. Probar desde un celular

El celular tiene que estar en la **misma red Wi-Fi** que la laptop, y la
laptop tiene que estar conectada por Wi-Fi (no solo por cable, porque con
cable el celular no llega).

```bash
# 1. Compilar los estilos (obligatorio: con Vite dev el celular no carga nada)
npm run build

# 2. Levantar el servidor escuchando en toda la red, no solo en localhost
php artisan serve --host=0.0.0.0 --port=8000
```

3. Averiguar la IP de la laptop (la de la Wi-Fi, no la `127.0.0.1`):

```bash
ip -4 addr show scope global
```

Sale algo como `inet 192.168.18.35/24` en la interfaz `wlan0`. La IP es
`192.168.18.35`.

4. En el navegador del celular abrir:

```
http://IP-DE-LA-LAPTOP:8000
```

Ejemplo: `http://192.168.18.35:8000`

### Si el celular no entra

Casi siempre es el firewall. En CachyOS (firewalld):

```bash
# Ver la zona de la interfaz Wi-Fi y los puertos ya abiertos
sudo firewall-cmd --get-active-zones
sudo firewall-cmd --list-all

# Abrir el 8000 en la zona de la Wi-Fi (cambiar 'trusted' por la zona que
# devuelva --get-active-zones; en redes de invitados o públicas usar 'public')
sudo firewall-cmd --zone=trusted --add-port=8000/tcp --permanent
sudo firewall-cmd --reload

# Comprobar que quedó abierto
sudo firewall-cmd --zone=trusted --list-ports
```

Si la red Wi-Fi está en la zona `public`, el comando queda:

```bash
sudo firewall-cmd --zone=public --add-port=8000/tcp --permanent
sudo firewall-cmd --reload
```

> Con `--permanent` el puerto sigue abierto después de reiniciar. Sin esa
> bandera, el cambio se pierde al apagar la máquina.

### Probar sin tocar el firewall

Para descartar que el problema sea el firewall, se puede levantar el servidor
en una red abierta solo mientras se prueba:

```bash
# temporal, se cierra con Ctrl+C
sudo firewall-cmd --zone=trusted --add-port=8000/tcp
php artisan serve --host=0.0.0.0 --port=8000
```

Y después devolver todo a como estaba:

```bash
sudo firewall-cmd --zone=trusted --remove-port=8000/tcp
```

### Other causas

- **El celular y la laptop en redes distintas.** Wi-Fi de guests, o datos
  móviles del celular en vez de la Wi-Fi de la casa. No hay salida.
- **La laptop tiene dos Wi-Fi / VPN.** A veces la IP de la VPN es la que
  muestra `ip addr` y no es la de la red local.

### Antes de probar con usuarios reales

En `.env` poner:

```
APP_DEBUG=false
```

Con `APP_DEBUG=true` cualquier error muestra una pantalla con detalles internos
del sistema. Para desarrollo local está bien; para la prueba con usuarios, no.

## 6. Estructura

```
app/
  Consultas/            LineasProduccion y ResumenDashboard (consultas de las 3 categorías)
  Http/Controllers/     Login, Dashboard, Produccion, Historial
  Models/               UsuarioSistema, Produccion, Producto, Empleado, ...
database/
  migrations/           esquema (no modificar)
  seeders/              datos de ejemplo
lang/es/validation.php  mensajes de validación en español
resources/
  css/                  variables.css tiene la paleta y la tipografía de marca
  js/                   scripts por página + sidebar.js (menú hamburguesa)
  views/                Blade
public/
  images/               imágenes y fotos de tortas
```

### Paleta y tipografía

Definidas en `resources/css/variables.css` y en ningún otro lado:

| Variable | Color |
|---|---|
| `--verde-oscuro` | `#33403A` |
| `--verde-medio` | `#4F6355` |
| `--verde-sage` | `#7D9481` |
| `--verde-claro` | `#AEC0AC` |
| `--beige` | `#DCD6C4` |
| `--crema` | `#F6F2EF` |

Texto: **Huninn** (`--fuente`). Títulos y subtítulos: **Baloo 2**
(`--fuente-titulos`).

## 7. Módulos

| Módulo | Estado |
|---|---|
| Login / logout | Listo |
| Dashboard | Listo (tarjetas, gráficos y detalle por categoría) |
| Ingreso de producción | Listo (pan, torta con foto, bocadito) |
| Historial con filtros | Listo (categoría, rango de fechas y turno) |
| Pedidos | **Próximamente** — la ruta existe y avisa que no está implementado |

## 8. Pruebas manuales

### En escritorio

```bash
npm run build && php artisan serve --host=0.0.0.0 --port=8000
```

1. Entrar a `/login` e iniciar sesión con `carlos.m` / `password123`.
2. Entrar con una contraseña incorrecta: debe volver al login con
   "Usuario o contraseña incorrectos", sin decir cuál de los dos falló.
3. Probar 6 veces seguidas con contraseña incorrecta: a la 6ª debe avisar que
   hay que esperar.
4. Navegar por Dashboard, Producción, Historial y Pedidos desde el menú.
5. En Pedidos debe aparecer "Próximamente".
6. Ingresar una producción de pan: elegir producto, turno, cantidad y al menos
   un empleado. Debe avisar que se guardó y aparecer en "Registrado
   recientemente".
7. Cambiar a Torta: eligen forma, subir una foto y guardar.
8. Ingresar un bocadito.
9. En el historial, filtrar por categoría, por rango de fechas y por turno.
10. Cerrar sesión y tratar de entrar a `/dashboard` de nuevo: debe mandar al
    login.

### En el celular

11. `npm run build` (obligatorio) y `php artisan serve --host=0.0.0.0 --port=8000`.
12. Abrir `http://IP-DE-LA-LAPTOP:8000` en el celular.
13. Iniciar sesión.
14. Abrir el menú hamburguesa: tapar el ícono, el fondo, un enlace y la tecla
    Escape (en un teclado externo) para cerrarlo.
15. Ingresar una producción con foto sacada con la cámara del celular.

## 9. Problemas frecuentes

| Síntoma | Causa |
|---|---|
| El celular entra sin estilos | Falta `npm run build`, o quedó `public/hot` de `npm run dev`. Se borra con `rm public/hot` |
| "419 La sesión expiró" | La sesión de 120 minutos venció, o el reloj cambió. Volver a entrar |
| "405 Method Not Allowed" en el login | El login tiene que hacer POST a `route('login.attempt')`. Si hay un build viejo, volver a compilar con `npm run build` |
| Login dice "Usuario o contraseña incorrectos" y el usuario existe | Correr `php artisan db:seed` para crear el usuario de prueba |
| El login no encuentra al usuario | `usuarios_sistema` está vacía: `php artisan db:seed` |
| No se ve la fuente Huninn / Baloo 2 | Sin internet: las fuentes vienen de Google Fonts |
