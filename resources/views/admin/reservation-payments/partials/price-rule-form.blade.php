@php
    $selectedWeekdays = collect(old('weekdays', $rule?->weekdays ?? []))
        ->map(fn ($day) => (int) $day)
        ->all();
@endphp

<form method="POST" action="{{ $action }}">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <input type="hidden" name="shooting_range_id" value="{{ $shootingRange->id }}">

    <div class="row g-3">
        <div class="col-md-8">
            <label class="form-label">Nazwa</label>
            <input name="name" class="form-control" value="{{ old('name', $rule?->name) }}" required>
        </div>

        <div class="col-md-4">
            <label class="form-label">Oś</label>
            <select name="lane_id" class="form-select">
                <option value="">Wszystkie osie</option>
                @foreach ($shootingRange->lanes as $lane)
                    <option value="{{ $lane->id }}" @selected((int) old('lane_id', $rule?->lane_id) === $lane->id)>
                        {{ $lane->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="col-12">
            <label class="form-label">Dni tygodnia</label>
            <div class="flex-wrap gap-2 d-flex">
                @foreach ($weekdays as $day => $label)
                    <label class="form-check form-check-inline">
                        <input
                            type="checkbox"
                            name="weekdays[]"
                            value="{{ $day }}"
                            class="form-check-input"
                            @checked(in_array($day, $selectedWeekdays, true))
                        >
                        <span class="form-check-label">{{ $label }}</span>
                    </label>
                @endforeach
            </div>
            <div class="form-text">Brak zaznaczenia oznacza wszystkie dni.</div>
        </div>

        <div class="col-md-3">
            <label class="form-label">Godzina od</label>
            <input type="time" name="time_from" class="form-control" value="{{ old('time_from', $rule?->time_from ? substr($rule->time_from, 0, 5) : '') }}">
        </div>

        <div class="col-md-3">
            <label class="form-label">Godzina do</label>
            <input type="time" name="time_to" class="form-control" value="{{ old('time_to', $rule?->time_to ? substr($rule->time_to, 0, 5) : '') }}">
        </div>

        <div class="col-md-3">
            <label class="form-label">Ważne od</label>
            <input type="date" name="valid_from" class="form-control" value="{{ old('valid_from', $rule?->valid_from?->format('Y-m-d')) }}">
        </div>

        <div class="col-md-3">
            <label class="form-label">Ważne do</label>
            <input type="date" name="valid_to" class="form-control" value="{{ old('valid_to', $rule?->valid_to?->format('Y-m-d')) }}">
        </div>

        <div class="col-md-4">
            <label class="form-label">Cena / 30 min / stanowisko</label>
            <input type="number" step="0.01" min="0.01" name="price_per_position_30min" class="form-control" value="{{ old('price_per_position_30min', $rule?->price_per_position_30min) }}" required>
        </div>

        <div class="col-md-4">
            <label class="form-label">Cena dynamiczna</label>
            <input type="number" step="0.01" min="0.01" name="dynamic_price_per_position_30min" class="form-control" value="{{ old('dynamic_price_per_position_30min', $rule?->dynamic_price_per_position_30min) }}">
        </div>

        <div class="col-md-2">
            <label class="form-label">Waluta</label>
            <input name="currency" maxlength="3" class="text-uppercase form-control" value="{{ old('currency', $rule?->currency ?? 'PLN') }}" required>
        </div>

        <div class="col-md-2">
            <label class="form-label">Priorytet</label>
            <input type="number" min="1" max="999" name="priority" class="form-control" value="{{ old('priority', $rule?->priority ?? 100) }}" required>
        </div>

        <div class="col-12">
            <label class="form-label">Notatka</label>
            <textarea name="note" rows="2" class="form-control">{{ old('note', $rule?->note) }}</textarea>
        </div>

        <div class="col-12">
            <input type="hidden" name="is_active" value="0">
            <label class="form-check">
                <input type="checkbox" name="is_active" value="1" class="form-check-input" @checked(old('is_active', $rule?->is_active ?? true))>
                <span class="form-check-label">Aktywny</span>
            </label>
        </div>

        <div class="col-12">
            <button type="submit" class="btn btn-danger">
                {{ $rule ? 'Zapisz zmiany' : 'Dodaj cennik' }}
            </button>
        </div>
    </div>
</form>
