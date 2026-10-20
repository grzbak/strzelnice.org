<x-app-layout>
    <div class="page-bg-2"></div>

    <main class="container py-4 py-lg-5">
        <header class="p-4 mb-4 text-white rounded bg-secondary">
            <div class="gap-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                <div>
                    <div class="gap-2 mb-2 d-flex align-items-center">
                        <i class="bi bi-pencil-square fs-4"></i>
                        <span class="opacity-75 text-uppercase small">Panel strzelnicy</span>
                    </div>
                    <h1 class="mb-1 h3">Edycja strzelnicy</h1>
                    <p class="mb-0 opacity-75">{{ $shootingRange->name }}</p>
                </div>
                <div class="text-md-end small opacity-75">
                    <div>Uzupełnij dane widoczne w profilu strzelnicy oraz w rezerwacjach.</div>
                </div>
            </div>
        </header>

        <section class="p-3 p-lg-4 bg-white rounded shooting-edit-panel">
            <x-session-alerts />

            <div class="mb-4">
                <div class="gap-2 mb-1 d-flex align-items-center">
                    <i class="bi bi-card-checklist text-danger fs-5"></i>
                    <h2 class="mb-0 h5">Dane podstawowe</h2>
                </div>
                <p class="mb-0 text-muted small">
                    Zmiany zapisane tutaj wpływają na publiczną kartę strzelnicy i informacje pokazywane klientom.
                </p>
            </div>

            <form method="POST" action="{{ route('shooting.update', $shootingRange) }}" enctype="multipart/form-data" novalidate>
                @csrf

                <x-shooting-range-form
                    :values="$shootingRange"
                    submit-label="Zapisz strzelnicę"
                />
            </form>
        </section>
    </main>

    <style>
        .shooting-edit-panel {
            border: 1px solid rgba(52, 58, 64, .12);
            box-shadow: 0 .75rem 1.5rem rgba(15, 23, 42, .08);
        }
    </style>
</x-app-layout>
