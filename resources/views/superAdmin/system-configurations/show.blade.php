<x-app-layout>
    <div class="page-bg-2"></div>

    @include('components.breadcrumb')

    @php
        $details = [
            'Nazwa firmy 1' => $systemConfiguration->company_name_1,
            'Nazwa firmy 2' => $systemConfiguration->company_name_2,
            'Nazwa firmy 3' => $systemConfiguration->company_name_3,
            'Ulica' => $systemConfiguration->street,
            'Numer budynku' => $systemConfiguration->building_number,
            'Numer lokalu' => $systemConfiguration->unit_number,
            'Kod pocztowy' => $systemConfiguration->postal_code,
            'Miasto' => $systemConfiguration->city,
            'Województwo' => $systemConfiguration->voivodeship,
            'Telefon 1' => $systemConfiguration->phone_1,
            'Telefon 2' => $systemConfiguration->phone_2,
            'Faks' => $systemConfiguration->fax,
            'E-mail' => $systemConfiguration->email,
            'Bank' => $systemConfiguration->bank_name,
            'Numer rachunku' => $systemConfiguration->formatted_bank_account_number,
            'Domyślny termin rezerwacji' => $systemConfiguration->default_max_advance_booking_days . ' dni',
            'Rezerwacje w tabeli bieżącej' => $systemConfiguration->reservation_operational_months . ' mies.',
            'Łączny okres historii rezerwacji' => $systemConfiguration->reservation_history_months . ' mies.',
        ];
    @endphp

    <div class="container py-4">
        <x-session-alerts />

        <div class="p-3 mb-3 bg-white shadow-sm d-flex justify-content-between align-items-center">
            <div>
                <h1 class="mb-1">Konfiguracja #{{ $systemConfiguration->id }}</h1>
                <span class="badge {{ $systemConfiguration->is_archived ? 'text-bg-secondary' : 'text-bg-success' }}">
                    {{ $systemConfiguration->is_archived ? 'Archiwalna' : 'Aktywna' }}
                </span>
            </div>

            <div class="gap-2 d-flex">
                <a href="{{ route('admin.system-configurations.edit', $systemConfiguration) }}" class="btn btn-primary">
                    <i class="bi bi-pencil me-1"></i>
                    Edytuj
                </a>
                <a href="{{ route('admin.system-configurations.index') }}" class="btn btn-outline-secondary">
                    Wróć
                </a>
            </div>
        </div>

        <dl class="p-3 mb-0 bg-white shadow-sm row">
            @foreach ($details as $label => $value)
                <dt class="py-2 col-md-4 border-bottom">{{ $label }}</dt>
                <dd class="py-2 col-md-8 border-bottom">{{ $value ?: '—' }}</dd>
            @endforeach

            <dt class="py-2 col-md-4">Data utworzenia</dt>
            <dd class="py-2 col-md-8">{{ $systemConfiguration->created_at?->format('d.m.Y H:i:s') }}</dd>
        </dl>
    </div>
</x-app-layout>
