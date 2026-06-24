<x-app-layout>

    @if(session('import_success') || session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('import_success') ?? session('success') }}

            <button type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                    aria-label="Close"></button>
        </div>
    @endif

    @if(session('import_errors') && count(session('import_errors')))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">

            <strong>Wystąpiły błędy podczas importu:</strong>

            <ul class="mt-2 mb-0">
                @foreach(session('import_errors') as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>

            <button type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                    aria-label="Close"></button>
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

        <!-- FILTRY -->
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
                    <i
                        class="bi bi-filetype-xls action-icon"
                        data-bs-toggle="tooltip"
                        data-bs-placement="top"
                        title="Import z pliku *.xls(x)"
                    ></i>
                </button>
                <button class="btn btn-success" onclick="window.location='{{ route('admin.shootings.create') }}'">
                    <i
                        class="bi bi-house-add-fill action-icon"
                        data-bs-toggle="tooltip"
                        data-bs-placement="top"
                        title="Dodaj nową strzelnicę"
                    ></i>
                </button>
            </div>
        </div>


        <table class="table bg-white shadow-sm table-hover">
            <thead class="table-light">
                <tr>
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
            <form method="POST"
                action="{{ route('shooting-ranges.import') }}"
                enctype="multipart/form-data"
                class="modal-content">
                @csrf

                <div class="modal-header">
                    <h5 class="modal-title">Import strzelnic z XLS/XLSX</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <input type="file"
                        name="file"
                        class="form-control"
                        accept=".xls,.xlsx"
                        required>

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
        document.addEventListener('DOMContentLoaded', function () {
            setTimeout(() => {
                document.querySelectorAll('.alert').forEach(alert => {
                    bootstrap.Alert.getOrCreateInstance(alert).close();
                });
            }, 15000);
        });

        const shootings = @json($shootings);
        let currentPage = 1;

        function todayDate() {
            return new Date().toISOString().slice(0, 10);
        }

        function normalizeDate(value) {
            return value ? String(value).slice(0, 10) : null;
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
                    text: `Aktywny przedział od ${normalizeDate(activeInterval.start_date)} do ${normalizeDate(activeInterval.end_date)}. <b class='text-success'>Opłacony.</b>`,
                };
            }

            const paymentDueDate = normalizeDate(activeInterval.payment_due_date);
            const today = todayDate();

            if (paymentDueDate && paymentDueDate >= today) {
                return {
                    icon: 'bi-clock-fill',
                    color: 'text-warning',
                    text: `Aktywny przedział od ${normalizeDate(activeInterval.start_date)} do ${normalizeDate(activeInterval.end_date)}. </br><b>Termin płatności: ${paymentDueDate}</b>`,
                };
            }

            return {
                icon: 'bi-exclamation-triangle-fill',
                color: 'text-danger',
                text: paymentDueDate
                    ? `Aktywny przedział od ${normalizeDate(activeInterval.start_date)} do ${normalizeDate(activeInterval.end_date)}. <b>Termin płatności:</b><b class='text-danger'> ${paymentDueDate}</b>`
                    : 'Aktywny przedział nieopłacony. Brak terminu płatności',
            };
        }

        function renderStatusIcon(shootingRange) {
            const status = shootingRangeStatus(shootingRange);

            return `
                <i
                    class="bi ${status.icon} action-icon ${status.color}"
                    data-bs-toggle="tooltip"
                    data-bs-placement="top"
                    title="${status.text}"
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
                table.innerHTML += `
                    <tr class="align-middle">
                        <td>
                            <div class="d-flex align-items-center">
                                <div><strong>${s.name ?? ''}</strong></div>
                            </div>
                        </td>
                        <td>${s.street ?? ''} ${s.houseNumber ?? ''}, ${s.zipCode ?? ''} ${s.town ?? ''}</td>
                        <td>${s.voivodeship ?? ''}</td>
                        <td>
                            ${renderStatusIcon(s)}
                        </td>
                        <td>
                            <form method="POST" action="/strzelnice/${s.id}/widocznosc" class="d-inline">
                                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                <input type="hidden" name="_method" value="PATCH">

                                <button
                                    type="submit"
                                    class="p-0 bg-transparent border-0"
                                    title="${s.is_visible ? 'Ukryj na stronie' : 'Pokaż na stronie'}"
                                >
                                    <i class="bi ${s.is_visible ? 'bi-eye' : 'bi-eye-slash'} action-icon ${s.is_visible ? 'text-success' : 'text-danger'}" data-bs-toggle="tooltip" data-bs-placement="top" aria-label="${s.is_visible ? 'Ukryj na stronie' : 'Pokaż na stronie'}" data-bs-original-title="${s.is_visible ? 'Ukryj na stronie' : 'Pokaż na stronie'}"></i>
                                </button>
                            </form>

                            <i class="bi bi-clock action-icon text-warning" onclick="window.location.href='/strzelnice/${s.id}/przedzialy'" data-bs-toggle="tooltip" data-bs-placement="top" aria-label="Zarządzaj przedziałami" data-bs-original-title="Zarządzaj przedziałami"></i>
                            <i class="bi bi-pencil action-icon text-dark" onclick="window.location.href='/strzelnice/${s.id}/edytuj'" data-bs-toggle="tooltip" data-bs-placement="top" aria-label="Edytuj" data-bs-original-title="Edytuj"></i>
                            <i class="bi bi-images action-icon text-primary" onclick="window.location.href='/strzelnice/${s.id}/galeria'" data-bs-toggle="tooltip" data-bs-placement="top" aria-label="Zarządzaj galerią" data-bs-original-title="Zarządzaj galerią"></i>
                        </td>
                    </tr>`;
            });

            renderPagination(filtered.length, perPage);
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
