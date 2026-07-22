<x-app-layout>

    <div class="page-bg-2"></div>
    @if (session('error'))
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                showToast(@json(session('error')), 'error');
            });
        </script>
    @endif
    @if (session('success'))
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                showToast(@json(session('success')), 'success');
            });
        </script>
    @endif

    @php
        $oldInput = old();

    @endphp

    @include('components.visiting-card')

    <div class="container py-4">
        <header class="p-4 mb-3 text-white rounded bg-secondary">
            <div class="gap-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                <div>
                    <div class="gap-2 mb-2 d-flex align-items-center">
                        <i class="bi bi-calendar-week fs-4"></i>
                        <span class="opacity-75 text-uppercase small">Panel strzelnicy</span>
                    </div>
                    <h1 class="mb-1 h3">Godziny pracy</h1>
                    <p class="mb-0 opacity-75">{{ $shootingRange->name }}</p>
                </div>
            </div>
        </header>

        <x-session-alerts />

        <h4 class="mb-3">Godziny pracy strzelnicy</h4>

        <div class="p-3 mb-4 bg-white rounded opening-panel">
            <form method="POST" action="{{ route('shooting.time.update', ['shootingRange' => $shootingRange]) }}">
                @csrf
                <div class="table-responsive">
                <table class="table align-middle table-hover opening-table">
                    <thead>
                        <tr>
                            <th></th>
                            <th>Dzień</th>
                            <th>Od</th>
                            <th>Do</th>
                            <th>Nieczynne</th>
                        </tr>
                    </thead>
                    <tbody id="work-hours">
                        @php
                            $days = ['Poniedziałek', 'Wtorek', 'Środa', 'Czwartek', 'Piątek', 'Sobota', 'Niedziela'];
                        @endphp

                        @foreach ($days as $i => $day)
                            @php
                                $hours = $openingHours->keyBy('day_of_week');
                            @endphp
                            <tr>
                                <td class="text-end" style="width:40px;">
                                    @if ($i === 0)
                                        <button type="button"
                                            class="btn btn-sm btn-outline-danger rounded-circle d-flex align-items-center justify-content-center"
                                            id="copyAll" style="width:32px;height:32px;"
                                            title="Kopiuj godziny do wszystkich dni">

                                            <i class="bi bi-copy"></i>
                                        </button>
                                    @endif
                                </td>
                                <td class="fw-semibold">
                                    {{ $day }}
                                </td>

                                <td>
                                    <input type="time" name="days[{{ $i }}][opens_at]"
                                        class="form-control from"
                                        value="{{ isset($hours[$i + 1]) ? \Carbon\Carbon::parse($hours[$i + 1]->opens_at)->format('H:i') : '' }}">
                                </td>

                                <td>
                                    <input type="time" name="days[{{ $i }}][closes_at]"
                                        class="form-control to"
                                        value="{{ isset($hours[$i + 1]) ? \Carbon\Carbon::parse($hours[$i + 1]->closes_at)->format('H:i') : '' }}">
                                </td>

                                <td>
                                    <input type="checkbox" name="days[{{ $i }}][closed]"
                                        class="form-check-input closed">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>

                <div class="mt-4 text-end">
                    <button type="submit" class="px-4 btn btn-success">
                        <i class="bi bi-save me-1"></i>
                        Zapisz
                    </button>
                </div>
            </form>
        </div>

        <h4 class="mb-3">Dni wolne (wyjątki)</h4>

        <div class="p-3 bg-white rounded opening-panel">
            <form method="POST" action="{{ route('shooting-ranges.holidays.store', $shootingRange) }}">
                @csrf
                <div class="mb-3 row g-2">
                    <div class="col-md-4">
                        <input type="date" name="date" id="holidayDates" class="form-control"
                            placeholder="yyyy.mm.dd" max="3000-12-31" min="2000-01-01">
                        @error('holidayDate')
                            <div class="text-danger">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>
                    <div class="col-md-6">
                        <input type="text" name="name" id="holidayName" class="form-control"
                            placeholder="Opis (np. Święto)">
                        @error('holidayName')
                            <div class="text-danger">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-success w-100" id="addHoliday">Dodaj</button>
                    </div>
                </div>
            </form>




            @php
                $selectedYear = request('year', now()->year);
            @endphp

            <form method="GET" class="mb-3" id="holiday-year-form">
                <label for="year" class="form-label">Rok</label>

                <div class="input-group" style="max-width: 220px;">
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
            </form>

            <div class="table-responsive">
            <table class="table table-hover align-middle opening-table">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Nazwa</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="holidayList">
                    @foreach ($holidays as $holiday)
                        <tr>
                            <td>
                                {{ $holiday->date->format('Y-m-d') }}
                            </td>
                            <td>
                                {{ $holiday->name }}
                            </td>
                            <td>
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
                    @endforeach
                </tbody>
            </table>
            </div>
        </div>
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

        .opening-table thead th {
            background: #212529;
            color: #fff;
            font-size: .78rem;
            text-transform: uppercase;
        }
    </style>


    <script>
        document.addEventListener('DOMContentLoaded', () => {

            // 🔁 Kopiuj pierwszy dzień do wszystkich
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
                });
            });


            // 🚫 Nieczynne → blokuje godziny
            document.querySelectorAll('.closed').forEach(chk => {
                chk.addEventListener('change', function() {
                    const row = this.closest('tr');
                    row.querySelector('.from').disabled = this.checked;
                    row.querySelector('.to').disabled = this.checked;
                });
            });



            // 📅 Dni wolne
            document.getElementById('addHoliday22')?.addEventListener('click', () => {
                const date = document.getElementById('holidayDate').value;
                const name = document.getElementById('holidayName').value;

                if (!date) {
                    alert('Wybierz datę');
                    return;
                }

                const row = document.createElement('tr');

                row.innerHTML = `
                    <td>
                        ${date}
                        <input type="hidden" name="holidays[][date]" value="${date}">
                    </td>
                    <td>
                        ${name}
                        <input type="hidden" name="holidays[][name]" value="${name}">
                    </td>
                    <td>
                        <button class="btn btn-sm btn-danger remove">Usuń</button>
                    </td>
                `;

                document.getElementById('holidayList').appendChild(row);

                // reset
                document.getElementById('holidayDate').value = '';
                document.getElementById('holidayName').value = '';
            });

            // ❌ Usuwanie dnia wolnego
            document.getElementById('holidayList').addEventListener('click', e => {
                if (e.target.classList.contains('remove')) {
                    e.target.closest('tr').remove();
                }
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
