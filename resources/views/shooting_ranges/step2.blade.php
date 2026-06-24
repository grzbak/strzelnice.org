<x-app-layout>
    <script>
        console.log(@json($step));
    </script>



    @php
        $oldLanes = old('lanes', session('shooting_range_form.lanes', [[]]));
    @endphp

    <div class="page-bg-2"></div>
    @if (session('error'))
        <script>
            console.log(@json(session('error')));
        </script>
    @endif

    @include('components.breadcrumb')



    <div class="flex flex-col items-center ">
        <div class="container mb-4 sm:max-w-3xl mt-1 px-6 py-6 bg-white shadow-md overflow-hidden sm:rounded-lg">
            @php
    // Obliczamy procent wypełnienia
    $percent = match($step) {
        1 => 33,
        2 => 66,
        3 => 100,
    };
@endphp
{{session('error')}}
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
    <div class="d-flex justify-content-between mt-1 text-sm">
        <a href="{{ route('range.step1') }}"><span class="{{ $step >= 1 ? 'fw-bold text-primary' : 'text-muted' }}">Strzelnica</span></a>
        <a href="{{ route('range.step2') }}"><span class="{{ $step >= 2 ? 'fw-bold text-primary' : 'text-muted' }}">Osie</span></a>
        <a href="{{ route('range.step3') }}"><span class="{{ $step >= 3 ? 'fw-bold text-primary' : 'text-muted' }}">Admin</span></a>
    </div>
</div>



            <h1 class="text-xl font-semibold text-center mb-6">
            Dodaj osie strzeleckie
        </h1>

        <form method="POST" action="{{ route('range.step2') }}">
            @csrf

            <div id="lanes-wrapper" class="space-y-4">
                @foreach ($oldLanes as $i => $lane)
                    <div class="lane-item border border-2 border-secondary border-opacity-25 rounded p-3 shadow-md">
                        <div class="row g-3 align-items-end">
                            <div class="col-12 col-md-6">
                                <input name="lanes[{{ $i }}][name]" type="text" required
                                    placeholder="Nazwa osi"
                                    value="{{ $lane['name'] ?? '' }}"
                                    class="form-control">
                                @error("lanes.$i.name")
                                    <div class="text-danger text-sm mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12 col-md-3">
                                <input name="lanes[{{ $i }}][length]" type="number" min="1" required
                                    placeholder="Długość (m)"
                                    value="{{ $lane['length'] ?? '' }}"
                                    class="form-control">
                                <x-input-error :messages="$errors->get("lanes.$i.length")" class="mt-1"/>
                            </div>

                            <div class="col-12 col-md-2">
                                <input name="lanes[{{ $i }}][numberOfPositions]" type="number" min="1" required
                                    placeholder="Stanowiska"
                                    value="{{ $lane['numberOfPositions'] ?? '' }}"
                                    class="form-control">
                                <x-input-error :messages="$errors->get("lanes.$i.numberOfPositions")" class="mt-1"/>
                            </div>

                            <div class="col-12 col-md-1 text-end">
                                <button type="button"
                                        class="btn btn-outline-danger btn-sm remove-lane"
                                        title="Usuń oś">
                                    🗑
                                </button>
                            </div>
                        </div>

                        <div class="mt-3">
                            <input
                                type="hidden"
                                name="lanes[{{ $i }}][allows_dynamic_shooting]"
                                value="0"
                            >
                            <div class="form-check">
                                <input
                                    type="checkbox"
                                    name="lanes[{{ $i }}][allows_dynamic_shooting]"
                                    id="lane-dynamic-{{ $i }}"
                                    value="1"
                                    class="form-check-input"
                                    @checked((bool) ($lane['allows_dynamic_shooting'] ?? false))
                                >
                                <label class="form-check-label" for="lane-dynamic-{{ $i }}">
                                    Możliwość strzelania dynamicznego
                                </label>
                            </div>
                            <x-input-error :messages="$errors->get("lanes.$i.allows_dynamic_shooting")" class="mt-1"/>
                        </div>

                        <div class="mt-3">
                            <textarea name="lanes[{{ $i }}][description]"
                                    placeholder="Opis (opcjonalnie)"
                                    class="form-control">{{ $lane['description'] ?? '' }}</textarea>
                        </div>
                    </div>
                @endforeach
            </div>


            {{-- PRZYCISKI --}}
            <div class="flex justify-between mt-6">
                <button type="button"
                        id="add-lane"
                        class="px-4 py-2 bg-green-600 text-white rounded">
                    ➕ Dodaj oś
                </button>

                <x-primary-button>
                    Dalej
                </x-primary-button>
            </div>
        </form>
    </div>

    {{-- JS --}}
    <script>
        let laneIndex = {{ count($oldLanes) }};

        const wrapper = document.getElementById('lanes-wrapper');

        document.getElementById('add-lane').addEventListener('click', () => {
            const lane = document.createElement('div');
            lane.className = 'lane-item border border-2 border-secondary border-opacity-25 rounded p-3 shadow-md';

            lane.innerHTML = `
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-md-6">
                        <input name="lanes[${laneIndex}][name]" type="text" required
                            placeholder="Nazwa osi"
                            value="{{ old('lanes.${laneIndex}.name') }}"
                            class="form-control">
                    </div>

                    <div class="col-12 col-md-3">
                        <input name="lanes[${laneIndex}][length]" type="number" min="1" required
                            placeholder="Długość (m)"
                            value="{{ old('lanes.${laneIndex}.length') }}"
                            class="form-control">
                    </div>

                    <div class="col-12 col-md-2">
                        <input name="lanes[${laneIndex}][numberOfPositions]" type="number" min="1" required
                            placeholder="Stanowiska"
                            value="{{ old('lanes.${laneIndex}.numberOfPositions') }}"
                            class="form-control">
                    </div>

                    <div class="col-12 col-md-1 text-end">
                        <button type="button"
                                class="btn btn-outline-danger btn-sm remove-lane"
                                title="Usuń oś">
                            🗑
                        </button>
                    </div>
                </div>

                <div class="mt-3">
                    <input
                        type="hidden"
                        name="lanes[${laneIndex}][allows_dynamic_shooting]"
                        value="0"
                    >
                    <div class="form-check">
                        <input
                            type="checkbox"
                            name="lanes[${laneIndex}][allows_dynamic_shooting]"
                            id="lane-dynamic-${laneIndex}"
                            value="1"
                            class="form-check-input"
                        >
                        <label class="form-check-label" for="lane-dynamic-${laneIndex}">
                            Możliwość strzelania dynamicznego
                        </label>
                    </div>
                </div>

                <div class="mt-3">
                    <textarea name="lanes[${laneIndex}][description]"
                            placeholder="Opis (opcjonalnie)"
                            class="form-control">{{ old('lanes.${laneIndex}.description') }}</textarea>
                </div>
            `;

            wrapper.appendChild(lane);
            laneIndex++;
        });

        // USUWANIE OSI (delegacja zdarzeń)
        wrapper.addEventListener('click', (e) => {
            if (!e.target.classList.contains('remove-lane')) return;

            const lanes = document.querySelectorAll('.lane-item');

            if (lanes.length <= 1) {
                alert('Musisz dodać przynajmniej jedną oś strzelecką.');
                return;
            }

            e.target.closest('.lane-item').remove();
        });
    </script>

</x-app-layout>
