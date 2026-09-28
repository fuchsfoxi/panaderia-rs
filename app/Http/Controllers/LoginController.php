<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Autenticacion del sistema, hecha a mano (sin Breeze/Jetstream).
 *
 * El modelo de usuarios es App\Models\UsuarioSistema sobre la tabla
 * usuarios_sistema, cuya columna de usuario es 'username' y la de contraseña
 * es 'password_hash'. Ese mapeo ya vive en el modelo (getAuthPassword) y en
 * config/auth.php, asi que aca NO se tocan esos nombres en ningun momento:
 * Auth::attempt() los resuelve solo.
 */
class LoginController extends Controller
{
    /** Intentos de login permitidos por usuario+IP antes de bloquear. */
    private const INTENTOS_MAXIMOS = 5;

    /** Segundos de espera una vez superado el limite. */
    private const SEGUNDOS_DE_ESPERA = 60;

    public function index(): View
    {
        // Si ya hay sesion y alguien entra a /login a mano, el middleware
        // 'guest' lo manda al dashboard, asi que esta vista solo se ve sin
        // sesion.
        return view('auth.login');
    }

    /**
     * Procesa el envio del formulario de login (POST /login).
     */
    public function authenticate(Request $request): RedirectResponse
    {
        // Mensajes en español y aca adentro (no en un archivo de traduccion)
        // para que se vean igual sin depender del APP_LOCALE del .env.
        $credenciales = $request->validate(
            [
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

        // La clave del limitador junta usuario + IP: frena tanto el ataque de
        // fuerza bruta contra una cuenta como el que prueba muchas cuentas
        // desde la misma maquina.
        $clave = $this->claveDelLimitador($request, $credenciales['username']);

        if (RateLimiter::tooManyAttempts($clave, self::INTENTOS_MAXIMOS)) {
            $esperar = RateLimiter::availableIn($clave);

            // availableIn devuelve los segundos que faltan; se toma el mayor
            // entre el configurado y el real para no prometer menos de lo que
            // se cumple si el limite se configuro con otra duracion.
            $segundos = max($esperar, self::SEGUNDOS_DE_ESPERA);

            return $this->volverAlLogin($request, "Demasiados intentos fallidos. Esperá $segundos segundos e intentá de nuevo.");
        }

        // Auth::attempt() arma la consulta con 'username' y 'password'. El
        // password se pasa plano porque el provider lo hashea con
        // getAuthPassword() (que devuelve la columna password_hash) y compara
        // con Hash::check(): aca jamas se toca el hash.
        //
        // Cuando exista una columna 'activo' en usuarios_sistema, el filtro se
        // agrega en este mismo array:
        //     $buscable['activo'] = true;
        // (hoy esa columna no existe en el esquema y consultarla daria error,
        //  por eso NO se escribe todavia).
        $intento = [
            'username' => $credenciales['username'],
            'password' => $credenciales['password'],
        ];

        if (! Auth::attempt($intento)) {
            // Un solo intento consumido, tambien cuando falla.
            RateLimiter::hit($clave, self::SEGUNDOS_DE_ESPERA);

            // Mensaje generico a proposito: decir "usuario no existe" o
            // "contraseña incorrecta" le confirmaria a un atacante que cuenta
            // hay en el sistema.
            return $this->volverAlLogin($request, 'Usuario o contraseña incorrectos.');
        }

        // Login correcto: se limpia el limite de intentos de esta combinacion.
        RateLimiter::clear($clave);

        // Se regenera el id de sesion al iniciar sesion para evitar que un
        // atacante fije el id de otra persona antes del login (session
        // fixation). generate() es el metodo viejo; regenerate() ademas rota
        // los datos de la sesion.
        $request->session()->regenerate();

        // intended() devuelve la pagina que el visitante quiso ver antes de
        // que el middleware 'auth' lo mandara al login; si no hay ninguna
        // (entro directo a /login), cae al dashboard.
        return redirect()->intended(route('dashboard'));
    }

    /**
     * Cierra la sesion (POST /logout).
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        // invalidate() borra todos los datos de la sesion y regenera el id;
        // regenerateToken() cambia el _token del formulario, para que un POST
        // guardado en el historial del navegador no vuelva a servir.
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Vuelve al login con el usuario escrito y el error en pantalla.
     *
     * except('password') es lo que evita que la contraseña del usuario quede
     * cargada en el HTML de vuelta: al reenviar el formulario solo se
     * recupera el usuario, nunca la contraseña.
     */
    private function volverAlLogin(Request $request, string $mensaje): RedirectResponse
    {
        return redirect()
            ->route('login')
            ->withInput($request->except('password'))
            ->withErrors(['username' => $mensaje]);
    }

    /**
     * Clave del limitador: usuario en minuscula + IP.
     */
    private function claveDelLimitador(Request $request, string $username): string
    {
        return 'login|'.Str::lower(trim($username)).'|'.$request->ip();
    }
}
