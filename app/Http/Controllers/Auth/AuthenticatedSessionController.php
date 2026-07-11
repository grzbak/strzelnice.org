<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Support\PendingInvitationSession;
use App\Support\ReservationIntentSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = $request->user();
        PendingInvitationSession::syncForUser($request, $user);

        $reservationIntentUrl = ReservationIntentSession::redirectUrl($request);

        if ($reservationIntentUrl && $user->isClient()) {
            if (! $user->hasVerifiedEmail()) {
                return redirect()->route('verification.notice');
            }

            ReservationIntentSession::forget($request);

            return redirect()->to($reservationIntentUrl);
        }

        if ($user->isSuperAdmin()) {
            return redirect()->intended('/superAdmin');
        }

        if ($user->isAdmin()) {
            return redirect()->intended('/panel/rezerwacje');
        }

        if ($user->isEmployee()) {
            return redirect()->intended('/panel/rezerwacje');
        }

        return redirect()->intended('/klient');

        // return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
