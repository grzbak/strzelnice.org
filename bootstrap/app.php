<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function ($middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\ClearReservationIntentOutsideAuthFlow::class,
            \App\Http\Middleware\EnsureRequiredRegulationsAccepted::class,
        ]);

        $middleware->alias([
            'approved' => \App\Http\Middleware\ApprovedUser::class,
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'range.step' => \App\Http\Middleware\EnsureRangeStep::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (TokenMismatchException $exception, Request $request) {
            $message = $request->user()
                ? 'Formularz stracił ważność. Odśwież stronę i spróbuj ponownie.'
                : 'Sesja wygasła. Zaloguj się ponownie.';

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                ], 419);
            }

            if ($request->user()) {
                return back()
                    ->with('status', $message)
                    ->with('status_type', 'warning');
            }

            return redirect()
                ->route('login')
                ->with('status', $message)
                ->with('status_type', 'warning');
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if ($request->expectsJson()) {
                return null;
            }

            $redirect = redirect()->guest(route('login'));

            if ($request->hasCookie(config('session.cookie'))) {
                $redirect->with('status', 'Sesja wygasła. Zaloguj się ponownie, aby kontynuować pracę.')
                    ->with('status_type', 'warning');
            }

            return $redirect;
        });
    })->create();
