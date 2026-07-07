<x-app-layout>
    @php
        $sortLabels = [
            'name' => 'Imię i nazwisko',
            'email' => 'Email',
            'status' => 'Status',
            'expires_at' => 'Ważne do',
            'created_at' => 'Dodano',
        ];
        $sortUrl = function (string $column) use ($search, $sort, $direction, $perPage) {
            $nextDirection = $sort === $column && $direction === 'asc'
                ? 'desc'
                : 'asc';

            return route('shooting.invitations.index', array_filter([
                'search' => $search,
                'sort' => $column,
                'direction' => $nextDirection,
                'per_page' => $perPage,
            ], fn ($value) => $value !== null && $value !== ''));
        };
        $sortIcon = function (string $column) use ($sort, $direction) {
            if ($sort !== $column) {
                return 'bi-arrow-down-up';
            }

            return $direction === 'asc'
                ? 'bi-sort-alpha-down'
                : 'bi-sort-alpha-up';
        };
    @endphp

    <div class="page-bg-2"></div>
    <div class="container py-4">
        <header class="p-4 text-white rounded bg-secondary">
            <div class="gap-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                <div>
                    <div class="gap-2 mb-2 d-flex align-items-center">
                        <i class="bi bi-people-fill fs-4"></i>
                        <span class="opacity-75 text-uppercase small">
                            Zarządzanie listą użytkowników zapisanych do strzelnicy
                        </span>
                    </div>

                    <h1 class="mb-0 h3">
                        {{ $shootingRange->name }}
                    </h1>
                </div>
            </div>
        </header>

        <div class="p-2 bg-white rounded">
            <x-session-alerts />

            @if ($errors->any())
                <div class="alert alert-danger">
                    Popraw błędy w formularzu i spróbuj ponownie.
                </div>
            @endif

            <div class="col-12">
                <form method="POST" action="{{ route('shooting.invitations.store') }}" class="mb-4">
                    @csrf

                    <div class="row g-2 align-items-end">
                        <div class="col-md-2">
                            <label class="form-label small text-muted" for="first_name">Imię</label>
                            <input id="first_name" name="first_name" class="w-full border-gray-300 rounded-md focus:border-indigo-500 focus:ring-indigo-500" placeholder="Imię" value="{{ old('first_name') }}">
                            @error('first_name')
                                <div class="mt-1 small text-danger">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-2">
                            <label class="form-label small text-muted" for="last_name">Nazwisko</label>
                            <input id="last_name" name="last_name" class="w-full border-gray-300 rounded-md focus:border-indigo-500 focus:ring-indigo-500" placeholder="Nazwisko" value="{{ old('last_name') }}">
                            @error('last_name')
                                <div class="mt-1 small text-danger">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-3">
                            <label class="form-label small text-muted" for="email">Email</label>
                            <input id="email" name="email" type="email" class="w-full border-gray-300 rounded-md focus:border-indigo-500 focus:ring-indigo-500" placeholder="Email" value="{{ old('email') }}" required>
                            @error('email')
                                <div class="mt-1 small text-danger">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-3">
                            <div class="gap-2 d-flex align-items-center">
                                <button
                                    type="submit"
                                    class="btn btn-success invitation-icon-button"
                                    data-bs-toggle="tooltip"
                                    data-bs-title="Dodaj użytkownika ręcznie. Termin ważności linku zostanie ustawiony automatycznie na 7 dni."
                                    aria-label="Dodaj użytkownika ręcznie"
                                >
                                    <i class="bi bi-person-plus-fill" aria-hidden="true"></i>
                                </button>

                                <span
                                    data-bs-toggle="tooltip"
                                    data-bs-title="Importuj użytkowników z pliku Excel"
                                    tabindex="0"
                                >
                                    <button
                                        type="button"
                                        class="btn btn-outline-primary invitation-icon-button"
                                        data-bs-toggle="modal"
                                        data-bs-target="#invitation-import-modal"
                                        aria-label="Importuj użytkowników z pliku Excel"
                                    >
                                        <i class="bi bi-file-earmark-arrow-up-fill" aria-hidden="true"></i>
                                    </button>
                                </span>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="p-3 mt-3 bg-white rounded invitation-list-panel">
            <form
                id="invitation-search-form"
                method="GET"
                action="{{ route('shooting.invitations.index') }}"
                class="gap-2 mb-2 invitation-search-form d-flex flex-column flex-md-row align-items-md-end"
            >
                <input type="hidden" name="sort" value="{{ $sort }}">
                <input type="hidden" name="direction" value="{{ $direction }}">

                <div class="flex-grow-1">
                    <label class="form-label small text-muted" for="search">
                        Wyszukaj użytkownika
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="bi bi-search" aria-hidden="true"></i>
                        </span>
                        <input
                            id="search"
                            name="search"
                            type="search"
                            class="form-control"
                            value="{{ $search }}"
                            placeholder="Imię i nazwisko lub adres e-mail"
                        >
                    </div>
                </div>

                <div class="invitation-per-page-field">
                    <label class="form-label small text-muted" for="per_page">
                        Wiersze
                    </label>
                    <select
                        id="per_page"
                        name="per_page"
                        class="form-select"
                        aria-label="Liczba wierszy na stronie"
                    >
                        @foreach ([10, 20, 50] as $option)
                            <option value="{{ $option }}" @selected($perPage === $option)>
                                {{ $option }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <a
                    id="clear-invitation-search"
                    href="{{ route('shooting.invitations.index', array_filter([
                        'sort' => $sort,
                        'direction' => $direction,
                        'per_page' => $perPage,
                    ])) }}"
                    class="btn btn-outline-secondary invitation-icon-button @if ($search === '') d-none @endif"
                    data-bs-toggle="tooltip"
                    data-bs-title="Wyczyść wyszukiwanie"
                    aria-label="Wyczyść wyszukiwanie"
                >
                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                </a>
            </form>

            <div id="invitation-results" aria-live="polite">
                <form id="bulk-invitations-form" method="POST" action="{{ route('shooting.invitations.bulk') }}"></form>
                <input type="hidden" name="_token" value="{{ csrf_token() }}" form="bulk-invitations-form">

                <div class="gap-2 mb-3 invitation-bulk-actions d-none flex-wrap align-items-end">
                    <div class="invitation-selected-counter">
                        <i class="bi bi-check2-square" aria-hidden="true"></i>
                        <span data-selected-count>0</span>
                        zazn.
                    </div>

                    <div class="invitation-bulk-field">
                        <label class="form-label small text-muted" for="bulk_action">Operacja zbiorcza</label>
                        <select id="bulk_action" name="action" class="form-select" form="bulk-invitations-form" required>
                            <option value="">Operacja zbiorcza</option>
                            <option value="generate">Generuj linki</option>
                            <option value="send">Wyślij maile</option>
                            <option value="revoke">Odwołaj</option>
                            <option value="delete">Usuń z listy</option>
                        </select>
                    </div>

                    <button
                        class="btn btn-primary invitation-icon-button"
                        type="submit"
                        form="bulk-invitations-form"
                        data-bs-toggle="tooltip"
                        data-bs-title="Wykonaj operację zbiorczą"
                        aria-label="Wykonaj operację zbiorczą"
                    >
                        <i class="bi bi-play-fill" aria-hidden="true"></i>
                    </button>
                </div>

                <table class="table bg-white table-hover align-middle invitation-table">
                <thead>
                    <tr>
                        <th class="invitation-check-column">
                            <input
                                type="checkbox"
                                class="invitation-check-all"
                                aria-label="Zaznacz wszystkie zaproszenia"
                            >
                        </th>
                        <th>
                            <a href="{{ $sortUrl('name') }}" class="invitation-sort-link">
                                <span>Imię i nazwisko</span>
                                <i class="bi {{ $sortIcon('name') }}" aria-hidden="true"></i>
                            </a>
                        </th>
                        <th>
                            <a href="{{ $sortUrl('email') }}" class="invitation-sort-link">
                                <span>Email</span>
                                <i class="bi {{ $sortIcon('email') }}" aria-hidden="true"></i>
                            </a>
                        </th>
                        <th>
                            <a href="{{ $sortUrl('status') }}" class="invitation-sort-link">
                                <span>Status</span>
                                <i class="bi {{ $sortIcon('status') }}" aria-hidden="true"></i>
                            </a>
                        </th>
                        <th>
                            <a href="{{ $sortUrl('expires_at') }}" class="invitation-sort-link">
                                <span>Ważne do</span>
                                <i class="bi {{ $sortIcon('expires_at') }}" aria-hidden="true"></i>
                            </a>
                        </th>
                        <th>Link</th>
                        <th>Akcje</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach ($invitations as $invitation)
                        <tr>
                            <td class="invitation-check-cell" data-label="Zaznacz">
                                <input type="checkbox" name="ids[]" value="{{ $invitation->id }}" class="invitation-check" form="bulk-invitations-form">
                            </td>

                            <td data-label="Imię i nazwisko">
                                <strong class="invitation-person-name">
                                    {{ $invitation->first_name }} {{ $invitation->last_name }}
                                </strong>
                            </td>
                            <td data-label="Email">
                                <span class="invitation-email">{{ $invitation->email }}</span>
                            </td>
                            <td data-label="Status">
                                <span class="invitation-status">{{ $invitation->statusLabel() }}</span>
                            </td>
                            <td data-label="Ważne do">{{ optional($invitation->expires_at)->format('Y-m-d H:i') ?? '-' }}</td>

                            <td data-label="Link">
                                @if ($invitation->token)
                                    <input class="form-control form-control-sm invitation-link-input" readonly value="{{ route('shooting.invitations.accept', $invitation->token) }}">
                                @else
                                    -
                                @endif
                            </td>

                            <td data-label="Akcje">
                                <div class="invitation-actions">
                                    <form method="POST" action="{{ route('shooting.invitations.link', $invitation) }}" class="invitation-link-form">
                                        @csrf
                                        @method('PATCH')

                                        <button
                                            type="submit"
                                            class="btn btn-sm btn-outline-secondary invitation-icon-button"
                                            data-bs-toggle="tooltip"
                                            data-bs-title="Wygeneruj nowy link ważny przez 7 dni"
                                            aria-label="Wygeneruj nowy link"
                                        >
                                            <i class="bi bi-link-45deg" aria-hidden="true"></i>
                                        </button>
                                    </form>

                                    <form method="POST" action="{{ route('shooting.invitations.send', $invitation) }}">
                                        @csrf
                                        <button
                                            type="submit"
                                            class="btn btn-sm btn-outline-primary invitation-icon-button"
                                            data-bs-toggle="tooltip"
                                            data-bs-title="Wyślij zaproszenie e-mailem"
                                            aria-label="Wyślij zaproszenie e-mailem"
                                        >
                                            <i class="bi bi-envelope-arrow-up-fill" aria-hidden="true"></i>
                                        </button>
                                    </form>

                                    <form method="POST" action="{{ route('shooting.invitations.revoke', $invitation) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button
                                            type="submit"
                                            class="btn btn-sm btn-outline-danger invitation-icon-button"
                                            data-bs-toggle="tooltip"
                                            data-bs-title="Odwołaj zaproszenie"
                                            aria-label="Odwołaj zaproszenie"
                                        >
                                            <i class="bi bi-person-x-fill" aria-hidden="true"></i>
                                        </button>
                                    </form>

                                    <form
                                        method="POST"
                                        action="{{ route('shooting.invitations.destroy', $invitation) }}"
                                        class="delete-invitation-form"
                                        data-email="{{ $invitation->email }}"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button
                                            type="submit"
                                            class="btn btn-sm btn-danger invitation-icon-button"
                                            data-bs-toggle="tooltip"
                                            data-bs-title="Usuń użytkownika z listy"
                                            aria-label="Usuń użytkownika z listy"
                                        >
                                            <i class="bi bi-trash" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                </table>

                @if ($invitations->hasPages())
                    <div class="gap-3 pt-2 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                    <small class="text-muted">
                        Wyświetlono {{ $invitations->firstItem() }}–{{ $invitations->lastItem() }}
                        z {{ $invitations->total() }}
                    </small>

                    <div class="gap-2 d-flex align-items-center">
                        <a
                            href="{{ $invitations->previousPageUrl() ?? '#' }}"
                            class="btn btn-outline-dark btn-sm invitation-icon-button @if ($invitations->onFirstPage()) disabled @endif"
                            data-bs-toggle="tooltip"
                            data-bs-title="Poprzednia strona"
                            aria-label="Poprzednia strona"
                            @if ($invitations->onFirstPage()) aria-disabled="true" tabindex="-1" @endif
                        >
                            <i class="bi bi-chevron-left" aria-hidden="true"></i>
                        </a>

                        <form method="GET" action="{{ route('shooting.invitations.index') }}">
                            @if ($search !== '')
                                <input type="hidden" name="search" value="{{ $search }}">
                            @endif
                            <input type="hidden" name="sort" value="{{ $sort }}">
                            <input type="hidden" name="direction" value="{{ $direction }}">
                            <input type="hidden" name="per_page" value="{{ $perPage }}">

                            <label class="visually-hidden" for="page">
                                Numer strony
                            </label>
                            <select
                                id="page"
                                name="page"
                                class="form-select form-select-sm"
                                onchange="this.form.submit()"
                                title="Wybierz stronę"
                            >
                                @for ($page = 1; $page <= $invitations->lastPage(); $page++)
                                    <option
                                        value="{{ $page }}"
                                        @selected($page === $invitations->currentPage())
                                    >
                                        Strona {{ $page }} z {{ $invitations->lastPage() }}
                                    </option>
                                @endfor
                            </select>
                        </form>

                        <a
                            href="{{ $invitations->nextPageUrl() ?? '#' }}"
                            class="btn btn-outline-dark btn-sm invitation-icon-button @if (! $invitations->hasMorePages()) disabled @endif"
                            data-bs-toggle="tooltip"
                            data-bs-title="Następna strona"
                            aria-label="Następna strona"
                            @if (! $invitations->hasMorePages()) aria-disabled="true" tabindex="-1" @endif
                        >
                            <i class="bi bi-chevron-right" aria-hidden="true"></i>
                        </a>
                    </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div
        class="modal fade"
        id="invitation-import-modal"
        tabindex="-1"
        aria-labelledby="invitation-import-modal-title"
        aria-hidden="true"
    >
        <div class="modal-dialog modal-dialog-centered">
            <form
                method="POST"
                action="{{ route('shooting.invitations.import') }}"
                enctype="multipart/form-data"
                class="modal-content"
            >
                @csrf

                <div class="modal-header">
                    <h2 class="modal-title h5" id="invitation-import-modal-title">
                        Import użytkowników z Excela
                    </h2>
                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Zamknij"
                    ></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="file">
                            Plik Excel
                        </label>
                        <input
                            id="file"
                            type="file"
                            name="file"
                            class="form-control"
                            accept=".xls,.xlsx,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                            required
                        >
                        @error('file')
                            <div class="mt-1 small text-danger">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="p-3 rounded bg-light small text-muted">
                        Wymagane kolumny: imię, nazwisko, email. Ważność linków zostanie ustawiona automatycznie na 7 dni.
                    </div>
                </div>

                <div class="gap-2 modal-footer justify-content-between">
                    <a
                        href="{{ route('shooting.invitations.template') }}"
                        class="btn btn-outline-secondary"
                    >
                        <i class="bi bi-download me-1" aria-hidden="true"></i>
                        Pobierz szablon
                    </a>

                    <div class="gap-2 d-flex">
                        <button
                            type="button"
                            class="btn btn-outline-dark"
                            data-bs-dismiss="modal"
                        >
                            Anuluj
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-file-earmark-arrow-up-fill me-1" aria-hidden="true"></i>
                            Importuj
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <style>
        .invitation-list-panel {
            border: 1px solid rgba(52, 58, 64, .12);
            box-shadow: 0 .75rem 1.5rem rgba(15, 23, 42, .08);
        }

        .invitation-search-form {
            border-radius: .75rem;
            background: #f1f5f9;
            padding: .75rem;
        }

        .invitation-search-form .input-group-text {
            border-color: #adb5bd;
            background: #343a40;
            color: #fff;
        }

        .invitation-search-form .form-control {
            border-color: #adb5bd;
        }

        .invitation-per-page-field {
            width: 110px;
            flex: 0 0 110px;
        }

        .invitation-bulk-actions {
            display: flex;
            border-radius: .75rem;
            padding: .75rem;
            border: 1px solid rgba(13, 110, 253, .22);
            background: #e7f1ff;
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .55);
        }

        .invitation-bulk-field {
            flex: 1 1 220px;
        }

        .invitation-selected-counter {
            display: inline-flex;
            min-height: 38px;
            align-items: center;
            gap: .35rem;
            border-radius: .5rem;
            background: #0d6efd;
            color: #fff;
            font-size: .9rem;
            font-weight: 700;
            padding: .35rem .65rem;
        }

        .invitation-icon-button {
            display: inline-grid;
            width: 38px;
            height: 38px;
            padding: 0;
            place-items: center;
        }

        .invitation-icon-button i {
            font-size: 1.15rem;
            line-height: 1;
        }

        .invitation-icon-button.btn-sm {
            width: 32px;
            height: 32px;
        }

        .invitation-icon-button.btn-sm i {
            font-size: 1rem;
        }

        .invitation-table {
            table-layout: fixed;
            border-collapse: separate;
            border-spacing: 0;
            overflow: hidden;
            border: 1px solid rgba(52, 58, 64, .18);
            border-radius: .75rem;
        }

        .invitation-check-column {
            width: 46px;
        }

        .invitation-table thead th {
            border-bottom: 2px solid rgba(0, 0, 0, .24);
            background: #212529;
            color: #fff;
            font-size: .78rem;
            font-weight: 700;
            letter-spacing: 0;
            text-transform: uppercase;
            vertical-align: middle;
        }

        .invitation-table thead th:first-child {
            border-top-left-radius: .65rem;
        }

        .invitation-table thead th:last-child {
            border-top-right-radius: .65rem;
        }

        .invitation-sort-link {
            display: inline-flex;
            max-width: 100%;
            align-items: center;
            gap: .45rem;
            padding: .15rem .2rem;
            border-radius: .35rem;
            color: #fff;
            text-decoration: none;
        }

        .invitation-sort-link:hover,
        .invitation-sort-link:focus {
            color: #cfe2ff;
            text-decoration: none;
        }

        .invitation-sort-link i {
            flex: 0 0 auto;
            font-size: 1.08rem;
            opacity: .9;
        }

        .invitation-table tbody td {
            border-bottom-color: rgba(52, 58, 64, .1);
            background: #fff;
        }

        .invitation-table tbody tr:nth-child(even) td {
            background: #f8fafc;
        }

        .invitation-table tbody tr:hover td {
            background: #eef5ff;
        }

        .invitation-table tbody tr:last-child td {
            border-bottom: 0;
        }

        .invitation-table th:nth-child(2) {
            width: 18%;
        }

        .invitation-table th:nth-child(3) {
            width: 22%;
        }

        .invitation-table th:nth-child(4),
        .invitation-table th:nth-child(5) {
            width: 12%;
        }

        .invitation-table th:nth-child(6) {
            width: 24%;
        }

        .invitation-table th:nth-child(7) {
            width: 220px;
        }

        .invitation-person-name,
        .invitation-email {
            overflow-wrap: anywhere;
        }

        .invitation-status {
            display: inline-flex;
            max-width: 100%;
            padding: .2rem .5rem;
            border: 1px solid rgba(52, 58, 64, .16);
            border-radius: 999px;
            background: #e9ecef;
            color: #212529;
            font-size: .82rem;
            font-weight: 700;
            line-height: 1.2;
        }

        .invitation-link-input {
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .invitation-actions {
            display: grid;
            grid-template-columns: repeat(4, 32px);
            gap: .35rem;
            align-items: center;
        }

        .invitation-actions form {
            margin: 0;
        }

        @media (max-width: 767.98px) {
            .invitation-bulk-actions {
                display: flex;
                align-items: stretch !important;
            }

            .invitation-bulk-field,
            .invitation-bulk-actions .invitation-icon-button {
                width: 100%;
            }

            .invitation-bulk-actions .invitation-icon-button {
                height: 42px;
            }

            .invitation-table {
                border-collapse: separate;
                border-spacing: 0 .75rem;
                table-layout: auto;
                overflow: visible;
                border: 0;
                border-radius: 0;
            }

            .invitation-table thead {
                position: absolute;
                width: 1px;
                height: 1px;
                margin: -1px;
                padding: 0;
                overflow: hidden;
                clip: rect(0, 0, 0, 0);
                white-space: nowrap;
                border: 0;
            }

            .invitation-table,
            .invitation-table tbody,
            .invitation-table tr,
            .invitation-table td {
                display: block;
                width: 100%;
            }

            .invitation-table tr {
                border: 1px solid rgba(0, 0, 0, .08);
                border-radius: .75rem;
                background: #fff;
                box-shadow: 0 .35rem 1rem rgba(15, 23, 42, .08);
                overflow: hidden;
            }

            .invitation-table td {
                display: grid;
                grid-template-columns: minmax(6.5rem, 38%) minmax(0, 1fr);
                gap: .75rem;
                align-items: start;
                padding: .65rem .85rem;
                border-bottom: 1px solid rgba(0, 0, 0, .06);
            }

            .invitation-table td:last-child {
                border-bottom: 0;
            }

            .invitation-table td::before {
                content: attr(data-label);
                color: #6c757d;
                font-size: .78rem;
                font-weight: 700;
                line-height: 1.3;
                text-transform: uppercase;
            }

            .invitation-check-cell {
                grid-template-columns: minmax(6.5rem, 38%) auto !important;
                align-items: center !important;
            }

            .invitation-link-input {
                width: 100%;
            }

            .invitation-actions {
                grid-template-columns: repeat(4, minmax(0, 1fr));
                align-items: stretch;
            }

            .invitation-actions .invitation-icon-button {
                width: 100%;
                min-width: 38px;
            }
        }

        @media (max-width: 420px) {
            .invitation-table td {
                grid-template-columns: 1fr;
                gap: .25rem;
            }

        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('invitation-search-form');
            const input = document.getElementById('search');
            const clearButton = document.getElementById('clear-invitation-search');
            const results = document.getElementById('invitation-results');
            let debounceTimer;
            let activeRequest;

            const initializeTooltips = (container) => {
                if (!window.bootstrap?.Tooltip) {
                    return;
                }

                container
                    .querySelectorAll('[data-bs-toggle="tooltip"]')
                    .forEach(element => {
                        bootstrap.Tooltip.getOrCreateInstance(element);
                    });
            };

            initializeTooltips(document);

            const updateBulkActions = () => {
                const checkboxes = Array.from(
                    results.querySelectorAll('.invitation-check')
                );
                const selectedCount = checkboxes
                    .filter(checkbox => checkbox.checked)
                    .length;
                const bulkActions = results.querySelector('.invitation-bulk-actions');
                const selectedCounter = results.querySelector('[data-selected-count]');
                const checkAll = results.querySelector('.invitation-check-all');

                bulkActions?.classList.toggle('d-none', selectedCount === 0);

                if (selectedCounter) {
                    selectedCounter.textContent = selectedCount;
                }

                if (checkAll) {
                    checkAll.checked = checkboxes.length > 0 && selectedCount === checkboxes.length;
                    checkAll.indeterminate = selectedCount > 0 && selectedCount < checkboxes.length;
                }
            };

            const refreshResults = async () => {
                const url = new URL(form.action, window.location.origin);
                const search = input.value.trim();
                const sort = form.elements.namedItem('sort')?.value;
                const direction = form.elements.namedItem('direction')?.value;
                const perPage = form.elements.namedItem('per_page')?.value;

                if (search !== '') {
                    url.searchParams.set('search', search);
                }

                if (sort) {
                    url.searchParams.set('sort', sort);
                }

                if (direction) {
                    url.searchParams.set('direction', direction);
                }

                if (perPage) {
                    url.searchParams.set('per_page', perPage);
                }

                clearButton.classList.toggle('d-none', search === '');
                activeRequest?.abort();

                const request = new AbortController();
                activeRequest = request;
                results.setAttribute('aria-busy', 'true');

                try {
                    const response = await fetch(url, {
                        headers: {
                            'Accept': 'text/html',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        signal: request.signal
                    });

                    if (!response.ok) {
                        throw new Error('Nie udało się pobrać listy użytkowników.');
                    }

                    const documentContent = new DOMParser()
                        .parseFromString(await response.text(), 'text/html');
                    const newResults = documentContent
                        .getElementById('invitation-results');

                    if (!newResults) {
                        throw new Error('Odpowiedź nie zawiera listy użytkowników.');
                    }

                    results
                        .querySelectorAll('[data-bs-toggle="tooltip"]')
                        .forEach(element => {
                            bootstrap.Tooltip.getInstance(element)?.dispose();
                        });
                    results.innerHTML = newResults.innerHTML;
                    initializeTooltips(results);
                    updateBulkActions();
                    window.history.replaceState({}, '', url);
                } catch (error) {
                    if (error.name !== 'AbortError') {
                        console.error(error);
                    }
                } finally {
                    if (activeRequest === request) {
                        results.removeAttribute('aria-busy');
                    }
                }
            };

            input.addEventListener('input', () => {
                window.clearTimeout(debounceTimer);
                debounceTimer = window.setTimeout(refreshResults, 350);
            });

            form.addEventListener('submit', event => {
                event.preventDefault();
                window.clearTimeout(debounceTimer);
                refreshResults();
            });

            form.elements.namedItem('per_page')?.addEventListener('change', () => {
                window.clearTimeout(debounceTimer);
                refreshResults();
            });

            results.addEventListener('change', event => {
                const target = event.target;

                if (target.matches('.invitation-check-all')) {
                    results
                        .querySelectorAll('.invitation-check')
                        .forEach(checkbox => {
                            checkbox.checked = target.checked;
                        });
                    updateBulkActions();

                    return;
                }

                if (target.matches('.invitation-check')) {
                    updateBulkActions();
                }
            });

            document.addEventListener('submit', event => {
                const submittedForm = event.target;

                if (submittedForm.matches('.delete-invitation-form')) {
                    const email = submittedForm.dataset.email;

                    if (!window.confirm(
                        `Czy na pewno usunąć użytkownika ${email} z listy strzelnicy?`
                    )) {
                        event.preventDefault();
                    }

                    return;
                }

                if (
                    submittedForm.id === 'bulk-invitations-form'
                    && submittedForm.elements.namedItem('action')?.value === 'delete'
                ) {
                    const selectedCount = Array.from(submittedForm.elements)
                        .filter(element => {
                            return element.name === 'ids[]' && element.checked;
                        })
                        .length;

                    if (!window.confirm(
                        `Czy na pewno usunąć zaznaczonych użytkowników (${selectedCount}) z listy strzelnicy?`
                    )) {
                        event.preventDefault();
                    }
                }
            });

            updateBulkActions();
        });
    </script>
</x-app-layout>
