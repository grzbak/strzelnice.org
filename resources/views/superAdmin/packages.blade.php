<x-app-layout>
    <div class="container py-4">
        <h1>Pakiety</h1>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <form method="POST" action="{{ route('admin.packages.store') }}" class="mb-4">
            @csrf

            <input type="hidden" name="shooting_range_id" id="shooting_range_id" value="{{ old('shooting_range_id', 0) }}">

            <div class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label">Okres</label>
                    <input type="number" name="period_months" class="form-control" min="1" value="{{ old('period_months') }}">
                    @error('period_months') <div class="text-danger">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-2">
                    <label class="form-label">Wartość</label>
                    <input type="number" name="value" class="form-control" min="0" step="0.01" value="{{ old('value') }}">
                    @error('value') <div class="text-danger">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-2">
                    <label class="form-label">Obowiązuje od</label>
                    <input
                        type="date"
                        name="valid_from"
                        class="form-control"
                        value="{{ old('valid_from', now()->format('Y-m-d')) }}"
                        max="3000-12-31"
                        min="2000-01-01"
                    >
                    @error('valid_from') <div class="text-danger">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label">Strzelnica</label>
                    <div>
                        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#shootingRangeModal">
                            Wybierz strzelnicę
                        </button>

                        <span id="selectedShootingRangeName" class="ms-2 text-muted">Pakiet ogólny</span>

                        <button type="button" id="removeShootingRange" class="btn btn-sm btn-outline-danger ms-2 d-none">
                            Usuń
                        </button>
                    </div>
                    @error('shooting_range_id') <div class="text-danger">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-2">
                    <button class="btn btn-success w-100">Dodaj</button>
                </div>
            </div>
        </form>

        <div class="mb-3 d-flex justify-content-between align-items-center">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="showArchivedPackages">
                <label class="form-check-label" for="showArchivedPackages">
                    Pokaż archiwalne
                </label>
            </div>

            <div style="max-width: 320px; width: 100%;">
                <input
                    type="text"
                    id="packageSearch"
                    class="form-control"
                    placeholder="Szukaj pakietów"
                >
            </div>
        </div>

        <table class="table">
            <thead>
                <tr>
                    <th>Okres</th>
                    <th>Wartość</th>
                    <th>Obowiązuje od</th>
                    <th>Strzelnica</th>
                    <th>Status</th>
                    <th>Akcje</th>
                </tr>
            </thead>
            <tbody id="packagesTable"></tbody>
        </table>

        <div class="d-flex justify-content-between align-items-center">
            <div id="packagesInfo" class="text-muted small"></div>

            <nav>
                <ul class="mb-0 pagination pagination-sm" id="packagesPagination"></ul>
            </nav>
        </div>
    </div>

    <div class="modal fade" id="shootingRangeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Wybierz strzelnicę</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <input
                            type="text"
                            id="shootingRangeSearch"
                            class="form-control"
                            placeholder="Szukaj po nazwie, mieście lub województwie"
                        >
                    </div>

                    <table class="table align-middle table-hover">
                        <thead>
                            <tr>
                                <th>Nazwa</th>
                                <th>Miasto</th>
                                <th>Województwo</th>
                                <th class="text-end">Akcja</th>
                            </tr>
                        </thead>
                        <tbody id="shootingRangeModalTable"></tbody>
                    </table>

                    <div class="d-flex justify-content-between align-items-center">
                        <div id="shootingRangeModalInfo" class="text-muted small"></div>

                        <nav>
                            <ul class="mb-0 pagination pagination-sm" id="shootingRangeModalPagination"></ul>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const packages = @json($packages);
            const shootingRanges = @json($shootingRanges);
            const oldShootingRangeId = Number(@json((int) old('shooting_range_id', 0)));

            const packagesTable = document.getElementById('packagesTable');
            const packagesPagination = document.getElementById('packagesPagination');
            const packagesInfo = document.getElementById('packagesInfo');
            const packageSearch = document.getElementById('packageSearch');
            const showArchivedPackages = document.getElementById('showArchivedPackages');

            const shootingRangeIdInput = document.getElementById('shooting_range_id');
            const selectedName = document.getElementById('selectedShootingRangeName');
            const removeButton = document.getElementById('removeShootingRange');

            const shootingRangeSearch = document.getElementById('shootingRangeSearch');
            const modalTable = document.getElementById('shootingRangeModalTable');
            const modalPagination = document.getElementById('shootingRangeModalPagination');
            const modalInfo = document.getElementById('shootingRangeModalInfo');

            const packageDeleteUrlTemplate = @json(route('admin.packages.destroy', ['package' => '__ID__']));
            const csrfToken = @json(csrf_token());

            let currentPackagesPage = 1;
            const packagesPerPage = 10;

            let currentShootingRangePage = 1;
            const shootingRangesPerPage = 10;

            const sortedPackages = [...packages].sort((a, b) => {
                const dateCompare = String(b.valid_from ?? '').localeCompare(String(a.valid_from ?? ''));

                if (dateCompare !== 0) {
                    return dateCompare;
                }

                return Number(b.id ?? 0) - Number(a.id ?? 0);
            });



            const today = new Date().toISOString().slice(0, 10);

            function packageKey(packageItem) {
                return `${Number(packageItem.shooting_range_id ?? 0)}-${Number(packageItem.period_months ?? 0)}`;
            }

            const packageGroups = new Map();

            sortedPackages.forEach(packageItem => {
                const key = packageKey(packageItem);

                if (!packageGroups.has(key)) {
                    packageGroups.set(key, []);
                }

                packageGroups.get(key).push(packageItem);
            });

            function packageStatus(packageItem) {
                const validFrom = packageDate(packageItem);

                if (validFrom > today) {
                    return 'waiting';
                }

                const group = packageGroups.get(packageKey(packageItem)) ?? [];

                const activePackage = group
                    .filter(item => packageDate(item) <= today)
                    .sort((a, b) => {
                        const dateCompare = packageDate(b).localeCompare(packageDate(a));

                        if (dateCompare !== 0) {
                            return dateCompare;
                        }

                        return Number(b.id ?? 0) - Number(a.id ?? 0);
                    })[0];

                if (activePackage && Number(activePackage.id) === Number(packageItem.id)) {
                    return 'active';
                }

                return 'archived';
            }

            function packageStatusLabel(packageItem) {
                const status = packageStatus(packageItem);

                if (status === 'active') {
                    return 'Aktywny';
                }

                if (status === 'waiting') {
                    return 'Oczekujący';
                }

                return 'Archiwalny';
            }

            function packageStatusClass(packageItem) {
                const status = packageStatus(packageItem);

                if (status === 'active') {
                    return 'bg-success';
                }

                if (status === 'waiting') {
                    return 'bg-warning text-dark';
                }

                return 'bg-secondary';
            }





            function escapeHtml(value) {
                return String(value ?? '')
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#039;');
            }

            function packageShootingRangeName(packageItem) {
                return Number(packageItem.shooting_range_id)
                    ? (packageItem.shooting_range?.name ?? '')
                    : 'Pakiet ogólny';
            }

            function isPackageActive(packageItem) {
                return packageStatus(packageItem) === 'active';
            }

            function packageDate(packageItem) {
                return String(packageItem.valid_from ?? '').slice(0, 10);
            }

            function filteredPackages() {
                const search = packageSearch.value.toLowerCase().trim();
                const showArchived = showArchivedPackages.checked;

                return sortedPackages.filter(packageItem => {
                    if (!showArchived && packageStatus(packageItem) === 'archived') {
                        return false;
                    }

                    if (!search) {
                        return true;
                    }

                    const shootingRangeName = packageShootingRangeName(packageItem);

                    return String(packageItem.period_months ?? '').toLowerCase().includes(search)
                        || String(packageItem.value ?? '').toLowerCase().includes(search)
                        || packageDate(packageItem).toLowerCase().includes(search)
                        || String(shootingRangeName ?? '').toLowerCase().includes(search);
                });
            }

            function renderPackagesTable() {
                const filtered = filteredPackages();
                const totalPages = Math.max(1, Math.ceil(filtered.length / packagesPerPage));

                if (currentPackagesPage > totalPages) {
                    currentPackagesPage = totalPages;
                }

                const start = (currentPackagesPage - 1) * packagesPerPage;
                const paginated = filtered.slice(start, start + packagesPerPage);

                packagesTable.innerHTML = '';

                if (paginated.length === 0) {
                    packagesTable.innerHTML = `
                        <tr>
                            <td colspan="6" class="text-center text-muted">Brak pakietów.</td>
                        </tr>
                    `;
                } else {
                    paginated.forEach(packageItem => {
                        const status = packageStatus(packageItem);
                        const deleteUrl = packageDeleteUrlTemplate.replace('__ID__', packageItem.id);

                        packagesTable.innerHTML += `
                            <tr>
                                <td>${escapeHtml(packageItem.period_months)} mies.</td>
                                <td>${escapeHtml(packageItem.value)} zł</td>
                                <td>${escapeHtml(packageDate(packageItem))}</td>
                                <td>${escapeHtml(packageShootingRangeName(packageItem))}</td>
                                <td>
                                    <span class="badge ${packageStatusClass(packageItem)}">
                                        ${packageStatusLabel(packageItem)}
                                    </span>
                                </td>
                                <td>
                                    <form method="POST" action="${deleteUrl}" class="d-inline">
                                        <input type="hidden" name="_token" value="${csrfToken}">
                                        <input type="hidden" name="_method" value="DELETE">

                                        <button class="btn btn-sm btn-danger" onclick="return confirm('Usunąć pakiet?')">
                                            Usuń
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        `;
                    });
                }

                packagesInfo.textContent = `Wyświetlono ${paginated.length} z ${filtered.length}`;
                renderPackagesPagination(totalPages);
            }

            function renderPackagesPagination(totalPages) {
                packagesPagination.innerHTML = '';

                packagesPagination.innerHTML += `
                    <li class="page-item ${currentPackagesPage === 1 ? 'disabled' : ''}">
                        <button type="button" class="page-link" data-package-page="${currentPackagesPage - 1}">
                            Poprzednia
                        </button>
                    </li>
                `;

                for (let page = 1; page <= totalPages; page++) {
                    packagesPagination.innerHTML += `
                        <li class="page-item ${page === currentPackagesPage ? 'active' : ''}">
                            <button type="button" class="page-link" data-package-page="${page}">
                                ${page}
                            </button>
                        </li>
                    `;
                }

                packagesPagination.innerHTML += `
                    <li class="page-item ${currentPackagesPage === totalPages ? 'disabled' : ''}">
                        <button type="button" class="page-link" data-package-page="${currentPackagesPage + 1}">
                            Następna
                        </button>
                    </li>
                `;
            }

            function filteredShootingRanges() {
                const search = shootingRangeSearch.value.toLowerCase().trim();

                if (!search) {
                    return shootingRanges;
                }

                return shootingRanges.filter(range => {
                    return String(range.name ?? '').toLowerCase().includes(search)
                        || String(range.town ?? '').toLowerCase().includes(search)
                        || String(range.voivodeship ?? '').toLowerCase().includes(search);
                });
            }

            function renderShootingRangeModalTable() {
                const filtered = filteredShootingRanges();
                const totalPages = Math.max(1, Math.ceil(filtered.length / shootingRangesPerPage));

                if (currentShootingRangePage > totalPages) {
                    currentShootingRangePage = totalPages;
                }

                const start = (currentShootingRangePage - 1) * shootingRangesPerPage;
                const paginated = filtered.slice(start, start + shootingRangesPerPage);

                modalTable.innerHTML = '';

                if (paginated.length === 0) {
                    modalTable.innerHTML = `
                        <tr>
                            <td colspan="4" class="text-center text-muted">Brak strzelnic.</td>
                        </tr>
                    `;
                } else {
                    paginated.forEach(range => {
                        modalTable.innerHTML += `
                            <tr>
                                <td><strong>${escapeHtml(range.name)}</strong></td>
                                <td>${escapeHtml(range.town)}</td>
                                <td>${escapeHtml(range.voivodeship)}</td>
                                <td class="text-end">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-primary choose-shooting-range"
                                        data-id="${range.id}"
                                        data-name="${escapeHtml(range.name)}"
                                        data-bs-dismiss="modal"
                                    >
                                        Wybierz
                                    </button>
                                </td>
                            </tr>
                        `;
                    });
                }

                modalInfo.textContent = `Wyświetlono ${paginated.length} z ${filtered.length}`;
                renderShootingRangeModalPagination(totalPages);
            }

            function renderShootingRangeModalPagination(totalPages) {
                modalPagination.innerHTML = '';

                modalPagination.innerHTML += `
                    <li class="page-item ${currentShootingRangePage === 1 ? 'disabled' : ''}">
                        <button type="button" class="page-link" data-range-page="${currentShootingRangePage - 1}">
                            Poprzednia
                        </button>
                    </li>
                `;

                for (let page = 1; page <= totalPages; page++) {
                    modalPagination.innerHTML += `
                        <li class="page-item ${page === currentShootingRangePage ? 'active' : ''}">
                            <button type="button" class="page-link" data-range-page="${page}">
                                ${page}
                            </button>
                        </li>
                    `;
                }

                modalPagination.innerHTML += `
                    <li class="page-item ${currentShootingRangePage === totalPages ? 'disabled' : ''}">
                        <button type="button" class="page-link" data-range-page="${currentShootingRangePage + 1}">
                            Następna
                        </button>
                    </li>
                `;
            }

            function chooseShootingRange(id, name) {
                shootingRangeIdInput.value = id;
                selectedName.textContent = name;
                selectedName.classList.remove('text-muted');
                removeButton.classList.remove('d-none');
            }

            packagesPagination.addEventListener('click', event => {
                const button = event.target.closest('[data-package-page]');

                if (!button) {
                    return;
                }

                const page = Number(button.dataset.packagePage);
                const totalPages = Math.max(1, Math.ceil(filteredPackages().length / packagesPerPage));

                if (page < 1 || page > totalPages) {
                    return;
                }

                currentPackagesPage = page;
                renderPackagesTable();
            });

            packageSearch.addEventListener('input', () => {
                currentPackagesPage = 1;
                renderPackagesTable();
            });

            showArchivedPackages.addEventListener('change', () => {
                currentPackagesPage = 1;
                renderPackagesTable();
            });

            shootingRangeSearch.addEventListener('input', () => {
                currentShootingRangePage = 1;
                renderShootingRangeModalTable();
            });

            modalPagination.addEventListener('click', event => {
                const button = event.target.closest('[data-range-page]');

                if (!button) {
                    return;
                }

                const page = Number(button.dataset.rangePage);
                const totalPages = Math.max(1, Math.ceil(filteredShootingRanges().length / shootingRangesPerPage));

                if (page < 1 || page > totalPages) {
                    return;
                }

                currentShootingRangePage = page;
                renderShootingRangeModalTable();
            });

            modalTable.addEventListener('click', event => {
                const button = event.target.closest('.choose-shooting-range');

                if (!button) {
                    return;
                }

                chooseShootingRange(button.dataset.id, button.dataset.name);
            });

            removeButton.addEventListener('click', () => {
                shootingRangeIdInput.value = 0;
                selectedName.textContent = 'Pakiet ogólny';
                selectedName.classList.add('text-muted');
                removeButton.classList.add('d-none');
            });

            if (oldShootingRangeId > 0) {
                const selectedRange = shootingRanges.find(range => Number(range.id) === oldShootingRangeId);

                if (selectedRange) {
                    chooseShootingRange(selectedRange.id, selectedRange.name);
                }
            }

            renderPackagesTable();
            renderShootingRangeModalTable();
        });
    </script>
</x-app-layout>
