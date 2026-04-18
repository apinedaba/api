<?php

namespace App\Http\Controllers\Vendedor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class VendedorAuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::guard('vendedor_web')->check()) {
            return redirect()->route('vendedor.dashboard');
        }

        return Inertia::render('Vendedor/Login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ], [
            'email.required'    => 'El correo es obligatorio.',
            'email.email'       => 'Ingresa un correo válido.',
            'password.required' => 'La contraseña es obligatoria.',
        ]);

        if (Auth::guard('vendedor_web')->attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            return redirect()->intended(route('vendedor.dashboard'));
        }

        return back()->withErrors([
            'email' => 'Las credenciales no son correctas.',
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::guard('vendedor_web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('vendedor.login');
    }
}
