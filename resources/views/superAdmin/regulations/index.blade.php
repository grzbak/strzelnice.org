<x-app-layout>
    @php
        $columns = [
            ['key' => 'name', 'label' => 'Nazwa'],
            ['key' => 'page', 'label' => 'Strona'],
            ['key' => 'groups', 'label' => 'Grupy'],
            ['key' => 'period', 'label' => 'Obowiązuje'],
            ['key' => 'version', 'label' => 'Wersja'],
            ['key' => 'status', 'label' => 'Status'],
            ['key' => 'actions', 'label' => 'Akcje', 'class' => 'regulation-actions-column'],
        ];
    @endphp

    <div class="page-bg-2"></div>

    <div class="container py-4">
        <header class="p-4 text-white rounded bg-secondary">
            <div class="gap-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                <div>
                    <div class="gap-2 mb-2 d-flex align-items-center">
                        <i class="bi bi-shield-check fs-4"></i>
                        <span class="opacity-75 text-uppercase small">Panel superadmina</span>
                    </div>
                    <h1 class="mb-1 h3">Regulaminy i zgody</h1>
                    <p class="mb-0 opacity-75">Zarządzanie wymaganymi zgodami i wersjami regulaminów.</p>
                </div>

                <a href="{{ route('admin.regulations.create') }}" class="btn btn-success">
                    <i class="bi bi-plus-circle me-1" aria-hidden="true"></i>
                    Dodaj regulamin
                </a>
            </div>
        </header>

        <div class="mt-3">
            <x-session-alerts />
        </div>

        <x-data-table
            id="regulations-table"
            :columns="$columns"
            :paginator="$regulations"
            :searchable="false"
            :ajax="false"
        >
            @forelse ($regulations as $regulation)
                <tr>
                    <td data-label="Nazwa"><strong>{{ $regulation->name }}</strong></td>
                    <td data-label="Strona">
                        @if ($regulation->page)
                            <a href="{{ route('admin.pages.edit', $regulation->page) }}">{{ $regulation->page->display_name }}</a>
                        @else
                            <span class="text-muted">Strona usunięta</span>
                        @endif
                    </td>
                    <td data-label="Grupy">{{ $regulation->targetRoleLabels() }}</td>
                    <td data-label="Obowiązuje">
                        {{ $regulation->starts_at?->format('Y-m-d') }}
                        -
                        {{ $regulation->is_indefinite ? 'bezterminowo' : $regulation->ends_at?->format('Y-m-d') }}
                    </td>
                    <td data-label="Wersja">
                        @if ($regulation->currentVersion)
                            <span class="fw-semibold">v{{ $regulation->currentVersion->version_number }}</span>
                            <span class="d-block text-muted small">{{ $regulation->currentVersion->published_at?->format('Y-m-d H:i') }}</span>
                        @else
                            <span class="text-muted">Brak</span>
                        @endif
                    </td>
                    <td data-label="Status">
                        <span class="badge {{ $regulation->status === \App\Models\Regulation::STATUS_ACTIVE ? 'text-bg-success' : 'text-bg-secondary' }}">
                            {{ $regulation->status === \App\Models\Regulation::STATUS_ACTIVE ? 'Aktywny' : 'Odwołany' }}
                        </span>
                    </td>
                    <td data-label="Akcje">
                        <div class="regulation-actions">
                            <a href="{{ route('admin.regulations.edit', $regulation) }}" class="btn btn-sm btn-outline-primary data-table-icon-button" data-bs-toggle="tooltip" data-bs-title="Edytuj regulamin" aria-label="Edytuj regulamin">
                                <i class="bi bi-pencil" aria-hidden="true"></i>
                            </a>

                            @if ($regulation->status === \App\Models\Regulation::STATUS_ACTIVE)
                                <form method="POST" action="{{ route('admin.regulations.revoke', $regulation) }}" class="m-0 regulation-revoke-form" data-message="Odwołać regulamin '{{ $regulation->name }}'? Użytkownicy, którzy go zaakceptowali, otrzymają informację e-mail.">
                                    @csrf
                                    @method('PATCH')
                                    <button type="button" class="btn btn-sm btn-outline-danger data-table-icon-button regulation-revoke-button" data-bs-toggle="tooltip" data-bs-title="Odwołaj regulamin" aria-label="Odwołaj regulamin">
                                        <i class="bi bi-x-circle" aria-hidden="true"></i>
                                    </button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($columns) }}" class="py-4 text-center text-muted">Brak regulaminów.</td>
                </tr>
            @endforelse
        </x-data-table>
    </div>

    <div class="modal fade" id="regulationRevokeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h5">Odwołaj regulamin</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0" data-regulation-revoke-message></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-dark" data-bs-dismiss="modal">Anuluj</button>
                    <button type="button" class="btn btn-danger" data-regulation-revoke-confirm>
                        <i class="bi bi-x-circle me-1" aria-hidden="true"></i>
                        Odwołaj
                    </button>
                </div>
            </div>
        </div>
    </div>

    <style>
        .regulation-actions-column { width: 110px; }
        .regulation-actions { display: flex; flex-wrap: wrap; gap: .35rem; }
    </style>

    <script>
        document.addEventListener('click', event => {
            const button = event.target.closest('.regulation-revoke-button');

            if (!button) {
                return;
            }

            const form = button.closest('form');
            const modalElement = document.getElementById('regulationRevokeModal');
            const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
            const message = modalElement.querySelector('[data-regulation-revoke-message]');
            const confirmButton = modalElement.querySelector('[data-regulation-revoke-confirm]');

            message.textContent = form.dataset.message || 'Odwołać regulamin?';
            confirmButton.onclick = () => {
                modal.hide();
                form.submit();
            };

            modal.show();
        });
    </script>
</x-app-layout>
