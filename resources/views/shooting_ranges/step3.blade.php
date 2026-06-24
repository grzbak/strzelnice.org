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
            Administrator strzelnicy
        </h1>

            <form method="POST" action="{{ route('range.store') }}">
                @csrf
                    <input id="role" name="role" type="hidden" value="admin">

                <!-- Name -->
                <div>
                    <div class="flex rounded-md shadow-sm">
                        <span class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 bg-gray-100 text-gray-500">
                            <i class="bi bi-person text-lg"></i>
                        </span>

                        <input
                            id="name"
                            name="name"
                            type="text"
                            required
                            autofocus
                            autocomplete="name"
                            placeholder="{{__('Name')}}"
                            value="{{ old('name') }}"
                            class="block w-full rounded-none rounded-r-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

                <!-- Email Address -->
                <div class="mt-4">
                    <div class="flex rounded-md shadow-sm">
                        <span class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 bg-gray-100 text-gray-500">
                            <i class="bi bi-at text-lg"></i>
                        </span>

                        <input
                            id="email"
                            name="email"
                            type="email"
                            required
                            autocomplete="username"
                            placeholder="{{__('Email')}}"
                            value="{{ old('email') }}"
                            class="block w-full rounded-none rounded-r-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                        >
                    </div>

                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>


                <!-- Telephone -->
                <div class="mt-4">
                    <div class="flex rounded-md shadow-sm">
                        <span class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 bg-gray-100 text-gray-500">
                            <i class="bi bi-at text-lg"></i>
                        </span>

                        <input
                            id="telephone"
                            name="telephone"
                            type="text"
                            required
                            autocomplete="telephone"
                            placeholder="{{__('Telephone')}}"
                            value="{{ old('telephone') }}"
                            class="block w-full rounded-none rounded-r-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                        >
                    </div>

                    <x-input-error :messages="$errors->get('telephone')" class="mt-2" />
                </div>


                <!-- Password -->
                <div class="mt-4">
                    <div class="flex rounded-md shadow-sm">
                        <span class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 bg-gray-100 text-gray-500">
                            <i class="bi bi-lock text-lg"></i>
                        </span>

                        <input
                            id="password"
                            name="password"
                            type="password"
                            required
                            autocomplete="new-password"
                            placeholder="{{__('Password')}}"
                            class="block w-full rounded-none rounded-r-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                        >
                    </div>
                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>


                <!-- Confirm Password -->
                <div class="mt-4">
                    <div class="flex rounded-md shadow-sm">
                        <span class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 bg-gray-100 text-gray-500">
                            <i class="bi bi-lock text-lg"></i>
                        </span>

                        <input
                            id="password"
                            name="password_confirmation"
                            type="password"
                            required
                            autocomplete="new-password"
                            placeholder="{{__('Confirm Password')}}"
                            class="block w-full rounded-none rounded-r-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                        >
                    </div>
                    <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
                </div>


                <div class="flex items-center justify-end mt-4">

                    <x-primary-button class="ms-4">
                        {{ __('Register') }}
                    </x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
