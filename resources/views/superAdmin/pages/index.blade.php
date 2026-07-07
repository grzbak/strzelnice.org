<x-app-layout>
    <x-session-alerts />

    <div class="page-bg-2"></div>

    @include('components.breadcrumb')

    @php
        $pageColumns = [
            ['key' => 'id', 'label' => 'ID', 'sortable' => true, 'class' => 'pages-id-column'],
            ['key' => 'navigation_name', 'label' => 'Nazwa w nawigacji', 'sortable' => true],
            ['key' => 'display_name', 'label' => 'Nazwa wyświetlana', 'sortable' => true],
            ['key' => 'is_active', 'label' => 'Status', 'sortable' => true],
            ['key' => 'updated_at', 'label' => 'Aktualizacja', 'sortable' => true],
            ['key' => 'actions', 'label' => 'Akcje', 'class' => 'pages-actions-column'],
        ];
    @endphp

    <div class="container py-4">
        <header class="p-4 text-white rounded bg-secondary">
            <div class="gap-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                <div>
                    <div class="gap-2 mb-2 d-flex align-items-center">
                        <i class="bi bi-file-earmark-text-fill fs-4"></i>
                        <span class="opacity-75 text-uppercase small">
                            Panel superadmina
                        </span>
                    </div>

                    <h1 class="mb-0 h3">
                        Strony
                    </h1>
                </div>

                <a
                    href="{{ route('admin.pages.create') }}"
                    class="btn btn-success pages-header-action"
                    title="Dodaj stronę"
                    aria-label="Dodaj stronę"
                >
                    <i class="bi bi-file-earmark-plus-fill" aria-hidden="true"></i>
                    <span>Dodaj</span>
                </a>
            </div>
        </header>

        <x-data-table
            id="pages-table"
            :action="route('admin.pages.index')"
            :columns="$pageColumns"
            :paginator="$pages"
            :search="$search"
            search-label="Wyszukaj stronę"
            search-placeholder="Nazwa w nawigacji, nazwa wyświetlana lub treść"
            :sort="$sort"
            :direction="$direction"
            :per-page="$perPage"
            :per-page-options="[10, 20, 50]"
        >
            @forelse ($pages as $page)
                <tr class="align-middle">
                    <td data-label="ID">
                        {{ $page->id }}
                    </td>

                    <td data-label="Nazwa w nawigacji">
                        <code>{{ $page->navigation_name }}</code>
                    </td>

                    <td data-label="Nazwa wyświetlana">
                        <strong>{{ $page->display_name }}</strong>
                    </td>

                    <td data-label="Status">
                        <form method="POST" action="{{ route('admin.pages.toggle-status', $page) }}" class="m-0">
                            @csrf
                            @method('PATCH')
                            <button
                                type="submit"
                                class="btn btn-sm {{ $page->is_active ? 'btn-success' : 'btn-outline-secondary' }} pages-status-button"
                                data-bs-toggle="tooltip"
                                data-bs-title="Zmień status strony"
                                aria-label="Zmień status strony"
                            >
                                <i class="bi {{ $page->is_active ? 'bi-toggle-on' : 'bi-toggle-off' }}" aria-hidden="true"></i>
                                <span>{{ $page->is_active ? 'Aktywna' : 'Nieaktywna' }}</span>
                            </button>
                        </form>
                    </td>

                    <td data-label="Aktualizacja">
                        {{ optional($page->updated_at)->format('Y-m-d') ?? '-' }}
                    </td>

                    <td data-label="Akcje">
                        <div class="pages-actions">
                            <a
                                href="{{ url($page->navigation_name) }}"
                                target="_blank"
                                rel="noopener"
                                class="btn btn-sm btn-outline-secondary data-table-icon-button"
                                data-bs-toggle="tooltip"
                                data-bs-title="Podgląd"
                                aria-label="Podgląd"
                            >
                                <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
                            </a>

                            <a
                                href="{{ route('admin.pages.edit', $page) }}"
                                class="btn btn-sm btn-outline-primary data-table-icon-button"
                                data-bs-toggle="tooltip"
                                data-bs-title="Edytuj"
                                aria-label="Edytuj"
                            >
                                <i class="bi bi-pencil" aria-hidden="true"></i>
                            </a>

                            <form
                                method="POST"
                                action="{{ route('admin.pages.destroy', $page) }}"
                                class="m-0 delete-page-form"
                                data-page-name="{{ $page->display_name }}"
                            >
                                @csrf
                                @method('DELETE')
                                <button
                                    type="submit"
                                    class="btn btn-sm btn-outline-danger data-table-icon-button"
                                    data-bs-toggle="tooltip"
                                    data-bs-title="Usuń"
                                    aria-label="Usuń"
                                >
                                    <i class="bi bi-trash3" aria-hidden="true"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($pageColumns) }}" class="py-4 text-center text-muted">
                        @if ($search !== '')
                            Brak stron pasujących do wyszukiwania.
                        @else
                            Brak stron do wyświetlenia.
                        @endif
                    </td>
                </tr>
            @endforelse
        </x-data-table>
    </div>

    <style>
        .pages-header-action {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            min-height: 38px;
            font-weight: 700;
        }

        .pages-header-action i {
            font-size: 1.1rem;
            line-height: 1;
        }

        .pages-id-column {
            width: 70px;
        }

        .pages-actions-column {
            width: 130px;
        }

        .pages-status-button {
            display: inline-flex;
            min-width: 112px;
            align-items: center;
            justify-content: center;
            gap: .35rem;
            font-weight: 700;
        }

        .pages-actions {
            display: grid;
            grid-template-columns: repeat(3, 32px);
            gap: .35rem;
            align-items: center;
            justify-content: start;
        }

        .pages-actions form {
            margin: 0;
        }

        @media (max-width: 767.98px) {
            .pages-actions {
                grid-template-columns: repeat(3, minmax(0, 1fr));
                align-items: stretch;
            }

            .pages-actions .data-table-icon-button {
                width: 100%;
            }
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.addEventListener('submit', (event) => {
                const form = event.target;

                if (!form.matches('.delete-page-form')) {
                    return;
                }

                const pageName = form.dataset.pageName || 'wybraną stronę';

                if (!confirm(`Usunąć stronę: ${pageName}?`)) {
                    event.preventDefault();
                }
            });
        });
    </script>
</x-app-layout>