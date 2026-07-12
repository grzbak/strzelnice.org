@csrf

<div class="alert alert-warning">
    Regulamin jest publikowany jako wersja treści wskazanej strony. Jeżeli treść strony nie zmieni się względem ostatniej wersji, system nie wymusi ponownej akceptacji.
</div>

<div class="mb-3">
    <label for="name" class="form-label">Nazwa regulaminu</label>
    <input type="text" id="name" name="name" value="{{ old('name', $regulation->name) }}" class="form-control @error('name') is-invalid @enderror" required>
    <x-input-error :messages="$errors->get('name')" class="mt-2" />
</div>

<div class="mb-3">
    <label for="page_id" class="form-label">Strona z treścią regulaminu</label>
    <select id="page_id" name="page_id" class="form-select @error('page_id') is-invalid @enderror" required>
        <option value="">Wybierz stronę</option>
        @foreach ($pages as $page)
            <option value="{{ $page->id }}" @selected((int) old('page_id', $regulation->page_id) === $page->id)>
                {{ $page->display_name }} (/{{ $page->navigation_name }})
            </option>
        @endforeach
    </select>
    <x-input-error :messages="$errors->get('page_id')" class="mt-2" />
</div>

<div class="mb-3">
    <div class="form-label">Wymagaj od grup</div>
    <div class="row g-2">
        @foreach ($roles as $role => $label)
            <div class="col-md-4">
                <label class="p-2 border rounded d-flex gap-2 align-items-center">
                    <input type="checkbox" name="target_roles[]" value="{{ $role }}" class="form-check-input m-0" @checked(in_array($role, old('target_roles', $regulation->target_roles ?? []), true))>
                    <span>{{ $label }}</span>
                </label>
            </div>
        @endforeach
    </div>
    <x-input-error :messages="$errors->get('target_roles')" class="mt-2" />
</div>

<div class="row g-3">
    <div class="col-md-4">
        <label for="starts_at" class="form-label">Obowiązuje od</label>
        <input type="date" id="starts_at" name="starts_at" value="{{ old('starts_at', optional($regulation->starts_at)->format('Y-m-d') ?? today()->format('Y-m-d')) }}" class="form-control @error('starts_at') is-invalid @enderror" required>
        <x-input-error :messages="$errors->get('starts_at')" class="mt-2" />
    </div>

    <div class="col-md-4">
        <label for="ends_at" class="form-label">Obowiązuje do</label>
        <input type="date" id="ends_at" name="ends_at" value="{{ old('ends_at', optional($regulation->ends_at)->format('Y-m-d')) }}" class="form-control @error('ends_at') is-invalid @enderror">
        <x-input-error :messages="$errors->get('ends_at')" class="mt-2" />
    </div>

    <div class="col-md-4 d-flex align-items-end">
        <label class="mb-2 form-check">
            <input type="checkbox" name="is_indefinite" value="1" class="form-check-input" @checked(old('is_indefinite', $regulation->is_indefinite))>
            <span class="form-check-label">Bezterminowo / do odwołania</span>
        </label>
    </div>
</div>

<div class="gap-2 mt-4 d-flex">
    <button class="btn btn-success">
        <i class="bi bi-save me-1"></i>
        Zapisz
    </button>

    <a href="{{ route('admin.regulations.index') }}" class="btn btn-outline-secondary">Anuluj</a>
</div>
