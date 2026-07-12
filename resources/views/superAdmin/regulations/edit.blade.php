<x-app-layout>
    <div class="page-bg-2"></div>
    @include('components.breadcrumb')

    <div class="container py-4">
        <h1 class="mb-3">Edytuj regulamin</h1>

        <form method="POST" action="{{ route('admin.regulations.update', $regulation) }}" class="p-3 bg-white shadow-sm">
            @method('PATCH')
            @include('superAdmin.regulations._form')
        </form>

        <section class="p-3 mt-4 bg-white shadow-sm">
            <div class="gap-3 mb-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                <div>
                    <h2 class="mb-1 h4">Historia wersji</h2>
                    <p class="mb-0 text-muted">
                        Podgląd archiwalnych wersji regulaminu oraz liczba użytkowników, którzy zaakceptowali daną wersję.
                    </p>
                </div>
            </div>

            @if ($regulation->versions->isEmpty())
                <div class="alert alert-info mb-0">
                    Ten regulamin nie ma jeszcze opublikowanych wersji.
                </div>
            @else
                <div class="accordion regulation-version-history" id="regulationVersionHistory">
                    @foreach ($regulation->versions as $version)
                        <div class="accordion-item">
                            <h3 class="accordion-header">
                                <button
                                    class="accordion-button {{ $loop->first ? '' : 'collapsed' }}"
                                    type="button"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#regulation-version-{{ $version->id }}"
                                    aria-expanded="{{ $loop->first ? 'true' : 'false' }}"
                                    aria-controls="regulation-version-{{ $version->id }}"
                                >
                                    <span class="gap-3 d-flex flex-column flex-md-row align-items-md-center w-100">
                                        <strong>Wersja v{{ $version->version_number }}</strong>
                                        <span class="text-muted small">
                                            Opublikowano:
                                            {{ $version->published_at?->format('Y-m-d H:i') ?? '-' }}
                                        </span>
                                        <span class="badge text-bg-secondary">
                                            Akceptacje: {{ $version->acceptances_count }}
                                        </span>
                                        @if ($regulation->currentVersion?->id === $version->id)
                                            <span class="badge text-bg-success">Aktualna</span>
                                        @endif
                                    </span>
                                </button>
                            </h3>
                            <div
                                id="regulation-version-{{ $version->id }}"
                                class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}"
                                data-bs-parent="#regulationVersionHistory"
                            >
                                <div class="accordion-body">
                                    <div class="mb-3 small text-muted">
                                        <div><strong>Nazwa strony:</strong> {{ $version->display_name }}</div>
                                        <div><strong>Hash treści:</strong> <code>{{ $version->content_hash }}</code></div>
                                    </div>

                                    <div class="p-3 border rounded regulation-version-preview">
                                        {!! $version->content ?: '<p class="text-muted mb-0">Brak treści.</p>' !!}
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    </div>

    <style>
        .regulation-version-history .accordion-button {
            gap: .75rem;
        }

        .regulation-version-preview {
            max-height: 520px;
            overflow: auto;
            background: #fff;
        }
    </style>
</x-app-layout>
