<?php

namespace App\Http\Controllers;

use App\Actions\Usuarios\AdministrarUsuarios;
use App\Http\Requests\CambiarEstadoUsuarioRequest;
use App\Http\Requests\CambiarPasswordUsuarioRequest;
use App\Http\Requests\CrearUsuarioRequest;
use App\Http\Requests\GestionarUsuarioRequest;
use App\Models\Rol;
use App\Models\Turno;
use App\Models\Usuario;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UsuarioController extends Controller
{
    public function index(): View
    {
        return view('usuarios.index', ['usuarios' => Usuario::with(['rol', 'turno', 'empleado'])->orderBy('username')->paginate(15)]);
    }

    public function create(): View
    {
        return view('usuarios.create', [
            'roles' => Rol::whereIn('nombre_roles', [Rol::ADMINISTRADOR, Rol::OPERADOR])->orderBy('nombre_roles')->get(),
            'turnos' => Turno::orderBy('nombre_turnos')->get(),
        ]);
    }

    public function store(CrearUsuarioRequest $request, AdministrarUsuarios $usuarios): RedirectResponse
    {
        return $this->guardar($request, fn () => $usuarios->crear($request->validated(), (int) $request->user()->id), 'Usuario creado.');
    }

    public function estado(CambiarEstadoUsuarioRequest $request, Usuario $usuario, AdministrarUsuarios $usuarios): RedirectResponse
    {
        return $this->guardar($request, fn () => $usuarios->estado($usuario->id, $request->boolean('activo'), (int) $request->user()->id), 'Estado de la cuenta actualizado.');
    }

    public function password(Usuario $usuario): View
    {
        return view('usuarios.password', compact('usuario'));
    }

    public function cambiarPassword(CambiarPasswordUsuarioRequest $request, Usuario $usuario, AdministrarUsuarios $usuarios): RedirectResponse
    {
        return $this->guardar($request, fn () => $usuarios->password($usuario->id, $request->validated('password'), (int) $request->user()->id), 'Contraseña actualizada. Las sesiones anteriores de esta cuenta se revalidarán en su próxima petición protegida.');
    }

    private function guardar(GestionarUsuarioRequest $request, Closure $operacion, string $mensaje): RedirectResponse
    {
        try {
            $operacion();
        } catch (UniqueConstraintViolationException) {
            return back()->withErrors(['username' => 'Ese nombre de usuario ya está en uso.'])->withInput($request->safeInput());
        } catch (ValidationException $exception) {
            if ($request->expectsJson()) {
                throw $exception;
            }

            return back()->withErrors($exception->errors())->withInput($request->safeInput());
        } catch (QueryException $exception) {
            // Una excepción SQL puede contener bindings de credenciales: no registrarla completa.
            Log::error('No se pudo guardar la cuenta de usuario.', ['sqlstate' => $exception->errorInfo[0] ?? null]);

            return back()->withErrors(['usuarios' => 'No se pudo guardar la cuenta. Inténtalo de nuevo.'])->withInput($request->safeInput());
        }

        return redirect()->route('usuarios.index')->with('success', $mensaje);
    }
}
