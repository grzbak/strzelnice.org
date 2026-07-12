<x-app-layout>
    <x-session-alerts />
    <div class="page-bg-2"></div>

    @include('components.breadcrumb')

    <div class="container py-4">
        <header class="p-4 text-white rounded bg-secondary">
            <div class="gap-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                <div>
                    <div class="gap-2 mb-2 d-flex align-items-center">
                        <i class="bi bi-shield-check fs-4"></i>
                        <span class="opacity-75 text-uppercase small">Panel superadmina</span>
                    </div>
                    <h1 class="mb-0 h3">Regulaminy i zgody</h1>
                </div>

                <a href="{{ route('admin.regulations.create') }}" class="btn btn-success">
                    <i class="bi bi-plus-circle me-1"></i>
                    Dodaj regulamin
                </a>
            </div>
        </header>

        <div class="mt-4 bg-white shadow-sm table-responsive">
            <table class="table mb-0 align-middle table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Nazwa</th>
                        <th>Strona</th>
                        <th>Grupy</th>
                        <th>Obowiązuje</th>
                        <th>Wersja</th>
                        <th>Status</th>
                        <th class="text-end">Akcje</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($regulations as $regulation)
                        <tr>
                            <td><strong>{{ $regulation->name }}</strong></td>
                            <td>
                                @if ($regulation->page)
                                    <a href="{{ route('admin.pages.edit', $regulation->page) }}">{{ $regulation->page->display_name }}</a>
                                @else
                                    <span class="text-muted">Strona usunięta</span>
                                @endif
                            </td>
                            <td>{{ $regulation->targetRoleLabels() }}</td>
                            <td>
                                {{ $regulation->starts_at?->format('Y-m-d') }}
                                -
                                {{ $regulation->is_indefinite ? 'bezterminowo' : $regulation->ends_at?->format('Y-m-d') }}
                            </td>
                            <td>
                                @if ($regulation->currentVersion)
                                    v{{ $regulation->currentVersion->version_number }}
                                    <span class="text-muted small">{{ $regulation->currentVersion->published_at?->format('Y-m-d H:i') }}</span>
                                @else
                                    <span class="text-muted">Brak</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $regulation->status === \App\Models\Regulation::STATUS_ACTIVE ? 'text-bg-success' : 'text-bg-secondary' }}">
                                    {{ $regulation->status === \App\Models\Regulation::STATUS_ACTIVE ? 'Aktywny' : 'Odwołany' }}
                                </span>
                            </td>
                            <td>
                                <div class="gap-2 d-flex justify-content-end">
                                    <a href="{{ route('admin.regulations.edit', $regulation) }}" class="btn btn-sm btn-outline-primary" title="Edytuj">
                                        <i class="bi bi-pencil"></i>
                                    </a>

                                    @if ($regulation->status === \App\Models\Regulation::STATUS_ACTIVE)
                                        <form method="POST" action="{{ route('admin.regulations.revoke', $regulation) }}" onsubmit="return confirm('Odwołać regulamin? Użytkownicy, którzy go zaakceptowali, otrzymają informację e-mail.');">
                                            @csrf
                                            @method('PATCH')
                                            <button class="btn btn-sm btn-outline-danger" title="Odwołaj">
                                                <i class="bi bi-x-circle"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-4 text-center text-muted">Brak regulaminów.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">{{ $regulations->links() }}</div>
    </div>
</x-app-layout>
