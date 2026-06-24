<x-app-layout>
    <x-session-alerts />

    @if(session('import_errors') && count(session('import_errors')))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong>Wystąpiły błędy podczas importu:</strong>

            <ul class="mt-2 mb-0">
                @foreach(session('import_errors') as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>

            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="page-bg-2"></div>

    @if (session('error'))
        <script>
            console.error(@json(session('error')));
        </script>
    @endif

    @include('components.breadcrumb')

    <div class="container py-2">
        <div class="mb-3 row">
            <div class="col-md-4">
                <input type="text" id="search" class="form-control" placeholder="Szukaj...">
            </div>

            <div class="col-md-3">
                <select id="sort" class="form-select">
                    <option value="">Sortuj</option>
                    <option value="name">Nazwa</option>
                    <option value="town">Miasto</option>
                    <option value="voivodeship">Województwo</option>
                </select>
            </div>

            <div class="col-md-3">
                <select id="perPage" class="form-select">
                    <option value="10">10 na stronę</option>
                    <option value="50">50 na stronę</option>
                    <option value="100">100 na stronę</option>
                </select>
            </div>

            <div class="col-md-2">
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#importRangesModal">
                    <i class="bi bi-filetype-xls action-icon" data-bs-toggle="tooltip" data-bs-placement="top" title="Import z pliku *.xls(x)"></i>
                </button>
                <button class="btn btn-success" onclick="window.location='{{ route('admin.shootings.create') }}'">
                    <i class="bi bi-house-add-fill action-icon" data-bs-toggle="tooltip" data-bs-placement="top" title="Dodaj nową strzelnicę"></i>
                </button>
            </div>
        </div>

        <form id="bulkDeleteForm" method="POST" action="{{ route('admin.shootings.bulk-destroy') }}" class="mb-2">
            @csrf
            <input type="hidden" name="archive_with_reservations" id="bulkArchiveWithReservations" value="0">
            <div id="bulkDeleteIds"></div>

            <button type="submit" class="btn btn-outline-danger btn-sm" id="bulkDeleteButton" disabled>
                <i class="bi bi-trash3 me-1"></i>
                Usuń zaznaczone
            </button>

            <small class="ms-2 text-muted">
                Usunięcie obejmuje przedziały czasowe, galerie, zdjęcia, godziny pracy, dni wolne i zaproszenia.
            </small>
        </form>

        <table class="table bg-white shadow-sm table-hover">
            <thead class="table-light">
                <tr>
                    <th style="width: 40px;"><input type="checkbox" id="selectAllShootings"></th>
                    <th>Nazwa</th>
                    <th>Adres</th>
                    <th>Województwo</th>
                    <th>Status</th>
                    <th>Akcje</th>
                </tr>
            </thead>
            <tbody id="shootingTable"></tbody>
        </table>

        <nav>
            <ul class="pagination" id="pagination"></ul>
        </nav>
    </div>

    <div class="modal fade" id="importRangesModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('shooting-ranges.import') }}" enctype="multipart/form-data" class="modal-content">
                @csrf

                <div class="modal-header">
                    <h5 class="modal-title">Import strzelnic z XLS/XLSX</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <input type="file" name="file" class="form-control" accept=".xls,.xlsx" required>

                    <small class="text-muted">
                        Pierwszy wiersz musi zawierać nazwy kolumn jak we wzorze.
                    </small>
                </div>

                <div class="modal-footer">
                    <a href="{{ asset('files/download/wzor_importu_strzelnic.xls') }}" class="btn btn-outline-secondary">
                        Pobierz wzór
                    </a>

                    <button type="submit" class="btn btn-success">
                        Importuj
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const shootings = @json($shootings);
        const selectedShootingIds = new Set();
        const deleteBaseUrl = @json(url('/strzelnice'));
        let currentPage = 1;

        function todayDate() {
            return new Date().toISOString().slice(0, 10);
        }

        function normalizeDate(value) {
            return value ? String(value).slice(0, 10) : null;
        }

        function escapeHtml(value) {
            const div = document.createElement('div');
            div.textContent = value ?? '';
            return div.innerHTML;
        }

        function getActiveInterval(shootingRange) {
            return shootingRange.active_interval ?? null;
        }

        function shootingRangeStatus(shootingRange) {
            const activeInterval = getActiveInterval(shootingRange);

            if (!activeInterval) {
                return {
                    icon: 'bi-calendar-x',
                    color: 'text-secondary',
                    text: 'Brak aktywnych przedziałów',
                };
            }

            if (activeInterval.datePaid) {
                return {
                    icon: 'bi-check-circle-fill',
                    color: 'text-success',
                    text: `Aktywny przedział od ${normalizeDate(activeInterval.start_date)} do ${normalizeDate(activeInterval.end_date)}. Opłacony.`,
                };
            }

            const paymentDueDate = normalizeDate(activeInterval.payment_due_date);
            const today = todayDate();

            if (paymentDueDate && paymentDueDate >= today) {
                return {
                    icon: 'bi-clock-fill',
                    color: 'text-warning',
                    text: `Aktywny przedział od ${normalizeDate(activeInterval.start_date)} do ${normalizeDate(activeInterval.end_date)}. Termin płatności: ${paymentDueDate}`,
                };
            }

            return {
                icon: 'bi-exclamation-triangle-fill',
                color: 'text-danger',
                text: paymentDueDate
                    ? `Aktywny przedział od ${normalizeDate(activeInterval.start_date)} do ${normalizeDate(activeInterval.end_date)}. Termin płatności: ${paymentDueDate}`
                    : 'Aktywny przedział nieopłacony. Brak terminu płatności.',
            };
        }

        function renderStatusIcon(shootingRange) {
            const status = shootingRangeStatus(shootingRange);

            return `
                <i
                    class="bi ${status.icon} action-icon ${status.color}"
                    data-bs-toggle="tooltip"
                    data-bs-placement="top"
                    title="${escapeHtml(status.text)}"
                ></i>
            `;
        }

        function refreshTooltips() {
            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(element => {
                const oldTooltip = bootstrap.Tooltip.getInstance(element);

                if (oldTooltip) {
                    oldTooltip.dispose();
                }

                new bootstrap.Tooltip(element);
            });
        }

        function dependentDeleteWarningText(rangeText) {
            return `${rangeText}\n\nRazem ze strzelnicami zostaną usunięte przedziały czasowe, galerie, zdjęcia, godziny pracy, dni wolne i zaproszenia.\n\nCzy na pewno kontynuować?`;
        }

        function confirmArchiveText(rangeText) {
            return `${rangeText}\n\nDo wybranych strzelnic istnieją rezerwacje, dlatego nie zostaną usunięte. Można je zarchiwizować - wtedy parametr archiwum zostanie ustawiony na 1, a strzelnice zostaną ukryte.\n\nCzy chcesz je zarchiwizować?`;
        }

        function refreshBulkDeleteState() {
            const button = document.getElementById('bulkDeleteButton');
            const selectAll = document.getElementById('selectAllShootings');
            const visibleCheckboxes = document.querySelectorAll('.shooting-select');

            button.disabled = selectedShootingIds.size === 0;

            if (selectAll) {
                selectAll.checked = visibleCheckboxes.length > 0
                    && Array.from(visibleCheckboxes).every(checkbox => checkbox.checked);
            }
        }

        function render() {
            let filtered = [...shootings];

            const search = document.getElementById('search').value.toLowerCase();
            const sort = document.getElementById('sort').value;
            const perPage = parseInt(document.getElementById('perPage').value);

            if (search) {
                filtered = filtered.filter(s =>
                    String(s.name ?? '').toLowerCase().includes(search) ||
                    String(s.voivodeship ?? '').toLowerCase().includes(search) ||
                    String(s.town ?? '').toLowerCase().includes(search) ||
                    String(s.street ?? '').toLowerCase().includes(search) ||
                    String(s.houseNumber ?? '').toLowerCase().includes(search)
                );
            }

            if (sort === 'name') {
                filtered.sort((a, b) => String(a.name ?? '').localeCompare(String(b.name ?? '')));
            }

            if (sort === 'voivodeship') {
                filtered.sort((a, b) => String(a.voivodeship ?? '').localeCompare(String(b.voivodeship ?? '')));
            }

            if (sort === 'town') {
                filtered.sort((a, b) => String(a.town ?? '').localeCompare(String(b.town ?? '')));
            }

            const start = (currentPage - 1) * perPage;
            const paginated = filtered.slice(start, start + perPage);
            const table = document.getElementById('shootingTable');

            table.innerHTML = '';

            paginated.forEach(s => {
                const reservationsCount = Number(s.reservations_count || 0);
                const escapedName = escapeHtml(s.name ?? '');

                table.innerHTML += `
                    <tr class="align-middle">
                        <td>
                            <input type="checkbox" class="shooting-select" value="${s.id}" ${selectedShootingIds.has(String(s.id)) ? 'checked' : ''}>
                        </td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div><strong>${escapedName}</strong></div>
                                ${reservationsCount > 0 ? `<span class="ms-2 badge bg-warning text-dark">rezerwacje: ${reservationsCount}</span>` : ''}
                            </div>
                        </td>
                        <td>${escapeHtml(s.street ?? '')} ${escapeHtml(s.houseNumber ?? '')}, ${escapeHtml(s.zipCode ?? '')} ${escapeHtml(s.town ?? '')}</td>
                        <td>${escapeHtml(s.voivodeship ?? '')}</td>
                        <td>${renderStatusIcon(s)}</td>
                        <td>
                            <form method="POST" action="${deleteBaseUrl}/${s.id}/widocznosc" class="d-inline">
                                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                <input type="hidden" name="_method" value="PATCH">
                                <button type="submit" class="p-0 bg-transparent border-0" title="${s.is_visible ? 'Ukryj na stronie' : 'Pokaż na stronie'}">
                                    <i class="bi ${s.is_visible ? 'bi-eye' : 'bi-eye-slash'} action-icon ${s.is_visible ? 'text-success' : 'text-danger'}" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-original-title="${s.is_visible ? 'Ukryj na stronie' : 'Pokaż na stronie'}"></i>
                                </button>
                            </form>

                            <i class="bi bi-clock action-icon text-warning" onclick="window.location.href='${deleteBaseUrl}/${s.id}/przedzialy'" data-bs-toggle="tooltip" data-bs-placement="top" title="Zarządzaj przedziałami"></i>
                            <i class="bi bi-pencil action-icon text-dark" onclick="window.location.href='${deleteBaseUrl}/${s.id}/edytuj'" data-bs-toggle="tooltip" data-bs-placement="top" title="Edytuj"></i>
                            <i class="bi bi-images action-icon text-primary" onclick="window.location.href='${deleteBaseUrl}/${s.id}/galeria'" data-bs-toggle="tooltip" data-bs-placement="top" title="Zarządzaj galerią"></i>

                            <form method="POST" action="${deleteBaseUrl}/${s.id}" class="d-inline shooting-delete-form" data-range-name="${escapedName}" data-reservations-count="${reservationsCount}">
                                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                <input type="hidden" name="_method" value="DELETE">
                                <input type="hidden" name="archive_with_reservations" value="0">
                                <button type="submit" class="p-0 bg-transparent border-0" data-bs-toggle="tooltip" data-bs-placement="top" title="Usuń lub archiwizuj strzelnicę">
                                    <i class="bi bi-trash3 action-icon text-danger"></i>
                                </button>
                            </form>
                        </td>
                    </tr>`;
            });

            table.querySelectorAll('.shooting-select').forEach(checkbox => {
                checkbox.addEventListener('change', () => {
                    if (checkbox.checked) {
                        selectedShootingIds.add(checkbox.value);
                    } else {
                        selectedShootingIds.delete(checkbox.value);
                    }

                    refreshBulkDeleteState();
                });
            });

            table.querySelectorAll('.shooting-delete-form').forEach(form => {
                form.addEventListener('submit', (event) => {
                    const reservationsCount = Number(form.dataset.reservationsCount || 0);
                    const rangeName = form.dataset.rangeName || 'wybraną strzelnicę';
                    const archiveInput = form.querySelector('input[name="archive_with_reservations"]');

                    if (reservationsCount > 0) {
                        if (!confirm(confirmArchiveText(`Strzelnica: ${rangeName}`))) {
                            event.preventDefault();
                            return;
                        }

                        archiveInput.value = '1';
                        return;
                    }

                    if (!confirm(dependentDeleteWarningText(`Strzelnica: ${rangeName}`))) {
                        event.preventDefault();
                    }
                });
            });

            renderPagination(filtered.length, perPage);
            refreshBulkDeleteState();
            refreshTooltips();
        }

        function renderPagination(total, perPage) {
            const pages = Math.ceil(total / perPage);
            const pag = document.getElementById('pagination');
            pag.innerHTML = '';

            for (let i = 1; i <= pages; i++) {
                pag.innerHTML += `
                    <li class="page-item ${i === currentPage ? 'active' : ''}">
                        <a class="page-link" href="#" onclick="goPage(${i}); return false;">${i}</a>
                    </li>`;
            }
        }

        function goPage(p) {
            currentPage = p;
            render();
        }

        document.getElementById('selectAllShootings').addEventListener('change', function () {
            document.querySelectorAll('.shooting-select').forEach(checkbox => {
                checkbox.checked = this.checked;

                if (checkbox.checked) {
                    selectedShootingIds.add(checkbox.value);
                } else {
                    selectedShootingIds.delete(checkbox.value);
                }
            });

            refreshBulkDeleteState();
        });

        document.getElementById('bulkDeleteForm').addEventListener('submit', function (event) {
            if (selectedShootingIds.size === 0) {
                event.preventDefault();
                return;
            }

            const selectedRanges = shootings.filter(s => selectedShootingIds.has(String(s.id)));
            const withReservations = selectedRanges.filter(s => Number(s.reservations_count || 0) > 0);
            const bulkIds = document.getElementById('bulkDeleteIds');
            const archiveInput = document.getElementById('bulkArchiveWithReservations');

            if (withReservations.length > 0) {
                const names = withReservations.map(s => `- ${s.name}`).join('\n');
                const withoutReservationsCount = selectedRanges.length - withReservations.length;
                const deleteInfo = withoutReservationsCount > 0
                    ? `\n\nPozostałe wybrane strzelnice bez rezerwacji (${withoutReservationsCount}) zostaną usunięte razem z przedziałami czasowymi, galeriami, zdjęciami, godzinami pracy, dniami wolnymi i zaproszeniami.`
                    : '';

                if (!confirm(`${confirmArchiveText(`Wybrane strzelnice z rezerwacjami:\n${names}`)}${deleteInfo}`)) {
                    event.preventDefault();
                    return;
                }

                archiveInput.value = '1';
            } else {
                if (!confirm(dependentDeleteWarningText(`Wybrano ${selectedRanges.length} strzelnic.`))) {
                    event.preventDefault();
                    return;
                }

                archiveInput.value = '0';
            }

            bulkIds.innerHTML = '';
            selectedShootingIds.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'shooting_range_ids[]';
                input.value = id;
                bulkIds.appendChild(input);
            });
        });

        document.getElementById('search').addEventListener('input', () => {
            currentPage = 1;
            render();
        });

        document.getElementById('sort').addEventListener('change', () => {
            currentPage = 1;
            render();
        });

        document.getElementById('perPage').addEventListener('change', () => {
            currentPage = 1;
            render();
        });

        render();
    </script>
</x-app-layout>
