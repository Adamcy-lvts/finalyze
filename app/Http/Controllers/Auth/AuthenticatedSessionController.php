<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Show the login page.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        Log::info('=== LOGIN ATTEMPT ===', [
            'email' => $request->input('email'),
            'has_csrf_header' => $request->hasHeader('X-CSRF-TOKEN'),
            'csrf_header_prefix' => $request->header('X-CSRF-TOKEN') ? substr($request->header('X-CSRF-TOKEN'), 0, 12).'...' : null,
            'session_id_before' => $request->session()->getId(),
        ]);

        $request->authenticate();

        $request->session()->regenerate();

        Log::info('=== LOGIN SUCCESS ===', [
            'email' => $request->input('email'),
            'user_id' => $request->user()?->id,
            'session_id_after' => $request->session()->getId(),
        ]);

        $user = $request->user();
        $adminHome = route('admin.dashboard', absolute: false);
        $defaultHome = route('dashboard', absolute: false);

        // Admin/support roles land on admin dashboard; everyone else goes to user dashboard
        $target = $user && $user->hasAnyRole(['super_admin', 'admin', 'support'])
            ? $adminHome
            : $defaultHome;

        return redirect()->intended($target);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Log::info('=== LOGOUT REQUEST ===', [
            'user_id' => $request->user()?->id,
            'email' => $request->user()?->email,
            'session_id_before' => $request->session()->getId(),
        ]);

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        Log::info('=== LOGOUT COMPLETE ===', [
            'session_id_after' => $request->session()->getId(),
        ]);

        return redirect('/');
    }
}
