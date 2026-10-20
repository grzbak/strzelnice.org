<x-app-layout>
    <div class="page-bg-2"></div>

    <div class="container py-4">
        <header class="p-4 text-white rounded bg-secondary">
            <div class="gap-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                <div>
                    <div class="gap-2 mb-2 d-flex align-items-center">
                        <i class="bi bi-pencil-square fs-4"></i>
                        <span class="opacity-75 text-uppercase small">Panel superadmina</span>
                    </div>
                    <h1 class="mb-1 h3">Edytuj ręczny przedział aktywności</h1>
                    <p class="mb-0 opacity-75">{{ $shootingRange->name }}</p>
                </div>

                <a href="{{ route('admin.shootings.intervals', $shootingRange) }}" class="btn btn-light">
                    <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>
                    Wróć
                </a>
            </div>
        </header>

        <div class="mt-3">
            <x-session-alerts />
        </div>

        @if ($interval->hasBlockingPackageOrders())
            <div class="p-4 mt-3 bg-white border rounded shadow-sm">
                <div class="gap-2 d-flex align-items-start">
                    <i class="bi bi-info-circle text-danger fs-4" aria-hidden="true"></i>
                    <div>
                        <h2 class="mb-1 h5">Przedział powiązany z abonamentami</h2>
                        <p class="mb-0 text-muted">
                            Tego przedziału nie edytujemy ręcznie. Zmiany zakresu wynikają z zamówień abonamentów,
                            płatności, anulowania albo przeterminowania zamówienia.
                        </p>
                    </div>
                </div>
            </div>
        @else
            <form method="POST" action="{{ route('admin.shootings.intervals.update', [$shootingRange, $interval]) }}" class="p-4 mt-3 bg-white rounded shadow-sm interval-edit-panel">
                @csrf
                @method('PATCH')

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Od</label>
                        <input
                            type="date"
                            name="start_date"
                            class="form-control"
                            value="{{ old('start_date', $interval->start_date->format('Y-m-d')) }}"
                            max="3000-12-31"
                            min="2000-01-01"
                        >
                        @error('start_date')
                            <div class="text-danger">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Do</label>
                        <input
                            type="date"
                            name="end_date"
                            class="form-control"
                            value="{{ old('end_date', $interval->end_date->format('Y-m-d')) }}"
                            max="3000-12-31"
                            min="2000-01-01"
                        >
                        @error('end_date')
                            <div class="text-danger">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <div class="pt-2 form-check">
                            <input type="hidden" name="is_active" value="0">
                            <input
                                type="checkbox"
                                name="is_active"
                                value="1"
                                class="form-check-input"
                                id="is_active"
                                @checked(old('is_active', $interval->is_active))
                            >
                            <label class="form-check-label" for="is_active">Aktywny</label>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Powód / notatka</label>
                        <textarea name="manual_reason" class="form-control" rows="3">{{ old('manual_reason') }}</textarea>
                        @error('manual_reason')
                            <div class="text-danger">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="gap-2 mt-3 d-flex">
                    <button class="btn btn-danger">
                        <i class="bi bi-save me-1" aria-hidden="true"></i>
                        Zapisz
                    </button>

                    <a href="{{ route('admin.shootings.intervals', $shootingRange) }}" class="btn btn-outline-dark">
                        Anuluj
                    </a>
                </div>
            </form>
        @endif
    </div>

    <style>
        .interval-edit-panel {
            border: 1px solid rgba(52, 58, 64, .12);
        }
    </style>
</x-app-layout>
