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

    @php
        $shootingColumns = [
            ['key' => 'name', 'label' => 'Nazwa', 'sortable' => true, 'class' => 'shooting-name-column'],
            ['key' => 'town', 'label' => 'Adres', 'sortable' => true, 'class' => 'shooting-address-column'],
            ['key' => 'voivodeship', 'label' => 'Województwo', 'sortable' => true],
            ['key' => 'status', 'label' => 'Status'],
            ['key' => 'actions', 'label' => 'Akcje', 'class' => 'shooting-actions-column'],
        ];

        $normalizeDate = fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->toDateString() : null;
        $today = now()->toDateString();

        $shootingRangeStatus = function ($shootingRange) use ($normalizeDate, $today) {
            $activeInterval = $shootingRange->activeInterval;

            if (! $activeInterval) {
                return [
                    'icon' => 'bi-calendar-x',
                    'color' => 'text-secondary',
                    'text' => 'Brak aktywnych przedziałów',
                ];
            }

            if ($activeInterval->datePaid) {
                return [
                    'icon' => 'bi-check-circle-fill',
                    'color' => 'text-success',
                    'text' => sprintf(
                        'Aktywny przedział od %s do %s. Opłacony.',
                        $normalizeDate($activeInterval->start_date),
                        $normalizeDate($activeInterval->end_date)
                    ),
                ];
            }

            $paymentDueDate = $normalizeDate($activeInterval->payment_due_date);

            if ($paymentDueDate && $paymentDueDate >= $today) {
                return [
                    'icon' => 'bi-clock-fill',
                    'color' => 'text-warning',
                    'text' => sprintf(
                        'Aktywny przedział od %s do %s. Termin płatności: %s',
                        $normalizeDate($activeInterval->start_date),
                        $normalizeDate($activeInterval->end_date),
                        $paymentDueDate
                    ),
                ];
            }

            return [
                'icon' => 'bi-exclamation-triangle-fill',
                'color' => 'text-danger',
                'text' => $paymentDueDate
                    ? sprintf(
                        'Aktywny przedział od %s do %s. Termin płatności: %s',
                        $normalizeDate($activeInterval->start_date),
                        $normalizeDate($activeInterval->end_date),
                        $paymentDueDate
                    )
                    : 'Aktywny przedział nieopłacony. Brak terminu płatności.',
            ];
        };
    @endphp

    <div class="container py-4">
        <header class="p-4 text-white rounded bg-secondary">
            <div class="gap-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                <div>
                    <div class="gap-2 mb-2 d-flex align-items-center">
                        <i class="bi bi-buildings-fill fs-4"></i>
                        <span class="opacity-75 text-uppercase small">
                            Panel superadmina
                        </span>
                    </div>

                    <h1 class="mb-0 h3">
                        Strzelnice
                    </h1>
                </div>

                <div class="gap-2 d-flex flex-wrap justify-content-md-end">
                    <button
                        type="button"
                        class="btn btn-light shooting-header-action"
                        data-bs-toggle="modal"
                        data-bs-target="#importRangesModal"
                        title="Import z pliku *.xls(x)"
                        aria-label="Import z pliku *.xls(x)"
                    >
                        <i class="bi bi-file-earmark-arrow-up-fill" aria-hidden="true"></i>
                        <span>Import</span>
                    </button>

                    <a
                        href="{{ route('admin.shootings.create') }}"
                        class="btn btn-success shooting-header-action"
                        title="Dodaj nową strzelnicę"
                        aria-label="Dodaj nową strzelnicę"
                    >
                        <i class="bi bi-house-add-fill" aria-hidden="true"></i>
                        <span>Dodaj</span>
                    </a>
                </div>
            </div>
        </header>

        <x-data-table
            id="shooting-ranges-table"
            :action="route('admin.shootings')"
            :columns="$shootingColumns"
            :paginator="$shootings"
            :search="$search"
            search-label="Wyszukaj strzelnicę"
            search-placeholder="Nazwa, miasto, województwo lub adres"
            :sort="$sort"
            :direction="$direction"
            :per-page="$perPage"
            :per-page-options="[10, 20, 50]"
            selectable
            bulk-form-id="bulkDeleteForm"
            :bulk-action="route('admin.shootings.bulk-destroy')"
        >
            <x-slot:bulkActions>
                <input
                    type="hidden"
                    name="archive_with_reservations"
                    id="bulkArchiveWithReservations"
                    value="0"
                    form="bulkDeleteForm"
                >

                <button
                    type="submit"
                    class="btn btn-outline-danger btn-sm data-table-icon-button"
                    id="bulkDeleteButton"
                    form="bulkDeleteForm"
                    data-bs-toggle="tooltip"
                    data-bs-title="Usuń lub archiwizuj zaznaczone strzelnice"
                    aria-label="Usuń lub archiwizuj zaznaczone strzelnice"
                >
                    <i class="bi bi-trash3" aria-hidden="true"></i>
                </button>

                <small class="text-muted shooting-bulk-info">
                    Usunięcie obejmuje przedziały czasowe, galerie, zdjęcia, godziny pracy, dni wolne i zaproszenia.
                </small>
            </x-slot:bulkActions>

            @forelse ($shootings as $shootingRange)
                @php
                    $status = $shootingRangeStatus($shootingRange);
                    $administratorsCount = (int) $shootingRange->active_admins_count;
                    $administratorStatus = $administratorsCount > 0
                        ? [
                            'icon' => 'bi-person-check-fill',
                            'color' => 'text-success',
                            'text' => $administratorsCount === 1
                                ? 'Przypisano aktywnego administratora.'
                                : "Przypisani aktywni administratorzy: {$administratorsCount}.",
                        ]
                        : [
                            'icon' => 'bi-person-x-fill',
                            'color' => 'text-danger',
                            'text' => 'Brak aktywnego administratora strzelnicy.',
                        ];
                    $reservationsCount = (int) $shootingRange->reservations_count;
                @endphp

                <tr class="align-middle">
                    <td class="shooting-check-cell" data-label="Zaznacz">
                        <input
                            type="checkbox"
                            name="shooting_range_ids[]"
                            value="{{ $shootingRange->id }}"
                            class="shooting-ranges-table-check"
                            form="bulkDeleteForm"
                            data-range-name="{{ $shootingRange->name }}"
                            data-reservations-count="{{ $reservationsCount }}"
                            aria-label="Zaznacz strzelnicę {{ $shootingRange->name }}"
                        >
                    </td>

                    <td data-label="Nazwa">
                        <div class="d-flex flex-column gap-1">
                            <strong>{{ $shootingRange->name }}</strong>

                            @if ($reservationsCount > 0)
                                <span class="badge bg-warning text-dark shooting-reservation-badge">
                                    rezerwacje: {{ $reservationsCount }}
                                </span>
                            @endif
                        </div>
                    </td>

                    <td data-label="Adres">
                        {{ $shootingRange->street }} {{ $shootingRange->houseNumber }},
                        {{ $shootingRange->zipCode }} {{ $shootingRange->town }}
                    </td>

                    <td data-label="Województwo">
                        {{ $shootingRange->voivodeship }}
                    </td>

                    <td data-label="Status">
                        <div class="gap-2 d-flex align-items-center">
                            <i
                                class="bi {{ $status['icon'] }} action-icon {{ $status['color'] }}"
                                data-bs-toggle="tooltip"
                                data-bs-placement="top"
                                title="{{ $status['text'] }}"
                            ></i>
                            <i
                                class="bi {{ $administratorStatus['icon'] }} action-icon {{ $administratorStatus['color'] }}"
                                data-bs-toggle="tooltip"
                                data-bs-placement="top"
                                title="{{ $administratorStatus['text'] }}"
                            ></i>
                        </div>
                    </td>

                    <td data-label="Akcje">
                        <div class="shooting-actions">
                            <form method="POST" action="{{ route('admin.shootings.visibility', $shootingRange) }}" class="m-0">
                                @csrf
                                @method('PATCH')
                                <button
                                    type="submit"
                                    class="btn btn-sm btn-outline-secondary data-table-icon-button"
                                    data-bs-toggle="tooltip"
                                    data-bs-title="{{ $shootingRange->is_visible ? 'Ukryj na stronie' : 'Pokaż na stronie' }}"
                                    aria-label="{{ $shootingRange->is_visible ? 'Ukryj na stronie' : 'Pokaż na stronie' }}"
                                >
                                    <i class="bi {{ $shootingRange->is_visible ? 'bi-eye' : 'bi-eye-slash' }} {{ $shootingRange->is_visible ? 'text-success' : 'text-danger' }}" aria-hidden="true"></i>
                                </button>
                            </form>

                            <a href="{{ route('admin.shootings.intervals', $shootingRange) }}" class="btn btn-sm btn-outline-warning data-table-icon-button" data-bs-toggle="tooltip" data-bs-title="Zarządzaj przedziałami" aria-label="Zarządzaj przedziałami">
                                <i class="bi bi-clock" aria-hidden="true"></i>
                            </a>

                            <a href="{{ route('admin.shootings.edit', $shootingRange) }}" class="btn btn-sm btn-outline-dark data-table-icon-button" data-bs-toggle="tooltip" data-bs-title="Edytuj" aria-label="Edytuj">
                                <i class="bi bi-pencil" aria-hidden="true"></i>
                            </a>

                            <a href="{{ route('shooting.gallery.index', $shootingRange) }}" class="btn btn-sm btn-outline-primary data-table-icon-button" data-bs-toggle="tooltip" data-bs-title="Zarządzaj galerią" aria-label="Zarządzaj galerią">
                                <i class="bi bi-images" aria-hidden="true"></i>
                            </a>

                            <a href="{{ route('shooting.staff.index', $shootingRange) }}" class="btn btn-sm btn-outline-info data-table-icon-button" data-bs-toggle="tooltip" data-bs-title="Zarządzaj personelem strzelnicy" aria-label="Zarządzaj personelem strzelnicy">
                                <i class="bi bi-people-fill" aria-hidden="true"></i>
                            </a>

                            <a href="{{ route('admin.reservations.calendar', ['shooting_range_id' => $shootingRange->id]) }}" class="btn btn-sm btn-outline-success data-table-icon-button" data-bs-toggle="tooltip" data-bs-title="Rezerwacje strzelnicy" aria-label="Rezerwacje strzelnicy">
                                <i class="bi bi-calendar3" aria-hidden="true"></i>
                            </a>

                            <a href="{{ route('admin.reservations.archive', ['shooting_range_id' => $shootingRange->id]) }}" class="btn btn-sm btn-outline-secondary data-table-icon-button" data-bs-toggle="tooltip" data-bs-title="Archiwum rezerwacji strzelnicy" aria-label="Archiwum rezerwacji strzelnicy">
                                <i class="bi bi-archive" aria-hidden="true"></i>
                            </a>

                            <form
                                method="POST"
                                action="{{ route('admin.shootings.destroy', $shootingRange) }}"
                                class="m-0 shooting-delete-form"
                                data-range-name="{{ $shootingRange->name }}"
                                data-reservations-count="{{ $reservationsCount }}"
                            >
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="archive_with_reservations" value="0">

                                <button
                                    type="submit"
                                    class="btn btn-sm btn-outline-danger data-table-icon-button"
                                    data-bs-toggle="tooltip"
                                    data-bs-title="Usuń lub archiwizuj strzelnicę"
                                    aria-label="Usuń lub archiwizuj strzelnicę"
                                >
                                    <i class="bi bi-trash3" aria-hidden="true"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($shootingColumns) + 1 }}" class="py-4 text-center text-muted">
                        @if ($search !== '')
                            Brak strzelnic pasujących do wyszukiwania.
                        @else
                            Brak strzelnic do wyświetlenia.
                        @endif
                    </td>
                </tr>
            @endforelse
        </x-data-table>
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

    <style>
        .shooting-header-action {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            min-height: 38px;
            font-weight: 700;
        }

        .shooting-header-action i {
            font-size: 1.1rem;
            line-height: 1;
        }

        .shooting-actions-column {
            width: 180px;
        }

        .shooting-actions {
            display: grid;
            grid-template-columns: repeat(4, 32px);
            gap: .35rem;
            align-items: center;
            justify-content: start;
        }

        .shooting-actions form {
            margin: 0;
        }

        .shooting-reservation-badge {
            width: fit-content;
        }

        .shooting-bulk-info {
            align-self: center;
        }

        @media (max-width: 767.98px) {
            .shooting-actions {
                grid-template-columns: repeat(4, minmax(0, 1fr));
                align-items: stretch;
            }

            .shooting-actions .data-table-icon-button {
                width: 100%;
            }

            .shooting-check-cell {
                grid-template-columns: minmax(6.5rem, 38%) auto !important;
                align-items: center !important;
            }
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const dependentDeleteWarningText = (rangeText) => {
                return `${rangeText}\n\nRazem ze strzelnicami zostaną usunięte przedziały czasowe, galerie, zdjęcia, godziny pracy, dni wolne i zaproszenia.\n\nCzy na pewno kontynuować?`;
            };

            const confirmArchiveText = (rangeText) => {
                return `${rangeText}\n\nDo wybranych strzelnic istnieją rezerwacje, dlatego nie zostaną usunięte. Można je zarchiwizować - wtedy parametr archiwum zostanie ustawiony na 1, a strzelnice zostaną ukryte.\n\nCzy chcesz je zarchiwizować?`;
            };

            document.addEventListener('submit', (event) => {
                const form = event.target;

                if (form.matches('.shooting-delete-form')) {
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

                    return;
                }

                if (form.id === 'bulkDeleteForm') {
                    const selectedRanges = Array.from(document.querySelectorAll('.shooting-ranges-table-check:checked'));
                    const archiveInput = document.getElementById('bulkArchiveWithReservations');

                    if (selectedRanges.length === 0) {
                        event.preventDefault();
                        return;
                    }

                    const withReservations = selectedRanges.filter((checkbox) => Number(checkbox.dataset.reservationsCount || 0) > 0);

                    if (withReservations.length > 0) {
                        const names = withReservations
                            .map((checkbox) => `- ${checkbox.dataset.rangeName || checkbox.value}`)
                            .join('\n');
                        const withoutReservationsCount = selectedRanges.length - withReservations.length;
                        const deleteInfo = withoutReservationsCount > 0
                            ? `\n\nPozostałe wybrane strzelnice bez rezerwacji (${withoutReservationsCount}) zostaną usunięte razem z przedziałami czasowymi, galeriami, zdjęciami, godzinami pracy, dniami wolnymi i zaproszeniami.`
                            : '';

                        if (!confirm(`${confirmArchiveText(`Wybrane strzelnice z rezerwacjami:\n${names}`)}${deleteInfo}`)) {
                            event.preventDefault();
                            return;
                        }

                        archiveInput.value = '1';
                        return;
                    }

                    if (!confirm(dependentDeleteWarningText(`Wybrano ${selectedRanges.length} strzelnic.`))) {
                        event.preventDefault();
                        return;
                    }

                    archiveInput.value = '0';
                }
            });
        });
    </script>
</x-app-layout>