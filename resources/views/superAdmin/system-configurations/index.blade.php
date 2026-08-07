<x-app-layout>
    @php
        $columns = [
            ['key' => 'id', 'label' => 'ID'],
            ['key' => 'company', 'label' => 'Firma'],
            ['key' => 'city', 'label' => 'Miasto'],
            ['key' => 'email', 'label' => 'E-mail'],
            ['key' => 'status', 'label' => 'Status'],
            ['key' => 'created_at', 'label' => 'Utworzono'],
            ['key' => 'actions', 'label' => 'Akcje', 'class' => 'system-config-actions-column'],
        ];
    @endphp

    <div class="page-bg-2"></div>

    <div class="container py-4">
        <header class="p-4 text-white rounded bg-secondary">
            <div class="gap-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                <div>
                    <div class="gap-2 mb-2 d-flex align-items-center">
                        <i class="bi bi-sliders fs-4"></i>
                        <span class="opacity-75 text-uppercase small">Panel superadmina</span>
                    </div>
                    <h1 class="mb-1 h3">Konfiguracja systemu</h1>
                    <p class="mb-0 opacity-75">Dane firmy i historia ich zmian.</p>
                </div>

                <form method="GET" action="{{ route('admin.system-configurations.index') }}" class="p-2 bg-white bg-opacity-25 rounded">
                    <div class="form-check form-switch">
                        <input
                            type="checkbox"
                            id="show_archived"
                            name="show_archived"
                            value="1"
                            class="form-check-input"
                            @checked($showArchived)
                            onchange="this.form.submit()"
                        >
                        <label for="show_archived" class="form-check-label">Pokaż archiwalne</label>
                    </div>
                </form>
            </div>
        </header>

        <div class="mt-3">
            <x-session-alerts />
        </div>

        <x-data-table
            id="system-configurations-table"
            :columns="$columns"
            :searchable="false"
            :ajax="false"
        >
            @forelse ($configurations as $configuration)
                <tr @class(['opacity-75' => $configuration->is_archived])>
                    <td data-label="ID">{{ $configuration->id }}</td>
                    <td data-label="Firma"><strong>{{ $configuration->company_name_1 ?: 'Brak nazwy' }}</strong></td>
                    <td data-label="Miasto">{{ $configuration->city ?: '—' }}</td>
                    <td data-label="E-mail">{{ $configuration->email ?: '—' }}</td>
                    <td data-label="Status">
                        <span class="badge {{ $configuration->is_archived ? 'text-bg-secondary' : 'text-bg-success' }}">
                            {{ $configuration->is_archived ? 'Archiwalna' : 'Aktywna' }}
                        </span>
                    </td>
                    <td data-label="Utworzono">{{ $configuration->created_at?->format('d.m.Y H:i') }}</td>
                    <td data-label="Akcje">
                        <div class="system-config-actions">
                            <a href="{{ route('admin.system-configurations.show', $configuration) }}" class="btn btn-sm btn-outline-secondary data-table-icon-button" data-bs-toggle="tooltip" data-bs-title="Pokaż konfigurację" aria-label="Pokaż konfigurację">
                                <i class="bi bi-eye" aria-hidden="true"></i>
                            </a>
                            <a href="{{ route('admin.system-configurations.edit', $configuration) }}" class="btn btn-sm btn-outline-primary data-table-icon-button" data-bs-toggle="tooltip" data-bs-title="Edytuj konfigurację" aria-label="Edytuj konfigurację">
                                <i class="bi bi-pencil" aria-hidden="true"></i>
                            </a>

                            @if ($configuration->is_archived)
                                <form method="POST" action="{{ route('admin.system-configurations.restore', $configuration) }}" class="m-0 system-config-restore-form" data-message="Przywrócić konfigurację #{{ $configuration->id }} jako nową aktywną wersję?">
                                    @csrf
                                    @method('PATCH')
                                    <button type="button" class="btn btn-sm btn-outline-success data-table-icon-button system-config-restore-button" data-bs-toggle="tooltip" data-bs-title="Przywróć jako aktywną" aria-label="Przywróć jako aktywną">
                                        <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
                                    </button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($columns) }}" class="py-4 text-center text-muted">Brak konfiguracji.</td>
                </tr>
            @endforelse
        </x-data-table>
    </div>

    <div class="modal fade" id="systemConfigRestoreModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h5">Przywróć konfigurację</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0" data-system-config-restore-message></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-dark" data-bs-dismiss="modal">Anuluj</button>
                    <button type="button" class="btn btn-success" data-system-config-restore-confirm>
                        <i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>
                        Przywróć
                    </button>
                </div>
            </div>
        </div>
    </div>

    <style>
        .system-config-actions-column { width: 120px; }
        .system-config-actions { display: flex; flex-wrap: wrap; gap: .35rem; }
    </style>

    <script>
        document.addEventListener('click', event => {
            const button = event.target.closest('.system-config-restore-button');

            if (!button) {
                return;
            }

            const form = button.closest('form');
            const modalElement = document.getElementById('systemConfigRestoreModal');
            const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
            const message = modalElement.querySelector('[data-system-config-restore-message]');
            const confirmButton = modalElement.querySelector('[data-system-config-restore-confirm]');

            message.textContent = form.dataset.message || 'Przywrócić tę wersję jako nową aktywną konfigurację?';
            confirmButton.onclick = () => {
                modal.hide();
                form.submit();
            };

            modal.show();
        });
    </script>
</x-app-layout>
