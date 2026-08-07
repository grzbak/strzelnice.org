@php
    use Carbon\Carbon;

    $calendarDate = request('date', $calendarDate ?? now()->format('Y-m-d'));

    $calendarAxes = $shootingRange->lanes->map(function ($lane) {
        return [
            'id' => $lane->id,
            'name' => $lane->name,
            'length' => $lane->length,
            'positions' => $lane->numberOfPositions,
            'description' => $lane->description,
        ];
    })->values();

    $openingHours = $shootingRange->openingHours->mapWithKeys(function ($hour) {
        return [
            (int) $hour->day_of_week => [
                'opens_at' => Carbon::parse($hour->opens_at)->format('H:i'),
                'closes_at' => Carbon::parse($hour->closes_at)->format('H:i'),
            ],
        ];
    });

    $holidays = $shootingRange->holidays->map(function ($holiday) {
        return [
            'date' => Carbon::parse($holiday->date)->format('Y-m-d'),
            'name' => $holiday->name,
        ];
    })->values();

    $activeIntervals = $shootingRange->intervals
        ->where('is_active', true)
        ->map(function ($interval) {
            return [
                'start_date' => Carbon::parse($interval->start_date)->format('Y-m-d'),
                'end_date' => Carbon::parse($interval->end_date)->format('Y-m-d'),
            ];
        })
        ->values();
@endphp

<x-app-layout>
    <div class="page-bg-2"></div>


    <main class="container py-4 reservation-calendar">
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">
                <strong>Nie udało się zapisać rezerwacji.</strong>
                <ul class="mt-2 mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="calendar-toolbar">
            <div>
                <div class="calendar-eyebrow">Rezerwacja online</div>
                <h1 class="calendar-title">{{ $shootingRange->name }}</h1>
                <div class="calendar-address">
                    <i class="bi bi-geo-alt"></i>
                    {{ $shootingRange->street }} {{ $shootingRange->houseNumber }},
                    {{ $shootingRange->zipCode }} {{ $shootingRange->town }}
                </div>
            </div>

            <div class="calendar-controls">
                <div class="btn-group" role="group" aria-label="Zmiana daty">
                    <button type="button" id="prevDate" class="btn btn-light" title="Poprzedni dzień">
                        <i class="bi bi-chevron-left"></i>
                    </button>

                    <button type="button" id="todayDate" class="btn btn-light">Dzisiaj</button>

                    <button type="button" id="nextDate" class="btn btn-light" title="Następny dzień">
                        <i class="bi bi-chevron-right"></i>
                    </button>
                </div>

                <input
                    type="date"
                    id="calendarDate"
                    class="form-control"
                    value="{{ $calendarDate }}"
                    min="{{ now()->format('Y-m-d') }}"
                    aria-label="Data kalendarza"
                >

                <div class="btn-group" role="group" aria-label="Widok kalendarza">
                    <button type="button" class="btn btn-outline-secondary view-button" data-days="1">
                        Dzień
                    </button>
                    <button type="button" class="btn btn-outline-secondary view-button active" data-days="3">
                        3 dni
                    </button>
                </div>
            </div>
        </section>

        <section class="calendar-legend" aria-label="Legenda dostępności">
            <span><i class="availability-dot availability-free"></i> Wolne</span>
            <span><i class="availability-dot availability-limited"></i> Częściowo zajęte</span>
            <span><i class="availability-dot availability-full"></i> Brak miejsc</span>
            <span><i class="availability-dot availability-closed"></i> Niedostępne</span>
        </section>

        <div id="calendarApp" aria-live="polite"></div>
    </main>

    <div class="modal fade" id="reservationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="{{ route('rezerwacje.store') }}" class="modal-content">
                @csrf

                <input type="hidden" name="shooting_range_id" value="{{ $shootingRange->id }}">

                <div class="modal-header">
                    <div>
                        <div class="calendar-eyebrow">Wybrany termin</div>
                        <h5 class="modal-title">Nowa rezerwacja</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                </div>

                <div class="modal-body">
                    <div id="reservationAvailability" class="mb-3 availability-summary"></div>

                    <div class="mb-3">
                        <label for="reservationLine" class="form-label">Oś strzelecka</label>
                        <select name="line_id" id="reservationLine" class="form-select" required>
                            @foreach ($shootingRange->lanes as $lane)
                                <option value="{{ $lane->id }}">
                                    {{ $lane->name }} · {{ $lane->length }} m · {{ $lane->numberOfPositions }} stanowisk
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label for="reservationDate" class="form-label">Data</label>
                            <input
                                type="date"
                                name="date"
                                id="reservationDate"
                                class="form-control"
                                min="{{ now()->format('Y-m-d') }}"
                                required
                            >
                        </div>

                        <div class="col-sm-6">
                            <label for="reservationPositions" class="form-label">Liczba stanowisk</label>
                            <select name="positions_count" id="reservationPositions" class="form-select" required></select>
                        </div>

                        <div class="col-sm-6">
                            <label for="reservationStart" class="form-label">Od</label>
                            <select name="start_time" id="reservationStart" class="form-select" required></select>
                        </div>

                        <div class="col-sm-6">
                            <label for="reservationEnd" class="form-label">Do</label>
                            <select name="end_time" id="reservationEnd" class="form-select" required></select>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Anuluj</button>
                    <button type="submit" id="reservationSubmit" class="btn btn-success">
                        <i class="bi bi-calendar-check me-1"></i>
                        Zarezerwuj
                    </button>
                </div>
            </form>
        </div>
    </div>

    <style>
        .reservation-calendar {
            --calendar-border: #e3e7e5;
            --calendar-muted: #68736e;
            --calendar-green: #198754;
            --calendar-green-bg: #e9f7ef;
            --calendar-yellow: #997404;
            --calendar-yellow-bg: #fff8db;
            --calendar-red: #b42318;
            --calendar-red-bg: #fff0ee;
        }

        .calendar-toolbar {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 1rem;
            padding: 1.25rem;
            background: #fff;
            border: 1px solid var(--calendar-border);
            border-radius: 8px;
        }

        .calendar-eyebrow {
            color: var(--calendar-green);
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: 0;
            text-transform: uppercase;
        }

        .calendar-title {
            margin: .15rem 0 .25rem;
            font-size: clamp(1.35rem, 3vw, 2rem);
            font-weight: 700;
        }

        .calendar-address {
            color: var(--calendar-muted);
            font-size: .9rem;
        }

        .calendar-controls {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: .5rem;
            flex-wrap: wrap;
        }

        .calendar-controls .form-control {
            width: auto;
        }

        .calendar-legend {
            display: flex;
            gap: 1.25rem;
            flex-wrap: wrap;
            padding: .8rem 1rem;
            color: var(--calendar-muted);
            font-size: .82rem;
        }

        .calendar-legend span {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
        }

        .availability-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }

        .availability-free {
            background: var(--calendar-green);
        }

        .availability-limited {
            background: #ffc107;
        }

        .availability-full {
            background: #dc3545;
        }

        .availability-closed {
            background: #adb5bd;
        }

        .calendar-day {
            margin-bottom: .8rem;
            overflow: hidden;
            background: #fff;
            border: 1px solid var(--calendar-border);
            border-radius: 8px;
        }

        .calendar-day-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: .8rem 1rem;
            border-bottom: 1px solid var(--calendar-border);
        }

        .calendar-day-heading.is-today {
            border-left: 4px solid var(--calendar-green);
        }

        .calendar-day-title {
            margin: 0;
            font-size: 1rem;
            font-weight: 700;
        }

        .calendar-day-status {
            font-size: .8rem;
            color: var(--calendar-muted);
        }

        .calendar-scroll {
            overflow-x: auto;
        }

        .calendar-grid {
            min-width: max-content;
        }

        .calendar-time-row,
        .calendar-lane-row {
            display: grid;
            grid-template-columns: 190px repeat(var(--slot-count), 86px);
        }

        .calendar-time-row {
            position: sticky;
            top: 0;
            z-index: 4;
            background: #f8f9fa;
            border-bottom: 1px solid var(--calendar-border);
        }

        .calendar-time-label,
        .calendar-lane-label {
            position: sticky;
            left: 0;
            z-index: 3;
            border-right: 1px solid var(--calendar-border);
        }

        .calendar-time-label {
            z-index: 5;
            background: #f8f9fa;
        }

        .calendar-time-cell {
            padding: .55rem .25rem;
            color: var(--calendar-muted);
            font-size: .72rem;
            font-weight: 600;
            text-align: center;
            border-right: 1px solid var(--calendar-border);
        }

        .calendar-lane-row {
            min-height: 76px;
            border-bottom: 1px solid var(--calendar-border);
        }

        .calendar-lane-row:last-child {
            border-bottom: 0;
        }

        .calendar-lane-label {
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: .7rem .85rem;
            background: #fff;
        }

        .calendar-lane-label strong {
            font-size: .88rem;
        }

        .calendar-lane-label span {
            color: var(--calendar-muted);
            font-size: .72rem;
        }

        .calendar-slot {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: .12rem;
            min-width: 86px;
            padding: .35rem;
            border: 0;
            border-right: 1px solid var(--calendar-border);
            transition: background-color .15s ease, box-shadow .15s ease;
        }

        .calendar-slot strong {
            font-size: .78rem;
        }

        .calendar-slot span {
            font-size: .66rem;
        }

        .calendar-slot.is-free {
            color: #11643d;
            background: var(--calendar-green-bg);
            cursor: pointer;
        }

        .calendar-slot.is-limited {
            color: var(--calendar-yellow);
            background: var(--calendar-yellow-bg);
            cursor: pointer;
        }

        .calendar-slot.is-full {
            color: var(--calendar-red);
            background: var(--calendar-red-bg);
            cursor: not-allowed;
        }

        .calendar-slot.is-free:hover,
        .calendar-slot.is-limited:hover {
            box-shadow: inset 0 0 0 2px currentColor;
        }

        .calendar-closed {
            padding: 1.5rem;
            color: var(--calendar-muted);
            text-align: center;
            background: #f8f9fa;
        }

        .availability-summary {
            padding: .75rem;
            color: #11643d;
            font-size: .86rem;
            background: var(--calendar-green-bg);
            border-left: 3px solid var(--calendar-green);
            border-radius: 4px;
        }

        .availability-summary.is-unavailable {
            color: var(--calendar-red);
            background: var(--calendar-red-bg);
            border-left-color: var(--calendar-red);
        }

        @media (max-width: 767.98px) {
            .calendar-toolbar {
                align-items: stretch;
                flex-direction: column;
            }

            .calendar-controls {
                justify-content: flex-start;
            }

            .calendar-controls .form-control {
                flex: 1 1 150px;
            }

            .calendar-legend {
                gap: .7rem 1rem;
            }

            .calendar-time-row,
            .calendar-lane-row {
                grid-template-columns: 145px repeat(var(--slot-count), 78px);
            }

            .calendar-lane-label {
                padding: .55rem;
            }

            .calendar-slot {
                min-width: 78px;
            }
        }
    </style>

    <script>
        const calendarConfig = {
            axes: @json($calendarAxes),
            openingHours: @json($openingHours),
            holidays: @json($holidays),
            intervals: @json($activeIntervals),
            reservations: @json($reservations ?? []),
            initialDate: @json($calendarDate),
            slotMinutes: 30
        };

        const calendarState = {
            date: parseLocalDate(calendarConfig.initialDate),
            days: 3
        };

        const polishDays = [
            'Niedziela',
            'Poniedziałek',
            'Wtorek',
            'Środa',
            'Czwartek',
            'Piątek',
            'Sobota'
        ];

        function parseLocalDate(value) {
            const [year, month, day] = value.split('-').map(Number);
            return new Date(year, month - 1, day);
        }

        function formatDate(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }

        function formatDayHeading(date) {
            return `${polishDays[date.getDay()]}, ${String(date.getDate()).padStart(2, '0')}.${String(date.getMonth() + 1).padStart(2, '0')}.${date.getFullYear()}`;
        }

        function timeToMinutes(value) {
            const [hours, minutes] = value.split(':').map(Number);
            return (hours * 60) + minutes;
        }

        function minutesToTime(value) {
            const hours = Math.floor(value / 60);
            const minutes = value % 60;
            return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`;
        }

        function getIsoDay(date) {
            return date.getDay() === 0 ? 7 : date.getDay();
        }

        function getDayAvailability(date) {
            const dateString = formatDate(date);

            if (dateString < formatDate(new Date())) {
                return { isOpen: false, reason: 'Termin minął' };
            }

            const intervalExists = calendarConfig.intervals.some(interval => {
                return interval.start_date <= dateString && interval.end_date >= dateString;
            });

            if (!intervalExists) {
                return { isOpen: false, reason: 'Brak aktywnego przedziału' };
            }

            const holiday = calendarConfig.holidays.find(item => item.date === dateString);

            if (holiday) {
                return { isOpen: false, reason: holiday.name || 'Dzień wolny' };
            }

            const opening = calendarConfig.openingHours[getIsoDay(date)];

            if (!opening) {
                return { isOpen: false, reason: 'Strzelnica nieczynna' };
            }

            return {
                isOpen: true,
                opensAt: opening.opens_at,
                closesAt: opening.closes_at
            };
        }

        function getReservations(axisId, dateString) {
            return calendarConfig.reservations.filter(reservation => {
                return Number(reservation.line_id) === Number(axisId)
                    && reservation.start_date === dateString;
            });
        }

        function getFreePositions(axis, dateString, startTime, endTime) {
            const start = timeToMinutes(startTime);
            const end = timeToMinutes(endTime);
            const used = getReservations(axis.id, dateString)
                .filter(reservation => {
                    return timeToMinutes(reservation.start_time) < end
                        && timeToMinutes(reservation.end_time) > start;
                })
                .reduce((sum, reservation) => sum + Number(reservation.positions_count || 1), 0);

            return Math.max(0, Number(axis.positions) - used);
        }

        function getMinimumFreePositions(axis, dateString, startTime, endTime) {
            const start = timeToMinutes(startTime);
            const end = timeToMinutes(endTime);
            let minimum = Number(axis.positions);

            for (let slotStart = start; slotStart < end; slotStart += calendarConfig.slotMinutes) {
                const slotEnd = Math.min(slotStart + calendarConfig.slotMinutes, end);
                minimum = Math.min(
                    minimum,
                    getFreePositions(axis, dateString, minutesToTime(slotStart), minutesToTime(slotEnd))
                );
            }

            return minimum;
        }

        function createSlot(axis, dateString, startTime, endTime) {
            const free = getFreePositions(axis, dateString, startTime, endTime);
            const slot = document.createElement('button');
            slot.type = 'button';
            slot.className = 'calendar-slot';

            if (free === Number(axis.positions)) {
                slot.classList.add('is-free');
                slot.innerHTML = `<strong>${free} wolnych</strong><span>z ${axis.positions}</span>`;
            } else if (free > 0) {
                slot.classList.add('is-limited');
                slot.innerHTML = `<strong>${free} wolnych</strong><span>z ${axis.positions}</span>`;
            } else {
                slot.classList.add('is-full');
                slot.disabled = true;
                slot.innerHTML = '<strong>Brak miejsc</strong><span>zajęte</span>';
            }

            if (free > 0) {
                slot.title = `Rezerwuj ${startTime} - ${endTime}`;
                slot.addEventListener('click', () => openReservationModal(axis, dateString, startTime));
            }

            return slot;
        }

        function createDay(date) {
            const dateString = formatDate(date);
            const availability = getDayAvailability(date);
            const section = document.createElement('section');
            section.className = 'calendar-day';

            const heading = document.createElement('header');
            heading.className = 'calendar-day-heading';

            if (dateString === formatDate(new Date())) {
                heading.classList.add('is-today');
            }

            heading.innerHTML = `
                <h2 class="calendar-day-title">${formatDayHeading(date)}</h2>
                <span class="calendar-day-status">
                    ${availability.isOpen
                        ? `Czynna ${availability.opensAt}–${availability.closesAt}`
                        : availability.reason}
                </span>
            `;

            section.appendChild(heading);

            if (!availability.isOpen) {
                const closed = document.createElement('div');
                closed.className = 'calendar-closed';
                closed.innerHTML = `
                    <i class="mb-2 bi bi-calendar-x fs-4 d-block"></i>
                    Brak możliwości rezerwacji w tym dniu.
                `;
                section.appendChild(closed);
                return section;
            }

            const startMinutes = timeToMinutes(availability.opensAt);
            const endMinutes = timeToMinutes(availability.closesAt);
            const slotCount = Math.ceil((endMinutes - startMinutes) / calendarConfig.slotMinutes);
            const scroll = document.createElement('div');
            scroll.className = 'calendar-scroll';

            const grid = document.createElement('div');
            grid.className = 'calendar-grid';
            grid.style.setProperty('--slot-count', slotCount);

            const timeRow = document.createElement('div');
            timeRow.className = 'calendar-time-row';
            timeRow.innerHTML = '<div class="calendar-time-label"></div>';

            for (let slotStart = startMinutes; slotStart < endMinutes; slotStart += calendarConfig.slotMinutes) {
                const cell = document.createElement('div');
                cell.className = 'calendar-time-cell';
                cell.textContent = minutesToTime(slotStart);
                timeRow.appendChild(cell);
            }

            grid.appendChild(timeRow);

            calendarConfig.axes.forEach(axis => {
                const row = document.createElement('div');
                row.className = 'calendar-lane-row';
                row.innerHTML = `
                    <div class="calendar-lane-label">
                        <strong>${axis.name}</strong>
                        <span>${axis.length} m · ${axis.positions} stanowisk</span>
                    </div>
                `;

                for (let slotStart = startMinutes; slotStart < endMinutes; slotStart += calendarConfig.slotMinutes) {
                    const slotEnd = Math.min(slotStart + calendarConfig.slotMinutes, endMinutes);
                    row.appendChild(createSlot(
                        axis,
                        dateString,
                        minutesToTime(slotStart),
                        minutesToTime(slotEnd)
                    ));
                }

                grid.appendChild(row);
            });

            scroll.appendChild(grid);
            section.appendChild(scroll);
            return section;
        }

        function renderCalendar() {
            const container = document.getElementById('calendarApp');
            container.innerHTML = '';

            for (let index = 0; index < calendarState.days; index++) {
                const date = new Date(calendarState.date);
                date.setDate(date.getDate() + index);
                container.appendChild(createDay(date));
            }

            document.getElementById('calendarDate').value = formatDate(calendarState.date);
        }

        function updatePositionOptions(maximum) {
            const select = document.getElementById('reservationPositions');
            const previous = Number(select.value || 1);
            select.innerHTML = '';

            for (let position = 1; position <= maximum; position++) {
                const option = document.createElement('option');
                option.value = position;
                option.textContent = position;
                select.appendChild(option);
            }

            if (maximum > 0) {
                select.value = Math.min(previous, maximum);
            }
        }

        function appendTimeOption(select, value) {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = value;
            select.appendChild(option);
        }

        function updateEndTimeOptions(preferredEnd = null) {
            const dateString = document.getElementById('reservationDate').value;
            const startSelect = document.getElementById('reservationStart');
            const endSelect = document.getElementById('reservationEnd');
            const availability = dateString
                ? getDayAvailability(parseLocalDate(dateString))
                : { isOpen: false };

            endSelect.innerHTML = '';

            if (!availability.isOpen || !startSelect.value) {
                return;
            }

            const startMinutes = timeToMinutes(startSelect.value);
            const closingMinutes = timeToMinutes(availability.closesAt);

            for (
                let minutes = startMinutes + calendarConfig.slotMinutes;
                minutes <= closingMinutes;
                minutes += calendarConfig.slotMinutes
            ) {
                appendTimeOption(endSelect, minutesToTime(minutes));
            }

            const defaultEnd = minutesToTime(
                Math.min(startMinutes + 60, closingMinutes)
            );

            endSelect.value = [...endSelect.options].some(option => option.value === preferredEnd)
                ? preferredEnd
                : defaultEnd;
        }

        function updateTimeOptions(preferredStart = null, preferredEnd = null) {
            const dateString = document.getElementById('reservationDate').value;
            const startSelect = document.getElementById('reservationStart');
            const availability = dateString
                ? getDayAvailability(parseLocalDate(dateString))
                : { isOpen: false };

            startSelect.innerHTML = '';

            if (!availability.isOpen) {
                document.getElementById('reservationEnd').innerHTML = '';
                return;
            }

            const openingMinutes = timeToMinutes(availability.opensAt);
            const closingMinutes = timeToMinutes(availability.closesAt);

            for (
                let minutes = openingMinutes;
                minutes < closingMinutes;
                minutes += calendarConfig.slotMinutes
            ) {
                appendTimeOption(startSelect, minutesToTime(minutes));
            }

            if ([...startSelect.options].some(option => option.value === preferredStart)) {
                startSelect.value = preferredStart;
            }

            updateEndTimeOptions(preferredEnd);
        }

        function updateModalAvailability() {
            const axis = calendarConfig.axes.find(item => {
                return Number(item.id) === Number(document.getElementById('reservationLine').value);
            });
            const dateString = document.getElementById('reservationDate').value;
            const startTime = document.getElementById('reservationStart').value;
            const endTime = document.getElementById('reservationEnd').value;
            const summary = document.getElementById('reservationAvailability');
            const submit = document.getElementById('reservationSubmit');

            if (!axis || !dateString || !startTime || !endTime || timeToMinutes(endTime) <= timeToMinutes(startTime)) {
                summary.classList.add('is-unavailable');
                summary.textContent = 'Wybierz poprawny termin rezerwacji.';
                updatePositionOptions(0);
                submit.disabled = true;
                return;
            }

            const availability = getDayAvailability(parseLocalDate(dateString));

            if (
                !availability.isOpen
                || timeToMinutes(startTime) < timeToMinutes(availability.opensAt)
                || timeToMinutes(endTime) > timeToMinutes(availability.closesAt)
            ) {
                summary.classList.add('is-unavailable');
                summary.textContent = availability.isOpen
                    ? `Rezerwacja musi mieścić się w godzinach ${availability.opensAt}–${availability.closesAt}.`
                    : availability.reason;
                updatePositionOptions(0);
                submit.disabled = true;
                return;
            }

            const free = getMinimumFreePositions(axis, dateString, startTime, endTime);
            updatePositionOptions(free);
            submit.disabled = free < 1;
            summary.classList.toggle('is-unavailable', free < 1);
            summary.innerHTML = free > 0
                ? `<strong>${free}</strong> stanowisk dostępnych przez cały wybrany czas.`
                : 'Brak wolnych stanowisk w części wybranego przedziału.';
        }

        function openReservationModal(axis, dateString, startTime) {
            const availability = getDayAvailability(parseLocalDate(dateString));
            const proposedEnd = Math.min(
                timeToMinutes(startTime) + 60,
                timeToMinutes(availability.closesAt)
            );

            document.getElementById('reservationLine').value = axis.id;
            document.getElementById('reservationDate').value = dateString;
            updateTimeOptions(startTime, minutesToTime(proposedEnd));
            updateModalAvailability();

            bootstrap.Modal.getOrCreateInstance(document.getElementById('reservationModal')).show();
        }

        document.addEventListener('DOMContentLoaded', () => {
            renderCalendar();

            document.getElementById('prevDate').addEventListener('click', () => {
                calendarState.date.setDate(calendarState.date.getDate() - 1);
                renderCalendar();
            });

            document.getElementById('nextDate').addEventListener('click', () => {
                calendarState.date.setDate(calendarState.date.getDate() + 1);
                renderCalendar();
            });

            document.getElementById('todayDate').addEventListener('click', () => {
                calendarState.date = new Date();
                renderCalendar();
            });

            document.getElementById('calendarDate').addEventListener('change', event => {
                calendarState.date = parseLocalDate(event.target.value);
                renderCalendar();
            });

            document.querySelectorAll('.view-button').forEach(button => {
                button.addEventListener('click', () => {
                    document.querySelectorAll('.view-button').forEach(item => item.classList.remove('active'));
                    button.classList.add('active');
                    calendarState.days = Number(button.dataset.days);
                    renderCalendar();
                });
            });

            document.getElementById('reservationLine').addEventListener('change', updateModalAvailability);

            document.getElementById('reservationDate').addEventListener('change', () => {
                updateTimeOptions();
                updateModalAvailability();
            });

            document.getElementById('reservationStart').addEventListener('change', () => {
                updateEndTimeOptions();
                updateModalAvailability();
            });

            document.getElementById('reservationEnd').addEventListener('change', updateModalAvailability);
        });
    </script>
</x-app-layout>
