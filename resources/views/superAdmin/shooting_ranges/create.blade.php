<x-app-layout>
    <div class="container py-4">
        <h1 class="mb-4">Dodaj strzelnicę</h1>
        <x-session-alerts />

        <form method="POST" action="{{ route('admin.shootings.store') }}" enctype="multipart/form-data" novalidate>
            @csrf

            <x-shooting-range-form submit-label="Dodaj strzelnicę"/>
        </form>
    </div>
</x-app-layout>
