<x-app-layout>
    <x-session-alerts />

    <div class="page-bg-2"></div>

    <div class="container py-4">
        @if ($errors->any())
            <div class="alert alert-danger">
                <strong>Nie mozna uruchomic platnosci.</strong>
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <header class="p-4 text-white rounded bg-secondary">
            <div class="gap-2 mb-2 d-flex align-items-center">
                <i class="bi bi-credit-card-2-front-fill fs-4"></i>
                <span class="opacity-75 text-uppercase small">Panel strzelnicy</span>
            </div>
            <h1 class="mb-1 h3">Abonament</h1>
            <p class="mb-0 opacity-75">
                {{ $shootingRange?->name ?? 'Brak przypisanej strzelnicy' }}
            </p>
        </header>

        @if (! $shootingRange)
            <div class="p-4 mt-3 bg-white rounded shadow-sm">
                Nie znaleziono strzelnicy, ktora mozesz zarzadzac jako administrator.
            </div>
        @else
            @if ($hasUnpaidCharges)
                <div class="mt-3 alert alert-warning">
                    <strong>Nowy abonament jest zablokowany.</strong>
                    Strzelnica ma nieoplacona naleznosc za poprzedni dostep warunkowy.
                </div>
            @endif

            <div class="p-4 mt-3 bg-white rounded shadow-sm">
                <h2 class="mb-3 h5">Dostepne pakiety</h2>

                <div class="row g-3">
                    @forelse ($packages as $package)
                        @php($preview = $packagePreviews[$package->id] ?? null)
                        <div class="col-md-6 col-xl-4">
                            <div class="p-3 border rounded h-100">
                                <div class="mb-2 fw-semibold">
                                    {{ $package->display_name }}
                                </div>
                                <div class="text-muted small">
                                    Okres: {{ $package->period_months }} mies.
                                    @if ($package->bonus_months)
                                        + {{ $package->bonus_months }} mies. bonusu
                                    @endif
                                </div>
                                <div class="mt-2 h4">
                                    {{ number_format((float) $package->effective_price, 2, ',', ' ') }}
                                    {{ $package->currency ?? 'PLN' }}
                                </div>

                                @if ($package->description)
                                    <p class="mt-2 mb-0 small text-muted">{{ $package->description }}</p>
                                @endif

                                @if ($preview)
                                    <div class="mt-2 small text-muted">
                                        Obowiazuje:
                                        <strong>{{ $preview['starts_at'] }}</strong>
                                        -
                                        <strong>{{ $preview['ends_at'] }}</strong>
                                    </div>
                                @endif

                                <button
                                    type="button"
                                    class="mt-3 btn btn-danger w-100 subscription-package-button"
                                    data-bs-toggle="modal"
                                    data-bs-target="#subscriptionOrderModal"
                                    data-package-id="{{ $package->id }}"
                                    @disabled($hasUnpaidCharges)
                                >
                                    <i class="bi bi-lock-fill me-1"></i>
                                    {{ $p24Enabled ? 'Zamow i oplac' : 'Zamow abonament' }}
                                </button>
                            </div>
                        </div>
                    @empty
                        <div class="col-12">
                            <div class="p-3 text-muted border rounded">
                                Brak dostepnych pakietow dla tej strzelnicy.
                            </div>
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="modal fade" id="subscriptionOrderModal" tabindex="-1" aria-labelledby="subscriptionOrderModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <form method="POST" action="{{ route('shooting.subscription.store') }}" class="modal-content">
                        @csrf
                        <div class="modal-header">
                            <h2 class="modal-title h5" id="subscriptionOrderModalLabel">Potwierdzenie zamowienia abonamentu</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                        </div>

                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="subscriptionPackageSelect" class="form-label">Pakiet</label>
                                <select id="subscriptionPackageSelect" name="package_id" class="form-select" required>
                                    @foreach ($packages as $package)
                                        @php($preview = $packagePreviews[$package->id] ?? null)
                                        @php($periodLabel = $package->period_months.' mies.'.($package->bonus_months ? ' + '.$package->bonus_months.' mies. bonusu' : ''))
                                        <option
                                            value="{{ $package->id }}"
                                            data-name="{{ $package->display_name }}"
                                            data-price="{{ number_format((float) $package->effective_price, 2, ',', ' ') }} {{ $package->currency ?? 'PLN' }}"
                                            data-period="{{ $periodLabel }}"
                                            data-starts-at="{{ $preview['starts_at'] ?? '' }}"
                                            data-ends-at="{{ $preview['ends_at'] ?? '' }}"
                                            @selected((int) old('package_id') === $package->id)
                                        >
                                            {{ $package->display_name }} - {{ number_format((float) $package->effective_price, 2, ',', ' ') }} {{ $package->currency ?? 'PLN' }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('package_id')" class="mt-1" />
                            </div>

                            <div class="p-3 mb-3 border rounded bg-light">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="text-muted small">Wybrany pakiet</div>
                                        <div class="fw-semibold" data-subscription-summary-name>-</div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="text-muted small">Kwota</div>
                                        <div class="fw-semibold" data-subscription-summary-price>-</div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="text-muted small">Okres pakietu</div>
                                        <div class="fw-semibold" data-subscription-summary-period>-</div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="text-muted small">Obowiazuje od - do</div>
                                        <div class="fw-semibold">
                                            <span data-subscription-summary-start>-</span>
                                            -
                                            <span data-subscription-summary-end>-</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            @if (! $p24Enabled)
                                <div class="mb-3 alert alert-warning">
                                    <strong>Płatność przelewem tradycyjnym.</strong>
                                    Po kliknięciu przycisku zamówienia system zapisze abonament jako oczekujący na płatność.
                                    Dane do przelewu oraz wygenerowany tytuł płatności będą widoczne na liście abonamentów.
                                </div>

                                <div class="p-3 mb-3 border rounded">
                                    <div class="mb-2 fw-semibold">Dane odbiorcy z konfiguracji systemu</div>
                                    <dl class="row mb-0 small">
                                        <dt class="col-sm-4">Odbiorca</dt>
                                        <dd class="col-sm-8">
                                            {{ $configuration?->company_name_1 ?: config('app.name') }}
                                            @if ($configuration?->company_name_2)
                                                <br>{{ $configuration->company_name_2 }}
                                            @endif
                                            @if ($configuration?->company_name_3)
                                                <br>{{ $configuration->company_name_3 }}
                                            @endif
                                        </dd>

                                        <dt class="col-sm-4">Bank</dt>
                                        <dd class="col-sm-8">{{ $configuration?->bank_name ?: '-' }}</dd>

                                        <dt class="col-sm-4">Numer rachunku</dt>
                                        <dd class="col-sm-8 fw-semibold">{{ $configuration?->formatted_bank_account_number ?: '-' }}</dd>
                                    </dl>
                                </div>
                            @endif

                            <div class="mb-3">
                                <label for="subscriptionNotes" class="form-label">Notatka do zamowienia</label>
                                <textarea id="subscriptionNotes" name="notes" class="form-control" rows="3">{{ old('notes') }}</textarea>
                                <x-input-error :messages="$errors->get('notes')" class="mt-1" />
                            </div>

                            <div class="form-check">
                                <input
                                    type="checkbox"
                                    id="termsAccepted"
                                    name="terms_accepted"
                                    value="1"
                                    class="form-check-input @error('terms_accepted') is-invalid @enderror"
                                    @checked(old('terms_accepted'))
                                    required
                                >
                                <label for="termsAccepted" class="form-check-label">
                                    Oświadczam, że zapoznałem/am się z
                                    <a href="{{ url('/regulamin_partnera') }}" target="_blank" rel="noopener" class="link-danger">regulaminem partnera</a>
                                    i akceptuję jego treść.
                                </label>
                                <x-input-error :messages="$errors->get('terms_accepted')" class="mt-1" />
                            </div>

                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-dark" data-bs-dismiss="modal">Anuluj</button>
                            <button type="submit" class="btn btn-danger">
                                Zamawiam z obowiązkiem zapłaty
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="p-4 mt-3 bg-white rounded shadow-sm">
                <h2 class="mb-3 h5">Historia zamowien</h2>

                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Pakiet</th>
                                <th>Okres</th>
                                <th>Kwota</th>
                                <th>Status płatności</th>
                                <th>Termin platnosci</th>
                                <th>Akcja</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($orders as $order)
                                <tr>
                                    <td>{{ $order->package?->display_name ?? 'Pakiet usuniety' }}</td>
                                    <td>{{ $order->starts_at?->format('Y-m-d') }} - {{ $order->ends_at?->format('Y-m-d') }}</td>
                                    <td>{{ number_format((float) $order->amount_gross, 2, ',', ' ') }} {{ $order->currency }}</td>
                                    <td>
                                        <span class="badge bg-{{ $order->payment_status_badge_class }}">
                                            {{ $order->payment_status_label }}
                                        </span>
                                    </td>
                                    <td>{{ $order->payment_due_at?->format('Y-m-d H:i') }}</td>
                                    <td>
                                        <div class="gap-1 d-flex flex-wrap">
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-dark subscription-action-button"
                                                data-subscription-details-target="#subscriptionOrderDetailsModal{{ $order->id }}"
                                                data-bs-toggle="tooltip"
                                                data-bs-title="Szczegóły zamówienia"
                                                aria-label="Szczegóły zamówienia"
                                            >
                                                <i class="bi bi-info-circle"></i>
                                            </button>

                                            @if (in_array($order->id, $payableOrderIds, true))
                                                <a
                                                    href="{{ route('subscription-orders.pay', $order) }}"
                                                    class="btn btn-sm btn-outline-danger subscription-action-button"
                                                    data-bs-toggle="tooltip"
                                                    data-bs-title="Opłać abonament"
                                                    aria-label="Opłać abonament"
                                                >
                                                    <i class="bi bi-credit-card-2-front"></i>
                                                </a>
                                            @endif

                                            @if (
                                                $order->payment_method === \App\Models\PackageOrder::METHOD_BANK_TRANSFER
                                                && $order->payment_status === \App\Models\PackageOrder::PAYMENT_PENDING
                                                && $order->status !== \App\Models\PackageOrder::STATUS_CANCELLED
                                            )
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-outline-secondary subscription-action-button"
                                                    data-subscription-details-target="#subscriptionTransferDetailsModal{{ $order->id }}"
                                                    data-bs-toggle="tooltip"
                                                    data-bs-title="Dane do przelewu"
                                                    aria-label="Dane do przelewu"
                                                >
                                                    <i class="bi bi-bank"></i>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-muted">Brak zamowien.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
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
                                            <span class="fw-semibold">#{{ $p24Payment->p24_order_id }}</span>
                                        </dd>
                                    @endif

                                    <dt class="col-sm-4">Termin płatności</dt>
                                    <dd class="col-sm-8">{{ $order->payment_due_at?->format('Y-m-d H:i') ?? '-' }}</dd>

                                    <dt class="col-sm-4">Notatka</dt>
                                    <dd class="col-sm-8">
                                        @if (filled($order->notes))
                                            <div class="p-2 border rounded bg-light text-break">{{ $order->notes }}</div>
                                        @else
                                            <span class="text-muted">Brak notatki.</span>
                                        @endif
                                    </dd>
                                </dl>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-dark" data-bs-dismiss="modal">Zamknij</button>
                                @if (in_array($order->id, $payableOrderIds, true))
                                    <a href="{{ route('subscription-orders.pay', $order) }}" class="btn btn-danger">
                                        <i class="bi bi-credit-card-2-front me-1"></i>
                                        Opłać abonament
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                @if ($order->payment_method === \App\Models\PackageOrder::METHOD_BANK_TRANSFER)
                    <div class="modal fade" id="subscriptionTransferDetailsModal{{ $order->id }}" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h2 class="modal-title h5">Dane do przelewu</h2>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="alert alert-warning">
                                        W tytule przelewu wpisz dokładnie podany tytuł płatności. Dzięki temu wpłata zostanie powiązana z właściwym abonamentem.
                                    </div>

                                    <dl class="row mb-0">
                                        <dt class="col-sm-4">Odbiorca</dt>
                                        <dd class="col-sm-8">
                                            {{ $configuration?->company_name_1 ?: config('app.name') }}
                                            @if ($configuration?->company_name_2)
                                                <br>{{ $configuration->company_name_2 }}
                                            @endif
                                            @if ($configuration?->company_name_3)
                                                <br>{{ $configuration->company_name_3 }}
                                            @endif
                                        </dd>

                                        <dt class="col-sm-4">Bank</dt>
                                        <dd class="col-sm-8">{{ $configuration?->bank_name ?: '-' }}</dd>

                                        <dt class="col-sm-4">Numer rachunku</dt>
                                        <dd class="col-sm-8 fw-semibold">{{ $configuration?->formatted_bank_account_number ?: '-' }}</dd>

                                        <dt class="col-sm-4">Kwota</dt>
                                        <dd class="col-sm-8 fw-semibold">{{ number_format((float) $order->amount_gross, 2, ',', ' ') }} {{ $order->currency }}</dd>

                                        <dt class="col-sm-4">Tytuł płatności</dt>
                                        <dd class="col-sm-8">
                                            <div class="p-2 border rounded bg-light fw-semibold text-break">
                                                {{ $order->payment_note ?: 'ABONAMENT STRZELNICE.ORG/'.$order->id.'/'.$order->shooting_range_id }}
                                            </div>
                                        </dd>

                                        <dt class="col-sm-4">Termin płatności</dt>
                                        <dd class="col-sm-8">{{ $order->payment_due_at?->format('Y-m-d H:i') ?? '-' }}</dd>
                                    </dl>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-dark" data-bs-dismiss="modal">Zamknij</button>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            @endforeach
        @endif
    </div>

    <style>
        .subscription-action-button {
            align-items: center;
            display: inline-flex;
            height: 34px;
            justify-content: center;
            padding: 0;
            width: 34px;
        }

        .subscription-action-button i {
            font-size: 1rem;
            line-height: 1;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modalElement = document.getElementById('subscriptionOrderModal');
            const select = document.getElementById('subscriptionPackageSelect');

            document.querySelectorAll('[data-subscription-details-target]').forEach(function (button) {
                button.addEventListener('click', function () {
                    const detailsModalElement = document.querySelector(button.dataset.subscriptionDetailsTarget);

                    if (detailsModalElement) {
                        bootstrap.Modal.getOrCreateInstance(detailsModalElement).show();
                    }
                });
            });

            if (!modalElement || !select) {
                return;
            }

            const summary = {
                name: modalElement.querySelector('[data-subscription-summary-name]'),
                price: modalElement.querySelector('[data-subscription-summary-price]'),
                period: modalElement.querySelector('[data-subscription-summary-period]'),
                start: modalElement.querySelector('[data-subscription-summary-start]'),
                end: modalElement.querySelector('[data-subscription-summary-end]'),
            };

            function refreshSummary() {
                const option = select.selectedOptions[0];

                if (!option) {
                    return;
                }

                summary.name.textContent = option.dataset.name || '-';
                summary.price.textContent = option.dataset.price || '-';
                summary.period.textContent = option.dataset.period || '-';
                summary.start.textContent = option.dataset.startsAt || '-';
                summary.end.textContent = option.dataset.endsAt || '-';
            }

            document.querySelectorAll('.subscription-package-button').forEach(function (button) {
                button.addEventListener('click', function () {
                    select.value = button.dataset.packageId;
                    refreshSummary();
                });
            });

            select.addEventListener('change', refreshSummary);
            refreshSummary();

            @if ($errors->any() && old('package_id'))
                bootstrap.Modal.getOrCreateInstance(modalElement).show();
            @endif
        });
    </script>
</x-app-layout>
