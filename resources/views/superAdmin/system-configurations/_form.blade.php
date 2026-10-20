@csrf

@php
    $fields = [
        'Dane firmy' => [
            ['company_name_1', 'Nazwa firmy 1'],
            ['company_name_2', 'Nazwa firmy 2'],
            ['company_name_3', 'Nazwa firmy 3'],
        ],
        'Adres' => [
            ['street', 'Ulica'],
            ['building_number', 'Numer budynku'],
            ['unit_number', 'Numer lokalu'],
            ['postal_code', 'Kod pocztowy'],
            ['city', 'Miasto'],
            ['voivodeship', 'Województwo'],
        ],
        'Kontakt' => [
            ['phone_1', 'Telefon 1'],
            ['phone_2', 'Telefon 2'],
            ['fax', 'Faks'],
            ['email', 'E-mail', 'email'],
        ],
        'Dane bankowe' => [
            ['bank_name', 'Bank'],
            ['bank_account_number', 'Numer rachunku bankowego'],
        ],
        'Rezerwacje' => [
            [
                'default_max_advance_booking_days',
                'Domyślny maksymalny termin rezerwacji (dni)',
                'number',
            ],
            [
                'reservation_operational_months',
                'Rezerwacje w tabeli bieżącej (miesiące)',
                'number',
            ],
            [
                'reservation_history_months',
                'Łączny okres przechowywania historii (miesiące)',
                'number',
            ],
        ],
        'Bezpieczeństwo i audyt' => [
            [
                'ip_retention_months',
                'Okres przechowywania adresów IP (miesiące)',
                'number',
            ],
            [
                'audit_retention_months',
                'Okres przechowywania audytu działań (miesiące)',
                'number',
            ],
            [
                'security_events_retention_months',
                'Okres przechowywania zdarzeń bezpieczeństwa (miesiące)',
                'number',
            ],
        ],
        'Analityka' => [
            [
                'google_analytics_id',
                'Google Analytics ID',
            ],
        ],
        'Raporty' => [
            ['reports_enabled', 'Włącz moduł raportów', 'checkbox'],
        ],
        'SEO domyślne' => [
            ['default_meta_title', 'Domyślny tytuł strony'],
            ['default_meta_description', 'Domyślny opis meta'],
            ['default_meta_keywords', 'Domyślne słowa kluczowe'],
            ['default_og_title', 'Domyślny tytuł Open Graph'],
            ['default_og_description', 'Domyślny opis Open Graph'],
            ['default_og_image', 'Domyślny obraz Open Graph'],
            ['default_robots', 'Domyślne robots'],
        ],
        'Platnosci i abonamenty' => [
            ['p24_enabled', 'Wlacz platnosci Przelewy24', 'checkbox'],
            ['p24_sandbox', 'Tryb testowy Przelewy24', 'checkbox'],
            ['p24_merchant_id', 'P24 Merchant ID'],
            ['p24_pos_id', 'P24 POS ID'],
            ['p24_crc', 'P24 CRC'],
            ['p24_api_key', 'P24 API key'],
            ['p24_currency', 'Waluta P24'],
            ['p24_time_limit', 'Limit czasu platnosci P24 (minuty)', 'number'],
            ['subscription_payment_due_days', 'Termin oplaty abonamentu (dni)', 'number'],
            ['conditional_access_charge_due_days', 'Termin oplaty rachunku za dostep warunkowy (dni)', 'number'],
            ['conditional_access_charge_mode', 'Rozliczenie nieoplaconego dostepu'],
        ],
    ];

    $fieldDefaults = [
        'default_max_advance_booking_days' => 30,
        'reservation_operational_months' => \App\Models\SystemConfiguration::DEFAULT_RESERVATION_OPERATIONAL_MONTHS,
        'reservation_history_months' => \App\Models\SystemConfiguration::DEFAULT_RESERVATION_HISTORY_MONTHS,
        'ip_retention_months' => \App\Models\SystemConfiguration::DEFAULT_IP_RETENTION_MONTHS,
        'audit_retention_months' => \App\Models\SystemConfiguration::DEFAULT_AUDIT_RETENTION_MONTHS,
        'security_events_retention_months' => \App\Models\SystemConfiguration::DEFAULT_SECURITY_EVENTS_RETENTION_MONTHS,
        'reports_enabled' => true,
        'default_robots' => 'index,follow',
        'p24_sandbox' => true,
        'p24_currency' => 'PLN',
        'subscription_payment_due_days' => 7,
        'conditional_access_charge_due_days' => 7,
        'conditional_access_charge_mode' => 'proportional',
    ];
@endphp

@foreach ($fields as $section => $sectionFields)
    <h2 class="mt-4 mb-3 h5">{{ $section }}</h2>

    <div class="row g-3">
        @foreach ($sectionFields as $field)
            @php
                [$name, $label, $type] = array_pad($field, 3, 'text');
                $currentValue = old($name, $systemConfiguration->{$name} ?? $fieldDefaults[$name] ?? null);
            @endphp
            <div class="{{ in_array($name, ['bank_account_number', 'p24_crc', 'p24_api_key', 'default_meta_description', 'default_meta_keywords', 'default_og_description'], true) ? 'col-12' : 'col-md-6' }}">
                <label for="{{ $name }}" class="form-label">{{ $label }}</label>
                @if ($type === 'checkbox')
                    <input type="hidden" name="{{ $name }}" value="0">
                    <div class="form-check form-switch">
                        <input
                            type="checkbox"
                            id="{{ $name }}"
                            name="{{ $name }}"
                            value="1"
                            class="form-check-input @error($name) is-invalid @enderror"
                            @checked((bool) $currentValue)
                        >
                    </div>
                @elseif ($name === 'conditional_access_charge_mode')
                    <select
                        id="{{ $name }}"
                        name="{{ $name }}"
                        class="form-select @error($name) is-invalid @enderror"
                    >
                        <option value="proportional" @selected($currentValue === 'proportional')>
                            Proporcjonalnie za wykorzystane dni
                        </option>
                        <option value="full" @selected($currentValue === 'full')>
                            Pelna wartosc abonamentu
                        </option>
                    </select>
                @elseif ($name === 'default_robots')
                    <select
                        id="{{ $name }}"
                        name="{{ $name }}"
                        class="form-select @error($name) is-invalid @enderror"
                    >
                        @foreach (['index,follow', 'noindex,follow', 'index,nofollow', 'noindex,nofollow'] as $robotsOption)
                            <option value="{{ $robotsOption }}" @selected($currentValue === $robotsOption)>
                                {{ $robotsOption }}
                            </option>
                        @endforeach
                    </select>
                @else
                <input
                    type="{{ $type }}"
                    id="{{ $name }}"
                    name="{{ $name }}"
                    value="{{ $name === 'bank_account_number'
                        ? \App\Models\SystemConfiguration::formatBankAccountNumber(
                            $currentValue
                        )
                        : $currentValue }}"
                    class="form-control @error($name) is-invalid @enderror"
                    @if ($name === 'postal_code') placeholder="00-000" @endif
                    @if ($name === 'bank_account_number')
                        placeholder="00 0000 0000 0000 0000 0000 0000"
                        inputmode="numeric"
                        maxlength="32"
                        autocomplete="off"
                    @endif
                    @if ($name === 'default_max_advance_booking_days')
                        min="0"
                        max="365"
                        required
                    @endif
                    @if (in_array($name, ['reservation_operational_months', 'reservation_history_months'], true))
                        min="1"
                        max="240"
                        required
                    @endif
                    @if (in_array($name, ['ip_retention_months', 'audit_retention_months', 'security_events_retention_months'], true))
                        min="1"
                        max="240"
                        required
                    @endif
                    @if ($name === 'p24_time_limit')
                        min="0"
                        max="99"
                    @endif
                    @if (in_array($name, ['subscription_payment_due_days', 'conditional_access_charge_due_days'], true))
                        min="1"
                    @endif
                >
                @endif
                @if ($name === 'bank_account_number')
                    <div class="form-text">
                        Wpisz 26 cyfr. Możesz również wkleić numer ze spacjami lub prefiksem PL.
                    </div>
                @endif
                @if ($name === 'google_analytics_id')
                    <div class="form-text">
                        Wpisz identyfikator pomiaru GA4, np. G-XXXXXXXXXX. Pozostaw puste, aby wyłączyć Google Analytics.
                    </div>
                @endif
                @if ($name === 'default_meta_title')
                    <div class="form-text">
                        Używany, gdy konkretna strona lub strzelnica nie ma własnego tytułu SEO.
                    </div>
                @endif
                @if ($name === 'default_meta_description')
                    <div class="form-text">
                        Zalecana długość: około 140-160 znaków.
                    </div>
                @endif
                @if ($name === 'default_og_image')
                    <div class="form-text">
                        Podaj pełny URL lub ścieżkę względną, np. img/og-default.jpg.
                    </div>
                @endif
                @if ($name === 'p24_time_limit')
                    <div class="form-text">
                        Limit P24 podawany jest w minutach. Wartość 0 oznacza brak limitu po stronie P24, maksymalnie 99 minut.
                    </div>
                @endif
                @if ($name === 'default_max_advance_booking_days')
                    <div class="form-text">
                        Wartość 0 pozwala na rezerwację wyłącznie na dzisiaj.
                    </div>
                @endif
                @if ($name === 'reservation_operational_months')
                    <div class="form-text">
                        Po tym czasie zakończone rezerwacje zostaną przeniesione do archiwum.
                    </div>
                @endif
                @if ($name === 'reservation_history_months')
                    <div class="form-text">
                        Po tym czasie, liczonym od końca rezerwacji, dane zostaną trwale usunięte.
                    </div>
                @endif
                @if ($name === 'ip_retention_months')
                    <div class="form-text">
                        Po tym czasie nieużywane adresy IP użytkowników zostaną usunięte.
                    </div>
                @endif
                @if ($name === 'audit_retention_months')
                    <div class="form-text">
                        Po tym czasie zwykłe wpisy audytu zostaną usunięte.
                    </div>
                @endif
                @if ($name === 'security_events_retention_months')
                    <div class="form-text">
                        Dotyczy zamkniętych i wygasłych zdarzeń bezpieczeństwa. Aktywne bany pozostają w systemie.
                    </div>
                @endif
                <x-input-error :messages="$errors->get($name)" class="mt-1" />
            </div>
        @endforeach
    </div>
@endforeach

<div class="gap-2 mt-4 d-flex">
    <button type="button" class="btn btn-outline-danger" id="testP24Configuration">
        <i class="bi bi-wifi me-1"></i>
        Testuj P24
    </button>

    <button type="submit" class="btn btn-success">
        <i class="bi bi-save me-1"></i>
        Zapisz jako nową wersję
    </button>

    <a href="{{ route('admin.system-configurations.index') }}" class="btn btn-outline-secondary">
        Anuluj
    </a>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const accountInput = document.getElementById('bank_account_number');
        const p24TestButton = document.getElementById('testP24Configuration');

        if (!accountInput) {
            return;
        }

        accountInput.addEventListener('input', function () {
            const digits = accountInput.value
                .toUpperCase()
                .replace(/^PL/, '')
                .replace(/\D/g, '')
                .slice(0, 26);

            const prefix = digits.slice(0, 2);
            const groups = digits.slice(2).match(/.{1,4}/g) ?? [];

            accountInput.value = [prefix, ...groups]
                .filter(Boolean)
                .join(' ');
        });

        if (p24TestButton) {
            p24TestButton.addEventListener('click', async function () {
                const form = p24TestButton.closest('form');
                const originalText = p24TestButton.innerHTML;
                const formData = new FormData(form);
                formData.delete('_method');

                p24TestButton.disabled = true;
                p24TestButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Testowanie...';

                form.querySelectorAll('[data-p24-test-alert]').forEach(alert => alert.remove());

                try {
                    const response = await fetch(@json(route('admin.system-configurations.test-p24')), {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': formData.get('_token'),
                        },
                        body: formData,
                    });

                    const result = await response.json();
                    const alert = document.createElement('div');
                    alert.dataset.p24TestAlert = 'true';
                    alert.className = `mt-3 alert ${response.ok && result.ok ? 'alert-success' : 'alert-danger'}`;
                    alert.textContent = result.message || 'Nie udalo sie wykonac testu Przelewy24.';
                    p24TestButton.closest('.d-flex').insertAdjacentElement('beforebegin', alert);
                } catch (error) {
                    const alert = document.createElement('div');
                    alert.dataset.p24TestAlert = 'true';
                    alert.className = 'mt-3 alert alert-danger';
                    alert.textContent = 'Nie udalo sie wykonac testu Przelewy24.';
                    p24TestButton.closest('.d-flex').insertAdjacentElement('beforebegin', alert);
                } finally {
                    p24TestButton.disabled = false;
                    p24TestButton.innerHTML = originalText;
                }
            });
        }
    });
</script>
