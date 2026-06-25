@csrf

<div class="mb-3">
    <label for="display_name" class="form-label">Nazwa wyświetlana</label>
    <input
        type="text"
        id="display_name"
        name="display_name"
        class="form-control @error('display_name') is-invalid @enderror"
        value="{{ old('display_name', $page->display_name) }}"
        required
        maxlength="255"
    >
    <div class="form-text">Na jej podstawie automatycznie powstaje unikalna nazwa w adresie strony.</div>
    <x-input-error :messages="$errors->get('display_name')" class="mt-2" />
</div>

@if ($page->exists)
    <div class="mb-3">
        <label class="form-label">Nazwa w nawigacji</label>
        <input type="text" class="form-control" value="{{ $page->navigation_name }}" readonly>
        <div class="form-text">Adres publiczny: <a href="{{ url('/' . $page->navigation_name) }}" target="_blank">{{ url('/' . $page->navigation_name) }}</a></div>
    </div>
@endif

<div class="row g-3">
    <div class="col-md-6">
        <label for="baner" class="form-label">Baner</label>
        <input type="file" id="baner" name="baner" class="form-control @error('baner') is-invalid @enderror" accept="image/jpeg,image/png,image/webp">
        <div class="form-text">Zmiana banera automatycznie usunie poprzedni plik.</div>
        <x-input-error :messages="$errors->get('baner')" class="mt-2" />

        @if ($page->baner)
            <img src="{{ asset($page->baner) }}" alt="Aktualny baner" class="mt-2 rounded img-fluid border">
        @endif
    </div>

    <div class="col-md-6">
        <label for="background" class="form-label">Tło strony</label>
        <input type="file" id="background" name="background" class="form-control @error('background') is-invalid @enderror" accept="image/jpeg,image/png,image/webp">
        <div class="form-text">Zmiana tła automatycznie usunie poprzedni plik.</div>
        <x-input-error :messages="$errors->get('background')" class="mt-2" />

        @if ($page->background)
            <img src="{{ asset($page->background) }}" alt="Aktualne tło" class="mt-2 rounded img-fluid border">
        @endif
    </div>
</div>

<div class="mt-3 mb-3">
    <label for="content" class="form-label">Treść</label>
    <textarea
        id="mytextarea"
        name="content"
        rows="14"
        class="form-control @error('content') is-invalid @enderror"
    >{{ old('content', $page->content) }}</textarea>
    <x-input-error :messages="$errors->get('content')" class="mt-2" />
</div>

<div class="mb-4 form-check form-switch">
    <input
        type="checkbox"
        id="is_active"
        name="is_active"
        value="1"
        class="form-check-input"
        @checked(old('is_active', $page->is_active))
    >
    <label for="is_active" class="form-check-label">Strona aktywna</label>
</div>

<div class="gap-2 d-flex">
    <button class="btn btn-success">
        <i class="bi bi-save me-1"></i>
        Zapisz
    </button>

    <a href="{{ route('admin.pages.index') }}" class="btn btn-outline-secondary">
        Anuluj
    </a>
</div>