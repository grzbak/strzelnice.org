<x-app-layout>
    <x-session-alerts />

    <div class="page-bg-2"></div>

    @php
        $orderColumns = [
            ['key' => 'shooting_range', 'label' => 'Strzelnica', 'sortable' => true, 'class' => 'subscription-range-column'],
            ['key' => 'package', 'label' => 'Pakiet', 'sortable' => true],
            ['key' => 'amount_gross', 'label' => 'Kwota', 'sortable' => true],
            ['key' => 'payment_status', 'label' => 'Status płatności', 'sortable' => true],
            ['key' => 'payment_method', 'label' => 'Forma płatności', 'sortable' => true],
            ['key' => 'payment_due_at', 'label' => 'Termin', 'sortable' => true],
            ['key' => 'actions', 'label' => 'Akcje', 'class' => 'subscription-actions-column'],
        ];

        $rangeColumns = [
            ['key' => 'name', 'label' => 'Strzelnica'],
            ['key' => 'address', 'label' => 'Adres'],
            ['key' => 'actions', 'label' => 'Akcja', 'class' => 'subscription-picker-action-column'],
        ];

        $paymentStatusOptions = [
            \App\Models\PackageOrder::PAYMENT_PENDING => 'Oczekuje',
            \App\Models\PackageOrder::PAYMENT_PAID => 'Opłacone',
            \App\Models\PackageOrder::PAYMENT_FAILED => 'Nieopłacone / wygasłe',
            \App\Models\PackageOrder::PAYMENT_CANCELLED => 'Anulowane',
        ];

        $paymentMethodOptions = [
            \App\Models\PackageOrder::METHOD_P24 => 'Przelewy24',
            \App\Models\PackageOrder::METHOD_BANK_TRANSFER => 'Przelew',
            \App\Models\PackageOrder::METHOD_CASH => 'Gotówka',
            \App\Models\PackageOrder::METHOD_FREE => 'Bez płatności',
        ];

        $dueStateOptions = [
            'on_time' => 'W terminie',
            'overdue' => 'Przeterminowane',
        ];

        $manualOldRange = $shootingRanges->firstWhere('id', (int) old('shooting_range_id'));
        $manualOldPackageId = (int) old('package_id');
    @endphp

    <div class="container py-4">
        <header class="p-4 text-white rounded bg-secondary">
            <div class="gap-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                <div>
                    <div class="gap-2 mb-2 d-flex align-items-center">
                        <i class="bi bi-cash-coin fs-4"></i>
                        <span class="opacity-75 text-uppercase small">Panel superadmina</span>
                    </div>

                    <h1 class="mb-0 h3">Abonamenty strzelnic</h1>
                </div>

                <button
                    type="button"
                    class="btn btn-light subscription-header-action"
                    data-bs-toggle="modal"
                    data-bs-target="#manualSubscriptionOrderModal"
                >
                    <i class="bi bi-plus-circle" aria-hidden="true"></i>
                    <span>Nowe zamówienie</span>
                </button>
            </div>
        </header>

        <div class="p-3 mt-3 bg-white rounded shadow-sm subscription-filters-panel">
            <form method="GET" action="{{ route('admin.subscription-orders.index') }}" data-orders-filter-form>
                <div class="row g-3 align-items-end">
                    <div class="col-lg-4">
                        <label class="form-label small text-muted" for="orders-search">Wyszukaj zamówienie</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
                            <input
                                id="orders-search"
                                type="search"
                                name="search"
                                class="form-control"
                                value="{{ $search }}"
                                placeholder="Strzelnica, adres, pakiet, status..."
                                data-orders-search
                            >
                        </div>
                    </div>

                    <div class="col-sm-6 col-lg-2">
                        <label class="form-label small text-muted" for="orders-sort">Sortowanie</label>
                        <select id="orders-sort" name="sort" class="form-select" data-orders-filter>
                            <option value="created_at" @selected($sort === 'created_at')>Data dodania</option>
                            <option value="shooting_range" @selected($sort === 'shooting_range')>Strzelnica</option>
                            <option value="package" @selected($sort === 'package')>Pakiet</option>
                            <option value="amount_gross" @selected($sort === 'amount_gross')>Kwota</option>
                            <option value="payment_status" @selected($sort === 'payment_status')>Status płatności</option>
                            <option value="payment_method" @selected($sort === 'payment_method')>Forma płatności</option>
                            <option value="payment_due_at" @selected($sort === 'payment_due_at')>Termin płatności</option>
                            <option value="starts_at" @selected($sort === 'starts_at')>Początek abonamentu</option>
                        </select>
                    </div>

                    <div class="col-sm-6 col-lg-2">
                        <label class="form-label small text-muted" for="orders-direction">Kierunek</label>
                        <select id="orders-direction" name="direction" class="form-select" data-orders-filter>
                            <option value="desc" @selected($direction === 'desc')>Malejąco</option>
                            <option value="asc" @selected($direction === 'asc')>Rosnąco</option>
                        </select>
                    </div>

                    <div class="col-sm-6 col-lg-2">
                        <label class="form-label small text-muted" for="orders-per-page">Wiersze</label>
                        <select id="orders-per-page" name="per_page" class="form-select" data-orders-filter>
                            @foreach ([10, 20, 50] as $option)
                                <option value="{{ $option }}" @selected((int) $perPage === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-sm-6 col-lg-2">
                        <a href="{{ route('admin.subscription-orders.index') }}" class="btn btn-outline-secondary w-100">
                            <i class="bi bi-x-lg me-1" aria-hidden="true"></i>
                            Wyczyść
                        </a>
                    </div>
                </div>

                <div class="pt-3 mt-3 border-top row g-3">
                    <div class="col-lg-5">
                        <div class="mb-2 small text-muted">Status płatności</div>
                        <div class="gap-2 d-flex flex-wrap">
                            @foreach ($paymentStatusOptions as $value => $label)
                                <label class="form-check subscription-filter-check">
                                    <input
                                        type="checkbox"
                                        name="payment_statuses[]"
                                        value="{{ $value }}"
                                        class="form-check-input"
                                        data-orders-filter
                                        @checked(in_array($value, $paymentStatuses, true))
                                    >
                                    <span class="form-check-label">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="mb-2 small text-muted">Forma płatności</div>
                        <div class="gap-2 d-flex flex-wrap">
                            @foreach ($paymentMethodOptions as $value => $label)
                                <label class="form-check subscription-filter-check">
                                    <input
                                        type="checkbox"
                                        name="payment_methods[]"
                                        value="{{ $value }}"
                                        class="form-check-input"
                                        data-orders-filter
                                        @checked(in_array($value, $paymentMethods, true))
                                    >
                                    <span class="form-check-label">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="col-lg-3">
                        <div class="mb-2 small text-muted">Termin płatności</div>
                        <div class="gap-2 d-flex flex-wrap">
                            @foreach ($dueStateOptions as $value => $label)
                                <label class="form-check subscription-filter-check">
                                    <input
                                        type="checkbox"
                                        name="due_states[]"
                                        value="{{ $value }}"
                                        class="form-check-input"
                                        data-orders-filter
                                        @checked(in_array($value, $dueStates, true))
                                    >
                                    <span class="form-check-label">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <x-data-table
            id="subscription-orders-table"
            :action="route('admin.subscription-orders.index')"
            :columns="$orderColumns"
            :paginator="$orders"
            :search="$search"
            :sort="$sort"
            :direction="$direction"
            :per-page="$perPage"
            :per-page-options="[10, 20, 50]"
            :searchable="false"
        >
            @forelse ($orders as $order)
                @php
                    $isPaymentOpen = ! in_array($order->payment_status, [
                        \App\Models\PackageOrder::PAYMENT_PAID,
                        \App\Models\PackageOrder::PAYMENT_CANCELLED,
                    ], true) && $order->status !== \App\Models\PackageOrder::STATUS_CANCELLED;

                    $isOverdue = $isPaymentOpen && $order->payment_due_at?->isPast();
                    $isOnTime = $isPaymentOpen && (! $order->payment_due_at || ! $order->payment_due_at->isPast());
                @endphp

                <tr class="align-middle">
                    <td data-label="Strzelnica">
                        <strong>{{ $order->shootingRange?->name ?? '-' }}</strong>
                        <div class="small text-muted subscription-range-address">
                            {{ $order->shootingRange?->full_address ?: 'Brak adresu' }}
                        </div>
                    </td>

                    <td data-label="Pakiet">{{ $order->package?->display_name ?? 'Pakiet usunięty' }}</td>
                    <td data-label="Kwota">{{ number_format((float) $order->amount_gross, 2, ',', ' ') }} {{ $order->currency }}</td>

                    <td data-label="Status płatności">
                        <span class="badge bg-{{ $order->payment_status_badge_class }}">
                            {{ $order->payment_status_label }}
                        </span>
                    </td>

                    <td data-label="Forma płatności">{{ $order->payment_method_label }}</td>

                    <td data-label="Termin">
                        <div>{{ $order->payment_due_at?->format('Y-m-d H:i') ?? '-' }}</div>
                        @if ($isOverdue)
                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Przeterminowane</span>
                        @elseif ($isOnTime)
                            <span class="badge bg-success-subtle text-success border border-success-subtle">W terminie</span>
                        @endif
                    </td>

                    <td data-label="Akcje">
                        <div class="subscription-actions">
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-dark data-table-icon-button"
                                data-subscription-details-target="#subscriptionOrderDetailsModal{{ $order->id }}"
                                data-bs-toggle="tooltip"
                                data-bs-title="Szczegóły zamówienia"
                                aria-label="Szczegóły zamówienia"
                            >
                                <i class="bi bi-info-circle" aria-hidden="true"></i>
                            </button>

                            @if ($order->status !== \App\Models\PackageOrder::STATUS_CANCELLED && $order->payment_status !== \App\Models\PackageOrder::PAYMENT_PAID)
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-success data-table-icon-button manual-payment-button"
                                    data-action="{{ route('admin.subscription-orders.confirm-payment', $order) }}"
                                    data-order-name="{{ $order->shootingRange?->name ?? 'Strzelnica' }}"
                                    data-order-package="{{ $order->package?->display_name ?? 'Pakiet usunięty' }}"
                                    data-payment-method="{{ $order->payment_method === \App\Models\PackageOrder::METHOD_P24 ? \App\Models\PackageOrder::METHOD_BANK_TRANSFER : $order->payment_method }}"
                                    data-manual-reason="{{ $order->manual_reason }}"
                                    data-internal-note="{{ $order->internal_note }}"
                                    data-bs-toggle="tooltip"
                                    data-bs-title="Rozlicz manualnie"
                                    aria-label="Rozlicz manualnie"
                                >
                                    <i class="bi bi-check-circle" aria-hidden="true"></i>
                                </button>

                                @if ($order->payment_method === \App\Models\PackageOrder::METHOD_P24)
                                    <form method="POST" action="{{ route('admin.subscription-orders.change-method', $order) }}" class="m-0">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="payment_method" value="bank_transfer">
                                        <input type="hidden" name="payment_note" value="Zmiana P24 na przelew tradycyjny">
                                        <button
                                            class="btn btn-sm btn-outline-secondary data-table-icon-button"
                                            data-bs-toggle="tooltip"
                                            data-bs-title="Zmień na przelew tradycyjny"
                                            aria-label="Zmień na przelew tradycyjny"
                                        >
                                            <i class="bi bi-bank" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                @endif

                                <form
                                    method="POST"
                                    action="{{ route('admin.subscription-orders.destroy', $order) }}"
                                    class="m-0 subscription-order-delete-form"
                                    data-message="Usunąć nieopłacone zamówienie abonamentu dla strzelnicy {{ $order->shootingRange?->name }}? Zamówienie zniknie z panelu admina strzelnicy, a powiązany przedział zostanie zablokowany."
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-danger subscription-order-delete-button data-table-icon-button"
                                        data-bs-toggle="tooltip"
                                        data-bs-title="Usuń nieopłacone zamówienie"
                                        aria-label="Usuń nieopłacone zamówienie"
                                    >
                                        <i class="bi bi-trash3" aria-hidden="true"></i>
                                    </button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($orderColumns) }}" class="py-4 text-center text-muted">
                        @if ($search !== '' || $paymentStatuses !== [] || $paymentMethods !== [] || $dueStates !== [])
                            Brak zamówień pasujących do wybranych filtrów.
                        @else
                            Brak zamówień.
                        @endif
                    </td>
                </tr>
            @endforelse
        </x-data-table>
    </div>

    <div class="modal fade" id="manualSubscriptionOrderModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <form method="POST" action="{{ route('admin.subscription-orders.manual-store') }}" class="modal-content">
                @csrf
                <input type="hidden" name="shooting_range_id" id="manualShootingRangeId" value="{{ old('shooting_range_id') }}">

                <div class="modal-header">
                    <h2 class="modal-title h5">Nowe zamówienie specjalne</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Strzelnica</label>
                        <div class="p-3 border rounded bg-light">
                            <div id="manualShootingRangeSummary">
                                @if ($manualOldRange)
                                    <strong>{{ $manualOldRange->name }}</strong>
                                    <div class="small text-muted">{{ $manualOldRange->full_address ?: 'Brak adresu' }}</div>
                                @else
                                    <span class="text-muted">Nie wybrano strzelnicy.</span>
                                @endif
                            </div>

                            <button type="button" class="mt-2 btn btn-outline-dark btn-sm" id="openShootingRangePicker">
                                <i class="bi bi-search me-1" aria-hidden="true"></i>
                                <span>{{ $manualOldRange ? 'Zamień strzelnicę' : 'Wybierz strzelnicę' }}</span>
                            </button>
                        </div>
                        <x-input-error :messages="$errors->get('shooting_range_id')" class="mt-1" />
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="manualPackageId">Pakiet</label>
                            <select name="package_id" id="manualPackageId" class="form-select" required @disabled(! $manualOldRange)>
                                <option value="">Najpierw wybierz strzelnicę</option>
                            </select>
                            <x-input-error :messages="$errors->get('package_id')" class="mt-1" />
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="manualPaymentMethod">Forma płatności</label>
                            <select name="payment_method" id="manualPaymentMethod" class="form-select" required>
                                <option value="bank_transfer" @selected(old('payment_method') === 'bank_transfer')>Przelew tradycyjny</option>
                                <option value="cash" @selected(old('payment_method') === 'cash')>Gotówka</option>
                                <option value="free" @selected(old('payment_method') === 'free')>Bezpłatnie / 0 zł</option>
                            </select>
                            <x-input-error :messages="$errors->get('payment_method')" class="mt-1" />
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="manualAmountGross">Kwota</label>
                            <input id="manualAmountGross" type="number" step="0.01" min="0" name="amount_gross" class="form-control" value="{{ old('amount_gross') }}">
                            <x-input-error :messages="$errors->get('amount_gross')" class="mt-1" />
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="manualStartsAt">Start</label>
                            <input id="manualStartsAt" type="date" name="starts_at" class="form-control" value="{{ old('starts_at') }}">
                            <x-input-error :messages="$errors->get('starts_at')" class="mt-1" />
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="manualReason">Powód / ustalenia</label>
                            <textarea id="manualReason" name="manual_reason" class="form-control" rows="3" required>{{ old('manual_reason') }}</textarea>
                            <x-input-error :messages="$errors->get('manual_reason')" class="mt-1" />
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="manualNotes">Notatka</label>
                            <textarea id="manualNotes" name="notes" class="form-control" rows="3">{{ old('notes') }}</textarea>
                            <x-input-error :messages="$errors->get('notes')" class="mt-1" />
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-dark" data-bs-dismiss="modal">Anuluj</button>
                    <button class="btn btn-danger">
                        <i class="bi bi-plus-circle me-1" aria-hidden="true"></i>
                        Utwórz zamówienie
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="shootingRangePickerModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h5">Wybierz strzelnicę do abonamentu</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3 input-group">
                        <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
                        <input type="search" class="form-control" id="shootingRangePickerSearch" placeholder="Szukaj po nazwie, adresie, mieście...">
                    </div>

                    <x-data-table
                        id="subscription-range-picker-table"
                        :columns="$rangeColumns"
                        :searchable="false"
                        class="mt-0 subscription-picker-table"
                    >
                        @foreach ($shootingRanges as $shootingRange)
                            @php
                                $rangeSearchText = \Illuminate\Support\Str::lower(trim(
                                    ($shootingRange->name ?? '') . ' ' .
                                    ($shootingRange->full_address ?? '') . ' ' .
                                    ($shootingRange->town ?? '') . ' ' .
                                    ($shootingRange->voivodeship ?? '')
                                ));
                            @endphp

                            <tr class="subscription-range-picker-row" data-search="{{ $rangeSearchText }}">
                                <td data-label="Strzelnica"><strong>{{ $shootingRange->name }}</strong></td>
                                <td data-label="Adres"><span class="text-muted">{{ $shootingRange->full_address ?: '-' }}</span></td>
                                <td data-label="Akcja">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-danger choose-subscription-range"
                                        data-id="{{ $shootingRange->id }}"
                                        data-name="{{ $shootingRange->name }}"
                                        data-address="{{ $shootingRange->full_address }}"
                                    >
                                        Wybierz
                                    </button>
                                </td>
                            </tr>
                        @endforeach

                        <tr class="d-none" id="subscriptionRangePickerEmpty">
                            <td colspan="{{ count($rangeColumns) }}" class="py-4 text-center text-muted">
                                Brak strzelnic pasujących do wyszukiwania.
                            </td>
                        </tr>
                    </x-data-table>
                </div>
            </div>
        </div>
    </div>

    @foreach ($orders as $order)
        <div class="modal fade" id="subscriptionOrderDetailsModal{{ $order->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title h5">Szczegóły zamówienia abonamentu</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                    </div>
                    <div class="modal-body">
                        @php($p24Payment = $order->payments->first(fn ($payment) => $payment->p24_order_id))

                        <dl class="row mb-0">
                            <dt class="col-sm-4">Strzelnica</dt>
                            <dd class="col-sm-8">{{ $order->shootingRange?->name ?? '-' }}</dd>

                            <dt class="col-sm-4">Pakiet</dt>
                            <dd class="col-sm-8">{{ $order->package?->display_name ?? 'Pakiet usunięty' }}</dd>

                            <dt class="col-sm-4">Okres</dt>
                            <dd class="col-sm-8">{{ $order->starts_at?->format('Y-m-d') }} - {{ $order->ends_at?->format('Y-m-d') }}</dd>

                            <dt class="col-sm-4">Kwota</dt>
                            <dd class="col-sm-8">{{ number_format((float) $order->amount_gross, 2, ',', ' ') }} {{ $order->currency }}</dd>

                            <dt class="col-sm-4">Status zamówienia</dt>
                            <dd class="col-sm-8">{{ $order->status_label }}</dd>

                            <dt class="col-sm-4">Status płatności</dt>
                            <dd class="col-sm-8">{{ $order->payment_status_label }}</dd>

                            <dt class="col-sm-4">Forma płatności</dt>
                            <dd class="col-sm-8">{{ $order->payment_method_label }}</dd>

                            @if ($p24Payment)
                                <dt class="col-sm-4">ID transakcji P24</dt>
                                <dd class="col-sm-8">
                                    @if ($p24Payment->p24_panel_url)
                                        <a href="{{ $p24Payment->p24_panel_url }}" target="_blank" rel="noopener" class="link-danger fw-semibold">
                                            #{{ $p24Payment->p24_order_id }}
                                        </a>
                                    @else
                                        <span class="fw-semibold">#{{ $p24Payment->p24_order_id }}</span>
                                    @endif
                                </dd>
                            @endif

                            <dt class="col-sm-4">Termin płatności</dt>
                            <dd class="col-sm-8">{{ $order->payment_due_at?->format('Y-m-d H:i') ?? '-' }}</dd>

                            <dt class="col-sm-4">Przedział aktywności</dt>
                            <dd class="col-sm-8">
                                @if ($order->interval)
                                    #{{ $order->interval->id }}:
                                    {{ $order->interval->start_date?->format('Y-m-d') }}
                                    -
                                    {{ $order->interval->end_date?->format('Y-m-d') }}
                                @else
                                    <span class="text-muted">Brak powiązanego przedziału.</span>
                                @endif
                            </dd>

                            <dt class="col-sm-4">Notatka zamawiającego</dt>
                            <dd class="col-sm-8">
                                @if (filled($order->notes))
                                    <div class="p-2 border rounded bg-light text-break">{{ $order->notes }}</div>
                                @else
                                    <span class="text-muted">Brak notatki.</span>
                                @endif
                            </dd>

                            <dt class="col-sm-4">Powód / ustalenia manualne</dt>
                            <dd class="col-sm-8">
                                @if (filled($order->manual_reason))
                                    <div class="p-2 border rounded bg-light text-break">{{ $order->manual_reason }}</div>
                                @else
                                    <span class="text-muted">Brak.</span>
                                @endif
                            </dd>

                            <dt class="col-sm-4">Notatka płatności</dt>
                            <dd class="col-sm-8">
                                @if (filled($order->payment_note))
                                    <div class="p-2 border rounded bg-light text-break">{{ $order->payment_note }}</div>
                                @else
                                    <span class="text-muted">Brak.</span>
                                @endif
                            </dd>

                            <dt class="col-sm-4">Notatka wewnętrzna</dt>
                            <dd class="col-sm-8">
                                @if (filled($order->internal_note))
                                    <div class="p-2 border rounded bg-light text-break">{{ $order->internal_note }}</div>
                                @else
                                    <span class="text-muted">Brak.</span>
                                @endif
                            </dd>
                        </dl>

                        <h3 class="mt-4 mb-3 h6">Historia wpłat</h3>

                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>P24</th>
                                        <th>Status</th>
                                        <th>Kwota</th>
                                        <th>Operator</th>
                                        <th>Utworzono</th>
                                        <th>Potwierdzono</th>
                                        <th>Notatka</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($order->payments as $payment)
                                        <tr>
                                            <td>#{{ $payment->id }}</td>
                                            <td>
                                                @if ($payment->p24_order_id)
                                                    @if ($payment->p24_panel_url)
                                                        <a href="{{ $payment->p24_panel_url }}" target="_blank" rel="noopener" class="link-danger fw-semibold">
                                                            #{{ $payment->p24_order_id }}
                                                        </a>
                                                    @else
                                                        <span class="fw-semibold">#{{ $payment->p24_order_id }}</span>
                                                    @endif
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>{{ $payment->status }}</td>
                                            <td>{{ number_format((float) $payment->amount, 2, ',', ' ') }} {{ $payment->currency }}</td>
                                            <td>{{ $payment->provider }} / {{ $payment->method }}</td>
                                            <td>{{ $payment->created_at?->format('Y-m-d H:i') }}</td>
                                            <td>{{ $payment->confirmed_at?->format('Y-m-d H:i') ?? '-' }}</td>
                                            <td>{{ $payment->confirmation_note ?: ($payment->note ?: '-') }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="text-muted">Brak wpłat dla tego zamówienia.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <h3 class="mt-4 mb-3 h6">Historia wpływu na przedział</h3>

                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead>
                                    <tr>
                                        <th>Data</th>
                                        <th>Zdarzenie</th>
                                        <th>Źródło</th>
                                        <th>Zakres abonamentu</th>
                                        <th>Zakres przedziału po zmianie</th>
                                        <th>Użytkownik</th>
                                        <th>Notatka</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($order->interval?->events?->where('package_order_id', $order->id)->sortByDesc('created_at') ?? collect() as $event)
                                        <tr>
                                            <td>{{ $event->created_at?->format('Y-m-d H:i') }}</td>
                                            <td>{{ $event->type_label }}</td>
                                            <td>{{ $event->source_label }}</td>
                                            <td>
                                                {{ $event->date_from?->format('Y-m-d') ?? '-' }}
                                                -
                                                {{ $event->date_to?->format('Y-m-d') ?? '-' }}
                                            </td>
                                            <td>
                                                {{ $event->new_start_date?->format('Y-m-d') ?? '-' }}
                                                -
                                                {{ $event->new_end_date?->format('Y-m-d') ?? '-' }}
                                            </td>
                                            <td>{{ $event->createdBy?->name ?? '-' }}</td>
                                            <td>{{ $event->note ?: '-' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-muted">Brak zdarzeń przedziału dla tego zamówienia.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-dark" data-bs-dismiss="modal">Zamknij</button>
                        @if (in_array($order->id, $payableOrderIds, true))
                            <a href="{{ route('subscription-orders.pay', $order) }}" class="btn btn-danger">
                                <i class="bi bi-credit-card-2-front me-1"></i>
                                Opłać przez P24
                            </a>
                        @endif
                        @if ($order->status !== \App\Models\PackageOrder::STATUS_CANCELLED && $order->payment_status !== \App\Models\PackageOrder::PAYMENT_PAID)
                            <button
                                type="button"
                                class="btn btn-outline-success manual-payment-button"
                                data-action="{{ route('admin.subscription-orders.confirm-payment', $order) }}"
                                data-order-name="{{ $order->shootingRange?->name ?? 'Strzelnica' }}"
                                data-order-package="{{ $order->package?->display_name ?? 'Pakiet usunięty' }}"
                                data-payment-method="{{ $order->payment_method === \App\Models\PackageOrder::METHOD_P24 ? \App\Models\PackageOrder::METHOD_BANK_TRANSFER : $order->payment_method }}"
                                data-manual-reason="{{ $order->manual_reason }}"
                                data-internal-note="{{ $order->internal_note }}"
                            >
                                <i class="bi bi-check-circle me-1"></i>
                                Rozlicz manualnie
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endforeach

    <div class="modal fade" id="manualPaymentModal" tabindex="-1" aria-labelledby="manualPaymentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <form method="POST" action="#" class="modal-content" data-manual-payment-form>
                @csrf
                @method('PATCH')

                <div class="modal-header">
                    <h2 class="modal-title h5" id="manualPaymentModalLabel">Rozliczenie manualne płatności</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3 p-3 border rounded bg-light">
                        <div class="text-muted small">Zamówienie</div>
                        <div class="fw-semibold" data-manual-payment-summary>Wybierz zamówienie do rozliczenia.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="manualSettlementPaymentMethod">Forma płatności</label>
                        <select id="manualSettlementPaymentMethod" name="payment_method" class="form-select" required>
                            <option value="{{ \App\Models\PackageOrder::METHOD_CASH }}">Gotówka</option>
                            <option value="{{ \App\Models\PackageOrder::METHOD_BANK_TRANSFER }}">Przelew tradycyjny</option>
                            <option value="{{ \App\Models\PackageOrder::METHOD_FREE }}">Bezpłatnie</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="manualSettlementReason">Powód / ustalenia</label>
                        <textarea
                            id="manualSettlementReason"
                            name="manual_reason"
                            class="form-control"
                            rows="3"
                            placeholder="Np. rabat indywidualny, rozliczenie po kontakcie telefonicznym, miesiąc promocyjny."
                        ></textarea>
                        <div class="form-text">Ta informacja opisuje podstawę rozliczenia zamówienia.</div>
                    </div>

                    <div class="mb-0">
                        <label class="form-label" for="manualSettlementInternalNote">Notatka wewnętrzna</label>
                        <textarea
                            id="manualSettlementInternalNote"
                            name="internal_note"
                            class="form-control"
                            rows="3"
                            placeholder="Opcjonalna notatka widoczna tylko w panelu superadmina."
                        ></textarea>
                        <div class="form-text">Nie będzie pokazywana adminowi strzelnicy w szczegółach abonamentu.</div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-dark" data-bs-dismiss="modal">Anuluj</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle me-1" aria-hidden="true"></i>
                        Zatwierdź i rozlicz
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="deleteSubscriptionOrderModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h5">Usuń zamówienie abonamentu</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0" data-delete-subscription-order-message></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-dark" data-bs-dismiss="modal">Anuluj</button>
                    <button type="button" class="btn btn-danger" data-delete-subscription-order-confirm>
                        Usuń zamówienie
                    </button>
                </div>
            </div>
        </div>
    </div>

    <style>
        .subscription-header-action {
            align-items: center;
            display: inline-flex;
            gap: .45rem;
        }

        .subscription-filters-panel {
            border: 1px solid rgba(52, 58, 64, .12);
        }

        .subscription-filters-panel .input-group-text {
            border-color: #adb5bd;
            background: #343a40;
            color: #fff;
        }

        .subscription-filter-check {
            align-items: center;
            background: #f8f9fa;
            border: 1px solid rgba(52, 58, 64, .14);
            border-radius: .5rem;
            display: inline-flex;
            gap: .45rem;
            margin: 0;
            padding: .4rem .65rem;
        }

        .subscription-filter-check .form-check-input {
            margin: 0;
        }

        .subscription-range-address {
            font-weight: 400;
            line-height: 1.35;
        }

        .subscription-actions {
            display: flex;
            flex-wrap: wrap;
            gap: .35rem;
            max-width: 150px;
        }

        .subscription-actions form {
            display: inline-flex;
        }

        .subscription-picker-table .data-table-search-form {
            display: none !important;
        }

        @media (max-width: 575.98px) {
            .subscription-actions {
                max-width: none;
            }
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const manualPackages = @json($manualPackagesByShootingRange);
            const oldPackageId = @json($manualOldPackageId);
            const filterForm = document.querySelector('[data-orders-filter-form]');
            const filterSearch = document.querySelector('[data-orders-search]');
            let filterSearchTimeout = null;

            if (filterForm) {
                filterForm.querySelectorAll('[data-orders-filter]').forEach(function (field) {
                    field.addEventListener('change', function () {
                        filterForm.submit();
                    });
                });
            }

            if (filterSearch && filterForm) {
                filterSearch.addEventListener('input', function () {
                    clearTimeout(filterSearchTimeout);
                    filterSearchTimeout = setTimeout(function () {
                        filterForm.submit();
                    }, 450);
                });
            }

            document.querySelectorAll('[data-subscription-details-target]').forEach(function (button) {
                button.addEventListener('click', function () {
                    const detailsModalElement = document.querySelector(button.dataset.subscriptionDetailsTarget);

                    if (detailsModalElement) {
                        bootstrap.Modal.getOrCreateInstance(detailsModalElement).show();
                    }
                });
            });

            const manualPaymentModalElement = document.getElementById('manualPaymentModal');
            const manualPaymentForm = document.querySelector('[data-manual-payment-form]');
            const manualPaymentSummary = document.querySelector('[data-manual-payment-summary]');
            const manualPaymentMethod = document.getElementById('manualSettlementPaymentMethod');
            const manualPaymentReason = document.getElementById('manualSettlementReason');
            const manualPaymentInternalNote = document.getElementById('manualSettlementInternalNote');

            document.querySelectorAll('.manual-payment-button').forEach(function (button) {
                button.addEventListener('click', function (event) {
                    event.preventDefault();
                    event.stopPropagation();

                    if (!manualPaymentModalElement || !manualPaymentForm) {
                        return;
                    }

                    const openModalElement = button.closest('.modal.show');
                    const manualPaymentModal = bootstrap.Modal.getOrCreateInstance(manualPaymentModalElement);

                    manualPaymentForm.action = button.dataset.action;
                    manualPaymentSummary.textContent = `${button.dataset.orderName || 'Strzelnica'} - ${button.dataset.orderPackage || 'abonament'}`;
                    manualPaymentMethod.value = button.dataset.paymentMethod || '{{ \App\Models\PackageOrder::METHOD_CASH }}';
                    manualPaymentReason.value = button.dataset.manualReason || '';
                    manualPaymentInternalNote.value = button.dataset.internalNote || '';

                    if (openModalElement && openModalElement !== manualPaymentModalElement) {
                        openModalElement.addEventListener('hidden.bs.modal', function () {
                            manualPaymentModal.show();
                        }, { once: true });

                        bootstrap.Modal.getOrCreateInstance(openModalElement).hide();

                        return;
                    }

                    manualPaymentModal.show();
                });
            });

            const manualModalElement = document.getElementById('manualSubscriptionOrderModal');
            const pickerModalElement = document.getElementById('shootingRangePickerModal');
            const openPickerButton = document.getElementById('openShootingRangePicker');
            const manualRangeInput = document.getElementById('manualShootingRangeId');
            const manualRangeSummary = document.getElementById('manualShootingRangeSummary');
            const manualPackageSelect = document.getElementById('manualPackageId');
            const pickerSearch = document.getElementById('shootingRangePickerSearch');
            const pickerEmpty = document.getElementById('subscriptionRangePickerEmpty');
            let openPickerAfterManualClose = false;
            let returningFromPicker = false;

            function refreshManualPackages(selectedPackageId = null) {
                const rangeId = manualRangeInput.value;
                const packages = manualPackages[rangeId] || [];

                manualPackageSelect.innerHTML = '';
                manualPackageSelect.disabled = packages.length === 0;

                if (packages.length === 0) {
                    const option = document.createElement('option');
                    option.value = '';
                    option.textContent = rangeId ? 'Brak dostępnych pakietów dla tej strzelnicy' : 'Najpierw wybierz strzelnicę';
                    manualPackageSelect.appendChild(option);

                    return;
                }

                packages.forEach(function (packageItem) {
                    const option = document.createElement('option');
                    option.value = packageItem.id;
                    option.textContent = packageItem.label;
                    option.selected = Number(selectedPackageId) === Number(packageItem.id);
                    manualPackageSelect.appendChild(option);
                });
            }

            if (openPickerButton && manualModalElement && pickerModalElement) {
                openPickerButton.addEventListener('click', function () {
                    openPickerAfterManualClose = true;
                    bootstrap.Modal.getOrCreateInstance(manualModalElement).hide();
                });
            }

            document.querySelectorAll('.choose-subscription-range').forEach(function (button) {
                button.addEventListener('click', function () {
                    manualRangeInput.value = button.dataset.id;
                    manualRangeSummary.innerHTML = `
                        <strong>${button.dataset.name}</strong>
                        <div class="small text-muted">${button.dataset.address || 'Brak adresu'}</div>
                    `;
                    openPickerButton.querySelector('span').textContent = 'Zamień strzelnicę';
                    refreshManualPackages();

                    returningFromPicker = true;
                    bootstrap.Modal.getOrCreateInstance(pickerModalElement).hide();
                });
            });

            if (manualModalElement && pickerModalElement) {
                manualModalElement.addEventListener('hidden.bs.modal', function () {
                    if (openPickerAfterManualClose) {
                        openPickerAfterManualClose = false;
                        bootstrap.Modal.getOrCreateInstance(pickerModalElement).show();
                    }
                });
            }

            if (pickerModalElement && manualModalElement) {
                pickerModalElement.addEventListener('shown.bs.modal', function () {
                    pickerSearch?.focus();
                });

                pickerModalElement.addEventListener('hidden.bs.modal', function () {
                    if (returningFromPicker) {
                        returningFromPicker = false;
                        bootstrap.Modal.getOrCreateInstance(manualModalElement).show();
                    }
                });
            }

            if (pickerSearch) {
                pickerSearch.addEventListener('input', function () {
                    const query = pickerSearch.value.trim().toLowerCase();
                    let visibleRows = 0;

                    document.querySelectorAll('.subscription-range-picker-row').forEach(function (row) {
                        const visible = row.dataset.search.includes(query);
                        row.classList.toggle('d-none', !visible);
                        visibleRows += visible ? 1 : 0;
                    });

                    pickerEmpty?.classList.toggle('d-none', visibleRows > 0);
                });
            }

            refreshManualPackages(oldPackageId);

            @if ($errors->any() && old('shooting_range_id'))
                bootstrap.Modal.getOrCreateInstance(manualModalElement).show();
            @endif
        });

        document.addEventListener('click', event => {
            const button = event.target.closest('.subscription-order-delete-button');

            if (!button) {
                return;
            }

            const form = button.closest('form');
            const modalElement = document.getElementById('deleteSubscriptionOrderModal');
            const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
            const message = modalElement.querySelector('[data-delete-subscription-order-message]');
            const confirmButton = modalElement.querySelector('[data-delete-subscription-order-confirm]');

            message.textContent = form.dataset.message || 'Usunąć to nieopłacone zamówienie abonamentu?';
            confirmButton.onclick = () => {
                modal.hide();
                form.submit();
            };

            modal.show();
        });
    </script>
</x-app-layout>
