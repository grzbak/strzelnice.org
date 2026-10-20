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

<div class="p-3 mb-3 border rounded bg-light">
    <h2 class="mb-3 h5">SEO</h2>

    <div class="row g-3">
        <div class="col-md-6">
            <label for="meta_title" class="form-label">Tytuł meta</label>
            <input type="text" id="meta_title" name="meta_title" class="form-control @error('meta_title') is-invalid @enderror" value="{{ old('meta_title', $page->meta_title) }}" maxlength="255">
            <div class="form-text">Jeśli puste, użyta zostanie nazwa wyświetlana strony.</div>
            <x-input-error :messages="$errors->get('meta_title')" class="mt-2" />
        </div>

        <div class="col-md-6">
            <label for="robots" class="form-label">Robots</label>
            <select id="robots" name="robots" class="form-select @error('robots') is-invalid @enderror">
                @foreach (['index,follow', 'noindex,follow', 'index,nofollow', 'noindex,nofollow'] as $robotsOption)
                    <option value="{{ $robotsOption }}" @selected(old('robots', $page->robots ?? 'index,follow') === $robotsOption)>
                        {{ $robotsOption }}
                    </option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('robots')" class="mt-2" />
        </div>

        <div class="col-12">
            <label for="meta_description" class="form-label">Opis meta</label>
            <textarea id="meta_description" name="meta_description" rows="2" class="form-control @error('meta_description') is-invalid @enderror" maxlength="500">{{ old('meta_description', $page->meta_description) }}</textarea>
            <div class="form-text">Zalecana długość: około 140-160 znaków.</div>
            <x-input-error :messages="$errors->get('meta_description')" class="mt-2" />
        </div>

        <div class="col-12">
            <label for="meta_keywords" class="form-label">Słowa kluczowe</label>
            <textarea id="meta_keywords" name="meta_keywords" rows="2" class="form-control @error('meta_keywords') is-invalid @enderror" maxlength="500">{{ old('meta_keywords', $page->meta_keywords) }}</textarea>
            <x-input-error :messages="$errors->get('meta_keywords')" class="mt-2" />
        </div>

        <div class="col-md-6">
            <label for="og_title" class="form-label">Tytuł Open Graph</label>
            <input type="text" id="og_title" name="og_title" class="form-control @error('og_title') is-invalid @enderror" value="{{ old('og_title', $page->og_title) }}" maxlength="255">
            <x-input-error :messages="$errors->get('og_title')" class="mt-2" />
        </div>

        <div class="col-md-6">
            <label for="og_image" class="form-label">Obraz Open Graph</label>
            <input type="text" id="og_image" name="og_image" class="form-control @error('og_image') is-invalid @enderror" value="{{ old('og_image', $page->og_image) }}" maxlength="255" placeholder="img/pages/example.jpg lub https://...">
            <div class="form-text">Jeśli puste, użyty zostanie baner lub tło strony.</div>
            <x-input-error :messages="$errors->get('og_image')" class="mt-2" />
        </div>

        <div class="col-12">
            <label for="og_description" class="form-label">Opis Open Graph</label>
            <textarea id="og_description" name="og_description" rows="2" class="form-control @error('og_description') is-invalid @enderror" maxlength="500">{{ old('og_description', $page->og_description) }}</textarea>
            <x-input-error :messages="$errors->get('og_description')" class="mt-2" />
        </div>

        <div class="col-12">
            <label for="canonical_url" class="form-label">Adres canonical</label>
            <input type="url" id="canonical_url" name="canonical_url" class="form-control @error('canonical_url') is-invalid @enderror" value="{{ old('canonical_url', $page->canonical_url) }}" maxlength="255">
            <div class="form-text">Pozostaw puste, aby system użył publicznego adresu strony.</div>
            <x-input-error :messages="$errors->get('canonical_url')" class="mt-2" />
        </div>
    </div>
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
