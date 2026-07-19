<x-app-layout>
    <div class="page-bg-2"></div>

    <section class="container py-5">
        <div class="mx-auto overflow-hidden bg-white shadow rounded-3" style="max-width: 820px;">
            <div class="p-4 p-md-5 text-center">
                <span class="mb-3 d-inline-flex align-items-center justify-content-center rounded-circle bg-warning bg-opacity-10 text-warning" style="width: 78px; height: 78px;">
                    <i class="bi bi-shield-exclamation fs-1"></i>
                </span>

                <p class="mb-2 text-uppercase text-muted fw-semibold" style="letter-spacing: .08em;">
                    Dodatkowa weryfikacja
                </p>

                <h1 class="mb-3 h2 fw-bold text-dark">
                    Konto oczekuje na zatwierdzenie
                </h1>

                <p class="mb-4 text-muted fs-5">
                    Rejestracja wymaga dodatkowej weryfikacji przez administratora.
                    Po zatwierdzeniu konta uzyskasz dostęp do funkcji serwisu.
                </p>

                @if (auth()->user()?->approval_reason)
                    <div class="p-3 mb-4 text-start border rounded bg-warning bg-opacity-10 border-warning border-opacity-25">
                        <div class="mb-1 fw-semibold text-dark">
                            Powód weryfikacji
                        </div>
                        <div class="text-muted">
                            {{ auth()->user()->approval_reason }}
                        </div>
                    </div>
                @endif

                <div class="flex-wrap gap-2 d-flex justify-content-center">
                    <a href="{{ route('index') }}" class="btn btn-danger">
                        <i class="bi bi-house-door me-1"></i>
                        Strona główna
                    </a>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary">
                            <i class="bi bi-box-arrow-right me-1"></i>
                            Wyloguj
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>
</x-app-layout>
