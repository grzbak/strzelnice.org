<x-app-layout>
    <div class="page-bg-2"></div>

    @include('components.visiting-card')

    <div class="flex flex-col items-center">
        <div class="container px-6 py-6 mt-1 mb-4 bg-white shadow-md sm:max-w-3xl sm:rounded-lg">
            <h1 class="mb-6 text-xl font-semibold text-center">Edycja strzelnicy</h1>

            @if (session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="alert alert-danger">
                    {{ session('error') }}
                </div>
            @endif

            <form method="POST" action="{{ route('shooting.update', $shootingRange) }}" enctype="multipart/form-data">
                @csrf

                <x-shooting-range-form
                    :values="$shootingRange"
                    submit-label="Zapisz strzelnicę"
                />
            </form>
        </div>
    </div>
</x-app-layout>
