<x-app-layout>
    <script>
        console.log('link: '+ @json($step));
    </script>

    @php
        $oldRange = old('range', session('shooting_range_form.range', []));
        $oldInput = old();
    @endphp

    <div class="page-bg-2"></div>
    @if (session('error'))
        <script>
            console.error(@json(session('error')));
        </script>
    @endif



    <div class="flex flex-col items-center ">
        <div class="container px-6 py-6 mt-1 mb-4 overflow-hidden bg-white shadow-md sm:max-w-3xl sm:rounded-lg">
@php
    // Obliczamy procent wypełnienia
    $percent = match($step) {
        1 => 33,
        2 => 66,
        3 => 100,
    };
@endphp

<div class="mb-3">
    {{-- Pasek postępu --}}
    <div class="progress bg-light " style="height: 25px; border: 1px solid #888;">
        <div class="progress-bar bg-primary" role="progressbar"
             style="width: {{ $percent }}%;"
             aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100">
            {{ $percent }}%
        </div>
    </div>

    {{-- Nazwy etapów --}}
    <div class="mt-1 text-sm d-flex justify-content-between">
        <a href="{{ route('range.step1') }}"><span class="{{ $step >= 1 ? 'fw-bold text-primary' : 'text-muted' }}">Strzelnica</span></a>
        <a href="{{ route('range.step2') }}"><span class="{{ $step >= 2 ? 'fw-bold text-primary' : 'text-muted' }}">Osie</span></a>
        <a href="{{ route('range.step3') }}"><span class="{{ $step >= 3 ? 'fw-bold text-primary' : 'text-muted' }}">Admin</span></a>
    </div>
</div>



            <h1 class="w-full mb-6 text-xl font-semibold text-center">
                Dodaj nową strzelnicę
            </h1>

            <form method="POST" action="{{ route('range.step1.post') }}" enctype="multipart/form-data">
                @csrf

                <x-shooting-range-form
                    :values="session('shooting_range_form.range', [])"
                    submit-label="Przejdź do osi"
                />
            </form>
            <form method="POST" action="{{ route('range.cancel') }}">
                @csrf
                <div class="flex justify-end mt-6">
                <button class="btn btn-outline-danger">
                    Anuluj formularz
                </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
