<x-app-layout>
    <div class="page-bg-2"></div>

    <div class="container py-4">
        <div class="p-3 mb-3 bg-white shadow-sm">
            <h1 class="mb-1">Edytuj konfigurację</h1>
            <p class="mb-0 text-muted">
                Zapis utworzy nową aktywną wersję. Dotychczasowa konfiguracja pozostanie w archiwum.
            </p>
        </div>

        <form
            method="POST"
            action="{{ route('admin.system-configurations.update', $systemConfiguration) }}"
            class="p-3 bg-white shadow-sm"
            novalidate
        >
            @method('PATCH')
            @include('superAdmin.system-configurations._form')
        </form>
    </div>
</x-app-layout>
