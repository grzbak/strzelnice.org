@php
    use Carbon\Carbon;

    $calendarDate = $calendarDate ?? now()->format('Y-m-d');

    $calendarAxes = $shootingRange->lanes->map(fn ($lane) => [
        'id' => $lane->id,
        'name' => $lane->name,
        'length' => $lane->length,
        'positions' => $lane->numberOfPositions,
        'allows_dynamic_shooting' => (bool) $lane->allows_dynamic_shooting,
        'description' => $lane->description,
    ])->values();

    $openingHours = $shootingRange->openingHours->mapWithKeys(fn ($hour) => [
        (int) $hour->day_of_week => [
            'opens_at' => Carbon::parse($hour->opens_at)->format('H:i'),
            'closes_at' => Carbon::parse($hour->closes_at)->format('H:i'),
        ],
    ]);

    $holidays = $shootingRange->holidays->map(fn ($holiday) => [
        'date' => Carbon::parse($holiday->date)->format('Y-m-d'),
        'name' => $holiday->name,
    ])->values();

    $activeIntervals = $shootingRange->intervals
        ->where('is_active', true)
        ->map(fn ($interval) => [
            'start_date' => Carbon::parse($interval->start_date)->format('Y-m-d'),
            'end_date' => Carbon::parse($interval->end_date)->format('Y-m-d'),
            'is_paid' => $interval->datePaid !== null,
            'payment_due_date' => $interval->payment_due_date
                ? Carbon::parse($interval->payment_due_date)->format('Y-m-d')
                : null,
        ])
        ->values();
@endphp

<x-app-layout>
    <div class="page-bg-2"></div>

    @include('components.breadcrumb')

    <main class="container py-4 day-booking">
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <section class="booking-head">
            <div>
                <span class="booking-kicker">Rezerwacja online</span>
                <h1>{{ $shootingRange->name }}</h1>
                <p class="mb-0">
                    <i class="bi bi-geo-alt"></i>
                    {{ $shootingRange->town }}
                </p>

                @if ($canViewCalendar)
                    <div class="availability-legend">
                        <span><i class="dot free"></i> Wolne</span>
                        <span><i class="dot limited"></i> Ostatnie miejsca</span>
                        <span><i class="dot full"></i> Zajęte</span>
                    </div>
                @endif
            </div>

            @if ($canViewCalendar)
                <div class="date-navigation">
                    <button type="button" id="previousDay" class="icon-button" title="Poprzedni dzień">
                        <i class="bi bi-chevron-left"></i>
                    </button>

                    <input
                        type="date"
                        id="selectedDate"
                        class="form-control"
                        value="{{ $calendarDate }}"
                        min="{{ now()->format('Y-m-d') }}"
                        max="{{ $maxBookingDate->format('Y-m-d') }}"
                        title="Rezerwacje są dostępne maksymalnie {{ $maxAdvanceBookingDays }} dni do przodu"
                    >

                    <button type="button" id="nextDay" class="icon-button" title="Następny dzień">
                        <i class="bi bi-chevron-right"></i>
                    </button>
                </div>
            @endif
        </section>

        @if ($canViewCalendar)
            <section id="daySummary" class="day-summary"></section>
            <section id="lanesList" class="lanes-list"></section>
        @elseif (auth()->check() && ! $shootingRange->is_public_access)
            <section id="closedGroupNotice" class="login-required-panel">
                <div class="login-required-icon">
                    <i class="bi bi-shield-lock-fill" aria-hidden="true"></i>
                </div>
                <div class="login-required-content">
                    <h2>Rezerwacje dla zamkniętej grupy</h2>
                    <p>
                        Ta strzelnica przyjmuje rezerwacje wyłącznie od
                        zaproszonych użytkowników.
                    </p>
                    <p class="login-required-note">
                        Twoje konto nie posiada aktywnego dostępu.
                    </p>
                    <a href="{{ route('shootings.list') }}" class="btn btn-outline-dark">
                        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>
                        Wróć do listy strzelnic
                    </a>
                </div>
            </section>
        @else
            <section id="loginRequiredNotice" class="login-required-panel">
                <div class="login-required-icon">
                    <i class="bi bi-shield-lock-fill" aria-hidden="true"></i>
                </div>
                <div class="login-required-content">
                    <h2>Zaloguj się, aby zarezerwować</h2>
                    <p>
                        Rezerwacje online są dostępne dla zalogowanych
                        użytkowników.
                    </p>
                    <div class="login-required-actions">
                        <a href="{{ route('login') }}" class="btn btn-dark">
                            <i class="bi bi-box-arrow-in-right me-1" aria-hidden="true"></i>
                            Zaloguj się
                        </a>
                        <span>
                            Nie masz konta?
                            <a href="{{ route('register') }}">Zarejestruj się</a>
                        </span>
                    </div>
                </div>
            </section>
        @endif
    </main>

    @if ($canViewCalendar)
        <div class="modal fade" id="reservationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="{{ route('rezerwacje.store') }}" class="modal-content">
                @csrf

                <input type="hidden" name="shooting_range_id" value="{{ $shootingRange->id }}">

                <div class="modal-header">
                    <div>
                        <span class="booking-kicker">Wybrany termin</span>
                        <h5 class="modal-title" id="modalHeading">Rezerwacja</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div id="modalAvailability" class="modal-availability"></div>

                    <div class="mb-3">
                        <label for="lineId" class="form-label">Oś</label>
                        <select name="line_id" id="lineId" class="form-select" required>
                            @foreach ($shootingRange->lanes as $lane)
                                <option value="{{ $lane->id }}">
                                    {{ $lane->name }} · {{ $lane->length }} m
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <input type="hidden" name="date" id="reservationDate">

                    <div class="row g-3">
                        <div class="col-6">
                            <label for="startTime" class="form-label">Od</label>
                            <select name="start_time" id="startTime" class="form-select" required></select>
                        </div>

                        <div class="col-6">
                            <label for="endTime" class="form-label">Do</label>
                            <select name="end_time" id="endTime" class="form-select" required></select>
                        </div>

                        <div class="col-12">
                            <label for="positionsCount" class="form-label">Liczba stanowisk</label>
                            <select name="positions_count" id="positionsCount" class="form-select" required></select>
                        </div>

                        <div class="col-12 d-none" id="dynamicShootingField">
                            <input type="hidden" name="is_dynamic_shooting" value="0">
                            <div class="form-check">
                                <input
                                    type="checkbox"
                                    name="is_dynamic_shooting"
                                    id="dynamicShooting"
                                    class="form-check-input"
                                    value="1"
                                >
                                <label for="dynamicShooting" class="form-check-label">
                                    Strzelanie dynamiczne - rezerwacja całej osi
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Anuluj</button>
                    <button type="submit" id="submitReservation" class="btn btn-success">
                        <i class="bi bi-calendar-check me-1"></i>
                        Zarezerwuj
                    </button>
                </div>
            </form>
        </div>
        </div>
    @endif

    <style>
        .day-booking {
            --line: #e5e9e7;
            --muted: #68736e;
            --green: #198754;
            --green-soft: #eaf7f0;
            --yellow: #9a7100;
            --yellow-soft: #fff7d6;
            --red: #b42318;
            --red-soft: #fff0ee;
        }

        .booking-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1.1rem 1.25rem;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 8px;
        }

        .booking-head h1 {
            margin: .15rem 0;
            font-size: clamp(1.25rem, 3vw, 1.8rem);
            font-weight: 700;
        }

        .booking-head p {
            color: var(--muted);
            font-size: .85rem;
        }

        .booking-kicker {
            color: var(--green);
            font-size: .7rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .login-required-panel {
            display: flex;
            align-items: flex-start;
            gap: 1.35rem;
            margin-top: .75rem;
            padding: 2rem 2.25rem;
            color: var(--red);
            background: rgba(255, 252, 235, .96);
            border: 1px solid var(--red);
            border-left: 7px solid var(--red);
            border-radius: 8px;
        }

        .login-required-panel h2 {
            margin: 0 0 .55rem;
            font-size: 1.25rem;
            font-weight: 700;
        }

        .login-required-icon {
            flex: 0 0 auto;
            color: var(--red);
            font-size: 3.4rem;
            line-height: 1;
        }

        .login-required-content {
            max-width: 680px;
        }

        .login-required-content > p {
            margin-bottom: .45rem;
            color: var(--muted);
        }

        .login-required-note {
            font-size: .9rem;
        }

        .login-required-content > .btn {
            margin-top: .7rem;
        }

        .login-required-actions {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: .75rem 1.1rem;
            margin-top: .9rem;
        }

        .login-required-actions span {
            color: var(--muted);
            font-size: .88rem;
        }

        .login-required-actions span a {
            color: #151719;
            font-weight: 600;
        }

        .date-navigation {
            display: flex;
            align-items: center;
            gap: .4rem;
        }

        .date-navigation .form-control {
            width: auto;
        }

        .icon-button {
            display: inline-grid;
            width: 40px;
            height: 40px;
            place-items: center;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 6px;
        }

        .icon-button:hover {
            background: #f4f6f5;
        }

        .icon-button:disabled {
            cursor: not-allowed;
            opacity: .45;
        }

        .availability-legend {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            padding-top: .65rem;
            color: var(--muted);
            font-size: .78rem;
        }

        .availability-legend span {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
        }

        .dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
        }

        .dot.free {
            background: var(--green);
        }

        .dot.limited {
            background: #ffc107;
        }

        .dot.full {
            background: #dc3545;
        }

        .day-summary {
            margin-bottom: .75rem;
            padding: .8rem 1rem;
            color: var(--muted);
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 6px;
        }

        .day-summary strong {
            color: #26332d;
        }

        .day-summary.is-closed {
            color: var(--red);
            background: var(--red-soft);
            border-color: #f2c7c2;
        }

        .lanes-list {
            display: grid;
            gap: .65rem;
        }

        .lane-panel {
            display: grid;
            grid-template-columns: 170px minmax(0, 1fr);
            overflow: hidden;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 7px;
        }

        .lane-info {
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 1rem;
            border-right: 1px solid var(--line);
        }

        .lane-info strong {
            font-size: .92rem;
        }

        .lane-info span {
            margin-top: .15rem;
            color: var(--muted);
            font-size: .74rem;
        }

        .time-slots {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(92px, 1fr));
            gap: .4rem;
            padding: .65rem;
        }

        .time-slot {
            min-height: 55px;
            padding: .35rem;
            border: 1px solid transparent;
            border-radius: 5px;
            transition: border-color .15s ease, box-shadow .15s ease;
        }

        .time-slot strong,
        .time-slot span {
            display: block;
        }

        .time-slot strong {
            font-size: .8rem;
        }

        .time-slot span {
            margin-top: .1rem;
            font-size: .67rem;
        }

        .time-slot.is-free {
            color: #11643d;
            background: var(--green-soft);
            cursor: pointer;
        }

        .time-slot.is-limited {
            color: var(--yellow);
            background: var(--yellow-soft);
            cursor: pointer;
        }

        .time-slot.is-full {
            color: var(--red);
            background: var(--red-soft);
            cursor: not-allowed;
        }

        .time-slot.is-free:hover,
        .time-slot.is-limited:hover {
            border-color: currentColor;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .08);
        }

        .modal-availability {
            margin-bottom: 1rem;
            padding: .7rem .8rem;
            color: #11643d;
            font-size: .84rem;
            background: var(--green-soft);
            border-left: 3px solid var(--green);
            border-radius: 4px;
        }

        .modal-availability.is-unavailable {
            color: var(--red);
            background: var(--red-soft);
            border-left-color: var(--red);
        }

        @media (max-width: 767.98px) {
            .booking-head {
                align-items: stretch;
                flex-direction: column;
            }

            .login-required-panel {
                gap: 1rem;
                padding: 1.5rem;
            }

            .login-required-icon {
                font-size: 2.75rem;
            }

            .date-navigation .form-control {
                flex: 1;
                width: 100%;
            }

            .lane-panel {
                display: block;
            }

            .lane-info {
                padding: .7rem .8rem;
                border-right: 0;
                border-bottom: 1px solid var(--line);
            }

            .time-slots {
                grid-template-columns: repeat(3, 1fr);
                gap: .3rem;
                padding: .5rem;
            }
        }

        @media (max-width: 420px) {
            .login-required-panel {
                align-items: center;
                flex-direction: column;
                text-align: center;
            }

            .login-required-actions {
                justify-content: center;
            }

            .time-slots {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>

    <script>
        const bookingConfig = {
            axes: @json($calendarAxes),
            openingHours: @json($openingHours),
            holidays: @json($holidays),
            intervals: @json($activeIntervals),
            reservations: @json($reservations ?? []),
            availabilityUrl: @json(route('rezerwacje.availability', $shootingRange->id)),
            initialDate: @json($calendarDate),
            loadedDate: @json($calendarDate),
            loadError: null,
            minDate: @json(now()->format('Y-m-d')),
            maxDate: @json($maxBookingDate->format('Y-m-d')),
            maxAdvanceDays: @json($maxAdvanceBookingDays),
            canViewCalendar: @json($canViewCalendar),
            slotMinutes: 30
        };

        let currentDate = parseDate(bookingConfig.initialDate);

        function parseDate(value) {
            const [year, month, day] = value.split('-').map(Number);
            return new Date(year, month - 1, day);
        }

        function formatDate(date) {
            return [
                date.getFullYear(),
                String(date.getMonth() + 1).padStart(2, '0'),
                String(date.getDate()).padStart(2, '0')
            ].join('-');
        }

        function formatReadableDate(date) {
            return new Intl.DateTimeFormat('pl-PL', {
                weekday: 'long',
                day: 'numeric',
                month: 'long',
                year: 'numeric'
            }).format(date);
        }

        function timeToMinutes(time) {
            const [hour, minute] = time.split(':').map(Number);
            return (hour * 60) + minute;
        }

        function minutesToTime(minutes) {
            return `${String(Math.floor(minutes / 60)).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}`;
        }

        function getIsoDay(date) {
            return date.getDay() === 0 ? 7 : date.getDay();
        }

        function getAvailability(date) {
            const dateString = formatDate(date);

            if (
                bookingConfig.loadError
                && bookingConfig.loadedDate === dateString
            ) {
                return {
                    isOpen: false,
                    reason: bookingConfig.loadError
                };
            }

            if (dateString < formatDate(new Date())) {
                return { isOpen: false, reason: 'Wybrany dzień już minął' };
            }

            if (dateString > bookingConfig.maxDate) {
                return {
                    isOpen: false,
                    reason: `Rezerwacje są dostępne maksymalnie ${bookingConfig.maxAdvanceDays} dni do przodu`
                };
            }

            const hasInterval = bookingConfig.intervals.some(interval => {
                const isFinanciallyValid = interval.is_paid
                    || (interval.payment_due_date && interval.payment_due_date >= dateString);

                return interval.start_date <= dateString
                    && interval.end_date >= dateString
                    && isFinanciallyValid;
            });

            if (!hasInterval) {
                return { isOpen: false, reason: 'Brak aktywnego przedziału' };
            }

            const holiday = bookingConfig.holidays.find(item => item.date === dateString);

            if (holiday) {
                return { isOpen: false, reason: holiday.name || 'Dzień wolny' };
            }

            const hours = bookingConfig.openingHours[getIsoDay(date)];

            return hours
                ? { isOpen: true, opensAt: hours.opens_at, closesAt: hours.closes_at }
                : { isOpen: false, reason: 'Strzelnica jest nieczynna' };
        }

        function getAxis(axisId) {
            return bookingConfig.axes.find(axis => Number(axis.id) === Number(axisId));
        }

        async function loadReservationsForDate(dateString) {
            const url = new URL(
                bookingConfig.availabilityUrl,
                window.location.origin
            );
            url.searchParams.set('date', dateString);

            try {
                const response = await fetch(url, {
                    headers: {
                        'Accept': 'application/json'
                    }
                });
                const result = await response.json();
                bookingConfig.loadedDate = dateString;

                if (!response.ok) {
                    bookingConfig.reservations = [];
                    bookingConfig.loadError = result.message
                        ?? 'Nie udało się pobrać dostępności.';
                    return;
                }

                bookingConfig.reservations = result.reservations ?? [];
                bookingConfig.loadError = null;
            } catch (error) {
                bookingConfig.loadedDate = dateString;
                bookingConfig.reservations = [];
                bookingConfig.loadError =
                    'Nie udało się pobrać aktualnej dostępności.';
            }
        }

        async function selectCalendarDate(date) {
            currentDate = date;
            await loadReservationsForDate(formatDate(currentDate));
            renderDay();
        }

        function getFreePositions(axis, dateString, startTime, endTime) {
            const start = timeToMinutes(startTime);
            const end = timeToMinutes(endTime);
            let minimumFree = Number(axis.positions);

            for (let slotStart = start; slotStart < end; slotStart += bookingConfig.slotMinutes) {
                const slotEnd = Math.min(slotStart + bookingConfig.slotMinutes, end);
                const used = bookingConfig.reservations
                    .filter(reservation => {
                        return Number(reservation.line_id) === Number(axis.id)
                            && reservation.start_date === dateString
                            && timeToMinutes(reservation.start_time) < slotEnd
                            && timeToMinutes(reservation.end_time) > slotStart;
                    })
                    .reduce(
                        (sum, reservation) => sum + Number(reservation.positions_count || 1),
                        0
                    );

                minimumFree = Math.min(
                    minimumFree,
                    Math.max(0, Number(axis.positions) - used)
                );
            }

            return minimumFree;
        }

        function getMinimumFree(axis, dateString, startTime, endTime) {
            let minimum = Number(axis.positions);

            for (
                let minutes = timeToMinutes(startTime);
                minutes < timeToMinutes(endTime);
                minutes += bookingConfig.slotMinutes
            ) {
                minimum = Math.min(minimum, getFreePositions(
                    axis,
                    dateString,
                    minutesToTime(minutes),
                    minutesToTime(Math.min(minutes + bookingConfig.slotMinutes, timeToMinutes(endTime)))
                ));
            }

            return minimum;
        }

        function isPastSlot(dateString, startTime) {
            const now = new Date();

            if (dateString !== formatDate(now)) {
                return false;
            }

            const slotStart = parseDate(dateString);
            const [hours, minutes] = startTime.split(':').map(Number);
            slotStart.setHours(hours, minutes, 0, 0);

            return slotStart <= now;
        }

        function createSlot(axis, dateString, startTime, endTime) {
            const free = getFreePositions(axis, dateString, startTime, endTime);
            const isPast = isPastSlot(dateString, startTime);
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'time-slot';

            if (isPast) {
                button.classList.add('is-full');
                button.disabled = true;
            } else if (free === Number(axis.positions)) {
                button.classList.add('is-free');
            } else if (free > 0) {
                button.classList.add('is-limited');
            } else {
                button.classList.add('is-full');
                button.disabled = true;
            }

            button.innerHTML = `
                <strong>${startTime}</strong>
                <span>${isPast ? 'termin minął' : (free > 0 ? `${free} wolnych` : 'brak miejsc')}</span>
            `;

            if (free > 0 && !isPast) {
                button.addEventListener('click', () => {
                    if (!bookingConfig.canViewCalendar) {
                        document.getElementById('loginRequiredNotice')
                            ?.scrollIntoView({
                                behavior: 'smooth',
                                block: 'center'
                            });
                        return;
                    }

                    openModal(axis, dateString, startTime);
                });
            }

            return button;
        }

        function renderDay() {
            const dateString = formatDate(currentDate);
            const availability = getAvailability(currentDate);
            const summary = document.getElementById('daySummary');
            const lanes = document.getElementById('lanesList');

            document.getElementById('selectedDate').value = dateString;
            document.getElementById('previousDay').disabled = dateString <= bookingConfig.minDate;
            document.getElementById('nextDay').disabled = dateString >= bookingConfig.maxDate;
            lanes.innerHTML = '';
            summary.classList.toggle('is-closed', !availability.isOpen);

            if (!availability.isOpen) {
                summary.innerHTML = `<strong>${formatReadableDate(currentDate)}</strong> · ${availability.reason}`;
                return;
            }

            summary.innerHTML = `
                <strong>${formatReadableDate(currentDate)}</strong>
                · czynna ${availability.opensAt}–${availability.closesAt}
                · wybierz godzinę rozpoczęcia
            `;

            bookingConfig.axes.forEach(axis => {
                const panel = document.createElement('article');
                panel.className = 'lane-panel';
                panel.innerHTML = `
                    <div class="lane-info">
                        <strong>${axis.name}</strong>
                        <span>${axis.length} m · ${axis.positions} stanowisk</span>
                    </div>
                `;

                const slots = document.createElement('div');
                slots.className = 'time-slots';

                for (
                    let minutes = timeToMinutes(availability.opensAt);
                    minutes < timeToMinutes(availability.closesAt);
                    minutes += bookingConfig.slotMinutes
                ) {
                    slots.appendChild(createSlot(
                        axis,
                        dateString,
                        minutesToTime(minutes),
                        minutesToTime(Math.min(minutes + bookingConfig.slotMinutes, timeToMinutes(availability.closesAt)))
                    ));
                }

                panel.appendChild(slots);
                lanes.appendChild(panel);
            });
        }

        function addOption(select, value) {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = value;
            select.appendChild(option);
        }

        function updateEndTimes(preferred = null) {
            const start = document.getElementById('startTime').value;
            const endSelect = document.getElementById('endTime');
            const availability = getAvailability(parseDate(document.getElementById('reservationDate').value));
            endSelect.innerHTML = '';

            if (!availability.isOpen || !start) {
                return;
            }

            const closing = timeToMinutes(availability.closesAt);

            for (let minutes = timeToMinutes(start) + 30; minutes <= closing; minutes += 30) {
                addOption(endSelect, minutesToTime(minutes));
            }

            const suggested = minutesToTime(Math.min(timeToMinutes(start) + 60, closing));
            endSelect.value = [...endSelect.options].some(option => option.value === preferred)
                ? preferred
                : suggested;
        }

        function updateStartTimes(preferredStart = null, preferredEnd = null) {
            const availability = getAvailability(parseDate(document.getElementById('reservationDate').value));
            const select = document.getElementById('startTime');
            select.innerHTML = '';

            if (!availability.isOpen) {
                return;
            }

            for (
                let minutes = timeToMinutes(availability.opensAt);
                minutes < timeToMinutes(availability.closesAt);
                minutes += 30
            ) {
                addOption(select, minutesToTime(minutes));
            }

            if ([...select.options].some(option => option.value === preferredStart)) {
                select.value = preferredStart;
            }

            updateEndTimes(preferredEnd);
        }

        function updateModalAvailability() {
            const axis = getAxis(document.getElementById('lineId').value);
            const date = document.getElementById('reservationDate').value;
            const start = document.getElementById('startTime').value;
            const end = document.getElementById('endTime').value;
            const message = document.getElementById('modalAvailability');
            const positions = document.getElementById('positionsCount');
            const submit = document.getElementById('submitReservation');
            const dynamicShooting = document.getElementById('dynamicShooting');

            positions.innerHTML = '';

            if (!axis || !start || !end) {
                message.classList.add('is-unavailable');
                message.textContent = 'Wybierz poprawny termin.';
                submit.disabled = true;
                return;
            }

            const free = getMinimumFree(axis, date, start, end);

            if (dynamicShooting.checked) {
                if (free === Number(axis.positions)) {
                    addOption(positions, axis.positions);
                }
            } else {
                for (let number = 1; number <= free; number++) {
                    addOption(positions, number);
                }
            }

            const isAvailable = dynamicShooting.checked
                ? free === Number(axis.positions)
                : free > 0;

            message.classList.toggle('is-unavailable', !isAvailable);
            message.innerHTML = free > 0
                ? `<strong>${free}</strong> stanowisk dostępnych przez cały wybrany czas.`
                : 'Brak wolnych stanowisk w wybranym czasie.';
            if (dynamicShooting.checked) {
                message.textContent = isAvailable
                    ? `Cała oś (${axis.positions} stanowisk) jest dostępna.`
                    : 'Strzelanie dynamiczne wymaga dostępności całej osi.';
            }

            submit.disabled = !isAvailable;
        }

        function openModal(axis, date, start) {
            const availability = getAvailability(parseDate(date));
            const end = minutesToTime(Math.min(timeToMinutes(start) + 60, timeToMinutes(availability.closesAt)));

            document.getElementById('modalHeading').textContent = `${axis.name} · ${date}`;
            document.getElementById('lineId').value = axis.id;
            document.getElementById('reservationDate').value = date;
            const dynamicField = document.getElementById('dynamicShootingField');
            const dynamicShooting = document.getElementById('dynamicShooting');
            dynamicShooting.checked = false;
            dynamicField.classList.toggle(
                'd-none',
                !axis.allows_dynamic_shooting
            );
            updateStartTimes(start, end);
            updateModalAvailability();
            bootstrap.Modal.getOrCreateInstance(document.getElementById('reservationModal')).show();
        }

        document.addEventListener('DOMContentLoaded', () => {
            if (!bookingConfig.canViewCalendar) {
                return;
            }

            renderDay();

            document.getElementById('previousDay').addEventListener('click', async () => {
                if (formatDate(currentDate) <= bookingConfig.minDate) {
                    return;
                }

                const date = new Date(currentDate);
                date.setDate(date.getDate() - 1);
                await selectCalendarDate(date);
            });

            document.getElementById('nextDay').addEventListener('click', async () => {
                if (formatDate(currentDate) >= bookingConfig.maxDate) {
                    return;
                }

                const date = new Date(currentDate);
                date.setDate(date.getDate() + 1);
                await selectCalendarDate(date);
            });

            document.getElementById('selectedDate').addEventListener('change', async event => {
                await selectCalendarDate(parseDate(event.target.value));
            });

            document.getElementById('lineId').addEventListener('change', () => {
                const axis = getAxis(document.getElementById('lineId').value);
                const dynamicShooting = document.getElementById('dynamicShooting');
                dynamicShooting.checked = false;
                document.getElementById('dynamicShootingField')
                    .classList.toggle(
                        'd-none',
                        !axis?.allows_dynamic_shooting
                    );
                updateModalAvailability();
            });
            document.getElementById('startTime').addEventListener('change', () => {
                updateEndTimes();
                updateModalAvailability();
            });
            document.getElementById('endTime').addEventListener('change', updateModalAvailability);
            document.getElementById('dynamicShooting')
                .addEventListener('change', updateModalAvailability);
        });
    </script>
</x-app-layout>
