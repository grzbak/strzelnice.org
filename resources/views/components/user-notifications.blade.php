@props(['idSuffix' => 'main'])

@php
    $pendingInvitations = collect(session('pending_invitations', []));

    if ($pendingInvitations->isEmpty() && session('pending_invitation_token')) {
        $pendingInvitations = collect([[
            'token' => session('pending_invitation_token'),
            'email' => session('pending_invitation_email', auth()->user()?->email),
            'shooting_range_name' => session('pending_invitation_range_name', 'Czeka na Ciebie zaproszenie do prywatnej strzelnicy.'),
            'expires_at' => null,
        ]]);
    }

    $hasPendingInvitations = $pendingInvitations->isNotEmpty();
    $pendingInvitationsCount = $pendingInvitations->count();
    $autoOpenModal = session()->pull('pending_invitation_open_modal', false);
    $autoOpenModalId = $hasPendingInvitations ? 'pendingInvitationModal-' . $idSuffix . '-0' : null;
@endphp

@if ($hasPendingInvitations)
    <div class="dropdown user-notifications">
        <button
            type="button"
            class="user-notifications-toggle"
            data-bs-toggle="dropdown"
            aria-expanded="false"
            aria-label="Powiadomienia"
        >
            <i class="bi bi-info-circle" aria-hidden="true"></i>
            <span class="user-notifications-badge">{{ $pendingInvitationsCount }}</span>
        </button>

        <div class="dropdown-menu dropdown-menu-end user-notifications-menu">
            @foreach ($pendingInvitations as $index => $invitation)
                @php
                    $modalId = 'pendingInvitationModal-' . $idSuffix . '-' . $index;
                @endphp

                <button
                    type="button"
                    class="dropdown-item user-notification-item"
                    data-bs-toggle="modal"
                    data-bs-target="#{{ $modalId }}"
                >
                    <span class="user-notification-icon">
                        <i class="bi bi-envelope-check" aria-hidden="true"></i>
                    </span>
                    <span>
                        <strong>Czeka zaproszenie do strzelnicy</strong>
                        <small>{{ $invitation['shooting_range_name'] }}</small>
                        <small>Kliknij, aby wybrać: akceptuj, odrzuć lub zamknij bez decyzji.</small>
                    </span>
                </button>
            @endforeach
        </div>
    </div>

    @foreach ($pendingInvitations as $index => $invitation)
        @php
            $modalId = 'pendingInvitationModal-' . $idSuffix . '-' . $index;
        @endphp

        <div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}Label" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content invitation-notification-modal">
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title" id="{{ $modalId }}Label">
                                Zaproszenie do strzelnicy
                            </h5>
                            <div class="small text-muted">
                                {{ $invitation['shooting_range_name'] }}
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                    </div>

                    <div class="modal-body">
                        <p class="mb-2">
                            Aby korzystać z rezerwacji tej strzelnicy, zaakceptuj zaproszenie przypisane do adresu:
                        </p>
                        <div class="invitation-notification-email">
                            {{ $invitation['email'] }}
                        </div>

                        @if (! empty($invitation['expires_at']))
                            <div class="mt-2 small text-muted">
                                Link ważny do: {{ $invitation['expires_at'] }}
                            </div>
                        @endif
                    </div>

                    <div class="gap-2 modal-footer flex-column flex-sm-row align-items-stretch align-items-sm-center">
                        <a
                            href="{{ route('shooting.invitations.accept', ['token' => $invitation['token'], 'confirm' => 1]) }}"
                            class="btn btn-dark"
                        >
                            <i class="bi bi-check2-circle me-1" aria-hidden="true"></i>
                            Akceptuj
                        </a>

                        <form method="POST" action="{{ route('shooting.invitations.decline', $invitation['token']) }}">
                            @csrf
                            <button type="submit" class="btn btn-outline-danger w-100">
                                <i class="bi bi-x-circle me-1" aria-hidden="true"></i>
                                Odrzuć
                            </button>
                        </form>

                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-clock me-1" aria-hidden="true"></i>
                            Nie teraz
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endforeach

    @if ($autoOpenModal && $autoOpenModalId)
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const modalElement = document.getElementById(@json($autoOpenModalId));

                if (modalElement && window.bootstrap?.Modal && ! window.__pendingInvitationModalOpened) {
                    window.__pendingInvitationModalOpened = true;
                    document.body.appendChild(modalElement);
                    window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
                }
            });
        </script>
    @endif
@endif