<x-app-layout>
    @php
        $statusPresentation = [
            'expired' => [
                'class' => 'is-expired',
                'icon' => 'bi-hourglass-split',
            ],
            'revoked' => [
                'class' => 'is-revoked',
                'icon' => 'bi-x-circle',
            ],
            'invalid' => [
                'class' => 'is-invalid-link',
                'icon' => 'bi-link-45deg',
            ],
            'wrong-account' => [
                'class' => 'is-wrong-account',
                'icon' => 'bi-person-x',
            ],
        ];

        $status = $statusPresentation[$type] ?? $statusPresentation['invalid'];
    @endphp

    <div class="page-bg-2"></div>

    <div class="container py-5">
        <div class="mx-auto invitation-status-panel">
            <div class="invitation-status-icon {{ $status['class'] }}">
                <i class="bi {{ $status['icon'] }}" aria-hidden="true"></i>
            </div>

            <h1 class="mb-3 h3 text-center">
                {{ $title }}
            </h1>

            <p class="mb-4 text-center text-muted">
                {{ $message }}
            </p>

            <div class="text-center">
                <a href="{{ route('index') }}" class="btn btn-dark">
                    <i class="bi bi-house-door me-1" aria-hidden="true"></i>
                    Wróć na stronę główną
                </a>
            </div>
        </div>
    </div>

    <style>
        .invitation-status-panel {
            max-width: 620px;
            padding: 2rem;
            border: 1px solid rgba(52, 58, 64, .14);
            border-radius: .75rem;
            background: rgba(255, 255, 255, .94);
            box-shadow: 0 .75rem 1.5rem rgba(15, 23, 42, .08);
        }

        .invitation-status-icon {
            display: grid;
            width: 72px;
            height: 72px;
            margin: 0 auto 1.25rem;
            place-items: center;
            border-radius: 50%;
            font-size: 2rem;
        }

        .invitation-status-icon.is-revoked,
        .invitation-status-icon.is-wrong-account {
            background: #f8d7da;
            color: #842029;
        }

        .invitation-status-icon.is-expired {
            background: #fff3cd;
            color: #664d03;
        }

        .invitation-status-icon.is-invalid-link {
            background: #e2e3e5;
            color: #41464b;
        }
    </style>
</x-app-layout>