<x-app-layout>
    <div class="container py-4">
        <h1>Edytuj przedział: {{ $shootingRange->name }}</h1>

        <form method="POST" action="{{ route('admin.shootings.intervals.update', [$shootingRange, $interval]) }}">
            @csrf
            @method('PATCH')

            <div class="mb-3">
                <label class="form-label">Od</label>
                <input type="date" name="start_date" class="form-control" value="{{ old('start_date', $interval->start_date->format('Y-m-d')) }}">
                @error('start_date') <div class="text-danger">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label class="form-label">Do</label>
                <input type="date" name="end_date" class="form-control" value="{{ old('end_date', $interval->end_date->format('Y-m-d')) }}">
                @error('end_date') <div class="text-danger">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label class="form-label">Termin płatności</label>
                <input type="date" name="payment_due_date" class="form-control" value="{{ old('payment_due_date', optional($interval->payment_due_date)->format('Y-m-d')) }}">
            </div>

            <div class="mb-3">
                <label class="form-label">Data opłacenia</label>
                <input type="datetime-local" name="datePaid" class="form-control" value="{{ old('datePaid', optional($interval->datePaid)->format('Y-m-d\TH:i')) }}">
            </div>

            <div class="mb-3 form-check">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active" @checked(old('is_active', $interval->is_active))>
                <label class="form-check-label" for="is_active">Aktywny</label>
            </div>

            <button class="btn btn-primary">Zapisz</button>

            <a href="{{ route('admin.shootings.intervals', $shootingRange) }}" class="btn btn-secondary">
                Wróć
            </a>
        </form>
    </div>
</x-app-layout>
