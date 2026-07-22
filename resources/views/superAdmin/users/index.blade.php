<x-app-layout>
    @php
        $columns = [
            ['key' => 'name', 'label' => 'Uzytkownik', 'sortable' => true, 'class' => 'admin-user-name-column'],
            ['key' => 'email', 'label' => 'Email', 'sortable' => true],
            ['key' => 'role', 'label' => 'Rola', 'sortable' => true],
            ['key' => 'approval_status', 'label' => 'Status', 'sortable' => true],
            ['key' => 'security', 'label' => 'Bezpieczenstwo'],
            ['key' => 'created_at', 'label' => 'Dodano', 'sortable' => true],
            ['key' => 'actions', 'label' => 'Akcje', 'class' => 'admin-user-actions-column'],
        ];

        $statusConfig = function ($user) {
            if ($user->approval_status === \App\Models\User::APPROVAL_APPROVED && $user->is_approved) {
                return ['class' => 'bg-success', 'label' => 'Aktywne', 'icon' => 'bi-check-circle-fill'];
            }

            if ($user->approval_status === \App\Models\User::APPROVAL_REJECTED) {
                return ['class' => 'bg-danger', 'label' => 'Zablokowane', 'icon' => 'bi-lock-fill'];
            }

            return ['class' => 'bg-warning text-dark', 'label' => 'Oczekuje', 'icon' => 'bi-hourglass-split'];
        };
    @endphp

    <div class="page-bg-2"></div>

    <div class="container py-4">
        <header class="p-4 text-white rounded bg-secondary">
            <div class="gap-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                <div>
                    <div class="gap-2 mb-2 d-flex align-items-center">
                        <i class="bi bi-people-fill fs-4"></i>
                        <span class="opacity-75 text-uppercase small">
                            Panel superadmina
                        </span>
                    </div>

                    <h1 class="mb-0 h3">
                        Uzytkownicy
                    </h1>
                </div>
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

        <x-data-table
            id="superadmin-users-table"
            :action="route('admin.users')"
            :columns="$columns"
            :paginator="$users"
            :search="$search"
            search-label="Wyszukaj uzytkownika"
            search-placeholder="Imie, nazwisko, email lub adres IP"
            :sort="$sort"
            :direction="$direction"
            :per-page="$perPage"
            :per-page-options="[10, 20, 50]"
        >
            @forelse ($users as $user)
                @php
                    $status = $statusConfig($user);
                    $isCurrentUser = auth()->id() === $user->id;
                    $isActiveUser = $user->approval_status === \App\Models\User::APPROVAL_APPROVED && $user->is_approved;
                    $isBlockedUser = $user->approval_status === \App\Models\User::APPROVAL_REJECTED;
                    $activationActionLabel = $isBlockedUser ? 'Odblokuj konto' : 'Aktywuj konto';
                    $activationModalTitle = $isBlockedUser ? 'Odblokowanie konta' : 'Aktywacja konta';
                    $activationModalMessage = $isBlockedUser
                        ? "Czy na pewno odblokowac konto uzytkownika {$user->email}? Uzytkownik otrzyma maila informacyjnego."
                        : "Czy na pewno aktywowac konto uzytkownika {$user->email}? Uzytkownik otrzyma maila informacyjnego.";
                    $activationModalButton = $isBlockedUser ? 'Odblokuj' : 'Aktywuj';
                    $activationIcon = $isBlockedUser ? 'bi-unlock-fill' : 'bi-person-check-fill';
                    $activationButtonClass = $isBlockedUser ? 'btn-outline-primary' : 'btn-outline-success';
                    $activationModalButtonClass = $isBlockedUser ? 'btn-primary' : 'btn-success';
                @endphp

                <tr>
                    <td data-label="Uzytkownik">
                        <div class="gap-2 d-flex align-items-center">
                            <span class="admin-user-avatar">
                                <i class="bi bi-person-fill" aria-hidden="true"></i>
                            </span>
                            <div class="min-w-0">
                                <strong class="d-block admin-user-name">{{ $user->name }}</strong>
                                <small class="text-muted">ID: {{ $user->id }}</small>
                            </div>
                        </div>
                    </td>

                    <td data-label="Email">
                        <span class="admin-user-email">{{ $user->email }}</span>
                    </td>

                    <td data-label="Rola">
                        <span class="badge bg-dark">{{ $user->role }}</span>
                    </td>

                    <td data-label="Status">
                        <span class="gap-1 badge {{ $status['class'] }} d-inline-flex align-items-center">
                            <i class="bi {{ $status['icon'] }}" aria-hidden="true"></i>
                            {{ $status['label'] }}
                        </span>

                        @if (! $user->hasVerifiedEmail())
                            <span
                                class="mt-1 badge bg-secondary d-inline-flex"
                                data-bs-toggle="tooltip"
                                data-bs-title="Adres e-mail nie zostal jeszcze zweryfikowany"
                            >
                                email niezweryfikowany
                            </span>
                        @endif
                    </td>

                    <td data-label="Bezpieczenstwo">
                        <div class="gap-2 d-flex flex-wrap align-items-center">
                            <span
                                class="badge bg-light text-dark border"
                                data-bs-toggle="tooltip"
                                data-bs-title="Zapisane adresy IP uzytkownika"
                            >
                                <i class="bi bi-hdd-network me-1" aria-hidden="true"></i>
                                IP: {{ $user->ip_addresses_count }}
                            </span>

                            <span
                                class="badge bg-light text-dark border"
                                data-bs-toggle="tooltip"
                                data-bs-title="Zdarzenia bezpieczenstwa"
                            >
                                <i class="bi bi-shield-exclamation me-1" aria-hidden="true"></i>
                                {{ $user->security_events_count }}
                            </span>

                            @if ($user->has_active_ip_ban)
                                <span
                                    class="badge bg-danger"
                                    data-bs-toggle="tooltip"
                                    data-bs-title="Uzytkownik ma aktywne zdarzenie blokady IP"
                                >
                                    aktywny ban IP
                                </span>
                            @endif
                        </div>
                    </td>

                    <td data-label="Dodano">
                        {{ optional($user->created_at)->format('Y-m-d H:i') }}
                    </td>

                    <td data-label="Akcje">
                        <div class="admin-user-actions">
                            <form
                                method="POST"
                                action="{{ route('admin.users.password-reset', $user) }}"
                                class="m-0 admin-user-confirm-form"
                                data-modal-title="Reset hasla"
                                data-modal-message="Czy na pewno wyslac link resetowania hasla do uzytkownika {{ $user->email }}?"
                                data-modal-icon="bi-key-fill"
                                data-modal-button="Wyslij link"
                                data-modal-button-class="btn-primary"
                            >
                                @csrf
                                <button
                                    type="submit"
                                    class="btn btn-sm btn-outline-primary data-table-icon-button"
                                    data-bs-toggle="tooltip"
                                    data-bs-title="Wyslij link resetowania hasla"
                                    aria-label="Wyslij link resetowania hasla"
                                >
                                    <i class="bi bi-key-fill" aria-hidden="true"></i>
                                </button>
                            </form>

                            @if ($isActiveUser)
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-secondary data-table-icon-button"
                                    data-bs-toggle="tooltip"
                                    data-bs-title="Konto jest aktywne"
                                    aria-label="Konto jest aktywne"
                                    disabled
                                >
                                    <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                                </button>
                            @else
                                <form
                                    method="POST"
                                    action="{{ route('admin.users.activate', $user) }}"
                                    class="m-0 admin-user-confirm-form"
                                    data-modal-title="{{ $activationModalTitle }}"
                                    data-modal-message="{{ $activationModalMessage }}"
                                    data-modal-icon="{{ $activationIcon }}"
                                    data-modal-button="{{ $activationModalButton }}"
                                    data-modal-button-class="{{ $activationModalButtonClass }}"
                                >
                                    @csrf
                                    @method('PATCH')
                                    <button
                                        type="submit"
                                        class="btn btn-sm {{ $activationButtonClass }} data-table-icon-button"
                                        data-bs-toggle="tooltip"
                                        data-bs-title="{{ $activationActionLabel }}"
                                        aria-label="{{ $activationActionLabel }}"
                                    >
                                        <i class="bi {{ $activationIcon }}" aria-hidden="true"></i>
                                    </button>
                                </form>
                            @endif

                            <form
                                method="POST"
                                action="{{ route('admin.users.block', $user) }}"
                                class="m-0 admin-user-block-form"
                                data-email="{{ $user->email }}"
                            >
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="reason" value="">
                                <button
                                    type="submit"
                                    class="btn btn-sm btn-outline-warning data-table-icon-button"
                                    data-bs-toggle="tooltip"
                                    data-bs-title="Zablokuj konto"
                                    aria-label="Zablokuj konto"
                                    @disabled($isCurrentUser)
                                >
                                    <i class="bi bi-lock-fill" aria-hidden="true"></i>
                                </button>
                            </form>

                            <a
                                href="{{ route('admin.users.security', $user) }}"
                                class="btn btn-sm btn-outline-dark data-table-icon-button"
                                data-bs-toggle="tooltip"
                                data-bs-title="Adresy IP i zdarzenia"
                                aria-label="Adresy IP i zdarzenia"
                            >
                                <i class="bi bi-shield-lock-fill" aria-hidden="true"></i>
                            </a>

                            <form
                                method="POST"
                                action="{{ route('admin.users.destroy', $user) }}"
                                class="m-0 admin-user-confirm-form"
                                data-modal-title="Usuniecie konta"
                                data-modal-message="Czy na pewno usunac uzytkownika {{ $user->email }}? Tej operacji nie da sie latwo cofnac. Jesli uzytkownik ma powiazane dane, system wyswietli blad i konto trzeba bedzie zablokowac zamiast usuwac."
                                data-modal-icon="bi-trash3-fill"
                                data-modal-button="Usun konto"
                                data-modal-button-class="btn-danger"
                            >
                                @csrf
                                @method('DELETE')
                                <button
                                    type="submit"
                                    class="btn btn-sm btn-outline-danger data-table-icon-button"
                                    data-bs-toggle="tooltip"
                                    data-bs-title="Usun konto"
                                    aria-label="Usun konto"
                                    @disabled($isCurrentUser)
                                >
                                    <i class="bi bi-trash3-fill" aria-hidden="true"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($columns) }}" class="py-4 text-center text-muted">
                        Brak uzytkownikow do wyswietlenia.
                    </td>
                </tr>
            @endforelse
        </x-data-table>
    </div>

    <div
        class="modal fade"
        id="admin-user-action-modal"
        tabindex="-1"
        aria-labelledby="admin-user-action-modal-title"
        aria-hidden="true"
    >
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h5" id="admin-user-action-modal-title">
                        Potwierdz akcje
                    </h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                </div>

                <div class="modal-body">
                    <div class="gap-3 d-flex align-items-start">
                        <span class="admin-user-modal-icon">
                            <i class="bi bi-question-circle-fill" aria-hidden="true"></i>
                        </span>
                        <p class="mb-0" data-admin-user-action-message></p>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-dark" data-bs-dismiss="modal">
                        Anuluj
                    </button>
                    <button type="button" class="btn btn-primary" data-admin-user-action-submit>
                        Potwierdz
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div
        class="modal fade"
        id="admin-user-block-modal"
        tabindex="-1"
        aria-labelledby="admin-user-block-modal-title"
        aria-hidden="true"
    >
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h5" id="admin-user-block-modal-title">
                        Zablokuj konto
                    </h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                </div>

                <div class="modal-body">
                    <div class="gap-3 mb-3 d-flex align-items-start">
                        <span class="admin-user-modal-icon admin-user-modal-icon-warning">
                            <i class="bi bi-lock-fill" aria-hidden="true"></i>
                        </span>
                        <p class="mb-0" data-admin-user-block-message></p>
                    </div>

                    <label class="form-label" for="admin-user-block-reason">
                        Powod blokady
                    </label>
                    <textarea
                        id="admin-user-block-reason"
                        class="form-control"
                        rows="4"
                        placeholder="Opcjonalnie wpisz powod widoczny w historii i mailu"
                    ></textarea>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-dark" data-bs-dismiss="modal">
                        Anuluj
                    </button>
                    <button type="button" class="btn btn-warning" data-admin-user-block-submit>
                        <i class="bi bi-lock-fill me-1" aria-hidden="true"></i>
                        Zablokuj
                    </button>
                </div>
            </div>
        </div>
    </div>

    <style>
        .admin-user-name-column {
            width: 18%;
        }

        .admin-user-actions-column {
            width: 180px;
        }

        .admin-user-avatar {
            display: inline-grid;
            width: 36px;
            height: 36px;
            flex: 0 0 36px;
            place-items: center;
            border-radius: 50%;
            background: #212529;
            color: #fff;
        }

        .admin-user-name,
        .admin-user-email {
            overflow-wrap: anywhere;
        }

        .admin-user-actions {
            display: flex;
            max-width: 100%;
            flex-wrap: wrap;
            gap: .35rem;
            align-items: center;
            justify-content: flex-start;
        }

        .admin-user-actions form {
            margin: 0;
        }

        .admin-user-actions form,
        .admin-user-actions > a,
        .admin-user-actions > button {
            flex: 0 0 32px;
        }

        .admin-user-modal-icon {
            display: inline-grid;
            width: 42px;
            height: 42px;
            flex: 0 0 42px;
            place-items: center;
            border-radius: 50%;
            background: #212529;
            color: #fff;
        }

        .admin-user-modal-icon-warning {
            background: #ffc107;
            color: #212529;
        }

        @media (max-width: 767.98px) {
            .admin-user-actions {
                align-items: stretch;
            }

            .admin-user-actions form,
            .admin-user-actions > a,
            .admin-user-actions > button {
                flex: 1 1 32px;
            }

            .admin-user-actions .data-table-icon-button {
                width: 100%;
            }
        }
    </style>

    <script>
        document.addEventListener('submit', (event) => {
            const form = event.target;

            if (form.matches('.admin-user-confirm-form')) {
                const modalElement = document.getElementById('admin-user-action-modal');
                const modal = modalElement && window.bootstrap?.Modal
                    ? bootstrap.Modal.getOrCreateInstance(modalElement)
                    : null;
                const submitButton = modalElement?.querySelector('[data-admin-user-action-submit]');
                const message = modalElement?.querySelector('[data-admin-user-action-message]');
                const title = modalElement?.querySelector('#admin-user-action-modal-title');
                const icon = modalElement?.querySelector('.admin-user-modal-icon i');

                if (!modal || !submitButton || !message || !title || !icon) {
                    return;
                }

                event.preventDefault();
                window.adminUserPendingForm = form;

                title.textContent = form.dataset.modalTitle || 'Potwierdz akcje';
                message.textContent = form.dataset.modalMessage || 'Czy na pewno wykonac te akcje?';
                submitButton.textContent = form.dataset.modalButton || 'Potwierdz';
                submitButton.className = `btn ${form.dataset.modalButtonClass || 'btn-primary'}`;
                icon.className = `bi ${form.dataset.modalIcon || 'bi-question-circle-fill'}`;

                submitButton.onclick = () => {
                    const pendingForm = window.adminUserPendingForm;
                    window.adminUserPendingForm = null;
                    modal.hide();
                    pendingForm?.submit();
                };

                modal.show();

                return;
            }

            if (form.matches('.admin-user-block-form')) {
                const modalElement = document.getElementById('admin-user-block-modal');
                const modal = modalElement && window.bootstrap?.Modal
                    ? bootstrap.Modal.getOrCreateInstance(modalElement)
                    : null;
                const submitButton = modalElement?.querySelector('[data-admin-user-block-submit]');
                const message = modalElement?.querySelector('[data-admin-user-block-message]');
                const reason = modalElement?.querySelector('#admin-user-block-reason');

                if (!modal || !submitButton || !message || !reason) {
                    return;
                }

                event.preventDefault();
                window.adminUserPendingBlockForm = form;
                reason.value = '';
                message.textContent = `Czy na pewno zablokowac konto ${form.dataset.email || 'uzytkownika'}? Uzytkownik otrzyma wiadomosc e-mail z informacja o blokadzie.`;

                submitButton.onclick = () => {
                    const pendingForm = window.adminUserPendingBlockForm;
                    window.adminUserPendingBlockForm = null;
                    modal.hide();

                    if (pendingForm) {
                        pendingForm.elements.namedItem('reason').value = reason.value;
                        pendingForm.submit();
                    }
                };

                modal.show();
            }
        });
    </script>
</x-app-layout>
