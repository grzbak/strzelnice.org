<x-app-layout>
    <div class="page-bg-2"></div>


    <div class="container py-4">
        <header class="p-4 mb-4 text-white rounded bg-secondary">
            <div class="gap-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                <div>
                    <div class="gap-2 mb-2 d-flex align-items-center">
                        <i class="bi bi-file-earmark-text-fill fs-4"></i>
                        <span class="opacity-75 text-uppercase small">
                            Panel superadmina
                        </span>
                    </div>

                    <h1 class="mb-1 h3">Edytuj stronę</h1>
                    <p class="mb-0 opacity-75">
                        Aktualizacja treści, banera i tła podstrony.
                    </p>
                </div>

                <a href="{{ route('admin.pages.index') }}" class="btn btn-light">
                    <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>
                    Wróć do listy
                </a>
            </div>
        </header>

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
