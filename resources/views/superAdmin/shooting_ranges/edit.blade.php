<x-app-layout>
    <div class="container py-4">
        <h1 class="mb-4">Edytuj strzelnicę: {{ $shootingRange->name }}</h1>
        <x-session-alerts />

        <form method="POST" action="{{ route('admin.shootings.update', $shootingRange) }}" enctype="multipart/form-data" novalidate>
            @csrf
            @method('PATCH')

            <x-shooting-range-form
                :values="$shootingRange"
                submit-label="Zapisz zmiany"
            />
        </form>
    </div>
</x-app-layout>
