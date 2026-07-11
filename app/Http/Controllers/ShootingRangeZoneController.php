<?php

namespace App\Http\Controllers;

use App\Mail\ShootingRangeZoneAdminMail;
use App\Mail\ShootingRangeZoneConfirmationMail;
use App\Models\SystemConfiguration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class ShootingRangeZoneController extends Controller
{
    public function show(): View
    {
        $configuration = $this->activeConfiguration();

        return view('shooting-range-zone', compact('configuration'));
    }

    public function send(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'source' => ['required', 'string', 'in:hero,offer,cta'],
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'shooting_range_name' => ['required', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'message' => ['nullable', 'string', 'max:3000'],
        ], [
            'name.required' => 'Podaj imię i nazwisko.',
            'email.required' => 'Podaj adres e-mail.',
            'email.email' => 'Podaj poprawny adres e-mail.',
            'shooting_range_name.required' => 'Podaj nazwę strzelnicy.',
        ]);

        $configuration = $this->activeConfiguration();
        $recipient = $configuration?->email ?: config('mail.from.address');

        if (! $recipient) {
            return back()
                ->withInput()
                ->with('error', 'Adres odbiorcy formularza nie został skonfigurowany.');
        }

        Mail::to($recipient)->send(
            new ShootingRangeZoneAdminMail($validated)
        );

        Mail::to($validated['email'])->send(
            new ShootingRangeZoneConfirmationMail($validated)
        );

        return back()
            ->with('success', 'Zgłoszenie zostało wysłane. Kopia wiadomości trafiła na podany adres e-mail.')
            ->with('submitted_source', $validated['source']);
    }

    private function activeConfiguration(): ?SystemConfiguration
    {
        return SystemConfiguration::query()
            ->active()
            ->latest('id')
            ->first();
    }
}
