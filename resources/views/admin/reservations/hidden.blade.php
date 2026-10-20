<x-app-layout>
    <div class="page-bg-2"></div>

    <main class="container py-4">
        <x-session-alerts />

        <header class="p-4 mb-4 text-white rounded bg-secondary">
            <div class="gap-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                <div>
                    <div class="gap-2 mb-2 d-flex align-items-center">
                        <i class="bi bi-eye-slash-fill fs-4"></i>
                        <span class="opacity-75 text-uppercase small">Panel administratora</span>
                    </div>
                    <h1 class="h3 mb-1">Ukryte rezerwacje</h1>
                    <p class="mb-0 opacity-75">Wpisy ukryte w kalendarzu. Historia i płatności pozostają zachowane.</p>
                </div>

                <a
                    href="{{ route('admin.reservations.calendar', array_filter([
                        'shooting_range_id' => $filters['shooting_range_id'] ?? null,
                    ])) }}"
                    class="btn btn-light"
                >
                    <i class="bi bi-calendar3 me-1"></i>
                    Kalendarz rezerwacji
                </a>
            </div>
        </header>

        <form method="GET" action="{{ route('admin.reservations.hidden') }}" class="p-3 mb-4 bg-white border rounded shadow-sm">
            <div class="row g-3 align-items-end">
                <div class="col-lg-4 col-md-6">
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

                <div class="col-lg-5 col-md-6">
                    <label for="q" class="form-label">Klient lub numer rezerwacji</label>
                    <input
                        type="search"
                        id="q"
                        name="q"
                        value="{{ $filters['q'] ?? '' }}"
                        class="form-control"
                        placeholder="Imię, e-mail, telefon lub ID"
                    >
                </div>

                <div class="gap-2 col-lg-3 d-flex">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-funnel me-1"></i>
                        Filtruj
                    </button>
                    <a href="{{ route('admin.reservations.hidden') }}" class="btn btn-outline-secondary">
                        Wyczyść
                    </a>
                </div>
            </div>
        </form>

        @if ($reservations->isEmpty())
            <div class="alert alert-info">Brak ukrytych rezerwacji spełniających wybrane kryteria.</div>
        @else
            <div class="overflow-hidden bg-white border rounded shadow-sm">
                <div class="table-responsive">
                    <table class="table mb-0 table-hover align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Klient</th>
                                <th>Strzelnica</th>
                                <th>Termin</th>
                                <th>Status płatności</th>
                                <th>Ukrycie</th>
                                <th class="text-end">Akcja</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($reservations as $reservation)
                                @php
                                    $paymentLabel = match ($reservation->payment_status) {
                                        'paid' => 'Opłacona',
                                        'pending' => 'Oczekuje',
                                        'refunded' => 'Zwrócona',
                                        'expired' => 'Wygasła',
                                        'failed' => 'Nieudana',
                                        default => 'Nie wymaga płatności',
                                    };
                                @endphp
                                <tr>
                                    <td class="text-nowrap">#{{ $reservation->id }}</td>
                                    <td>
                                        <strong>{{ $reservation->customer_name ?: 'Brak danych' }}</strong>
                                        <small class="d-block text-muted">{{ $reservation->customer_email ?: $reservation->customer_phone ?: 'Brak kontaktu' }}</small>
                                    </td>
                                    <td>
                                        <strong>{{ $reservation->shootingRange?->name ?: 'Usunięta strzelnica' }}</strong>
                                        <small class="d-block text-muted">{{ $reservation->lane?->name ?: 'Usunięta oś' }}</small>
                                    </td>
                                    <td class="text-nowrap">
                                        {{ $reservation->start->format('d.m.Y') }}
                                        <small class="d-block text-muted">{{ $reservation->start->format('H:i') }}–{{ $reservation->end->format('H:i') }}</small>
                                    </td>
                                    <td>
                                        <strong>{{ $paymentLabel }}</strong>
                                        @if ($reservation->payment_amount)
                                            <small class="d-block text-muted">
                                                {{ number_format((float) $reservation->payment_amount, 2, ',', ' ') }} {{ $reservation->payment_currency }}
                                            </small>
                                        @endif
                                    </td>
                                    <td>
                                        <strong>{{ $reservation->deleted_at?->format('d.m.Y H:i') }}</strong>
                                        <small class="d-block text-muted">{{ $reservation->deleter?->name ?: 'Brak danych' }}</small>
                                        <small class="d-block mt-1">{{ $reservation->deletion_reason }}</small>
                                    </td>
                                    <td class="text-end">
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-success restore-reservation-button"
                                            data-bs-toggle="modal"
                                            data-bs-target="#restoreReservationModal"
                                            data-reservation-id="{{ $reservation->id }}"
                                            data-reservation-label="#{{ $reservation->id }} · {{ $reservation->customer_name ?: 'Brak danych' }} · {{ $reservation->start->format('d.m.Y H:i') }}"
                                            title="Przywróć rezerwację"
                                        >
                                            <i class="bi bi-arrow-counterclockwise"></i>
                                            <span class="visually-hidden">Przywróć rezerwację</span>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-3">{{ $reservations->links() }}</div>
        @endif
    </main>

    <div class="modal fade" id="restoreReservationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="" class="modal-content" id="restoreReservationForm">
                @csrf
                @method('PATCH')
                <div class="modal-header">
                    <h2 class="modal-title fs-5">Przywróć rezerwację</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Czy przywrócić wpis <strong id="restoreReservationLabel"></strong>?</p>
                    <div class="mb-0 alert alert-warning">
                        Przywrócenie nie aktywuje rezerwacji. Wpis wróci jako anulowany, dzięki czemu nie zajmie terminu bez ponownej kontroli dostępności.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Anuluj</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>
                        Przywróć
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const routeTemplate = @json(route('admin.reservations.hidden.restore', ['reservation' => '__ID__']));
            const form = document.getElementById('restoreReservationForm');
            const label = document.getElementById('restoreReservationLabel');

            document.querySelectorAll('.restore-reservation-button').forEach(button => {
                button.addEventListener('click', () => {
                    form.action = routeTemplate.replace('__ID__', button.dataset.reservationId);
                    label.textContent = button.dataset.reservationLabel;
                });
            });
        });
    </script>
</x-app-layout>
