<p>Dzień dobry,</p>

<p>
    Otrzymujesz zaproszenie do strzelnicy:
    <strong>{{ $invitation->shootingRange->name }}</strong>.
</p>

<p>
    <a href="{{ route('shooting.invitations.accept', $invitation->token) }}">
        Akceptuj zaproszenie
    </a>
</p>

@if ($invitation->expires_at)
    <p>Zaproszenie ważne do: {{ $invitation->expires_at->format('Y-m-d H:i') }}</p>
@endif
