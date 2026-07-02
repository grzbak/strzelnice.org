<x-app-layout>
    <div class="page-bg-2"></div>

    @include('components.breadcrumb')

    <main class="container py-4 admin-reservations">
        <x-session-alerts />

        @if (! $shootingRange->is_visible)
            <div class="alert alert-warning d-flex align-items-start gap-2" role="alert">
                <i class="bi bi-eye-slash-fill fs-5" aria-hidden="true"></i>
                <div>
                    <strong>Strzelnica jest ukryta.</strong>
                    Kalendarz pozostaje dostępny do podglądu, ale dodawanie i edycja
                    rezerwacji są zablokowane do czasu ponownego włączenia widoczności.
                </div>
            </div>
        @endif

        <section class="calendar-header">
            <div>
                <span class="calendar-eyebrow">Panel strzelnicy</span>
                <h1>Rezerwacje</h1>
                <p>{{ $shootingRange->name }}</p>
                <div id="calendarTitle" class="calendar-period-title"></div>
            </div>

            <div class="calendar-navigation">
                @if (! auth()->user()->isSuperAdmin() && $managedRanges->count() > 1)
                    <form method="GET" action="{{ route('admin.reservations.calendar') }}">
                        <input type="hidden" name="view" value="{{ $viewMode }}">
                        <input type="hidden" name="date" value="{{ $selectedDate }}">
                        <select
                            name="shooting_range_id"
                            class="form-select"
                            onchange="this.form.submit()"
                            aria-label="Wybierz strzelnicę"
                        >
                            @foreach ($managedRanges as $range)
                                <option
                                    value="{{ $range->id }}"
                                    @selected($range->id === $shootingRange->id)
                                >
                                    {{ $range->name }}
                                    @if (! $range->is_visible)
                                        (ukryta)
                                    @endif
                                </option>
                            @endforeach
                        </select>
                    </form>
                @endif

                <button
                    type="button"
                    id="newReservationButton"
                    class="btn btn-success"
                    data-bs-toggle="modal"
                    data-bs-target="#staffReservationModal"
                    @disabled(! $shootingRange->is_visible)
                    @if (! $shootingRange->is_visible)
                        title="Rezerwacje są zablokowane dla ukrytej strzelnicy"
                    @endif
                >
                    <i class="bi bi-calendar-plus me-1"></i>
                    Dodaj rezerwację
                </button>

                <a
                    href="{{ route('admin.reservations.archive', ['shooting_range_id' => $shootingRange->id]) }}"
                    class="btn btn-outline-secondary"
                >
                    <i class="bi bi-archive me-1"></i>
                    Archiwum
                </a>

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
                    <option value="completed">Zrealizowane</option>
                    <option value="no_show">Niezrealizowane</option>
                </select>

                <input
                    type="search"
                    id="clientFilter"
                    class="form-control form-control-sm"
                    placeholder="Szukaj klienta"
                >
            </div>
        </section>

        <section id="calendarContent"></section>
    </main>

    <div class="modal fade" id="staffReservationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <form
                method="POST"
                action="{{ route('admin.reservations.store') }}"
                class="modal-content"
                id="staffReservationForm"
            >
                @csrf
                <input
                    type="hidden"
                    name="_method"
                    value="PATCH"
                    id="staffReservationMethod"
                    disabled
                >
                <input type="hidden" name="shooting_range_id" value="{{ $shootingRange->id }}">

                <div class="modal-header">
                    <div>
                        <span class="calendar-eyebrow">Panel strzelnicy</span>
                        <h2 class="modal-title fs-5" id="staffReservationTitle">Nowa rezerwacja</h2>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="staffCustomerType" class="form-label">Rodzaj klienta</label>
                            <select name="customer_type" id="staffCustomerType" class="form-select" required>
                                <option value="account">Użytkownik z kontem</option>
                                <option value="guest">Gość bez konta</option>
                            </select>
                            <input
                                type="hidden"
                                name="customer_user_id"
                                id="staffCustomerUserId"
                            >
                        </div>

                        <div class="col-md-6">
                            <label for="staffCustomerName" class="form-label">Imię i nazwisko</label>
                            <input
                                type="text"
                                name="customer_name"
                                id="staffCustomerName"
                                class="form-control"
                                maxlength="255"
                            >
                            <div class="form-text account-search-help">
                                Wpisz co najmniej 2 znaki, aby wyszukać konto.
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label for="staffCustomerEmail" class="form-label">E-mail</label>
                            <input
                                type="email"
                                name="customer_email"
                                id="staffCustomerEmail"
                                class="form-control"
                            >
                        </div>

                        <div class="col-md-6">
                            <label for="staffCustomerPhone" class="form-label">Telefon</label>
                            <input
                                type="tel"
                                name="customer_phone"
                                id="staffCustomerPhone"
                                class="form-control"
                                maxlength="50"
                            >
                        </div>

                        <div class="col-12 d-none" id="customerSuggestions">
                            <div class="list-group"></div>
                        </div>

                        <div class="col-md-4">
                            <label for="staffReservationDate" class="form-label">Data</label>
                            <input
                                type="date"
                                name="date"
                                id="staffReservationDate"
                                class="form-control"
                                value="{{ $selectedDate }}"
                                min="{{ today()->format('Y-m-d') }}"
                                max="{{ $maxBookingDate->format('Y-m-d') }}"
                                required
                            >
                        </div>

                        <div class="col-md-4">
                            <label for="staffStartTime" class="form-label">Od</label>
                            <select
                                name="start_time"
                                id="staffStartTime"
                                class="form-select"
                                required
                            ></select>
                        </div>

                        <div class="col-md-4">
                            <label for="staffEndTime" class="form-label">Do</label>
                            <select
                                name="end_time"
                                id="staffEndTime"
                                class="form-select"
                                required
                            ></select>
                        </div>

                        <div class="col-md-8">
                            <label for="staffLane" class="form-label">Oś</label>
                            <select name="line_id" id="staffLane" class="form-select" required>
                                @foreach ($shootingRange->lanes as $lane)
                                    <option
                                        value="{{ $lane->id }}"
                                        data-positions="{{ $lane->numberOfPositions }}"
                                        data-dynamic="{{ $lane->allows_dynamic_shooting ? '1' : '0' }}"
                                    >
                                        {{ $lane->name }} · {{ $lane->length }} m
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label for="staffPositions" class="form-label">Stanowiska</label>
                            <select
                                name="positions_count"
                                id="staffPositions"
                                class="form-select"
                                required
                            ></select>
                        </div>

                        <div class="col-12 d-none" id="staffDynamicField">
                            <input type="hidden" name="is_dynamic_shooting" value="0">
                            <div class="form-check">
                                <input
                                    type="checkbox"
                                    name="is_dynamic_shooting"
                                    id="staffDynamicShooting"
                                    class="form-check-input"
                                    value="1"
                                >
                                <label for="staffDynamicShooting" class="form-check-label">
                                    Strzelanie dynamiczne - rezerwacja całej osi
                                </label>
                            </div>
                        </div>

                        <div class="col-12">
                            <div id="staffAvailability" class="small text-muted" role="status"></div>
                        </div>

                        <div class="col-12">
                            <label for="staffNotes" class="form-label">Notatka wewnętrzna</label>
                            <textarea
                                name="notes"
                                id="staffNotes"
                                class="form-control"
                                rows="2"
                                maxlength="2000"
                            ></textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Anuluj</button>
                    <button type="submit" id="staffReservationSubmit" class="btn btn-success">
                        <i class="bi bi-check-lg me-1"></i>
                        <span id="staffReservationSubmitLabel">Zapisz rezerwację</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="reservationStatusModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <form method="POST" action="" class="modal-content" id="reservationStatusForm">
                @csrf
                @method('PATCH')

                <div class="modal-header">
                    <div>
                        <span class="detail-eyebrow">Szczegóły rezerwacji</span>
                        <h2 class="modal-title fs-5" id="reservationDetailTitle"></h2>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="reservation-detail-grid">
                        <div>
                            <span>Termin</span>
                            <strong id="reservationDetailDate"></strong>
                        </div>
                        <div>
                            <span>Oś i stanowiska</span>
                            <strong id="reservationDetailLane"></strong>
                        </div>
                        <div>
                            <span>E-mail</span>
                            <strong id="reservationDetailEmail"></strong>
                        </div>
                        <div>
                            <span>Telefon</span>
                            <strong id="reservationDetailPhone"></strong>
                        </div>
                        <div>
                            <span>Rodzaj klienta</span>
                            <strong id="reservationDetailSource"></strong>
                        </div>
                        <div>
                            <span>Utworzona</span>
                            <strong id="reservationDetailCreated"></strong>
                        </div>
                    </div>

                    <div class="reservation-detail-section d-none" id="reservationDetailNotesSection">
                        <span>Notatka wewnętrzna</span>
                        <p id="reservationDetailNotes"></p>
                    </div>

                    <div class="reservation-detail-section d-none" id="reservationDetailHistorySection">
                        <span>Historia statusu</span>
                        <p id="reservationDetailHistory"></p>
                    </div>

                    <hr class="my-4">

                    <label for="reservationStatus" class="form-label">Status</label>
                    <select name="status" id="reservationStatus" class="form-select" required>
                        <option value="pending">Oczekująca</option>
                        <option value="approved">Zatwierdzona</option>
                        <option value="cancelled">Anulowana</option>
                        <option value="completed">Zrealizowana</option>
                        <option value="no_show">Niezrealizowana</option>
                    </select>

                    <div class="mt-3 d-none" id="cancellationReasonField">
                        <label for="cancellationReason" class="form-label">Powód anulowania</label>
                        <textarea
                            name="cancellation_reason"
                            id="cancellationReason"
                            class="form-control"
                            rows="3"
                            maxlength="500"
                        ></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button
                        type="button"
                        class="btn btn-outline-primary me-auto d-none"
                        id="editReservationButton"
                    >
                        <i class="bi bi-pencil me-1"></i>
                        Edytuj rezerwację
                    </button>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Anuluj</button>
                    <button type="submit" class="btn btn-success">Zapisz status</button>
                </div>
            </form>
        </div>
    </div>

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

        .calendar-period-title {
            margin-top: .55rem;
            color: #26332d;
            font-size: 1.1rem;
            font-weight: 700;
        }

        #calendarContent {
            margin-top: .7rem;
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
            cursor: pointer;
            transition: box-shadow .15s ease, transform .15s ease;
        }

        .reservation-card:hover,
        .reservation-card:focus-visible {
            box-shadow: 0 4px 14px rgb(22 32 27 / 12%);
            transform: translateY(-1px);
        }

        .reservation-card:focus-visible {
            outline: 2px solid var(--green);
            outline-offset: 2px;
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

        .reservation-card.completed {
            color: #164e63;
            background: #ecfeff;
            border-color: #0891b2;
        }

        .reservation-card.no_show {
            color: #52525b;
            background: #f4f4f5;
            border-color: #71717a;
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
            background: transparent;
            border: 1px solid currentColor;
            border-radius: 4px;
        }

        .detail-eyebrow,
        .reservation-detail-grid span,
        .reservation-detail-section > span {
            display: block;
            color: var(--muted);
            font-size: .7rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .detail-eyebrow {
            margin-bottom: .15rem;
        }

        .reservation-detail-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1rem 1.5rem;
        }

        .reservation-detail-grid strong {
            display: block;
            margin-top: .2rem;
            overflow-wrap: anywhere;
        }

        .reservation-detail-section {
            margin-top: 1.25rem;
            padding: .8rem 1rem;
            background: #f7f9f8;
            border-left: 3px solid var(--border);
            border-radius: 4px;
        }

        .reservation-detail-section p {
            margin: .25rem 0 0;
            white-space: pre-line;
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

        .status-bar .completed {
            background: #0891b2;
        }

        .status-bar .no_show {
            background: #71717a;
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

            .reservation-detail-grid {
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
            openingHours: @json($openingHours),
            shootingRangeId: @json($shootingRange->id),
            customerSearchUrl: @json(route('admin.reservations.customers.search')),
            availabilityUrl: @json(route('rezerwacje.availability', $shootingRange->id)),
            statusRouteTemplate: @json(route('admin.reservations.status', ['reservation' => '__ID__'])),
            updateRouteTemplate: @json(route('admin.reservations.update', ['reservation' => '__ID__'])),
            storeRoute: @json(route('admin.reservations.store'))
        };

        const statusLabels = {
            pending: 'Oczekująca',
            approved: 'Zatwierdzona',
            cancelled: 'Anulowana',
            completed: 'Zrealizowana',
            no_show: 'Niezrealizowana'
        };
        const sourceLabels = {
            online_account: 'Użytkownik z kontem - rezerwacja online',
            staff_account: 'Użytkownik z kontem - rezerwacja personelu',
            staff_guest: 'Gość bez konta - rezerwacja personelu'
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
            url.searchParams.set('shooting_range_id', adminCalendar.shootingRangeId);
            window.location.href = url.toString();
        }

        function escapeHtml(value) {
            const element = document.createElement('div');
            element.textContent = value ?? '';
            return element.innerHTML;
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
            card.tabIndex = 0;
            card.setAttribute('role', 'button');
            card.setAttribute(
                'aria-label',
                `Pokaż szczegóły rezerwacji ${reservation.user_name}`
            );
            card.dataset.reservationId = reservation.id;
            card.dataset.bsToggle = 'modal';
            card.dataset.bsTarget = '#reservationStatusModal';
            card.innerHTML = `
                <div>
                    <strong>${escapeHtml(reservation.start_time)}–${escapeHtml(reservation.end_time)} · ${escapeHtml(reservation.user_name)}</strong>
                    <span>${escapeHtml(reservation.user_phone ?? reservation.user_email ?? 'Brak danych kontaktowych')}</span>
                </div>
                <div class="reservation-lane">
                    <strong>${escapeHtml(reservation.lane_name)}</strong>
                    <span>
                        ${reservation.lane_length ? `${escapeHtml(reservation.lane_length)} m · ` : ''}
                        ${escapeHtml(reservation.positions_count)} stanowisk
                        ${reservation.is_dynamic_shooting ? ' · dynamiczne' : ''}
                    </span>
                </div>
                <span class="reservation-status">
                    ${escapeHtml(statusLabels[reservation.status] ?? reservation.status)}
                </span>
            `;
            card.addEventListener('keydown', event => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    card.click();
                }
            });

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
                    ${['approved', 'pending', 'cancelled', 'completed', 'no_show'].map(status => {
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

            const customerType = document.getElementById('staffCustomerType');
            const customerName = document.getElementById('staffCustomerName');
            const customerEmail = document.getElementById('staffCustomerEmail');
            const customerPhone = document.getElementById('staffCustomerPhone');
            const customerUserId = document.getElementById('staffCustomerUserId');
            const customerSuggestions = document.getElementById('customerSuggestions');
            const accountSearchHelp = document.querySelector('.account-search-help');
            let customerSearchTimer = null;
            let fillingCustomer = false;

            function selectCustomer(user) {
                fillingCustomer = true;
                customerUserId.value = user.id;
                customerName.value = user.name ?? '';
                customerEmail.value = user.email ?? '';
                customerPhone.value = user.telephone ?? '';
                customerSuggestions.classList.add('d-none');
                fillingCustomer = false;
            }

            function renderCustomerSuggestions(users, searchedValue) {
                const list = customerSuggestions.querySelector('.list-group');
                list.innerHTML = '';

                if (!users.length) {
                    customerSuggestions.classList.add('d-none');
                    return;
                }

                const normalized = searchedValue.trim().toLowerCase();
                const exact = users.find(user => {
                    return [user.name, user.email, user.telephone]
                        .filter(Boolean)
                        .some(value => String(value).toLowerCase() === normalized);
                });

                if (exact || users.length === 1) {
                    selectCustomer(exact ?? users[0]);
                    return;
                }

                users.forEach(user => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'list-group-item list-group-item-action';
                    button.innerHTML = `
                        <strong>${escapeHtml(user.name)}</strong>
                        <span class="d-block small text-muted">
                            ${escapeHtml(user.email)}
                            ${user.telephone ? ` · ${escapeHtml(user.telephone)}` : ''}
                        </span>
                    `;
                    button.addEventListener('click', () => selectCustomer(user));
                    list.appendChild(button);
                });
                customerSuggestions.classList.remove('d-none');
            }

            async function searchCustomer(value) {
                const url = new URL(
                    adminCalendar.customerSearchUrl,
                    window.location.origin
                );
                url.searchParams.set(
                    'shooting_range_id',
                    adminCalendar.shootingRangeId
                );
                url.searchParams.set('q', value);

                const response = await fetch(url, {
                    headers: {
                        'Accept': 'application/json'
                    }
                });

                if (!response.ok) {
                    customerSuggestions.classList.add('d-none');
                    return;
                }

                const result = await response.json();
                renderCustomerSuggestions(result.users ?? [], value);
            }

            function handleCustomerInput(event) {
                if (fillingCustomer || customerType.value !== 'account') {
                    return;
                }

                customerUserId.value = '';
                customerSuggestions.classList.add('d-none');
                clearTimeout(customerSearchTimer);
                const value = event.target.value.trim();

                if (value.length < 2) {
                    return;
                }

                customerSearchTimer = setTimeout(
                    () => searchCustomer(value),
                    300
                );
            }

            function updateCustomerFields(clearValues = true) {
                const isGuest = customerType.value === 'guest';
                customerName.required = isGuest;

                if (clearValues) {
                    customerUserId.value = '';
                    customerName.value = '';
                    customerEmail.value = '';
                    customerPhone.value = '';
                }

                customerSuggestions.classList.add('d-none');
                accountSearchHelp.classList.toggle('d-none', isGuest);
            }

            [customerName, customerEmail, customerPhone].forEach(input => {
                input.addEventListener('input', handleCustomerInput);
            });
            customerType.addEventListener('change', () => updateCustomerFields());
            updateCustomerFields();

            const staffForm = document.getElementById('staffReservationForm');
            const staffMethod = document.getElementById('staffReservationMethod');
            const staffTitle = document.getElementById('staffReservationTitle');
            const staffSubmitLabel = document.getElementById('staffReservationSubmitLabel');
            const staffNotes = document.getElementById('staffNotes');
            const staffDate = document.getElementById('staffReservationDate');
            const staffStart = document.getElementById('staffStartTime');
            const staffEnd = document.getElementById('staffEndTime');
            const staffLane = document.getElementById('staffLane');
            const staffPositions = document.getElementById('staffPositions');
            const staffDynamic = document.getElementById('staffDynamicShooting');
            const staffDynamicField = document.getElementById('staffDynamicField');
            const staffAvailability = document.getElementById('staffAvailability');
            const staffSubmit = document.getElementById('staffReservationSubmit');
            const staffModal = document.getElementById('staffReservationModal');
            let editingReservationId = null;
            let staffReservations = [];
            let staffAvailabilityLoading = false;
            let staffAvailabilityError = null;

            function addTimeOption(select, minutes) {
                const option = document.createElement('option');
                const value = `${String(Math.floor(minutes / 60)).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}`;
                option.value = value;
                option.textContent = value;
                select.appendChild(option);
            }

            function timeMinutes(value) {
                const [hours, minutes] = value.split(':').map(Number);
                return (hours * 60) + minutes;
            }

            function updateStaffEndTimes() {
                const date = parseDate(staffDate.value);
                const isoDay = date.getDay() === 0 ? 7 : date.getDay();
                const hours = adminCalendar.openingHours[isoDay];
                const previous = staffEnd.value;
                staffEnd.innerHTML = '';

                if (!hours || !staffStart.value) {
                    return;
                }

                const closing = timeMinutes(hours.closes_at);

                for (
                    let minutes = timeMinutes(staffStart.value) + 30;
                    minutes <= closing;
                    minutes += 30
                ) {
                    addTimeOption(staffEnd, minutes);
                }

                if ([...staffEnd.options].some(option => option.value === previous)) {
                    staffEnd.value = previous;
                }

                updateStaffPositions();
            }

            function updateStaffTimeOptions() {
                const date = parseDate(staffDate.value);
                const isoDay = date.getDay() === 0 ? 7 : date.getDay();
                const hours = adminCalendar.openingHours[isoDay];
                staffStart.innerHTML = '';
                staffEnd.innerHTML = '';

                if (!hours) {
                    return;
                }

                const now = new Date();
                const isToday = formatDate(date) === formatDate(now);
                const currentMinutes = (now.getHours() * 60) + now.getMinutes();
                const opening = timeMinutes(hours.opens_at);
                const closing = timeMinutes(hours.closes_at);

                for (
                    let minutes = opening;
                    minutes < closing;
                    minutes += 30
                ) {
                    if (!isToday || minutes > currentMinutes) {
                        addTimeOption(staffStart, minutes);
                    }
                }

                updateStaffEndTimes();
            }

            function overlappingReservationPositions() {
                if (!staffStart.value || !staffEnd.value || !staffLane.value) {
                    return null;
                }

                const start = timeMinutes(staffStart.value);
                const end = timeMinutes(staffEnd.value);
                let maximumOccupied = 0;

                for (let slot = start; slot < end; slot += 30) {
                    const occupied = staffReservations
                        .filter(reservation => (
                            String(reservation.line_id) === staffLane.value
                            && Number(reservation.id) !== editingReservationId
                            && timeMinutes(reservation.start_time) < slot + 30
                            && timeMinutes(reservation.end_time) > slot
                        ))
                        .reduce(
                            (sum, reservation) => sum + Number(reservation.positions_count),
                            0
                        );

                    maximumOccupied = Math.max(maximumOccupied, occupied);
                }

                return maximumOccupied;
            }

            function updateStaffPositions() {
                const option = staffLane.selectedOptions[0];
                const capacity = Number(option?.dataset.positions ?? 0);
                const allowsDynamic = option?.dataset.dynamic === '1';
                const occupied = overlappingReservationPositions();
                const available = occupied === null
                    ? 0
                    : Math.max(0, capacity - occupied);
                staffDynamicField.classList.toggle('d-none', !allowsDynamic);

                if (!allowsDynamic) {
                    staffDynamic.checked = false;
                }

                staffPositions.innerHTML = '';
                staffAvailability.className = 'small text-muted';

                if (staffAvailabilityLoading) {
                    staffAvailability.textContent = 'Sprawdzanie dostępności...';
                    staffSubmit.disabled = true;
                    return;
                }

                if (staffAvailabilityError) {
                    staffAvailability.className = 'small text-danger';
                    staffAvailability.textContent = staffAvailabilityError;
                    staffSubmit.disabled = true;
                    return;
                }

                if (occupied === null) {
                    staffAvailability.textContent = 'Wybierz oś i zakres godzin.';
                    staffSubmit.disabled = true;
                    return;
                }

                if (staffDynamic.checked) {
                    const position = document.createElement('option');
                    position.value = capacity;
                    position.textContent = `${capacity} - cała oś`;
                    staffPositions.appendChild(position);
                    const dynamicAvailable = available === capacity;
                    staffAvailability.className = dynamicAvailable
                        ? 'small text-success'
                        : 'small text-danger';
                    staffAvailability.textContent = dynamicAvailable
                        ? 'Cała oś jest dostępna w wybranym czasie.'
                        : 'Strzelanie dynamiczne wymaga dostępności wszystkich stanowisk osi.';
                    staffSubmit.disabled = !dynamicAvailable;
                    return;
                }

                for (let number = 1; number <= available; number++) {
                    const position = document.createElement('option');
                    position.value = number;
                    position.textContent = number;
                    staffPositions.appendChild(position);
                }

                staffAvailability.className = available > 0
                    ? 'small text-success'
                    : 'small text-danger';
                staffAvailability.textContent = available > 0
                    ? `Dostępne stanowiska w całym przedziale: ${available}.`
                    : 'Brak wolnych stanowisk w wybranym czasie.';
                staffSubmit.disabled = available === 0;
            }

            async function loadStaffAvailability() {
                if (!staffDate.value) {
                    staffReservations = [];
                    staffAvailabilityError = null;
                    updateStaffPositions();
                    return;
                }

                staffAvailabilityLoading = true;
                staffAvailabilityError = null;
                updateStaffPositions();

                try {
                    const url = new URL(
                        adminCalendar.availabilityUrl,
                        window.location.origin
                    );
                    url.searchParams.set('date', staffDate.value);

                    const response = await fetch(url, {
                        headers: { Accept: 'application/json' }
                    });
                    const result = await response.json();

                    if (!response.ok) {
                        throw new Error(
                            result.message ?? 'Nie udało się sprawdzić dostępności.'
                        );
                    }

                    staffReservations = result.reservations ?? [];
                } catch (error) {
                    staffReservations = [];
                    staffAvailabilityError = error.message;
                } finally {
                    staffAvailabilityLoading = false;
                    updateStaffPositions();
                }
            }

            async function resetStaffForm() {
                editingReservationId = null;
                staffForm.action = adminCalendar.storeRoute;
                staffMethod.disabled = true;
                staffTitle.textContent = 'Nowa rezerwacja';
                staffSubmitLabel.textContent = 'Zapisz rezerwację';
                staffForm.reset();
                customerType.value = 'account';
                updateCustomerFields();
                staffDate.value = adminCalendar.selectedDate;
                staffDynamic.checked = false;
                updateStaffTimeOptions();
                await loadStaffAvailability();
            }

            async function configureStaffEdit(reservation) {
                editingReservationId = Number(reservation.id);
                staffForm.action = adminCalendar.updateRouteTemplate.replace(
                    '__ID__',
                    reservation.id
                );
                staffMethod.disabled = false;
                staffTitle.textContent = `Edytuj rezerwację #${reservation.id}`;
                staffSubmitLabel.textContent = 'Zapisz zmiany';
                staffForm.reset();

                customerType.value = reservation.customer_type;
                updateCustomerFields(false);
                customerUserId.value = reservation.customer_user_id ?? '';
                customerName.value = reservation.user_name ?? '';
                customerEmail.value = reservation.user_email ?? '';
                customerPhone.value = reservation.user_phone ?? '';
                staffDate.value = reservation.date;
                staffLane.value = String(reservation.line_id);
                staffDynamic.checked = Boolean(
                    reservation.is_dynamic_shooting
                );
                staffNotes.value = reservation.notes ?? '';

                updateStaffTimeOptions();

                if ([...staffStart.options].some(
                    option => option.value === reservation.start_time
                )) {
                    staffStart.value = reservation.start_time;
                }

                updateStaffEndTimes();

                if ([...staffEnd.options].some(
                    option => option.value === reservation.end_time
                )) {
                    staffEnd.value = reservation.end_time;
                }

                await loadStaffAvailability();

                if ([...staffPositions.options].some(
                    option => Number(option.value)
                        === Number(reservation.positions_count)
                )) {
                    staffPositions.value = reservation.positions_count;
                }
            }

            staffDate.addEventListener('change', () => {
                updateStaffTimeOptions();
                loadStaffAvailability();
            });
            staffStart.addEventListener('change', updateStaffEndTimes);
            staffEnd.addEventListener('change', updateStaffPositions);
            staffLane.addEventListener('change', updateStaffPositions);
            staffDynamic.addEventListener('change', updateStaffPositions);
            staffModal.addEventListener('show.bs.modal', event => {
                if (event.relatedTarget?.id === 'newReservationButton') {
                    resetStaffForm();
                }
            });

            const statusModal = document.getElementById('reservationStatusModal');
            const statusForm = document.getElementById('reservationStatusForm');
            const statusSelect = document.getElementById('reservationStatus');
            const cancellationField = document.getElementById('cancellationReasonField');
            const cancellationReason = document.getElementById('cancellationReason');
            const notesSection = document.getElementById('reservationDetailNotesSection');
            const historySection = document.getElementById('reservationDetailHistorySection');
            const editReservationButton = document.getElementById(
                'editReservationButton'
            );
            let selectedReservation = null;

            function setDetailText(id, value) {
                document.getElementById(id).textContent = value || 'Brak danych';
            }

            function setAvailableStatusOptions(reservation) {
                const availableBeforeStart = {
                    pending: ['pending', 'approved', 'cancelled'],
                    approved: ['approved', 'cancelled'],
                    cancelled: ['cancelled', 'pending', 'approved'],
                    completed: ['completed'],
                    no_show: ['no_show']
                };
                const availableDuringReservation = {
                    pending: ['pending'],
                    approved: ['approved'],
                    cancelled: ['cancelled'],
                    completed: ['completed'],
                    no_show: ['no_show']
                };
                const availableAfterEnd = {
                    pending: ['pending', 'completed', 'no_show'],
                    approved: ['approved', 'completed', 'no_show'],
                    cancelled: ['cancelled'],
                    completed: ['completed'],
                    no_show: ['no_show']
                };
                const availableStatuses = reservation.has_ended
                    ? availableAfterEnd
                    : (
                        reservation.has_started
                            ? availableDuringReservation
                            : availableBeforeStart
                    );

                [...statusSelect.options].forEach(option => {
                    option.disabled = ! availableStatuses[reservation.status]?.includes(
                        option.value
                    );
                });
            }

            function updateCancellationField() {
                const isCancelled = statusSelect.value === 'cancelled';
                cancellationField.classList.toggle('d-none', !isCancelled);
                cancellationReason.required = isCancelled;
            }

            statusModal.addEventListener('show.bs.modal', event => {
                const reservationId = Number(
                    event.relatedTarget?.dataset.reservationId
                );
                const reservation = adminCalendar.reservations.find(
                    item => Number(item.id) === reservationId
                );

                if (! reservation) {
                    event.preventDefault();
                    return;
                }

                selectedReservation = reservation;

                statusForm.action = adminCalendar.statusRouteTemplate.replace(
                    '__ID__',
                    reservation.id
                );
                setDetailText(
                    'reservationDetailTitle',
                    `#${reservation.id} · ${reservation.user_name}`
                );
                setDetailText(
                    'reservationDetailDate',
                    `${reservation.date} · ${reservation.start_time}–${reservation.end_time}`
                );
                setDetailText(
                    'reservationDetailLane',
                    [
                        reservation.lane_name,
                        reservation.lane_length
                            ? `${reservation.lane_length} m`
                            : null,
                        `${reservation.positions_count} stanowisk`,
                        reservation.is_dynamic_shooting
                            ? 'strzelanie dynamiczne'
                            : null
                    ].filter(Boolean).join(' · ')
                );
                setDetailText('reservationDetailEmail', reservation.user_email);
                setDetailText('reservationDetailPhone', reservation.user_phone);
                setDetailText(
                    'reservationDetailSource',
                    sourceLabels[reservation.source] ?? reservation.source
                );
                setDetailText(
                    'reservationDetailCreated',
                    [
                        reservation.created_at,
                        reservation.created_by_name
                    ].filter(Boolean).join(' · ')
                );

                notesSection.classList.toggle('d-none', ! reservation.notes);
                setDetailText('reservationDetailNotes', reservation.notes);

                const history = [];

                if (reservation.approved_at) {
                    history.push(
                        `Zatwierdzona: ${reservation.approved_at}`
                        + (reservation.approved_by_name
                            ? ` · ${reservation.approved_by_name}`
                            : '')
                    );
                }

                if (reservation.cancelled_at) {
                    history.push(
                        `Anulowana: ${reservation.cancelled_at}`
                        + (reservation.cancelled_by_name
                            ? ` · ${reservation.cancelled_by_name}`
                            : '')
                    );
                }

                if (reservation.cancellation_reason) {
                    history.push(
                        `Powód anulowania: ${reservation.cancellation_reason}`
                    );
                }

                if (reservation.finalized_at) {
                    history.push(
                        `${statusLabels[reservation.status]}: ${reservation.finalized_at}`
                        + (reservation.finalized_by_name
                            ? ` · ${reservation.finalized_by_name}`
                            : '')
                    );
                }

                historySection.classList.toggle('d-none', history.length === 0);
                setDetailText('reservationDetailHistory', history.join('\n'));

                editReservationButton.classList.toggle(
                    'd-none',
                    ! reservation.can_edit
                );
                setAvailableStatusOptions(reservation);
                statusSelect.value = reservation.status;
                cancellationReason.value = reservation.cancellation_reason ?? '';
                updateCancellationField();
            });

            editReservationButton.addEventListener('click', async () => {
                if (! selectedReservation?.can_edit) {
                    return;
                }

                await configureStaffEdit(selectedReservation);
                const detailsModal = bootstrap.Modal.getInstance(statusModal);
                const editModal = bootstrap.Modal.getOrCreateInstance(staffModal);

                statusModal.addEventListener(
                    'hidden.bs.modal',
                    () => editModal.show(editReservationButton),
                    { once: true }
                );
                detailsModal.hide();
            });

            statusSelect.addEventListener('change', updateCancellationField);
        });
    </script>
</x-app-layout>
