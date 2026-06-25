<x-app-layout>
    <div class="page-bg-2"></div>
    <div class="container py-4">

        <header class="p-4 text-white bg-secondary">
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

            <div class="p-2 bg-white">
                <div class="">
                    @if (session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif
                    <div class="col-12">
                        <form method="POST" action="{{ route('shooting.invitations.store') }}" class="mb-4">
                            @csrf

                            <div class="row g-2">
                                <div class="col-md-2">
                                    <input name="first_name" class="w-full border-gray-300 rounded-md focus:border-indigo-500 focus:ring-indigo-500 " placeholder="Imię">
                                </div>

                                <div class="col-md-2">
                                    <input name="last_name" class="w-full border-gray-300 rounded-md focus:border-indigo-500 focus:ring-indigo-500 " placeholder="Nazwisko">
                                </div>

                                <div class="col-md-3">
                                    <input name="email" type="email" class="w-full border-gray-300 rounded-md focus:border-indigo-500 focus:ring-indigo-500 " placeholder="Email" required>
                                </div>

                                <div class="col-md-3">
                                    <input name="expires_at" type="datetime-local" class="w-full border-gray-300 rounded-md focus:border-indigo-500 focus:ring-indigo-500 ">
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

                            <div class="row g-2">
                                <div class="col-md-5">
                                    <input type="file" name="file" class="w-full border-gray-300 rounded-md focus:border-indigo-500 focus:ring-indigo-500 " required>
                                </div>

                                <div class="col-md-3">
                                    <input name="expires_at" type="datetime-local" class="w-full border-gray-300 rounded-md focus:border-indigo-500 focus:ring-indigo-500 ">
                                </div>

                                <div class="col-md-2">
                                    <button class="btn btn-outline-primary w-100">Import CSV</button>
                                </div>
                            </div>

                            <small class="text-muted">Format CSV: imię,nazwisko,email</small>
                        </form>
                    </div>
                </div>
            </div>









        <div class="p-2 mt-3 bg-white">
            <form method="POST" action="{{ route('shooting.invitations.bulk') }}">
                @csrf

                <div class="gap-2 mb-3 d-flex bg-slate-400">
                    <select name="action" class="form-select" style="max-width: 240px;" required>
                        <option value="">Operacja zbiorcza</option>
                        <option value="generate">Generuj linki</option>
                        <option value="send">Wyślij maile</option>
                        <option value="revoke">Odwołaj</option>
                    </select>

                    <button class="btn btn-primary">Wykonaj</button>
                </div>

                <table class="table bg-white table-hover">
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
                                    <input type="checkbox" name="ids[]" value="{{ $invitation->id }}" class="invitation-check">
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

                                <td class="gap-1 d-flex">
                                    <form method="POST" action="{{ route('shooting.invitations.link', $invitation) }}">
                                        @csrf
                                        @method('PATCH')
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
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </form>
        </div>
    </div>
</x-app-layout>
