<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Support\DemoAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $key = 'sicovi-login:'.hash('sha256', $request->string('username')->toString().'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['username' => 'Demasiados intentos. Intente de nuevo en '.RateLimiter::availableIn($key).' segundos.']);
        }
        $credentials = $request->safe()->only(['username', 'password']) + ['active' => true];
        if (! Auth::attemptWhen($credentials, fn (User $user) => $user->role()->where('active', true)->exists() && (! $user->is_demo || app(DemoAccess::class)->allowed()))) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['username' => 'No se pudo iniciar sesión con esas credenciales.']);
        }
        RateLimiter::clear($key);
        $request->session()->regenerate();
        // Capture the login credential immediately, including before the first protected request.
        $request->session()->put('password_hash_'.Auth::getDefaultDriver(), Auth::guard()->hashPasswordForCookie($request->user()->getAuthPassword()));
        $request->user()->forceFill(['last_login_at' => now('UTC')])->save();

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'La sesión se cerró.');
    }
}
