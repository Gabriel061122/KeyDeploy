<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Autenticación mínima para que funcionen los módulos de proyectos,
 * requerimientos y arquitecturas (RF01/RF14). El registro público crea
 * cuentas exclusivamente de tipo CLIENTE; el control de roles interno
 * (RF16) pertenece a EP02 y ampliará este módulo.
 */
class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            return redirect()->intended(route('projects.index'));
        }

        // RF01: al fallar se informa del error general sin revelar cuál
        // de las dos credenciales era la incorrecta.
        return back()
            ->withErrors(['email' => 'Las credenciales no son válidas.'])
            ->onlyInput('email');
    }

    public function showRegister(): View
    {
        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique((new User)->getTable(), 'email')],
            'password' => ['required', 'string', Password::min(8)->letters()->numbers(), 'confirmed'],
        ]);

        $user = new User($validated);
        $user->role = User::ROLE_CLIENTE; // RF14: el único rol asignable al registrarse.
        $user->save();

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('projects.index');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
