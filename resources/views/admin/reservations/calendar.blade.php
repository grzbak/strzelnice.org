<x-app-layout>
    <div class="page-bg-2"></div>

    @include('components.breadcrumb')

    <main class="container py-4 admin-reservations">
        <section class="calendar-header">
            <div>
                <span class="calendar-eyebrow">Panel strzelnicy</span>
                <h1>Rezerwacje</h1>
                <p>{{ $shootingRange->name }}</p>
            </div>

            <div class="calendar-navigation">
                <div class="btn-group" role="group" aria-label="Nawigacja po kalendarzu">
                    <button type="button" id="previousPeriod" class="btn btn-light" title="Poprzedni okres">
                        <i class="bi bi-chevron-left"></i>
                    </button>
                    <button type="button" id="todayButton" class="btn btn-light">Dzisiaj</button>
                    <button type="button" id="nextPeriod" class="btn btn-light" title="Następny okres">
                        <i class="bi bi-chevron-right"></i>
                    </button>
                </div>

                <input type="date" id="calendarDate" class="form-control" value="{{ $selectedDate }}">

                <div class="view-switcher" role="group" aria-label="Widok kalendarza">
                    <button type="button" data-view="day" class="view-button">Dzień</button>
                    <button type="button" data-view="week" class="view-button">Tydzień</button>
                    <button type="button" data-view="month" class="view-button">Miesiąc</button>
                </div>
            </div>
        </section>

        <section class="calendar-summary">
            <div>
                <span>Rezerwacje</span>
                <strong id="summaryReservations">0</strong>
            </div>
            <div>
                <span>Stanowiska</span>
                <strong id="summaryPositions">0</strong>
            </div>
            <div>
                <span>Oczekujące</span>
                <strong id="summaryPending">0</strong>
            </div>

            <div class="summary-filters">
                <select id="laneFilter" class="form-select form-select-sm" aria-label="Filtr osi">
                    <option value="">Wszystkie osie</option>
                    @foreach ($shootingRange->lanes as $lane)
                        <option value="{{ $lane->name }}">{{ $lane->name }}</option>
                    @endforeach
                </select>

                <select id="statusFilter" class="form-select form-select-sm" aria-label="Filtr statusu">
                    <option value="">Wszystkie statusy</option>
                    <option value="pending">Oczekujące</option>
                    <option value="approved">Zatwierdzone</option>
                    <option value="cancelled">Anulowane</option>
                </select>

                <input
                    type="search"
                    id="clientFilter"
                    class="form-control form-control-sm"
                    placeholder="Szukaj klienta"
                >
            </div>
        </section>

        <section id="calendarTitle" class="period-title"></section>
        <section id="calendarContent"></section>
    </main>

    <style>
        .admin-reservations {
            --border: #e3e7e5;
            --muted: #68736e;
            --green: #198754;
            --green-soft: #eaf7f0;
            --yellow: #9a7100;
            --yellow-soft: #fff7d6;
            --red: #b42318;
            --red-soft: #fff0ee;
        }

        .calendar-header,
        .calendar-summary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem 1.15rem;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 8px;
        }

        .calendar-header h1 {
            margin: .1rem 0;
            font-size: 1.75rem;
            font-weight: 700;
        }

        .calendar-header p,
        .calendar-summary span {
            margin: 0;
            color: var(--muted);
            font-size: .78rem;
        }

        .calendar-eyebrow {
            color: var(--green);
            font-size: .7rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .calendar-navigation,
        .summary-filters {
            display: flex;
            align-items: center;
            gap: .5rem;
            flex-wrap: wrap;
        }

        .calendar-navigation .form-control {
            width: auto;
        }

        .view-switcher {
            display: inline-flex;
            padding: 3px;
            background: #f1f3f2;
            border-radius: 6px;
        }

        .view-button {
            padding: .4rem .75rem;
            color: var(--muted);
            font-size: .8rem;
            background: transparent;
            border: 0;
            border-radius: 4px;
        }

        .view-button.active {
            color: #26332d;
            font-weight: 700;
            background: #fff;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .12);
        }

        .calendar-summary {
            justify-content: flex-start;
            margin-top: .7rem;
        }

        .calendar-summary > div:not(.summary-filters) {
            min-width: 85px;
        }

        .calendar-summary strong {
            display: block;
            font-size: 1.15rem;
        }

        .summary-filters {
            margin-left: auto;
        }

        .summary-filters .form-select,
        .summary-filters .form-control {
            width: auto;
            min-width: 145px;
        }

        .period-title {
            padding: 1rem .15rem .65rem;
            font-size: 1.1rem;
            font-weight: 700;
        }

        .empty-state {
            padding: 3rem 1rem;
            color: var(--muted);
            text-align: center;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 8px;
        }

        .day-timeline {
            display: grid;
            gap: 0;
            overflow: hidden;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 8px;
        }

        .hour-row {
            display: grid;
            grid-template-columns: 80px minmax(0, 1fr);
            min-height: 72px;
            border-bottom: 1px solid var(--border);
        }

        .hour-row:last-child {
            border-bottom: 0;
        }

        .hour-label {
            padding: .8rem;
            color: var(--muted);
            font-size: .75rem;
            font-weight: 700;
            text-align: center;
            background: #f8f9fa;
            border-right: 1px solid var(--border);
        }

        .hour-bookings {
            display: grid;
            gap: .45rem;
            padding: .45rem;
        }

        .hour-empty {
            align-self: center;
            color: #a1aaa6;
            font-size: .76rem;
        }

        .reservation-card {
            display: grid;
            grid-template-columns: minmax(140px, 1fr) minmax(130px, .7fr) auto;
            align-items: center;
            gap: .75rem;
            padding: .65rem .75rem;
            border-left: 4px solid;
            border-radius: 5px;
        }

        .reservation-card.pending {
            color: var(--yellow);
            background: var(--yellow-soft);
            border-color: #ffc107;
        }

        .reservation-card.approved {
            color: #11643d;
            background: var(--green-soft);
            border-color: var(--green);
        }

        .reservation-card.cancelled {
            color: var(--red);
            background: var(--red-soft);
            border-color: #dc3545;
        }

        .reservation-card strong,
        .reservation-card span {
            display: block;
        }

        .reservation-card span {
            margin-top: .1rem;
            font-size: .72rem;
        }

        .reservation-status {
            padding: .25rem .45rem;
            font-size: .65rem;
            font-weight: 700;
            text-transform: uppercase;
            border: 1px solid currentColor;
            border-radius: 4px;
        }

        .week-grid,
        .month-grid {
            display: grid;
            gap: .5rem;
        }

        .week-grid {
            grid-template-columns: repeat(7, minmax(0, 1fr));
        }

        .month-grid {
            grid-template-columns: repeat(7, minmax(0, 1fr));
        }

        .weekday-label {
            padding: .35rem;
            color: var(--muted);
            font-size: .7rem;
            font-weight: 700;
            text-align: center;
            text-transform: uppercase;
        }

        .day-tile {
            min-height: 120px;
            padding: .7rem;
            text-align: left;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 7px;
            transition: border-color .15s ease, box-shadow .15s ease;
        }

        .day-tile:hover {
            border-color: #aab3af;
            box-shadow: 0 3px 12px rgba(0, 0, 0, .08);
        }

        .day-tile.outside-month {
            opacity: .45;
        }

        .day-tile.today {
            border-color: var(--green);
        }

        .day-tile strong,
        .day-tile span {
            display: block;
        }

        .day-tile .day-number {
            margin-bottom: .65rem;
            font-size: .95rem;
        }

        .day-tile span {
            margin-top: .18rem;
            color: var(--muted);
            font-size: .7rem;
        }

        .status-bar {
            display: flex;
            height: 5px;
            margin-top: .8rem;
            overflow: hidden;
            background: #edf0ef;
            border-radius: 4px;
        }

        .status-bar i {
            height: 100%;
        }

        .status-bar .approved {
            background: var(--green);
        }

        .status-bar .pending {
            background: #ffc107;
        }

        .status-bar .cancelled {
            background: #dc3545;
        }

        @media (max-width: 991.98px) {
            .calendar-header,
            .calendar-summary {
                align-items: stretch;
                flex-direction: column;
            }

            .summary-filters {
                margin-left: 0;
            }

            .week-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .month-grid {
                grid-template-columns: repeat(7, minmax(90px, 1fr));
                overflow-x: auto;
            }

            .reservation-card {
                grid-template-columns: 1fr auto;
            }

            .reservation-card .reservation-lane {
                grid-column: 1 / -1;
            }
        }

        @media (max-width: 575.98px) {
            .calendar-navigation,
            .summary-filters {
                align-items: stretch;
                flex-direction: column;
            }

            .calendar-navigation .form-control,
            .summary-filters .form-select,
            .summary-filters .form-control {
                width: 100%;
            }

            .view-switcher {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
            }

            .week-grid {
                grid-template-columns: 1fr;
            }

            .hour-row {
                grid-template-columns: 58px minmax(0, 1fr);
            }

            .reservation-card {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <script>
        const adminCalendar = {
            route: @json(route('admin.reservations.calendar')),
            view: @json($viewMode),
            selectedDate: @json($selectedDate),
            periodStart: @json($periodStart),
            periodEnd: @json($periodEnd),
            reservations: @json($reservations),
            openingHours: @json($openingHours)
        };

        const statusLabels = {
            pending: 'Oczekująca',
            approved: 'Zatwierdzona',
            cancelled: 'Anulowana'
        };

        const weekdayLabels = ['Pon', 'Wt', 'Śr', 'Czw', 'Pt', 'Sob', 'Nd'];

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

        function readableDate(date, options = {}) {
            return new Intl.DateTimeFormat('pl-PL', options).format(date);
        }

        function openView(view, date) {
            const url = new URL(adminCalendar.route, window.location.origin);
            url.searchParams.set('view', view);
            url.searchParams.set('date', date);
            window.location.href = url.toString();
        }

        function filteredReservations() {
            const lane = document.getElementById('laneFilter').value;
            const status = document.getElementById('statusFilter').value;
            const client = document.getElementById('clientFilter').value.trim().toLowerCase();

            return adminCalendar.reservations.filter(reservation => {
                return (!lane || reservation.lane_name === lane)
                    && (!status || reservation.status === status)
                    && (!client || `${reservation.user_name} ${reservation.user_email ?? ''} ${reservation.user_phone ?? ''}`.toLowerCase().includes(client));
            });
        }

        function updateSummary(reservations) {
            document.getElementById('summaryReservations').textContent = reservations.length;
            document.getElementById('summaryPositions').textContent = reservations.reduce(
                (sum, reservation) => sum + Number(reservation.positions_count || 0),
                0
            );
            document.getElementById('summaryPending').textContent = reservations.filter(
                reservation => reservation.status === 'pending'
            ).length;
        }

        function reservationsForDate(reservations, date) {
            return reservations.filter(reservation => reservation.date === date);
        }

        function createReservationCard(reservation) {
            const card = document.createElement('article');
            card.className = `reservation-card ${reservation.status}`;
            card.innerHTML = `
                <div>
                    <strong>${reservation.start_time}–${reservation.end_time} · ${reservation.user_name}</strong>
                    <span>${reservation.user_phone ?? reservation.user_email ?? 'Brak danych kontaktowych'}</span>
                </div>
                <div class="reservation-lane">
                    <strong>${reservation.lane_name}</strong>
                    <span>${reservation.lane_length ? `${reservation.lane_length} m · ` : ''}${reservation.positions_count} stanowisk</span>
                </div>
                <span class="reservation-status">${statusLabels[reservation.status] ?? reservation.status}</span>
            `;
            return card;
        }

        function renderDay(reservations) {
            const content = document.getElementById('calendarContent');
            const selected = parseDate(adminCalendar.selectedDate);
            const dayReservations = reservationsForDate(reservations, adminCalendar.selectedDate);
            const isoDay = selected.getDay() === 0 ? 7 : selected.getDay();
            const hours = adminCalendar.openingHours[isoDay] ?? { opens_at: '08:00', closes_at: '22:00' };
            const startHour = Number(hours.opens_at.split(':')[0]);
            const endHour = Number(hours.closes_at.split(':')[0]) + (hours.closes_at.split(':')[1] !== '00' ? 1 : 0);

            document.getElementById('calendarTitle').textContent = readableDate(selected, {
                weekday: 'long',
                day: 'numeric',
                month: 'long',
                year: 'numeric'
            });

            const timeline = document.createElement('div');
            timeline.className = 'day-timeline';

            for (let hour = startHour; hour < endHour; hour++) {
                const row = document.createElement('div');
                row.className = 'hour-row';
                row.innerHTML = `<div class="hour-label">${String(hour).padStart(2, '0')}:00</div>`;

                const bookings = document.createElement('div');
                bookings.className = 'hour-bookings';
                const hourReservations = dayReservations.filter(reservation => Number(reservation.start_time.slice(0, 2)) === hour);

                if (!hourReservations.length) {
                    bookings.innerHTML = '<span class="hour-empty">Brak rezerwacji rozpoczynających się o tej godzinie</span>';
                } else {
                    hourReservations.forEach(reservation => bookings.appendChild(createReservationCard(reservation)));
                }

                row.appendChild(bookings);
                timeline.appendChild(row);
            }

            content.appendChild(timeline);
        }

        function statusBar(reservations) {
            if (!reservations.length) {
                return '<div class="status-bar"></div>';
            }

            return `
                <div class="status-bar">
                    ${['approved', 'pending', 'cancelled'].map(status => {
                        const count = reservations.filter(item => item.status === status).length;
                        return `<i class="${status}" style="width:${(count / reservations.length) * 100}%"></i>`;
                    }).join('')}
                </div>
            `;
        }

        function createDayTile(date, reservations, outsideMonth = false) {
            const dateString = formatDate(date);
            const dayReservations = reservationsForDate(reservations, dateString);
            const positions = dayReservations.reduce((sum, item) => sum + Number(item.positions_count || 0), 0);
            const tile = document.createElement('button');
            tile.type = 'button';
            tile.className = 'day-tile';

            if (outsideMonth) tile.classList.add('outside-month');
            if (dateString === formatDate(new Date())) tile.classList.add('today');

            tile.innerHTML = `
                <strong class="day-number">${date.getDate()}</strong>
                <span>${dayReservations.length} rezerwacji</span>
                <span>${positions} stanowisk</span>
                ${statusBar(dayReservations)}
            `;
            tile.addEventListener('click', () => openView('day', dateString));
            return tile;
        }

        function renderWeek(reservations) {
            const content = document.getElementById('calendarContent');
            const start = parseDate(adminCalendar.periodStart);
            const end = parseDate(adminCalendar.periodEnd);
            document.getElementById('calendarTitle').textContent = `${readableDate(start, { day: 'numeric', month: 'long' })} – ${readableDate(end, { day: 'numeric', month: 'long', year: 'numeric' })}`;

            const grid = document.createElement('div');
            grid.className = 'week-grid';

            for (let index = 0; index < 7; index++) {
                const date = new Date(start);
                date.setDate(start.getDate() + index);
                const tile = createDayTile(date, reservations);
                tile.querySelector('.day-number').textContent = `${weekdayLabels[index]} ${date.getDate()}`;
                grid.appendChild(tile);
            }

            content.appendChild(grid);
        }

        function renderMonth(reservations) {
            const content = document.getElementById('calendarContent');
            const selected = parseDate(adminCalendar.selectedDate);
            const start = parseDate(adminCalendar.periodStart);
            const end = parseDate(adminCalendar.periodEnd);
            document.getElementById('calendarTitle').textContent = readableDate(selected, {
                month: 'long',
                year: 'numeric'
            });

            const grid = document.createElement('div');
            grid.className = 'month-grid';
            weekdayLabels.forEach(label => {
                const element = document.createElement('div');
                element.className = 'weekday-label';
                element.textContent = label;
                grid.appendChild(element);
            });

            const cursor = new Date(start);

            while (cursor <= end) {
                grid.appendChild(createDayTile(
                    new Date(cursor),
                    reservations,
                    cursor.getMonth() !== selected.getMonth()
                ));
                cursor.setDate(cursor.getDate() + 1);
            }

            content.appendChild(grid);
        }

        function renderCalendar() {
            const reservations = filteredReservations();
            const content = document.getElementById('calendarContent');
            content.innerHTML = '';
            updateSummary(reservations);

            if (adminCalendar.view === 'day') {
                renderDay(reservations);
            } else if (adminCalendar.view === 'week') {
                renderWeek(reservations);
            } else {
                renderMonth(reservations);
            }
        }

        function shiftedDate(direction) {
            const date = parseDate(adminCalendar.selectedDate);

            if (adminCalendar.view === 'month') {
                date.setMonth(date.getMonth() + direction);
            } else if (adminCalendar.view === 'week') {
                date.setDate(date.getDate() + (7 * direction));
            } else {
                date.setDate(date.getDate() + direction);
            }

            return formatDate(date);
        }

        document.addEventListener('DOMContentLoaded', () => {
            document.querySelector(`[data-view="${adminCalendar.view}"]`)?.classList.add('active');
            renderCalendar();

            document.getElementById('previousPeriod').addEventListener('click', () => openView(adminCalendar.view, shiftedDate(-1)));
            document.getElementById('nextPeriod').addEventListener('click', () => openView(adminCalendar.view, shiftedDate(1)));
            document.getElementById('todayButton').addEventListener('click', () => openView(adminCalendar.view, formatDate(new Date())));
            document.getElementById('calendarDate').addEventListener('change', event => openView(adminCalendar.view, event.target.value));

            document.querySelectorAll('.view-button').forEach(button => {
                button.addEventListener('click', () => openView(button.dataset.view, adminCalendar.selectedDate));
            });

            ['laneFilter', 'statusFilter'].forEach(id => {
                document.getElementById(id).addEventListener('change', renderCalendar);
            });
            document.getElementById('clientFilter').addEventListener('input', renderCalendar);
        });
    </script>
</x-app-layout>
