<x-app-layout>
    <div class="page-bg-2"></div>

    <section class="container py-5">
        <div class="p-4 mx-auto bg-white shadow-sm" style="max-width: 900px;">
            <h1 class="mb-3 h3">{{ $regulation->name }}</h1>

            <div class="regulation-content">
                {!! $regulation->currentVersion->content !!}
            </div>

            <a href="{{ route('regulations.required') }}" class="mt-4 btn btn-outline-secondary">
                Wróć do akceptacji
            </a>
        </div>
    </section>
</x-app-layout>
