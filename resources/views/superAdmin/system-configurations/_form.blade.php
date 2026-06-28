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
        ],
    ];
@endphp

@foreach ($fields as $section => $sectionFields)
    <h2 class="mt-4 mb-3 h5">{{ $section }}</h2>

    <div class="row g-3">
        @foreach ($sectionFields as $field)
            @php([$name, $label, $type] = array_pad($field, 3, 'text'))
            <div class="{{ $name === 'bank_account_number' ? 'col-12' : 'col-md-6' }}">
                <label for="{{ $name }}" class="form-label">{{ $label }}</label>
                <input
                    type="{{ $type }}"
                    id="{{ $name }}"
                    name="{{ $name }}"
                    value="{{ $name === 'bank_account_number'
                        ? \App\Models\SystemConfiguration::formatBankAccountNumber(
                            old($name, $systemConfiguration->{$name})
                        )
                        : old($name, $systemConfiguration->{$name}) }}"
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
                >
                @if ($name === 'bank_account_number')
                    <div class="form-text">
                        Wpisz 26 cyfr. Możesz również wkleić numer ze spacjami lub prefiksem PL.
                    </div>
                @endif
                @if ($name === 'default_max_advance_booking_days')
                    <div class="form-text">
                        Wartość 0 pozwala na rezerwację wyłącznie na dzisiaj.
                    </div>
                @endif
                <x-input-error :messages="$errors->get($name)" class="mt-1" />
            </div>
        @endforeach
    </div>
@endforeach

<div class="gap-2 mt-4 d-flex">
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
    });
</script>
