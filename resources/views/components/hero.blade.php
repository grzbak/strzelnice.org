<section class="text-white hero-search d-flex align-items-center">
    <div class="container mb-4 text-center">

        <h3 class="mt-4 mb-3 mt-md-0 display-4 fw-bold">
            <i class="bi bi-calendar-check text-danger"></i> sprawdzasz. klikasz. strzelasz
        </h3>

        <p class="mb-4 lead">
            Skorzystaj z formularza i znajdź wolną strzelnicę w określonej lokalizacji i czasie:
        </p>

        <form method="GET" action="{{ route('index') }}" class="p-4 bg-white rounded shadow search-box text-dark" id="heroSearchForm">
            <input type="hidden" name="lat" id="searchLat" value="{{ request('lat') }}">
            <input type="hidden" name="lng" id="searchLng" value="{{ request('lng') }}">
            <input type="hidden" name="location_mode" id="locationMode" value="{{ request('location_mode', request()->filled('location') ? '' : 'radius') }}">
            <input type="hidden" name="viewport_radius_km" id="viewportRadiusKm" value="{{ request('viewport_radius_km') }}">

            <div class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Data</label>
                    <input
                        type="date"
                        name="date"
                        id="searchDate"
                        class="form-control"
                        value="{{ request('date', now()->format('Y-m-d')) }}"
                        min="2000-01-01"
                        max="3000-12-31"
                    >
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Lokalizacja</label>

                    <div class="input-group">
                        <input
                            type="text"
                            name="location"
                            id="heroLocation"
                            class="form-control"
                            placeholder="Województwo lub miasto..."
                            value="{{ request('location', 'Moja lokalizacja') }}"
                        >

                        <span class="input-group-text">+</span>

                        <input
                            type="number"
                            name="radius_km"
                            id="heroRadiusKm"
                            class="form-control"
                            min="0"
                            max="500"
                            step="1"
                            value="{{ request('radius_km', 20) }}"
                            placeholder="20"
                            style="max-width: 80px;"
                        >

                        <span class="input-group-text">km</span>
                    </div>
                </div>

                <div class="col-md-2">
                    <label class="form-label fw-semibold">Długość osi [m]</label>
                    <input
                        type="number"
                        name="lane_length"
                        class="form-control"
                        min="1"
                        step="1"
                        placeholder="100"
                        value="{{ request('lane_length') }}"
                    >
                </div>

                <div class="col-md-2 d-grid">
                    <button class="btn btn-danger btn-lg">
                        <i class="bi bi-search me-1"></i> Szukaj
                    </button>
                </div>
            </div>
        </form>
    </div>
</section>

@if ($searched)
    <section class="search-results-section">
        <div class="container py-4 py-lg-5">
            <div class="mb-4">
                <h4 class="mb-1">Wyniki wyszukiwania</h4>
                <p class="mb-0 text-muted small">
                    Znaleziono:
                    {{ method_exists($shootings, 'total') ? $shootings->total() : $shootings->count() }}
                </p>
            </div>

            @if ($shootings->isNotEmpty())
                <div class="d-none d-lg-block table-responsive search-results-table-wrap">
                    <table class="table mb-0 align-middle bg-white table-hover">
                        <thead class="table-light">
                            <tr>
                                <th class="search-result-logo-column" aria-label="Logo"></th>
                                <th>Nazwa</th>
                                <th>Adres</th>
                                <th>Województwo</th>
                                <th>Dostępne osie</th>
                                <th>Odległość</th>
                                <th aria-label="Przejdź"></th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($shootings as $shootingRange)
                                <tr
                                    class="search-result-link"
                                    data-reservation-url="{{ url('/rezerwacje/' . $shootingRange->id) }}"
                                    onclick="goToReservation(this)"
                                    tabindex="0"
                                    role="link"
                                >
                                    <td class="search-result-logo-cell">
                                        <span class="search-result-logo">
                                            @if ($shootingRange->brand_url)
                                                <img
                                                    src="{{ $shootingRange->brand_url }}"
                                                    alt="Logo {{ $shootingRange->name }}"
                                                    loading="lazy"
                                                >
                                            @else
                                                <i class="bi bi-bullseye" aria-hidden="true"></i>
                                            @endif
                                        </span>
                                    </td>
                                    <td>
                                        <strong>{{ $shootingRange->name }}</strong>
                                        @if (! $shootingRange->is_public_access)
                                            <span
                                                class="badge bg-warning text-dark ms-1"
                                                title="Rezerwacje są dostępne wyłącznie dla zaakceptowanych użytkowników"
                                            >
                                                <i class="bi bi-lock-fill me-1" aria-hidden="true"></i>
                                                Grupa zamknięta
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        {{ $shootingRange->street }} {{ $shootingRange->houseNumber }},
                                        {{ $shootingRange->zipCode }} {{ $shootingRange->town }}
                                    </td>
                                    <td>{{ $shootingRange->voivodeship }}</td>
                                    <td>
                                        <div class="flex-wrap gap-1 d-flex">
                                            @foreach ($shootingRange->available_lanes as $lane)
                                                <span class="badge bg-success search-result-lane">
                                                    {{ $lane->name }} · {{ $lane->length }} m ·
                                                    wolne: {{ $lane->numberOfPositions - $lane->reservations_count }}
                                                </span>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="text-nowrap">
                                        @isset($shootingRange->distance)
                                            {{ number_format($shootingRange->distance, 1) }} km
                                        @else
                                            -
                                        @endisset
                                    </td>
                                    <td class="text-end">
                                        <i class="bi bi-chevron-right text-primary" aria-hidden="true"></i>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="gap-3 d-grid d-lg-none">
                    @foreach ($shootings as $shootingRange)
                        <article
                            class="search-result-mobile search-result-link"
                            data-reservation-url="{{ url('/rezerwacje/' . $shootingRange->id) }}"
                            onclick="goToReservation(this)"
                            tabindex="0"
                            role="link"
                        >
                            <div class="gap-3 d-flex align-items-start justify-content-between">
                                <span class="flex-shrink-0 search-result-logo search-result-logo-mobile">
                                    @if ($shootingRange->brand_url)
                                        <img
                                            src="{{ $shootingRange->brand_url }}"
                                            alt="Logo {{ $shootingRange->name }}"
                                            loading="lazy"
                                        >
                                    @else
                                        <i class="bi bi-bullseye" aria-hidden="true"></i>
                                    @endif
                                </span>
                                <div class="min-w-0">
                                    <div class="flex-wrap gap-2 mb-1 d-flex align-items-center">
                                        <h5 class="mb-0 search-result-mobile-title">{{ $shootingRange->name }}</h5>
                                        @if (! $shootingRange->is_public_access)
                                            <span
                                                class="badge bg-warning text-dark"
                                                title="Rezerwacje są dostępne wyłącznie dla zaakceptowanych użytkowników"
                                            >
                                                <i class="bi bi-lock-fill me-1" aria-hidden="true"></i>
                                                Grupa zamknięta
                                            </span>
                                        @endif
                                    </div>
                                    <div class="text-muted small">
                                        <i class="bi bi-geo-alt-fill me-1" aria-hidden="true"></i>
                                        {{ $shootingRange->street }} {{ $shootingRange->houseNumber }},
                                        {{ $shootingRange->zipCode }} {{ $shootingRange->town }}
                                    </div>
                                </div>
                                <i class="flex-shrink-0 bi bi-chevron-right text-primary" aria-hidden="true"></i>
                            </div>

                            <div class="flex-wrap gap-2 mt-3 d-flex search-result-meta">
                                <span>
                                    <i class="bi bi-map me-1" aria-hidden="true"></i>
                                    {{ $shootingRange->voivodeship }}
                                </span>
                                <span>
                                    <i class="bi bi-signpost-split me-1" aria-hidden="true"></i>
                                    @isset($shootingRange->distance)
                                        {{ number_format($shootingRange->distance, 1) }} km
                                    @else
                                        Odległość niedostępna
                                    @endisset
                                </span>
                            </div>

                            <div class="mt-3">
                                <div class="mb-2 text-muted small fw-semibold">Dostępne osie</div>
                                <div class="flex-wrap gap-2 d-flex">
                                    @foreach ($shootingRange->available_lanes as $lane)
                                        <span class="badge bg-success search-result-lane">
                                            {{ $lane->name }} · {{ $lane->length }} m ·
                                            wolne: {{ $lane->numberOfPositions - $lane->reservations_count }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>

                @if (method_exists($shootings, 'hasPages') && $shootings->hasPages())
                    <div class="mt-4">
                        {{ $shootings->links() }}
                    </div>
                @endif
            @else
                <div class="p-4 text-center bg-white search-results-empty">
                    <i class="mb-2 bi bi-search d-block text-muted fs-3" aria-hidden="true"></i>
                    <span class="text-muted">Brak strzelnic spełniających kryteria.</span>
                </div>
            @endif
        </div>
    </section>

    <style>
        .search-results-section {
            background: #f6f8f8;
            border-bottom: 1px solid #e6ebeb;
        }

        .search-results-table-wrap,
        .search-results-empty {
            overflow: hidden;
            border: 1px solid #e1e7e7;
            border-radius: 6px;
            box-shadow: 0 8px 24px rgba(31, 45, 48, 0.06);
        }

        .search-result-link {
            cursor: pointer;
        }

        .search-result-link:focus-visible {
            outline: 3px solid rgba(13, 110, 253, 0.3);
            outline-offset: 2px;
        }

        .search-result-logo-column,
        .search-result-logo-cell {
            width: 74px;
        }

        .search-result-logo {
            width: 48px;
            height: 48px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            color: #8f9a9d;
            background: #eef2f2;
            border: 1px solid #dbe3e3;
            border-radius: 6px;
            font-size: 1.35rem;
        }

        .search-result-logo img {
            width: 100%;
            height: 100%;
            display: block;
            object-fit: contain;
            padding: 5px;
        }

        .search-result-logo-mobile {
            width: 54px;
            height: 54px;
        }

        .search-result-lane {
            max-width: 100%;
            padding: 0.45rem 0.6rem;
            white-space: normal;
            text-align: left;
            line-height: 1.3;
        }

        .search-result-mobile {
            padding: 18px;
            background: #fff;
            border: 1px solid #e1e7e7;
            border-radius: 6px;
            box-shadow: 0 6px 18px rgba(31, 45, 48, 0.06);
        }

        .search-result-mobile-title {
            overflow-wrap: anywhere;
        }

        .search-result-meta {
            color: #526064;
            font-size: 0.82rem;
        }

        .search-result-meta span {
            display: inline-flex;
            align-items: center;
        }

        @media (max-width: 575.98px) {
            .search-result-mobile {
                padding: 15px;
            }

            .search-result-lane {
                width: 100%;
            }
        }
    </style>
@endif

<script>
    const polishVoivodeships = [
        'dolnoslaskie',
        'kujawsko-pomorskie',
        'lubelskie',
        'lubuskie',
        'lodzkie',
        'malopolskie',
        'mazowieckie',
        'opolskie',
        'podkarpackie',
        'podlaskie',
        'pomorskie',
        'slaskie',
        'swietokrzyskie',
        'warminsko-mazurskie',
        'wielkopolskie',
        'zachodniopomorskie',
    ];

    const currentLocationLabel = 'Moja lokalizacja';

    function normalizeLocationText(value) {
        return String(value ?? '')
            .toLowerCase()
            .trim()
            .replaceAll('ą', 'a')
            .replaceAll('ć', 'c')
            .replaceAll('ę', 'e')
            .replaceAll('ł', 'l')
            .replaceAll('ń', 'n')
            .replaceAll('ó', 'o')
            .replaceAll('ś', 's')
            .replaceAll('ż', 'z')
            .replaceAll('ź', 'z')
            .replace(/^wojewodztwo\s+/, '');
    }

    function isVoivodeshipLocation(value) {
        return polishVoivodeships.includes(normalizeLocationText(value));
    }

    function isCurrentLocation(value) {
        return normalizeLocationText(value) === normalizeLocationText(currentLocationLabel);
    }

    function setVoivodeshipMode() {
        document.getElementById('searchLat').value = '';
        document.getElementById('searchLng').value = '';
        document.getElementById('locationMode').value = 'voivodeship';
    }

    function setCurrentLocationMode() {
        document.getElementById('locationMode').value = 'radius';
        document.getElementById('viewportRadiusKm').value = 0;
    }

    function setCurrentCoordinates(position) {
        document.getElementById('searchLat').value = position.coords.latitude;
        document.getElementById('searchLng').value = position.coords.longitude;
        setCurrentLocationMode();
    }

    function requestBrowserLocation(form = null) {
        if (!navigator.geolocation) {
            return false;
        }

        navigator.geolocation.getCurrentPosition(
            position => {
                setCurrentCoordinates(position);

                if (form) {
                    isSubmittingAfterGeocode = true;
                    form.submit();
                }
            },
            () => {
                document.getElementById('searchLat').value = '';
                document.getElementById('searchLng').value = '';
                setCurrentLocationMode();

                if (form) {
                    isSubmittingAfterGeocode = true;
                    form.submit();
                }
            },
            {
                enableHighAccuracy: false,
                timeout: 8000,
                maximumAge: 300000,
            }
        );

        return true;
    }

    function setRadiusMode(place) {
        const latInput = document.getElementById('searchLat');
        const lngInput = document.getElementById('searchLng');
        const locationModeInput = document.getElementById('locationMode');
        const viewportRadiusInput = document.getElementById('viewportRadiusKm');

        latInput.value = place.geometry.location.lat();
        lngInput.value = place.geometry.location.lng();
        locationModeInput.value = 'radius';
        viewportRadiusInput.value = 0;

        if (place.geometry.viewport) {
            const bounds = place.geometry.viewport;
            const center = bounds.getCenter();

            const ne = bounds.getNorthEast();
            const sw = bounds.getSouthWest();

            const corners = [
                ne,
                sw,
                new google.maps.LatLng(ne.lat(), sw.lng()),
                new google.maps.LatLng(sw.lat(), ne.lng()),
            ];

            const radiusMeters = Math.max(...corners.map(corner => {
                return google.maps.geometry.spherical.computeDistanceBetween(center, corner);
            }));

            latInput.value = center.lat();
            lngInput.value = center.lng();
            viewportRadiusInput.value = radiusMeters / 1000;
        }
    }

    function syncLocationModeFromText() {
        const locationInput = document.getElementById('heroLocation');
        const latInput = document.getElementById('searchLat');
        const lngInput = document.getElementById('searchLng');
        const locationModeInput = document.getElementById('locationMode');

        if (isVoivodeshipLocation(locationInput.value)) {
            setVoivodeshipMode();
            return;
        }

        if (isCurrentLocation(locationInput.value)) {
            setCurrentLocationMode();
            return;
        }

        locationModeInput.value = '';
        latInput.value = '';
        lngInput.value = '';
        document.getElementById('viewportRadiusKm').value = '';
    }

    function initHeroLocationAutocomplete() {
        const locationInput = document.getElementById('heroLocation');

        if (!locationInput || !window.google?.maps?.places) {
            return;
        }

        const autocomplete = new google.maps.places.Autocomplete(locationInput, {
            componentRestrictions: { country: 'pl' },
            fields: ['geometry', 'name', 'formatted_address', 'address_components', 'types'],
        });

        autocomplete.addListener('place_changed', () => {
            const place = autocomplete.getPlace();

            if (isVoivodeshipLocation(locationInput.value) || isVoivodeshipLocation(place.name)) {
                setVoivodeshipMode();
                return;
            }

            if (!place.geometry) {
                return;
            }

            setRadiusModeFromPlace(place);

            locationInput.value = place.formatted_address ?? locationInput.value;
        });

        locationInput.addEventListener('input', syncLocationModeFromText);
        setupHeroSearchSubmit();
    }

    let isHeroSearchSubmitBound = false;

    function setupHeroSearchSubmit() {
        const searchForm = document.getElementById('heroSearchForm');

        if (!searchForm || isHeroSearchSubmitBound) {
            return;
        }

        isHeroSearchSubmitBound = true;

        searchForm.addEventListener('submit', event => {
            if (isSubmittingAfterGeocode) {
                return;
            }

            const form = event.target;
            const locationInput = document.getElementById('heroLocation');
            const latInput = document.getElementById('searchLat');
            const lngInput = document.getElementById('searchLng');

            syncLocationModeFromText();

            if (!locationInput.value.trim()) {
                return;
            }

            if (isCurrentLocation(locationInput.value)) {
                setCurrentLocationMode();

                if (!latInput.value || !lngInput.value) {
                    event.preventDefault();
                    requestBrowserLocation(form);
                    return;
                }

                return;
            }

            if (isVoivodeshipLocation(locationInput.value)) {
                setVoivodeshipMode();
                return;
            }

            if (!latInput.value || !lngInput.value) {
                if (!window.google?.maps?.Geocoder) {
                    return;
                }

                event.preventDefault();
                geocodeLocationForSubmit(form);
                return;
            }

            document.getElementById('locationMode').value = 'radius';
        });
    }

    function goToReservation(row) {
        const dateInput = document.getElementById('searchDate');
        const date = dateInput?.value || new Date().toISOString().slice(0, 10);

        window.location.href = `${row.dataset.reservationUrl}?date=${encodeURIComponent(date)}`;
    }

    document.querySelectorAll('.search-result-link').forEach(result => {
        result.addEventListener('keydown', event => {
            if (event.key === 'Enter') {
                goToReservation(result);
            }
        });
    });

    function distanceKm(lat1, lng1, lat2, lng2) {
    const earthRadiusKm = 6371;

    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLng = (lng2 - lng1) * Math.PI / 180;

    const a = Math.sin(dLat / 2) * Math.sin(dLat / 2)
        + Math.cos(lat1 * Math.PI / 180)
        * Math.cos(lat2 * Math.PI / 180)
        * Math.sin(dLng / 2)
        * Math.sin(dLng / 2);

    return earthRadiusKm * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

    function setRadiusModeFromPlace(place) {
        const latInput = document.getElementById('searchLat');
        const lngInput = document.getElementById('searchLng');
        const locationModeInput = document.getElementById('locationMode');
        const viewportRadiusInput = document.getElementById('viewportRadiusKm');

        latInput.value = place.geometry.location.lat();
        lngInput.value = place.geometry.location.lng();
        locationModeInput.value = 'radius';
        viewportRadiusInput.value = 0;

        if (!place.geometry.viewport) {
            return;
        }

        const bounds = place.geometry.viewport;
        const center = bounds.getCenter();

        const ne = bounds.getNorthEast();
        const sw = bounds.getSouthWest();

        const corners = [
            { lat: ne.lat(), lng: ne.lng() },
            { lat: sw.lat(), lng: sw.lng() },
            { lat: ne.lat(), lng: sw.lng() },
            { lat: sw.lat(), lng: ne.lng() },
        ];

        const radiusKm = Math.max(...corners.map(corner => {
            return distanceKm(center.lat(), center.lng(), corner.lat, corner.lng);
        }));

        latInput.value = center.lat();
        lngInput.value = center.lng();
        viewportRadiusInput.value = radiusKm;
    }

    let isSubmittingAfterGeocode = false;

    function geocodeLocationForSubmit(form) {
        const locationInput = document.getElementById('heroLocation');
        const geocoder = new google.maps.Geocoder();

        geocoder.geocode(
            {
                address: locationInput.value,
                componentRestrictions: { country: 'PL' },
            },
            (results, status) => {
                if (status !== 'OK' || !results.length || !results[0].geometry) {
                    form.submit();
                    return;
                }

                const fakePlace = {
                    geometry: results[0].geometry,
                };

                setRadiusModeFromPlace(fakePlace);

                isSubmittingAfterGeocode = true;
                form.submit();
            }
        );
    }

    document.addEventListener('DOMContentLoaded', () => {
        setupHeroSearchSubmit();

        const locationInput = document.getElementById('heroLocation');
        const latInput = document.getElementById('searchLat');
        const lngInput = document.getElementById('searchLng');

        if (
            locationInput
            && isCurrentLocation(locationInput.value)
            && (!latInput.value || !lngInput.value)
        ) {
            setCurrentLocationMode();
            requestBrowserLocation();
        }
    });

    window.addEventListener('load', initHeroLocationAutocomplete);
</script>

<script
    src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google_maps.key') }}&libraries=places&loading=async"
    async
    defer>
</script>
