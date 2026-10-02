<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
        // valido que username y password no lleguen vacíos
        // si falta alguno, Laravel devuelve al formulario con el error solo
        $credenciales = $request->validate([
            'username' => 'required',
            'password' => 'required',
        ]);

        // Auth::attempt busca al usuario por username en usuarios_sistema
        // y compara la contraseña escrita con password_hash (por getAuthPassword() del modelo)
        // devuelve true si coincide y false si no
        if (Auth::attempt($credenciales)) {
            // cambio el ID de la sesión al iniciar sesión
            // así evito el session fixation (que alguien reutilice una sesión vieja)
            $request->session()->regenerate();

            // mando al usuario a la página que quería visitar antes del login
            // si no había ninguna, va al dashboard
            return redirect()->intended('dashboard');
        }

        // si llegó aquí es porque el login falló
        // el mensaje es genérico para no decir si falló el usuario o la contraseña
        // onlyInput guarda solo el username para que no tenga que escribirlo otra vez (la contraseña nunca)
        return back()->withErrors([
            'username' => 'Credenciales incorrectas.',
        ])->onlyInput('username');
    }
}
