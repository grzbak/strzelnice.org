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
            search-placeholder="Imie, nazwisko lub email"
            :sort="$sort"
            :direction="$direction"
            :per-page="$perPage"
            :per-page-options="[10, 20, 50]"
        >
            @forelse ($users as $user)
                @php
                    $status = $statusConfig($user);
                    $isCurrentUser = auth()->id() === $user->id;
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
                                class="m-0"
                                onsubmit="return confirm('Czy na pewno wyslac link resetowania hasla do uzytkownika {{ addslashes($user->email) }}?')"
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

                            <form
                                method="POST"
                                action="{{ route('admin.users.activate', $user) }}"
                                class="m-0"
                                onsubmit="return confirm('Czy na pewno aktywowac konto uzytkownika {{ addslashes($user->email) }}?')"
                            >
                                @csrf
                                @method('PATCH')
                                <button
                                    type="submit"
                                    class="btn btn-sm btn-outline-success data-table-icon-button"
                                    data-bs-toggle="tooltip"
                                    data-bs-title="Aktywuj konto"
                                    aria-label="Aktywuj konto"
                                >
                                    <i class="bi bi-person-check-fill" aria-hidden="true"></i>
                                </button>
                            </form>

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
                                class="m-0"
                                onsubmit="return confirm('Czy na pewno usunac uzytkownika {{ addslashes($user->email) }}? Tej operacji nie da sie latwo cofnac.')"
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

    <style>
        .admin-user-name-column {
            width: 18%;
        }

        .admin-user-actions-column {
            width: 190px;
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
            display: grid;
            grid-template-columns: repeat(5, 32px);
            gap: .35rem;
            align-items: center;
        }

        .admin-user-actions form {
            margin: 0;
        }

        @media (max-width: 767.98px) {
            .admin-user-actions {
                grid-template-columns: repeat(5, minmax(0, 1fr));
                align-items: stretch;
            }

            .admin-user-actions .data-table-icon-button {
                width: 100%;
            }
        }
    </style>

    <script>
        document.addEventListener('submit', (event) => {
            const form = event.target;

            if (!form.matches('.admin-user-block-form')) {
                return;
            }

            const email = form.dataset.email || 'uzytkownika';
            const reason = window.prompt(
                `Podaj powod blokady konta ${email}. Pozostaw puste, aby uzyc komunikatu domyslnego.`
            );

            if (reason === null) {
                event.preventDefault();
                return;
            }

            if (!window.confirm(`Czy na pewno zablokowac konto ${email}? Uzytkownik otrzyma wiadomosc e-mail.`)) {
                event.preventDefault();
                return;
            }

            form.elements.namedItem('reason').value = reason;
        });
    </script>
</x-app-layout>
