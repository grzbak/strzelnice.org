<x-app-layout>
    <div class="page-bg-2"></div>

    @include('components.breadcrumb')

    <div class="container py-4">
        <h1 class="mb-3">Dodaj stronę</h1>

        <form method="POST" action="{{ route('admin.pages.store') }}" enctype="multipart/form-data" class="p-3 bg-white shadow-sm">
            @include('superAdmin.pages._form')
        </form>
    </div>
</x-app-layout>