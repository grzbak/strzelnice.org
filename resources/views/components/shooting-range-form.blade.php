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
                    class="w-full border-gray-300 rounded-md focus:border-indigo-500 focus:ring-indigo-500"
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
            class="w-full border-gray-300 rounded-md"
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

        <select id="type" name="type" required class="w-full border-gray-300 rounded-md">
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

<h2 class="mt-6 mb-2 font-semibold">Opis</h2>

<textarea
    name="description"
    id="mytextarea"
    rows="4"
    class="w-full border-gray-300 rounded-md"
>{{ $fieldValue('description') }}</textarea>

<x-input-error :messages="$errors->get('description')" class="mt-1"/>

<div class="flex justify-end mt-6">
    <x-primary-button>{{ $submitLabel }}</x-primary-button>
</div>
