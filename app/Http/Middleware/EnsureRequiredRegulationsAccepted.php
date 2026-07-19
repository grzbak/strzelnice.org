<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class EnsureRequiredRegulationsAccepted
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            ! $user
            || $this->isAllowedRoute($request)
            || ! Schema::hasTable('regulations')
            || ! Schema::hasTable('regulation_versions')
            || ! Schema::hasTable('regulation_acceptances')
        ) {
            return $next($request);
        }

        if ($user->hasRequiredRegulationsToAccept()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Przed wykonaniem tej akcji zaakceptuj wymagane regulaminy.',
                ], 423);
            }

            return redirect()
                ->route('regulations.required')
                ->with('warning', 'Przed kontynuacją zaakceptuj wymagane regulaminy.');
        }

        return $next($request);
    }

    private function isAllowedRoute(Request $request): bool
    {
        $routeName = (string) $request->route()?->getName();

        if ($request->isMethod('GET') && (
            $routeName === 'regulations.required'
            || $routeName === 'regulations.required.show'
            || $routeName === 'profile.edit'
            || $routeName === 'rezerwacje.mine'
            || $routeName === 'account.pending-approval'
            || $routeName === 'logout'
        )) {
            return true;
        }

        return in_array($routeName, [
            'regulations.required.accept',
            'profile.update',
            'profile.destroy',
            'logout',
            'verification.notice',
            'verification.send',
            'verification.verify',
            'account.pending-approval',
        ], true);
    }
}
