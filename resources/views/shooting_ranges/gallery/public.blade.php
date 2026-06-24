<x-app-layout>
    <div class="container py-4">
        <h1 class="mb-4">Galeria: {{ $shootingRange->name }}</h1>

        <div class="row g-3">
            @forelse ($gallery?->photos ?? [] as $photo)
                <div class="col-md-4">
                    <img
                        src="{{ $photo->full_url }}"
                        alt="{{ $photo->description ?? $photo->original_name }}"
                        class="rounded img-fluid"
                    >

                    @if ($photo->description)
                        <p class="mt-2">{{ $photo->description }}</p>
                    @endif
                </div>
            @empty
                <p class="text-muted">Brak zdjęć w galerii.</p>
            @endforelse
        </div>
    </div>
</x-app-layout>
