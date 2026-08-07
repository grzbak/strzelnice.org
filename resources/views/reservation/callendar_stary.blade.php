@php
    use Carbon\Carbon;

    $calendarDate = request('date', $calendarDate ?? now()->format('Y-m-d'));

    $calendarAxes = $shootingRange->lanes->map(function ($lane) {
        return [
            'id' => $lane->id,
            'name' => $lane->name,
            'info' => $lane->length . ' m',
            'positions' => $lane->numberOfPositions,
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
            'description' => $holiday->description,
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

    @if (session('success'))
        <div class="container mt-3">
            <div class="alert alert-success">{{ session('success') }}</div>
        </div>
    @endif

    @if (session('error'))
        <div class="container mt-3">
            <div class="alert alert-danger">{{ session('error') }}</div>
        </div>
    @endif


    <div class="container px-4 mx-auto my-6">
        <div class="flex items-center justify-between gap-4 p-4 bg-white border border-b-0 rounded-t-lg shadow-sm">
            <div class="flex items-center gap-4">
                <input
                    type="date"
                    id="datepicker"
                    value="{{ $calendarDate }}"
                    class="border-gray-300 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500"
                >

                <div class="flex gap-1">
                    <button id="prevBtn" class="px-4 py-2 text-sm font-bold bg-gray-100 border border-gray-300 rounded hover:bg-gray-200" type="button">
                        <
                    </button>

                    <button id="nextBtn" class="px-4 py-2 text-sm font-bold bg-gray-100 border border-gray-300 rounded hover:bg-gray-200" type="button">
                        >
                    </button>
                </div>
            </div>

            <div class="flex gap-1 p-1 bg-gray-200 rounded-md">
                <button onclick="changeCalendarView('day')" id="btn-view-day" class="view-btn px-4 py-1.5 rounded text-sm font-medium transition-all" type="button">
                    Dzień
                </button>

                <button onclick="changeCalendarView('week')" id="btn-view-week" class="view-btn px-4 py-1.5 rounded text-sm font-medium transition-all active bg-white shadow-sm" type="button">
                    Tydzień (3 dni)
                </button>
            </div>
        </div>

        <div class="overflow-hidden bg-white border rounded-b-lg shadow-sm">
            <div id="calendar-app" class="bg-[#f4f4f4] overflow-x-auto min-h-[500px]"></div>
        </div>
    </div>

    <div class="modal fade" id="reservationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('rezerwacje.store') }}" class="modal-content">
                @csrf

                <input type="hidden" name="shooting_range_id" value="{{ $shootingRange->id }}">

                <div class="modal-header">
                    <h5 class="modal-title">Nowa rezerwacja</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label for="reservation_line_id" class="form-label">Oś</label>
                        <select name="line_id" id="reservation_line_id" class="form-select" required>
                            @foreach ($shootingRange->lanes as $lane)
                                <option
                                    value="{{ $lane->id }}"
                                    data-positions="{{ $lane->numberOfPositions }}"
                                >
                                    {{ $lane->name }} / {{ $lane->length }} m
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="reservation_positions_count" class="form-label">Liczba stanowisk</label>
                        <select name="positions_count" id="reservation_positions_count" class="form-select" required></select>
                    </div>

                    <div class="mb-3">
                        <label for="reservation_date" class="form-label">Data</label>
                        <input type="date" name="date" id="reservation_date" class="form-control" min="2000-01-01" max="3000-12-31" required>
                    </div>

                    <div class="mb-3">
                        <label for="reservation_start_time" class="form-label">Godzina rozpoczęcia</label>
                        <input type="time" name="start_time" id="reservation_start_time" class="form-control" step="1800" required>
                    </div>

                    <div class="mb-3">
                        <label for="reservation_end_time" class="form-label">Godzina zakończenia</label>
                        <input type="time" name="end_time" id="reservation_end_time" class="form-control" step="1800" required>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Anuluj</button>
                    <button type="submit" class="btn btn-success">Zarezerwuj</button>
                </div>
            </form>
        </div>
    </div>

    <style>
        .calendar-day-section {
            background: white;
            border-bottom: 3px solid #ddd;
        }

        .day-header {
            width: 100%;
            box-sizing: border-box;
            background: #e2eec7;
            padding: 10px 15px;
            font-weight: bold;
            font-size: 14px;
            color: #333;
            border-bottom: 1px solid #ccc;
            position: sticky;
            left: 0;
            z-index: 20;
        }

        .grid-row {
            display: flex;
            border-bottom: 1px solid #eee;
            min-height: 72px;
            position: relative;
        }

        .grid-row:hover {
            background-color: #fcfcfc;
        }

        .axis-label {
            width: 200px;
            min-width: 200px;
            padding: 5px 15px;
            border-right: 2px solid #ddd;
            font-size: 13px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: #fff;
            position: sticky;
            left: 0;
            z-index: 10;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.05);
        }

        .axis-label small {
            color: #777;
            font-size: 11px;
            margin-top: 2px;
        }

        .time-header-row {
            display: flex;
            background: #f8f9fa;
            border-bottom: 1px solid #ccc;
            height: 40px;
        }

        .time-header-cell {
            width: 60px;
            min-width: 60px;
            border-right: 1px solid #ddd;
            font-size: 11px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #555;
        }

        .time-slots-container {
            display: flex;
            flex-grow: 1;
            position: relative;
            background-image: linear-gradient(to right, #f0f0f0 1px, transparent 1px);
            background-size: 60px 100%;
        }

        .booking-slot {
            position: absolute;
            height: 28px;
            top: 8px;
            border-radius: 4px;
            font-size: 11px;
            padding: 3px 8px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            z-index: 5;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(0, 0, 0, 0.2);
            line-height: 1.1;
        }

        .status-pending {
            background: #fff7ed !important;
            border-left: 4px solid #f59e0b !important;
            color: #92400e;
        }

        .status-approved {
            background: #ecfdf5 !important;
            border-left: 4px solid #10b981 !important;
            color: #065f46;
        }

        .status-cancelled {
            background: #fee2e2 !important;
            border-left: 4px solid #ef4444 !important;
            color: #991b1b;
        }

        .view-btn.active {
            background-color: white !important;
            font-weight: bold;
            color: #059669;
        }
    </style>

    <script>
        const config = {
            axes: @json($calendarAxes),
            openingHours: @json($openingHours),
            holidays: @json($holidays),
            reservations: @json($reservations ?? []),
            activeIntervals: @json($activeIntervals),
            defaultStartHour: 8,
            defaultEndHour: 22,
            slotWidth: 60
        };
console.log(config.reservations);
        let state = {
            currentDate: createLocalDate(@json($calendarDate)),
            viewMode: 'week'
        };

        function createLocalDate(dateString) {
            const [year, month, day] = dateString.split('-').map(Number);
            return new Date(year, month - 1, day);
        }

        function formatDateForInput(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');

            return `${year}-${month}-${day}`;
        }

        function jsDayToIsoDay(date) {
            const day = date.getDay();
            return day === 0 ? 7 : day;
        }

        function getHolidayForDate(date) {
            const dateString = formatDateForInput(date);
            return config.holidays.find(holiday => holiday.date === dateString) ?? null;
        }

        function getOpeningHoursForDate(date) {
            const isoDay = jsDayToIsoDay(date);
            return config.openingHours[isoDay] ?? null;
        }

        function getDayAvailability(date) {
             if (!hasValidActiveIntervalForDate(date)) {
                return {
                    isOpen: false,
                    reason: 'Brak aktywnego pakietu. Rezerwacja niedostępna.',
                    opensAt: null,
                    closesAt: null
                };
            }
            const holiday = getHolidayForDate(date);

            if (holiday) {
                return {
                    isOpen: false,
                    reason: holiday.name || 'Dzień wolny',
                    opensAt: null,
                    closesAt: null
                };
            }

            const openingHours = getOpeningHoursForDate(date);

            if (!openingHours) {
                return {
                    isOpen: false,
                    reason: 'Strzelnica nieczynna',
                    opensAt: null,
                    closesAt: null
                };
            }

            return {
                isOpen: true,
                reason: 'Strzelnica czynna',
                opensAt: openingHours.opens_at,
                closesAt: openingHours.closes_at
            };
        }

        function timeToHour(value, fallback) {
            if (!value) {
                return fallback;
            }

            const [hour] = value.split(':').map(Number);
            return hour;
        }

        function closingTimeToEndHour(value, fallback) {
            if (!value) {
                return fallback;
            }

            const [hour, minute] = value.split(':').map(Number);
            return minute > 0 ? hour + 1 : hour;
        }

        function minutesToTime(totalMinutes) {
            const hours = Math.floor(totalMinutes / 60);
            const minutes = totalMinutes % 60;

            return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`;
        }

        function timeToMinutes(time) {
            const [hour, minute] = time.split(':').map(Number);
            return hour * 60 + minute;
        }

        function reservationsOverlap(a, b) {
            return timeToMinutes(a.start_time) < timeToMinutes(b.end_time)
                && timeToMinutes(a.end_time) > timeToMinutes(b.start_time);
        }

        function getReservationRowIndex(reservation, reservations) {
            const previousOverlapping = reservations.filter(item => {
                return Number(item.id) !== Number(reservation.id)
                    && reservationsOverlap(reservation, item)
                    && (
                        timeToMinutes(item.start_time) < timeToMinutes(reservation.start_time)
                        || (
                            timeToMinutes(item.start_time) === timeToMinutes(reservation.start_time)
                            && Number(item.id) < Number(reservation.id)
                        )
                    );
            });

            return previousOverlapping.length;
        }

        function getReservationsForAxisAndDate(axisId, date) {
            const dateString = formatDateForInput(date);

            return config.reservations.filter(reservation => {
                return Number(reservation.line_id) === Number(axisId)
                    && reservation.start_date === dateString;
            });
        }

        function renderCalendar() {
            const container = document.getElementById('calendar-app');

            if (!container) {
                return;
            }

            container.innerHTML = '';

            const daysToRender = state.viewMode === 'day' ? 1 : 3;

            for (let i = 0; i < daysToRender; i++) {
                const date = new Date(state.currentDate);
                date.setDate(state.currentDate.getDate() + i);
                container.appendChild(createDaySection(date));
            }

            document.getElementById('datepicker').value = formatDateForInput(state.currentDate);
        }

        function createDaySection(date) {
            const section = document.createElement('div');
            section.className = 'calendar-day-section';

            const availability = getDayAvailability(date);
            const startHour = availability.isOpen
                ? timeToHour(availability.opensAt, config.defaultStartHour)
                : config.defaultStartHour;

            const endHour = availability.isOpen
                ? closingTimeToEndHour(availability.closesAt, config.defaultEndHour)
                : config.defaultEndHour;

            const totalWidth = 200 + ((endHour - startHour) * 2 * config.slotWidth);
            section.style.width = `${totalWidth}px`;
            section.style.minWidth = `${totalWidth}px`;

            const daysNames = ['Niedziela', 'Poniedziałek', 'Wtorek', 'Środa', 'Czwartek', 'Piątek', 'Sobota'];
            const dateStr = `${daysNames[date.getDay()]} ${String(date.getDate()).padStart(2, '0')}/${String(date.getMonth() + 1).padStart(2, '0')}`;

            section.innerHTML = `
                <div class="day-header">
                    ${dateStr.toUpperCase()}
                    <span style="margin-left: 12px; font-weight: normal;">
                        ${availability.isOpen
                            ? `czynna ${availability.opensAt} - ${availability.closesAt}`
                            : `nieczynna: ${availability.reason}`}
                    </span>
                </div>
            `;

            const timeHeader = document.createElement('div');
            timeHeader.className = 'time-header-row';
            timeHeader.innerHTML = `<div style="width: 200px; min-width: 200px; background: #e2eec7; border-right: 2px solid #ddd;"></div>`;

            for (let h = startHour; h < endHour; h++) {
                timeHeader.innerHTML += `
                    <div class="time-header-cell">${h}:00</div>
                    <div class="time-header-cell">${h}:30</div>
                `;
            }

            section.appendChild(timeHeader);

            if (!availability.isOpen) {
                const closedRow = document.createElement('div');
                closedRow.className = 'grid-row';
                closedRow.style.height = '90px';
                closedRow.innerHTML = `
                    <div class="axis-label">
                        <b>Nieczynne</b>
                        <small>${availability.reason}</small>
                    </div>
                    <div class="px-4 time-slots-container d-flex align-items-center text-muted">
                        Brak możliwości rezerwacji w tym dniu.
                    </div>
                `;

                section.appendChild(closedRow);
                return section;
            }

            config.axes.forEach(axis => {
                const row = document.createElement('div');
                row.className = 'grid-row';

                row.innerHTML = `
                    <div class="axis-label">
                        <b>${axis.name}</b>
                        <small>${axis.info} / stanowiska: ${axis.positions}</small>
                    </div>
                `;

                const slotsContainer = document.createElement('div');
                slotsContainer.className = 'time-slots-container';

                for (let j = 0; j < (endHour - startHour) * 2; j++) {
                    const line = document.createElement('div');
                    line.style.minWidth = `${config.slotWidth}px`;
                    line.style.borderRight = '1px solid #eee';
                    line.style.cursor = 'pointer';

                    const slotMinutes = (startHour * 60) + (j * 30);
                    const slotTime = minutesToTime(slotMinutes);

                    line.title = `Rezerwuj ${slotTime}`;
                    line.addEventListener('click', () => {
                        openReservationModal(axis, date, slotTime);
                    });

                    slotsContainer.appendChild(line);
                }

                const reservations = getReservationsForAxisAndDate(axis.id, date).sort((a, b) => {
                    const startCompare = timeToMinutes(a.start_time) - timeToMinutes(b.start_time);

                    if (startCompare !== 0) {
                        return startCompare;
                    }

                    return Number(a.id) - Number(b.id);
                });

                const maxReservationRowIndex = reservations.reduce((max, reservation) => {
                    return Math.max(max, getReservationRowIndex(reservation, reservations));
                }, 0);

                row.style.height = `${Math.max(72, 72 + (maxReservationRowIndex * 32))}px`;

                reservations.forEach(reservation => {
                    const rowIndex = getReservationRowIndex(reservation, reservations);

                    addBooking(
                        slotsContainer,
                        `${reservation.title} #${reservation.id}`,
                        reservation.start_time,
                        reservation.end_time,
                        `status-${reservation.status}`,
                        startHour,
                        reservation.positions_count,
                        rowIndex
                    );
                });

                row.appendChild(slotsContainer);
                section.appendChild(row);
            });

            return section;
        }

        function addBooking(container, title, start, end, className, startHour, positionsCount = 1, rowIndex = 0) {
            const startMinutes = timeToMinutes(start);
            const endMinutes = timeToMinutes(end);

            const startPos = ((startMinutes - (startHour * 60)) / 30) * config.slotWidth;
            const width = ((endMinutes - startMinutes) / 30) * config.slotWidth;

            const booking = document.createElement('div');
            booking.className = `booking-slot ${className}`;
            booking.style.left = `${startPos}px`;
            booking.style.top = `${8 + (rowIndex * 32)}px`;
            booking.style.width = `${Math.max(width - 4, 36)}px`;
            booking.innerHTML = `
                <strong>${title}</strong>
                <span style="font-size:9px">${start} - ${end} / miejsca: ${positionsCount}</span>
            `;

            container.appendChild(booking);
        }

        function updateReservationPositions() {
            const lineSelect = document.getElementById('reservation_line_id');
            const positionsSelect = document.getElementById('reservation_positions_count');
            const selectedOption = lineSelect.options[lineSelect.selectedIndex];
            const positions = Number(selectedOption?.dataset.positions ?? 1);

            positionsSelect.innerHTML = '';

            for (let i = 1; i <= positions; i++) {
                positionsSelect.innerHTML += `<option value="${i}">${i}</option>`;
            }
        }

        function openReservationModal(axis, date, startTime) {
            const startMinutes = timeToMinutes(startTime);
            const endTime = minutesToTime(startMinutes + 60);

            document.getElementById('reservation_line_id').value = axis.id;
            document.getElementById('reservation_date').value = formatDateForInput(date);
            document.getElementById('reservation_start_time').value = startTime;
            document.getElementById('reservation_end_time').value = endTime;

            updateReservationPositions();

            const modal = new bootstrap.Modal(document.getElementById('reservationModal'));
            modal.show();
        }

        window.changeCalendarView = function (mode) {
            state.viewMode = mode;

            document.querySelectorAll('.view-btn').forEach(button => {
                button.classList.remove('active', 'bg-white', 'shadow-sm');
            });

            document.getElementById(`btn-view-${mode}`).classList.add('active', 'bg-white', 'shadow-sm');

            renderCalendar();
        };

        document.addEventListener('DOMContentLoaded', function () {
            renderCalendar();
            updateReservationPositions();

            document.getElementById('datepicker').addEventListener('change', function (event) {
                state.currentDate = createLocalDate(event.target.value);
                renderCalendar();
            });

            document.getElementById('prevBtn').addEventListener('click', function () {
                state.currentDate.setDate(state.currentDate.getDate() - 1);
                renderCalendar();
            });

            document.getElementById('nextBtn').addEventListener('click', function () {
                state.currentDate.setDate(state.currentDate.getDate() + 1);
                renderCalendar();
            });

            document.getElementById('reservation_line_id').addEventListener('change', updateReservationPositions);
        });

        function hasValidActiveIntervalForDate(date) {
            const dateString = formatDateForInput(date);

            return config.activeIntervals.some(interval => {
                return interval.start_date <= dateString
                    && interval.end_date >= dateString;
            });
        }
    </script>
</x-app-layout>
