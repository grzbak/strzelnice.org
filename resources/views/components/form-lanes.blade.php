<div class="p-3 border border-2 border-opacity-25 rounded shadow lane-item border-secondary">
    <div class="row g-3 align-items-end">
        <div class="col-12 col-md-6">
            <input name="name" type="text" value="{{ $lane->name ?? (old('name') ?? '') }}" placeholder="Nazwa osi"
                class="form-control @error('name') is-invalid @enderror">
            @error('name')
                <div class="invalid-feedback">
                    {{ $message }}
                </div>
            @enderror
        </div>

        <div class="col-12 col-md-3">
            <input name="length" type="number" min="1" value="{{ $lane->length ?? old('length' ?? '') }}"
                placeholder="Długość (m)" class="form-control @error('length') is-invalid @enderror">
            @error('length')
                <div class="invalid-feedback">
                    {{ $message }}
                </div>
            @enderror
        </div>

        <div class="col-12 col-md-3">
            <input name="numberOfPositions" type="number" min="1"
                value="{{ $lane->numberOfPositions ?? old('numberOfPositions' ?? '') }}" placeholder="Stanowiska"
                class="form-control @error('numberOfPositions') is-invalid @enderror">
            @error('numberOfPositions')
                <div class="invalid-feedback">
                    {{ $message }}
                </div>
            @enderror
        </div>

    </div>
    <div class="mt-1 row g-3 align-items-end">
        <div class="mt-3">
            <input type="hidden" name="allows_dynamic_shooting" value="0">

            <div class="form-check">
                <input
                    type="checkbox"
                    name="allows_dynamic_shooting"
                    id="allows_dynamic_shooting_{{ $lane->id ?? 'new' }}"
                    value="1"
                    class="form-check-input @error('allows_dynamic_shooting') is-invalid @enderror"
                    @checked((bool) old('allows_dynamic_shooting', $lane->allows_dynamic_shooting ?? false))
                >
                <label
                    class="form-check-label"
                    for="allows_dynamic_shooting_{{ $lane->id ?? 'new' }}"
                >
                    Możliwość strzelania dynamicznego
                </label>
                @error('allows_dynamic_shooting')
                    <div class="invalid-feedback">
                        {{ $message }}
                    </div>
                @enderror
            </div>
        </div>

        <div class="mt-3">
            <textarea name="description" placeholder="Opis (opcjonalnie)" class="form-control">{{ $lane->description ?? old('description' ?? '') }}</textarea>
        </div>
    </div>
</div>
<div class="mt-3 d-flex justify-content-end">

    <button type="submit" class="px-4 btn btn-success">
        Zapisz
    </button>
</div>
