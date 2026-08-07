<x-app-layout>
    <div class="page-bg-2"></div>


    <div class="container py-3">
        <div class="mb-3 row g-2">
            <div class="col-md-9">
                <input
                    id="globalSearch"
                    class="form-control"
                    type="text"
                    placeholder="Szukaj adresu, miasta, województwa lub strzelnicy">
            </div>

            <div class="flex col-md-3">
                <div class="input-group">
                    <span class="inline-flex items-center px-3 text-gray-500 bg-gray-100 border border-r-0 border-gray-300 rounded-l-md">
                        +
                    </span>
                    <input
                        id="radiusKm"
                        class="form-control"
                        type="number"
                        min="1"
                        max="500"
                        step="1"
                        value="20">
                    <span class="input-group-text">km</span>
                </div>
            </div>
        </div>

        <div id="map"></div>
    </div>



    <!--a href="{{ route('range.step1') }}" class="btn btn-outline-primary">
        Zarejestruj strzelnicę
    </!--a-->

    <style>
        .brand-map-marker {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            overflow: hidden;
            background: #fff;
            border: 2px solid #fff;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.28);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .brand-map-marker img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
    </style>

    <script>
        let map;
        let markers = [];
        let cluster;
        let searchCircle = null;
        let selectedPlaceLocation = null;
        let voivodeshipRectangle = null;

        const shootings = @json($shootings);
        const brandBaseUrl = @json(asset('files/brands'));

        const voivodeshipBounds = {
            dolnoslaskie: {
                sw: { lat: 50.0960, lng: 14.8160 },
                ne: { lat: 51.8040, lng: 17.7950 },
            },
            'kujawsko-pomorskie': {
                sw: { lat: 52.3060, lng: 17.2470 },
                ne: { lat: 53.7810, lng: 19.7610 },
            },
            lubelskie: {
                sw: { lat: 50.2510, lng: 21.6150 },
                ne: { lat: 52.2960, lng: 24.1450 },
            },
            lubuskie: {
                sw: { lat: 51.3630, lng: 14.5320 },
                ne: { lat: 53.1320, lng: 16.4160 },
            },
            lodzkie: {
                sw: { lat: 50.8430, lng: 18.0740 },
                ne: { lat: 52.3940, lng: 20.6450 },
            },
            malopolskie: {
                sw: { lat: 49.1780, lng: 19.0760 },
                ne: { lat: 50.5200, lng: 21.4220 },
            },
            mazowieckie: {
                sw: { lat: 51.0130, lng: 19.2590 },
                ne: { lat: 53.4820, lng: 23.1280 },
            },
            opolskie: {
                sw: { lat: 49.9720, lng: 16.8560 },
                ne: { lat: 51.2040, lng: 18.6950 },
            },
            podkarpackie: {
                sw: { lat: 49.0020, lng: 21.1380 },
                ne: { lat: 50.8180, lng: 23.6510 },
            },
            podlaskie: {
                sw: { lat: 52.2840, lng: 21.5940 },
                ne: { lat: 54.4080, lng: 23.9460 },
            },
            pomorskie: {
                sw: { lat: 53.4900, lng: 16.6990 },
                ne: { lat: 54.8350, lng: 19.6490 },
            },
            slaskie: {
                sw: { lat: 49.3930, lng: 18.0350 },
                ne: { lat: 51.1000, lng: 19.9740 },
            },
            swietokrzyskie: {
                sw: { lat: 50.1850, lng: 19.7040 },
                ne: { lat: 51.3430, lng: 21.7010 },
            },
            'warminsko-mazurskie': {
                sw: { lat: 53.1380, lng: 19.1280 },
                ne: { lat: 54.4520, lng: 22.8060 },
            },
            wielkopolskie: {
                sw: { lat: 51.2830, lng: 15.7780 },
                ne: { lat: 53.6550, lng: 18.6820 },
            },
            zachodniopomorskie: {
                sw: { lat: 52.6250, lng: 14.1210 },
                ne: { lat: 54.5700, lng: 16.9840 },
            },
        };

        function parseCoordinate(value) {
            if (value === null || value === undefined || value === '') {
                return null;
            }

            const normalized = String(value).replace(',', '.');
            const number = Number(normalized);

            return Number.isFinite(number) ? number : null;
        }

        function createBrandMarkerContent(loc) {
            const brandFileName = loc.brand_file_name ?? null;
            const brandUrl = loc.brand_url ?? (brandFileName ? `${brandBaseUrl}/${brandFileName}` : null);

            if (!brandUrl) {
                return null;
            }

            const wrapper = document.createElement('div');
            wrapper.className = 'brand-map-marker';

            const img = document.createElement('img');
            img.src = brandUrl;
            img.alt = loc.brand_original_name ?? loc.name ?? 'Brand';

            wrapper.appendChild(img);

            return wrapper;
        }

        function normalizeText(value) {
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

        function clearAreaSelection() {
            if (searchCircle) {
                searchCircle.setMap(null);
                searchCircle = null;
            }

            if (voivodeshipRectangle) {
                voivodeshipRectangle.setMap(null);
                voivodeshipRectangle = null;
            }
        }

        function isVoivodeshipText(value) {
            return Object.prototype.hasOwnProperty.call(voivodeshipBounds, normalizeText(value));
        }

        window.initMap = function () {
            map = new google.maps.Map(document.getElementById("map"), {
                center: { lat: 52.0693, lng: 19.4803 },
                zoom: 6,
                mapId: "{{ config('services.google_maps.map_id') }}"
            });

            const polandBounds = new google.maps.LatLngBounds(
                { lat: 49.0, lng: 14.0 },
                { lat: 54.9, lng: 24.2 }
            );

            map.fitBounds(polandBounds);

            if (!window.markerClusterer) {
                console.error('MarkerClusterer nie został załadowany.');
                return;
            }

            markers = [];

            shootings.forEach(loc => {
                const lat = parseCoordinate(loc.lat);
                const lng = parseCoordinate(loc.lng);

                if (lat === null || lng === null) {
                    console.warn('Pominięto strzelnicę bez poprawnych współrzędnych:', loc);
                    return;
                }

                const markerOptions = {
                    map: map,
                    position: { lat, lng },
                    title: loc.name ?? '',
                    gmpClickable: true,
                };

                const brandMarkerContent = createBrandMarkerContent(loc);

                if (brandMarkerContent) {
                    markerOptions.content = brandMarkerContent;
                }

                const marker = new google.maps.marker.AdvancedMarkerElement(markerOptions);

                marker._searchText = `
                    ${loc.name ?? ''}
                    ${loc.voivodeship ?? ''}
                    ${loc.town ?? ''}
                    ${loc.street ?? ''}
                    ${loc.type ?? ''}
                `.toLowerCase();

                marker._voivodeship = normalizeText(loc.voivodeship ?? '');

                const publicLink = loc.public_link ?? `/${loc.endpoint ?? ''}`;

                const infoWindow = new google.maps.InfoWindow({
                    content: `
                        <div style="max-width:240px">
                            <strong>${loc.name ?? ''}</strong><br>
                            ${loc.street ?? ''} ${loc.houseNumber ?? ''}<br>
                            ${loc.zipCode ?? ''} ${loc.town ?? ''}<br>
                            <small>Typ: ${loc.type ?? ''}</small><br>
                            <a href="${publicLink}" class="mt-2 btn btn-sm btn-primary">
                                Zobacz
                            </a>
                        </div>
                    `
                });

                marker.addEventListener('gmp-click', () => {
                    infoWindow.open({
                        map: map,
                        anchor: marker,
                    });
                });

                markers.push(marker);
            });

            cluster = new markerClusterer.MarkerClusterer({
                map: map,
                markers: markers,
            });

            initGlobalSearch();
        };

        function fitMapToMarkers(markerList) {
            if (!markerList.length) {
                return;
            }

            const bounds = new google.maps.LatLngBounds();

            markerList.forEach(marker => {
                bounds.extend(marker.position);
            });

            map.fitBounds(bounds);

            if (markerList.length === 1) {
                map.setZoom(12);
            }
        }

        function initGlobalSearch() {
            const input = document.getElementById('globalSearch');
            const radiusInput = document.getElementById('radiusKm');

            const geocoder = new google.maps.Geocoder();

            input.addEventListener('keydown', (event) => {
                if (event.key !== 'Enter') {
                    return;
                }

                event.preventDefault();

                const query = input.value.trim();

                if (!query) {
                    return;
                }

                if (isVoivodeshipText(query)) {
                    renderVoivodeshipSearch(query);
                    return;
                }

                geocoder.geocode(
                    {
                        address: query,
                        componentRestrictions: { country: 'PL' },
                    },
                    (results, status) => {
                        if (status !== 'OK' || !results.length || !results[0].geometry) {
                            return;
                        }

                        selectedPlaceLocation = results[0].geometry.location;

                        map.panTo(selectedPlaceLocation);
                        map.setZoom(11);

                        renderRadiusSearch();
                    }
                );
            });

            const autocomplete = new google.maps.places.Autocomplete(input, {
                componentRestrictions: { country: "pl" },
                fields: ['geometry', 'name', 'formatted_address'],
            });

            autocomplete.addListener('place_changed', () => {
                const place = autocomplete.getPlace();

                if (!place.geometry) {
                    return;
                }

                if (isVoivodeshipText(input.value) || isVoivodeshipText(place.name)) {
                    renderVoivodeshipSearch(input.value || place.name);
                    return;
                }

                selectedPlaceLocation = place.geometry.location;

                map.panTo(selectedPlaceLocation);
                map.setZoom(11);

                renderRadiusSearch();
            });

            radiusInput.addEventListener('input', () => {
                if (selectedPlaceLocation) {
                    renderRadiusSearch();
                }
            });

            input.addEventListener('input', () => {
                selectedPlaceLocation = null;
                clearAreaSelection();

                const value = normalizeText(input.value);

                if (isVoivodeshipText(value)) {
                    renderVoivodeshipSearch(value);
                    return;
                }

                const visibleMarkers = markers.filter(marker => {
                    return !value || normalizeText(marker._searchText).includes(value);
                });

                cluster.clearMarkers();
                cluster.addMarkers(visibleMarkers);
            });
        }

        function renderRadiusSearch() {
            if (!selectedPlaceLocation) {
                return;
            }

            const radiusKm = Number(document.getElementById('radiusKm').value || 20);
            const radiusMeters = radiusKm * 1000;

            clearAreaSelection();

            searchCircle = new google.maps.Circle({
                map: map,
                center: selectedPlaceLocation,
                radius: radiusMeters,
                fillColor: '#0d6efd',
                fillOpacity: 0.12,
                strokeColor: '#0d6efd',
                strokeOpacity: 0.8,
                strokeWeight: 2,
            });

            const visibleMarkers = markers.filter(marker => {
                const distance = google.maps.geometry.spherical.computeDistanceBetween(
                    selectedPlaceLocation,
                    marker.position
                );

                return distance <= radiusMeters;
            });

            cluster.clearMarkers();
            cluster.addMarkers(visibleMarkers);

            const bounds = searchCircle.getBounds();

            if (bounds) {
                map.fitBounds(bounds);
            }
        }

        function renderVoivodeshipSearch(voivodeship) {
            const normalizedVoivodeship = normalizeText(voivodeship);
            const area = voivodeshipBounds[normalizedVoivodeship];

            if (!area) {
                return;
            }

            selectedPlaceLocation = null;
            clearAreaSelection();

            const bounds = new google.maps.LatLngBounds(area.sw, area.ne);
            const center = bounds.getCenter();

            const ne = new google.maps.LatLng(area.ne.lat, area.ne.lng);
            const sw = new google.maps.LatLng(area.sw.lat, area.sw.lng);
            const nw = new google.maps.LatLng(area.ne.lat, area.sw.lng);
            const se = new google.maps.LatLng(area.sw.lat, area.ne.lng);

            const radiusMeters = Math.max(
                google.maps.geometry.spherical.computeDistanceBetween(center, ne),
                google.maps.geometry.spherical.computeDistanceBetween(center, sw),
                google.maps.geometry.spherical.computeDistanceBetween(center, nw),
                google.maps.geometry.spherical.computeDistanceBetween(center, se)
            );

            searchCircle = new google.maps.Circle({
                map: map,
                center: center,
                radius: radiusMeters,
                fillColor: '#0d6efd',
                fillOpacity: 0.10,
                strokeColor: '#0d6efd',
                strokeOpacity: 0.8,
                strokeWeight: 2,
            });

            const visibleMarkers = markers.filter(marker => {
                return marker._voivodeship === normalizedVoivodeship;
            });

            cluster.clearMarkers();
            cluster.addMarkers(visibleMarkers);

            const circleBounds = searchCircle.getBounds();

            if (circleBounds) {
                map.fitBounds(circleBounds);
            }
        }
    </script>

    <script src="https://unpkg.com/@googlemaps/markerclusterer/dist/index.min.js"></script>

    <script
        src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google_maps.key') }}&libraries=places,geometry,marker&loading=async&callback=initMap"
        async defer>
    </script>
</x-app-layout>
