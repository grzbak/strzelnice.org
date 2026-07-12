<x-app-layout>
    <div class="page-bg-2"></div>
    @include('components.breadcrumb')

    <div class="container py-4">
        <h1 class="mb-3">Dodaj regulamin</h1>

        <form method="POST" action="{{ route('admin.regulations.store') }}" class="p-3 bg-white shadow-sm">
            @include('superAdmin.regulations._form')
        </form>
    </div>
</x-app-layout>
