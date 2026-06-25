<x-app-layout>
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
                            <label class="form-label small text-muted" for="expires_at">Data ważności</label>
                            <input id="expires_at" name="expires_at" type="datetime-local" class="w-full border-gray-300 rounded-md focus:border-indigo-500 focus:ring-indigo-500" value="{{ old('expires_at') }}">
                            @error('expires_at')
                                <div class="mt-1 small text-danger">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-2">
                            <button class="btn btn-success w-100">Dodaj</button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="col-12">
                <form method="POST" action="{{ route('shooting.invitations.import') }}" enctype="multipart/form-data" class="mb-4">
                    @csrf

                    <div class="row g-2 align-items-end">
                        <div class="col-md-5">
                            <label class="form-label small text-muted" for="file">Plik CSV</label>
                            <input id="file" type="file" name="file" class="w-full border-gray-300 rounded-md focus:border-indigo-500 focus:ring-indigo-500" required>
                            @error('file')
                                <div class="mt-1 small text-danger">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-3">
                            <label class="form-label small text-muted" for="import_expires_at">Data ważności</label>
                            <input id="import_expires_at" name="expires_at" type="datetime-local" class="w-full border-gray-300 rounded-md focus:border-indigo-500 focus:ring-indigo-500">
                        </div>

                        <div class="col-md-2">
                            <button class="btn btn-outline-primary w-100">Import CSV</button>
                        </div>
                    </div>

                    <small class="text-muted">Format CSV: imię,nazwisko,email</small>
                </form>
            </div>
        </div>

        <div class="p-2 mt-3 bg-white rounded">
            <form id="bulk-invitations-form" method="POST" action="{{ route('shooting.invitations.bulk') }}"></form>
            <input type="hidden" name="_token" value="{{ csrf_token() }}" form="bulk-invitations-form">

            <div class="gap-2 mb-3 d-flex flex-wrap align-items-end bg-slate-400">
                <div>
                    <label class="form-label small text-muted" for="bulk_action">Operacja zbiorcza</label>
                    <select id="bulk_action" name="action" class="form-select" style="max-width: 240px;" form="bulk-invitations-form" required>
                        <option value="">Operacja zbiorcza</option>
                        <option value="generate">Generuj linki</option>
                        <option value="send">Wyślij maile</option>
                        <option value="revoke">Odwołaj</option>
                    </select>
                </div>

                <div>
                    <label class="form-label small text-muted" for="bulk_expires_at">Data ważności linków</label>
                    <input id="bulk_expires_at" name="expires_at" type="datetime-local" class="form-control" style="max-width: 230px;" form="bulk-invitations-form">
                </div>

                <button class="btn btn-primary" type="submit" form="bulk-invitations-form">Wykonaj</button>
            </div>

            <table class="table bg-white table-hover align-middle">
                <thead>
                    <tr>
                        <th>
                            <input type="checkbox" onclick="document.querySelectorAll('.invitation-check').forEach(cb => cb.checked = this.checked)">
                        </th>
                        <th>Imię i nazwisko</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Ważne do</th>
                        <th>Link</th>
                        <th>Akcje</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach ($invitations as $invitation)
                        <tr>
                            <td>
                                <input type="checkbox" name="ids[]" value="{{ $invitation->id }}" class="invitation-check" form="bulk-invitations-form">
                            </td>

                            <td>{{ $invitation->first_name }} {{ $invitation->last_name }}</td>
                            <td>{{ $invitation->email }}</td>
                            <td>{{ $invitation->statusLabel() }}</td>
                            <td>{{ optional($invitation->expires_at)->format('Y-m-d H:i') ?? '-' }}</td>

                            <td>
                                @if ($invitation->token)
                                    <input class="form-control form-control-sm" readonly value="{{ route('shooting.invitations.accept', $invitation->token) }}">
                                @else
                                    -
                                @endif
                            </td>

                            <td>
                                <div class="gap-1 d-flex flex-wrap align-items-center">
                                    <form method="POST" action="{{ route('shooting.invitations.link', $invitation) }}" class="gap-1 d-flex flex-wrap align-items-center">
                                        @csrf
                                        @method('PATCH')

                                        <input
                                            name="expires_at"
                                            type="datetime-local"
                                            class="form-control form-control-sm"
                                            style="width: 190px;"
                                            title="Data ważności linku"
                                            aria-label="Data ważności linku"
                                            value="{{ optional($invitation->expires_at)->format('Y-m-d\TH:i') }}"
                                        >

                                        <button class="btn btn-sm btn-outline-secondary">Link</button>
                                    </form>

                                    <form method="POST" action="{{ route('shooting.invitations.send', $invitation) }}">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-primary">Wyślij</button>
                                    </form>

                                    <form method="POST" action="{{ route('shooting.invitations.revoke', $invitation) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button class="btn btn-sm btn-outline-danger">Odwołaj</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
