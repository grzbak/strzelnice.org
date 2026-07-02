<x-app-layout>
    <div class="page-bg-2"></div>

    @include('components.breadcrumb')

    <main class="container py-4">
        <x-session-alerts />

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 p-3 mb-4 bg-white border shadow-sm">
            <div>
                <span class="text-uppercase text-danger small fw-bold">Panel strzelnicy</span>
                <h1 class="h3 mb-1 text-dark">Archiwum rezerwacji</h1>
                <p class="mb-0 text-secondary">Zakończone rezerwacje przeniesione z kalendarza operacyjnego.</p>
            </div>

            <a
                href="{{ route('admin.reservations.calendar', array_filter([
                    'shooting_range_id' => $filters['shooting_range_id'] ?? null,
                ])) }}"
                class="btn btn-outline-secondary"
            >
                <i class="bi bi-calendar3 me-1"></i>
                Kalendarz rezerwacji
            </a>
        </div>

        <form
            method="GET"
            action="{{ route('admin.reservations.archive') }}"
            class="p-3 mb-4 bg-white border rounded"
        >
            @if (auth()->user()->isSuperAdmin() && filled($filters['shooting_range_id'] ?? null))
                <input
                    type="hidden"
                    name="shooting_range_id"
                    value="{{ $filters['shooting_range_id'] }}"
                >
            @endif

            <div class="row g-3 align-items-end">
                @if (! auth()->user()->isSuperAdmin())
                    <div class="col-lg-3 col-md-6">
                        <label for="shooting_range_id" class="form-label">Strzelnica</label>
                        <select
                            id="shooting_range_id"
                            name="shooting_range_id"
                            class="form-select"
                        >
                            <option value="">Wszystkie dostępne</option>
                            @foreach ($managedRanges as $range)
                                <option
                                    value="{{ $range->id }}"
                                    @selected((int) ($filters['shooting_range_id'] ?? 0) === $range->id)
                                >
                                    {{ $range->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="col-lg-2 col-md-6">
                    <label for="date_from" class="form-label">Od dnia</label>
                    <input
                        type="date"
                        id="date_from"
                        name="date_from"
                        value="{{ $filters['date_from'] ?? '' }}"
                        class="form-control"
                        min="1900-01-01"
                        max="{{ today()->toDateString() }}"
                        data-date-picker-only
                    >
                </div>

                <div class="col-lg-2 col-md-6">
                    <label for="date_to" class="form-label">Do dnia</label>
                    <input
                        type="date"
                        id="date_to"
                        name="date_to"
                        value="{{ $filters['date_to'] ?? '' }}"
                        class="form-control"
                        min="1900-01-01"
                        max="{{ today()->toDateString() }}"
                        data-date-picker-only
                    >
                </div>

                <div class="col-lg-2 col-md-6">
                    <label for="status" class="form-label">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">Wszystkie</option>
                        <option
                            value="{{ \App\Models\Reservation::STATUS_COMPLETED }}"
                            @selected(($filters['status'] ?? '') === \App\Models\Reservation::STATUS_COMPLETED)
                        >
                            Zrealizowane
                        </option>
                        <option
                            value="{{ \App\Models\Reservation::STATUS_NO_SHOW }}"
                            @selected(($filters['status'] ?? '') === \App\Models\Reservation::STATUS_NO_SHOW)
                        >
                            Niezrealizowane
                        </option>
                        <option
                            value="{{ \App\Models\Reservation::STATUS_CANCELLED }}"
                            @selected(($filters['status'] ?? '') === \App\Models\Reservation::STATUS_CANCELLED)
                        >
                            Anulowane
                        </option>
                    </select>
                </div>

                <div class="col-lg-3 col-md-8">
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

                <div class="col-12 d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-funnel me-1"></i>
                        Filtruj
                    </button>
                    <a
                        href="{{ route('admin.reservations.archive', auth()->user()->isSuperAdmin()
                            ? array_filter(['shooting_range_id' => $filters['shooting_range_id'] ?? null])
                            : []) }}"
                        class="btn btn-outline-secondary"
                    >
                        <i class="bi bi-x-circle me-1"></i>
                        Wyczyść
                    </a>
                </div>
            </div>
        </form>

        @if ($archives->isEmpty())
            <div class="alert alert-info">
                Brak archiwalnych rezerwacji spełniających wybrane kryteria.
            </div>
        @else
            <div class="table-responsive bg-white border rounded">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Klient</th>
                            <th>Strzelnica</th>
                            <th>Termin</th>
                            <th>Oś</th>
                            <th>Status</th>
                            <th class="text-end">Szczegóły</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($archives as $archive)
                            @php
                                $status = match ($archive->status) {
                                    \App\Models\Reservation::STATUS_COMPLETED => ['Zrealizowana', 'primary'],
                                    \App\Models\Reservation::STATUS_NO_SHOW => ['Niezrealizowana', 'secondary'],
                                    \App\Models\Reservation::STATUS_CANCELLED => ['Anulowana', 'danger'],
                                    default => [$archive->status, 'secondary'],
                                };
                                $source = match ($archive->source) {
                                    \App\Models\Reservation::SOURCE_ONLINE_ACCOUNT => 'Rezerwacja klienta',
                                    \App\Models\Reservation::SOURCE_STAFF_ACCOUNT => 'Dodana przez obsługę dla użytkownika',
                                    \App\Models\Reservation::SOURCE_STAFF_GUEST => 'Dodana przez obsługę dla gościa',
                                    default => 'Brak danych',
                                };
                            @endphp
                            <tr>
                                <td class="text-nowrap">#{{ $archive->original_reservation_id }}</td>
                                <td>
                                    <strong>{{ $archive->customer_name ?: 'Brak danych' }}</strong>
                                    @if ($archive->customer_email)
                                        <small class="d-block text-muted">{{ $archive->customer_email }}</small>
                                    @endif
                                </td>
                                <td>
                                    {{ $archive->shooting_range_name ?: 'Usunięta strzelnica' }}
                                    @if ($archive->shooting_range_town)
                                        <small class="d-block text-muted">{{ $archive->shooting_range_town }}</small>
                                    @endif
                                </td>
                                <td class="text-nowrap">
                                    {{ $archive->start->format('d.m.Y') }}
                                    <small class="d-block text-muted">
                                        {{ $archive->start->format('H:i') }}–{{ $archive->end->format('H:i') }}
                                    </small>
                                </td>
                                <td>
                                    {{ $archive->lane_name ?: 'Usunięta oś' }}
                                    @if ($archive->lane_length)
                                        <small class="d-block text-muted">{{ $archive->lane_length }} m</small>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge text-bg-{{ $status[1] }}">{{ $status[0] }}</span>
                                </td>
                                <td class="text-end">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-secondary"
                                        data-bs-toggle="collapse"
                                        data-bs-target="#archive-details-{{ $archive->id }}"
                                        aria-expanded="false"
                                        aria-controls="archive-details-{{ $archive->id }}"
                                        title="Pokaż szczegóły"
                                    >
                                        <i class="bi bi-eye"></i>
                                        <span class="visually-hidden">Pokaż szczegóły</span>
                                    </button>
                                </td>
                            </tr>
                            <tr class="collapse" id="archive-details-{{ $archive->id }}">
                                <td colspan="7" class="bg-light">
                                    <dl class="row mb-0 py-2">
                                        <dt class="col-md-3 col-lg-2">Telefon</dt>
                                        <dd class="col-md-3 col-lg-4">{{ $archive->customer_phone ?: 'Brak danych' }}</dd>

                                        <dt class="col-md-3 col-lg-2">Źródło</dt>
                                        <dd class="col-md-3 col-lg-4">{{ $source }}</dd>

                                        <dt class="col-md-3 col-lg-2">Stanowiska</dt>
                                        <dd class="col-md-3 col-lg-4">
                                            {{ $archive->positions_count }}
                                            @if ($archive->is_dynamic_shooting)
                                                <span class="badge text-bg-warning ms-1">strzelanie dynamiczne</span>
                                            @endif
                                        </dd>

                                        <dt class="col-md-3 col-lg-2">Zarchiwizowano</dt>
                                        <dd class="col-md-3 col-lg-4">
                                            {{ $archive->archived_at?->format('d.m.Y H:i') }}
                                        </dd>

                                        @if ($archive->cancellation_reason)
                                            <dt class="col-md-3 col-lg-2">Powód anulowania</dt>
                                            <dd class="col-md-9 col-lg-10">{{ $archive->cancellation_reason }}</dd>
                                        @endif

                                        @if ($archive->notes)
                                            <dt class="col-md-3 col-lg-2">Notatki</dt>
                                            <dd class="col-md-9 col-lg-10 mb-0">{{ $archive->notes }}</dd>
                                        @endif
                                    </dl>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $archives->links() }}
            </div>
        @endif
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('[data-date-picker-only]').forEach(function (input) {
                input.addEventListener('keydown', function (event) {
                    if (
                        event.key.length === 1
                        || event.key === 'Backspace'
                        || event.key === 'Delete'
                    ) {
                        event.preventDefault();
                    }
                });

                ['paste', 'drop'].forEach(function (eventName) {
                    input.addEventListener(eventName, function (event) {
                        event.preventDefault();
                    });
                });
            });
        });
    </script>
</x-app-layout>
