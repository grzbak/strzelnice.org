<x-app-layout>
    <div class="page-bg-2"></div>

    @include('components.breadcrumb')

    <main class="container py-4">
        <x-session-alerts />

        <section class="p-4 mb-4 bg-white border rounded shadow-sm">
            <div class="gap-3 d-flex flex-wrap align-items-center justify-content-between">
                <div>
                    <span class="text-uppercase small text-secondary">Panel strzelnicy</span>
                    <h1 class="mb-1 h3">Personel</h1>
                    <p class="mb-0 text-secondary">{{ $shootingRange->name }}</p>
                </div>

                <button
                    type="button"
                    class="btn btn-success"
                    data-bs-toggle="modal"
                    data-bs-target="#addStaffModal"
                >
                    <i class="bi bi-person-plus-fill me-1"></i>
                    Dodaj osobę
                </button>
            </div>
        </section>

        <section class="p-4 bg-white border rounded shadow-sm">
            @if ($staff->isEmpty())
                <div class="mb-0 alert alert-info">
                    Do strzelnicy nie przypisano jeszcze administratorów ani pracowników.
                </div>
            @else
                <div class="table-responsive">
                    <table class="table mb-0 align-middle table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Imię i nazwisko</th>
                                <th>E-mail</th>
                                <th>Rola w strzelnicy</th>
                                <th>Status konta</th>
                                <th class="text-end">Akcje</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($staff as $staffMember)
                                @php($isRangeAdmin = $staffMember->pivot->role === 'admin')
                                @php(
                                    $cannotDemoteLastAdministrator = $isRangeAdmin
                                        && $staffMember->is_approved
                                        && $activeAdministratorsCount <= 1
                                )
                                <tr>
                                    <td class="fw-semibold">{{ $staffMember->name }}</td>
                                    <td>{{ $staffMember->email }}</td>
                                    <td>
                                        <span class="badge {{ $isRangeAdmin ? 'text-bg-primary' : 'text-bg-secondary' }}">
                                            {{ $isRangeAdmin ? 'Administrator' : 'Pracownik' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="{{ $staffMember->is_approved ? 'text-success' : 'text-danger' }}">
                                            <i class="bi {{ $staffMember->is_approved ? 'bi-check-circle-fill' : 'bi-x-circle-fill' }} me-1"></i>
                                            {{ $staffMember->is_approved ? 'Aktywne' : 'Nieaktywne' }}
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        @if ($canManageAdministrators)
                                            <form
                                                method="POST"
                                                action="{{ route('shooting.staff.update-role', [$shootingRange, $staffMember]) }}"
                                                class="d-inline change-role-form"
                                                data-user-name="{{ $staffMember->name }}"
                                                data-target-role="{{ $isRangeAdmin ? 'pracownika' : 'administratora' }}"
                                            >
                                                @csrf
                                                @method('PATCH')
                                                <input
                                                    type="hidden"
                                                    name="role"
                                                    value="{{ $isRangeAdmin ? 'pracownik' : 'admin' }}"
                                                >
                                                <button
                                                    type="submit"
                                                    class="btn btn-sm btn-outline-primary"
                                                    @disabled($cannotDemoteLastAdministrator)
                                                    title="{{ $cannotDemoteLastAdministrator
                                                        ? 'Najpierw przypisz drugiego aktywnego administratora'
                                                        : ($isRangeAdmin ? 'Zmień na pracownika' : 'Zmień na administratora') }}"
                                                >
                                                    <i class="bi bi-arrow-left-right"></i>
                                                    <span class="visually-hidden">Zmień rolę</span>
                                                </button>
                                            </form>
                                        @endif

                                        @if ($canManageAdministrators || ! $isRangeAdmin)
                                            <form
                                                method="POST"
                                                action="{{ route('shooting.staff.destroy', [$shootingRange, $staffMember]) }}"
                                                class="d-inline remove-staff-form"
                                                data-user-name="{{ $staffMember->name }}"
                                                data-role="{{ $isRangeAdmin ? 'administratora' : 'pracownika' }}"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <button
                                                    type="submit"
                                                    class="btn btn-sm btn-outline-danger"
                                                    title="Usuń ze strzelnicy"
                                                >
                                                    <i class="bi bi-person-dash-fill"></i>
                                                    <span class="visually-hidden">Usuń ze strzelnicy</span>
                                                </button>
                                            </form>
                                        @else
                                            <span
                                                class="text-secondary"
                                                data-bs-toggle="tooltip"
                                                title="Administrator może być usunięty tylko przez superadmina"
                                            >
                                                <i class="bi bi-shield-lock-fill"></i>
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </main>

    <div class="modal fade" id="addStaffModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <span class="text-uppercase small text-secondary">Personel strzelnicy</span>
                        <h2 class="modal-title fs-5">
                            {{ $canManageAdministrators ? 'Dodaj administratora lub pracownika' : 'Dodaj pracownika' }}
                        </h2>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    @if (! $hasActiveAdministrator)
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i>
                            Strzelnica nie ma aktywnego administratora. Do czasu jego
                            przypisania nie można dodawać pracowników.
                        </div>
                    @endif

                    <label for="staffSearch" class="form-label">
                        Wyszukaj po imieniu, nazwisku lub adresie e-mail
                    </label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input
                            type="search"
                            id="staffSearch"
                            class="form-control"
                            minlength="2"
                            maxlength="255"
                            autocomplete="off"
                            placeholder="Wpisz co najmniej 2 znaki"
                        >
                    </div>

                    <div id="staffSearchStatus" class="mt-3 text-secondary small">
                        Wpisz co najmniej 2 znaki, aby rozpocząć wyszukiwanie.
                    </div>
                    <div id="staffSearchResults" class="mt-3 list-group"></div>
                </div>
            </div>
        </div>
    </div>

    <form
        method="POST"
        action="{{ route('shooting.staff.store', $shootingRange) }}"
        id="addStaffForm"
        class="d-none"
    >
        @csrf
        <input type="hidden" name="user_id" id="addStaffUserId">
        <input type="hidden" name="role" id="addStaffRole">
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const searchInput = document.getElementById('staffSearch');
            const status = document.getElementById('staffSearchStatus');
            const results = document.getElementById('staffSearchResults');
            const addForm = document.getElementById('addStaffForm');
            const userIdInput = document.getElementById('addStaffUserId');
            const roleInput = document.getElementById('addStaffRole');
            const searchUrl = @json(route('shooting.staff.search', $shootingRange));
            const canManageAdministrators = @json($canManageAdministrators);
            const hasActiveAdministrator = @json($hasActiveAdministrator);
            let searchTimer;
            let requestController;

            function escapeHtml(value) {
                const element = document.createElement('div');
                element.textContent = value ?? '';

                return element.innerHTML;
            }

            function renderUsers(users) {
                results.innerHTML = '';

                if (!users.length) {
                    status.textContent = 'Nie znaleziono użytkowników, których można dołączyć.';
                    return;
                }

                status.textContent = `Znaleziono: ${users.length}`;

                users.forEach(user => {
                    const item = document.createElement('div');
                    item.className = 'gap-3 list-group-item d-flex flex-wrap align-items-center justify-content-between';
                    item.innerHTML = `
                        <div>
                            <strong>${escapeHtml(user.name)}</strong>
                            <div class="small text-secondary">${escapeHtml(user.email)}</div>
                        </div>
                        <div class="gap-2 d-flex">
                            ${canManageAdministrators ? `
                                <button type="button" class="btn btn-sm btn-outline-primary add-staff-button" data-user-id="${user.id}" data-user-name="${escapeHtml(user.name)}" data-role="admin">
                                    <i class="bi bi-shield-check me-1"></i>
                                    Dołącz jako administratora
                                </button>
                            ` : ''}
                            <button
                                type="button"
                                class="btn btn-sm btn-success add-staff-button"
                                data-user-id="${user.id}"
                                data-user-name="${escapeHtml(user.name)}"
                                data-role="pracownik"
                                ${hasActiveAdministrator ? '' : 'disabled title="Najpierw przypisz aktywnego administratora"'}
                            >
                                <i class="bi bi-person-check me-1"></i>
                                Dołącz jako pracownika
                            </button>
                        </div>
                    `;
                    results.appendChild(item);
                });
            }

            async function searchUsers() {
                const query = searchInput.value.trim();

                if (query.length < 2) {
                    requestController?.abort();
                    results.innerHTML = '';
                    status.textContent = 'Wpisz co najmniej 2 znaki, aby rozpocząć wyszukiwanie.';
                    return;
                }

                requestController?.abort();
                requestController = new AbortController();
                status.textContent = 'Wyszukiwanie...';

                try {
                    const url = new URL(searchUrl, window.location.origin);
                    url.searchParams.set('q', query);
                    const response = await fetch(url, {
                        headers: { Accept: 'application/json' },
                        signal: requestController.signal,
                    });
                    const data = await response.json();

                    if (!response.ok) {
                        throw new Error(data.message ?? 'Nie udało się wyszukać użytkowników.');
                    }

                    renderUsers(data.users ?? []);
                } catch (error) {
                    if (error.name === 'AbortError') {
                        return;
                    }

                    results.innerHTML = '';
                    status.textContent = error.message;
                }
            }

            searchInput.addEventListener('input', () => {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(searchUsers, 300);
            });

            results.addEventListener('click', event => {
                const button = event.target.closest('.add-staff-button');

                if (!button) {
                    return;
                }

                const roleLabel = button.dataset.role === 'admin'
                    ? 'administratora'
                    : 'pracownika';

                if (!confirm(
                    `Czy na pewno chcesz dołączyć użytkownika ${button.dataset.userName} jako ${roleLabel} strzelnicy?`
                )) {
                    return;
                }

                userIdInput.value = button.dataset.userId;
                roleInput.value = button.dataset.role;
                addForm.submit();
            });

            document.querySelectorAll('.change-role-form').forEach(form => {
                form.addEventListener('submit', event => {
                    if (!confirm(
                        `Czy na pewno chcesz zmienić rolę użytkownika ${form.dataset.userName} na ${form.dataset.targetRole}?`
                    )) {
                        event.preventDefault();
                    }
                });
            });

            document.querySelectorAll('.remove-staff-form').forEach(form => {
                form.addEventListener('submit', event => {
                    if (!confirm(
                        `Czy na pewno chcesz usunąć ${form.dataset.role} ${form.dataset.userName} z tej strzelnicy?`
                    )) {
                        event.preventDefault();
                    }
                });
            });

            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(
                element => new bootstrap.Tooltip(element)
            );
        });
    </script>
</x-app-layout>
