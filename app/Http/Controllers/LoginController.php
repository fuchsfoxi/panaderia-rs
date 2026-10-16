<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class LoginController extends Controller
{
    // muestra la vista del login (la ruta GET /login llama a este método)
    public function index()
    {
        return view('auth.login');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    // recibe lo que se envía desde el formulario (ruta POST /login)
    public function authenticate(Request $request)
    {
        $validator = Validator::make($request->only('username', 'password'), [
            'username' => ['bail', 'required', 'string', 'max:50'],
            'password' => ['bail', 'required', 'string', 'max:1024'],
        ], [
            'username.required' => 'Ingresa tu usuario.',
            'username.string' => 'El usuario debe ser texto.',
            'username.max' => 'El usuario no debe superar los 50 caracteres.',
            'password.required' => 'Ingresa tu contraseña.',
            'password.string' => 'La contraseña debe ser texto.',
            'password.max' => 'La contraseña no debe superar los 1024 caracteres.',
        ]);

        if ($validator->fails() && ! $request->expectsJson()) {
            $username = $request->input('username');

            // No guardar contraseñas, arrays malformados ni campos ajenos al login.
            return back()->withErrors($validator)->withInput([
                'username' => is_string($username) && mb_strlen($username) <= 50 ? $username : '',
            ]);
        }

        $credenciales = $validator->validate();
        // La BD decide qué grafías identifican la misma cuenta, según su collation.
        // Seleccionar solo el ID evita transliterar identidades distintas o leer hashes.
        $usuarioId = Usuario::where('username', $credenciales['username'])->value('id');
        $identidad = $usuarioId !== null ? 'usuario:'.$usuarioId : 'username:'.Str::lower($credenciales['username']);
        $limite = 'login:'.hash('sha256', $identidad.'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($limite, 5)) {
            $segundosRestantes = RateLimiter::availableIn($limite);

            return back()->withErrors([
                'username' => 'Demasiados intentos. Inténtalo de nuevo en '.$segundosRestantes.' segundos.',
            ])->onlyInput('username')->with([
                'login_feedback' => 'throttle',
                'login_retry_at' => now()->timestamp + $segundosRestantes,
            ]);
        }

        // Auth::attempt busca al usuario por username en usuarios_sistema
        // y compara la contraseña escrita con password_hash (por getAuthPassword() del modelo)
        // devuelve true si coincide y false si no
        if (Auth::attempt($credenciales + ['activo' => true])) {
            RateLimiter::clear($limite);
            // cambio el ID de la sesión al iniciar sesión
            // así evito el session fixation (que alguien reutilice una sesión vieja)
            $request->session()->regenerate();

            // mando al usuario a la página que quería visitar antes del login
            // si no había ninguna, va al dashboard
            return redirect()->intended('dashboard');
        }

        RateLimiter::hit($limite, 60);

        // si llegó aquí es porque el login falló
        // el mensaje es genérico para no decir si falló el usuario o la contraseña
        // onlyInput guarda solo el username para que no tenga que escribirlo otra vez (la contraseña nunca)
        return back()->withErrors([
            'username' => 'Las credenciales ingresadas no son correctas.',
        ])->onlyInput('username')->with('login_feedback', 'credentials');
    }
}
