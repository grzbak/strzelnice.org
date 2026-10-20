<x-app-layout>
    @php
        $oldLanes = $shootingRange->lanes;
    @endphp

    <div class="page-bg-2"></div>

    <main class="container py-4 py-lg-5">
        <header class="p-4 mb-4 text-white rounded bg-secondary">
            <div class="gap-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                <div>
                    <div class="gap-2 mb-2 d-flex align-items-center">
                        <i class="bi bi-bullseye fs-4"></i>
                        <span class="opacity-75 text-uppercase small">Panel strzelnicy</span>
                    </div>
                    <h1 class="mb-1 h3">Osie strzeleckie</h1>
                    <p class="mb-0 opacity-75">{{ $shootingRange->name }}</p>
                </div>
                <div class="text-md-end small opacity-75">
                    <div>Dodawaj, edytuj i archiwizuj osie dostępne w kalendarzu rezerwacji.</div>
                </div>
            </div>
        </header>

        <section class="p-3 p-lg-4 overflow-hidden bg-white rounded shooting-lanes-panel">
            <x-session-alerts />

            <div class="mb-4">
                <div class="gap-2 mb-1 d-flex align-items-center">
                    <i class="bi bi-crosshair2 text-danger fs-5"></i>
                    <h2 class="mb-0 h5">Konfiguracja osi</h2>
                </div>
                <p class="mb-0 text-muted small">
                    Każda oś określa długość, liczbę stanowisk i możliwość rezerwacji strzelania dynamicznego.
                </p>
            </div>

            <x-shooting-lanes :shootingRange="$shootingRange" />

        </section>
    </main>

    <style>
        .shooting-lanes-panel {
            border: 1px solid rgba(52, 58, 64, .12);
            box-shadow: 0 .75rem 1.5rem rgba(15, 23, 42, .08);
        }
    </style>

    @stack('modals')
</x-app-layout>
