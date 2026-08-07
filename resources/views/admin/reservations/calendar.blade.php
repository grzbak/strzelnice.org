@php
    $shootingRangePublicUrl = $shootingRange->endpoint
        ? route('shooting.public.show', ['endpoint' => $shootingRange->endpoint])
        : null;
    $calendarLanes = $shootingRange->lanes
        ->map(fn ($lane) => [
            'id' => $lane->id,
            'name' => $lane->name,
            'positions' => (int) $lane->numberOfPositions,
        ])
        ->values();
@endphp

<x-app-layout>
    <div class="page-bg-2"></div>

    <main class="px-3 py-4 container-fluid px-xl-4 admin-reservations">
        <x-session-alerts />

        @if (! $shootingRange->is_visible)
            <div class="gap-2 alert alert-warning d-flex align-items-start" role="alert">
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
                <p>
                    @if ($shootingRangePublicUrl)
                        <a href="{{ $shootingRangePublicUrl }}">
                            {{ $shootingRange->name }}
                        </a>
                    @else
                        {{ $shootingRange->name }}
                    @endif
                </p>
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

                @if (! auth()->user()->isSuperAdmin())
                    <form
                        method="POST"
                        action="{{ route('admin.reservations.notifications') }}"
                        class="reservation-notification-toggle"
                    >
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="shooting_range_id" value="{{ $shootingRange->id }}">
                        <input type="hidden" name="receives_reservation_notifications" value="0">

                        <label
                            class="mb-0 form-check form-switch"
                            data-bs-toggle="tooltip"
                            data-bs-title="Powiadomienia e-mail o nowych rezerwacjach, zmianach statusu i anulowaniach"
                        >
                            <input
                                class="form-check-input"
                                type="checkbox"
                                name="receives_reservation_notifications"
                                value="1"
                                @checked($reservationNotificationsEnabled)
                                onchange="this.form.submit()"
                            >
                            <span class="form-check-label">Powiadomienia</span>
                        </label>
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
                    <div
                        id="staffReservationErrorPanel"
                        @class([
                            'alert',
                            'alert-warning',
                            'd-flex',
                            'gap-2',
                            'align-items-start',
                            'd-none' => ! session('staff_reservation_modal'),
                        ])
                        role="alert"
                    >
                        <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
                        <div>
                            <strong class="d-block">Nie można zapisać rezerwacji.</strong>
                            <span id="staffReservationErrorText">
                                {{ session('staff_reservation_modal.message') }}
                            </span>
                        </div>
                    </div>

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
                                Dla użytkownika z kontem wpisz e-mail i kliknij „Szukaj”.
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label for="staffCustomerEmail" class="form-label">E-mail</label>
                            <div class="input-group">
                                <input
                                    type="email"
                                    name="customer_email"
                                    id="staffCustomerEmail"
                                    class="form-control"
                                >
                                <button
                                    type="button"
                                    class="btn btn-outline-dark"
                                    id="staffCustomerSearchButton"
                                    data-bs-toggle="tooltip"
                                    data-bs-title="Wyszukaj użytkownika po e-mailu"
                                >
                                    <i class="bi bi-search"></i>
                                    <span class="visually-hidden">Szukaj użytkownika po e-mailu</span>
                                </button>
                            </div>
                            <div class="form-text d-none" id="staffCustomerSearchMessage"></div>
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
                        class="btn btn-outline-primary me-auto"
                        id="editReservationButton"
                        disabled
                    >
                        <i class="bi bi-pencil me-1"></i>
                        Edytuj rezerwację
                    </button>
                    <button
                        type="button"
                        class="btn btn-outline-danger d-none"
                        id="deleteReservationButton"
                    >
                        <i class="bi bi-trash3 me-1"></i>
                        Usuń rezerwację
                    </button>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Anuluj</button>
                    <button type="submit" class="btn btn-success">Zapisz status</button>
                </div>
            </form>
        </div>
    </div>

    <form method="POST" action="" id="deleteReservationForm" class="d-none">
        @csrf
        @method('DELETE')
    </form>

    <div class="modal fade" id="deleteReservationConfirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <span class="calendar-eyebrow">Usuwanie rezerwacji</span>
                        <h2 class="modal-title fs-5">Potwierdź usunięcie</h2>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-0 alert alert-warning">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        Czy na pewno chcesz usunąć tę rezerwację? Tej operacji nie można cofnąć.
                    </div>
                    <dl class="mt-3 mb-0 reservation-delete-summary small">
                        <dt>Klient</dt>
                        <dd id="deleteReservationCustomer" class="mb-2"></dd>
                        <dt>Termin</dt>
                        <dd id="deleteReservationDate" class="mb-2"></dd>
                        <dt>Oś i stanowiska</dt>
                        <dd id="deleteReservationLane" class="mb-0"></dd>
                    </dl>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Anuluj</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteReservationButton">
                        <i class="bi bi-trash3 me-1"></i>
                        Usuń rezerwację
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="customerMatchModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <span class="calendar-eyebrow">Weryfikacja klienta</span>
                        <h2 class="modal-title fs-5">Znaleziono użytkownika w bazie</h2>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">
                        Dla podanego adresu e-mail istnieje konto użytkownika. Możesz użyć danych z konta albo zapisać rezerwację z danymi wpisanymi w formularzu.
                    </p>

                    <div class="alert alert-warning d-none" id="customerMatchDifferenceAlert">
                        Dane wpisane w formularzu różnią się od danych użytkownika w bazie. Potwierdź, których danych chcesz użyć do rezerwacji.
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="p-3 border rounded h-100">
                                <h3 class="mb-3 fs-6">Dane z bazy</h3>
                                <dl class="mb-0 small">
                                    <dt>Imię i nazwisko</dt>
                                    <dd id="customerMatchUserName" class="mb-2"></dd>
                                    <dt>E-mail</dt>
                                    <dd id="customerMatchUserEmail" class="mb-2"></dd>
                                    <dt>Telefon</dt>
                                    <dd id="customerMatchUserPhone" class="mb-0"></dd>
                                </dl>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 border rounded h-100">
                                <h3 class="mb-3 fs-6">Dane z formularza</h3>
                                <dl class="mb-0 small">
                                    <dt>Imię i nazwisko</dt>
                                    <dd id="customerMatchFormName" class="mb-2"></dd>
                                    <dt>E-mail</dt>
                                    <dd id="customerMatchFormEmail" class="mb-2"></dd>
                                    <dt>Telefon</dt>
                                    <dd id="customerMatchFormPhone" class="mb-0"></dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-dark" id="customerMatchKeepTypedButton">
                        Zapisz z wpisanymi danymi
                    </button>
                    <button type="button" class="btn btn-danger" id="customerMatchUseAccountButton">
                        Użyj danych użytkownika
                    </button>
                </div>
            </div>
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

        .calendar-header p a {
            color: inherit;
            text-decoration: none;
        }

        .calendar-header p a:hover,
        .calendar-header p a:focus-visible {
            color: var(--red);
        }

        .calendar-eyebrow {
            color: var(--green);
            font-size: .7rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .modal-backdrop.reservation-delete-backdrop {
            --bs-backdrop-opacity: .78;
            background-color: #050505;
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

        .reservation-notification-toggle {
            padding: .42rem .65rem;
            color: #26342d;
            background: #f7f9f8;
            border: 1px solid #d7dfdb;
            border-radius: 6px;
        }

        .reservation-notification-toggle .form-check-label {
            font-size: .82rem;
            font-weight: 600;
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

        .day-board {
            overflow: visible;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 8px;
            box-shadow: 0 8px 24px rgb(21 31 27 / 8%);
        }

        .day-lane-filter {
            display: flex;
            align-items: stretch;
            gap: .55rem;
            padding: .85rem;
            overflow-x: auto;
            background: linear-gradient(90deg, #f8f9fa, #fff);
            border-bottom: 1px solid var(--border);
        }

        .day-lane-all {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            min-width: max-content;
            padding: .56rem .7rem;
            color: #26332d;
            font-size: .76rem;
            font-weight: 800;
            background: #fff;
            border: 1px solid #d7dfdb;
            border-radius: 6px;
            box-shadow: 0 2px 8px rgb(21 31 27 / 8%);
        }

        .day-lane-all input {
            width: 1rem;
            height: 1rem;
            accent-color: #26332d;
        }

        .day-lane-tile {
            min-width: 145px;
            padding: .55rem .7rem;
            color: #fff;
            text-align: left;
            background: var(--lane-color);
            border: 1px solid var(--lane-color);
            border-radius: 6px;
            box-shadow: 0 4px 12px rgb(21 31 27 / 13%);
            transition: opacity .15s ease, transform .15s ease, box-shadow .15s ease;
        }

        .day-lane-tile:hover,
        .day-lane-tile:focus-visible {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgb(21 31 27 / 18%);
        }

        .day-lane-tile.is-hidden {
            color: #26332d;
            background: color-mix(in srgb, var(--lane-color) 14%, white);
            border-color: color-mix(in srgb, var(--lane-color) 32%, white);
            box-shadow: none;
        }

        .day-lane-tile strong,
        .day-lane-tile span {
            display: block;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .day-lane-tile span {
            margin-top: .12rem;
            font-size: .68rem;
            font-weight: 700;
            opacity: .82;
        }

        .day-status-legend {
            display: flex;
            align-items: center;
            gap: .45rem;
            flex-wrap: wrap;
            padding: .55rem .85rem;
            color: var(--muted);
            font-size: .73rem;
            font-weight: 700;
            background: #fff;
            border-bottom: 1px solid var(--border);
        }

        .day-status-legend span {
            display: inline-flex;
            align-items: center;
            gap: .28rem;
        }

        .day-scroll-hint {
            display: none;
            padding: .45rem .85rem;
            color: #5d4037;
            font-size: .74rem;
            font-weight: 700;
            background: #fff8e1;
            border-bottom: 1px solid #f2df9f;
        }

        .day-scroll-controls {
            display: none;
            align-items: center;
            justify-content: space-between;
            gap: .6rem;
            padding: .5rem .85rem;
            background: #fff;
            border-bottom: 1px solid var(--border);
        }

        .day-scroll-controls button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2.35rem;
            height: 2.35rem;
            color: #26332d;
            background: #f8f9fa;
            border: 1px solid #d7dfdb;
            border-radius: 6px;
        }

        .day-scroll-controls span {
            color: var(--muted);
            font-size: .72rem;
            font-weight: 700;
            text-align: center;
        }

        .day-timeline {
            display: flex;
            align-items: stretch;
            max-width: 100%;
            overflow-x: auto;
            overflow-y: hidden;
            background: #fff;
            cursor: grab;
            touch-action: pan-y;
            -webkit-overflow-scrolling: touch;
            overscroll-behavior-x: contain;
        }

        .day-timeline.is-dragging {
            cursor: grabbing;
        }

        .day-time-labels {
            position: sticky;
            left: 0;
            z-index: 4;
            display: grid;
            flex: 0 0 80px;
            background: #f7f9f8;
            border-right: 1px solid var(--border);
        }

        .day-reservation-area {
            position: relative;
            min-width: 0;
            background:
                repeating-linear-gradient(
                    to bottom,
                    transparent 0,
                    transparent calc(var(--hour-height) - 1px),
                    #dfe5e5 calc(var(--hour-height) - 1px),
                    #dfe5e5 var(--hour-height)
                ),
                repeating-linear-gradient(
                    to right,
                    transparent 0,
                    transparent calc(var(--position-width) - 1px),
                    rgb(38 51 45 / 8%) calc(var(--position-width) - 1px),
                    rgb(38 51 45 / 8%) var(--position-width)
                ),
                #fff;
        }

        .day-lane-band {
            position: absolute;
            top: 0;
            bottom: 0;
            z-index: 1;
            overflow: hidden;
            background:
                repeating-linear-gradient(
                    to bottom,
                    transparent 0,
                    transparent calc(var(--hour-height) - 1px),
                    color-mix(in srgb, var(--lane-color) 20%, white) calc(var(--hour-height) - 1px),
                    color-mix(in srgb, var(--lane-color) 20%, white) var(--hour-height)
                ),
                color-mix(in srgb, var(--lane-color) 6%, white);
            border-right: 1px solid color-mix(in srgb, var(--lane-color) 28%, white);
        }

        .day-position-line {
            position: absolute;
            top: 0;
            bottom: 0;
            z-index: 2;
            width: 1px;
            pointer-events: none;
            background: color-mix(in srgb, var(--lane-color) 52%, #26332d);
            opacity: .55;
        }

        .day-lane-band-label {
            position: sticky;
            top: 0;
            z-index: 2;
            display: inline-block;
            margin: .35rem 0 0 .4rem;
            padding: .18rem .38rem;
            color: #fff;
            font-size: .62rem;
            font-weight: 800;
            background: var(--lane-color);
            border-radius: 4px;
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

        .reservation-card.day-reservation-card {
            position: absolute;
            z-index: 3;
            min-width: 60px;
            min-height: 48px;
            grid-template-columns: minmax(0, 1fr);
            align-content: start;
            gap: .18rem;
            padding: .48rem .52rem;
            overflow: visible;
            color: #26332d;
            background: color-mix(in srgb, var(--lane-color) 17%, white);
            border-color: color-mix(in srgb, var(--lane-color) 42%, white);
            border-left-color: var(--lane-color);
            border-width: 1px 1px 1px 5px;
            border-style: solid;
            box-shadow: 0 8px 18px rgb(22 32 27 / 18%);
        }

        .reservation-card.day-reservation-card:hover,
        .reservation-card.day-reservation-card:focus-visible {
            z-index: 8;
        }

        .reservation-card.day-reservation-card .reservation-lane,
        .reservation-card.day-reservation-card .reservation-status {
            display: none;
        }

        .reservation-card.day-reservation-card strong,
        .reservation-card.day-reservation-card span {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .day-reservation-time {
            display: inline-flex;
            align-items: center;
            gap: .25rem;
            font-size: .72rem;
            font-weight: 800;
        }

        .day-reservation-status-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1.1rem;
            height: 1.1rem;
            flex: 0 0 1.1rem;
            color: #fff;
            font-size: .68rem;
            background: #26332d;
            border-radius: 999px;
        }

        .day-reservation-status-icon.pending {
            color: #26332d;
            background: #ffc107;
        }

        .day-reservation-status-icon.approved {
            background: var(--green);
        }

        .day-reservation-status-icon.cancelled {
            background: #dc3545;
        }

        .day-reservation-status-icon.completed {
            background: #0891b2;
        }

        .day-reservation-status-icon.no_show {
            background: #71717a;
        }

        .day-reservation-meta {
            color: currentColor;
            opacity: .82;
        }

        .day-reservation-tooltip {
            position: fixed;
            z-index: 3000;
            display: none;
            width: min(360px, calc(100vw - 24px));
            padding: .85rem .95rem;
            color: #fff;
            font-size: .82rem;
            line-height: 1.45;
            background: #1f2924;
            border-radius: 6px;
            box-shadow: 0 14px 34px rgb(0 0 0 / 34%);
            pointer-events: none;
        }

        .day-reservation-tooltip.is-visible {
            display: block;
        }

        .day-reservation-tooltip strong {
            display: block;
            margin-bottom: .45rem;
            color: #fff;
            font-size: .95rem;
        }

        .day-reservation-tooltip dl {
            display: grid;
            grid-template-columns: max-content minmax(0, 1fr);
            gap: .25rem .65rem;
            margin: 0;
        }

        .day-reservation-tooltip dt {
            color: rgb(255 255 255 / 66%);
            font-size: .68rem;
            font-weight: 800;
            text-transform: uppercase;
        }

        .day-reservation-tooltip dd {
            min-width: 0;
            margin: 0;
            overflow-wrap: anywhere;
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

        #staffReservationModal,
        #reservationStatusModal,
        #deleteReservationConfirmModal,
        #customerMatchModal {
            z-index: 4100;
        }

        body.modal-open .day-reservation-tooltip {
            display: none !important;
        }

        .modal-backdrop.show {
            z-index: 4050;
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

            .day-lane-filter {
                flex-wrap: wrap;
                overflow-x: visible;
            }

            .day-lane-all,
            .day-lane-tile {
                min-width: 0;
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
            .admin-reservations {
                padding-right: .75rem !important;
                padding-left: .75rem !important;
            }

            #staffReservationModal {
                padding: 0 !important;
            }

            #staffReservationModal .modal-dialog {
                display: flex;
                align-items: stretch;
                width: calc(100vw - 1rem);
                max-width: calc(100vw - 1rem);
                height: calc(100dvh - 1rem);
                min-height: 0;
                margin: .5rem auto;
            }

            #staffReservationModal .modal-content {
                display: flex;
                flex-direction: column;
                width: 100%;
                max-height: 100%;
                overflow: hidden;
                border-radius: 8px;
            }

            #staffReservationModal .modal-header,
            #staffReservationModal .modal-footer {
                flex: 0 0 auto;
            }

            #staffReservationModal .modal-body {
                flex: 1 1 auto;
                min-height: 0;
                overflow-y: auto;
                -webkit-overflow-scrolling: touch;
            }

            #staffReservationModal .modal-footer {
                align-items: stretch;
                flex-direction: column-reverse;
                gap: .5rem;
            }

            #staffReservationModal .modal-footer .btn {
                width: 100%;
                margin: 0;
            }

            .day-lane-filter {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: .5rem;
                padding: .65rem;
            }

            .day-lane-all {
                justify-content: center;
                grid-column: 1 / -1;
                width: 100%;
            }

            .day-lane-tile {
                width: 100%;
                padding: .58rem .5rem;
                text-align: center;
            }

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

            .day-timeline {
                max-width: calc(100vw - 1.5rem);
            }

            .day-scroll-controls.is-visible {
                display: flex;
            }

            .day-time-labels {
                flex-basis: 64px;
            }

            .hour-label {
                padding: .65rem .35rem;
                font-size: .68rem;
            }

            .reservation-card {
                grid-template-columns: 1fr;
            }

            .reservation-detail-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 379.98px) {
            .day-lane-filter {
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
            lanes: @json($calendarLanes),
            openingHours: @json($openingHours),
            shootingRangeId: @json($shootingRange->id),
            customerSearchUrl: @json(route('admin.reservations.customers.search')),
            availabilityUrl: @json(route('rezerwacje.availability', $shootingRange->id)),
            statusRouteTemplate: @json(route('admin.reservations.status', ['reservation' => '__ID__'])),
            updateRouteTemplate: @json(route('admin.reservations.update', ['reservation' => '__ID__'])),
            deleteRouteTemplate: @json(route('admin.reservations.destroy', ['reservation' => '__ID__'])),
            storeRoute: @json(route('admin.reservations.store')),
            staffReservationRestore: @json(session('staff_reservation_modal')),
            oldInput: @json(session()->getOldInput())
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
        const lanePalette = [
            '#7a1f1f',
            '#1f5d3b',
            '#24537a',
            '#7a4c1f',
            '#5d377a',
            '#1f737a',
            '#7a1f4d',
            '#4f5f24',
            '#3c3f46',
            '#8a3f12'
        ];
        const dayVisibleLaneIds = new Set(
            (adminCalendar.lanes ?? []).map(lane => String(lane.id))
        );
        let dayLaneSelectionInitialized = dayVisibleLaneIds.size > 0;

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

        function timeToMinutes(value) {
            const [hours, minutes] = value.split(':').map(Number);

            return (hours * 60) + minutes;
        }

        function laneColor(index) {
            return lanePalette[index % lanePalette.length];
        }

        function statusIcon(status) {
            return {
                pending: 'bi-hourglass-split',
                approved: 'bi-check-lg',
                cancelled: 'bi-x-lg',
                completed: 'bi-flag-fill',
                no_show: 'bi-slash-circle'
            }[status] ?? 'bi-info-lg';
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

        function calendarTooltip() {
            let tooltip = document.getElementById('dayReservationTooltip');

            if (! tooltip) {
                tooltip = document.createElement('div');
                tooltip.id = 'dayReservationTooltip';
                tooltip.className = 'day-reservation-tooltip';
                tooltip.setAttribute('role', 'tooltip');
                document.body.appendChild(tooltip);
            }

            return tooltip;
        }

        function placeCalendarTooltip(card, tooltip) {
            const gap = 12;
            const viewportMargin = 12;
            const cardRect = card.getBoundingClientRect();
            const tooltipRect = tooltip.getBoundingClientRect();
            const placements = [
                {
                    name: 'right',
                    fits: window.innerWidth - cardRect.right >= tooltipRect.width + gap + viewportMargin,
                    left: cardRect.right + gap,
                    top: cardRect.top + (cardRect.height - tooltipRect.height) / 2
                },
                {
                    name: 'left',
                    fits: cardRect.left >= tooltipRect.width + gap + viewportMargin,
                    left: cardRect.left - tooltipRect.width - gap,
                    top: cardRect.top + (cardRect.height - tooltipRect.height) / 2
                },
                {
                    name: 'bottom',
                    fits: window.innerHeight - cardRect.bottom >= tooltipRect.height + gap + viewportMargin,
                    left: cardRect.left,
                    top: cardRect.bottom + gap
                },
                {
                    name: 'top',
                    fits: cardRect.top >= tooltipRect.height + gap + viewportMargin,
                    left: cardRect.left,
                    top: cardRect.top - tooltipRect.height - gap
                }
            ];
            const placement = placements.find(item => item.fits) ?? placements[2];
            const left = Math.min(
                Math.max(viewportMargin, placement.left),
                window.innerWidth - tooltipRect.width - viewportMargin
            );
            const top = Math.min(
                Math.max(viewportMargin, placement.top),
                window.innerHeight - tooltipRect.height - viewportMargin
            );

            tooltip.style.left = `${left}px`;
            tooltip.style.top = `${top}px`;
            tooltip.dataset.placement = placement.name;
        }

        function showCalendarTooltip(card) {
            const tooltip = calendarTooltip();
            tooltip.innerHTML = card.dataset.tooltipHtml ?? '';
            tooltip.classList.add('is-visible');
            placeCalendarTooltip(card, tooltip);
        }

        function hideCalendarTooltip() {
            calendarTooltip().classList.remove('is-visible');
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

        function enableDayTimelineDrag(timeline) {
            let isPointerDown = false;
            let isDragging = false;
            let isTouchDown = false;
            let isTouchDragging = false;
            let startX = 0;
            let startY = 0;
            let startScrollLeft = 0;
            let suppressClick = false;

            function startDrag(clientX, clientY) {
                startX = clientX;
                startY = clientY;
                startScrollLeft = timeline.scrollLeft;
                suppressClick = false;
            }

            function moveDrag(clientX, clientY, event) {
                const deltaX = clientX - startX;
                const deltaY = clientY - startY;

                if (! isDragging && ! isTouchDragging && Math.abs(deltaX) <= 6) {
                    return false;
                }

                if (! isDragging && ! isTouchDragging && Math.abs(deltaY) > Math.abs(deltaX)) {
                    return false;
                }

                isDragging = true;
                isTouchDragging = true;
                suppressClick = true;
                timeline.classList.add('is-dragging');
                timeline.scrollLeft = startScrollLeft - deltaX;
                event.preventDefault();

                return true;
            }

            function endDrag() {
                isPointerDown = false;
                isTouchDown = false;
                isDragging = false;
                isTouchDragging = false;
                timeline.classList.remove('is-dragging');

                window.setTimeout(() => {
                    suppressClick = false;
                }, 0);
            }

            timeline.addEventListener('pointerdown', event => {
                if (event.pointerType === 'touch') {
                    return;
                }

                if (event.button !== 0 && event.pointerType === 'mouse') {
                    return;
                }

                isPointerDown = true;
                isDragging = false;
                startDrag(event.clientX, event.clientY);
            });

            timeline.addEventListener('pointermove', event => {
                if (! isPointerDown) {
                    return;
                }

                moveDrag(event.clientX, event.clientY, event);
            });

            window.addEventListener('pointerup', endDrag);

            timeline.addEventListener('pointercancel', () => {
                suppressClick = false;
                endDrag();
            });

            timeline.addEventListener('touchstart', event => {
                if (event.touches.length !== 1) {
                    return;
                }

                isTouchDown = true;
                isTouchDragging = false;
                const touch = event.touches[0];
                startDrag(touch.clientX, touch.clientY);
            }, { passive: true });

            timeline.addEventListener('touchmove', event => {
                if (! isTouchDown || event.touches.length !== 1) {
                    return;
                }

                const touch = event.touches[0];
                moveDrag(touch.clientX, touch.clientY, event);
            }, { passive: false });

            window.addEventListener('touchend', endDrag, { passive: true });
            window.addEventListener('touchcancel', () => {
                suppressClick = false;
                endDrag();
            }, { passive: true });

            timeline.addEventListener('wheel', event => {
                if (! event.shiftKey && Math.abs(event.deltaX) <= Math.abs(event.deltaY)) {
                    return;
                }

                timeline.scrollLeft += event.deltaX || event.deltaY;
                event.preventDefault();
            }, { passive: false });

            timeline.querySelectorAll('article').forEach(card => {
                card.addEventListener('dragstart', event => {
                    event.preventDefault();
                });
            });

            timeline.addEventListener('click', event => {
                if (! suppressClick) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();
            }, true);
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
            card.addEventListener('mouseenter', () => showCalendarTooltip(card));
            card.addEventListener('mousemove', () => showCalendarTooltip(card));
            card.addEventListener('focus', () => showCalendarTooltip(card));
            card.addEventListener('mouseleave', hideCalendarTooltip);
            card.addEventListener('blur', hideCalendarTooltip);
            card.addEventListener('click', hideCalendarTooltip);

            return card;
        }

        function renderDay(reservations) {
            const content = document.getElementById('calendarContent');
            const selected = parseDate(adminCalendar.selectedDate);
            const dayReservations = reservationsForDate(reservations, adminCalendar.selectedDate);
            const isoDay = selected.getDay() === 0 ? 7 : selected.getDay();
            const hours = adminCalendar.openingHours[isoDay] ?? { opens_at: '08:00', closes_at: '22:00' };
            const startMinutes = Math.floor(timeToMinutes(hours.opens_at) / 60) * 60;
            const closesAtMinutes = timeToMinutes(hours.closes_at);
            const endMinutes = Math.ceil(closesAtMinutes / 60) * 60;
            const totalMinutes = Math.max(60, endMinutes - startMinutes);
            const hourHeight = 82;
            const lanes = (adminCalendar.lanes.length
                ? adminCalendar.lanes
                : [...new Map(dayReservations.map(reservation => [
                    String(reservation.line_id),
                    {
                        id: String(reservation.line_id),
                        name: reservation.lane_name || 'Oś',
                        positions: Number(reservation.positions_count || 1)
                    }
                ])).values()]
            ).map((lane, index) => ({
                ...lane,
                id: String(lane.id),
                positions: Math.max(1, Number(lane.positions || 1)),
                color: laneColor(index)
            }));

            if (! dayLaneSelectionInitialized) {
                lanes.forEach(lane => dayVisibleLaneIds.add(String(lane.id)));
                dayLaneSelectionInitialized = true;
            }

            const selectedLaneIds = new Set(
                lanes
                    .filter(lane => dayVisibleLaneIds.has(String(lane.id)))
                    .map(lane => String(lane.id))
            );
            const visibleLanes = lanes.filter(lane => selectedLaneIds.has(String(lane.id)));
            const activeDayReservations = dayReservations.filter(reservation => {
                return selectedLaneIds.has(String(reservation.line_id));
            });
            const totalPositions = visibleLanes.reduce((sum, lane) => sum + lane.positions, 0);
            const isMobileDayView = window.matchMedia('(max-width: 575.98px)').matches;
            const timeColumnWidth = isMobileDayView ? 64 : 80;
            const minPositionWidth = isMobileDayView ? 60 : 72;
            const contentStyles = window.getComputedStyle(content);
            const horizontalContentPadding = (
                Number.parseFloat(contentStyles.paddingLeft) || 0
            ) + (
                Number.parseFloat(contentStyles.paddingRight) || 0
            );
            const viewportContentWidth = isMobileDayView
                ? Math.max(0, window.innerWidth - 24)
                : 0;
            const contentWidth = Math.max(
                0,
                viewportContentWidth,
                (content.getBoundingClientRect().width || window.innerWidth || 0) - horizontalContentPadding
            );
            const availableBoardWidth = Math.max(0, contentWidth - timeColumnWidth);
            const minimumBoardWidth = totalPositions * minPositionWidth;
            const boardWidth = Math.max(availableBoardWidth, minimumBoardWidth);
            const positionWidth = totalPositions > 0
                ? Math.max(minPositionWidth, boardWidth / totalPositions)
                : minPositionWidth;
            const requiresHorizontalScroll = totalPositions > 0
                && minimumBoardWidth > availableBoardWidth;

            document.getElementById('calendarTitle').textContent = readableDate(selected, {
                weekday: 'long',
                day: 'numeric',
                month: 'long',
                year: 'numeric'
            });

            content.innerHTML = '';

            const board = document.createElement('div');
            board.className = 'day-board';

            const laneFilter = document.createElement('div');
            laneFilter.className = 'day-lane-filter';

            const allToggle = document.createElement('label');
            allToggle.className = 'day-lane-all';
            allToggle.innerHTML = `
                <input type="checkbox" ${visibleLanes.length === lanes.length ? 'checked' : ''}>
                <span>Wszystkie osie</span>
            `;
            const allToggleInput = allToggle.querySelector('input');
            allToggleInput.indeterminate = visibleLanes.length > 0 && visibleLanes.length < lanes.length;
            allToggleInput.addEventListener('change', event => {
                if (event.target.checked) {
                    lanes.forEach(lane => dayVisibleLaneIds.add(String(lane.id)));
                } else {
                    dayVisibleLaneIds.clear();
                }

                renderDay(reservations);
            });
            laneFilter.appendChild(allToggle);

            lanes.forEach(lane => {
                const tile = document.createElement('button');
                tile.type = 'button';
                tile.className = 'day-lane-tile';
                tile.classList.toggle('is-hidden', ! selectedLaneIds.has(String(lane.id)));
                tile.style.setProperty('--lane-color', lane.color);
                tile.innerHTML = `
                    <strong>${escapeHtml(lane.name)}</strong>
                    <span>${escapeHtml(lane.positions)} stanowisk</span>
                `;
                tile.addEventListener('click', () => {
                    if (dayVisibleLaneIds.has(String(lane.id))) {
                        dayVisibleLaneIds.delete(String(lane.id));
                    } else {
                        dayVisibleLaneIds.add(String(lane.id));
                    }

                    renderDay(reservations);
                });
                laneFilter.appendChild(tile);
            });

            const statusLegend = document.createElement('div');
            statusLegend.className = 'day-status-legend';
            statusLegend.innerHTML = `
                <span><i class="bi bi-check-lg day-reservation-status-icon approved"></i>Zatwierdzone</span>
                <span><i class="bi bi-hourglass-split day-reservation-status-icon pending"></i>Oczekujące</span>
                <span><i class="bi bi-x-lg day-reservation-status-icon cancelled"></i>Anulowane</span>
                <span><i class="bi bi-flag-fill day-reservation-status-icon completed"></i>Zrealizowane</span>
                <span><i class="bi bi-slash-circle day-reservation-status-icon no_show"></i>Niezrealizowane</span>
            `;

            const scrollHint = document.createElement('div');
            scrollHint.className = 'day-scroll-hint';
            scrollHint.textContent = 'Przewiń w prawo, aby zobaczyć kolejne osie i stanowiska.';
            if (requiresHorizontalScroll) {
                scrollHint.style.display = 'block';
            }

            const scrollControls = document.createElement('div');
            scrollControls.className = 'day-scroll-controls';
            if (requiresHorizontalScroll) {
                scrollControls.classList.add('is-visible');
            }
            scrollControls.innerHTML = `
                <button type="button" data-scroll-direction="-1" aria-label="Przewiń kalendarz w lewo">
                    <i class="bi bi-chevron-left" aria-hidden="true"></i>
                </button>
                <span>Przesuwaj palcem lub użyj strzałek</span>
                <button type="button" data-scroll-direction="1" aria-label="Przewiń kalendarz w prawo">
                    <i class="bi bi-chevron-right" aria-hidden="true"></i>
                </button>
            `;

            const timeline = document.createElement('div');
            timeline.className = 'day-timeline';
            timeline.style.setProperty('--hour-height', `${hourHeight}px`);
            timeline.style.overflowX = 'auto';
            enableDayTimelineDrag(timeline);

            scrollControls.querySelectorAll('button').forEach(button => {
                button.addEventListener('click', () => {
                    const direction = Number(button.dataset.scrollDirection || 1);
                    timeline.scrollBy({
                        left: direction * Math.max(180, timeline.clientWidth * .65),
                        behavior: 'smooth'
                    });
                });
            });

            const timeLabels = document.createElement('div');
            timeLabels.className = 'day-time-labels';

            const reservationArea = document.createElement('div');
            reservationArea.className = 'day-reservation-area';
            reservationArea.style.height = `${(totalMinutes / 60) * hourHeight}px`;
            reservationArea.style.minWidth = `${boardWidth}px`;
            reservationArea.style.setProperty('--position-width', `${positionWidth}px`);

            for (let minutes = startMinutes; minutes < endMinutes; minutes += 60) {
                const label = document.createElement('div');
                label.className = 'hour-label';
                label.style.height = `${hourHeight}px`;
                label.textContent = `${String(Math.floor(minutes / 60)).padStart(2, '0')}:00`;
                timeLabels.appendChild(label);
            }

            if (! visibleLanes.length) {
                const empty = document.createElement('div');
                empty.className = 'hour-empty';
                empty.style.padding = '1rem';
                empty.textContent = 'Wybierz przynajmniej jedną oś, aby wyświetlić rezerwacje.';
                reservationArea.appendChild(empty);
            }

            const laneOffsets = new Map();
            let runningPositionOffset = 0;

            visibleLanes.forEach(lane => {
                laneOffsets.set(String(lane.id), runningPositionOffset);

                const band = document.createElement('div');
                band.className = 'day-lane-band';
                band.style.setProperty('--lane-color', lane.color);
                band.style.left = `${runningPositionOffset * positionWidth}px`;
                band.style.width = `${lane.positions * positionWidth}px`;
                band.innerHTML = `<span class="day-lane-band-label">${escapeHtml(lane.name)}</span>`;

                for (let position = 1; position < lane.positions; position++) {
                    const positionLine = document.createElement('span');
                    positionLine.className = 'day-position-line';
                    positionLine.style.left = `${position * positionWidth}px`;
                    band.appendChild(positionLine);
                }

                reservationArea.appendChild(band);

                runningPositionOffset += lane.positions;
            });

            const activeLaneSegments = new Map();
            const sortedDayReservations = [...activeDayReservations].sort((first, second) => {
                const firstStart = timeToMinutes(first.start_time);
                const secondStart = timeToMinutes(second.start_time);

                return firstStart - secondStart
                    || timeToMinutes(first.end_time) - timeToMinutes(second.end_time);
            });

            sortedDayReservations.forEach(reservation => {
                const lane = visibleLanes.find(
                    item => String(item.id) === String(reservation.line_id)
                );

                if (! lane) {
                    return;
                }

                const lanePositions = Math.max(1, Number(lane.positions || 1));
                const reservationStart = Math.max(startMinutes, timeToMinutes(reservation.start_time));
                const reservationEnd = Math.min(endMinutes, timeToMinutes(reservation.end_time));
                const duration = Math.max(30, reservationEnd - reservationStart);
                const reservedPositions = Math.min(
                    lanePositions,
                    Math.max(1, Number(reservation.positions_count || 1))
                );
                const laneSegments = (
                    activeLaneSegments.get(String(lane.id)) ?? []
                ).filter(segment => segment.end > reservationStart);
                let offsetUnits = 0;

                for (
                    let candidate = 0;
                    candidate <= lanePositions - reservedPositions;
                    candidate++
                ) {
                    const collides = laneSegments.some(segment => {
                        const overlapsInTime = segment.start < reservationEnd
                            && segment.end > reservationStart;
                        const overlapsInPositions = candidate < segment.offset + segment.width
                            && candidate + reservedPositions > segment.offset;

                        return overlapsInTime && overlapsInPositions;
                    });

                    if (! collides) {
                        offsetUnits = candidate;
                        break;
                    }
                }

                laneSegments.push({
                    start: reservationStart,
                    end: reservationEnd,
                    offset: offsetUnits,
                    width: reservedPositions
                });
                activeLaneSegments.set(String(lane.id), laneSegments);

                const laneOffset = laneOffsets.get(String(lane.id)) ?? 0;
                const card = createReservationCard(reservation);
                card.classList.add('day-reservation-card');
                card.style.setProperty('--lane-color', lane.color);
                card.removeAttribute('title');
                card.dataset.tooltipHtml = `
                    <strong>${escapeHtml(reservation.user_name)}</strong>
                    <dl>
                        <dt>Data</dt>
                        <dd>${escapeHtml(reservation.date)}</dd>
                        <dt>Godziny</dt>
                        <dd>${escapeHtml(reservation.start_time)}-${escapeHtml(reservation.end_time)}</dd>
                        <dt>Oś</dt>
                        <dd>${escapeHtml(lane.name)}</dd>
                        <dt>Stanowiska</dt>
                        <dd>${escapeHtml(reservedPositions)} z ${escapeHtml(lanePositions)}</dd>
                        <dt>Status</dt>
                        <dd>${escapeHtml(statusLabels[reservation.status] ?? reservation.status)}</dd>
                        <dt>Kontakt</dt>
                        <dd>${escapeHtml(reservation.user_phone ?? reservation.user_email ?? 'Brak danych kontaktowych')}</dd>
                        <dt>Typ</dt>
                        <dd>${reservation.is_dynamic_shooting ? 'Strzelanie dynamiczne' : 'Strzelanie statyczne'}</dd>
                    </dl>
                `;
                card.innerHTML = `
                    <span class="day-reservation-time">
                        <i class="bi ${statusIcon(reservation.status)} day-reservation-status-icon ${reservation.status}" aria-hidden="true"></i>
                        ${escapeHtml(reservation.start_time)}-${escapeHtml(reservation.end_time)}
                    </span>
                    <strong>${escapeHtml(reservation.user_name)}</strong>
                    <span class="day-reservation-meta">
                        ${escapeHtml(lane.name)} · ${escapeHtml(reservedPositions)} stanowisk
                    </span>
                `;
                card.style.top = `${((reservationStart - startMinutes) / totalMinutes) * 100}%`;
                card.style.height = `calc(${(duration / totalMinutes) * 100}% - 6px)`;
                card.style.left = `${((laneOffset + offsetUnits) * positionWidth) + 5}px`;
                card.style.width = `${Math.max(60, (reservedPositions * positionWidth) - 10)}px`;
                reservationArea.appendChild(card);
            });

            timeline.appendChild(timeLabels);
            timeline.appendChild(reservationArea);
            board.appendChild(laneFilter);
            board.appendChild(statusLegend);
            board.appendChild(scrollHint);
            board.appendChild(scrollControls);
            board.appendChild(timeline);
            content.appendChild(board);
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

            window.addEventListener('scroll', hideCalendarTooltip, true);
            window.addEventListener('resize', hideCalendarTooltip);

            ['laneFilter', 'statusFilter'].forEach(id => {
                document.getElementById(id).addEventListener('change', renderCalendar);
            });
            document.getElementById('clientFilter').addEventListener('input', renderCalendar);

            const customerType = document.getElementById('staffCustomerType');
            const customerName = document.getElementById('staffCustomerName');
            const customerEmail = document.getElementById('staffCustomerEmail');
            const customerPhone = document.getElementById('staffCustomerPhone');
            const customerUserId = document.getElementById('staffCustomerUserId');
            const customerSearchButton = document.getElementById('staffCustomerSearchButton');
            const customerSearchMessage = document.getElementById('staffCustomerSearchMessage');
            const customerMatchModalElement = document.getElementById('customerMatchModal');
            const customerMatchModal = new bootstrap.Modal(customerMatchModalElement);
            const customerMatchDifferenceAlert = document.getElementById('customerMatchDifferenceAlert');
            const customerMatchUseAccountButton = document.getElementById('customerMatchUseAccountButton');
            const customerMatchKeepTypedButton = document.getElementById('customerMatchKeepTypedButton');
            const accountSearchHelp = document.querySelector('.account-search-help');
            let fillingCustomer = false;
            let pendingCustomerMatch = null;
            let skipCustomerMatchCheck = false;

            function setCustomerSearchMessage(message = '', type = 'muted') {
                customerSearchMessage.textContent = message;
                customerSearchMessage.classList.toggle('d-none', !message);
                customerSearchMessage.classList.remove(
                    'text-muted',
                    'text-success',
                    'text-danger',
                    'text-warning'
                );
                customerSearchMessage.classList.add(`text-${type}`);
            }

            function normalizeText(value) {
                return String(value ?? '').trim().replace(/\s+/g, ' ').toLowerCase();
            }

            function normalizePhone(value) {
                return String(value ?? '').replace(/\D/g, '');
            }

            function setCustomerMatchText(id, value) {
                document.getElementById(id).textContent = value || 'Brak danych';
            }

            function selectCustomer(user) {
                fillingCustomer = true;
                customerUserId.value = user.id;
                customerName.value = user.name ?? '';
                customerEmail.value = user.email ?? '';
                customerPhone.value = user.telephone ?? '';
                fillingCustomer = false;
                setCustomerSearchMessage('Użytkownik został znaleziony i przypisany do rezerwacji.', 'success');
            }

            async function fetchCustomerByEmail(value) {
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
                    throw new Error('customer-search-failed');
                }

                const result = await response.json();
                return (result.users ?? [])[0] ?? null;
            }

            async function searchCustomerByEmail() {
                if (customerType.value !== 'account') {
                    return;
                }

                const value = customerEmail.value.trim();
                customerUserId.value = '';

                if (!value) {
                    setCustomerSearchMessage('Wpisz e-mail użytkownika z kontem.', 'warning');
                    customerEmail.focus();
                    return;
                }

                if (!customerEmail.checkValidity()) {
                    customerEmail.reportValidity();
                    setCustomerSearchMessage('Wpisz poprawny adres e-mail.', 'warning');
                    return;
                }

                customerSearchButton.disabled = true;
                setCustomerSearchMessage('Szukam użytkownika...', 'muted');

                try {
                    const user = await fetchCustomerByEmail(value);

                    if (!user) {
                        setCustomerSearchMessage('Nie znaleziono aktywnego konta z podanym adresem e-mail.', 'warning');
                        return;
                    }

                    selectCustomer(user);
                } catch (error) {
                    setCustomerSearchMessage('Nie udało się połączyć z wyszukiwarką użytkowników.', 'danger');
                } finally {
                    customerSearchButton.disabled = false;
                }
            }

            function handleCustomerInput(event) {
                if (fillingCustomer || customerType.value !== 'account') {
                    return;
                }

                customerUserId.value = '';
                setCustomerSearchMessage('', 'muted');
            }

            function updateCustomerFields(clearValues = true) {
                const isGuest = customerType.value === 'guest';
                customerName.required = isGuest;
                customerSearchButton.classList.toggle('d-none', isGuest);

                if (clearValues) {
                    customerUserId.value = '';
                    customerName.value = '';
                    customerEmail.value = '';
                    customerPhone.value = '';
                }

                setCustomerSearchMessage('', 'muted');
                accountSearchHelp.classList.toggle('d-none', isGuest);
            }

            customerEmail.addEventListener('input', handleCustomerInput);
            customerSearchButton.addEventListener('click', searchCustomerByEmail);
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
            const staffErrorPanel = document.getElementById('staffReservationErrorPanel');
            const staffErrorText = document.getElementById('staffReservationErrorText');
            let editingReservationId = null;
            let staffReservations = [];
            let staffAvailabilityLoading = false;
            let staffAvailabilityError = null;
            let staffScheduleError = null;

            function setStaffReservationMessage(message = '', type = 'warning') {
                staffErrorText.textContent = message;
                staffErrorPanel.classList.toggle('d-none', !message);
                staffErrorPanel.classList.remove(
                    'alert-warning',
                    'alert-danger',
                    'alert-info'
                );
                staffErrorPanel.classList.add(`alert-${type}`);
            }

            function customerFieldsDiffer(user) {
                const nameDiffers = normalizeText(customerName.value)
                    && normalizeText(customerName.value) !== normalizeText(user.name);
                const phoneDiffers = normalizePhone(customerPhone.value)
                    && normalizePhone(customerPhone.value) !== normalizePhone(user.telephone);

                return nameDiffers || phoneDiffers;
            }

            function shouldConfirmCustomerMatch(user) {
                if (!user) {
                    return false;
                }

                if (customerType.value === 'guest') {
                    return true;
                }

                if (String(customerUserId.value) !== String(user.id)) {
                    return true;
                }

                return customerFieldsDiffer(user);
            }

            function showCustomerMatchModal(user) {
                pendingCustomerMatch = user;
                const differs = customerFieldsDiffer(user);

                setCustomerMatchText('customerMatchUserName', user.name);
                setCustomerMatchText('customerMatchUserEmail', user.email);
                setCustomerMatchText('customerMatchUserPhone', user.telephone);
                setCustomerMatchText('customerMatchFormName', customerName.value);
                setCustomerMatchText('customerMatchFormEmail', customerEmail.value);
                setCustomerMatchText('customerMatchFormPhone', customerPhone.value);
                customerMatchDifferenceAlert.classList.toggle('d-none', !differs);
                customerMatchModal.show();
            }

            async function handleStaffReservationSubmit(event) {
                const blockingMessage = staffReservationBlockingMessage();

                if (blockingMessage) {
                    event.preventDefault();
                    setStaffReservationMessage(blockingMessage, 'warning');
                    return;
                }

                if (skipCustomerMatchCheck) {
                    skipCustomerMatchCheck = false;
                    return;
                }

                const email = customerEmail.value.trim();
                const isAccountCustomer = customerType.value === 'account';

                if (isAccountCustomer && !email) {
                    event.preventDefault();
                    setStaffReservationMessage('Dla użytkownika z kontem wpisz e-mail i wyszukaj konto przed zapisem.', 'warning');
                    setCustomerSearchMessage('Dla użytkownika z kontem wpisz e-mail i wyszukaj konto przed zapisem.', 'warning');
                    customerEmail.focus();
                    return;
                }

                if (isAccountCustomer && !customerEmail.checkValidity()) {
                    event.preventDefault();
                    customerEmail.reportValidity();
                    setStaffReservationMessage('Wpisz poprawny adres e-mail użytkownika z kontem.', 'warning');
                    setCustomerSearchMessage('Wpisz poprawny adres e-mail użytkownika z kontem.', 'warning');
                    return;
                }

                if (!email) {
                    return;
                }

                event.preventDefault();
                staffSubmit.disabled = true;
                setCustomerSearchMessage('Sprawdzam adres e-mail w bazie użytkowników...', 'muted');

                try {
                    const user = await fetchCustomerByEmail(email);

                    if (isAccountCustomer && !user) {
                        setStaffReservationMessage('Nie znaleziono aktywnego konta z podanym adresem e-mail. Sprawdź adres albo wybierz gościa bez konta.', 'warning');
                        setCustomerSearchMessage('Nie znaleziono aktywnego konta z podanym adresem e-mail. Sprawdź adres albo wybierz gościa bez konta.', 'warning');
                        customerEmail.focus();
                        return;
                    }

                    if (isAccountCustomer && !customerUserId.value && user) {
                        showCustomerMatchModal(user);
                        return;
                    }

                    if (shouldConfirmCustomerMatch(user)) {
                        showCustomerMatchModal(user);
                        return;
                    }

                    skipCustomerMatchCheck = true;
                    staffForm.requestSubmit();
                } catch (error) {
                    setStaffReservationMessage('Nie udało się sprawdzić adresu e-mail w bazie użytkowników.', 'danger');
                    setCustomerSearchMessage('Nie udało się sprawdzić adresu e-mail w bazie użytkowników.', 'danger');
                } finally {
                    staffSubmit.disabled = false;
                }
            }

            function submitStaffFormWithoutCustomerAccount() {
                customerMatchModal.hide();
                pendingCustomerMatch = null;
                customerUserId.value = '';

                if (customerType.value === 'account') {
                    customerType.value = 'guest';
                    updateCustomerFields(false);
                }

                skipCustomerMatchCheck = true;
                staffForm.requestSubmit();
            }

            function submitStaffFormWithCustomerAccount() {
                if (!pendingCustomerMatch) {
                    return;
                }

                customerMatchModal.hide();
                customerType.value = 'account';
                selectCustomer(pendingCustomerMatch);
                pendingCustomerMatch = null;
                skipCustomerMatchCheck = true;
                staffForm.requestSubmit();
            }

            function staffReservationBlockingMessage() {
                if (staffAvailabilityLoading) {
                    return 'Trwa sprawdzanie dostępności. Poczekaj chwilę przed zapisem.';
                }

                if (staffScheduleError) {
                    return staffScheduleError;
                }

                if (staffAvailabilityError) {
                    return staffAvailabilityError;
                }

                if (!staffDate.value) {
                    return 'Wybierz datę rezerwacji.';
                }

                if (!staffLane.value) {
                    return 'Wybierz oś strzelecką.';
                }

                if (!staffStart.value || !staffEnd.value) {
                    return staffAvailability.textContent || 'Wybierz pełny zakres godzin rezerwacji.';
                }

                if (!staffPositions.value) {
                    return staffAvailability.textContent || 'Wybierz liczbę stanowisk.';
                }

                if (staffSubmit.disabled) {
                    return staffAvailability.textContent || 'Rezerwacja nie jest dostępna dla wybranych parametrów.';
                }

                return '';
            }

            staffForm.addEventListener('submit', handleStaffReservationSubmit);
            customerMatchKeepTypedButton.addEventListener('click', submitStaffFormWithoutCustomerAccount);
            customerMatchUseAccountButton.addEventListener('click', submitStaffFormWithCustomerAccount);

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

            function closingMinutes(value) {
                return value === '23:59'
                    ? 24 * 60
                    : timeMinutes(value);
            }

            function updateStaffEndTimes() {
                const date = parseDate(staffDate.value);
                const isoDay = date.getDay() === 0 ? 7 : date.getDay();
                const hours = adminCalendar.openingHours[isoDay];
                const previous = staffEnd.value;
                staffEnd.innerHTML = '';

                if (!hours || !staffStart.value) {
                    updateStaffPositions();
                    return;
                }

                const closing = closingMinutes(hours.closes_at);

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
                staffScheduleError = null;

                if (!hours) {
                    staffScheduleError = 'Strzelnica jest nieczynna w wybranym dniu.';
                    staffAvailability.className = 'small text-danger';
                    staffAvailability.textContent = staffScheduleError;
                    setStaffReservationMessage(staffScheduleError, 'warning');
                    staffSubmit.disabled = true;
                    return;
                }

                const now = new Date();
                const isToday = formatDate(date) === formatDate(now);
                const currentMinutes = (now.getHours() * 60) + now.getMinutes();
                const opening = timeMinutes(hours.opens_at);
                const closing = closingMinutes(hours.closes_at);

                for (
                    let minutes = opening;
                    minutes < closing;
                    minutes += 30
                ) {
                    if (!isToday || minutes > currentMinutes) {
                        addTimeOption(staffStart, minutes);
                    }
                }

                if (staffStart.options.length === 0) {
                    staffScheduleError = 'Brak dostępnych godzin rozpoczęcia w wybranym dniu.';
                    staffAvailability.className = 'small text-danger';
                    staffAvailability.textContent = staffScheduleError;
                    setStaffReservationMessage(staffScheduleError, 'warning');
                    staffSubmit.disabled = true;
                    return;
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

                if (!option || capacity < 1) {
                    const message = 'Brak dostępnej osi strzeleckiej do rezerwacji.';
                    staffAvailability.className = 'small text-danger';
                    staffAvailability.textContent = message;
                    setStaffReservationMessage(message, 'warning');
                    staffSubmit.disabled = true;
                    return;
                }

                if (staffAvailabilityLoading) {
                    staffAvailability.textContent = 'Sprawdzanie dostępności...';
                    staffSubmit.disabled = true;
                    return;
                }

                if (staffScheduleError) {
                    staffAvailability.className = 'small text-danger';
                    staffAvailability.textContent = staffScheduleError;
                    setStaffReservationMessage(staffScheduleError, 'warning');
                    staffSubmit.disabled = true;
                    return;
                }

                if (staffAvailabilityError) {
                    staffAvailability.className = 'small text-danger';
                    staffAvailability.textContent = staffAvailabilityError;
                    setStaffReservationMessage(staffAvailabilityError, 'danger');
                    staffSubmit.disabled = true;
                    return;
                }

                if (occupied === null) {
                    const message = !staffStart.value || !staffEnd.value
                        ? 'Wybierz pełny zakres godzin rezerwacji.'
                        : 'Wybierz oś i zakres godzin.';
                    staffAvailability.textContent = message;
                    setStaffReservationMessage(message, 'warning');
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
                    setStaffReservationMessage(
                        dynamicAvailable
                            ? ''
                            : 'Strzelanie dynamiczne wymaga dostępności wszystkich stanowisk osi.',
                        'warning'
                    );
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
                setStaffReservationMessage(
                    available > 0
                        ? ''
                        : 'Brak wolnych stanowisk w wybranym czasie.',
                    'warning'
                );
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
                setStaffReservationMessage('', 'warning');
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
                setStaffReservationMessage('', 'warning');
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

            async function restoreStaffReservationModal() {
                const restore = adminCalendar.staffReservationRestore;

                if (!restore) {
                    return;
                }

                const oldInput = adminCalendar.oldInput || {};

                if (restore.mode === 'edit' && restore.reservation_id) {
                    const reservation = adminCalendar.reservations.find(
                        item => Number(item.id) === Number(restore.reservation_id)
                    );

                    if (reservation) {
                        await configureStaffEdit(reservation);
                    } else {
                        await resetStaffForm();
                    }
                } else {
                    await resetStaffForm();
                }

                customerType.value = oldInput.customer_type || customerType.value;
                updateCustomerFields(false);
                customerUserId.value = oldInput.customer_user_id || '';
                customerName.value = oldInput.customer_name || '';
                customerEmail.value = oldInput.customer_email || '';
                customerPhone.value = oldInput.customer_phone || '';
                staffDate.value = oldInput.date || staffDate.value;
                staffLane.value = oldInput.line_id || staffLane.value;
                staffDynamic.checked = String(oldInput.is_dynamic_shooting || '0') === '1';
                staffNotes.value = oldInput.notes || '';

                updateStaffTimeOptions();

                if ([...staffStart.options].some(
                    option => option.value === oldInput.start_time
                )) {
                    staffStart.value = oldInput.start_time;
                }

                updateStaffEndTimes();

                if ([...staffEnd.options].some(
                    option => option.value === oldInput.end_time
                )) {
                    staffEnd.value = oldInput.end_time;
                }

                await loadStaffAvailability();

                if ([...staffPositions.options].some(
                    option => String(option.value) === String(oldInput.positions_count)
                )) {
                    staffPositions.value = oldInput.positions_count;
                }

                setStaffReservationMessage(
                    restore.message || 'Nie można zapisać rezerwacji dla wybranych parametrów.',
                    'warning'
                );

                bootstrap.Modal.getOrCreateInstance(staffModal).show();
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
            const deleteReservationButton = document.getElementById(
                'deleteReservationButton'
            );
            const deleteReservationForm = document.getElementById(
                'deleteReservationForm'
            );
            const deleteConfirmModalElement = document.getElementById(
                'deleteReservationConfirmModal'
            );
            const deleteConfirmModal = new bootstrap.Modal(deleteConfirmModalElement);
            const confirmDeleteReservationButton = document.getElementById(
                'confirmDeleteReservationButton'
            );
            let selectedReservation = null;

            function setDetailText(id, value) {
                document.getElementById(id).textContent = value || 'Brak danych';
            }

            deleteConfirmModalElement.addEventListener('shown.bs.modal', () => {
                document
                    .querySelectorAll('.modal-backdrop')
                    .forEach(backdrop => backdrop.classList.remove('reservation-delete-backdrop'));

                document
                    .querySelector('.modal-backdrop:last-of-type')
                    ?.classList.add('reservation-delete-backdrop');
            });

            deleteConfirmModalElement.addEventListener('hidden.bs.modal', () => {
                document
                    .querySelectorAll('.reservation-delete-backdrop')
                    .forEach(backdrop => backdrop.classList.remove('reservation-delete-backdrop'));

                confirmDeleteReservationButton.disabled = false;
            });

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
                deleteReservationForm.action = adminCalendar.deleteRouteTemplate.replace(
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

                editReservationButton.disabled = ! reservation.can_edit;
                editReservationButton.title = reservation.can_edit
                    ? ''
                    : 'Edycja jest dostępna tylko do zakończenia rezerwacji i przed ustawieniem statusu końcowego.';
                deleteReservationButton.classList.toggle(
                    'd-none',
                    ! reservation.can_delete
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
                const editModal = bootstrap.Modal.getInstance(staffModal)
                    || new bootstrap.Modal(staffModal);

                statusModal.addEventListener(
                    'hidden.bs.modal',
                    () => editModal.show(editReservationButton),
                    { once: true }
                );

                if (detailsModal) {
                    detailsModal.hide();
                } else {
                    editModal.show(editReservationButton);
                }
            });

            deleteReservationButton.addEventListener('click', () => {
                if (! selectedReservation?.can_delete) {
                    return;
                }

                setDetailText(
                    'deleteReservationCustomer',
                    selectedReservation.customer_name || selectedReservation.user_name || 'Brak danych'
                );
                setDetailText(
                    'deleteReservationDate',
                    `${selectedReservation.date} · ${selectedReservation.start_time}–${selectedReservation.end_time}`
                );
                setDetailText(
                    'deleteReservationLane',
                    [
                        selectedReservation.lane_name,
                        `${selectedReservation.positions_count} stanowisk`,
                        selectedReservation.is_dynamic_shooting
                            ? 'strzelanie dynamiczne'
                            : null
                    ].filter(Boolean).join(' · ')
                );

                const detailsModal = bootstrap.Modal.getInstance(statusModal);

                statusModal.addEventListener(
                    'hidden.bs.modal',
                    () => deleteConfirmModal.show(deleteReservationButton),
                    { once: true }
                );

                if (detailsModal) {
                    detailsModal.hide();
                } else {
                    deleteConfirmModal.show(deleteReservationButton);
                }
            });

            confirmDeleteReservationButton.addEventListener('click', () => {
                if (! selectedReservation?.can_delete) {
                    return;
                }

                confirmDeleteReservationButton.disabled = true;
                deleteReservationForm.submit();
            });

            statusSelect.addEventListener('change', updateCancellationField);
            restoreStaffReservationModal();
        });
    </script>
</x-app-layout>
