<x-app-layout>
    <x-session-alerts />

    <div class="page-bg-2"></div>


    @php
        $packageColumns = [
            ['key' => 'period_months', 'label' => 'Okres', 'sortable' => true],
            ['key' => 'value', 'label' => 'Wartość', 'sortable' => true],
            ['key' => 'valid_from', 'label' => 'Obowiązuje od', 'sortable' => true],
            ['key' => 'shooting_range', 'label' => 'Strzelnica', 'sortable' => true],
            ['key' => 'status', 'label' => 'Status'],
            ['key' => 'actions', 'label' => 'Akcje', 'class' => 'packages-actions-column'],
        ];

        $shootingRangeModalColumns = [
            ['key' => 'name', 'label' => 'Nazwa'],
            ['key' => 'town', 'label' => 'Miasto'],
            ['key' => 'voivodeship', 'label' => 'Województwo'],
            ['key' => 'actions', 'label' => 'Akcja', 'class' => 'packages-modal-actions-column'],
        ];

        $statusMeta = [
            'active' => ['label' => 'Aktywny', 'class' => 'bg-success'],
            'waiting' => ['label' => 'Oczekujący', 'class' => 'bg-warning text-dark'],
            'archived' => ['label' => 'Archiwalny', 'class' => 'bg-secondary'],
        ];

        $toggleArchivedQuery = request()->query();
        unset($toggleArchivedQuery['page']);

        if ($showArchived) {
            unset($toggleArchivedQuery['show_archived']);
        } else {
            $toggleArchivedQuery['show_archived'] = 1;
        }
    @endphp

    <div class="container py-4">
        <header class="p-4 text-white rounded bg-secondary">
            <div class="gap-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                <div>
                    <div class="gap-2 mb-2 d-flex align-items-center">
                        <i class="bi bi-box-seam-fill fs-4"></i>
                        <span class="opacity-75 text-uppercase small">
                            Panel superadmina
                        </span>
                    </div>

                    <h1 class="mb-0 h3">
                        Pakiety
                    </h1>
                </div>

                <a
                    href="{{ route('admin.packages.index', $toggleArchivedQuery) }}"
                    class="btn {{ $showArchived ? 'btn-light' : 'btn-outline-light' }} packages-header-action"
                    title="{{ $showArchived ? 'Ukryj archiwalne pakiety' : 'Pokaż archiwalne pakiety' }}"
                    aria-label="{{ $showArchived ? 'Ukryj archiwalne pakiety' : 'Pokaż archiwalne pakiety' }}"
                >
                    <i class="bi {{ $showArchived ? 'bi-eye-slash-fill' : 'bi-archive-fill' }}" aria-hidden="true"></i>
                    <span>{{ $showArchived ? 'Ukryj archiwalne' : 'Pokaż archiwalne' }}</span>
                </a>
            </div>
        </header>

        <div class="p-3 mt-3 bg-white rounded packages-form-panel">
            <form method="POST" action="{{ route('admin.packages.store') }}">
                @csrf

                <input type="hidden" name="shooting_range_id" id="shooting_range_id" value="{{ old('shooting_range_id', 0) }}">

                <div class="row g-2 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label" for="period_months">Okres</label>
                        <input id="period_months" type="number" name="period_months" class="form-control" min="1" value="{{ old('period_months') }}">
                        @error('period_months') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-2">
                        <label class="form-label" for="value">Wartość</label>
                        <input id="value" type="number" name="value" class="form-control" min="0" step="0.01" value="{{ old('value') }}">
                        @error('value') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-2">
                        <label class="form-label" for="valid_from">Obowiązuje od</label>
                        <input
                            id="valid_from"
                            type="date"
                            name="valid_from"
                            class="form-control"
                            value="{{ old('valid_from', now()->format('Y-m-d')) }}"
                            max="3000-12-31"
                            min="2000-01-01"
                        >
                        @error('valid_from') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Strzelnica</label>
                        <div class="gap-2 d-flex flex-wrap align-items-center">
                            <button
                                type="button"
                                class="btn btn-outline-primary packages-form-icon-button"
                                data-bs-toggle="modal"
                                data-bs-target="#shootingRangeModal"
                                title="Wybierz strzelnicę"
                                aria-label="Wybierz strzelnicę"
                            >
                                <i class="bi bi-crosshair" aria-hidden="true"></i>
                            </button>

                            <span id="selectedShootingRangeName" class="text-muted">Pakiet ogólny</span>

                            <button
                                type="button"
                                id="removeShootingRange"
                                class="btn btn-sm btn-outline-danger packages-form-icon-button d-none"
                                title="Usuń wybraną strzelnicę"
                                aria-label="Usuń wybraną strzelnicę"
                            >
                                <i class="bi bi-x-lg" aria-hidden="true"></i>
                            </button>
                        </div>
                        @error('shooting_range_id') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-2">
                        <button class="btn btn-success w-100 packages-add-button">
                            <i class="bi bi-plus-lg" aria-hidden="true"></i>
                            <span>Dodaj</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <x-data-table
            id="packages-table"
            :action="route('admin.packages.index')"
            :columns="$packageColumns"
            :paginator="$packages"
            :search="$search"
            search-label="Wyszukaj pakiet"
            search-placeholder="Okres, wartość, data lub strzelnica"
            :sort="$sort"
            :direction="$direction"
            :per-page="$perPage"
            :per-page-options="[10, 20, 50]"
        >
            @forelse ($packages as $package)
                @php
                    $status = $packageStatuses[$package->id] ?? 'archived';
                    $statusData = $statusMeta[$status];
                    $shootingRangeName = $package->shooting_range_id
                        ? ($package->shootingRange?->name ?? '-')
                        : 'Pakiet ogólny';
                @endphp

                <tr class="align-middle">
                    <td data-label="Okres">
                        {{ $package->period_months }} mies.
                    </td>

                    <td data-label="Wartość">
                        {{ number_format((float) $package->value, 2, ',', ' ') }} zł
                    </td>

                    <td data-label="Obowiązuje od">
                        {{ optional($package->valid_from)->format('Y-m-d') }}
                    </td>

                    <td data-label="Strzelnica">
                        <strong>{{ $shootingRangeName }}</strong>
                    </td>

                    <td data-label="Status">
                        <span class="badge {{ $statusData['class'] }}">
                            {{ $statusData['label'] }}
                        </span>
                    </td>

                    <td data-label="Akcje">
                        <form
                            method="POST"
                            action="{{ route('admin.packages.destroy', $package) }}"
                            class="m-0 delete-package-form"
                            data-package-name="{{ $package->period_months }} mies. / {{ $shootingRangeName }}"
                        >
                            @csrf
                            @method('DELETE')

                            <button
                                type="submit"
                                class="btn btn-sm btn-outline-danger data-table-icon-button"
                                data-bs-toggle="tooltip"
                                data-bs-title="Usuń pakiet"
                                aria-label="Usuń pakiet"
                            >
                                <i class="bi bi-trash3" aria-hidden="true"></i>
                            </button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($packageColumns) }}" class="py-4 text-center text-muted">
                        @if ($search !== '')
                            Brak pakietów pasujących do wyszukiwania.
                        @else
                            Brak pakietów do wyświetlenia.
                        @endif
                    </td>
                </tr>
            @endforelse
        </x-data-table>
    </div>

    <div class="modal fade" id="shootingRangeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Wybierz strzelnicę</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <x-data-table
                        id="shooting-range-modal-table"
                        :action="route('admin.packages.index')"
                        :columns="$shootingRangeModalColumns"
                        search-label="Wyszukaj strzelnicę"
                        search-placeholder="Nazwa, miasto lub województwo"
                        :per-page="10"
                        :per-page-options="[10, 20, 50]"
                        :ajax="false"
                        class="mt-0 packages-modal-table"
                    >
                        @foreach ($shootingRanges as $range)
                            @php
                                $rangeSearchText = \Illuminate\Support\Str::lower(trim(
                                    ($range->name ?? '') . ' ' .
                                    ($range->town ?? '') . ' ' .
                                    ($range->voivodeship ?? '')
                                ));
                            @endphp

                            <tr class="shooting-range-modal-row" data-search="{{ $rangeSearchText }}">
                                <td data-label="Nazwa"><strong>{{ $range->name }}</strong></td>
                                <td data-label="Miasto">{{ $range->town }}</td>
                                <td data-label="Województwo">{{ $range->voivodeship }}</td>
                                <td data-label="Akcja">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-primary choose-shooting-range"
                                        data-id="{{ $range->id }}"
                                        data-name="{{ $range->name }}"
                                        data-bs-dismiss="modal"
                                    >
                                        Wybierz
                                    </button>
                                </td>
                            </tr>
                        @endforeach

                        <tr id="shootingRangeModalEmpty" class="d-none">
                            <td colspan="{{ count($shootingRangeModalColumns) }}" class="text-center text-muted">
                                Brak strzelnic.
                            </td>
                        </tr>
                    </x-data-table>

                    <div class="gap-3 pt-2 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                        <small id="shootingRangeModalInfo" class="text-muted"></small>

                        <div class="gap-2 d-flex align-items-center" id="shootingRangeModalPagination">
                            <button
                                type="button"
                                class="btn btn-outline-dark btn-sm data-table-icon-button"
                                id="shootingRangeModalPrev"
                                data-bs-toggle="tooltip"
                                data-bs-title="Poprzednia strona"
                                aria-label="Poprzednia strona"
                            >
                                <i class="bi bi-chevron-left" aria-hidden="true"></i>
                            </button>

                            <label class="visually-hidden" for="shootingRangeModalPage">
                                Numer strony
                            </label>
                            <select
                                id="shootingRangeModalPage"
                                class="form-select form-select-sm"
                                title="Wybierz stronę"
                            ></select>

                            <button
                                type="button"
                                class="btn btn-outline-dark btn-sm data-table-icon-button"
                                id="shootingRangeModalNext"
                                data-bs-toggle="tooltip"
                                data-bs-title="Następna strona"
                                aria-label="Następna strona"
                            >
                                <i class="bi bi-chevron-right" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .packages-form-panel,
        .data-table-panel {
            border: 1px solid rgba(52, 58, 64, .12);
            box-shadow: 0 .75rem 1.5rem rgba(15, 23, 42, .08);
        }

        .packages-header-action,
        .packages-add-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .45rem;
            min-height: 38px;
            font-weight: 700;
        }

        .packages-header-action i,
        .packages-add-button i {
            font-size: 1.1rem;
            line-height: 1;
        }

        .packages-form-icon-button {
            display: inline-grid;
            width: 38px;
            height: 38px;
            padding: 0;
            place-items: center;
        }

        .packages-form-icon-button i {
            font-size: 1.1rem;
            line-height: 1;
        }

        .packages-actions-column {
            width: 80px;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const shootingRanges = @json($shootingRanges);
            const oldShootingRangeId = Number(@json((int) old('shooting_range_id', 0)));

            const shootingRangeIdInput = document.getElementById('shooting_range_id');
            const selectedName = document.getElementById('selectedShootingRangeName');
            const removeButton = document.getElementById('removeShootingRange');

            const shootingRangeSearch = document.getElementById('shooting-range-modal-table-search');
            const shootingRangePerPage = document.getElementById('shooting-range-modal-table-per-page');
            const shootingRangeClearSearch = document.querySelector('#shooting-range-modal-table-search-form .data-table-icon-button');
            const modalResults = document.getElementById('shooting-range-modal-table-results');
            const modalEmpty = document.getElementById('shootingRangeModalEmpty');
            const modalPagination = document.getElementById('shootingRangeModalPagination');
            const modalPrev = document.getElementById('shootingRangeModalPrev');
            const modalNext = document.getElementById('shootingRangeModalNext');
            const modalPageSelect = document.getElementById('shootingRangeModalPage');
            const modalInfo = document.getElementById('shootingRangeModalInfo');

            let currentShootingRangePage = 1;

            function shootingRangeRows() {
                return Array.from(document.querySelectorAll('.shooting-range-modal-row'));
            }

            function shootingRangesPerPage() {
                return Number(shootingRangePerPage?.value || 10);
            }

            function updateShootingRangeClearButton() {
                shootingRangeClearSearch?.classList.toggle(
                    'd-none',
                    shootingRangeSearch.value.trim() === ''
                );
            }

            function filteredShootingRangeRows() {
                const search = shootingRangeSearch.value.toLowerCase().trim();

                return shootingRangeRows().filter(row => {
                    return !search || String(row.dataset.search ?? '').includes(search);
                });
            }

            function renderShootingRangeModalTable() {
                const rows = shootingRangeRows();
                const filtered = filteredShootingRangeRows();
                const totalPages = Math.max(1, Math.ceil(filtered.length / shootingRangesPerPage()));

                if (currentShootingRangePage > totalPages) {
                    currentShootingRangePage = totalPages;
                }

                const start = (currentShootingRangePage - 1) * shootingRangesPerPage();
                const paginated = filtered.slice(start, start + shootingRangesPerPage());

                rows.forEach(row => row.classList.add('d-none'));
                paginated.forEach(row => row.classList.remove('d-none'));

                modalEmpty?.classList.toggle('d-none', filtered.length > 0);
                modalInfo.textContent = `Wyświetlono ${paginated.length} z ${filtered.length}`;
                renderShootingRangeModalPagination(totalPages);
            }

            function renderShootingRangeModalPagination(totalPages) {
                modalPageSelect.innerHTML = '';

                for (let page = 1; page <= totalPages; page++) {
                    const option = document.createElement('option');
                    option.value = page;
                    option.textContent = `Strona ${page} z ${totalPages}`;
                    option.selected = page === currentShootingRangePage;
                    modalPageSelect.appendChild(option);
                }

                modalPrev.disabled = currentShootingRangePage === 1;
                modalNext.disabled = currentShootingRangePage === totalPages;
                modalPrev.classList.toggle('disabled', modalPrev.disabled);
                modalNext.classList.toggle('disabled', modalNext.disabled);
            }

            function chooseShootingRange(id, name) {
                shootingRangeIdInput.value = id;
                selectedName.textContent = name;
                selectedName.classList.remove('text-muted');
                removeButton.classList.remove('d-none');
            }

            shootingRangeSearch.addEventListener('input', () => {
                currentShootingRangePage = 1;
                updateShootingRangeClearButton();
                renderShootingRangeModalTable();
            });

            shootingRangePerPage?.addEventListener('change', () => {
                currentShootingRangePage = 1;
                renderShootingRangeModalTable();
            });

            shootingRangeClearSearch?.addEventListener('click', event => {
                event.preventDefault();
                shootingRangeSearch.value = '';
                currentShootingRangePage = 1;
                updateShootingRangeClearButton();
                renderShootingRangeModalTable();
            });

            modalPrev.addEventListener('click', () => {
                if (currentShootingRangePage <= 1) {
                    return;
                }

                currentShootingRangePage -= 1;
                renderShootingRangeModalTable();
            });

            modalNext.addEventListener('click', () => {
                const totalPages = Math.max(1, Math.ceil(filteredShootingRangeRows().length / shootingRangesPerPage()));

                if (currentShootingRangePage >= totalPages) {
                    return;
                }

                currentShootingRangePage += 1;
                renderShootingRangeModalTable();
            });

            modalPageSelect.addEventListener('change', () => {
                currentShootingRangePage = Number(modalPageSelect.value || 1);
                renderShootingRangeModalTable();
            });
            modalResults.addEventListener('click', event => {
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

            document.addEventListener('submit', event => {
                const form = event.target;

                if (!form.matches('.delete-package-form')) {
                    return;
                }

                const packageName = form.dataset.packageName || 'wybrany pakiet';

                if (!confirm(`Usunąć pakiet: ${packageName}?`)) {
                    event.preventDefault();
                }
            });

            if (oldShootingRangeId > 0) {
                const selectedRange = shootingRanges.find(range => Number(range.id) === oldShootingRangeId);

                if (selectedRange) {
                    chooseShootingRange(selectedRange.id, selectedRange.name);
                }
            }

            updateShootingRangeClearButton();
            renderShootingRangeModalTable();
        });
    </script>
</x-app-layout>
