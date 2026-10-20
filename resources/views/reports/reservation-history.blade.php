<x-app-layout>
    <div class="page-bg-2"></div>

    @php
        $boolLabel = fn ($value) => $value ? 'Tak' : 'Nie';
        $sourceLabels = [
            \App\Models\Reservation::SOURCE_ONLINE_ACCOUNT => 'Klient z kontem',
            \App\Models\Reservation::SOURCE_STAFF_ACCOUNT => 'Obsługa dla klienta',
            \App\Models\Reservation::SOURCE_STAFF_GUEST => 'Obsługa dla gościa',
        ];
        $exportFormats = [
            'pdf' => ['PDF', 'bi-file-earmark-pdf'],
            'xml' => ['XML', 'bi-filetype-xml'],
            'xls' => ['XLS', 'bi-file-earmark-excel'],
            'xlsx' => ['XLSX', 'bi-file-earmark-spreadsheet'],
            'csv' => ['CSV', 'bi-filetype-csv'],
        ];
        $sortLink = function (string $key, string $label) use ($filters) {
            $active = ($filters['sort'] ?? 'start') === $key;
            $nextDirection = $active && ($filters['direction'] ?? 'desc') === 'asc' ? 'desc' : 'asc';
            $icon = $active
                ? (($filters['direction'] ?? 'desc') === 'asc' ? 'bi-sort-up' : 'bi-sort-down')
                : 'bi-arrow-down-up';

            return sprintf(
                '<a class="report-sort-link %s" href="%s"><span>%s</span><i class="bi %s" aria-hidden="true"></i></a>',
                $active ? 'is-active' : '',
                e(request()->fullUrlWithQuery([
                    'sort' => $key,
                    'direction' => $nextDirection,
                    'page' => null,
                ])),
                e($label),
                e($icon)
            );
        };
    @endphp

    <main class="container py-4">
        <x-session-alerts />

        <header class="p-4 mb-4 text-white rounded bg-secondary">
            <div class="gap-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                <div>
                    <div class="gap-2 mb-2 d-flex align-items-center">
                        <i class="bi bi-clipboard-data-fill fs-4" aria-hidden="true"></i>
                        <span class="opacity-75 text-uppercase small">Raport</span>
                    </div>
                    <h1 class="mb-1 h3">Historia rezerwacji</h1>
                    <p class="mb-0 opacity-75">Rezerwacje bieżące i archiwalne według wybranych kryteriów.</p>
                </div>

                <a href="{{ route('reports.index') }}" class="btn btn-light">
                    <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>
                    Raporty
                </a>
            </div>
        </header>

        <form method="GET" action="{{ route('reports.reservations.history') }}" class="p-3 mb-4 bg-white rounded shadow-sm report-filter-panel">
            <div class="row g-3 align-items-end">
                <div class="col-md-6 col-xl-3">
                    <label for="date_from" class="form-label">Data od</label>
                    <input
                        type="date"
                        id="date_from"
                        name="date_from"
                        value="{{ $filters['date_from'] }}"
                        class="form-control"
                        data-date-picker-only
                    >
                </div>

                <div class="col-md-6 col-xl-3">
                    <label for="date_to" class="form-label">Data do</label>
                    <input
                        type="date"
                        id="date_to"
                        name="date_to"
                        value="{{ $filters['date_to'] }}"
                        class="form-control"
                        data-date-picker-only
                    >
                </div>

                <div class="col-md-6 col-xl-3">
                    <label for="shooting_range_id" class="form-label">Strzelnica</label>
                    <select id="shooting_range_id" name="shooting_range_id" class="form-select">
                        <option value="">Wszystkie dostępne</option>
                        @foreach ($managedRanges as $range)
                            <option value="{{ $range->id }}" @selected((int) ($filters['shooting_range_id'] ?? 0) === $range->id)>
                                {{ $range->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-6 col-xl-3">
                    <label for="shooting_leader" class="form-label">Prowadzący strzelanie</label>
                    <select id="shooting_leader" name="shooting_leader" class="form-select">
                        <option value="all" @selected($filters['shooting_leader'] === 'all')>Wszystkie</option>
                        <option value="yes" @selected($filters['shooting_leader'] === 'yes')>Tylko z prowadzącym</option>
                        <option value="no" @selected($filters['shooting_leader'] === 'no')>Bez prowadzącego</option>
                    </select>
                </div>

                <div class="col-md-6 col-xl-3">
                    <label for="dynamic_shooting" class="form-label">Strzelanie dynamiczne</label>
                    <select id="dynamic_shooting" name="dynamic_shooting" class="form-select">
                        <option value="all" @selected($filters['dynamic_shooting'] === 'all')>Wszystkie</option>
                        <option value="yes" @selected($filters['dynamic_shooting'] === 'yes')>Tylko dynamiczne</option>
                        <option value="no" @selected($filters['dynamic_shooting'] === 'no')>Bez dynamicznego</option>
                    </select>
                </div>

                <div class="col-md-6 col-xl-3">
                    <label for="lane_ids" class="form-label">Osie strzelnicy</label>
                    <select id="lane_ids" name="lane_ids[]" class="form-select" multiple size="4">
                        @foreach ($lanes as $lane)
                            <option value="{{ $lane->id }}" @selected(in_array($lane->id, $filters['lane_ids'], false))>
                                {{ $lane->name }}
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text">Przytrzymaj Ctrl, aby zaznaczyć kilka osi.</div>
                </div>

                <div class="col-xl-6">
                    <label class="form-label">Status rezerwacji</label>
                    <div class="gap-2 d-flex flex-wrap">
                        @foreach ($statusOptions as $status => $label)
                            <label class="report-check">
                                <input
                                    type="checkbox"
                                    name="statuses[]"
                                    value="{{ $status }}"
                                    class="form-check-input"
                                    @checked(in_array($status, $filters['statuses'], true))
                                >
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="col-12 d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-funnel me-1" aria-hidden="true"></i>
                        Generuj raport
                    </button>
                    <a href="{{ route('reports.reservations.history') }}" class="btn btn-outline-dark">
                        <i class="bi bi-x-circle me-1" aria-hidden="true"></i>
                        Wyczyść
                    </a>
                </div>
            </div>
        </form>

        <section class="p-3 bg-white rounded shadow-sm">
            <div class="gap-2 mb-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                <div>
                    <h2 class="mb-1 h5">Wyniki</h2>
                    <p class="mb-0 text-muted small">Liczba rekordów: {{ $records->total() }}</p>
                </div>
                <div class="gap-2 d-flex flex-wrap">
                    @foreach ($exportFormats as $format => [$label, $icon])
                        <a
                            href="{{ route('reports.reservations.history.export', ['format' => $format] + request()->query()) }}"
                            class="btn btn-sm btn-outline-dark report-export-button"
                        >
                            <i class="bi {{ $icon }} me-1" aria-hidden="true"></i>
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
            </div>

            @if ($records->isEmpty())
                <div class="alert alert-info mb-0">Brak rezerwacji spełniających wybrane kryteria.</div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle report-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>{!! $sortLink('customer', 'Klient') !!}</th>
                                <th>{!! $sortLink('shooting_range', 'Strzelnica') !!}</th>
                                <th>{!! $sortLink('start', 'Termin') !!}</th>
                                <th>{!! $sortLink('lane', 'Oś') !!}</th>
                                <th>Status</th>
                                <th>{!! $sortLink('shooting_leader', 'Prowadzący') !!}</th>
                                <th>{!! $sortLink('dynamic_shooting', 'Dynamiczne') !!}</th>
                                <th>{!! $sortLink('source', 'Źródło') !!}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($records as $record)
                                @php
                                    $statusLabel = $statusOptions[$record->status] ?? $record->status;
                                    $sourceLabel = $sourceLabels[$record->source] ?? $record->source;
                                    $start = \Carbon\Carbon::parse($record->start);
                                    $end = \Carbon\Carbon::parse($record->end);
                                @endphp
                                <tr
                                    class="report-clickable-row"
                                    data-bs-toggle="modal"
                                    data-bs-target="#reservationReportDetails{{ $record->source_table }}{{ $record->reservation_id }}"
                                    tabindex="0"
                                    role="button"
                                >
                                    <td data-label="ID">
                                        #{{ $record->original_reservation_id }}
                                        <small class="d-block text-muted">{{ $record->source_table === 'archive' ? 'archiwum' : 'bieżąca' }}</small>
                                    </td>
                                    <td data-label="Klient">
                                        <strong>{{ $record->customer_name ?: 'Brak danych' }}</strong>
                                        @if ($record->customer_email)
                                            <small class="d-block text-muted">{{ $record->customer_email }}</small>
                                        @endif
                                        @if ($record->customer_phone)
                                            <small class="d-block text-muted">{{ $record->customer_phone }}</small>
                                        @endif
                                    </td>
                                    <td data-label="Strzelnica">
                                        {{ $record->shooting_range_name ?: 'Usunięta strzelnica' }}
                                        @if ($record->shooting_range_town)
                                            <small class="d-block text-muted">{{ $record->shooting_range_town }}</small>
                                        @endif
                                    </td>
                                    <td class="text-nowrap" data-label="Termin">
                                        {{ $start->format('d.m.Y') }}
                                        <small class="d-block text-muted">
                                            {{ $start->format('H:i') }}
                                            -
                                            {{ $end->format('H:i') }}
                                        </small>
                                    </td>
                                    <td data-label="Oś">{{ $record->lane_name ?: 'Usunięta oś' }}</td>
                                    <td data-label="Status">
                                        <span class="badge text-bg-secondary">
                                            {{ $statusLabel }}
                                        </span>
                                    </td>
                                    <td data-label="Prowadzący">
                                        {{ $boolLabel((bool) $record->has_shooting_leader_license) }}
                                        @if ($record->shooting_leader_license_number)
                                            <small class="d-block text-muted">{{ $record->shooting_leader_license_number }}</small>
                                        @endif
                                    </td>
                                    <td data-label="Dynamiczne">{{ $boolLabel((bool) $record->is_dynamic_shooting) }}</td>
                                    <td data-label="Źródło">{{ $sourceLabel }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @foreach ($records as $record)
                    @php
                        $statusLabel = $statusOptions[$record->status] ?? $record->status;
                        $sourceLabel = $sourceLabels[$record->source] ?? $record->source;
                        $start = \Carbon\Carbon::parse($record->start);
                        $end = \Carbon\Carbon::parse($record->end);
                    @endphp
                    <div class="modal fade" id="reservationReportDetails{{ $record->source_table }}{{ $record->reservation_id }}" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h2 class="modal-title h5">Szczegóły rezerwacji #{{ $record->original_reservation_id }}</h2>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                                </div>
                                <div class="modal-body">
                                    <dl class="row mb-0 report-details-list">
                                        <dt class="col-sm-4">Typ rekordu</dt>
                                        <dd class="col-sm-8">{{ $record->source_table === 'archive' ? 'Archiwum' : 'Rezerwacja bieżąca' }}</dd>

                                        <dt class="col-sm-4">Klient</dt>
                                        <dd class="col-sm-8">
                                            <strong>{{ $record->customer_name ?: 'Brak danych' }}</strong>
                                            @if ($record->customer_email)
                                                <div>{{ $record->customer_email }}</div>
                                            @endif
                                            @if ($record->customer_phone)
                                                <div>{{ $record->customer_phone }}</div>
                                            @endif
                                        </dd>

                                        <dt class="col-sm-4">Strzelnica</dt>
                                        <dd class="col-sm-8">
                                            {{ $record->shooting_range_name ?: 'Usunięta strzelnica' }}
                                            @if ($record->shooting_range_town)
                                                <div class="text-muted">{{ $record->shooting_range_town }}</div>
                                            @endif
                                        </dd>

                                        <dt class="col-sm-4">Termin</dt>
                                        <dd class="col-sm-8">
                                            {{ $start->format('d.m.Y H:i') }} - {{ $end->format('d.m.Y H:i') }}
                                        </dd>

                                        <dt class="col-sm-4">Oś</dt>
                                        <dd class="col-sm-8">{{ $record->lane_name ?: 'Usunięta oś' }}</dd>

                                        <dt class="col-sm-4">Liczba stanowisk</dt>
                                        <dd class="col-sm-8">{{ $record->positions_count ?: '-' }}</dd>

                                        <dt class="col-sm-4">Status</dt>
                                        <dd class="col-sm-8">{{ $statusLabel }}</dd>

                                        <dt class="col-sm-4">Prowadzący strzelanie</dt>
                                        <dd class="col-sm-8">
                                            {{ $boolLabel((bool) $record->has_shooting_leader_license) }}
                                            @if ($record->shooting_leader_license_number)
                                                <div class="text-muted">Nr: {{ $record->shooting_leader_license_number }}</div>
                                            @endif
                                        </dd>

                                        <dt class="col-sm-4">Strzelanie dynamiczne</dt>
                                        <dd class="col-sm-8">{{ $boolLabel((bool) $record->is_dynamic_shooting) }}</dd>

                                        <dt class="col-sm-4">Źródło</dt>
                                        <dd class="col-sm-8">{{ $sourceLabel }}</dd>

                                        <dt class="col-sm-4">Utworzono</dt>
                                        <dd class="col-sm-8">
                                            {{ $record->reservation_created_at ? \Carbon\Carbon::parse($record->reservation_created_at)->format('d.m.Y H:i') : '-' }}
                                        </dd>
                                    </dl>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-dark" data-bs-dismiss="modal">Zamknij</button>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach

                {{ $records->links() }}
            @endif
        </section>
    </main>

    <style>
        .report-filter-panel,
        .report-table {
            border: 1px solid rgba(33, 37, 41, .08);
        }

        .report-check {
            align-items: center;
            background: #f8f9fa;
            border: 1px solid rgba(33, 37, 41, .12);
            border-radius: .5rem;
            display: inline-flex;
            gap: .45rem;
            padding: .45rem .7rem;
        }

        .report-check .form-check-input {
            margin: 0;
        }

        .report-table thead th {
            background: #212529;
            color: #fff;
            white-space: nowrap;
        }

        .report-sort-link {
            align-items: center;
            color: inherit;
            display: inline-flex;
            gap: .35rem;
            text-decoration: none;
        }

        .report-sort-link:hover,
        .report-sort-link.is-active {
            color: #ffc9c9;
        }

        .report-clickable-row {
            cursor: pointer;
        }

        .report-clickable-row:focus {
            outline: 2px solid rgba(185, 28, 28, .45);
            outline-offset: -2px;
        }

        .report-export-button {
            align-items: center;
            display: inline-flex;
        }

        .report-details-list dt,
        .report-details-list dd {
            border-bottom: 1px solid rgba(33, 37, 41, .08);
            padding-bottom: .55rem;
            padding-top: .55rem;
        }

        @media (max-width: 767.98px) {
            .report-table thead {
                display: none;
            }

            .report-table,
            .report-table tbody,
            .report-table tr,
            .report-table td {
                display: block;
                width: 100%;
            }

            .report-table tr {
                border: 1px solid rgba(33, 37, 41, .12);
                border-radius: .5rem;
                margin-bottom: .75rem;
                padding: .5rem;
            }

            .report-table td {
                border: 0;
                padding: .35rem .25rem;
            }

            .report-table td::before {
                color: #6c757d;
                content: attr(data-label) ": ";
                display: block;
                font-size: .78rem;
                font-weight: 700;
                text-transform: uppercase;
            }
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.report-clickable-row').forEach(function (row) {
                row.addEventListener('keydown', function (event) {
                    if (!['Enter', ' '].includes(event.key)) {
                        return;
                    }

                    event.preventDefault();
                    const modalElement = document.querySelector(row.dataset.bsTarget);

                    if (modalElement) {
                        bootstrap.Modal.getOrCreateInstance(modalElement).show();
                    }
                });
            });
        });
    </script>
</x-app-layout>
