<x-app-layout>
    <div class="page-bg-2"></div>

    <div class="container py-4 py-lg-5">
        <header class="p-4 mb-4 text-white rounded bg-secondary">
            <div class="gap-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                <div>
                    <div class="gap-2 mb-2 d-flex align-items-center">
                        <i class="bi bi-calendar-week fs-4"></i>
                        <span class="opacity-75 text-uppercase small">Panel strzelnicy</span>
                    </div>
                    <h1 class="mb-1 h3">Godziny pracy i dni wolne</h1>
                    <p class="mb-0 opacity-75">{{ $shootingRange->name }}</p>
                </div>
                <div class="text-md-end small opacity-75">
                    <div>Ustaw tygodniowy harmonogram oraz wyjątki blokujące rezerwacje.</div>
                </div>
            </div>
        </header>

        <x-session-alerts />

        <section class="p-3 p-lg-4 mb-4 bg-white rounded opening-panel">
            <div class="gap-3 mb-3 d-flex flex-column flex-md-row align-items-md-start justify-content-between">
                <div>
                    <div class="gap-2 mb-1 d-flex align-items-center">
                        <i class="bi bi-clock-history text-danger fs-5"></i>
                        <h2 class="mb-0 h5">Godziny pracy strzelnicy</h2>
                    </div>
                    <p class="mb-0 text-muted small">
                        Wpisz godziny otwarcia dla każdego dnia. Zaznaczenie „nieczynne” wyłącza rezerwacje w danym dniu tygodnia.
                    </p>
                </div>
                <div class="px-3 py-2 rounded opening-helper small">
                    <i class="bi bi-copy me-1"></i>
                    Przycisk przy poniedziałku kopiuje jego ustawienia do pozostałych dni.
                </div>
            </div>

            <form method="POST" action="{{ route('shooting.time.update', ['shootingRange' => $shootingRange]) }}">
                @csrf
                <div class="table-responsive">
                    <table class="table align-middle table-hover opening-table">
                        <thead>
                            <tr>
                                <th class="text-center">Akcja</th>
                                <th>Dzień tygodnia</th>
                                <th>Otwarcie</th>
                                <th>Zamknięcie</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody id="work-hours">
                            @php
                                $days = ['Poniedziałek', 'Wtorek', 'Środa', 'Czwartek', 'Piątek', 'Sobota', 'Niedziela'];
                                $hours = $openingHours->keyBy('day_of_week');
                            @endphp

                            @foreach ($days as $i => $day)
                                @php
                                    $dayHours = $hours[$i + 1] ?? null;
                                    $isClosed = ! $dayHours;
                                @endphp
                                <tr>
                                    <td class="text-center" style="width:72px;">
                                        @if ($i === 0)
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-danger opening-icon-button"
                                                id="copyAll"
                                                data-bs-toggle="tooltip"
                                                data-bs-title="Kopiuj godziny z poniedziałku do wszystkich dni"
                                                aria-label="Kopiuj godziny z poniedziałku do wszystkich dni"
                                            >
                                                <i class="bi bi-copy"></i>
                                            </button>
                                        @else
                                            <span class="text-muted small">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="fw-semibold">{{ $day }}</div>
                                        <div class="small text-muted">
                                            {{ $isClosed ? 'Brak rezerwacji w tym dniu' : 'Rezerwacje dostępne w podanych godzinach' }}
                                        </div>
                                    </td>

                                    <td>
                                        <label class="visually-hidden" for="opensAt{{ $i }}">Otwarcie: {{ $day }}</label>
                                        <input
                                            type="time"
                                            name="days[{{ $i }}][opens_at]"
                                            id="opensAt{{ $i }}"
                                            class="form-control from"
                                            value="{{ $dayHours ? \Carbon\Carbon::parse($dayHours->opens_at)->format('H:i') : '' }}"
                                            @disabled($isClosed)
                                        >
                                    </td>

                                    <td>
                                        <label class="visually-hidden" for="closesAt{{ $i }}">Zamknięcie: {{ $day }}</label>
                                        <input
                                            type="time"
                                            name="days[{{ $i }}][closes_at]"
                                            id="closesAt{{ $i }}"
                                            class="form-control to"
                                            value="{{ $dayHours ? \Carbon\Carbon::parse($dayHours->closes_at)->format('H:i') : '' }}"
                                            @disabled($isClosed)
                                        >
                                    </td>

                                    <td class="text-center">
                                        <div class="form-check form-switch d-inline-flex align-items-center gap-2 ps-0">
                                            <input
                                                type="checkbox"
                                                name="days[{{ $i }}][closed]"
                                                id="closed{{ $i }}"
                                                class="form-check-input closed ms-0"
                                                @checked($isClosed)
                                            >
                                            <label class="form-check-label small fw-semibold" for="closed{{ $i }}">
                                                Nieczynne
                                            </label>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 text-end">
                    <button type="submit" class="px-4 btn btn-danger">
                        <i class="bi bi-save me-1"></i>
                        Zapisz godziny pracy
                    </button>
                </div>
            </form>
        </section>

        <section class="p-3 p-lg-4 bg-white rounded opening-panel">
            <div class="gap-3 mb-3 d-flex flex-column flex-md-row align-items-md-start justify-content-between">
                <div>
                    <div class="gap-2 mb-1 d-flex align-items-center">
                        <i class="bi bi-calendar-x text-danger fs-5"></i>
                        <h2 class="mb-0 h5">Dni wolne i wyjątki</h2>
                    </div>
                    <p class="mb-0 text-muted small">
                        Dodaj pojedyncze daty, w których strzelnica ma być niedostępna niezależnie od tygodniowych godzin pracy.
                    </p>
                </div>
            </div>

            <form method="POST" action="{{ route('shooting-ranges.holidays.store', $shootingRange) }}">
                @csrf
                <div class="p-3 mb-4 rounded opening-subpanel">
                    <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold" for="holidayDates">Data wyjątku</label>
                        <input type="date" name="date" id="holidayDates" class="form-control" max="3000-12-31" min="2000-01-01">
                        @error('date')
                            <div class="mt-1 small text-danger">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="holidayName">Nazwa / opis</label>
                        <input type="text" name="name" id="holidayName" class="form-control" placeholder="Np. święto, zawody, przerwa techniczna">
                        @error('name')
                            <div class="mt-1 small text-danger">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-danger w-100" id="addHoliday">
                            <i class="bi bi-plus-lg me-1"></i>
                            Dodaj
                        </button>
                    </div>
                    </div>
                </div>
            </form>

            @php
                $selectedYear = request('year', now()->year);
            @endphp

            <form method="GET" class="gap-2 mb-3 d-flex flex-column flex-sm-row align-items-sm-end justify-content-between" id="holiday-year-form">
                <div>
                    <label for="year" class="form-label fw-semibold">Rok listy wyjątków</label>
                    <input
                        type="number"
                        name="year"
                        id="year"
                        class="form-control"
                        min="2000"
                        max="3000"
                        step="1"
                        value="{{ $selectedYear }}"
                        onchange="window.location.href = '{{ url()->current() }}?year=' + this.value + '#holiday-year-form'"
                    >
                </div>
                <div class="text-muted small">
                    Liczba wpisów: <strong>{{ $holidays->count() }}</strong>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table align-middle table-hover opening-table">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Opis wyjątku</th>
                            <th class="text-end">Akcje</th>
                        </tr>
                    </thead>
                    <tbody id="holidayList">
                        @forelse ($holidays as $holiday)
                            <tr>
                                <td class="fw-semibold">
                                    {{ $holiday->date->format('Y-m-d') }}
                                </td>
                                <td>
                                    {{ $holiday->name }}
                                </td>
                                <td class="text-end">
                                    <form method="POST" action="{{ route('shooting-ranges.holidays.destroy', [$shootingRange, $holiday]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-danger data-table-icon-button holiday-delete-button"
                                            data-bs-toggle="tooltip"
                                            data-bs-title="Usuń dzień wolny"
                                            aria-label="Usuń dzień wolny"
                                            data-message="Czy na pewno chcesz usunąć dzień wolny {{ $holiday->date->format('Y-m-d') }}?"
                                        >
                                            <i class="bi bi-trash3" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="py-4 text-center text-muted">
                                    Brak dni wolnych w wybranym roku.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="modal fade" id="holidayDeleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h5">Usuń dzień wolny</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0" data-holiday-delete-message></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-dark" data-bs-dismiss="modal">Anuluj</button>
                    <button type="button" class="btn btn-danger" data-holiday-delete-confirm>
                        <i class="bi bi-trash3 me-1" aria-hidden="true"></i>
                        Usuń
                    </button>
                </div>
            </div>
        </div>
    </div>

    <style>
        .opening-panel {
            border: 1px solid rgba(52, 58, 64, .12);
            box-shadow: 0 .75rem 1.5rem rgba(15, 23, 42, .08);
        }

        .opening-subpanel,
        .opening-helper {
            background: #fff7ed;
            border: 1px solid #fed7aa;
        }

        .opening-icon-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2rem;
            height: 2rem;
            padding: 0;
            border-radius: 50%;
        }

        .opening-table thead th {
            background: #212529;
            color: #fff;
            font-size: .78rem;
            text-transform: uppercase;
        }

        .opening-table td,
        .opening-table th {
            vertical-align: middle;
        }

        @media (max-width: 767.98px) {
            .opening-table {
                min-width: 720px;
            }
        }
    </style>


    <script>
        document.addEventListener('DOMContentLoaded', () => {

            document.getElementById('copyAll').addEventListener('click', () => {
                const firstRow = document.querySelector('#work-hours tr');

                const from = firstRow.querySelector('.from').value;
                const to = firstRow.querySelector('.to').value;
                const closed = firstRow.querySelector('.closed').checked;

                document.querySelectorAll('#work-hours tr').forEach((row, index) => {
                    if (index === 0) return;

                    row.querySelector('.from').value = from;
                    row.querySelector('.to').value = to;
                    row.querySelector('.closed').checked = closed;
                    row.querySelector('.from').disabled = closed;
                    row.querySelector('.to').disabled = closed;
                });
            });

            document.querySelectorAll('.closed').forEach(chk => {
                const row = chk.closest('tr');
                row.querySelector('.from').disabled = chk.checked;
                row.querySelector('.to').disabled = chk.checked;

                chk.addEventListener('change', function() {
                    const row = this.closest('tr');
                    row.querySelector('.from').disabled = this.checked;
                    row.querySelector('.to').disabled = this.checked;
                });
            });

            document.addEventListener('click', event => {
                const button = event.target.closest('.holiday-delete-button');

                if (!button) {
                    return;
                }

                const modalElement = document.getElementById('holidayDeleteModal');
                const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
                const message = modalElement.querySelector('[data-holiday-delete-message]');
                const confirmButton = modalElement.querySelector('[data-holiday-delete-confirm]');
                const form = button.closest('form');

                message.textContent = button.dataset.message || 'Czy na pewno chcesz usunąć ten dzień wolny?';
                confirmButton.onclick = () => {
                    modal.hide();
                    form.submit();
                };

                modal.show();
            });

        });
    </script>
</x-app-layout>
