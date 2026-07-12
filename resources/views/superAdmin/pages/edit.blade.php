<x-app-layout>
    <div class="page-bg-2"></div>

    @include('components.breadcrumb')

    <div class="container py-4">
        <h1 class="mb-3">Edytuj stronę</h1>

        @if ($page->activeRegulations->isNotEmpty())
            <div class="alert alert-warning">
                <strong>Ta strona jest aktywnym regulaminem.</strong>
                Zmiana treści utworzy nową wersję regulaminu tylko wtedy, gdy treść różni się od ostatniej wersji.
                Użytkownicy, którzy zaakceptowali poprzednią wersję, otrzymają informację e-mail i będą musieli zaakceptować nową wersję po zalogowaniu.
            </div>
        @endif

        <form method="POST" action="{{ route('admin.pages.update', $page) }}" enctype="multipart/form-data" class="p-3 bg-white shadow-sm">
            @method('PATCH')
            @include('superAdmin.pages._form')
        </form>
    </div>
</x-app-layout>
