<x-app-layout>
    <div class="page-bg-2"></div>
    <div class="container py-4">











        @php
            $canUpload = ! $gallery
                || $gallery->allow_upload
                || auth()->user()->role === 'superadmin';

            $usedMb = $gallery
                ? $gallery->used_bytes / 1024 / 1024
                : 0;

            $capacityMb = $gallery
                ? $gallery->capacity_bytes / 1024 / 1024
                : 50;

            $usedPercent = $gallery && $gallery->capacity_bytes > 0
                ? min(100, ($gallery->used_bytes / $gallery->capacity_bytes) * 100)
                : 0;

            $progressColor = match (true) {
                $usedPercent >= 90 => 'bg-danger',
                $usedPercent >= 70 => 'bg-warning',
                default => 'bg-success',
            };
        @endphp

        <section class="mb-5 overflow-hidden bg-white border rounded shadow-sm">
            <header class="p-4 text-white bg-secondary">
                <div class="gap-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                    <div>
                        <div class="gap-2 mb-2 d-flex align-items-center">
                            <i class="bi bi-images fs-4"></i>
                            <span class="opacity-75 text-uppercase small">
                                Zarządzanie galerią
                            </span>
                        </div>

                        <h1 class="mb-0 h3">
                            {{ $shootingRange->name }}
                        </h1>
                    </div>

                    <div class="gap-2 d-flex">
                        <button
                            type="button"
                            class="btn btn-success"
                            data-bs-toggle="modal"
                            data-bs-target="#uploadPhotosModal"
                            title="Dodaj zdjęcia"
                            @disabled(! $canUpload)
                        >
                            <i class="bi bi-images me-1"></i>
                            <i class="bi bi-plus-lg"></i>
                            <span class="ms-1">Dodaj zdjęcia</span>
                        </button>

                        @if ($gallery && auth()->user()->role === 'superadmin')
                            <button
                                type="button"
                                class="btn btn-outline-light"
                                data-bs-toggle="modal"
                                data-bs-target="#gallerySettingsModal"
                                title="Ustawienia galerii"
                            >
                                <i class="bi bi-gear-fill"></i>
                            </button>
                        @endif
                    </div>
                </div>
            </header>

            <div class="p-4">
                <div class="gap-3 d-flex justify-content-between align-items-end">
                    <div>
                        <div class="mb-1 text-muted small">
                            Wykorzystana powierzchnia
                        </div>

                        <strong class="fs-5">
                            {{ number_format($usedMb, 2) }} MB
                        </strong>

                        <span class="text-muted">
                            z {{ number_format($capacityMb, 2) }} MB
                        </span>
                    </div>

                    <span class="badge {{ $usedPercent >= 90 ? 'bg-danger' : 'bg-secondary' }}">
                        {{ number_format($usedPercent, 1) }}%
                    </span>
                </div>

                <div
                    class="mt-3 progress"
                    style="height: 10px;"
                    role="progressbar"
                    aria-valuenow="{{ $usedPercent }}"
                    aria-valuemin="0"
                    aria-valuemax="100"
                >
                    <div
                        class="progress-bar {{ $progressColor }}"
                        style="width: {{ $usedPercent }}%;"
                    ></div>
                </div>

                @if (! $canUpload)
                    <div class="mt-3 mb-0 alert alert-warning">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        Dodawanie zdjęć do galerii zostało wyłączone.
                    </div>
                @endif
            </div>
        </section>

        <x-session-alerts />

        <div class="p-3 mb-4 bg-white rounded border-bottom">
            <h2 class="mb-1 h4">
                <i class="bi bi-grid-3x3-gap-fill me-1"></i>
                Zdjęcia
            </h2>

            <span class="text-muted small">
                {{ $gallery?->photos->count() ?? 0 }} zdjęć w galerii
            </span>
        </div>














        @if ($gallery)
            <div class="pb-2 bg-white rounded row g-3">
                @forelse ($gallery->photos as $photo)
                    <div class="col-md-3">
                        <div class="card h-100 gallery-photo-card">
                            <img
                                src="{{ $photo->full_url }}"
                                class="card-img-top"
                                alt="{{ $photo->original_name }}"
                                style="height: 220px; object-fit: cover;"
                            >

                            <div class="card-body">
                                <form
                                    method="POST"
                                    action="{{ route('shooting.gallery.photos.update', [$shootingRange, $photo]) }}"
                                >
                                    @csrf
                                    @method('PATCH')

                                    <textarea
                                        name="description"
                                        class="form-control"
                                        rows="3"
                                        maxlength="2000"
                                        placeholder="Opis zdjęcia"
                                    >{{ $photo->description }}</textarea>

                                    <div class="gap-2 mt-2 d-flex">
                                        <button
                                            type="submit"
                                            class="btn btn-primary btn-sm"
                                            title="Zapisz opis"
                                        >
                                            <i class="bi bi-floppy-fill"></i>
                                        </button>

                                        @if ($gallery->allow_delete || auth()->user()->role === 'superadmin')
                                            <button
                                                type="button"
                                                form="delete-photo-{{ $photo->id }}"
                                                class="btn btn-outline-danger btn-sm gallery-delete-photo-button"
                                                data-bs-toggle="tooltip"
                                                data-bs-title="Usuń zdjęcie"
                                                data-message="Czy na pewno usunąć zdjęcie: {{ $photo->original_name }}?"
                                                aria-label="Usuń zdjęcie"
                                            >
                                                <i class="bi bi-trash-fill"></i>
                                            </button>
                                        @endif
                                    </div>
                                </form>

                                @if ($gallery->allow_delete || auth()->user()->role === 'superadmin')
                                    <form
                                        id="delete-photo-{{ $photo->id }}"
                                        method="POST"
                                        action="{{ route('shooting.gallery.photos.destroy', [$shootingRange, $photo]) }}"
                                        class="d-none"
                                    >
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12">
                        <div class="alert alert-info">
                            Galeria nie zawiera jeszcze zdjęć.
                        </div>
                    </div>
                @endforelse
            </div>
        @else
            <div class="alert alert-info">
                Galeria zostanie utworzona po dodaniu pierwszego zdjęcia.
            </div>
        @endif
    </div>

    {{-- Modal dodawania zdjęć --}}
    <div class="modal fade" id="uploadPhotosModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form
                method="POST"
                action="{{ route('shooting.gallery.photos.store', $shootingRange) }}"
                enctype="multipart/form-data"
                class="modal-content"
            >
                @csrf

                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-images me-1"></i>
                        Dodaj zdjęcia
                    </h5>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Zamknij"
                    ></button>
                </div>

                <div class="modal-body">
                    <label for="galleryPhotos" class="form-label">
                        Wybierz zdjęcia
                    </label>

                    <input
                        type="file"
                        name="photos[]"
                        id="galleryPhotos"
                        class="form-control {{ $errors->has('photos') || $errors->has('photos.*') ? 'is-invalid' : '' }}"
                        accept=".jpg,.jpeg,.png"
                        multiple
                        required
                    >

                    @error('photos')
                        <div class="mt-1 text-danger small">
                            {{ $message }}
                        </div>
                    @enderror

                    @foreach ($errors->get('photos.*') as $photoErrors)
                        @foreach ($photoErrors as $message)
                            <div class="mt-1 text-danger small">
                                {{ $message }}
                            </div>
                        @endforeach
                    @endforeach

                    <div class="form-text">
                        Dozwolone formaty: JPG, JPEG, PNG. Maksymalnie 1 MB na zdjęcie.
                    </div>
                </div>

                <div class="modal-footer">
                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal"
                    >
                        Anuluj
                    </button>

                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-upload me-1"></i>
                        Dodaj
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal ustawień galerii --}}
    @if ($gallery && auth()->user()->role === 'superadmin')
        <div class="modal fade" id="gallerySettingsModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form
                    method="POST"
                    action="{{ route('shooting.gallery.settings.update', $shootingRange) }}"
                    class="modal-content"
                >
                    @csrf
                    @method('PATCH')

                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-gear-fill me-1"></i>
                            Ustawienia galerii
                        </h5>

                        <button
                            type="button"
                            class="btn-close"
                            data-bs-dismiss="modal"
                            aria-label="Zamknij"
                        ></button>
                    </div>

                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="capacityMb" class="form-label">
                                Pojemność galerii [MB]
                            </label>

                            <input
                                type="number"
                                name="capacity_mb"
                                id="capacityMb"
                                class="form-control @error('capacity_mb') is-invalid @enderror"
                                min="1"
                                value="{{ old('capacity_mb', round($gallery->capacity_bytes / 1024 / 1024)) }}"
                                required
                            >

                            @error('capacity_mb')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>

                        <div class="mb-3 form-check form-switch">
                            <input type="hidden" name="is_visible" value="0">

                            <input
                                type="checkbox"
                                name="is_visible"
                                id="galleryVisible"
                                value="1"
                                class="form-check-input"
                                @checked(old('is_visible', $gallery->is_visible))
                            >

                            <label class="form-check-label" for="galleryVisible">
                                Galeria widoczna na stronie
                            </label>
                        </div>

                        <div class="mb-3 form-check form-switch">
                            <input type="hidden" name="allow_upload" value="0">

                            <input
                                type="checkbox"
                                name="allow_upload"
                                id="galleryAllowUpload"
                                value="1"
                                class="form-check-input"
                                @checked(old('allow_upload', $gallery->allow_upload))
                            >

                            <label class="form-check-label" for="galleryAllowUpload">
                                Zezwalaj na dodawanie zdjęć
                            </label>
                        </div>

                        <div class="form-check form-switch">
                            <input type="hidden" name="allow_delete" value="0">

                            <input
                                type="checkbox"
                                name="allow_delete"
                                id="galleryAllowDelete"
                                value="1"
                                class="form-check-input"
                                @checked(old('allow_delete', $gallery->allow_delete))
                            >

                            <label class="form-check-label" for="galleryAllowDelete">
                                Zezwalaj na usuwanie zdjęć
                            </label>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button
                            type="button"
                            class="btn btn-secondary"
                            data-bs-dismiss="modal"
                        >
                            Anuluj
                        </button>

                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-floppy-fill me-1"></i>
                            Zapisz
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <div class="modal fade" id="deletePhotoModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h5">Usuń zdjęcie</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0" data-delete-photo-message></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-dark" data-bs-dismiss="modal">Anuluj</button>
                    <button type="button" class="btn btn-danger" data-delete-photo-confirm>
                        <i class="bi bi-trash3 me-1" aria-hidden="true"></i>
                        Usuń
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            @if ($errors->has('photos') || $errors->has('photos.*'))
                bootstrap.Modal
                    .getOrCreateInstance(document.getElementById('uploadPhotosModal'))
                    .show();
            @elseif ($errors->has('capacity_mb'))
                const settingsModal = document.getElementById('gallerySettingsModal');

                if (settingsModal) {
                    bootstrap.Modal
                        .getOrCreateInstance(settingsModal)
                        .show();
                }
            @endif

            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(element => {
                bootstrap.Tooltip.getOrCreateInstance(element);
            });

            document.addEventListener('click', event => {
                const button = event.target.closest('.gallery-delete-photo-button');

                if (!button) {
                    return;
                }

                const modalElement = document.getElementById('deletePhotoModal');
                const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
                const message = modalElement.querySelector('[data-delete-photo-message]');
                const confirmButton = modalElement.querySelector('[data-delete-photo-confirm]');
                const form = document.getElementById(button.getAttribute('form'));

                message.textContent = button.dataset.message || 'Czy na pewno usunąć zdjęcie?';
                confirmButton.onclick = () => {
                    modal.hide();
                    form.submit();
                };

                modal.show();
            });
        });
    </script>

    <style>
        .gallery-photo-card {
            border: 1px solid rgba(52, 58, 64, .12);
            box-shadow: 0 .75rem 1.5rem rgba(15, 23, 42, .08);
        }

        .gallery-photo-card .card-body {
            background: #fff;
        }
    </style>
</x-app-layout>
