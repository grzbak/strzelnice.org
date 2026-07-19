<?php

namespace App\Http\Controllers;

use App\Models\SystemConfiguration;
use App\Rules\PolishBankAccountNumber;
use App\Services\UserSecurityService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class SystemConfigurationController extends Controller
{
    public function index(Request $request): View
    {
        $showArchived = $request->boolean('show_archived');

        $configurations = SystemConfiguration::query()
            ->when(! $showArchived, fn ($query) => $query->active())
            ->latest('id')
            ->get();

        return view('superAdmin.system-configurations.index', compact(
            'configurations',
            'showArchived'
        ));
    }

    public function show(SystemConfiguration $systemConfiguration): View
    {
        return view('superAdmin.system-configurations.show', compact('systemConfiguration'));
    }

    public function edit(SystemConfiguration $systemConfiguration): View
    {
        return view('superAdmin.system-configurations.edit', compact('systemConfiguration'));
    }

    public function update(
        Request $request,
        SystemConfiguration $systemConfiguration
    ): RedirectResponse {
        $validated = $this->validateConfiguration($request);

        $newConfiguration = DB::transaction(function () use (
            $validated
        ): SystemConfiguration {
            $this->archiveActiveConfigurations();

            return SystemConfiguration::create([
                ...$validated,
                'is_archived' => false,
            ]);
        });

        return redirect()
            ->route('admin.system-configurations.show', $newConfiguration)
            ->with('success', 'Zapisano nowÄ… aktywnÄ… wersjÄ™ konfiguracji.');
    }

    public function restore(
        SystemConfiguration $systemConfiguration
    ): RedirectResponse {
        $newConfiguration = DB::transaction(function () use (
            $systemConfiguration
        ): SystemConfiguration {
            $this->archiveActiveConfigurations();

            return SystemConfiguration::create([
                ...$systemConfiguration->only(SystemConfiguration::DATA_FIELDS),
                'is_archived' => false,
            ]);
        });

        return redirect()
            ->route('admin.system-configurations.show', $newConfiguration)
            ->with('success', 'PrzywrĂłcono konfiguracjÄ™ jako nowÄ… aktywnÄ… wersjÄ™.');
    }

    public function contact(): View
    {
        $configuration = SystemConfiguration::query()
            ->active()
            ->latest('id')
            ->first();

        return view('contact', compact('configuration'));
    }

    public function sendContact(
        Request $request,
        UserSecurityService $security
    ): RedirectResponse {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'subject' => ['required', 'string', 'max:200'],
            'message' => ['required', 'string', 'max:5000'],
            'g-recaptcha-response' => ['required', 'string'],
        ], [
            'g-recaptcha-response.required' => 'Weryfikacja reCAPTCHA jest wymagana.',
        ]);

        if (! $this->recaptchaIsValid($request)) {
            return back()
                ->withErrors([
                    'g-recaptcha-response' => 'Weryfikacja reCAPTCHA nie powiodĹ‚a siÄ™.',
                ])
                ->withInput();
        }

        $recipient = SystemConfiguration::query()
            ->active()
            ->latest('id')
            ->value('email') ?: config('mail.from.address');

        if (! $recipient) {
            return back()
                ->withInput()
                ->with('error', 'Adres odbiorcy formularza kontaktowego nie zostaĹ‚ skonfigurowany.');
        }

        $body = implode(PHP_EOL, array_filter([
            'Nadawca: '.$validated['name'],
            'E-mail: '.$validated['email'],
            filled($validated['phone'] ?? null)
                ? 'Telefon: '.$validated['phone']
                : null,
            '',
            $validated['message'],
        ], fn ($line) => $line !== null));

        Mail::raw($body, function ($mail) use ($recipient, $validated): void {
            $mail->to($recipient)
                ->replyTo($validated['email'], $validated['name'])
                ->subject('Formularz kontaktowy: '.$validated['subject']);
        });
        $security->audit('contact_form.sent', $request->user(), $request, payload: [
            'email' => $validated['email'],
            'subject' => $validated['subject'],
        ]);

        return back()->with('success', 'WiadomoĹ›Ä‡ zostaĹ‚a wysĹ‚ana.');
    }

    private function validateConfiguration(Request $request): array
    {
        $request->merge([
            'bank_account_number' => SystemConfiguration::normalizeBankAccountNumber(
                $request->input('bank_account_number')
            ),
            'ip_retention_months' => $request->input(
                'ip_retention_months',
                SystemConfiguration::DEFAULT_IP_RETENTION_MONTHS
            ),
            'audit_retention_months' => $request->input(
                'audit_retention_months',
                SystemConfiguration::DEFAULT_AUDIT_RETENTION_MONTHS
            ),
            'security_events_retention_months' => $request->input(
                'security_events_retention_months',
                SystemConfiguration::DEFAULT_SECURITY_EVENTS_RETENTION_MONTHS
            ),
        ]);

        return $request->validate([
            'company_name_1' => ['nullable', 'string', 'max:255'],
            'company_name_2' => ['nullable', 'string', 'max:255'],
            'company_name_3' => ['nullable', 'string', 'max:255'],
            'street' => ['nullable', 'string', 'max:255'],
            'building_number' => ['nullable', 'string', 'max:30'],
            'unit_number' => ['nullable', 'string', 'max:30'],
            'postal_code' => ['nullable', 'string', 'regex:/^\d{2}-\d{3}$/'],
            'city' => ['nullable', 'string', 'max:255'],
            'voivodeship' => ['nullable', 'string', 'max:100'],
            'phone_1' => ['nullable', 'string', 'max:50'],
            'phone_2' => ['nullable', 'string', 'max:50'],
            'fax' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account_number' => ['nullable', new PolishBankAccountNumber],
            'default_max_advance_booking_days' => ['required', 'integer', 'min:0', 'max:365'],
            'reservation_operational_months' => [
                'required',
                'integer',
                'min:1',
                'max:240',
                'lte:reservation_history_months',
            ],
            'reservation_history_months' => [
                'required',
                'integer',
                'min:1',
                'max:240',
            ],
            'ip_retention_months' => ['required', 'integer', 'min:1', 'max:240'],
            'audit_retention_months' => ['required', 'integer', 'min:1', 'max:240'],
            'security_events_retention_months' => ['required', 'integer', 'min:1', 'max:240'],
        ], [
            'reservation_operational_months.lte' => 'Okres przechowywania rezerwacji bieĹĽÄ…cych nie moĹĽe byÄ‡ dĹ‚uĹĽszy niĹĽ Ĺ‚Ä…czny okres historii.',
            'postal_code.regex' => 'Kod pocztowy musi mieÄ‡ format 00-000.',
        ]);
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
            && ($recaptcha['action'] ?? '') === 'contact';
    }

    private function archiveActiveConfigurations(): void
    {
        $activeIds = SystemConfiguration::query()
            ->active()
            ->lockForUpdate()
            ->pluck('id');

        if ($activeIds->isNotEmpty()) {
            SystemConfiguration::query()
                ->whereKey($activeIds)
                ->update(['is_archived' => true]);
        }
    }
}
