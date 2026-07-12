<x-app-layout>
    <div class="page-bg-2"></div>

    <section class="container py-5">
        <div class="p-4 mx-auto bg-white shadow-sm" style="max-width: 900px;">
            <h1 class="mb-3 h3">Wymagane akceptacje</h1>
            <p class="text-muted">
                Przed dalszym korzystaniem z serwisu zaakceptuj aktualne regulaminy.
                Do czasu akceptacji możesz edytować dane konta oraz przeglądać swoje rezerwacje.
            </p>

            <form method="POST" action="{{ route('regulations.required.accept') }}">
                @csrf

                <div class="mt-4 accordion" id="requiredRegulationsAccordion">
                    @foreach ($regulations as $regulation)
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button {{ $loop->first ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#regulation-{{ $regulation->id }}">
                                    {{ $regulation->name }}
                                </button>
                            </h2>
                            <div id="regulation-{{ $regulation->id }}" class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}" data-bs-parent="#requiredRegulationsAccordion">
                                <div class="accordion-body">
                                    <div class="mb-3 regulation-content">
                                        {!! $regulation->currentVersion->content !!}
                                    </div>

                                    <label class="p-3 border rounded d-flex gap-2 align-items-start">
                                        <input type="checkbox" name="accepted[]" value="{{ $regulation->id }}" class="mt-1 form-check-input">
                                        <span>
                                            Oświadczam, że zapoznałem/am się z treścią regulaminu „{{ $regulation->name }}” i akceptuję jego aktualną wersję.
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <x-input-error :messages="$errors->get('accepted')" class="mt-3" />

                <button class="mt-4 btn btn-danger">
                    <i class="bi bi-check2-circle me-1"></i>
                    Akceptuję wymagane regulaminy
                </button>
            </form>
        </div>
    </section>
</x-app-layout>
