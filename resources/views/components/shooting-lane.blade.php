<div class="col">
    <div class="overflow-hidden shadow-lg card card-cover h-100 rounded-4"
        style="background-image: url(&quot;unsplash-photo-3.jpg&quot;)">
        <div class="p-5 pb-3 d-flex flex-column h-100 text-shadow-1">

            <form id="formDeleteLane_{{ $lane->id }}"
                action="{{ route('shooting.lanes.destroy', ['id' => $lane->id]) }}" method="POST"
                enctype="multipart/form-data">
                {{ csrf_field() }}
                @method('DELETE')
            </form>

            <h3 class="mb-2 lh-1 fw-bold">
                {{ $lane->name }}
            </h3>

            <h5 class="mb-4 lh-1">
                {{ $lane->description }}
            </h5>

            @if ($lane->allows_dynamic_shooting)
                <div class="mb-3">
                    <span class="badge bg-success">
                        <i class="bi bi-lightning-charge-fill me-1"></i>
                        Strzelanie dynamiczne
                    </span>
                </div>
            @endif

            <ul class="mt-auto d-flex list-unstyled">
                <li class="me-auto">
                    <i class="bi bi-rulers fw-bold"></i>
                    <small>{{ $lane->length }} m</small>
                </li>
                <li class="me-auto">
                    <i class="bi bi-arrows-move fw-bold"></i>
                    <small>{{ $lane->numberOfPositions }} pozycji</small>
                </li>
                <li class="me-auto">
                    <i class="bi bi-calendar-range fw-bold"></i>
                    <small></small>
                </li>
                <li class="me-auto">
                    <a href="#" class="btn btn-sm" x-data
                        x-on:click="
                            document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
                            document.querySelectorAll('.invalid-feedback').forEach(el => el.remove());
                            $dispatch('open-modal', 'confirm-editLane-{{ $lane->id }}')"
                        title="Edytuj oś!
                        ">
                        <i class="bi bi-pencil fw-bold"></i>
                    </a>
                    <small></small>
                </li>
                @if ($permissionDelete)
                    <li class="me-auto">
                        <a href="#" class="btn btn-sm" x-data
                            x-on:click="$dispatch('open-modal', 'confirm-delete-{{ $lane->id }}')" title="Usuń oś!">
                            <i class="bi bi-trash"></i>
                            <small></small>
                        </a>
                    </li>
                @endif
            </ul>
        </div>
    </div>
</div>





@push('modals')
    <x-modal name="confirm-delete-{{ $lane->id }}">
        <div class="p-6">
            <h2 class="text-lg font-semibold text-gray-900">
                Potwierdzenie usunięcia
            </h2>

            <p class="mt-4 text-sm text-gray-600">
                Czy na pewno chcesz usunąć tą oś strzelecką?
                Tej operacji nie można cofnąć.
            </p>

            <div class="flex justify-end gap-3 mt-6">
                <!-- Anuluj -->
                <button type="button" class="px-4 py-2 bg-gray-300 rounded"
                    x-on:click="$dispatch('close-modal', 'confirm-delete-{{ $lane->id }}')">
                    Anuluj
                </button>

                <!-- Formularz DELETE -->
                <form method="POST" action="{{ route('shooting.lanes.destroy', $lane->id) }}">
                    @csrf
                    @method('DELETE')

                    <button type="submit" class="px-4 py-2 text-white bg-red-600 rounded">
                        Usuń
                    </button>
                </form>
            </div>
        </div>
    </x-modal>

    <x-modal name="confirm-editLane-{{ $lane->id }}" x-teleport="body">
        <div class="p-6">
            <h2 class="text-lg font-semibold text-gray-900">
                Edycja osi
            </h2>
            <form method="POST" action="{{ route('shooting.lanes.update', $lane->id) }}">
                @csrf
                <x-form-lanes name="confirm-EditLane" :lane="$lane">
                </x-form-lanes>
            </form>
        </div>
    </x-modal>
@endpush
