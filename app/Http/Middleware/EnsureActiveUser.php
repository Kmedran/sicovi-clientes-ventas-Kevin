<?php

namespace App\Http\Middleware;

use App\Support\DemoAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $current = $user?->fresh();

        if (! $current || ! $current->active || ! $current->role()->where('active', true)->exists() || ($current->is_demo && ! app(DemoAccess::class)->allowed())) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json(['message' => 'La cuenta no tiene acceso activo.'], 401);
            }

            return redirect()->route('login')->withErrors(['email' => 'La cuenta no tiene acceso activo.']);
        }

        return $next($request);
    }
}
