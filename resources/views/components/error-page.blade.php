@props([
    'code',
    'title',
    'message',
    'icon' => 'bi-exclamation-triangle',
    'tone' => 'danger',
])

<x-app-layout>
    <div class="page-bg-2"></div>

    <section class="flex flex-col items-center pt-6">
        <div class="w-full px-6 py-4 mx-auto mt-6 overflow-hidden text-center bg-white shadow-md sm:max-w-md sm:rounded-lg" style="max-width: 760px;">
            <div class="mb-3">
                <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-{{ $tone }} bg-opacity-10 text-{{ $tone }}" style="width: 76px; height: 76px;">
                    <i class="bi {{ $icon }} fs-1"></i>
                </span>
            </div>

            <p class="mb-2 text-uppercase text-muted fw-semibold" style="letter-spacing: .08em;">
                Błąd {{ $code }}
            </p>

            <h1 class="mb-3 h2 fw-bold text-dark">
                {{ $title }}
            </h1>

            <p class="mb-4 text-muted fs-5">
                {{ $message }}
            </p>

            <div class="flex-wrap gap-2 d-flex justify-content-center">
                <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>
                    Wróć
                </a>

                <a href="{{ route('index') }}" class="btn btn-danger">
                    <i class="bi bi-house-door me-1"></i>
                    Strona główna
                </a>
            </div>
        </div>
    </section>
</x-app-layout>
