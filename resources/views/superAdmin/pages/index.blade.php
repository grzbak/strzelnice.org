<x-app-layout>
    <x-session-alerts />

    <div class="page-bg-2"></div>

    @include('components.breadcrumb')

    <div class="container py-4">
        <div class="mb-3 d-flex justify-content-between align-items-center">
            <div>
                <h1 class="mb-1">Strony</h1>
                <p class="mb-0 text-muted">Zarządzaj prostymi stronami dostępnymi pod adresem /nazwa_strony.</p>
            </div>

            <a href="{{ route('admin.pages.create') }}" class="btn btn-success">
                <i class="bi bi-file-earmark-plus me-1"></i>
                Dodaj stronę
            </a>
        </div>

        <div class="mb-3" style="max-width: 360px;">
            <input type="text" id="pageSearch" class="form-control" placeholder="Szukaj strony">
        </div>

        <table class="table bg-white shadow-sm table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Nazwa w nawigacji</th>
                    <th>Nazwa wyświetlana</th>
                    <th>Status</th>
                    <th>Aktualizacja</th>
                    <th class="text-end">Akcje</th>
                </tr>
            </thead>
            <tbody id="pagesTable"></tbody>
        </table>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const pages = @json($pages);
            const table = document.getElementById('pagesTable');
            const search = document.getElementById('pageSearch');
            const csrfToken = @json(csrf_token());
            const editTemplate = @json(route('admin.pages.edit', ['page' => '__ID__']));
            const destroyTemplate = @json(route('admin.pages.destroy', ['page' => '__ID__']));
            const statusTemplate = @json(route('admin.pages.toggle-status', ['page' => '__ID__']));
            const publicTemplate = @json(url('/__NAVIGATION_NAME__'));

            function escapeHtml(value) {
                return String(value ?? '')
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#039;');
            }

            function render() {
                const term = search.value.toLowerCase().trim();
                const filtered = pages.filter(page => {
                    if (!term) {
                        return true;
                    }

                    return String(page.navigation_name ?? '').toLowerCase().includes(term)
                        || String(page.display_name ?? '').toLowerCase().includes(term)
                        || String(page.content ?? '').toLowerCase().includes(term);
                });

                table.innerHTML = '';

                if (filtered.length === 0) {
                    table.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Brak stron.</td></tr>';
                    return;
                }

                filtered.forEach(page => {
                    const editUrl = editTemplate.replace('__ID__', page.id);
                    const destroyUrl = destroyTemplate.replace('__ID__', page.id);
                    const statusUrl = statusTemplate.replace('__ID__', page.id);
                    const publicUrl = publicTemplate.replace('__NAVIGATION_NAME__', page.navigation_name);
                    const statusClass = page.is_active ? 'btn-success' : 'btn-outline-secondary';
                    const statusText = page.is_active ? 'Aktywna' : 'Nieaktywna';
                    const statusIcon = page.is_active ? 'bi-toggle-on' : 'bi-toggle-off';

                    table.innerHTML += `
                        <tr>
                            <td>${page.id}</td>
                            <td><code>${escapeHtml(page.navigation_name)}</code></td>
                            <td><strong>${escapeHtml(page.display_name)}</strong></td>
                            <td>
                                <form method="POST" action="${statusUrl}" class="d-inline">
                                    <input type="hidden" name="_token" value="${csrfToken}">
                                    <input type="hidden" name="_method" value="PATCH">
                                    <button type="submit" class="btn btn-sm ${statusClass}" title="Zmień status strony">
                                        <i class="bi ${statusIcon} me-1"></i>
                                        ${statusText}
                                    </button>
                                </form>
                            </td>
                            <td>${escapeHtml(String(page.updated_at ?? '').slice(0, 10))}</td>
                            <td class="text-end">
                                <a href="${publicUrl}" target="_blank" class="btn btn-sm btn-outline-secondary" title="Podgląd">
                                    <i class="bi bi-box-arrow-up-right"></i>
                                </a>

                                <a href="${editUrl}" class="btn btn-sm btn-primary">
                                    <i class="bi bi-pencil me-1"></i>
                                    Edytuj
                                </a>

                                <form method="POST" action="${destroyUrl}" class="d-inline" onsubmit="return confirm('Usunąć stronę?')">
                                    <input type="hidden" name="_token" value="${csrfToken}">
                                    <input type="hidden" name="_method" value="DELETE">
                                    <button class="btn btn-sm btn-danger">
                                        <i class="bi bi-trash3 me-1"></i>
                                        Usuń
                                    </button>
                                </form>
                            </td>
                        </tr>
                    `;
                });
            }

            search.addEventListener('input', render);
            render();
        });
    </script>
</x-app-layout>