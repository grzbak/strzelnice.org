<x-app-layout>
    <div class="container py-4">
        <h1>Przedziały aktywności: {{ $shootingRange->name }}</h1>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <form method="POST" action="{{ route('admin.shootings.intervals.store', $shootingRange) }}" class="mb-4">
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
                        Dodaj przedział
                    </button>
                </div>
            </div>
        </form>

        <h2>Aktywne przedziały</h2>

        <table class="table">
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
                            <a
                                href="{{ route('admin.shootings.intervals.edit', [$shootingRange, $interval]) }}"
                                class="btn btn-sm btn-primary"
                            >
                                Edytuj
                            </a>

                            <form
                                method="POST"
                                action="{{ route('admin.shootings.intervals.destroy', [$shootingRange, $interval]) }}"
                                class="d-inline"
                            >
                                @csrf
                                @method('DELETE')

                                <button class="btn btn-sm btn-danger" onclick="return confirm('Usunąć przedział?')">
                                    Usuń
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">Brak aktywnych przedziałów.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <h2>Archiwum</h2>

        <table class="table">
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
    </script>
</x-app-layout>
