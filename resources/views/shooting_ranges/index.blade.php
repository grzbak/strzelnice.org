<x-app-layout>
    <div class="page-bg-2"></div>

    <main class="shooting-list-page">
        <div class="container py-4 py-lg-5">
            <form
                method="GET"
                action="{{ route('shootings.list') }}"
                id="shootingListFilters"
                class="p-3 mb-4 bg-white border shadow-sm p-md-4 shooting-list-filters"
            >
                <div class="row g-3 align-items-end">
                    <div class="col-md-7 col-lg-8">
                        <label for="cityFilter" class="form-label fw-semibold">
                            Miasto wojewódzkie
                        </label>

                        <select id="cityFilter" name="m" class="form-select">
                            <option value="">Cała Polska</option>

                            @foreach ($cities as $city => $voivodeship)
                                <option value="{{ $city }}" @selected($selectedCity === $city)>
                                    {{ $city }} — {{ $voivodeship }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-5 col-lg-4 d-flex justify-content-md-end">
                        <div class="form-check form-switch shooting-online-filter">
                            <input
                                type="checkbox"
                                class="form-check-input"
                                id="onlineReservationOnly"
                            >

                            <label class="form-check-label fw-semibold" for="onlineReservationOnly">
                                Pokaż tylko z rezerwacją online
                            </label>
                        </div>
                    </div>
                </div>

                <div class="gap-3 pt-3 mt-4 border-top d-flex align-items-center justify-content-between">
                    <h2 class="mb-0 h4">Strzelnice</h2>
                    <span id="visibleResultsInfo" class="text-muted small"></span>
                </div>
            </form>

            <div id="shootingRangeList" class="d-grid shooting-range-list">
                @foreach ($shootings as $shootingRange)
                    <div
                        class="shooting-range-result"
                        data-online="{{ $shootingRange->can_reserve_online ? '1' : '0' }}"
                    >
                        <article class="bg-white border shadow-sm shooting-range-card">
                            <div class="shooting-range-brand">
                                @if ($shootingRange->brand_url)
                                    <img
                                        src="{{ $shootingRange->brand_url }}"
                                        alt="Logo {{ $shootingRange->name }}"
                                        loading="lazy"
                                    >
                                @else
                                    <i class="bi bi-bullseye" aria-hidden="true"></i>
                                @endif
                            </div>

                            <div class="p-3 p-md-4 d-flex flex-column justify-content-center flex-grow-1">
                                <div class="flex-wrap gap-2 mb-2 d-flex align-items-center">
                                    <h3 class="mb-0 h5">{{ $shootingRange->name }}</h3>

                                    @if ($shootingRange->can_reserve_online)
                                        <span class="shooting-online-status">
                                            <span class="shooting-online-dot" aria-hidden="true"></span>
                                            Online
                                        </span>
                                    @endif

                                    @if (! $shootingRange->is_public_access)
                                        <span
                                            class="shooting-private-status"
                                            title="Rezerwacje są dostępne wyłącznie dla zaakceptowanych użytkowników"
                                        >
                                            <i class="bi bi-lock-fill" aria-hidden="true"></i>
                                            Grupa zamknięta
                                        </span>
                                    @endif
                                </div>

                                <div>
                                    <div class="text-muted small">
                                        <i class="bi bi-geo-alt-fill me-1" aria-hidden="true"></i>
                                        {{ $shootingRange->full_address }}
                                    </div>
                                </div>

                                <div class="gap-2 mt-3 d-flex align-items-center">
                                    @if ($shootingRange->navLink)
                                        <a
                                            href="{{ $shootingRange->navLink }}"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="btn btn-outline-primary btn-sm shooting-range-icon-action"
                                            title="Nawiguj"
                                            aria-label="Nawiguj"
                                        >
                                            <i class="bi bi-signpost-2-fill me-1" aria-hidden="true"></i>
                                            Nawiguj
                                        </a>
                                    @endif

                                    @if ($shootingRange->can_reserve_online)
                                        <a
                                            href="{{ url('/rezerwacje/' . $shootingRange->id) }}?date={{ now()->format('Y-m-d') }}"
                                            class="btn btn-success btn-sm shooting-range-icon-action"
                                            title="Rezerwuj"
                                            aria-label="Rezerwuj"
                                        >
                                            <i class="bi bi-calendar-check-fill me-1" aria-hidden="true"></i>
                                            Rezerwuj
                                        </a>
                                    @endif
                                </div>
                            </div>

                            <div class="p-3 p-md-4 shooting-range-actions">
                                <a
                                    href="{{ route('shooting.public.show', ['endpoint' => $shootingRange->endpoint]) }}"
                                    class="shooting-range-more-link"
                                >
                                    Zobacz więcej <span aria-hidden="true">&gt;</span>
                                </a>
                            </div>
                        </article>
                    </div>
                @endforeach
            </div>

            <div id="emptyShootingRanges" class="p-4 mt-4 text-center bg-white border d-none shooting-list-empty">
                <i class="mb-2 bi bi-search d-block text-muted fs-3" aria-hidden="true"></i>
                <span class="text-muted">Brak strzelnic spełniających wybrane kryteria.</span>
            </div>

            <div class="mt-4 text-center">
                <button type="button" id="showMoreShootingRanges" class="btn shooting-list-more-button d-none">
                    Więcej strzelnic
                    <i class="bi bi-chevron-down ms-1" aria-hidden="true"></i>
                </button>
            </div>
        </div>
    </main>

    <style>
        .shooting-list-page {
            min-height: 70vh;
        }

        .shooting-list-filters,
        .shooting-range-card,
        .shooting-list-empty {
            border-radius: 6px;
        }

        .shooting-online-filter {
            min-height: 38px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .shooting-range-card {
            overflow: hidden;
            display: grid;
            grid-template-columns: 180px minmax(0, 1fr) auto;
            min-height: 150px;
            transition: background-color 0.18s ease, border-color 0.18s ease, box-shadow 0.18s ease;
        }

        .shooting-range-card:hover {
            background-color: #fafafa !important;
            border-color: #aeb5b7 !important;
            box-shadow:
                0 0 0 3px rgba(108, 117, 125, 0.1),
                0 8px 22px rgba(31, 45, 48, 0.14) !important;
        }

        .shooting-range-list {
            gap: 8px;
        }

        .shooting-range-brand {
            min-height: 150px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: #9aa5a8;
            background: #eef2f2;
            border-right: 1px solid #e1e7e7;
            font-size: 3rem;
        }

        .shooting-range-brand img {
            width: 100%;
            height: 100%;
            display: block;
            object-fit: contain;
        }

        .shooting-range-actions {
            min-width: 145px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-left: 1px solid #e1e7e7;
        }

        .shooting-range-icon-action {
            height: 36px;
            padding: 0 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .shooting-range-more-link {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: #212529;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 600;
            white-space: nowrap;
            transition: color 0.18s ease;
        }

        .shooting-range-more-link:hover,
        .shooting-range-more-link:focus-visible {
            color: #212529;
            text-decoration: none;
            font-weight: 700;
        }

        .shooting-list-more-button {
            color: #212529;
            background: #fff;
            border: 1px solid #d7dede;
            box-shadow: 0 2px 8px rgba(31, 45, 48, 0.05);
            transition: background-color 0.18s ease, border-color 0.18s ease;
        }

        .shooting-list-more-button:hover,
        .shooting-list-more-button:focus-visible {
            color: #212529;
            background: #eef1f1;
            border-color: #cbd3d4;
        }

        .shooting-online-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #198754;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .shooting-online-dot {
            width: 8px;
            height: 8px;
            background: #198754;
            border-radius: 50%;
            box-shadow: 0 0 0 0 rgba(25, 135, 84, 0.45);
            animation: shooting-online-pulse 1.8s infinite;
        }

        .shooting-private-status {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: #8a5b00;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        @keyframes shooting-online-pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(25, 135, 84, 0.45);
            }

            70% {
                box-shadow: 0 0 0 8px rgba(25, 135, 84, 0);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(25, 135, 84, 0);
            }
        }

        @media (max-width: 767.98px) {
            .shooting-range-card {
                grid-template-columns: 90px minmax(0, 1fr) auto;
            }

            .shooting-range-brand {
                min-height: 120px;
                padding: 10px;
            }

            .shooting-range-actions {
                min-width: 92px;
                padding-left: 6px !important;
                padding-right: 6px !important;
            }

            .shooting-range-more-link {
                font-size: 0.75rem;
            }

        }

        @media (prefers-reduced-motion: reduce) {
            .shooting-online-dot {
                animation: none;
            }
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('shootingListFilters');
            const cityFilter = document.getElementById('cityFilter');
            const onlineOnly = document.getElementById('onlineReservationOnly');
            const results = [...document.querySelectorAll('.shooting-range-result')];
            const showMoreButton = document.getElementById('showMoreShootingRanges');
            const emptyState = document.getElementById('emptyShootingRanges');
            const visibleResultsInfo = document.getElementById('visibleResultsInfo');

            const pageSize = 10;
            let visibleLimit = pageSize;

            function renderResults() {
                const filtered = results.filter(result => {
                    return !onlineOnly.checked || result.dataset.online === '1';
                });

                results.forEach(result => result.classList.add('d-none'));
                filtered.slice(0, visibleLimit).forEach(result => result.classList.remove('d-none'));

                const visibleCount = Math.min(visibleLimit, filtered.length);

                visibleResultsInfo.textContent = filtered.length
                    ? `Wyświetlono ${visibleCount} z ${filtered.length}`
                    : '';

                emptyState.classList.toggle('d-none', filtered.length !== 0);
                showMoreButton.classList.toggle('d-none', visibleCount >= filtered.length);
            }

            cityFilter.addEventListener('change', function () {
                form.submit();
            });

            onlineOnly.addEventListener('change', function () {
                visibleLimit = pageSize;
                renderResults();
            });

            showMoreButton.addEventListener('click', function () {
                visibleLimit += pageSize;
                renderResults();
            });

            renderResults();
        });
    </script>
</x-app-layout>
