<x-app-layout>
    <main class="container py-4">
        <x-session-alerts />

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <span class="text-uppercase text-muted small fw-bold">Panel klienta</span>
                <h1 class="h3 mb-0">Moje rezerwacje</h1>
            </div>
            <a href="{{ route('index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-search me-1"></i>
                Znajdź strzelnicę
            </a>
        </div>

        @if ($reservations->isEmpty())
            <div class="alert alert-info">
                Nie masz jeszcze żadnych rezerwacji.
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
                        @foreach ($reservations as $reservation)
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
    </main>
</x-app-layout>
