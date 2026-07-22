<x-app-layout>
    <div class="page-bg-2"></div>

    <main class="container py-4">
        <div class="col-xl-10 mx-auto reservations-page">
            <x-session-alerts />

            <section class="reservations-heading">
                <span class="text-uppercase text-muted small fw-bold">Panel klienta</span>
                <h1 class="h3 mb-0">Moje rezerwacje</h1>
            </section>

            <section class="reservations-content">
                <div class="reservations-toolbar">
                    <nav class="nav nav-tabs reservations-tabs" aria-label="Rodzaj rezerwacji">
                        <a
                            href="{{ route('rezerwacje.mine') }}"
                            @class(['nav-link', 'active' => $section === 'upcoming'])
                            @if ($section === 'upcoming') aria-current="page" @endif
                        >
                            <i class="bi bi-calendar-event me-1"></i>
                            Nadchodzące
                        </a>
                        <a
                            href="{{ route('rezerwacje.mine', ['view' => 'history']) }}"
                            @class(['nav-link', 'active' => $section === 'history'])
                            @if ($section === 'history') aria-current="page" @endif
                        >
                            <i class="bi bi-clock-history me-1"></i>
                            Historia
                        </a>
                    </nav>

                    <a href="{{ route('index') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-search me-1"></i>
                        Znajdź strzelnicę
                    </a>
                </div>

                @if ($section === 'upcoming')
            @if ($upcomingReservations->isEmpty())
                <div class="alert alert-info">
                    Nie masz żadnych nadchodzących rezerwacji.
                </div>
            @else
                <div class="table-responsive bg-white rounded reservations-table-panel">
                    <table class="table table-hover align-middle mb-0 reservations-table">
                        <thead>
                            <tr>
                                <th>Strzelnica</th>
                                <th>Termin</th>
                                <th>Oś</th>
                                <th>Stanowiska</th>
                                <th>Status</th>
                                <th class="text-end">Akcja</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($upcomingReservations as $reservation)
                                @php
                                    $status = match ($reservation->status) {
                                        \App\Models\Reservation::STATUS_PENDING => ['Oczekująca', 'warning'],
                                        \App\Models\Reservation::STATUS_APPROVED => ['Zatwierdzona', 'success'],
                                        \App\Models\Reservation::STATUS_CANCELLED => ['Anulowana', 'danger'],
                                        \App\Models\Reservation::STATUS_COMPLETED => ['Zrealizowana', 'primary'],
                                        \App\Models\Reservation::STATUS_NO_SHOW => ['Niezrealizowana', 'secondary'],
                                        default => [$reservation->status, 'secondary'],
                                    };
                                    $canEdit = in_array($reservation->status, [
                                            \App\Models\Reservation::STATUS_PENDING,
                                            \App\Models\Reservation::STATUS_APPROVED,
                                        ], true)
                                        && $reservation->canBeEdited();
                                @endphp
                                <tr>
                                    <td data-label="Strzelnica">
                                        <strong>{{ $reservation->shootingRange?->name ?? 'Usunięta strzelnica' }}</strong>
                                        @if ($reservation->shootingRange?->town)
                                            <small class="d-block text-muted">{{ $reservation->shootingRange->town }}</small>
                                        @endif
                                    </td>
                                    <td data-label="Termin">
                                        {{ $reservation->start->format('d.m.Y') }}
                                        <small class="d-block text-muted">
                                            {{ $reservation->start->format('H:i') }}–{{ $reservation->end->format('H:i') }}
                                        </small>
                                    </td>
                                    <td data-label="Oś">{{ $reservation->lane?->name ?? 'Usunięta oś' }}</td>
                                    <td data-label="Stanowiska">
                                        {{ $reservation->positions_count }}
                                        @if ($reservation->is_dynamic_shooting)
                                            <small class="d-block text-muted">dynamiczne</small>
                                        @endif
                                    </td>
                                    <td data-label="Status">
                                        <span class="badge text-bg-{{ $status[1] }}">{{ $status[0] }}</span>
                                    </td>
                                    <td class="text-end" data-label="Akcja">
                                        @if ($canEdit)
                                            <a
                                                href="{{ route('rezerwacje.edit', $reservation) }}"
                                                class="btn btn-sm btn-outline-primary"
                                            >
                                                <i class="bi bi-pencil me-1"></i>
                                                Edytuj
                                            </a>
                                        @else
                                            <span class="text-muted small">Brak dostępnych akcji</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @elseif ($historyReservations->isEmpty())
            <div class="alert alert-info">
                Historia rezerwacji jest pusta.
            </div>
        @else
            <div class="table-responsive bg-white rounded reservations-table-panel">
                <table class="table table-hover align-middle mb-0 reservations-table">
                    <thead>
                        <tr>
                            <th>Strzelnica</th>
                            <th>Termin</th>
                            <th>Oś</th>
                            <th>Stanowiska</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($historyReservations as $reservation)
                            @php
                                $start = \Illuminate\Support\Carbon::parse($reservation->start);
                                $end = \Illuminate\Support\Carbon::parse($reservation->end);
                                $status = match ($reservation->status) {
                                    \App\Models\Reservation::STATUS_PENDING => ['Oczekująca', 'warning'],
                                    \App\Models\Reservation::STATUS_APPROVED => ['Zatwierdzona', 'success'],
                                    \App\Models\Reservation::STATUS_CANCELLED => ['Anulowana', 'danger'],
                                    \App\Models\Reservation::STATUS_COMPLETED => ['Zrealizowana', 'primary'],
                                    \App\Models\Reservation::STATUS_NO_SHOW => ['Niezrealizowana', 'secondary'],
                                    default => [$reservation->status, 'secondary'],
                                };
                            @endphp
                            <tr>
                                <td data-label="Strzelnica">
                                    <strong>{{ $reservation->shooting_range_name ?? 'Usunięta strzelnica' }}</strong>
                                    @if ($reservation->shooting_range_town)
                                        <small class="d-block text-muted">{{ $reservation->shooting_range_town }}</small>
                                    @endif
                                </td>
                                <td data-label="Termin">
                                    {{ $start->format('d.m.Y') }}
                                    <small class="d-block text-muted">
                                        {{ $start->format('H:i') }}–{{ $end->format('H:i') }}
                                    </small>
                                </td>
                                <td data-label="Oś">{{ $reservation->lane_name ?? 'Usunięta oś' }}</td>
                                <td data-label="Stanowiska">
                                    {{ $reservation->positions_count }}
                                    @if ($reservation->is_dynamic_shooting)
                                        <small class="d-block text-muted">dynamiczne</small>
                                    @endif
                                </td>
                                <td data-label="Status">
                                    <span class="badge text-bg-{{ $status[1] }}">{{ $status[0] }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $historyReservations->links() }}
            </div>
                @endif
            </section>
        </div>
    </main>

    <style>
        .reservations-page {
            --reservations-border: #dfe5e2;
            --reservations-green: #198754;
        }

        .reservations-heading,
        .reservations-content {
            background: rgba(255, 255, 255, .92);
            border: 1px solid var(--reservations-border);
            box-shadow: 0 3px 12px rgba(20, 35, 28, .08);
        }

        .reservations-heading {
            padding: 1rem 1.15rem;
            margin-bottom: .75rem;
        }

        .reservations-heading h1 {
            color: #202a25;
        }

        .reservations-content {
            padding: 1rem;
        }

        .reservations-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .reservations-tabs {
            flex-wrap: nowrap;
            border-bottom: 0;
        }

        .reservations-tabs .nav-link {
            min-width: 145px;
            text-align: center;
            letter-spacing: 0;
            border: 1px solid transparent;
            transition:
                color .18s ease,
                background-color .18s ease,
                border-color .18s ease;
        }

        .reservations-tabs .nav-link.active {
            color: #fff;
            font-weight: 700;
            background: var(--reservations-green);
            border-color: var(--reservations-green);
        }

        .reservations-tabs .nav-link:not(.active) {
            color: #435149;
            background: #f1f4f2;
            border-color: #d3dcd7;
        }

        .reservations-tabs .nav-link:not(.active):hover,
        .reservations-tabs .nav-link:not(.active):focus-visible {
            color: #17221d;
            background: #fff;
            border-color: #8fa398;
            box-shadow: 0 2px 5px rgba(20, 35, 28, .12);
        }

        .reservations-table-panel {
            border: 1px solid rgba(52, 58, 64, .12);
            box-shadow: 0 .75rem 1.5rem rgba(15, 23, 42, .08);
        }

        .reservations-table {
            border-collapse: separate;
            border-spacing: 0;
            overflow: hidden;
            border: 1px solid rgba(52, 58, 64, .18);
            border-radius: .75rem;
        }

        .reservations-table thead th {
            background: #212529;
            color: #fff;
            font-size: .78rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .reservations-table tbody tr:nth-child(even) td {
            background: #f8fafc;
        }

        .reservations-table tbody tr:hover td {
            background: #eef5ff;
        }

        @media (max-width: 767.98px) {
            .reservations-toolbar {
                align-items: stretch;
                flex-direction: column;
            }

            .reservations-tabs {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .reservations-tabs .nav-link {
                min-width: 0;
            }

            .reservations-table {
                border: 0;
                border-collapse: separate;
                border-spacing: 0 .75rem;
                overflow: visible;
            }

            .reservations-table thead {
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

            .reservations-table,
            .reservations-table tbody,
            .reservations-table tr,
            .reservations-table td {
                display: block;
                width: 100%;
            }

            .reservations-table tr {
                border: 1px solid rgba(0, 0, 0, .08);
                border-radius: .75rem;
                background: #fff;
                box-shadow: 0 .35rem 1rem rgba(15, 23, 42, .08);
                overflow: hidden;
            }

            .reservations-table td {
                display: grid;
                grid-template-columns: minmax(6.5rem, 38%) minmax(0, 1fr);
                gap: .75rem;
                align-items: start;
                padding: .65rem .85rem;
                border-bottom: 1px solid rgba(0, 0, 0, .06);
            }

            .reservations-table td::before {
                content: attr(data-label);
                color: #6c757d;
                font-size: .78rem;
                font-weight: 700;
                text-transform: uppercase;
            }
        }
    </style>
</x-app-layout>
