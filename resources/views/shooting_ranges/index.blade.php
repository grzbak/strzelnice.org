<x-app-layout :seo="$seo">
    <div class="page-bg-2"></div>

    <main class="shooting-list-page">
        <div class="container py-4 py-lg-5">
            {{-- Okruszki --}}
            <nav aria-label="Ścieżka nawigacji" class="mb-3 directory-breadcrumbs">
                <ol>
                    @foreach ($breadcrumbs as $crumb)
                        <li>
                            @if ($loop->last)
                                <span aria-current="page">{{ $crumb['name'] }}</span>
                            @else
                                <a href="{{ $crumb['url'] }}">{{ $crumb['name'] }}</a>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </nav>

            {{-- Nagłówek i opis --}}
            <header class="p-3 mb-4 bg-white border shadow-sm p-md-4 directory-intro">
                <h1 class="mb-3 h2">{{ $heading }}</h1>

                @foreach ($intro as $paragraph)
                    <p class="mb-2 text-muted">{{ $paragraph }}</p>
                @endforeach
            </header>

            <form
                method="GET"
                action="{{ route('shootings.list') }}"
                id="shootingListFilters"
                class="p-3 mb-4 bg-white border shadow-sm p-md-4 shooting-list-filters"
            >
                <div class="row g-3 align-items-end">
                    <div class="col-md-7 col-lg-8">
                        <label for="voivodeshipFilter" class="form-label fw-semibold">
                            Województwo
                        </label>

                        <select id="voivodeshipFilter" name="w" class="form-select">
                            <option value="" data-url="{{ route('shootings.list') }}">Cała Polska</option>

                            @foreach ($voivodeships as $slug => $name)
                                <option
                                    value="{{ $slug }}"
                                    data-url="{{ route('shootings.voivodeship', ['voivodeship' => $slug]) }}"
                                    @selected($selectedVoivodeship === $slug)
                                >
                                    {{ $name }}
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
                                name="online"
                                value="1"
                                @checked($onlineReservationOnly)
                            >

                            <label class="form-check-label fw-semibold" for="onlineReservationOnly">
                                Pokaż tylko z rezerwacją online
                            </label>
                        </div>
                    </div>
                </div>

                <div class="gap-3 pt-3 mt-4 border-top d-flex align-items-center justify-content-between">
                    <h2 class="mb-0 h4">Lista strzelnic</h2>
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
                                    <i class="bi bi-crosshair2" aria-hidden="true"></i>
                                @endif
                            </div>

                            <div class="p-3 p-md-4 d-flex flex-column justify-content-center flex-grow-1">
                                <div class="flex-wrap gap-2 mb-2 d-flex align-items-center">
                                    <h3 class="mb-0 h5">
                                        <a
                                            href="{{ route('shooting.public.show', ['endpoint' => $shootingRange->endpoint]) }}"
                                            class="shooting-range-title-link"
                                        >{{ $shootingRange->name }}</a>
                                    </h3>

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

                                <div class="gap-2 mt-3 d-flex align-items-center shooting-range-primary-actions">
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
                                @if ($shootingRange->navLink)
                                    <a
                                        href="{{ $shootingRange->navLink }}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="btn btn-outline-primary btn-sm shooting-range-icon-action shooting-range-mobile-action"
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
                                        class="btn btn-success btn-sm shooting-range-icon-action shooting-range-mobile-action"
                                        title="Rezerwuj"
                                        aria-label="Rezerwuj"
                                    >
                                        <i class="bi bi-calendar-check-fill me-1" aria-hidden="true"></i>
                                        Rezerwuj
                                    </a>
                                @endif

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

            @if ($regionLinks->isNotEmpty())
                <section class="p-3 mt-5 bg-white border shadow-sm p-md-4 directory-regions" aria-labelledby="directoryRegionsTitle">
                    <h2 id="directoryRegionsTitle" class="mb-3 h4">{{ $regionLinksTitle }}</h2>

                    <ul class="directory-region-list">
                        @foreach ($regionLinks as $region)
                            <li>
                                <a href="{{ $region['url'] }}">
                                    <span class="directory-region-name">{{ $region['name'] }}</span>
                                    @if (! empty($region['hint']))
                                        <span class="directory-region-hint">{{ $region['hint'] }}</span>
                                    @endif
                                    <span class="directory-region-count">{{ $region['count'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if (! empty($faq))
                <section class="p-3 mt-4 bg-white border shadow-sm p-md-4 directory-faq" aria-labelledby="directoryFaqTitle">
                    <h2 id="directoryFaqTitle" class="mb-3 h4">Najczęstsze pytania</h2>

                    @foreach ($faq as $item)
                        <details @if ($loop->first) open @endif>
                            <summary><h3 class="h6 d-inline">{{ $item['question'] }}</h3></summary>
                            <p class="mt-2 mb-0 text-muted">{{ $item['answer'] }}</p>
                        </details>
                    @endforeach
                </section>
            @endif
        </div>
    </main>

    <style>
        .shooting-list-page {
            min-height: 70vh;
        }

        .shooting-range-title-link {
            color: inherit;
            text-decoration: none;
        }

        .shooting-range-title-link:hover {
            text-decoration: underline;
        }

        .directory-breadcrumbs ol {
            display: inline-flex;
            padding: 6px 12px;
            background: rgba(255, 255, 255, 0.92);
            border-radius: 6px;
            box-shadow: 0 1px 4px rgba(31, 45, 48, 0.12);
            flex-wrap: wrap;
            gap: 4px;
            margin: 0;
            list-style: none;
            font-size: 0.85rem;
        }

        .directory-breadcrumbs li + li::before {
            content: "›";
            margin-right: 4px;
            color: #8a9396;
        }

        .directory-breadcrumbs a {
            color: #495057;
            text-decoration: none;
        }

        .directory-breadcrumbs a:hover {
            text-decoration: underline;
        }

        .directory-intro,
        .directory-regions,
        .directory-faq {
            border-radius: 6px;
        }

        .directory-region-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 6px 16px;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .directory-region-list a {
            display: flex;
            align-items: baseline;
            gap: 6px;
            padding: 6px 0;
            color: #212529;
            text-decoration: none;
            border-bottom: 1px solid #eef1f1;
        }

        .directory-region-list a:hover .directory-region-name {
            text-decoration: underline;
        }

        .directory-region-name {
            font-weight: 600;
        }

        .directory-region-hint {
            color: #6c757d;
            font-size: 0.8rem;
        }

        .directory-region-count {
            margin-left: auto;
            color: #6c757d;
            font-size: 0.8rem;
        }

        .directory-faq details {
            padding: 10px 0;
            border-bottom: 1px solid #eef1f1;
        }

        .directory-faq details:last-child {
            border-bottom: 0;
        }

        .directory-faq summary {
            cursor: pointer;
        }

        .directory-faq summary h3 {
            font-weight: 600;
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

        .shooting-range-mobile-action {
            display: none !important;
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
                grid-template-columns: 92px minmax(0, 1fr);
                min-height: 0;
            }

            .shooting-range-brand {
                min-height: 118px;
                padding: 10px;
            }

            .shooting-range-primary-actions {
                display: none !important;
            }

            .shooting-range-actions {
                grid-column: 1 / -1;
                min-width: 0;
                padding: 10px !important;
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(82px, 1fr));
                gap: 8px;
                border-top: 1px solid #e1e7e7;
                border-left: 0;
                background: #fbfcfc;
            }

            .shooting-range-mobile-action,
            .shooting-range-more-link {
                width: 100%;
                min-height: 38px;
                display: inline-flex !important;
                align-items: center;
                justify-content: center;
                border-radius: 4px;
                text-align: center;
                white-space: nowrap;
            }

            .shooting-range-more-link {
                padding: 0 10px;
                color: #212529;
                background: #fff;
                border: 1px solid #adb5bd;
            }

            .shooting-range-more-link:hover,
            .shooting-range-more-link:focus-visible {
                color: #fff;
                background: #212529;
                border-color: #212529;
            }

        }

        @media (max-width: 380px) {
            .shooting-range-actions {
                gap: 6px;
                padding: 8px !important;
            }

            .shooting-range-mobile-action,
            .shooting-range-more-link {
                min-height: 36px;
                padding-left: 6px;
                padding-right: 6px;
                font-size: 0.74rem;
            }

            .shooting-range-mobile-action i {
                margin-right: 0.25rem !important;
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
            const voivodeshipFilter = document.getElementById('voivodeshipFilter');
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

            function goToSelectedRegion() {
                const option = voivodeshipFilter.options[voivodeshipFilter.selectedIndex];
                const url = new URL(option.dataset.url, window.location.origin);

                if (onlineOnly.checked) {
                    url.searchParams.set('online', '1');
                }

                window.location.href = url.toString();
            }

            voivodeshipFilter.addEventListener('change', goToSelectedRegion);

            onlineOnly.addEventListener('change', function () {
                visibleLimit = pageSize;

                // Na stronie miejscowości zostajemy na tym samym adresie.
                const url = new URL(window.location.href);
                onlineOnly.checked ? url.searchParams.set('online', '1') : url.searchParams.delete('online');
                window.location.href = url.toString();
            });

            showMoreButton.addEventListener('click', function () {
                visibleLimit += pageSize;
                renderResults();
            });

            renderResults();
        });
    </script>
</x-app-layout>
