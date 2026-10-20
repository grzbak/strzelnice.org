<x-app-layout>
    @php
        $weekdays = [
            1 => 'Pon',
            2 => 'Wt',
            3 => 'Śr',
            4 => 'Czw',
            5 => 'Pt',
            6 => 'Sob',
            7 => 'Nd',
        ];

        $sort = request('sort', 'created_at');
        $direction = request('direction', 'desc');
        $sortUrl = function (string $column) use ($sort, $direction) {
            return request()->fullUrlWithQuery([
                'sort' => $column,
                'direction' => $sort === $column && $direction === 'asc' ? 'desc' : 'asc',
            ]);
        };
        $sortIcon = fn (string $column) => $sort === $column
            ? ($direction === 'asc' ? 'bi-sort-up' : 'bi-sort-down')
            : 'bi-arrow-down-up';
    @endphp

    <div class="page-bg-2"></div>

    <main class="container py-4 py-lg-5 reservation-payments-page">
        <header class="p-4 mb-4 text-white rounded bg-secondary">
            <div class="gap-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                <div>
                    <div class="gap-2 mb-2 d-flex align-items-center">
                        <i class="bi bi-credit-card-2-front fs-4"></i>
                        <span class="opacity-75 text-uppercase small">Panel strzelnicy</span>
                    </div>
                    <h1 class="mb-1 h3">Płatności rezerwacji</h1>
                    <p class="mb-0 opacity-75">{{ $shootingRange->name }}</p>
                </div>
                <div class="text-md-end small opacity-75">
                    <div>Konfiguruj Przelewy24, cenniki i rozliczenia płatności za rezerwacje.</div>
                </div>
            </div>
        </header>

        <x-session-alerts />

        @if ($managedRanges->count() > 1)
            <form method="GET" action="{{ route('shooting.reservation-payments.index') }}" class="p-3 mb-4 bg-white border rounded shadow-sm">
                <label for="shootingRangeSwitcher" class="form-label fw-semibold">Strzelnica</label>
                <div class="gap-2 d-flex">
                    <select id="shootingRangeSwitcher" name="shooting_range_id" class="form-select">
                        @foreach ($managedRanges as $managedRange)
                            <option value="{{ $managedRange->id }}" @selected($managedRange->id === $shootingRange->id)>
                                {{ $managedRange->name }}
                            </option>
                        @endforeach
                    </select>

                    <button type="submit" class="btn btn-danger">Pokaż</button>
                </div>
            </form>
        @endif

        <div class="row g-4">
            <div class="col-lg-5">
                <section class="p-3 bg-white border rounded shadow-sm h-100">
                    <div class="mb-3 d-flex align-items-start justify-content-between">
                        <div>
                            <h2 class="mb-1 h5">Konto Przelewy24</h2>
                            <p class="mb-0 text-muted small">Dane sprzedawcy przypisane do tej strzelnicy.</p>
                        </div>

                        <span class="badge {{ $paymentAccount->p24_enabled ? 'bg-success' : 'bg-secondary' }}">
                            {{ $paymentAccount->p24_enabled ? 'P24 włączone' : 'P24 wyłączone' }}
                        </span>
                    </div>

                    @if (! $priceRules->where('is_active', true)->count())
                        <div class="alert alert-warning small">
                            Przed włączeniem Przelewy24 dodaj przynajmniej jeden aktywny cennik rezerwacji.
                        </div>
                    @endif

                    @if ($paymentAccount->last_tested_at)
                        <div class="alert {{ $paymentAccount->last_test_success ? 'alert-success' : 'alert-warning' }} small">
                            Ostatni test:
                            <strong>{{ $paymentAccount->last_tested_at->format('Y-m-d H:i') }}</strong>
                            <br>
                            {{ $paymentAccount->last_test_message }}
                        </div>
                    @endif

                    <form
                        method="POST"
                        action="{{ route('shooting.reservation-payments.account') }}"
                        id="reservationP24AccountForm"
                    >
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="shooting_range_id" value="{{ $shootingRange->id }}">
                        <input type="hidden" name="force_save_without_p24" id="forceSaveWithoutP24" value="0">

                        <div class="mb-3 form-check form-switch">
                            <input type="hidden" name="p24_enabled" value="0">
                            <input
                                type="checkbox"
                                class="form-check-input"
                                id="p24Enabled"
                                name="p24_enabled"
                                value="1"
                                @checked($paymentAccount->p24_enabled)
                            >
                            <label class="form-check-label fw-semibold" for="p24Enabled">
                                Włącz płatności Przelewy24 dla rezerwacji
                            </label>
                        </div>

                        <div class="mb-3 form-check form-switch">
                            <input type="hidden" name="p24_sandbox" value="0">
                            <input
                                type="checkbox"
                                class="form-check-input"
                                id="p24Sandbox"
                                name="p24_sandbox"
                                value="1"
                                @checked($paymentAccount->p24_sandbox)
                            >
                            <label class="form-check-label" for="p24Sandbox">
                                Tryb testowy sandbox
                            </label>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="p24MerchantId">Merchant ID</label>
                                <input id="p24MerchantId" name="p24_merchant_id" class="form-control" value="{{ old('p24_merchant_id', $paymentAccount->p24_merchant_id) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="p24PosId">POS ID</label>
                                <input id="p24PosId" name="p24_pos_id" class="form-control" value="{{ old('p24_pos_id', $paymentAccount->p24_pos_id) }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="p24Crc">CRC</label>
                                <input id="p24Crc" name="p24_crc" class="form-control" value="{{ old('p24_crc', $paymentAccount->p24_crc) }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="p24ApiKey">API key</label>
                                <input id="p24ApiKey" name="p24_api_key" class="form-control" value="{{ old('p24_api_key', $paymentAccount->p24_api_key) }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="paymentCurrency">Waluta</label>
                                <input id="paymentCurrency" name="currency" maxlength="3" class="text-uppercase form-control" value="{{ old('currency', $paymentAccount->currency ?? 'PLN') }}">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label" for="reservationPaymentTimeLimit">
                                    Czas na opłacenie rezerwacji
                                </label>
                                <div class="input-group">
                                    <input
                                        id="reservationPaymentTimeLimit"
                                        name="reservation_payment_time_limit_minutes"
                                        type="number"
                                        min="5"
                                        max="120"
                                        step="1"
                                        class="form-control"
                                        value="{{ old('reservation_payment_time_limit_minutes', $paymentAccount->reservation_payment_time_limit_minutes ?? 15) }}"
                                    >
                                    <span class="input-group-text">min</span>
                                </div>
                                <div class="form-text">
                                    Przez ten czas termin pozostaje zablokowany podczas płatności online.
                                </div>
                            </div>
                        </div>

                        <div id="p24TestAlert" class="mt-3 d-none alert small"></div>

                        <div class="gap-2 mt-3 d-flex">
                            <button type="button" class="btn btn-outline-dark" id="testP24Button">
                                Testuj połączenie
                            </button>
                            <button type="submit" class="btn btn-danger">
                                Zapisz konfigurację
                            </button>
                        </div>
                    </form>
                </section>
            </div>

            <div class="col-lg-7">
                <section class="p-3 bg-white border rounded shadow-sm h-100">
                    <h2 class="mb-1 h5">Nowa pozycja cennika</h2>
                    <p class="mb-3 text-muted small">Cena podstawowa liczona jest za 1 stanowisko / 30 minut.</p>

                    @include('admin.reservation-payments.partials.price-rule-form', [
                        'action' => route('shooting.reservation-payments.price-rules.store'),
                        'method' => 'POST',
                        'rule' => null,
                        'shootingRange' => $shootingRange,
                        'weekdays' => $weekdays,
                    ])
                </section>
            </div>
        </div>

        <section class="p-3 mt-4 bg-white border rounded shadow-sm">
            <div class="mb-3 d-flex align-items-center justify-content-between">
                <div>
                    <h2 class="mb-1 h5">Cennik rezerwacji</h2>
                    <p class="mb-0 text-muted small">Reguły o niższym priorytecie liczbowym są ważniejsze.</p>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Nazwa</th>
                            <th>Oś</th>
                            <th>Dni / godziny</th>
                            <th>Cena</th>
                            <th>Status</th>
                            <th class="text-end">Akcje</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($priceRules as $rule)
                            <tr>
                                <td>
                                    <strong>{{ $rule->name }}</strong>
                                    <div class="small text-muted">Priorytet: {{ $rule->priority }}</div>
                                </td>
                                <td>{{ $rule->lane?->name ?? 'Wszystkie osie' }}</td>
                                <td>
                                    <div class="small">
                                        {{ collect($rule->weekdays ?: array_keys($weekdays))->map(fn ($day) => $weekdays[(int) $day] ?? null)->filter()->implode(', ') }}
                                    </div>
                                    <div class="small text-muted">
                                        {{ $rule->time_from ? substr($rule->time_from, 0, 5) : '00:00' }}
                                        -
                                        {{ $rule->time_to ? substr($rule->time_to, 0, 5) : '23:59' }}
                                    </div>
                                </td>
                                <td>
                                    <strong>{{ number_format((float) $rule->price_per_position_30min, 2, ',', ' ') }} {{ $rule->currency }}</strong>
                                    <div class="small text-muted">
                                        Dynamiczne:
                                        {{ $rule->dynamic_price_per_position_30min ? number_format((float) $rule->dynamic_price_per_position_30min, 2, ',', ' ') . ' ' . $rule->currency : 'jak podstawowa' }}
                                    </div>
                                </td>
                                <td>
                                    <span class="badge {{ $rule->is_active ? 'bg-success' : 'bg-secondary' }}">
                                        {{ $rule->is_active ? 'Aktywny' : 'Wyłączony' }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-dark" data-bs-toggle="modal" data-bs-target="#priceRuleModal{{ $rule->id }}">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <form method="POST" action="{{ route('shooting.reservation-payments.price-rules.destroy', $rule) }}" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="shooting_range_id" value="{{ $shootingRange->id }}">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Usunąć tę pozycję cennika?')">
                                            <i class="bi bi-trash3"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-muted">Brak cennika rezerwacji.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <div class="row g-4 mt-1">
            <div class="col-lg-5">
                <section class="p-3 bg-white border rounded shadow-sm">
                    <h2 class="mb-1 h5">Nowa promocja</h2>
                    <p class="mb-3 text-muted small">Promocje nakładają rabat na cenę wynikającą z cennika.</p>

                    @include('admin.reservation-payments.partials.promotion-form', [
                        'action' => route('shooting.reservation-payments.promotions.store'),
                        'method' => 'POST',
                        'promotion' => null,
                        'shootingRange' => $shootingRange,
                        'weekdays' => $weekdays,
                    ])
                </section>
            </div>

            <div class="col-lg-7">
                <section class="p-3 bg-white border rounded shadow-sm">
                    <h2 class="mb-3 h5">Promocje</h2>

                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Nazwa</th>
                                    <th>Rabat</th>
                                    <th>Zakres</th>
                                    <th>Status</th>
                                    <th class="text-end">Akcje</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($promotions as $promotion)
                                    <tr>
                                        <td>
                                            <strong>{{ $promotion->name }}</strong>
                                            <div class="small text-muted">{{ $promotion->lane?->name ?? 'Wszystkie osie' }}</div>
                                        </td>
                                        <td>
                                            {{ $promotion->discount_type === \App\Models\ReservationPricePromotion::TYPE_PERCENT ? $promotion->discount_value . '%' : number_format((float) $promotion->discount_value, 2, ',', ' ') . ' zł' }}
                                        </td>
                                        <td class="small">
                                            {{ $promotion->valid_from?->format('Y-m-d') ?? 'bez początku' }}
                                            -
                                            {{ $promotion->valid_to?->format('Y-m-d') ?? 'bez końca' }}
                                        </td>
                                        <td>
                                            <span class="badge {{ $promotion->is_active ? 'bg-success' : 'bg-secondary' }}">
                                                {{ $promotion->is_active ? 'Aktywna' : 'Wyłączona' }}
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <button class="btn btn-sm btn-outline-dark" data-bs-toggle="modal" data-bs-target="#promotionModal{{ $promotion->id }}">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                            <form method="POST" action="{{ route('shooting.reservation-payments.promotions.destroy', $promotion) }}" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="shooting_range_id" value="{{ $shootingRange->id }}">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Usunąć tę promocję?')">
                                                    <i class="bi bi-trash3"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-muted">Brak promocji.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>

        <section class="p-3 mt-4 bg-white border rounded shadow-sm">
            <div class="mb-3 d-flex flex-wrap gap-2 align-items-center justify-content-between">
                <div>
                    <h2 class="mb-1 h5">Płatności za rezerwacje</h2>
                    <p class="mb-0 text-muted small">Lista korzysta z głównej tabeli płatności powiązanej z rezerwacjami.</p>
                </div>
                <a href="{{ route('shooting.reservation-payments.export', request()->query() + ['shooting_range_id' => $shootingRange->id]) }}" class="btn btn-outline-danger">
                    <i class="bi bi-filetype-csv me-1"></i>
                    Eksport CSV
                </a>
            </div>

            <form method="GET" action="{{ route('shooting.reservation-payments.index') }}" class="row g-2 mb-3">
                <input type="hidden" name="shooting_range_id" value="{{ $shootingRange->id }}">
                <div class="col-md-3">
                    <input type="search" name="search" class="form-control" value="{{ request('search') }}" placeholder="Szukaj klienta, e-maila, ID P24...">
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select">
                        <option value="">Wszystkie statusy</option>
                        @foreach ($statusLabels as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="method" class="form-select">
                        <option value="">Wszystkie metody</option>
                        @foreach ($methodLabels as $value => $label)
                            <option value="{{ $value }}" @selected(request('method') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                </div>
                <div class="col-md-2">
                    <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-dark w-100">Filtruj</button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>
                                <a href="{{ $sortUrl('id') }}" class="text-decoration-none text-dark">
                                    ID <i class="bi {{ $sortIcon('id') }}"></i>
                                </a>
                            </th>
                            <th>
                                <a href="{{ $sortUrl('customer') }}" class="text-decoration-none text-dark">
                                    Klient <i class="bi {{ $sortIcon('customer') }}"></i>
                                </a>
                            </th>
                            <th>
                                <a href="{{ $sortUrl('reservation_start') }}" class="text-decoration-none text-dark">
                                    Termin <i class="bi {{ $sortIcon('reservation_start') }}"></i>
                                </a>
                            </th>
                            <th>
                                <a href="{{ $sortUrl('status') }}" class="text-decoration-none text-dark">
                                    Status <i class="bi {{ $sortIcon('status') }}"></i>
                                </a>
                            </th>
                            <th>
                                <a href="{{ $sortUrl('amount') }}" class="text-decoration-none text-dark">
                                    Kwota <i class="bi {{ $sortIcon('amount') }}"></i>
                                </a>
                            </th>
                            <th>
                                <a href="{{ $sortUrl('method') }}" class="text-decoration-none text-dark">
                                    Metoda <i class="bi {{ $sortIcon('method') }}"></i>
                                </a>
                            </th>
                            <th class="text-end">Akcje</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($payments as $payment)
                            @php($reservation = $payment->payable)
                            <tr>
                                <td>#{{ $payment->id }}</td>
                                <td>
                                    <strong>{{ $reservation?->customer_name ?? $payment->payer_name ?? '-' }}</strong>
                                    <div class="small text-muted">{{ $reservation?->customer_email ?? $payment->payer_email }}</div>
                                </td>
                                <td class="small">
                                    {{ $reservation?->start?->format('Y-m-d H:i') ?? '-' }}
                                    <br>
                                    {{ $reservation?->end?->format('Y-m-d H:i') ?? '-' }}
                                </td>
                                <td>
                                    <span class="badge {{ $statusBadgeClasses[$payment->status] ?? 'bg-secondary' }}">
                                        {{ $statusLabels[$payment->status] ?? $payment->status }}
                                    </span>
                                    @if ($payment->expires_at && $payment->status !== \App\Models\Payment::STATUS_PAID)
                                        <div class="small text-muted">do {{ $payment->expires_at->format('Y-m-d H:i') }}</div>
                                    @endif
                                </td>
                                <td>{{ number_format((float) $payment->amount, 2, ',', ' ') }} {{ $payment->currency }}</td>
                                <td>{{ $methodLabels[$payment->method] ?? $payment->method }}</td>
                                <td class="text-end">
                                    <div class="gap-1 d-flex justify-content-end flex-wrap">
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-dark reservation-payment-action"
                                            data-bs-toggle="modal"
                                            data-bs-target="#paymentDetailsModal{{ $payment->id }}"
                                            title="Szczegóły płatności"
                                        >
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        @if ($payment->method === 'p24' && $payment->status !== \App\Models\Payment::STATUS_PAID)
                                            <form method="POST" action="{{ route('shooting.reservation-payments.payments.resend-link', $payment) }}" class="d-inline">
                                                @csrf
                                                <input type="hidden" name="shooting_range_id" value="{{ $shootingRange->id }}">
                                                <button
                                                    type="submit"
                                                    class="btn btn-sm btn-outline-primary reservation-payment-action"
                                                    title="Wyślij ponownie link płatności"
                                                >
                                                    <i class="bi bi-send"></i>
                                                </button>
                                            </form>
                                        @endif
                                    @if (in_array($payment->status, [
                                        \App\Models\Payment::STATUS_CREATED,
                                        \App\Models\Payment::STATUS_REGISTERED,
                                        \App\Models\Payment::STATUS_PENDING,
                                    ], true) && in_array($payment->method, ['bank_transfer', 'cash'], true))
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-success reservation-payment-action"
                                            data-bs-toggle="modal"
                                            data-bs-target="#confirmPaymentModal{{ $payment->id }}"
                                            title="Rozlicz ręcznie"
                                        >
                                            <i class="bi bi-cash-coin"></i>
                                        </button>
                                    @endif
                                    @if ($payment->status === \App\Models\Payment::STATUS_REFUND_PENDING && in_array($payment->method, ['bank_transfer', 'cash'], true))
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-warning reservation-payment-action"
                                            data-bs-toggle="modal"
                                            data-bs-target="#confirmRefundModal{{ $payment->id }}"
                                            title="Potwierdź ręczny zwrot"
                                        >
                                            <i class="bi bi-arrow-return-left"></i>
                                        </button>
                                    @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-muted">Brak płatności za rezerwacje.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $payments->links() }}
        </section>
    </main>

    @foreach ($priceRules as $rule)
        <div class="modal fade" id="priceRuleModal{{ $rule->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title h5">Edycja cennika</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                    </div>
                    <div class="modal-body">
                        @include('admin.reservation-payments.partials.price-rule-form', [
                            'action' => route('shooting.reservation-payments.price-rules.update', $rule),
                            'method' => 'PATCH',
                            'rule' => $rule,
                            'shootingRange' => $shootingRange,
                            'weekdays' => $weekdays,
                        ])
                    </div>
                </div>
            </div>
        </div>
    @endforeach

    @foreach ($promotions as $promotion)
        <div class="modal fade" id="promotionModal{{ $promotion->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title h5">Edycja promocji</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                    </div>
                    <div class="modal-body">
                        @include('admin.reservation-payments.partials.promotion-form', [
                            'action' => route('shooting.reservation-payments.promotions.update', $promotion),
                            'method' => 'PATCH',
                            'promotion' => $promotion,
                            'shootingRange' => $shootingRange,
                            'weekdays' => $weekdays,
                        ])
                    </div>
                </div>
            </div>
        </div>
    @endforeach

    @foreach ($payments as $payment)
        @php($reservation = $payment->payable)
        <div class="modal fade" id="paymentDetailsModal{{ $payment->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <div class="small text-muted text-uppercase">Płatność za rezerwację</div>
                            <h2 class="modal-title h5">#{{ $payment->id }} · {{ $statusLabels[$payment->status] ?? $payment->status }}</h2>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="p-3 border rounded bg-light h-100">
                                    <h3 class="mb-3 h6">Dane płatności</h3>
                                    <dl class="mb-0 row small">
                                        <dt class="col-sm-5">Status</dt>
                                        <dd class="col-sm-7">
                                            <span class="badge {{ $statusBadgeClasses[$payment->status] ?? 'bg-secondary' }}">
                                                {{ $statusLabels[$payment->status] ?? $payment->status }}
                                            </span>
                                        </dd>
                                        <dt class="col-sm-5">Metoda</dt>
                                        <dd class="col-sm-7">{{ $methodLabels[$payment->method] ?? $payment->method }}</dd>
                                        <dt class="col-sm-5">Kwota</dt>
                                        <dd class="col-sm-7 fw-semibold">{{ number_format((float) $payment->amount, 2, ',', ' ') }} {{ $payment->currency }}</dd>
                                        <dt class="col-sm-5">Utworzono</dt>
                                        <dd class="col-sm-7">{{ $payment->created_at?->format('Y-m-d H:i') ?? '-' }}</dd>
                                        <dt class="col-sm-5">Zarejestrowano</dt>
                                        <dd class="col-sm-7">{{ $payment->registered_at?->format('Y-m-d H:i') ?? '-' }}</dd>
                                        <dt class="col-sm-5">Opłacono</dt>
                                        <dd class="col-sm-7">{{ $payment->verified_at?->format('Y-m-d H:i') ?? $payment->confirmed_at?->format('Y-m-d H:i') ?? '-' }}</dd>
                                        <dt class="col-sm-5">Termin płatności</dt>
                                        <dd class="col-sm-7">{{ $payment->expires_at?->format('Y-m-d H:i') ?? '-' }}</dd>
                                    </dl>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-3 border rounded bg-light h-100">
                                    <h3 class="mb-3 h6">Dane rezerwacji</h3>
                                    <dl class="mb-0 row small">
                                        <dt class="col-sm-5">Klient</dt>
                                        <dd class="col-sm-7">{{ $reservation?->customer_name ?? $payment->payer_name ?? '-' }}</dd>
                                        <dt class="col-sm-5">E-mail</dt>
                                        <dd class="col-sm-7 text-break">{{ $reservation?->customer_email ?? $payment->payer_email ?? '-' }}</dd>
                                        <dt class="col-sm-5">Telefon</dt>
                                        <dd class="col-sm-7">{{ $reservation?->customer_phone ?? '-' }}</dd>
                                        <dt class="col-sm-5">Termin</dt>
                                        <dd class="col-sm-7">
                                            @if ($reservation)
                                                {{ $reservation->start?->format('Y-m-d H:i') }} - {{ $reservation->end?->format('H:i') }}
                                            @else
                                                -
                                            @endif
                                        </dd>
                                        <dt class="col-sm-5">Oś</dt>
                                        <dd class="col-sm-7">{{ $reservation?->lane?->name ?? '-' }}</dd>
                                        <dt class="col-sm-5">Stanowiska</dt>
                                        <dd class="col-sm-7">{{ $reservation?->positions_count ?? '-' }}</dd>
                                    </dl>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="p-3 border rounded bg-white">
                                    <h3 class="mb-3 h6">Identyfikatory techniczne</h3>
                                    <dl class="mb-0 row small">
                                        <dt class="col-md-3">Session ID</dt>
                                        <dd class="col-md-9 text-break">{{ $payment->p24_session_id ?? '-' }}</dd>
                                        <dt class="col-md-3">Order ID P24</dt>
                                        <dd class="col-md-9">
                                            @if ($payment->p24_order_id && $payment->p24_panel_url)
                                                <a href="{{ $payment->p24_panel_url }}" target="_blank" rel="noopener">
                                                    {{ $payment->p24_order_id }}
                                                </a>
                                            @else
                                                {{ $payment->p24_order_id ?? '-' }}
                                            @endif
                                        </dd>
                                        <dt class="col-md-3">Notatka</dt>
                                        <dd class="col-md-9 text-break">{{ $payment->confirmation_note ?: $payment->note ?: '-' }}</dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        @if ($payment->method === 'p24' && $payment->status !== \App\Models\Payment::STATUS_PAID)
                            <form method="POST" action="{{ route('shooting.reservation-payments.payments.resend-link', $payment) }}" class="me-auto">
                                @csrf
                                <input type="hidden" name="shooting_range_id" value="{{ $shootingRange->id }}">
                                <button type="submit" class="btn btn-outline-primary">
                                    <i class="bi bi-send me-1"></i>
                                    Wyślij link ponownie
                                </button>
                            </form>
                        @endif
                        <button type="button" class="btn btn-dark" data-bs-dismiss="modal">Zamknij</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="confirmPaymentModal{{ $payment->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form method="POST" action="{{ route('shooting.reservation-payments.payments.confirm', $payment) }}" class="modal-content">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="shooting_range_id" value="{{ $shootingRange->id }}">
                    <div class="modal-header">
                        <h2 class="modal-title h5">Ręczne rozliczenie płatności</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2">Rozliczasz płatność #{{ $payment->id }} na kwotę <strong>{{ number_format((float) $payment->amount, 2, ',', ' ') }} {{ $payment->currency }}</strong>.</p>
                        <label class="form-label" for="confirmationNote{{ $payment->id }}">Notatka rozliczenia</label>
                        <textarea id="confirmationNote{{ $payment->id }}" name="confirmation_note" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-dark" data-bs-dismiss="modal">Anuluj</button>
                        <button type="submit" class="btn btn-success">Zatwierdź rozliczenie</button>
                    </div>
                </form>
            </div>
        </div>

        @if ($payment->status === \App\Models\Payment::STATUS_REFUND_PENDING && in_array($payment->method, ['bank_transfer', 'cash'], true))
            <div class="modal fade" id="confirmRefundModal{{ $payment->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <form method="POST" action="{{ route('shooting.reservation-payments.payments.confirm-refund', $payment) }}" class="modal-content">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="shooting_range_id" value="{{ $shootingRange->id }}">
                        <div class="modal-header">
                            <h2 class="modal-title h5">Potwierdzenie ręcznego zwrotu</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                        </div>
                        <div class="modal-body">
                            <p>
                                Płatność #{{ $payment->id }} ·
                                <strong>{{ number_format((float) $payment->amount, 2, ',', ' ') }} {{ $payment->currency }}</strong>
                            </p>
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <label for="refundedAmount{{ $payment->id }}" class="form-label">Zwrócona kwota</label>
                                    <input
                                        type="number"
                                        id="refundedAmount{{ $payment->id }}"
                                        name="refunded_amount"
                                        class="form-control"
                                        min="0.01"
                                        max="{{ number_format(($payment->refund_requested_amount_minor ?: $payment->amount_minor) / 100, 2, '.', '') }}"
                                        step="0.01"
                                        value="{{ number_format(($payment->refund_requested_amount_minor ?: $payment->amount_minor) / 100, 2, '.', '') }}"
                                        required
                                    >
                                </div>
                                <div class="col-sm-6">
                                    <label for="refundedAt{{ $payment->id }}" class="form-label">Data zwrotu</label>
                                    <input
                                        type="datetime-local"
                                        id="refundedAt{{ $payment->id }}"
                                        name="refunded_at"
                                        class="form-control"
                                        value="{{ now()->format('Y-m-d\TH:i') }}"
                                        max="{{ now()->format('Y-m-d\TH:i') }}"
                                        required
                                    >
                                </div>
                                <div class="col-12">
                                    <label for="refundNote{{ $payment->id }}" class="form-label">Potwierdzenie lub notatka</label>
                                    <textarea
                                        id="refundNote{{ $payment->id }}"
                                        name="confirmation_note"
                                        class="form-control"
                                        rows="3"
                                        maxlength="2000"
                                        placeholder="Np. numer przelewu zwrotnego albo informacja o zwrocie gotówki"
                                        required
                                    ></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-dark" data-bs-dismiss="modal">Anuluj</button>
                            <button type="submit" class="btn btn-warning">Potwierdź zwrot</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    @endforeach

    <style>
        .reservation-payment-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2rem;
            height: 2rem;
            padding: 0;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('reservationP24AccountForm');
            const testButton = document.getElementById('testP24Button');
            const alertBox = document.getElementById('p24TestAlert');
            const enabledInput = document.getElementById('p24Enabled');
            const forceInput = document.getElementById('forceSaveWithoutP24');
            let lastTestOk = {{ $paymentAccount->last_test_success ? 'true' : 'false' }};

            async function testP24() {
                alertBox.className = 'mt-3 alert alert-info small';
                alertBox.textContent = 'Trwa test połączenia z Przelewy24...';
                alertBox.classList.remove('d-none');

                const body = new FormData(form);
                const response = await fetch(@json(route('shooting.reservation-payments.test-p24')), {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': @json(csrf_token()),
                    },
                    body,
                });

                const result = await response.json();
                lastTestOk = Boolean(result.ok);
                alertBox.className = `mt-3 alert ${lastTestOk ? 'alert-success' : 'alert-warning'} small`;
                alertBox.textContent = result.message || (lastTestOk ? 'Połączenie działa poprawnie.' : 'Test połączenia nie powiódł się.');

                return lastTestOk;
            }

            testButton?.addEventListener('click', async () => {
                try {
                    await testP24();
                } catch (error) {
                    lastTestOk = false;
                    alertBox.className = 'mt-3 alert alert-warning small';
                    alertBox.textContent = 'Nie udało się wykonać testu Przelewy24.';
                    alertBox.classList.remove('d-none');
                }
            });

            form?.addEventListener('submit', async (event) => {
                forceInput.value = '0';

                if (! enabledInput.checked) {
                    return;
                }

                event.preventDefault();

                try {
                    const ok = await testP24();
                    if (ok) {
                        form.submit();
                        return;
                    }
                } catch (error) {
                    lastTestOk = false;
                }

                if (window.confirm('Dane Przelewy24 wyglądają na niepoprawne. Zapisać je mimo to bez włączania płatności P24?')) {
                    forceInput.value = '1';
                    form.submit();
                }
            });
        });
    </script>
</x-app-layout>
