<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Services\AccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function edit(): View
    {
        return view('account');
    }

    public function updatePassword(ChangePasswordRequest $request, AccessService $access): RedirectResponse
    {
        $access->changePassword($request->user(), $request->validated('current_password'), $request->validated('password'));
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Contraseña actualizada. Todas las sesiones se cerraron; inicie sesión nuevamente.');
    }
}
