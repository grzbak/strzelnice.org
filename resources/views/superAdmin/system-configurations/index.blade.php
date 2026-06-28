<x-app-layout>
    <div class="page-bg-2"></div>

    @include('components.breadcrumb')

    <div class="container py-4">
        <x-session-alerts />

        <div class="p-3 mb-3 bg-white shadow-sm d-flex justify-content-between align-items-end">
            <div>
                <h1 class="mb-1">Konfiguracja systemu</h1>
                <p class="mb-0 text-muted">Dane firmy i historia ich zmian.</p>
            </div>

            <form method="GET" action="{{ route('admin.system-configurations.index') }}">
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

        <div class="table-responsive">
            <table class="table bg-white shadow-sm table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Firma</th>
                        <th>Miasto</th>
                        <th>E-mail</th>
                        <th>Status</th>
                        <th>Utworzono</th>
                        <th class="text-end">Akcje</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($configurations as $configuration)
                        <tr @class(['table-secondary opacity-75' => $configuration->is_archived])>
                            <td>{{ $configuration->id }}</td>
                            <td><strong>{{ $configuration->company_name_1 ?: 'Brak nazwy' }}</strong></td>
                            <td>{{ $configuration->city ?: '—' }}</td>
                            <td>{{ $configuration->email ?: '—' }}</td>
                            <td>
                                <span class="badge {{ $configuration->is_archived ? 'text-bg-secondary' : 'text-bg-success' }}">
                                    {{ $configuration->is_archived ? 'Archiwalna' : 'Aktywna' }}
                                </span>
                            </td>
                            <td>{{ $configuration->created_at?->format('d.m.Y H:i') }}</td>
                            <td class="text-end text-nowrap">
                                <a
                                    href="{{ route('admin.system-configurations.show', $configuration) }}"
                                    class="btn btn-sm btn-outline-secondary"
                                    title="Pokaż konfigurację"
                                >
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a
                                    href="{{ route('admin.system-configurations.edit', $configuration) }}"
                                    class="btn btn-sm btn-primary"
                                    title="Edytuj konfigurację"
                                >
                                    <i class="bi bi-pencil"></i>
                                </a>

                                @if ($configuration->is_archived)
                                    <form
                                        method="POST"
                                        action="{{ route('admin.system-configurations.restore', $configuration) }}"
                                        class="d-inline"
                                        onsubmit="return confirm('Przywrócić tę wersję jako nową aktywną konfigurację?')"
                                    >
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-sm btn-outline-success">
                                            <i class="bi bi-arrow-counterclockwise me-1"></i>
                                            Przywróć
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-4 text-center text-muted">Brak konfiguracji.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
