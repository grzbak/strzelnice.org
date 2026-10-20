<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\UserSecurityService;
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
    public function store(
        LoginRequest $request,
        UserSecurityService $security
    ): RedirectResponse {
        $request->authenticate();

        $request->session()->regenerate();

        $user = $request->user();
        $security->recordIpAddress($user, $request, 'login');
        $security->audit('user.login', $user, $request, $user);
        PendingInvitationSession::syncForUser($request, $user);

        if (! $user->is_approved) {
            return new RedirectResponse(
                route('account.pending-approval', absolute: false)
            );
        }

        $reservationIntentUrl = ReservationIntentSession::redirectUrl($request);

        if ($reservationIntentUrl && $user->isClient()) {
            if (! $user->hasVerifiedEmail()) {
                return new RedirectResponse(
                    route('verification.notice', absolute: false)
                );
            }

            ReservationIntentSession::forget($request);

            return new RedirectResponse($reservationIntentUrl);
        }

        if ($user->isSuperAdmin()) {
            return new RedirectResponse(
                $this->intendedPath($request, '/superAdmin')
            );
        }

        if ($user->isAdmin()) {
            return new RedirectResponse(
                $this->intendedPath($request, '/panel/rezerwacje')
            );
        }

        if ($user->isEmployee()) {
            return new RedirectResponse(
                $this->intendedPath($request, '/panel/rezerwacje')
            );
        }

        return new RedirectResponse(
            $this->intendedPath($request, '/klient')
        );

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

    private function intendedPath(Request $request, string $fallback): string
    {
        $intendedUrl = $request->session()->pull('url.intended');

        if (! is_string($intendedUrl) || trim($intendedUrl) === '') {
            return $fallback;
        }

        $parts = parse_url($intendedUrl);

        if ($parts === false) {
            return $fallback;
        }

        $path = '/'.ltrim((string) ($parts['path'] ?? '/'), '/');
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';

        return $path.$query.$fragment;
    }
}
