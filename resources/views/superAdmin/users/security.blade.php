<x-app-layout>
    <div class="page-bg-2"></div>

    <div class="container py-4">
        <header class="p-4 text-white rounded bg-secondary">
            <div class="gap-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                <div>
                    <div class="gap-2 mb-2 d-flex align-items-center">
                        <i class="bi bi-shield-lock-fill fs-4"></i>
                        <span class="opacity-75 text-uppercase small">
                            Panel superadmina
                        </span>
                    </div>

                    <h1 class="mb-1 h3">
                        Bezpieczenstwo uzytkownika
                    </h1>
                    <p class="mb-0 opacity-75">
                        {{ $user->name }} | {{ $user->email }}
                    </p>
                </div>

                <a href="{{ route('admin.users') }}" class="btn btn-light">
                    <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>
                    Wroc do listy
                </a>
            </div>
        </header>

        <div class="mt-3">
            <x-session-alerts />
        </div>

        @if ($errors->any())
            <div class="alert alert-danger">
                Popraw bledy w formularzu i sprobuj ponownie.
            </div>
        @endif

        <div class="row g-3 mt-1">
            <div class="col-md-4">
                <div class="p-3 bg-white rounded security-summary-card">
                    <div class="gap-2 d-flex align-items-center">
                        <i class="bi bi-hdd-network fs-4 text-primary"></i>
                        <div>
                            <div class="small text-muted">Adresy IP</div>
                            <strong>{{ $user->ip_addresses_count }}</strong>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-3 bg-white rounded security-summary-card">
                    <div class="gap-2 d-flex align-items-center">
                        <i class="bi bi-shield-exclamation fs-4 text-warning"></i>
                        <div>
                            <div class="small text-muted">Zdarzenia bezpieczenstwa</div>
                            <strong>{{ $user->security_events_count }}</strong>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-3 bg-white rounded security-summary-card">
                    <div class="gap-2 d-flex align-items-center">
                        <i class="bi bi-journal-text fs-4 text-dark"></i>
                        <div>
                            <div class="small text-muted">Wpisy audytu</div>
                            <strong>{{ $user->audit_logs_count }}</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <section class="p-3 mt-3 bg-white rounded security-panel">
            <div class="gap-2 mb-3 d-flex flex-column flex-lg-row justify-content-between align-items-lg-center">
                <div>
                    <h2 class="mb-1 h5">Adresy IP uzytkownika</h2>
                    <p class="mb-0 small text-muted">
                        System zapisuje IP automatycznie tylko raz dla danego uzytkownika, a kolejne akcje aktualizuja ostatnie uzycie.
                    </p>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle security-table">
                    <thead>
                        <tr>
                            <th>Adres IP</th>
                            <th>Pierwsza akcja</th>
                            <th>Pierwsze uzycie</th>
                            <th>Ostatnie uzycie</th>
                            <th>Akcje</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($ipAddresses as $ipAddress)
                            <tr>
                                <td><code>{{ $ipAddress->ip_address }}</code></td>
                                <td>{{ $ipAddress->first_action }}</td>
                                <td>{{ optional($ipAddress->first_seen_at)->format('Y-m-d H:i') }}</td>
                                <td>{{ optional($ipAddress->last_seen_at)->format('Y-m-d H:i') }}</td>
                                <td>
                                    <form
                                        method="POST"
                                        action="{{ route('admin.users.security-events.store', $user) }}"
                                        class="m-0"
                                    >
                                        @csrf
                                        <input type="hidden" name="ip_address" value="{{ $ipAddress->ip_address }}">
                                        <input type="hidden" name="event_type" value="{{ \App\Models\UserSecurityEvent::TYPE_BAN }}">
                                        <input type="hidden" name="severity" value="{{ \App\Models\UserSecurityEvent::SEVERITY_CRITICAL }}">
                                        <input type="hidden" name="reason" value="IP oznaczone jako zbanowane przez superadmina.">
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-danger data-table-icon-button"
                                            data-bs-toggle="tooltip"
                                            data-bs-title="Oznacz IP jako zbanowane"
                                            data-security-confirm
                                            data-security-confirm-class="btn-danger"
                                            data-security-confirm-label="Oznacz"
                                            data-security-confirm-message="Czy oznaczyć IP {{ $ipAddress->ip_address }} jako zbanowane dla tego użytkownika?"
                                            aria-label="Oznacz IP jako zbanowane"
                                        >
                                            <i class="bi bi-ban" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-4 text-center text-muted">
                                    Brak zapisanych adresow IP.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="p-3 mt-3 bg-white rounded security-panel">
            <h2 class="mb-3 h5">Dodaj zdarzenie bezpieczenstwa</h2>

            <form method="POST" action="{{ route('admin.users.security-events.store', $user) }}">
                @csrf

                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label" for="ip_address">Adres IP</label>
                        <input id="ip_address" name="ip_address" class="form-control" value="{{ old('ip_address') }}" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label" for="event_type">Typ zdarzenia</label>
                        <select id="event_type" name="event_type" class="form-select" required>
                            <option value="{{ \App\Models\UserSecurityEvent::TYPE_BAN }}">Ban IP</option>
                            <option value="{{ \App\Models\UserSecurityEvent::TYPE_SUSPICIOUS_REGISTRATION }}">Podejrzana rejestracja</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label" for="severity">Waga</label>
                        <select id="severity" name="severity" class="form-select" required>
                            <option value="{{ \App\Models\UserSecurityEvent::SEVERITY_WARNING }}">Ostrzezenie</option>
                            <option value="{{ \App\Models\UserSecurityEvent::SEVERITY_CRITICAL }}">Krytyczne</option>
                            <option value="{{ \App\Models\UserSecurityEvent::SEVERITY_INFO }}">Info</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label" for="expires_at">Wygasa</label>
                        <input id="expires_at" name="expires_at" type="datetime-local" class="form-control" value="{{ old('expires_at') }}">
                    </div>

                    <div class="col-md-2">
                        <button type="submit" class="w-100 btn btn-primary">
                            <i class="bi bi-plus-circle me-1" aria-hidden="true"></i>
                            Dodaj
                        </button>
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="reason">Opis</label>
                        <textarea id="reason" name="reason" class="form-control" rows="3">{{ old('reason') }}</textarea>
                    </div>
                </div>
            </form>
        </section>

        <section class="p-3 mt-3 bg-white rounded security-panel">
            <h2 class="mb-3 h5">Zdarzenia bezpieczenstwa</h2>

            <div class="table-responsive">
                <table class="table table-hover align-middle security-table">
                    <thead>
                        <tr>
                            <th>Typ</th>
                            <th>IP</th>
                            <th>Waga</th>
                            <th>Opis</th>
                            <th>Status</th>
                            <th>Dodano</th>
                            <th>Akcje</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($securityEvents as $event)
                            @php
                                $isActive = is_null($event->resolved_at) && (is_null($event->expires_at) || $event->expires_at->isFuture());
                            @endphp
                            <tr>
                                <td>{{ $event->event_type }}</td>
                                <td><code>{{ $event->ip_address }}</code></td>
                                <td>
                                    <span @class([
                                        'badge',
                                        'bg-danger' => $event->severity === \App\Models\UserSecurityEvent::SEVERITY_CRITICAL,
                                        'bg-warning text-dark' => $event->severity === \App\Models\UserSecurityEvent::SEVERITY_WARNING,
                                        'bg-secondary' => $event->severity === \App\Models\UserSecurityEvent::SEVERITY_INFO,
                                    ])>
                                        {{ $event->severity }}
                                    </span>
                                </td>
                                <td>{{ $event->reason ?: '-' }}</td>
                                <td>
                                    @if ($event->resolved_at)
                                        <span class="badge bg-success">zamkniete</span>
                                    @elseif ($event->expires_at && $event->expires_at->isPast())
                                        <span class="badge bg-secondary">wygaslo</span>
                                    @else
                                        <span class="badge bg-danger">aktywne</span>
                                    @endif
                                </td>
                                <td>{{ optional($event->created_at)->format('Y-m-d H:i') }}</td>
                                <td>
                                    @if ($isActive)
                                        <form
                                            method="POST"
                                            action="{{ route('admin.users.security-events.resolve', [$user, $event]) }}"
                                            class="m-0"
                                        >
                                            @csrf
                                            @method('PATCH')
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-success data-table-icon-button"
                                                data-bs-toggle="tooltip"
                                                data-bs-title="Zamknij zdarzenie"
                                                data-security-confirm
                                                data-security-confirm-class="btn-success"
                                                data-security-confirm-label="Zamknij"
                                                data-security-confirm-message="Czy oznaczyć zdarzenie jako zamknięte?"
                                                aria-label="Zamknij zdarzenie"
                                            >
                                                <i class="bi bi-check2-circle" aria-hidden="true"></i>
                                            </button>
                                        </form>
                                    @else
                                        -
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-4 text-center text-muted">
                                    Brak zdarzen bezpieczenstwa.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $securityEvents->links() }}
        </section>

        <section class="p-3 mt-3 bg-white rounded security-panel">
            <h2 class="mb-3 h5">Historia audytu</h2>

            <div class="table-responsive">
                <table class="table table-hover align-middle security-table">
                    <thead>
                        <tr>
                            <th>Akcja</th>
                            <th>IP</th>
                            <th>Model</th>
                            <th>Dane</th>
                            <th>Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($auditLogs as $log)
                            <tr>
                                <td>{{ $log->action }}</td>
                                <td><code>{{ $log->ip_address }}</code></td>
                                <td>
                                    {{ class_basename($log->model_type) ?: '-' }}
                                    @if ($log->model_id)
                                        #{{ $log->model_id }}
                                    @endif
                                </td>
                                <td>
                                    @if ($log->payload)
                                        <code class="security-payload">{{ json_encode($log->payload, JSON_UNESCAPED_UNICODE) }}</code>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>{{ optional($log->created_at)->format('Y-m-d H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-4 text-center text-muted">
                                    Brak wpisow audytu.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $auditLogs->links() }}
        </section>
    </div>

    <div class="modal fade" id="securityConfirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h5">Potwierdź akcję</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0" data-security-confirm-message></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-dark" data-bs-dismiss="modal">Anuluj</button>
                    <button type="button" class="btn btn-danger" data-security-confirm-submit>
                        Potwierdź
                    </button>
                </div>
            </div>
        </div>
    </div>

    <style>
        .security-summary-card,
        .security-panel {
            border: 1px solid rgba(52, 58, 64, .12);
            box-shadow: 0 .75rem 1.5rem rgba(15, 23, 42, .08);
        }

        .security-table thead th {
            background: #212529;
            color: #fff;
            font-size: .78rem;
            text-transform: uppercase;
        }

        .security-payload {
            display: block;
            max-width: 360px;
            white-space: normal;
            overflow-wrap: anywhere;
        }
    </style>

    <script>
        document.addEventListener('click', event => {
            const button = event.target.closest('[data-security-confirm]');

            if (!button) {
                return;
            }

            const form = button.closest('form');
            const modalElement = document.getElementById('securityConfirmModal');
            const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
            const message = modalElement.querySelector('[data-security-confirm-message]');
            const submitButton = modalElement.querySelector('[data-security-confirm-submit]');

            message.textContent = button.dataset.securityConfirmMessage || 'Czy na pewno wykonać tę akcję?';
            submitButton.className = `btn ${button.dataset.securityConfirmClass || 'btn-danger'}`;
            submitButton.textContent = button.dataset.securityConfirmLabel || 'Potwierdź';
            submitButton.onclick = () => {
                modal.hide();
                form.submit();
            };

            modal.show();
        });
    </script>
</x-app-layout>
