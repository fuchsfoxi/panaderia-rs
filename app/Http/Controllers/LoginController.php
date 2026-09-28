<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * ============================================================================
 * AUTENTICACION DEL SISTEMA
 * ============================================================================
 *
 * ESTE ARCHIVO ES EL CORAZON DEL LOGIN. Si hay que defender o explicar cómo el
 * sistema controla el acceso, es el primero que hay que saber de memoria.
 *
 * Está escrito a mano, sin Breeze ni Jetstream ni ningún starter kit. Toda la
 * lógica cabe en este archivo y en dos piezas que YA existían antes:
 *
 *   1. config/auth.php
 *      Dice qué guard y qué provider se usan. El provider 'users' es de tipo
 *      'eloquent' y apunta a App\Models\UsuarioSistema. Acá NO se toca ese
 *      archivo: Laravel lo lee solo.
 *
 *   2. App\Models\UsuarioSistema
 *      -> getAuthPassword() devuelve $this->password_hash
 *      Es la pieza que le dice a Laravel "la contraseña de este modelo está en
 *      la columna password_hash, no en 'password' como en el modelo User de
 *      fábrica".
 *
 * LA REGLA DE ORO DE ESTE ARCHIVO
 * -------------------------------
 * La palabra 'password_hash' NO aparece ni una sola vez en este controlador.
 * Solo aparecen 'username' y 'password'. El mapeo entre el nombre lógico
 * "password" y la columna real "password_hash" vive únicamente en el modelo.
 *
 * Por qué importa: si mañana la columna se llama distinto, o se cambia el
 * modelo de autenticación, hay que ir a UN solo lugar. Si el hash se
 * nombrara en el controlador también, habría dos lugares, y con dos lugares
 * siempre hay un momento en que uno se actualiza y el otro no.
 *
 * ---------------------------------------------------------------------------
 * EL VIAJE DE UNA PETICIÓN DE LOGIN, PASO A PASO
 * ---------------------------------------------------------------------------
 *
 *  1. El usuario escribe usuario y contraseña y toca "Iniciar sesión".
 *
 *  2. El navegador manda POST /login con el _token de CSRF.
 *     (El token va porque el formulario tiene @csrf. Sin él, Laravel
 *      respondería 419 en vez de procesar nada.)
 *
 *  3. routes/web.php busca la ruta POST /login, llamada 'login.attempt',
 *     y ve que tiene el middleware 'guest'.
 *
 *  4. Middleware 'guest' (ver bootstrap/app.php):
 *     - Si el visitante YA tiene sesión, no entra acá: lo manda al dashboard.
 *     - Si no tiene sesión, sigue.
 *
 *  5. Entra a authenticate(), que es este método. Acá empieza todo:
 *     - Valida que los dos campos vengan.
 *     - Mira si esa combinación usuario+IP ya agotó sus intentos.
 *     - Le pide a Auth que verifique la contraseña.
 *
 *  6. Auth::attempt() hace, internamente:
 *     a) Toma del array lo que NO sea 'password' y busca el usuario:
 *            SELECT * FROM usuarios_sistema WHERE username = ? LIMIT 1
 *        Ojo: el array ya trae 'username' limpio, sin 'password'.
 *     b) Si el usuario no existe, devuelve false. No dice por qué.
 *     c) Si existe, lee el hash con getAuthPassword() -> columna password_hash.
 *     d) Compara con Hash::check($passwordPlano, $hashGuardado).
 *        Hash::check() aplica el algoritmo al texto plano y compara el
 *        resultado con lo que está guardado. Son dos operaciones distintas:
 *        no se "descifra" nada, se recalcula y se compara.
 *     e) Si coincide, crea la sesión con el id del usuario.
 *
 *  7. Éxito -> se regenera el id de sesión y se redirige.
 *      Fallo  -> se cuenta el intento y se vuelve al login con un error.
 *
 *  8. En las páginas siguientes, el middleware 'auth:web' hace lo inverso:
 *     lee el id de usuario de la sesión y lo carga en Auth::user(). Por eso
 *     el resto de las páginas no necesitan preguntar quién sos.
 *
 * ---------------------------------------------------------------------------
 * POR QUÉ CADA DECISIÓN
 * ---------------------------------------------------------------------------
 *
 * - Sin starter kit: el proyecto no usa Breeze ni Jetstream, así que esta
 *   lógica es la única que existe y hay que entenderla sí o sí.
 *
 * - Mensajes en español y escritos acá: el idioma en .env es local de cada
 *   máquina. Si el login dependiera de APP_LOCALE, en una máquina con
 *   APP_LOCALE=en los usuarios verían los errores en inglés.
 *
 * - Límite de 5 intentos por usuario+IP: frena dos ataques distintos con una
 *   sola clave. Solo-usuario frena la fuerza bruta contra una cuenta conocida.
 *   Solo-IP frena el barrido de muchas cuentas desde una máquina. Las dos
 *   mitades juntas cierran los dos casos.
 *
 * - Mensaje de error genérico: decir "usuario no encontrado" o "contraseña
 *   incorrecta" le confirma a un atacante qué cuentas existen. El mensaje
 *   genérico no revela nada.
 *
 * - except('password'): al volver al login se reenvían los datos escritos,
 *   para no perder el usuario. La contraseña queda explícitamente afuera, así
 *   que nunca aparece en el HTML de vuelta.
 *
 * - regenerate() al iniciar sesión: sin esto, un atacante que lograra fijar
 *   el id de sesión en el navegador de la víctima (session fixation) heredaría
 *   una sesión válida. Regenerar el id después de autenticar lo evita.
 */
class LoginController extends Controller
{
    /**
     * Intentos permitidos antes de bloquear, por combinación usuario+IP.
     *
     * Con 5 por minuto, un atacante puede probar 7.200 contraseñas por día
     * contra una cuenta. Es una barrera, no un muro: sirve para frenar el
     * automatizado, no a alguien que escribe a mano.
     */
    private const INTENTOS_MAXIMOS = 5;

    /**
     * Segundos de bloqueo una vez agotados los intentos.
     *
     * Con SESSION_DRIVER=database los contadores del limitador viven en la
     * tabla 'cache', no en memoria. Por eso, si se quiere limpiar un bloqueo
     * sin esperar, se corre: php artisan cache:clear
     */
    private const SEGUNDOS_DE_ESPERA = 60;

    /**
     * Muestra el formulario de login (GET /login).
     *
     * Si alguien ya tiene sesión y entra acá a mano, nunca llega a ver esto:
     * el middleware 'guest' lo manda al dashboard. Ver bootstrap/app.php.
     */
    public function index(): View
    {
        return view('auth.login');
    }

    /**
     * Procesa el envío del formulario de login (POST /login).
     *
     * El nombre de la ruta es 'login.attempt' y no 'login' a propósito: el
     * GET conserva el nombre 'login' porque el middleware 'auth' redirige
     * ahí a quien no tiene sesión, y si ese nombre lo tomara el POST, el
     * middleware apuntaría a un endpoint que no dibuja nada.
     */
    public function authenticate(Request $request): RedirectResponse
    {
        // ------------------------------------------------------------------
        // PASO 1: validar que los campos vengan.
        //
        // validate() hace tres cosas:
        //   - Si falta un campo, corta acá: NO sigue a la base de datos. Por
        //     eso una petición con el formulario vacío no consulta nada.
        //   - Devuelve solo los campos validados, ya limpios. Lo que no se
        //     lista acá se descarta, así que un campo extra enviado a mano
        //     no llega al resto del método.
        //   - Si falla, arma los errores y Laravel los muestra en la vista
        //     con @error, y vuelve atrás con los datos escritos.
        //
        // Los mensajes van en el segundo argumento y los nombres de campo en
        // el tercero. El cuarto parámetro (atributos) no se usa porque los
        // nombres ya están en el segundo.
        // ------------------------------------------------------------------
        $credenciales = $request->validate(
            [
                // 'max:50' y 'max:255' coinciden con el tamaño real de las
                // columnas (varchar 50 y varchar 255). Cortar acá evita mandar
                // cadenas gigantes a la base.
                'username' => ['required', 'string', 'max:50'],
                'password' => ['required', 'string', 'max:255'],
            ],
            [
                'username.required' => 'Escribí tu usuario.',
                'password.required' => 'Escribí tu contraseña.',
                'username.max' => 'El usuario no puede ser tan largo.',
                'password.max' => 'La contraseña no puede ser tan larga.',
            ],
            [
                'username' => 'usuario',
                'password' => 'contraseña',
            ]
        );

        // ------------------------------------------------------------------
        // PASO 2: ¿esta combinación usuario+IP ya gastó sus intentos?
        //
        // La clave se compone de las dos mitades, y por eso frena los dos
        // ataques: alguien que insiste contra 'carlos.m' desde una máquina, y
        // alguien que prueba cien cuentas distintas desde la misma máquina.
        // ------------------------------------------------------------------
        $clave = $this->claveDelLimitador($request, $credenciales['username']);

        if (RateLimiter::tooManyAttempts($clave, self::INTENTOS_MAXIMOS)) {
            $esperar = RateLimiter::availableIn($clave);

            // availableIn() devuelve los segundos que faltan para que expire
            // el bloqueo. Se toma el mayor entre ese número y el configurado,
            // para no prometer en pantalla menos tiempo del que realmente se
            // cumple si el límite se reconfigurara con otra duración.
            $segundos = max($esperar, self::SEGUNDOS_DE_ESPERA);

            // Ojo: acá NO se consume un intento. El bloqueo se aplica antes
            // de tocar la base, así que un atacante que insiste no puede
            // alargar su propio bloqueo ni generar más consultas.
            return $this->volverAlLogin(
                $request,
                "Demasiados intentos fallidos. Esperá $segundos segundos e intentá de nuevo."
            );
        }

        // ------------------------------------------------------------------
        // PASO 3: verificar la contraseña.
        //
        // Auth::attempt() separa solo lo que necesita:
        //   - Todo lo que NO sea 'password' lo usa como condiciones de búsqueda.
        //   - 'password' lo saca del array y lo compara contra el hash.
        //
        // Por eso acá solo se escriben los nombres lógicos. De dónde sale
        // el hash es problema de getAuthPassword(), en el modelo.
        //
        // ------------------------------------------------------------------
        // PENDIENTE, cuando exista la columna:
        // Si usuarios_sistema llegara a tener una columna 'activo', el filtro
        // se agrega acá, en este mismo array:
        //
        //     $intento['activo'] = true;
        //
        // Funciona porque Auth::attempt() trata cualquier clave que no sea
        // 'password' como una condición del WHERE. Hoy esa columna NO existe
        // en el esquema (ver database/migrations/..._create_usuarios_sistema_table.php),
        // y consultarla daría un error de SQL en cada intento de login, así
        // que por eso no está escrita. Es una línea, en este lugar exacto.
        // ------------------------------------------------------------------
        $intento = [
            'username' => $credenciales['username'],
            'password' => $credenciales['password'],
        ];

        if (! Auth::attempt($intento)) {
            // Falló. Se cuenta UN intento (también cuando falla: el login
            // correcto no tiene sentido si el anterior no se registró).
            RateLimiter::hit($clave, self::SEGUNDOS_DE_ESPERA);

            // Mensaje genérico a propósito. Las dos alternativas obvias
            // ("ese usuario no existe" / "la contraseña está mal") le dicen al
            // atacante algo útil: qué cuentas hay, o que encontró la correcta.
            return $this->volverAlLogin($request, 'Usuario o contraseña incorrectos.');
        }

        // ------------------------------------------------------------------
        // PASO 4: login correcto.
        // ------------------------------------------------------------------

        // Esta combinación ya no está sospechosa: se le borra el historial de
        // intentos. Si no, alguien que fallara 4 veces y después acertara
        // igual quedaría a un intento del bloqueo.
        RateLimiter::clear($clave);

        // Se cambia el identificador de la sesión DESPUÉS de autenticar.
        //
        // Sin esto se sufre session fixation: si un atacante logra
        // poner un id de sesión conocido en el navegador de la víctima
        // (por ejemplo, en un enlace), y la víctima se loguea sin que ese id
        // cambie, el atacante queda con una sesión válida.
        //
        // regenerate() genera un id nuevo y arrastra los datos. (El método
        // viejo generate() solo generaba el id, sin rotar los datos.)
        $request->session()->regenerate();

        // intended() devuelve la página que el visitante quería ver antes de
        // que el middleware 'auth' lo mandara al login. Así, si alguien entra
        // a /produccion sin sesión, ve el login, entra, y vuelve justo a
        // /produccion en vez de caer al dashboard.
        // Si entró directo a /login no hay nada pendiente, y va al dashboard.
        return redirect()->intended(route('dashboard'));
    }

    /**
     * Cierra la sesión (POST /logout).
     *
     * Es POST y no GET a propósito: un cierre de sesión con GET se dispararía
     * desde un <img src="/logout"> en cualquier página, y cerraría la sesión
     * de alguien sin que lo pidiera. Por eso el botón del menú está dentro de
     * un <form> con @csrf.
     */
    public function logout(Request $request): RedirectResponse
    {
        // 1. El guard se olvida del usuario. A partir de acá Auth::check()
        //    devuelve false, aunque la sesión siga existiendo.
        Auth::logout();

        // 2. invalidate() borra TODOS los datos que había en la sesión y
        //    genera un id nuevo. Sin esto, los datos anteriores quedarían
        //    guardados en la tabla 'sessions' hasta que expiraran.
        $request->session()->invalidate();

        // 3. regenerateToken() cambia el _token de los formularios. Si no,
        //    un POST que quedó guardado en el historial del navegador podría
        //    volver a servirse con el token viejo.
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Vuelve al login conservando lo escrito y mostrando el error.
     *
     * withInput() reenvía los datos del request para que el formulario se
     * repinte con lo que la persona había escrito. except('password') saca la
     * contraseña de ese reenvío: es el motivo de que la contraseña nunca
     * aparezca en el HTML de vuelta ni quede en la sesión.
     *
     * withErrors() deja el mensaje en la sesión; la vista lo muestra arriba
     * del formulario con @if ($errors->any()).
     */
    private function volverAlLogin(Request $request, string $mensaje): RedirectResponse
    {
        return redirect()
            ->route('login')
            ->withInput($request->except('password'))
            ->withErrors(['username' => $mensaje]);
    }

    /**
     * Arma la clave del limitador: usuario en minúsculas + IP.
     *
     * Dos detalles:
     * - minúsculas: si el limitador distinguiera 'Carlos' de 'carlos.m',
     *   un atacante podría saltear el límite alternando mayúsculas. La
     *   consulta a la base SÍ distingue (en MariaDB la comparación depende
     *   del collation), pero para el contador se normaliza.
     * - trim(): espacios accidentales tampoco generan contadores distintos.
     */
    private function claveDelLimitador(Request $request, string $username): string
    {
        return 'login|'.Str::lower(trim($username)).'|'.$request->ip();
    }
}
