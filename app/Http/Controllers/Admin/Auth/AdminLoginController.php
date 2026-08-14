<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AdminLoginController extends Controller
{
    public function showLogin(): Response|RedirectResponse
    {
        if (Auth::guard('admin')->check()) {
            return redirect()->route('admin.campaigns.index');
        }

        return Inertia::render('admin/login');
    }

    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::guard('admin')->attempt(
            $request->only('email', 'password'),
            $request->boolean('remember'),
        )) {
            return back()
                ->withErrors(['email' => 'Kredensial tidak valid.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('admin.campaigns.index'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
