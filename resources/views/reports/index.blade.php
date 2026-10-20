<x-app-layout>
    <div class="page-bg-2"></div>

    <main class="container py-4">
        <x-session-alerts />

        <header class="p-4 mb-4 text-white rounded bg-secondary">
            <div class="gap-2 mb-2 d-flex align-items-center">
                <i class="bi bi-bar-chart-line-fill fs-4" aria-hidden="true"></i>
                <span class="opacity-75 text-uppercase small">Panel raportów</span>
            </div>
            <h1 class="mb-1 h3">Raporty</h1>
            <p class="mb-0 opacity-75">Zestawienia dostępne zgodnie z rolą użytkownika.</p>
        </header>

        @if (! $reportsEnabled)
            <div class="alert alert-warning">
                <strong>Moduł raportów jest wyłączony.</strong>
                @if (auth()->user()->isSuperAdmin())
                    Możesz włączyć go w konfiguracji systemu.
                @else
                    Skontaktuj się z administratorem systemu.
                @endif
            </div>
        @endif

        @if ($reports->isEmpty())
            <div class="p-4 bg-white rounded shadow-sm">
                Brak raportów dostępnych dla Twojego konta.
            </div>
        @else
            <div class="row g-3">
                @foreach ($reports as $report)
                    <div class="col-md-6 col-xl-4">
                        <a href="{{ $report['route'] }}" class="text-decoration-none text-dark">
                            <article class="p-4 bg-white rounded shadow-sm h-100 report-card">
                                <div class="gap-3 d-flex align-items-start">
                                    <span class="report-card-icon">
                                        <i class="bi {{ $report['icon'] }}" aria-hidden="true"></i>
                                    </span>
                                    <div>
                                        <h2 class="mb-2 h5">{{ $report['title'] }}</h2>
                                        <p class="mb-0 text-muted">{{ $report['description'] }}</p>
                                    </div>
                                </div>
                            </article>
                        </a>
                    </div>
                @endforeach
            </div>
        @endif
    </main>

    <style>
        .report-card {
            border: 1px solid rgba(33, 37, 41, .08);
            transition: border-color .2s ease, transform .2s ease;
        }

        .report-card:hover {
            border-color: rgba(185, 28, 28, .35);
            transform: translateY(-2px);
        }

        .report-card-icon {
            align-items: center;
            background: #111;
            border-radius: .5rem;
            color: #fff;
            display: inline-flex;
            flex: 0 0 44px;
            height: 44px;
            justify-content: center;
            width: 44px;
        }
    </style>
</x-app-layout>
