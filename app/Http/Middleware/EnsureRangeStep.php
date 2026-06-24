<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureRangeStep
{
    public function handle(Request $request, Closure $next, int $requiredStep)
    {
        $form = session('shooting_range_form');

        if (!$form || ($form['step'] ?? 1) < $requiredStep) {
            return redirect()
                ->route('range.step'.($requiredStep-1))
                ->with('error', 'Najpierw uzupełnij poprzedni krok formularza.');
        }
        return $next($request);
    }
}
