@props([
    'id' => 'data-table',
    'action' => url()->current(),
    'columns' => [],
    'paginator' => null,
    'search' => '',
    'searchLabel' => 'Wyszukaj',
    'searchPlaceholder' => 'Szukaj...',
    'sort' => null,
    'direction' => 'asc',
    'perPage' => 20,
    'perPageOptions' => [10, 20, 50],
    'selectable' => false,
    'bulkFormId' => null,
    'bulkAction' => null,
    'bulkMethod' => 'POST',
    'searchable' => true,
    'ajax' => true,
])

@php
    $searchFormId = "{$id}-search-form";
    $resultsId = "{$id}-results";
    $checkAllClass = "{$id}-check-all";
    $checkClass = "{$id}-check";
    $bulkActionsClass = "{$id}-bulk-actions";
    $bulkCounterClass = "{$id}-selected-count";
    $bulkFormId = $bulkFormId ?: "{$id}-bulk-form";

    $queryFor = function (array $overrides = []) use ($search, $sort, $direction, $perPage) {
        $query = request()->query();
        unset($query['page']);

        return array_filter(array_merge($query, [
            'search' => $search,
            'sort' => $sort,
            'direction' => $direction,
            'per_page' => $perPage,
        ], $overrides), fn ($value) => $value !== null && $value !== '');
    };

    $urlFor = fn (array $overrides = []) => $action.'?'.http_build_query($queryFor($overrides));

    $sortUrl = function (string $column) use ($sort, $direction, $urlFor) {
        $nextDirection = $sort === $column && $direction === 'asc'
            ? 'desc'
            : 'asc';

        return $urlFor([
            'sort' => $column,
            'direction' => $nextDirection,
        ]);
    };

    $sortIcon = function (string $column) use ($sort, $direction) {
        if ($sort !== $column) {
            return 'bi-arrow-down-up';
        }

        return $direction === 'asc'
            ? 'bi-sort-alpha-down'
            : 'bi-sort-alpha-up';
    };
@endphp

<div {{ $attributes->merge(['class' => 'p-3 mt-3 bg-white rounded data-table-panel']) }}>
    @if ($searchable)
    <form
        id="{{ $searchFormId }}"
        method="GET"
        action="{{ $action }}"
        class="gap-2 mb-2 data-table-search-form d-flex flex-column flex-md-row align-items-md-end"
    >
        @if ($sort)
            <input type="hidden" name="sort" value="{{ $sort }}">
            <input type="hidden" name="direction" value="{{ $direction }}">
        @endif

        <div class="flex-grow-1">
            <label class="form-label small text-muted" for="{{ $id }}-search">
                {{ $searchLabel }}
            </label>
            <div class="input-group">
                <span class="input-group-text">
                    <i class="bi bi-search" aria-hidden="true"></i>
                </span>
                <input
                    id="{{ $id }}-search"
                    name="search"
                    type="search"
                    class="form-control"
                    value="{{ $search }}"
                    placeholder="{{ $searchPlaceholder }}"
                >
            </div>
        </div>

        <div class="data-table-per-page-field">
            <label class="form-label small text-muted" for="{{ $id }}-per-page">
                Wiersze
            </label>
            <select
                id="{{ $id }}-per-page"
                name="per_page"
                class="form-select"
                aria-label="Liczba wierszy na stronie"
            >
                @foreach ($perPageOptions as $option)
                    <option value="{{ $option }}" @selected((int) $perPage === (int) $option)>
                        {{ $option }}
                    </option>
                @endforeach
            </select>
        </div>

        <a
            href="{{ $urlFor(['search' => null]) }}"
            class="btn btn-outline-secondary data-table-icon-button @if ($search === '') d-none @endif"
            data-bs-toggle="tooltip"
            data-bs-title="Wyczyść wyszukiwanie"
            aria-label="Wyczyść wyszukiwanie"
        >
            <i class="bi bi-x-lg" aria-hidden="true"></i>
        </a>
    </form>
    @endif

    <div id="{{ $resultsId }}" aria-live="polite">
        @if ($bulkAction)
            <form id="{{ $bulkFormId }}" method="POST" action="{{ $bulkAction }}"></form>
            <input type="hidden" name="_token" value="{{ csrf_token() }}" form="{{ $bulkFormId }}">
            @if (strtoupper($bulkMethod) !== 'POST')
                <input type="hidden" name="_method" value="{{ $bulkMethod }}" form="{{ $bulkFormId }}">
            @endif
        @endif

        @isset($bulkActions)
            <div class="gap-2 mb-3 data-table-bulk-actions {{ $bulkActionsClass }} d-none flex-wrap align-items-end">
                <div class="data-table-selected-counter">
                    <i class="bi bi-check2-square" aria-hidden="true"></i>
                    <span class="{{ $bulkCounterClass }}">0</span>
                    zazn.
                </div>

                {{ $bulkActions }}
            </div>
        @endisset

        <table class="table bg-white table-hover align-middle data-table">
            <thead>
                <tr>
                    @if ($selectable)
                        <th class="data-table-check-column">
                            <input
                                type="checkbox"
                                class="{{ $checkAllClass }}"
                                aria-label="Zaznacz wszystkie wiersze"
                            >
                        </th>
                    @endif

                    @foreach ($columns as $column)
                        @php
                            $key = $column['key'] ?? null;
                            $label = $column['label'] ?? $key;
                            $sortable = (bool) ($column['sortable'] ?? false);
                            $class = $column['class'] ?? '';
                        @endphp
                        <th @class([$class])>
                            @if ($sortable && $key)
                                <a href="{{ $sortUrl($key) }}" class="data-table-sort-link">
                                    <span>{{ $label }}</span>
                                    <i class="bi {{ $sortIcon($key) }}" aria-hidden="true"></i>
                                </a>
                            @else
                                {{ $label }}
                            @endif
                        </th>
                    @endforeach
                </tr>
            </thead>

            <tbody>
                {{ $slot }}
            </tbody>
        </table>

        @if ($paginator && $paginator->hasPages())
            <div class="gap-3 pt-2 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                <small class="text-muted">
                    Wyświetlono {{ $paginator->firstItem() }}-{{ $paginator->lastItem() }}
                    z {{ $paginator->total() }}
                </small>

                <div class="gap-2 d-flex align-items-center">
                    <a
                        href="{{ $paginator->previousPageUrl() ?? '#' }}"
                        class="btn btn-outline-dark btn-sm data-table-icon-button @if ($paginator->onFirstPage()) disabled @endif"
                        data-bs-toggle="tooltip"
                        data-bs-title="Poprzednia strona"
                        aria-label="Poprzednia strona"
                        @if ($paginator->onFirstPage()) aria-disabled="true" tabindex="-1" @endif
                    >
                        <i class="bi bi-chevron-left" aria-hidden="true"></i>
                    </a>

                    <form method="GET" action="{{ $action }}">
                        @foreach ($queryFor() as $name => $value)
                            @if (is_array($value))
                                @foreach ($value as $item)
                                    <input type="hidden" name="{{ $name }}[]" value="{{ $item }}">
                                @endforeach
                            @else
                                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                            @endif
                        @endforeach

                        <label class="visually-hidden" for="page">
                            Numer strony
                        </label>
                        <select
                            id="page"
                            name="page"
                            class="form-select form-select-sm"
                            onchange="this.form.submit()"
                            title="Wybierz stronę"
                        >
                            @for ($page = 1; $page <= $paginator->lastPage(); $page++)
                                <option
                                    value="{{ $page }}"
                                    @selected($page === $paginator->currentPage())
                                >
                                    Strona {{ $page }} z {{ $paginator->lastPage() }}
                                </option>
                            @endfor
                        </select>
                    </form>

                    <a
                        href="{{ $paginator->nextPageUrl() ?? '#' }}"
                        class="btn btn-outline-dark btn-sm data-table-icon-button @if (! $paginator->hasMorePages()) disabled @endif"
                        data-bs-toggle="tooltip"
                        data-bs-title="Następna strona"
                        aria-label="Następna strona"
                        @if (! $paginator->hasMorePages()) aria-disabled="true" tabindex="-1" @endif
                    >
                        <i class="bi bi-chevron-right" aria-hidden="true"></i>
                    </a>
                </div>
            </div>
        @endif
    </div>
</div>

@once
    <style>
        .data-table-panel {
            border: 1px solid rgba(52, 58, 64, .12);
            box-shadow: 0 .75rem 1.5rem rgba(15, 23, 42, .08);
        }

        .data-table-search-form {
            border-radius: .75rem;
            background: #f1f5f9;
            padding: .75rem;
        }

        .data-table-search-form .input-group-text {
            border-color: #adb5bd;
            background: #343a40;
            color: #fff;
        }

        .data-table-search-form .form-control {
            border-color: #adb5bd;
        }

        .data-table-per-page-field {
            width: 110px;
            flex: 0 0 110px;
        }

        .data-table-bulk-actions {
            display: flex;
            border-radius: .75rem;
            padding: .75rem;
            border: 1px solid rgba(13, 110, 253, .22);
            background: #e7f1ff;
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .55);
        }

        .data-table-selected-counter {
            display: inline-flex;
            min-height: 38px;
            align-items: center;
            gap: .35rem;
            border-radius: .5rem;
            background: #0d6efd;
            color: #fff;
            font-size: .9rem;
            font-weight: 700;
            padding: .35rem .65rem;
        }

        .data-table-icon-button {
            display: inline-grid;
            width: 38px;
            height: 38px;
            padding: 0;
            place-items: center;
        }

        .data-table-icon-button i {
            font-size: 1.15rem;
            line-height: 1;
        }

        .data-table-icon-button.btn-sm {
            width: 32px;
            height: 32px;
        }

        .data-table-icon-button.btn-sm i {
            font-size: 1rem;
        }

        .data-table {
            table-layout: fixed;
            border-collapse: separate;
            border-spacing: 0;
            overflow: hidden;
            border: 1px solid rgba(52, 58, 64, .18);
            border-radius: .75rem;
        }

        .data-table-check-column {
            width: 46px;
        }

        .data-table thead th {
            border-bottom: 2px solid rgba(0, 0, 0, .24);
            background: #212529;
            color: #fff;
            font-size: .78rem;
            font-weight: 700;
            letter-spacing: 0;
            text-transform: uppercase;
            vertical-align: middle;
        }

        .data-table thead th:first-child {
            border-top-left-radius: .65rem;
        }

        .data-table thead th:last-child {
            border-top-right-radius: .65rem;
        }

        .data-table-sort-link {
            display: inline-flex;
            max-width: 100%;
            align-items: center;
            gap: .45rem;
            padding: .15rem .2rem;
            border-radius: .35rem;
            color: #fff;
            text-decoration: none;
        }

        .data-table-sort-link:hover,
        .data-table-sort-link:focus {
            color: #cfe2ff;
            text-decoration: none;
        }

        .data-table-sort-link i {
            flex: 0 0 auto;
            font-size: 1.08rem;
            opacity: .9;
        }

        .data-table tbody td {
            border-bottom-color: rgba(52, 58, 64, .1);
            background: #fff;
        }

        .data-table tbody tr:nth-child(even) td {
            background: #f8fafc;
        }

        .data-table tbody tr:hover td {
            background: #eef5ff;
        }

        .data-table tbody tr:last-child td {
            border-bottom: 0;
        }

        @media (max-width: 767.98px) {
            .data-table-bulk-actions {
                display: flex;
                align-items: stretch !important;
            }

            .data-table-bulk-actions .data-table-icon-button {
                width: 100%;
                height: 42px;
            }

            .data-table {
                border-collapse: separate;
                border-spacing: 0 .75rem;
                table-layout: auto;
                overflow: visible;
                border: 0;
                border-radius: 0;
            }

            .data-table thead {
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

            .data-table,
            .data-table tbody,
            .data-table tr,
            .data-table td {
                display: block;
                width: 100%;
            }

            .data-table tr {
                border: 1px solid rgba(0, 0, 0, .08);
                border-radius: .75rem;
                background: #fff;
                box-shadow: 0 .35rem 1rem rgba(15, 23, 42, .08);
                overflow: hidden;
            }

            .data-table td {
                display: grid;
                grid-template-columns: minmax(6.5rem, 38%) minmax(0, 1fr);
                gap: .75rem;
                align-items: start;
                padding: .65rem .85rem;
                border-bottom: 1px solid rgba(0, 0, 0, .06);
            }

            .data-table td:last-child {
                border-bottom: 0;
            }

            .data-table td::before {
                content: attr(data-label);
                color: #6c757d;
                font-size: .78rem;
                font-weight: 700;
                line-height: 1.3;
                text-transform: uppercase;
            }
        }

        @media (max-width: 420px) {
            .data-table td {
                grid-template-columns: 1fr;
                gap: .25rem;
            }
        }
    </style>
@endonce

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById(@json($searchFormId));
        const input = document.getElementById(@json("{$id}-search"));
        const results = document.getElementById(@json($resultsId));
        const checkAllSelector = @json(".{$checkAllClass}");
        const checkSelector = @json(".{$checkClass}");
        const bulkActionsSelector = @json(".{$bulkActionsClass}");
        const bulkCounterSelector = @json(".{$bulkCounterClass}");
        let debounceTimer;
        let activeRequest;

        if (!results) {
            return;
        }

        const initializeTooltips = (container) => {
            if (!window.bootstrap?.Tooltip) {
                return;
            }

            container
                .querySelectorAll('[data-bs-toggle="tooltip"]')
                .forEach(element => {
                    bootstrap.Tooltip.getOrCreateInstance(element);
                });
        };

        initializeTooltips(document);

        const updateBulkActions = () => {
            const checkboxes = Array.from(results.querySelectorAll(checkSelector));
            const selectedCount = checkboxes
                .filter(checkbox => checkbox.checked)
                .length;
            const bulkActions = results.querySelector(bulkActionsSelector);
            const selectedCounter = results.querySelector(bulkCounterSelector);
            const checkAll = results.querySelector(checkAllSelector);

            bulkActions?.classList.toggle('d-none', selectedCount === 0);

            if (selectedCounter) {
                selectedCounter.textContent = selectedCount;
            }

            if (checkAll) {
                checkAll.checked = checkboxes.length > 0 && selectedCount === checkboxes.length;
                checkAll.indeterminate = selectedCount > 0 && selectedCount < checkboxes.length;
            }
        };

        const refreshResults = async () => {
            const url = new URL(form.action, window.location.origin);
            const formData = new FormData(form);

            formData.forEach((value, key) => {
                const normalizedValue = String(value).trim();

                if (normalizedValue !== '') {
                    url.searchParams.set(key, normalizedValue);
                } else {
                    url.searchParams.delete(key);
                }
            });

            activeRequest?.abort();

            const request = new AbortController();
            activeRequest = request;
            results.setAttribute('aria-busy', 'true');

            try {
                const response = await fetch(url, {
                    headers: {
                        'Accept': 'text/html',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    signal: request.signal
                });

                if (!response.ok) {
                    throw new Error('Nie udało się pobrać danych tabeli.');
                }

                const documentContent = new DOMParser()
                    .parseFromString(await response.text(), 'text/html');
                const newResults = documentContent.getElementById(@json($resultsId));

                if (!newResults) {
                    throw new Error('Odpowiedź nie zawiera danych tabeli.');
                }

                results
                    .querySelectorAll('[data-bs-toggle="tooltip"]')
                    .forEach(element => {
                        bootstrap.Tooltip.getInstance(element)?.dispose();
                    });
                results.innerHTML = newResults.innerHTML;
                initializeTooltips(results);
                updateBulkActions();
                window.history.replaceState({}, '', url);
            } catch (error) {
                if (error.name !== 'AbortError') {
                    console.error(error);
                }
            } finally {
                if (activeRequest === request) {
                    results.removeAttribute('aria-busy');
                }
            }
        };

        if (!form || !input) {
            updateBulkActions();
            return;
        }

        if (!@json($ajax)) {
            form.addEventListener('submit', event => event.preventDefault());
            updateBulkActions();
            return;
        }

        input.addEventListener('input', () => {
            window.clearTimeout(debounceTimer);
            debounceTimer = window.setTimeout(refreshResults, 350);
        });

        form.addEventListener('submit', event => {
            event.preventDefault();
            window.clearTimeout(debounceTimer);
            refreshResults();
        });

        form.elements.namedItem('per_page')?.addEventListener('change', () => {
            window.clearTimeout(debounceTimer);
            refreshResults();
        });

        results.addEventListener('change', event => {
            const target = event.target;

            if (target.matches(checkAllSelector)) {
                results
                    .querySelectorAll(checkSelector)
                    .forEach(checkbox => {
                        checkbox.checked = target.checked;
                    });
                updateBulkActions();

                return;
            }

            if (target.matches(checkSelector)) {
                updateBulkActions();
            }
        });

        updateBulkActions();
    });
</script>
