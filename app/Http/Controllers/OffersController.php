<?php

namespace App\Http\Controllers;

use App\Mail\OffersAdminMail;
use App\Mail\OffersConfirmationMail;
use App\Models\SystemConfiguration;
use App\Services\UserSecurityService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class OffersController extends Controller
{
    public function show(): View
    {
        $configuration = $this->activeConfiguration();

        return view('offers', compact('configuration'));
    }

    public function send(
        Request $request,
        UserSecurityService $security
    ): RedirectResponse {
        $validated = $request->validate([
            'source' => ['required', 'string', 'in:hero,offer,cta'],
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'shooting_range_name' => ['required', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'message' => ['nullable', 'string', 'max:3000'],
            'g-recaptcha-response' => ['required', 'string'],
        ], [
            'name.required' => 'Podaj imię i nazwisko.',
            'email.required' => 'Podaj adres e-mail.',
            'email.email' => 'Podaj poprawny adres e-mail.',
            'shooting_range_name.required' => 'Podaj nazwę strzelnicy.',
            'g-recaptcha-response.required' => 'Weryfikacja reCAPTCHA jest wymagana.',
        ]);

        if (! $this->recaptchaIsValid($request)) {
            return back()
                ->withErrors([
                    'g-recaptcha-response' => 'Weryfikacja reCAPTCHA nie powiodła się.',
                ])
                ->withInput();
        }

        $configuration = $this->activeConfiguration();
        $recipient = $configuration?->email ?: config('mail.from.address');

        if (! $recipient) {
            return back()
                ->withInput()
                ->with('error', 'Adres odbiorcy formularza nie został skonfigurowany.');
        }

        Mail::to($recipient)->send(
            new OffersAdminMail($validated)
        );

        Mail::to($validated['email'])->send(
            new OffersConfirmationMail($validated)
        );

        $security->audit('offers_form.sent', $request->user(), $request, payload: [
            'email' => $validated['email'],
            'source' => $validated['source'],
            'shooting_range_name' => $validated['shooting_range_name'],
        ]);

        return back()
            ->with('success', 'Zgłoszenie zostało wysłane. Kopia wiadomości trafiła na podany adres e-mail.')
            ->with('submitted_source', $validated['source']);
    }

    private function recaptchaIsValid(Request $request): bool
    {
        try {
            $response = Http::asForm()
                ->timeout(10)
                ->post('https://www.google.com/recaptcha/api/siteverify', [
                    'secret' => config('services.recaptcha.secret_key'),
                    'response' => $request->input('g-recaptcha-response'),
                    'remoteip' => $request->ip(),
                ]);
        } catch (ConnectionException) {
            return false;
        }

        $recaptcha = $response->json();

        return ! empty($recaptcha['success'])
            && ($recaptcha['score'] ?? 0) >= 0.5
            && ($recaptcha['action'] ?? '') === 'offers';
    }

    private function activeConfiguration(): ?SystemConfiguration
    {
        return SystemConfiguration::query()
            ->active()
            ->latest('id')
            ->first();
    }
}
