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
                <div class="table-responsive bg-white border rounded">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
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
                                    $canEdit = $reservation->status === \App\Models\Reservation::STATUS_PENDING
                                        && $reservation->canBeEdited();
                                @endphp
                                <tr>
                                    <td>
                                        <strong>{{ $reservation->shootingRange?->name ?? 'Usunięta strzelnica' }}</strong>
                                        @if ($reservation->shootingRange?->town)
                                            <small class="d-block text-muted">{{ $reservation->shootingRange->town }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        {{ $reservation->start->format('d.m.Y') }}
                                        <small class="d-block text-muted">
                                            {{ $reservation->start->format('H:i') }}–{{ $reservation->end->format('H:i') }}
                                        </small>
                                    </td>
                                    <td>{{ $reservation->lane?->name ?? 'Usunięta oś' }}</td>
                                    <td>
                                        {{ $reservation->positions_count }}
                                        @if ($reservation->is_dynamic_shooting)
                                            <small class="d-block text-muted">dynamiczne</small>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge text-bg-{{ $status[1] }}">{{ $status[0] }}</span>
                                    </td>
                                    <td class="text-end">
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
            <div class="table-responsive bg-white border rounded">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
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
                                <td>
                                    <strong>{{ $reservation->shooting_range_name ?? 'Usunięta strzelnica' }}</strong>
                                    @if ($reservation->shooting_range_town)
                                        <small class="d-block text-muted">{{ $reservation->shooting_range_town }}</small>
                                    @endif
                                </td>
                                <td>
                                    {{ $start->format('d.m.Y') }}
                                    <small class="d-block text-muted">
                                        {{ $start->format('H:i') }}–{{ $end->format('H:i') }}
                                    </small>
                                </td>
                                <td>{{ $reservation->lane_name ?? 'Usunięta oś' }}</td>
                                <td>
                                    {{ $reservation->positions_count }}
                                    @if ($reservation->is_dynamic_shooting)
                                        <small class="d-block text-muted">dynamiczne</small>
                                    @endif
                                </td>
                                <td>
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
        }
    </style>
</x-app-layout>
