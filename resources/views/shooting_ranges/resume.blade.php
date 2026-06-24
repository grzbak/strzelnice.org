<x-app-layout>
    <div class="container max-w-xl mt-10 bg-white shadow rounded p-6 text-center">

        <h2 class="text-xl font-semibold mb-3">
            Masz niedokończony formularz
        </h2>

        <p class="text-gray-600 mb-6">
            Wykryliśmy niedokończoną rejestrację strzelnicy.
            Czy chcesz do niej wrócić, czy zacząć od nowa?
        </p>

        <div class="flex justify-center gap-4">
            {{-- POWRÓT --}}
            <a href="{{ route('range.step' . session('shooting_range_form.step', 1)) }}"
               class="btn btn-primary">
                Kontynuuj
            </a>

            {{-- NOWY --}}
            <form method="POST" action="{{ route('range.reset') }}">
                @csrf
                <button type="submit" class="btn btn-outline-danger">
                    Zacznij od nowa
                </button>
            </form>
        </div>

    </div>
</x-app-layout>
