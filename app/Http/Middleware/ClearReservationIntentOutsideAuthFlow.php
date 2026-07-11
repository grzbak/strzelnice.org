<?php

namespace App\Http\Middleware;

use App\Support\ReservationIntentSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ClearReservationIntentOutsideAuthFlow
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->has('reservation_intent')) {
            return $next($request);
        }

        if (! ReservationIntentSession::valid($request)) {
            return $next($request);
        }

        if (! $this->keepsReservationIntent($request)) {
            ReservationIntentSession::forget($request);
        }

        return $next($request);
    }

    private function keepsReservationIntent(Request $request): bool
    {
        return $request->routeIs(
            'login',
            'register',
            'verification.*',
            'rezerwacje.intent.store'
        ) || $request->is(
            'login',
            'register',
            'verify-email',
            'verify-email/*',
            'email/verification-notification',
            'rezerwacje/intencja'
        );
    }
}
