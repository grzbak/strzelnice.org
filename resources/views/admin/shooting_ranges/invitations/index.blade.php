<x-app-layout>
    <div class="container py-4">
        <h1>Zaproszenia: {{ $shootingRange->name }}</h1>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <form method="POST" action="{{ route('shooting.invitations.store') }}" class="mb-4">
            @csrf

            <div class="row g-2">
                <div class="col-md-2">
                    <input name="first_name" class="form-control" placeholder="Imię">
                </div>

                <div class="col-md-2">
                    <input name="last_name" class="form-control" placeholder="Nazwisko">
                </div>

                <div class="col-md-3">
                    <input name="email" type="email" class="form-control" placeholder="Email" required>
                </div>

                <div class="col-md-3">
                    <input name="expires_at" type="datetime-local" class="form-control">
                </div>

                <div class="col-md-2">
                    <button class="btn btn-success w-100">Dodaj</button>
                </div>
            </div>
        </form>

        <form method="POST" action="{{ route('shooting.invitations.import') }}" enctype="multipart/form-data" class="mb-4">
            @csrf

            <div class="row g-2">
                <div class="col-md-5">
                    <input type="file" name="file" class="form-control" required>
                </div>

                <div class="col-md-3">
                    <input name="expires_at" type="datetime-local" class="form-control">
                </div>

                <div class="col-md-2">
                    <button class="btn btn-outline-primary w-100">Import CSV</button>
                </div>
            </div>

            <small class="text-muted">Format CSV: imię,nazwisko,email</small>
        </form>

        <form method="POST" action="{{ route('shooting.invitations.bulk') }}">
            @csrf

            <div class="gap-2 mb-3 d-flex">
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
</x-app-layout>
