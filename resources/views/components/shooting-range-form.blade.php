@props([
    'values' => [],
    'submitLabel' => 'Zapisz strzelnicę',
])

@php
    $fieldValue = fn (string $name, mixed $default = '') =>
        old($name, data_get($values, $name, $default));

    $voivodeships = [
        'dolnośląskie',
        'kujawsko-pomorskie',
        'lubelskie',
        'lubuskie',
        'łódzkie',
        'małopolskie',
        'mazowieckie',
        'opolskie',
        'podkarpackie',
        'podlaskie',
        'pomorskie',
        'śląskie',
        'świętokrzyskie',
        'warmińsko-mazurskie',
        'wielkopolskie',
        'zachodniopomorskie',
    ];

    $sections = [
        'Dane podstawowe' => [
            ['name' => 'name', 'label' => 'Nazwa strzelnicy', 'required' => true],
            ['name' => 'town', 'label' => 'Miasto', 'required' => true],
        ],
        'Adres' => [
            ['name' => 'street', 'label' => 'Ulica'],
            ['name' => 'houseNumber', 'label' => 'Nr budynku'],
            ['name' => 'apartamentNumber', 'label' => 'Nr lokalu'],
            ['name' => 'plotNumber', 'label' => 'Nr działki'],
            ['name' => 'zipCode', 'label' => 'Kod pocztowy'],
        ],
        'Lokalizacja na mapie' => [
            ['name' => 'navLink', 'label' => 'Link Google Maps', 'required' => true],
            ['name' => 'lat', 'label' => 'Szerokość geograficzna', 'required' => true],
            ['name' => 'lng', 'label' => 'Długość geograficzna', 'required' => true],
        ],
        'Kontakt' => [
            ['name' => 'email', 'label' => 'Email', 'type' => 'email'],
            ['name' => 'telephone1', 'label' => 'Telefon'],
            ['name' => 'telephone2', 'label' => 'Telefon dodatkowy'],
            ['name' => 'url', 'label' => 'Strona WWW'],
            ['name' => 'facebook', 'label' => 'Facebook'],
            ['name' => 'instagram', 'label' => 'Instagram'],
        ],
    ];
@endphp

@if ($errors->any())
    <div class="mb-4 alert alert-danger">
        <strong>Formularz zawiera błędy.</strong>

        <ul class="mt-2 mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<h2 class="mt-6 mb-2 font-semibold">Brand / logo</h2>

@if (! empty($values['brand_file_name'] ?? null))
    <div class="mb-3">
        <img
            src="{{ asset('files/brands/' . $values['brand_file_name']) }}"
            alt="{{ $values['brand_original_name'] ?? 'Logo strzelnicy' }}"
            style="max-height: 120px;"
        >

        <div class="mt-1 text-sm text-muted">
            Aktualny plik: {{ $values['brand_original_name'] }}
        </div>

        <input type="hidden" name="remove_brand" value="0">

        <label class="inline-flex items-center gap-2 mt-3 text-sm">
            <input
                type="checkbox"
                name="remove_brand"
                value="1"
                @checked(old('remove_brand'))
                class="border-gray-300 rounded text-danger focus:ring-danger"
            >
            Usuń aktualne logo
        </label>

        <div class="mt-1 text-sm text-muted">
            Logo zostanie usunięte po zapisaniu formularza.
        </div>
    </div>
@endif

<div class="mb-4">
    <input
        type="file"
        name="brand"
        accept=".jpg,.jpeg,.png,image/jpeg,image/png"
        class="w-full border-gray-300 rounded-md focus:border-indigo-500 focus:ring-indigo-500"
    >

    <div class="mt-1 text-sm text-muted">
        Dozwolone formaty: JPG, PNG. Maksymalnie 300 KB.
    </div>

    <x-input-error :messages="$errors->get('brand')" class="mt-1"/>
</div>

@foreach ($sections as $section => $fields)
    <h2 class="mt-6 mb-3 font-semibold">{{ $section }}</h2>

    <div class="grid grid-cols-1 gap-4 mb-4 md:grid-cols-2">
        @foreach ($fields as $field)
            <div>
                <label for="{{ $field['name'] }}" class="block mb-1 text-sm font-medium">
                    {{ $field['label'] }}
                    @if ($field['required'] ?? false)
                        <span class="text-danger">*</span>
                    @endif
                </label>

                <input
                    id="{{ $field['name'] }}"
                    name="{{ $field['name'] }}"
                    type="{{ $field['type'] ?? 'text' }}"
                    value="{{ $fieldValue($field['name']) }}"
                    @required($field['required'] ?? false)
                    class="w-full border-gray-300 rounded-md focus:border-indigo-500 focus:ring-indigo-500 @error($field['name']) is-invalid @enderror"
                >
                <x-input-error :messages="$errors->get($field['name'])" class="mt-1"/>
            </div>
        @endforeach
    </div>
@endforeach

<h2 class="mt-6 mb-3 font-semibold">Województwo i typ strzelnicy</h2>

<div class="grid grid-cols-1 gap-4 mb-4 md:grid-cols-2">
    <div>
        <label for="voivodeship" class="block mb-1 text-sm font-medium">
            Województwo <span class="text-danger">*</span>
        </label>

        <select
            id="voivodeship"
            name="voivodeship"
            required
            class="w-full border-gray-300 rounded-md @error('voivodeship') is-invalid @enderror"
        >
            <option value="" disabled @selected(!$fieldValue('voivodeship'))>
                Wybierz województwo
            </option>

            @foreach ($voivodeships as $voivodeship)
                <option value="{{ $voivodeship }}" @selected($fieldValue('voivodeship') === $voivodeship)>
                    {{ ucfirst($voivodeship) }}
                </option>
            @endforeach
        </select>

        <x-input-error :messages="$errors->get('voivodeship')" class="mt-1"/>
    </div>

    <div>
        <label for="type" class="block mb-1 text-sm font-medium">
            Typ strzelnicy <span class="text-danger">*</span>
        </label>

        <select id="type" name="type" required class="w-full border-gray-300 rounded-md @error('type') is-invalid @enderror">
            <option value="otwarta" @selected($fieldValue('type', 'otwarta') === 'otwarta')>Otwarta</option>
            <option value="kryta" @selected($fieldValue('type') === 'kryta')>Kryta</option>
        </select>

        <x-input-error :messages="$errors->get('type')" class="mt-1"/>
    </div>
</div>

<div class="mb-4">
    <input type="hidden" name="is_public_access" value="0">

    <label class="flex items-center gap-2">
        <input
            type="checkbox"
            name="is_public_access"
            value="1"
            @checked((bool) $fieldValue('is_public_access', true))
        >
        Strzelnica ogólnodostępna
    </label>

    <x-input-error :messages="$errors->get('is_public_access')" class="mt-1"/>
</div>

<div class="mb-4">
    <input type="hidden" name="requires_reservation_confirmation" value="0">

    <label class="flex items-center gap-2">
        <input
            type="checkbox"
            name="requires_reservation_confirmation"
            value="1"
            @checked((bool) $fieldValue('requires_reservation_confirmation', true))
        >
        Wymagaj potwierdzenia rezerwacji przez personel
    </label>

    <div class="mt-1 text-sm text-muted">
        Po wyłączeniu tej opcji rezerwacje złożone przez klientów będą automatycznie zatwierdzane.
        Zmiana lub anulowanie rezerwacji przez klienta nadal wymaga obsługi personelu.
    </div>

    <x-input-error :messages="$errors->get('requires_reservation_confirmation')" class="mt-1"/>
</div>

<div class="mb-4">
    <input type="hidden" name="requires_shooting_leader" value="0">

    <label class="flex items-center gap-2">
        <input
            type="checkbox"
            name="requires_shooting_leader"
            value="1"
            @checked((bool) $fieldValue('requires_shooting_leader', false))
        >
        Wymagaj prowadzącego strzelanie przy rezerwacji
    </label>

    <div class="mt-1 text-sm text-muted">
        Po włączeniu tej opcji rezerwację będzie można zapisać tylko po podaniu
        informacji o uprawnieniach prowadzącego strzelanie.
    </div>

    <x-input-error :messages="$errors->get('requires_shooting_leader')" class="mt-1"/>
</div>

<div class="mb-4">
    @php
        $globalBookingDays = \App\Models\SystemConfiguration::query()
            ->active()
            ->latest('id')
            ->value('default_max_advance_booking_days') ?? 30;
    @endphp

    <label for="max_advance_booking_days" class="block mb-1 text-sm font-medium">
        Maksymalny termin rezerwacji (dni)
    </label>
    <input
        type="number"
        id="max_advance_booking_days"
        name="max_advance_booking_days"
        value="{{ $fieldValue('max_advance_booking_days') }}"
        min="0"
        max="365"
        placeholder="{{ $globalBookingDays }}"
        class="w-full border-gray-300 rounded-md focus:border-indigo-500 focus:ring-indigo-500 @error('max_advance_booking_days') is-invalid @enderror"
    >
    <div class="mt-1 text-sm text-muted">
        Pozostaw puste, aby użyć ustawienia globalnego: {{ $globalBookingDays }} dni.
        Wartość 0 pozwala rezerwować tylko na dzisiaj.
    </div>
    <x-input-error :messages="$errors->get('max_advance_booking_days')" class="mt-1"/>
</div>

<h2 class="mt-6 mb-2 font-semibold">Opis</h2>

<textarea
    name="description"
    id="mytextarea"
    rows="4"
    class="w-full border-gray-300 rounded-md @error('description') is-invalid @enderror"
>{{ $fieldValue('description') }}</textarea>

<x-input-error :messages="$errors->get('description')" class="mt-1"/>

<h2 class="mt-6 mb-3 font-semibold">SEO</h2>

<div class="grid grid-cols-1 gap-4 mb-4 md:grid-cols-2">
    <div>
        <label for="meta_title" class="block mb-1 text-sm font-medium">Tytuł meta</label>
        <input
            id="meta_title"
            name="meta_title"
            type="text"
            value="{{ $fieldValue('meta_title') }}"
            maxlength="255"
            class="w-full border-gray-300 rounded-md focus:border-indigo-500 focus:ring-indigo-500 @error('meta_title') is-invalid @enderror"
        >
        <div class="mt-1 text-sm text-muted">
            Jeśli puste, system utworzy tytuł z nazwy i miejscowości strzelnicy.
        </div>
        <x-input-error :messages="$errors->get('meta_title')" class="mt-1"/>
    </div>

    <div>
        <label for="robots" class="block mb-1 text-sm font-medium">Robots</label>
        <select
            id="robots"
            name="robots"
            class="w-full border-gray-300 rounded-md @error('robots') is-invalid @enderror"
        >
            @foreach (['index,follow', 'noindex,follow', 'index,nofollow', 'noindex,nofollow'] as $robotsOption)
                <option value="{{ $robotsOption }}" @selected($fieldValue('robots', 'index,follow') === $robotsOption)>
                    {{ $robotsOption }}
                </option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('robots')" class="mt-1"/>
    </div>
</div>

<div class="mb-4">
    <label for="meta_description" class="block mb-1 text-sm font-medium">Opis meta</label>
    <textarea
        id="meta_description"
        name="meta_description"
        rows="2"
        maxlength="500"
        class="w-full border-gray-300 rounded-md @error('meta_description') is-invalid @enderror"
    >{{ $fieldValue('meta_description') }}</textarea>
    <div class="mt-1 text-sm text-muted">
        Zalecana długość: około 140-160 znaków.
    </div>
    <x-input-error :messages="$errors->get('meta_description')" class="mt-1"/>
</div>

<div class="mb-4">
    <label for="meta_keywords" class="block mb-1 text-sm font-medium">Słowa kluczowe</label>
    <textarea
        id="meta_keywords"
        name="meta_keywords"
        rows="2"
        maxlength="500"
        class="w-full border-gray-300 rounded-md @error('meta_keywords') is-invalid @enderror"
    >{{ $fieldValue('meta_keywords') }}</textarea>
    <x-input-error :messages="$errors->get('meta_keywords')" class="mt-1"/>
</div>

<div class="grid grid-cols-1 gap-4 mb-4 md:grid-cols-2">
    <div>
        <label for="og_title" class="block mb-1 text-sm font-medium">Tytuł Open Graph</label>
        <input
            id="og_title"
            name="og_title"
            type="text"
            value="{{ $fieldValue('og_title') }}"
            maxlength="255"
            class="w-full border-gray-300 rounded-md focus:border-indigo-500 focus:ring-indigo-500 @error('og_title') is-invalid @enderror"
        >
        <x-input-error :messages="$errors->get('og_title')" class="mt-1"/>
    </div>

    <div>
        <label for="og_image" class="block mb-1 text-sm font-medium">Obraz Open Graph</label>
        <input
            id="og_image"
            name="og_image"
            type="text"
            value="{{ $fieldValue('og_image') }}"
            maxlength="255"
            placeholder="files/brands/logo.png lub https://..."
            class="w-full border-gray-300 rounded-md focus:border-indigo-500 focus:ring-indigo-500 @error('og_image') is-invalid @enderror"
        >
        <div class="mt-1 text-sm text-muted">
            Jeśli puste, system użyje logo strzelnicy.
        </div>
        <x-input-error :messages="$errors->get('og_image')" class="mt-1"/>
    </div>
</div>

<div class="mb-4">
    <label for="og_description" class="block mb-1 text-sm font-medium">Opis Open Graph</label>
    <textarea
        id="og_description"
        name="og_description"
        rows="2"
        maxlength="500"
        class="w-full border-gray-300 rounded-md @error('og_description') is-invalid @enderror"
    >{{ $fieldValue('og_description') }}</textarea>
    <x-input-error :messages="$errors->get('og_description')" class="mt-1"/>
</div>

<div class="flex justify-end mt-6">
    <x-primary-button>{{ $submitLabel }}</x-primary-button>
</div>
