<x-app-layout>
    <div class="page-bg-2"></div>

    <main class="container py-4">
        <div class="col-xl-10 mx-auto reservation-edit-page">
            <x-session-alerts />

            <section class="reservation-edit-heading">
                <div>
                    <span class="text-uppercase text-muted small fw-bold">Panel klienta</span>
                    <h1 class="h3 mb-1">Edytuj rezerwację</h1>
                    <p class="text-muted mb-0">{{ $shootingRange->name }}</p>
                </div>

                <a href="{{ route('rezerwacje.mine') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>
                    Moje rezerwacje
                </a>
            </section>

            <form
                method="POST"
                action="{{ route('rezerwacje.update', $reservation) }}"
                class="reservation-edit-form"
            >
                @csrf
                @method('PATCH')
                <input type="hidden" name="shooting_range_id" value="{{ $shootingRange->id }}">
                <input type="hidden" name="is_dynamic_shooting" value="0">

                @if ($paidReservation)
                    <div class="alert alert-warning">
                        <strong>Opłacona rezerwacja.</strong>
                        Możesz zmienić termin, oś, godzinę rozpoczęcia oraz dane prowadzącego strzelanie.
                        Czas trwania i liczba stanowisk pozostają bez zmian, a zmiana wymaga zatwierdzenia przez personel strzelnicy.
                    </div>
                @endif

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="reservationDate" class="form-label">Data</label>
                        <input
                            type="date"
                            name="date"
                            id="reservationDate"
                            class="form-control"
                            value="{{ old('date', $reservation->start->format('Y-m-d')) }}"
                            min="{{ today()->format('Y-m-d') }}"
                            max="{{ $maxBookingDate->format('Y-m-d') }}"
                            required
                        >
                        <x-input-error :message="$errors->first('date')" />
                    </div>

                    <div class="col-md-6">
                        <label for="reservationLane" class="form-label">Oś</label>
                        <select name="line_id" id="reservationLane" class="form-select" required>
                            @foreach ($shootingRange->lanes as $lane)
                                <option
                                    value="{{ $lane->id }}"
                                    data-positions="{{ $lane->numberOfPositions }}"
                                    data-dynamic="{{ $lane->allows_dynamic_shooting ? '1' : '0' }}"
                                    @selected((int) old('line_id', $reservation->line_id) === (int) $lane->id)
                                >
                                    {{ $lane->name }} · {{ $lane->length }} m
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :message="$errors->first('line_id')" />
                    </div>

                    <div class="col-md-4">
                        <label for="reservationStart" class="form-label">Od</label>
                        <select
                            name="start_time"
                            id="reservationStart"
                            class="form-select"
                            data-initial="{{ old('start_time', $reservation->start->format('H:i')) }}"
                            required
                        ></select>
                        <x-input-error :message="$errors->first('start_time')" />
                    </div>

                    <div class="col-md-4">
                        <label for="reservationEnd" class="form-label">Do</label>
                        <select
                            name="end_time"
                            id="reservationEnd"
                            class="form-select"
                            data-initial="{{ old('end_time', $reservation->end->format('H:i')) }}"
                            @disabled($paidReservation)
                            required
                        ></select>
                        <x-input-error :message="$errors->first('end_time')" />
                    </div>

                    <div class="col-md-4">
                        <label for="reservationPositions" class="form-label">Stanowiska</label>
                        <select
                            name="positions_count"
                            id="reservationPositions"
                            class="form-select"
                            data-initial="{{ old('positions_count', $reservation->positions_count) }}"
                            @disabled($paidReservation)
                            required
                        ></select>
                        <x-input-error :message="$errors->first('positions_count')" />
                    </div>

                    <div class="col-12 d-none" id="dynamicField">
                        <div class="form-check">
                            <input
                                type="checkbox"
                                name="is_dynamic_shooting"
                                id="dynamicShooting"
                                class="form-check-input"
                                value="1"
                                @checked((bool) old('is_dynamic_shooting', $reservation->is_dynamic_shooting))
                                @disabled($paidReservation)
                            >
                            <label for="dynamicShooting" class="form-check-label">
                                Strzelanie dynamiczne - rezerwacja całej osi
                            </label>
                        </div>
                    </div>

                    <div class="col-12">
                        @if ($shootingRange->requires_shooting_leader)
                            <div class="mb-2 small text-danger fw-semibold">
                                <i class="bi bi-exclamation-triangle-fill me-1" aria-hidden="true"></i>
                                Ta strzelnica wymaga prowadzącego strzelanie.
                            </div>
                        @endif

                        <input type="hidden" name="has_shooting_leader_license" value="0">
                        <div class="form-check">
                            <input
                                type="checkbox"
                                name="has_shooting_leader_license"
                                id="hasShootingLeaderLicense"
                                class="form-check-input"
                                value="1"
                                @checked((bool) old(
                                    'has_shooting_leader_license',
                                    $reservation->has_shooting_leader_license
                                ))
                            >
                            <label for="hasShootingLeaderLicense" class="form-check-label">
                                Posiadam uprawnienia prowadzącego strzelanie
                            </label>
                        </div>
                        <x-input-error :message="$errors->first('has_shooting_leader_license')" />
                    </div>

                    <div class="col-12" id="shootingLeaderLicenseNumberField">
                        <label for="shootingLeaderLicenseNumber" class="form-label">
                            Numer uprawnień / legitymacji
                        </label>
                        <input
                            type="text"
                            name="shooting_leader_license_number"
                            id="shootingLeaderLicenseNumber"
                            class="form-control"
                            maxlength="100"
                            value="{{ old(
                                'shooting_leader_license_number',
                                $reservation->shooting_leader_license_number
                            ) }}"
                            @required($shootingRange->requires_shooting_leader)
                        >
                        <x-input-error :message="$errors->first('shooting_leader_license_number')" />
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <a href="{{ route('rezerwacje.mine') }}" class="btn btn-light">Anuluj</a>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-lg me-1"></i>
                        Zapisz zmiany
                    </button>
                </div>
            </form>
        </div>
    </main>

    <style>
        .reservation-edit-page {
            --reservation-edit-border: #dfe5e2;
            max-width: 900px;
        }

        .reservation-edit-heading,
        .reservation-edit-form {
            background: rgba(255, 255, 255, .94);
            border: 1px solid var(--reservation-edit-border);
            box-shadow: 0 3px 12px rgba(20, 35, 28, .08);
        }

        .reservation-edit-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem 1.15rem;
            margin-bottom: .75rem;
        }

        .reservation-edit-heading h1 {
            color: #202a25;
        }

        .reservation-edit-form {
            padding: 1.25rem;
        }

        .reservation-edit-form .form-label {
            color: #27342e;
            font-weight: 600;
        }

        .reservation-edit-form .form-control,
        .reservation-edit-form .form-select {
            background-color: #fff;
            border-color: #cbd5d0;
        }

        .reservation-edit-form .form-control:focus,
        .reservation-edit-form .form-select:focus {
            border-color: #198754;
            box-shadow: 0 0 0 .2rem rgba(25, 135, 84, .15);
        }

        @media (max-width: 575.98px) {
            .reservation-edit-heading {
                align-items: stretch;
                flex-direction: column;
            }

            .reservation-edit-heading .btn {
                width: 100%;
            }

            .reservation-edit-form {
                padding: 1rem;
            }
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const openingHours = @json($openingHours);
            const date = document.getElementById('reservationDate');
            const lane = document.getElementById('reservationLane');
            const start = document.getElementById('reservationStart');
            const end = document.getElementById('reservationEnd');
            const positions = document.getElementById('reservationPositions');
            const dynamic = document.getElementById('dynamicShooting');
            const dynamicField = document.getElementById('dynamicField');
            const leaderLicense = document.getElementById('hasShootingLeaderLicense');
            const leaderLicenseNumber = document.getElementById('shootingLeaderLicenseNumber');
            const leaderLicenseNumberField = document.getElementById('shootingLeaderLicenseNumberField');
            const requiresShootingLeader = @json((bool) $shootingRange->requires_shooting_leader);

            const minutes = value => {
                const [hour, minute] = value.split(':').map(Number);
                return (hour * 60) + minute;
            };
            const time = value => (
                `${String(Math.floor(value / 60)).padStart(2, '0')}:`
                + String(value % 60).padStart(2, '0')
            );
            const addOption = (select, value) => {
                const option = document.createElement('option');
                option.value = value;
                option.textContent = value;
                select.appendChild(option);
            };

            function updateEnd() {
                const selectedDate = new Date(`${date.value}T00:00:00`);
                const day = selectedDate.getDay() === 0 ? 7 : selectedDate.getDay();
                const hours = openingHours[day];
                const initial = end.dataset.initial;
                end.innerHTML = '';

                if (! hours || ! start.value) {
                    return;
                }

                for (
                    let value = minutes(start.value) + 30;
                    value <= minutes(hours.closes_at);
                    value += 30
                ) {
                    addOption(end, time(value));
                }

                if ([...end.options].some(option => option.value === initial)) {
                    end.value = initial;
                }
                end.dataset.initial = '';
            }

            function updateTimes() {
                const selectedDate = new Date(`${date.value}T00:00:00`);
                const day = selectedDate.getDay() === 0 ? 7 : selectedDate.getDay();
                const hours = openingHours[day];
                const initial = start.dataset.initial;
                start.innerHTML = '';
                end.innerHTML = '';

                if (! hours) {
                    return;
                }

                for (
                    let value = minutes(hours.opens_at);
                    value < minutes(hours.closes_at);
                    value += 30
                ) {
                    addOption(start, time(value));
                }

                if ([...start.options].some(option => option.value === initial)) {
                    start.value = initial;
                }
                start.dataset.initial = '';
                updateEnd();
            }

            function updatePositions() {
                const option = lane.selectedOptions[0];
                const capacity = Number(option?.dataset.positions ?? 0);
                const allowsDynamic = option?.dataset.dynamic === '1';
                const initial = Number(positions.dataset.initial);
                dynamicField.classList.toggle('d-none', ! allowsDynamic);

                if (! allowsDynamic) {
                    dynamic.checked = false;
                }

                positions.innerHTML = '';

                if (dynamic.checked) {
                    const item = document.createElement('option');
                    item.value = capacity;
                    item.textContent = `${capacity} - cała oś`;
                    positions.appendChild(item);
                    return;
                }

                for (let value = 1; value <= capacity; value++) {
                    const item = document.createElement('option');
                    item.value = value;
                    item.textContent = value;
                    positions.appendChild(item);
                }

                if ([...positions.options].some(option => Number(option.value) === initial)) {
                    positions.value = initial;
                }
                positions.dataset.initial = '';
            }

            function updateShootingLeaderFields() {
                const required = requiresShootingLeader || leaderLicense.checked;
                leaderLicense.required = requiresShootingLeader;
                leaderLicenseNumber.required = required;
                leaderLicenseNumberField.classList.toggle('d-none', !required);

                if (!required) {
                    leaderLicenseNumber.value = '';
                }
            }

            date.addEventListener('change', updateTimes);
            start.addEventListener('change', updateEnd);
            lane.addEventListener('change', updatePositions);
            dynamic.addEventListener('change', updatePositions);
            leaderLicense.addEventListener('change', updateShootingLeaderFields);
            updateTimes();
            updatePositions();
            updateShootingLeaderFields();
        });
    </script>
</x-app-layout>
