<x-app-layout>
    <div class="page-bg-2"></div>

    <div class="container py-4">
        <header class="p-4 text-white rounded bg-secondary">
            <div class="gap-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                <div>
                    <div class="gap-2 mb-2 d-flex align-items-center">
                        <i class="bi bi-clock-history fs-4"></i>
                        <span class="opacity-75 text-uppercase small">Panel superadmina</span>
                    </div>
                    <h1 class="mb-1 h3">Przedziały aktywności</h1>
                    <p class="mb-0 opacity-75">{{ $shootingRange->name }}</p>
                </div>

                <a href="{{ route('admin.shootings') }}" class="btn btn-light">
                    <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>
                    Wróć do strzelnic
                </a>
            </div>
        </header>

        <div class="mt-3">
            <x-session-alerts />
        </div>

        <form method="POST" action="{{ route('admin.shootings.intervals.store', $shootingRange) }}" class="p-3 mt-3 bg-white rounded intervals-panel">
            @csrf

            <div class="row g-2">
                <div class="col-md-3">
                    <label class="form-label">Data startu</label>
                    <input
                        type="date"
                        name="start_date"
                        id="start_date"
                        class="form-control"
                        value="{{ old('start_date', $suggestedStartDate) }}"
                        max="3000-12-31"
                        min="2000-01-01"
                    >
                    @error('start_date')
                        <div class="text-danger">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-3">
                    <label class="form-label">Pakiet</label>
                    <select name="package_id" id="package_id" class="form-select">
                        @forelse ($rangePackages as $package)
                            <option
                                value="{{ $package->id }}"
                                data-months="{{ $package->period_months }}"
                                data-value="{{ $package->value }}"
                                @selected(old('package_id') == $package->id)
                            >
                                {{ $package->period_months }} mies. - {{ $package->value }} zł
                            </option>
                        @empty
                            <option value="">Brak aktywnych pakietów</option>
                        @endforelse
                    </select>
                    @error('package_id')
                        <div class="text-danger">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-3">
                    <label class="form-label">Termin płatności</label>
                    <input
                        type="date"
                        name="payment_due_date"
                        id="payment_due_date"
                        class="form-control"
                        value="{{ old('payment_due_date', $suggestedPaymentDueDate) }}"
                        max="3000-12-31"
                        min="2000-01-01"
                    >
                    @error('payment_due_date')
                        <div class="text-danger">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-3">
                    <label class="form-label">Data opłacenia</label>
                    <input
                        type="datetime-local"
                        name="datePaid"
                        class="form-control"
                        value="{{ old('datePaid') }}"
                    >
                    @error('datePaid')
                        <div class="text-danger">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-3">
                    <label class="form-label">Data końcowa</label>
                    <input type="text" id="end_date_preview" class="form-control" readonly>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Cena do zapłaty</label>
                    <input type="text" id="package_value_preview" class="form-control" readonly>
                </div>

                <div class="col-12">
                    <button class="btn btn-success" @disabled($rangePackages->isEmpty())>
                        <i class="bi bi-plus-circle me-1" aria-hidden="true"></i>
                        Dodaj przedział
                    </button>
                </div>
            </div>
        </form>

        <section class="p-3 mt-3 bg-white rounded intervals-panel">
            <h2 class="mb-3 h5">Aktywne przedziały</h2>

            <div class="table-responsive">
        <table class="table table-hover align-middle intervals-table">
            <thead>
                <tr>
                    <th>Od</th>
                    <th>Do</th>
                    <th>Termin płatności</th>
                    <th>Wartość</th>
                    <th>Status</th>
                    <th>Akcje</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($intervals as $interval)
                    <tr>
                        <td>{{ $interval->start_date->format('Y-m-d') }}</td>
                        <td>{{ $interval->end_date->format('Y-m-d') }}</td>
                        <td>{{ optional($interval->payment_due_date)->format('Y-m-d') }}</td>
                        <td>{{ $interval->value }} zł</td>
                        <td>{{ $interval->is_active ? 'Aktywny' : 'Nieaktywny' }}</td>
                        <td>
                            <div class="interval-actions">
                            <a
                                href="{{ route('admin.shootings.intervals.edit', [$shootingRange, $interval]) }}"
                                class="btn btn-sm btn-outline-primary data-table-icon-button"
                                data-bs-toggle="tooltip"
                                data-bs-title="Edytuj przedział"
                                aria-label="Edytuj przedział"
                            >
                                <i class="bi bi-pencil" aria-hidden="true"></i>
                            </a>

                            <form
                                method="POST"
                                action="{{ route('admin.shootings.intervals.destroy', [$shootingRange, $interval]) }}"
                                class="d-inline"
                            >
                                @csrf
                                @method('DELETE')

                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-danger data-table-icon-button interval-delete-button"
                                    data-bs-toggle="tooltip"
                                    data-bs-title="Usuń przedział"
                                    aria-label="Usuń przedział"
                                    data-message="Czy na pewno usunąć przedział od {{ $interval->start_date->format('Y-m-d') }} do {{ $interval->end_date->format('Y-m-d') }}?"
                                >
                                    <i class="bi bi-trash3" aria-hidden="true"></i>
                                </button>
                            </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">Brak aktywnych przedziałów.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
            </div>
        </section>

        <section class="p-3 mt-3 bg-white rounded intervals-panel">
            <h2 class="mb-3 h5">Archiwum</h2>

            <div class="table-responsive">
        <table class="table table-hover align-middle intervals-table">
            <thead>
                <tr>
                    <th>Od</th>
                    <th>Do</th>
                    <th>Termin płatności</th>
                    <th>Wartość</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($archiveIntervals as $interval)
                    <tr>
                        <td>{{ $interval->start_date->format('Y-m-d') }}</td>
                        <td>{{ $interval->end_date->format('Y-m-d') }}</td>
                        <td>{{ optional($interval->payment_due_date)->format('Y-m-d') }}</td>
                        <td>{{ $interval->value }} zł</td>
                        <td>{{ $interval->is_active ? 'Aktywny' : 'Archiwalny' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">Brak wpisów w archiwum.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
            </div>
        </section>
    </div>

    <div class="modal fade" id="intervalDeleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h5">Usuń przedział</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0" data-interval-delete-message></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-dark" data-bs-dismiss="modal">Anuluj</button>
                    <button type="button" class="btn btn-danger" data-interval-delete-confirm>
                        <i class="bi bi-trash3 me-1" aria-hidden="true"></i>
                        Usuń
                    </button>
                </div>
            </div>
        </div>
    </div>

    <style>
        .intervals-panel {
            border: 1px solid rgba(52, 58, 64, .12);
            box-shadow: 0 .75rem 1.5rem rgba(15, 23, 42, .08);
        }

        .intervals-table thead th {
            background: #212529;
            color: #fff;
            font-size: .78rem;
            text-transform: uppercase;
        }

        .interval-actions {
            display: flex;
            flex-wrap: wrap;
            gap: .35rem;
        }
    </style>

    <script>
        function addMonthsNoOverflow(date, months) {
            const result = new Date(date);
            const day = result.getDate();

            result.setMonth(result.getMonth() + months);

            if (result.getDate() !== day) {
                result.setDate(0);
            }

            return result;
        }

        function formatDate(date) {
            return date.toISOString().slice(0, 10);
        }

        function updateIntervalPreview() {
            const startInput = document.getElementById('start_date');
            const packageInput = document.getElementById('package_id');
            const paymentDueInput = document.getElementById('payment_due_date');
            const endPreview = document.getElementById('end_date_preview');
            const valuePreview = document.getElementById('package_value_preview');

            const selectedOption = packageInput.options[packageInput.selectedIndex];

            if (!startInput.value || !selectedOption || !selectedOption.value) {
                endPreview.value = '';
                valuePreview.value = '';
                return;
            }

            const startDate = new Date(startInput.value);
            const months = parseInt(selectedOption.dataset.months, 10);
            const value = selectedOption.dataset.value;

            const endDate = addMonthsNoOverflow(startDate, months);
            endDate.setDate(endDate.getDate() - 1);

            const paymentDueDate = new Date(startDate);
            paymentDueDate.setDate(paymentDueDate.getDate() + 7);

            endPreview.value = formatDate(endDate);
            valuePreview.value = `${value} zł`;

            if (!paymentDueInput.value) {
                paymentDueInput.value = formatDate(paymentDueDate);
            }
        }

        document.getElementById('start_date').addEventListener('change', function () {
            const paymentDueInput = document.getElementById('payment_due_date');

            if (this.value) {
                const paymentDueDate = new Date(this.value);
                paymentDueDate.setDate(paymentDueDate.getDate() + 7);
                paymentDueInput.value = formatDate(paymentDueDate);
            }

            updateIntervalPreview();
        });

        document.getElementById('package_id').addEventListener('change', updateIntervalPreview);

        updateIntervalPreview();

        document.addEventListener('click', event => {
            const button = event.target.closest('.interval-delete-button');

            if (!button) {
                return;
            }

            const modalElement = document.getElementById('intervalDeleteModal');
            const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
            const message = modalElement.querySelector('[data-interval-delete-message]');
            const confirmButton = modalElement.querySelector('[data-interval-delete-confirm]');
            const form = button.closest('form');

            message.textContent = button.dataset.message || 'Czy na pewno usunąć przedział?';
            confirmButton.onclick = () => {
                modal.hide();
                form.submit();
            };

            modal.show();
        });
    </script>
</x-app-layout>
