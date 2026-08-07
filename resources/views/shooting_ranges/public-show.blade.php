<x-app-layout>
    @php
        $gallery = $shootingRange->gallery;
        $brandUrl = $shootingRange->brand_url;
        $reservationUrl = url('/rezerwacje/' . $shootingRange->id)
            . '?date=' . now()->format('Y-m-d');
    @endphp

    <div class="page-bg-2"></div>

    <div class="container py-4">
        <header class="mb-4 overflow-hidden bg-white border rounded shadow-sm">
            <div class="gap-4 p-4 d-flex flex-column flex-md-row align-items-center">
                {{-- Logo --}}
                <div
                    class="flex-shrink-0 mx-auto border rounded d-flex align-items-center justify-content-center bg-light mx-md-0"
                    style="width: 140px; height: 140px;"
                >
                    @if ($brandUrl)
                        <img
                            src="{{ $brandUrl }}"
                            alt="Logo {{ $shootingRange->name }}"
                            style="width: 100%; height: 100%; object-fit: contain; padding: 8px;"
                        >
                    @else
                        <i class="bi bi-image text-muted" style="font-size: 3rem;"></i>
                    @endif
                </div>

                {{-- Informacje --}}
                <div class="text-center flex-grow-1 text-md-start">
                    <h1 class="mb-2">{{ $shootingRange->name }}</h1>

                    <div class="text-muted">
                        <i class="bi bi-geo-alt-fill me-1"></i>
                        {{ $shootingRange->full_address }}
                    </div>

                    <div class="flex-wrap gap-2 mt-3 d-flex justify-content-center justify-content-md-start">
                        <span class="badge bg-secondary">
                            {{ ucfirst($shootingRange->type) }}
                        </span>

                        @if ($canReserve)
                            <span class="badge bg-success">
                                <i class="bi bi-calendar-check me-1"></i>
                                Rezerwacje dostępne
                            </span>
                        @else
                            <span
                                class="badge bg-danger"
                                title="{{ $reservationUnavailableReason }}"
                            >
                                <i class="bi bi-calendar-x me-1"></i>
                                Rezerwacje niedostępne
                            </span>
                        @endif

                        @if (! $shootingRange->is_public_access)
                            <span
                                class="badge bg-warning text-dark"
                                title="Rezerwacje są dostępne wyłącznie dla zaakceptowanych użytkowników"
                            >
                                <i class="bi bi-lock-fill me-1"></i>
                                Rezerwacje dla zamkniętej grupy użytkowników
                            </span>
                        @endif
                    </div>
                </div>

                {{-- Przyciski --}}
                <div class="flex-wrap gap-2 header-actions w-100 d-flex justify-content-center ms-md-auto">
                    <a
                        href="{{ $shootingRange->navLink }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="btn btn-outline-primary"
                    >
                        <i class="bi bi-signpost-2-fill me-1"></i>
                        Nawiguj
                    </a>

                    @if ($canReserve)
                        <a href="{{ $reservationUrl }}" class="btn btn-success">
                            <i class="bi bi-calendar-check-fill me-1"></i>
                            Rezerwuj
                        </a>
                    @endif
                </div>
            </div>
        </header>

        {{-- Zakładki --}}
        <ul
            class="nav nav-tabs flex-nowrap"
            id="shootingRangeTabs"
            role="tablist"
        >
            <li class="nav-item flex-fill" role="presentation">
                <button
                    class="nav-link active w-100"
                    data-bs-toggle="tab"
                    data-bs-target="#information"
                    type="button"
                    role="tab"
                    title="Informacje"
                >
                    <i class="bi bi-info-circle"></i>
                    <span class="tab-label ms-1">Informacje</span>
                </button>
            </li>

            <li class="nav-item flex-fill" role="presentation">
                <button
                    class="nav-link w-100"
                    data-bs-toggle="tab"
                    data-bs-target="#gallery"
                    type="button"
                    role="tab"
                    title="Galeria"
                >
                    <i class="bi bi-images"></i>
                    <span class="tab-label ms-1">Galeria</span>
                </button>
            </li>

            @if ($canReserve)
                <!--li class="nav-item flex-fill" role="presentation">
                    <button
                        class="nav-link w-100"
                        data-bs-toggle="tab"
                        data-bs-target="#reservations"
                        type="button"
                        role="tab"
                        title="Rezerwacje"
                    >
                        <i class="bi bi-calendar-check"></i>
                        <span class="tab-label ms-1">Rezerwacje</span>
                    </button>
                </li-->
            @endif
        </ul>

        <div class="p-4 bg-white border shadow-sm border-top-0 tab-content">
            {{-- Informacje --}}
            <div class="tab-pane fade show active" id="information">
                @if ($shootingRange->description)
                    <section class="mb-5">
                        <h2 class="mb-3 h4">O strzelnicy</h2>

                        <div class="shooting-range-description">
                            {!! $shootingRange->description !!}
                        </div>
                    </section>
                @endif

                <div class="row g-4">
                    <div class="col-lg-6">
                        <h2 class="mb-3 h4">Dane strzelnicy</h2>

                        <table class="table align-middle public-info-table">
                            <tbody>
                                <tr>
                                    <th data-label="Pole">Typ</th>
                                    <td data-label="Wartość">{{ ucfirst($shootingRange->type) }}</td>
                                </tr>

                                <tr>
                                    <th data-label="Pole">Województwo</th>
                                    <td data-label="Wartość">{{ ucfirst($shootingRange->voivodeship) }}</td>
                                </tr>

                                <tr>
                                    <th data-label="Pole">Adres</th>
                                    <td data-label="Wartość">{{ $shootingRange->full_address }}</td>
                                </tr>

                                <tr>
                                    <th data-label="Pole">E-mail</th>
                                    <td data-label="Wartość">
                                        <a href="mailto:{{ $shootingRange->email }}">
                                            {{ $shootingRange->email }}
                                        </a>
                                    </td>
                                </tr>

                                <tr>
                                    <th data-label="Pole">Telefon</th>
                                    <td data-label="Wartość">
                                        <a href="tel:{{ $shootingRange->telephone1 }}">
                                            {{ $shootingRange->telephone1 }}
                                        </a>
                                    </td>
                                </tr>

                                @if ($shootingRange->url)
                                    <tr>
                                        <th data-label="Pole">Strona WWW</th>
                                        <td data-label="Wartość">
                                            <a
                                                href="{{ $shootingRange->url }}"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                            >
                                                {{ $shootingRange->url }}
                                            </a>
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>

                        <h2 class="mt-5 mb-3 h4">Osie strzeleckie</h2>

                        <table class="table align-middle table-hover public-lanes-table">
                            <thead>
                                <tr>
                                    <th>Nazwa</th>
                                    <th>Długość</th>
                                    <th>Stanowiska</th>
                                    <th>Strzelanie dynamiczne</th>
                                </tr>
                            </thead>

                            <tbody>
                                @forelse ($shootingRange->lanes as $lane)
                                    <tr>
                                        <td data-label="Nazwa">{{ $lane->name }}</td>
                                        <td data-label="Długość">{{ $lane->length }} m</td>
                                        <td data-label="Stanowiska">{{ $lane->numberOfPositions }}</td>
                                        <td data-label="Strzelanie dynamiczne">
                                            @if ($lane->allows_dynamic_shooting)
                                                <span class="badge bg-success">
                                                    <i class="bi bi-check-lg me-1"></i>
                                                    Dostępne
                                                </span>
                                            @else
                                                <span class="text-muted">
                                                    <i class="bi bi-dash-lg me-1"></i>
                                                    Niedostępne
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-muted">
                                            Brak informacji o osiach.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="col-lg-6">
                        <div class="mb-3 d-flex justify-content-between align-items-center">
                            <h2 class="mb-0 h4">Lokalizacja</h2>

                            <a
                                href="{{ $shootingRange->navLink }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="btn btn-sm btn-outline-primary"
                            >
                                <i class="bi bi-signpost-2-fill me-1"></i>
                                Nawiguj
                            </a>
                        </div>

                        <div
                            id="shootingRangeMap"
                            class="border rounded"
                            style="height: 440px;"
                        ></div>
                    </div>
                </div>
            </div>

            {{-- Galeria --}}
            <div class="tab-pane fade" id="gallery">
                @if ($gallery && $gallery->is_visible && $gallery->photos->isNotEmpty())
                    <div class="gallery-grid">
                        @foreach ($gallery->photos as $photo)
                            <a
                                href="{{ $photo->full_url }}"
                                data-fancybox="shooting-range-gallery"
                                data-caption="{{ $photo->description ?? '' }}"
                                class="gallery-item"
                            >
                                <img
                                    src="{{ $photo->full_url }}"
                                    alt="{{ $photo->description ?? $photo->original_name }}"
                                    loading="lazy"
                                >

                                <span class="gallery-item-overlay">
                                    <i class="bi bi-arrows-fullscreen"></i>
                                </span>
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="mb-0 alert alert-info">
                        Galeria nie zawiera jeszcze zdjęć.
                    </div>
                @endif
            </div>



        </div>
    </div>

    <style>
        .public-brand-marker {
            width: 42px;
            height: 42px;
            overflow: hidden;
            background: #fff;
            border: 2px solid #fff;
            border-radius: 50%;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.28);
        }

        .public-brand-marker img {
            width: 100%;
            height: 100%;
            display: block;
            object-fit: cover;
        }

        .public-info-table,
        .public-lanes-table {
            border: 1px solid rgba(52, 58, 64, .18);
            border-collapse: separate;
            border-radius: .75rem;
            border-spacing: 0;
            overflow: hidden;
        }

        .public-info-table th {
            width: 38%;
            background: #f1f5f9;
            color: #343a40;
            font-size: .82rem;
            text-transform: uppercase;
        }

        .public-info-table td,
        .public-info-table th,
        .public-lanes-table td,
        .public-lanes-table th {
            border-bottom-color: rgba(52, 58, 64, .1);
            overflow-wrap: anywhere;
            word-break: normal;
        }

        .public-info-table a,
        .public-lanes-table a {
            overflow-wrap: anywhere;
        }

        .public-lanes-table thead th {
            background: #212529;
            color: #fff;
            font-size: .78rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .public-lanes-table tbody tr:nth-child(even) td {
            background: #f8fafc;
        }

        .public-lanes-table tbody tr:hover td {
            background: #eef5ff;
        }

        @media (min-width: 768px) {
            .header-actions {
                width: auto !important;
            }
        }

        /*zakładki*/

        #shootingRangeTabs {
            gap: 0;
            border-bottom: 0;
        }

        #shootingRangeTabs .nav-item {
            min-width: 0;
        }

        #shootingRangeTabs .nav-link {
            width: 100%;
            height: 100%;
            padding: 12px 16px;
            color: #495057;
            background-color: #fff;
            border: 1px solid #dee2e6;
            border-bottom-color: transparent;
            border-radius: 0;
            white-space: nowrap;
            text-align: center;
        }

        #shootingRangeTabs .nav-item:first-child .nav-link {
            border-top-left-radius: 6px;
        }

        #shootingRangeTabs .nav-item:last-child .nav-link {
            border-top-right-radius: 6px;
        }

        #shootingRangeTabs .nav-item + .nav-item .nav-link {
            border-left: 0;
        }

        #shootingRangeTabs .nav-link.active {
            position: relative;
            z-index: 2;
            color: #0d6efd;
            font-weight: 600;
            border-color: #dee2e6;
            border-bottom-color: #fff;
        }

        #shootingRangeTabs .nav-link:not(.active) {
            border-bottom-color: #dee2e6;
        }

        #shootingRangeTabs .nav-link:hover {
            color: #0d6efd;
        }

        @media (max-width: 575.98px) {
            #shootingRangeTabs .nav-link {
                padding: 10px 8px;
            }

            #shootingRangeTabs .nav-link:not(.active) .tab-label {
                display: none;
            }

            #shootingRangeTabs .nav-link:not(.active) i {
                font-size: 1.25rem;
            }
        }

        /*galeria*/
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
        }

        .gallery-item {
            position: relative;
            display: block;
            overflow: hidden;
            aspect-ratio: 4 / 3;
            background: #eee;
            border-radius: 4px;
        }

        .gallery-item img {
            width: 100%;
            height: 100%;
            display: block;
            object-fit: cover;
            transition: transform 0.25s ease;
        }

        .gallery-item-overlay {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            background: rgba(0, 0, 0, 0.35);
            font-size: 1.6rem;
            opacity: 0;
            transition: opacity 0.2s ease;
        }

        .gallery-item:hover img {
            transform: scale(1.04);
        }

        .gallery-item:hover .gallery-item-overlay {
            opacity: 1;
        }

        @media (max-width: 767.98px) {
            .gallery-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 8px;
            }

            .public-info-table,
            .public-lanes-table {
                border: 0;
                border-collapse: separate;
                border-spacing: 0 .75rem;
                overflow: visible;
            }

            .public-lanes-table thead {
                position: absolute;
                width: 1px;
                height: 1px;
                margin: -1px;
                padding: 0;
                overflow: hidden;
                clip: rect(0, 0, 0, 0);
                white-space: nowrap;
                border: 0;
            }

            .public-info-table,
            .public-info-table tbody,
            .public-info-table tr,
            .public-info-table th,
            .public-info-table td,
            .public-lanes-table,
            .public-lanes-table tbody,
            .public-lanes-table tr,
            .public-lanes-table td {
                display: block;
                width: 100%;
            }

            .public-info-table tr,
            .public-lanes-table tr {
                border: 1px solid rgba(0, 0, 0, .08);
                border-radius: .75rem;
                background: #fff;
                box-shadow: 0 .35rem 1rem rgba(15, 23, 42, .08);
                overflow: hidden;
            }

            .public-info-table tr {
                display: grid;
                grid-template-columns: minmax(7rem, 38%) minmax(0, 1fr);
                align-items: stretch;
            }

            .public-lanes-table td {
                display: grid;
                grid-template-columns: minmax(6.5rem, 38%) minmax(0, 1fr);
                gap: .75rem;
                align-items: start;
                padding: .65rem .85rem;
                border-bottom: 1px solid rgba(0, 0, 0, .06);
            }

            .public-info-table th {
                width: 100%;
                display: flex;
                align-items: center;
                background: #f8fafc;
                border-right: 1px solid rgba(0, 0, 0, .06);
                border-bottom: 0;
            }

            .public-info-table td {
                display: flex;
                align-items: center;
                min-width: 0;
                padding: .65rem .85rem;
                border-bottom: 0;
            }

            .public-lanes-table td::before {
                content: attr(data-label);
                color: #6c757d;
                font-size: .78rem;
                font-weight: 700;
                text-transform: uppercase;
            }

            @media (max-width: 420px) {
                .public-info-table tr {
                    grid-template-columns: 1fr;
                }

                .public-info-table th {
                    border-right: 0;
                    border-bottom: 1px solid rgba(0, 0, 0, .06);
                }
            }
        }
    </style>

    <script>
        window.initPublicShootingRangeMap = function () {
            const position = {
                lat: Number(@json($shootingRange->lat)),
                lng: Number(@json($shootingRange->lng)),
            };

            const map = new google.maps.Map(
                document.getElementById('shootingRangeMap'),
                {
                    center: position,
                    zoom: 14,
                    mapId: @json(config('services.google_maps.map_id')),
                }
            );

            const markerOptions = {
                map: map,
                position: position,
                title: @json($shootingRange->name),
                gmpClickable: true,
            };

            @if ($brandUrl)
                const markerContent = document.createElement('div');
                markerContent.className = 'public-brand-marker';

                const markerImage = document.createElement('img');
                markerImage.src = @json($brandUrl);
                markerImage.alt = @json($shootingRange->name);

                markerContent.appendChild(markerImage);
                markerOptions.content = markerContent;
            @endif

            const marker = new google.maps.marker.AdvancedMarkerElement(
                markerOptions
            );

            const infoWindow = new google.maps.InfoWindow({
                content: `
                    <div style="max-width: 240px;">
                        <strong>${@json($shootingRange->name)}</strong><br>
                        ${@json($shootingRange->full_address)}
                    </div>
                `,
            });

            marker.addEventListener('gmp-click', function () {
                infoWindow.open({
                    map: map,
                    anchor: marker,
                });
            });
        };
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            Fancybox.bind('[data-fancybox="shooting-range-gallery"]', {
                Thumbs: {
                    type: 'classic',
                },

                Toolbar: {
                    display: {
                        left: ['infobar'],
                        middle: [],
                        right: ['slideshow', 'fullscreen', 'thumbs', 'close'],
                    },
                },

                Images: {
                    zoom: true,
                },
            });
        });
    </script>


    <script
        src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google_maps.key') }}&libraries=marker&loading=async&callback=initPublicShootingRangeMap"
        async
        defer
    ></script>






















    @if ($gallery && $gallery->is_visible && $gallery->photos->isNotEmpty())
        <div
            class="modal fade"
            id="galleryViewerModal"
            tabindex="-1"
            aria-hidden="true"
        >
            <div class="modal-dialog modal-xl modal-dialog-centered">
                <div class="text-white modal-content bg-dark">
                    <div class="modal-header border-secondary">
                        <div>
                            <h5 class="mb-0 modal-title">Galeria</h5>
                            <small id="galleryViewerCounter" class="text-light"></small>
                        </div>

                        <button
                            type="button"
                            class="btn-close btn-close-white"
                            data-bs-dismiss="modal"
                            aria-label="Zamknij"
                        ></button>
                    </div>

                    <div class="position-relative modal-body">
                        <div
                            class="d-flex align-items-center justify-content-center"
                            style="min-height: 65vh;"
                        >
                            <img
                                id="galleryViewerImage"
                                src=""
                                alt=""
                                class="img-fluid"
                                style="max-height: 72vh; object-fit: contain;"
                            >
                        </div>

                        <button
                            type="button"
                            id="galleryPreviousButton"
                            class="gallery-navigation gallery-navigation-previous"
                            title="Poprzednie zdjęcie"
                        >
                            <i class="bi bi-chevron-left"></i>
                        </button>

                        <button
                            type="button"
                            id="galleryNextButton"
                            class="gallery-navigation gallery-navigation-next"
                            title="Następne zdjęcie"
                        >
                            <i class="bi bi-chevron-right"></i>
                        </button>
                    </div>

                    <div
                        id="galleryViewerDescription"
                        class="text-center border-secondary modal-footer justify-content-center"
                    ></div>
                </div>
            </div>
        </div>
    @endif
</x-app-layout>
