<?php

namespace App\Http\Controllers;

use App\Models\Regulation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RequiredRegulationController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $regulations = $request->user()->requiredRegulations();

        if ($regulations->isEmpty()) {
            return redirect()->route('index');
        }

        return view('regulations.required', compact('regulations'));
    }

    public function accept(Request $request): RedirectResponse
    {
        $regulations = $request->user()->requiredRegulations();

        $validated = $request->validate([
            'accepted' => ['required', 'array'],
            'accepted.*' => ['required', 'integer'],
        ], [
            'accepted.required' => 'Zaakceptuj wszystkie wymagane regulaminy.',
        ]);

        $acceptedIds = collect($validated['accepted'])->map(fn ($id) => (int) $id);

        foreach ($regulations as $regulation) {
            if (! $acceptedIds->contains($regulation->id)) {
                return back()
                    ->withErrors(['accepted' => 'Zaakceptuj wszystkie wymagane regulaminy.'])
                    ->withInput();
            }
        }

        foreach ($regulations as $regulation) {
            $version = $regulation->currentVersion;

            if (! $version) {
                continue;
            }

            $request->user()->regulationAcceptances()->firstOrCreate([
                'regulation_version_id' => $version->id,
            ], [
                'regulation_id' => $regulation->id,
                'accepted_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 2000),
            ]);
        }

        return redirect()
            ->intended(route('index'))
            ->with('success', 'Wymagane regulaminy zostały zaakceptowane.');
    }

    public function show(Regulation $regulation): View
    {
        abort_unless(
            $regulation->currentVersion && $regulation->requiresAcceptanceFrom(request()->user()),
            404
        );

        return view('regulations.show', compact('regulation'));
    }
}
