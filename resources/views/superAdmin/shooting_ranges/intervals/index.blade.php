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
                    <label class="form-label">Data końcowa</label>
                    <input
                        type="date"
                        name="end_date"
                        id="end_date"
                        class="form-control"
                        value="{{ old('end_date', $suggestedEndDate) }}"
                        max="3000-12-31"
                        min="2000-01-01"
                    >
                    @error('end_date')
                        <div class="text-danger">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label">Powód / notatka</label>
                    <input
                        type="text"
                        name="manual_reason"
                        class="form-control"
                        value="{{ old('manual_reason') }}"
                        placeholder="np. dostęp testowy, promocja, korekta"
                    >
                    @error('manual_reason')
                        <div class="text-danger">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12">
                    <button class="btn btn-success">
                        <i class="bi bi-plus-circle me-1" aria-hidden="true"></i>
                        Dodaj / rozszerz ręczny przedział
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
                    <th>Źródło</th>
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
                        <td>
                            <span class="badge bg-dark">
                                {{ $interval->source === 'mixed' ? 'Abonament + ręcznie' : ($interval->source === 'package_order' ? 'Abonament' : 'Ręcznie') }}
                            </span>
                        </td>
                        <td>{{ number_format((float) $interval->value, 2, ',', ' ') }} zł</td>
                        <td>{{ $interval->is_active ? 'Aktywny' : 'Nieaktywny' }}</td>
                        <td>
                            <div class="interval-actions">
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-dark data-table-icon-button"
                                data-bs-toggle="modal"
                                data-bs-target="#intervalHistoryModal{{ $interval->id }}"
                                aria-label="Historia przedziału"
                            >
                                <i class="bi bi-clock-history" aria-hidden="true"></i>
                            </button>

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
                    <th>Źródło</th>
                    <th>Wartość</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($archiveIntervals as $interval)
                    <tr>
                        <td>{{ $interval->start_date->format('Y-m-d') }}</td>
                        <td>{{ $interval->end_date->format('Y-m-d') }}</td>
                        <td>{{ $interval->source === 'mixed' ? 'Abonament + ręcznie' : ($interval->source === 'package_order' ? 'Abonament' : 'Ręcznie') }}</td>
                        <td>{{ number_format((float) $interval->value, 2, ',', ' ') }} zł</td>
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

    @foreach ($intervals as $interval)
        <div class="modal fade" id="intervalHistoryModal{{ $interval->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <h2 class="modal-title h5">Historia przedziału aktywności</h2>
                            <div class="small text-muted">
                                {{ $interval->start_date->format('Y-m-d') }} - {{ $interval->end_date->format('Y-m-d') }}
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                    </div>
                    <div class="modal-body">
                        <h3 class="mb-3 h6">Zamówienia abonamentów</h3>

                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Pakiet</th>
                                        <th>Okres</th>
                                        <th>Kwota</th>
                                        <th>Status płatności</th>
                                        <th>Forma</th>
                                        <th>Termin</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($interval->packageOrders as $order)
                                        <tr>
                                            <td>#{{ $order->id }}</td>
                                            <td>{{ $order->package?->display_name ?? 'Pakiet usunięty' }}</td>
                                            <td>{{ $order->starts_at?->format('Y-m-d') }} - {{ $order->ends_at?->format('Y-m-d') }}</td>
                                            <td>{{ number_format((float) $order->amount_gross, 2, ',', ' ') }} {{ $order->currency }}</td>
                                            <td>
                                                <span class="badge bg-{{ $order->payment_status_badge_class }}">
                                                    {{ $order->payment_status_label }}
                                                </span>
                                            </td>
                                            <td>{{ $order->payment_method_label }}</td>
                                            <td>{{ $order->payment_due_at?->format('Y-m-d H:i') ?? '-' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-muted">Brak zamówień powiązanych z tym przedziałem.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <h3 class="mt-4 mb-3 h6">Wpłaty</h3>

                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Zamówienie</th>
                                        <th>Status</th>
                                        <th>Kwota</th>
                                        <th>Operator</th>
                                        <th>Potwierdzono</th>
                                        <th>Notatka</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $payments = $interval->packageOrders->flatMap->payments;
                                    @endphp

                                    @forelse ($payments as $payment)
                                        <tr>
                                            <td>#{{ $payment->id }}</td>
                                            <td>#{{ $payment->package_order_id }}</td>
                                            <td>{{ $payment->status }}</td>
                                            <td>{{ number_format((float) $payment->amount, 2, ',', ' ') }} {{ $payment->currency }}</td>
                                            <td>{{ $payment->provider }} / {{ $payment->method }}</td>
                                            <td>{{ $payment->confirmed_at?->format('Y-m-d H:i') ?? '-' }}</td>
                                            <td>{{ $payment->confirmation_note ?: ($payment->note ?: '-') }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-muted">Brak wpłat powiązanych z tym przedziałem.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <h3 class="mt-4 mb-3 h6">Historia zmian przedziału</h3>

                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead>
                                    <tr>
                                        <th>Data</th>
                                        <th>Zdarzenie</th>
                                        <th>Źródło</th>
                                        <th>Zakres źródła</th>
                                        <th>Zakres po zmianie</th>
                                        <th>Użytkownik</th>
                                        <th>Notatka</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($interval->events->sortByDesc('created_at') as $event)
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
                                            <td colspan="7" class="text-muted">Brak historii zmian dla tego przedziału.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-dark" data-bs-dismiss="modal">Zamknij</button>
                    </div>
                </div>
            </div>
        </div>
    @endforeach

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
