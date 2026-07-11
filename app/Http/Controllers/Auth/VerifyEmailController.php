<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\ReservationIntentSession;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     */
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            if ($reservationIntentUrl = ReservationIntentSession::redirectUrl($request)) {
                ReservationIntentSession::forget($request);

                return redirect()->to($reservationIntentUrl);
            }

            return redirect()->intended(route('dashboard', absolute: false).'?verified=1');
        }

        if ($request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));
        }

        if ($reservationIntentUrl = ReservationIntentSession::redirectUrl($request)) {
            ReservationIntentSession::forget($request);

            return redirect()->to($reservationIntentUrl);
        }

        return redirect()->intended(route('dashboard', absolute: false).'?verified=1');
    }
}
